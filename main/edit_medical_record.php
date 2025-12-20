<?php
define('MHAVIS_EXEC', true);
$page_title = "Edit Medical Record";
$active_page = "patients";
require_once __DIR__ . '/config/init.php';
requireLogin();

$conn = getDBConnection();

$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$recordId) {
    header("Location: patients.php&error=Invalid record ID");
    exit;
}

// Check if tracking columns exist before building query
$checkCreatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'created_by'");
$hasCreatedBy = $checkCreatedBy && $checkCreatedBy->num_rows > 0;
$checkUpdatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'updated_by'");
$hasUpdatedBy = $checkUpdatedBy && $checkUpdatedBy->num_rows > 0;

// Get record details with creator and updater information
if ($hasCreatedBy && $hasUpdatedBy) {
    // Query with tracking columns
    $stmt = $conn->prepare("SELECT mr.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.sex as patient_sex,
                                   creator.id as creator_id, creator.first_name as creator_first_name, creator.last_name as creator_last_name, creator.role as creator_role,
                                   updater.id as updater_id, updater.first_name as updater_first_name, updater.last_name as updater_last_name, updater.role as updater_role,
                                   attending.id as attending_doctor_user_id, attending.first_name as attending_first_name, attending.last_name as attending_last_name, attending.specialization as attending_specialization
                            FROM medical_records mr 
                            JOIN patients p ON mr.patient_id = p.id 
                            LEFT JOIN users creator ON mr.created_by = creator.id
                            LEFT JOIN users updater ON mr.updated_by = updater.id
                            LEFT JOIN users attending ON mr.doctor_id = attending.id
                            WHERE mr.id = ?");
} else {
    // Fallback query without tracking columns
    $stmt = $conn->prepare("SELECT mr.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.sex as patient_sex,
                                   NULL as creator_id, NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role,
                                   NULL as updater_id, NULL as updater_first_name, NULL as updater_last_name, NULL as updater_role,
                                   attending.id as attending_doctor_user_id, attending.first_name as attending_first_name, attending.last_name as attending_last_name, attending.specialization as attending_specialization
                            FROM medical_records mr 
                            JOIN patients p ON mr.patient_id = p.id 
                            LEFT JOIN users attending ON mr.doctor_id = attending.id
                            WHERE mr.id = ?");
}
$stmt->bind_param("i", $recordId);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    header("Location: patients.php&error=Record not found");
    exit;
}

// Classify BMI based on gender
if (!function_exists('classifyBMI')) {
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
}

// Admin can edit medical records

// Get all active doctors for attending doctor dropdown (admin only)
$allDoctors = [];
if (isAdmin()) {
    $doctorsQuery = "SELECT u.id, u.first_name, u.last_name, u.specialization, 
                            dept.id as department_id, dept.name as department_name
                     FROM users u
                     LEFT JOIN doctors d ON u.id = d.user_id
                     LEFT JOIN departments dept ON u.department_id = dept.id
                     WHERE u.role = 'Doctor' AND u.status = 'Active'
                     ORDER BY dept.name, u.last_name, u.first_name";
    $doctorsResult = $conn->query($doctorsQuery);
    while ($row = $doctorsResult->fetch_assoc()) {
        $allDoctors[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For both admin and doctor, verify password before allowing update
    $password = $_POST['edit_password'] ?? '';
    if (empty($password)) {
        $error = "Password verification required to update medical record.";
    } else {
        // Verify password
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (!password_verify($password, $user['password'])) {
                    $error = "Incorrect password. Please try again.";
                }
            } else {
                $error = "User not found.";
            }
            $stmt->close();
        } else {
            $error = "User not authenticated.";
        }
    }
    
    // Only proceed if no password error (for doctors) or if admin
    if (!isset($error)) {
        $visitDate = $_POST['visit_date'] ?? '';
        $diagnosis = $_POST['diagnosis'] ?? '';
        $treatment = $_POST['treatment'] ?? '';
        $labResults = $_POST['lab_results'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $nextAppointmentDate = !empty($_POST['next_appointment_date']) ? $_POST['next_appointment_date'] : null;
        
        // Combine vital signs fields into vitals text
        $vitals_parts = [];
        $systolic = $_POST['vitals_systolic'] ?? '';
        $diastolic = $_POST['vitals_diastolic'] ?? '';
        if (!empty($systolic) && !empty($diastolic)) {
            $vitals_parts[] = 'BP: ' . $systolic . '/' . $diastolic;
        }
        if (!empty($_POST['vitals_temperature'])) {
            $vitals_parts[] = 'Temperature: ' . $_POST['vitals_temperature'] . ' °F';
        }
        if (!empty($_POST['vitals_heart_rate'])) {
            $vitals_parts[] = 'Heart Rate: ' . $_POST['vitals_heart_rate'] . ' bpm';
        }
        if (!empty($_POST['vitals_respiratory_rate'])) {
            $vitals_parts[] = 'Respiratory Rate: ' . $_POST['vitals_respiratory_rate'] . ' /min';
        }
        if (!empty($_POST['vitals_oxygen_saturation'])) {
            $vitals_parts[] = 'O2 Saturation: ' . $_POST['vitals_oxygen_saturation'] . ' %';
        }
        if (!empty($_POST['vitals_weight'])) {
            $vitals_parts[] = 'Weight: ' . $_POST['vitals_weight'] . ' lbs';
        }
        if (!empty($_POST['vitals_height'])) {
            $vitals_parts[] = 'Height: ' . $_POST['vitals_height'] . ' in';
        }
        if (!empty($_POST['vitals_notes'])) {
            $vitals_parts[] = 'Notes: ' . $_POST['vitals_notes'];
        }
        $vitals = !empty($vitals_parts) ? implode(' • ', $vitals_parts) : '';
        
        // Determine attending doctor and updated_by based on user role
        $updatedBy = $_SESSION['user_id']; // Who is updating this (admin or doctor)
        
        if (isAdmin()) {
            // Admin: can change attending doctor, admin is updated_by
            $attendingDoctorId = isset($_POST['attending_doctor_id']) && !empty($_POST['attending_doctor_id']) 
                ? (int)$_POST['attending_doctor_id'] 
                : $record['doctor_id']; // Keep existing if not provided
        } elseif (isDoctor()) {
            // Doctor: doctor is both attending doctor and updated_by
            $attendingDoctorId = $_SESSION['user_id'];
        } else {
            if (!isset($error)) {
                $error = "Unauthorized access.";
            }
        }

        // Validate required fields
        if (empty($visitDate)) {
            $error = "Visit date is required.";
        } elseif (empty($attendingDoctorId)) {
            $error = "Attending doctor is required.";
        } else {
        try {
            // Handle file uploads
            $uploadedFiles = [];
            $uploadDir = 'uploads/medical_records/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB

                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = $_FILES['attachments']['name'][$key];
                        $fileType = $_FILES['attachments']['type'][$key];
                        $fileSize = $_FILES['attachments']['size'][$key];

                        // Validate file type
                        if (!in_array($fileType, $allowedTypes)) {
                            $error = "Invalid file type. Only JPG, PNG, PDF, and DOC files are allowed.";
                            break;
                        }

                        // Validate file size
                        if ($fileSize > $maxFileSize) {
                            $error = "File size too large. Maximum size is 5MB.";
                            break;
                        }

                        // Generate unique filename
                        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                        $filePath = $uploadDir . $uniqueFileName;

                        // Move uploaded file
                        if (move_uploaded_file($tmpName, $filePath)) {
                            $uploadedFiles[] = [
                                'original_name' => $fileName,
                                'file_path' => $filePath,
                                'file_type' => $fileType,
                                'file_size' => $fileSize
                            ];
                        }
                    }
                }
            }

            if (!isset($error)) {
                // Ensure nextAppointmentDate is NULL (not empty string) if not provided
                if (empty($nextAppointmentDate)) {
                    $nextAppointmentDate = null;
                }
                
                // Check if updated_by column exists
                $checkColumn = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'updated_by'");
                $hasUpdatedBy = $checkColumn && $checkColumn->num_rows > 0;
                
                // Update medical record
                // updated_by: who updated this (admin or doctor)
                // doctor_id: attending doctor for this record
                $prescription = $_POST['prescription'] ?? '';
                
                // For UPDATE, we can set NULL directly - MySQL handles it
                if ($hasUpdatedBy) {
                    $stmt = $conn->prepare("UPDATE medical_records SET 
                                            visit_date = ?, diagnosis = ?, 
                                            treatment = ?, prescription = ?, lab_results = ?, notes = ?, 
                                            vitals = ?, next_appointment_date = ?, 
                                            doctor_id = ?, updated_by = ?, updated_at = NOW() 
                                            WHERE id = ?");
                    $stmt->bind_param("ssssssssiii", $visitDate, $diagnosis, 
                                     $treatment, $prescription, $labResults, $notes, $vitals, $nextAppointmentDate, 
                                     $attendingDoctorId, $updatedBy, $recordId);
                } else {
                    // Fallback if column doesn't exist yet
                    $stmt = $conn->prepare("UPDATE medical_records SET 
                                            visit_date = ?, diagnosis = ?, 
                                            treatment = ?, prescription = ?, lab_results = ?, notes = ?, 
                                            vitals = ?, next_appointment_date = ?, 
                                            doctor_id = ?, updated_at = NOW() 
                                            WHERE id = ?");
                    $stmt->bind_param("ssssssssii", $visitDate, $diagnosis, 
                                     $treatment, $prescription, $labResults, $notes, $vitals, $nextAppointmentDate, 
                                     $attendingDoctorId, $recordId);
                }
                
                if (!$stmt->execute()) {
                    error_log("SQL Execute failed in edit_medical_record.php: " . $stmt->error);
                    $error = "Failed to update medical record: " . $stmt->error;
                }

                // Update attachments if new files were uploaded
                if (!empty($uploadedFiles)) {
                    // Get existing attachments
                    $existingAttachments = json_decode($record['attachments'] ?? '[]', true) ?: [];
                    $allAttachments = array_merge($existingAttachments, $uploadedFiles);
                    $attachmentsJson = json_encode($allAttachments);
                    
                    $updateStmt = $conn->prepare("UPDATE medical_records SET attachments = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $attachmentsJson, $recordId);
                    $updateStmt->execute();
                }

                // Send notification to patient
                // First, get the patient_user_id from the patient_id
                $patientUserStmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ? LIMIT 1");
                $patientUserStmt->bind_param("i", $record['patient_id']);
                $patientUserStmt->execute();
                $patientUserResult = $patientUserStmt->get_result();
                
                if ($patientUserResult && $patientUserResult->num_rows > 0) {
                    $patientUserData = $patientUserResult->fetch_assoc();
                    $patientUserId = $patientUserData['id'];
                    
                    // Create notification
                    $updaterName = isAdmin() ? "the admin" : "your doctor";
                    $notificationTitle = "Medical Record Updated";
                    $notificationMessage = "Your medical record from " . formatDate($visitDate) . " has been updated by " . $updaterName . ". Please review the changes in your medical records.";
                    $notificationType = "Medical_Record_Updated";
                    $recipientType = "Patient";
                    $sentVia = "System";
                    
                    $notifStmt = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, type, title, message, sent_via, created_at) 
                                                 VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $notifStmt->bind_param("sissss", $recipientType, $patientUserId, $notificationType, $notificationTitle, $notificationMessage, $sentVia);
                    $notifStmt->execute();
                }

                header("Location: patients.php?patient_id=" . $record['patient_id'] . "&tab=medical_records&message=Medical record updated successfully");
                exit;
            }
        } catch (Exception $e) {
            $error = "Failed to update medical record: " . $e->getMessage();
        }
        } // Close the else block for validation
    } // Close the if (!isset($error)) block
} // Close the POST request block

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Medical Record</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <strong>Patient:</strong> <?= htmlspecialchars($record['patient_first_name'] . ' ' . $record['patient_last_name']) ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="editMedicalRecordForm" onsubmit="return handleFormSubmit(event);">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="visit_date" class="form-label">Visit Date *</label>
                                    <input type="date" class="form-control" id="visit_date" name="visit_date" 
                                           value="<?= htmlspecialchars($record['visit_date']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="next_appointment_date" class="form-label">Next Appointment Date</label>
                                    <input type="date" class="form-control" id="next_appointment_date" name="next_appointment_date" 
                                           value="<?= htmlspecialchars($record['next_appointment_date']) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <?php if (isAdmin()): ?>
                            <div class="mb-3">
                                <label for="attending_doctor_id" class="form-label">Attending Doctor *</label>
                                <select class="form-select" id="attending_doctor_id" name="attending_doctor_id" required>
                                    <option value="">Select Attending Doctor</option>
                                    <?php 
                                    $current_dept = '';
                                    foreach ($allDoctors as $doctor): 
                                        $dept_name = $doctor['department_name'] ?? 'Unassigned';
                                        if ($dept_name != $current_dept) {
                                            if ($current_dept != '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($dept_name) . '">';
                                            $current_dept = $dept_name;
                                        }
                                        $selected = ($doctor['id'] == $record['doctor_id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $doctor['id']; ?>" <?php echo $selected; ?>>
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            <?php if ($doctor['specialization']): ?>
                                                - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($current_dept != '') echo '</optgroup>'; ?>
                                </select>
                            </div>
                        <?php elseif (isDoctor()): ?>
                            <div class="mb-3">
                                <label class="form-label">Attending Doctor</label>
                                <div class="form-control-plaintext">
                                    <span class="badge bg-info text-white me-2">Doctor</span>
                                    Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                    <?php if (isset($_SESSION['specialization']) && $_SESSION['specialization']): ?>
                                        - <?php echo htmlspecialchars($_SESSION['specialization']); ?>
                                    <?php endif; ?>
                                    <input type="hidden" name="attending_doctor_id" value="<?php echo $_SESSION['user_id']; ?>">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <small>
                                <strong>Recorded by:</strong> 
                                <?php if (isset($record['creator_first_name']) && $record['creator_first_name']): ?>
                                    <?php if ($record['creator_role'] == 'Admin'): ?>
                                        <span class="badge bg-warning text-dark">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-white">Doctor</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($record['creator_first_name'] . ' ' . $record['creator_last_name']); ?>
                                    on <?php echo formatDateTime($record['created_at']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Unknown</span>
                                <?php endif; ?>
                                <?php if (isset($record['updater_first_name']) && $record['updater_first_name'] && 
                                         isset($record['updated_by']) && $record['updated_by'] && 
                                         (!isset($record['created_by']) || !$record['created_by'] || $record['updated_by'] != $record['created_by'])): ?>
                                    <br><strong>Last updated by:</strong> 
                                    <?php if ($record['updater_role'] == 'Admin'): ?>
                                        <span class="badge bg-warning text-dark">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-white">Doctor</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($record['updater_first_name'] . ' ' . $record['updater_last_name']); ?>
                                    on <?php echo formatDateTime($record['updated_at']); ?>
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-heartbeat me-1"></i>Vital Signs</label>
                            <?php
                            // Parse existing vitals data
                            $systolic = '';
                            $diastolic = '';
                            $temperature = '';
                            $heart_rate = '';
                            $respiratory_rate = '';
                            $oxygen_saturation = '';
                            $weight = '';
                            $height = '';
                            $vitals_notes = '';
                            
                            if (!empty($record['vitals'])) {
                                // Try to parse as JSON first
                                $vitals_data = json_decode($record['vitals'], true);
                                if (is_array($vitals_data)) {
                                    // It's JSON format
                                    $systolic = $vitals_data['systolic'] ?? $vitals_data['Systolic BP'] ?? '';
                                    $diastolic = $vitals_data['diastolic'] ?? $vitals_data['Diastolic BP'] ?? '';
                                    $temperature = $vitals_data['temperature'] ?? $vitals_data['Temperature'] ?? '';
                                    $heart_rate = $vitals_data['heart_rate'] ?? $vitals_data['Heart Rate'] ?? '';
                                    $respiratory_rate = $vitals_data['respiratory_rate'] ?? $vitals_data['Respiratory Rate'] ?? '';
                                    $oxygen_saturation = $vitals_data['oxygen_saturation'] ?? $vitals_data['O2 Saturation'] ?? '';
                                    $weight = $vitals_data['weight'] ?? $vitals_data['Weight'] ?? '';
                                    $height = $vitals_data['height'] ?? $vitals_data['Height'] ?? '';
                                    $vitals_notes = $vitals_data['notes'] ?? '';
                                } else {
                                    // Try to parse text format (e.g., "BP: 120/80 • Temperature: 98.6 °F • Heart Rate: 72 bpm", etc.)
                                    $vitals_text = $record['vitals'];
                                    
                                    // Extract blood pressure (format: "BP: 120/80" or "120/80")
                                    if (preg_match('/BP\s*:\s*(\d+)\s*\/\s*(\d+)/i', $vitals_text, $bp_matches)) {
                                        $systolic = $bp_matches[1];
                                        $diastolic = $bp_matches[2];
                                    } elseif (preg_match('/(\d+)\s*\/\s*(\d+)/', $vitals_text, $bp_matches)) {
                                        // Fallback: just numbers with slash
                                        $systolic = $bp_matches[1];
                                        $diastolic = $bp_matches[2];
                                    }
                                    
                                    // Extract temperature (format: "Temperature: 98.6 °F")
                                    if (preg_match('/Temperature\s*:\s*(\d+\.?\d*)\s*°?F?/i', $vitals_text, $temp_matches)) {
                                        $temperature = $temp_matches[1];
                                    }
                                    
                                    // Extract heart rate (format: "Heart Rate: 72 bpm")
                                    if (preg_match('/Heart\s*Rate\s*:\s*(\d+)\s*bpm/i', $vitals_text, $hr_matches)) {
                                        $heart_rate = $hr_matches[1];
                                    }
                                    
                                    // Extract respiratory rate (format: "Respiratory Rate: 16 /min")
                                    if (preg_match('/Respiratory\s*Rate\s*:\s*(\d+)\s*\/?min/i', $vitals_text, $rr_matches)) {
                                        $respiratory_rate = $rr_matches[1];
                                    }
                                    
                                    // Extract O2 saturation (format: "O2 Saturation: 98 %")
                                    if (preg_match('/O2\s*Saturation\s*:\s*(\d+)\s*%/i', $vitals_text, $o2_matches)) {
                                        $oxygen_saturation = $o2_matches[1];
                                    }
                                    
                                    // Extract weight (format: "Weight: 165 lbs")
                                    if (preg_match('/Weight\s*:\s*(\d+\.?\d*)\s*lbs/i', $vitals_text, $weight_matches)) {
                                        $weight = $weight_matches[1];
                                    }
                                    
                                    // Extract height (format: "Height: 68 in")
                                    if (preg_match('/Height\s*:\s*(\d+\.?\d*)\s*in/i', $vitals_text, $height_matches)) {
                                        $height = $height_matches[1];
                                    }
                                    
                                    // Extract vital signs notes (format: "Notes: ...")
                                    if (preg_match('/Notes\s*:\s*(.+?)(?:\s*•|$)/i', $vitals_text, $notes_matches)) {
                                        $vitals_notes = trim($notes_matches[1]);
                                    }
                                }
                            }
                            ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-heartbeat me-1"></i>Systolic BP</label>
                                        <input type="number" class="form-control" id="vitals_systolic" name="vitals_systolic" placeholder="120" value="<?= htmlspecialchars($systolic) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-heartbeat me-1"></i>Diastolic BP</label>
                                        <input type="number" class="form-control" id="vitals_diastolic" name="vitals_diastolic" placeholder="80" value="<?= htmlspecialchars($diastolic) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-thermometer-half me-1"></i>Temperature (°F)</label>
                                        <input type="number" step="0.1" class="form-control" id="vitals_temperature" name="vitals_temperature" placeholder="98.6" value="<?= htmlspecialchars($temperature) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-heart me-1"></i>Heart Rate (bpm)</label>
                                        <input type="number" class="form-control" id="vitals_heart_rate" name="vitals_heart_rate" placeholder="72" value="<?= htmlspecialchars($heart_rate) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-lungs me-1"></i>Respiratory Rate (/min)</label>
                                        <input type="number" class="form-control" id="vitals_respiratory_rate" name="vitals_respiratory_rate" placeholder="16" value="<?= htmlspecialchars($respiratory_rate) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-percent me-1"></i>O2 Saturation (%)</label>
                                        <input type="number" class="form-control" id="vitals_oxygen_saturation" name="vitals_oxygen_saturation" placeholder="98" value="<?= htmlspecialchars($oxygen_saturation) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-weight me-1"></i>Weight (lbs)</label>
                                        <input type="number" step="0.1" class="form-control" id="vitals_weight" name="vitals_weight" placeholder="165" value="<?= htmlspecialchars($weight) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-ruler-vertical me-1"></i>Height (inches)</label>
                                        <input type="number" step="0.1" class="form-control" id="vitals_height" name="vitals_height" placeholder="68" value="<?= htmlspecialchars($height) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-calculator me-1"></i>BMI</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="vitals_bmi_display" readonly placeholder="Calculated automatically">
                                            <span class="input-group-text" id="bmi_classification_badge"></span>
                                        </div>
                                        <small class="text-muted">BMI is calculated automatically from weight and height</small>
                                        <input type="hidden" id="vitals_bmi" name="vitals_bmi" value="">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-notes-medical me-1"></i>Vital Signs Notes</label>
                                <textarea class="form-control" id="vitals_notes" name="vitals_notes" rows="2" placeholder="Additional observations..."><?= htmlspecialchars($vitals_notes) ?></textarea>
                            </div>
                            
                            <!-- Hidden field to store formatted vitals -->
                            <input type="hidden" id="vitals" name="vitals" value="">
                        </div>

                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Diagnosis</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?= htmlspecialchars($record['diagnosis']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="treatment" class="form-label">Treatment</label>
                            <textarea class="form-control" id="treatment" name="treatment" rows="3"><?= htmlspecialchars($record['treatment']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="prescription" class="form-label">Prescription</label>
                            <textarea class="form-control" id="prescription" name="prescription" rows="3"><?= htmlspecialchars($record['prescription']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="lab_results" class="form-label">Lab Results</label>
                            <textarea class="form-control" id="lab_results" name="lab_results" rows="3"><?= htmlspecialchars($record['lab_results']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($record['notes']) ?></textarea>
                        </div>

                        <?php if (!empty($record['attachments'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Current Attachments</label>
                                <div class="border rounded p-3 bg-light">
                                    <?php 
                                    $attachments = json_decode($record['attachments'], true);
                                    if (is_array($attachments)):
                                        foreach ($attachments as $index => $attachment): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-file me-2"></i>
                                                <span class="flex-grow-1"><?= htmlspecialchars($attachment['original_name']) ?></span>
                                                <small class="text-muted me-2"><?= number_format($attachment['file_size'] / 1024, 1) ?> KB</small>
                                                <a href="<?= htmlspecialchars($attachment['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                            </div>
                                        <?php endforeach;
                                    endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="attachments" class="form-label">Add New Attachments</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            <div class="form-text">Allowed file types: JPG, PNG, GIF, PDF, DOC, DOCX (Max 5MB each)</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="patients.php?patient_id=<?= $record['patient_id'] ?>&tab=medical_records" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitEditBtn">
                                <i class="fas fa-save me-1"></i>Update Medical Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Confirmation Modal for Edit (Admin and Doctor) -->
<?php if (isAdmin() || isDoctor()): ?>
<div class="modal fade" id="editPasswordConfirmModal" tabindex="-1" aria-labelledby="editPasswordConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editPasswordConfirmModalLabel">
                    <i class="fas fa-shield-alt me-2"></i>Password Verification Required
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Security Verification Required</strong>
                </div>
                <p class="mb-3">Please enter your password to confirm this update to the medical record. This is the second confirmation step.</p>
                <form id="editPasswordForm">
                    <div class="mb-3">
                        <label for="editPasswordInput" class="form-label">Enter Your Password</label>
                        <input type="password" class="form-control" id="editPasswordInput" placeholder="Enter your password" required autocomplete="current-password">
                        <div class="invalid-feedback" id="editPasswordError"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-warning" id="confirmEditBtn" onclick="verifyPasswordAndSubmit();">
                    <i class="fas fa-check me-1"></i>Confirm Update
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Function to handle form submission with double confirmation
function handleFormSubmit(event) {
    event.preventDefault();
    
    // Format vitals first
    formatVitalsBeforeSubmit(null);
    
    // First confirmation: Are you sure you want to update?
    confirmDialog(
        'Are you sure you want to update this medical record? This action will modify the patient\'s medical history.',
        'Continue',
        'Cancel',
        'Confirm Update'
    ).then(function(confirmed) {
        if (confirmed) {
            // For both admin and doctor: Show password confirmation modal (second confirmation)
            showPasswordConfirmationModal();
        }
    });
    
    return false;
}

// Function to show password confirmation modal (for admin and doctor)
function showPasswordConfirmationModal() {
    const passwordModal = new bootstrap.Modal(document.getElementById('editPasswordConfirmModal'));
    document.getElementById('editPasswordInput').value = '';
    document.getElementById('editPasswordError').textContent = '';
    document.getElementById('editPasswordInput').classList.remove('is-invalid');
    
    passwordModal.show();
    setTimeout(() => {
        document.getElementById('editPasswordInput').focus();
    }, 300);
    
    // Handle Enter key in password field
    document.getElementById('editPasswordInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyPasswordAndSubmit();
        }
    });
}

// Classify BMI based on gender
function classifyBMI(bmi, gender = '') {
    if (!bmi || bmi <= 0 || isNaN(bmi)) {
        return {status: 'Unknown', class: 'secondary'};
    }
    
    const bmiValue = parseFloat(bmi);
    
    // Standard BMI classification (same for both men and women)
    if (bmiValue < 18.5) {
        return {status: 'Underweight', class: 'info'};
    } else if (bmiValue >= 18.5 && bmiValue < 25.0) {
        return {status: 'Healthy', class: 'success'};
    } else if (bmiValue >= 25.0 && bmiValue < 30.0) {
        return {status: 'Overweight', class: 'warning'};
    } else {
        return {status: 'Obesity', class: 'danger'};
    }
}

// Calculate and display BMI
function calculateAndDisplayBMI() {
    const weight = parseFloat(document.getElementById('vitals_weight').value);
    const height = parseFloat(document.getElementById('vitals_height').value);
    const bmiDisplay = document.getElementById('vitals_bmi_display');
    const bmiInput = document.getElementById('vitals_bmi');
    const badgeSpan = document.getElementById('bmi_classification_badge');
    
    if (weight && height && height > 0) {
        // Calculate BMI: (weight in lbs * 703) / (height in inches)^2
        const bmi = (weight * 703) / (height * height);
        const bmiRounded = bmi.toFixed(1);
        
        // Display BMI
        bmiDisplay.value = bmiRounded;
        bmiInput.value = bmiRounded;
        
        // Get patient gender from PHP (we'll pass it via data attribute)
        const patientGender = document.getElementById('vitals_weight').getAttribute('data-patient-gender') || '';
        const classification = classifyBMI(bmiRounded, patientGender);
        
        // Update badge
        badgeSpan.innerHTML = '<span class="badge bg-' + classification.class + '">' + classification.status + '</span>';
    } else {
        bmiDisplay.value = '';
        bmiInput.value = '';
        badgeSpan.innerHTML = '';
    }
}

// Function to format vitals before form submission
function formatVitalsBeforeSubmit(event) {
    const vitals_parts = [];
    const systolic = document.getElementById('vitals_systolic').value.trim();
    const diastolic = document.getElementById('vitals_diastolic').value.trim();
    
    if (systolic && diastolic) {
        vitals_parts.push('BP: ' + systolic + '/' + diastolic);
    }
    
    const temperature = document.getElementById('vitals_temperature').value.trim();
    if (temperature) {
        vitals_parts.push('Temperature: ' + temperature + ' °F');
    }
    
    const heart_rate = document.getElementById('vitals_heart_rate').value.trim();
    if (heart_rate) {
        vitals_parts.push('Heart Rate: ' + heart_rate + ' bpm');
    }
    
    const respiratory_rate = document.getElementById('vitals_respiratory_rate').value.trim();
    if (respiratory_rate) {
        vitals_parts.push('Respiratory Rate: ' + respiratory_rate + ' /min');
    }
    
    const oxygen_saturation = document.getElementById('vitals_oxygen_saturation').value.trim();
    if (oxygen_saturation) {
        vitals_parts.push('O2 Saturation: ' + oxygen_saturation + ' %');
    }
    
    const weight = document.getElementById('vitals_weight').value.trim();
    if (weight) {
        vitals_parts.push('Weight: ' + weight + ' lbs');
    }
    
    const height = document.getElementById('vitals_height').value.trim();
    if (height) {
        vitals_parts.push('Height: ' + height + ' in');
    }
    
    const bmi = document.getElementById('vitals_bmi').value.trim();
    if (bmi) {
        const patientGender = document.getElementById('vitals_weight').getAttribute('data-patient-gender') || '';
        const classification = classifyBMI(bmi, patientGender);
        vitals_parts.push('BMI: ' + bmi + ' (' + classification.status + ')');
    }
    
    const vitals_notes = document.getElementById('vitals_notes').value.trim();
    if (vitals_notes) {
        vitals_parts.push('Notes: ' + vitals_notes);
    }
    
    // Set the hidden vitals field
    document.getElementById('vitals').value = vitals_parts.join(' • ');
    
    return true;
}

// File size validation
const attachmentsInput = document.getElementById('attachments');
if (attachmentsInput) {
    attachmentsInput.addEventListener('change', function() {
        const files = this.files;
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        for (let i = 0; i < files.length; i++) {
            if (files[i].size > maxSize) {
                alert('File "' + files[i].name + '" is too large. Maximum size is 5MB.');
                this.value = '';
                return;
            }
        }
    });
}

// Initialize BMI calculation on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add patient gender to weight input for BMI calculation
    const weightInput = document.getElementById('vitals_weight');
    if (weightInput) {
        weightInput.setAttribute('data-patient-gender', '<?= isset($record['patient_sex']) ? htmlspecialchars($record['patient_sex'], ENT_QUOTES) : '' ?>');
        
        // Add event listeners for BMI calculation
        weightInput.addEventListener('input', calculateAndDisplayBMI);
        weightInput.addEventListener('change', calculateAndDisplayBMI);
    }
    
    const heightInput = document.getElementById('vitals_height');
    if (heightInput) {
        heightInput.addEventListener('input', calculateAndDisplayBMI);
        heightInput.addEventListener('change', calculateAndDisplayBMI);
    }
    
    // Calculate initial BMI if weight and height are already filled
    if (weightInput && heightInput) {
        calculateAndDisplayBMI();
    }
});

// Function to verify password and submit form (for both admin and doctor)

function verifyPasswordAndSubmit() {
    const password = document.getElementById('editPasswordInput').value;
    const passwordInput = document.getElementById('editPasswordInput');
    const errorDiv = document.getElementById('editPasswordError');
    const confirmBtn = document.getElementById('confirmEditBtn');
    const form = document.getElementById('editMedicalRecordForm');
    
    if (!password) {
        passwordInput.classList.add('is-invalid');
        errorDiv.textContent = 'Password is required';
        return;
    }
    
    // Format vitals before submission
    formatVitalsBeforeSubmit(null);
    
    // Disable button and show loading state
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verifying...';
    
    // Verify password via API
    fetch('verify_delete_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'password=' + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Password verified, add password to form and submit
            const passwordField = document.createElement('input');
            passwordField.type = 'hidden';
            passwordField.name = 'edit_password';
            passwordField.value = password;
            form.appendChild(passwordField);
            
            // Close password modal
            const passwordModal = bootstrap.Modal.getInstance(document.getElementById('editPasswordConfirmModal'));
            if (passwordModal) {
                passwordModal.hide();
            }
            
            // Submit the form
            form.submit();
        } else {
            // Password incorrect
            passwordInput.classList.add('is-invalid');
            errorDiv.textContent = data.message || 'Incorrect password';
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirm Update';
            passwordInput.value = '';
            passwordInput.focus();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        passwordInput.classList.add('is-invalid');
        errorDiv.textContent = 'An error occurred. Please try again.';
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirm Update';
    });
}
</script>

<?php include 'includes/footer.php'; ?> 