<?php
define('MHAVIS_EXEC', true);
$page_title = "Appointments";
$active_page = "appointments";
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/patient_auth.php'; // For notification functions
require_once __DIR__ . '/process_notifications.php'; // For email queue processing
requireAdmin();

$conn = getDBConnection();

// Handle appointment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_appointment') {
    // Admin can create appointments
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $appointment_date = sanitize($_POST['appointment_date']);
    $appointment_time = sanitize($_POST['appointment_time']);
    $reason = sanitize($_POST['reason']);
    $notes = sanitize($_POST['notes']);
    
    // Validate required fields
    if (empty($patient_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time) || empty($reason)) {
        $error = "Please fill in all required fields.";
    } else {
        // Verify the doctor exists in the doctors table
        // Note: $doctor_id from form is doctors.id (already the correct ID for appointments table)
        $stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $doctor_result = $stmt->get_result();
        
        if ($doctor_result->num_rows === 0) {
            $error = "Selected doctor not found in the system.";
        } else {
            // Use doctor_id directly since it's already doctors.id
            $doctor_table_id = $doctor_id;
            
            // Check for time conflicts - use exact ENUM values (case-sensitive)
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments 
                                   WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                                   AND status != 'cancelled'");
            $stmt->bind_param("iss", $doctor_table_id, $appointment_date, $appointment_time);
            $stmt->execute();
            $result = $stmt->get_result();
            $conflict = $result->fetch_assoc()['count'];
            
            if ($conflict > 0) {
                $error = "This time slot is already booked for the selected doctor.";
            } else {
                // Create appointment
                $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, notes, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, 'scheduled')");
                $stmt->bind_param("iissss", $patient_id, $doctor_table_id, $appointment_date, $appointment_time, $reason, $notes);
                
                if ($stmt->execute()) {
                    $success = "Appointment scheduled successfully!";
                    $appointment_id = $conn->insert_id;
                    
                    // Get patient and doctor details for notifications
                    // Note: $doctor_table_id is already doctors.id, so we join to get user_id
                    $stmt = $conn->prepare("SELECT p.id as patient_id, p.first_name as patient_first_name, p.last_name as patient_last_name, p.email as patient_email,
                                            d.user_id as doctor_user_id,
                                            u.first_name as doctor_first_name, u.last_name as doctor_last_name
                                            FROM patients p
                                            CROSS JOIN doctors d ON d.id = ?
                                            CROSS JOIN users u ON u.id = d.user_id
                                            WHERE p.id = ?");
                    $stmt->bind_param("ii", $doctor_table_id, $patient_id);
                    $stmt->execute();
                    $details_result = $stmt->get_result();
                    
                    if ($details_result->num_rows > 0) {
                        $details = $details_result->fetch_assoc();
                        $patient_name = trim(($details['patient_first_name'] ?? '') . ' ' . ($details['patient_last_name'] ?? ''));
                        $doctor_name = trim(($details['doctor_first_name'] ?? '') . ' ' . ($details['doctor_last_name'] ?? ''));
                        $patient_email = $details['patient_email'] ?? '';
                        $doctor_user_id = $details['doctor_user_id'] ?? null;
                        
                        // Format appointment details
                        $formatted_date = date('l, F j, Y', strtotime($appointment_date));
                        $formatted_time = date('g:i A', strtotime($appointment_time));
                        
                        // Get patient user ID if exists
                        $patient_user_id = null;
                        $patient_user_stmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ? LIMIT 1");
                        $patient_user_stmt->bind_param("i", $patient_id);
                        $patient_user_stmt->execute();
                        $patient_user_result = $patient_user_stmt->get_result();
                        if ($patient_user_result->num_rows > 0) {
                            $patient_user_row = $patient_user_result->fetch_assoc();
                            $patient_user_id = $patient_user_row['id'];
                        }
                        $patient_user_stmt->close();
                        
                        // Send email notification to patient (if email exists)
                        if (!empty($patient_email) && function_exists('sendEmailNotification')) {
                            $email_subject = "Appointment Scheduled - Mhavis Medical Center";
                            
                            $email_body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                                <div style='background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                                    <h2 style='margin: 0;'>✅ Appointment Scheduled!</h2>
                                </div>
                                <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                                    <p style='font-size: 16px; color: #333;'>Dear <strong>{$patient_name}</strong>,</p>
                                    
                                    <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                        Your appointment has been successfully scheduled by our administration team.
                                    </p>
                                    
                                    <div style='background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;'>
                                        <h3 style='margin-top: 0; color: #333;'>📅 Appointment Details</h3>
                                        <p style='margin: 10px 0;'><strong>Doctor:</strong> Dr. {$doctor_name}</p>
                                        <p style='margin: 10px 0;'><strong>Date:</strong> {$formatted_date}</p>
                                        <p style='margin: 10px 0;'><strong>Time:</strong> {$formatted_time}</p>
                                        " . (!empty($reason) ? "<p style='margin: 10px 0;'><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : "") . "
                                    </div>
                                    
                                    <div style='background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;'>
                                        <p style='margin: 0; font-size: 14px; color: #856404;'>
                                            ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.
                                        </p>
                                    </div>
                                    
                                    <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                        If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.
                                    </p>
                                    
                                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                                    
                                    <p style='font-size: 13px; color: #777; text-align: center; margin: 0;'>
                                        Best regards,<br>
                                        <strong>Mhavis Medical & Diagnostic Center</strong><br>
                                        Healthcare Team
                                    </p>
                                </div>
                            </div>
                            ";
                            
                            sendEmailNotification($patient_email, $patient_name, $email_subject, $email_body, 'html');
                            
                            // Process email queue immediately
                            if (function_exists('processEmailQueue')) {
                                processEmailQueue();
                            }
                        }
                        
                        // Create in-app notification for patient
                        if ($patient_user_id && function_exists('createNotification')) {
                            $patient_title = "Appointment Scheduled";
                            $patient_message = "Your appointment with Dr. {$doctor_name} has been scheduled for {$formatted_date} at {$formatted_time}.";
                            createNotification('Patient', $patient_user_id, 'Appointment_Scheduled', $patient_title, $patient_message, 'System');
                        }
                        
                        // Create in-app notification for doctor
                        if ($doctor_user_id && function_exists('createNotification')) {
                            $doctor_title = 'New Appointment Assigned';
                            $doctor_message = "You have a new appointment.\n\n" .
                                           "Patient: " . $patient_name . "\n" .
                                           "Date: " . $formatted_date . "\n" .
                                           "Time: " . $formatted_time . "\n\n" .
                                           "This appointment was scheduled by an administrator.";
                            createNotification('Doctor', $doctor_user_id, 'New_Assigned_Patient', $doctor_title, $doctor_message, 'System');
                        }
                        
                        // Create in-app notification for admin (the one who made the booking)
                        if (isset($_SESSION['user_id']) && isAdmin() && function_exists('createNotification')) {
                            $admin_title = "Appointment Booked";
                            $admin_message = "You have successfully booked an appointment for {$patient_name} with Dr. {$doctor_name} on {$formatted_date} at {$formatted_time}.";
                            createNotification('Admin', $_SESSION['user_id'], 'Appointment_Booked', $admin_title, $admin_message, 'System');
                        }
                    }
                } else {
                    $error = "Error creating appointment: " . $conn->error;
                }
            }
        }
    }
}

