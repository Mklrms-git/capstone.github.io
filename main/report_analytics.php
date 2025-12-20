<?php
define('MHAVIS_EXEC', true);
$page_title = "Report Analytics";
$active_page = "analytics";
require_once __DIR__ . '/config/init.php';
requireLogin();

// Helper function to safely escape HTML
function safeHtmlspecialchars($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Helper function to safely format currency
function safeCurrency($value) {
    return number_format((float)($value ?? 0), 2);
}

// Helper function to calculate analytics for a date range
function calculatePeriodAnalytics($conn, $startDate, $endDate, $hasTransactionItems, $hasReferenceNumber, $hasNotes, $hasAge, $hasSex, $hasPhone, $hasDiagnosis, $hasLabsDone, $hasMedications, $hasProcedure, $hasProcedureNotes, $hasOtherFees) {
    // Build query
    if ($hasTransactionItems) {
        $query = "SELECT t.*, 
                  DATE(t.transaction_date) as transaction_date,
                  TIME(t.transaction_date) as transaction_time,
                  " . ($hasReferenceNumber ? "t.reference_number as id_no," : "CONCAT('TXN-', t.id) as id_no,") . "
                  CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                  " . ($hasAge ? "p.age," : "'' as age,") . "
                  " . ($hasSex ? "p.sex," : "'' as sex,") . "
                  " . ($hasPhone ? "p.phone as patient_phone," : "'' as patient_phone,") . "
                  " . ($hasDiagnosis ? "p.diagnosis," : "'' as diagnosis,") . "
                  " . ($hasLabsDone ? "p.labs_done," : "'' as labs_done,") . "
                  " . ($hasMedications ? "p.medications_supply," : "'' as medications_supply,") . "
                  " . ($hasProcedure ? "p.procedure_done," : "'' as procedure_done,") . "
                  " . ($hasProcedureNotes ? "p.procedure_notes," : "'' as procedure_notes,") . "
                  " . ($hasOtherFees ? "p.other_fees_description," : "'' as other_fees_description,") . "
                  t.amount as gross_amount,
                  t.discount_amount,
                  COALESCE(t.net_amount, t.amount - COALESCE(t.discount_amount, 0)) as net_amount,
                  t.payment_method as mop,
                  t.payment_status,
                  CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                  COALESCE(NULLIF(fc.name, ''), NULLIF(fc2.name, ''), '') as category_name,
                  COALESCE(fc.id, fc2.id, 0) as category_id,
                  COALESCE(NULLIF(ti.fee_name, ''), f.name, f2.name, '') as fee_name,
                  ti.id as item_id" .
                  ($hasNotes ? ", t.notes" : ", '' as notes") . "
                  FROM transactions t
                  JOIN patients p ON t.patient_id = p.id
                  LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
                  LEFT JOIN fees f ON ti.fee_id = f.id
                  LEFT JOIN fee_categories fc ON f.category_id = fc.id
                  LEFT JOIN fees f2 ON t.fee_id = f2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
                  LEFT JOIN fee_categories fc2 ON f2.category_id = fc2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
                  JOIN users u ON t.created_by = u.id
                  WHERE DATE(t.transaction_date) BETWEEN ? AND ?
                  AND (ti.id IS NOT NULL OR (ti.id IS NULL AND t.fee_id IS NOT NULL))";
    } else {
        $query = "SELECT t.*, 
                  DATE(t.transaction_date) as transaction_date,
                  TIME(t.transaction_date) as transaction_time,
                  " . ($hasReferenceNumber ? "t.reference_number as id_no," : "CONCAT('TXN-', t.id) as id_no,") . "
                  CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                  " . ($hasAge ? "p.age," : "'' as age,") . "
                  " . ($hasSex ? "p.sex," : "'' as sex,") . "
                  " . ($hasPhone ? "p.phone as patient_phone," : "'' as patient_phone,") . "
                  " . ($hasDiagnosis ? "p.diagnosis," : "'' as diagnosis,") . "
                  " . ($hasLabsDone ? "p.labs_done," : "'' as labs_done,") . "
                  " . ($hasMedications ? "p.medications_supply," : "'' as medications_supply,") . "
                  " . ($hasProcedure ? "p.procedure_done," : "'' as procedure_done,") . "
                  " . ($hasProcedureNotes ? "p.procedure_notes," : "'' as procedure_notes,") . "
                  " . ($hasOtherFees ? "p.other_fees_description," : "'' as other_fees_description,") . "
                  t.amount as gross_amount,
                  t.discount_amount,
                  COALESCE(t.net_amount, t.amount - COALESCE(t.discount_amount, 0)) as net_amount,
                  t.payment_method as mop,
                  t.payment_status,
                  CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                  fc.name as category_name,
                  fc.id as category_id,
                  f.name as fee_name" .
                  ($hasNotes ? ", t.notes" : ", '' as notes") . "
                  FROM transactions t
                  JOIN patients p ON t.patient_id = p.id
                  JOIN fees f ON t.fee_id = f.id
                  JOIN fee_categories fc ON f.category_id = fc.id
                  JOIN users u ON t.created_by = u.id
                  WHERE DATE(t.transaction_date) BETWEEN ? AND ?";
    }

    $params = [$startDate, $endDate];
    $paramTypes = 'ss';

    if ($hasTransactionItems) {
        $query .= " ORDER BY t.transaction_date DESC, t.id DESC, ti.id ASC";
    } else {
        $query .= " ORDER BY t.transaction_date DESC, t.id DESC";
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return ['totalPatients' => 0, 'totalAppointments' => 0, 'netRevenue' => 0];
    }

    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        return ['totalPatients' => 0, 'totalAppointments' => 0, 'netRevenue' => 0];
    }

    $allTransactions = $result->fetch_all(MYSQLI_ASSOC);

    // Group transactions by transaction ID
    $groupedTransactions = [];
    foreach ($allTransactions as $row) {
        $transactionId = $row['id'];
        
        if (!isset($groupedTransactions[$transactionId])) {
            $groupedTransactions[$transactionId] = [
                'id' => $row['id'],
                'amount' => $row['gross_amount'] ?? $row['amount'] ?? 0,
                'discount_amount' => $row['discount_amount'] ?? 0,
                'payment_status' => $row['payment_status']
            ];
        }
    }
    $transactions = array_values($groupedTransactions);

    // Calculate net revenue
    $netRevenue = 0;
    foreach ($transactions as $transaction) {
        if ($transaction['payment_status'] === 'Completed') {
            $amount = $transaction['amount'] ?? 0;
            $discount = $transaction['discount_amount'] ?? 0;
            $net = $amount - $discount;
            $netRevenue += $net;
        }
    }

    // Calculate total number of unique patients
    $uniquePatientsQuery = "SELECT COUNT(DISTINCT t.patient_id) as total_patients
                            FROM transactions t
                            WHERE DATE(t.transaction_date) BETWEEN ? AND ?";
    $uniquePatientsStmt = $conn->prepare($uniquePatientsQuery);
    $uniquePatientsStmt->bind_param('ss', $startDate, $endDate);
    $uniquePatientsStmt->execute();
    $uniquePatientsResult = $uniquePatientsStmt->get_result()->fetch_assoc();
    $totalPatients = $uniquePatientsResult['total_patients'] ?? 0;

    // Calculate total number of appointments
    $appointmentsQuery = "SELECT COUNT(*) as total_appointments
                          FROM appointments a
                          WHERE DATE(a.appointment_date) BETWEEN ? AND ?";
    $appointmentsStmt = $conn->prepare($appointmentsQuery);
    $appointmentsStmt->bind_param('ss', $startDate, $endDate);
    $appointmentsStmt->execute();
    $appointmentsResult = $appointmentsStmt->get_result()->fetch_assoc();
    $totalAppointments = $appointmentsResult['total_appointments'] ?? 0;

    return [
        'totalPatients' => $totalPatients,
        'totalAppointments' => $totalAppointments,
        'netRevenue' => $netRevenue
    ];
}

