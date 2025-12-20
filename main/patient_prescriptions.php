<?php
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Get prescriptions for the current patient
$prescriptions = [];
$prescriptions_by_month = [];
if ($viewing_patient_id) {
    $stmt = $conn->prepare("
        SELECT p.*, 
               u.first_name as doctor_first_name, 
               u.last_name as doctor_last_name,
               u.specialization,
               u.role as doctor_role
        FROM prescriptions p 
        LEFT JOIN users u ON p.doctor_id = u.id 
        WHERE p.patient_id = ? 
        ORDER BY p.date_prescribed DESC
    ");
    $stmt->bind_param("i", $viewing_patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $prescriptions[] = $row;
        
        // Group by month and year, then by date
        $date = strtotime($row['date_prescribed']);
        $month_key = date('Y-m', $date);
        $month_label = date('F Y', $date);
        $date_key = date('Y-m-d', $date);
        $date_label = date('F j, Y', $date);
        
        if (!isset($prescriptions_by_month[$month_key])) {
            $prescriptions_by_month[$month_key] = [
                'label' => $month_label,
                'prescriptions' => [],
                'dates' => []
            ];
        }
        
        if (!isset($prescriptions_by_month[$month_key]['dates'][$date_key])) {
            $prescriptions_by_month[$month_key]['dates'][$date_key] = [
                'label' => $date_label,
                'prescriptions' => []
            ];
        }
        
        $prescriptions_by_month[$month_key]['dates'][$date_key]['prescriptions'][] = $row;
        $prescriptions_by_month[$month_key]['prescriptions'][] = $row;
    }
}

// Handle prescription actions (for both admin and doctors)
if ((isAdmin() || isDoctor()) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_prescription':
                $medication_name = sanitize($_POST['medication_name']);
                $dosage = sanitize($_POST['dosage']);
                $frequency = sanitize($_POST['frequency']);
                $duration = sanitize($_POST['duration']);
                $instructions = sanitize($_POST['instructions']);
                $date_prescribed = sanitize($_POST['date_prescribed']);
                $status = sanitize($_POST['status']);

                // Validate status against database ENUM values
                $valid_statuses = ['active', 'completed', 'cancelled'];
                if (!in_array($status, $valid_statuses)) {
                    $error_message = "Invalid prescription status. Allowed values: " . implode(', ', $valid_statuses);
                    break;
                }

                // Use current user's ID as doctor_id
                $doctor_id = $_SESSION['user_id'];

                $stmt = $conn->prepare("
                    INSERT INTO prescriptions 
                    (patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions, date_prescribed, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iisssssss", $viewing_patient_id, $doctor_id, $medication_name, $dosage, $frequency, $duration, $instructions, $date_prescribed, $status);

                if ($stmt->execute()) {
                    // Create notification for patient about new prescription
                    require_once __DIR__ . '/config/patient_auth.php';
                    $patientUserStmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ?");
                    if ($patientUserStmt) {
                        $patientUserStmt->bind_param("i", $viewing_patient_id);
                        if ($patientUserStmt->execute()) {
                            $patientUserResult = $patientUserStmt->get_result();
                            if ($patientUserResult && $patientUserResult->num_rows > 0) {
                                $patientUser = $patientUserResult->fetch_assoc();
                                $patient_user_id = $patientUser['id'];
                                
                                // Get doctor name for notification
                                $doctorStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                                $doctorStmt->bind_param("i", $doctor_id);
                                $doctorStmt->execute();
                                $doctorResult = $doctorStmt->get_result();
                                $doctorName = 'Your doctor';
                                if ($doctorResult && $doctorResult->num_rows > 0) {
                                    $doctor = $doctorResult->fetch_assoc();
                                    $doctorName = 'Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
                                }
                                $doctorStmt->close();
                                
                                // Format date prescribed
                                $dateFormatted = date('M j, Y', strtotime($date_prescribed));
                                
                                $notificationTitle = "New Prescription Added";
                                $notificationMessage = "A new prescription for $medication_name has been prescribed by $doctorName on $dateFormatted. Please check your prescriptions section for details.";
                                
                                $notificationResult = createNotification('Patient', $patient_user_id, 'Prescription_Added', $notificationTitle, $notificationMessage, 'System');
                                if (!$notificationResult) {
                                    error_log("Failed to create notification for prescription ID: " . $conn->insert_id);
                                }
                            }
                        }
                        $patientUserStmt->close();
                    }
                    
                    $success_message = "Prescription added successfully!";
                    echo "<script>window.location.href='patients.php?patient_id=$viewing_patient_id&tab=prescriptions';</script>";
                    exit;
                } else {
                    $error_message = "Error adding prescription.";
                }
                break;

            case 'update_status':
                $prescription_id = (int)$_POST['prescription_id'];
                $new_status = sanitize($_POST['new_status']);

                // Validate status against database ENUM values
                $valid_statuses = ['active', 'completed', 'cancelled'];
                if (!in_array($new_status, $valid_statuses)) {
                    $error_message = "Invalid prescription status. Allowed values: " . implode(', ', $valid_statuses);
                    break;
                }

                $stmt = $conn->prepare("UPDATE prescriptions SET status = ? WHERE id = ? AND patient_id = ?");
                $stmt->bind_param("sii", $new_status, $prescription_id, $viewing_patient_id);

                if ($stmt->execute()) {
                    $success_message = "Prescription status updated!";
                    echo "<script>window.location.href='patients.php?patient_id=$viewing_patient_id&tab=prescriptions';</script>";
                    exit;
                } else {
                    $error_message = "Error updating prescription status.";
                }
                break;

            case 'delete_prescription':
                $prescription_id = (int)$_POST['prescription_id'];

                $stmt = $conn->prepare("DELETE FROM prescriptions WHERE id = ? AND patient_id = ?");
                $stmt->bind_param("ii", $prescription_id, $viewing_patient_id);

                if ($stmt->execute()) {
                    $success_message = "Prescription deleted successfully!";
                    echo "<script>window.location.href='patients.php?patient_id=$viewing_patient_id&tab=prescriptions';</script>";
                    exit;
                } else {
                    $error_message = "Error deleting prescription.";
                }
                break;
        }
    }
}

// Get doctors for dropdown - using users table directly
$doctors = [];
$result = $conn->query("SELECT id, first_name, last_name, specialization FROM users WHERE role = 'Doctor' ORDER BY last_name, first_name");
while ($row = $result->fetch_assoc()) {
    $doctors[] = $row;
}
?>
<!-- Include jQuery (if not already included) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
.month-group-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
    background-color: #f8f9fa;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s;
}
.month-group-card:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.month-group-header {
    padding: 15px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px;
    transition: background-color 0.2s;
}
.month-group-header:hover {
    background-color: #e9ecef;
}
.month-group-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 1em;
}
.month-group-count {
    color: #6c757d;
    font-size: 0.9em;
    font-weight: normal;
    margin-left: 5px;
}
.month-group-chevron {
    transition: transform 0.3s;
    color: #6c757d;
}
.month-group-chevron.expanded {
    transform: rotate(180deg);
}
.month-group-content {
    display: none;
    padding: 0;
}
.month-group-content.show {
    display: block;
}
.date-group-item {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background-color 0.2s;
    background-color: white;
}
.date-group-item:last-child {
    border-bottom: none;
}
.date-group-item:hover {
    background-color: #f8f9fa;
}
.date-group-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95em;
}
.date-group-count {
    color: #6c757d;
    font-size: 0.85em;
    font-weight: normal;
    margin-left: 5px;
}
.date-group-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.date-group-actions .btn {
    font-size: 0.875rem;
    padding: 0.25rem 0.75rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
    font-weight: 500;
}
.date-group-actions .btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #ffffff;
}
.date-group-actions .btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.date-group-chevron {
    transition: transform 0.3s;
    color: #6c757d;
    font-size: 0.8em;
}
.date-group-chevron.expanded {
    transform: rotate(180deg);
}
.date-group-content {
    display: none;
    padding: 15px;
    background-color: #f8f9fa;
}
.date-group-content.show {
    display: block;
}
.date-group-content .prescription-card {
    margin-top: 0;
}
.date-group-content .prescription-card:first-child {
    margin-top: 0;
}
.prescription-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
    background-color: white;
    transition: box-shadow 0.2s;
}
.prescription-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.prescription-header {
    background-color: #ffffff;
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    border-radius: 8px 8px 0 0;
}
.prescription-body {
    padding: 15px;
}
.status-badge {
    font-size: 0.8em;
    padding: 4px 8px;
    border-radius: 12px;
}
.status-active {
    background-color: #d4edda;
    color: #155724;
}
.status-completed {
    background-color: #d1ecf1;
    color: #0c5460;
}
.status-discontinued {
    background-color: #f8d7da;
    color: #721c24;
}
.medication-name {
    font-size: 1.1em;
    font-weight: 600;
    color: #2c3e50;
}
.dosage-info {
    color: #6c757d;
    font-size: 0.9em;
}
.prescriber-info {
    background-color: #e3f2fd;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 10px;
    border-left: 4px solid #2196f3;
}

