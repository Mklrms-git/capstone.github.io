<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/auth.php';
require_once 'config/patient_auth.php'; // For notification functions
require_once 'process_notifications.php'; // For email queue processing
$page_title = "Appointment Requests";
$active_page = "appointment-requests";

// Require admin login
requireRole('Admin');

$conn = getDBConnection();
$message = '';
$error = '';

// Automatic cleanup of logs history (runs automatically when admin accesses this page)
try {
    require_once __DIR__ . '/config/cleanup_helper.php';
    runAutomaticCleanup($conn, true); // Silent mode - won't break page if cleanup fails
} catch (Exception $e) {
    // Don't break the page if cleanup fails, just log it
    error_log("Automatic cleanup error in admin_appointment_requests.php: " . $e->getMessage());
}

// Handle appointment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Return JSON response for AJAX requests
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['request_id'] ?? '';
    $admin_notes = $_POST['admin_notes'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    
    // Validation
    if (!$action) {
        echo json_encode(['success' => false, 'error' => 'No action specified']);
        exit();
    }
    
    if (!$request_id) {
        echo json_encode(['success' => false, 'error' => 'No request ID specified']);
        exit();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User session not found. Please log in again.']);
        exit();
    }
    
    if ($action && $request_id) {
        $stmt = $conn->prepare("SELECT ar.*, p.email, p.phone, p.id as patient_id, p.first_name, p.last_name, u.first_name as doctor_first_name, u.last_name as doctor_last_name
                               FROM appointment_requests ar
                               JOIN patient_users pu ON ar.patient_user_id = pu.id
                               JOIN patients p ON pu.patient_id = p.id
                               JOIN users u ON ar.doctor_id = u.id
                               WHERE ar.id = ? AND ar.status = 'Pending'");
        
        $request = null;
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
            error_log("Admin appointment request query preparation error: " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database preparation error: ' . $conn->error]);
            exit();
        } else {
            $stmt->bind_param("i", $request_id);
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'error' => 'Database execution error: ' . $stmt->error]);
                exit();
            }
            $request = $stmt->get_result()->fetch_assoc();
        }
        
        if ($request) {
            if ($action === 'approve') {
                // Create appointment
                $final_date = $appointment_date ?: $request['preferred_date'];
                $final_time = $appointment_time ?: $request['preferred_time'];
                
                // Validate date and time
                if (empty($final_date) || empty($final_time)) {
                    echo json_encode(['success' => false, 'error' => 'Appointment date and time are required']);
                    exit();
                }
                
                // Get the doctor's ID from the doctors table using the user_id
                // appointment_requests.doctor_id is actually a user_id, but appointments.doctor_id needs doctors.id
                $doctor_lookup = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
                if (!$doctor_lookup) {
                    echo json_encode(['success' => false, 'error' => 'Failed to prepare doctor lookup: ' . $conn->error]);
                    exit();
                }
                
                $doctor_lookup->bind_param("i", $request['doctor_id']);
                if (!$doctor_lookup->execute()) {
                    echo json_encode(['success' => false, 'error' => 'Failed to execute doctor lookup: ' . $doctor_lookup->error]);
                    exit();
                }
                
                $doctor_result = $doctor_lookup->get_result()->fetch_assoc();
                if (!$doctor_result) {
                    echo json_encode(['success' => false, 'error' => 'Doctor not found in the system. The doctor may have been removed. Please contact the administrator.']);
                    exit();
                }
                
                $doctor_table_id = $doctor_result['id'];
                
                $stmt = $conn->prepare("INSERT INTO appointments 
                    (patient_id, doctor_id, appointment_date, appointment_time, status, reason, notes) 
                    VALUES (?, ?, ?, ?, 'scheduled', ?, ?)");
                
                if (!$stmt) {
                    echo json_encode(['success' => false, 'error' => 'Failed to prepare appointment insert: ' . $conn->error]);
                    exit();
                }
                
                $notes = "Approved by admin. " . ($admin_notes ? "Notes: " . $admin_notes : "");
                $stmt->bind_param("iissss", $request['patient_id'], $doctor_table_id, 
                                 $final_date, $final_time, $request['reason'], $notes);
                
                if ($stmt->execute()) {
                    $appointment_id = $conn->insert_id;
                    
                    // Update request status
                    $stmt = $conn->prepare("UPDATE appointment_requests 
                        SET status = 'Approved', approved_by = ?, approved_at = NOW(), admin_notes = ?, appointment_id = ? 
                        WHERE id = ?");
                    
                    if (!$stmt) {
                        echo json_encode(['success' => false, 'error' => 'Failed to prepare status update: ' . $conn->error]);
                        exit();
                    }
                    
                    $stmt->bind_param("isii", $_SESSION['user_id'], $admin_notes, $appointment_id, $request_id);
                    
                    if (!$stmt->execute()) {
                        echo json_encode(['success' => false, 'error' => 'Failed to update request status: ' . $stmt->error]);
                        exit();
                    }
                    
                    // Send approval email (wrapped in try-catch)
                    try {
                        $subject = "Appointment Approved - Mhavis Medical Center";
                        
                        // Create HTML email body for better formatting
                        $formatted_date = date('l, F j, Y', strtotime($final_date));
                        $formatted_time = date('g:i A', strtotime($final_time));
                        
                        $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                            <div style='background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                                <h2 style='margin: 0;'> Appointment Approved!</h2>
                            </div>
                            <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                                <p style='font-size: 16px; color: #333;'>Dear <strong>{$request['first_name']} {$request['last_name']}</strong>,</p>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    Great news! Your appointment request has been approved by our admin team.
                                </p>
                                
                                <div style='background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;'>
                                    <h3 style='margin-top: 0; color: #333;'>📅 Appointment Details</h3>
                                    <p style='margin: 10px 0;'><strong>Doctor:</strong> Dr. {$request['doctor_first_name']} {$request['doctor_last_name']}</p>
                                    <p style='margin: 10px 0;'><strong>Date:</strong> {$formatted_date}</p>
                                    <p style='margin: 10px 0;'><strong>Time:</strong> {$formatted_time}</p>
                                    <p style='margin: 10px 0;'><strong>Reason:</strong> {$request['reason']}</p>
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
                        
                        if (function_exists('sendEmailNotification')) {
                            $email_queued = sendEmailNotification($request['email'], $request['first_name'] . ' ' . $request['last_name'], 
                                                $subject, $body, 'html');
                            
                            // Process email queue immediately to send the email now
                            if ($email_queued) {
                                processEmailQueue();
                                error_log("Approval email queued and processed for: " . $request['email']);
                            } else {
                                error_log("Failed to queue approval email for: " . $request['email']);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error sending email notification: " . $e->getMessage());
                    }
                    
                    // Create Email notification (for tracking email sends) - wrapped in try-catch
                    try {
                        if (function_exists('createNotification')) {
                            createNotification('Patient', $request['patient_user_id'], 'Appointment_Approved', 
                                'Appointment Approved', 
                                "Your appointment with Dr. {$request['doctor_first_name']} {$request['doctor_last_name']} has been approved for " . 
                                date('M j, Y', strtotime($final_date)) . " at " . date('g:i A', strtotime($final_time)) . ".", 
                                'Email');
                        }
                    } catch (Exception $e) {
                        error_log("Error creating email notification: " . $e->getMessage());
                    }
                    
                    // Create System notification (displays in patient dashboard) - wrapped in try-catch
                    try {
                        if (function_exists('createNotification')) {
                            createNotification('Patient', $request['patient_user_id'], 'Appointment_Approved', 
                                'Appointment Approved', 
                                "Your appointment request has been approved!\n\n" .
                                "Doctor: Dr. {$request['doctor_first_name']} {$request['doctor_last_name']}\n" .
                                "Date: " . date('M j, Y', strtotime($final_date)) . "\n" .
                                "Time: " . date('g:i A', strtotime($final_time)) . "\n\n" .
                                "Please arrive 15 minutes early for your appointment.", 
                                'System');
                        }
                    } catch (Exception $e) {
                        error_log("Error creating system notification: " . $e->getMessage());
                    }
                    
                    // Notify Doctor about new assigned patient/appointment
                    try {
                        if (function_exists('createNotification')) {
                            $patientFullName = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
                            $title = 'New Appointment Assigned';
                            $message = "You have a new appointment.\n\n" .
                                       "Patient: " . ($patientFullName ?: 'Patient') . "\n" .
                                       "Date: " . date('M j, Y', strtotime($final_date)) . "\n" .
                                       "Time: " . date('g:i A', strtotime($final_time)) . "\n\n" .
                                       "Please review the patient record before the visit.";
                            // request['doctor_id'] is users.id
                            createNotification('Doctor', $request['doctor_id'], 'New_Assigned_Patient', $title, $message, 'System');
                        }
                    } catch (Exception $e) {
                        error_log("Error creating doctor notification: " . $e->getMessage());
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Appointment approved successfully. Patient has been notified.']);
                    exit();
                } else {
                    $error = "Failed to create appointment: " . $stmt->error;
                    error_log("Admin appointment approval error: " . $stmt->error);
                    echo json_encode(['success' => false, 'error' => 'Failed to create appointment: ' . $stmt->error]);
                    exit();
                }
            } elseif ($action === 'reject') {
                // Update request status
                $stmt = $conn->prepare("UPDATE appointment_requests 
                    SET status = 'Rejected', approved_by = ?, approved_at = NOW(), admin_notes = ? 
                    WHERE id = ?");
                
                if (!$stmt) {
                    echo json_encode(['success' => false, 'error' => 'Failed to prepare rejection update: ' . $conn->error]);
                    exit();
                }
                
                $stmt->bind_param("isi", $_SESSION['user_id'], $admin_notes, $request_id);
                
                if ($stmt->execute()) {
                    // Send rejection email (wrapped in try-catch)
                    try {
                        $subject = "Appointment Request Status Update - Mhavis Medical Center";
                        
                        // Create HTML email body for better formatting
                        $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                            <div style='background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                                <h2 style='margin: 0;'>Appointment Request Status Update</h2>
                            </div>
                            <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                                <p style='font-size: 16px; color: #333;'>Dear <strong>{$request['first_name']} {$request['last_name']}</strong>,</p>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    Thank you for your appointment request with Dr. {$request['doctor_first_name']} {$request['doctor_last_name']}.
                                </p>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    Unfortunately, we are unable to accommodate your requested appointment at this time.
                                </p>
                                
                                " . (!empty($admin_notes) ? "
                                <div style='background-color: #f8d7da; padding: 15px; margin: 20px 0; border-left: 4px solid #dc3545; border-radius: 4px;'>
                                    <p style='margin: 0; font-size: 14px; color: #721c24;'>
                                        <strong>Reason:</strong> {$admin_notes}
                                    </p>
                                </div>
                                " : "") . "
                                
                                <div style='background-color: #d1ecf1; padding: 15px; margin: 20px 0; border-left: 4px solid #0dcaf0; border-radius: 4px;'>
                                    <p style='margin: 0; font-size: 14px; color: #055160;'>
                                        💡 <strong>What's Next?</strong><br>
                                        You can submit a new appointment request with different date/time preferences, or contact us directly for assistance in finding an available slot.
                                    </p>
                                </div>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    We apologize for any inconvenience and look forward to serving you soon.
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
                        
                        if (function_exists('sendEmailNotification')) {
                            $email_queued = sendEmailNotification($request['email'], $request['first_name'] . ' ' . $request['last_name'], 
                                                $subject, $body, 'html');
                            
                            // Process email queue immediately to send the email now
                            if ($email_queued) {
                                processEmailQueue();
                                error_log("Rejection email queued and processed for: " . $request['email']);
                            } else {
                                error_log("Failed to queue rejection email for: " . $request['email']);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error sending rejection email: " . $e->getMessage());
                    }
                    
                    // Create Email notification (for tracking email sends) - wrapped in try-catch
                    try {
                        if (function_exists('createNotification')) {
                            createNotification('Patient', $request['patient_user_id'], 'Appointment_Rejected', 
                                'Appointment Request Rejected', 
                                'Your appointment request has been rejected. ' . ($admin_notes ? "Reason: {$admin_notes}" : "Please contact us for more information."), 
                                'Email');
                        }
                    } catch (Exception $e) {
                        error_log("Error creating email notification: " . $e->getMessage());
                    }
                    
                    // Create System notification (displays in patient dashboard) - wrapped in try-catch
                    try {
                        if (function_exists('createNotification')) {
                            createNotification('Patient', $request['patient_user_id'], 'Appointment_Rejected', 
                                'Appointment Request Rejected', 
                                "We regret to inform you that your appointment request could not be approved at this time.\n\n" .
                                ($admin_notes ? "Reason: {$admin_notes}\n\n" : "") .
                                "Please feel free to submit a new request with different preferences or contact us for assistance.", 
                                'System');
                        }
                    } catch (Exception $e) {
                        error_log("Error creating system notification: " . $e->getMessage());
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Appointment request rejected. Patient has been notified.']);
                    exit();
                } else {
                    $error = "Failed to reject appointment request: " . $stmt->error;
                    error_log("Admin appointment rejection error: " . $stmt->error);
                    echo json_encode(['success' => false, 'error' => 'Failed to reject appointment request: ' . $stmt->error]);
                    exit();
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid action specified: ' . $action]);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Request not found or already processed. It may have been handled by another admin.']);
            exit();
        }
    }
}

// Get appointment requests for each tab
$pending_requests = [];
$approved_requests = [];
$rejected_requests = [];
$all_logs = [];

try {
    // Get pending requests
    $stmt = $conn->prepare("SELECT ar.*, p.first_name, p.last_name, p.phone, p.email, 
                           u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                           d.name as department_name
                           FROM appointment_requests ar
                           JOIN patient_users pu ON ar.patient_user_id = pu.id
                           JOIN patients p ON pu.patient_id = p.id
                           JOIN users u ON ar.doctor_id = u.id
                           LEFT JOIN departments d ON ar.department_id = d.id
                           WHERE ar.status = 'Pending'
                           ORDER BY ar.created_at DESC");
    if ($stmt) {
        $stmt->execute();
        $pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Get approved requests
    $stmt = $conn->prepare("SELECT ar.*, p.first_name, p.last_name, p.phone, p.email,
                           u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                           admin.first_name as processed_by_name, admin.last_name as processed_by_last_name,
                           d.name as department_name
                           FROM appointment_requests ar
                           JOIN patient_users pu ON ar.patient_user_id = pu.id
                           JOIN patients p ON pu.patient_id = p.id
                           JOIN users u ON ar.doctor_id = u.id
                           LEFT JOIN users admin ON ar.approved_by = admin.id
                           LEFT JOIN departments d ON ar.department_id = d.id
                           WHERE ar.status = 'Approved'
                           ORDER BY ar.approved_at DESC");
    if ($stmt) {
        $stmt->execute();
        $approved_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Get rejected requests
    $stmt = $conn->prepare("SELECT ar.*, p.first_name, p.last_name, p.phone, p.email,
                           u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                           admin.first_name as processed_by_name, admin.last_name as processed_by_last_name,
                           d.name as department_name
                           FROM appointment_requests ar
                           JOIN patient_users pu ON ar.patient_user_id = pu.id
                           JOIN patients p ON pu.patient_id = p.id
                           JOIN users u ON ar.doctor_id = u.id
                           LEFT JOIN users admin ON ar.approved_by = admin.id
                           LEFT JOIN departments d ON ar.department_id = d.id
                           WHERE ar.status = 'Rejected'
                           ORDER BY ar.approved_at DESC");
    if ($stmt) {
        $stmt->execute();
        $rejected_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Get all logs (all requests)
    $stmt = $conn->prepare("SELECT ar.*, p.first_name, p.last_name, p.phone, p.email,
                           u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                           admin.first_name as processed_by_name, admin.last_name as processed_by_last_name,
                           d.name as department_name
                           FROM appointment_requests ar
                           JOIN patient_users pu ON ar.patient_user_id = pu.id
                           JOIN patients p ON pu.patient_id = p.id
                           JOIN users u ON ar.doctor_id = u.id
                           LEFT JOIN users admin ON ar.approved_by = admin.id
                           LEFT JOIN departments d ON ar.department_id = d.id
                           ORDER BY ar.created_at DESC");
    if ($stmt) {
        $stmt->execute();
        $all_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Database query error in admin_appointment_requests.php: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Request Management - Mhavis Medical & Diagnostic Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
    <style>
        /* Uniform Tab Styling */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-right: 4px;
        }
        
        .nav-tabs .nav-link:hover {
            color: #495057;
            border-bottom-color: #dee2e6;
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            background-color: transparent;
            border-bottom-color: #0d6efd;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            font-weight: 600;
        }
        
        /* Uniform Table Styling */
        .table {
            margin-bottom: 0;
            width: 100%;
            font-size: 1rem;
        }
        
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
            padding: 16px 20px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 16px 20px;
            vertical-align: middle;
            word-wrap: break-word;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        /* Uniform Badge Styling */
        .badge {
            padding: 8px 14px;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: 6px;
            display: inline-block;
            white-space: nowrap;
        }
        
        /* Table cell content spacing */
        .table td strong {
            font-weight: 600;
            color: #212529;
        }
        
        .table td small {
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        /* Better column spacing */
        .table th:first-child,
        .table td:first-child {
            padding-left: 30px;
        }
        
        .table th:last-child,
        .table td:last-child {
            padding-right: 30px;
        }
        
        /* Uniform Empty State */
        .alert-info {
            border-left: 4px solid #0dcaf0;
            background-color: #e7f3f5;
            color: #055160;
            padding: 16px 20px;
        }
        
        .alert-info i {
            font-size: 1.2rem;
        }
        
        /* Card Styling */
        .card {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 16px 20px;
        }
        
        .card-header h4 {
            margin: 0;
            font-weight: 600;
            color: #212529;
        }
        
        .card-body {
            padding: 30px 40px;
        }
        
        /* Improved spacing for tabs */
        .nav-tabs {
            margin-bottom: 35px;
        }
        
        /* Better table spacing */
        .table-responsive {
            margin-top: 25px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) > td {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .table-hover > tbody > tr:hover > td {
            background-color: #f8f9fa;
        }
        
        /* Better container spacing */
        .container-fluid {
            padding-left: 30px;
            padding-right: 30px;
            padding-top: 30px;
            padding-bottom: 30px;
        }
        
        /* Tab content spacing */
        .tab-content {
            padding-top: 10px;
        }
        
        .tab-pane {
            min-height: 200px;
        }
        
        /* Alert spacing improvements */
        .alert {
            margin-bottom: 20px;
        }
        
        /* Empty state spacing */
        .alert-info {
            margin-top: 20px;
        }
        
        /* Uniform Button Group */
        .btn-group .btn {
            border-radius: 4px;
        }
        
        .btn-group .btn:first-child {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .btn-group .btn:last-child {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        /* Action buttons spacing */
        .btn-group {
            gap: 8px;
        }
        
        .btn-group .btn {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        /* Column width improvements */
        .table th:nth-child(1),
        .table td:nth-child(1) {
            min-width: 150px;
        }
        
        .table th:nth-child(2),
        .table td:nth-child(2) {
            min-width: 180px;
        }
        
        .table th:nth-child(3),
        .table td:nth-child(3) {
            min-width: 140px;
        }
        
        .table th:nth-child(4),
        .table td:nth-child(4) {
            min-width: 140px;
        }
        
        .table th:nth-child(5),
        .table td:nth-child(5) {
            min-width: 130px;
        }
        
        .table th:nth-child(6),
        .table td:nth-child(6) {
            min-width: 120px;
        }
        
        .table th:nth-child(7),
        .table td:nth-child(7) {
            min-width: 150px;
        }
        
        .table th:nth-child(8),
        .table td:nth-child(8) {
            min-width: 180px;
        }
        
        .table th:nth-child(9),
        .table td:nth-child(9) {
            min-width: 180px;
        }
        
        .table th:nth-child(10),
        .table td:nth-child(10) {
            min-width: 200px;
        }
        
        .table th:nth-child(11),
        .table td:nth-child(11) {
            min-width: 250px;
        }
        
        /* Better text wrapping for long content */
        .table td {
            word-break: break-word;
            overflow-wrap: break-word;
        }
        
        /* Ensure table cells have proper spacing */
        .table td strong {
            display: block;
            margin-bottom: 4px;
        }
        
        /* Better spacing for table rows */
        .table tbody tr {
            height: auto;
            min-height: 50px;
        }
        
        /* Improved table responsive spacing */
        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-4" id="appointmentRequestTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                                    <i class="bi bi-check-circle me-1"></i>Approved
                                    <span class="badge bg-success ms-1"><?php echo count($approved_requests); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                                    <i class="bi bi-clock-history me-1"></i>Pending
                                    <span class="badge bg-warning text-dark ms-1"><?php echo count($pending_requests); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">
                                    <i class="bi bi-x-circle me-1"></i>Rejected
                                    <span class="badge bg-danger ms-1"><?php echo count($rejected_requests); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                                    <i class="bi bi-journal-text me-1"></i>Logs
                                    <span class="badge bg-info text-dark ms-1"><?php echo count($all_logs); ?></span>
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="appointmentRequestTabsContent">
                            <!-- Approved Tab -->
                            <div class="tab-pane fade show active" id="approved" role="tabpanel">
                                <?php if (empty($approved_requests)): ?>
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span>No approved appointment requests found.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Doctor</th>
                                                    <th>Department</th>
                                                    <th>Preferred Date</th>
                                                    <th>Preferred Time</th>
                                                    <th>Processed By</th>
                                                    <th>Processed Date</th>
                                                    <th>Admin Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($approved_requests as $request): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong></td>
                                                        <td>Dr. <?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($request['preferred_date'])); ?></td>
                                                        <td><?php echo date('g:i A', strtotime($request['preferred_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars(trim(($request['processed_by_name'] ?? '') . ' ' . ($request['processed_by_last_name'] ?? '')) ?: 'N/A'); ?></td>
                                                        <td><?php echo $request['approved_at'] ? date('M j, Y g:i A', strtotime($request['approved_at'])) : 'N/A'; ?></td>
                                                        <td><small class="text-muted"><?php echo htmlspecialchars($request['admin_notes'] ?? '—'); ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Pending Tab -->
                            <div class="tab-pane fade" id="pending" role="tabpanel">
                                <?php if (empty($pending_requests)): ?>
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span>No pending appointment requests found.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Doctor</th>
                                                    <th>Department</th>
                                                    <th>Preferred Date</th>
                                                    <th>Preferred Time</th>
                                                    <th>Request Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_requests as $request): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong></td>
                                                        <td>Dr. <?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($request['preferred_date'])); ?></td>
                                                        <td><?php echo date('g:i A', strtotime($request['preferred_time'])); ?></td>
                                                        <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button class="btn btn-sm btn-success" 
                                                                        onclick="showRequestDetails(<?php echo htmlspecialchars(json_encode($request)); ?>, 'approve')"
                                                                        title="Approve Request">
                                                                    <i class="bi bi-check-circle me-1"></i> Approve
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" 
                                                                        onclick="showRequestDetails(<?php echo htmlspecialchars(json_encode($request)); ?>, 'reject')"
                                                                        title="Reject Request">
                                                                    <i class="bi bi-x-circle me-1"></i> Reject
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Rejected Tab -->
                            <div class="tab-pane fade" id="rejected" role="tabpanel">
                                <?php if (empty($rejected_requests)): ?>
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span>No rejected appointment requests found.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Doctor</th>
                                                    <th>Department</th>
                                                    <th>Preferred Date</th>
                                                    <th>Preferred Time</th>
                                                    <th>Processed By</th>
                                                    <th>Processed Date</th>
                                                    <th>Admin Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rejected_requests as $request): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong></td>
                                                        <td>Dr. <?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($request['preferred_date'])); ?></td>
                                                        <td><?php echo date('g:i A', strtotime($request['preferred_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars(trim(($request['processed_by_name'] ?? '') . ' ' . ($request['processed_by_last_name'] ?? '')) ?: 'N/A'); ?></td>
                                                        <td><?php echo $request['approved_at'] ? date('M j, Y g:i A', strtotime($request['approved_at'])) : 'N/A'; ?></td>
                                                        <td><small class="text-muted"><?php echo htmlspecialchars($request['admin_notes'] ?? '—'); ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Logs Tab -->
                            <div class="tab-pane fade" id="logs" role="tabpanel">
                                <?php if (empty($all_logs)): ?>
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span>No appointment request logs available.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Doctor</th>
                                                    <th>Department</th>
                                                    <th>Status</th>
                                                    <th>Preferred Date</th>
                                                    <th>Preferred Time</th>
                                                    <th>Request Date</th>
                                                    <th>Processed By</th>
                                                    <th>Processed Date</th>
                                                    <th>Admin Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_logs as $request): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong></td>
                                                        <td>Dr. <?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $request['status'] === 'Approved' ? 'success' : 
                                                                    ($request['status'] === 'Rejected' ? 'danger' : 'warning'); 
                                                            ?>">
                                                                <?php echo htmlspecialchars($request['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($request['preferred_date'])); ?></td>
                                                        <td><?php echo date('g:i A', strtotime($request['preferred_time'])); ?></td>
                                                        <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                        <td><?php echo htmlspecialchars(trim(($request['processed_by_name'] ?? 'N/A') . ' ' . ($request['processed_by_last_name'] ?? '')) ?: 'N/A'); ?></td>
                                                        <td><?php echo $request['approved_at'] ? date('M j, Y g:i A', strtotime($request['approved_at'])) : 'N/A'; ?></td>
                                                        <td><small class="text-muted"><?php echo htmlspecialchars($request['admin_notes'] ?? '—'); ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="requestDetails"></div>
                    <form id="actionForm">
                        <input type="hidden" id="requestId" name="request_id">
                        <input type="hidden" id="actionType" name="action">
                        
                        <div id="approvalFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Final Appointment Date</label>
                                    <input type="date" class="form-control" name="appointment_date" id="appointment_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Final Appointment Time</label>
                                    <input type="time" class="form-control" name="appointment_time" id="appointment_time">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Notes</label>
                            <textarea class="form-control" name="admin_notes" rows="3" 
                                      placeholder="Add notes about your decision..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmAction">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        let currentRequest = null;
        let currentAction = '';

        function showRequestDetails(request, action) {
            currentRequest = request;
            currentAction = action;
            
            const detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Patient Information</h6>
                        <p><strong>Name:</strong> ${request.first_name} ${request.last_name}</p>
                        <p><strong>Phone:</strong> ${request.phone}</p>
                        <p><strong>Email:</strong> ${request.email}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Appointment Details</h6>
                        <p><strong>Doctor:</strong> Dr. ${request.doctor_first_name} ${request.doctor_last_name}</p>
                        <p><strong>Department:</strong> ${request.department_name}</p>
                        <p><strong>Preferred Date:</strong> ${new Date(request.preferred_date).toLocaleDateString()}</p>
                        <p><strong>Preferred Time:</strong> ${new Date('2000-01-01 ' + request.preferred_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Reason for Visit</h6>
                        <p>${request.reason}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Request Information</h6>
                        <p><strong>Request Date:</strong> ${new Date(request.created_at).toLocaleString()}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('requestDetails').innerHTML = detailsHtml;
            document.getElementById('requestId').value = request.id;
            document.getElementById('actionType').value = action;
            
            // Show/hide approval fields
            const approvalFields = document.getElementById('approvalFields');
            if (action === 'approve') {
                approvalFields.style.display = 'block';
                document.getElementById('appointment_date').value = request.preferred_date;
                document.getElementById('appointment_time').value = request.preferred_time;
            } else {
                approvalFields.style.display = 'none';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('requestModal'));
            modal.show();
        }

        document.getElementById('confirmAction').addEventListener('click', function() {
            const form = document.getElementById('actionForm');
            const formData = new FormData(form);
            
            // Log form data for debugging
            console.log('Submitting form with data:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }
            
            // Disable button to prevent double submission
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response content type:', response.headers.get('content-type'));
                
                // Try to parse as JSON
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    
                    try {
                        const json = JSON.parse(text);
                        return {
                            ok: response.ok,
                            status: response.status,
                            data: json,
                            isJson: true
                        };
                    } catch (e) {
                        // Not JSON, might be HTML error page
                        return {
                            ok: response.ok,
                            status: response.status,
                            data: { error: 'Invalid response format. Response: ' + text.substring(0, 200) },
                            isJson: false
                        };
                    }
                });
            })
            .then(result => {
                console.log('Parsed result:', result);
                
                // Check if the response indicates success
                if (result.data.success) {
                    showAlert(result.data.message, 'Success', 'success').then(function() {
                        location.reload();
                    });
                } else {
                    // Show error message
                    const errorMsg = result.data.error || 'Unknown error occurred';
                    showAlert(errorMsg + '\n\nPlease check the issue and try again.', 'Error', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Confirm';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while processing the request.\n\nError: ' + error.message + '\n\nPlease check the browser console for more details and try again.', 'Error', 'error');
                btn.disabled = false;
                btn.textContent = 'Confirm';
            });
        });
    </script>
</body>
</html>