$conn = getDBConnection();

// Get filter parameters
$period = isset($_GET['period']) ? sanitize($_GET['period']) : 'day';

// Calculate date range based on period
$today = date('Y-m-d');
$showSeparatePeriods = false;
$prevPeriodData = null;
$currentPeriodData = null;
$prevPeriodLabel = '';
$currentPeriodLabel = '';

switch ($period) {
    case 'day':
        $startDate = $today;
        $endDate = $today;
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = $today;
        break;
    case 'month':
        // Calculate previous month range
        $prevMonthStart = date('Y-m-01', strtotime('first day of last month'));
        $prevMonthEnd = date('Y-m-t', strtotime('first day of last month'));
        // Calculate current month range
        $currentMonthStart = date('Y-m-01');
        $currentMonthEnd = $today;
        
        $startDate = $prevMonthStart; // For combined totals
        $endDate = $today;
        $showSeparatePeriods = true;
        $prevPeriodLabel = date('F Y', strtotime('first day of last month'));
        $currentPeriodLabel = date('F Y');
        break;
    case 'year':
        // Calculate previous year range
        $prevYear = date('Y') - 1;
        $prevYearStart = $prevYear . '-01-01';
        $prevYearEnd = $prevYear . '-12-31';
        // Calculate current year range
        $currentYearStart = date('Y-01-01');
        $currentYearEnd = $today;
        
        $startDate = $prevYearStart; // For combined totals
        $endDate = $today;
        $showSeparatePeriods = true;
        $prevPeriodLabel = $prevYear;
        $currentPeriodLabel = date('Y');
        break;
    default:
        $startDate = $today;
        $endDate = $today;
}

