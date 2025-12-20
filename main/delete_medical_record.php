<?php
// Dedicated endpoint for deleting medical records
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

$conn = getDBConnection();

// Admin and Doctor can delete medical records (with password verification)
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;

// Check if user is admin or doctor
if (!isAdmin() && !isDoctor()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access',
        'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
    ]);
    exit;
}

// Verify password if provided
$password = $_POST['delete_password'] ?? '';
if (empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Password verification required',
        'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
    ]);
    exit;
}

// Verify password
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated',
        'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
    ]);
    exit;
}

// Get user's password from database
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode([
        'success' => false,
        'message' => 'User not found',
        'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
    ]);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect password',
        'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
    ]);
    exit;
}

// Password verified, proceed with deletion
$delete_id = isset($_POST['delete_record_id']) ? (int)$_POST['delete_record_id'] : 0;

if ($delete_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid record ID',
        'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
    ]);
    exit;
}

$delete_success = false;
$stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
if ($stmt !== false) {
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $delete_success = true;
    }
    $stmt->close();
}

// Build redirect URL
$year_param = $selected_year ? '&year=' . $selected_year : '';
$redirect_url = $patient_id 
    ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" . $year_param . "&message=" . urlencode('Medical record deleted successfully')
    : "patients.php?message=" . urlencode('Medical record deleted successfully');

echo json_encode([
    'success' => $delete_success,
    'message' => $delete_success ? 'Medical record deleted successfully' : 'Failed to delete medical record',
    'redirect' => $redirect_url
]);
exit;
?>

