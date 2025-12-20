<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireLogin();

header('Content-Type: application/json');

$conn = getDBConnection();
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit();
}

// Check if medical_history table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
$hasHistoryTable = $checkTable && $checkTable->num_rows > 0;

// Build query based on user role
if (isAdmin()) {
    // Admin can view any patient
    if ($hasHistoryTable) {
        $stmt = $conn->prepare("SELECT p.*, 
           (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
           (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
           (SELECT COUNT(*) FROM medical_history mh WHERE mh.patient_id = p.id AND mh.status = 'active') as history_count
        FROM patients p WHERE p.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT p.*, 
           (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
           (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
           (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND m.history_type IS NOT NULL AND m.history_type != '') as history_count
        FROM patients p WHERE p.id = ?");
    }
    $stmt->bind_param("i", $patient_id);
} else {
    // Doctor can only view their assigned patients
    if ($hasHistoryTable) {
        $stmt = $conn->prepare("SELECT p.*, 
           (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
           (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
           (SELECT COUNT(*) FROM medical_history mh WHERE mh.patient_id = p.id AND mh.status = 'active') as history_count
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE p.id = ? AND u.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT p.*, 
           (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
           (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
           (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND m.history_type IS NOT NULL AND m.history_type != '') as history_count
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE p.id = ? AND u.id = ?");
    }
    $stmt->bind_param("ii", $patient_id, $userId);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Patient not found or access denied']);
    exit();
}

$patient_details = $result->fetch_assoc();

// Fetch patient profile image from patient_users table
$check_column = $conn->query("SHOW COLUMNS FROM patient_users LIKE 'profile_image'");
$has_profile_image = $check_column && $check_column->num_rows > 0;

if ($has_profile_image) {
    $profile_stmt = $conn->prepare("SELECT profile_image FROM patient_users WHERE patient_id = ? LIMIT 1");
    $profile_stmt->bind_param("i", $patient_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    if ($profile_row = $profile_result->fetch_assoc()) {
        $patient_details['profile_image'] = !empty($profile_row['profile_image']) ? $profile_row['profile_image'] : null;
    } else {
        $patient_details['profile_image'] = null;
    }
    $profile_stmt->close();
} else {
    $patient_details['profile_image'] = null;
}

// Calculate age
$birthDate = new DateTime($patient_details['date_of_birth']);
$today = new DateTime();
$age = $today->diff($birthDate)->y;
$patient_details['age'] = $age;

// Format full name
$fullName = $patient_details['first_name'];
if (!empty($patient_details['middle_name'])) {
    $fullName .= ' ' . $patient_details['middle_name'];
}
$fullName .= ' ' . $patient_details['last_name'];
$patient_details['full_name'] = $fullName;

// Format date of birth
$patient_details['date_of_birth_formatted'] = date('F j, Y', strtotime($patient_details['date_of_birth']));

// Format created_at if exists
if (!empty($patient_details['created_at'])) {
    $patient_details['created_at_formatted'] = date('F j, Y', strtotime($patient_details['created_at']));
} else {
    $patient_details['created_at_formatted'] = date('F j, Y', strtotime($patient_details['date_of_birth']));
}

echo json_encode(['success' => true, 'patient' => $patient_details]);





