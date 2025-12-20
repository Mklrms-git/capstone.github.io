<?php
define('MHAVIS_EXEC', true);
session_start();
require_once 'config/init.php';
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
    
    if (!$conn->query($createTable)) {
        error_log("Failed to create medical_history table: " . $conn->error);
        header("Location: patients.php?patient_id=" . (isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : '') . "&tab=medical_history&error=Database error. Please contact administrator.");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $doctorId = $_SESSION['user_id'];

    if (!isset($_POST['history_types']) || empty($_POST['history_types'])) {
        header("Location: patients.php?patient_id=$patientId&tab=medical_history&error=No history type selected.");
        exit;
    }

    // Check if columns exist
    $checkColumn = $conn->query("SHOW COLUMNS FROM `medical_history` LIKE 'created_by'");
    $hasCreatedBy = $checkColumn && $checkColumn->num_rows > 0;
    
    $checkStructured = $conn->query("SHOW COLUMNS FROM `medical_history` LIKE 'structured_data'");
    $hasStructured = $checkStructured && $checkStructured->num_rows > 0;
    
    // Add structured_data column if it doesn't exist
    if (!$hasStructured) {
        $conn->query("ALTER TABLE `medical_history` ADD COLUMN `structured_data` JSON NULL AFTER `history_details`");
        $hasStructured = true;
    }
    
    $createdBy = $_SESSION['user_id'];

    foreach ($_POST['history_types'] as $type) {
        $type = trim($type);
        
        // Process structured data based on type
        $structuredData = [];
        $textDetails = '';
        
        switch($type) {
            case 'allergies':
                $structuredData = processAllergiesData($_POST);
                $textDetails = formatAllergiesText($structuredData);
                break;
            case 'medications':
                $structuredData = processMedicationsData($_POST);
                $textDetails = formatMedicationsText($structuredData);
                break;
            case 'past_history':
                $structuredData = processPastHistoryData($_POST);
                $textDetails = formatPastHistoryText($structuredData);
                break;
            case 'immunization':
                $structuredData = processImmunizationData($_POST);
                $textDetails = formatImmunizationText($structuredData);
                break;
            case 'procedures':
                $structuredData = processProceduresData($_POST);
                $textDetails = formatProceduresText($structuredData);
                break;
            case 'substance':
                $structuredData = processSubstanceData($_POST);
                $textDetails = formatSubstanceText($structuredData);
                break;
            case 'family':
                $structuredData = processFamilyData($_POST);
                $textDetails = formatFamilyText($structuredData);
                break;
            case 'menstrual':
                $structuredData = processMenstrualData($_POST);
                $textDetails = formatMenstrualText($structuredData);
                break;
            case 'obstetric':
                $structuredData = processObstetricData($_POST);
                $textDetails = formatObstetricText($structuredData);
                break;
            case 'growth':
                $structuredData = processGrowthData($_POST);
                $textDetails = formatGrowthText($structuredData);
                break;
            case 'sexual':
                $structuredData = processSexualData($_POST);
                $textDetails = formatSexualText($structuredData);
                break;
        }
        
        $structuredJson = !empty($structuredData) ? json_encode($structuredData, JSON_UNESCAPED_UNICODE) : null;
        
        if ($hasCreatedBy && $hasStructured) {
            $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, doctor_id, history_type, history_details, structured_data, status, created_by, created_at) 
                                    VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())");
            $stmt->bind_param("iisssi", $patientId, $doctorId, $type, $textDetails, $structuredJson, $createdBy);
        } elseif ($hasCreatedBy) {
            $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, doctor_id, history_type, history_details, status, created_by, created_at) 
                                    VALUES (?, ?, ?, ?, 'active', ?, NOW())");
            $stmt->bind_param("iissi", $patientId, $doctorId, $type, $textDetails, $createdBy);
        } elseif ($hasStructured) {
            $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, doctor_id, history_type, history_details, structured_data, status, created_at) 
                                    VALUES (?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("iiss", $patientId, $doctorId, $type, $textDetails, $structuredJson);
        } else {
            $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, doctor_id, history_type, history_details, status, created_at) 
                                    VALUES (?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("iiss", $patientId, $doctorId, $type, $textDetails);
        }
        
        if (!$stmt->execute()) {
            error_log("SQL Execute failed in add_medical_history.php: " . $stmt->error);
        }
        $stmt->close();
    }

    header("Location: patients.php?patient_id=$patientId&tab=medical_history&message=Medical history entry(ies) added successfully");
    exit;
}

