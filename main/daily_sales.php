<?php
define('MHAVIS_EXEC', true);
$page_title = "Daily Revenue";
$active_page = "sales";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

// Check if page is loaded in iframe
$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';

// Get filter parameters
$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$paymentMethod = isset($_GET['payment_method']) ? sanitize($_GET['payment_method']) : 'all';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Check if optional columns exist in the transactions table
$checkColumns = $conn->query("SHOW COLUMNS FROM transactions LIKE 'reference_number'");
$hasReferenceNumber = $checkColumns->num_rows > 0;

$checkNotes = $conn->query("SHOW COLUMNS FROM transactions LIKE 'notes'");
$hasNotes = $checkNotes->num_rows > 0;

$checkPhone = $conn->query("SHOW COLUMNS FROM patients LIKE 'phone'");
$hasPhone = $checkPhone->num_rows > 0;

// Check if transaction_items table exists
$checkTransactionItems = $conn->query("SHOW TABLES LIKE 'transaction_items'");
$hasTransactionItems = $checkTransactionItems->num_rows > 0;

// Build transactions query with enhanced search
// Use transaction_items if available to show all services, otherwise fall back to single fee
if ($hasTransactionItems) {
    // Query using transaction_items to show all services for each transaction
    // Use LEFT JOIN to get all items, and also get transactions without items
    $query = "SELECT t.*, 
              CONCAT(p.first_name, ' ', p.last_name) as patient_name,
              " . ($hasPhone ? "p.phone as patient_phone," : "'' as patient_phone,") . "
              p.is_senior_citizen, p.senior_citizen_id, p.is_pwd, p.pwd_id,
              COALESCE(NULLIF(ti.fee_name, ''), f.name, f2.name, '') as fee_name,
              COALESCE(NULLIF(fc.name, ''), NULLIF(fc2.name, ''), '') as category_name,
              COALESCE(fc.id, fc2.id, 0) as category_id,
              CONCAT(u.first_name, ' ', u.last_name) as staff_name,
              ti.id as item_id,
              COALESCE(ti.quantity, 1) as item_quantity,
              ti.unit_price as item_unit_price,
              ti.total_price as item_total_price" .
              ($hasNotes ? ", t.notes" : ", '' as notes") .
              ($hasReferenceNumber ? ", t.reference_number" : ", CONCAT('TXN-', t.id) as reference_number") . "
              FROM transactions t
              JOIN patients p ON t.patient_id = p.id
              LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
              LEFT JOIN fees f ON ti.fee_id = f.id
              LEFT JOIN fee_categories fc ON f.category_id = fc.id
              LEFT JOIN fees f2 ON t.fee_id = f2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
              LEFT JOIN fee_categories fc2 ON f2.category_id = fc2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
              JOIN users u ON t.created_by = u.id
              WHERE DATE(t.transaction_date) = ?
              AND (ti.id IS NOT NULL OR (ti.id IS NULL AND t.fee_id IS NOT NULL))";
} else {
    // Fallback to original query if transaction_items doesn't exist
    $query = "SELECT t.*, 
              CONCAT(p.first_name, ' ', p.last_name) as patient_name,
              " . ($hasPhone ? "p.phone as patient_phone," : "'' as patient_phone,") . "
              p.is_senior_citizen, p.senior_citizen_id, p.is_pwd, p.pwd_id,
              f.name as fee_name,
              fc.name as category_name,
              fc.id as category_id,
              CONCAT(u.first_name, ' ', u.last_name) as staff_name" .
              ($hasNotes ? ", t.notes" : ", '' as notes") .
              ($hasReferenceNumber ? ", t.reference_number" : ", CONCAT('TXN-', t.id) as reference_number") . "
              FROM transactions t
              JOIN patients p ON t.patient_id = p.id
              JOIN fees f ON t.fee_id = f.id
              JOIN fee_categories fc ON f.category_id = fc.id
              JOIN users u ON t.created_by = u.id
              WHERE DATE(t.transaction_date) = ?";
}

$params = [$date];
$types = "s";

// Add search functionality
if (!empty($search)) {
    if ($hasTransactionItems) {
        $searchConditions = [
            "CONCAT(p.first_name, ' ', p.last_name) LIKE ?", 
            "COALESCE(NULLIF(ti.fee_name, ''), f.name, f2.name) LIKE ?"
        ];
    } else {
        $searchConditions = [
            "CONCAT(p.first_name, ' ', p.last_name) LIKE ?", 
            "f.name LIKE ?"
        ];
    }
    $searchParams = ["%$search%", "%$search%"];
    
    if ($hasReferenceNumber) {
        $searchConditions[] = "t.reference_number LIKE ?";
        $searchParams[] = "%$search%";
    }
    
    if ($hasPhone) {
        $searchConditions[] = "p.phone LIKE ?";
        $searchParams[] = "%$search%";
    }
    
    $query .= " AND (" . implode(" OR ", $searchConditions) . ")";
    $params = array_merge($params, $searchParams);
    $types .= str_repeat("s", count($searchParams));
}

if ($paymentMethod !== 'all') {
    $query .= " AND t.payment_method = ?";
    $params[] = $paymentMethod;
    $types .= "s";
}

