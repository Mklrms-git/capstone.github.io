<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireLogin();

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $visitDate = $_POST['visit_date'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment = $_POST['treatment'] ?? '';
    $prescription = $_POST['prescription'] ?? ''; // Required by DB (NOT NULL)
    $labResults = $_POST['lab_results'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $vitals = $_POST['vitals'] ?? ''; // Required by DB (NOT NULL)
    $nextAppointmentDate = !empty($_POST['next_appointment_date']) ? $_POST['next_appointment_date'] : null;
    
    // Admin can add medical records
    
    // Determine attending doctor and recorded by based on user role
    $createdBy = $_SESSION['user_id']; // Who is recording this (admin or doctor)
    
    if (isAdmin()) {
        // Admin: must select attending doctor, admin is recorded by
        $attendingDoctorId = isset($_POST['attending_doctor_id']) && !empty($_POST['attending_doctor_id']) 
            ? (int)$_POST['attending_doctor_id'] 
            : null;
        
        if (empty($attendingDoctorId)) {
            header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Please select an attending doctor.");
            exit;
        }
    } elseif (isDoctor()) {
        // Doctor: doctor is both attending doctor and recorded by
        $attendingDoctorId = $_SESSION['user_id'];
    } else {
        header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Unauthorized access.");
        exit;
    }

    // Validate required fields
    if (empty($visitDate)) {
        header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Visit date is required.");
        exit;
    }

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
                        header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Invalid file type. Only JPG, PNG, PDF, and DOC files are allowed.");
                        exit;
                    }

                    // Validate file size
                    if ($fileSize > $maxFileSize) {
                        header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=File size too large. Maximum size is 5MB.");
                        exit;
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

        // Check if created_by column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'created_by'");
        $hasCreatedBy = $checkColumn && $checkColumn->num_rows > 0;
        
        // Insert medical record
        // created_by: who recorded this (admin or doctor)
        // doctor_id: attending doctor for this record
        // Note: prescription and vitals are required by DB (NOT NULL), so we provide empty strings
        // Ensure nextAppointmentDate is NULL (not empty string) if not provided
        if (empty($nextAppointmentDate)) {
            $nextAppointmentDate = null;
        }
        
        // Check if next_appointment_date has a value - if NULL, exclude it from INSERT
        $hasNextAppt = !empty($nextAppointmentDate);
        
        if ($hasCreatedBy) {
            if ($hasNextAppt) {
                // Include next_appointment_date when it has a value
                $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, visit_date, vitals, diagnosis, treatment, prescription, lab_results, notes, next_appointment_date, created_by, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if ($stmt === false) {
                    error_log("SQL Prepare failed in add_medical_record.php: " . $conn->error);
                    header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Database error: " . htmlspecialchars($conn->error));
                    exit;
                }
                
                $stmt->bind_param("iissssssssi", $patientId, $attendingDoctorId, $visitDate, $vitals, $diagnosis, $treatment, $prescription, $labResults, $notes, $nextAppointmentDate, $createdBy);
            } else {
                // Exclude next_appointment_date when it's NULL
                $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, visit_date, vitals, diagnosis, treatment, prescription, lab_results, notes, created_by, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if ($stmt === false) {
                    error_log("SQL Prepare failed in add_medical_record.php: " . $conn->error);
                    header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Database error: " . htmlspecialchars($conn->error));
                    exit;
                }
                
                $stmt->bind_param("iisssssssi", $patientId, $attendingDoctorId, $visitDate, $vitals, $diagnosis, $treatment, $prescription, $labResults, $notes, $createdBy);
            }
        } else {
            // Fallback if column doesn't exist yet
            if ($hasNextAppt) {
                // Include next_appointment_date when it has a value
                $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, visit_date, vitals, diagnosis, treatment, prescription, lab_results, notes, next_appointment_date, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if ($stmt === false) {
                    error_log("SQL Prepare failed in add_medical_record.php: " . $conn->error);
                    header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Database error: " . htmlspecialchars($conn->error));
                    exit;
                }
                
                $stmt->bind_param("iissssssss", $patientId, $attendingDoctorId, $visitDate, $vitals, $diagnosis, $treatment, $prescription, $labResults, $notes, $nextAppointmentDate);
            } else {
                // Exclude next_appointment_date when it's NULL
                $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, visit_date, vitals, diagnosis, treatment, prescription, lab_results, notes, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if ($stmt === false) {
                    error_log("SQL Prepare failed in add_medical_record.php: " . $conn->error);
                    header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Database error: " . htmlspecialchars($conn->error));
                    exit;
                }
                
                $stmt->bind_param("iisssssss", $patientId, $attendingDoctorId, $visitDate, $vitals, $diagnosis, $treatment, $prescription, $labResults, $notes);
            }
        }
        
        if (!$stmt->execute()) {
            error_log("SQL Execute failed in add_medical_record.php: " . $stmt->error);
            header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Failed to save medical record: " . htmlspecialchars($stmt->error));
            exit;
        }
        
        $medicalRecordId = $conn->insert_id;

        // Store file references in the attachments field (as JSON)
        if (!empty($uploadedFiles)) {
            $attachmentsJson = json_encode($uploadedFiles);
            $updateStmt = $conn->prepare("UPDATE medical_records SET attachments = ? WHERE id = ?");
            if ($updateStmt !== false) {
                $updateStmt->bind_param("si", $attachmentsJson, $medicalRecordId);
                if (!$updateStmt->execute()) {
                    error_log("SQL Execute failed for attachments update in add_medical_record.php: " . $updateStmt->error);
                }
            } else {
                error_log("SQL Prepare failed for attachments update in add_medical_record.php: " . $conn->error);
            }
        }

        // Create notification for patient about new medical record
        require_once __DIR__ . '/config/patient_auth.php';
        $patientUserStmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ?");
        if ($patientUserStmt) {
            $patientUserStmt->bind_param("i", $patientId);
            if ($patientUserStmt->execute()) {
                $patientUserResult = $patientUserStmt->get_result();
                if ($patientUserResult && $patientUserResult->num_rows > 0) {
                    $patientUser = $patientUserResult->fetch_assoc();
                    $patient_user_id = $patientUser['id'];
                    
                    // Get doctor name for notification
                    $doctorStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                    $doctorStmt->bind_param("i", $attendingDoctorId);
                    $doctorStmt->execute();
                    $doctorResult = $doctorStmt->get_result();
                    $doctorName = 'Your doctor';
                    if ($doctorResult && $doctorResult->num_rows > 0) {
                        $doctor = $doctorResult->fetch_assoc();
                        $doctorName = 'Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
                    }
                    $doctorStmt->close();
                    
                    // Format visit date
                    $visitDateFormatted = formatDate($visitDate);
                    
                    $notificationTitle = "New Medical Record Added";
                    $notificationMessage = "A new medical record for your visit on $visitDateFormatted has been added by $doctorName. Please check your medical records section for details.";
                    
                    $notificationResult = createNotification('Patient', $patient_user_id, 'Medical_Record_Updated', $notificationTitle, $notificationMessage, 'System');
                    if (!$notificationResult) {
                        error_log("Failed to create notification for medical record ID: " . $conn->insert_id);
                    }
                }
            }
            $patientUserStmt->close();
        }

        header("Location: patients.php?patient_id=$patientId&tab=medical_records&message=Medical record added successfully");
        exit;
    } catch (Exception $e) {
        header("Location: patients.php?patient_id=$patientId&tab=medical_records&error=Failed to add medical record: " . $e->getMessage());
        exit;
    }
} else {
    // GET request - show form
    // Admin can access add medical record form
    
    $patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    
    if (!$patient_id) {
        header("Location: patients.php");
        exit;
    }
    
    // Get all active doctors for attending doctor dropdown (admin only)
    $allDoctors = [];
    if (isAdmin()) {
        $doctorsQuery = "SELECT u.id, u.first_name, u.last_name, u.specialization, 
                                COALESCE(dept.id, 0) as department_id, 
                                COALESCE(dept.name, 'Unassigned') as department_name
                         FROM users u
                         LEFT JOIN departments dept ON u.department_id = dept.id
                         WHERE u.role = 'Doctor' AND u.status = 'Active'
                         ORDER BY dept.name, u.last_name, u.first_name";
        $doctorsResult = $conn->query($doctorsQuery);
        if ($doctorsResult !== false) {
            while ($row = $doctorsResult->fetch_assoc()) {
                $allDoctors[] = $row;
            }
        }
    }
}

