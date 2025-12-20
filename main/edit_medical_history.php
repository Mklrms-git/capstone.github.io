<?php
define('MHAVIS_EXEC', true);
$page_title = "Edit Medical History";
$active_page = "patients";
require_once __DIR__ . '/config/init.php';
requireLogin();

$conn = getDBConnection();

// Ensure medical_history table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
if ($checkTable->num_rows == 0) {
    // Create medical_history table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS `medical_history` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `patient_id` int(11) NOT NULL,
        `doctor_id` int(11) NOT NULL,
        `history_type` enum('allergies','medications','past_history','immunization','procedures','substance','family','menstrual','sexual','obstetric','growth') NOT NULL,
        `history_details` text DEFAULT NULL,
        `structured_data` JSON NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_by` int(11) DEFAULT NULL,
        `updated_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_patient_id` (`patient_id`),
        KEY `idx_doctor_id` (`doctor_id`),
        KEY `idx_history_type` (`history_type`),
        CONSTRAINT `fk_medical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_medical_history_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($createTable);
}

$historyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$historyId) {
    header("Location: patients.php&error=Invalid history ID");
    exit;
}

// Check if tracking columns exist
$checkCreatedBy = $conn->query("SHOW COLUMNS FROM `medical_history` LIKE 'created_by'");
$hasCreatedBy = $checkCreatedBy && $checkCreatedBy->num_rows > 0;
$checkUpdatedBy = $conn->query("SHOW COLUMNS FROM `medical_history` LIKE 'updated_by'");
$hasUpdatedBy = $checkUpdatedBy && $checkUpdatedBy->num_rows > 0;