if ($status !== 'all') {
    $query .= " AND t.payment_status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($category !== 'all') {
    if ($hasTransactionItems) {
        $query .= " AND (fc.id = ? OR (ti.id IS NULL AND t.fee_id IN (SELECT id FROM fees WHERE category_id = ?)))";
        $params[] = $category;
        $params[] = $category;
        $types .= "ss";
    } else {
        $query .= " AND fc.id = ?";
        $params[] = $category;
        $types .= "s";
    }
}

// Order by transaction date and transaction ID to group services from same transaction together
if ($hasTransactionItems) {
    $query .= " ORDER BY t.transaction_date DESC, t.id DESC, ti.id ASC";
} else {
    $query .= " ORDER BY t.transaction_date DESC";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$allTransactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group transactions by transaction ID to collect all services for each transaction
$groupedTransactions = [];
foreach ($allTransactions as $row) {
    $transactionId = $row['id'];
    
    if (!isset($groupedTransactions[$transactionId])) {
        // Initialize transaction with first row's data
        $groupedTransactions[$transactionId] = [
            'id' => $row['id'],
            'transaction_date' => $row['transaction_date'],
            'patient_name' => $row['patient_name'],
            'patient_phone' => $row['patient_phone'] ?? '',
            'is_senior_citizen' => $row['is_senior_citizen'] ?? 0,
            'is_pwd' => $row['is_pwd'] ?? 0,
            'amount' => $row['amount'],
            'discount_amount' => $row['discount_amount'] ?? 0,
            'payment_method' => $row['payment_method'],
            'payment_status' => $row['payment_status'],
            'notes' => $row['notes'] ?? '',
            'reference_number' => $row['reference_number'] ?? '',
            'staff_name' => $row['staff_name'],
            'services' => [] // Array to collect all services
        ];
    }
    
    // Add service to this transaction's services array
    // Check if fee_name exists and is not null/empty
    $feeName = trim($row['fee_name'] ?? '');
    if (!empty($feeName)) {
        $serviceInfo = [
            'name' => $feeName,
            'category' => trim($row['category_name'] ?? ''),
            'item_price' => isset($row['item_total_price']) && $row['item_total_price'] > 0 
                ? $row['item_total_price'] 
                : null,
            'quantity' => $row['item_quantity'] ?? 1,
            'unit_price' => $row['item_unit_price'] ?? null
        ];
        
        // Only add if not already added (avoid duplicates)
        // Use item_id if available, otherwise use fee_name with category
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
                        'category' => trim($row['category_name'] ?? ''),
                        'item_price' => null,
                        'quantity' => 1,
                        'unit_price' => null
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
        $groupedTransactions[$transactionId]['services'] = [['name' => 'Service', 'category' => '', 'item_price' => null, 'quantity' => 1, 'unit_price' => null]];
    } else {
        $groupedTransactions[$transactionId]['services'] = array_values($transaction['services']);
    }
}
$transactions = array_values($groupedTransactions);

// Get categories for filter dropdown
$categoriesQuery = "SELECT DISTINCT fc.id, fc.name FROM fee_categories fc 
                   JOIN fees f ON fc.id = f.category_id 
                   JOIN transactions t ON f.id = t.fee_id 
                   WHERE DATE(t.transaction_date) = ? ORDER BY fc.name";
$categoriesStmt = $conn->prepare($categoriesQuery);
$categoriesStmt->bind_param("s", $date);
$categoriesStmt->execute();
$categories = $categoriesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper function to ensure numeric values (replace NaN/null with 0)
function ensureNumeric($value) {
    if (!is_numeric($value) || is_nan($value) || $value === null) {
        return 0;
    }
    return (float)$value;
}

// Calculate comprehensive summaries
$totalAmount = 0;
$totalDiscount = 0;
$netAmount = 0;
$methodTotals = [];
$categoryTotals = [];
$statusCounts = ['Completed' => 0, 'Pending' => 0, 'Refunded' => 0];
$patientSummary = [];

foreach ($transactions as $transaction) {
    $amount = is_numeric($transaction['amount']) ? (float)$transaction['amount'] : 0;
    $discount = is_numeric($transaction['discount_amount'] ?? 0) ? (float)($transaction['discount_amount'] ?? 0) : 0;
    $net = $amount - $discount;
    // Ensure net is not negative or NaN
    $net = max(0, is_numeric($net) ? (float)$net : 0);
    
    // Status counts
    $statusCounts[$transaction['payment_status']]++;
    
    if ($transaction['payment_status'] === 'Completed') {
        // Ensure all values are numeric before adding
        $amount = ensureNumeric($amount);
        $discount = ensureNumeric($discount);
        $net = ensureNumeric($net);
        
        $totalAmount += $amount;
        $totalDiscount += $discount;
        $netAmount += $net;
        
        // Payment method totals
        $method = $transaction['payment_method'];
        $methodTotals[$method] = ($methodTotals[$method] ?? 0) + $net;
    }
    
    // Category totals - count each service/item separately
    if ($transaction['payment_status'] === 'Completed' && !empty($transaction['services'])) {
        foreach ($transaction['services'] as $service) {
            $category = $service['category'] ?? 'Uncategorized';
            $itemNet = $service['item_price'] ?? ($net / max(1, count($transaction['services'])));
            // Ensure itemNet is numeric and not null/NaN
            $itemNet = is_numeric($itemNet) ? (float)$itemNet : 0;
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + $itemNet;
        }
    }
    
    // Patient summary - include all services
    $patientId = $transaction['patient_name'];
    if (!isset($patientSummary[$patientId])) {
        $patientSummary[$patientId] = [
            'name' => $transaction['patient_name'],
            'phone' => $transaction['patient_phone'] ?? '',
            'total' => 0,
            'services' => []
        ];
    }
    
    if ($transaction['payment_status'] === 'Completed') {
        $patientSummary[$patientId]['total'] += $net;
    }
    
    // Add all services to patient summary
    foreach ($transaction['services'] as $service) {
        $itemNet = $service['item_price'] ?? ($net / max(1, count($transaction['services'])));
        // Ensure itemNet is numeric and not null/NaN
        $itemNet = is_numeric($itemNet) ? (float)$itemNet : 0;
        $patientSummary[$patientId]['services'][] = [
            'name' => $service['name'],
            'amount' => $itemNet,
            'status' => $transaction['payment_status']
        ];
    }
}

// Clinic Information for Print Report
$clinicName = "MHAVIS Medical Clinic";
$clinicAddress = "123 Medical Street, Health District";
$clinicCity = "Manila, Philippines";
$clinicPhone = "(02) 1234-5678";
$clinicEmail = "info@mhavisclinic.com";

include 'includes/header.php';
?>

<style>
.revenue-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}
.revenue-card:hover {
    transform: translateY(-2px);
}
.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
}
.search-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
}
.filter-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    border: none;
}
.transaction-row {
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
}
.transaction-row:hover {
    background-color: #f8f9ff;
    border-left-color: #667eea;
}
.completed { border-left-color: #28a745; }
.pending { border-left-color: #ffc107; }
.refunded { border-left-color: #dc3545; }
.category-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.6rem;
    border-radius: 15px;
}

/* Improved table spacing and layout */
#transactionsTable {
    width: 100% !important;
    table-layout: auto;
}

#transactionsTable th,
#transactionsTable td {
    white-space: normal;
    word-wrap: break-word;
    vertical-align: middle;
}

