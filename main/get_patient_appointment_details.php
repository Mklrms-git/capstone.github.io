<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/patient_auth.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Invalid appointment ID</div>';
    exit;
}

$appointmentId = (int)$_GET['id'];
$conn = getDBConnection();

// Check if user is admin/doctor (from patients.php) or patient (from patient dashboard)
$is_admin_or_doctor = isLoggedIn() && (isAdmin() || isDoctor());
$is_patient = isPatientLoggedIn();

// Determine which patient_id to use
$target_patient_id = null;

if ($is_admin_or_doctor) {
    // Admin/doctor can view any appointment - get patient_id from appointment
    $query = "SELECT a.*, 
              u.first_name AS doctor_first_name, u.last_name AS doctor_last_name,
              u.specialization AS doctor_specialty, u.license_number AS doctor_license,
              u.email as doctor_email, u.phone as doctor_phone,
              d.name AS department_name, d.color AS department_color,
              COALESCE(a.notes, '') as notes,
              COALESCE(a.reason, '') as reason,
              a.patient_id
              FROM appointments a
              LEFT JOIN doctors doc ON a.doctor_id = doc.id
              LEFT JOIN users u ON doc.user_id = u.id
              LEFT JOIN departments d ON u.department_id = d.id
              WHERE a.id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Database error: ' . htmlspecialchars($conn->error) . '</div>';
        exit;
    }
    
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();
} elseif ($is_patient) {
    // Patient can only view their own appointments
    $patient_user = getCurrentPatientUser();
    
    if (!$patient_user) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Patient not found</div>';
        exit;
    }
    
    $query = "SELECT a.*, 
              u.first_name AS doctor_first_name, u.last_name AS doctor_last_name,
              u.specialization AS doctor_specialty, u.license_number AS doctor_license,
              u.email as doctor_email, u.phone as doctor_phone,
              d.name AS department_name, d.color AS department_color,
              COALESCE(a.notes, '') as notes,
              COALESCE(a.reason, '') as reason,
              a.patient_id
              FROM appointments a
              LEFT JOIN doctors doc ON a.doctor_id = doc.id
              LEFT JOIN users u ON doc.user_id = u.id
              LEFT JOIN departments d ON u.department_id = d.id
              WHERE a.id = ? AND a.patient_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Database error: ' . htmlspecialchars($conn->error) . '</div>';
        exit;
    }
    
    $stmt->bind_param("ii", $appointmentId, $patient_user['patient_id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Not logged in at all
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>You must be logged in to view appointment details.</div>';
    exit;
}

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Appointment not found</div>';
    exit;
}

$appointment = $result->fetch_assoc();

// Format the data for display
$formattedDate = date('F j, Y (l)', strtotime($appointment['appointment_date']));
$formattedTime = date('g:i A', strtotime($appointment['appointment_time']));
$formattedDateTime = $formattedDate . ' at ' . $formattedTime;

$doctor_name = trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''));
$doctor_display = $doctor_name ? 'Dr. ' . $doctor_name : 'Dr. Unknown Doctor';

// Status badge class
$statusClass = match(strtolower($appointment['status'] ?? '')) {
    'settled' => 'success',
    'scheduled' => 'primary',
    'ongoing' => 'warning',
    'cancelled', 'canceled' => 'danger',
    default => 'light'
};

?>
<div class="row">
    <div class="col-md-12">
        <h6 class="text-primary mb-3"><i class="fas fa-calendar-check me-2"></i>Appointment Information</h6>
        
        <div class="mb-3">
            <strong><i class="fas fa-calendar me-2 text-primary"></i>Date & Time:</strong><br>
            <span class="text-muted"><?= htmlspecialchars($formattedDateTime); ?></span>
        </div>
        
        <div class="mb-3">
            <strong><i class="fas fa-user-md me-2 text-primary"></i>Doctor:</strong><br>
            <span class="text-muted"><?= htmlspecialchars($doctor_display); ?></span>
            <?php if (!empty($appointment['doctor_specialty'])): ?>
                <br><small class="text-muted">(<?= htmlspecialchars($appointment['doctor_specialty']); ?>)</small>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($appointment['department_name'])): ?>
        <div class="mb-3">
            <strong><i class="fas fa-building me-2 text-primary"></i>Department:</strong><br>
            <span class="badge" style="background-color: <?= htmlspecialchars($appointment['department_color'] ?? '#6c757d'); ?>;">
                <?= htmlspecialchars($appointment['department_name']); ?>
            </span>
        </div>
        <?php endif; ?>
        
        <div class="mb-3">
            <strong><i class="fas fa-info-circle me-2 text-primary"></i>Status:</strong><br>
            <span class="badge bg-<?= $statusClass; ?>"><?= ucfirst($appointment['status'] ?? 'Scheduled'); ?></span>
        </div>
        
        <?php if (!empty($appointment['reason'])): ?>
        <div class="mb-3">
            <strong><i class="fas fa-file-alt me-2 text-primary"></i>Reason:</strong><br>
            <span class="text-muted"><?= htmlspecialchars($appointment['reason']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($appointment['notes'])): ?>
        <div class="mb-3">
            <strong><i class="fas fa-sticky-note me-2 text-primary"></i>Notes:</strong><br>
            <span class="text-muted"><?= htmlspecialchars($appointment['notes']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($appointment['doctor_license'])): ?>
        <div class="mb-3">
            <strong><i class="fas fa-certificate me-2 text-primary"></i>Doctor License:</strong><br>
            <span class="text-muted"><?= htmlspecialchars($appointment['doctor_license']); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