$isToday = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));

// Check if optional columns exist
$checkColumns = $conn->query("SHOW COLUMNS FROM transactions LIKE 'reference_number'");
$hasReferenceNumber = $checkColumns->num_rows > 0;

$checkNotes = $conn->query("SHOW COLUMNS FROM transactions LIKE 'notes'");
$hasNotes = $checkNotes->num_rows > 0;

$checkPhone = $conn->query("SHOW COLUMNS FROM patients LIKE 'phone'");
$hasPhone = $checkPhone->num_rows > 0;

$checkAge = $conn->query("SHOW COLUMNS FROM patients LIKE 'age'");
$hasAge = $checkAge->num_rows > 0;

$checkSex = $conn->query("SHOW COLUMNS FROM patients LIKE 'sex'");
$hasSex = $checkSex->num_rows > 0;

$checkDiagnosis = $conn->query("SHOW COLUMNS FROM patients LIKE 'diagnosis'");
$hasDiagnosis = $checkDiagnosis->num_rows > 0;

$checkLabsDone = $conn->query("SHOW COLUMNS FROM patients LIKE 'labs_done'");
$hasLabsDone = $checkLabsDone->num_rows > 0;

$checkMedications = $conn->query("SHOW COLUMNS FROM patients LIKE 'medications_supply'");
$hasMedications = $checkMedications->num_rows > 0;

$checkProcedure = $conn->query("SHOW COLUMNS FROM patients LIKE 'procedure_done'");
$hasProcedure = $checkProcedure->num_rows > 0;

$checkProcedureNotes = $conn->query("SHOW COLUMNS FROM patients LIKE 'procedure_notes'");
$hasProcedureNotes = $checkProcedureNotes->num_rows > 0;

$checkOtherFees = $conn->query("SHOW COLUMNS FROM patients LIKE 'other_fees_description'");
$hasOtherFees = $checkOtherFees->num_rows > 0;

// Check if transaction_items table exists
$checkTransactionItems = $conn->query("SHOW TABLES LIKE 'transaction_items'");
$hasTransactionItems = $checkTransactionItems->num_rows > 0;

// Calculate separate period analytics if needed
if ($showSeparatePeriods) {
    if ($period === 'month') {
        $prevMonthStart = date('Y-m-01', strtotime('first day of last month'));
        $prevMonthEnd = date('Y-m-t', strtotime('first day of last month'));
        $currentMonthStart = date('Y-m-01');
        $currentMonthEnd = $today;
        
        $prevPeriodData = calculatePeriodAnalytics($conn, $prevMonthStart, $prevMonthEnd, $hasTransactionItems, $hasReferenceNumber, $hasNotes, $hasAge, $hasSex, $hasPhone, $hasDiagnosis, $hasLabsDone, $hasMedications, $hasProcedure, $hasProcedureNotes, $hasOtherFees);
        $currentPeriodData = calculatePeriodAnalytics($conn, $currentMonthStart, $currentMonthEnd, $hasTransactionItems, $hasReferenceNumber, $hasNotes, $hasAge, $hasSex, $hasPhone, $hasDiagnosis, $hasLabsDone, $hasMedications, $hasProcedure, $hasProcedureNotes, $hasOtherFees);
    } elseif ($period === 'year') {
        $prevYear = date('Y') - 1;
        $prevYearStart = $prevYear . '-01-01';
        $prevYearEnd = $prevYear . '-12-31';
        $currentYearStart = date('Y-01-01');
        $currentYearEnd = $today;
        
        $prevPeriodData = calculatePeriodAnalytics($conn, $prevYearStart, $prevYearEnd, $hasTransactionItems, $hasReferenceNumber, $hasNotes, $hasAge, $hasSex, $hasPhone, $hasDiagnosis, $hasLabsDone, $hasMedications, $hasProcedure, $hasProcedureNotes, $hasOtherFees);
        $currentPeriodData = calculatePeriodAnalytics($conn, $currentYearStart, $currentYearEnd, $hasTransactionItems, $hasReferenceNumber, $hasNotes, $hasAge, $hasSex, $hasPhone, $hasDiagnosis, $hasLabsDone, $hasMedications, $hasProcedure, $hasProcedureNotes, $hasOtherFees);
    }
}

