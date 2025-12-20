<?php
define('MHAVIS_EXEC', true);
// Start output buffering to prevent any warnings/errors from corrupting JSON
ob_start();
require_once 'config/init.php';
require_once 'config/patient_auth.php';

// Set header for JSON response
header('Content-Type: application/json');

// Determine if this is an admin request, patient request, or doctor request
$is_admin = false;
$is_doctor = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    $is_admin = true;
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'Doctor') {
    $is_doctor = true;
}

$action = $_GET['action'] ?? 'schedule'; // 'schedule', 'available_dates', 'time_slots', 'my_appointments', 'my_appointment_stats'
$doctor_id = $_GET['doctor_id'] ?? '';
$date = $_GET['date'] ?? '';

// For doctor-specific actions, doctor_id is not required (uses session)
$doctor_only_actions = ['my_appointments', 'my_appointment_stats'];
if (!in_array($action, $doctor_only_actions)) {
    // Validate doctor_id for other actions
    if (!$doctor_id || !is_numeric($doctor_id)) {
        ob_clean();
        echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Invalid doctor ID']);
        ob_end_flush();
        exit();
    }
}

// Require appropriate authentication
if ($is_doctor && in_array($action, $doctor_only_actions)) {
    requireDoctor();
} elseif ($is_admin) {
    requireAdmin();
} else {
    requirePatientLogin();
}