/* Medication autocomplete styling */
.medication-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ced4da;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.medication-suggestions .suggestion-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.2s;
}

.medication-suggestions .suggestion-item:hover,
.medication-suggestions .suggestion-item.highlighted {
    background-color: #f8f9fa;
}

.medication-suggestions .suggestion-item:last-child {
    border-bottom: none;
}

.medication-suggestions .suggestion-item mark {
    background-color: #fff3cd;
    padding: 0 2px;
    border-radius: 2px;
    font-weight: 600;
}

.medication-suggestions .no-results {
    padding: 10px 12px;
    color: #6c757d;
    font-style: italic;
    text-align: center;
}

#medication_name:focus + .medication-suggestions {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.medication-input-container {
    position: relative;
}

/* Prescription Modal Styles */
#viewPrescriptionsModal .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}
#viewPrescriptionsModal .prescription-card {
    margin-bottom: 15px;
}
#viewPrescriptionsModal .prescription-card:last-child {
    margin-bottom: 0;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-prescription-bottle-alt me-2"></i>Medicine Prescriptions</h5>
    <?php if (isAdmin() || isDoctor()): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPrescriptionModal">
            <i class="fas fa-plus me-1"></i>Add Medicine Prescription
        </button>
    <?php endif; ?>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($prescriptions_by_month)): ?>
    <?php foreach ($prescriptions_by_month as $month_key => $month_data): ?>
        <div class="month-group-card">
            <div class="month-group-header" onclick="toggleMonthGroup('<?php echo $month_key; ?>')">
                <div class="month-group-title">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo htmlspecialchars($month_data['label']); ?></span>
                    <span class="month-group-count">
                        (<?php echo count($month_data['prescriptions']); ?> 
                        <?php echo count($month_data['prescriptions']) == 1 ? 'prescription' : 'prescriptions'; ?>)
                    </span>
                </div>
                <i class="fas fa-chevron-down month-group-chevron" id="chevron-<?php echo $month_key; ?>"></i>
            </div>
            <div class="month-group-content" id="content-<?php echo $month_key; ?>">
                <?php 
                // Sort dates in descending order
                $dates = $month_data['dates'];
                krsort($dates);
                foreach ($dates as $date_key => $date_data): 
                ?>
                    <div class="date-group-item">
                        <div class="date-group-title">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo htmlspecialchars($date_data['label']); ?></span>
                            <span class="date-group-count">
                                (<?php echo count($date_data['prescriptions']); ?> 
                                <?php echo count($date_data['prescriptions']) == 1 ? 'prescription' : 'prescriptions'; ?>)
                            </span>
                        </div>
                        <div class="date-group-actions">
                            <button class="btn btn-sm btn-primary" onclick="showPrescriptionsModal(<?php echo htmlspecialchars(json_encode($date_data['prescriptions']), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo htmlspecialchars($date_data['label'], ENT_QUOTES); ?>');">
                                <i class="fas fa-eye me-1"></i>View
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-prescription-bottle-alt fa-3x text-muted mb-3"></i>
        <h6 class="text-muted">No Prescriptions Found</h6>
        <p class="text-muted">No medicine prescriptions have been added for this patient yet.</p>
        <?php if (isAdmin() || isDoctor()): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPrescriptionModal">
                <i class="fas fa-plus me-1"></i>Add First Medicine Prescription
            </button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isAdmin() || isDoctor()): ?>