#transactionsTable tbody tr {
    border-bottom: 1px solid #e9ecef;
}

#transactionsTable tbody tr:last-child {
    border-bottom: none;
}

/* Ensure table is not compressed */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Better spacing for table content */
#transactionsTable .btn-group {
    display: flex;
    flex-wrap: nowrap;
    gap: 4px;
}

/* Ensure transaction details card uses full width */
.col-12 .card {
    width: 100%;
}

.col-12 .card-body {
    width: 100%;
}
.patient-summary-card {
    border-radius: 10px;
    border: 1px solid #e9ecef;
    margin-bottom: 0.5rem;
    transition: box-shadow 0.2s ease;
}
.patient-summary-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.report-nav-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    transition: all 0.3s ease;
}
.report-nav-btn:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    color: white;
    transform: translateY(-1px);
}

/* Print Styles for Daily Report */
@media print {
    * {
        background: transparent !important;
        color: black !important;
        box-shadow: none !important;
        text-shadow: none !important;
    }

    body {
        font-family: 'Times New Roman', serif;
        font-size: 12pt;
        line-height: 1.4;
        margin: 0;
        padding: 0;
    }

    .container-fluid {
        padding: 0 20px !important;
        margin: 0 auto !important;
        max-width: 95% !important;
        width: 100% !important;
    }

    /* Hide all UI elements */
    .no-print,
    .search-container,
    .revenue-card,
    .card-header,
    .card,
    .btn,
    .btn-group,
    .dropdown,
    nav,
    .sidebar,
    footer,
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_paginate,
    .dataTables_wrapper .dataTables_info,
    /* Hide page header with title and description */
    .d-flex.justify-content-between.align-items-center.mb-4,
    /* Hide all headings (print sections use divs, not headings) */
    h1, h2, h3, h4, h5, h6,
    /* Hide summary cards */
    .row.mb-4,
    /* Hide regular transaction table */
    .table-responsive,
    #transactionsTable,
    /* Hide top bar and mobile menu button (three-bar icon) */
    .top-bar,
    .mobile-menu-btn,
    #mobileMenuBtn {
        display: none !important;
    }
    
    /* Ensure print sections are visible */
    .print-header,
    .print-table-container,
    .print-summary,
    .print-footer {
        display: block !important;
        width: 100%;
    }

    /* Print header */
    .print-header {
        text-align: center;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 2px solid black;
    }

    .print-header .clinic-logo {
        width: 100px;
        height: 100px;
        margin: 0 auto 10px;
    }

    .print-header .clinic-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .print-header .clinic-name {
        font-size: 20pt;
        font-weight: bold;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .print-header .clinic-info {
        font-size: 11pt;
        margin-bottom: 10px;
    }

    .print-header .report-date {
        font-size: 13pt;
        font-weight: bold;
        margin-top: 10px;
    }

    /* Print table */
    .print-table-container {
        /*display: block !important;
        margin: 20px 0;
        width: 100%;*/
        margin-top: 10px;
    }

    .print-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 auto;
        font-size: 10.5pt;
        table-layout: auto;
    }

    /*.print-table th {
        text-align: left;
        font-weight: bold;
        padding: 10px 8px;
        border-bottom: 2px solid black;
        background-color: #f5f5f5 !important;
        font-size: 10pt;
    }*/
    .print-table th,
    .print-table td {
        padding: 8px 6px;
        border: 1px solid #ddd;
    }

    /*.print-table td {
        padding: 8px;
        border-bottom: 1px solid #ddd;
        font-size: 10pt;
    }*/
    .print-table th{
        background:#f0f0f0 !important;
        font-weight: bold;
        text-align: center;
    }

    /*.print-table tbody tr {
        page-break-inside: avoid;
    }

    .print-table thead {
        display: table-header-group;
    }

    .print-table tfoot {
        display: table-footer-group;
    }*/
    .print-table td{
        text-align: left;
    }

    /* Print summary */
    .print-summary {
        margin-top: 15px;
        padding-top: 10px;
        border-top: 2px solid black;
    }

    .print-summary .summary-row {
        display: flex;
        justify-content: space-between;
        margin: 2px 0;
        font-size: 11pt;
    }

    .print-summary .summary-total {
        font-weight: bold;
        font-size: 13pt;
        margin-top: 10px;
        padding-top: 8px;
        border-top: 2px solid black;
    }

    /* Print footer */
    .print-footer {
        text-align: center;
        margin-top: 20px;
        border-top: 1px solid #ccc;
        font-size: 9pt;
        font-style: italic;
    }

    /* Ensure proper page breaks */
    @page {
        margin: 0.5cm;
        size: A4 portrait;
    }
}

