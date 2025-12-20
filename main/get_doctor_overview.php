<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid doctor ID']);
    exit;
}

$doctor_id = (int)$_GET['id'];
$conn = getDBConnection();

try {
    // Get doctor details with appointment counts
    $stmt = $conn->prepare("SELECT u.*, 
                           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = u.id) as appointment_count,
                           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = u.id AND a.appointment_date = CURDATE()) as today_appointments
                           FROM users u WHERE u.id = ? AND u.role = 'Doctor'");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Doctor not found']);
        exit;
    }
    
    $doctor = $result->fetch_assoc();
    
    // Return doctor data
    echo json_encode([
        'id' => $doctor['id'],
        'first_name' => $doctor['first_name'],
        'last_name' => $doctor['last_name'],
        'email' => $doctor['email'],
        'phone' => $doctor['phone'],
        'specialization' => $doctor['specialization'],
        'appointment_count' => $doctor['appointment_count'],
        'today_appointments' => $doctor['today_appointments']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
