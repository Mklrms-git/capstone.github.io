<?php
// Prevent direct access
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Patient authentication functions
function isPatientLoggedIn() {
    return isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id']);
}

// Require patient login
function requirePatientLogin() {
	if (!isPatientLoggedIn()) {
		header('Location: patient_login.php');
		exit();
	}

	// Verify that the patient user still exists (may have been deleted while logged in)
	$conn = getDBConnection();
	$stmt = $conn->prepare("SELECT id FROM patient_users WHERE id = ?");
	$stmt->bind_param("i", $_SESSION['patient_user_id']);
	$stmt->execute();
	$result = $stmt->get_result();
	if (!$result || $result->num_rows === 0) {
		// Clear session and redirect with informative message
		session_unset();
		session_destroy();
		header('Location: patient_login.php?deleted=1');
		exit();
	}
}

// Get current patient user
function getCurrentPatientUser() {
    if (!isPatientLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        error_log("Database connection failed in getCurrentPatientUser");
        return null;
    }
    
    // Check if profile_image column exists
    $check_column = $conn->query("SHOW COLUMNS FROM patient_users LIKE 'profile_image'");
    $has_profile_image = $check_column && $check_column->num_rows > 0;
    
    $profile_image_select = $has_profile_image ? ', pu.profile_image' : ', NULL as profile_image';
    
    $stmt = $conn->prepare("SELECT pu.*, p.first_name, p.last_name, p.email, p.phone, p.date_of_birth, p.sex" . $profile_image_select . " 
                           FROM patient_users pu 
                           JOIN patients p ON pu.patient_id = p.id 
                           WHERE pu.id = ?");
    if (!$stmt) {
        error_log("Failed to prepare statement in getCurrentPatientUser: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $_SESSION['patient_user_id']);
    if (!$stmt->execute()) {
        error_log("Failed to execute query in getCurrentPatientUser: " . $stmt->error);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user_data) {
        error_log("No patient user data found for ID: " . $_SESSION['patient_user_id']);
    }
    
    return $user_data;
}

// Check if patient user has specific status
function hasPatientStatus($status) {
    $user = getCurrentPatientUser();
    return $user && $user['status'] === $status;
}

// Require patient status
function requirePatientStatus($status) {
    if (!hasPatientStatus($status)) {
        header('Location: patient_login.php');
        exit();
    }
}

// Send email notification
function sendEmailNotification($to_email, $to_name, $subject, $body, $body_type = 'html') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO email_queue (to_email, to_name, subject, body, body_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $to_email, $to_name, $subject, $body, $body_type);
    return $stmt->execute();
}

// Create notification
function createNotification($recipient_type, $recipient_id, $type, $title, $message, $sent_via = 'System') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, type, title, message, sent_via, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        error_log("Failed to prepare notification statement: " . $conn->error);
        return false;
    }
    $stmt->bind_param("sissss", $recipient_type, $recipient_id, $type, $title, $message, $sent_via);
    $result = $stmt->execute();
    if (!$result) {
        error_log("Failed to create notification: " . $stmt->error);
    }
    $stmt->close();
    return $result;
}

// Get patient notifications
function getPatientNotifications($patient_user_id, $limit = 10) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE recipient_type = 'Patient' AND recipient_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $patient_user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Mark notification as read
function markNotificationAsRead($notification_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    return $stmt->execute();
}

// Get unread notification count
function getUnreadNotificationCount($patient_user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_type = 'Patient' AND recipient_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $patient_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Create notification for all admins
function createAdminNotification($type, $title, $message, $sent_via = 'System') {
    $conn = getDBConnection();
    
    // Get all admin user IDs
    $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'Admin' AND status = 'Active'");
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    
    $notifications_created = 0;
    $recipient_type = 'Admin';
    
    // Temporarily disable foreign key check for admin notifications
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    while ($admin = $admin_result->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, type, title, message, sent_via) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissss", $recipient_type, $admin['id'], $type, $title, $message, $sent_via);
        
        if ($stmt->execute()) {
            $notifications_created++;
        }
    }
    
    // Re-enable foreign key check
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    return $notifications_created > 0;
}