// Use direct query instead of view to ensure real-time data
// Use transaction_items if available to show all services
if ($hasTransactionItems) {
    $query = "SELECT t.*, 
              DATE(t.transaction_date) as transaction_date,
              TIME(t.transaction_date) as transaction_time,
              " . ($hasReferenceNumber ? "t.reference_number as id_no," : "CONCAT('TXN-', t.id) as id_no,") . "
              CONCAT(p.first_name, ' ', p.last_name) as patient_name,
              " . ($hasAge ? "p.age," : "'' as age,") . "
              " . ($hasSex ? "p.sex," : "'' as sex,") . "
              " . ($hasPhone ? "p.phone as patient_phone," : "'' as patient_phone,") . "
              " . ($hasDiagnosis ? "p.diagnosis," : "'' as diagnosis,") . "
              " . ($hasLabsDone ? "p.labs_done," : "'' as labs_done,") . "
              " . ($hasMedications ? "p.medications_supply," : "'' as medications_supply,") . "
              " . ($hasProcedure ? "p.procedure_done," : "'' as procedure_done,") . "
              " . ($hasProcedureNotes ? "p.procedure_notes," : "'' as procedure_notes,") . "
              " . ($hasOtherFees ? "p.other_fees_description," : "'' as other_fees_description,") . "
              t.amount as gross_amount,
              t.discount_amount,
              COALESCE(t.net_amount, t.amount - COALESCE(t.discount_amount, 0)) as net_amount,
              t.payment_method as mop,
              t.payment_status,
              CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
              COALESCE(NULLIF(fc.name, ''), NULLIF(fc2.name, ''), '') as category_name,
              COALESCE(fc.id, fc2.id, 0) as category_id,
              COALESCE(NULLIF(ti.fee_name, ''), f.name, f2.name, '') as fee_name,
              ti.id as item_id" .
              ($hasNotes ? ", t.notes" : ", '' as notes") . "
              FROM transactions t
              JOIN patients p ON t.patient_id = p.id
              LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
              LEFT JOIN fees f ON ti.fee_id = f.id
              LEFT JOIN fee_categories fc ON f.category_id = fc.id
              LEFT JOIN fees f2 ON t.fee_id = f2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
              LEFT JOIN fee_categories fc2 ON f2.category_id = fc2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
              JOIN users u ON t.created_by = u.id
              WHERE DATE(t.transaction_date) BETWEEN ? AND ?
              AND (ti.id IS NOT NULL OR (ti.id IS NULL AND t.fee_id IS NOT NULL))";
} else {
    $query = "SELECT t.*, 
              DATE(t.transaction_date) as transaction_date,
              TIME(t.transaction_date) as transaction_time,
              " . ($hasReferenceNumber ? "t.reference_number as id_no," : "CONCAT('TXN-', t.id) as id_no,") . "
              CONCAT(p.first_name, ' ', p.last_name) as patient_name,
              " . ($hasAge ? "p.age," : "'' as age,") . "
              " . ($hasSex ? "p.sex," : "'' as sex,") . "
              " . ($hasPhone ? "p.phone as patient_phone," : "'' as patient_phone,") . "
              " . ($hasDiagnosis ? "p.diagnosis," : "'' as diagnosis,") . "
              " . ($hasLabsDone ? "p.labs_done," : "'' as labs_done,") . "
              " . ($hasMedications ? "p.medications_supply," : "'' as medications_supply,") . "
              " . ($hasProcedure ? "p.procedure_done," : "'' as procedure_done,") . "
              " . ($hasProcedureNotes ? "p.procedure_notes," : "'' as procedure_notes,") . "
              " . ($hasOtherFees ? "p.other_fees_description," : "'' as other_fees_description,") . "
              t.amount as gross_amount,
              t.discount_amount,
              COALESCE(t.net_amount, t.amount - COALESCE(t.discount_amount, 0)) as net_amount,
              t.payment_method as mop,
              t.payment_status,
              CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
              fc.name as category_name,
              fc.id as category_id,
              f.name as fee_name" .
              ($hasNotes ? ", t.notes" : ", '' as notes") . "
              FROM transactions t
              JOIN patients p ON t.patient_id = p.id
              JOIN fees f ON t.fee_id = f.id
              JOIN fee_categories fc ON f.category_id = fc.id
              JOIN users u ON t.created_by = u.id
              WHERE DATE(t.transaction_date) BETWEEN ? AND ?";
}

$params = [$startDate, $endDate];
$paramTypes = 'ss';

