<?php
define('MHAVIS_EXEC', true);
$page_title = "Schedules";
$active_page = "schedules";
require_once __DIR__ . '/config/init.php';
requireDoctor();

$conn = getDBConnection();
$doctorId = $_SESSION['user_id'];

// Initialize messages
$success_message = '';
$error_message = '';

// Handle schedule update
if ($_POST && isset($_POST['update_schedule'])) {
    foreach ($_POST['schedule'] as $day => $schedule_data) {
        $is_available = isset($schedule_data['is_available']) ? 1 : 0;
        $start_time = !empty($schedule_data['start_time']) ? $schedule_data['start_time'] : null;
        $end_time = !empty($schedule_data['end_time']) ? $schedule_data['end_time'] : null;
        $break_start = !empty($schedule_data['break_start']) ? $schedule_data['break_start'] : null;
        $break_end = !empty($schedule_data['break_end']) ? $schedule_data['break_end'] : null;
        
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
        $stmt->close();
    }
    
    if (!$error_message) {
        $success_message = "Schedule updated successfully!";
    }
}

// Get doctor's current schedule
$doctor_schedule = [];
$stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$schedules = $stmt->get_result();

while ($schedule = $schedules->fetch_assoc()) {
    $doctor_schedule[$schedule['day_of_week']] = $schedule;
}
$stmt->close();

include 'includes/header.php';
?>

<style>
.schedule-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}
.schedule-card.active {
    border-color: #007bff;
    background-color: #f8f9ff;
}
.schedule-day-header {
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
    background-color: #f8f9fa;
}
.schedule-day-content {
    padding: 15px;
}
.time-input {
    max-width: 120px;
}
.leave-card {
    border-left: 4px solid #007bff;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.leave-card .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.leave-card.vacation {
    border-left-color: #28a745;
}
.leave-card.emergency {
    border-left-color: #dc3545;
}
.leave-card.cancelled {
    opacity: 0.6;
    border-left-color: #6c757d;
}
.bg-purple {
    background-color: #6f42c1 !important;
}
#leavesList .row {
    display: flex;
    flex-wrap: wrap;
}
#leavesList .col-md-4 {
    display: flex;
}
</style>
<!-- Leave Management -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-calendar-times me-2"></i>Manage Leave
        </h5>
        <button type="button" class="btn btn-sm btn-primary" id="addLeaveBtn" data-bs-toggle="modal" data-bs-target="#addLeaveModal">
            <i class="fas fa-plus me-1"></i>Add Leave
        </button>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Set vacation or emergency leave periods. During these periods, patients will not be able to book appointments with you.</p>
        <div id="leavesList">
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ms-2">Loading leaves...</span>
            </div>
        </div>
    </div>
</div>

<!-- Add Leave Modal -->
<div class="modal fade" id="addLeaveModal" tabindex="-1" aria-labelledby="addLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveModalLabel">Add Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addLeaveForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="leave_type" required>
                            <option value="">Select leave type</option>
                            <option value="Annual">Annual</option>
                            <option value="Sick">Sick</option>
                            <option value="Maternity">Maternity</option>
                            <option value="Paternity">Paternity</option>
                            <option value="Parental Leave">Parental Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Bereavement Leave">Bereavement Leave</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason <small class="text-muted">(Optional)</small></label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Enter reason for leave"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Add Leave
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

