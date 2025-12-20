<?php
define('MHAVIS_EXEC', true);
$page_title = "Doctor Management";
$active_page = "doctors";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

$viewing_doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Handle leave cancellation and deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'cancel_leave' || $action === 'delete_leave') {
        header('Content-Type: application/json');
        $leave_id = isset($_POST['leave_id']) ? (int)$_POST['leave_id'] : 0;
        $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
        
        if (!$leave_id || !$doctor_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid leave ID or doctor ID']);
            exit();
        }
        
        // Verify the leave exists and belongs to the doctor
        $stmt = $conn->prepare("SELECT id, status FROM doctor_leaves WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $leave_id, $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Leave not found or unauthorized']);
            exit();
        }
        
        $leave = $result->fetch_assoc();
        
        if ($action === 'cancel_leave') {
            if ($leave['status'] === 'Cancelled') {
                echo json_encode(['success' => false, 'message' => 'Leave is already cancelled']);
                exit();
            }
            
            // Cancel the leave (soft delete by setting status to Cancelled)
            $stmt = $conn->prepare("UPDATE doctor_leaves SET status = 'Cancelled' WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ii", $leave_id, $doctor_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Leave cancelled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error cancelling leave: ' . $conn->error]);
            }
        } elseif ($action === 'delete_leave') {
            // Permanently delete the leave
            $stmt = $conn->prepare("DELETE FROM doctor_leaves WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ii", $leave_id, $doctor_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Leave deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting leave: ' . $conn->error]);
            }
        }
        exit();
    }
}

// Handle doctor deletion
if (isset($_GET['delete'])) {
    $doctorId = (int)$_GET['delete'];
    
    // Check if doctor has appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointmentCount = $result->fetch_assoc()['count'];
    
    if ($appointmentCount > 0) {
        $error = "Cannot delete doctor. They have existing appointments.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'Doctor'");
        $stmt->bind_param("i", $doctorId);
        if ($stmt->execute()) {
            $success = "Doctor deleted successfully";
        } else {
            $error = "Error deleting doctor";
        }
    }
}