/* Screen-only print button area */
.print-button-area {
    display: none;
}

@media screen {
    .print-button-area {
        display: block;
    }
}

/* Ensure sidebar is hidden in iframe mode - applied immediately */
<?php if ($is_iframe): ?>
body.iframe-mode .sidebar,
body.iframe-mode .sidebar-overlay {
    display: none !important;
}

body.iframe-mode .main-content {
    margin-left: 0 !important;
    padding: 0 !important;
}

body.iframe-mode .top-bar {
    display: none !important;
}

body.iframe-mode .container-fluid {
    padding: 0 !important;
}
<?php endif; ?>
</style>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-calendar-day text-primary me-2"></i>Daily Revenue</h2>
            <p class="text-muted mb-0">Track daily transactions and revenue for <?php echo date('F j, Y', strtotime($date)); ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php 
            // Get the directory path for proper URL construction
            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
            $scriptDir = rtrim($scriptDir, '/\\');
            if ($scriptDir === '' || $scriptDir === '.' || $scriptDir === '/') {
                $basePath = '';
            } else {
                $basePath = $scriptDir . '/';
            }
            ?>
            <a href="<?php echo $basePath; ?>add_transaction.php?date=<?php echo urlencode($date); ?><?php echo $is_iframe ? '&iframe=1' : ''; ?>" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Transaction
            </a>
            <div class="dropdown">
                <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Daily Report
            </button>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-container">
        <div class="row">
            <div class="col-md-12 mb-3">
                <h5 class="mb-3">
                    <i class="fas fa-filter me-2"></i>
                    Filters & Search
                </h5>
            </div>
        </div>
        <form method="GET" id="filterForm">
            <?php if ($is_iframe): ?>
                <input type="hidden" name="iframe" value="1">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Date</label>
                    <input type="date" name="date" class="form-control filter-input" value="<?php echo $date; ?>" 
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Category</label>
                    <select name="category" class="form-select filter-input">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" class="form-select filter-input">
                        <option value="all">All Methods</option>
                        <option value="Cash" <?php echo $paymentMethod === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="Online" <?php echo $paymentMethod === 'Online' ? 'selected' : ''; ?>>Gcash</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select filter-input">
                        <option value="all">All Status</option>
                        <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-light me-2">
                        <i class="fas fa-search me-1"></i> Apply Filters
                    </button>
                    <a href="?date=<?php echo $date; ?><?php echo $is_iframe ? '&iframe=1' : ''; ?>" class="btn btn-outline-light me-2">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card revenue-card bg-primary text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title mb-1">Gross Revenue</h6>
                        <h2 class="mb-0"><?php echo formatCurrency(ensureNumeric($totalAmount)); ?></h2>
                        <small class="opacity-75">Before discounts</small>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card revenue-card bg-success text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title mb-1">Net Revenue</h6>
                        <h2 class="mb-0"><?php echo formatCurrency(ensureNumeric($netAmount)); ?></h2>
                        <small class="opacity-75">After discounts</small>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card revenue-card bg-info text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title mb-1">Total Discounts</h6>
                        <h2 class="mb-0"><?php echo formatCurrency(ensureNumeric($totalDiscount)); ?></h2>
                        <small class="opacity-75">Given to patients</small>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-percent fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card revenue-card bg-warning text-dark h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title mb-1">Transactions</h6>
                        <h2 class="mb-0"><?php echo count($transactions); ?></h2>
                        <small>
                            <span class="badge bg-success me-1"><?php echo $statusCounts['Completed']; ?> Done</span>
                            <span class="badge bg-warning me-1"><?php echo $statusCounts['Pending']; ?> Pending</span>
                        </small>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-receipt fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Transactions List -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list-alt text-primary me-2"></i>
                            Transaction Details
                        </h5>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="transactionsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 140px; padding: 12px 18px;">Time</th>
                                    <th style="min-width: 220px; padding: 12px 18px;">Patient</th>
                                    <th style="min-width: 300px; padding: 12px 18px;">Service Details</th>
                                    <th style="min-width: 150px; padding: 12px 18px;">Amount</th>
                                    <th style="min-width: 120px; padding: 12px 18px;">Payment</th>
                                    <th style="min-width: 120px; padding: 12px 18px;">Status</th>
                                    <th style="min-width: 150px; padding: 12px 18px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-info-circle text-muted me-2"></i>
                                            No transactions found for <?php echo formatDate($date); ?>
                                            <br>
                                            <a href="<?php echo $basePath; ?>add_transaction.php?date=<?php echo urlencode($date); ?><?php echo $is_iframe ? '&iframe=1' : ''; ?>" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-plus me-1"></i> Add First Transaction
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr class="transaction-row <?php echo strtolower($transaction['payment_status']); ?>">
                                            <td class="align-middle" style="padding: 15px 18px;">
                                                <div style="line-height: 1.6;">
                                                    <strong style="display: block; margin-bottom: 4px;"><?php echo formatDateTime($transaction['transaction_date']); ?></strong>
                                                    <small class="text-muted" style="font-size: 0.85rem;">
                                                        <?php 
                                                        $refNum = $transaction['reference_number'] ?? 'TXN-' . $transaction['id'];
                                                        echo htmlspecialchars($refNum); 
                                                        ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="align-middle" style="padding: 15px 18px;">
                                                <div style="line-height: 1.6;">
                                                    <strong style="display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($transaction['patient_name']); ?></strong>
                                                    <?php if ($transaction['is_senior_citizen'] || $transaction['is_pwd']): ?>
                                                        <div style="margin: 6px 0;">
                                                            <?php if ($transaction['is_senior_citizen']): ?>
                                                                <span class="badge bg-warning text-dark me-1">
                                                                    <i class="fas fa-user-clock fa-xs"></i> Senior
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($transaction['is_pwd']): ?>
                                                                <span class="badge bg-info text-white">
                                                                    <i class="fas fa-wheelchair fa-xs"></i> PWD
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($transaction['patient_phone'])): ?>
                                                        <small class="text-muted" style="font-size: 0.85rem; display: block; margin-top: 4px;">
                                                            <i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars(formatPhoneNumber($transaction['patient_phone'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="align-middle" style="padding: 15px 18px;">
                                                <div style="line-height: 1.8;">
                                                    <?php if (!empty($transaction['services'])): ?>
                                                        <?php 
                                                        $serviceList = [];
                                                        foreach ($transaction['services'] as $service) {
                                                            $serviceText = '<div style="margin-bottom: 6px;">' . htmlspecialchars($service['name']);
                                                            if (!empty($service['category'])) {
                                                                $serviceText .= ' <span class="badge category-badge bg-light text-dark border" style="margin-left: 6px;">' . htmlspecialchars($service['category']) . '</span>';
                                                            }
                                                            if (isset($service['quantity']) && $service['quantity'] > 1) {
                                                                $serviceText .= ' <small class="text-muted" style="margin-left: 6px;">(x' . $service['quantity'] . ')</small>';
                                                            }
                                                            $serviceText .= '</div>';
                                                            $serviceList[] = $serviceText;
                                                        }
                                                        echo implode('', $serviceList);
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No services listed</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($transaction['notes'])): ?>
                                                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e9ecef;">
                                                            <small class="text-muted" style="font-size: 0.85rem;">
                                                                <i class="fas fa-sticky-note fa-xs"></i> 
                                                                <?php echo htmlspecialchars(substr($transaction['notes'], 0, 50)); ?>
                                                                <?php if (strlen($transaction['notes']) > 50): ?>...<?php endif; ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="align-middle" style="padding: 15px 18px;">
                                                <div style="line-height: 1.6;">
                                                    <?php 
                                                    $transAmount = ensureNumeric($transaction['amount']);
                                                    $transDiscount = ensureNumeric($transaction['discount_amount'] ?? 0);
                                                    $transNetAmount = $transAmount - $transDiscount;
                                                    ?>
                                                    <strong class="text-primary" style="display: block; margin-bottom: 4px; font-size: 1.05rem;"><?php echo formatCurrency($transNetAmount); ?></strong>
                                                    <?php if ($transDiscount > 0): ?>
                                                        <div style="margin-top: 6px;">
                                                            <small class="text-muted" style="font-size: 0.85rem; display: block;">
                                                                <del><?php echo formatCurrency($transAmount); ?></del>
                                                            </small>
                                                            <small class="text-success" style="font-size: 0.85rem; display: block; margin-top: 2px;">
                                                                -<?php echo formatCurrency($transDiscount); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="align-middle" style="padding: 15px 18px;">
                                                <span class="badge bg-<?php 
                                                    echo $transaction['payment_method'] === 'Cash' ? 'success' : 
                                                        ($transaction['payment_method'] === 'Card' ? 'primary' : 
                                                        ($transaction['payment_method'] === 'Online' ? 'info' : 'secondary')); 
                                                ?>" style="padding: 6px 10px; font-size: 0.85rem;">
                                                    <?php echo $transaction['payment_method']; ?>
                                                </span>
                                            </td>
                                            <td class="align-middle" style="padding: 15px 18px;">
                                                <span class="badge status-badge bg-<?php 
                                                    echo $transaction['payment_status'] === 'Completed' ? 'success' : 
                                                        ($transaction['payment_status'] === 'Pending' ? 'warning' : 'danger'); 
                                                ?>" style="padding: 6px 10px; font-size: 0.85rem;">
                                                    <?php echo $transaction['payment_status']; ?>
                                                </span>
                                            </td>
                                            <td class="align-middle" style="padding: 15px 18px;">
                                                <div class="btn-group" role="group" style="gap: 4px;">
                                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                                            onclick="viewTransaction(<?php echo $transaction['id']; ?>)" 
                                                            title="View Details"
                                                            style="padding: 6px 10px;">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($transaction['payment_status'] === 'Pending'): ?>
                                                        <button type="button" class="btn btn-outline-warning btn-sm" 
                                                                onclick="editTransaction(<?php echo $transaction['id']; ?>)" 
                                                                title="Edit"
                                                                style="padding: 6px 10px;">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Print Report Section (Hidden on screen, shown when printing) -->
    <div class="print-header" style="display: none;">
        <div class="clinic-logo">
            <?php if (file_exists(__DIR__ . '/img/logo.png')): ?>
                <img src="img/logo.png" alt="Clinic Logo">
            <?php elseif (file_exists(__DIR__ . '/img/logo2.jpeg')): ?>
                <img src="img/logo2.jpeg" alt="Clinic Logo">
            <?php else: ?>
                <div style="width: 80px; height: 80px; background: #007bff; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin: 0 auto;">
                    <?php echo substr($clinicName, 0, 2); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="clinic-name"><?php echo htmlspecialchars($clinicName); ?></div>
        <div class="clinic-info">
            <?php echo htmlspecialchars($clinicAddress); ?><br>
            <?php echo htmlspecialchars($clinicCity); ?><br>
            Tel: <?php echo htmlspecialchars($clinicPhone); ?><br>
            Email: <?php echo htmlspecialchars($clinicEmail); ?>
        </div>
        <div class="report-date">
            Daily Sales Report - <?php echo formatDate($date); ?>
        </div>
    </div>

    <!-- Print Table Container -->
    <div class="print-table-container" style="display: none;">
        <?php if (!empty($transactions)): ?>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Receipt #</th>
                        <th>Patient Name</th>
                        <th>Service</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Discount</th>
                        <th>Net Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <?php
                        $refNum = $transaction['reference_number'] ?? 'TXN-' . str_pad($transaction['id'], 6, '0', STR_PAD_LEFT);
                        $amount = ensureNumeric($transaction['amount']);
                        $discount = ensureNumeric($transaction['discount_amount'] ?? 0);
                        $netAmount = ensureNumeric($amount - $discount);
                        
                        // Build service list for print
                        $serviceList = [];
                        $categoryList = [];
                        if (!empty($transaction['services'])) {
                            foreach ($transaction['services'] as $service) {
                                $serviceList[] = htmlspecialchars($service['name']);
                                if (!empty($service['category'])) {
                                    $categoryList[] = htmlspecialchars($service['category']);
                                }
                            }
                        }
                        $servicesText = !empty($serviceList) ? implode(', ', $serviceList) : 'N/A';
                        $categoriesText = !empty($categoryList) ? implode(', ', array_unique($categoryList)) : 'N/A';
                        ?>
                        <tr>
                            <td><?php echo formatDateTime($transaction['transaction_date']); ?></td>
                            <td><?php echo htmlspecialchars($refNum); ?></td>
                            <td>
                                <?php echo htmlspecialchars($transaction['patient_name']); ?>
                                <?php if ($transaction['is_senior_citizen']): ?>
                                    (Senior)
                                <?php endif; ?>
                                <?php if ($transaction['is_pwd']): ?>
                                    (PWD)
                                <?php endif; ?>
                            </td>
                            <td><?php echo $servicesText; ?></td>
                            <td><?php echo $categoriesText; ?></td>
                            <td><?php echo formatCurrency($amount); ?></td>
                            <td><?php echo $discount > 0 ? formatCurrency($discount) : '-'; ?></td>
                            <td><?php echo formatCurrency($netAmount); ?></td>
                            <td><?php echo htmlspecialchars($transaction['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['payment_status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; font-style: italic; margin: 20px 0;">
                No transactions found for <?php echo formatDate($date); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Print Summary Section -->
    <div class="print-summary" style="display: none;">
        <div class="summary-row">
            <span>Total Transactions:</span>
            <span><?php echo count($transactions); ?></span>
        </div>
        <div class="summary-row">
            <span>Completed Transactions:</span>
            <span><?php echo $statusCounts['Completed']; ?></span>
        </div>
        <div class="summary-row">
            <span>Pending Transactions:</span>
            <span><?php echo $statusCounts['Pending']; ?></span>
        </div>
        <div class="summary-row">
            <span>Gross Revenue:</span>
            <span><?php echo formatCurrency(ensureNumeric($totalAmount)); ?></span>
        </div>
        <div class="summary-row">
            <span>Total Discounts:</span>
            <span><?php echo formatCurrency(ensureNumeric($totalDiscount)); ?></span>
        </div>
        <div class="summary-row summary-total">
            <span>TOTAL SALES:</span>
            <span><?php echo formatCurrency(ensureNumeric($netAmount)); ?></span>
        </div>
    </div>

    <!-- Print Footer -->
    <div class="print-footer" style="display: none;">
        <div style="margin-bottom: 5px;">End of Daily Sales Report</div>
        <div>Report Generated by MHAVIS System on <?php echo formatDateTime(date('Y-m-d H:i:s')); ?></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Check if in iframe mode - MUST be defined first
const isIframe = <?php echo $is_iframe ? 'true' : 'false'; ?>;
const iframeParam = isIframe ? '&iframe=1' : '';

// Global function to preserve iframe parameter in URLs
function preserveIframeInUrl(url) {
    if (!isIframe) return url;
    try {
        const urlObj = new URL(url, window.location.origin);
        urlObj.searchParams.set('iframe', '1');
        return urlObj.toString();
    } catch (e) {
        // If URL parsing fails, append iframe parameter manually
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'iframe=1';
    }
}

// Intercept all link clicks to preserve iframe parameter
document.addEventListener('click', function(e) {
    const link = e.target.closest('a[href]');
    if (link && isIframe) {
        const href = link.getAttribute('href');
        // Only intercept relative URLs or same-origin URLs
        if (href && !href.startsWith('http://') && !href.startsWith('https://') && !href.startsWith('mailto:') && !href.startsWith('tel:') && !href.startsWith('#')) {
            const newHref = preserveIframeInUrl(href);
            if (newHref !== href) {
                link.setAttribute('href', newHref);
            }
        }
    }
}, true);

document.addEventListener('DOMContentLoaded', function() {
    
    // Check if table has data rows (not just the "no data" row)
    const tableBody = document.querySelector('#transactionsTable tbody');
    const hasDataRows = tableBody.querySelectorAll('tr').length > 0 && 
                       !tableBody.querySelector('td[colspan]');
    
    // Only initialize DataTables if there are actual data rows
    if (hasDataRows) {
        try {
            // Destroy existing DataTable if it exists
            if ($.fn.DataTable.isDataTable('#transactionsTable')) {
                $('#transactionsTable').DataTable().destroy();
            }

            // Initialize DataTable with enhanced features
            const table = $('#transactionsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                dom: 'rtip',
                language: {
                    search: "Search transactions:",
                    lengthMenu: "Show _MENU_ transactions per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ transactions",
                    paginate: {
                        previous: "Previous",
                        next: "Next"
                    },
                    emptyTable: "No transactions found for this date",
                    zeroRecords: "No matching transactions found"
                },
                columnDefs: [
                    { orderable: false, targets: [6] }, // Actions column not sortable
                    { searchable: false, targets: [6] }  // Actions column not searchable
                ],
                responsive: true,
                autoWidth: false,
                // Handle empty table gracefully
                drawCallback: function(settings) {
                    if (settings.fnRecordsTotal() === 0) {
                        $(this).closest('.dataTables_wrapper').find('.dataTables_paginate, .dataTables_info').hide();
                    } else {
                        $(this).closest('.dataTables_wrapper').find('.dataTables_paginate, .dataTables_info').show();
                    }
                }
            });
        } catch (error) {
            console.error('DataTables initialization error:', error);
            // Fallback: just show the table without DataTables features
        }
    } else {
        // If no data, just hide any existing DataTables elements
        console.log('No data rows found, skipping DataTables initialization');
    }

    // Compact view toggle
    const compactViewToggle = document.getElementById('compactView');
    if (compactViewToggle) {
        compactViewToggle.addEventListener('change', function() {
            const rows = document.querySelectorAll('.transaction-row');
            rows.forEach(row => {
                if (this.checked) {
                    row.style.fontSize = '0.85rem';
                    row.style.padding = '0.25rem';
                } else {
                    row.style.fontSize = '';
                    row.style.padding = '';
                }
            });
        });
    }

    // Payment Methods Chart
    <?php if (!empty($methodTotals)): ?>
    // Clean methodTotals to replace any NaN/null values with 0
    <?php 
    $cleanedMethodTotals = [];
    foreach ($methodTotals as $key => $value) {
        $cleanedMethodTotals[$key] = is_numeric($value) && !is_nan($value) ? (float)$value : 0;
    }
    ?>
    const methodsCanvas = document.getElementById('paymentMethodsChart');
    if (methodsCanvas) {
        const methodsCtx = methodsCanvas.getContext('2d');
        new Chart(methodsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($cleanedMethodTotals)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($cleanedMethodTotals)); ?>,
                    backgroundColor: ['#198754', '#0d6efd', '#17a2b8', '#6f42c1'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Categories Chart
    <?php if (!empty($categoryTotals)): ?>
    // Clean categoryTotals to replace any NaN/null values with 0
    <?php 
    $cleanedCategoryTotals = [];
    foreach ($categoryTotals as $key => $value) {
        $cleanedCategoryTotals[$key] = is_numeric($value) && !is_nan($value) ? (float)$value : 0;
    }
    ?>
    const categoriesCanvas = document.getElementById('categoriesChart');
    if (categoriesCanvas) {
        const categoriesCtx = categoriesCanvas.getContext('2d');
        new Chart(categoriesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($cleanedCategoryTotals)); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_values($cleanedCategoryTotals)); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.8)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                // Replace NaN with 0
                                if (isNaN(value) || value === null || value === undefined) {
                                    value = 0;
                                }
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    <?php endif; ?>
});

// Action Functions
function viewTransaction(id) {
    window.open('view_transaction.php?id=' + id, '_blank');
}

function editTransaction(id) {
    window.location.href = 'edit_transaction.php?id=' + id + iframeParam;
}

function printReceipt(id) {
    window.open('print_receipt.php?id=' + id, '_blank');
}

function printDailyReport() {
    const date = '<?php echo $date; ?>';
    window.open(`report_analytics.php?start_date=${date}&end_date=${date}&print=1`, '_blank');
}

function exportToExcel() {
    const date = '<?php echo $date; ?>';
    const filters = new URLSearchParams(window.location.search);
    let exportUrl = 'export_daily_sales.php?' + filters.toString() + '&format=excel';
    if (isIframe) {
        exportUrl += '&iframe=1';
    }
    window.location.href = exportUrl;
}

// Real-time search functionality and filter auto-submit
const filterForm = document.getElementById('filterForm');
if (filterForm) {
    // Handle search input with debounce
    filterForm.addEventListener('input', function(e) {
        if (e.target.name === 'search') {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(() => {
                ensureIframeParamAndSubmit(this);
            }, 500);
        }
    });
    
    // Handle filter inputs (date, category, payment_method, status) auto-submit
    const filterInputs = filterForm.querySelectorAll('.filter-input');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            ensureIframeParamAndSubmit(filterForm);
        });
    });
    
    // Ensure iframe parameter is preserved on any form submit
    filterForm.addEventListener('submit', function(e) {
        ensureIframeParam(this);
    });
}

