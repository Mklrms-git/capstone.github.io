<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

// Set header for JSON response
header('Content-Type: application/json');

// Determine if this is an admin request or patient request
$is_admin = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    $is_admin = true;
}

// Require appropriate authentication
if ($is_admin) {
    requireAdmin();
} else {
    requirePatientLogin();
}

$doctor_id = $_GET['doctor_id'] ?? '';
$date = $_GET['date'] ?? '';

// Validate inputs
if (!$doctor_id || !$date) {
    echo json_encode(['success' => false, 'time_slots' => []]);
    exit();
}

// Validate doctor_id is numeric
if (!is_numeric($doctor_id)) {
    echo json_encode(['success' => false, 'time_slots' => []]);
    exit();
}

// Validate date format
$date_obj = DateTime::createFromFormat('Y-m-d', $date);
if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
    echo json_encode(['success' => false, 'time_slots' => []]);
    exit();
}

// Don't allow booking in the past
$today = new DateTime();
$today->setTime(0, 0, 0);
if ($date_obj < $today) {
    echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Cannot book appointments in the past']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // For admin: doctor_id is doctors.id, need to convert to users.id for schedule lookup
    // For patient: doctor_id is users.id
    if ($is_admin) {
        // Convert doctors.id to users.id
        $stmt = $conn->prepare("SELECT user_id FROM doctors WHERE id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Doctor not found']);
            exit();
        }
        
        $doctor_data = $result->fetch_assoc();
        $user_id = $doctor_data['user_id'];
        $doctor_table_id = $doctor_id; // For checking appointments (appointments table uses doctors.id)
    } else {
        $user_id = $doctor_id;
        // Get doctors.id from users.id for checking appointments
        $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Doctor not found']);
            exit();
        }
        $doctor_data = $result->fetch_assoc();
        $doctor_table_id = $doctor_data['id'];
    }
    
    // Get doctor schedule for the day
    // date('N') returns 1-7 (Monday-Sunday), convert to string to match VARCHAR in database
    $day_of_week = (string)date('N', strtotime($date)); // '1' = Monday, '7' = Sunday
    $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1");
    $stmt->bind_param("is", $user_id, $day_of_week);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Doctor is not available on this day']);
        exit();
    }
    
    $schedule = $result->fetch_assoc();
    $start_time = new DateTime($schedule['start_time']);
    $end_time = new DateTime($schedule['end_time']);
    $break_start = $schedule['break_start'] ? new DateTime($schedule['break_start']) : null;
    $break_end = $schedule['break_end'] ? new DateTime($schedule['break_end']) : null;
    
    // Generate 30-minute slots
    $available_slots = [];
    $current_time = clone $start_time;
    $interval = new DateInterval('PT30M');
    
    // Check if appointment is for today
    $is_today = (date('Y-m-d') === $date);
    $current_hour = new DateTime();
    $current_hour->modify('+30 minutes'); // Add buffer to allow booking
    
    while ($current_time < $end_time) {
        $time_slot = $current_time->format('H:i:s');
        $time_display = $current_time->format('H:i');
        
        // Skip past time slots if appointment is for today
        if ($is_today && $current_time < $current_hour) {
            $current_time->add($interval);
            continue;
        }
        
        // Skip break time
        if ($break_start && $break_end && $current_time >= $break_start && $current_time < $break_end) {
            $current_time->add($interval);
            continue;
        }
        
        // Check if slot is already booked
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments 
                               WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                               AND status != 'cancelled'");
        $stmt->bind_param("iss", $doctor_table_id, $date, $time_slot);
        $stmt->execute();
        $check_result = $stmt->get_result();
        $is_booked = $check_result->fetch_assoc()['count'] > 0;
        
        if (!$is_booked) {
            $available_slots[] = [
                'value' => $time_display,
                'display' => $current_time->format('g:i A')
            ];
        }
        
        $current_time->add($interval);
    }
    
    if (empty($available_slots)) {
        echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'No available time slots for this date']);
    } else {
        echo json_encode([
            'success' => true,
            'time_slots' => $available_slots
        ]);
    }
} catch (Exception $e) {
    error_log("Error getting available time slots: " . $e->getMessage());
    echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Error loading time slots']);
}