if ($hasTransactionItems) {
    $query .= " ORDER BY t.transaction_date DESC, t.id DESC, ti.id ASC";
} else {
    $query .= " ORDER BY t.transaction_date DESC, t.id DESC";
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query execution failed: " . $stmt->error);
}

$allTransactions = $result->fetch_all(MYSQLI_ASSOC);

// Group transactions by transaction ID to collect all services for each transaction
$groupedTransactions = [];
foreach ($allTransactions as $row) {
    $transactionId = $row['id'];
    
    if (!isset($groupedTransactions[$transactionId])) {
        // Initialize transaction with first row's data
        $groupedTransactions[$transactionId] = [
            'id' => $row['id'],
            'transaction_date' => $row['transaction_date'],
            'transaction_time' => $row['transaction_time'],
            'id_no' => $row['id_no'],
            'patient_name' => $row['patient_name'],
            'age' => $row['age'] ?? '',
            'sex' => $row['sex'] ?? '',
            'patient_phone' => $row['patient_phone'] ?? '',
            'diagnosis' => $row['diagnosis'] ?? '',
            'labs_done' => $row['labs_done'] ?? '',
            'medications_supply' => $row['medications_supply'] ?? '',
            'procedure_done' => $row['procedure_done'] ?? '',
            'procedure_notes' => $row['procedure_notes'] ?? '',
            'other_fees_description' => $row['other_fees_description'] ?? '',
            'amount' => $row['gross_amount'] ?? $row['amount'] ?? 0, // Match daily_sales.php: use amount field
            'gross_amount' => $row['gross_amount'],
            'discount_amount' => $row['discount_amount'] ?? 0,
            'net_amount' => $row['net_amount'],
            'mop' => $row['mop'],
            'payment_status' => $row['payment_status'],
            'doctor_name' => $row['doctor_name'],
            'notes' => $row['notes'] ?? '',
            'services' => []
        ];
    }
    
    // Add service to this transaction's services array
    $feeName = trim($row['fee_name'] ?? '');
    if (!empty($feeName)) {
        $serviceInfo = [
            'name' => $feeName,
            'category' => trim($row['category_name'] ?? '')
        ];
        
        // Only add if not already added (avoid duplicates)
        $serviceKey = !empty($row['item_id']) ? $row['item_id'] : ($feeName . '|' . ($row['category_name'] ?? ''));
        if (!isset($groupedTransactions[$transactionId]['services'][$serviceKey])) {
            $groupedTransactions[$transactionId]['services'][$serviceKey] = $serviceInfo;
        }
    }
}

// Ensure each transaction has at least one service if it exists
// If a transaction has no services collected, try to get it from the transaction's fee_id
if ($hasTransactionItems) {
    foreach ($groupedTransactions as $transactionId => $transaction) {
        if (empty($transaction['services'])) {
            // Try to find a row with fee information for this transaction
            foreach ($allTransactions as $row) {
                if ($row['id'] == $transactionId && !empty(trim($row['fee_name'] ?? ''))) {
                    $feeName = trim($row['fee_name']);
                    $transaction['services']['default'] = [
                        'name' => $feeName,
                        'category' => trim($row['category_name'] ?? '')
                    ];
                    $groupedTransactions[$transactionId] = $transaction;
                    break;
                }
            }
        }
    }
}

// Convert services from associative array to indexed array and transactions to array
foreach ($groupedTransactions as $transactionId => $transaction) {
    if (empty($transaction['services'])) {
        // If still no services, add a placeholder
        $groupedTransactions[$transactionId]['services'] = [['name' => 'Service', 'category' => '']];
    } else {
        $groupedTransactions[$transactionId]['services'] = array_values($transaction['services']);
    }
}
$transactions = array_values($groupedTransactions);

// Calculate comprehensive totals - matching daily_sales.php logic
$totalTransactions = count($transactions);
$grossRevenue = 0;
$totalDiscounts = 0;
$netRevenue = 0;

foreach ($transactions as $transaction) {
    if ($transaction['payment_status'] === 'Completed') {
        // Match daily_sales.php logic exactly: amount - discount_amount
        $amount = $transaction['amount'] ?? 0;
        $discount = $transaction['discount_amount'] ?? 0;
        $net = $amount - $discount;
        
        $grossRevenue += $amount;
        $totalDiscounts += $discount;
        $netRevenue += $net;
    }
}

// Calculate total number of unique patients from transactions
$uniquePatientsQuery = "SELECT COUNT(DISTINCT t.patient_id) as total_patients
                        FROM transactions t
                        WHERE DATE(t.transaction_date) BETWEEN ? AND ?";
$uniquePatientsParams = [$startDate, $endDate];
$uniquePatientsTypes = 'ss';