// Get history record details
if ($hasCreatedBy && $hasUpdatedBy) {
    $stmt = $conn->prepare("SELECT mh.*, 
                                   p.first_name as patient_first_name, p.last_name as patient_last_name,
                                   creator.first_name as creator_first_name, creator.last_name as creator_last_name, creator.role as creator_role,
                                   updater.first_name as updater_first_name, updater.last_name as updater_last_name, updater.role as updater_role,
                                   doctor.first_name as doctor_first_name, doctor.last_name as doctor_last_name, doctor.specialization
                            FROM medical_history mh 
                            JOIN patients p ON mh.patient_id = p.id 
                            LEFT JOIN users creator ON mh.created_by = creator.id
                            LEFT JOIN users updater ON mh.updated_by = updater.id
                            LEFT JOIN users doctor ON mh.doctor_id = doctor.id
                            WHERE mh.id = ?");
} else {
    $stmt = $conn->prepare("SELECT mh.*, 
                                   p.first_name as patient_first_name, p.last_name as patient_last_name,
                                   NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role,
                                   NULL as updater_first_name, NULL as updater_last_name, NULL as updater_role,
                                   doctor.first_name as doctor_first_name, doctor.last_name as doctor_last_name, doctor.specialization
                            FROM medical_history mh 
                            JOIN patients p ON mh.patient_id = p.id 
                            LEFT JOIN users doctor ON mh.doctor_id = doctor.id
                            WHERE mh.id = ?");
}

$stmt->bind_param("i", $historyId);
$stmt->execute();
$history = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$history) {
    header("Location: patients.php&error=History record not found");
    exit;
}

$patientId = $history['patient_id']; // Use patient_id from record

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $historyType = $_POST['history_type'] ?? '';
    $historyDetails = $_POST['history_details'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $updatedBy = $_SESSION['user_id'];

    if (empty($historyType)) {
        $error = "History type is required.";
    } else {
        try {
            if ($hasUpdatedBy) {
                $stmt = $conn->prepare("UPDATE medical_history SET 
                                        history_type = ?, history_details = ?, status = ?, 
                                        updated_by = ?, updated_at = NOW() 
                                        WHERE id = ?");
                $stmt->bind_param("sssii", $historyType, $historyDetails, $status, $updatedBy, $historyId);
            } else {
                $stmt = $conn->prepare("UPDATE medical_history SET 
                                        history_type = ?, history_details = ?, status = ?, 
                                        updated_at = NOW() 
                                        WHERE id = ?");
                $stmt->bind_param("sssi", $historyType, $historyDetails, $status, $historyId);
            }
            
            if (!$stmt->execute()) {
                error_log("SQL Execute failed in edit_medical_history.php: " . $stmt->error);
                $error = "Failed to update medical history: " . $stmt->error;
            } else {
                header("Location: patients.php?patient_id=" . $patientId . "&tab=medical_history&message=Medical history updated successfully");
                exit;
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = "Failed to update medical history: " . $e->getMessage();
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
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Medical History</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <strong>Patient:</strong> <?= htmlspecialchars($history['patient_first_name'] . ' ' . $history['patient_last_name']) ?>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="history_type" class="form-label">History Type *</label>
                            <select class="form-select" id="history_type" name="history_type" required>
                                <option value="">Select type</option>
                                <option value="allergies" <?= $history['history_type'] == 'allergies' ? 'selected' : '' ?>>Allergies</option>
                                <option value="medications" <?= $history['history_type'] == 'medications' ? 'selected' : '' ?>>Medications</option>
                                <option value="past_history" <?= $history['history_type'] == 'past_history' ? 'selected' : '' ?>>Past Medical History</option>
                                <option value="immunization" <?= $history['history_type'] == 'immunization' ? 'selected' : '' ?>>Immunization/Vaccines</option>
                                <option value="procedures" <?= $history['history_type'] == 'procedures' ? 'selected' : '' ?>>Procedures</option>
                                <option value="substance" <?= $history['history_type'] == 'substance' ? 'selected' : '' ?>>Substance Used</option>
                                <option value="family" <?= $history['history_type'] == 'family' ? 'selected' : '' ?>>Family History</option>
                                <option value="menstrual" <?= $history['history_type'] == 'menstrual' ? 'selected' : '' ?>>Menstrual History</option>
                                <option value="sexual" <?= $history['history_type'] == 'sexual' ? 'selected' : '' ?>>Sexual History</option>
                                <option value="obstetric" <?= $history['history_type'] == 'obstetric' ? 'selected' : '' ?>>Obstetric History</option>
                                <option value="growth" <?= $history['history_type'] == 'growth' ? 'selected' : '' ?>>Growth Milestone History</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="history_details" class="form-label">Details</label>
                            <textarea class="form-control" id="history_details" name="history_details" rows="5" placeholder="Enter history details"><?= htmlspecialchars($history['history_details'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($history['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($history['status'] ?? 'active') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <small>
                                <strong>Recorded by:</strong> 
                                <?php if (isset($history['creator_first_name']) && $history['creator_first_name']): ?>
                                    <?php if ($history['creator_role'] == 'Admin'): ?>
                                        <span class="badge bg-warning text-dark">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-white">Doctor</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($history['creator_first_name'] . ' ' . $history['creator_last_name']); ?>
                                    on <?php echo date('M d, Y g:i A', strtotime($history['created_at'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Unknown</span>
                                    on <?php echo date('M d, Y g:i A', strtotime($history['created_at'])); ?>
                                <?php endif; ?>
                                <?php if (isset($history['updater_first_name']) && $history['updater_first_name'] && 
                                         isset($history['updated_by']) && $history['updated_by'] && 
                                         (!isset($history['created_by']) || !$history['created_by'] || $history['updated_by'] != $history['created_by'])): ?>
                                    <br><strong>Last updated by:</strong> 
                                    <?php if ($history['updater_role'] == 'Admin'): ?>
                                        <span class="badge bg-warning text-dark">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-white">Doctor</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($history['updater_first_name'] . ' ' . $history['updater_last_name']); ?>
                                    on <?php echo date('M d, Y g:i A', strtotime($history['updated_at'])); ?>
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="patients.php?patient_id=<?= $patientId ?>&tab=medical_history" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Medical History
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