// Get all patients
$patients_query = "SELECT id, first_name, last_name, phone, email, date_of_birth, sex 
                   FROM patients 
                   ORDER BY last_name, first_name";
$patients_result = $conn->query($patients_query);
$patients = [];
while ($row = $patients_result->fetch_assoc()) {
    $patients[] = $row;
}

// Get all doctors (only active and available) with department info
$doctors_query = "SELECT d.id, u.first_name, u.last_name, d.specialization, 
                         dept.id as department_id, dept.name as department_name, dept.color as department_color
                  FROM doctors d
                  JOIN users u ON d.user_id = u.id
                  LEFT JOIN departments dept ON u.department_id = dept.id
                  WHERE u.role = 'Doctor' AND u.status = 'Active' AND u.is_available = 1
                  ORDER BY dept.name, u.last_name, u.first_name";
$doctors_result = $conn->query($doctors_query);
$doctors = [];
while ($row = $doctors_result->fetch_assoc()) {
    $doctors[] = $row;
}

// Get today's appointments - include all statuses except cancelled
$todays_query = "SELECT a.*, 
                   CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                   p.phone as patient_phone,
                   dept.name as department_name,
                   dept.color as department_color
                 FROM appointments a
                 JOIN patients p ON a.patient_id = p.id
                 JOIN doctors d ON a.doctor_id = d.id
                 JOIN users u ON d.user_id = u.id
                 LEFT JOIN departments dept ON u.department_id = dept.id
                 WHERE a.appointment_date = CURDATE()
                 AND a.status != 'cancelled'
                 ORDER BY a.appointment_time";