$uniquePatientsStmt = $conn->prepare($uniquePatientsQuery);
$uniquePatientsStmt->bind_param($uniquePatientsTypes, ...$uniquePatientsParams);
$uniquePatientsStmt->execute();
$uniquePatientsResult = $uniquePatientsStmt->get_result()->fetch_assoc();
$totalPatients = $uniquePatientsResult['total_patients'] ?? 0;

// Calculate total number of appointments
$appointmentsQuery = "SELECT COUNT(*) as total_appointments
                      FROM appointments a
                      WHERE DATE(a.appointment_date) BETWEEN ? AND ?";
$appointmentsParams = [$startDate, $endDate];
$appointmentsTypes = 'ss';

$appointmentsStmt = $conn->prepare($appointmentsQuery);
$appointmentsStmt->bind_param($appointmentsTypes, ...$appointmentsParams);
$appointmentsStmt->execute();
$appointmentsResult = $appointmentsStmt->get_result()->fetch_assoc();
$totalAppointments = $appointmentsResult['total_appointments'] ?? 0;

include 'includes/header.php';
?>

<style>
.transaction-table {
    font-size: 0.8rem;
}

.transaction-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border: 1px solid #dee2e6;
    padding: 8px 4px;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
}

.transaction-table td {
    border: 1px solid #dee2e6;
    padding: 6px 4px;
    vertical-align: middle;
    font-size: 0.75rem;
}

