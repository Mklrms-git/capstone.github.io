<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/patient_auth.php';
requireDoctor();

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $decision = isset($_POST['decision']) ? strtolower(trim($_POST['decision'])) : '';

    if (!$appointment_id || !in_array($decision, ['approved', 'declined'])) {
        $_SESSION['error'] = 'Invalid request.';
        header('Location: doctor_dashboard.php');
        exit;
    }

    // Ensure the appointment belongs to this doctor and get appointment details
    $doctorUserId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT a.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                             pu.email, pu.phone
                             FROM appointments a
                             JOIN doctors d ON a.doctor_id = d.id
                             JOIN users u ON d.user_id = u.id
                             LEFT JOIN patient_users pu ON a.patient_id = pu.id
                             WHERE a.id = ? AND d.user_id = ?");
    $stmt->bind_param('ii', $appointment_id, $doctorUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $_SESSION['error'] = 'You are not authorized to act on this appointment.';
        header('Location: doctor_dashboard.php');
        exit;
    }
    
    $appointment = $result->fetch_assoc();
    // Use valid ENUM values (case-sensitive): 'scheduled', 'ongoing', 'settled', 'cancelled'
    $newStatus = $decision === 'approved' ? 'scheduled' : 'cancelled';

    $upd = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $upd->bind_param('si', $newStatus, $appointment_id);
    if ($upd->execute() && $upd->affected_rows > 0) {
        $_SESSION['success'] = $decision === 'approved' ? 'Appointment approved.' : 'Appointment declined.';
        
        // Send notifications to patient if they have a patient_users account
        if ($appointment['patient_id']) {
            if ($decision === 'approved') {
                // Create System notification for approval
                createNotification('Patient', $appointment['patient_id'], 'Appointment_Approved', 
                    'Appointment Confirmed', 
                    "Your appointment has been confirmed by Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']}.\n\n" .
                    "Date: " . date('M j, Y', strtotime($appointment['appointment_date'])) . "\n" .
                    "Time: " . date('g:i A', strtotime($appointment['appointment_time'])) . "\n\n" .
                    "Please arrive 15 minutes early for your appointment.", 
                    'System');
                
                // Send email notification if email is available
                if ($appointment['email']) {
                    $subject = "Appointment Confirmed - Mhavis Medical Center";
                    $body = "Dear Patient,\n\n" .
                           "Your appointment has been confirmed!\n\n" .
                           "Doctor: Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']}\n" .
                           "Date: " . date('M j, Y', strtotime($appointment['appointment_date'])) . "\n" .
                           "Time: " . date('g:i A', strtotime($appointment['appointment_time'])) . "\n\n" .
                           "Please arrive 15 minutes early for your appointment.\n\n" .
                           "Best regards,\nMhavis Medical & Diagnostic Center";
                    sendEmailNotification($appointment['email'], 'Patient', $subject, $body, 'text');
                    
                    // Create email tracking notification
                    createNotification('Patient', $appointment['patient_id'], 'Appointment_Approved', 
                        'Appointment Confirmed', 
                        "Your appointment with Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']} has been confirmed for " . 
                        date('M j, Y', strtotime($appointment['appointment_date'])) . " at " . date('g:i A', strtotime($appointment['appointment_time'])) . ".", 
                        'Email');
                }
            } else {
                // Create System notification for decline
                createNotification('Patient', $appointment['patient_id'], 'Appointment_Rejected', 
                    'Appointment Declined', 
                    "Unfortunately, your appointment request has been declined by Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']}.\n\n" .
                    "Please contact us or submit a new appointment request with different preferences.", 
                    'System');
                
                // Send email notification if email is available
                if ($appointment['email']) {
                    $subject = "Appointment Status Update - Mhavis Medical Center";
                    $body = "Dear Patient,\n\n" .
                           "We regret to inform you that your appointment request has been declined.\n\n" .
                           "Please feel free to submit a new request with different preferences or contact us for assistance.\n\n" .
                           "Best regards,\nMhavis Medical & Diagnostic Center";
                    sendEmailNotification($appointment['email'], 'Patient', $subject, $body, 'text');
                    
                    // Create email tracking notification
                    createNotification('Patient', $appointment['patient_id'], 'Appointment_Rejected', 
                        'Appointment Declined', 
                        'Your appointment request has been declined. Please contact us or submit a new request.', 
                        'Email');
                }
            }
        }
    } else {
        $_SESSION['error'] = 'Failed to update appointment.';
    }

    header('Location: doctor_dashboard.php');
    exit;
}

header('Location: doctor_dashboard.php');
exit;
?>


