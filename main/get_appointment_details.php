<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireLogin();
// Allow admin and doctor access
if (!isAdmin() && !isDoctor()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid appointment ID']);
    exit;
}

$appointmentId = (int)$_GET['id'];
$conn = getDBConnection();

$query = "SELECT a.*, 
          COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Patient') as patient_name,
          COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown Doctor') as doctor_name,
          p.phone, p.email, p.date_of_birth, p.sex, p.address,
          u.email as doctor_email, u.phone as doctor_phone,
          COALESCE(a.notes, '') as notes,
          COALESCE(a.reason, '') as reason
          FROM appointments a
          LEFT JOIN patients p ON a.patient_id = p.id
          LEFT JOIN doctors d ON a.doctor_id = d.id
          LEFT JOIN users u ON d.user_id = u.id
          WHERE a.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();

// Format the data for display
$formattedDate = formatDate($appointment['appointment_date'], 'F d, Y (l)');
$formattedTime = formatTime($appointment['appointment_time']);
$formattedDateTime = $formattedDate . ' at ' . $formattedTime;

// Calculate patient age
$patientAge = '';
if ($appointment['date_of_birth']) {
    $birthDate = new DateTime($appointment['date_of_birth']);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    $patientAge = $age->y . ' years old';
}

// Status badge class - use exact ENUM values (case-sensitive): 'scheduled', 'ongoing', 'settled', 'cancelled'
$statusLower = strtolower(trim($appointment['status'] ?? ''));
$statusClass = match($statusLower) {
    'scheduled' => 'primary',
    'ongoing' => 'warning',
    'settled' => 'success',
    'cancelled', 'canceled' => 'danger',
    default => 'secondary'
};

// Format status display to match appointments.php format: Scheduled, Ongoing, Settled, Cancelled
$statusDisplay = ucfirst($appointment['status'] ?? 'Unknown');

$response = [
    'id' => $appointment['id'],
    'patient_name' => $appointment['patient_name'],
    'doctor_name' => $appointment['doctor_name'],
    'appointment_date' => $appointment['appointment_date'],
    'appointment_time' => $appointment['appointment_time'],
    'formatted_datetime' => $formattedDateTime,
    'status' => $statusDisplay,
    'status_class' => $statusClass,
    'notes' => $appointment['notes'],
    'reason' => $appointment['reason'],
    'patient_phone' => $appointment['phone'],
    'patient_email' => $appointment['email'],
    'patient_age' => $patientAge,
    'patient_gender' => $appointment['sex'],
    'patient_address' => $appointment['address'],
    'doctor_email' => $appointment['doctor_email'],
    'doctor_phone' => $appointment['doctor_phone']
];

echo json_encode($response);
?>
