<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/patient_auth.php'; // For notification functions
require_once __DIR__ . '/process_notifications.php'; // For email queue processing
requireAdmin();

// Admin can create appointments

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    $appointment_date = isset($_POST['appointment_date']) ? sanitize($_POST['appointment_date']) : '';
    $appointment_time = isset($_POST['appointment_time']) ? sanitize($_POST['appointment_time']) : '';
    $reason = isset($_POST['reason']) ? sanitize($_POST['reason']) : '';
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    $status = isset($_POST['status']) ? strtolower(trim(sanitize($_POST['status']))) : 'scheduled';
    
    // Validate required fields
    if (!$patient_id || !$doctor_id || !$appointment_date || !$appointment_time) {
        $_SESSION['error'] = "All required fields must be filled.";
        header('Location: doctors.php?doctor_id=' . $doctor_id . '&tab=appointments');
        exit;
    }
    
    // Validate date is not in the past
    if ($appointment_date < date('Y-m-d')) {
        $_SESSION['error'] = "Appointment date cannot be in the past.";
        header('Location: doctors.php?doctor_id=' . $doctor_id . '&tab=appointments');
        exit;
    }
    
    // Check if patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $_SESSION['error'] = "Selected patient does not exist.";
        header('Location: doctors.php?doctor_id=' . $doctor_id . '&tab=appointments');
        exit;
    }
    
    // Check if doctor exists and get the doctors table ID
    // Note: $doctor_id from form is actually a user_id, but appointments table needs doctors.id
    $stmt = $conn->prepare("SELECT d.id as doctor_table_id FROM doctors d 
                           INNER JOIN users u ON d.user_id = u.id 
                           WHERE u.id = ? AND u.role = 'Doctor'");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $doctor_result = $stmt->get_result();
    if ($doctor_result->num_rows === 0) {
        $_SESSION['error'] = "Selected doctor does not exist.";
        header('Location: doctors.php?doctor_id=' . $doctor_id . '&tab=appointments');
        exit;
    }
    $doctor_data = $doctor_result->fetch_assoc();
    $doctor_table_id = $doctor_data['doctor_table_id'];
    
    // Check if doctor is on leave for this date - use doctor_table_id (doctors.id) not user_id
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM doctor_leaves 
                           WHERE doctor_id = ? AND status = 'Active' 
                           AND ? BETWEEN start_date AND end_date");
    $stmt->bind_param("is", $doctor_table_id, $appointment_date);
    $stmt->execute();
    $leave_result = $stmt->get_result();
    $is_on_leave = $leave_result->fetch_assoc()['count'] > 0;
    $stmt->close();
    
    if ($is_on_leave) {
        $_SESSION['error'] = "Doctor is on leave during this period. Please select another date.";
        header('Location: doctors.php?doctor_id=' . $doctor_id . '&tab=appointments');
        exit;
    }
    
    // Check for conflicting appointments - use exact ENUM values (case-sensitive)
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
    $stmt->bind_param("iss", $doctor_table_id, $appointment_date, $appointment_time);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "There is already an appointment scheduled at this time.";
        header('Location: doctors.php?doctor_id=' . $doctor_id . '&tab=appointments');
        exit;
    }
    
    // Insert new appointment
    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $patient_id, $doctor_table_id, $appointment_date, $appointment_time, $reason, $notes, $status);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Appointment scheduled successfully.";
        $appointment_id = $conn->insert_id;

        // Get patient and doctor details for notifications
        $stmt = $conn->prepare("SELECT p.id as patient_id, p.first_name as patient_first_name, p.last_name as patient_last_name, p.email as patient_email,
                                d.user_id as doctor_user_id,
                                u.first_name as doctor_first_name, u.last_name as doctor_last_name
                                FROM patients p
                                CROSS JOIN doctors d ON d.id = ?
                                CROSS JOIN users u ON u.id = d.user_id
                                WHERE p.id = ?");
        $stmt->bind_param("ii", $doctor_table_id, $patient_id);
        $stmt->execute();
        $details_result = $stmt->get_result();
        
        if ($details_result->num_rows > 0) {
            $details = $details_result->fetch_assoc();
            $patient_name = trim(($details['patient_first_name'] ?? '') . ' ' . ($details['patient_last_name'] ?? ''));
            $doctor_name = trim(($details['doctor_first_name'] ?? '') . ' ' . ($details['doctor_last_name'] ?? ''));
            $patient_email = $details['patient_email'] ?? '';
            $doctor_user_id = $details['doctor_user_id'] ?? null;
            
            // Format appointment details
            $formatted_date = date('l, F j, Y', strtotime($appointment_date));
            $formatted_time = date('g:i A', strtotime($appointment_time));
            
            // Get patient user ID if exists
            $patient_user_id = null;
            $patient_user_stmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ? LIMIT 1");
            $patient_user_stmt->bind_param("i", $patient_id);
            $patient_user_stmt->execute();
            $patient_user_result = $patient_user_stmt->get_result();
            if ($patient_user_result->num_rows > 0) {
                $patient_user_row = $patient_user_result->fetch_assoc();
                $patient_user_id = $patient_user_row['id'];
            }
            $patient_user_stmt->close();
            
            // Send email notification to patient (if email exists)
            if (!empty($patient_email) && function_exists('sendEmailNotification')) {
                $email_subject = "Appointment Scheduled - Mhavis Medical Center";
                
                $email_body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                    <div style='background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                        <h2 style='margin: 0;'>✅ Appointment Scheduled!</h2>
                    </div>
                    <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                        <p style='font-size: 16px; color: #333;'>Dear <strong>{$patient_name}</strong>,</p>
                        
                        <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                            Your appointment has been successfully scheduled by our administration team.
                        </p>
                        
                        <div style='background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;'>
                            <h3 style='margin-top: 0; color: #333;'>📅 Appointment Details</h3>
                            <p style='margin: 10px 0;'><strong>Doctor:</strong> Dr. {$doctor_name}</p>
                            <p style='margin: 10px 0;'><strong>Date:</strong> {$formatted_date}</p>
                            <p style='margin: 10px 0;'><strong>Time:</strong> {$formatted_time}</p>
                            " . (!empty($reason) ? "<p style='margin: 10px 0;'><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : "") . "
                        </div>
                        
                        <div style='background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;'>
                            <p style='margin: 0; font-size: 14px; color: #856404;'>
                                ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.
                            </p>
                        </div>
                        
                        <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                            If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.
                        </p>
                        
                        <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                        
                        <p style='font-size: 13px; color: #777; text-align: center; margin: 0;'>
                            Best regards,<br>
                            <strong>Mhavis Medical & Diagnostic Center</strong><br>
                            Healthcare Team
                        </p>
                    </div>
                </div>
                ";
                
                sendEmailNotification($patient_email, $patient_name, $email_subject, $email_body, 'html');
                
                // Process email queue immediately
                if (function_exists('processEmailQueue')) {
                    processEmailQueue();
                }
            }
            
            // Create in-app notification for patient
            if ($patient_user_id && function_exists('createNotification')) {
                $patient_title = "Appointment Scheduled";
                $patient_message = "Your appointment with Dr. {$doctor_name} has been scheduled for {$formatted_date} at {$formatted_time}.";
                createNotification('Patient', $patient_user_id, 'Appointment_Scheduled', $patient_title, $patient_message, 'System');
            }
            
            // Create in-app notification for doctor
            if ($doctor_user_id && function_exists('createNotification')) {
                $doctor_title = 'New Appointment Assigned';
                $doctor_message = "You have a new appointment.\n\n" .
                               "Patient: " . $patient_name . "\n" .
                               "Date: " . $formatted_date . "\n" .
                               "Time: " . $formatted_time . "\n\n" .
                               "This appointment was scheduled by an administrator.";
                createNotification('Doctor', $doctor_user_id, 'New_Assigned_Patient', $doctor_title, $doctor_message, 'System');
            }
            
            // Create in-app notification for admin (the one who made the booking)
            if (isset($_SESSION['user_id']) && isAdmin() && function_exists('createNotification')) {
                $admin_title = "Appointment Booked";
                $admin_message = "You have successfully booked an appointment for {$patient_name} with Dr. {$doctor_name} on {$formatted_date} at {$formatted_time}.";
                createNotification('Admin', $_SESSION['user_id'], 'Appointment_Booked', $admin_title, $admin_message, 'System');
            }
        }
    } else {
        $_SESSION['error'] = "Error scheduling appointment.";
    }
    
    header('Location: doctors.php?doctor_id=' . $doctor_id . '&tab=appointments');
    exit;
} else {
    // If not POST request, redirect to doctors page
    header('Location: doctors.php');
    exit;
}
?>