// Helper function to ensure iframe parameter is in form before submit
function ensureIframeParam(form) {
    if (isIframe) {
        let iframeInput = form.querySelector('input[name="iframe"]');
        if (!iframeInput) {
            iframeInput = document.createElement('input');
            iframeInput.type = 'hidden';
            iframeInput.name = 'iframe';
            iframeInput.value = '1';
            form.appendChild(iframeInput);
        } else {
            iframeInput.value = '1';
        }
    }
}

// Helper function to ensure iframe param and submit form
function ensureIframeParamAndSubmit(form) {
    ensureIframeParam(form);
    form.submit();
}

// Enhanced transaction actions with confirmation
function deleteTransaction(id) {
    confirmDialog('Are you sure you want to delete this transaction? This action cannot be undone.', 'Delete', 'Cancel').then(function(confirmed) {
        if (!confirmed) return;
        
        fetch('delete_transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: id})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Preserve iframe parameter on reload
                if (isIframe) {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('iframe', '1');
                    window.location.href = currentUrl.toString();
                } else {
                    location.reload();
                }
            } else {
                showAlert('Error deleting transaction: ' + data.message, 'Error', 'error');
            }
        })
        .catch(error => {
            showAlert('Error deleting transaction. Please try again.', 'Error', 'error');
        });
    });
}