$todays_result = $conn->query($todays_query);
$todays_appointments = [];
while ($row = $todays_result->fetch_assoc()) {
    $todays_appointments[] = $row;
}

// Get upcoming appointments - include all statuses except cancelled
$upcoming_query = "SELECT a.*, 
                   CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                   p.phone as patient_phone,
                   p.email as patient_email,
                   dept.name as department_name,
                   dept.color as department_color
                   FROM appointments a
                   JOIN patients p ON a.patient_id = p.id
                   JOIN doctors d ON a.doctor_id = d.id
                   JOIN users u ON d.user_id = u.id
                   LEFT JOIN departments dept ON u.department_id = dept.id
                   WHERE a.appointment_date >= CURDATE() 
                   AND a.status != 'cancelled'
                   ORDER BY a.appointment_date, a.appointment_time";
$upcoming_result = $conn->query($upcoming_query);
$upcoming_appointments = [];
while ($row = $upcoming_result->fetch_assoc()) {
    $upcoming_appointments[] = $row;
}

include 'includes/header.php';
?>

<style>
.patient-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.patient-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-color: #0c1a6a;
}

.patient-card.selected {
    border-color: #0c1a6a;
    background-color: #f8f9ff;
}

.appointment-card {
    border-left: 4px solid #0c1a6a;
    transition: all 0.3s ease;
}

.appointment-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.status-badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

.search-box {
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
    padding: 15px 0;
    border-bottom: 1px solid #dee2e6;
}

.patient-list {
    max-height: 70vh;
    overflow-y: auto;
}

