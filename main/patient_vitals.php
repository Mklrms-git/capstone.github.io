<?php
// File: includes/patient_vitals.php (Updated sections)
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Handle success/error messages
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Get the latest/current vital sign for this patient
$stmt_latest = $conn->prepare("SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt_latest->bind_param("i", $patient_details['id']);
$stmt_latest->execute();
$result_latest = $stmt_latest->get_result();
$latest_vital = $result_latest->fetch_assoc();
$stmt_latest->close();

// Get all vital signs for this patient (for history)
$stmt = $conn->prepare("SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $patient_details['id']);
$stmt->execute();
$result = $stmt->get_result();

$vitals = [];
$vitals_by_date = [];
while ($row = $result->fetch_assoc()) {
    $vitals[] = $row;
    // Group by date (Y-m-d format)
    $date_key = date('Y-m-d', strtotime($row['created_at']));
    if (!isset($vitals_by_date[$date_key])) {
        $vitals_by_date[$date_key] = [];
    }
    $vitals_by_date[$date_key][] = $row;
}
$stmt->close();

// Helper function to parse blood pressure
function parseBP($bp_string) {
    $parts = explode('/', $bp_string);
    return [
        'systolic' => isset($parts[0]) ? (int)$parts[0] : 0,
        'diastolic' => isset($parts[1]) ? (int)$parts[1] : 0
    ];
}

// Calculate BMI
function calculateBMI($weight, $height) {
    if ($weight && $height) {
        return ($weight * 703) / ($height * $height);
    }
    return null;
}

// Classify BMI based on gender
function classifyBMI($bmi, $gender = '') {
    if (!$bmi || $bmi <= 0) {
        return ['status' => 'Unknown', 'class' => 'secondary'];
    }
    
    // Standard BMI classification (same for both men and women)
    if ($bmi < 18.5) {
        return ['status' => 'Underweight', 'class' => 'info'];
    } elseif ($bmi >= 18.5 && $bmi < 25.0) {
        return ['status' => 'Healthy', 'class' => 'success'];
    } elseif ($bmi >= 25.0 && $bmi < 30.0) {
        return ['status' => 'Overweight', 'class' => 'warning'];
    } else {
        return ['status' => 'Obesity', 'class' => 'danger'];
    }
}

// Determine status class based on values
function getStatusClass($value, $normal_range, $warning_range) {
    if ($value >= $warning_range[1] || $value <= $warning_range[0]) {
        return 'danger';
    } elseif ($value >= $normal_range[1] || $value <= $normal_range[0]) {
        return 'warning';
    }
    return 'success';
}
?>

<div class="section-container">
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5><i class="fas fa-heartbeat me-2"></i>Vital Signs</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVitalModal">
            <i class="fas fa-plus me-1"></i>Record Vitals
        </button>
    </div>

    <!-- Current Vitals Overview -->
    <div class="mb-3">
        <?php if (!empty($latest_vital)): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="text-muted mb-0">
                    <i class="fas fa-clock me-1"></i>Current Vitals
                </h6>
                <small class="text-muted">
                    Last Updated: <?php echo date('M j, Y h:i A', strtotime($latest_vital['created_at'])); ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="row mb-4">
        <?php if (!empty($latest_vital)): ?>
            <?php $bp = parseBP($latest_vital['blood_pressure']); ?>
            
            <div class="col-md-3">
                <div class="card vital-card">
                    <div class="card-body text-center">
                        <i class="fas fa-heartbeat fa-2x text-primary mb-2"></i>
                        <?php $bp_class = getStatusClass($bp['systolic'], [120,139], [140,90]); ?>
                        <h4 class="text-<?php echo $bp_class; ?>">
                            <?php echo $latest_vital['blood_pressure']; ?>
                        </h4>
                        <small class="text-muted">Blood Pressure</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card vital-card">
                    <div class="card-body text-center">
                        <i class="fas fa-thermometer-half fa-2x text-primary mb-2"></i>
                        <?php $temp_class = getStatusClass($latest_vital['temperature'], [99,100.3], [100.4,95]); ?>
                        <h4 class="text-<?php echo $temp_class; ?>">
                            <?php echo $latest_vital['temperature']; ?>°F
                        </h4>
                        <small class="text-muted">Temperature</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card vital-card">
                    <div class="card-body text-center">
                        <i class="fas fa-heart fa-2x text-primary mb-2"></i>
                        <?php $hr_class = getStatusClass($latest_vital['heart_rate'], [60,100], [101,59]); ?>
                        <h4 class="text-<?php echo $hr_class; ?>">
                            <?php echo $latest_vital['heart_rate']; ?> bpm
                        </h4>
                        <small class="text-muted">Heart Rate</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card vital-card">
                    <div class="card-body text-center">
                        <i class="fas fa-weight fa-2x text-primary mb-2"></i>
                        <?php 
                        $bmi = $latest_vital['bmi'];
                        $patient_gender = isset($patient_details['sex']) ? $patient_details['sex'] : '';
                        $bmi_classification = classifyBMI($bmi, $patient_gender);
                        ?>
                        <h4 class="text-<?php echo $bmi_classification['class']; ?>">
                            <?php echo $latest_vital['weight']; ?> lbs
                        </h4>
                        <small class="text-muted">
                            BMI: <?php echo number_format($bmi, 1); ?> 
                            <span class="badge bg-<?php echo $bmi_classification['class']; ?> ms-1">
                                <?php echo $bmi_classification['status']; ?>
                            </span>
                        </small>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    No vital signs recorded yet. Click "Record Vitals" to add the first entry.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Vitals History Cards -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">
                <i class="fas fa-chart-line me-2"></i>Vitals History
            </h6>
        </div>
        
        <div class="card-body">
            <?php if (!empty($vitals_by_date)): ?>
                <div class="vitals-history-container">
                    <?php 
                    // Sort dates in descending order (newest first)
                    krsort($vitals_by_date);
                    foreach ($vitals_by_date as $date_key => $date_vitals): 
                        $date_display = date('M j, Y', strtotime($date_key));
                        $count = count($date_vitals);
                        $card_id = 'vitals-card-' . str_replace('-', '', $date_key);
                    ?>
                        <div class="vitals-date-card mb-3">
                            <div class="vitals-date-header" data-bs-toggle="collapse" data-bs-target="#<?php echo $card_id; ?>" aria-expanded="false" aria-controls="<?php echo $card_id; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                        <h6 class="mb-0"><?php echo $date_display; ?></h6>
                                        <span class="badge bg-secondary ms-2"><?php echo $count; ?> <?php echo $count == 1 ? 'Record' : 'Records'; ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down collapse-icon"></i>
                                </div>
                            </div>
                            <div class="collapse" id="<?php echo $card_id; ?>">
                                <div class="vitals-date-body">
                                    <div class="table-responsive" style="overflow-x: auto;">
                                        <table class="table table-striped table-sm" style="min-width: 1200px;">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Blood Pressure</th>
                                                    <th>Temperature</th>
                                                    <th>Heart Rate</th>
                                                    <th>Respiratory Rate</th>
                                                    <th>O2 Saturation</th>
                                                    <th>Weight</th>
                                                    <th>Height</th>
                                                    <th>BMI</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($date_vitals as $vital): ?>
                                                    <?php $bp = parseBP($vital['blood_pressure']); ?>
                                                    <tr>
                                                        <td><?php echo date('h:i A', strtotime($vital['created_at'])); ?></td>
                                                        <td><span class="badge bg-<?php echo getStatusClass($bp['systolic'], [120,139], [140,90]); ?>">
                                                            <?php echo $vital['blood_pressure']; ?>
                                                        </span></td>
                                                        <td><span class="badge bg-<?php echo getStatusClass($vital['temperature'], [99,100.3], [100.4,95]); ?>">
                                                            <?php echo $vital['temperature']; ?>°F
                                                        </span></td>
                                                        <td><span class="badge bg-<?php echo getStatusClass($vital['heart_rate'], [60,100], [101,59]); ?>">
                                                            <?php echo $vital['heart_rate']; ?> bpm
                                                        </span></td>
                                                        <td><?php echo $vital['respiratory_rate']; ?> /min</td>
                                                        <td><?php echo $vital['oxygen_saturation']; ?>%</td>
                                                        <td><?php echo $vital['weight']; ?> lbs</td>
                                                        <td><?php echo $vital['height']; ?> in</td>
                                                        <td>
                                                            <?php 
                                                            $patient_gender = isset($patient_details['sex']) ? $patient_details['sex'] : '';
                                                            $vital_bmi_classification = classifyBMI($vital['bmi'], $patient_gender);
                                                            ?>
                                                            <span class="badge bg-<?php echo $vital_bmi_classification['class']; ?>">
                                                                <?php echo number_format($vital['bmi'], 1); ?> - <?php echo $vital_bmi_classification['status']; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-heartbeat fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Vital Signs Recorded</h5>
                    <p class="text-muted">Start tracking this patient's vital signs.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVitalModal">
                        <i class="fas fa-plus me-1"></i>Record First Vitals
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Vital Modal -->
    <div class="modal fade" id="addVitalModal" tabindex="-1" aria-labelledby="addVitalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="vitalForm" method="POST" action="add_vitals.php">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_details['id']; ?>">
                    <input type="hidden" name="visit_date" value="<?php echo date('Y-m-d'); ?>">
                    
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addVitalModalLabel">Record New Vital Signs</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-heartbeat me-1"></i>Systolic BP</label>
                                    <input type="number" class="form-control" name="systolic" placeholder="120" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-heartbeat me-1"></i>Diastolic BP</label>
                                    <input type="number" class="form-control" name="diastolic" placeholder="80" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-thermometer-half me-1"></i>Temperature (°F)</label>
                                    <input type="number" step="0.1" class="form-control" name="temperature" placeholder="98.6" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-heart me-1"></i>Heart Rate (bpm)</label>
                                    <input type="number" class="form-control" name="heart_rate" placeholder="72" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-lungs me-1"></i>Respiratory Rate (/min)</label>
                                    <input type="number" class="form-control" name="respiratory_rate" placeholder="16" value="16">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-percent me-1"></i>O2 Saturation (%)</label>
                                    <input type="number" class="form-control" name="oxygen_saturation" placeholder="98" value="98">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-weight me-1"></i>Weight (lbs)</label>
                                    <input type="number" step="0.1" class="form-control" name="weight" placeholder="165" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="vital-input-group mb-3">
                                    <label class="form-label"><i class="fas fa-ruler-vertical me-1"></i>Height (inches)</label>
                                    <input type="number" step="0.1" class="form-control" name="height" placeholder="68" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="vital-input-group mb-3">
                            <label class="form-label"><i class="fas fa-notes-medical me-1"></i>Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional observations..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            All fields are required. Vital signs data will be used for patient assessment.
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Save Record</button>
                         <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.vital-card {
    transition: all 0.3s ease;
    border-radius: 10px;
    border: none;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.vital-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}

.vital-input-group {
    position: relative;
}

.vital-input-group .form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 5px;
}

#vitalsTable th {
    font-weight: 600;
    background-color: #f8f9fa;
}

