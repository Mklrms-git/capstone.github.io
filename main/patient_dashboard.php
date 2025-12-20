<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';
$page_title = 'Welcome to Mhavis Medical';
$active_page = 'Patient dashboard';

requirePatientLogin();

$patient_user = getCurrentPatientUser();

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT a.*, 
                       u.first_name as doctor_first_name, 
                       u.last_name as doctor_last_name, 
                       COALESCE(
                           dept_appt.name,
                           dept_doc.name,
                           dept_user.name,
                           'General'
                       ) as department_name 
                       FROM appointments a 
                       LEFT JOIN doctors doc ON a.doctor_id = doc.id 
                       LEFT JOIN users u ON doc.user_id = u.id 
                       LEFT JOIN departments dept_appt ON a.department_id = dept_appt.id 
                       LEFT JOIN departments dept_doc ON doc.department_id = dept_doc.id 
                       LEFT JOIN departments dept_user ON u.department_id = dept_user.id 
                       WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() 
                       ORDER BY a.appointment_date, a.appointment_time 
                       LIMIT 5");
$stmt->bind_param("i", $patient_user['patient_id']);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$departments = getAllDepartments();

// Get patient notifications - filter for patient activity and admin/email notifications
$conn_notif = getDBConnection();
$patient_user_id = $patient_user['id']; // Use patient_users.id, not patients.id

// Pagination parameters
$notif_page = isset($_GET['notif_page']) ? max(1, (int)$_GET['notif_page']) : 1;
$notif_limit = 5;
$notif_offset = ($notif_page - 1) * $notif_limit;

