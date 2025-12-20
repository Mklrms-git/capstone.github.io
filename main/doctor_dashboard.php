<?php
// Start output buffering to prevent any output before AJAX responses
ob_start();

define('MHAVIS_EXEC', true);
$page_title = "Doctor Dashboard";
$active_page = "dashboard";

require_once __DIR__ . '/config/init.php';

// Check if this is an AJAX request for notifications - handle auth before requireDoctor()
if ($_POST && isset($_POST['ajax']) && in_array($_POST['ajax'], ['doctor_mark_all_read', 'doctor_mark_read', 'doctor_delete'])) {
    ini_set('display_errors', 0);
    error_reporting(0);
    
    // Clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // For AJAX requests, check auth manually and return JSON instead of redirecting
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    
    // Check if patient is logged in (should not access doctor features)
    if (isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit;
    }
    
    // Check if user is a doctor
    if (!isDoctor()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Doctor access required.']);
        exit;
    }
    
    // Get database connection and doctor ID
    $conn = getDBConnection();
    $doctorId = $_SESSION['user_id'];
    
    // Handle AJAX: Doctor mark all notifications as read
    if ($_POST['ajax'] === 'doctor_mark_all_read') {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        try {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_type = 'Doctor' AND recipient_id = ? AND is_read = 0");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("i", $doctorId);
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $response = json_encode(['success' => true, 'message' => 'All notifications marked as read', 'count' => $affected_rows]);
                if ($response === false) {
                    $response = json_encode(['success' => false, 'message' => 'JSON encoding error']);
                }
                echo $response;
            } else {
                $error_msg = $stmt->error ? $stmt->error : 'Unknown database error';
                echo json_encode(['success' => false, 'message' => 'Failed to update notifications: ' . $error_msg]);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } catch (Error $e) {
            echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Handle AJAX: Doctor mark single notification as read
    if ($_POST['ajax'] === 'doctor_mark_read') {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        try {
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'Doctor' AND recipient_id = ?");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("ii", $id, $doctorId);
            if ($stmt->execute()) {
                $success = $stmt->affected_rows > 0;
                echo json_encode(['success' => $success, 'message' => $success ? 'Notification marked as read' : 'Notification not found']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update notification: ' . $stmt->error]);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } catch (Error $e) {
            echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Handle AJAX: Doctor delete notification
    if ($_POST['ajax'] === 'doctor_delete') {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        try {
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND recipient_type = 'Doctor' AND recipient_id = ?");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("ii", $id, $doctorId);
            if ($stmt->execute()) {
                $success = $stmt->affected_rows > 0;
                echo json_encode(['success' => $success, 'message' => $success ? 'Notification deleted' : 'Notification not found']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete notification: ' . $stmt->error]);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } catch (Error $e) {
            echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// For non-AJAX requests, require doctor authentication
requireDoctor();

// Get database connection and doctor ID
$conn = getDBConnection();
$doctorId = $_SESSION['user_id'];

// Initialize messages
$success_message = '';
$error_message = '';

// Handle AJAX requests for appointment status updates
if ($_POST && isset($_POST['update_appointment_status'])) {
    header('Content-Type: application/json');
    
    $appointmentId = intval($_POST['appointmentId'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $notes = $_POST['notes'] ?? '';
    
    // Validate status against database ENUM values (case-sensitive)
    $valid_statuses = ['scheduled', 'ongoing', 'settled', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value. Allowed: ' . implode(', ', $valid_statuses)]);
        exit;
    }
    
    // Verify doctor has access to this appointment
    $stmt = $conn->prepare("SELECT a.id 
                           FROM appointments a
                           JOIN doctors d ON a.doctor_id = d.id
                           JOIN users u ON d.user_id = u.id
                           WHERE a.id = ? AND u.id = ?");
    $stmt->bind_param("ii", $appointmentId, $doctorId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update appointment status
        $updateStmt = $conn->prepare("UPDATE appointments a
                                     JOIN doctors d ON a.doctor_id = d.id
                                     JOIN users u ON d.user_id = u.id
                                     SET a.status = ?, a.notes = ?, a.updated_at = NOW() 
                                     WHERE a.id = ? AND u.id = ?");
        $updateStmt->bind_param("ssii", $status, $notes, $appointmentId, $doctorId);
        
        if ($updateStmt->execute()) {
            // Check if any rows were actually updated
            if ($updateStmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes were made. Status may already be set to this value.']);
            }
        } else {
            // Return actual database error for debugging
            $error_msg = $conn->error ?: 'Unknown database error';
            error_log("Appointment status update failed: " . $error_msg);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $error_msg]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
    }
    exit;
}

// Handle schedule update
if ($_POST && isset($_POST['update_schedule'])) {
    foreach ($_POST['schedule'] as $day => $schedule_data) {
        $is_available = isset($schedule_data['is_available']) ? 1 : 0;
        $start_time = $schedule_data['start_time'] ?? null;
        $end_time = $schedule_data['end_time'] ?? null;
        $break_start = $schedule_data['break_start'] ?? null;
        $break_end = $schedule_data['break_end'] ?? null;
        
        // Validate time inputs if available
        if ($is_available && (!$start_time || !$end_time)) {
            $error_message = "Please provide start and end times for available days";
            break;
        }
        
        // Check if schedule exists for this day
        $stmt = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
        $stmt->bind_param("ii", $doctorId, $day);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing schedule
            $stmt = $conn->prepare("UPDATE doctor_schedules SET is_available = ?, start_time = ?, end_time = ?, break_start = ?, break_end = ?, updated_at = NOW() WHERE doctor_id = ? AND day_of_week = ?");
            $stmt->bind_param("issssii", $is_available, $start_time, $end_time, $break_start, $break_end, $doctorId, $day);
        } else {
            // Insert new schedule
            $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, is_available, start_time, end_time, break_start, break_end, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("iiissss", $doctorId, $day, $is_available, $start_time, $end_time, $break_start, $break_end);
        }
        
        if (!$stmt->execute()) {
            $error_message = "Error updating schedule for " . date('l', strtotime("Sunday +{$day} days"));
            break;
        }
    }
    
    if (!$error_message) {
        $success_message = "Schedule updated successfully!";
    }
}

// AJAX: Patient quick view
if ($_POST && isset($_POST['ajax']) && $_POST['ajax'] === 'patient_quick_view') {
    header('Content-Type: text/html; charset=UTF-8');
    $patientId = intval($_POST['patient_id'] ?? 0);

    $stmt = $conn->prepare("SELECT p.*, 
                            CONCAT(p.first_name, ' ', p.last_name) AS full_name
                            FROM patients p
                            WHERE p.id = ?");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();

    if (!$patient) {
        echo '<div class="alert alert-warning mb-0">Patient not found.</div>';
        exit;
    }

    $ageText = 'N/A';
    if (!empty($patient['date_of_birth'])) {
        $birthDate = new DateTime($patient['date_of_birth']);
        $ageText = $birthDate->diff(new DateTime())->y . ' years';
    }

    echo '
        <div class="row">
            <div class="col-md-6 mb-3">
                <div><strong>Name:</strong> ' . htmlspecialchars($patient['full_name']) . '</div>
                <div><strong>Age:</strong> ' . htmlspecialchars($ageText) . '</div>
                <div><strong>Gender:</strong> ' . htmlspecialchars($patient['gender'] ?? 'N/A') . '</div>
            </div>
            <div class="col-md-6 mb-3">
                <div><strong>Phone:</strong> ' . htmlspecialchars(formatPhoneNumber($patient['phone'] ?? '') ?: 'N/A') . '</div>
                <div><strong>Email:</strong> ' . htmlspecialchars($patient['email'] ?? 'N/A') . '</div>
            </div>
            <div class="col-12">
                <div><strong>Address:</strong> ' . htmlspecialchars($patient['address'] ?? 'N/A') . '</div>
                <div class="mt-2"><strong>Notes:</strong><br>' . nl2br(htmlspecialchars($patient['notes'] ?? '')) . '</div>
            </div>
        </div>
    ';
    exit;
}

// AJAX: Patient history (for this doctor)
if ($_POST && isset($_POST['ajax']) && $_POST['ajax'] === 'patient_history') {
    header('Content-Type: text/html; charset=UTF-8');
    $patientId = intval($_POST['patient_id'] ?? 0);

    $stmt = $conn->prepare("SELECT a.appointment_date, a.appointment_time, a.status, a.notes
                            FROM appointments a
                            JOIN doctors d ON a.doctor_id = d.id
                            JOIN users u ON d.user_id = u.id
                            WHERE a.patient_id = ? AND u.id = ?
                            ORDER BY a.appointment_date DESC, a.appointment_time DESC
                            LIMIT 15");
    $stmt->bind_param("ii", $patientId, $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo '<div class="text-muted">No history recorded with you yet.</div>';
        exit;
    }

    echo '<div class="list-group">';
    while ($r = $res->fetch_assoc()) {
        // Status badge class - consistent mapping
        $statusLower = strtolower(trim($r['status'] ?? ''));
        $statusClass = match($statusLower) {
            'scheduled' => 'primary',
            'ongoing' => 'warning',
            'settled' => 'success',
            'cancelled', 'canceled' => 'danger',
            default => 'secondary'
        };
        
        echo '<div class="list-group-item">';
        echo '<div class="d-flex justify-content-between">';
        echo '<strong>' . date('M d, Y', strtotime($r['appointment_date'])) . ' ' . date('h:i A', strtotime($r['appointment_time'])) . '</strong>';
        echo '<span class="badge bg-' . $statusClass . '">' . htmlspecialchars($r['status']) . '</span>';
        echo '</div>';
        if (!empty($r['notes'])) {
            echo '<div class="small text-muted mt-1">' . nl2br(htmlspecialchars($r['notes'])) . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    exit;
}

// AJAX: Create appointment for patient
if ($_POST && isset($_POST['create_appointment'])) {
    header('Content-Type: application/json');
    $patientId = intval($_POST['patient_id'] ?? 0);
    $appointmentDate = $_POST['appointment_date'] ?? '';
    $appointmentTime = $_POST['appointment_time'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (!$patientId || !$appointmentDate || !$appointmentTime) {
        echo json_encode(['success' => false, 'message' => 'Please provide date and time.']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, notes, created_at, updated_at)
                            VALUES (?, (SELECT id FROM doctors WHERE user_id = ? LIMIT 1), ?, ?, 'scheduled', ?, NOW(), NOW())");
    $stmt->bind_param("iisss", $patientId, $doctorId, $appointmentDate, $appointmentTime, $notes);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appointment scheduled successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to schedule appointment.']);
    }
    exit;
}
// Get doctor statistics - FIXED: Only for this doctor's patients
function getDoctorStats($doctorId, $conn) {
    $stats = [];
    
    // Total patients under this doctor (from appointments table) - FIXED
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT a.patient_id) as count 
                           FROM appointments a
                           JOIN doctors d ON a.doctor_id = d.id
                           JOIN users u ON d.user_id = u.id
                           WHERE u.id = ?");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $stats['total_patients'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Today's appointments for this doctor only - ALREADY CORRECT
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count 
                           FROM appointments a
                           JOIN doctors d ON a.doctor_id = d.id
                           JOIN users u ON d.user_id = u.id
                           WHERE u.id = ? AND a.appointment_date = ? AND a.status != 'cancelled'");
    $stmt->bind_param("is", $doctorId, $today);
    $stmt->execute();
    $stats['today_appointments'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // This week's appointments for this doctor only - ALREADY CORRECT
    $weekStart = date('Y-m-d', strtotime('this week monday'));
    $weekEnd = date('Y-m-d', strtotime('this week sunday'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count 
                           FROM appointments a
                           JOIN doctors d ON a.doctor_id = d.id
                           JOIN users u ON d.user_id = u.id
                           WHERE u.id = ? AND a.appointment_date BETWEEN ? AND ? AND a.status != 'cancelled'");
    $stmt->bind_param("iss", $doctorId, $weekStart, $weekEnd);
    $stmt->execute();
    $stats['weekly_appointments'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Completed appointments this month for this doctor only - ALREADY CORRECT
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count 
                           FROM appointments a
                           JOIN doctors d ON a.doctor_id = d.id
                           JOIN users u ON d.user_id = u.id
                           WHERE u.id = ? AND a.status = 'settled' AND a.appointment_date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $doctorId, $monthStart, $monthEnd);
    $stmt->execute();
    $stats['completed_appointments'] = $stmt->get_result()->fetch_assoc()['count'];
    
    return $stats;
}

// Get monthly data for charts - FIXED: Only for this doctor
function getMonthlyData($doctorId, $conn) {
    $monthlyAppointmentsData = [];
    $monthlyCompletedData = [];
    
    for ($i = 0; $i < 6; $i++) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        
        // Total appointments for this doctor (excluding cancelled) - ALREADY CORRECT
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM appointments a
                               JOIN doctors d ON a.doctor_id = d.id
                               JOIN users u ON d.user_id = u.id
                               WHERE u.id = ? AND a.appointment_date BETWEEN ? AND ? AND a.status != 'cancelled'");
        $stmt->bind_param("iss", $doctorId, $monthStart, $monthEnd);
        $stmt->execute();
        $monthlyAppointmentsData[date('M Y', strtotime($month))] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Completed appointments for this doctor - ALREADY CORRECT
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM appointments a
                               JOIN doctors d ON a.doctor_id = d.id
                               JOIN users u ON d.user_id = u.id
                               WHERE u.id = ? AND a.status = 'settled' AND a.appointment_date BETWEEN ? AND ?");
        $stmt->bind_param("iss", $doctorId, $monthStart, $monthEnd);
        $stmt->execute();
        $monthlyCompletedData[date('M Y', strtotime($month))] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // Reverse arrays to show oldest to newest
    return [
        'appointments' => array_reverse($monthlyAppointmentsData),
        'completed' => array_reverse($monthlyCompletedData)
    ];
}

// Get all statistics
$stats = getDoctorStats($doctorId, $conn);
$monthlyData = getMonthlyData($doctorId, $conn);

// Prepare data for JavaScript
$chartLabels = json_encode(array_keys($monthlyData['appointments']));
$appointmentsData = json_encode(array_values($monthlyData['appointments']));
$completedData = json_encode(array_values($monthlyData['completed']));

// Doctor notifications (recipient_type = 'Doctor', recipient_id = current user id)
$doctorNotifications = [];
$doctorUnreadCount = 0;

// Pagination parameters
$notif_page = isset($_GET['notif_page']) ? max(1, (int)$_GET['notif_page']) : 1;
$notif_limit = 5;
$notif_offset = ($notif_page - 1) * $notif_limit;

// Get total count
$total_notif_stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications 
                                    WHERE recipient_type = 'Doctor' AND recipient_id = ?");
$total_notifications = 0;
if ($total_notif_stmt) {
    $total_notif_stmt->bind_param("i", $doctorId);
    if ($total_notif_stmt->execute()) {
        $total_result = $total_notif_stmt->get_result()->fetch_assoc();
        $total_notifications = $total_result ? (int)$total_result['total'] : 0;
    }
    $total_notif_stmt->close();
}

$notifStmt = $conn->prepare("SELECT * FROM notifications
                             WHERE recipient_type = 'Doctor' AND recipient_id = ?
                             ORDER BY created_at DESC
                             LIMIT ? OFFSET ?");
$notifStmt->bind_param("iii", $doctorId, $notif_limit, $notif_offset);
$notifStmt->execute();
$doctorNotifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notifStmt->close();

$total_notif_pages = ceil($total_notifications / $notif_limit);

$unreadStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications
                              WHERE recipient_type = 'Doctor' AND recipient_id = ?
                              AND is_read = 0");
$unreadStmt->bind_param("i", $doctorId);
$unreadStmt->execute();
$doctorUnreadCount = ($unreadStmt->get_result()->fetch_assoc()['cnt']) ?? 0;

include 'includes/header.php';
?>

<style>
.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.calendar-container {
    min-height: 400px;
}
.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}
.btn-group .btn {
    margin-right: 2px;
}
.badge {
    font-size: 0.75em;
}
.alert {
    border-radius: 8px;
    border: none;
}
.card {
    border-radius: 10px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 10px 10px 0 0 !important;
}
</style>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>



<!-- Overview Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3">
                        <div class="text-center">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="col-9 text-end">
                        <h3 class="mb-1 fw-bold"><?php echo $stats['total_patients']; ?></h3>
                        <div class="text-muted small">My Patients</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3">
                        <div class="text-center">
                            <i class="fas fa-calendar-day fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="col-9 text-end">
                        <h3 class="mb-1 fw-bold"><?php echo $stats['today_appointments']; ?></h3>
                        <div class="text-muted small">Today's Appointments</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3">
                        <div class="text-center">
                            <i class="fas fa-calendar-week fa-2x text-info"></i>
                        </div>
                    </div>
                    <div class="col-9 text-end">
                        <h3 class="mb-1 fw-bold"><?php echo $stats['weekly_appointments']; ?></h3>
                        <div class="text-muted small">This Week</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3">
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-2x text-warning"></i>
                        </div>
                    </div>
                    <div class="col-9 text-end">
                        <h3 class="mb-1 fw-bold"><?php echo $stats['completed_appointments']; ?></h3>
                        <div class="text-muted small">Completed This Month</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar and Chart Row -->
<div class="row mb-4">
    <div class="col-xl-7 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>My Appointment Calendar
                </h5>
                <a href="my_appointments.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-list me-1"></i>View All
                </a>
            </div>
            <div class="card-body calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
    <div class="col-xl-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>Appointment Statistics
                </h5>
            </div>
            <div class="card-body">
                <canvas id="appointmentsChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Today's Appointments -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-clock me-2"></i>Today's Appointments
        </h5>
        <small class="text-muted">
            <i class="fas fa-calendar-day me-1"></i><?php echo date('M d, Y'); ?>
        </small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th><i class="fas fa-clock me-1"></i>Time</th>
                        <th><i class="fas fa-user me-1"></i>Patient</th>
                        <th><i class="fas fa-phone me-1"></i>Contact</th>
                        <th><i class="fas fa-info-circle me-1"></i>Status</th>
                        <th><i class="fas fa-cogs me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $today = date('Y-m-d');
                    // FIXED: Added doctor_id filter to ensure only this doctor's appointments
                    $query = "SELECT a.*, 
                                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                                p.phone, p.email, p.date_of_birth
                             FROM appointments a
                             JOIN patients p ON a.patient_id = p.id
                             JOIN doctors d ON a.doctor_id = d.id
                             JOIN users u ON d.user_id = u.id
                             WHERE u.id = ? AND a.appointment_date = ?
                             ORDER BY a.appointment_time";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("is", $doctorId, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $statusLower = strtolower(trim($row['status'] ?? ''));
                            $statusClass = match($statusLower) {
                                'scheduled' => 'primary',
                                'ongoing' => 'warning',
                                'settled' => 'success',
                                'cancelled', 'canceled' => 'danger',
                                default => 'light'
                            };
                            
                            $age = '';
                            if ($row['date_of_birth']) {
                                $birthDate = new DateTime($row['date_of_birth']);
                                $today_dt = new DateTime();
                                $age = $birthDate->diff($today_dt)->y;
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['patient_name']); ?></strong>
                                        <?php if ($age): ?>
                                            <small class="text-muted d-block"><?php echo $age; ?> years old</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['phone']): ?>
                                        <small class="d-block">
                                            <i class="fas fa-phone text-success"></i> 
                                            <?php echo htmlspecialchars(formatPhoneNumber($row['phone'])); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($row['email']): ?>
                                        <small class="d-block text-muted">
                                            <i class="fas fa-envelope"></i> 
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view_patient.php?id=<?php echo $row['patient_id']; ?>" 
                                           class="btn btn-sm btn-outline-info" title="View Patient">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <a href="medical_record.php?appointment_id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Medical Record">
                                            <i class="fas fa-file-medical"></i>
                                        </a>
                                        <?php 
                                        $statusLower = strtolower(trim($row['status'] ?? ''));
                                        if ($statusLower !== 'settled' && $statusLower !== 'cancelled' && $statusLower !== 'canceled'): ?>
                                        <button class="btn btn-sm btn-outline-success update-status" 
                                                data-id="<?php echo $row['id']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#updateStatusModal" 
                                                title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="5" class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                                No appointments scheduled for today
                              </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- My Patients Summary -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-users me-2"></i>My Patients
        </h5>
        <a href="Patients.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-list me-1"></i>View All Patients
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th><i class="fas fa-user me-1"></i>Patient Name</th>
                        <th><i class="fas fa-birthday-cake me-1"></i>Age</th>
                        <th><i class="fas fa-history me-1"></i>Last Visit</th>
                        <th><i class="fas fa-calendar-plus me-1"></i>Next Appointment</th>
                        <th><i class="fas fa-chart-bar me-1"></i>Total Visits</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Pagination setup for My Patients
                    $patients_limit = 8; // Maximum 8 patients per page
                    $patients_page = isset($_GET['patients_page']) ? max(1, (int)$_GET['patients_page']) : 1;
                    $patients_offset = ($patients_page - 1) * $patients_limit;
                    
                    // Count query for total patients
                    $countQuery = "SELECT COUNT(DISTINCT p.id) as total
                                   FROM patients p
                                   WHERE p.id IN (
                                       SELECT DISTINCT a5.patient_id 
                                       FROM appointments a5
                                       JOIN doctors d5 ON a5.doctor_id = d5.id
                                       JOIN users u5 ON d5.user_id = u5.id
                                       WHERE u5.id = ?
                                   )";
                    
                    $countStmt = $conn->prepare($countQuery);
                    $countStmt->bind_param("i", $doctorId);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $totalPatients = $countResult->fetch_assoc()['total'];
                    $totalPatientsPages = ceil($totalPatients / $patients_limit);
                    $countStmt->close();
                    
                    // FIXED: Ensured all subqueries include doctor_id filter
                    $query = "SELECT DISTINCT p.id, p.first_name, p.last_name, p.date_of_birth,
                             (SELECT MAX(appointment_date) 
                              FROM appointments a2
                              JOIN doctors d2 ON a2.doctor_id = d2.id
                              JOIN users u2 ON d2.user_id = u2.id
                              WHERE a2.patient_id = p.id AND u2.id = ? AND a2.status = 'settled') as last_appointment,
                             (SELECT MIN(appointment_date) 
                              FROM appointments a3
                              JOIN doctors d3 ON a3.doctor_id = d3.id
                              JOIN users u3 ON d3.user_id = u3.id
                              WHERE a3.patient_id = p.id AND u3.id = ? AND a3.appointment_date >= CURDATE() 
                              AND a3.status = 'scheduled') as next_appointment,
                             (SELECT COUNT(*) 
                              FROM appointments a4
                              JOIN doctors d4 ON a4.doctor_id = d4.id
                              JOIN users u4 ON d4.user_id = u4.id
                              WHERE a4.patient_id = p.id AND u4.id = ? AND a4.status = 'settled') as total_visits
                             FROM patients p
                             WHERE p.id IN (
                                 SELECT DISTINCT a5.patient_id 
                                 FROM appointments a5
                                 JOIN doctors d5 ON a5.doctor_id = d5.id
                                 JOIN users u5 ON d5.user_id = u5.id
                                 WHERE u5.id = ?
                             )
                             ORDER BY p.last_name, p.first_name
                             LIMIT ? OFFSET ?";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iiiiii", $doctorId, $doctorId, $doctorId, $doctorId, $patients_limit, $patients_offset);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Calculate age
                            $age = '';
                            if ($row['date_of_birth']) {
                                $birthDate = new DateTime($row['date_of_birth']);
                                $today_dt = new DateTime();
                                $age = $birthDate->diff($today_dt)->y;
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                </td>
                                <td><?php echo $age ? $age . ' years' : 'N/A'; ?></td>
                                <td>
                                    <?php echo $row['last_appointment'] ? 
                                        '<span class="text-success">' . date('M d, Y', strtotime($row['last_appointment'])) . '</span>' : 
                                        '<span class="text-muted">No visits</span>'; ?>
                                </td>
                                <td>
                                    <?php echo $row['next_appointment'] ? 
                                        '<span class="text-primary">' . date('M d, Y', strtotime($row['next_appointment'])) . '</span>' : 
                                        '<span class="text-muted">None scheduled</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $row['total_visits']; ?></span>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="5" class="text-center text-muted py-4">
                                <i class="fas fa-user-plus fa-2x mb-2 d-block"></i>
                                No patients assigned yet. Patients will appear here once they have appointments with you.
                              </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Controls for My Patients -->
        <?php if ($totalPatientsPages > 1): ?>
            <div class="mt-3">
                <nav aria-label="My Patients pagination">
                    <ul class="pagination justify-content-center mb-2">
                        <!-- Previous button -->
                        <li class="page-item <?php echo $patients_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?patients_page=<?php echo max(1, $patients_page - 1); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Page numbers -->
                        <?php
                        $startPage = max(1, $patients_page - 2);
                        $endPage = min($totalPatientsPages, $patients_page + 2);
                        
                        // Show first page if not in range
                        if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?patients_page=1">1</a>
                            </li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo $i == $patients_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?patients_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Show last page if not in range -->
                        <?php if ($endPage < $totalPatientsPages): ?>
                            <?php if ($endPage < $totalPatientsPages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?patients_page=<?php echo $totalPatientsPages; ?>"><?php echo $totalPatientsPages; ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Next button -->
                        <li class="page-item <?php echo $patients_page >= $totalPatientsPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?patients_page=<?php echo min($totalPatientsPages, $patients_page + 1); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center text-muted small">
                    Showing <?php echo ($patients_offset + 1); ?>-<?php echo min($patients_offset + $patients_limit, $totalPatients); ?> of <?php echo $totalPatients; ?> patients
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">
                    <i class="fas fa-edit me-2"></i>Update Appointment Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <input type="hidden" id="appointmentId" name="appointmentId">
                    <div class="mb-3">
                        <label for="status" class="form-label">
                            <i class="fas fa-info-circle me-1"></i>Status
                        </label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="settled">Settled</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">
                            <i class="fas fa-sticky-note me-1"></i>Notes (Optional)
                        </label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Add any relevant notes about this status change..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
                <button type="button" class="btn btn-success" id="saveStatus">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Patient Quick View Modal -->
<div class="modal fade" id="patientQuickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user me-2"></i>Patient Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="patientQuickViewBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 small text-muted">Loading...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Patient History Modal -->
<div class="modal fade" id="patientHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i>Patient History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="patientHistoryBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 small text-muted">Loading...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Appointment Modal -->
<div class="modal fade" id="scheduleAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleAppointmentForm">
                    <input type="hidden" id="schedule_patient_id" name="patient_id">
                    <div class="mb-2">
                        <label class="form-label">Patient</label>
                        <input type="text" class="form-control" id="schedule_patient_name" disabled>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" id="schedule_date" name="appointment_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" id="schedule_time" name="appointment_time" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="schedule_notes" name="notes" rows="3" placeholder="Optional notes"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="saveScheduleBtn">
                    <i class="fas fa-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Doctor Notifications Modal -->
<div class="modal fade" id="doctorNotificationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-bell me-2"></i>Notifications
                </h5>
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm btn-outline-primary me-2" id="markAllDoctorNotifications">
                        <i class="fas fa-check-double me-1"></i>Mark all as read
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <?php if (empty($doctorNotifications)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-bell-slash fa-2x mb-2"></i>
                        <div>No notifications yet.</div>
                    </div>
                <?php else: ?>
                    <div class="list-group" id="doctorNotificationsList">
                        <?php foreach ($doctorNotifications as $n): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start doctor-notif-item <?php echo $n['is_read'] ? '' : 'bg-light'; ?>" data-id="<?php echo $n['id']; ?>">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?>
                                        <?php if (!$n['is_read']): ?>
                                            <span class="badge bg-danger ms-2">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted mb-1"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></div>
                                    <div><?php echo nl2br(htmlspecialchars($n['message'] ?? '')); ?></div>
                                </div>
                                <div class="btn-group btn-group-sm align-self-center">
                                    <?php if (!$n['is_read']): ?>
                                        <button class="btn btn-outline-success btn-doc-notif-read">Read</button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-danger btn-doc-notif-del">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total_notif_pages > 1): ?>
                        <nav aria-label="Doctor notification pagination" class="mt-3">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <li class="page-item <?php echo $notif_page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link doctor-notification-page-link" href="#" data-page="<?php echo $notif_page - 1; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_notif_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $notif_page ? 'active' : ''; ?>">
                                        <a class="page-link doctor-notification-page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $notif_page >= $total_notif_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link doctor-notification-page-link" href="#" data-page="<?php echo $notif_page + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1" aria-labelledby="appointmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentDetailsModalLabel">
                    <i class="fas fa-calendar-check me-2"></i>Appointment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="appointmentDetailsBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 small text-muted">Loading appointment details...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Calendar events query - show all appointments (past and future) except cancelled to include settled status
$stmt = $conn->prepare("SELECT a.id, a.appointment_date, a.appointment_time, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       a.status, a.notes
                       FROM appointments a
                       JOIN patients p ON a.patient_id = p.id
                       JOIN doctors d ON a.doctor_id = d.id
                       JOIN users u ON d.user_id = u.id
                       WHERE u.id = ? AND a.status != 'cancelled'
                       ORDER BY a.appointment_date, a.appointment_time");

$stmt->bind_param("i", $doctorId);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    // Status-based colors - using Bootstrap 5 standard colors for consistency
    $statusLower = strtolower(trim($row['status'] ?? ''));
    $color = match($statusLower) {
        'scheduled' => '#0d6efd', // Bootstrap 5 primary
        'ongoing' => '#fd7e14',   // Orange for ongoing status
        'settled' => '#198754',    // Bootstrap 5 success
        'cancelled', 'canceled' => '#dc3545', // Bootstrap 5 danger
        default => '#6c757d'       // Default gray
    };
    
    $events[] = [
        'id' => $row['id'],
        'title' => $row['patient_name'],
        'start' => $row['appointment_date'] . 'T' . $row['appointment_time'],
        'color' => $color,
        'extendedProps' => [
            'status' => $row['status'],
            'notes' => $row['notes']
        ]
    ];
}

$events = json_encode($events);
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Calendar
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: <?php echo $events; ?>,
        eventClick: function(info) {
            // Show appointment details modal
            var appointmentId = info.event.id;
            var modal = new bootstrap.Modal(document.getElementById('appointmentDetailsModal'));
            var modalBody = document.getElementById('appointmentDetailsBody');
            
            // Show loading state
            modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small text-muted">Loading appointment details...</div></div>';
            modal.show();
            
            // Fetch appointment details
            fetch('get_appointment_details.php?id=' + appointmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + data.error + '</div>';
                        return;
                    }

                    var statusBadge = '<span class="badge bg-' + data.status_class + '">' + data.status + '</span>';
                    
                    var modalContent = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3"><i class="fas fa-calendar-alt me-2"></i>Appointment Information</h6>
                                <div class="mb-3">
                                    <strong>Date & Time:</strong><br>
                                    <span class="text-muted">${data.formatted_datetime}</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Status:</strong><br>
                                    ${statusBadge}
                                </div>
                                ${data.reason ? '<div class="mb-3"><strong>Reason:</strong><br><span class="text-muted">' + data.reason + '</span></div>' : ''}
                                ${data.notes ? '<div class="mb-3"><strong>Notes:</strong><br><span class="text-muted">' + data.notes + '</span></div>' : ''}
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success mb-3"><i class="fas fa-user me-2"></i>Patient Information</h6>
                                <div class="mb-3">
                                    <strong>Name:</strong><br>
                                    <span class="text-muted">${data.patient_name}</span>
                                </div>
                                ${data.patient_age ? '<div class="mb-3"><strong>Age:</strong><br><span class="text-muted">' + data.patient_age + '</span></div>' : ''}
                                ${data.patient_gender ? '<div class="mb-3"><strong>Gender:</strong><br><span class="text-muted">' + data.patient_gender + '</span></div>' : ''}
                                ${data.patient_phone ? '<div class="mb-3"><strong>Phone:</strong><br><span class="text-muted"><i class="fas fa-phone text-success me-1"></i>' + data.patient_phone + '</span></div>' : ''}
                                ${data.patient_email ? '<div class="mb-3"><strong>Email:</strong><br><span class="text-muted"><i class="fas fa-envelope text-info me-1"></i>' + data.patient_email + '</span></div>' : ''}
                                ${data.patient_address ? '<div class="mb-3"><strong>Address:</strong><br><span class="text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i>' + data.patient_address + '</span></div>' : ''}
                            </div>
                        </div>
                    `;
                    
                    modalBody.innerHTML = modalContent;
                })
                .catch(error => {
                    console.error('Error fetching appointment details:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading appointment details. Please try again.</div>';
                });
        },
        eventMouseEnter: function(info) {
            info.el.setAttribute('title', 
                info.event.title + ' (' + info.event.extendedProps.status + ') - ' +
                info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
            );
        },
        height: 'auto',
        dayMaxEvents: 3,
        eventDisplay: 'block',
        displayEventTime: true,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        }
    });
    calendar.render();

    // Initialize Chart
    var ctx = document.getElementById('appointmentsChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $chartLabels; ?>,
            datasets: [{
                label: 'Total Appointments',
                data: <?php echo $appointmentsData; ?>,
                borderColor: '#0c1a6a',
                backgroundColor: 'rgba(12, 26, 106, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7
            }, {
                label: 'Completed',
                data: <?php echo $completedData; ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                title: {
                    display: true,
                    text: 'Appointment Trends (Last 6 Months)',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            },
            interaction: {
                intersect: false
            }
        }
    });

    // Update Status Modal
    $('.update-status').on('click', function() {
        $('#appointmentId').val($(this).data('id'));
        $('#status').val('Completed'); // Default to completed
        $('#notes').val('');
    });

    // Save Status Changes
    $('#saveStatus').on('click', function() {
        var formData = {
            update_appointment_status: true,
            appointmentId: $('#appointmentId').val(),
            status: $('#status').val(),
            notes: $('#notes').val()
        };

        // Show loading state
        var $button = $(this);
        var originalText = $button.html();
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#updateStatusModal').modal('hide');
                    
                    // Show success message
                    var alertHtml = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                                    '<i class="fas fa-check-circle me-2"></i>Appointment status updated successfully!' +
                                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                                    '</div>';
                    
                    $('main .container-fluid').prepend(alertHtml);
                    
                    // Reload page after short delay
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert('Error updating status: ' + (response.message || 'Unknown error'), 'Error', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showAlert('Error updating status: ' + error, 'Error', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Patients table: Quick View
    $(document).on('click', '.btn-view-patient', function () {
        var patientId = $(this).data('patient-id');
        $('#patientQuickViewBody').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small text-muted">Loading...</div></div>');
        $.post(window.location.href, { ajax: 'patient_quick_view', patient_id: patientId }, function (html) {
            $('#patientQuickViewBody').html(html);
        });
    });

    // Patients table: History
    $(document).on('click', '.btn-view-history', function () {
        var patientId = $(this).data('patient-id');
        $('#patientHistoryBody').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small text-muted">Loading...</div></div>');
        $.post(window.location.href, { ajax: 'patient_history', patient_id: patientId }, function (html) {
            $('#patientHistoryBody').html(html);
        });
    });

    // Patients table: Schedule
    $(document).on('click', '.btn-schedule-appointment', function () {
        $('#schedule_patient_id').val($(this).data('patient-id'));
        $('#schedule_patient_name').val($(this).data('patient-name'));
        $('#schedule_date').val('');
        $('#schedule_time').val('');
        $('#schedule_notes').val('');
    });

    // Save new appointment
    $('#saveScheduleBtn').on('click', function () {
        var payload = {
            create_appointment: true,
            patient_id: $('#schedule_patient_id').val(),
            appointment_date: $('#schedule_date').val(),
            appointment_time: $('#schedule_time').val(),
            notes: $('#schedule_notes').val()
        };

        var $btn = $(this);
        var original = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');

        $.post(window.location.href, payload, function (res) {
            if (res && res.success) {
                $('#scheduleAppointmentModal').modal('hide');
                $('main .container-fluid').prepend('<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i>' + (res.message || 'Appointment scheduled.') + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                showAlert(res && res.message ? res.message : 'Failed to schedule appointment.', 'Error', 'error');
            }
        }, 'json').fail(function () {
            showAlert('Network error while scheduling appointment.', 'Error', 'error');
        }).always(function () {
            $btn.prop('disabled', false).html(original);
        });
    });

    // Track current doctor notification page
    let currentDoctorNotificationPage = 1;

    // Function to update doctor notification badge
    function updateDoctorNotificationBadge() {
        $.get('get_doctor_notifications.php?count_only=1', function(data) {
            if (data && data.success) {
                var $badge = $('#doctorUnreadBadge');
                if (data.unread_count > 0) {
                    if ($badge.length) {
                        $badge.text(data.unread_count);
                    } else {
                        var $btn = $('#openDoctorNotifications');
                        if ($btn.length) {
                            $btn.append('<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="doctorUnreadBadge">' + data.unread_count + '</span>');
                        }
                    }
                } else {
                    $badge.remove();
                }
            }
        }, 'json').fail(function() {
            console.error('Error updating doctor notification badge');
        });
    }

    // Function to reload doctor notification modal
    function reloadDoctorNotificationModal(page) {
        if (!page) page = currentDoctorNotificationPage;
        $.get('get_doctor_notifications.php?page=' + page, function(data) {
            if (data && data.success) {
                var $modalBody = $('#doctorNotificationsModal .modal-body');
                var $markAllBtn = $('#markAllDoctorNotifications');
                if (data.notifications.length === 0 && data.pagination.current_page === 1) {
                    $modalBody.html('<div class="text-center text-muted py-4"><i class="fas fa-bell-slash fa-2x mb-2"></i><div>No notifications yet.</div></div>');
                    $markAllBtn.hide();
                } else {
                    $markAllBtn.show();
                    var html = '<div class="list-group" id="doctorNotificationsList">';
                    data.notifications.forEach(function(n) {
                        html += '<div class="list-group-item d-flex justify-content-between align-items-start doctor-notif-item ' + (n.is_read ? '' : 'bg-light') + '" data-id="' + n.id + '">';
                        html += '<div class="ms-2 me-auto">';
                        html += '<div class="fw-bold">' + $('<div>').text(n.title || 'Notification').html();
                        if (!n.is_read) {
                            html += '<span class="badge bg-danger ms-2">New</span>';
                        }
                        html += '</div>';
                        html += '<div class="small text-muted mb-1">' + new Date(n.created_at).toLocaleString('en-US', {month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'}) + '</div>';
                        html += '<div>' + $('<div>').text(n.message || '').html().replace(/\n/g, '<br>') + '</div>';
                        html += '</div>';
                        html += '<div class="btn-group btn-group-sm align-self-center">';
                        if (!n.is_read) {
                            html += '<button class="btn btn-outline-success btn-doc-notif-read">Read</button>';
                        }
                        html += '<button class="btn btn-outline-danger btn-doc-notif-del">Delete</button>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';

                    // Add pagination if needed
                    if (data.pagination && data.pagination.total_pages > 1) {
                        html += '<nav aria-label="Doctor notification pagination" class="mt-3">';
                        html += '<ul class="pagination pagination-sm justify-content-center mb-0">';
                        html += '<li class="page-item ' + (data.pagination.current_page <= 1 ? 'disabled' : '') + '">';
                        html += '<a class="page-link doctor-notification-page-link" href="#" data-page="' + (data.pagination.current_page - 1) + '">Previous</a>';
                        html += '</li>';
                        for (var i = 1; i <= data.pagination.total_pages; i++) {
                            html += '<li class="page-item ' + (i == data.pagination.current_page ? 'active' : '') + '">';
                            html += '<a class="page-link doctor-notification-page-link" href="#" data-page="' + i + '">' + i + '</a>';
                            html += '</li>';
                        }
                        html += '<li class="page-item ' + (data.pagination.current_page >= data.pagination.total_pages ? 'disabled' : '') + '">';
                        html += '<a class="page-link doctor-notification-page-link" href="#" data-page="' + (data.pagination.current_page + 1) + '">Next</a>';
                        html += '</li>';
                        html += '</ul>';
                        html += '</nav>';
                    }

                    $modalBody.html(html);
                }
                updateDoctorNotificationBadge();
            }
        }, 'json').fail(function() {
            console.error('Error reloading doctor notifications');
        });
    }

    // Doctor notifications actions - use event delegation for dynamically added elements
    $(document).on('click', '#markAllDoctorNotifications', function() {
        confirmDialog(
            'Are you sure you want to mark all notifications as read?',
            'Mark All as Read',
            'Cancel',
            'Mark All Notifications as Read'
        ).then(function(confirmed) {
            if (!confirmed) return;
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: { ajax: 'doctor_mark_all_read' },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        updateDoctorNotificationBadge();
                        reloadDoctorNotificationModal(currentDoctorNotificationPage);
                        showAlert('All notifications have been marked as read!', 'Success', 'success');
                    } else {
                        showAlert('Error: ' + (res && res.message ? res.message : 'Failed to mark all as read'), 'Error', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error Details:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('HTTP Status:', xhr.status);
                    console.error('Ready State:', xhr.readyState);
                    
                    var errorMsg = 'Network error while marking all notifications as read.';
                    
                    if (xhr.status === 0) {
                        errorMsg = 'Unable to connect to server. Please check your internet connection.';
                    } else if (xhr.status === 404) {
                        errorMsg = 'Requested page not found. Please refresh the page and try again.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error occurred. Please try again later.';
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.message) {
                                errorMsg = errorData.message;
                            }
                        } catch(e) {
                            // If response is not JSON, check if it's HTML (which means output was sent)
                            if (xhr.responseText.trim().startsWith('<')) {
                                errorMsg = 'Server returned HTML instead of JSON. This usually means there was an error on the server.';
                            }
                        }
                    }
                    
                    showAlert(errorMsg + ' Please try again.', 'Error', 'error');
                }
            });
        });
    });

    $(document).on('click', '.btn-doc-notif-read', function() {
        var $item = $(this).closest('.doctor-notif-item');
        var id = $item.data('id');
        $.post(window.location.href, { ajax: 'doctor_mark_read', id: id }, function(res) {
            if (res && res.success) {
                updateDoctorNotificationBadge();
                reloadDoctorNotificationModal(currentDoctorNotificationPage);
            } else {
                showAlert('Error: ' + (res && res.message ? res.message : 'Failed to mark notification as read'), 'Error', 'error');
            }
        }, 'json').fail(function() {
            showAlert('Network error while marking notification as read. Please try again.', 'Error', 'error');
        });
    });

    $(document).on('click', '.btn-doc-notif-del', function() {
        var $item = $(this).closest('.doctor-notif-item');
        var id = $item.data('id');
        var currentCount = $('#doctorNotificationsList .doctor-notif-item').length;
        
        confirmDialog(
            'Are you sure you want to delete this notification? This action cannot be undone.',
            'Delete',
            'Cancel',
            'Delete Notification'
        ).then(function(confirmed) {
            if (!confirmed) return;
            
            $.post(window.location.href, { ajax: 'doctor_delete', id: id }, function(res) {
                if (res && res.success) {
                    var pageToLoad = currentDoctorNotificationPage;
                    // If we deleted the last item on the current page and we're not on page 1, go to previous page
                    if (currentCount === 1 && currentDoctorNotificationPage > 1) {
                        pageToLoad = currentDoctorNotificationPage - 1;
                        currentDoctorNotificationPage = pageToLoad;
                    }
                    updateDoctorNotificationBadge();
                    reloadDoctorNotificationModal(pageToLoad);
                } else {
                    showAlert('Error: ' + (res && res.message ? res.message : 'Failed to delete notification'), 'Error', 'error');
                }
            }, 'json').fail(function() {
                showAlert('Network error while deleting notification. Please try again.', 'Error', 'error');
            });
        });
    });

    // Handle pagination clicks
    $(document).on('click', '.doctor-notification-page-link', function(e) {
        e.preventDefault();
        var page = parseInt($(this).data('page'));
        if (page > 0) {
            currentDoctorNotificationPage = page;
            reloadDoctorNotificationModal(page);
        }
    });

    // Reload notifications when modal is opened
    $('#doctorNotificationsModal').on('show.bs.modal', function() {
        currentDoctorNotificationPage = 1;
        // Show the mark all button initially (will be hidden if no notifications)
        $('#markAllDoctorNotifications').show();
        reloadDoctorNotificationModal(1);
    });

    // Update badge on page load
    updateDoctorNotificationBadge();

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include 'includes/footer.php'; ?>