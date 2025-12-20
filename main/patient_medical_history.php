<?php
// Patient Medical History Tab Content
// File: includes/patient_medical_history.php

if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_history_id'])) {
    $delete_id = (int)$_POST['delete_history_id'];
    $stmt = $conn->prepare("DELETE FROM medical_history WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    exit;
}

// Get medical history records for this patient with doctor information
// Try to use medical_history table first, fallback to medical_records if needed
$checkTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
$useHistoryTable = $checkTable && $checkTable->num_rows > 0;

if ($useHistoryTable) {
    // Check if created_by column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM `medical_history` LIKE 'created_by'");
    $hasCreatedBy = $checkColumn && $checkColumn->num_rows > 0;
    
    if ($hasCreatedBy) {
        $stmt = $conn->prepare("SELECT mh.*, 
                                       u.first_name as doctor_first_name, u.last_name as doctor_last_name, 
                                       u.role as doctor_role, u.specialization,
                                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                                       creator.role as creator_role,
                                       updater.first_name as updater_first_name, updater.last_name as updater_last_name,
                                       updater.role as updater_role
                                FROM medical_history mh 
                                LEFT JOIN users u ON mh.doctor_id = u.id 
                                LEFT JOIN users creator ON mh.created_by = creator.id
                                LEFT JOIN users updater ON mh.updated_by = updater.id
                                WHERE mh.patient_id = ? AND mh.status = 'active'
                                ORDER BY mh.history_type, mh.created_at DESC");
    } else {
        $stmt = $conn->prepare("SELECT mh.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name, u.role as doctor_role, u.specialization
                                FROM medical_history mh 
                                LEFT JOIN users u ON mh.doctor_id = u.id 
                                WHERE mh.patient_id = ? AND mh.status = 'active'
                                ORDER BY mh.history_type, mh.created_at DESC");
    }
} else {
    // Fallback: query from medical_records table (for backward compatibility)
    $stmt = $conn->prepare("SELECT mr.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name, u.role as doctor_role, u.specialization
                            FROM medical_records mr 
                            LEFT JOIN users u ON mr.doctor_id = u.id 
                            WHERE mr.patient_id = ? AND mr.history_type IS NOT NULL AND mr.history_type != ''
                            ORDER BY mr.history_type, mr.created_at DESC");
}

$stmt->bind_param("i", $patient_details['id']);
$stmt->execute();
$result = $stmt->get_result();

$grouped = [];
while ($row = $result->fetch_assoc()) {
    $grouped[$row['history_type']][] = $row;
}
$stmt->close();
?>

<style>
.history-type-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    cursor: pointer;
    background: white;
    height: 100%;
}
.history-type-card:hover {
    border-color: #2196f3;
    box-shadow: 0 4px 12px rgba(33, 150, 243, 0.15);
    transform: translateY(-2px);
}
.history-type-icon {
    font-size: 2.5rem;
    color: #2196f3;
    margin-bottom: 10px;
}
.history-type-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}
.history-type-count {
    font-size: 0.9rem;
    color: #6c757d;
}
.history-entry-item {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    background: #f8f9fa;
    transition: all 0.2s;
}
.history-entry-item:hover {
    background: #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.history-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: box-shadow 0.2s;
}
.history-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.history-header {
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    border-radius: 8px 8px 0 0;
}
.history-body {
    padding: 15px;
}
.recorder-info {
    background-color: #e8f5e8;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 10px;
    border-left: 4px solid #28a745;
}
.history-type-badge {
    font-size: 0.8em;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5><i class="fas fa-history me-2"></i>Medical History</h5>
    <?php if (isAdmin() || isDoctor()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalHistoryModal">
            <i class="fas fa-plus me-1"></i>Add Medical History
        </button>
    <?php endif; ?>
</div>

<?php 
// Define icons for each history type
$historyTypeIcons = [
    'allergies' => 'fa-exclamation-triangle',
    'medications' => 'fa-pills',
    'past_history' => 'fa-file-medical',
    'immunization' => 'fa-syringe',
    'procedures' => 'fa-procedures',
    'substance' => 'fa-smoking-ban',
    'family' => 'fa-users',
    'menstrual' => 'fa-calendar-alt',
    'sexual' => 'fa-heart',
    'obstetric' => 'fa-baby',
    'growth' => 'fa-chart-line'
];

$historyTypeLabels = [
    'allergies' => 'Allergies',
    'medications' => 'Medications',
    'past_history' => 'Past Medical History',
    'immunization' => 'Immunization/Vaccines',
    'procedures' => 'Procedures',
    'substance' => 'Substance Used',
    'family' => 'Family History',
    'menstrual' => 'Menstrual History',
    'sexual' => 'Sexual History',
    'obstetric' => 'Obstetric History',
    'growth' => 'Growth Milestone History'
];
?>

<?php if (!empty($grouped)): ?>
    <div class="row">
        <?php foreach ($grouped as $category => $records): 
            $icon = $historyTypeIcons[$category] ?? 'fa-folder';
            $label = $historyTypeLabels[$category] ?? ucfirst(str_replace('_', ' ', $category));
            $count = count($records);
        ?>
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="history-type-card text-center" 
                     data-history-type="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"
                     data-history-records="<?= htmlspecialchars(json_encode($records), ENT_QUOTES, 'UTF-8') ?>"
                     onclick="showHistoryTypeModalFromCard(this)">
                    <div class="history-type-icon">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="history-type-title">
                        <?= htmlspecialchars($label) ?>
                    </div>
                    <div class="history-type-count">
                        <?= $count ?> <?= $count == 1 ? 'Entry' : 'Entries' ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-history fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No Medical History Found</h5>
        <p class="text-muted">This patient doesn't have any medical history recorded yet.</p>
        <?php if (isAdmin() || isDoctor()): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalHistoryModal">
                <i class="fas fa-plus me-1"></i>Add First Medical History Entry
            </button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isAdmin() || isDoctor()): ?>
<!-- Modal for Add Medical History -->
<div class="modal fade" id="addMedicalHistoryModal" tabindex="-1" aria-labelledby="addMedicalHistoryLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="add_medical_history.php">
        <input type="hidden" name="patient_id" value="<?php echo $patient_details['id']; ?>">
        <input type="hidden" name="doctor_id" value="<?php echo $_SESSION['user_id']; ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="addMedicalHistoryLabel">
              <i class="fas fa-history me-2"></i>Add Medical History
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Select Medical History Types</label><br>
            <?php
              $types = [
                'allergies' => 'Allergies',
                'medications' => 'Medications',
                'past_history' => 'Past Medical History',
                'immunization' => 'Immunization/Vaccines',
                'procedures' => 'Procedures',
                'substance' => 'Substance Used',
                'family' => 'Family History',
                'menstrual' => 'Menstrual History',
                'sexual' => 'Sexual History',
                'obstetric' => 'Obstetric History',
                'growth' => 'Growth Milestone History'
              ];
              foreach ($types as $value => $label):
            ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="history_types[]" value="<?php echo $value; ?>" onchange="toggleChecklist('<?php echo $value; ?>')">
                <label class="form-check-label"><?php echo $label; ?></label>
              </div>
            <?php endforeach; ?>
          </div>

          <div id="checklists"></div>

          <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Recorded by:</strong> 
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
              <i class="fas fa-save me-1"></i>Save History Entry
          </button>
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Comprehensive medical history form builder
function toggleChecklist(type) {
  const container = document.getElementById('checklists');
  const existing = document.getElementById('checklist_' + type);

  if (existing) {
    existing.remove();
    return;
  }

  const wrapper = document.createElement('div');
  wrapper.id = 'checklist_' + type;
  wrapper.classList.add('mb-4', 'p-3', 'border', 'rounded', 'bg-light');
  
  let formHTML = '';

  switch(type) {
    case 'allergies':
      formHTML = buildAllergiesForm();
      break;
    case 'medications':
      formHTML = buildMedicationsForm();
      break;
    case 'past_history':
      formHTML = buildPastHistoryForm();
      break;
    case 'immunization':
      formHTML = buildImmunizationForm();
      break;
    case 'procedures':
      formHTML = buildProceduresForm();
      break;
    case 'substance':
      formHTML = buildSubstanceForm();
      break;
    case 'family':
      formHTML = buildFamilyHistoryForm();
      break;
    case 'menstrual':
      formHTML = buildMenstrualForm();
      break;
    case 'obstetric':
      formHTML = buildObstetricForm();
      break;
    case 'growth':
      formHTML = buildGrowthForm();
      break;
    case 'sexual':
      formHTML = buildSexualForm();
      break;
    default:
      formHTML = '<p class="text-muted">Form not available</p>';
  }

  wrapper.innerHTML = `<h6 class="mb-3"><i class="fas fa-${getIconForType(type)} me-2"></i>${getLabelForType(type)}</h6>` + formHTML;
  container.appendChild(wrapper);
}

function getIconForType(type) {
  const icons = {
    'allergies': 'exclamation-triangle',
    'medications': 'pills',
    'past_history': 'file-medical',
    'immunization': 'syringe',
    'procedures': 'procedures',
    'substance': 'smoking-ban',
    'family': 'users',
    'menstrual': 'calendar-alt',
    'sexual': 'heart',
    'obstetric': 'baby',
    'growth': 'chart-line'
  };
  return icons[type] || 'file';
}

function getLabelForType(type) {
  const labels = {
    'allergies': 'Allergies',
    'medications': 'Medications',
    'past_history': 'Past Medical History',
    'immunization': 'Immunization/Vaccines',
    'procedures': 'Procedures & Surgeries',
    'substance': 'Social/Substance Use History',
    'family': 'Family History',
    'menstrual': 'Menstrual History',
    'sexual': 'Sexual History',
    'obstetric': 'Obstetric & Gynecologic History',
    'growth': 'Growth & Development'
  };
  return labels[type] || type;
}

function buildAllergiesForm() {
  return `
    <div class="row">
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Food Allergies</label>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[food][]" value="Seafood (shrimp, crab, shellfish)" id="allergy_seafood">
              <label class="form-check-label" for="allergy_seafood">Seafood (shrimp, crab, shellfish) ⭐</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[food][]" value="Eggs" id="allergy_eggs">
              <label class="form-check-label" for="allergy_eggs">Eggs</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[food][]" value="Milk" id="allergy_milk">
              <label class="form-check-label" for="allergy_milk">Milk</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Drug Allergies</label>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[drug][]" value="Penicillin" id="allergy_penicillin">
              <label class="form-check-label" for="allergy_penicillin">Penicillin</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[drug][]" value="Sulfa drugs" id="allergy_sulfa">
              <label class="form-check-label" for="allergy_sulfa">Sulfa drugs</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[drug][]" value="NSAIDs (ibuprofen)" id="allergy_nsaids">
              <label class="form-check-label" for="allergy_nsaids">NSAIDs (e.g., ibuprofen)</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Environmental Allergies</label>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[environmental][]" value="Dust" id="allergy_dust">
              <label class="form-check-label" for="allergy_dust">Dust</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[environmental][]" value="Pollen" id="allergy_pollen">
              <label class="form-check-label" for="allergy_pollen">Pollen</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[environmental][]" value="Mold" id="allergy_mold">
              <label class="form-check-label" for="allergy_mold">Mold</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Other Allergies</label>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[other][]" value="Insect bites (bee, ant)" id="allergy_insect">
              <label class="form-check-label" for="allergy_insect">Insect bites (bee, ant)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="allergies[other][]" value="Latex" id="allergy_latex">
              <label class="form-check-label" for="allergy_latex">Latex</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Others (specify)</label>
        <input type="text" class="form-control" name="allergies[others_text]" placeholder="Specify other allergies">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Reaction Type / Symptoms</label>
        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Rash" id="reaction_rash">
            <label class="form-check-label" for="reaction_rash">Rash</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Anaphylaxis" id="reaction_anaphylaxis">
            <label class="form-check-label" for="reaction_anaphylaxis">Anaphylaxis</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Shortness of breath" id="reaction_sob">
            <label class="form-check-label" for="reaction_sob">Shortness of breath</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Hives" id="reaction_hives">
            <label class="form-check-label" for="reaction_hives">Hives</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Swelling" id="reaction_swelling">
            <label class="form-check-label" for="reaction_swelling">Swelling</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Itching" id="reaction_itching">
            <label class="form-check-label" for="reaction_itching">Itching</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Nausea" id="reaction_nausea">
            <label class="form-check-label" for="reaction_nausea">Nausea</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Vomiting" id="reaction_vomiting">
            <label class="form-check-label" for="reaction_vomiting">Vomiting</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Diarrhea" id="reaction_diarrhea">
            <label class="form-check-label" for="reaction_diarrhea">Diarrhea</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Dizziness" id="reaction_dizziness">
            <label class="form-check-label" for="reaction_dizziness">Dizziness</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allergies[reaction_type][]" value="Other" id="reaction_other">
            <label class="form-check-label" for="reaction_other">Other</label>
          </div>
        </div>
        <small class="text-muted">You can select one or more reaction types/symptoms</small>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Severity</label>
        <select class="form-select" name="allergies[severity]">
          <option value="">Select severity</option>
          <option value="Mild">Mild</option>
          <option value="Moderate">Moderate</option>
          <option value="Severe">Severe</option>
        </select>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="allergies[notes]" rows="2" placeholder="Additional information about allergies"></textarea>
      </div>
    </div>
  `;
}

function buildMedicationsForm() {
  return `
    <div id="medications-container">
      <div class="medication-entry mb-3 p-3 border rounded bg-white">
        <div class="row">
          <div class="col-md-6 mb-2">
            <label class="form-label">Medication Name (Generic Preferred)</label>
            <input type="text" class="form-control" name="medications[0][name]" placeholder="e.g., Amlodipine, Metformin" list="medication-suggestions">
            <datalist id="medication-suggestions">
              <option value="Amlodipine">
              <option value="Losartan">
              <option value="Metformin">
              <option value="Insulin">
              <option value="Salbutamol inhaler">
              <option value="Paracetamol">
              <option value="Ibuprofen">
            </datalist>
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label">Dosage</label>
            <input type="text" class="form-control" name="medications[0][dosage]" placeholder="e.g., 5mg, 500mg">
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label">Frequency</label>
            <input type="text" class="form-control" name="medications[0][frequency]" placeholder="e.g., Once daily, Twice daily">
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label">Purpose</label>
            <input type="text" class="form-control" name="medications[0][purpose]" placeholder="e.g., For hypertension, For diabetes">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">Prescribed By (Optional)</label>
            <input type="text" class="form-control" name="medications[0][prescribed_by]" placeholder="Doctor name">
          </div>
          <div class="col-md-2 mb-2 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="medications[0][maintenance]" value="1" id="med_maintenance_0">
              <label class="form-check-label" for="med_maintenance_0">Maintenance</label>
            </div>
          </div>
        </div>
      </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addMedicationEntry()">
      <i class="fas fa-plus me-1"></i>Add Another Medication
    </button>
  `;
}

function addMedicationEntry() {
  const container = document.getElementById('medications-container');
  const count = container.children.length;
  const entry = document.createElement('div');
  entry.className = 'medication-entry mb-3 p-3 border rounded bg-white';
  entry.innerHTML = `
    <div class="row">
      <div class="col-md-6 mb-2">
        <label class="form-label">Medication Name (Generic Preferred)</label>
        <input type="text" class="form-control" name="medications[${count}][name]" placeholder="e.g., Amlodipine, Metformin" list="medication-suggestions">
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label">Dosage</label>
        <input type="text" class="form-control" name="medications[${count}][dosage]" placeholder="e.g., 5mg, 500mg">
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label">Frequency</label>
        <input type="text" class="form-control" name="medications[${count}][frequency]" placeholder="e.g., Once daily, Twice daily">
      </div>
      <div class="col-md-6 mb-2">
        <label class="form-label">Purpose</label>
        <input type="text" class="form-control" name="medications[${count}][purpose]" placeholder="e.g., For hypertension, For diabetes">
      </div>
      <div class="col-md-4 mb-2">
        <label class="form-label">Prescribed By (Optional)</label>
        <input type="text" class="form-control" name="medications[${count}][prescribed_by]" placeholder="Doctor name">
      </div>
      <div class="col-md-2 mb-2 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="medications[${count}][maintenance]" value="1" id="med_maintenance_${count}">
          <label class="form-check-label" for="med_maintenance_${count}">Maintenance</label>
        </div>
        <button type="button" class="btn btn-sm btn-danger ms-2" onclick="this.closest('.medication-entry').remove()">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  `;
  container.appendChild(entry);
}

function buildPastHistoryForm() {
  return `
    <div class="row">
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Chronic Conditions</label>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Hypertension" id="ph_hypertension">
              <label class="form-check-label" for="ph_hypertension">Hypertension</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Diabetes Mellitus" id="ph_diabetes">
              <label class="form-check-label" for="ph_diabetes">Diabetes Mellitus</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Asthma" id="ph_asthma">
              <label class="form-check-label" for="ph_asthma">Asthma</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Heart Disease" id="ph_heart">
              <label class="form-check-label" for="ph_heart">Heart Disease</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Stroke" id="ph_stroke">
              <label class="form-check-label" for="ph_stroke">Stroke</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Chronic Kidney Disease" id="ph_ckd">
              <label class="form-check-label" for="ph_ckd">Chronic Kidney Disease</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Hepatitis B / C" id="ph_hepatitis">
              <label class="form-check-label" for="ph_hepatitis">Hepatitis B / C</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Peptic Ulcer Disease" id="ph_ulcer">
              <label class="form-check-label" for="ph_ulcer">Peptic Ulcer Disease</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Seizure Disorder / Epilepsy" id="ph_seizure">
              <label class="form-check-label" for="ph_seizure">Seizure Disorder / Epilepsy</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Tuberculosis (PTB / EPTB)" id="ph_tb">
              <label class="form-check-label" for="ph_tb">Tuberculosis (PTB / EPTB) ⭐</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Pneumonia" id="ph_pneumonia">
              <label class="form-check-label" for="ph_pneumonia">Pneumonia</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Dengue" id="ph_dengue">
              <label class="form-check-label" for="ph_dengue">Dengue</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="COVID-19" id="ph_covid">
              <label class="form-check-label" for="ph_covid">COVID-19</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Cancer" id="ph_cancer">
              <label class="form-check-label" for="ph_cancer">Cancer (specify type)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Depression" id="ph_depression">
              <label class="form-check-label" for="ph_depression">Depression</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Anxiety" id="ph_anxiety">
              <label class="form-check-label" for="ph_anxiety">Anxiety</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="past_history[conditions][]" value="Schizophrenia" id="ph_schizophrenia">
              <label class="form-check-label" for="ph_schizophrenia">Schizophrenia</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Others (specify)</label>
        <input type="text" class="form-control" name="past_history[others_text]" placeholder="Specify other conditions">
      </div>
      
      <div class="col-md-4 mb-3">
        <label class="form-label">Year Diagnosed</label>
        <input type="number" class="form-control" name="past_history[year_diagnosed]" placeholder="e.g., 2020" min="1900" max="2099">
      </div>
      
      <div class="col-md-4 mb-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="past_history[status]">
          <option value="">Select status</option>
          <option value="Ongoing">Ongoing</option>
          <option value="Resolved">Resolved</option>
        </select>
      </div>
      
      <div class="col-md-4 mb-3">
        <label class="form-label">Hospitalized?</label>
        <select class="form-select" name="past_history[hospitalized]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="past_history[notes]" rows="2" placeholder="Additional information"></textarea>
      </div>
    </div>
  `;
}

function buildImmunizationForm() {
  return `
    <div class="row">
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Children Vaccines (EPI)</label>
        <div class="row">
          <div class="col-md-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[children][]" value="BCG" id="imm_bcg">
              <label class="form-check-label" for="imm_bcg">BCG</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[children][]" value="Hepatitis B" id="imm_hepb">
              <label class="form-check-label" for="imm_hepb">Hepatitis B</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[children][]" value="Pentavalent (DPT-HepB-Hib)" id="imm_penta">
              <label class="form-check-label" for="imm_penta">Pentavalent (DPT-HepB-Hib)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[children][]" value="OPV / IPV" id="imm_opv">
              <label class="form-check-label" for="imm_opv">OPV / IPV</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[children][]" value="Measles / MMR" id="imm_measles">
              <label class="form-check-label" for="imm_measles">Measles / MMR</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Adult Vaccines</label>
        <div class="row">
          <div class="col-md-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[adults][]" value="COVID-19" id="imm_covid">
              <label class="form-check-label" for="imm_covid">COVID-19</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[adults][]" value="Influenza" id="imm_flu">
              <label class="form-check-label" for="imm_flu">Influenza</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[adults][]" value="Tetanus toxoid" id="imm_tetanus">
              <label class="form-check-label" for="imm_tetanus">Tetanus toxoid</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="immunization[adults][]" value="Hepatitis B" id="imm_hepb_adult">
              <label class="form-check-label" for="imm_hepb_adult">Hepatitis B</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">COVID-19 Brand (if applicable)</label>
        <input type="text" class="form-control" name="immunization[covid_brand]" placeholder="e.g., Pfizer, Moderna, Sinovac">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">COVID-19 Doses</label>
        <input type="number" class="form-control" name="immunization[covid_doses]" placeholder="Number of doses" min="0" max="5">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Date Last Dose</label>
        <input type="date" class="form-control" name="immunization[last_dose_date]">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="immunization[status]">
          <option value="">Select status</option>
          <option value="Completed">Completed</option>
          <option value="Incomplete">Incomplete</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">With Vaccination Card?</label>
        <select class="form-select" name="immunization[has_card]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="immunization[notes]" rows="2" placeholder="Additional information"></textarea>
      </div>
    </div>
  `;
}

function buildProceduresForm() {
  return `
    <div id="procedures-container">
      <div class="procedure-entry mb-3 p-3 border rounded bg-white">
        <div class="row">
          <div class="col-md-6 mb-2">
            <label class="form-label">Procedure Name</label>
            <input type="text" class="form-control" name="procedures[0][name]" placeholder="Procedure name" list="procedure-suggestions">
            <datalist id="procedure-suggestions">
              <option value="Appendectomy">
              <option value="Cesarean section">
              <option value="Cholecystectomy">
              <option value="Hernia repair">
              <option value="Cataract surgery">
              <option value="Endoscopy">
              <option value="MRI Scan">
              <option value="Blood Transfusion">
            </datalist>
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label">Year</label>
            <input type="number" class="form-control" name="procedures[0][year]" placeholder="e.g., 2020" min="1900" max="2099">
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label">Hospital</label>
            <input type="text" class="form-control" name="procedures[0][hospital]" placeholder="Hospital name">
          </div>
        </div>
      </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProcedureEntry()">
      <i class="fas fa-plus me-1"></i>Add Another Procedure
    </button>
  `;
}

function addProcedureEntry() {
  const container = document.getElementById('procedures-container');
  const count = container.children.length;
  const entry = document.createElement('div');
  entry.className = 'procedure-entry mb-3 p-3 border rounded bg-white';
  entry.innerHTML = `
    <div class="row">
      <div class="col-md-6 mb-2">
        <label class="form-label">Procedure Name</label>
        <input type="text" class="form-control" name="procedures[${count}][name]" placeholder="Procedure name" list="procedure-suggestions">
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label">Year</label>
        <input type="number" class="form-control" name="procedures[${count}][year]" placeholder="e.g., 2020" min="1900" max="2099">
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label">Hospital</label>
        <input type="text" class="form-control" name="procedures[${count}][hospital]" placeholder="Hospital name">
        <button type="button" class="btn btn-sm btn-danger mt-2" onclick="this.closest('.procedure-entry').remove()">
          <i class="fas fa-times me-1"></i>Remove
        </button>
      </div>
    </div>
  `;
  container.appendChild(entry);
}

function buildSubstanceForm() {
  return `
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Smoking</label>
        <select class="form-select" name="substance[smoking_status]">
          <option value="">Select</option>
          <option value="Never">Never</option>
          <option value="Former">Former</option>
          <option value="Current">Current</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Packs per Year</label>
        <input type="number" class="form-control" name="substance[smoking_packs_year]" placeholder="e.g., 10" min="0" step="0.1">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Alcohol</label>
        <select class="form-select" name="substance[alcohol_status]">
          <option value="">Select</option>
          <option value="None">None</option>
          <option value="Occasional">Occasional</option>
          <option value="Regular">Regular</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Alcohol Type</label>
        <input type="text" class="form-control" name="substance[alcohol_type]" placeholder="e.g., Beer, Hard liquor">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Vaping</label>
        <select class="form-select" name="substance[vaping]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Illicit Drugs</label>
        <input type="text" class="form-control" name="substance[illicit_drugs]" placeholder="Specify if applicable">
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="substance[notes]" rows="2" placeholder="Additional information"></textarea>
      </div>
    </div>
  `;
}

function buildFamilyHistoryForm() {
  return `
    <div class="row">
      <div class="col-md-12 mb-3">
        <label class="form-label fw-bold">Family History Conditions</label>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="Hypertension" id="fam_hypertension">
              <label class="form-check-label" for="fam_hypertension">Hypertension</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="Diabetes" id="fam_diabetes">
              <label class="form-check-label" for="fam_diabetes">Diabetes</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="Heart Disease" id="fam_heart">
              <label class="form-check-label" for="fam_heart">Heart Disease</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="Stroke" id="fam_stroke">
              <label class="form-check-label" for="fam_stroke">Stroke</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="Cancer" id="fam_cancer">
              <label class="form-check-label" for="fam_cancer">Cancer</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="Asthma" id="fam_asthma">
              <label class="form-check-label" for="fam_asthma">Asthma</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="TB" id="fam_tb">
              <label class="form-check-label" for="fam_tb">TB</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="family[conditions][]" value="Kidney Disease" id="fam_kidney">
              <label class="form-check-label" for="fam_kidney">Kidney Disease</label>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Relationship</label>
        <select class="form-select" name="family[relationship]">
          <option value="">Select relationship</option>
          <option value="Mother">Mother</option>
          <option value="Father">Father</option>
          <option value="Sibling">Sibling</option>
          <option value="Grandmother">Grandmother</option>
          <option value="Grandfather">Grandfather</option>
          <option value="Aunt">Aunt</option>
          <option value="Uncle">Uncle</option>
          <option value="Other">Other</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="family[status]">
          <option value="">Select status</option>
          <option value="Alive">Alive</option>
          <option value="Deceased">Deceased</option>
        </select>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Cause of Death (if applicable)</label>
        <input type="text" class="form-control" name="family[cause_of_death]" placeholder="Specify cause of death">
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="family[notes]" rows="2" placeholder="Additional information"></textarea>
      </div>
    </div>
  `;
}

function buildMenstrualForm() {
  return `
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Age of Menarche</label>
        <input type="number" class="form-control" name="menstrual[menarche_age]" placeholder="e.g., 12" min="8" max="20">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">LMP (Last Menstrual Period)</label>
        <input type="date" class="form-control" name="menstrual[lmp]">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Cycle Regularity</label>
        <select class="form-select" name="menstrual[regularity]">
          <option value="">Select</option>
          <option value="Regular">Regular</option>
          <option value="Irregular">Irregular</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Duration (days)</label>
        <input type="number" class="form-control" name="menstrual[duration]" placeholder="e.g., 5" min="1" max="15">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Dysmenorrhea</label>
        <select class="form-select" name="menstrual[dysmenorrhea]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="menstrual[notes]" rows="2" placeholder="Additional information"></textarea>
      </div>
    </div>
  `;
}

function buildObstetricForm() {
  return `
    <div class="row">
      <div class="col-md-3 mb-3">
        <label class="form-label">Gravida (G)</label>
        <input type="number" class="form-control" name="obstetric[gravida]" placeholder="Number of pregnancies" min="0">
      </div>
      
      <div class="col-md-3 mb-3">
        <label class="form-label">Para (P)</label>
        <input type="number" class="form-control" name="obstetric[para]" placeholder="Number of births" min="0">
      </div>
      
      <div class="col-md-3 mb-3">
        <label class="form-label">Normal Deliveries</label>
        <input type="number" class="form-control" name="obstetric[normal_deliveries]" placeholder="Number" min="0">
      </div>
      
      <div class="col-md-3 mb-3">
        <label class="form-label">Cesarean Sections</label>
        <input type="number" class="form-control" name="obstetric[cs]" placeholder="Number" min="0">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">History of Miscarriage</label>
        <select class="form-select" name="obstetric[miscarriage]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Last Delivery Date</label>
        <input type="date" class="form-control" name="obstetric[last_delivery_date]">
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Prenatal Complications</label>
        <textarea class="form-control" name="obstetric[prenatal_complications]" rows="2" placeholder="Specify any complications"></textarea>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="obstetric[notes]" rows="2" placeholder="Additional information"></textarea>
      </div>
    </div>
  `;
}

function buildGrowthForm() {
  return `
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Birth History</label>
        <select class="form-select" name="growth[birth_history]">
          <option value="">Select</option>
          <option value="NSD">NSD (Normal Spontaneous Delivery)</option>
          <option value="CS">CS (Cesarean Section)</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Birth Weight (kg)</label>
        <input type="number" class="form-control" name="growth[birth_weight]" placeholder="e.g., 3.2" min="0" step="0.1">
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Developmental Milestones</label>
        <select class="form-select" name="growth[milestones]">
          <option value="">Select</option>
          <option value="Normal">Normal</option>
          <option value="Delayed">Delayed</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Feeding History</label>
        <input type="text" class="form-control" name="growth[feeding]" placeholder="e.g., Breastfed, Formula, Mixed">
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="growth[notes]" rows="2" placeholder="Additional information about growth and development"></textarea>
      </div>
    </div>
  `;
}

function buildSexualForm() {
  return `
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Sexually Active</label>
        <select class="form-select" name="sexual[active]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">Multiple Partners</label>
        <select class="form-select" name="sexual[multiple_partners]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-6 mb-3">
        <label class="form-label">STI History</label>
        <select class="form-select" name="sexual[sti_history]">
          <option value="">Select</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>
      
      <div class="col-md-12 mb-3">
        <label class="form-label">Additional Notes</label>
        <textarea class="form-control" name="sexual[notes]" rows="2" placeholder="Additional information"></textarea>
      </div>
    </div>
  `;
}
</script>
<?php endif; ?>

<?php
// Include the view medical history modal (combined file)
include 'view_medical_history_modal.php';
?> 