.table-container {
    max-height: 70vh;
    overflow: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

.summary-cards {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.filter-section {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.text-truncate-custom {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
}

.daily-sales-btn {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    color: white;
    transition: all 0.3s ease;
}

.daily-sales-btn:hover {
    background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    color: white;
    transform: translateY(-1px);
}

.breadcrumb-nav {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.refresh-indicator {
    display: none;
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    z-index: 1000;
}

.print-logo-container {
    margin-bottom: 20px;
}

.print-logo {
    max-width: 150px;
    height: auto;
    display: block;
    margin: 0 auto;
    border-radius: 70px;
}

.print-date-range-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin: 10px 0;
    flex-wrap: wrap;
}

.print-period-label {
    font-weight: 500;
    font-size: 1rem;
}

.print-date-separator {
    color: #666;
    font-size: 1.2rem;
    font-weight: 300;
    padding: 0 10px;
}

.print-date-range {
    font-weight: 500;
    font-size: 1rem;
}

@media print {
    .sidebar,
    .sidebar-overlay,
    .mobile-menu-btn,
    .top-bar { 
        display: none !important; 
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .d-print-none { display: none !important; }
    .transaction-table { font-size: 8px; }
    .transaction-table th, .transaction-table td { padding: 1px 2px; }
    .table-container { max-height: none; overflow: visible; }
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
    .print-logo {
        max-width: 120px;
        margin-bottom: 5px;
    }
    .print-logo-container {
        margin-bottom: 15px;
    }
    .print-date-range-row {
        gap: 10px;
        margin: 8px 0;
    }
    .print-period-label,
    .print-date-range {
        font-size: 0.9rem;
    }
    .print-date-separator {
        font-size: 1rem;
        padding: 0 8px;
    }
}
</style>

<div class="container-fluid px-4">
    <!-- Refresh Indicator -->
    <div id="refreshIndicator" class="refresh-indicator">
        <i class="fas fa-sync-alt me-2"></i> Data refreshed successfully!
    </div>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <div>
            <h2>
                <i class="fas fa-chart-line text-primary"></i> 
                Analytics Report
                <?php if ($isToday): ?>
                    <span class="badge bg-success ms-2">Today</span>
                <?php endif; ?>
            </h2>
            <p class="text-muted mb-0">
                <?php 
                $periodLabels = [
                    'day' => 'Today',
                    'week' => 'This Week',
                    'month' => 'Previous Month & This Month',
                    'year' => 'Previous Year & This Year'
                ];
                echo $periodLabels[$period] ?? 'Today';
                ?>
                (<?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>)
                <small class="text-success ms-2">
                    <i class="fas fa-clock"></i> Last updated: <?php echo date('g:i A'); ?>
                </small>
            </p>
        </div>
        <div class="btn-group">
            <button onclick="refreshData()" class="btn btn-outline-primary" title="Refresh Data">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards d-print-none">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                    <h3 class="mb-1"><?php echo number_format($totalPatients); ?></h3>
                    <p class="mb-0 opacity-75">Total Number of Patients</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                    </div>
                    <h3 class="mb-1"><?php echo number_format($totalAppointments); ?></h3>
                    <p class="mb-0 opacity-75">Total Number of Appointments</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
                    </div>
                    <h3 class="mb-1">₱<?php echo number_format($netRevenue, 2); ?></h3>
                    <p class="mb-0 opacity-75">Net Revenue</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section d-print-none">
        <form method="GET" class="row g-3 align-items-end">
            <?php if (isset($_GET['iframe']) && $_GET['iframe'] == '1'): ?>
            <input type="hidden" name="iframe" value="1">
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label fw-bold">Period</label>
                <select name="period" class="form-select" onchange="this.form.submit()">
                    <option value="day" <?php echo $period === 'day' ? 'selected' : ''; ?>>Per Day</option>
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Per Week</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Per Month</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Per Year</option>
                </select>
            </div>
            <div class="col-md-8">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Period:</strong> 
                    <?php 
                    $periodLabels = [
                        'day' => 'Today',
                        'week' => 'This Week (Monday to Today)',
                        'month' => 'Previous Month & This Month (1st of Previous Month to Today)',
                        'year' => 'Previous Year & This Year (Jan 1st of Previous Year to Today)'
                    ];
                    echo $periodLabels[$period] ?? 'Today';
                    ?>
                    <br>
                    <small>Date Range: <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?></small>
                </div>
            </div>
        </form>
    </div>

    <!-- Print Header -->
    <div class="d-none d-print-block text-center mb-4">
        <div class="print-logo-container">
            <img src="img/logo.png" alt="Mhavis Medical Logo" class="print-logo">
        </div>
        <h2>MHAVIS MEDICAL & DIAGNOSTIC CENTER</h2>
        <h3>Analytics Report</h3>
        <?php if ($showSeparatePeriods && $prevPeriodData && $currentPeriodData): ?>
            <div class="print-date-range-row">
                <span class="print-date-range"><?php echo date('F j, Y', strtotime($startDate)); ?> - <?php echo date('F j, Y', strtotime($endDate)); ?></span>
            </div>
        <?php else: ?>
            <p><?php echo date('F j, Y', strtotime($startDate)); ?> - <?php echo date('F j, Y', strtotime($endDate)); ?></p>
        <?php endif; ?>
        <hr>
    </div>

    <!-- Report Display -->
    <?php if ($showSeparatePeriods && $prevPeriodData && $currentPeriodData): ?>
        <!-- Previous Period Table -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar text-primary me-2"></i>
                    Report Analytics - <?php echo $prevPeriodLabel; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50%;">Metric</th>
                                        <th style="width: 50%;">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><i class="fas fa-users me-2"></i>Total Number of Patients</strong></td>
                                        <td class="fs-4 fw-bold"><?php echo number_format($prevPeriodData['totalPatients']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-calendar-check me-2"></i>Total Number of Appointments</strong></td>
                                        <td class="fs-4 fw-bold"><?php echo number_format($prevPeriodData['totalAppointments']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-chart-line me-2"></i>Net Revenue</strong></td>
                                        <td class="fs-4 fw-bold text-success">₱<?php echo number_format($prevPeriodData['netRevenue'], 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Period Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar text-primary me-2"></i>
                    Report Analytics - <?php echo $currentPeriodLabel; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50%;">Metric</th>
                                        <th style="width: 50%;">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><i class="fas fa-users me-2"></i>Total Number of Patients</strong></td>
                                        <td class="fs-4 fw-bold"><?php echo number_format($currentPeriodData['totalPatients']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-calendar-check me-2"></i>Total Number of Appointments</strong></td>
                                        <td class="fs-4 fw-bold"><?php echo number_format($currentPeriodData['totalAppointments']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-chart-line me-2"></i>Net Revenue</strong></td>
                                        <td class="fs-4 fw-bold text-success">₱<?php echo number_format($currentPeriodData['netRevenue'], 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Single Period Table (for day/week or when separate periods not available) -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar text-primary me-2"></i>
                    Report Analytics
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50%;">Metric</th>
                                        <th style="width: 50%;">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><i class="fas fa-users me-2"></i>Total Number of Patients</strong></td>
                                        <td class="fs-4 fw-bold"><?php echo number_format($totalPatients); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-calendar-check me-2"></i>Total Number of Appointments</strong></td>
                                        <td class="fs-4 fw-bold"><?php echo number_format($totalAppointments); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-chart-line me-2"></i>Net Revenue</strong></td>
                                        <td class="fs-4 fw-bold text-success">₱<?php echo number_format($netRevenue, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Print Footer -->
    <div class="d-none d-print-block mt-4 text-center">
        <hr>
        <p class="small text-muted">
            Generated on <?php echo date('F j, Y g:i A'); ?>
            <?php if ($showSeparatePeriods && $prevPeriodData && $currentPeriodData): ?>
                <br>
                <strong><?php echo $prevPeriodLabel; ?>:</strong> 
                Total Patients: <?php echo number_format($prevPeriodData['totalPatients']); ?> | 
                Total Appointments: <?php echo number_format($prevPeriodData['totalAppointments']); ?> | 
                Net Revenue: ₱<?php echo number_format($prevPeriodData['netRevenue'], 2); ?>
                <br>
                <strong><?php echo $currentPeriodLabel; ?>:</strong> 
                Total Patients: <?php echo number_format($currentPeriodData['totalPatients']); ?> | 
                Total Appointments: <?php echo number_format($currentPeriodData['totalAppointments']); ?> | 
                Net Revenue: ₱<?php echo number_format($currentPeriodData['netRevenue'], 2); ?>
                <br>
                <strong>Combined Total:</strong> 
                Total Patients: <?php echo number_format($totalPatients); ?> | 
                Total Appointments: <?php echo number_format($totalAppointments); ?> | 
                Net Revenue: ₱<?php echo number_format($netRevenue, 2); ?>
            <?php else: ?>
                | Total Patients: <?php echo number_format($totalPatients); ?> | 
                Total Appointments: <?php echo number_format($totalAppointments); ?> | 
                Net Revenue: ₱<?php echo number_format($netRevenue, 2); ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Print optimization
    window.addEventListener('beforeprint', function() {
        // Any print-specific optimizations can go here
    });

    window.addEventListener('afterprint', function() {
        // Any post-print cleanup can go here
    });


    // Check for updates if this is today's view
    <?php if ($isToday): ?>
    // Check for updates every 2 minutes on today's view
    setInterval(function() {
        checkForUpdates();
    }, 120000);
    <?php endif; ?>
});

// Refresh data function
function refreshData() {
    const refreshButton = document.querySelector('button[onclick="refreshData()"]');
    const icon = refreshButton.querySelector('i');
    
    // Show loading state
    icon.classList.add('fa-spin');
    refreshButton.disabled = true;
    
    // Get current URL parameters
    const currentParams = new URLSearchParams(window.location.search);
    
    // Add cache busting parameter
    currentParams.set('refresh', Date.now());
    
    // Reload page with parameters
    window.location.href = window.location.pathname + '?' + currentParams.toString();
}

// Check for updates function
function checkForUpdates() {
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.set('ajax', '1');
    currentParams.set('check_updates', '1');
    
    fetch(window.location.pathname + '?' + currentParams.toString())
        .then(response => response.json())
        .then(data => {
            if (data.has_updates) {
                showUpdateNotification();
            }
        })
        .catch(error => {
            console.log('Update check failed:', error);
        });
}

// Show update notification
function showUpdateNotification() {
    const indicator = document.getElementById('refreshIndicator');
    indicator.innerHTML = '<i class="fas fa-info-circle me-2"></i> New transactions available. <a href="#" onclick="refreshData()" class="text-white text-decoration-underline">Refresh now</a>';
    indicator.style.display = 'block';
    indicator.style.background = '#17a2b8';
    
    setTimeout(() => {
        indicator.style.display = 'none';
    }, 10000);
}


// Initialize tooltips if Bootstrap is available
if (typeof bootstrap !== 'undefined') {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Handle browser back/forward navigation
window.addEventListener('popstate', function(event) {
    if (event.state && event.state.refresh) {
        location.reload();
    }
});

// Add state to history for refresh tracking
history.replaceState({refresh: true}, document.title, window.location.href);
</script>

<?php 
// Handle AJAX requests for update checking
if (isset($_GET['ajax']) && isset($_GET['check_updates'])) {
    $lastTransactionQuery = "SELECT MAX(id) as max_id FROM transactions WHERE DATE(transaction_date) = ?";
    $lastStmt = $conn->prepare($lastTransactionQuery);
    $lastStmt->bind_param("s", $startDate);
    $lastStmt->execute();
    $lastResult = $lastStmt->get_result()->fetch_assoc();
    
    $response = [
        'has_updates' => false,
        'last_transaction_id' => $lastResult['max_id'],
        'timestamp' => time()
    ];
    
    // Check if there are new transactions since last load
    if (isset($_SESSION['last_transaction_id_' . $startDate])) {
        if ($lastResult['max_id'] > $_SESSION['last_transaction_id_' . $startDate]) {
            $response['has_updates'] = true;
        }
    }
    
    $_SESSION['last_transaction_id_' . $startDate] = $lastResult['max_id'];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

include 'includes/footer.php'; 
?>