<!-- Add Prescription Modal -->
<div class="modal fade" id="addPrescriptionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-prescription-bottle-alt me-2"></i>Add New Medicine Prescription
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_prescription">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Medicine Name *</label>
                            <div class="medication-input-container">
                                <input type="text" id="medication_name" name="medication_name" class="form-control" placeholder="Type medicine name..." autocomplete="on" required>
                                <div id="medication_suggestions" class="medication-suggestions"></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dosage *</label>
                            <input type="text" class="form-control" name="dosage" placeholder="e.g., 500mg, 2 tablets" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Frequency *</label>
                            <select class="form-select" name="frequency" required>
                                <option value="">Select frequency</option>
                                <option value="Once daily">Once daily</option>
                                <option value="Twice daily">Twice daily</option>
                                <option value="Three times daily">Three times daily</option>
                                <option value="Four times daily">Four times daily</option>
                                <option value="Every 4 hours">Every 4 hours</option>
                                <option value="Every 6 hours">Every 6 hours</option>
                                <option value="Every 8 hours">Every 8 hours</option>
                                <option value="As needed">As needed</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration</label>
                            <input type="text" class="form-control" name="duration" placeholder="e.g., 7 days, 2 weeks, ongoing">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Prescribed *</label>
                            <input type="date" class="form-control" name="date_prescribed" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Instructions</label>
                        <textarea class="form-control" name="instructions" rows="3" placeholder="Special instructions for taking this medicine..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Prescribed by:</strong> 
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-warning text-dark">Admin</span>
                        <?php else: ?>
                            <span class="badge bg-info text-white">Doctor</span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Add Medicine Prescription
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Prescriptions Modal -->
<div class="modal fade" id="viewPrescriptionsModal" tabindex="-1" aria-labelledby="viewPrescriptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewPrescriptionsModalLabel">
                    <i class="fas fa-prescription-bottle-alt me-2"></i>Medicine Prescriptions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewPrescriptionsModalBody">
                <!-- Prescriptions will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for status updates and deletion -->
