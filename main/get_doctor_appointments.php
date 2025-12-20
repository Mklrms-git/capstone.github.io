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
    // Get all appointments for the doctor with patient details
    $stmt = $conn->prepare("SELECT a.*, 
                           COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Patient') as patient_name,
                           p.phone as patient_phone,
                           p.email as patient_email
                           FROM appointments a 
                           LEFT JOIN patients p ON a.patient_id = p.id 
                           WHERE a.doctor_id = ? 
                           ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $all_appointments = [];
    while ($row = $result->fetch_assoc()) {
        $all_appointments[] = $row;
    }
    
    // Separate upcoming and past appointments - consider both date and time
    $upcoming_appointments = [];
    $past_appointments = [];
    $currentDateTime = new DateTime();
    
    foreach ($all_appointments as $appointment) {
        // Create appointment datetime for accurate comparison
        $appointmentDate = $appointment['appointment_date'];
        $appointmentTime = $appointment['appointment_time'] ?? '00:00:00';
        $appointmentDateTime = new DateTime($appointmentDate . ' ' . $appointmentTime);
        
        // Compare full datetime (date + time) to determine if appointment is past or upcoming
        if ($appointmentDateTime >= $currentDateTime) {
            $upcoming_appointments[] = $appointment;
        } else {
            $past_appointments[] = $appointment;
        }
    }
    
    // Return appointments data
    echo json_encode([
        'upcoming' => $upcoming_appointments,
        'past' => $past_appointments,
        'total' => count($all_appointments),
        'upcoming_count' => count($upcoming_appointments),
        'past_count' => count($past_appointments)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