// Get unread admin notification count
function getUnreadAdminNotificationCount($admin_user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_type = 'Admin' AND recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $admin_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

// Get admin notifications
function getAdminNotifications($admin_user_id, $limit = 50) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE recipient_type = 'Admin' AND recipient_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $admin_user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Check if appointment time is available
function isAppointmentTimeAvailable($doctor_id, $appointment_date, $appointment_time) {
    $conn = getDBConnection();
    
    // Check doctor schedule
    // date('N') returns 1-7 (Monday-Sunday), convert to string to match VARCHAR in database
    $day_of_week = (string)date('N', strtotime($appointment_date)); // '1' = Monday, '7' = Sunday
    $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1");
    $stmt->bind_param("is", $doctor_id, $day_of_week);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false; // Doctor not available on this day
    }
    
    $schedule = $result->fetch_assoc();
    $appointment_time_obj = new DateTime($appointment_time);
    $start_time_obj = new DateTime($schedule['start_time']);
    $end_time_obj = new DateTime($schedule['end_time']);
    
    // Check if appointment time is within doctor's working hours
    if ($appointment_time_obj < $start_time_obj || $appointment_time_obj >= $end_time_obj) {
        return false;
    }
    
    // Check break time
    if ($schedule['break_start'] && $schedule['break_end']) {
        $break_start_obj = new DateTime($schedule['break_start']);
        $break_end_obj = new DateTime($schedule['break_end']);
        
        if ($appointment_time_obj >= $break_start_obj && $appointment_time_obj < $break_end_obj) {
            return false;
        }
    }
    
    // Check if doctor is on leave for this date
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM doctor_leaves 
                           WHERE doctor_id = ? AND status = 'Active' 
                           AND ? BETWEEN start_date AND end_date");
    $stmt->bind_param("is", $doctor_id, $appointment_date);
    $stmt->execute();
    $leave_result = $stmt->get_result();
    $is_on_leave = $leave_result->fetch_assoc()['count'] > 0;
    $stmt->close();
    
    if ($is_on_leave) {
        return false; // Doctor is on leave
    }
    
    // Get doctors.id from users.id (appointments.doctor_id is doctors.id, not users.id)
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $doctor_result = $stmt->get_result();
    if ($doctor_result->num_rows === 0) {
        return false; // Doctor not found
    }
    $doctor_data = $doctor_result->fetch_assoc();
    $doctor_table_id = $doctor_data['id'];
    
    // Normalize time format for comparison (handle both H:i and H:i:s)
    $time_normalized = $appointment_time;
    if (strlen($time_normalized) === 5) {
        // If H:i format, convert to H:i:s
        $time_normalized .= ':00';
    }
    
    // Check for existing appointments - use exact ENUM value
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments 
                           WHERE doctor_id = ? AND appointment_date = ? 
                           AND TIME(appointment_time) = TIME(?) 
                           AND status = 'scheduled'");
    $stmt->bind_param("iss", $doctor_table_id, $appointment_date, $time_normalized);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $is_booked = $row['count'] > 0;
    
    // Also check for pending/approved appointment requests
    // Note: appointment_requests.doctor_id is users.id
    // Use TIME_TO_SEC to normalize time comparison (handles both H:i and H:i:s)
    $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM appointment_requests 
                            WHERE doctor_id = ? AND preferred_date = ? 
                            AND TIME_TO_SEC(preferred_time) = TIME_TO_SEC(?)
                            AND status IN ('Pending', 'Approved')");
    $stmt2->bind_param("iss", $doctor_id, $appointment_date, $time_normalized);
    if ($stmt2->execute()) {
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $is_requested = $row2['count'] > 0;
    } else {
        error_log("Error checking appointment_requests in isAppointmentTimeAvailable: " . $stmt2->error);
        $is_requested = false;
    }
    $stmt2->close();
    
    // Time slot is available only if not booked and not requested
    return !$is_booked && !$is_requested;
}

