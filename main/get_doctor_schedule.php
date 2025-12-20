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
    // Get doctor's schedule from doctor_schedules table
    $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Convert to associative array with day as key
    $doctor_schedule = [];
    while ($schedule = $result->fetch_assoc()) {
        $doctor_schedule[$schedule['day_of_week']] = $schedule;
    }
    
    // Return schedule data
    echo json_encode([
        'schedule' => $doctor_schedule,
        'total_days' => count($doctor_schedule)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