// Get total count of notifications
$total_notif_stmt = $conn_notif->prepare("SELECT COUNT(*) as total FROM notifications 
                                         WHERE recipient_id = ? 
                                         AND recipient_type = 'Patient'
                                         AND type IN ('Registration_Approved', 'Registration_Rejected', 'Appointment_Approved', 'Appointment_Rejected', 'Appointment_Reminder', 'Appointment_Rescheduled', 'Medical_Record_Updated')
                                         AND sent_via IN ('Email', 'System')");
$total_notif_stmt->bind_param("i", $patient_user_id);
$total_notif_stmt->execute();
$total_notif_result = $total_notif_stmt->get_result()->fetch_assoc();
$total_notifications = $total_notif_result ? (int)$total_notif_result['total'] : 0;
$total_notif_stmt->close();

// Fetch notifications that are:
// 1. Appointment-related (patient activity)
// 2. Medical record updates (admin notifications)
// 3. Sent via Email or System (admin notifications)
$notif_stmt = $conn_notif->prepare("SELECT * FROM notifications 
                                    WHERE recipient_id = ? 
                                    AND recipient_type = 'Patient'
                                    AND type IN ('Registration_Approved', 'Registration_Rejected', 'Appointment_Approved', 'Appointment_Rejected', 'Appointment_Reminder', 'Appointment_Rescheduled', 'Medical_Record_Updated')
                                    AND sent_via IN ('Email', 'System')
                                    ORDER BY created_at DESC 
                                    LIMIT ? OFFSET ?");
$notif_stmt->bind_param("iii", $patient_user_id, $notif_limit, $notif_offset);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();

$total_notif_pages = ceil($total_notifications / $notif_limit);

// Get unread count
$unread_stmt = $conn_notif->prepare("SELECT COUNT(*) as count FROM notifications 
                                     WHERE recipient_id = ? 
                                     AND recipient_type = 'Patient' 
                                     AND is_read = 0
                                     AND type IN ('Registration_Approved', 'Registration_Rejected', 'Appointment_Approved', 'Appointment_Rejected', 'Appointment_Reminder', 'Appointment_Rescheduled', 'Medical_Record_Updated')
                                     AND sent_via IN ('Email', 'System')");
$unread_stmt->bind_param("i", $patient_user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result()->fetch_assoc();
$unread_count = $unread_result['count'];

// Get selected year filter (default to current year or all)
$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;

// Get all medical records for this patient (added by admin or doctor)
// Show all records regardless of history_type - user wants to see all records added for them
$medical_records = [];
$available_years = [];

if (isset($patient_user['patient_id']) && !empty($patient_user['patient_id'])) {
    $patient_id = (int)$patient_user['patient_id'];
    
    // Get available years from medical records for the filter dropdown
    $yearsQuery = $conn->prepare("SELECT DISTINCT YEAR(COALESCE(visit_date, created_at)) as year 
                                   FROM medical_records 
                                   WHERE patient_id = ? 
                                   AND (visit_date IS NOT NULL AND visit_date != '0000-00-00' OR created_at IS NOT NULL)
                                   ORDER BY year DESC");
    if ($yearsQuery !== false) {
        $yearsQuery->bind_param("i", $patient_id);
        if ($yearsQuery->execute()) {
            $yearsResult = $yearsQuery->get_result();
            while ($yearRow = $yearsResult->fetch_assoc()) {
                $available_years[] = $yearRow['year'];
            }
        }
        $yearsQuery->close();
    }
    
    // Check if tracking columns exist before building query
    $checkCreatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'created_by'");
    $hasCreatedBy = $checkCreatedBy && $checkCreatedBy->num_rows > 0;
    $checkUpdatedBy = $conn->query("SHOW COLUMNS FROM `medical_records` LIKE 'updated_by'");
    $hasUpdatedBy = $checkUpdatedBy && $checkUpdatedBy->num_rows > 0;
    
    // Get all medical records for this patient with doctor, creator, and updater information
    // Use consistent query structure with patient_medical_records.php
    if ($hasCreatedBy || $hasUpdatedBy) {
        // Build query parts based on which columns exist
        $selectFields = "mr.*, ";
        $joinClauses = "LEFT JOIN users attending ON mr.doctor_id = attending.id ";
        
        if ($hasCreatedBy) {
            $joinClauses .= "LEFT JOIN users creator ON mr.created_by = creator.id ";
            $selectFields .= "mr.created_by as mr_created_by, creator.first_name as creator_first_name, creator.last_name as creator_last_name, creator.role as creator_role, ";
        } else {
            $selectFields .= "NULL as created_by, NULL as mr_created_by, NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role, ";
        }
        
        if ($hasUpdatedBy) {
            $joinClauses .= "LEFT JOIN users updater ON mr.updated_by = updater.id ";
            $selectFields .= "updater.first_name as updater_first_name, updater.last_name as updater_last_name, updater.role as updater_role, ";
        } else {
            $selectFields .= "NULL as updated_by, NULL as updater_first_name, NULL as updater_last_name, NULL as updater_role, ";
        }
        
        $selectFields .= "attending.first_name as doctor_first_name, attending.last_name as doctor_last_name, 
                          attending.role as doctor_role, attending.specialization";
        
        // Add year filter to WHERE clause if selected
        $yearFilter = "";
        if ($selected_year !== null) {
            $yearFilter = " AND ((mr.visit_date IS NOT NULL AND mr.visit_date != '0000-00-00' AND YEAR(mr.visit_date) = ?) OR ((mr.visit_date IS NULL OR mr.visit_date = '0000-00-00') AND YEAR(mr.created_at) = ?))";
        }
        
        // Query with tracking columns
        $orderBy = "ORDER BY COALESCE(NULLIF(mr.visit_date, '0000-00-00'), mr.created_at) DESC, mr.created_at DESC";
        $stmt_medical = $conn->prepare("SELECT " . $selectFields . "
                                        FROM medical_records mr 
                                        " . $joinClauses . "
                                        WHERE mr.patient_id = ?" . $yearFilter . "
                                        " . $orderBy);
    } else {
        // Add year filter to WHERE clause if selected
        $yearFilter = "";
        if ($selected_year !== null) {
            $yearFilter = " AND ((mr.visit_date IS NOT NULL AND mr.visit_date != '0000-00-00' AND YEAR(mr.visit_date) = ?) OR ((mr.visit_date IS NULL OR mr.visit_date = '0000-00-00') AND YEAR(mr.created_at) = ?))";
        }
        
        // Fallback query without tracking columns
        $orderBy = "ORDER BY COALESCE(NULLIF(mr.visit_date, '0000-00-00'), mr.created_at) DESC, mr.created_at DESC";
        $stmt_medical = $conn->prepare("SELECT mr.*, 
                                               attending.first_name as doctor_first_name, attending.last_name as doctor_last_name, 
                                               attending.role as doctor_role, attending.specialization,
                                               NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role,
                                               NULL as updater_first_name, NULL as updater_last_name, NULL as updater_role
                                        FROM medical_records mr 
                                        LEFT JOIN users attending ON mr.doctor_id = attending.id 
                                        WHERE mr.patient_id = ?" . $yearFilter . "
                                        " . $orderBy);
    }
    if ($stmt_medical) {
        // Bind parameters based on whether year filter is applied
        if ($selected_year !== null) {
            $stmt_medical->bind_param("iii", $patient_id, $selected_year, $selected_year);
        } else {
            $stmt_medical->bind_param("i", $patient_id);
        }
        if ($stmt_medical->execute()) {
            $medical_records_result = $stmt_medical->get_result();
            if ($medical_records_result) {
                while ($row = $medical_records_result->fetch_assoc()) {
                    $medical_records[] = $row;
                }
            }
        } else {
            // If query fails, try a simpler version without COALESCE
            $stmt_medical->close();
            
            // Build year filter for fallback queries
            $yearFilterFallback = "";
            if ($selected_year !== null) {
                $yearFilterFallback = " AND YEAR(COALESCE(mr.visit_date, mr.created_at)) = ?";
            }
            
            if ($hasCreatedBy && $hasUpdatedBy) {
                $stmt_medical = $conn->prepare("SELECT mr.*, 
                                                       attending.first_name as doctor_first_name, attending.last_name as doctor_last_name, 
                                                       attending.role as doctor_role, attending.specialization,
                                                       creator.first_name as creator_first_name, creator.last_name as creator_last_name, 
                                                       creator.role as creator_role,
                                                       updater.first_name as updater_first_name, updater.last_name as updater_last_name, 
                                                       updater.role as updater_role
                                                FROM medical_records mr 
                                                LEFT JOIN users attending ON mr.doctor_id = attending.id 
                                                LEFT JOIN users creator ON mr.created_by = creator.id
                                                LEFT JOIN users updater ON mr.updated_by = updater.id
                                                WHERE mr.patient_id = ?" . $yearFilterFallback . "
                                                ORDER BY mr.created_at DESC");
            } else {
                $stmt_medical = $conn->prepare("SELECT mr.*, 
                                                       attending.first_name as doctor_first_name, attending.last_name as doctor_last_name, 
                                                       attending.role as doctor_role, attending.specialization,
                                                       NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role,
                                                       NULL as updater_first_name, NULL as updater_last_name, NULL as updater_role
                                                FROM medical_records mr 
                                                LEFT JOIN users attending ON mr.doctor_id = attending.id 
                                                WHERE mr.patient_id = ?" . $yearFilterFallback . "
                                                ORDER BY mr.created_at DESC");
            }
            if ($stmt_medical) {
                // Bind parameters based on whether year filter is applied
                if ($selected_year !== null) {
                    $stmt_medical->bind_param("ii", $patient_id, $selected_year);
                } else {
                    $stmt_medical->bind_param("i", $patient_id);
                }
                if ($stmt_medical->execute()) {
                    $medical_records_result = $stmt_medical->get_result();
                    if ($medical_records_result) {
                        while ($row = $medical_records_result->fetch_assoc()) {
                            $medical_records[] = $row;
                        }
                    }
                }
            }
        }
        if ($stmt_medical) {
            $stmt_medical->close();
        }
    }
    
    // Get medical history records for this patient
    $medical_history_records = [];
    $checkHistoryTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
    if ($checkHistoryTable && $checkHistoryTable->num_rows > 0) {
        $checkCreatedBy = $conn->query("SHOW COLUMNS FROM `medical_history` LIKE 'created_by'");
        $hasCreatedBy = $checkCreatedBy && $checkCreatedBy->num_rows > 0;
        
        if ($hasCreatedBy) {
            $stmt_history = $conn->prepare("SELECT mh.*, 
                                                   u.first_name as doctor_first_name, u.last_name as doctor_last_name, 
                                                   u.role as doctor_role, u.specialization,
                                                   creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                                                   creator.role as creator_role
                                            FROM medical_history mh 
                                            LEFT JOIN users u ON mh.doctor_id = u.id 
                                            LEFT JOIN users creator ON mh.created_by = creator.id
                                            WHERE mh.patient_id = ? AND mh.status = 'active'
                                            ORDER BY mh.created_at DESC");
        } else {
            $stmt_history = $conn->prepare("SELECT mh.*, 
                                                   u.first_name as doctor_first_name, u.last_name as doctor_last_name, 
                                                   u.role as doctor_role, u.specialization,
                                                   NULL as creator_first_name, NULL as creator_last_name, NULL as creator_role
                                            FROM medical_history mh 
                                            LEFT JOIN users u ON mh.doctor_id = u.id 
                                            WHERE mh.patient_id = ? AND mh.status = 'active'
                                            ORDER BY mh.created_at DESC");
        }
        
        if ($stmt_history) {
            $stmt_history->bind_param("i", $patient_id);
            if ($stmt_history->execute()) {
                $history_result = $stmt_history->get_result();
                if ($history_result) {
                    while ($row = $history_result->fetch_assoc()) {
                        // Add record type identifier
                        $row['record_type'] = 'medical_history';
                        $medical_history_records[] = $row;
                    }
                }
            }
            $stmt_history->close();
        }
    }
    
    // Get vitals records for this patient
    $vitals_records = [];
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
            $stmt_vitals->bind_param("i", $patient_id);
            if ($stmt_vitals->execute()) {
                $vitals_result = $stmt_vitals->get_result();
                if ($vitals_result) {
                    while ($row = $vitals_result->fetch_assoc()) {
                        // Add record type identifier
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
    
    // Combine all records: medical records, medical history, and vitals
    // Add record type identifier to medical records
    foreach ($medical_records as &$record) {
        $record['record_type'] = 'medical_record';
    }
    unset($record);
    
    // Merge all records into one array
    $all_records = array_merge($medical_records, $medical_history_records, $vitals_records);
    
    // Update available years to include years from all record types
    if (!empty($all_records)) {
        $years_from_records = [];
        foreach ($all_records as $record) {
            $date_field = null;
            if ($record['record_type'] == 'medical_record') {
                $date_field = !empty($record['visit_date']) && $record['visit_date'] != '0000-00-00' 
                    ? $record['visit_date'] 
                    : $record['created_at'];
            } elseif ($record['record_type'] == 'vitals') {
                $date_field = !empty($record['visit_date']) && $record['visit_date'] != '0000-00-00' 
                    ? $record['visit_date'] 
                    : $record['created_at'];
            } else {
                $date_field = $record['created_at'];
            }
            
            if ($date_field) {
                $year = date('Y', strtotime($date_field));
                if ($year && !in_array($year, $years_from_records)) {
                    $years_from_records[] = $year;
                }
            }
        }
        // Merge with existing available_years and remove duplicates
        $available_years = array_unique(array_merge($available_years, $years_from_records));
        rsort($available_years); // Sort descending
    }
    
    // Apply year filter to all records if selected
    if ($selected_year !== null) {
        $all_records = array_filter($all_records, function($record) use ($selected_year) {
            $date_field = null;
            if ($record['record_type'] == 'medical_record') {
                $date_field = !empty($record['visit_date']) && $record['visit_date'] != '0000-00-00' 
                    ? $record['visit_date'] 
                    : $record['created_at'];
            } elseif ($record['record_type'] == 'vitals') {
                $date_field = !empty($record['visit_date']) && $record['visit_date'] != '0000-00-00' 
                    ? $record['visit_date'] 
                    : $record['created_at'];
            } else {
                $date_field = $record['created_at'];
            }
            
            if ($date_field) {
                $year = date('Y', strtotime($date_field));
                return $year == $selected_year;
            }
            return false;
        });
    }
    
    // Sort all records by date (newest first)
    usort($all_records, function($a, $b) {
        $date_a = null;
        $date_b = null;
        
        if ($a['record_type'] == 'medical_record') {
            $date_a = !empty($a['visit_date']) && $a['visit_date'] != '0000-00-00' 
                ? strtotime($a['visit_date'] . ' ' . ($a['created_at'] ?? '')) 
                : strtotime($a['created_at']);
        } elseif ($a['record_type'] == 'vitals') {
            $date_a = !empty($a['visit_date']) && $a['visit_date'] != '0000-00-00' 
                ? strtotime($a['visit_date'] . ' ' . ($a['created_at'] ?? '')) 
                : strtotime($a['created_at']);
        } else {
            $date_a = strtotime($a['created_at']);
        }
        
        if ($b['record_type'] == 'medical_record') {
            $date_b = !empty($b['visit_date']) && $b['visit_date'] != '0000-00-00' 
                ? strtotime($b['visit_date'] . ' ' . ($b['created_at'] ?? '')) 
                : strtotime($b['created_at']);
        } elseif ($b['record_type'] == 'vitals') {
            $date_b = !empty($b['visit_date']) && $b['visit_date'] != '0000-00-00' 
                ? strtotime($b['visit_date'] . ' ' . ($b['created_at'] ?? '')) 
                : strtotime($b['created_at']);
        } else {
            $date_b = strtotime($b['created_at']);
        }
        
        return $date_b - $date_a; // Descending order
    });
    
    // Replace medical_records with all_records for display
    $medical_records = $all_records;
    
    // Create a map of vitals and medical history by visit_date for easy lookup
    $vitals_by_visit_date = [];
    foreach ($vitals_records as $vital) {
        $visit_date = !empty($vital['visit_date']) && $vital['visit_date'] != '0000-00-00' 
            ? $vital['visit_date'] 
            : date('Y-m-d', strtotime($vital['created_at']));
        if (!isset($vitals_by_visit_date[$visit_date])) {
            $vitals_by_visit_date[$visit_date] = [];
        }
        $vitals_by_visit_date[$visit_date][] = $vital;
    }
    
    $medical_history_by_visit_date = [];
    foreach ($medical_history_records as $history) {
        $history_date = date('Y-m-d', strtotime($history['created_at']));
        if (!isset($medical_history_by_visit_date[$history_date])) {
            $medical_history_by_visit_date[$history_date] = [];
        }
        $medical_history_by_visit_date[$history_date][] = $history;
    }
}

// Get prescriptions for this patient
$prescriptions = [];
$prescriptions_by_month = [];
if (isset($patient_user['patient_id']) && !empty($patient_user['patient_id'])) {
    $patient_id = (int)$patient_user['patient_id'];
    $stmt_prescriptions = $conn->prepare("SELECT p.*, 
                   u.first_name as doctor_first_name, 
                   u.last_name as doctor_last_name,
                   u.specialization,
                   u.role as doctor_role
            FROM prescriptions p 
            LEFT JOIN users u ON p.doctor_id = u.id 
            WHERE p.patient_id = ? 
            ORDER BY p.date_prescribed DESC");
    if ($stmt_prescriptions) {
        $stmt_prescriptions->bind_param("i", $patient_id);
        if ($stmt_prescriptions->execute()) {
            $prescriptions_result = $stmt_prescriptions->get_result();
            if ($prescriptions_result) {
                while ($row = $prescriptions_result->fetch_assoc()) {
                    $prescriptions[] = $row;
                    
                    // Group by month and date
                    $date_prescribed = $row['date_prescribed'];
                    $month_key = date('Y-m', strtotime($date_prescribed));
                    $date_key = date('Y-m-d', strtotime($date_prescribed));
                    
                    if (!isset($prescriptions_by_month[$month_key])) {
                        $prescriptions_by_month[$month_key] = [
                            'month_name' => date('F Y', strtotime($date_prescribed)),
                            'month_key' => $month_key,
                            'dates' => []
                        ];
                    }
                    
                    if (!isset($prescriptions_by_month[$month_key]['dates'][$date_key])) {
                        $prescriptions_by_month[$month_key]['dates'][$date_key] = [
                            'date_display' => date('F d, Y', strtotime($date_prescribed)),
                            'date_key' => $date_key,
                            'prescriptions' => []
                        ];
                    }
                    
                    $prescriptions_by_month[$month_key]['dates'][$date_key]['prescriptions'][] = $row;
                }
            }
        }
        $stmt_prescriptions->close();
    }
}

include 'includes/header.php';
?>

<style>
    .patient-dashboard {
        padding: 0;
    }

    .patient-dashboard .dashboard-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .patient-dashboard .dashboard-header {
        background: linear-gradient(135deg, #0D92F4, #77CDFF);
        color: #ffffff;
        padding: 2rem;
        margin: -1px -1px 0 -1px;
    }

    .patient-dashboard .dashboard-header h3 {
        margin-bottom: 0.5rem;
        font-weight: 600;
        font-size: 1.75rem;
    }

    .patient-dashboard .dashboard-header p {
        margin: 0;
        opacity: 0.95;
        font-size: 1rem;
    }

    .patient-dashboard .card-header {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        padding: 1.25rem 1.5rem;
    }

    .patient-dashboard .card-header h5 {
        margin: 0;
        font-weight: 600;
        color: #1e293b;
        font-size: 1.25rem;
    }

    .patient-dashboard .card-body {
        padding: 1.5rem;
    }

    .patient-dashboard .appointment-card {
        border-left: 4px solid #0D92F4;
        background: linear-gradient(to right, #f0f9ff 0%, #ffffff 100%);
        border-radius: 8px;
        transition: all 0.2s ease;
        border: 1px solid #e0f2fe;
    }

    .patient-dashboard .appointment-card:hover {
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.15);
        transform: translateY(-2px);
    }

    .patient-dashboard .appointment-card h6 {
        color: #0c4a6e;
        font-weight: 600;
    }

    .patient-dashboard .status-badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.75rem;
        border-radius: 999px;
        font-weight: 600;
        letter-spacing: 0.025em;
    }

    .patient-dashboard .calendar-container {
        background: #ffffff;
        border-radius: 8px;
        padding: 1rem;
    }

    .patient-dashboard .content-section {
        display: none;
    }

    .patient-dashboard .content-section.active {
        display: block;
    }

    .patient-dashboard .btn-primary {
        background: linear-gradient(135deg, #0D92F4, #0c7cd5);
        border: none;
        padding: 0.625rem 1.25rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .patient-dashboard .btn-primary:hover {
        background: linear-gradient(135deg, #0c7cd5, #0b6bc0);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.3);
    }

    .patient-dashboard .btn-outline-primary {
        border-color: #0D92F4;
        color: #0D92F4;
        padding: 0.625rem 1.25rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .patient-dashboard .btn-outline-primary:hover {
        background: #0D92F4;
        border-color: #0D92F4;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.2);
    }

    .patient-dashboard .form-label {
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
    }

    .patient-dashboard .form-control,
    .patient-dashboard .form-select {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 0.625rem 0.875rem;
        transition: all 0.2s ease;
    }

    .patient-dashboard .form-control:focus,
    .patient-dashboard .form-select:focus {
        border-color: #0D92F4;
        box-shadow: 0 0 0 3px rgba(13, 146, 244, 0.1);
    }

    .patient-dashboard .info-card {
        background: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
        border: 1px solid #e2e8f0;
    }

    .patient-dashboard .info-card p {
        margin-bottom: 0.75rem;
    }

    .patient-dashboard .info-card p:last-child {
        margin-bottom: 0;
    }

    .patient-dashboard h5 {
        color: #1e293b;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .patient-dashboard h6 {
        color: #334155;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    /* Notifications Styles */
    .patient-dashboard .notifications-list {
        max-height: 600px;
        overflow-y: auto;
    }

    .patient-dashboard .notification-item {
        display: flex;
        gap: 1rem;
        padding: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        background: #ffffff;
    }

    .patient-dashboard .notification-item:last-child {
        border-bottom: none;
    }

    .patient-dashboard .notification-item.unread {
        background: linear-gradient(to right, #f0f9ff 0%, #ffffff 100%);
        border-left: 4px solid #0D92F4;
    }

    .patient-dashboard .notification-item:hover {
        background: #f8fafc;
    }

    .patient-dashboard .notification-icon {
        flex-shrink: 0;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border-radius: 50%;
        font-size: 1.25rem;
    }

    .patient-dashboard .notification-content {
        flex: 1;
    }

    .patient-dashboard .notification-title {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.25rem;
        font-size: 1rem;
    }

    .patient-dashboard .notification-message {
        color: #64748b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .patient-dashboard .notification-time {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .patient-dashboard .notification-actions {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        align-items: flex-end;
    }

    .patient-dashboard .notification-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .patient-dashboard .notification-item.removing {
        animation: slideOut 0.3s ease-out forwards;
    }

    /* Prescription Styles - Matching patient_prescriptions.php */
    .patient-dashboard .prescription-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 15px;
        background-color: white;
        transition: box-shadow 0.2s;
    }
    .patient-dashboard .prescription-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .patient-dashboard .prescription-header {
        background-color: #ffffff;
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        border-radius: 8px 8px 0 0;
    }
    .patient-dashboard .prescription-body {
        padding: 15px;
    }
    .patient-dashboard .status-badge {
        font-size: 0.8em;
        padding: 4px 8px;
        border-radius: 12px;
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
    .month-prescriptions-container {
        padding: 15px;
    }
    .prescription-record-item {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        margin-bottom: 10px;
        background-color: #fff;
    }
    .prescription-record-header {
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e9ecef;
    }
    .prescription-record-date {
        font-weight: 500;
        color: #495057;
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

    @keyframes slideOut {
        to {
            opacity: 0;
            transform: translateX(100%);
            height: 0;
            padding: 0;
            margin: 0;
        }
    }

    /* Responsive Design - Tablet */
    @media (max-width: 991.98px) {
        .patient-dashboard .dashboard-header {
            padding: 1.5rem;
        }

        .patient-dashboard .dashboard-header h3 {
            font-size: 1.5rem;
        }

        .patient-dashboard .dashboard-header p {
            font-size: 0.95rem;
        }

        .patient-dashboard .card-body {
            padding: 1.25rem;
        }

        .patient-dashboard .card-header {
            padding: 1rem 1.25rem;
        }

        .patient-dashboard .card-header h5 {
            font-size: 1.1rem;
        }

        .patient-dashboard .notification-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .patient-dashboard .notification-actions {
            flex-direction: row;
            width: 100%;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }

        .patient-dashboard .row.g-4 {
            --bs-gutter-y: 1rem;
        }

        .patient-dashboard .appointment-card {
            padding: 1rem !important;
        }

        .patient-dashboard .appointment-modal-content {
            width: 95%;
            max-width: 550px;
        }

        .patient-dashboard .appointment-modal-body {
            padding: 1.5rem;
        }
    }

    /* Responsive Design - Mobile */
    @media (max-width: 575.98px) {
        .patient-dashboard {
            padding: 0;
        }

        .patient-dashboard .dashboard-header {
            padding: 1.25rem 1rem;
        }

        .patient-dashboard .dashboard-header h3 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .patient-dashboard .dashboard-header p {
            font-size: 0.875rem;
        }

        .patient-dashboard .card-body {
            padding: 1rem;
        }

        .patient-dashboard .card-header {
            padding: 0.875rem 1rem;
        }

        .patient-dashboard .card-header h5 {
            font-size: 1rem;
        }

        .patient-dashboard .card-header h5 i {
            font-size: 0.9rem;
        }

        .patient-dashboard h5 {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }

        .patient-dashboard h6 {
            font-size: 0.95rem;
        }

        .patient-dashboard .appointment-card {
            padding: 0.875rem !important;
            margin-bottom: 0.75rem !important;
        }

        .patient-dashboard .appointment-card h6 {
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        .patient-dashboard .appointment-card p {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .patient-dashboard .status-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }

        .patient-dashboard .btn-primary,
        .patient-dashboard .btn-outline-primary {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .patient-dashboard .btn-primary i,
        .patient-dashboard .btn-outline-primary i {
            font-size: 0.85rem;
        }

        .patient-dashboard .form-label {
            font-size: 0.9rem;
        }

        .patient-dashboard .form-control,
        .patient-dashboard .form-select {
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
        }

        .patient-dashboard .info-card {
            padding: 0.875rem;
        }

        .patient-dashboard .info-card h6 {
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }

        .patient-dashboard .info-card p {
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .patient-dashboard .notification-item {
            padding: 1rem;
            gap: 0.75rem;
        }

        .patient-dashboard .notification-icon {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .patient-dashboard .notification-title {
            font-size: 0.95rem;
        }

        .patient-dashboard .notification-message {
            font-size: 0.85rem;
        }

        .patient-dashboard .notification-time {
            font-size: 0.7rem;
        }

        .patient-dashboard .notification-actions .btn {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
        }

        .patient-dashboard .appointment-modal-content {
            width: 100%;
            max-width: 100%;
            margin: 0;
            border-radius: 0;
            max-height: 100vh;
            overflow-y: auto;
        }

        .patient-dashboard .appointment-modal-header {
            padding: 1rem;
            border-radius: 0;
        }

        .patient-dashboard .appointment-modal-header h5 {
            font-size: 1.1rem;
        }

        .patient-dashboard .appointment-modal-body {
            padding: 1rem;
        }

        .patient-dashboard .appointment-detail-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
        }

        .patient-dashboard .appointment-detail-label {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        .patient-dashboard .appointment-detail-value {
            font-size: 1rem;
        }

        .patient-dashboard .appointment-status-badge {
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
        }

        .patient-dashboard .record-card,
        .patient-dashboard .prescription-card {
            margin-bottom: 1rem;
        }

        .patient-dashboard .record-header,
        .patient-dashboard .prescription-header {
            padding: 0.875rem;
        }

        .patient-dashboard .record-body,
        .patient-dashboard .prescription-body {
            padding: 0.875rem;
        }

        .patient-dashboard .doctor-info,
        .patient-dashboard .prescriber-info {
            padding: 0.75rem;
            font-size: 0.85rem;
        }

        .patient-dashboard .medication-name {
            font-size: 1rem;
        }

        .patient-dashboard .dosage-info {
            font-size: 0.85rem;
        }

        .patient-dashboard .profile-preview-img {
            width: 120px;
            height: 120px;
        }

        .patient-dashboard .calendar-container {
            padding: 0.75rem;
        }

        .patient-dashboard .row.g-3 {
            --bs-gutter-y: 0.75rem;
        }

        .patient-dashboard .row.g-4 {
            --bs-gutter-y: 0.75rem;
        }

        .patient-dashboard .col-lg-6 {
            margin-bottom: 1rem;
        }

        .patient-dashboard .d-grid.gap-3 {
            gap: 0.75rem !important;
        }

        .patient-dashboard .alert {
            padding: 0.75rem;
            font-size: 0.875rem;
        }

        /* FullCalendar Mobile Responsive */
        .patient-dashboard .fc {
            font-size: 0.85rem;
        }

        .patient-dashboard .fc-header-toolbar {
            flex-direction: column;
            gap: 0.5rem;
        }

        .patient-dashboard .fc-toolbar-chunk {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.25rem;
        }

        .patient-dashboard .fc-button {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .patient-dashboard .fc-toolbar-title {
            font-size: 1rem;
        }

        .patient-dashboard .fc-daygrid-day-number {
            font-size: 0.8rem;
        }

        .patient-dashboard .fc-event-title {
            font-size: 0.75rem;
        }
    }

    /* Small Mobile Devices */
    @media (max-width: 375px) {
        .patient-dashboard .dashboard-header {
            padding: 1rem 0.875rem;
        }

        .patient-dashboard .dashboard-header h3 {
            font-size: 1.1rem;
        }

        .patient-dashboard .card-body {
            padding: 0.875rem;
        }

        .patient-dashboard .btn-primary,
        .patient-dashboard .btn-outline-primary {
            padding: 0.45rem 0.875rem;
            font-size: 0.85rem;
        }

        .patient-dashboard .profile-preview-img {
            width: 100px;
            height: 100px;
        }
    }

    /* Medical Records and Prescriptions Styles */
    .patient-dashboard .record-card,
    .patient-dashboard .prescription-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 20px;
        transition: box-shadow 0.2s;
        background: #ffffff;
    }
    .patient-dashboard .record-card:hover,
    .patient-dashboard .prescription-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .patient-dashboard .record-header,
    .patient-dashboard .prescription-header {
        background-color: #f8f9fa;
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        border-radius: 8px 8px 0 0;
    }
    .patient-dashboard .record-body,
    .patient-dashboard .prescription-body {
        padding: 15px;
    }
    .patient-dashboard .doctor-info,
    .patient-dashboard .prescriber-info {
        background-color: #e8f5e8;
        padding: 12px;
        border-radius: 6px;
        margin-top: 15px;
        border-left: 4px solid #28a745;
    }
    .patient-dashboard .attachment-item {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 8px;
        margin: 5px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .patient-dashboard .attachment-icon {
        margin-right: 8px;
        color: #6c757d;
    }
    .patient-dashboard .vital-signs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 10px 0;
    }
    .patient-dashboard .vital-item {
        background-color: #f8f9fa;
        padding: 8px;
        border-radius: 4px;
        text-align: center;
        height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .patient-dashboard .vital-label {
        font-size: 0.8em;
        color: #6c757d;
        font-weight: 500;
    }
    .patient-dashboard .vital-value {
        font-size: 1.1em;
        font-weight: 600;
        color: #495057;
    }
    .patient-dashboard .status-badge {
        font-size: 0.8em;
        padding: 4px 8px;
        border-radius: 12px;
    }
    
    /* Month Group Card Styles */
    .patient-dashboard .month-group-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 15px;
        background-color: #f8f9fa;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: box-shadow 0.2s;
    }
    .patient-dashboard .month-group-card:hover {
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .patient-dashboard .month-group-header {
        padding: 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 8px;
        transition: background-color 0.2s;
    }
    .patient-dashboard .month-group-header:hover {
        background-color: #e9ecef;
    }
    .patient-dashboard .month-group-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        color: #2c3e50;
        font-size: 1em;
    }
    .patient-dashboard .month-group-count {
        color: #6c757d;
        font-size: 0.9em;
        font-weight: normal;
        margin-left: 5px;
    }
    .patient-dashboard .month-group-chevron {
        transition: transform 0.3s;
        color: #6c757d;
    }
    .patient-dashboard .month-group-chevron.expanded {
        transform: rotate(180deg);
    }
    .patient-dashboard .month-group-content {
        display: none;
        padding: 0;
    }
    .patient-dashboard .month-group-content.show {
        display: block;
    }
    .patient-dashboard .date-group-item {
        padding: 12px 15px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color 0.2s;
        background-color: white;
    }
    .patient-dashboard .date-group-item:last-child {
        border-bottom: none;
    }
    .patient-dashboard .date-group-item:hover {
        background-color: #f8f9fa;
    }
    .patient-dashboard .date-group-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95em;
    }
    .patient-dashboard .date-group-count {
        color: #6c757d;
        font-size: 0.85em;
        font-weight: normal;
        margin-left: 5px;
    }
    .patient-dashboard .date-group-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .patient-dashboard .date-group-actions .btn {
        font-size: 0.875rem;
        padding: 0.25rem 0.75rem;
        border-radius: 0.375rem;
        transition: all 0.2s ease;
        font-weight: 500;
    }
    .patient-dashboard .date-group-actions .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #ffffff;
    }
    .patient-dashboard .date-group-actions .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }
    .patient-dashboard .date-group-chevron {
        transition: transform 0.3s;
        color: #6c757d;
        font-size: 0.8em;
    }
    .patient-dashboard .date-group-chevron.expanded {
        transform: rotate(180deg);
    }
    .patient-dashboard .date-group-content {
        display: none;
        padding: 15px;
        background-color: #f8f9fa;
    }
    .patient-dashboard .date-group-content.show {
        display: block;
    }
    .patient-dashboard .date-group-content .prescription-card {
        margin-top: 0;
    }
    .patient-dashboard .date-group-content .prescription-card:first-child {
        margin-top: 0;
    }
    
    .patient-dashboard .status-active {
        background-color: #d4edda;
        color: #155724;
    }
    .patient-dashboard .status-completed {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    .patient-dashboard .status-discontinued {
        background-color: #f8d7da;
        color: #721c24;
    }
    .patient-dashboard .medication-name {
        font-size: 1.1em;
        font-weight: 600;
        color: #2c3e50;
    }
    .patient-dashboard .dosage-info {
        color: #6c757d;
        font-size: 0.9em;
    }
    .patient-dashboard .prescriber-info {
        background-color: #e3f2fd;
        padding: 8px 12px;
        border-radius: 6px;
        margin-top: 10px;
        border-left: 4px solid #2196f3;
    }
    
    /* Prescription Modal Styles */
    .patient-dashboard #viewPrescriptionsModal .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
    .patient-dashboard #viewPrescriptionsModal .prescription-card {
        margin-bottom: 15px;
    }
    .patient-dashboard #viewPrescriptionsModal .prescription-card:last-child {
        margin-bottom: 0;
    }
    
    /* Appointment Modal Styles */
    .patient-dashboard .appointment-modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .patient-dashboard .appointment-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .patient-dashboard .appointment-modal-content {
        background-color: #ffffff;
        margin: auto;
        padding: 0;
        border: none;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        animation: modalFadeIn 0.3s ease;
    }
    
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .patient-dashboard .appointment-modal-header {
        background: linear-gradient(135deg, #0D92F4, #77CDFF);
        color: #ffffff;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .patient-dashboard .appointment-modal-header h5 {
        margin: 0;
        font-weight: 600;
        font-size: 1.5rem;
    }
    
    .patient-dashboard .appointment-modal-close {
        color: #ffffff;
        font-size: 1.5rem;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }
    
    .patient-dashboard .appointment-modal-close:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }
    
    .patient-dashboard .appointment-modal-body {
        padding: 2rem;
    }
    
    .patient-dashboard .appointment-detail-item {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .patient-dashboard .appointment-detail-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .patient-dashboard .appointment-detail-label {
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .patient-dashboard .appointment-detail-value {
        color: #1e293b;
        font-size: 1.1rem;
    }
    
    .patient-dashboard .appointment-status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .patient-dashboard .appointment-status-scheduled {
        background-color: #cfe2ff;
        color: #084298;
    }
    
    .patient-dashboard .appointment-status-ongoing {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .patient-dashboard .appointment-status-settled {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    
    .patient-dashboard .appointment-status-cancelled,
    .patient-dashboard .appointment-status-canceled {
        background-color: #f8d7da;
        color: #842029;
    }
    
    /* Profile Section Styles */
    .patient-dashboard .profile-image-upload-container {
        text-align: center;
    }
    
    .patient-dashboard .profile-image-preview {
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .patient-dashboard .profile-preview-img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .patient-dashboard .profile-preview-img:hover {
        border-color: #0D92F4;
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.2);
    }
    
    .patient-dashboard .profile-image-upload-container .input-group {
        max-width: 400px;
        margin: 0 auto;
    }
    
    .patient-dashboard #profile-message {
        margin-bottom: 1.5rem;
    }
</style>

<div class="patient-dashboard">
    <!-- Dashboard Section -->
    <div id="dashboard-section" class="content-section active">
        <div class="dashboard-card">
            <div class="dashboard-header">
                <h3>Patient Dashboard</h3>
                <p>Manage your appointments and health records</p>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12 col-lg-6">
                        <h5><i class="fas fa-calendar-check me-2"></i>Upcoming Appointments</h5>
                        <?php if (empty($upcoming_appointments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No upcoming appointments</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <?php
                                $doctor_name = trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''));
                                $doctor_display = $doctor_name ? 'Dr. ' . $doctor_name : 'Dr. Unknown Doctor';
                                $appointment_data = [
                                    'id' => $appointment['id'],
                                    'doctor_name' => $doctor_display,
                                    'department' => $appointment['department_name'] ?? 'General',
                                    'date' => $appointment['appointment_date'],
                                    'time' => $appointment['appointment_time'],
                                    'status' => $appointment['status'],
                                    'reason' => $appointment['reason'] ?? '',
                                    'notes' => $appointment['notes'] ?? ''
                                ];
                                ?>
                                <div class="appointment-card p-3 mb-3" style="cursor: pointer;" 
                                     data-appointment='<?php echo htmlspecialchars(json_encode($appointment_data), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($doctor_display); ?></h6>
                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($appointment['department_name'] ?? 'General'); ?></p>
                                            <p class="mb-1 small">
                                                <i class="fas fa-calendar me-1 text-primary"></i>
                                                <?php echo formatDate($appointment['appointment_date']); ?>
                                            </p>
                                            <p class="mb-0 small">
                                                <i class="fas fa-clock me-1 text-primary"></i>
                                                <?php echo formatTime($appointment['appointment_time']); ?>
                                            </p>
                                        </div>
                                        <?php
                                        $statusLower = strtolower($appointment['status']);
                                        $statusClass = match($statusLower) {
                                            'scheduled' => 'primary',
                                            'ongoing' => 'warning',
                                            'settled' => 'success',
                                            'cancelled', 'canceled' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge status-badge bg-<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-lg-6">
                        <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        <div class="d-grid gap-3">
                            <button class="btn btn-primary" onclick="showSection('book-appointment')">
                                <i class="fas fa-plus-circle me-2"></i>Book New Appointment
                            </button>
                            <button class="btn btn-outline-primary" onclick="showSection('appointments')">
                                <i class="fas fa-calendar-alt me-2"></i>View All Appointments
                            </button>
                            <button class="btn btn-outline-primary" onclick="showSection('medical-records')">
                                <i class="fas fa-file-medical me-2"></i>View Medical Records
                            </button>
                            <button class="btn btn-outline-primary" onclick="showSection('prescriptions')">
                                <i class="fas fa-prescription-bottle-alt me-2"></i>View Prescriptions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments Section -->
    <div id="appointments-section" class="content-section">
        <div class="dashboard-card">
            <div class="card-header">
                <h5><i class="fas fa-calendar-alt me-2"></i>My Appointments</h5>
            </div>
            <div class="card-body">
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div id="appointment-modal" class="appointment-modal">
        <div class="appointment-modal-content">
            <div class="appointment-modal-header">
                <h5><i class="fas fa-calendar-check me-2"></i>Appointment Details</h5>
                <button type="button" class="appointment-modal-close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="appointment-modal-body">
                <div class="appointment-detail-item">
                    <div class="appointment-detail-label"><i class="fas fa-user-md me-2"></i>Doctor</div>
                    <div class="appointment-detail-value" id="modal-doctor-name">-</div>
                </div>
                <div class="appointment-detail-item">
                    <div class="appointment-detail-label"><i class="fas fa-building me-2"></i>Department</div>
                    <div class="appointment-detail-value" id="modal-department">-</div>
                </div>
                <div class="appointment-detail-item">
                    <div class="appointment-detail-label"><i class="fas fa-calendar me-2"></i>Date</div>
                    <div class="appointment-detail-value" id="modal-date">-</div>
                </div>
                <div class="appointment-detail-item">
                    <div class="appointment-detail-label"><i class="fas fa-clock me-2"></i>Time</div>
                    <div class="appointment-detail-value" id="modal-time">-</div>
                </div>
                <div class="appointment-detail-item">
                    <div class="appointment-detail-label"><i class="fas fa-info-circle me-2"></i>Status</div>
                    <div class="appointment-detail-value">
                        <span class="appointment-status-badge" id="modal-status">-</span>
                    </div>
                </div>
                <div class="appointment-detail-item" id="modal-reason-item" style="display: none;">
                    <div class="appointment-detail-label"><i class="fas fa-stethoscope me-2"></i>Reason for Visit</div>
                    <div class="appointment-detail-value" id="modal-reason">-</div>
                </div>
                <div class="appointment-detail-item" id="modal-notes-item" style="display: none;">
                    <div class="appointment-detail-label"><i class="fas fa-sticky-note me-2"></i>Notes</div>
                    <div class="appointment-detail-value" id="modal-notes">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Book Appointment Section -->
    <div id="book-appointment-section" class="content-section">
        <div class="dashboard-card">
            <div class="card-header">
                <h5><i class="fas fa-plus-circle me-2"></i>Book New Appointment</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-primary">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> Available appointment times are based on the doctor's working schedule. 
                    Select a doctor and date to see available time slots.
                </div>
                <form id="appointment-form">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" id="department-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Doctor</label>
                            <select class="form-select" id="doctor-select" required disabled>
                                <option value="">Select Doctor</option>
                            </select>
                        </div>
                        <div class="col-12" id="doctor-schedule-display" style="display: none;">
                            <div class="alert alert-info">
                                <h6 class="mb-2"><i class="fas fa-clock me-2"></i>Doctor's Schedule</h6>
                                <div id="doctor-schedule-content"></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Preferred Date</label>
                            <input type="date" class="form-control" id="appointment-date" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Preferred Time</label>
                            <select class="form-select" id="appointment-time" required disabled>
                                <option value="">Select Time</option>
                            </select>
                            <small class="text-muted" id="time-slot-info"></small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason for Visit</label>
                            <textarea class="form-control" id="appointment-reason" rows="3" required placeholder="Please describe the reason for your visit..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-paper-plane me-2"></i>Request Appointment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Medical Records Section -->
    <div id="medical-records-section" class="content-section">
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-medical me-2"></i>Medical Records</h5>
                <?php if (!empty($available_years)): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="yearFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
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
                                   href="patient_dashboard.php#medical-records-section">
                                    <i class="fas fa-list me-2"></i>All Years
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php foreach ($available_years as $year): ?>
                                <li>
                                    <a class="dropdown-item <?= $selected_year == $year ? 'active' : '' ?>" 
                                       href="patient_dashboard.php?year=<?= $year ?>#medical-records-section">
                                        <i class="fas fa-calendar me-2"></i><?= htmlspecialchars($year) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($selected_year !== null): ?>
                    <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                        <i class="fas fa-filter me-2"></i>Filtered by year: <strong><?= htmlspecialchars($selected_year) ?></strong>
                        (<?= count($medical_records) ?> record<?= count($medical_records) != 1 ? 's' : '' ?>)
                        <a href="patient_dashboard.php#medical-records-section" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="fas fa-times me-1"></i>Clear Filter
                        </a>
                    </div>
                <?php elseif (!empty($medical_records)): ?>
                    <small class="text-muted d-block mb-3">
                        <i class="fas fa-info-circle me-1"></i>Showing all records (<?= count($medical_records) ?> total)
                    </small>
                <?php endif; ?>
                <style>
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
                .date-group {
                    margin-bottom: 20px;
                }
                .date-group:last-child {
                    margin-bottom: 0;
                }
                .date-header {
                    background-color: #e3f2fd;
                    padding: 12px 15px;
                    border-radius: 8px;
                    margin-bottom: 15px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-left: 4px solid #2196f3;
                }
                .date-header .date-info {
                    flex: 1;
                }
                .date-header h6 {
                    margin: 0;
                    font-size: 1em;
                    font-weight: 600;
                    color: #1976d2;
                    display: flex;
                    align-items: center;
                }
                .date-header .date-buttons {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .date-header .date-buttons .btn {
                    font-size: 0.85em;
                    padding: 6px 12px;
                    white-space: nowrap;
                }
                .record-card {
                    border: 1px solid #e9ecef;
                    border-radius: 8px;
                    margin-bottom: 15px;
                    transition: box-shadow 0.2s;
                    background-color: #fff;
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
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .record-body {
                    padding: 15px;
                    border-bottom: 1px solid #e9ecef;
                    border-radius: 8px 8px 0 0;
                }
                .record-card.filtered-out {
                    display: none;
                }
                .record-body {
                    padding: 15px;
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
                .prescriber-info {
                    background-color: #e3f2fd;
                    padding: 8px 12px;
                    border-radius: 6px;
                    margin-top: 10px;
                    border-left: 4px solid #2196f3;
                }
                </style>
                <?php if (is_array($medical_records) && count($medical_records) > 0): ?>
                    <?php 
                    // Group records by date first
                    $records_by_date = [];
                    foreach ($medical_records as $record) {
                        // Determine which date field to use based on record type
                        if ($record['record_type'] == 'medical_record' || $record['record_type'] == 'vitals') {
                            $date_field = !empty($record['visit_date']) && $record['visit_date'] != '0000-00-00' 
                                ? $record['visit_date'] 
                                : date('Y-m-d', strtotime($record['created_at']));
                        } else {
                            // For medical history, use created_at date only
                            $date_field = date('Y-m-d', strtotime($record['created_at']));
                        }
                        
                        if ($date_field) {
                            if (!isset($records_by_date[$date_field])) {
                                $records_by_date[$date_field] = [
                                    'medical_records' => [],
                                    'vitals' => [],
                                    'medical_history' => []
                                ];
                            }
                            
                            // Categorize by record type
                            if ($record['record_type'] == 'medical_record') {
                                $records_by_date[$date_field]['medical_records'][] = $record;
                            } elseif ($record['record_type'] == 'vitals') {
                                $records_by_date[$date_field]['vitals'][] = $record;
                            } elseif ($record['record_type'] == 'medical_history') {
                                $records_by_date[$date_field]['medical_history'][] = $record;
                            }
                        }
                    }
                    // Sort dates in descending order (newest first)
                    krsort($records_by_date);
                    
                    // Group dates by month for display
                    $dates_by_month = [];
                    foreach ($records_by_date as $date_key => $date_records) {
                        $month_key = date('Y-m', strtotime($date_key));
                        if (!isset($dates_by_month[$month_key])) {
                            $dates_by_month[$month_key] = [];
                        }
                        $dates_by_month[$month_key][$date_key] = $date_records;
                    }
                    // Sort months in descending order
                    krsort($dates_by_month);
                    
                    foreach ($dates_by_month as $month_key => $month_dates): 
                        $month_name = formatDate($month_key . '-01', 'F Y');
                        // Calculate total records for the month
                        $total_records = 0;
                        foreach ($month_dates as $date_records) {
                            $total_records += count($date_records['medical_records']) + 
                                             count($date_records['vitals']) + 
                                             count($date_records['medical_history']);
                        }
                        $collapse_id = 'month-' . str_replace('-', '', $month_key);
                    ?>
                        <div class="month-card">
                            <div class="month-card-header" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>" aria-expanded="false" aria-controls="<?= $collapse_id ?>">
                                <h6>
                                    <i class="fas fa-calendar-alt me-2"></i><?= $month_name ?>
                                    <span class="badge bg-primary ms-2"><?= $total_records ?> record<?= $total_records != 1 ? 's' : '' ?></span>
                                </h6>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="collapse" id="<?= $collapse_id ?>">
                                <div class="month-records-container">
                                    <?php foreach ($month_dates as $date_key => $date_records): 
                                        $date_formatted = formatDate($date_key);
                                        $date_medical_count = count($date_records['medical_records']);
                                        $date_vitals_count = count($date_records['vitals']);
                                        $date_history_count = count($date_records['medical_history']);
                                        $date_total_count = $date_medical_count + $date_vitals_count + $date_history_count;
                                        
                                        // Prepare data for modal
                                        $date_records_json = htmlspecialchars(json_encode([
                                            'date' => $date_key,
                                            'medical_records' => $date_records['medical_records'],
                                            'vitals' => $date_records['vitals'],
                                            'medical_history' => $date_records['medical_history']
                                        ]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <div class="record-card" data-date="<?= $date_key ?>" data-records-json="<?= $date_records_json ?>">
                                            <div class="record-header d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i><?= $date_formatted ?>
                                                    </small>
                                                    <div class="mt-1">
                                                        <?php if ($date_medical_count > 0): ?>
                                                            <span class="badge bg-primary me-1">
                                                                <i class="fas fa-file-medical"></i> <?= $date_medical_count ?> Medical Record<?= $date_medical_count != 1 ? 's' : '' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($date_vitals_count > 0): ?>
                                                            <span class="badge bg-success me-1">
                                                                <i class="fas fa-heartbeat"></i> <?= $date_vitals_count ?> Vital Sign<?= $date_vitals_count != 1 ? 's' : '' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($date_history_count > 0): ?>
                                                            <span class="badge bg-info me-1">
                                                                <i class="fas fa-history"></i> <?= $date_history_count ?> Medical Histor<?= $date_history_count != 1 ? 'ies' : 'y' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary" type="button" onclick="const card = this.closest('.record-card'); const dateKey = card.getAttribute('data-date'); const recordsJson = card.getAttribute('data-records-json'); showDateMedicalRecordsModal(dateKey, recordsJson); return false;" title="View All Records for <?= htmlspecialchars($date_formatted, ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="fas fa-eye"></i> View All
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-medical fa-4x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No medical records found.</p>
                        <p class="text-muted small">Your medical records will appear here once they are added by your doctor or the admin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Prescriptions Section -->
    <div id="prescriptions-section" class="content-section">
        <div class="dashboard-card">
            <div class="card-header">
                <h5><i class="fas fa-prescription-bottle-alt me-2"></i>Medicine Prescriptions</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($prescriptions_by_month)): ?>
                    <?php foreach ($prescriptions_by_month as $month_data): ?>
                        <div class="month-group-card mb-3">
                            <div class="month-group-header" onclick="toggleMonthGroup('<?php echo $month_data['month_key']; ?>')">
                                <div class="month-group-title">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo htmlspecialchars($month_data['month_name']); ?></span>
                                    <span class="month-group-count">
                                        (<?php 
                                        $total_prescriptions = 0;
                                        foreach ($month_data['dates'] as $date_data) {
                                            $total_prescriptions += count($date_data['prescriptions']);
                                        }
                                        echo $total_prescriptions . ' ' . ($total_prescriptions == 1 ? 'prescription' : 'prescriptions');
                                        ?>)
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down month-group-chevron" id="chevron-<?php echo $month_data['month_key']; ?>"></i>
                            </div>
                            <div class="month-group-content" id="content-<?php echo $month_data['month_key']; ?>">
                                <?php 
                                // Sort dates in descending order
                                $dates = $month_data['dates'];
                                krsort($dates);
                                foreach ($dates as $date_data): 
                                ?>
                                    <div class="date-group-item">
                                        <div class="date-group-title">
                                            <i class="fas fa-calendar"></i>
                                            <span><?php echo htmlspecialchars($date_data['date_display']); ?></span>
                                            <span class="date-group-count">
                                                (<?php echo count($date_data['prescriptions']); ?> 
                                                <?php echo count($date_data['prescriptions']) == 1 ? 'prescription' : 'prescriptions'; ?>)
                                            </span>
                                        </div>
                                        <div class="date-group-actions">
                                            <button class="btn btn-sm btn-primary" onclick="showPrescriptionsModal(<?php echo htmlspecialchars(json_encode($date_data['prescriptions']), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo htmlspecialchars($date_data['date_display'], ENT_QUOTES); ?>');">
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
                        <i class="fas fa-prescription-bottle-alt fa-4x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No medicine prescriptions found.</p>
                        <p class="text-muted small">Your medicine prescriptions will appear here once they are added by your doctor or the admin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notifications Section -->
    <div id="notifications-section" class="content-section">
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h5>
                <?php if (!empty($notifications)): ?>
                    <button class="btn btn-sm btn-outline-primary" id="mark-all-read">
                        <i class="fas fa-check-double me-1"></i>Mark All as Read
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No notifications yet</p>
                        <p class="text-muted small">You'll see your appointment updates and messages here.</p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                <div class="notification-icon">
                                    <?php
                                    // Set icon based on notification type
                                    $icon = 'fa-bell';
                                    $icon_color = 'primary';
                                    
                                    switch ($notification['type']) {
                                        case 'Appointment_Approved':
                                            $icon = 'fa-calendar-check';
                                            $icon_color = 'success';
                                            break;
                                        case 'Appointment_Rejected':
                                            $icon = 'fa-times-circle';
                                            $icon_color = 'danger';
                                            break;
                                        case 'Appointment_Reminder':
                                            $icon = 'fa-clock';
                                            $icon_color = 'warning';
                                            break;
                                        case 'Appointment_Rescheduled':
                                            $icon = 'fa-calendar-alt';
                                            $icon_color = 'info';
                                            break;
                                        case 'Registration_Approved':
                                            $icon = 'fa-user-check';
                                            $icon_color = 'success';
                                            break;
                                        case 'Registration_Rejected':
                                            $icon = 'fa-user-times';
                                            $icon_color = 'danger';
                                            break;
                                        case 'Medical_Record_Updated':
                                            $icon = 'fa-file-medical';
                                            $icon_color = 'info';
                                            break;
                                        default:
                                            $icon = 'fa-envelope';
                                            $icon_color = 'primary';
                                            break;
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon; ?> text-<?php echo $icon_color; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <h6 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                    <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small class="notification-time text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php
                                        $time_ago = time() - strtotime($notification['created_at']);
                                        if ($time_ago < 60) {
                                            echo 'Just now';
                                        } elseif ($time_ago < 3600) {
                                            echo floor($time_ago / 60) . ' minutes ago';
                                        } elseif ($time_ago < 86400) {
                                            echo floor($time_ago / 3600) . ' hours ago';
                                        } elseif ($time_ago < 604800) {
                                            echo floor($time_ago / 86400) . ' days ago';
                                        } else {
                                            echo formatDate($notification['created_at']);
                                        }
                                        ?>
                                    </small>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="btn btn-sm btn-link mark-read" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-link text-danger delete-notification" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total_notif_pages > 1): ?>
                        <nav aria-label="Notification pagination" class="mt-3">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <li class="page-item <?php echo $notif_page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link notification-page-link-dashboard" href="#" data-page="<?php echo $notif_page - 1; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_notif_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $notif_page ? 'active' : ''; ?>">
                                        <a class="page-link notification-page-link-dashboard" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $notif_page >= $total_notif_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link notification-page-link-dashboard" href="#" data-page="<?php echo $notif_page + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Section -->
    <div id="profile-section" class="content-section">
        <div class="dashboard-card" 
             data-patient-first-name="<?php echo htmlspecialchars($patient_user['first_name'] ?? '', ENT_QUOTES); ?>"
             data-patient-last-name="<?php echo htmlspecialchars($patient_user['last_name'] ?? '', ENT_QUOTES); ?>">
            <div class="card-header">
                <h5><i class="fas fa-user me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body">
                <?php
                // Get current profile image if exists
                $profile_image = isset($patient_user['profile_image']) && !empty($patient_user['profile_image']) 
                    ? htmlspecialchars($patient_user['profile_image']) 
                    : 'img/defaultDP.jpg';
                ?>
                <div id="profile-message" class="alert" style="display: none;"></div>
                <div class="row g-4">
                    <!-- Left Side: Personal Information -->
                    <div class="col-12 col-lg-6">
                        <div class="info-card">
                            <h6 class="mb-3"><i class="fas fa-user-circle me-2 text-primary"></i>Personal Information</h6>
                            <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($patient_user['first_name'] . ' ' . $patient_user['last_name']); ?></p>
                            <p class="mb-2"><strong>Date of Birth:</strong> <?php echo formatDate($patient_user['date_of_birth']); ?></p>
                            <p class="mb-2"><strong>Sex:</strong> <?php echo htmlspecialchars($patient_user['sex']); ?></p>
                            <hr class="my-3">
                            <h6 class="mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Account Status</h6>
                            <p class="mb-2"><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $patient_user['status'] === 'Active' ? 'success' : 'warning'; ?> ms-2">
                                    <?php echo htmlspecialchars($patient_user['status']); ?>
                                </span>
                            </p>
                            <p class="mb-2"><strong>Username:</strong> <?php echo htmlspecialchars($patient_user['username']); ?></p>
                            <p class="mb-0"><strong>Last Login:</strong> 
                                <?php echo $patient_user['last_login'] ? formatDateTime($patient_user['last_login']) : 'Never'; ?>
                            </p>
                            <p class="text-muted small mt-3 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Personal information can only be updated by contacting the administrator.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Right Side: Editable Fields (Contact Details, Profile Image & Password) -->
                    <div class="col-12 col-lg-6">
                        <div class="info-card">
                            <h6 class="mb-3"><i class="fas fa-edit me-2 text-primary"></i>Account Settings</h6>
                            
                           <!-- Profile Image Upload -->
                           <div class="mb-4">
                                <label class="form-label"><i class="fas fa-image me-2"></i>Profile Picture</label>
                                <div class="profile-image-upload-container">
                                    <div class="profile-image-preview mb-3">
                                        <img id="profile-image-preview" src="<?php echo htmlspecialchars($profile_image); ?>" 
                                             alt="Profile Picture" class="profile-preview-img"
                                             onerror="this.src='img/defaultDP.jpg'">
                                    </div>
                                    <div class="input-group">
                                        <input type="file" class="form-control" id="profile-image-input" 
                                               accept="image/jpeg,image/png,image/gif" name="profile_image">
                                        <button class="btn btn-outline-primary" type="button" id="upload-image-btn">
                                            <i class="fas fa-upload me-2"></i>Upload
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Accepted formats: JPG, PNG, GIF. Max size: 5MB
                                    </small>
                                </div>
                            </div>
                           
                            <hr class="my-4">
                        
                            <!-- Contact Details (Editable) -->
                            <div class="mb-4">
                                <label class="form-label"><i class="fas fa-address-card me-2"></i>Contact Details</label>
                                <div class="mb-3">
                                    <label class="form-label small">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="profile-email" 
                                           value="<?php echo htmlspecialchars($patient_user['email'] ?? ''); ?>" 
                                           placeholder="Enter your email address" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">+63</span>
                                        <input type="tel" class="form-control" id="profile-phone" 
                                               value="<?php echo htmlspecialchars(preg_replace('/^\+63|^0/', '', phoneToInputFormat($patient_user['phone'] ?? ''))); ?>" 
                                               pattern="^\d{10}$" inputmode="numeric" maxlength="10"
                                               placeholder="9123456789" required>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.
                                    </small>
                                </div>
                                <button class="btn btn-success" type="button" id="update-contact-btn">
                                    <i class="fas fa-save me-2"></i>Save Contact Details
                                </button>
                            </div>
                            
                            <hr class="my-4">
                            

                            
                            <!-- Password Change -->
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>Change Password</label>
                                <div class="mb-2">
                                    <input type="password" class="form-control" id="current-password" 
                                           placeholder="Current Password" name="current_password">
                                </div>
                                <div class="mb-2">
                                    <input type="password" class="form-control" id="new-password" 
                                           placeholder="New Password" name="new_password">
                                </div>
                                <div class="mb-2">
                                    <input type="password" class="form-control" id="confirm-password" 
                                           placeholder="Confirm New Password" name="confirm_password">
                                </div>
                                <button class="btn btn-primary" type="button" id="change-password-btn">
                                    <i class="fas fa-key me-2"></i>Update Password
                                </button>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Password must be at least 8 characters long
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
// Patient data for contact update - get from data attributes
function getPatientData() {
    const profileCard = document.querySelector('#profile-section .dashboard-card');
    if (profileCard) {
        return {
            firstName: profileCard.dataset.patientFirstName || '',
            lastName: profileCard.dataset.patientLastName || ''
        };
    }
    return { firstName: '', lastName: '' };
}

// Global calendar instance
let calendarInstance = null;

function showSection(sectionName) {
    // Normalize section name
    let normalized = (sectionName || '').replace('#', '') || 'dashboard';
    
    // Check if the normalized name already ends with '-section'
    // If not, add it. This handles both 'medical-records' and 'medical-records-section'
    let sectionId = normalized;
    if (!normalized.endsWith('-section')) {
        sectionId = `${normalized}-section`;
    }
    
    // For sidebar navigation, use the base name without '-section'
    const baseName = normalized.replace('-section', '');
    
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected section
    const sectionElement = document.getElementById(sectionId);
    if (sectionElement) {
        sectionElement.classList.add('active');
        
        // If showing appointments section, initialize/update calendar
        if (baseName === 'appointments') {
            // Initialize calendar if not already initialized
            if (!calendarInstance) {
                initializeCalendar();
            } else {
                // Update calendar size after section becomes visible
                updateCalendarSize();
            }
        }
    } else {
        console.warn(`Section element not found: ${sectionId}, falling back to dashboard`);
        // Fallback to dashboard if section not found
        const dashboardElement = document.getElementById('dashboard-section');
        if (dashboardElement) {
            dashboardElement.classList.add('active');
        }
    }
    
    // Update active nav item in sidebar
    document.querySelectorAll('.patient-nav-link').forEach(link => {
        if (link.dataset.section === baseName) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
    
    // Update URL hash (use base name for consistency)
    history.replaceState(null, '', `#${baseName}`);
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Function to initialize calendar
function initializeCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (calendarEl && !calendarInstance) {
        calendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: function(fetchInfo, successCallback, failureCallback) {
                fetch('get_patient_appointments.php')
                    .then(response => response.json())
                    .then(data => successCallback(data))
                    .catch(error => {
                        console.error('Error fetching appointments:', error);
                        failureCallback(error);
                    });
            },
            eventClick: function(info) {
                showAppointmentModal(info.event);
            },
            eventDisplay: 'block',
            dayMaxEvents: 3,
            moreLinkClick: 'popover'
        });
        // Render calendar after a short delay to ensure DOM is ready
        requestAnimationFrame(() => {
            setTimeout(() => {
                if (calendarInstance) {
                    calendarInstance.render();
                    // Update size after rendering to ensure correct dimensions
                    calendarInstance.updateSize();
                }
            }, 100);
        });
    }
}

// Function to update calendar size when section becomes visible
function updateCalendarSize() {
    if (calendarInstance) {
        // Use requestAnimationFrame to ensure DOM has updated
        requestAnimationFrame(() => {
            setTimeout(() => {
                if (calendarInstance) {
                    calendarInstance.updateSize();
                }
            }, 150);
        });
    }
}

// Make showSection available globally
window.showSection = showSection;

document.addEventListener('DOMContentLoaded', function() {
    // Handle initial section from URL hash - show immediately
    const hash = window.location.hash ? window.location.hash.substring(1) : '';
    const initialSection = hash || 'dashboard';
    
    // Show section immediately
    showSection(initialSection);
    
    // Also ensure section is visible after a short delay (in case of timing issues)
    setTimeout(function() {
        const hashAfterLoad = window.location.hash ? window.location.hash.substring(1) : '';
        if (hashAfterLoad) {
            showSection(hashAfterLoad);
        }
    }, 100);
    
    // Handle hash changes
    window.addEventListener('hashchange', function() {
        const section = window.location.hash ? window.location.hash.substring(1) : 'dashboard';
        showSection(section);
    });
    
    // Handle sidebar navigation clicks
    document.querySelectorAll('.patient-nav-link').forEach(link => {
        link.addEventListener('click', function(event) {
            const section = link.dataset.section;
            if (section) {
                event.preventDefault();
                showSection(section);
            }
        });
    });
    
    // Handle notification pagination clicks
    document.querySelectorAll('.notification-page-link-dashboard').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(this.dataset.page);
            if (page > 0) {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('notif_page', page);
                const hash = window.location.hash || '#notifications-section';
                window.location.href = window.location.pathname + '?' + urlParams.toString() + hash;
            }
        });
    });
    
    // Initialize calendar if appointments section is initially visible
    if (initialSection === 'appointments') {
        initializeCalendar();
        updateCalendarSize();
    }
    
    // Add click handlers for appointment cards in dashboard
    document.querySelectorAll('.appointment-card[data-appointment]').forEach(card => {
        card.addEventListener('click', function() {
            const appointmentData = JSON.parse(this.getAttribute('data-appointment'));
            if (window.showAppointmentDetails) {
                window.showAppointmentDetails(appointmentData);
            }
        });
    });
    
    // Appointment Modal Functions - Make globally accessible
    window.showAppointmentModal = function(event) {
        const modal = document.getElementById('appointment-modal');
        if (!modal) return;
        const props = event.extendedProps || {};
        
        // Format date
        const appointmentDate = new Date(event.start);
        const formattedDate = appointmentDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Format time
        const formattedTime = appointmentDate.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        
        // Set doctor name
        document.getElementById('modal-doctor-name').textContent = props.doctor_name || event.title || 'Unknown Doctor';
        
        // Set department
        document.getElementById('modal-department').textContent = props.department || 'General';
        
        // Set date
        document.getElementById('modal-date').textContent = formattedDate;
        
        // Set time
        document.getElementById('modal-time').textContent = formattedTime;
        
        // Set status
        const status = props.status || 'Scheduled';
        const statusBadge = document.getElementById('modal-status');
        statusBadge.textContent = status;
        const statusClass = status.toLowerCase().replace(/\s+/g, '-');
        statusBadge.className = 'appointment-status-badge appointment-status-' + statusClass;
        
        // Set reason (if available)
        const reasonItem = document.getElementById('modal-reason-item');
        const reasonValue = document.getElementById('modal-reason');
        if (props.reason && props.reason.trim()) {
            reasonValue.textContent = props.reason;
            reasonItem.style.display = 'block';
        } else {
            reasonItem.style.display = 'none';
        }
        
        // Set notes (if available)
        const notesItem = document.getElementById('modal-notes-item');
        const notesValue = document.getElementById('modal-notes');
        if (props.notes && props.notes.trim()) {
            notesValue.textContent = props.notes;
            notesItem.style.display = 'block';
        } else {
            notesItem.style.display = 'none';
        }
        
        // Show modal
        modal.classList.add('show');
    }
    
    // Global function to show appointment details from appointment data (for appointment cards)
    window.showAppointmentDetails = function(appointmentData) {
        const modal = document.getElementById('appointment-modal');
        if (!modal) return;
        
        // Format date
        const appointmentDate = new Date(appointmentData.date + 'T' + appointmentData.time);
        const formattedDate = appointmentDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Format time
        const timeDate = new Date('2000-01-01T' + appointmentData.time);
        const formattedTime = timeDate.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        
        // Set doctor name
        document.getElementById('modal-doctor-name').textContent = appointmentData.doctor_name || 'Unknown Doctor';
        
        // Set department
        document.getElementById('modal-department').textContent = appointmentData.department || 'General';
        
        // Set date
        document.getElementById('modal-date').textContent = formattedDate;
        
        // Set time
        document.getElementById('modal-time').textContent = formattedTime;
        
        // Set status
        const status = appointmentData.status || 'Scheduled';
        const statusBadge = document.getElementById('modal-status');
        statusBadge.textContent = status;
        const statusClass = status.toLowerCase().replace(/\s+/g, '-');
        statusBadge.className = 'appointment-status-badge appointment-status-' + statusClass;
        
        // Set reason (if available)
        const reasonItem = document.getElementById('modal-reason-item');
        const reasonValue = document.getElementById('modal-reason');
        if (appointmentData.reason && appointmentData.reason.trim()) {
            reasonValue.textContent = appointmentData.reason;
            reasonItem.style.display = 'block';
        } else {
            reasonItem.style.display = 'none';
        }
        
        // Set notes (if available)
        const notesItem = document.getElementById('modal-notes-item');
        const notesValue = document.getElementById('modal-notes');
        if (appointmentData.notes && appointmentData.notes.trim()) {
            notesValue.textContent = appointmentData.notes;
            notesItem.style.display = 'block';
        } else {
            notesItem.style.display = 'none';
        }
        
        // Show modal
        modal.classList.add('show');
    }
    
    function closeAppointmentModal() {
        const modal = document.getElementById('appointment-modal');
        modal.classList.remove('show');
    }
    
    // Close modal handlers
    const modalCloseBtn = document.querySelector('.appointment-modal-close');
    const appointmentModal = document.getElementById('appointment-modal');
    
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeAppointmentModal);
    }
    
    if (appointmentModal) {
        appointmentModal.addEventListener('click', function(e) {
            if (e.target === appointmentModal) {
                closeAppointmentModal();
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && appointmentModal.classList.contains('show')) {
                closeAppointmentModal();
            }
        });
    }
    
    // Department selection handler
    const departmentSelect = document.getElementById('department-select');
    
    if (departmentSelect) {
        departmentSelect.addEventListener('change', function() {
            const departmentId = this.value;
            
            if (departmentId) {
                fetch(`get_doctors_by_department.php?department_id=${departmentId}`)
                    .then(response => response.json())
                    .then(data => {
                        const doctorSelectEl = document.getElementById('doctor-select');
                        const timeSelectEl = document.getElementById('appointment-time');
                        if (doctorSelectEl) {
                            doctorSelectEl.innerHTML = '<option value="">Select Doctor</option>';
                            data.forEach(doctor => {
                                doctorSelectEl.innerHTML += `<option value="${doctor.id}">Dr. ${doctor.first_name} ${doctor.last_name}</option>`;
                            });
                            doctorSelectEl.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching doctors:', error);
                        showAlert('Error loading doctors. Please try again.', 'Error', 'error');
                    });
            } else {
                const doctorSelectEl = document.getElementById('doctor-select');
                const timeSelectEl = document.getElementById('appointment-time');
                if (doctorSelectEl) {
                    doctorSelectEl.innerHTML = '<option value="">Select Doctor</option>';
                    doctorSelectEl.disabled = true;
                }
                if (timeSelectEl) {
                    timeSelectEl.innerHTML = '<option value="">Select Time</option>';
                    timeSelectEl.disabled = true;
                }
            }
        });
    }
    
    // Function to load available time slots
    const loadAvailableTimes = () => {
        // Re-get elements in case they weren't available when script first ran
        const doctorSelectEl = document.getElementById('doctor-select');
        const appointmentDateEl = document.getElementById('appointment-date');
        const timeSelectEl = document.getElementById('appointment-time');
        const timeSlotInfo = document.getElementById('time-slot-info');
        
        // Check if all required elements exist
        if (!doctorSelectEl || !appointmentDateEl || !timeSelectEl) {
            console.warn('Appointment form elements not found');
            return;
        }
        
        const doctorId = doctorSelectEl.value;
        const date = appointmentDateEl.value;
        
        if (doctorId && date) {
            // Show loading state
            timeSelectEl.innerHTML = '<option value="">Loading available times...</option>';
            timeSelectEl.disabled = true;
            if (timeSlotInfo) {
                timeSlotInfo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking doctor\'s schedule...';
                timeSlotInfo.className = 'text-muted';
            }
            
            // Validate date format (should be YYYY-MM-DD)
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                console.error('Invalid date format:', date);
                timeSelectEl.innerHTML = '<option value="">Invalid date format</option>';
                timeSelectEl.disabled = true;
                if (timeSlotInfo) {
                    timeSlotInfo.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please select a valid date.';
                    timeSlotInfo.className = 'text-danger';
                }
                return;
            }
            
            // Validate inputs
            if (!doctorId || !/^\d+$/.test(doctorId)) {
                console.error('Invalid doctor ID:', doctorId);
                timeSelectEl.innerHTML = '<option value="">Invalid doctor selected</option>';
                timeSelectEl.disabled = true;
                if (timeSlotInfo) {
                    timeSlotInfo.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please select a valid doctor.';
                    timeSlotInfo.className = 'text-danger';
                }
                return;
            }
            
            if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                console.error('Invalid date format:', date);
                timeSelectEl.innerHTML = '<option value="">Invalid date format</option>';
                timeSelectEl.disabled = true;
                if (timeSlotInfo) {
                    timeSlotInfo.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please select a valid date.';
                    timeSlotInfo.className = 'text-danger';
                }
                return;
            }
            
            const apiUrl = `get_doctor_availability.php?action=time_slots&doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`;
            console.log('Fetching time slots from:', apiUrl);
            console.log('Doctor ID:', doctorId, 'Date:', date);
            
            fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', Object.fromEntries(response.headers.entries()));
                    
                    // Check if response is ok
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('HTTP error response:', text);
                            let errorMessage = `HTTP error! status: ${response.status}`;
                            try {
                                const errorData = JSON.parse(text);
                                if (errorData.message) {
                                    errorMessage = errorData.message;
                                }
                            } catch (e) {
                                // Not JSON, use text
                                if (text.length > 0) {
                                    errorMessage = text.substring(0, 200);
                                }
                            }
                            throw new Error(errorMessage);
                        });
                    }
                    
                    // Check if response is JSON
                    const contentType = response.headers.get("content-type");
                    console.log('Response content-type:', contentType);
                    if (!contentType || !contentType.includes("application/json")) {
                        return response.text().then(text => {
                            console.error('Non-JSON response:', text);
                            throw new Error("Server did not return JSON. Response: " + text.substring(0, 200));
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    
                    if (!timeSelectEl) {
                        console.error('Time select element not found');
                        return;
                    }
                    
                    timeSelectEl.innerHTML = '<option value="">Select Time</option>';
                    
                    // Check if data is valid
                    if (!data) {
                        console.error('No data received from server');
                        timeSelectEl.innerHTML = '<option value="">Error loading times</option>';
                        timeSelectEl.disabled = true;
                        if (timeSlotInfo) {
                            timeSlotInfo.innerHTML = '<i class="fas fa-times-circle"></i> No response from server. Please try again.';
                            timeSlotInfo.className = 'text-danger';
                        }
                        return;
                    }
                    
                    // Check if request was successful
                    if (!data.success) {
                        // Request failed - show error message
                        timeSelectEl.innerHTML = '<option value="">No available time slots</option>';
                        timeSelectEl.disabled = true;
                        if (timeSlotInfo) {
                            const errorMsg = data.message || 'Doctor is not available on this date or all slots are booked.';
                            timeSlotInfo.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMsg}`;
                            timeSlotInfo.className = 'text-warning';
                        }
                        return;
                    }
                    
                    // Check if time_slots array exists and has items
                    if (!data.time_slots || !Array.isArray(data.time_slots) || data.time_slots.length === 0) {
                        timeSelectEl.innerHTML = '<option value="">No available time slots</option>';
                        timeSelectEl.disabled = true;
                        if (timeSlotInfo) {
                            const errorMsg = data.message || 'No available time slots for this date.';
                            timeSlotInfo.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMsg}`;
                            timeSlotInfo.className = 'text-warning';
                        }
                        return;
                    }
                    
                    // Add available time slots
                    data.time_slots.forEach(slot => {
                        if (slot && slot.value && slot.display) {
                            const option = document.createElement('option');
                            option.value = slot.value;
                            option.textContent = slot.display;
                            timeSelectEl.appendChild(option);
                        }
                    });
                    
                    timeSelectEl.disabled = false;
                    if (timeSlotInfo) {
                        timeSlotInfo.innerHTML = `<i class="fas fa-check-circle"></i> ${data.time_slots.length} available time slot(s) based on doctor's schedule.`;
                        timeSlotInfo.className = 'text-success';
                    }
                })
                .catch(error => {
                    console.error('Error fetching time slots:', error);
                    console.error('Error details:', {
                        message: error.message,
                        doctorId: doctorId,
                        date: date,
                        stack: error.stack
                    });
                    if (!timeSelectEl) return;
                    
                    timeSelectEl.innerHTML = '<option value="">Error loading times</option>';
                    timeSelectEl.disabled = true;
                    if (timeSlotInfo) {
                        let errorMsg = 'Error loading time slots. ';
                        if (error.message) {
                            errorMsg += error.message;
                        } else {
                            errorMsg += 'Please check that both doctor and date are selected correctly.';
                        }
                        timeSlotInfo.innerHTML = `<i class="fas fa-times-circle"></i> ${errorMsg}`;
                        timeSlotInfo.className = 'text-danger';
                    }
                });
        } else {
            if (!timeSelectEl) return;
            timeSelectEl.innerHTML = '<option value="">Select doctor and date first</option>';
            timeSelectEl.disabled = true;
            if (timeSlotInfo) {
                timeSlotInfo.innerHTML = '';
            }
        }
    };
    
    // Store available day numbers for date validation
    let availableDayNumbers = [];
    
    // Function to get day of week number (1=Monday, 7=Sunday) from a date string
    const getDayOfWeek = (dateString) => {
        const date = new Date(dateString + 'T00:00:00');
        const day = date.getDay();
        // Convert from JavaScript's 0-6 (Sunday-Saturday) to 1-7 (Monday-Sunday)
        return day === 0 ? 7 : day;
    };
    
    // Function to validate if a date falls on an available day
    const isValidDateForDoctor = (dateString) => {
        if (!dateString || availableDayNumbers.length === 0) {
            return false;
        }
        const dayOfWeek = getDayOfWeek(dateString);
        return availableDayNumbers.includes(dayOfWeek);
    };
    
    // Function to update date picker helper text
    const updateDatePickerHelper = (availableDays) => {
        const dateInput = document.getElementById('appointment-date');
        if (!dateInput) return;
        
        // Remove existing helper text if any
        let helperText = dateInput.parentElement.querySelector('.date-helper-text');
        if (helperText) {
            helperText.remove();
        }
        
        if (availableDays.length > 0) {
            const dayNames = {
                1: 'Monday',
                2: 'Tuesday',
                3: 'Wednesday',
                4: 'Thursday',
                5: 'Friday',
                6: 'Saturday',
                7: 'Sunday'
            };
            
            const availableDayNames = availableDays.map(dayNum => dayNames[dayNum]).join(', ');
            helperText = document.createElement('small');
            helperText.className = 'form-text text-muted date-helper-text mt-1';
            helperText.innerHTML = `<i class="fas fa-calendar-check me-1"></i>Available days: <strong>${availableDayNames}</strong>`;
            dateInput.parentElement.appendChild(helperText);
        }
    };
    
    // Function to load doctor's schedule
    const loadDoctorSchedule = () => {
        const doctorSelectEl = document.getElementById('doctor-select');
        if (!doctorSelectEl) return;
        const doctorId = doctorSelectEl.value;
        const scheduleDisplay = document.getElementById('doctor-schedule-display');
        const scheduleContent = document.getElementById('doctor-schedule-content');
        const appointmentDateEl = document.getElementById('appointment-date');
        
        if (!doctorId) {
            scheduleDisplay.style.display = 'none';
            availableDayNumbers = [];
            // Clear date picker helper text
            const helperText = document.querySelector('.date-helper-text');
            if (helperText) helperText.remove();
            // Clear selected date
            if (appointmentDateEl) {
                appointmentDateEl.value = '';
            }
            return;
        }
        
        scheduleContent.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading schedule...';
        scheduleDisplay.style.display = 'block';
        
        fetch(`get_doctor_availability.php?doctor_id=${doctorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.schedule) {
                    let scheduleHTML = '<div class="row g-2">';
                    availableDayNumbers = []; // Reset available days
                    
                    data.schedule.forEach(item => {
                        const badgeClass = item.available ? 'bg-success' : 'bg-secondary';
                        scheduleHTML += `
                            <div class="col-md-6">
                                <span class="badge ${badgeClass} me-2">${item.day}</span>
                                <small>${item.hours}</small>
                            </div>
                        `;
                        
                        // Store available day numbers
                        if (item.available && item.day_num) {
                            const dayNum = parseInt(item.day_num);
                            if (!availableDayNumbers.includes(dayNum)) {
                                availableDayNumbers.push(dayNum);
                            }
                        }
                    });
                    scheduleHTML += '</div>';
                    scheduleContent.innerHTML = scheduleHTML;
                    
                    // Update date picker helper text
                    updateDatePickerHelper(availableDayNumbers);
                    
                    // Validate current date selection if any
                    if (appointmentDateEl && appointmentDateEl.value) {
                        if (!isValidDateForDoctor(appointmentDateEl.value)) {
                            appointmentDateEl.value = '';
                            appointmentDateEl.classList.add('is-invalid');
                            const timeSelectEl = document.getElementById('appointment-time');
                            const timeSlotInfo = document.getElementById('time-slot-info');
                            if (timeSelectEl) {
                                timeSelectEl.innerHTML = '<option value="">Select Time</option>';
                                timeSelectEl.disabled = true;
                            }
                            if (timeSlotInfo) {
                                timeSlotInfo.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> Please select a date that falls on one of the doctor\'s available days.';
                                timeSlotInfo.className = 'text-danger';
                            }
                        } else {
                            appointmentDateEl.classList.remove('is-invalid');
                        }
                    }
                } else {
                    scheduleContent.innerHTML = `<small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>${data.message || 'Schedule information not available'}</small>`;
                    availableDayNumbers = [];
                    // Clear date picker helper text
                    const helperText = document.querySelector('.date-helper-text');
                    if (helperText) helperText.remove();
                }
            })
            .catch(error => {
                console.error('Error fetching doctor schedule:', error);
                scheduleContent.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Error loading schedule</small>';
                availableDayNumbers = [];
                // Clear date picker helper text
                const helperText = document.querySelector('.date-helper-text');
                if (helperText) helperText.remove();
            });
    };
    
    // Attach event listeners using element references that are re-queried
    const doctorSelectEl = document.getElementById('doctor-select');
    const appointmentDateEl = document.getElementById('appointment-date');
    
    if (doctorSelectEl) {
        doctorSelectEl.addEventListener('change', () => {
            loadDoctorSchedule();
            loadAvailableTimes();
        });
    }
    
    if (appointmentDateEl) {
        appointmentDateEl.addEventListener('change', function() {
            // Ensure date is in correct format before loading times
            const selectedDate = this.value;
            const timeSelectEl = document.getElementById('appointment-time');
            const timeSlotInfo = document.getElementById('time-slot-info');
            
            if (selectedDate && /^\d{4}-\d{2}-\d{2}$/.test(selectedDate)) {
                // Check if doctor is selected and validate date against available days
                const doctorSelectEl = document.getElementById('doctor-select');
                if (doctorSelectEl && doctorSelectEl.value && availableDayNumbers.length > 0) {
                    if (!isValidDateForDoctor(selectedDate)) {
                        // Date doesn't fall on an available day
                        this.classList.add('is-invalid');
                        this.setCustomValidity('Please select a date that falls on one of the doctor\'s available days.');
                        
                        if (timeSelectEl) {
                            timeSelectEl.innerHTML = '<option value="">Select Time</option>';
                            timeSelectEl.disabled = true;
                        }
                        if (timeSlotInfo) {
                            const dayNames = {
                                1: 'Monday',
                                2: 'Tuesday',
                                3: 'Wednesday',
                                4: 'Thursday',
                                5: 'Friday',
                                6: 'Saturday',
                                7: 'Sunday'
                            };
                            const selectedDayName = dayNames[getDayOfWeek(selectedDate)];
                            const availableDayNames = availableDayNumbers.map(dayNum => dayNames[dayNum]).join(', ');
                            timeSlotInfo.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> <strong>${selectedDayName}</strong> is not available. Please select a date that falls on: <strong>${availableDayNames}</strong>`;
                            timeSlotInfo.className = 'text-danger mt-1';
                        }
                        return;
                    } else {
                        // Date is valid, remove error styling
                        this.classList.remove('is-invalid');
                        this.setCustomValidity('');
                        if (timeSlotInfo) {
                            timeSlotInfo.innerHTML = '';
                            timeSlotInfo.className = '';
                        }
                    }
                } else {
                    // No doctor selected yet, just remove any error styling
                    this.classList.remove('is-invalid');
                    this.setCustomValidity('');
                }
                
                // Load available times for the selected date
                loadAvailableTimes();
            } else if (selectedDate) {
                console.warn('Invalid date format:', selectedDate);
                this.classList.add('is-invalid');
                this.setCustomValidity('Please select a valid date.');
                if (timeSelectEl) {
                    timeSelectEl.innerHTML = '<option value="">Invalid date format</option>';
                    timeSelectEl.disabled = true;
                }
                if (timeSlotInfo) {
                    timeSlotInfo.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please select a valid date.';
                    timeSlotInfo.className = 'text-danger';
                }
            } else {
                // Date cleared
                this.classList.remove('is-invalid');
                this.setCustomValidity('');
                if (timeSelectEl) {
                    timeSelectEl.innerHTML = '<option value="">Select Time</option>';
                    timeSelectEl.disabled = true;
                }
                if (timeSlotInfo) {
                    timeSlotInfo.innerHTML = '';
                    timeSlotInfo.className = '';
                }
            }
        });
        appointmentDateEl.min = new Date().toISOString().split('T')[0];
    }
    
    // Appointment form submission
    const appointmentForm = document.getElementById('appointment-form');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const doctorSelectEl = document.getElementById('doctor-select');
            const departmentSelectEl = document.getElementById('department-select');
            const appointmentDateEl = document.getElementById('appointment-date');
            const timeSelectEl = document.getElementById('appointment-time');
            
            // Validate date selection against doctor's available days
            if (appointmentDateEl && appointmentDateEl.value && doctorSelectEl && doctorSelectEl.value && availableDayNumbers.length > 0) {
                if (!isValidDateForDoctor(appointmentDateEl.value)) {
                    appointmentDateEl.classList.add('is-invalid');
                    appointmentDateEl.setCustomValidity('Please select a date that falls on one of the doctor\'s available days.');
                    appointmentDateEl.reportValidity();
                    
                    const dayNames = {
                        1: 'Monday',
                        2: 'Tuesday',
                        3: 'Wednesday',
                        4: 'Thursday',
                        5: 'Friday',
                        6: 'Saturday',
                        7: 'Sunday'
                    };
                    const selectedDayName = dayNames[getDayOfWeek(appointmentDateEl.value)];
                    const availableDayNames = availableDayNumbers.map(dayNum => dayNames[dayNum]).join(', ');
                    const timeSlotInfo = document.getElementById('time-slot-info');
                    if (timeSlotInfo) {
                        timeSlotInfo.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> <strong>${selectedDayName}</strong> is not available. Please select a date that falls on: <strong>${availableDayNames}</strong>`;
                        timeSlotInfo.className = 'text-danger mt-1';
                    }
                    return;
                }
            }
            
            const formData = {
                doctor_id: doctorSelectEl ? doctorSelectEl.value : '',
                department_id: departmentSelectEl ? departmentSelectEl.value : '',
                preferred_date: appointmentDateEl ? appointmentDateEl.value : '',
                preferred_time: timeSelectEl ? timeSelectEl.value : '',
                reason: document.getElementById('appointment-reason') ? document.getElementById('appointment-reason').value : ''
            };
            
            fetch('submit_appointment_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("Server did not return JSON");
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showAlert('Appointment request submitted successfully! You will be notified once approved.', 'Success', 'success');
                    appointmentForm.reset();
                    const doctorSelectEl = document.getElementById('doctor-select');
                    const timeSelectEl = document.getElementById('appointment-time');
                    if (doctorSelectEl) doctorSelectEl.disabled = true;
                    if (timeSelectEl) {
                        timeSelectEl.disabled = true;
                        timeSelectEl.innerHTML = '<option value="">Select Time</option>';
                    }
                    // Hide doctor schedule
                    const scheduleDisplay = document.getElementById('doctor-schedule-display');
                    if (scheduleDisplay) scheduleDisplay.style.display = 'none';
                    // Clear time slot info
                    const timeSlotInfo = document.getElementById('time-slot-info');
                    if (timeSlotInfo) timeSlotInfo.innerHTML = '';
                } else {
                    showAlert('Error: ' + (data.message || 'Unknown error occurred'), 'Error', 'error');
                }
            })
            .catch(error => {
                console.error('Error submitting appointment:', error);
                showAlert('Error submitting appointment: ' + error.message + '\n\nPlease check the browser console for more details and try again.', 'Error', 'error');
            });
        });
    }
    
    // Notifications functionality
    // Mark single notification as read
    document.querySelectorAll('.mark-read').forEach(button => {
        button.addEventListener('click', function() {
            const notificationItem = this.closest('.notification-item');
            const notificationId = notificationItem.dataset.id;
            
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationItem.classList.remove('unread');
                    this.remove();
                    updateNotificationBadge();
                    // Also update header badge if function exists
                    if (typeof updatePatientNotificationBadge === 'function') {
                        updatePatientNotificationBadge();
                    }
                } else {
                    showAlert('Error: ' + data.message, 'Error', 'error');
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
                showAlert('Error marking notification as read. Please try again.', 'Error', 'error');
            });
        });
    });
    
    // Mark all notifications as read
    const markAllReadBtn = document.getElementById('mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            fetch('mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        document.querySelectorAll('.mark-read').forEach(btn => btn.remove());
                        this.remove();
                        updateNotificationBadge();
                        // Also update header badge if function exists
                        if (typeof updatePatientNotificationBadge === 'function') {
                            updatePatientNotificationBadge();
                        }
                        showAlert('All notifications marked as read!', 'Success', 'success');
                    } else {
                        showAlert('Error: ' + data.message, 'Error', 'error');
                    }
                })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
                showAlert('Error marking all notifications as read. Please try again.', 'Error', 'error');
            });
        });
    }
    
    // Delete notification
    document.querySelectorAll('.delete-notification').forEach(button => {
        button.addEventListener('click', function() {
            confirmDialog('Are you sure you want to delete this notification?', 'Delete', 'Cancel').then(function(confirmed) {
                if (!confirmed) return;
                
                const notificationItem = button.closest('.notification-item');
                const notificationId = notificationItem.dataset.id;
                
                // Add removing animation
                notificationItem.classList.add('removing');
                
                fetch('delete_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id=' + notificationId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setTimeout(() => {
                            notificationItem.remove();
                            
                            // Check if notifications list is empty or if we need to handle pagination
                            const notificationsList = document.querySelector('.notifications-list');
                            const currentNotificationsCount = notificationsList ? notificationsList.querySelectorAll('.notification-item').length : 0;
                            
                            // Get current page from URL
                            const urlParams = new URLSearchParams(window.location.search);
                            let currentPage = parseInt(urlParams.get('notif_page')) || 1;
                            
                            // If we deleted the last item on the current page and we're not on page 1, go to previous page
                            if (currentNotificationsCount === 0 && currentPage > 1) {
                                urlParams.set('notif_page', currentPage - 1);
                                window.location.href = window.location.pathname + '?' + urlParams.toString() + window.location.hash;
                                return;
                            }
                            
                            // If list is empty, reload to show empty state
                            if (currentNotificationsCount === 0) {
                                location.reload(); // Reload to show empty state
                                return;
                            }
                            
                            updateNotificationBadge();
                            // Also update header badge if function exists
                            if (typeof updatePatientNotificationBadge === 'function') {
                                updatePatientNotificationBadge();
                            }
                        }, 300);
                    } else {
                        notificationItem.classList.remove('removing');
                        showAlert('Error: ' + data.message, 'Error', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting notification:', error);
                    notificationItem.classList.remove('removing');
                    showAlert('Error deleting notification. Please try again.', 'Error', 'error');
                });
            });
        });
    });
    
    // Update notification badge in sidebar
    function updateNotificationBadge() {
        const badge = document.querySelector('.nav-link[data-section="notifications"] .badge');
        if (badge) {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
            } else {
                badge.remove();
            }
        }
    }
    
    // Profile Image Upload Functionality
    const profileImageInput = document.getElementById('profile-image-input');
    const profileImagePreview = document.getElementById('profile-image-preview');
    const uploadImageBtn = document.getElementById('upload-image-btn');
    const profileMessage = document.getElementById('profile-message');
    
    // Preview image when file is selected
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showProfileMessage('Invalid file type. Only JPG, PNG and GIF are allowed.', 'danger');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showProfileMessage('File size too large. Maximum size is 5MB.', 'danger');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    profileImagePreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Handle image upload
    if (uploadImageBtn) {
        uploadImageBtn.addEventListener('click', function() {
            const fileInput = profileImageInput;
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showProfileMessage('Please select an image file first.', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('profile_image', fileInput.files[0]);
            
            uploadImageBtn.disabled = true;
            uploadImageBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            
            fetch('update_patient_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is JSON
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                uploadImageBtn.disabled = false;
                uploadImageBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload';
                
                if (data.success) {
                    showProfileMessage(data.message, 'success');
                    fileInput.value = '';
                    
                    // Update all profile images in real-time without page reload
                    if (data.image_path) {
                        const timestamp = new Date().getTime();
                        const newImagePath = data.image_path + '?t=' + timestamp;
                        
                        // Update preview image in profile section
                        const profileImagePreview = document.getElementById('profile-image-preview');
                        if (profileImagePreview) {
                            profileImagePreview.src = newImagePath;
                            profileImagePreview.onerror = function() {
                                this.src = 'img/defaultDP.jpg';
                            };
                        }
                        
                        // Update header profile image using specific ID
                        const headerProfileImg = document.getElementById('header-profile-image');
                        if (headerProfileImg) {
                            headerProfileImg.src = newImagePath;
                            headerProfileImg.onerror = function() {
                                this.src = 'img/defaultDP.jpg';
                            };
                        }
                        
                        // Also try to update using class selector as fallback
                        const headerProfileImgByClass = document.querySelector('.user-profile img');
                        if (headerProfileImgByClass && headerProfileImgByClass.id !== 'header-profile-image') {
                            headerProfileImgByClass.src = newImagePath;
                            headerProfileImgByClass.onerror = function() {
                                this.src = 'img/defaultDP.jpg';
                            };
                        }
                        
                        // Update any other profile images on the page
                        const allProfileImages = document.querySelectorAll('img[src*="profile"], .profile-preview-img, [id*="profile-image"]');
                        allProfileImages.forEach(img => {
                            // Skip if already updated
                            if (img.id === 'profile-image-preview' || img.id === 'header-profile-image') {
                                return;
                            }
                            // Update images that contain profile in their src or have profile-related classes/ids
                            if (img.src && (img.src.includes('profile') || img.classList.contains('profile-preview-img') || img.id.includes('profile-image'))) {
                                img.src = newImagePath;
                                img.onerror = function() {
                                    this.src = 'img/defaultDP.jpg';
                                };
                            }
                        });
                    }
                } else {
                    showProfileMessage(data.message || 'Error uploading image. Please try again.', 'danger');
                }
            })
            .catch(error => {
                uploadImageBtn.disabled = false;
                uploadImageBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload';
                console.error('Error uploading image:', error);
                showProfileMessage('Error uploading image. Please check your connection and try again.', 'danger');
            });
        });
    }
    
    // Password Change Functionality
    const changePasswordBtn = document.getElementById('change-password-btn');
    const currentPasswordInput = document.getElementById('current-password');
    const newPasswordInput = document.getElementById('new-password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', function() {
            const currentPassword = currentPasswordInput.value;
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Validation
            if (!currentPassword) {
                showProfileMessage('Please enter your current password.', 'warning');
                currentPasswordInput.focus();
                return;
            }
            
            if (!newPassword) {
                showProfileMessage('Please enter a new password.', 'warning');
                newPasswordInput.focus();
                return;
            }
            
            if (newPassword.length < 8) {
                showProfileMessage('New password must be at least 8 characters long.', 'warning');
                newPasswordInput.focus();
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showProfileMessage('New password and confirm password do not match.', 'warning');
                confirmPasswordInput.focus();
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            
            changePasswordBtn.disabled = true;
            changePasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            
            fetch('update_patient_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                changePasswordBtn.disabled = false;
                changePasswordBtn.innerHTML = '<i class="fas fa-key me-2"></i>Update Password';
                
                if (data.success) {
                    showProfileMessage(data.message, 'success');
                    // Clear password fields
                    currentPasswordInput.value = '';
                    newPasswordInput.value = '';
                    confirmPasswordInput.value = '';
                } else {
                    showProfileMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                changePasswordBtn.disabled = false;
                changePasswordBtn.innerHTML = '<i class="fas fa-key me-2"></i>Update Password';
                console.error('Error changing password:', error);
                showProfileMessage('Error changing password. Please try again.', 'danger');
            });
        });
    }
    
    // Phone number input validation - only allow digits, max 10 digits
    const profilePhoneInput = document.getElementById('profile-phone');
    if (profilePhoneInput) {
        // Only allow numeric input
        profilePhoneInput.addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/[^\d]/g, '');
            // Limit to 10 digits
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });
        
        // Prevent non-numeric characters on keypress
        profilePhoneInput.addEventListener('keypress', function(e) {
            // Allow: backspace, delete, tab, escape, enter, and numbers
            if ([46, 8, 9, 27, 13, 110].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
        
        // Prevent paste of non-numeric content
        profilePhoneInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const numericOnly = paste.replace(/[^\d]/g, '').slice(0, 10);
            this.value = numericOnly;
        });
    }
    
    // Contact Details Update Functionality
    const updateContactBtn = document.getElementById('update-contact-btn');
    const profileEmailInput = document.getElementById('profile-email');
    
    if (updateContactBtn) {
        updateContactBtn.addEventListener('click', function() {
            const email = profileEmailInput.value.trim();
            const phone = profilePhoneInput.value.trim();
            
            // Validation
            if (!email) {
                showProfileMessage('Please enter your email address.', 'warning');
                profileEmailInput.focus();
                return;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showProfileMessage('Please enter a valid email address.', 'warning');
                profileEmailInput.focus();
                return;
            }
            
            if (!phone) {
                showProfileMessage('Please enter your phone number.', 'warning');
                profilePhoneInput.focus();
                return;
            }
            
            // Phone validation - must be exactly 10 digits (country code +63 is fixed)
            const phoneRegex = /^\d{10}$/;
            if (!phoneRegex.test(phone)) {
                showProfileMessage('Phone number must be exactly 10 digits. Country code +63 is fixed.', 'warning');
                profilePhoneInput.focus();
                return;
            }
            
            const patientData = getPatientData();
            const formData = new FormData();
            formData.append('action', 'update_account_info');
            formData.append('first_name', patientData.firstName);
            formData.append('last_name', patientData.lastName);
            formData.append('email', email);
            formData.append('phone', phone); // Send only 10 digits, server will add +63
            
            updateContactBtn.disabled = true;
            updateContactBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            
            fetch('update_patient_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                updateContactBtn.disabled = false;
                updateContactBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Contact Details';
                
                if (data.success) {
                    showProfileMessage(data.message, 'success');
                    // The updates will be reflected immediately on admin/doctor dashboards 
                    // since they query from the same database tables (patients and patient_users)
                } else {
                    showProfileMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                updateContactBtn.disabled = false;
                updateContactBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Contact Details';
                console.error('Error updating contact details:', error);
                showProfileMessage('Error updating contact details. Please try again.', 'danger');
            });
        });
    }
    
    
    // Function to show profile messages
    function showProfileMessage(message, type) {
        if (!profileMessage) return;
        
        profileMessage.textContent = message;
        profileMessage.className = 'alert alert-' + type;
        profileMessage.style.display = 'block';
        
        // Scroll to message
        profileMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                profileMessage.style.display = 'none';
            }, 5000);
        }
    }
    
    // Function to show records modal for a specific date and type
    window.showDateRecordsModal = function(modalId, title, records, recordType) {
        const modal = new bootstrap.Modal(document.getElementById('viewMedicalRecordModal'));
        const modalBody = document.getElementById('viewMedicalRecordModalBody');
        const modalTitle = document.getElementById('viewMedicalRecordModalLabel');
        
        // Update modal title
        if (modalTitle) {
            modalTitle.innerHTML = `<i class="fas fa-${recordType === 'vitals' ? 'heartbeat' : recordType === 'medical_history' ? 'history' : 'file-medical'} me-2"></i>${title}`;
        }
        
        if (!records || records.length === 0) {
            modalBody.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No ${title.toLowerCase()} found for this date.
                </div>
            `;
            modal.show();
            return;
        }
        
        // Build content for all records
        let content = '';
        
        records.forEach((record, index) => {
            // Add record type to each record
            record.record_type = recordType;
            
            // Add separator between multiple records
            if (index > 0) {
                content += '<hr class="my-4">';
            }
            
            // Format date
            const visitDate = record.visit_date ? new Date(record.visit_date + 'T00:00:00').toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : (record.created_at ? new Date(record.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'Not specified');
            
            const recordedTime = record.created_at ? new Date(record.created_at).toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }) : 'Not specified';
            
            if (recordType === 'vitals') {
                // Display vitals
                const vitalsHtml = buildVitalsHtml(record);
                content += `
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    <i class="fas fa-heartbeat me-2 text-success"></i>Vital Signs Record
                                </h5>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>Visit Date: <strong>${visitDate}</strong>
                                    | <i class="fas fa-clock me-1"></i>Recorded: <strong>${recordedTime}</strong>
                                </small>
                            </div>
                            <span class="badge bg-success">Vitals</span>
                        </div>
                    </div>
                    ${vitalsHtml}
                    ${record.notes ? `
                        <div class="mb-3">
                            <strong><i class="fas fa-sticky-note me-1"></i>Notes:</strong>
                            <div class="text-muted mt-2" style="white-space: pre-wrap;">${escapeHtml(record.notes)}</div>
                        </div>
                    ` : ''}
                `;
            } else if (recordType === 'medical_history') {
                // Display medical history
                const historyTypes = {
                    'allergies': 'Allergies',
                    'medications': 'Medications',
                    'past_history': 'Past History',
                    'immunization': 'Immunization',
                    'procedures': 'Procedures',
                    'substance': 'Substance Use',
                    'family': 'Family History',
                    'menstrual': 'Menstrual History',
                    'sexual': 'Sexual History',
                    'obstetric': 'Obstetric History',
                    'growth': 'Growth History'
                };
                const historyType = historyTypes[record.history_type] || 'Medical History';
                
                content += `
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    <i class="fas fa-history me-2 text-info"></i>${historyType}
                                </h5>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>Date: <strong>${visitDate}</strong>
                                    | <i class="fas fa-clock me-1"></i>Recorded: <strong>${recordedTime}</strong>
                                </small>
                            </div>
                            <span class="badge bg-info">History</span>
                        </div>
                    </div>
                    ${record.history_details ? `
                        <div class="mb-3">
                            <strong><i class="fas fa-file-alt me-1"></i>Details:</strong>
                            <div class="text-muted mt-2" style="white-space: pre-wrap;">${escapeHtml(record.history_details)}</div>
                        </div>
                    ` : ''}
                `;
            } else {
                // Display medical record
                content += `
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    <i class="fas fa-stethoscope me-2 text-primary"></i>Medical Visit Record
                                </h5>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>Visit Date: <strong>${visitDate}</strong>
                                    | <i class="fas fa-clock me-1"></i>Recorded: <strong>${recordedTime}</strong>
                                </small>
                            </div>
                            <span class="badge bg-primary">Visit</span>
                        </div>
                    </div>
                    ${record.diagnosis ? `
                        <div class="mb-3">
                            <strong><i class="fas fa-notes-medical me-1"></i>Diagnosis:</strong>
                            <div class="text-muted">${escapeHtml(record.diagnosis).replace(/\n/g, '<br>')}</div>
                        </div>
                    ` : ''}
                    ${record.treatment ? `
                        <div class="mb-3">
                            <strong><i class="fas fa-pills me-1"></i>Treatment:</strong>
                            <div class="text-muted">${escapeHtml(record.treatment).replace(/\n/g, '<br>')}</div>
                        </div>
                    ` : ''}
                    ${record.prescription ? `
                        <div class="mb-3">
                            <strong><i class="fas fa-prescription me-1"></i>Prescription:</strong>
                            <div class="text-muted">${escapeHtml(record.prescription).replace(/\n/g, '<br>')}</div>
                        </div>
                    ` : ''}
                    ${record.lab_results ? `
                        <div class="mb-3">
                            <strong><i class="fas fa-flask me-1"></i>Lab Results:</strong>
                            <div class="text-muted">${escapeHtml(record.lab_results).replace(/\n/g, '<br>')}</div>
                        </div>
                    ` : ''}
                    ${(function() {
                        // Display vitals from medical record's vitals field
                        if (!record.vitals || record.vitals.trim() === '') return '';
                        let vitalsDisplay = '';
                        try {
                            // Try to parse as JSON first
                            const vitalsData = JSON.parse(record.vitals);
                            if (typeof vitalsData === 'object' && vitalsData !== null) {
                                // It's JSON - display in a nice grid format
                                const vitalItems = [];
                                for (const [key, value] of Object.entries(vitalsData)) {
                                    if (value && value !== '') {
                                        let displayValue = String(value);
                                        // Add BMI classification if key is bmi
                                        if (key.toLowerCase() === 'bmi') {
                                            const bmiValue = parseFloat(value);
                                            if (!isNaN(bmiValue)) {
                                                const bmiClassification = classifyBMI(bmiValue);
                                                displayValue = value + ' <span class="badge bg-' + bmiClassification.class + ' ms-1">' + bmiClassification.status + '</span>';
                                            }
                                        }
                                        vitalItems.push({label: key, value: displayValue});
                                    }
                                }
                                if (vitalItems.length > 0) {
                                    vitalsDisplay = `
                                        <div class="mb-3">
                                            <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                                            <div class="vital-signs-grid mt-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                                                ${vitalItems.map(item => `
                                                    <div class="vital-item" style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; text-align: center; height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div class="vital-label" style="font-size: 0.8em; color: #6c757d; font-weight: 500;">
                                                            ${escapeHtml(item.label)}
                                                        </div>
                                                        <div class="vital-value" style="font-size: 1.1em; font-weight: 600; color: #495057;">
                                                            ${item.label.toLowerCase() === 'bmi' && item.value.includes('<span') ? item.value : escapeHtml(String(item.value))}
                                                        </div>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                        } catch (e) {
                            // Not JSON, treat as plain text
                            vitalsDisplay = `
                                <div class="mb-3">
                                    <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                                    <div class="text-muted">${escapeHtml(record.vitals).replace(/\n/g, '<br>')}</div>
                                </div>
                            `;
                        }
                        return vitalsDisplay;
                    })()}
                    ${record.notes ? `
                        <div class="mb-3">
                            <strong><i class="fas fa-sticky-note me-1"></i>Notes:</strong>
                            <div class="text-muted">${escapeHtml(record.notes).replace(/\n/g, '<br>')}</div>
                        </div>
                    ` : ''}
                `;
            }
        });
        
        modalBody.innerHTML = content;
        modal.show();
    };
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
    
    // Helper function to build vitals HTML
    function buildVitalsHtml(record) {
        const vitals = [];
        if (record.blood_pressure) vitals.push({ label: 'Blood Pressure', value: record.blood_pressure });
        if (record.temperature) vitals.push({ label: 'Temperature', value: record.temperature + '°F' });
        if (record.heart_rate) vitals.push({ label: 'Heart Rate', value: record.heart_rate + ' bpm' });
        if (record.respiratory_rate) vitals.push({ label: 'Respiratory Rate', value: record.respiratory_rate + ' /min' });
        if (record.oxygen_saturation) vitals.push({ label: 'Oxygen Saturation', value: record.oxygen_saturation + '%' });
        if (record.weight) vitals.push({ label: 'Weight', value: record.weight + ' lbs' });
        if (record.height) vitals.push({ label: 'Height', value: record.height + ' in' });
        if (record.bmi) {
            const bmiClassification = classifyBMI(record.bmi);
            vitals.push({ label: 'BMI', value: record.bmi + ' <span class="badge bg-' + bmiClassification.class + ' ms-1">' + bmiClassification.status + '</span>' });
        }
        
        if (vitals.length === 0) {
            return '<p class="text-muted">No vital signs recorded.</p>';
        }
        
        return `
            <div class="vital-signs-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px;">
                ${vitals.map(item => `
                    <div class="vital-item" style="background-color: #f8f9fa; padding: 12px; border-radius: 6px; text-align: center; height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <div class="vital-label" style="font-size: 0.85em; color: #6c757d; font-weight: 500; margin-bottom: 5px;">
                            ${escapeHtml(item.label)}
                        </div>
                        <div class="vital-value" style="font-size: 1.2em; font-weight: 600; color: #495057;">
                            ${item.label === 'BMI' && item.value.includes('<span') ? item.value : escapeHtml(String(item.value))}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // Toggle month group collapsible
    window.toggleMonthGroup = function(monthKey) {
        const content = document.getElementById('content-' + monthKey);
        const chevron = document.getElementById('chevron-' + monthKey);
        
        if (content.classList.contains('show')) {
            content.classList.remove('show');
            chevron.classList.remove('expanded');
        } else {
            content.classList.add('show');
            chevron.classList.add('expanded');
        }
    };
    
    // Function to show all medical records for a specific date
    window.showDateMedicalRecordsModal = function(dateKey, dateRecordsData) {
        const modal = new bootstrap.Modal(document.getElementById('viewMedicalRecordModal'));
        const modalBody = document.getElementById('viewMedicalRecordModalBody');
        const modalTitle = document.getElementById('viewMedicalRecordModalLabel');
        
        // Parse dateRecordsData if it's a string
        if (typeof dateRecordsData === 'string') {
            try {
                dateRecordsData = JSON.parse(dateRecordsData);
            } catch (e) {
                console.error('Error parsing date records data:', e);
                if (modalBody) {
                    modalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading records data.</div>';
                }
                modal.show();
                return;
            }
        }
        
        // Format date for display
        const dateDisplay = new Date(dateKey + 'T00:00:00').toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Update modal title
        if (modalTitle) {
            modalTitle.innerHTML = `<i class="fas fa-calendar-alt me-2"></i>Medical Records - ${escapeHtml(dateDisplay)}`;
        }
        
        if (!dateRecordsData) {
            if (modalBody) {
                modalBody.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found for this date.</div>';
            }
            modal.show();
            return;
        }
        
        const medicalRecords = dateRecordsData.medical_records || [];
        const vitals = dateRecordsData.vitals || [];
        const medicalHistory = dateRecordsData.medical_history || [];
        
        if (medicalRecords.length === 0 && vitals.length === 0 && medicalHistory.length === 0) {
            if (modalBody) {
                modalBody.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found for this date.</div>';
            }
            modal.show();
            return;
        }
        
        let content = `<div class="mb-4">
            <h5 class="mb-3"><i class="fas fa-calendar me-2"></i>${dateDisplay}</h5>`;
        
        // Display Vital Signs (compact list format)
        if (vitals.length > 0) {
            content += `<div class="mb-3">
                <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                <div class="text-muted mt-1">`;
            
            // Collect all unique vitals from all vital records
            const allVitalItems = [];
            vitals.forEach(vital => {
                if (vital.blood_pressure && !allVitalItems.find(v => v.label === 'BP')) {
                    allVitalItems.push({ label: 'BP', value: vital.blood_pressure });
                }
                if (vital.temperature && !allVitalItems.find(v => v.label === 'Temperature')) {
                    allVitalItems.push({ label: 'Temperature', value: vital.temperature + ' °F' });
                }
                if (vital.heart_rate && !allVitalItems.find(v => v.label === 'Heart Rate')) {
                    allVitalItems.push({ label: 'Heart Rate', value: vital.heart_rate + ' bpm' });
                }
                if (vital.respiratory_rate && !allVitalItems.find(v => v.label === 'Respiratory Rate')) {
                    allVitalItems.push({ label: 'Respiratory Rate', value: vital.respiratory_rate + ' /min' });
                }
                if (vital.oxygen_saturation && !allVitalItems.find(v => v.label === 'O2 Saturation')) {
                    allVitalItems.push({ label: 'O2 Saturation', value: vital.oxygen_saturation + ' %' });
                }
                if (vital.weight && !allVitalItems.find(v => v.label === 'Weight')) {
                    allVitalItems.push({ label: 'Weight', value: vital.weight + ' lbs' });
                }
                if (vital.height && !allVitalItems.find(v => v.label === 'Height')) {
                    allVitalItems.push({ label: 'Height', value: vital.height + ' in' });
                }
                if (vital.bmi && !allVitalItems.find(v => v.label === 'BMI')) {
                    allVitalItems.push({ label: 'BMI', value: vital.bmi });
                }
            });
            
            if (allVitalItems.length > 0) {
                content += allVitalItems.map(item => 
                    `<strong>${escapeHtml(item.label)}:</strong> ${escapeHtml(String(item.value))}`
                ).join(' • ');
            } else {
                content += 'No vital signs recorded.';
            }
            
            content += `</div></div>`;
        }
        
        // Display Medical History (compact list format)
        if (medicalHistory.length > 0) {
            content += `<div class="mb-3">
                <strong><i class="fas fa-history me-1"></i>Medical History:</strong>
                <div class="text-muted mt-1">`;
            
            const historyTypes = {
                'allergies': 'Allergies',
                'medications': 'Medications',
                'past_history': 'Past History',
                'immunization': 'Immunization',
                'procedures': 'Procedures',
                'substance': 'Substance Use',
                'family': 'Family History',
                'menstrual': 'Menstrual History',
                'sexual': 'Sexual History',
                'obstetric': 'Obstetric History',
                'growth': 'Growth History'
            };
            
            const historyItems = [];
            medicalHistory.forEach(history => {
                const historyType = historyTypes[history.history_type] || 'Medical History';
                const details = history.history_details ? escapeHtml(history.history_details) : 'N/A';
                historyItems.push(`<strong>${escapeHtml(historyType)}:</strong> ${details}`);
            });
            
            if (historyItems.length > 0) {
                content += historyItems.join('<br>');
            } else {
                content += 'No medical history recorded.';
            }
            
            content += `</div></div>`;
        }
        
        // Display Medical Records (compact format)
        if (medicalRecords.length > 0) {
            medicalRecords.forEach((record, index) => {
                if (index > 0) content += '<hr class="my-3">';
                
                content += `<div class="mb-3">`;
                
                if (record.diagnosis) {
                    content += `<div class="mb-2"><strong>Diagnosis:</strong> <span class="text-muted">${escapeHtml(record.diagnosis).replace(/\n/g, ', ')}</span></div>`;
                }
                if (record.treatment) {
                    content += `<div class="mb-2"><strong>Treatment:</strong> <span class="text-muted">${escapeHtml(record.treatment).replace(/\n/g, ', ')}</span></div>`;
                }
                if (record.prescription) {
                    content += `<div class="mb-2"><strong>Prescription:</strong> <span class="text-muted">${escapeHtml(record.prescription).replace(/\n/g, ', ')}</span></div>`;
                }
                if (record.lab_results) {
                    content += `<div class="mb-2"><strong>Lab Results:</strong> <span class="text-muted">${escapeHtml(record.lab_results).replace(/\n/g, ', ')}</span></div>`;
                }
                if (record.notes) {
                    content += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(record.notes).replace(/\n/g, ', ')}</span></div>`;
                }
                
                content += `</div>`;
            });
        }
        
        content += `</div>`;
        
        if (modalBody) {
            modalBody.innerHTML = content;
        }
        modal.show();
    };
    
    // Function to show medical record modal (for patient dashboard)
    window.showViewMedicalRecordModal = function(recordId, recordType) {
        // Get the record card with this ID and type
        const recordCard = document.querySelector(`.record-card[data-record-id="${recordId}"][data-record-type="${recordType}"]`);
        
        if (recordCard) {
            // Get record data from data attribute
            const recordJson = recordCard.getAttribute('data-record-json');
            if (recordJson) {
                try {
                    const recordData = JSON.parse(recordJson);
                    recordData.record_type = recordType;
                    
                    // Use the populateViewModal function from view_medical_record_modal.php
                    if (typeof populateViewModal === 'function') {
                        populateViewModal(recordData);
                        
                        // Show the modal
                        const modalElement = document.getElementById('viewMedicalRecordModal');
                        if (modalElement) {
                            let modal = bootstrap.Modal.getInstance(modalElement);
                            if (!modal) {
                                modal = new bootstrap.Modal(modalElement);
                            }
                            modal.show();
                        }
                    } else {
                        console.error('populateViewModal function not found. Make sure view_medical_record_modal.php is included.');
                    }
                } catch (e) {
                    console.error('Error parsing record data:', e);
                    alert('Error loading record data. Please try again.');
                }
            }
        }
    };
    
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
            content += '<div class="col-12 col-md-6">';
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
            content += '<div class="col-12 col-md-6 text-md-end mt-2 mt-md-0">';
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
    };
    
    // Expand first month group by default
    document.addEventListener('DOMContentLoaded', function() {
        const firstMonthGroup = document.querySelector('.month-group-content');
        const firstMonthChevron = document.querySelector('.month-group-chevron');
        if (firstMonthGroup && firstMonthChevron) {
            firstMonthGroup.classList.add('show');
            firstMonthChevron.classList.add('expanded');
        }
    });
});
</script>
HTML;

// Live session guard: show a modal and require re-login if account was deleted while active
echo <<<HTML
<div class="modal fade" id="sessionEndedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Session Ended</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Your account is no longer available. Please log in again, register a new account, or contact Mhavis.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="sessionEndedLoginBtn">Go to Login</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function checkPatientSession() {
        fetch('patient_session_check.php', { credentials: 'same-origin' })
            .then(function (res) {
                if (res && res.status === 401) {
                    var modalEl = document.getElementById('sessionEndedModal');
                    if (modalEl && window.bootstrap) {
                        var modal = new bootstrap.Modal(modalEl);
                        modal.show();
                        var btn = document.getElementById('sessionEndedLoginBtn');
                        if (btn) {
                            btn.addEventListener('click', function () {
                                window.location.href = 'patient_login.php?deleted=1';
                            }, { once: true });
                        }
                    } else {
                        // Fallback to immediate redirect
                        window.location.href = 'patient_login.php?deleted=1';
                    }
                }
            })
            .catch(function () {
                // Ignore network errors for this lightweight check
            });
    }
    // Check every 30 seconds
    setInterval(checkPatientSession, 30000);
});
</script>

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
HTML;

// Include the view medical record modal
include 'view_medical_record_modal.php';

include 'includes/footer.php';
?>