// Get available time slots for a doctor on a specific date
function getAvailableTimeSlots($doctor_id, $appointment_date) {
    $conn = getDBConnection();
    $available_slots = [];
    
    // Check if doctor is on leave for this date
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM doctor_leaves 
                           WHERE doctor_id = ? AND status = 'Active' 
                           AND ? BETWEEN start_date AND end_date");
    $stmt->bind_param("is", $doctor_id, $appointment_date);
    $stmt->execute();
    $leave_result = $stmt->get_result();
    $is_on_leave = $leave_result->fetch_assoc()['count'] > 0;
    $stmt->close();
    
    if ($is_on_leave) {
        return $available_slots; // Doctor is on leave, return empty array
    }
    
    // Get doctor schedule for the day
    // date('N') returns 1-7 (Monday-Sunday), convert to string to match VARCHAR in database
    $day_of_week = (string)date('N', strtotime($appointment_date)); // '1' = Monday, '7' = Sunday
    $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1");
    $stmt->bind_param("is", $doctor_id, $day_of_week);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return $available_slots; // Doctor not available
    }
    
    $schedule = $result->fetch_assoc();
    $start_time = new DateTime($schedule['start_time']);
    $end_time = new DateTime($schedule['end_time']);
    $break_start = $schedule['break_start'] ? new DateTime($schedule['break_start']) : null;
    $break_end = $schedule['break_end'] ? new DateTime($schedule['break_end']) : null;
    
    // Generate 30-minute slots
    $current_time = clone $start_time;
    $interval = new DateInterval('PT30M');
    
    // Check if appointment is for today
    $is_today = (date('Y-m-d') === $appointment_date);
    $current_hour = new DateTime();
    
    while ($current_time < $end_time) {
        $time_slot = $current_time->format('H:i:s');
        
        // Skip past time slots if appointment is for today
        if ($is_today && $current_time <= $current_hour) {
            $current_time->add($interval);
            continue;
        }
        
        // Skip break time
        if ($break_start && $break_end && $current_time >= $break_start && $current_time < $break_end) {
            $current_time->add($interval);
            continue;
        }
        
        // Check if slot is available
        if (isAppointmentTimeAvailable($doctor_id, $appointment_date, $time_slot)) {
            $available_slots[] = $time_slot;
        }
        
        $current_time->add($interval);
    }
    
    return $available_slots;
}

// Get doctors by department
function getDoctorsByDepartment($department_id) {
    $conn = getDBConnection();
    
    // Check if doctor_departments table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'doctor_departments'");
    $useNewTable = $checkTable && $checkTable->num_rows > 0;
    
    if ($useNewTable) {
        // Use the new doctor_departments table (supports multiple departments per doctor)
        $stmt = $conn->prepare("SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, 
                                       COALESCE(dd.specialization, u.specialization) as specialization,
                                       u.phone, u.profile_image, u.license_number, 
                                       dept.id as department_id, dept.name as department_name,
                                       doc.id as doctor_id
                               FROM users u 
                               INNER JOIN doctors doc ON u.id = doc.user_id
                               LEFT JOIN doctor_departments dd ON u.id = dd.doctor_id AND dd.department_id = ?
                               LEFT JOIN departments dept ON (dd.department_id = dept.id OR doc.department_id = dept.id)
                               WHERE u.role = 'Doctor' 
                               AND (dd.department_id = ? OR doc.department_id = ?)
                               AND u.status = 'Active' 
                               AND u.is_available = 1
                               ORDER BY u.first_name, u.last_name");
        $stmt->bind_param("iii", $department_id, $department_id, $department_id);
    } else {
        // Fallback to old method (single department per doctor)
        $stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.specialization, 
                                       u.phone, u.profile_image, u.license_number, 
                                       dept.id as department_id, dept.name as department_name,
                                       doc.id as doctor_id
                               FROM users u 
                               INNER JOIN doctors doc ON u.id = doc.user_id
                               INNER JOIN departments dept ON doc.department_id = dept.id 
                               WHERE u.role = 'Doctor' 
                               AND doc.department_id = ? 
                               AND u.status = 'Active' 
                               AND u.is_available = 1
                               ORDER BY u.first_name, u.last_name");
        $stmt->bind_param("i", $department_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all departments
function getAllDepartments() {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM departments ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Log patient activity
function logPatientActivity($patient_user_id, $action, $ip_address = null) {
    if (!$ip_address) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $patient_user_id, $action, $ip_address);
    return $stmt->execute();
}