// Get doctor details if viewing specific doctor
$doctor_details = null;
$doctor_departments_list = [];
if ($viewing_doctor_id) {
    // Check if PRC columns exist
    $checkPrcNumber = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_number'");
    $checkLicenseType = $conn->query("SHOW COLUMNS FROM users LIKE 'license_type'");
    $checkPrcIdDoc = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_id_document'");
    
    $prcNumberExists = $checkPrcNumber && $checkPrcNumber->num_rows > 0;
    $licenseTypeExists = $checkLicenseType && $checkLicenseType->num_rows > 0;
    $prcIdDocExists = $checkPrcIdDoc && $checkPrcIdDoc->num_rows > 0;
    
    // Use SELECT u.* which will include all existing columns
    $stmt = $conn->prepare("SELECT u.*, 
       (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id IN (SELECT d.id FROM doctors d WHERE d.user_id = u.id)) as appointment_count,
       (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id IN (SELECT d2.id FROM doctors d2 WHERE d2.user_id = u.id) AND a.appointment_date = CURDATE()) as today_appointments
    FROM users u WHERE u.id = ? AND u.role = 'Doctor'");
    $stmt->bind_param("i", $viewing_doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor_details = $result->fetch_assoc();
    
    // Ensure these keys exist in the array even if columns don't exist in database
    if (!$prcNumberExists || !isset($doctor_details['prc_number'])) {
        $doctor_details['prc_number'] = null;
    }
    if (!$licenseTypeExists || !isset($doctor_details['license_type'])) {
        $doctor_details['license_type'] = null;
    }
    if (!$prcIdDocExists || !isset($doctor_details['prc_id_document'])) {
        $doctor_details['prc_id_document'] = null;
    }
    
    // Get doctor's departments from doctor_departments table
    $checkTable = $conn->query("SHOW TABLES LIKE 'doctor_departments'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("SELECT dd.*, d.name as department_name, d.description as department_description 
                                FROM doctor_departments dd 
                                INNER JOIN departments d ON dd.department_id = d.id 
                                WHERE dd.doctor_id = ? 
                                ORDER BY d.name");
        $stmt->bind_param("i", $viewing_doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $doctor_departments_list[] = $row;
        }
    }
}

// Search functionality
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$searchCondition = '';
$params = [];
$types = '';

if ($search) {
    $searchCondition = "AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR specialization LIKE ? OR prc_number LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    $types = 'sssss';
}

// Get all doctors with additional info
$query = "SELECT u.*, 
    (SELECT COUNT(*) FROM appointments WHERE doctor_id IN (SELECT d.id FROM doctors d WHERE d.user_id = u.id)) as appointment_count,
    (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND doctor_id IN (SELECT d2.id FROM doctors d2 WHERE d2.user_id = u.id)) as today_appointments
FROM users u WHERE u.role = 'Doctor' $searchCondition ORDER BY u.first_name, u.last_name";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$doctors = $stmt->get_result();

include 'includes/header.php';
?>

<style>
.doctor-main { padding: 20px; overflow-y: auto; }
.doctor-list-item { padding: 12px 16px; border-bottom: 1px solid #e9ecef; cursor: pointer; }
.doctor-list-item:hover { background-color: #e9ecef; }
.doctor-list-item.active { background-color: #007bff; color: white; }
.doctor-avatar {
    width: 40px; height: 40px; border-radius: 50%; background: #28a745; color: white;
    display: flex; align-items: center; justify-content: center; font-weight: bold;
}
.doctor-header { background: #f8f9fa; padding: 20px; border-bottom: 1px solid #dee2e6; }
.doctor-tabs { background: white; border-bottom: 1px solid #dee2e6; padding: 0 20px; }
.doctor-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #6c757d; padding: 12px 16px; }
.doctor-tabs .nav-link.active { color: #007bff; border-bottom-color: #007bff; }
.doctor-content { padding: 20px; }
.empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
.empty-state i { font-size: 3rem; margin-bottom: 20px; color: #dee2e6; }
.schedule-day { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 10px; padding: 15px; background-color: #fff; }
.schedule-day.active { background-color: #e3f2fd; border-color: #2196f3; }
.schedule-day.h-100 { display: flex; flex-direction: column; }
.time-slot { display: inline-block; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; margin: 2px; font-size: 0.875rem; }
.time-slot.active { background: #007bff; color: white; }
.prc-status { padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 500; }
.prc-valid { background-color: #d4edda; color: #155724; }
.prc-expiring { background-color: #fff3cd; color: #856404; }
.prc-expired { background-color: #f8d7da; color: #721c24; }
.info-row { margin-bottom: 12px; }
.info-label { font-weight: 600; color: #495057; min-width: 140px; display: inline-block; }
.info-value { color: #212529; }
.border-left-primary { border-left: 4px solid #007bff !important; }
.stat-box {
    padding: 10px; border-radius: 8px; background: white; margin: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.patient-avatar { flex-shrink: 0; }
.appointment-date { text-align: center; }
.appointment-date .badge { font-size: 0.75rem; }
.appointment-date .h5 { margin: 0; font-weight: bold; }
.bg-outline-primary { background-color: transparent; color: #007bff; border: 1px solid #007bff; }
.month-header { transition: background-color 0.2s; }
.month-header:hover { background-color: #e9ecef !important; }
.month-header .collapse-icon { transition: transform 0.3s; }
.month-header[aria-expanded="true"] .collapse-icon { transform: rotate(180deg); }
.month-appointments { padding-top: 10px; }
.month-group { transition: opacity 0.3s, max-height 0.3s; }
.month-group.hidden { display: none !important; }
</style>

<div class="doctor-main">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="add_doctor.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add New Doctor
            </a>
            <?php if ($viewing_doctor_id): ?>
                <a href="manage_doctor_departments.php?doctor_id=<?php echo $viewing_doctor_id; ?>" class="btn btn-sm btn-info">
                    <i class="fas fa-hospital"></i> Manage Departments
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="GET" id="searchForm" class="input-group mb-4">
        <?php if ($viewing_doctor_id): ?>
            <input type="hidden" name="doctor_id" value="<?php echo $viewing_doctor_id; ?>">
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
        <?php endif; ?>
        <input type="text" class="form-control" name="search" 
               placeholder="Search by name, email, specialization, or PRC number" 
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-outline-secondary" type="submit">
            <i class="fas fa-search"></i>
        </button>
        <?php if ($search): ?>
            <a href="doctors.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <div class="row">
        <div class="col-md-4">
            <div class="list-group">
                <?php if ($doctors->num_rows > 0): ?>
                    <?php while ($doctor = $doctors->fetch_assoc()): ?>
                        <div class="doctor-list-item <?php echo $viewing_doctor_id == $doctor['id'] ? 'active' : ''; ?>" 
                             onclick="viewDoctor(<?php echo $doctor['id']; ?>)">
                            <div class="d-flex align-items-center">
                                <?php 
                                $doctor_profile_image = !empty($doctor['profile_image']) ? htmlspecialchars($doctor['profile_image']) : null;
                                $doctor_timestamp = time();
                                ?>
                                <?php if ($doctor_profile_image): ?>
                                    <img src="<?php echo $doctor_profile_image; ?>?t=<?php echo $doctor_timestamp; ?>" 
                                         alt="Profile" 
                                         class="doctor-avatar" 
                                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #dee2e6; flex-shrink: 0;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="doctor-avatar" style="display: none;">
                                        <?php echo strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1)); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="doctor-avatar">
                                        <?php echo strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ms-3">
                                    <strong><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($doctor['specialization'] ?? 'General Practice'); ?></small><br>
                                    <?php if (!empty($doctor['license_type'])): ?>
                                        <small class="text-info">
                                            <i class="fas fa-certificate"></i> <?php echo htmlspecialchars($doctor['license_type']); ?>
                                        </small><br>
                                    <?php endif; ?>
                                    <small>
                                        <i class="fas fa-calendar-check"></i> <?php echo $doctor['appointment_count']; ?> total
                                        <?php if ($doctor['today_appointments'] > 0): ?>
                                            <span class="badge bg-success ms-1"><?php echo $doctor['today_appointments']; ?> today</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-muted text-center p-3">
                        <i class="fas fa-user-md fa-2x mb-2"></i>
                        <p>No doctors found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-8">
            <?php if ($doctor_details): ?>
                <div class="doctor-header d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center">
                        <?php if ($doctor_details['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($doctor_details['profile_image']); ?>" 
                                 alt="Profile" class="rounded-circle me-3" 
                                 style="width: 60px; height: 60px; object-fit: cover;">
                        <?php else: ?>
                            <div class="doctor-avatar me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <?php echo strtoupper(substr($doctor_details['first_name'], 0, 1) . substr($doctor_details['last_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h4>Dr. <?php echo htmlspecialchars($doctor_details['first_name'] . ' ' . $doctor_details['last_name']); ?></h4>
                            <p class="text-muted">
                                <i class="fas fa-stethoscope me-1"></i>
                                <?php echo htmlspecialchars($doctor_details['specialization'] ?? 'General Practice'); ?>
                                <?php if (!empty($doctor_details['license_type'])): ?>
                                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($doctor_details['license_type']); ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="text-muted">
                                <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars(formatPhoneNumber($doctor_details['phone'] ?? '') ?: 'Not specified'); ?>
                                <i class="fas fa-envelope ms-3 me-1"></i> <?php echo htmlspecialchars($doctor_details['email']); ?>
                            </p>
                        </div>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="edit_doctor.php?id=<?php echo $doctor_details['id']; ?>">
                                <i class="fas fa-edit me-2"></i>Edit Doctor
                            </a></li>
                            <li><a class="dropdown-item" href="manage_doctor_departments.php?doctor_id=<?php echo $doctor_details['id']; ?>">
                                <i class="fas fa-hospital me-2"></i>Manage Departments
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" 
                                   href="doctors.php?delete=<?php echo $doctor_details['id']; ?>" 
                                   onclick="return confirmLink(event, 'Are you sure you want to delete this doctor?');">
                                <i class="fas fa-trash me-2"></i>Delete Doctor
                            </a></li>
                        </ul>
                    </div>
                </div>

                <div class="doctor-tabs">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab == 'overview' ? 'active' : ''; ?>" 
                               href="?doctor_id=<?php echo $viewing_doctor_id; ?>&tab=overview<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-user-md me-1"></i>Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab == 'appointments' ? 'active' : ''; ?>" 
                               href="?doctor_id=<?php echo $viewing_doctor_id; ?>&tab=appointments<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-calendar-check me-1"></i>Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab == 'schedule' ? 'active' : ''; ?>" 
                               href="?doctor_id=<?php echo $viewing_doctor_id; ?>&tab=schedule<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-clock me-1"></i>Schedule
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="doctor-content">
                    <?php
                    switch ($active_tab) {
                        case 'overview':
                            ?>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">Personal Information</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="info-row">
                                                <span class="info-label">Full Name:</span>
                                                <span class="info-value"><?php echo htmlspecialchars($doctor_details['first_name'] . ' ' . $doctor_details['last_name']); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Username:</span>
                                                <span class="info-value"><?php echo htmlspecialchars($doctor_details['username']); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Email:</span>
                                                <span class="info-value"><?php echo htmlspecialchars($doctor_details['email']); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Phone:</span>
                                                <span class="info-value"><?php echo htmlspecialchars(formatPhoneNumber($doctor_details['phone'] ?? '') ?: 'Not specified'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Address:</span>
                                                <span class="info-value"><?php echo htmlspecialchars($doctor_details['address'] ?? 'Not specified'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">PRC Number:</span>
                                                <span class="info-value">
                                                    <?php if (!empty($doctor_details['prc_number'])): ?>
                                                        <strong><?php echo htmlspecialchars($doctor_details['prc_number']); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="card-title mb-0">Department Assignments</h6>
                                            <a href="manage_doctor_departments.php?doctor_id=<?php echo $viewing_doctor_id; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-hospital"></i> Manage Departments
                                            </a>
                                        </div>
                                        <div class="card-body">
                                            <?php if (empty($doctor_departments_list)): ?>
                                                <div class="text-center text-muted py-3">
                                                    <i class="fas fa-hospital fa-2x mb-2"></i>
                                                    <p class="mb-0">No departments assigned yet.</p>
                                                    <small>Click "Manage Departments" to assign this doctor to departments.</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="row">
                                                    <?php foreach ($doctor_departments_list as $dept): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="border rounded p-3">
                                                                <h6 class="mb-2">
                                                                    <i class="fas fa-hospital text-primary me-2"></i>
                                                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                                                </h6>
                                                                <?php if ($dept['department_description']): ?>
                                                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($dept['department_description']); ?></p>
                                                                <?php endif; ?>
                                                                <div class="small">
                                                                    <?php if ($dept['specialization']): ?>
                                                                        <div><strong>Specialization:</strong> <?php echo htmlspecialchars($dept['specialization']); ?></div>
                                                                    <?php endif; ?>
                                                                    <?php if ($dept['prc_number']): ?>
                                                                        <div><strong>PRC Number:</strong> <?php echo htmlspecialchars($dept['prc_number']); ?></div>
                                                                    <?php endif; ?>
                                                                    <?php if ($dept['license_type']): ?>
                                                                        <div><strong>License Type:</strong> <?php echo htmlspecialchars($dept['license_type']); ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">Quick Stats</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <h4 class="text-primary"><?php echo $doctor_details['appointment_count']; ?></h4>
                                                    <small class="text-muted">Total Appointments</small>
                                                </div>
                                                <div class="col-4">
                                                    <h4 class="text-success"><?php echo $doctor_details['today_appointments']; ?></h4>
                                                    <small class="text-muted">Today's Appointments</small>
                                                </div>
                                                <div class="col-4">
                                                    <h4 class="text-info">
                                                        <?php echo !empty($doctor_details['prc_number']) ? '✓' : '✗'; ?>
                                                    </h4>
                                                    <small class="text-muted">PRC Registered</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            break;

                        case 'appointments':
                            // Get doctor's appointments with patient details
                            $stmt = $conn->prepare("SELECT a.*, 
                                                           p.first_name, p.last_name, p.phone, p.email
                                                    FROM appointments a 
                                                    LEFT JOIN patients p ON a.patient_id = p.id 
                                                    WHERE a.doctor_id IN (SELECT d.id FROM doctors d WHERE d.user_id = ?) 
                                                    ORDER BY a.appointment_date DESC, a.appointment_time DESC");
                            $stmt->bind_param("i", $viewing_doctor_id);
                            $stmt->execute();
                            $all_appointments = $stmt->get_result();
                            
                            // Separate upcoming and past appointments
                            $upcoming_appointments = [];
                            $past_appointments = [];
                            $currentDateTime = new DateTime();
                            
                            while ($appointment = $all_appointments->fetch_assoc()) {
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
                            
                            // Group past appointments by month
                            $past_appointments_by_month = [];
                            foreach ($past_appointments as $appointment) {
                                $month_key = date('Y-m', strtotime($appointment['appointment_date']));
                                $month_label = date('F Y', strtotime($appointment['appointment_date']));
                                if (!isset($past_appointments_by_month[$month_key])) {
                                    $past_appointments_by_month[$month_key] = [
                                        'label' => $month_label,
                                        'appointments' => []
                                    ];
                                }
                                $past_appointments_by_month[$month_key]['appointments'][] = $appointment;
                            }
                            // Sort months in descending order (most recent first)
                            krsort($past_appointments_by_month);
                            ?>
                            
                            <!-- HEADER -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5>Appointments</h5>
                            </div>

                            <!-- UPCOMING APPOINTMENTS -->
                            <div class="card mb-4">
                                <div class="card-header"><h6><i class="fas fa-calendar-alt me-2"></i>Upcoming Appointments</h6></div>
                                <div class="card-body">
                                    <?php if (!empty($upcoming_appointments)): ?>
                                        <?php foreach ($upcoming_appointments as $appointment): ?>
                                            <div class="record-card mb-3" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; background: white;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="flex-grow-1 d-flex align-items-center">
                                                        <i class="fas fa-calendar-check me-3" style="color: #007bff; font-size: 1.5rem;"></i>
                                                        <div>
                                                            <div style="color: #333; font-weight: 600; font-size: 0.95rem;">
                                                                <?= date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?= date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                                <?php if (!empty($appointment['first_name']) && !empty($appointment['last_name'])): ?>
                                                                    • <?= htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group">
                                                        <button class="btn btn-outline-primary btn-sm" onclick="viewAppointment(<?= $appointment['id']; ?>)" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-calendar-alt fa-2x mb-2"></i><br>
                                            No upcoming appointments
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- PAST APPOINTMENTS -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Past Appointments</h6>
                                    <div class="year-filter" style="max-width: 200px;">
                                        <input type="text" 
                                               class="form-control form-control-sm" 
                                               id="yearFilterInput" 
                                               placeholder="Filter by year (e.g., 2024)" 
                                               autocomplete="off">
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($past_appointments_by_month)): ?>
                                        <?php foreach ($past_appointments_by_month as $month_key => $month_data): ?>
                                            <?php 
                                            // Extract year from month_key (format: Y-m, e.g., 2024-01)
                                            $year = substr($month_key, 0, 4);
                                            ?>
                                            <div class="month-group mb-3" data-year="<?= $year; ?>">
                                                <div class="month-header" style="cursor: pointer; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 10px;" 
                                                     data-bs-toggle="collapse" 
                                                     data-bs-target="#month-<?= $month_key; ?>" 
                                                     aria-expanded="false" 
                                                     aria-controls="month-<?= $month_key; ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-calendar-alt me-2"></i>
                                                            <strong><?= htmlspecialchars($month_data['label']); ?></strong>
                                                            <span class="badge bg-secondary ms-2"><?= count($month_data['appointments']); ?> appointment(s)</span>
                                                        </div>
                                                        <i class="fas fa-chevron-down collapse-icon"></i>
                                                    </div>
                                                </div>
                                                <div class="collapse" id="month-<?= $month_key; ?>">
                                                    <div class="month-appointments">
                                                        <?php foreach ($month_data['appointments'] as $appointment): ?>
                                                            <div class="record-card mb-3" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; background: white; margin-left: 20px;">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div class="flex-grow-1 d-flex align-items-center">
                                                                        <i class="fas fa-calendar-check me-3" style="color: #6c757d; font-size: 1.5rem;"></i>
                                                                        <div>
                                                                            <div style="color: #333; font-weight: 600; font-size: 0.95rem;">
                                                                                <?= date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                                                            </div>
                                                                            <small class="text-muted">
                                                                                <?= date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                                                <?php if (!empty($appointment['first_name']) && !empty($appointment['last_name'])): ?>
                                                                                    • <?= htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                                                                <?php endif; ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                    <div class="btn-group">
                                                                        <button class="btn btn-outline-secondary btn-sm" onclick="viewAppointment(<?= $appointment['id']; ?>)" title="View">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-history fa-2x mb-2"></i><br>
                                            No past appointments
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- VIEW MODAL -->
                            <div class="modal fade" id="viewAppointmentModal" tabindex="-1" aria-labelledby="viewAppointmentModalLabel" aria-hidden="true">
                              <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="viewAppointmentModalLabel">
                                      <i class="fas fa-calendar-check me-2"></i>Appointment Details
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body" id="viewAppointmentContent">
                                    <div class="text-center">
                                      <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                      </div>
                                      <p class="mt-2">Loading appointment details...</p>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <?php
                            break;

                        case 'schedule':
                            // Get doctor's schedule from doctor_schedules table
                            $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week");
                            $stmt->bind_param("i", $viewing_doctor_id);
                            $stmt->execute();
                            $schedules = $stmt->get_result();
                            
                            // Convert to associative array with day as key
                            $doctor_schedule = [];
                            while ($schedule = $schedules->fetch_assoc()) {
                                $doctor_schedule[$schedule['day_of_week']] = $schedule;
                            }
                            
                            // Get doctor's vacation and emergency leave entries
                            $stmt = $conn->prepare("SELECT * FROM doctor_leaves WHERE doctor_id = ? ORDER BY start_date DESC, end_date DESC");
                            $stmt->bind_param("i", $viewing_doctor_id);
                            $stmt->execute();
                            $leaves_result = $stmt->get_result();
                            $doctor_leaves = [];
                            while ($leave = $leaves_result->fetch_assoc()) {
                                $doctor_leaves[] = $leave;
                            }
                            
                            $days = [
                                1 => 'Monday',
                                2 => 'Tuesday', 
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                                7 => 'Sunday'
                            ];
                            ?>
                            <!-- Weekly Schedule Card -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0">Weekly Schedule</h6>
                                    <small class="text-muted">Doctor can edit this schedule from their dashboard</small>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($days as $day_num => $day_name): ?>
                                        <div class="schedule-day <?php echo isset($doctor_schedule[$day_num]) && $doctor_schedule[$day_num]['is_available'] ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $day_name; ?></h6>
                                                    <?php if (isset($doctor_schedule[$day_num]) && $doctor_schedule[$day_num]['is_available']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-clock text-success me-2"></i>
                                                            <span><?php echo date('h:i A', strtotime($doctor_schedule[$day_num]['start_time'])); ?> - 
                                                                  <?php echo date('h:i A', strtotime($doctor_schedule[$day_num]['end_time'])); ?></span>
                                                        </div>
                                                        <?php if ($doctor_schedule[$day_num]['break_start'] && $doctor_schedule[$day_num]['break_end']): ?>
                                                            <small class="text-muted">
                                                                <i class="fas fa-coffee me-1"></i>
                                                                Break: <?php echo date('h:i A', strtotime($doctor_schedule[$day_num]['break_start'])); ?> - 
                                                                       <?php echo date('h:i A', strtotime($doctor_schedule[$day_num]['break_end'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="text-muted">
                                                            <i class="fas fa-times me-2"></i>
                                                            Not Available
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if (isset($doctor_schedule[$day_num]) && $doctor_schedule[$day_num]['is_available']): ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Off</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($doctor_schedule)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                            <p>No schedule set yet</p>
                                            <small>Doctor needs to set their schedule from their dashboard</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Vacation and Emergency Leave Card -->
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>Vacation & Emergency Leave
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($doctor_leaves)): ?>
                                        <div class="row">
                                            <?php foreach ($doctor_leaves as $leave): ?>
                                                <?php
                                                $leave_class = '';
                                                $badge_class = '';
                                                $icon_class = '';
                                                $border_color = '#6c757d';
                                                
                                                // Map leave types to styles
                                                $leave_styles = [
                                                    'Annual' => ['class' => 'annual', 'badge' => 'bg-info', 'icon' => 'fa-calendar-alt', 'color' => '#17a2b8'],
                                                    'Sick' => ['class' => 'sick', 'badge' => 'bg-warning', 'icon' => 'fa-thermometer-half', 'color' => '#ffc107'],
                                                    'Maternity' => ['class' => 'maternity', 'badge' => 'bg-purple', 'icon' => 'fa-baby', 'color' => '#6f42c1'],
                                                    'Paternity' => ['class' => 'paternity', 'badge' => 'bg-primary', 'icon' => 'fa-user', 'color' => '#007bff'],
                                                    'Parental Leave' => ['class' => 'parental-leave', 'badge' => 'bg-success', 'icon' => 'fa-heart', 'color' => '#28a745'],
                                                    'Emergency Leave' => ['class' => 'emergency-leave', 'badge' => 'bg-danger', 'icon' => 'fa-exclamation-triangle', 'color' => '#dc3545'],
                                                    'Bereavement Leave' => ['class' => 'bereavement-leave', 'badge' => 'bg-dark', 'icon' => 'fa-cross', 'color' => '#343a40']
                                                ];
                                                
                                                if (isset($leave_styles[$leave['leave_type']])) {
                                                    $style = $leave_styles[$leave['leave_type']];
                                                    $leave_class = $style['class'];
                                                    $badge_class = $style['badge'];
                                                    $icon_class = $style['icon'];
                                                    $border_color = $style['color'];
                                                } else {
                                                    // Default fallback
                                                    $leave_class = strtolower(str_replace(' ', '-', $leave['leave_type']));
                                                    $badge_class = 'bg-secondary';
                                                    $icon_class = 'fa-calendar';
                                                }
                                                
                                                if ($leave['status'] == 'Cancelled') {
                                                    $leave_class .= ' cancelled';
                                                    $badge_class = 'bg-secondary';
                                                    $border_color = '#6c757d';
                                                }
                                                
                                                $today = date('Y-m-d');
                                                $is_past = $leave['end_date'] < $today;
                                                $is_current = $leave['start_date'] <= $today && $leave['end_date'] >= $today;
                                                $is_cancelled = $leave['status'] == 'Cancelled';
                                                ?>
                                                <div class="col-md-8 col-lg-6 mb-3">
                                                    <div class="schedule-day h-100 d-flex flex-column <?php echo $leave_class; ?>" style="border-left: 4px solid <?php echo $border_color; ?>; <?php echo $leave['status'] == 'Cancelled' ? 'opacity: 0.6;' : ''; ?>">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div class="d-flex align-items-center flex-wrap">
                                                                <i class="fas <?php echo $icon_class; ?> me-2" style="color: <?php echo $border_color; ?>;"></i>
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($leave['leave_type']); ?> Leave</h6>
                                                                <span class="badge <?php echo $badge_class; ?> ms-2"><?php echo htmlspecialchars($leave['status']); ?></span>
                                                                <?php if ($is_current): ?>
                                                                    <span class="badge bg-warning text-dark ms-2">Current</span>
                                                                <?php elseif ($is_past): ?>
                                                                    <span class="badge bg-secondary ms-2">Past</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-info ms-2">Upcoming</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="mb-2 flex-grow-1">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <strong>Period:</strong> 
                                                            <?php echo date('F j, Y', strtotime($leave['start_date'])); ?> - 
                                                            <?php echo date('F j, Y', strtotime($leave['end_date'])); ?>
                                                            <?php
                                                            $start = new DateTime($leave['start_date']);
                                                            $end = new DateTime($leave['end_date']);
                                                            $end->modify('+1 day'); // Include end date in count
                                                            $interval = $start->diff($end);
                                                            $days_count = $interval->days;
                                                            ?>
                                                            <span class="text-muted">(<?php echo $days_count; ?> day<?php echo $days_count != 1 ? 's' : ''; ?>)</span>
                                                        </div>
                                                        <?php if (!empty($leave['reason'])): ?>
                                                            <div class="mb-2">
                                                                <i class="fas fa-comment me-1"></i>
                                                                <strong>Reason:</strong> <?php echo htmlspecialchars($leave['reason']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="d-flex gap-2 mt-auto">
                                                            <?php if (!$is_cancelled): ?>
                                                                <button class="btn btn-sm btn-outline-warning flex-fill" onclick="cancelLeave(<?php echo $leave['id']; ?>, <?php echo $viewing_doctor_id; ?>)" title="Cancel Leave">
                                                                    <i class="fas fa-ban me-1"></i>Cancel
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-sm btn-outline-danger flex-fill" onclick="deleteLeave(<?php echo $leave['id']; ?>, <?php echo $viewing_doctor_id; ?>)" title="Delete Leave">
                                                                <i class="fas fa-trash me-1"></i>Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                            <p>No vacation or emergency leave entries</p>
                                            <small>Doctor can add leave entries from their dashboard</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                            break;
                        default:
                            echo "<p class='text-muted'>Invalid tab selected</p>";
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-md"></i>
                    <h5>Select a doctor to view details</h5>
                    <p>Choose a doctor from the list to view their profile, appointments, and schedule.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function viewDoctor(doctorId) {
    const search = new URLSearchParams(window.location.search).get('search') || '';
    window.location.href = `doctors.php?doctor_id=${doctorId}&tab=overview${search ? '&search=' + encodeURIComponent(search) : ''}`;
}

document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const search = new FormData(this).get('search');
    let url = 'doctors.php';
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    <?php if ($viewing_doctor_id): ?>
    params.append('doctor_id', '<?php echo $viewing_doctor_id; ?>');
    params.append('tab', '<?php echo $active_tab; ?>');
    <?php endif; ?>
    window.location.href = url + '?' + params.toString();
});

// Appointment management functions
function viewAppointment(id) {
  const modalEl = document.getElementById('viewAppointmentModal');
  const contentEl = document.getElementById('viewAppointmentContent');
  if (!modalEl || !contentEl) return;
  
  contentEl.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading appointment details...</p></div>';
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
  
  fetch('get_appointment_details.php?id=' + encodeURIComponent(id))
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        contentEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + data.error + '</div>';
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
        <hr>
        <div class="row">
          <div class="col-12">
            <h6 class="text-info mb-3"><i class="fas fa-user-md me-2"></i>Doctor Information</h6>
            <div class="mb-3">
              <strong>Doctor:</strong><br>
              <span class="text-muted">${data.doctor_name}</span>
            </div>
            ${data.doctor_phone ? '<div class="mb-3"><strong>Doctor Phone:</strong><br><span class="text-muted"><i class="fas fa-phone text-success me-1"></i>' + (data.doctor_phone.startsWith('+63') ? data.doctor_phone : (data.doctor_phone.startsWith('0') ? '+63' + data.doctor_phone.substring(1) : data.doctor_phone)) + '</span></div>' : ''}
            ${data.doctor_email ? '<div class="mb-3"><strong>Doctor Email:</strong><br><span class="text-muted"><i class="fas fa-envelope text-info me-1"></i>' + data.doctor_email + '</span></div>' : ''}
          </div>
        </div>
      `;
      
      contentEl.innerHTML = modalContent;
    })
    .catch(error => {
      console.error('Error fetching appointment details:', error);
      contentEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load appointment details. Please try again.</div>';
    });
}

// Handle month collapse icon rotation
document.addEventListener('DOMContentLoaded', function() {
    const monthHeaders = document.querySelectorAll('.month-header[data-bs-toggle="collapse"]');
    monthHeaders.forEach(header => {
        const targetId = header.getAttribute('data-bs-target');
        const collapseElement = document.querySelector(targetId);
        const icon = header.querySelector('.collapse-icon');
        
        if (collapseElement && icon) {
            collapseElement.addEventListener('show.bs.collapse', function() {
                icon.style.transform = 'rotate(180deg)';
            });
            collapseElement.addEventListener('hide.bs.collapse', function() {
                icon.style.transform = 'rotate(0deg)';
            });
        }
    });
    
    // Year filter functionality
    const yearFilterInput = document.getElementById('yearFilterInput');
    if (yearFilterInput) {
        yearFilterInput.addEventListener('input', function() {
            filterByYear(this.value.trim());
        });
    }
});

function filterByYear(year) {
    const yearFilterInput = document.getElementById('yearFilterInput');
    if (!yearFilterInput) return;
    
    // Find the card-body that contains the year filter input
    const pastAppointmentsCard = yearFilterInput.closest('.card');
    const cardBody = pastAppointmentsCard ? pastAppointmentsCard.querySelector('.card-body') : null;
    if (!cardBody) return;
    
    const monthGroups = cardBody.querySelectorAll('.month-group');
    let visibleCount = 0;
    
    monthGroups.forEach(group => {
        const groupYear = group.getAttribute('data-year');
        if (!year || groupYear === year || groupYear.includes(year)) {
            group.classList.remove('hidden');
            visibleCount++;
        } else {
            group.classList.add('hidden');
        }
    });
    
    // Show/hide "no results" message if needed
    const noResultsMsg = document.getElementById('noYearResults');
    if (year && visibleCount === 0) {
        if (!noResultsMsg) {
            const msg = document.createElement('div');
            msg.id = 'noYearResults';
            msg.className = 'text-center py-4 text-muted';
            msg.innerHTML = '<i class="fas fa-search fa-2x mb-2"></i><br>No appointments found for year ' + year;
            cardBody.appendChild(msg);
        }
    } else {
        if (noResultsMsg) {
            noResultsMsg.remove();
        }
    }
}

function cancelLeave(leaveId, doctorId) {
    confirmDialog('Are you sure you want to cancel this leave? Patients will be able to book appointments during this period.', 'Cancel Leave', 'Cancel').then(function(confirmed) {
        if (!confirmed) return;
        
        const formData = new FormData();
        formData.append('action', 'cancel_leave');
        formData.append('leave_id', leaveId);
        formData.append('doctor_id', doctorId);
        
        fetch('doctors.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show updated leaves
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to cancel leave'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error canceling leave. Please try again.');
        });
    });
}

function deleteLeave(leaveId, doctorId) {
    confirmDialog('Are you sure you want to permanently delete this leave? This action cannot be undone.', 'Delete Leave', 'Cancel').then(function(confirmed) {
        if (!confirmed) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_leave');
        formData.append('leave_id', leaveId);
        formData.append('doctor_id', doctorId);
        
        fetch('doctors.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show updated leaves
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to delete leave'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting leave. Please try again.');
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>