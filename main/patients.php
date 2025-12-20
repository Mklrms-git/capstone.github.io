<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireLogin();

$page_title = isAdmin() ? "Patient Records" : "My Patients";
$active_page = "patients";

$conn = getDBConnection();

$viewing_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Initialize deletion confirmation variables
$show_delete_confirmation = false;
$delete_patient_id = null;
$delete_appointment_count = 0;
$delete_record_count = 0;
$delete_transaction_count = 0;
$delete_prescription_count = 0;

if (isAdmin() && isset($_GET['delete'])) {
    $patientId = (int)$_GET['delete'];
    
    // Check if confirmation is provided for deletion with existing records
    $confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
    
    // Check if patient has any related records
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM appointments WHERE patient_id = ?) as appointment_count,
        (SELECT COUNT(*) FROM medical_records WHERE patient_id = ?) as record_count,
        (SELECT COUNT(*) FROM transactions WHERE patient_id = ?) as transaction_count,
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?) as prescription_count");
    $stmt->bind_param("iiii", $patientId, $patientId, $patientId, $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts = $result->fetch_assoc();
    
    $total_related_records = $counts['appointment_count'] + $counts['record_count'] + $counts['transaction_count'] + $counts['prescription_count'];

    // If there are existing records and not confirmed, show confirmation page
    if ($total_related_records > 0 && !$confirmed) {
        // Store counts to show confirmation
        $show_delete_confirmation = true;
        $delete_patient_id = $patientId;
        $delete_appointment_count = $counts['appointment_count'];
        $delete_record_count = $counts['record_count'];
        $delete_transaction_count = $counts['transaction_count'];
        $delete_prescription_count = $counts['prescription_count'];
    } else {
        // Proceed with deletion (either no records or confirmed)
        // Start transaction for atomic deletion
        $conn->begin_transaction();
        
        try {
            // Delete related records in the correct order to handle foreign key constraints
            // Step 1: Delete transactions linked to appointments (must be done before deleting appointments)
            if ($counts['appointment_count'] > 0) {
                // First, get all appointment IDs for this patient
                $stmt = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ?");
                $stmt->bind_param("i", $patientId);
                $stmt->execute();
                $appointment_result = $stmt->get_result();
                $appointment_ids = [];
                while ($row = $appointment_result->fetch_assoc()) {
                    $appointment_ids[] = $row['id'];
                }
                $stmt->close();
                
                // Delete transactions that reference these appointments
                if (!empty($appointment_ids)) {
                    $placeholders = implode(',', array_fill(0, count($appointment_ids), '?'));
                    $stmt = $conn->prepare("DELETE FROM transactions WHERE appointment_id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($appointment_ids)), ...$appointment_ids);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Step 2: Now delete appointments (transactions referencing them are already deleted)
            if ($counts['appointment_count'] > 0) {
                $stmt = $conn->prepare("DELETE FROM appointments WHERE patient_id = ?");
                $stmt->bind_param("i", $patientId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Step 3: Delete any remaining transactions directly linked to patient_id
            if ($counts['transaction_count'] > 0) {
                $stmt = $conn->prepare("DELETE FROM transactions WHERE patient_id = ?");
                $stmt->bind_param("i", $patientId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Step 4: Delete prescriptions
            if ($counts['prescription_count'] > 0) {
                $stmt = $conn->prepare("DELETE FROM prescriptions WHERE patient_id = ?");
                $stmt->bind_param("i", $patientId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Note: medical_records, medical_certificates, patient_users, and patient_vitals
            // will be automatically deleted due to CASCADE constraints
            
            // Explicitly delete patient user account (ensures email is removed even if CASCADE is missing)
            $stmt = $conn->prepare("DELETE FROM patient_users WHERE patient_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $stmt->close();
            
            // Now delete the patient
            $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                // Commit transaction
                $conn->commit();
                // Patient deleted successfully - redirect to patient list
                header('Location: patients.php?message=' . urlencode('Patient deleted successfully'));
                exit();
            } else {
                $conn->rollback();
                $error = "Patient not found or already deleted.";
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error deleting patient: " . $e->getMessage();
        }
    }
}

// Get user role and ID
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$patient_details = null;
if ($viewing_patient_id) {
    // Check if medical_history table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
    $hasHistoryTable = $checkTable && $checkTable->num_rows > 0;
    
    if (isAdmin()) {
        // Admin can view any patient
        if ($hasHistoryTable) {
            $stmt = $conn->prepare("SELECT p.*, 
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
               (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
               (SELECT COUNT(*) FROM medical_history mh WHERE mh.patient_id = p.id AND mh.status = 'active') as history_count
            FROM patients p WHERE p.id = ?");
        } else {
            $stmt = $conn->prepare("SELECT p.*, 
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
               (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
               (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND m.history_type IS NOT NULL AND m.history_type != '') as history_count
            FROM patients p WHERE p.id = ?");
        }
        $stmt->bind_param("i", $viewing_patient_id);
    } else {
        // Doctor can only view their assigned patients
        if ($hasHistoryTable) {
            $stmt = $conn->prepare("SELECT p.*, 
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
               (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
               (SELECT COUNT(*) FROM medical_history mh WHERE mh.patient_id = p.id AND mh.status = 'active') as history_count
            FROM patients p
            JOIN appointments a ON p.id = a.patient_id
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE p.id = ? AND u.id = ?");
        } else {
            $stmt = $conn->prepare("SELECT p.*, 
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as appointment_count,
               (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND (m.history_type IS NULL OR m.history_type = '')) as record_count,
               (SELECT COUNT(*) FROM medical_records m WHERE m.patient_id = p.id AND m.history_type IS NOT NULL AND m.history_type != '') as history_count
            FROM patients p
            JOIN appointments a ON p.id = a.patient_id
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE p.id = ? AND u.id = ?");
        }
        $stmt->bind_param("ii", $viewing_patient_id, $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_details = $result->fetch_assoc();
    
    // If doctor is trying to access a patient not assigned to them, redirect
    if (!isAdmin() && $viewing_patient_id && !$patient_details) {
        header('Location: patients.php');
        exit;
    }
    
    // Fetch patient profile image from patient_users table if patient exists
    if ($patient_details) {
        $check_column = $conn->query("SHOW COLUMNS FROM patient_users LIKE 'profile_image'");
        $has_profile_image = $check_column && $check_column->num_rows > 0;
        
        if ($has_profile_image) {
            $profile_stmt = $conn->prepare("SELECT profile_image FROM patient_users WHERE patient_id = ? LIMIT 1");
            $profile_stmt->bind_param("i", $viewing_patient_id);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            if ($profile_row = $profile_result->fetch_assoc()) {
                $patient_details['profile_image'] = !empty($profile_row['profile_image']) ? $profile_row['profile_image'] : null;
            } else {
                $patient_details['profile_image'] = null;
            }
            $profile_stmt->close();
        } else {
            $patient_details['profile_image'] = null;
        }
    }
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$searchCondition = '';
$params = [];
$types = '';

// Pagination setup
$limit = 8; // Maximum 8 patients per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Build search condition
if ($search) {
    $searchCondition = "AND (p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    $types = 'sssss';
}

// Check if medical_history table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
$hasHistoryTable = $checkTable && $checkTable->num_rows > 0;

// Build count query to get total number of patients
if (isAdmin()) {
    // Admin sees all patients
    $countQuery = "SELECT COUNT(*) as total FROM patients p WHERE 1=1 $searchCondition";
} else {
    // Doctor sees only their assigned patients
    $countQuery = "SELECT COUNT(DISTINCT p.id) as total 
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE u.id = ? $searchCondition";
}

// Execute count query
$countParams = [];
$countTypes = '';
if (!isAdmin()) {
    $countParams[] = $userId;
    $countTypes = 'i';
}
if ($search) {
    $countSearchParam = "%$search%";
    $countParams = array_merge($countParams, [$countSearchParam, $countSearchParam, $countSearchParam, $countSearchParam, $countSearchParam]);
    $countTypes .= 'sssss';
}

$countStmt = $conn->prepare($countQuery);
if ($countParams) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalPatients = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalPatients / $limit);
$countStmt->close();

// Build the main query based on user role
if (isAdmin()) {
    // Admin sees all patients
    if ($hasHistoryTable) {
        $query = "SELECT p.*, 
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as appointment_count,
            (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.id AND (history_type IS NULL OR history_type = '')) as record_count,
            (SELECT COUNT(*) FROM medical_history WHERE patient_id = p.id AND status = 'active') as history_count
        FROM patients p 
        WHERE 1=1 $searchCondition 
        ORDER BY p.last_name, p.first_name
        LIMIT ? OFFSET ?";
    } else {
        $query = "SELECT p.*, 
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as appointment_count,
            (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.id AND (history_type IS NULL OR history_type = '')) as record_count,
            (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.id AND history_type IS NOT NULL AND history_type != '') as history_count
        FROM patients p 
        WHERE 1=1 $searchCondition 
        ORDER BY p.last_name, p.first_name
        LIMIT ? OFFSET ?";
    }
    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
} else {
    // Doctor sees only their assigned patients
    if ($hasHistoryTable) {
        $query = "SELECT DISTINCT p.*, 
            (SELECT COUNT(*) FROM appointments a2 WHERE a2.patient_id = p.id) as appointment_count,
            (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.id AND (history_type IS NULL OR history_type = '')) as record_count,
            (SELECT COUNT(*) FROM medical_history WHERE patient_id = p.id AND status = 'active') as history_count
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE u.id = ? $searchCondition 
        ORDER BY p.last_name, p.first_name
        LIMIT ? OFFSET ?";
    } else {
        $query = "SELECT DISTINCT p.*, 
            (SELECT COUNT(*) FROM appointments a2 WHERE a2.patient_id = p.id) as appointment_count,
            (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.id AND (history_type IS NULL OR history_type = '')) as record_count,
            (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.id AND history_type IS NOT NULL AND history_type != '') as history_count
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE u.id = ? $searchCondition 
        ORDER BY p.last_name, p.first_name
        LIMIT ? OFFSET ?";
    }
    
    // Add doctor ID as first parameter, then limit and offset
    array_unshift($params, $userId);
    $types = 'i' . $types;
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
}

$stmt = $conn->prepare($query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result();

// Fetch profile images for all patients in the list
$check_column = $conn->query("SHOW COLUMNS FROM patient_users LIKE 'profile_image'");
$has_profile_image_column = $check_column && $check_column->num_rows > 0;
$patient_profile_images = [];

if ($has_profile_image_column && $patients->num_rows > 0) {
    // Store patient IDs
    $patients->data_seek(0);
    $patient_ids = [];
    while ($patient = $patients->fetch_assoc()) {
        $patient_ids[] = $patient['id'];
    }
    
    // Fetch all profile images at once
    if (!empty($patient_ids)) {
        $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
        $profile_query = "SELECT patient_id, profile_image FROM patient_users WHERE patient_id IN ($placeholders)";
        $profile_stmt = $conn->prepare($profile_query);
        $profile_types = str_repeat('i', count($patient_ids));
        $profile_stmt->bind_param($profile_types, ...$patient_ids);
        $profile_stmt->execute();
        $profile_result = $profile_stmt->get_result();
        
        while ($row = $profile_result->fetch_assoc()) {
            if (!empty($row['profile_image'])) {
                $patient_profile_images[$row['patient_id']] = $row['profile_image'];
            }
        }
        $profile_stmt->close();
    }
    
    // Reset result pointer
    $patients->data_seek(0);
}

include 'includes/header.php';
?>

<style>
.patient-main { padding: 20px; overflow-y: auto; }
.patient-list-item { padding: 12px 16px; border-bottom: 1px solid #e9ecef; cursor: pointer; }
.patient-list-item:hover { background-color: #e9ecef; }
.patient-list-item.active { background-color: #007bff; color: white; }
.patient-avatar {
    width: 40px; height: 40px; border-radius: 50%; background: #007bff; color: white;
    display: flex; align-items: center; justify-content: center; font-weight: bold;
    flex-shrink: 0;
}
.patient-list-avatar {
    width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
    border: 2px solid #dee2e6; flex-shrink: 0;
}
.patient-list-item.active .patient-avatar {
    background: rgba(255, 255, 255, 0.3);
}
.patient-list-item.active .patient-list-avatar {
    border-color: rgba(255, 255, 255, 0.5);
}
.patient-header { background: #f8f9fa; padding: 20px; border-bottom: 1px solid #dee2e6; }
.patient-tabs { background: white; border-bottom: 1px solid #dee2e6; padding: 0 20px; }
.patient-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #6c757d; padding: 12px 16px; }
.patient-tabs .nav-link.active { color: #007bff; border-bottom-color: #007bff; }
.patient-content { padding: 20px; }
.empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
.empty-state i { font-size: 3rem; margin-bottom: 20px; color: #dee2e6; }

/* Pagination Styles */
.pagination {
    margin-top: 15px;
    margin-bottom: 0;
}

.pagination .page-link {
    color: #007bff;
    border-color: #dee2e6;
}

.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
    pointer-events: none;
    cursor: auto;
    background-color: #fff;
    border-color: #dee2e6;
}

/* Medical Certificate Styles */
.medical-certificate {
    background: white;
    padding: 40px;
    margin: 20px 0;
    border: 2px solid #000;
    font-family: 'Times New Roman', serif;
    font-size: 14px;
    line-height: 1.6;
    max-width: 800px;
    margin: 0 auto;
}

.certificate-header {
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #000;
    padding-bottom: 20px;
}

.clinic-logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 15px;
    background: #007bff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    font-weight: bold;
}

.clinic-name {
    font-size: 24px;
    font-weight: bold;
    margin: 10px 0 5px;
    color: #000;
}

.clinic-subtitle {
    font-size: 16px;
    color: #666;
    margin-bottom: 10px;
}

.clinic-address {
    font-size: 12px;
    color: #666;
}

.certificate-title {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    margin: 30px 0;
    text-decoration: underline;
    color: #000;
}

.certificate-content {
    text-align: justify;
    margin: 20px 0;
}

.fill-blank {
    border-bottom: 1px solid #000;
    min-width: 100px;
    display: inline-block;
    margin: 0 5px;
    text-align: center;
    padding: 2px 5px;
}

.certificate-footer {
    margin-top: 40px;
    text-align: right;
}

.signature-section {
    margin-top: 60px;
    text-align: right;
}

.signature-line {
    border-bottom: 1px solid #000;
    width: 300px;
    margin: 20px 0 5px auto;
}

.print-only {
    display: none;
}

/* Print Styles */
@media print {
    * {
        visibility: hidden;
    }
    
    .medical-certificate,
    .medical-certificate * {
        visibility: visible;
    }
    
    .medical-certificate {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 20px;
        border: 2px solid #000 !important;
        box-shadow: none;
        background: white !important;
        font-size: 12px;
        page-break-inside: avoid;
    }
    
    .clinic-logo {
        background: #000 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .certificate-header {
        border-bottom: 2px solid #000 !important;
    }
    
    .signature-line {
        border-bottom: 1px solid #000 !important;
    }
    
    .editable-field {
        border: none !important;
        border-bottom: 1px solid #000 !important;
        background: transparent !important;
    }
    
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    @page {
        margin: 0.5in;
        size: A4;
    }
}

.editable-field {
    border: 1px dashed #ccc;
    padding: 2px 5px;
    min-width: 100px;
    display: inline-block;
    cursor: text;
}

.editable-field:focus {
    outline: 2px solid #007bff;
    border-color: #007bff;
}

.editable-field.large {
    min-width: 200px;
}

.editable-field.medium {
    min-width: 150px;
}

.editable-field.small {
    min-width: 80px;
}

/* Modal Styles for Appointment Details */
.modal-lg {
    max-width: 800px;
}

.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-body .row {
    margin-bottom: 0;
}

.modal-body h6 {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 8px;
    margin-bottom: 15px;
}

.modal-body .text-muted {
    font-size: 0.9rem;
}

.modal-body .badge {
    font-size: 0.8rem;
    padding: 6px 12px;
}

.modal-footer .btn {
    min-width: 120px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-lg {
        max-width: 95%;
        margin: 10px auto;
    }
    
    .modal-body {
        max-height: 60vh;
    }
    
    .modal-body .col-md-6 {
        margin-bottom: 20px;
    }
    
    /* Main layout adjustments */
    .patient-main {
        padding: 10px;
    }
    
    /* Patient list column */
    .col-md-4 {
        margin-bottom: 20px;
    }
    
    /* Patient header responsive */
    .patient-header {
        flex-direction: column;
        align-items: flex-start !important;
        padding: 15px;
        position: relative;
    }
    
    .patient-header > div:first-child {
        width: 100%;
        margin-bottom: 15px;
    }
    
    .patient-header .dropdown {
        position: absolute;
        top: 15px;
        right: 15px;
    }
    
    .patient-header .d-flex.align-items-center {
        padding-right: 50px;
    }
    
    /* Patient tabs responsive */
    .patient-tabs {
        padding: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        position: relative;
        scrollbar-width: thin;
        scrollbar-color: #007bff #f8f9fa;
    }
    
    .patient-tabs::-webkit-scrollbar {
        height: 4px;
    }
    
    .patient-tabs::-webkit-scrollbar-track {
        background: #f8f9fa;
    }
    
    .patient-tabs::-webkit-scrollbar-thumb {
        background: #007bff;
        border-radius: 2px;
    }
    
    .patient-tabs .nav {
        flex-wrap: nowrap;
        white-space: nowrap;
        overflow-x: auto;
        padding: 0 10px;
    }
    
    .patient-tabs .nav-link {
        padding: 10px 12px;
        font-size: 0.85rem;
        white-space: nowrap;
        min-width: fit-content;
    }
    
    .patient-tabs .nav-link i {
        margin-right: 4px;
    }
    
    /* Patient content area */
    .patient-content {
        padding: 15px 10px;
    }
    
    /* Cards responsive */
    .card {
        margin-bottom: 15px;
    }
    
    .card-header {
        padding: 12px 15px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    /* Tables responsive */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        font-size: 0.85rem;
        min-width: 600px;
    }
    
    /* Buttons responsive */
    .btn {
        padding: 8px 12px;
        font-size: 0.875rem;
    }
    
    .btn-sm {
        padding: 6px 10px;
        font-size: 0.8rem;
    }
    
    /* Form elements responsive */
    .form-control,
    .form-select {
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    /* Flex containers */
    .d-flex {
        flex-wrap: wrap;
    }
    
    /* Grid adjustments */
    .row {
        margin-left: -10px;
        margin-right: -10px;
    }
    
    .row > * {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    /* Statistics cards */
    .col-4 {
        margin-bottom: 10px;
    }
    
    /* Search form */
    .input-group {
        flex-wrap: nowrap;
    }
    
    .input-group .form-control {
        font-size: 16px;
    }
    
    /* Empty state */
    .empty-state {
        padding: 40px 15px;
    }
    
    .empty-state i {
        font-size: 2rem;
    }
    
    /* Patient list item */
    .patient-list-item {
        padding: 10px 12px;
    }
    
    .patient-avatar,
    .patient-list-avatar {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }
    
    /* Medical certificate modal */
    .medical-certificate {
        padding: 20px;
        font-size: 12px;
    }
    
    /* Dropdown menus */
    .dropdown-menu {
        font-size: 0.9rem;
    }
    
    /* Badges */
    .badge {
        font-size: 0.75rem;
        padding: 4px 8px;
    }
    
    /* Alert messages */
    .alert {
        padding: 12px 15px;
        font-size: 0.9rem;
    }
    
    /* Modal adjustments */
    .modal-dialog {
        margin: 10px;
    }
    
    .modal-content {
        border-radius: 8px;
    }
    
    .modal-header,
    .modal-footer {
        padding: 12px 15px;
    }
    
    .modal-body {
        padding: 15px;
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }
}

/* Extra small devices */
@media (max-width: 576px) {
    .patient-main {
        padding: 5px;
    }
    
    .patient-header {
        padding: 10px;
    }
    
    .patient-content {
        padding: 10px 5px;
    }
    
    .patient-tabs .nav-link {
        padding: 8px 10px;
        font-size: 0.8rem;
    }
    
    .patient-tabs .nav-link i {
        display: none;
    }
    
    .card-header h5,
    .card-header h6 {
        font-size: 1rem;
    }
    
    .btn {
        padding: 6px 10px;
        font-size: 0.8rem;
    }
    
    .table {
        font-size: 0.8rem;
    }
    
    .col-md-6 {
        margin-bottom: 15px;
    }
    
    .col-md-3 {
        margin-bottom: 10px;
    }
    
    /* Hide some icons on very small screens */
    .patient-header .fas,
    .patient-header .far {
        font-size: 0.9rem;
    }
}

/* Additional responsive styles for tab content */
@media (max-width: 768px) {
    /* Overview tab */
    .table-borderless td {
        display: block;
        width: 100% !important;
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .table-borderless td:first-child {
        font-weight: 600;
        color: #495057;
        margin-bottom: 5px;
    }
    
    .table-borderless tr:last-child td {
        border-bottom: none;
    }
    
    /* Medical records tab */
    .record-card {
        margin-bottom: 15px;
    }
    
    .record-header {
        flex-direction: column;
        align-items: flex-start !important;
        padding: 12px;
    }
    
    .record-header > div:first-child {
        margin-bottom: 10px;
    }
    
    .record-body {
        padding: 12px;
    }
    
    .month-card-header {
        padding: 12px 15px;
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .month-card-header h6 {
        font-size: 1rem;
        margin-bottom: 8px;
    }
    
    .vital-signs-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    /* Appointments tab */
    .record-card .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .record-card .btn-group {
        width: 100%;
        margin-top: 10px;
        justify-content: flex-start;
    }
    
    .record-card .btn-group .btn {
        flex: 1;
    }
    
    .month-header {
        padding: 10px 12px !important;
    }
    
    /* Vitals tab */
    .vital-card {
        margin-bottom: 10px;
    }
    
    .vital-card .card-body {
        padding: 15px 10px;
    }
    
    .vital-card h4 {
        font-size: 1.5rem;
    }
    
    .vitals-date-header {
        padding: 12px 15px;
    }
    
    .vitals-date-body {
        padding: 10px;
    }
    
    /* Prescriptions tab */
    .prescription-card {
        margin-bottom: 15px;
    }
    
    .prescription-header {
        flex-direction: column;
        align-items: flex-start !important;
        padding: 12px;
    }
    
    .prescription-body {
        padding: 12px;
    }
    
    /* Medical history tab */
    .history-type-card {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    /* Requests tab */
    .medical-form {
        padding: 15px;
        font-size: 12px;
    }
    
    .checkbox-grid {
        grid-template-columns: 1fr;
    }
    
    /* General responsive utilities */
    .d-flex.justify-content-between {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .d-flex.justify-content-between > * {
        margin-bottom: 10px;
    }
    
    .d-flex.justify-content-between > *:last-child {
        margin-bottom: 0;
    }
    
    /* Button groups */
    .btn-group {
        flex-wrap: wrap;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
    }
    
    /* Dropdown adjustments */
    .dropdown-toggle::after {
        margin-left: 0.5em;
    }
    
    /* Text alignment */
    .text-center {
        text-align: center !important;
    }
    
    .text-md-end {
        text-align: left !important;
    }
    
    /* Spacing adjustments */
    .mb-4 {
        margin-bottom: 1rem !important;
    }
    
    .mb-3 {
        margin-bottom: 0.75rem !important;
    }
    
    .mt-3 {
        margin-top: 0.75rem !important;
    }
    
    /* Form groups */
    .form-group,
    .mb-3 {
        margin-bottom: 1rem;
    }
    
    /* Input groups */
    .input-group {
        flex-direction: column;
    }
    
    .input-group .form-control,
    .input-group .btn {
        border-radius: 0.375rem;
        margin-bottom: 5px;
    }
    
    .input-group .btn {
        width: 100%;
    }
}

@media (max-width: 576px) {
    /* Even smaller adjustments */
    .table-borderless td {
        font-size: 0.9rem;
    }
    
    .vital-signs-grid {
        grid-template-columns: 1fr;
    }
    
    .record-card,
    .prescription-card,
    .history-type-card {
        border-radius: 6px;
    }
    
    .card-header h5,
    .card-header h6 {
        font-size: 0.95rem;
    }
    
    .modal-dialog {
        margin: 5px;
    }
    
    .modal-body {
        padding: 10px;
    }
}
</style>

<div class="patient-main">
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success alert-dismissible">
            <?php echo htmlspecialchars($_GET['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($show_delete_confirmation) && $show_delete_confirmation): ?>
        <div class="alert alert-warning alert-dismissible">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
            <p class="mb-2">This patient has existing records that will be deleted:</p>
            <ul class="mb-3">
                <?php if ($delete_appointment_count > 0): ?>
                    <li><strong><?php echo $delete_appointment_count; ?></strong> appointment(s)</li>
                <?php endif; ?>
                <?php if ($delete_record_count > 0): ?>
                    <li><strong><?php echo $delete_record_count; ?></strong> medical record(s)</li>
                <?php endif; ?>
                <?php if ($delete_transaction_count > 0): ?>
                    <li><strong><?php echo $delete_transaction_count; ?></strong> transaction(s)</li>
                <?php endif; ?>
                <?php if ($delete_prescription_count > 0): ?>
                    <li><strong><?php echo $delete_prescription_count; ?></strong> prescription(s)</li>
                <?php endif; ?>
            </ul>
            <p class="mb-3"><strong>Warning:</strong> Deleting this patient will permanently delete all associated records including appointments, medical records, transactions, prescriptions, and other related data. This action cannot be undone.</p>
            <div>
                <a href="patients.php?delete=<?php echo $delete_patient_id; ?>&confirm=yes" class="btn btn-success me-2" onclick="return confirmLink(event, 'Are you absolutely sure? This will permanently delete the patient and ALL associated records. This action cannot be undone.');">
                    <i class="fas fa-check me-1"></i> Yes, Delete Patient
                </a>
                <a href="patients.php<?php echo $viewing_patient_id ? '?patient_id=' . $viewing_patient_id . '&tab=' . $active_tab : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-danger">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
            </div>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">
                <?php if (isAdmin()): ?>
                    <i class="fas fa-users me-2"></i>All Patient Records
                <?php else: ?>
                    <i class="fas fa-user-md me-2"></i>My Patients
                <?php endif; ?>
            </h4>
            <small class="text-muted">
                <?php if (isAdmin()): ?>
                    Manage all patient records in the system
                <?php else: ?>
                    View patients assigned to you
                <?php endif; ?>
            </small>
        </div>
        <?php if (isAdmin()): ?>
            <a href="add_patient.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Patient</a>
        <?php endif; ?>
    </div>

    <form method="GET" id="searchForm" class="input-group mb-4">
        <?php if ($viewing_patient_id): ?>
            <input type="hidden" name="patient_id" value="<?php echo $viewing_patient_id; ?>">
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
        <?php endif; ?>
        <input type="text" class="form-control" id="searchInput" name="search" placeholder="Search by name, email, or phone" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
    </form>
    <script>
    // Reset to page 1 when searching
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        const form = this;
        const searchInput = form.querySelector('input[name="search"]');
        const currentSearch = '<?php echo htmlspecialchars($search); ?>';
        const newSearch = searchInput.value.trim();
        
        // If search term changed, reset to page 1
        if (currentSearch !== newSearch) {
            // Remove page parameter or set it to 1
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('page');
            if (newSearch) {
                urlParams.set('search', newSearch);
            } else {
                urlParams.delete('search');
            }
            // Preserve patient_id and tab if they exist
            <?php if ($viewing_patient_id): ?>
            urlParams.set('patient_id', '<?php echo $viewing_patient_id; ?>');
            urlParams.set('tab', '<?php echo $active_tab; ?>');
            <?php endif; ?>
            
            window.location.href = 'patients.php?' + urlParams.toString();
            e.preventDefault();
        }
    });
    </script>

    <div class="row">
        <div class="col-md-4">
            <div class="list-group" id="patientListContainer">
                <?php if ($patients->num_rows > 0): ?>
                    <?php 
                    // Reset result pointer to iterate again
                    $patients->data_seek(0);
                    while ($patient = $patients->fetch_assoc()): 
                        $fullName = htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);
                        $searchableText = strtolower($fullName . ' ' . htmlspecialchars($patient['email'] ?? '') . ' ' . htmlspecialchars($patient['phone'] ?? ''));
                    ?>
                        <div class="patient-list-item <?php echo $viewing_patient_id == $patient['id'] ? 'active' : ''; ?>" 
                             onclick="viewPatient(<?php echo $patient['id']; ?>)"
                             data-search="<?php echo htmlspecialchars($searchableText); ?>"
                             data-patient-id="<?php echo $patient['id']; ?>">
                            <div class="d-flex align-items-center">
                                <?php 
                                $list_profile_image = isset($patient_profile_images[$patient['id']]) && !empty($patient_profile_images[$patient['id']]) 
                                    ? htmlspecialchars($patient_profile_images[$patient['id']]) 
                                    : null;
                                $list_timestamp = time();
                                ?>
                                <?php if ($list_profile_image): ?>
                                    <img src="<?php echo $list_profile_image; ?>?t=<?php echo $list_timestamp; ?>" 
                                         alt="Profile" 
                                         class="patient-list-avatar" 
                                         data-patient-id="<?php echo $patient['id']; ?>"
                                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #dee2e6; flex-shrink: 0;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="patient-avatar" style="display: none;"><?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?></div>
                                <?php else: ?>
                                    <div class="patient-avatar"><?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?></div>
                                <?php endif; ?>
                                <div class="ms-3">
                                    <strong><?php echo $fullName; ?></strong><br>
                                    <small>
                                        <?php
                                        $birthDate = new DateTime($patient['date_of_birth']);
                                        $today = new DateTime();
                                        $age = $today->diff($birthDate)->y;
                                        echo $age . ' years • ' . htmlspecialchars($patient['sex']);
                                        ?>
                                    </small><br>
                                    <small>
                                        <i class="fas fa-calendar-check"></i> <?php echo $patient['appointment_count']; ?>
                                        <i class="fas fa-file-medical ms-2"></i> <?php echo $patient['record_count']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <div class="text-muted text-center p-3" id="noSearchResults" style="display: none;">
                        <i class="fas fa-search fa-2x mb-2"></i>
                        <p>No patients found matching your search</p>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center p-3" id="noPatientsMessage">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <p>
                            <?php if (isAdmin()): ?>
                                No patients found
                            <?php else: ?>
                                No patients assigned to you yet
                            <?php endif; ?>
                        </p>
                        <?php if (!isAdmin()): ?>
                            <small class="text-muted">Patients will appear here once they have appointments with you</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Patient list pagination" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <!-- Previous button -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php 
                                $prevParams = $_GET;
                                $prevParams['page'] = max(1, $page - 1);
                                echo '?' . http_build_query($prevParams);
                            ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Page numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        // Show first page if not in range
                        if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php 
                                    $firstParams = $_GET;
                                    $firstParams['page'] = 1;
                                    echo '?' . http_build_query($firstParams);
                                ?>">1</a>
                            </li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php 
                                    $pageParams = $_GET;
                                    $pageParams['page'] = $i;
                                    echo '?' . http_build_query($pageParams);
                                ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Show last page if not in range -->
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php 
                                    $lastParams = $_GET;
                                    $lastParams['page'] = $totalPages;
                                    echo '?' . http_build_query($lastParams);
                                ?>"><?php echo $totalPages; ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Next button -->
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php 
                                $nextParams = $_GET;
                                $nextParams['page'] = min($totalPages, $page + 1);
                                echo '?' . http_build_query($nextParams);
                            ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center text-muted small mt-2">
                    Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $limit, $totalPatients); ?> of <?php echo $totalPatients; ?> patients
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-8">
            <?php if ($patient_details): ?>
                                 <div class="patient-header d-flex justify-content-between align-items-start">
                     <div class="d-flex align-items-center">
                         <?php 
                         $profile_image = isset($patient_details['profile_image']) && !empty($patient_details['profile_image']) 
                             ? htmlspecialchars($patient_details['profile_image']) 
                             : null;
                         $timestamp = time(); // Cache-busting timestamp
                         ?>
                         <?php if ($profile_image): ?>
                             <img src="<?php echo $profile_image; ?>?t=<?php echo $timestamp; ?>" 
                                  alt="Profile" 
                                  class="rounded-circle me-3" 
                                  id="admin-patient-profile-image"
                                  style="width: 60px; height: 60px; object-fit: cover; border: 2px solid #dee2e6;"
                                  onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                             <div class="patient-avatar me-3" style="width: 60px; height: 60px; font-size: 1.5rem; display: none;">
                                 <?php echo strtoupper(substr($patient_details['first_name'], 0, 1) . substr($patient_details['last_name'], 0, 1)); ?>
                             </div>
                         <?php else: ?>
                             <div class="patient-avatar me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                 <?php echo strtoupper(substr($patient_details['first_name'], 0, 1) . substr($patient_details['last_name'], 0, 1)); ?>
                             </div>
                         <?php endif; ?>
                         <div>
                             <h4><?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?></h4>
                             <p class="text-muted">
                                 <?php
                                 $birthDate = new DateTime($patient_details['date_of_birth']);
                                 $today = new DateTime();
                                 $age = $today->diff($birthDate)->y;
                                 echo $age . ' years old • ' . htmlspecialchars($patient_details['sex']);
                                 ?>
                             </p>
                             <p class="text-muted">
                                 <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars(formatPhoneNumber($patient_details['phone'])); ?>
                                 <i class="fas fa-envelope ms-3 me-1"></i> <?php echo htmlspecialchars($patient_details['email']); ?>
                             </p>
                             <p class="text-muted mb-0">
                                 <i class="fas fa-calendar-alt me-1"></i> Patient Since: <?php echo date('F j, Y', strtotime($patient_details['created_at'] ?? $patient_details['date_of_birth'])); ?>
                             </p>
                         </div>
                     </div>

                                         <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                        <ul class="dropdown-menu">
                            <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="edit_patient.php?id=<?php echo $patient_details['id']; ?>"><i class="fas fa-edit me-2"></i>Edit Patient</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="patients.php?delete=<?php echo $patient_details['id']; ?>" onclick="return confirmLink(event, 'Are you sure?');"><i class="fas fa-trash me-2"></i>Delete Patient</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                 </div>

                                  <div class="patient-tabs">
                     <ul class="nav nav-tabs">
                         <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'overview' ? 'active' : ''; ?>" href="?patient_id=<?php echo $viewing_patient_id; ?>&tab=overview"><i class="fas fa-user me-1"></i>Overview</a></li>
                         <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'medical_records' ? 'active' : ''; ?>" href="?patient_id=<?php echo $viewing_patient_id; ?>&tab=medical_records"><i class="fas fa-file-medical me-1"></i>Medical Records</a></li>
                         <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'medical_history' ? 'active' : ''; ?>" href="?patient_id=<?php echo $viewing_patient_id; ?>&tab=medical_history"><i class="fas fa-history me-1"></i>Medical History</a></li>
                         <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'vitals' ? 'active' : ''; ?>" href="?patient_id=<?php echo $viewing_patient_id; ?>&tab=vitals"><i class="fas fa-heartbeat me-1"></i>Vitals</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'appointments' ? 'active' : ''; ?>" href="?patient_id=<?php echo $viewing_patient_id; ?>&tab=appointments"><i class="fas fa-calendar-check me-1"></i>Appointments</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'prescriptions' ? 'active' : ''; ?>" href="?patient_id=<?php echo $viewing_patient_id; ?>&tab=prescriptions"><i class="fas fa-prescription-bottle-alt me-1"></i>Prescriptions</a></li>
                         <?php if (!isDoctor()): ?>
                         <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'requests' ? 'active' : ''; ?>" href="?patient_id=<?php echo $viewing_patient_id; ?>&tab=requests"><i class="fas fa-file-alt me-1"></i>Requests</a></li>
                         <?php endif; ?>
                     </ul>
                 </div>

                 <div class="patient-content">
                     <?php
                     switch ($active_tab) {
                         case 'overview': include 'patient_overview.php'; break;
                         case 'medical_records': include 'patient_medical_records.php'; break;
                         case 'medical_history': include 'patient_medical_history.php'; break;
                        case 'vitals': include 'patient_vitals.php'; break;
                        case 'appointments': include 'patient_appointment.php'; break;
                        case 'prescriptions': include 'patient_prescriptions.php'; break;
                         case 'requests': 
                             if (isDoctor()) {
                                 // Redirect doctors away from requests tab
                                 header('Location: ?patient_id=' . $viewing_patient_id . '&tab=overview');
                                 exit;
                             }
                             include 'patient_requests.php'; 
                             break;
                         default: include 'patient_overview.php';
                     }
                     ?>
                 </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-md"></i>
                    <h5>Select a patient to view details</h5>
                    <p>Choose a patient from the list to view their medical records, appointments, and more.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Medical Certificate Modal -->
<div class="modal fade" id="medicalCertificateModal" tabindex="-1" aria-labelledby="medicalCertificateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header no-print">
                <h5 class="modal-title" id="medicalCertificateModalLabel">Medical Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="medical-certificate" id="medicalCertificate">
                    <div class="certificate-header">
                        <div class="clinic-logo">
                                <img src="img/logo.png" alt="Clinic Logo" style="height: 80px; width: auto;">
                        </div>
                        <div class="clinic-name">MHAVIS MEDICAL CENTER</div>
                        <div class="clinic-subtitle">(Mhavis Medical & Diagnostic Center)</div>
                        <div class="clinic-address">
                            Pub-3 Ibabang Cantic<br>
                            (046) 415-1386, 09488419847
                        </div>
                    </div>

                    <div class="certificate-title">MEDICAL CERTIFICATE</div>

                    <div style="text-align: right; margin-bottom: 20px;">
                        <strong>Date:</strong> <span class="editable-field medium" contenteditable="true" id="certDate"><?php echo date('M d, Y'); ?></span>
                    </div>

                    <div class="certificate-content">
                        <p>
                            This is to certify that Ms./Mr./Mrs. 
                            <span class="editable-field large" contenteditable="true" id="patientName">
                                <?php echo $patient_details ? htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']) : '____________________'; ?>
                            </span>, 
                            <span class="editable-field small" contenteditable="true" id="patientAge">
                                <?php 
                                if ($patient_details) {
                                    $birthDate = new DateTime($patient_details['date_of_birth']);
                                    $today = new DateTime();
                                    $age = $today->diff($birthDate)->y;
                                    echo $age;
                                } else {
                                    echo '___';
                                }
                                ?>
                            </span>
                            years old, male/female of 
                            <span class="editable-field large" contenteditable="true" id="address">____________________</span>
                            was seen, examined, and treated on 
                            <span class="editable-field medium" contenteditable="true" id="treatmentDate">____________________</span>
                            / admitted on 
                            <span class="editable-field medium" contenteditable="true" id="admissionDate">____________________</span>
                            to 
                            <span class="editable-field medium" contenteditable="true" id="dischargeTo">____________________</span>,  
                            with chief complaint of 
                            <span class="editable-field large" contenteditable="true" id="complaint">____________________</span>
                            and working diagnosis of 
                            <span class="editable-field large" contenteditable="true" id="diagnosis">____________________</span>.
                        </p>
                        <br>
                        <p>
                            <strong>REMARKS:</strong>
                        </p>
                        <div style="border-bottom: 1px solid #000; min-height: 60px; margin: 10px 0;">
                            <div class="editable-field" contenteditable="true" id="remarks" style="border: none; width: 100%; min-height: 50px; padding: 5px;">
                                
                            </div>
                        </div>
                        <br>
                        <p style="margin-top: 30px;">
                            This certification is issued upon the request of the patient for whatever purpose it may serve except medicolegal services.
                        </p>
                    </div>

                    <div class="signature-section">
                        <div class="signature-line"></div>
                        <div style="text-align: center; margin-top: 5px;">
                            Physician's Signature over Printed Name
                        </div>
                        <div style="margin-top: 15px;">
                            <strong>License No.</strong> <span class="editable-field medium" contenteditable="true" id="licenseNo">____________________</span>
                        </div>
                        <div style="margin-top: 10px;">
                            <strong>PTR. No.</strong> <span class="editable-field medium" contenteditable="true" id="ptrNo">____________________</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer no-print">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printCertificate()">
                    <i class="fas fa-print me-1"></i>Print Certificate
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewPatient(patientId) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('patient_id', patientId);
    urlParams.set('tab', 'overview');
    // Preserve search but reset page to 1 when viewing a patient
    urlParams.delete('page');
    window.location.href = 'patients.php?' + urlParams.toString();
}


function generateMedicalCertificate() {
    // Reset all editable fields to default values
    document.getElementById('certDate').textContent = new Date().toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit'
    });
    
    <?php if ($patient_details): ?>
    document.getElementById('patientName').textContent = '<?php echo addslashes($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?>';
    document.getElementById('patientAge').textContent = '<?php 
        $birthDate = new DateTime($patient_details['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        echo $age;
    ?>';
    <?php else: ?>
    document.getElementById('patientName').textContent = '____________________';
    document.getElementById('patientAge').textContent = '___';
    <?php endif; ?>
    
    // Clear other fields
    document.getElementById('address').textContent = '____________________';
    document.getElementById('treatmentDate').textContent = '____________________';
    document.getElementById('admissionDate').textContent = '____________________';
    document.getElementById('dischargeTo').textContent = '____________________';
    document.getElementById('complaint').textContent = '____________________';
    document.getElementById('diagnosis').textContent = '____________________';
    document.getElementById('remarks').textContent = '';
    document.getElementById('licenseNo').textContent = '____________________';
    document.getElementById('ptrNo').textContent = '____________________';
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('medicalCertificateModal'));
    modal.show();
}

function printCertificate() {
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Get the certificate content
    const certificateContent = document.getElementById('medicalCertificate').innerHTML;
    
    // Create the print document
    const printDocument = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Medical Certificate</title>
            <style>
                body {
                    margin: 0;
                    padding: 20px;
                    font-family: 'Times New Roman', serif;
                    background: white;
                }
                
                .medical-certificate {
                    background: white;
                    padding: 40px;
                    margin: 0;
                    border: 2px solid #000;
                    font-family: 'Times New Roman', serif;
                    font-size: 14px;
                    line-height: 1.6;
                    max-width: 800px;
                    margin: 0 auto;
                }

                .certificate-header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 20px;
                }

                .clinic-logo {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 15px;
                    background: #000;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 24px;
                    font-weight: bold;
                }

                .clinic-name {
                    font-size: 24px;
                    font-weight: bold;
                    margin: 10px 0 5px;
                    color: #000;
                }

                .clinic-subtitle {
                    font-size: 16px;
                    color: #666;
                    margin-bottom: 10px;
                }

                .clinic-address {
                    font-size: 12px;
                    color: #666;
                }

                .certificate-title {
                    text-align: center;
                    font-size: 22px;
                    font-weight: bold;
                    margin: 30px 0;
                    text-decoration: underline;
                    color: #000;
                }

                .certificate-content {
                    text-align: justify;
                    margin: 20px 0;
                }

                .signature-section {
                    margin-top: 60px;
                    text-align: right;
                }

                .signature-line {
                    border-bottom: 1px solid #000;
                    width: 300px;
                    margin: 20px 0 5px auto;
                }

                .editable-field {
                    border: none;
                    border-bottom: 1px solid #000;
                    min-width: 100px;
                    display: inline-block;
                    margin: 0 5px;
                    text-align: center;
                    padding: 2px 5px;
                    background: transparent;
                }

                .editable-field.large {
                    min-width: 200px;
                }

                .editable-field.medium {
                    min-width: 150px;
                }

                .editable-field.small {
                    min-width: 80px;
                }
                
                @media print {
                    body {
                        margin: 0;
                        padding: 10px;
                    }
                    
                    .medical-certificate {
                        border: 2px solid #000;
                        margin: 0;
                        padding: 30px;
                    }
                    
                    @page {
                        margin: 0.5in;
                        size: A4;
                    }
                }
            </style>
        </head>
        <body>
            <div class="medical-certificate">
                ${certificateContent}
            </div>
        </body>
        </html>
    `;
    
    // Write the content to the new window
    printWindow.document.write(printDocument);
    printWindow.document.close();
    
    // Wait for the content to load, then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    };
}

// Real-time search filtering
function filterPatients() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const patientItems = document.querySelectorAll('.patient-list-item');
    const noSearchResults = document.getElementById('noSearchResults');
    
    let visibleCount = 0;
    
    patientItems.forEach(item => {
        const searchableText = item.getAttribute('data-search') || '';
        if (searchableText.includes(searchTerm)) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    // Show/hide "no search results" message
    if (noSearchResults) {
        if (visibleCount === 0 && searchTerm !== '') {
            noSearchResults.style.display = 'block';
        } else {
            noSearchResults.style.display = 'none';
        }
    }
}

// Add real-time search on input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Filter on input change
        searchInput.addEventListener('input', filterPatients);
        
        // Allow Enter key to submit form (for URL update)
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').dispatchEvent(new Event('submit'));
            }
        });
        
        // Run filter on page load if there's a search term
        if (searchInput.value.trim() !== '') {
            filterPatients();
        }
    }
});

// Search form submission is now handled inline in the form above
// This handler is kept for backward compatibility but the inline script takes precedence

// Make editable fields more user-friendly
document.addEventListener('DOMContentLoaded', function() {
    const editableFields = document.querySelectorAll('.editable-field');
    
    editableFields.forEach(field => {
        // Clear placeholder text on focus
        field.addEventListener('focus', function() {
            if (this.textContent.includes('___')) {
                this.textContent = '';
            }
        });
        
        // Add placeholder back if empty
        field.addEventListener('blur', function() {
            if (this.textContent.trim() === '') {
                this.textContent = '____________________';
            }
        });
        
        // Handle Enter key
        field.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.blur();
            }
        });
    });
});

// Mobile tab navigation enhancement
document.addEventListener('DOMContentLoaded', function() {
    const patientTabs = document.querySelector('.patient-tabs');
    if (patientTabs && window.innerWidth <= 768) {
        const activeTab = patientTabs.querySelector('.nav-link.active');
        if (activeTab) {
            // Scroll active tab into view on mobile
            setTimeout(() => {
                activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }, 100);
        }
        
        // Add smooth scrolling to tab links
        const tabLinks = patientTabs.querySelectorAll('.nav-link');
        tabLinks.forEach(link => {
            link.addEventListener('click', function() {
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }, 100);
            });
        });
    }
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Re-check and adjust if needed
            if (window.innerWidth <= 768) {
                const activeTab = document.querySelector('.patient-tabs .nav-link.active');
                if (activeTab) {
                    activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            }
        }, 250);
    });
});

// Real-time profile image update functionality
<?php if ($patient_details && $viewing_patient_id): ?>
(function() {
    const patientId = <?php echo $viewing_patient_id; ?>;
    if (!patientId) return;
    
    let lastProfileImagePath = '<?php echo htmlspecialchars($patient_details['profile_image'] ?? ''); ?>';
    let lastUpdated = '<?php echo isset($patient_details['updated_at']) ? $patient_details['updated_at'] : ''; ?>';
    let pollingInterval = null;
    let isPolling = false;
    
    function getProfileImageElement() {
        return document.getElementById('admin-patient-profile-image');
    }
    
    function getAvatarElement() {
        // Try to find avatar element near the profile image
        const img = getProfileImageElement();
        if (img) {
            return img.nextElementSibling;
        }
        // If no image, find the avatar in the patient header
        const header = document.querySelector('.patient-header');
        if (header) {
            return header.querySelector('.patient-avatar');
        }
        return null;
    }
    
    function createProfileImageElement() {
        const avatar = getAvatarElement();
        if (!avatar) return null;
        
        // Create image element
        const img = document.createElement('img');
        img.id = 'admin-patient-profile-image';
        img.alt = 'Profile';
        img.className = 'rounded-circle me-3';
        img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border: 2px solid #dee2e6;';
        img.onerror = function() {
            this.style.display = 'none';
            const avatarEl = this.nextElementSibling;
            if (avatarEl && avatarEl.classList.contains('patient-avatar')) {
                avatarEl.style.display = 'flex';
            }
        };
        
        // Insert before avatar
        avatar.parentNode.insertBefore(img, avatar);
        return img;
    }
    
    function updateListItemImage(patientId, newImagePath) {
        // Find the list item for this patient
        const listItem = document.querySelector(`.patient-list-item[data-patient-id="${patientId}"]`);
        if (!listItem) return;
        
        const listImage = listItem.querySelector('.patient-list-avatar');
        const listAvatar = listItem.querySelector('.patient-avatar');
        
        if (!newImagePath || newImagePath === '') {
            // Hide image and show avatar fallback in list
            if (listImage) {
                listImage.style.display = 'none';
            }
            if (listAvatar) {
                listAvatar.style.display = 'flex';
            }
            return;
        }
        
        // Create image element in list if it doesn't exist
        if (!listImage) {
            if (!listAvatar) return;
            const img = document.createElement('img');
            img.className = 'patient-list-avatar';
            img.alt = 'Profile';
            img.setAttribute('data-patient-id', patientId);
            img.style.cssText = 'width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #dee2e6; flex-shrink: 0;';
            img.onerror = function() {
                this.style.display = 'none';
                if (listAvatar) {
                    listAvatar.style.display = 'flex';
                }
            };
            listAvatar.parentNode.insertBefore(img, listAvatar);
            img.src = newImagePath + '?t=' + new Date().getTime();
            if (listAvatar) {
                listAvatar.style.display = 'none';
            }
        } else {
            // Update existing list image
            const timestamp = new Date().getTime();
            const newSrc = newImagePath + '?t=' + timestamp;
            const currentSrc = listImage.src.split('?')[0];
            const newSrcBase = newImagePath.split('?')[0];
            
            if (currentSrc !== newSrcBase) {
                listImage.src = newSrc;
                listImage.style.display = '';
                if (listAvatar) {
                    listAvatar.style.display = 'none';
                }
            }
        }
    }
    
    function updateProfileImage(newImagePath) {
        let profileImage = getProfileImageElement();
        
        if (!newImagePath || newImagePath === '') {
            // Hide image and show avatar fallback in detail view
            if (profileImage) {
                profileImage.style.display = 'none';
            }
            const avatar = getAvatarElement();
            if (avatar) {
                avatar.style.display = 'flex';
            }
            // Also update list item
            updateListItemImage(patientId, newImagePath);
            return;
        }
        
        // Create image element if it doesn't exist (detail view)
        if (!profileImage) {
            profileImage = createProfileImageElement();
            if (!profileImage) return;
        }
        
        // Show image and hide avatar in detail view
        profileImage.style.display = '';
        const avatar = getAvatarElement();
        if (avatar) {
            avatar.style.display = 'none';
        }
        
        // Update image with cache-busting timestamp
        const timestamp = new Date().getTime();
        const newSrc = newImagePath + '?t=' + timestamp;
        
        // Only update if the path has changed
        const currentSrc = profileImage.src.split('?')[0];
        const newSrcBase = newImagePath.split('?')[0];
        if (currentSrc !== newSrcBase) {
            profileImage.src = newSrc;
            profileImage.onerror = function() {
                this.style.display = 'none';
                const avatarEl = getAvatarElement();
                if (avatarEl) {
                    avatarEl.style.display = 'flex';
                }
            };
            lastProfileImagePath = newImagePath;
        }
        
        // Also update the list item image
        updateListItemImage(patientId, newImagePath);
    }
    
    function checkProfileImageUpdate() {
        if (isPolling || document.hidden) return;
        
        isPolling = true;
        fetch(`user_profile_image.php?patient_id=${patientId}&t=${new Date().getTime()}`)
            .then(response => response.json())
            .then(data => {
                isPolling = false;
                if (data.success) {
                    const currentImagePath = data.profile_image || '';
                    const currentUpdated = data.last_updated || '';
                    
                    // Check if profile image has changed (including first-time image upload)
                    const imageChanged = currentImagePath !== lastProfileImagePath;
                    const timestampChanged = currentUpdated && currentUpdated !== lastUpdated;
                    const imageExistsButNotShown = currentImagePath && !getProfileImageElement();
                    
                    if (imageChanged || timestampChanged || imageExistsButNotShown) {
                        updateProfileImage(currentImagePath);
                        lastProfileImagePath = currentImagePath;
                        lastUpdated = currentUpdated;
                    }
                }
            })
            .catch(error => {
                isPolling = false;
                console.error('Error checking profile image update:', error);
            });
    }
    
    // Start polling every 3 seconds when page is visible
    function startPolling() {
        if (pollingInterval) return;
        
        // Check immediately
        checkProfileImageUpdate();
        
        // Then check every 3 seconds
        pollingInterval = setInterval(function() {
            if (!document.hidden) {
                checkProfileImageUpdate();
            }
        }, 3000); // Poll every 3 seconds
    }
    
    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }
    
    // Start polling when page becomes visible
    if (!document.hidden) {
        startPolling();
    }
    
    // Handle visibility changes
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            startPolling();
            // Check immediately when page becomes visible
            checkProfileImageUpdate();
        } else {
            stopPolling();
        }
    });
    
    // Also refresh when patient tab is clicked
    const patientTabs = document.querySelectorAll('.patient-tabs .nav-link');
    patientTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            setTimeout(checkProfileImageUpdate, 500);
        });
    });
    
    // Refresh on window focus
    window.addEventListener('focus', function() {
        checkProfileImageUpdate();
        if (!pollingInterval) {
            startPolling();
        }
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        stopPolling();
    });
})();
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>