try {
    $conn = getDBConnection();
    
    // Handle different actions
    if ($action === 'available_dates') {
        // Get available dates for the next 90 days
        // For admin: doctor_id is doctors.id, need to convert to users.id
        // For patient: doctor_id is users.id
        
        if ($is_admin) {
            // Convert doctors.id to users.id
            $stmt = $conn->prepare("SELECT user_id FROM doctors WHERE id = ?");
            $stmt->bind_param("i", $doctor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Doctor not found']);
                exit();
            }
            
            $doctor_data = $result->fetch_assoc();
            $user_id = $doctor_data['user_id'];
        } else {
            $user_id = $doctor_id;
        }
        
        // Get doctor's schedule
        $stmt = $conn->prepare("SELECT day_of_week, is_available FROM doctor_schedules WHERE doctor_id = ? AND is_available = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $available_days = [];
        while ($row = $result->fetch_assoc()) {
            // Convert to integer for comparison with date('N') which returns 1-7
            $available_days[] = (int)$row['day_of_week'];
        }
        
        if (empty($available_days)) {
            echo json_encode(['success' => false, 'available_dates' => [], 'message' => 'Doctor has no available schedule']);
            exit();
        }
        
        // Get active leaves for the doctor
        $stmt = $conn->prepare("SELECT start_date, end_date FROM doctor_leaves 
                               WHERE doctor_id = ? AND status = 'Active' 
                               AND end_date >= CURDATE()");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $leaves_result = $stmt->get_result();
        
        $leave_periods = [];
        while ($leave = $leaves_result->fetch_assoc()) {
            $leave_periods[] = [
                'start' => new DateTime($leave['start_date']),
                'end' => new DateTime($leave['end_date'])
            ];
        }
        $stmt->close();
        
        // Generate available dates for the next 90 days
        $available_dates = [];
        $start_date = new DateTime();
        $end_date = clone $start_date;
        $end_date->modify('+90 days');
        
        $current = clone $start_date;
        while ($current <= $end_date) {
            $day_of_week = (int)$current->format('N'); // 1 = Monday, 7 = Sunday
            if (in_array($day_of_week, $available_days)) {
                // Check if date falls within any leave period
                $is_on_leave = false;
                foreach ($leave_periods as $period) {
                    $current_date = clone $current;
                    $current_date->setTime(0, 0, 0);
                    if ($current_date >= $period['start'] && $current_date <= $period['end']) {
                        $is_on_leave = true;
                        break;
                    }
                }
                
                // Only add if not on leave
                if (!$is_on_leave) {
                    $available_dates[] = $current->format('Y-m-d');
                }
            }
            $current->modify('+1 day');
        }
        
        echo json_encode([
            'success' => true,
            'available_dates' => $available_dates,
            'available_days' => $available_days
        ]);
        
    } elseif ($action === 'time_slots') {
        // Get available time slots for a specific date
        if (!$date) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Date is required']);
            ob_end_flush();
            exit();
        }
        
        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Invalid date format. Please select a valid date.']);
            ob_end_flush();
            exit();
        }
        
        // Don't allow booking in the past
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        if ($date_obj < $today) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Cannot book appointments in the past']);
            ob_end_flush();
            exit();
        }
        
        // For admin: doctor_id is doctors.id, need to convert to users.id
        // For patient: doctor_id is users.id
        if ($is_admin) {
            // Convert doctors.id to users.id
            $stmt = $conn->prepare("SELECT user_id FROM doctors WHERE id = ?");
            $stmt->bind_param("i", $doctor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                ob_clean();
                echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Doctor not found']);
                ob_end_flush();
                exit();
            }
            
            $doctor_data = $result->fetch_assoc();
            $user_id = $doctor_data['user_id'];
            $doctor_table_id = $doctor_id; // For checking appointments
        } else {
            $user_id = $doctor_id;
            // Get doctors.id from users.id for checking appointments
            $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                ob_clean();
                echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Doctor not found']);
                ob_end_flush();
                exit();
            }
            $doctor_data = $result->fetch_assoc();
            $doctor_table_id = $doctor_data['id'];
        }
        
        // Check if doctor is on leave for this date
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM doctor_leaves 
                               WHERE doctor_id = ? AND status = 'Active' 
                               AND ? BETWEEN start_date AND end_date");
        $stmt->bind_param("is", $user_id, $date);
        $stmt->execute();
        $leave_result = $stmt->get_result();
        $is_on_leave = $leave_result->fetch_assoc()['count'] > 0;
        $stmt->close();
        
        if ($is_on_leave) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Doctor is on leave during this period. Please select another date.']);
            ob_end_flush();
            exit();
        }
        
        // Get doctor schedule for the day
        // date('N') returns 1-7 (Monday-Sunday), convert to string to match VARCHAR in database
        $day_of_week = (string)date('N', strtotime($date)); // '1' = Monday, '7' = Sunday
        
        // Debug: Log the query parameters
        error_log("Loading time slots - Doctor ID (user_id): $user_id, Date: $date, Day of week: $day_of_week");
        
        $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1");
        if (!$stmt) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Database error: ' . $conn->error]);
            ob_end_flush();
            exit();
        }
        
        $stmt->bind_param("is", $user_id, $day_of_week);
        if (!$stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Database error: ' . $stmt->error]);
            $stmt->close();
            ob_end_flush();
            exit();
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Doctor is not available on this day. Please check the doctor\'s schedule.']);
            $stmt->close();
            ob_end_flush();
            exit();
        }
        
        $schedule = $result->fetch_assoc();
        $stmt->close();
        
        if (!$schedule || !isset($schedule['start_time']) || !isset($schedule['end_time']) || empty($schedule['start_time']) || empty($schedule['end_time'])) {
            ob_clean();
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Invalid schedule data. Doctor schedule is incomplete.']);
            ob_end_flush();
            exit();
        }
        
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
            
            // Check if slot is already booked in appointments table
            $is_booked_appointment = false;
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments 
                                   WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                                   AND status != 'cancelled'");
            if ($stmt) {
                $stmt->bind_param("iss", $doctor_table_id, $date, $time_slot);
                if ($stmt->execute()) {
                    $check_result = $stmt->get_result();
                    $is_booked_appointment = $check_result->fetch_assoc()['count'] > 0;
                } else {
                    error_log("Error checking appointments: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Also check if slot is already requested in appointment_requests table
            // Note: appointment_requests.doctor_id is users.id, not doctors.id
            // Use TIME_TO_SEC to normalize time comparison (handles both H:i and H:i:s)
            $is_requested = false;
            $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM appointment_requests 
                                    WHERE doctor_id = ? AND preferred_date = ? 
                                    AND TIME_TO_SEC(preferred_time) = TIME_TO_SEC(?)
                                    AND status IN ('Pending', 'Approved')");
            if ($stmt2) {
                // Use time_slot (H:i:s format) - TIME_TO_SEC will normalize it
                $stmt2->bind_param("iss", $user_id, $date, $time_slot);
                if ($stmt2->execute()) {
                    $check_result2 = $stmt2->get_result();
                    $is_requested = $check_result2->fetch_assoc()['count'] > 0;
                } else {
                    error_log("Error checking appointment_requests: " . $stmt2->error);
                }
                $stmt2->close();
            }
            
            // Slot is available only if not booked and not requested
            if (!$is_booked_appointment && !$is_requested) {
                $available_slots[] = [
                    'value' => $time_display,
                    'display' => $current_time->format('g:i A')
                ];
            }
            
            $current_time->add($interval);
        }
        
        ob_clean();
        if (empty($available_slots)) {
            echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'No available time slots for this date. All slots may be booked.']);
        } else {
            echo json_encode([
                'success' => true,
                'time_slots' => $available_slots
            ]);
        }
        ob_end_flush();
        exit();
        
    } elseif ($action === 'my_appointments') {
        // Get doctor's own appointments (for doctor schedules page)
        if (!$is_doctor) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            ob_end_flush();
            exit();
        }
        
        $doctorUserId = $_SESSION['user_id'];
        
        // Get filter values
        $status = $_GET['status'] ?? '';
        $dateRange = $_GET['date_range'] ?? 'all';
        $searchTerm = $_GET['search'] ?? '';
        
        // Base query - using proper join through doctors table
        $baseQuery = "SELECT a.*, 
                             CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                             p.phone,
                             p.email,
                             p.emergency_contact_phone
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.id
                      JOIN doctors d ON a.doctor_id = d.id
                      JOIN users u ON d.user_id = u.id
                      WHERE u.id = ?";
        
        $params = [$doctorUserId];
        $types = "i";
        
        // Add filters
        if ($status) {
            $baseQuery .= " AND a.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($dateRange !== 'all') {
            switch ($dateRange) {
                case 'today':
                    $date = date('Y-m-d');
                    $baseQuery .= " AND a.appointment_date = ?";
                    $params[] = $date;
                    $types .= "s";
                    break;
                case 'week':
                    $weekStart = date('Y-m-d', strtotime('this week monday'));
                    $weekEnd = date('Y-m-d', strtotime('this week sunday'));
                    $baseQuery .= " AND a.appointment_date BETWEEN ? AND ?";
                    $params[] = $weekStart;
                    $params[] = $weekEnd;
                    $types .= "ss";
                    break;
                case 'month':
                    $monthStart = date('Y-m-01');
                    $monthEnd = date('Y-m-t');
                    $baseQuery .= " AND a.appointment_date BETWEEN ? AND ?";
                    $params[] = $monthStart;
                    $params[] = $monthEnd;
                    $types .= "ss";
                    break;
            }
        }
        
        if ($searchTerm) {
            $baseQuery .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR p.phone LIKE ?)";
            $searchPattern = "%$searchTerm%";
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $types .= "ss";
        }
        
        // Get appointments
        $baseQuery .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $conn->prepare($baseQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $appointments = [];
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'appointments' => $appointments
        ]);
        ob_end_flush();
        exit();
        
    } elseif ($action === 'my_appointment_stats') {
        // Get appointment statistics for charts (for doctor schedules page)
        if (!$is_doctor) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            ob_end_flush();
            exit();
        }
        
        $doctorUserId = $_SESSION['user_id'];
        
        // Get appointment status counts for chart (using proper join) - use exact ENUM values
        $statusCounts = [];
        $statuses = ['scheduled', 'ongoing', 'settled', 'cancelled'];
        foreach ($statuses as $s) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count 
                                   FROM appointments a
                                   JOIN doctors d ON a.doctor_id = d.id
                                   JOIN users u ON d.user_id = u.id
                                   WHERE u.id = ? AND a.status = ?");
            $stmt->bind_param("is", $doctorUserId, $s);
            $stmt->execute();
            $statusCounts[$s] = (int)$stmt->get_result()->fetch_assoc()['count'];
            $stmt->close();
        }
        
        // Get daily appointment counts for the last 7 days (using proper join)
        $dailyData = [];
        $dailyLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmt = $conn->prepare("SELECT COUNT(*) as count 
                                   FROM appointments a
                                   JOIN doctors d ON a.doctor_id = d.id
                                   JOIN users u ON d.user_id = u.id
                                   WHERE u.id = ? AND a.appointment_date = ?");
            $stmt->bind_param("is", $doctorUserId, $date);
            $stmt->execute();
            $count = (int)$stmt->get_result()->fetch_assoc()['count'];
            $dailyLabels[] = date('M j', strtotime($date));
            $dailyData[] = $count;
            $stmt->close();
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'status_counts' => $statusCounts,
            'daily_labels' => $dailyLabels,
            'daily_data' => $dailyData
        ]);
        ob_end_flush();
        exit();
        
    } else {
        // Default: Get weekly schedule (existing functionality)
        // For admin: doctor_id is doctors.id, need to convert to users.id
        // For patient: doctor_id is users.id
        
        if ($is_admin) {
            // Convert doctors.id to users.id
            $stmt = $conn->prepare("SELECT user_id FROM doctors WHERE id = ?");
            $stmt->bind_param("i", $doctor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Doctor not found']);
                exit();
            }
            
            $doctor_data = $result->fetch_assoc();
            $user_id = $doctor_data['user_id'];
        } else {
            $user_id = $doctor_id;
        }
        
        // Get doctor's schedule
        $stmt = $conn->prepare("SELECT day_of_week, is_available, start_time, end_time, break_start, break_end 
                               FROM doctor_schedules 
                               WHERE doctor_id = ? 
                               ORDER BY FIELD(day_of_week, '1', '2', '3', '4', '5', '6', '7')");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $schedule = [];
        $days_map = [
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday',
            '7' => 'Sunday'
        ];
        
        while ($row = $result->fetch_assoc()) {
            $day_name = $days_map[$row['day_of_week']] ?? 'Unknown';
            
            if ($row['is_available']) {
                $start = date('g:i A', strtotime($row['start_time']));
                $end = date('g:i A', strtotime($row['end_time']));
                
                $schedule_info = "$start - $end";
                
                if ($row['break_start'] && $row['break_end']) {
                    $break_start = date('g:i A', strtotime($row['break_start']));
                    $break_end = date('g:i A', strtotime($row['break_end']));
                    $schedule_info .= " (Break: $break_start - $break_end)";
                }
                
                $schedule[] = [
                    'day' => $day_name,
                    'day_num' => $row['day_of_week'],
                    'available' => true,
                    'hours' => $schedule_info,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time']
                ];
            } else {
                $schedule[] = [
                    'day' => $day_name,
                    'day_num' => $row['day_of_week'],
                    'available' => false,
                    'hours' => 'Not Available'
                ];
            }
        }
        
        if (empty($schedule)) {
            ob_clean();
            echo json_encode([
                'success' => false, 
                'message' => 'Doctor has not set their schedule yet. Please contact the clinic.'
            ]);
            ob_end_flush();
            exit();
        } else {
            ob_clean();
            echo json_encode([
                'success' => true,
                'schedule' => $schedule
            ]);
            ob_end_flush();
            exit();
        }
    }
    
} catch (Exception $e) {
    // Clear any output that might have been generated
    ob_clean();
    error_log("Error getting doctor availability: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Error loading time slots. Please try again or contact support.']);
    ob_end_flush();
    exit();
} catch (Error $e) {
    // Catch fatal errors as well
    ob_clean();
    error_log("Fatal error getting doctor availability: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'time_slots' => [], 'message' => 'Error loading time slots. Please try again or contact support.']);
    ob_end_flush();
    exit();
}
// Clean output buffer and send response (only if we haven't exited already)
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>