// Helper functions to process structured data
function processAllergiesData($post) {
    $data = [];
    if (isset($post['allergies'])) {
        $allergies = $post['allergies'];
        $data['food'] = isset($allergies['food']) ? $allergies['food'] : [];
        $data['drug'] = isset($allergies['drug']) ? $allergies['drug'] : [];
        $data['environmental'] = isset($allergies['environmental']) ? $allergies['environmental'] : [];
        $data['other'] = isset($allergies['other']) ? $allergies['other'] : [];
        $data['others_text'] = isset($allergies['others_text']) ? trim($allergies['others_text']) : '';
        $data['reaction_type'] = isset($allergies['reaction_type']) && is_array($allergies['reaction_type']) ? $allergies['reaction_type'] : (isset($allergies['reaction_type']) ? [trim($allergies['reaction_type'])] : []);
        $data['severity'] = isset($allergies['severity']) ? trim($allergies['severity']) : '';
        $data['notes'] = isset($allergies['notes']) ? trim($allergies['notes']) : '';
    }
    return $data;
}

function formatAllergiesText($data) {
    $parts = [];
    if (!empty($data['food'])) $parts[] = 'Food: ' . implode(', ', $data['food']);
    if (!empty($data['drug'])) $parts[] = 'Drug: ' . implode(', ', $data['drug']);
    if (!empty($data['environmental'])) $parts[] = 'Environmental: ' . implode(', ', $data['environmental']);
    if (!empty($data['other'])) $parts[] = 'Other: ' . implode(', ', $data['other']);
    if (!empty($data['others_text'])) $parts[] = 'Others: ' . $data['others_text'];
    if (!empty($data['reaction_type']) && is_array($data['reaction_type'])) {
        $parts[] = 'Reaction: ' . implode(', ', $data['reaction_type']);
    } elseif (!empty($data['reaction_type'])) {
        $parts[] = 'Reaction: ' . $data['reaction_type'];
    }
    if (!empty($data['severity'])) $parts[] = 'Severity: ' . $data['severity'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processMedicationsData($post) {
    $data = [];
    if (isset($post['medications']) && is_array($post['medications'])) {
        foreach ($post['medications'] as $med) {
            if (!empty($med['name'])) {
                $data[] = [
                    'name' => trim($med['name']),
                    'dosage' => isset($med['dosage']) ? trim($med['dosage']) : '',
                    'frequency' => isset($med['frequency']) ? trim($med['frequency']) : '',
                    'purpose' => isset($med['purpose']) ? trim($med['purpose']) : '',
                    'prescribed_by' => isset($med['prescribed_by']) ? trim($med['prescribed_by']) : '',
                    'maintenance' => isset($med['maintenance']) && $med['maintenance'] == '1'
                ];
            }
        }
    }
    return $data;
}

function formatMedicationsText($data) {
    $parts = [];
    foreach ($data as $med) {
        $medStr = $med['name'];
        if (!empty($med['dosage'])) $medStr .= ' (' . $med['dosage'] . ')';
        if (!empty($med['frequency'])) $medStr .= ' - ' . $med['frequency'];
        if (!empty($med['purpose'])) $medStr .= ' - ' . $med['purpose'];
        if ($med['maintenance']) $medStr .= ' [Maintenance]';
        $parts[] = $medStr;
    }
    return implode('; ', $parts);
}

function processPastHistoryData($post) {
    $data = [];
    if (isset($post['past_history'])) {
        $ph = $post['past_history'];
        $data['conditions'] = isset($ph['conditions']) ? $ph['conditions'] : [];
        $data['others_text'] = isset($ph['others_text']) ? trim($ph['others_text']) : '';
        $data['year_diagnosed'] = isset($ph['year_diagnosed']) ? trim($ph['year_diagnosed']) : '';
        $data['status'] = isset($ph['status']) ? trim($ph['status']) : '';
        $data['hospitalized'] = isset($ph['hospitalized']) ? trim($ph['hospitalized']) : '';
        $data['notes'] = isset($ph['notes']) ? trim($ph['notes']) : '';
    }
    return $data;
}

function formatPastHistoryText($data) {
    $parts = [];
    if (!empty($data['conditions'])) $parts[] = 'Conditions: ' . implode(', ', $data['conditions']);
    if (!empty($data['others_text'])) $parts[] = 'Others: ' . $data['others_text'];
    if (!empty($data['year_diagnosed'])) $parts[] = 'Year: ' . $data['year_diagnosed'];
    if (!empty($data['status'])) $parts[] = 'Status: ' . $data['status'];
    if (!empty($data['hospitalized'])) $parts[] = 'Hospitalized: ' . $data['hospitalized'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processImmunizationData($post) {
    $data = [];
    if (isset($post['immunization'])) {
        $imm = $post['immunization'];
        $data['children'] = isset($imm['children']) ? $imm['children'] : [];
        $data['adults'] = isset($imm['adults']) ? $imm['adults'] : [];
        $data['covid_brand'] = isset($imm['covid_brand']) ? trim($imm['covid_brand']) : '';
        $data['covid_doses'] = isset($imm['covid_doses']) ? trim($imm['covid_doses']) : '';
        $data['last_dose_date'] = isset($imm['last_dose_date']) ? trim($imm['last_dose_date']) : '';
        $data['status'] = isset($imm['status']) ? trim($imm['status']) : '';
        $data['has_card'] = isset($imm['has_card']) ? trim($imm['has_card']) : '';
        $data['notes'] = isset($imm['notes']) ? trim($imm['notes']) : '';
    }
    return $data;
}

function formatImmunizationText($data) {
    $parts = [];
    if (!empty($data['children'])) $parts[] = 'Children: ' . implode(', ', $data['children']);
    if (!empty($data['adults'])) $parts[] = 'Adults: ' . implode(', ', $data['adults']);
    if (!empty($data['covid_brand'])) $parts[] = 'COVID-19 Brand: ' . $data['covid_brand'];
    if (!empty($data['covid_doses'])) $parts[] = 'COVID-19 Doses: ' . $data['covid_doses'];
    if (!empty($data['last_dose_date'])) $parts[] = 'Last Dose: ' . $data['last_dose_date'];
    if (!empty($data['status'])) $parts[] = 'Status: ' . $data['status'];
    if (!empty($data['has_card'])) $parts[] = 'Has Card: ' . $data['has_card'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processProceduresData($post) {
    $data = [];
    if (isset($post['procedures']) && is_array($post['procedures'])) {
        foreach ($post['procedures'] as $proc) {
            if (!empty($proc['name'])) {
                $data[] = [
                    'name' => trim($proc['name']),
                    'year' => isset($proc['year']) ? trim($proc['year']) : '',
                    'hospital' => isset($proc['hospital']) ? trim($proc['hospital']) : ''
                ];
            }
        }
    }
    return $data;
}

function formatProceduresText($data) {
    $parts = [];
    foreach ($data as $proc) {
        $procStr = $proc['name'];
        if (!empty($proc['year'])) $procStr .= ' (' . $proc['year'] . ')';
        if (!empty($proc['hospital'])) $procStr .= ' - ' . $proc['hospital'];
        $parts[] = $procStr;
    }
    return implode('; ', $parts);
}

function processSubstanceData($post) {
    $data = [];
    if (isset($post['substance'])) {
        $sub = $post['substance'];
        $data['smoking_status'] = isset($sub['smoking_status']) ? trim($sub['smoking_status']) : '';
        $data['smoking_packs_year'] = isset($sub['smoking_packs_year']) ? trim($sub['smoking_packs_year']) : '';
        $data['alcohol_status'] = isset($sub['alcohol_status']) ? trim($sub['alcohol_status']) : '';
        $data['alcohol_type'] = isset($sub['alcohol_type']) ? trim($sub['alcohol_type']) : '';
        $data['vaping'] = isset($sub['vaping']) ? trim($sub['vaping']) : '';
        $data['illicit_drugs'] = isset($sub['illicit_drugs']) ? trim($sub['illicit_drugs']) : '';
        $data['notes'] = isset($sub['notes']) ? trim($sub['notes']) : '';
    }
    return $data;
}

function formatSubstanceText($data) {
    $parts = [];
    if (!empty($data['smoking_status'])) $parts[] = 'Smoking: ' . $data['smoking_status'];
    if (!empty($data['smoking_packs_year'])) $parts[] = 'Packs/Year: ' . $data['smoking_packs_year'];
    if (!empty($data['alcohol_status'])) $parts[] = 'Alcohol: ' . $data['alcohol_status'];
    if (!empty($data['alcohol_type'])) $parts[] = 'Alcohol Type: ' . $data['alcohol_type'];
    if (!empty($data['vaping'])) $parts[] = 'Vaping: ' . $data['vaping'];
    if (!empty($data['illicit_drugs'])) $parts[] = 'Illicit Drugs: ' . $data['illicit_drugs'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processFamilyData($post) {
    $data = [];
    if (isset($post['family'])) {
        $fam = $post['family'];
        $data['conditions'] = isset($fam['conditions']) ? $fam['conditions'] : [];
        $data['relationship'] = isset($fam['relationship']) ? trim($fam['relationship']) : '';
        $data['status'] = isset($fam['status']) ? trim($fam['status']) : '';
        $data['cause_of_death'] = isset($fam['cause_of_death']) ? trim($fam['cause_of_death']) : '';
        $data['notes'] = isset($fam['notes']) ? trim($fam['notes']) : '';
    }
    return $data;
}

function formatFamilyText($data) {
    $parts = [];
    if (!empty($data['conditions'])) $parts[] = 'Conditions: ' . implode(', ', $data['conditions']);
    if (!empty($data['relationship'])) $parts[] = 'Relationship: ' . $data['relationship'];
    if (!empty($data['status'])) $parts[] = 'Status: ' . $data['status'];
    if (!empty($data['cause_of_death'])) $parts[] = 'Cause of Death: ' . $data['cause_of_death'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processMenstrualData($post) {
    $data = [];
    if (isset($post['menstrual'])) {
        $men = $post['menstrual'];
        $data['menarche_age'] = isset($men['menarche_age']) ? trim($men['menarche_age']) : '';
        $data['lmp'] = isset($men['lmp']) ? trim($men['lmp']) : '';
        $data['regularity'] = isset($men['regularity']) ? trim($men['regularity']) : '';
        $data['duration'] = isset($men['duration']) ? trim($men['duration']) : '';
        $data['dysmenorrhea'] = isset($men['dysmenorrhea']) ? trim($men['dysmenorrhea']) : '';
        $data['notes'] = isset($men['notes']) ? trim($men['notes']) : '';
    }
    return $data;
}

function formatMenstrualText($data) {
    $parts = [];
    if (!empty($data['menarche_age'])) $parts[] = 'Menarche Age: ' . $data['menarche_age'];
    if (!empty($data['lmp'])) $parts[] = 'LMP: ' . $data['lmp'];
    if (!empty($data['regularity'])) $parts[] = 'Regularity: ' . $data['regularity'];
    if (!empty($data['duration'])) $parts[] = 'Duration: ' . $data['duration'] . ' days';
    if (!empty($data['dysmenorrhea'])) $parts[] = 'Dysmenorrhea: ' . $data['dysmenorrhea'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processObstetricData($post) {
    $data = [];
    if (isset($post['obstetric'])) {
        $obs = $post['obstetric'];
        $data['gravida'] = isset($obs['gravida']) ? trim($obs['gravida']) : '';
        $data['para'] = isset($obs['para']) ? trim($obs['para']) : '';
        $data['normal_deliveries'] = isset($obs['normal_deliveries']) ? trim($obs['normal_deliveries']) : '';
        $data['cs'] = isset($obs['cs']) ? trim($obs['cs']) : '';
        $data['miscarriage'] = isset($obs['miscarriage']) ? trim($obs['miscarriage']) : '';
        $data['last_delivery_date'] = isset($obs['last_delivery_date']) ? trim($obs['last_delivery_date']) : '';
        $data['prenatal_complications'] = isset($obs['prenatal_complications']) ? trim($obs['prenatal_complications']) : '';
        $data['notes'] = isset($obs['notes']) ? trim($obs['notes']) : '';
    }
    return $data;
}

function formatObstetricText($data) {
    $parts = [];
    if (!empty($data['gravida'])) $parts[] = 'G: ' . $data['gravida'];
    if (!empty($data['para'])) $parts[] = 'P: ' . $data['para'];
    if (!empty($data['normal_deliveries'])) $parts[] = 'Normal Deliveries: ' . $data['normal_deliveries'];
    if (!empty($data['cs'])) $parts[] = 'CS: ' . $data['cs'];
    if (!empty($data['miscarriage'])) $parts[] = 'Miscarriage: ' . $data['miscarriage'];
    if (!empty($data['last_delivery_date'])) $parts[] = 'Last Delivery: ' . $data['last_delivery_date'];
    if (!empty($data['prenatal_complications'])) $parts[] = 'Complications: ' . $data['prenatal_complications'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processGrowthData($post) {
    $data = [];
    if (isset($post['growth'])) {
        $gr = $post['growth'];
        $data['birth_history'] = isset($gr['birth_history']) ? trim($gr['birth_history']) : '';
        $data['birth_weight'] = isset($gr['birth_weight']) ? trim($gr['birth_weight']) : '';
        $data['milestones'] = isset($gr['milestones']) ? trim($gr['milestones']) : '';
        $data['feeding'] = isset($gr['feeding']) ? trim($gr['feeding']) : '';
        $data['notes'] = isset($gr['notes']) ? trim($gr['notes']) : '';
    }
    return $data;
}

function formatGrowthText($data) {
    $parts = [];
    if (!empty($data['birth_history'])) $parts[] = 'Birth: ' . $data['birth_history'];
    if (!empty($data['birth_weight'])) $parts[] = 'Birth Weight: ' . $data['birth_weight'] . ' kg';
    if (!empty($data['milestones'])) $parts[] = 'Milestones: ' . $data['milestones'];
    if (!empty($data['feeding'])) $parts[] = 'Feeding: ' . $data['feeding'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}

function processSexualData($post) {
    $data = [];
    if (isset($post['sexual'])) {
        $sex = $post['sexual'];
        $data['active'] = isset($sex['active']) ? trim($sex['active']) : '';
        $data['multiple_partners'] = isset($sex['multiple_partners']) ? trim($sex['multiple_partners']) : '';
        $data['sti_history'] = isset($sex['sti_history']) ? trim($sex['sti_history']) : '';
        $data['notes'] = isset($sex['notes']) ? trim($sex['notes']) : '';
    }
    return $data;
}

function formatSexualText($data) {
    $parts = [];
    if (!empty($data['active'])) $parts[] = 'Sexually Active: ' . $data['active'];
    if (!empty($data['multiple_partners'])) $parts[] = 'Multiple Partners: ' . $data['multiple_partners'];
    if (!empty($data['sti_history'])) $parts[] = 'STI History: ' . $data['sti_history'];
    if (!empty($data['notes'])) $parts[] = 'Notes: ' . $data['notes'];
    return implode(' | ', $parts);
}
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <h4>Add Medical History</h4>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">

        <div class="mb-3">
            <label for="history_type" class="form-label">Medical History Type</label>
            <select id="history_type" name="history_type" class="form-select" onchange="showChecklist(this.value)" required>
                <option value="">Select type</option>
                <option value="allergies">Allergies</option>
                <option value="medications">Medications</option>
                <option value="past_history">Past Medical History</option>
                <option value="immunization">Immunization/Vaccines</option>
                <option value="procedures">Procedures</option>
                <option value="substance">Substance Used</option>
                <option value="family">Family History</option>
                <option value="menstrual">Menstrual History</option>
                <option value="sexual">Sexual History</option>
                <option value="obstetric">Obstetric History</option>
                <option value="growth">Growth Milestone History</option>
            </select>
        </div>

        <div id="history_checklist" class="mb-3"></div>

        <button type="submit" class="btn btn-primary">Add History Entry</button>
        <a href="patients.php?id=<?= htmlspecialchars($patient_id) ?>" class="btn btn-danger">Cancel</a>
    </form>
</div>

<script>
function showChecklist(type) {
    const checklistContainer = document.getElementById('history_checklist');
    checklistContainer.innerHTML = '';

    let options = [];
    switch(type) {
        case 'family':
            options = ['Hypertension', 'Diabetes', 'Cancer', 'Heart Disease', 'Asthma']; break;
        case 'allergies':
            options = ['Pollen', 'Peanuts', 'Dust Mites', 'Latex', 'Medication']; break;
        case 'medications':
            options = ['Paracetamol', 'Ibuprofen', 'Amoxicillin', 'Metformin']; break;
        case 'past_history':
            options = ['Asthma', 'Tuberculosis', 'Chickenpox', 'Surgery History']; break;
        case 'immunization':
            options = ['MMR', 'Hepatitis B', 'COVID-19', 'Tetanus', 'HPV']; break;
        case 'procedures':
            options = ['Surgery', 'Endoscopy', 'MRI Scan', 'Blood Transfusion']; break;
        case 'substance':
            options = ['Alcohol', 'Cigarettes', 'Illegal Drugs']; break;
        case 'menstrual':
            options = ['Regular', 'Irregular', 'Painful', 'Heavy Bleeding']; break;
        case 'sexual':
            options = ['Sexually Active', 'Multiple Partners', 'STI History']; break;
        case 'obstetric':
            options = ['Gravida', 'Para', 'Miscarriages', 'C-Section']; break;
        case 'growth':
            options = ['Delayed Walking', 'Delayed Speech', 'Underweight', 'Overweight']; break;
    }

    if (options.length > 0) {
        const html = options.map(item => `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="history_items[]" value="${item}" id="${item}">
                <label class="form-check-label" for="${item}">${item}</label>
            </div>
        `).join('');
        checklistContainer.innerHTML = `<label class="form-label">Details</label>` + html + `
            <div class="mt-2">
                <label for="${type}_others" class="form-label">Others:</label>
                <input type="text" class="form-control" id="${type}_others" name="history_items_others[${type}]" placeholder="Type custom history if not in the choices above">
            </div>
        `;
    }
}
</script>

<?php include 'includes/footer.php'; ?> 