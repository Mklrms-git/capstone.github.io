<?php
// Patient Medical Records Tab Content
// File: includes/patient_medical_records.php

if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Handle deletion - Return JSON to avoid header issues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record_id'])) {
    // Set JSON header early
    header('Content-Type: application/json');
    
    // Admin and Doctor can delete medical records (with password verification)
    $patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : (isset($patient_details) && isset($patient_details['id']) ? (int)$patient_details['id'] : null);
    
    // Check if user is admin or doctor
    if (!isAdmin() && !isDoctor()) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access',
            'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
        ]);
        exit;
    }
    
    // Verify password if provided
    $password = $_POST['delete_password'] ?? '';
    if (empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password verification required',
            'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
        ]);
        exit;
    }
    
    // Verify password
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated',
            'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
        ]);
        exit;
    }
    
    // Get user's password from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
            'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Incorrect password',
            'redirect' => $patient_id ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" : "patients.php"
        ]);
        exit;
    }
    
    // Password verified, proceed with deletion
    $delete_success = false;
    if (isset($conn) && $conn) {
        $delete_id = (int)$_POST['delete_record_id'];
        $stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
        if ($stmt !== false) {
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $delete_success = true;
            }
            $stmt->close();
        }
    }
    
    // Return JSON response with redirect URL
    $year_param = isset($_GET['year']) && !empty($_GET['year']) ? '&year=' . (int)$_GET['year'] : '';
    $redirect_url = $patient_id 
        ? "patients.php?patient_id=" . $patient_id . "&tab=medical_records" . $year_param . "&message=" . urlencode('Medical record deleted successfully')
        : "patients.php?message=" . urlencode('Medical record deleted successfully');
    
    echo json_encode([
        'success' => $delete_success,
        'message' => $delete_success ? 'Medical record deleted successfully' : 'Failed to delete medical record',
        'redirect' => $redirect_url
    ]);
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

// Get selected year filter (default to current year or all)
$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;

// Get medical records for this patient with doctor, creator, and updater information (only actual visit records, not history)
$medical_records = [];
$hasCreatedBy = false; // Initialize to ensure it's available throughout the file
$hasUpdatedBy = false; // Initialize to ensure it's available throughout the file

