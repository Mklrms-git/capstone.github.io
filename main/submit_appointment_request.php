<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

// Require patient login
requirePatientLogin();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON parsing failed
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

$doctor_id = $input['doctor_id'] ?? '';
$department_id = $input['department_id'] ?? '';
$preferred_date = $input['preferred_date'] ?? '';
$preferred_time = $input['preferred_time'] ?? '';
$reason = $input['reason'] ?? '';

// Validation
if (empty($doctor_id) || empty($department_id) || empty($preferred_date) || 
    empty($preferred_time) || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit();
}

// Check if time slot is still available
if (!isAppointmentTimeAvailable($doctor_id, $preferred_date, $preferred_time)) {
    echo json_encode(['success' => false, 'message' => 'Selected time slot is no longer available']);
    exit();
}

$patient_user = getCurrentPatientUser();

// Insert appointment request
$conn = getDBConnection();
$stmt = $conn->prepare("INSERT INTO appointment_requests 
    (patient_user_id, doctor_id, department_id, preferred_date, preferred_time, reason) 
    VALUES (?, ?, ?, ?, ?, ?)");

$stmt->bind_param("iiisss", $patient_user['id'], $doctor_id, $department_id, 
                  $preferred_date, $preferred_time, $reason);

if ($stmt->execute()) {
    $request_id = $conn->insert_id;
    
    // Create notification for all admins (wrapped in try-catch)
    try {
        if (function_exists('createAdminNotification')) {
            // Get doctor name for the notification
            $doctor_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $doctor_stmt->bind_param("i", $doctor_id);
            $doctor_stmt->execute();
            $doctor_result = $doctor_stmt->get_result();
            $doctor = $doctor_result->fetch_assoc();
            $doctor_name = $doctor ? "Dr. {$doctor['first_name']} {$doctor['last_name']}" : "Doctor";
            
            createAdminNotification('Appointment_Reminder', // Using existing type, will be filtered by recipient_type
                'New Appointment Request',
                "A new appointment request has been submitted.\n\n" .
                "Patient: {$patient_user['first_name']} {$patient_user['last_name']}\n" .
                "Doctor: {$doctor_name}\n" .
                "Date: " . date('M j, Y', strtotime($preferred_date)) . "\n" .
                "Time: " . date('g:i A', strtotime($preferred_time)) . "\n" .
                "Reason: " . substr($reason, 0, 100) . (strlen($reason) > 100 ? '...' : ''),
                'System');
        }
    } catch (Exception $e) {
        error_log("Error creating admin notification: " . $e->getMessage());
        // Continue anyway
    }
    
    // Log activity (wrapped in try-catch)
    try {
        logPatientActivity($patient_user['id'], 'Appointment request submitted');
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        // Continue anyway
    }
    
    echo json_encode(['success' => true, 'message' => 'Appointment request submitted successfully']);
} else {
    $error = $stmt->error;
    error_log("Database error in submit_appointment_request.php: " . $error);
    echo json_encode(['success' => false, 'message' => 'Failed to submit appointment request. Please try again.']);
}