include 'includes/header.php';
?>


<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-file-medical me-2"></i>Add Medical Record</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="patient_id" value="<?= htmlspecialchars($_GET['patient_id'] ?? '') ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="visit_date" class="form-label">Visit Date *</label>
                                    <input type="date" class="form-control" id="visit_date" name="visit_date" required>
                                    <div class="form-text">You can add records from the past year</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="next_appointment_date" class="form-label">Next Appointment Date</label>
                                    <input type="date" class="form-control" id="next_appointment_date" name="next_appointment_date">
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
                                <div class="form-text">Select the attending doctor for this medical record</div>
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

                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Diagnosis</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" placeholder="Enter diagnosis    "></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="treatment" class="form-label">Treatment</label>
                            <textarea class="form-control" id="treatment" name="treatment" rows="3" placeholder="Enter treatment details"></textarea>
                        </div>


                        <div class="mb-3">
                            <label for="lab_results" class="form-label">Lab Results</label>
                            <textarea class="form-control" id="lab_results" name="lab_results" rows="3" placeholder="Enter lab results"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments (Lab Results, Images, etc.)</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            <div class="form-text">Allowed file types: JPG, PNG, GIF, PDF, DOC, DOCX (Max 5MB each)</div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Recorded by:</strong> 
                            <?php if (isAdmin()): ?>
                                <span class="badge bg-warning text-dark">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-info text-white">Doctor</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                            <?php if (isDoctor()): ?>
                                <small class="text-muted">(You are also the attending doctor)</small>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="patients.php?patient_id=<?= htmlspecialchars($_GET['patient_id'] ?? '') ?>&tab=medical_records" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Medical Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set default date to today and allow dates from past year
(function() {
    const today = new Date();
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(today.getFullYear() - 1);
    
    const todayStr = today.toISOString().split('T')[0];
    const oneYearAgoStr = oneYearAgo.toISOString().split('T')[0];
    
    const visitDateInput = document.getElementById('visit_date');
    visitDateInput.setAttribute('min', oneYearAgoStr);
    visitDateInput.setAttribute('max', todayStr);
    visitDateInput.value = todayStr;
})();

// File size validation
document.getElementById('attachments').addEventListener('change', function() {
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
</script>

<?php include 'includes/footer.php'; ?>