// Get available years from medical records, vitals, and medical history for the filter dropdown
$available_years = [];
if (isset($conn) && $conn && isset($patient_details) && $patient_details && isset($patient_details['id'])) {
    $yearsQuery = $conn->prepare("SELECT DISTINCT YEAR(visit_date) as year 
                                   FROM medical_records 
                                   WHERE patient_id = ? AND (history_type IS NULL OR history_type = '') 
                                   AND visit_date IS NOT NULL AND visit_date != '0000-00-00'
                                   ORDER BY year DESC");
    if ($yearsQuery !== false) {
        $yearsQuery->bind_param("i", $patient_details['id']);
        if ($yearsQuery->execute()) {
            $yearsResult = $yearsQuery->get_result();
            while ($yearRow = $yearsResult->fetch_assoc()) {
                $available_years[] = $yearRow['year'];
            }
        }
    }
    
    // Add years from vitals records
    $checkVitalsTable = $conn->query("SHOW TABLES LIKE 'patient_vitals'");
    if ($checkVitalsTable && $checkVitalsTable->num_rows > 0) {
        $vitalsYearsQuery = $conn->prepare("SELECT DISTINCT YEAR(visit_date) as year 
                                           FROM patient_vitals 
                                           WHERE patient_id = ? 
                                           AND visit_date IS NOT NULL AND visit_date != '0000-00-00'
                                           ORDER BY year DESC");
        if ($vitalsYearsQuery !== false) {
            $vitalsYearsQuery->bind_param("i", $patient_details['id']);
            if ($vitalsYearsQuery->execute()) {
                $vitalsYearsResult = $vitalsYearsQuery->get_result();
                while ($yearRow = $vitalsYearsResult->fetch_assoc()) {
                    if (!in_array($yearRow['year'], $available_years)) {
                        $available_years[] = $yearRow['year'];
                    }
                }
            }
            $vitalsYearsQuery->close();
        }
    }
    
    // Add years from medical history records
    $checkHistoryTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
    if ($checkHistoryTable && $checkHistoryTable->num_rows > 0) {
        $historyYearsQuery = $conn->prepare("SELECT DISTINCT YEAR(created_at) as year 
                                            FROM medical_history 
                                            WHERE patient_id = ? AND status = 'active'
                                            ORDER BY year DESC");
        if ($historyYearsQuery !== false) {
            $historyYearsQuery->bind_param("i", $patient_details['id']);
            if ($historyYearsQuery->execute()) {
                $historyYearsResult = $historyYearsQuery->get_result();
                while ($yearRow = $historyYearsResult->fetch_assoc()) {
                    if (!in_array($yearRow['year'], $available_years)) {
                        $available_years[] = $yearRow['year'];
                    }
                }
            }
            $historyYearsQuery->close();
        }
    }
    
    // Sort years in descending order
    rsort($available_years);
}

// Check if required variables exist
if (!isset($conn) || !$conn) {
    error_log("Database connection not available in patient_medical_records.php");
    $medical_records = [];
} elseif (!isset($patient_details) || !$patient_details || !isset($patient_details['id'])) {
    error_log("Patient details not available in patient_medical_records.php");
    $medical_records = [];
} else {
    // Check if tracking columns exist before building query
    $checkCreatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'created_by'");
    $hasCreatedBy = $checkCreatedBy && $checkCreatedBy->num_rows > 0;
    $checkUpdatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'updated_by'");
    $hasUpdatedBy = $checkUpdatedBy && $checkUpdatedBy->num_rows > 0;
    
    // If created_by column doesn't exist, try to add it automatically (one-time migration)
    if (!$hasCreatedBy && isAdmin()) {
        // Try to add the column
        $addColumn = $conn->query("ALTER TABLE `medical_records` ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `status`");
        if ($addColumn) {
            $hasCreatedBy = true;
            error_log("Successfully added created_by column to medical_records table");
        } else {
            // Column might already exist or there's an error - check again
            $checkCreatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'created_by'");
            $hasCreatedBy = $checkCreatedBy && $checkCreatedBy->num_rows > 0;
        }
    }
    
    // If updated_by column doesn't exist, try to add it automatically (one-time migration)
    if (!$hasUpdatedBy && isAdmin()) {
        // Try to add the column
        $addColumn = $conn->query("ALTER TABLE `medical_records` ADD COLUMN `updated_by` int(11) DEFAULT NULL AFTER `created_by`");
        if ($addColumn) {
            $hasUpdatedBy = true;
            error_log("Successfully added updated_by column to medical_records table");
        } else {
            // Column might already exist or there's an error - check again
            $checkUpdatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'updated_by'");
            $hasUpdatedBy = $checkUpdatedBy && $checkUpdatedBy->num_rows > 0;
        }
    }
    
    if ($hasCreatedBy || $hasUpdatedBy) {
        // Build query parts based on which columns exist
        // Select all medical_records fields explicitly to avoid conflicts
        $joinClauses = "LEFT JOIN users attending ON mr.doctor_id = attending.id ";
        $selectFields = "mr.id, mr.patient_id, mr.doctor_id, mr.visit_date, mr.vitals, mr.diagnosis, mr.treatment, mr.prescription, mr.lab_results, mr.notes, mr.attachments, mr.next_appointment_date, mr.created_at, mr.updated_at, mr.history_type, mr.history_details, mr.status, ";
        
        if ($hasCreatedBy) {
            // Explicitly include mr.created_by to ensure we can use it for fallback lookup
            // JOIN creator info to get the name and role of who recorded it
            $joinClauses .= "LEFT JOIN users creator ON mr.created_by = creator.id ";
            $selectFields .= "mr.created_by, mr.created_by as mr_created_by, creator.first_name as creator_first_name, creator.last_name as creator_last_name, creator.role as creator_role, ";
        } else {
            $selectFields .= "NULL as created_by, NULL as mr_created_by, NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role, ";
        }
        
        if ($hasUpdatedBy) {
            // JOIN updater info to get who last updated it
            $joinClauses .= "LEFT JOIN users updater ON mr.updated_by = updater.id ";
            $selectFields .= "mr.updated_by, updater.first_name as updater_first_name, updater.last_name as updater_last_name, updater.role as updater_role, ";
        } else {
            $selectFields .= "NULL as updated_by, NULL as updater_first_name, NULL as updater_last_name, NULL as updater_role, ";
        }
        
        // Attending doctor info
        $selectFields .= "attending.first_name as doctor_first_name, attending.last_name as doctor_last_name, 
                          attending.role as doctor_role, attending.specialization";
        
        // Add year filter to WHERE clause if selected
        $yearFilter = "";
        if ($selected_year !== null) {
            $yearFilter = " AND YEAR(mr.visit_date) = ?";
        }
        
        $stmt = $conn->prepare("SELECT " . $selectFields . "
                                FROM medical_records mr 
                                " . $joinClauses . "
                                WHERE mr.patient_id = ? AND (mr.history_type IS NULL OR mr.history_type = '')" . $yearFilter . "
                                ORDER BY mr.visit_date DESC, mr.created_at DESC");
    } else {
        // Fallback query without tracking columns
        // Add year filter to WHERE clause if selected
        $yearFilter = "";
        if ($selected_year !== null) {
            $yearFilter = " AND YEAR(mr.visit_date) = ?";
        }
        
        $stmt = $conn->prepare("SELECT mr.*, 
                                       attending.first_name as doctor_first_name, attending.last_name as doctor_last_name, 
                                       attending.role as doctor_role, attending.specialization,
                                       NULL as created_by, NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role,
                                       NULL as updated_by, NULL as updater_first_name, NULL as updater_last_name, NULL as updater_role
                                FROM medical_records mr 
                                LEFT JOIN users attending ON mr.doctor_id = attending.id 
                                WHERE mr.patient_id = ? AND (mr.history_type IS NULL OR mr.history_type = '')" . $yearFilter . "
                                ORDER BY mr.visit_date DESC, mr.created_at DESC");
    }
    
    if ($stmt === false) {
        error_log("SQL Prepare failed in patient_medical_records.php: " . $conn->error);
        $medical_records = [];
    } else {
        // Bind parameters based on whether year filter is applied
        if ($selected_year !== null) {
            $stmt->bind_param("ii", $patient_details['id'], $selected_year);
        } else {
            $stmt->bind_param("i", $patient_details['id']);
        }
        if (!$stmt->execute()) {
            error_log("SQL Execute failed in patient_medical_records.php: " . $stmt->error);
            $medical_records = [];
        } else {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $medical_records[] = $row;
            }
        }
    }
}

// Get medical history records for this patient
$medical_history_records = [];
if (isset($conn) && $conn && isset($patient_details) && $patient_details && isset($patient_details['id'])) {
    $checkHistoryTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
    if ($checkHistoryTable && $checkHistoryTable->num_rows > 0) {
        // Check if structured_data column exists
        $checkStructured = $conn->query("SHOW COLUMNS FROM `medical_history` LIKE 'structured_data'");
        $hasStructured = $checkStructured && $checkStructured->num_rows > 0;
        
        $stmt_history = $conn->prepare("SELECT mh.*, 
                                               u.first_name as doctor_first_name, u.last_name as doctor_last_name, 
                                               u.role as doctor_role, u.specialization,
                                               creator.first_name as creator_first_name, creator.last_name as creator_last_name, creator.role as creator_role
                                        FROM medical_history mh 
                                        LEFT JOIN users u ON mh.doctor_id = u.id 
                                        LEFT JOIN users creator ON mh.created_by = creator.id
                                        WHERE mh.patient_id = ? AND mh.status = 'active'
                                        ORDER BY mh.created_at DESC");
        
        if ($stmt_history) {
            $stmt_history->bind_param("i", $patient_details['id']);
            if ($stmt_history->execute()) {
                $history_result = $stmt_history->get_result();
                if ($history_result) {
                    while ($row = $history_result->fetch_assoc()) {
                        $row['record_type'] = 'medical_history';
                        $medical_history_records[] = $row;
                    }
                }
            }
            $stmt_history->close();
        }
    }
}

// Get vitals records for this patient
$vitals_records = [];
if (isset($conn) && $conn && isset($patient_details) && $patient_details && isset($patient_details['id'])) {
    $checkVitalsTable = $conn->query("SHOW TABLES LIKE 'patient_vitals'");
    if ($checkVitalsTable && $checkVitalsTable->num_rows > 0) {
        $stmt_vitals = $conn->prepare("SELECT pv.*, 
                                              NULL as doctor_first_name, NULL as doctor_last_name, 
                                              NULL as doctor_role, NULL as specialization,
                                              NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role
                                       FROM patient_vitals pv 
                                       WHERE pv.patient_id = ?
                                       ORDER BY pv.visit_date DESC, pv.created_at DESC");
        
        if ($stmt_vitals) {
            $stmt_vitals->bind_param("i", $patient_details['id']);
            if ($stmt_vitals->execute()) {
                $vitals_result = $stmt_vitals->get_result();
                if ($vitals_result) {
                    while ($row = $vitals_result->fetch_assoc()) {
                        $row['record_type'] = 'vitals';
                        // Use visit_date as the date field for grouping
                        if (empty($row['visit_date']) || $row['visit_date'] == '0000-00-00') {
                            $row['visit_date'] = date('Y-m-d', strtotime($row['created_at']));
                        }
                        $vitals_records[] = $row;
                    }
                }
            }
            $stmt_vitals->close();
        }
    }
}

// Create a map of vitals and medical history by visit_date for easy lookup
$vitals_by_visit_date = [];
foreach ($vitals_records as $vital) {
    $visit_date = $vital['visit_date'];
    if (!isset($vitals_by_visit_date[$visit_date])) {
        $vitals_by_visit_date[$visit_date] = [];
    }
    $vitals_by_visit_date[$visit_date][] = $vital;
}

$medical_history_by_visit_date = [];
foreach ($medical_history_records as $history) {
    // Medical history doesn't have visit_date, so we'll use created_at date
    $history_date = date('Y-m-d', strtotime($history['created_at']));
    if (!isset($medical_history_by_visit_date[$history_date])) {
        $medical_history_by_visit_date[$history_date] = [];
    }
    $medical_history_by_visit_date[$history_date][] = $history;
}

// Get prescriptions for the current patient
$prescriptions = [];
if (isset($conn) && $conn && isset($patient_details) && $patient_details && isset($patient_details['id'])) {
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
    if ($stmt !== false) {
        $stmt->bind_param("i", $patient_details['id']);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $prescriptions[] = $row;
            }
        } else {
            error_log("SQL Execute failed for prescriptions in patient_medical_records.php: " . $stmt->error);
        }
    } else {
        error_log("SQL Prepare failed for prescriptions in patient_medical_records.php: " . $conn->error);
    }
}

// Get all active doctors for attending doctor dropdown
$allDoctors = [];
if (isAdmin() || isDoctor()) {
    try {
        $doctorsQuery = "SELECT u.id, u.first_name, u.last_name, u.specialization, 
                                COALESCE(dept.id, 0) as department_id, 
                                COALESCE(dept.name, 'Unassigned') as department_name
                         FROM users u
                         LEFT JOIN departments dept ON u.department_id = dept.id
                         WHERE u.role = 'Doctor' AND u.status = 'Active'
                         ORDER BY dept.name, u.last_name, u.first_name";
        $doctorsResult = $conn->query($doctorsQuery);
        if ($doctorsResult) {
            while ($row = $doctorsResult->fetch_assoc()) {
                $allDoctors[] = $row;
            }
        } else {
            // Query failed - log the error
            error_log("Doctors query failed: " . $conn->error);
        }
    } catch (Exception $e) {
        // Log error but continue - field will show with empty message
        error_log("Error fetching doctors: " . $e->getMessage());
    }
}
// Debug: Ensure $allDoctors is always set (even if empty)
if (!isset($allDoctors)) {
    $allDoctors = [];
}

// Note: Attachments will be available after running the migration
// to create the medical_record_attachments table

// Helper function to create structured history preview
function createStructuredHistoryPreview($historyType, $data) {
    if (!$data || !is_array($data)) {
        return '';
    }
    
    $preview = '';
    
    switch($historyType) {
        case 'allergies':
            $items = [];
            if (!empty($data['food'])) $items[] = 'Food: ' . implode(', ', array_slice($data['food'], 0, 2));
            if (!empty($data['drug'])) $items[] = 'Drug: ' . implode(', ', array_slice($data['drug'], 0, 2));
            if (!empty($data['environmental'])) $items[] = 'Env: ' . implode(', ', array_slice($data['environmental'], 0, 2));
            if (!empty($data['severity'])) $items[] = 'Severity: ' . $data['severity'];
            $preview = implode(' | ', $items);
            break;
        case 'medications':
            if (is_array($data) && count($data) > 0) {
                $meds = array_slice($data, 0, 2);
                $preview = implode(', ', array_map(function($m) { return $m['name'] ?? ''; }, $meds));
                if (count($data) > 2) $preview .= '...';
            }
            break;
        case 'past_history':
            if (!empty($data['conditions'])) {
                $preview = implode(', ', array_slice($data['conditions'], 0, 3));
                if (count($data['conditions']) > 3) $preview .= '...';
            }
            break;
        case 'immunization':
            $items = [];
            if (!empty($data['children'])) $items = array_merge($items, array_slice($data['children'], 0, 2));
            if (!empty($data['adults'])) $items = array_merge($items, array_slice($data['adults'], 0, 2));
            $preview = implode(', ', $items);
            break;
        case 'procedures':
            if (is_array($data) && count($data) > 0) {
                $procs = array_slice($data, 0, 2);
                $preview = implode(', ', array_map(function($p) { return $p['name'] ?? ''; }, $procs));
            }
            break;
        case 'substance':
            $items = [];
            if (!empty($data['smoking_status'])) $items[] = 'Smoking: ' . $data['smoking_status'];
            if (!empty($data['alcohol_status'])) $items[] = 'Alcohol: ' . $data['alcohol_status'];
            $preview = implode(' | ', $items);
            break;
        case 'family':
            if (!empty($data['conditions'])) {
                $preview = implode(', ', array_slice($data['conditions'], 0, 3));
                if (!empty($data['relationship'])) $preview .= ' (' . $data['relationship'] . ')';
            }
            break;
        case 'menstrual':
            if (!empty($data['lmp'])) $preview = 'LMP: ' . $data['lmp'];
            if (!empty($data['regularity'])) $preview .= ($preview ? ' | ' : '') . 'Regularity: ' . $data['regularity'];
            break;
        case 'obstetric':
            if (!empty($data['gravida']) || !empty($data['para'])) {
                $preview = 'G' . ($data['gravida'] ?? '0') . 'P' . ($data['para'] ?? '0');
            }
            break;
        case 'growth':
            if (!empty($data['birth_weight'])) $preview = 'Birth Weight: ' . $data['birth_weight'] . ' kg';
            if (!empty($data['milestones'])) $preview .= ($preview ? ' | ' : '') . 'Milestones: ' . $data['milestones'];
            break;
    }
    
    return htmlspecialchars($preview);
}
?>

<style>
.record-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: box-shadow 0.2s;
}
.month-records-container .record-card:last-child {
    margin-bottom: 0;
}
.record-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.record-header {
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    border-radius: 8px 8px 0 0;
}
.record-body {
    padding: 15px;
}
.doctor-info {
    background-color: #e8f5e8;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 10px;
    border-left: 4px solid #28a745;
}
.attachment-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 8px;
    margin: 5px 0;
    display: flex;
    justify-content: between;
    align-items: center;
}
.attachment-icon {
    margin-right: 8px;
    color: #6c757d;
}
.vital-signs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    margin: 10px 0;
}
.vital-item {
    background-color: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    text-align: center;
}
.vital-label {
    font-size: 0.8em;
    color: #6c757d;
    font-weight: 500;
}
.vital-value {
    font-size: 1.1em;
    font-weight: 600;
    color: #495057;
}
.prescription-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: box-shadow 0.2s;
}
.prescription-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.prescription-header {
    background-color: #f8f9fa;
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
.month-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 15px;
    background-color: #fff;
    transition: box-shadow 0.2s;
}
.month-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.month-card-header {
    background-color: #f8f9fa;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #dee2e6;
}
.month-card-header:hover {
    background-color: #e9ecef;
}
.month-card-header h6 {
    margin: 0;
    font-size: 1.1em;
    font-weight: 600;
    color: #495057;
    display: flex;
    align-items: center;
}
.month-card-header .badge {
    font-size: 0.9em;
    padding: 6px 12px;
}
.month-card-header .chevron {
    transition: transform 0.3s;
    color: #6c757d;
}
.month-card-header[aria-expanded="true"] .chevron {
    transform: rotate(180deg);
}
.month-records-container {
    padding: 15px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <div>
        <h5 class="mb-1"><i class="fas fa-file-medical me-2"></i>Medical Records (Visits)</h5>
        <?php if ($selected_year !== null): ?>
            <small class="text-muted">
                <i class="fas fa-filter me-1"></i>Filtered by year: <strong><?= htmlspecialchars($selected_year) ?></strong>
                (<?= count($medical_records) ?> record<?= count($medical_records) != 1 ? 's' : '' ?>)
            </small>
        <?php elseif (!empty($medical_records)): ?>
            <small class="text-muted">
                Showing all records (<?= count($medical_records) ?> total)
            </small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if (!empty($available_years)): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="yearFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-filter me-1"></i>
                    <?php if ($selected_year !== null): ?>
                        Year: <?= htmlspecialchars($selected_year) ?>
                    <?php else: ?>
                        All Years
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="yearFilterBtn">
                    <li>
                        <a class="dropdown-item <?= $selected_year === null ? 'active' : '' ?>" 
                           href="patients.php?patient_id=<?= $patient_details['id'] ?>&tab=medical_records">
                            <i class="fas fa-list me-2"></i>All Years
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php foreach ($available_years as $year): ?>
                        <li>
                            <a class="dropdown-item <?= $selected_year == $year ? 'active' : '' ?>" 
                               href="patients.php?patient_id=<?= $patient_details['id'] ?>&tab=medical_records&year=<?= $year ?>">
                                <i class="fas fa-calendar me-2"></i><?= htmlspecialchars($year) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (isAdmin() || isDoctor()): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalRecordModal">
                <i class="fas fa-plus me-1"></i>Add Medical Record
            </button>
        <?php endif; ?>
    </div>
</div>

<?php 
// Display success/error messages
if (isset($_GET['message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($medical_records)): ?>
    <?php 
    // Group records by month
    $records_by_month = [];
    foreach ($medical_records as $record) {
        $month_key = date('Y-m', strtotime($record['visit_date']));
        if (!isset($records_by_month[$month_key])) {
            $records_by_month[$month_key] = [];
        }
        $records_by_month[$month_key][] = $record;
    }
    // Sort months in descending order (newest first)
    krsort($records_by_month);
    
    foreach ($records_by_month as $month_key => $month_records): 
        $month_name = formatDate($month_key . '-01', 'F Y');
        $record_count = count($month_records);
        $collapse_id = 'month-' . str_replace('-', '', $month_key);
    ?>
        <div class="month-card">
            <div class="month-card-header" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>" aria-expanded="false" aria-controls="<?= $collapse_id ?>">
                <h6>
                    <i class="fas fa-calendar-alt me-2"></i><?= $month_name ?>
                    <span class="badge bg-primary ms-2"><?= $record_count ?> record<?= $record_count != 1 ? 's' : '' ?></span>
                </h6>
                <i class="fas fa-chevron-down chevron"></i>
            </div>
            <div class="collapse" id="<?= $collapse_id ?>">
                <div class="month-records-container">
                    <?php foreach ($month_records as $record): 
                        // Get vitals and medical history for this visit date
                        $visit_date = $record['visit_date'];
                        $record_vitals = isset($vitals_by_visit_date[$visit_date]) ? $vitals_by_visit_date[$visit_date] : [];
                        $record_history = [];
                        // Get medical history within 7 days of visit date
                        foreach ($medical_history_by_visit_date as $history_date => $histories) {
                            $date_diff = abs((strtotime($visit_date) - strtotime($history_date)) / 86400);
                            if ($date_diff <= 7) {
                                $record_history = array_merge($record_history, $histories);
                            }
                        }
                        // Add vitals and history to record data
                        $record['related_vitals'] = $record_vitals;
                        $record['related_medical_history'] = $record_history;
                    ?>
                <div class="record-card" data-record-id="<?= $record['id'] ?>" data-record-json="<?= htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="record-header d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                <?php
                                $visit_date_formatted = formatDate($record['visit_date']);
                                if ($record['created_at']):
                                    $visit_date_only = date('Y-m-d', strtotime($record['visit_date']));
                                    $created_date_only = date('Y-m-d', strtotime($record['created_at']));
                                    
                                    if ($visit_date_only === $created_date_only):
                                        // Same day - show only time
                                        $time_only = date('g:i A', strtotime($record['created_at']));
                                        echo $visit_date_formatted . ' - ' . $time_only;
                                    else:
                                        // Different days - show full date-time
                                        echo $visit_date_formatted . ' - ' . formatDateTime($record['created_at']);
                                    endif;
                                else:
                                    echo $visit_date_formatted;
                                endif;
                                ?>
                            </small>
                        </div>

                <button class="btn btn-sm btn-outline-primary" type="button" onclick="showViewMedicalRecordModal(<?= $record['id'] ?>); return false;" title="View Medical Record">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="record-body" id="record-body-<?= $record['id'] ?>" style="display: none;">


                <?php if (!empty($record['diagnosis'])): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-notes-medical me-1"></i>Diagnosis:</strong>
                        <div class="text-muted"><?= htmlspecialchars($record['diagnosis']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['treatment'])): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-pills me-1"></i>Treatment:</strong>
                        <div class="text-muted"><?= htmlspecialchars($record['treatment']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['prescription'])): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-prescription me-1"></i>Prescription:</strong>
                        <div class="text-muted"><?= htmlspecialchars($record['prescription']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['lab_results'])): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-flask me-1"></i>Lab Results:</strong>
                        <div class="text-muted"><?= htmlspecialchars($record['lab_results']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['vitals'])): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                        <div class="text-muted"><?= htmlspecialchars($record['vitals']) ?></div>
                    </div>
                <?php else: ?>
                    <?php 
                    // Only display vitals from patient_vitals table if the medical record's vitals field is empty
                    // This prevents duplication when vitals are stored in the medical record
                    $visit_date = $record['visit_date'];
                    if (isset($vitals_by_visit_date[$visit_date]) && !empty($vitals_by_visit_date[$visit_date])): 
                        foreach ($vitals_by_visit_date[$visit_date] as $vital): 
                            $vital_items = [];
                            if (!empty($vital['blood_pressure'])) $vital_items[] = 'Blood Pressure: ' . htmlspecialchars($vital['blood_pressure']);
                            if (!empty($vital['heart_rate'])) $vital_items[] = 'Heart Rate: ' . htmlspecialchars($vital['heart_rate']) . ' bpm';
                            if (!empty($vital['respiratory_rate'])) $vital_items[] = 'Respiratory Rate: ' . htmlspecialchars($vital['respiratory_rate']) . ' /min';
                            if (!empty($vital['temperature'])) $vital_items[] = 'Temperature: ' . htmlspecialchars($vital['temperature']) . ' °F';
                            if (!empty($vital['oxygen_saturation'])) $vital_items[] = 'Oxygen Saturation: ' . htmlspecialchars($vital['oxygen_saturation']) . ' %';
                            if (!empty($vital['weight'])) $vital_items[] = 'Weight: ' . htmlspecialchars($vital['weight']) . ' lbs';
                            if (!empty($vital['height'])) $vital_items[] = 'Height: ' . htmlspecialchars($vital['height']) . ' in';
                            if (!empty($vital['bmi'])) {
                                $patient_gender = isset($patient_details['sex']) ? $patient_details['sex'] : '';
                                $bmi_classification = classifyBMI($vital['bmi'], $patient_gender);
                                $vital_items[] = 'BMI: ' . htmlspecialchars($vital['bmi']) . ' <span class="badge bg-' . $bmi_classification['class'] . '">' . htmlspecialchars($bmi_classification['status']) . '</span>';
                            }
                            
                            if (!empty($vital_items)): ?>
                                <div class="mb-3">
                                    <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                                    <div class="text-muted"><?= implode(' • ', $vital_items) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php 
                // Display medical history records for this visit date (or nearby dates)
                $visit_date = $record['visit_date'];
                $history_items = [];
                // Check for history on the same date or within 7 days
                foreach ($medical_history_by_visit_date as $history_date => $histories): 
                    $date_diff = abs((strtotime($visit_date) - strtotime($history_date)) / 86400);
                    if ($date_diff <= 7): // Within 7 days
                        foreach ($histories as $history): 
                            $historyTypes = [
                                'allergies' => 'Allergies',
                                'medications' => 'Medications',
                                'past_history' => 'Past History',
                                'immunization' => 'Immunization',
                                'procedures' => 'Procedures',
                                'substance' => 'Substance Use',
                                'family' => 'Family History',
                                'menstrual' => 'Menstrual History',
                                'sexual' => 'Sexual History',
                                'obstetric' => 'Obstetric History',
                                'growth' => 'Growth History'
                            ];
                            $historyType = isset($historyTypes[$history['history_type']]) ? $historyTypes[$history['history_type']] : ucfirst($history['history_type']);
                            
                            // Try to parse structured data for better display
                            $historyPreview = '';
                            if (!empty($history['structured_data'])) {
                                try {
                                    $structuredData = json_decode($history['structured_data'], true);
                                    if ($structuredData && is_array($structuredData)) {
                                        // Create a brief preview from structured data
                                        $historyPreview = createStructuredHistoryPreview($history['history_type'], $structuredData);
                                    }
                                } catch (Exception $e) {
                                    // Fallback to text details
                                }
                            }
                            
                            // Use structured preview if available, otherwise use text details
                            if (empty($historyPreview) && !empty($history['history_details'])) {
                                $historyPreview = htmlspecialchars(substr($history['history_details'], 0, 100));
                                if (strlen($history['history_details']) > 100) {
                                    $historyPreview .= '...';
                                }
                            }
                            
                            if (!empty($historyPreview)) {
                                $history_items[] = [
                                    'type' => $historyType,
                                    'preview' => $historyPreview,
                                    'id' => $history['id'],
                                    'full_data' => $history
                                ];
                            }
                        endforeach; 
                    endif;
                endforeach; 
                
                if (!empty($history_items)): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-history me-1"></i>Medical History:</strong>
                        <div class="text-muted mt-2">
                            <?php foreach ($history_items as $item): ?>
                                <div class="mb-2 p-2 bg-light border rounded">
                                    <strong class="text-primary"><?= htmlspecialchars($item['type']) ?>:</strong>
                                    <div class="mt-1"><?= $item['preview'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['notes'])): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-sticky-note me-1"></i>Notes:</strong>
                        <div class="text-muted"><?= htmlspecialchars($record['notes']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['attachments'])): ?>
                    <div class="mb-3">
                        <strong><i class="fas fa-paperclip me-1"></i>Attachments:</strong>
                        <div class="mt-2">
                            <?php 
                            $attachments = json_decode($record['attachments'], true);
                            if (is_array($attachments)):
                                foreach ($attachments as $attachment): ?>
                                    <div class="attachment-item">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file attachment-icon"></i>
                                            <span class="flex-grow-1"><?= htmlspecialchars($attachment['original_name']) ?></span>
                                            <small class="text-muted me-2"><?= number_format($attachment['file_size'] / 1024, 1) ?> KB</small>
                                            <a href="<?= htmlspecialchars($attachment['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download me-1"></i>View
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach;
                            endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['next_appointment_date']) && $record['next_appointment_date'] != '0000-00-00' && $record['next_appointment_date'] != '-0001-11-30'): ?>
                    <?php 
                    $nextApptDate = strtotime($record['next_appointment_date']);
                    if ($nextApptDate !== false && $nextApptDate > 0):
                    ?>
                        <div class="mb-3">
                            <strong><i class="fas fa-calendar-alt me-1"></i>Next Appointment:</strong>
                            <div class="text-muted"><?= formatDate(date('Y-m-d', $nextApptDate)) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="doctor-info">
                    <div class="row">
                                        <div class="col-md-12 mb-3">
                            <div class="card border-primary">
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-user-md me-2 text-primary"></i>Attending Doctor for This Visit
                                    </h6>
                                    <div class="d-flex align-items-center">
                                        <?php if ($record['doctor_first_name']): ?>
                                            <div class="me-3">
                                                <?php if ($record['doctor_role'] == 'Admin'): ?>
                                                    <span class="badge bg-warning text-dark me-2">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-white me-2">Doctor</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong>Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></strong>
                                                <?php if ($record['specialization']): ?>
                                                    <span class="text-muted">- <?php echo htmlspecialchars($record['specialization']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown Doctor</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card border-secondary">
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-user-edit me-2 text-secondary"></i>Record Information
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <i class="fas fa-user-plus me-1"></i>
                                            <strong>Recorded by:</strong> 
                                            <?php 
                                            // Automatically detect and show the creator (admin or doctor who recorded it)
                                            $creatorName = '';
                                            $creatorRole = '';
                                            $creatorId = null;
                                            
                                            // Step 1: Try to get creator info from JOIN (most efficient)
                                            if (isset($record['creator_first_name']) && !empty($record['creator_first_name'])) {
                                                $creatorName = trim($record['creator_first_name'] . ' ' . $record['creator_last_name']);
                                                $creatorRole = isset($record['creator_role']) ? $record['creator_role'] : '';
                                            }
                                            
                                            // Step 2: If JOIN didn't work, fetch using created_by ID
                                            if (empty($creatorName)) {
                                                // Get created_by ID from record - check multiple possible field names
                                                $creatorId = null;
                                                
                                                // Check all possible field names (handle both NULL and empty string)
                                                $possibleFields = ['mr_created_by', 'created_by'];
                                                foreach ($possibleFields as $field) {
                                                    if (isset($record[$field]) && $record[$field] !== null && $record[$field] !== '' && (int)$record[$field] > 0) {
                                                        $creatorId = (int)$record[$field];
                                                        break; // Use the first valid ID found
                                                    }
                                                }
                                                
                                                // Fetch creator info from database if we have an ID
                                                if ($creatorId && $creatorId > 0 && isset($conn) && $conn) {
                                                    $creatorStmt = $conn->prepare("SELECT first_name, last_name, role FROM users WHERE id = ? LIMIT 1");
                                                    if ($creatorStmt !== false) {
                                                        $creatorStmt->bind_param("i", $creatorId);
                                                        if ($creatorStmt->execute()) {
                                                            $creatorResult = $creatorStmt->get_result();
                                                            if ($creatorRow = $creatorResult->fetch_assoc()) {
                                                                $creatorName = trim($creatorRow['first_name'] . ' ' . $creatorRow['last_name']);
                                                                $creatorRole = isset($creatorRow['role']) ? $creatorRow['role'] : '';
                                                            } else {
                                                                // User not found - log for debugging
                                                                error_log("Creator user not found for ID: " . $creatorId . " in medical record ID: " . (isset($record['id']) ? $record['id'] : 'unknown'));
                                                            }
                                                        } else {
                                                            error_log("Failed to execute creator lookup query: " . $creatorStmt->error);
                                                        }
                                                        $creatorStmt->close();
                                                    } else {
                                                        error_log("Failed to prepare creator lookup query: " . $conn->error);
                                                    }
                                                } else {
                                                    // No creator ID found - this is expected for old records created before tracking was added
                                                    // Only log if we expected to find one (i.e., if created_by column exists)
                                                    if (isset($record['id']) && isset($hasCreatedBy) && $hasCreatedBy) {
                                                        $debugInfo = [];
                                                        foreach (['mr_created_by', 'created_by'] as $field) {
                                                            $debugInfo[] = $field . ": " . (isset($record[$field]) ? ($record[$field] === null ? 'NULL' : $record[$field]) : 'not set');
                                                        }
                                                        error_log("No creator ID found for medical record ID: " . $record['id'] . " (" . implode(", ", $debugInfo) . ")");
                                                    }
                                                }
                                            }
                                            
                                            // Display the creator information
                                            ?>
                                            <br>
                                            <?php if (!empty($creatorName)): ?>
                                                <?php if ($creatorRole == 'Admin'): ?>
                                                    <span class="badge bg-warning text-dark me-1">Admin</span>
                                                <?php elseif ($creatorRole == 'Doctor'): ?>
                                                    <span class="badge bg-info text-white me-1">Doctor</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary me-1">User</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($creatorName); ?>
                                                <br>
                                                <small class="text-muted">on <?php echo formatDateTime($record['created_at']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Not available</span>
                                                <?php if (isset($record['created_by']) && $record['created_by']): ?>
                                                    <small class="text-muted d-block">(User ID: <?= htmlspecialchars($record['created_by']) ?>)</small>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">on <?php echo formatDateTime($record['created_at']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <?php if (isset($record['updater_first_name']) && $record['updater_first_name'] && 
                                                     isset($record['updated_by']) && $record['updated_by'] && 
                                                     (!isset($record['created_by']) || !$record['created_by'] || $record['updated_by'] != $record['created_by'])): ?>
                                                <i class="fas fa-user-edit me-1"></i>
                                                <strong>Last updated by:</strong> 
                                                <br>
                                                <?php if ($record['updater_role'] == 'Admin'): ?>
                                                    <span class="badge bg-warning text-dark me-1">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-white me-1">Doctor</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($record['updater_first_name'] . ' ' . $record['updater_last_name']); ?>
                                                <br>
                                                <small class="text-muted">on <?php echo formatDateTime($record['updated_at']); ?></small>
                                            <?php else: ?>
                                                <i class="fas fa-calendar me-1"></i>
                                                <strong>Record Date:</strong> 
                                                <br>
                                                <small class="text-muted"><?php echo formatDateTime($record['created_at']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="text-center py-5">
        <?php if ($selected_year !== null): ?>
            <i class="fas fa-filter fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No medical records found for year <?= htmlspecialchars($selected_year) ?></h5>
            <p class="text-muted">Try selecting a different year or view all records.</p>
            <a href="patients.php?patient_id=<?= $patient_details['id'] ?>&tab=medical_records" class="btn btn-outline-primary mt-2">
                <i class="fas fa-list me-1"></i>View All Years
            </a>
        <?php else: ?>
            <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No Medical Records Found</h5>
            <p class="text-muted">This patient doesn't have any medical visit records yet.</p>
            <?php if (isAdmin() || isDoctor()): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalRecordModal">
                    <i class="fas fa-plus me-1"></i>Add First Medical Record
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isAdmin() || isDoctor()): ?>
<!-- Modal for Add Medical Record -->
<div class="modal fade" id="addMedicalRecordModal" tabindex="-1" aria-labelledby="addMedicalRecordLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="POST" action="add_medical_record.php" enctype="multipart/form-data">
        <input type="hidden" name="patient_id" value="<?php echo $patient_details['id']; ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="addMedicalRecordLabel">
              <i class="fas fa-file-medical me-2"></i>Add Medical Record
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (!empty($prescriptions)): ?>
            <div class="mb-4">
              <label class="form-label"><i class="fas fa-prescription-bottle-alt me-2"></i>Existing Prescriptions</label>
              <div style="max-height: 300px; overflow-y: auto; padding: 10px; border: 1px solid #e9ecef; border-radius: 8px; background-color: #f8f9fa;">
                <?php foreach ($prescriptions as $prescription): ?>
                  <div class="prescription-card">
                    <div class="prescription-header d-flex justify-content-between align-items-start">
                      <div>
                        <div class="medication-name"><?php echo htmlspecialchars($prescription['medication_name']); ?></div>
                        <div class="dosage-info mt-1">
                          <strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?> • 
                          <strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?>
                          <?php if ($prescription['duration']): ?>
                            • <strong>Duration:</strong> <?php echo htmlspecialchars($prescription['duration']); ?>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="d-flex align-items-center">
                        <span class="status-badge status-<?php echo strtolower($prescription['status']); ?> me-2">
                          <?php echo ucfirst($prescription['status']); ?>
                        </span>
                      </div>
                    </div>
                    <div class="prescription-body">
                      <?php if ($prescription['instructions']): ?>
                        <div class="mb-3">
                          <strong>Instructions:</strong>
                          <div class="text-muted"><?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?></div>
                        </div>
                      <?php endif; ?>

                      <div class="prescriber-info">
                        <div class="row">
                          <div class="col-md-6">
                            <i class="fas fa-user-md me-1"></i>
                            <strong>Prescribed by:</strong> 
                            <?php if ($prescription['doctor_first_name']): ?>
                              <?php if ($prescription['doctor_role'] == 'Admin'): ?>
                                <span class="badge bg-warning text-dark me-1">Admin</span>
                              <?php else: ?>
                                <span class="badge bg-info text-white me-1">Doctor</span>
                              <?php endif; ?>
                              <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                              <?php if ($prescription['specialization']): ?>
                                <span class="text-muted">(<?php echo htmlspecialchars($prescription['specialization']); ?>)</span>
                              <?php endif; ?>
                            <?php else: ?>
                              <span class="text-muted">Unknown Prescriber</span>
                            <?php endif; ?>
                          </div>
                          <div class="col-md-6 text-md-end">
                            <i class="fas fa-calendar me-1"></i>
                            <strong>Date:</strong> <?php echo formatDate($prescription['date_prescribed']); ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

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

          <!-- ASSIGN DOCTOR FIELD - ALWAYS VISIBLE -->
          <div class="mb-3" style="border: 2px solid #007bff; padding: 15px; border-radius: 5px; background-color: #f8f9fa;">
            <label for="attending_doctor_id" class="form-label fw-bold">
              <i class="fas fa-user-md me-1 text-primary"></i>Assign Doctor *
            </label>
            <select class="form-select form-select-lg" id="attending_doctor_id" name="attending_doctor_id" required style="font-size: 1rem;">
              <option value="">Select Attending Doctor</option>
              <?php 
              // Debug: Check if $allDoctors is set
              if (!isset($allDoctors) || empty($allDoctors)): ?>
                  <option value="" disabled>No active doctors found (Debug: <?php echo isset($allDoctors) ? 'Array empty' : 'Variable not set'; ?>)</option>
              <?php else:
                  $current_dept = '';
                  if (isAdmin()): 
                      // Admin can select any doctor
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
              <?php elseif (isDoctor()): 
                  // Doctor - auto-select themselves
                  $currentDoctor = null;
                  foreach ($allDoctors as $doctor) {
                      if ($doctor['id'] == $_SESSION['user_id']) {
                          $currentDoctor = $doctor;
                          break;
                      }
                  }
                  if ($currentDoctor): ?>
                      <option value="<?php echo $currentDoctor['id']; ?>" selected>
                          Dr. <?php echo htmlspecialchars($currentDoctor['first_name'] . ' ' . $currentDoctor['last_name']); ?>
                          <?php if ($currentDoctor['specialization']): ?>
                              - <?php echo htmlspecialchars($currentDoctor['specialization']); ?>
                          <?php endif; ?>
                          (You)
                      </option>
                  <?php else: ?>
                      <option value="<?php echo $_SESSION['user_id']; ?>" selected>
                          Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                          (You)
                      </option>
                  <?php endif; ?>
              <?php endif; ?>
              <?php endif; ?>
            </select>
            <div class="form-text">The doctor assigned to this medical record</div>
          </div>

          <div class="mb-3">
            <label for="diagnosis" class="form-label">Diagnosis</label>
            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"></textarea>
          </div>

          <div class="mb-3">
            <label for="treatment" class="form-label">Treatment</label>
            <textarea class="form-control" id="treatment" name="treatment" rows="3"></textarea>
          </div>

          <div class="mb-3">
            <label for="lab_results" class="form-label">Lab Results</label>
            <textarea class="form-control" id="lab_results" name="lab_results" rows="3"></textarea>
          </div>

          <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">
              <i class="fas fa-save me-1"></i>Save Medical Record
          </button>
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Set default date to today and allow dates from past year when modal opens
$(document).ready(function() {
    $('#addMedicalRecordModal').on('show.bs.modal', function() {
        const today = new Date();
        const oneYearAgo = new Date();
        oneYearAgo.setFullYear(today.getFullYear() - 1);
        
        const todayStr = today.toISOString().split('T')[0];
        const oneYearAgoStr = oneYearAgo.toISOString().split('T')[0];
        
        $('#visit_date').attr('min', oneYearAgoStr);
        $('#visit_date').attr('max', todayStr);
        $('#visit_date').val(todayStr);
    });
});
</script>
<?php endif; ?>

<?php
// Include the view medical record modal
include 'view_medical_record_modal.php';
?>