.appointment-list {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-header {
    background-color: #0c1a6a;
    color: white;
}

.btn-primary {
    background-color: #0c1a6a;
    border-color: #0c1a6a;
}

.btn-primary:hover {
    background-color: #1a2fa0;
    border-color: #1a2fa0;
}

.form-control:focus {
    border-color: #0c1a6a;
    box-shadow: 0 0 0 0.2rem rgba(12, 26, 106, 0.25);
}
</style>

<div class="container-fluid">
    <!-- Success/Error Messages -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Today's Appointments Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-calendar-day me-2"></i>Today's Appointments
            </h5>
            <span class="badge bg-primary"><?php echo count($todays_appointments); ?> today</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($todays_appointments)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="fas fa-calendar-times fa-2x mb-2"></i><br>
                    No appointments scheduled for today
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 140px;">Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Reason</th>
                                <th style="width: 120px;">Status</th>
                                <th style="width: 120px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todays_appointments as $appt): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-clock me-1 text-muted"></i>
                                        <?php echo formatTime($appt['appointment_time']); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appt['patient_name']); ?></strong><br>
                                        <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars(formatPhoneNumber($appt['patient_phone'])); ?></small>
                                    </td>
                                    <td>
                                        <strong>Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></strong><br>
                                        <?php if (!empty($appt['department_name'])): ?>
                                            <span class="badge" style="background-color: <?php echo htmlspecialchars($appt['department_color'] ?? '#6c757d'); ?>; font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($appt['department_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($appt['reason']); ?></td>
                                    <td>
                                        <?php
                                        $statusLower = strtolower(trim($appt['status'] ?? ''));
                                        $statusClass = match($statusLower) {
                                            'scheduled' => 'primary',
                                            'ongoing' => 'warning',
                                            'settled' => 'success',
                                            'cancelled', 'canceled' => 'danger',
                                            default => 'secondary'
                                        };
                                        $statusDisplay = ucfirst($appt['status'] ?? 'Unknown');
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusDisplay); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewAppointment(<?php echo $appt['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="editAppointment(<?php echo $appt['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Left Side - Patient List -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Select Patient
                    </h5>
                </div>
                <div class="card-body p-0">
                    <!-- Search Box -->
                    <div class="search-box">
                        <div class="px-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control" id="patientSearch" placeholder="Search patients...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Patient List -->
                    <div class="patient-list">
                        <?php if (empty($patients)): ?>
                            <div class="text-center p-4">
                                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No patients found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <div class="patient-card p-3 border-bottom" 
                                     data-patient-id="<?php echo $patient['id']; ?>"
                                     data-patient-name="<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>"
                                     data-patient-phone="<?php echo htmlspecialchars(formatPhoneNumber($patient['phone'])); ?>"
                                     data-patient-email="<?php echo htmlspecialchars($patient['email']); ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h6>
                                            <p class="text-muted small mb-1">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars(formatPhoneNumber($patient['phone'])); ?>
                                            </p>
                                            <p class="text-muted small mb-0">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($patient['email']); ?>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <button class="btn btn-sm btn-outline-primary" onclick="scheduleAppointment(<?php echo $patient['id']; ?>)">
                                                <i class="fas fa-calendar-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Upcoming Appointments -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-check me-2"></i>Upcoming Appointments
                    </h5>
                    <span class="badge bg-primary"><?php echo count($upcoming_appointments); ?> appointments</span>
                </div>
                <div class="card-body p-0">
                    <div class="appointment-list">
                        <?php if (empty($upcoming_appointments)): ?>
                            <div class="text-center p-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No upcoming appointments</h6>
                                <p class="text-muted">Select a patient to schedule an appointment</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="appointment-card p-3 border-bottom">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($appointment['patient_name']); ?></h6>
                                                    <p class="text-muted small mb-0">
                                                        <i class="fas fa-user-md me-1"></i>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                                        <?php if (!empty($appointment['department_name'])): ?>
                                                            <span class="badge ms-2" style="background-color: <?php echo htmlspecialchars($appointment['department_color'] ?? '#6c757d'); ?>; font-size: 0.65rem;">
                                                                <?php echo htmlspecialchars($appointment['department_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <!-- Intentionally hide appointment details (date/time/reason) on the card.
                                                 Details are available inside the View Appointment modal. -->
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <?php
                                            $statusLower = strtolower(trim($appointment['status'] ?? ''));
                                            $statusClass = match($statusLower) {
                                                'scheduled' => 'primary',
                                                'ongoing' => 'warning',
                                                'settled' => 'success',
                                                'cancelled', 'canceled' => 'danger',
                                                default => 'secondary'
                                            };
                                            $statusDisplay = ucfirst($appointment['status'] ?? 'Unknown');
                                            ?>
                                            <span class="badge status-badge bg-<?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($statusDisplay); ?>
                                            </span>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-primary me-1" 
                                                        onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="editAppointment(<?php echo $appointment['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Scheduling Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">
                    <i class="fas fa-calendar-plus me-2"></i>Schedule Appointment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="appointmentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_appointment">
                    <input type="hidden" name="patient_id" id="modal_patient_id">
                    
                    <!-- Patient Info Display -->
                    <div class="alert alert-info">
                        <h6 class="mb-1">Patient Information</h6>
                        <p class="mb-0" id="modal_patient_info">Select a patient to schedule appointment</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="doctor_id" class="form-label">Doctor <span class="text-danger">*</span></label>
                                <select class="form-select" name="doctor_id" id="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php 
                                    $current_dept = '';
                                    foreach ($doctors as $doctor): 
                                        $dept_name = $doctor['department_name'] ?? 'Unassigned';
                                        if ($dept_name != $current_dept) {
                                            if ($current_dept != '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($dept_name) . '">';
                                            $current_dept = $dept_name;
                                        }
                                    ?>
                                        <option value="<?php echo $doctor['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            <?php if ($doctor['specialization']): ?>
                                                - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($current_dept != '') echo '</optgroup>'; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="appointment_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="appointment_date" id="appointment_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="appointment_time" class="form-label">Time <span class="text-danger">*</span></label>
                                <select class="form-select" name="appointment_time" id="appointment_time" required disabled>
                                    <option value="">Select Doctor and Date first</option>
                                </select>
                                <small class="text-muted" id="time_slots_help">Please select a doctor and date to see available time slots</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for Visit <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="reason" id="reason" 
                                       placeholder="e.g., General Checkup, Consultation" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="3" 
                                  placeholder="Additional notes or special instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-1"></i>Schedule Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Patient search functionality
document.getElementById('patientSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const patientCards = document.querySelectorAll('.patient-card');
    
    patientCards.forEach(card => {
        const patientName = card.querySelector('h6').textContent.toLowerCase();
        const patientPhone = card.querySelector('.text-muted.small').textContent.toLowerCase();
        
        if (patientName.includes(searchTerm) || patientPhone.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Patient selection
document.querySelectorAll('.patient-card').forEach(card => {
    card.addEventListener('click', function() {
        // Remove selected class from all cards
        document.querySelectorAll('.patient-card').forEach(c => c.classList.remove('selected'));
        // Add selected class to clicked card
        this.classList.add('selected');
    });
});

// Schedule appointment function
function scheduleAppointment(patientId) {
    const patientCard = document.querySelector(`[data-patient-id="${patientId}"]`);
    const patientName = patientCard.getAttribute('data-patient-name');
    const patientPhone = patientCard.getAttribute('data-patient-phone');
    const patientEmail = patientCard.getAttribute('data-patient-email');
    
    // Set patient info in modal
    document.getElementById('modal_patient_id').value = patientId;
    document.getElementById('modal_patient_info').innerHTML = `
        <strong>${patientName}</strong><br>
        <small class="text-muted">
            <i class="fas fa-phone me-1"></i>${patientPhone}<br>
            <i class="fas fa-envelope me-1"></i>${patientEmail}
        </small>
    `;
    
    // Reset form fields when modal opens
    document.getElementById('doctor_id').value = '';
    document.getElementById('appointment_date').value = '';
    document.getElementById('appointment_time').innerHTML = '<option value="">Select Doctor and Date first</option>';
    document.getElementById('appointment_time').disabled = true;
    document.getElementById('time_slots_help').textContent = 'Please select a doctor and date to see available time slots';
    document.getElementById('reason').value = '';
    document.getElementById('notes').value = '';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
    modal.show();
}

// Load available time slots when doctor and date are selected
function loadAvailableTimeSlots() {
    const doctorId = document.getElementById('doctor_id').value;
    const appointmentDate = document.getElementById('appointment_date').value;
    const timeSelect = document.getElementById('appointment_time');
    const helpText = document.getElementById('time_slots_help');
    
    // Reset time select
    timeSelect.innerHTML = '<option value="">Loading...</option>';
    timeSelect.disabled = true;
    
    // Check if both doctor and date are selected
    if (!doctorId || !appointmentDate) {
        timeSelect.innerHTML = '<option value="">Select Doctor and Date first</option>';
        helpText.textContent = 'Please select a doctor and date to see available time slots';
        return;
    }
    
    // Show loading state
    helpText.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading available time slots...';
    
    // Fetch available time slots
    fetch(`get_available_time_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(appointmentDate)}`)
        .then(response => response.json())
        .then(data => {
            timeSelect.innerHTML = '';
            
            if (!data.success || !data.time_slots || data.time_slots.length === 0) {
                timeSelect.innerHTML = '<option value="">No slots available</option>';
                timeSelect.disabled = true;
                helpText.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>${data.message || 'No available time slots for this date'}</span>`;
                return;
            }
            
            // Populate time slots
            data.time_slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.display;
                timeSelect.appendChild(option);
            });
            
            timeSelect.disabled = false;
            helpText.innerHTML = `<span class="text-success"><i class="fas fa-check-circle me-1"></i>${data.time_slots.length} time slot(s) available</span>`;
        })
        .catch(error => {
            console.error('Error loading time slots:', error);
            timeSelect.innerHTML = '<option value="">Error loading slots</option>';
            timeSelect.disabled = true;
            helpText.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error loading time slots. Please try again.</span>';
        });
}

// Add event listeners for doctor and date changes
document.addEventListener('DOMContentLoaded', function() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const appointmentModal = document.getElementById('appointmentModal');
    
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function() {
            loadAvailableTimeSlots();
        });
    }
    
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            loadAvailableTimeSlots();
        });
    }
    
    // Reset form when modal is hidden
    if (appointmentModal) {
        appointmentModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('doctor_id').value = '';
            document.getElementById('appointment_date').value = '';
            document.getElementById('appointment_time').innerHTML = '<option value="">Select Doctor and Date first</option>';
            document.getElementById('appointment_time').disabled = true;
            document.getElementById('time_slots_help').textContent = 'Please select a doctor and date to see available time slots';
            document.getElementById('reason').value = '';
            document.getElementById('notes').value = '';
        });
    }
});

// View appointment function (opens modal)
function viewAppointment(appointmentId) {
    // Ensure modal exists
    const modalEl = document.getElementById('viewAppointmentModal');
    const bodyEl = document.getElementById('viewAppointmentBody');
    if (!modalEl || !bodyEl) return;

    bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading details...</p></div>';
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    fetch('get_appointment_details.php?id=' + encodeURIComponent(appointmentId))
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                bodyEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${data.error}</div>`;
                return;
            }

            const statusBadge = `<span class="badge bg-${data.status_class}">${data.status}</span>`;

            bodyEl.innerHTML = `
                <div class="mb-3">
                    <h5 class="mb-1">Appointment ${statusBadge}</h5>
                    <div class="text-muted"><i class="fas fa-calendar me-1"></i>${data.formatted_datetime}</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-2"><i class="fas fa-user me-1"></i>Patient</h6>
                                <div><strong>${data.patient_name}</strong></div>
                                <div class="small text-muted">${data.patient_age || ''} ${data.patient_sex ? '(' + data.patient_sex + ')' : ''}</div>
                                <div class="small mt-2"><i class="fas fa-phone me-1"></i>${data.patient_phone || ''}</div>
                                <div class="small"><i class="fas fa-envelope me-1"></i>${data.patient_email || ''}</div>
                                <div class="small"><i class="fas fa-map-marker-alt me-1"></i>${data.patient_address || ''}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-2"><i class="fas fa-user-md me-1"></i>Doctor</h6>
                                <div><strong>Dr. ${data.doctor_name}</strong></div>
                                <div class="small mt-2"><i class="fas fa-phone me-1"></i>${data.doctor_phone || ''}</div>
                                <div class="small"><i class="fas fa-envelope me-1"></i>${data.doctor_email || ''}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title mb-2"><i class="fas fa-notes-medical me-1"></i>Reason</h6>
                        <div>${(data.reason || '').toString().length ? data.reason : '<span class="text-muted">No reason provided</span>'}</div>
                        <hr class="my-3">
                        <h6 class="card-title mb-2"><i class="fas fa-sticky-note me-1"></i>Notes</h6>
                        <div>${(data.notes || '').toString().length ? data.notes : '<span class="text-muted">No notes</span>'}</div>
                    </div>
                </div>
            `;
        })
        .catch(() => {
            bodyEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load appointment details.</div>';
        });
}

// Edit appointment function (opens modal with status form)
function editAppointment(appointmentId) {
    const modalEl = document.getElementById('editAppointmentModal');
    const bodyEl = document.getElementById('editAppointmentBody');
    const alertEl = document.getElementById('editAppointmentAlert');
    if (!modalEl || !bodyEl) return;

    alertEl.classList.add('d-none');
    alertEl.innerHTML = '';

    bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning" role="status"></div><p class="mt-2">Loading appointment...</p></div>';
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Load the edit form from edit_appointment.php (same as admin dashboard) - includes cancellation reason field
    fetch('edit_appointment.php?id=' + encodeURIComponent(appointmentId))
        .then(response => response.text())
        .then(html => {
            bodyEl.innerHTML = html;
            
            // Initialize cancellation reason toggle functionality
            function initCancellationToggle(formContainer) {
                const statusSelect = formContainer.querySelector('#status');
                const cancellationReasonGroup = formContainer.querySelector('#cancellationReasonGroup');
                const cancellationReasonField = formContainer.querySelector('#cancellation_reason');
                
                if (!statusSelect || !cancellationReasonGroup || !cancellationReasonField) {
                    return;
                }
                
                // Function to toggle cancellation reason field
                function toggleCancellationReason() {
                    const selectedStatus = statusSelect.value.toLowerCase();
                    if (selectedStatus === 'cancelled') {
                        // Show with animation
                        cancellationReasonGroup.style.display = 'block';
                        // Force reflow for animation
                        cancellationReasonGroup.offsetHeight;
                        cancellationReasonGroup.style.maxHeight = '500px';
                        cancellationReasonGroup.style.opacity = '1';
                        cancellationReasonField.setAttribute('required', 'required');
                        // Focus on the textarea after a short delay for smooth UX
                        setTimeout(() => {
                            if (cancellationReasonField) {
                                cancellationReasonField.focus();
                            }
                        }, 300);
                    } else {
                        // Hide with animation
                        cancellationReasonGroup.style.maxHeight = '0';
                        cancellationReasonGroup.style.opacity = '0';
                        cancellationReasonField.removeAttribute('required');
                        cancellationReasonField.value = '';
                        setTimeout(() => {
                            if (statusSelect && statusSelect.value.toLowerCase() !== 'cancelled') {
                                cancellationReasonGroup.style.display = 'none';
                            }
                        }, 300);
                    }
                }
                
                // Check initial status on load
                toggleCancellationReason();
                
                // Listen for status changes
                statusSelect.addEventListener('change', toggleCancellationReason);
            }
            
            // The form from edit_appointment.php already has its own submit handler with cancellation reason validation
            // We just need to override the success handler to reload this page
            const form = bodyEl.querySelector('#editAppointmentForm');
            if (form) {
                // Remove the existing submit handler by cloning the form
                const newForm = form.cloneNode(true);
                form.parentNode.replaceChild(newForm, form);
                
                // Initialize toggle after cloning (cloning removes event listeners)
                initCancellationToggle(bodyEl);
                
                // Add our submit handler that handles the response for this page
                newForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const submitBtn = newForm.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    
                    // Get cancellation reason field if it exists
                    const cancellationReasonField = newForm.querySelector('#cancellation_reason');
                    const statusSelect = newForm.querySelector('#status');
                    
                    // Validate cancellation reason if status is cancelled
                    if (statusSelect && statusSelect.value.toLowerCase() === 'cancelled') {
                        if (cancellationReasonField && !cancellationReasonField.value.trim()) {
                            alertEl.classList.remove('d-none', 'alert-success');
                            alertEl.classList.add('alert-danger');
                            alertEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please provide a reason for cancelling the appointment.';
                            cancellationReasonField.focus();
                            return;
                        }
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
                    
                    const formData = new FormData(newForm);
                    fetch('edit_appointment.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(async response => {
                        const contentType = response.headers.get('content-type');
                        if (!response.ok) {
                            // Try to parse JSON error response
                            if (contentType && contentType.includes('application/json')) {
                                try {
                                    const data = await response.json();
                                    throw new Error(data.error || `Server error (${response.status})`);
                                } catch (e) {
                                    if (e instanceof Error) {
                                        throw e;
                                    }
                                    throw new Error(`Server error (${response.status}): ${response.statusText}`);
                                }
                            } else {
                                const text = await response.text();
                                throw new Error(text || `Server error (${response.status}): ${response.statusText}`);
                            }
                        }
                        // Success response
                        if (contentType && contentType.includes('application/json')) {
                            return response.json();
                        } else {
                            return { success: true };
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            // Show success and refresh to reflect changes
                            alertEl.classList.remove('d-none');
                            alertEl.classList.remove('alert-danger');
                            alertEl.classList.add('alert-success');
                            alertEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>Appointment updated successfully. Refreshing...';
                            setTimeout(() => {
                                modal.hide();
                                window.location.reload();
                            }, 1000);
                        } else {
                            alertEl.classList.remove('d-none');
                            alertEl.classList.remove('alert-success');
                            alertEl.classList.add('alert-danger');
                            alertEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (data.error || 'Failed to update appointment.');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alertEl.classList.remove('d-none');
                        alertEl.classList.remove('alert-success');
                        alertEl.classList.add('alert-danger');
                        alertEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (error.message || 'Failed to update appointment. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    });
                });
            }
        })
        .catch(error => {
            console.error('Error loading edit form:', error);
            bodyEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load appointment form. Please try again.</div>';
        });
}

// Form validation
document.getElementById('appointmentForm').addEventListener('submit', function(e) {
    const doctorId = document.getElementById('doctor_id').value;
    const appointmentDate = document.getElementById('appointment_date').value;
    const appointmentTime = document.getElementById('appointment_time').value;
    const reason = document.getElementById('reason').value;
    
    if (!doctorId || !appointmentDate || !appointmentTime || !reason) {
        e.preventDefault();
        showAlert('Please fill in all required fields.', 'Validation Error', 'warning');
        return;
    }
    
    // Check if date is not in the past
    const today = new Date().toISOString().split('T')[0];
    if (appointmentDate < today) {
        e.preventDefault();
        showAlert('Appointment date cannot be in the past.', 'Validation Error', 'warning');
        return;
    }
});
</script>

<!-- VIEW APPOINTMENT MODAL -->
<div class="modal fade" id="viewAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Appointment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewAppointmentBody">
                <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
    </div>

<!-- EDIT APPOINTMENT MODAL -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="alert d-none m-3" id="editAppointmentAlert"></div>
            <div class="modal-body" id="editAppointmentBody">
                <div class="text-center py-4"><div class="spinner-border text-warning" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