<form id="updateStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="prescription_id" id="statusPrescriptionId">
    <input type="hidden" name="new_status" id="newStatus">
</form>

<form id="deletePrescriptionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_prescription">
    <input type="hidden" name="prescription_id" id="deletePrescriptionId">
</form>

<script>
function updatePrescriptionStatus(prescriptionId, status) {
    confirmDialog('Are you sure you want to update the prescription status?', 'Confirm', 'Cancel').then(function(confirmed) {
        if (confirmed) {
            document.getElementById('statusPrescriptionId').value = prescriptionId;
            document.getElementById('newStatus').value = status;
            document.getElementById('updateStatusForm').submit();
        }
    });
}

function deletePrescription(prescriptionId) {
    confirmDialog('Are you sure you want to delete this prescription? This action cannot be undone.', 'Delete', 'Cancel').then(function(confirmed) {
        if (confirmed) {
            document.getElementById('deletePrescriptionId').value = prescriptionId;
            document.getElementById('deletePrescriptionForm').submit();
        }
    });
}

// Smart autocomplete for medication input
$(document).ready(function() {
    let medications = [];
    let currentHighlight = -1;

    // Load medications from JSON (OPTION 1 fix)
    $.getJSON("assets/data/drugs.json")
        .done(function(data) {
            medications = data.drugs || []; // <-- simplified for your structure
            medications = medications.filter(function(med) {
                return med && typeof med === 'string' && med.trim().length > 0;
            });
            console.log('Loaded ' + medications.length + ' medications');
        })
        .fail(function(jqxhr, textStatus, error) {
            console.error("Could not load drugs.json file:", error);
            medications = [
                'Acetaminophen', 'Advil', 'Albuterol', 'Amlodipine', 'Amoxicillin', 'Aspirin',
                'Atorvastatin', 'Azithromycin', 'Cetirizine', 'Ciprofloxacin', 'Doxycycline',
                'Furosemide', 'Gabapentin', 'Hydrochlorothiazide', 'Ibuprofen', 'Lisinopril',
                'Losartan', 'Metformin', 'Metoprolol', 'Omeprazole', 'Prednisone', 'Simvastatin'
            ];
            console.log('Using fallback medication list with ' + medications.length + ' items');
        });

    $('#medication_name').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        const suggestions = $('#medication_suggestions');
        currentHighlight = -1;

        if (query.length < 1) {
            suggestions.hide().empty();
            return;
        }

        const filtered = medications.filter(function(med) {
            return med.toLowerCase().includes(query);
        }).slice(0, 10);

        if (filtered.length === 0) {
            suggestions.html('<div class="no-results">No medications found</div>').show();
            return;
        }

        let html = '';
        filtered.forEach(function(med, index) {
            const highlighted = med.replace(
                new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'),
                '<mark>$1</mark>'
            );
            html += '<div class="suggestion-item" data-index="' + index + '" data-value="' + med + '">' + highlighted + '</div>';
        });

        suggestions.html(html).show();
    });

    $(document).on('click', '.suggestion-item', function() {
        const value = $(this).data('value');
        $('#medication_name').val(value);
        $('#medication_suggestions').hide().empty();
        currentHighlight = -1;
    });

    $('#medication_name').on('keydown', function(e) {
        const suggestions = $('#medication_suggestions .suggestion-item');
        if (suggestions.length === 0) return;

        if (e.keyCode === 40) { // down
            e.preventDefault();
            currentHighlight++;
            if (currentHighlight >= suggestions.length) currentHighlight = 0;
            updateHighlight(suggestions);
        } else if (e.keyCode === 38) { // up
            e.preventDefault();
            currentHighlight--;
            if (currentHighlight < 0) currentHighlight = suggestions.length - 1;
            updateHighlight(suggestions);
        } else if (e.keyCode === 13) { // enter
            if (currentHighlight >= 0 && suggestions.length > 0) {
                e.preventDefault();
                const selected = suggestions.eq(currentHighlight);
                $('#medication_name').val(selected.data('value'));
                $('#medication_suggestions').hide().empty();
                currentHighlight = -1;
            }
        } else if (e.keyCode === 27) { // esc
            $('#medication_suggestions').hide().empty();
            currentHighlight = -1;
        }
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.medication-input-container').length) {
            $('#medication_suggestions').hide().empty();
            currentHighlight = -1;
        }
    });

    $('#medication_name').on('focus', function() {
        if ($(this).val().trim().length > 0) {
            $(this).trigger('input');
        }
    });

    function updateHighlight(suggestions) {
        suggestions.removeClass('highlighted');
        if (currentHighlight >= 0) {
            suggestions.eq(currentHighlight).addClass('highlighted');
        }
    }

    $('#addPrescriptionModal').on('hidden.bs.modal', function() {
        $('#medication_suggestions').hide().empty();
        $('#medication_name').val('');
        currentHighlight = -1;
    });
});