<!-- Schedule Management -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-clock me-2"></i>Manage Your Schedule
        </h5>
        <small class="text-muted">Set your availability for each day of the week</small>
    </div>
    <div class="card-body">
        <form method="POST" id="scheduleForm" action="">
            <input type="hidden" name="update_schedule" value="1">
            
            <?php 
            $days = [
                1 => 'Monday',
                2 => 'Tuesday', 
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                7 => 'Sunday'
            ];
            
            foreach ($days as $day_num => $day_name): 
                $schedule = $doctor_schedule[$day_num] ?? null;
                $is_available = $schedule ? $schedule['is_available'] : 0;
                $start_time = $schedule ? $schedule['start_time'] : '09:00';
                $end_time = $schedule ? $schedule['end_time'] : '17:00';
                $break_start = $schedule ? $schedule['break_start'] : '12:00';
                $break_end = $schedule ? $schedule['break_end'] : '13:00';
            ?>
            <div class="schedule-card <?php echo $is_available ? 'active' : ''; ?>">
                <div class="schedule-day-header">
                    <div class="form-check form-switch">
                        <input class="form-check-input day-toggle" type="checkbox" 
                               name="schedule[<?php echo $day_num; ?>][is_available]" 
                               id="day<?php echo $day_num; ?>" 
                               <?php echo $is_available ? 'checked' : ''; ?>
                               onchange="toggleDay(<?php echo $day_num; ?>)">
                        <label class="form-check-label fw-bold" for="day<?php echo $day_num; ?>">
                            <?php echo htmlspecialchars($day_name); ?>
                        </label>
                    </div>
                </div>
                <div class="schedule-day-content" id="content<?php echo $day_num; ?>" 
                     style="display: <?php echo $is_available ? 'block' : 'none'; ?>;">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control time-input" 
                                   name="schedule[<?php echo $day_num; ?>][start_time]"
                                   value="<?php echo htmlspecialchars($start_time); ?>"
                                   required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control time-input" 
                                   name="schedule[<?php echo $day_num; ?>][end_time]"
                                   value="<?php echo htmlspecialchars($end_time); ?>"
                                   required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Break Start <small class="text-muted">(Optional)</small></label>
                            <input type="time" class="form-control time-input" 
                                   name="schedule[<?php echo $day_num; ?>][break_start]"
                                   value="<?php echo htmlspecialchars($break_start); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Break End <small class="text-muted">(Optional)</small></label>
                            <input type="time" class="form-control time-input" 
                                   name="schedule[<?php echo $day_num; ?>][break_end]"
                                   value="<?php echo htmlspecialchars($break_end); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="text-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Schedule management functions
function toggleDay(dayNum) {
    const checkbox = document.getElementById('day' + dayNum);
    const content = document.getElementById('content' + dayNum);
    const card = content.closest('.schedule-card');
    
    if (checkbox.checked) {
        content.style.display = 'block';
        card.classList.add('active');
        // Make time inputs required when day is enabled
        content.querySelectorAll('input[type="time"]').forEach(function(input) {
            if (input.name.includes('start_time') || input.name.includes('end_time')) {
                input.required = true;
            }
        });
    } else {
        content.style.display = 'none';
        card.classList.remove('active');
        // Remove required attribute when day is disabled
        content.querySelectorAll('input[type="time"]').forEach(function(input) {
            input.required = false;
        });
    }
}

// Form validation
document.getElementById('scheduleForm').addEventListener('submit', function(e) {
    const checkedDays = document.querySelectorAll('.day-toggle:checked');
    let isValid = true;
    
    checkedDays.forEach(function(checkbox) {
        const dayNum = checkbox.id.replace('day', '');
        const startTime = document.querySelector('input[name="schedule[' + dayNum + '][start_time]"]');
        const endTime = document.querySelector('input[name="schedule[' + dayNum + '][end_time]"]');
        
        if (!startTime.value || !endTime.value) {
            isValid = false;
            showAlert('Please fill in start and end times for all selected days.', 'Validation Error', 'warning');
            e.preventDefault();
            return false;
        }
        
        if (startTime.value >= endTime.value) {
            isValid = false;
            showAlert('End time must be after start time.', 'Validation Error', 'warning');
            e.preventDefault();
            return false;
        }
    });
    
    return isValid;
});
</script>

<script>
// Load leaves on page load
document.addEventListener('DOMContentLoaded', function() {
    loadLeaves();
    
    // Handle add leave button click (for debugging)
    const addLeaveBtn = document.getElementById('addLeaveBtn');
    if (addLeaveBtn) {
        addLeaveBtn.addEventListener('click', function() {
            console.log('Add Leave button clicked');
        });
    }
    
    // Handle add leave form submission
    const addLeaveForm = document.getElementById('addLeaveForm');
    if (!addLeaveForm) {
        console.error('Add leave form not found!');
        return;
    }
    
    addLeaveForm.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Form submitted');
        
        // Validate form
        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            return false;
        }
        
        // Get form button and disable it during submission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Adding...';
        
        const formData = new FormData(this);
        formData.append('action', 'add');
        
        console.log('Sending request to manage_doctor_leaves.php');
        
        fetch('manage_doctor_leaves.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response received:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text().then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
            });
        })
        .then(data => {
            console.log('Parsed data:', data);
            if (data.success) {
                // Close modal and reset form
                const modalElement = document.getElementById('addLeaveModal');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                }
                addLeaveForm.reset();
                addLeaveForm.classList.remove('was-validated');
                
                // Show success message
                showAlert('success', data.message);
                
                // Reload leaves
                loadLeaves();
            } else {
                showAlert('danger', data.message || 'Failed to add leave. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred: ' + error.message);
        })
        .finally(() => {
            // Re-enable button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
        
        return false;
    });
    
    // Set minimum end date based on start date (use event delegation for modal)
    const addLeaveModal = document.getElementById('addLeaveModal');
    if (addLeaveModal) {
        addLeaveModal.addEventListener('shown.bs.modal', function() {
            console.log('Modal opened');
            const startDateInput = addLeaveModal.querySelector('input[name="start_date"]');
            const endDateInput = addLeaveModal.querySelector('input[name="end_date"]');
            
            if (startDateInput && endDateInput) {
                // Remove any existing listeners by cloning
                const newStartInput = startDateInput.cloneNode(true);
                startDateInput.parentNode.replaceChild(newStartInput, startDateInput);
                
                newStartInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                    if (endDateInput.value && endDateInput.value < this.value) {
                        endDateInput.value = this.value;
                    }
                });
            }
        });
    }
});