.alert {
    margin-bottom: 20px;
}

/* Vitals History Date Cards */
.vitals-date-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: white;
    overflow: hidden;
    transition: all 0.3s ease;
}

.vitals-date-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.vitals-date-header {
    padding: 15px 20px;
    background-color: #f8f9fa;
    cursor: pointer;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #e9ecef;
}

.vitals-date-header:hover {
    background-color: #e9ecef;
}

.vitals-date-header[aria-expanded="true"] {
    background-color: #e3f2fd;
    border-bottom-color: #2196f3;
}

.vitals-date-header[aria-expanded="true"] .collapse-icon {
    transform: rotate(180deg);
}

.collapse-icon {
    transition: transform 0.3s ease;
    color: #6c757d;
}

.vitals-date-body {
    padding: 0;
    overflow-x: auto;
}

.vitals-date-body .table {
    margin-bottom: 0;
}

.vitals-date-body .table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 15px 18px;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
    min-width: 100px;
}

.vitals-date-body .table tbody td {
    padding: 15px 18px;
    font-size: 0.9rem;
    vertical-align: middle;
    white-space: nowrap;
}

.vitals-date-body .table thead th:nth-child(1) { min-width: 110px; } /* Time */
.vitals-date-body .table thead th:nth-child(2) { min-width: 130px; } /* Blood Pressure */
.vitals-date-body .table thead th:nth-child(3) { min-width: 120px; } /* Temperature */
.vitals-date-body .table thead th:nth-child(4) { min-width: 110px; } /* Heart Rate */
.vitals-date-body .table thead th:nth-child(5) { min-width: 140px; } /* Respiratory Rate */
.vitals-date-body .table thead th:nth-child(6) { min-width: 120px; } /* O2 Saturation */
.vitals-date-body .table thead th:nth-child(7) { min-width: 100px; } /* Weight */
.vitals-date-body .table thead th:nth-child(8) { min-width: 100px; } /* Height */
.vitals-date-body .table thead th:nth-child(9) { min-width: 90px; }  /* BMI */

.vitals-history-container {
    max-height: 600px;
    overflow-y: auto;
}

/* Make vital cards uniform size */
.vital-card {
    height: 200px;
    display: flex;
    flex-direction: column;
}

.vital-card .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
</style>

<script>
$(document).ready(function() {
    // Export vitals data
    $('#exportVitals').click(function() {
        showAlert('Export functionality would be implemented here', 'Information', 'info');
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});
</script>