// Helpers
function clearMedicationInput() {
    $('#medication_name').val('');
    $('#medication_suggestions').hide().empty();
}

function setMedicationValue(medicationName) {
    $('#medication_name').val(medicationName);
    $('#medication_suggestions').hide().empty();
}

// Toggle month group collapsible
function toggleMonthGroup(monthKey) {
    const content = document.getElementById('content-' + monthKey);
    const chevron = document.getElementById('chevron-' + monthKey);
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        chevron.classList.remove('expanded');
    } else {
        content.classList.add('show');
        chevron.classList.add('expanded');
    }
}

// Show prescriptions modal
window.showPrescriptionsModal = function(prescriptions, dateLabel) {
    const modal = new bootstrap.Modal(document.getElementById('viewPrescriptionsModal'));
    const modalBody = document.getElementById('viewPrescriptionsModalBody');
    const modalTitle = document.getElementById('viewPrescriptionsModalLabel');
    
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-prescription-bottle-alt me-2"></i>Medicine Prescriptions - ' + escapeHtml(dateLabel);
    }
    
    if (!prescriptions || prescriptions.length === 0) {
        if (modalBody) {
            modalBody.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No prescriptions found for this date.</div>';
        }
        modal.show();
        return;
    }
    
    const isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;
    
    let content = '';
    prescriptions.forEach(function(prescription, index) {
        if (index > 0) {
            content += '<hr class="my-4">';
        }
        
        const statusClass = prescription.status ? 'status-' + prescription.status.toLowerCase() : '';
        const statusText = prescription.status ? prescription.status.charAt(0).toUpperCase() + prescription.status.slice(1) : 'Unknown';
        
        content += '<div class="prescription-card">';
        content += '<div class="prescription-header d-flex justify-content-between align-items-start">';
        content += '<div>';
        content += '<div class="medication-name"><strong>Medicine:</strong> ' + escapeHtml(prescription.medication_name || 'N/A') + '</div>';
        content += '<div class="dosage-info mt-1">';
        content += '<strong>Dosage:</strong> ' + escapeHtml(prescription.dosage || 'N/A') + ' • ';
        content += '<strong>Frequency:</strong> ' + escapeHtml(prescription.frequency || 'N/A');
        if (prescription.duration) {
            content += ' • <strong>Duration:</strong> ' + escapeHtml(prescription.duration);
        }
        content += '</div>';
        content += '</div>';
        content += '<div class="d-flex align-items-center">';
        content += '<span class="status-badge ' + statusClass + ' me-2">' + escapeHtml(statusText) + '</span>';
        if (isAdminUser) {
            content += '<div class="dropdown">';
            content += '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">';
            content += '<i class="fas fa-ellipsis-v"></i>';
            content += '</button>';
            content += '<ul class="dropdown-menu">';
            content += '<li><a class="dropdown-item" href="#" onclick="updatePrescriptionStatus(' + prescription.id + ', \'active\')">';
            content += '<i class="fas fa-play-circle me-2"></i>Mark Active</a></li>';
            content += '<li><a class="dropdown-item" href="#" onclick="updatePrescriptionStatus(' + prescription.id + ', \'completed\')">';
            content += '<i class="fas fa-check-circle me-2"></i>Mark Completed</a></li>';
            content += '<li><hr class="dropdown-divider"></li>';
            content += '<li><a class="dropdown-item text-danger" href="#" onclick="deletePrescription(' + prescription.id + ')">';
            content += '<i class="fas fa-trash me-2"></i>Delete</a></li>';
            content += '</ul>';
            content += '</div>';
        }
        content += '</div>';
        content += '</div>';
        content += '<div class="prescription-body">';
        if (prescription.instructions) {
            content += '<div class="mb-3">';
            content += '<strong>Instructions:</strong>';
            content += '<div class="text-muted">' + escapeHtml(prescription.instructions).replace(/\n/g, '<br>') + '</div>';
            content += '</div>';
        }
        content += '<div class="prescriber-info">';
        content += '<div class="row">';
        content += '<div class="col-md-6">';
        content += '<i class="fas fa-user-md me-1"></i>';
        content += '<strong>Prescribed by:</strong> ';
        if (prescription.doctor_first_name) {
            if (prescription.doctor_role === 'Admin') {
                content += '<span class="badge bg-warning text-dark me-1">Admin</span>';
            } else {
                content += '<span class="badge bg-info text-white me-1">Doctor</span>';
            }
            content += escapeHtml(prescription.doctor_first_name + ' ' + (prescription.doctor_last_name || ''));
            if (prescription.specialization) {
                content += ' <span class="text-muted">(' + escapeHtml(prescription.specialization) + ')</span>';
            }
        } else {
            content += '<span class="text-muted">Unknown Prescriber</span>';
        }
        content += '</div>';
        content += '<div class="col-md-6 text-md-end">';
        content += '<i class="fas fa-calendar me-1"></i>';
        content += '<strong>Date:</strong> ';
        if (prescription.date_prescribed) {
            const date = new Date(prescription.date_prescribed);
            content += date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } else {
            content += 'N/A';
        }
        content += '</div>';
        content += '</div>';
        content += '</div>';
        content += '</div>';
        content += '</div>';
    });
    
    if (modalBody) {
        modalBody.innerHTML = content;
    }
    modal.show();
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Expand first month group by default
$(document).ready(function() {
    const firstMonthGroup = document.querySelector('.month-group-content');
    const firstMonthChevron = document.querySelector('.month-group-chevron');
    if (firstMonthGroup && firstMonthChevron) {
        firstMonthGroup.classList.add('show');
        firstMonthChevron.classList.add('expanded');
    }
});
</script>

<?php endif; ?>