function loadLeaves() {
    fetch('manage_doctor_leaves.php?action=list')
        .then(response => response.json())
        .then(data => {
            const leavesList = document.getElementById('leavesList');
            
            if (!data.success) {
                leavesList.innerHTML = '<div class="alert alert-warning">Error loading leaves. Please refresh the page.</div>';
                return;
            }
            
            if (data.leaves.length === 0) {
                leavesList.innerHTML = '<div class="text-center py-4 text-muted">No leaves scheduled. Click "Add Leave" to schedule one.</div>';
                return;
            }
            
            let html = '<div class="row">';
            data.leaves.forEach(leave => {
                const startDate = new Date(leave.start_date);
                const endDate = new Date(leave.end_date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                const isActive = leave.status === 'Active' && endDate >= today;
                const leaveTypeLower = leave.leave_type.toLowerCase().replace(/\s+/g, '-');
                const cardClass = `leave-card ${leaveTypeLower} ${leave.status === 'Cancelled' ? 'cancelled' : ''}`;
                const badgeClass = getLeaveBadgeClass(leave.leave_type);
                
                html += `
                    <div class="col-md-4 mb-3">
                        <div class="card ${cardClass}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge ${badgeClass}">${leave.leave_type}</span>
                                    ${isActive ? '<span class="badge bg-primary">Active</span>' : ''}
                                    ${leave.status === 'Cancelled' ? '<span class="badge bg-secondary">Cancelled</span>' : ''}
                                </div>
                                <h6 class="card-title">${formatDate(startDate)} - ${formatDate(endDate)}</h6>
                                ${leave.reason ? `<p class="card-text text-muted small">${escapeHtml(leave.reason)}</p>` : ''}
                                <div class="d-flex gap-2 mt-2">
                                    ${isActive ? `
                                        <button class="btn btn-sm btn-outline-danger" onclick="cancelLeave(${leave.id})">
                                            <i class="fas fa-times me-1"></i>Cancel Leave
                                        </button>
                                    ` : ''}
                                    <button class="btn btn-sm btn-danger" onclick="deleteLeave(${leave.id})">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            leavesList.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('leavesList').innerHTML = '<div class="alert alert-danger">Error loading leaves. Please refresh the page.</div>';
        });
}

function cancelLeave(leaveId) {
    confirmDialog('Are you sure you want to cancel this leave? Patients will be able to book appointments during this period.', 'Cancel Leave', 'Cancel').then(function(confirmed) {
        if (!confirmed) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('leave_id', leaveId);
    
        fetch('manage_doctor_leaves.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadLeaves();
            } else {
                showAlert('danger', data.message || 'Failed to cancel leave. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred. Please try again.');
        });
    });
}

function deleteLeave(leaveId) {
    confirmDialog('Are you sure you want to permanently delete this leave? This action cannot be undone.', 'Delete Leave', 'Cancel').then(function(confirmed) {
        if (!confirmed) return;
    
        const formData = new FormData();
        formData.append('action', 'permanent_delete');
        formData.append('leave_id', leaveId);
        
        fetch('manage_doctor_leaves.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message || 'Leave deleted successfully');
                loadLeaves();
            } else {
                showAlert('danger', data.message || 'Failed to delete leave. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred. Please try again.');
        });
    });
}

function getLeaveBadgeClass(leaveType) {
    const badgeMap = {
        'Annual': 'bg-info',
        'Sick': 'bg-warning',
        'Maternity': 'bg-purple',
        'Paternity': 'bg-primary',
        'Parental Leave': 'bg-success',
        'Emergency Leave': 'bg-danger',
        'Bereavement Leave': 'bg-dark'
    };
    return badgeMap[leaveType] || 'bg-secondary';
}

function formatDate(date) {
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert after the page title or at the top of the content
    const firstCard = document.querySelector('.card');
    if (firstCard) {
        firstCard.parentNode.insertBefore(alertDiv, firstCard);
    } else {
        document.body.insertBefore(alertDiv, document.body.firstChild);
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?>
