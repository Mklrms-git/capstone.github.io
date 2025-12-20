<?php
// File: includes/patient_appointments.php
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Fetch appointments for this patient
$stmt = $conn->prepare("SELECT a.*, u.first_name AS doctor_first_name, u.last_name AS doctor_last_name,
                               u.specialization AS doctor_specialty, u.license_number AS doctor_license,
                               d.name AS department_name, d.color AS department_color
                        FROM appointments a
                        LEFT JOIN doctors doc ON a.doctor_id = doc.id
                        LEFT JOIN users u ON doc.user_id = u.id
                        LEFT JOIN departments d ON u.department_id = d.id
                        WHERE a.patient_id = ?
                        ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$stmt->bind_param("i", $patient_details['id']);
$stmt->execute();
$appointments = $stmt->get_result();

$upcoming_appointments = [];
$past_appointments = [];
$today = date('Y-m-d');
$currentDateTime = new DateTime();

while ($appointment = $appointments->fetch_assoc()) {
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
                                <?php
                                // Determine status badge class - match database ENUM: 'scheduled', 'ongoing', 'settled', 'cancelled'
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
                                <div class="record-card mb-3" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; background: white; margin-left: 20px;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1 d-flex align-items-center">
                                            <i class="fas fa-calendar-check me-3" style="color: #6c757d; font-size: 1.5rem;"></i>
                                            <div>
                                                <div style="color: #333; font-weight: 600; font-size: 0.95rem;">
                                                    <?= date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                                    <span class="badge bg-<?= $statusClass; ?> ms-2"><?= htmlspecialchars($statusDisplay); ?></span>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                    <?php if (!empty($appointment['doctor_first_name']) && !empty($appointment['doctor_last_name'])): ?>
                                                        • Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
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

<!-- JS -->
<style>
.month-header { transition: background-color 0.2s; }
.month-header:hover { background-color: #e9ecef !important; }
.month-header .collapse-icon { transition: transform 0.3s; }
.month-header[aria-expanded="true"] .collapse-icon { transform: rotate(180deg); }
.month-appointments { padding-top: 10px; }
.month-group { transition: opacity 0.3s, max-height 0.3s; }
.month-group.hidden { display: none !important; }
</style>
<script>
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
            ${data.patient_phone ? '<div class="mb-3"><strong>Phone:</strong><br><span class="text-muted"><i class="fas fa-phone text-success me-1"></i>' + (data.patient_phone.startsWith('+63') ? data.patient_phone : (data.patient_phone.startsWith('0') ? '+63' + data.patient_phone.substring(1) : data.patient_phone)) + '</span></div>' : ''}
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
</script>