// Quick status update
function updateTransactionStatus(id, newStatus) {
    fetch('update_transaction_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Preserve iframe parameter on reload
            if (isIframe) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('iframe', '1');
                window.location.href = currentUrl.toString();
            } else {
                location.reload();
            }
        } else {
            showAlert('Error updating status: ' + data.message, 'Error', 'error');
        }
    })
    .catch(error => {
        showAlert('Error updating status. Please try again.', 'Error', 'error');
    });
}

// Initialize tooltips if Bootstrap is available
if (typeof bootstrap !== 'undefined') {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Summary statistics update animation
function animateCounters() {
    const counters = document.querySelectorAll('.revenue-card h2');
    counters.forEach(counter => {
        const textValue = counter.textContent.replace(/[₱,]/g, '');
        let target = parseFloat(textValue);
        
        // Handle NaN - replace with 0
        if (isNaN(target)) {
            target = parseInt(textValue) || 0;
        }
        if (isNaN(target)) {
            target = 0;
        }
        
        let current = 0;
        const increment = target / 20;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            
            // Ensure current is not NaN
            if (isNaN(current)) {
                current = 0;
            }
            
            if (counter.textContent.includes('₱')) {
                counter.textContent = '₱' + Math.floor(current).toLocaleString();
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 50);
    });
}

// Run counter animation on page load
setTimeout(animateCounters, 500);

// Navigate to analytics with current filters
function goToAnalytics() {
    const params = new URLSearchParams(window.location.search);
    const date = params.get('date') || '<?php echo $date; ?>';
    const category = params.get('category') || '';
    const paymentMethod = params.get('payment_method') || '';
    const status = params.get('status') || '';
    
    let analyticsUrl = `report_analytics.php?start_date=${date}&end_date=${date}`;
    
    if (category && category !== 'all') {
        analyticsUrl += `&category=${category}`;
    }
    if (paymentMethod && paymentMethod !== 'all') {
        analyticsUrl += `&mop=${paymentMethod}`;
    }
    if (status && status !== 'all') {
        analyticsUrl += `&status=${status}`;
    }
    
    if (isIframe) {
        analyticsUrl += '&iframe=1';
    }
    
    window.location.href = analyticsUrl;
}
</script>

<?php include 'includes/footer.php'; ?>