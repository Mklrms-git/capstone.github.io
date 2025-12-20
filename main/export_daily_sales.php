<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireAdmin();

// Check if page is loaded in iframe (for redirect purposes if needed)
$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';

// Get filter parameters
$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$paymentMethod = isset($_GET['payment_method']) ? sanitize($_GET['payment_method']) : 'all';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : 'all';
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'excel';

$conn = getDBConnection();

// Check if transaction_items table exists
$checkTransactionItems = $conn->query("SHOW TABLES LIKE 'transaction_items'");
$hasTransactionItems = $checkTransactionItems->num_rows > 0;

// Build query similar to daily_sales.php
if ($hasTransactionItems) {
    $query = "SELECT t.*, 
              CONCAT(p.first_name, ' ', p.last_name) as patient_name,
              COALESCE(NULLIF(ti.fee_name, ''), f.name, f2.name, '') as fee_name,
              COALESCE(NULLIF(fc.name, ''), NULLIF(fc2.name, ''), '') as category_name,
              CONCAT(u.first_name, ' ', u.last_name) as staff_name
              FROM transactions t
              JOIN patients p ON t.patient_id = p.id
              LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
              LEFT JOIN fees f ON ti.fee_id = f.id
              LEFT JOIN fee_categories fc ON f.category_id = fc.id
              LEFT JOIN fees f2 ON t.fee_id = f2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
              LEFT JOIN fee_categories fc2 ON f2.category_id = fc2.id AND (ti.id IS NULL OR ti.fee_id IS NULL)
              JOIN users u ON t.created_by = u.id
              WHERE DATE(t.transaction_date) = ?";
} else {
    $query = "SELECT t.*, 
              CONCAT(p.first_name, ' ', p.last_name) as patient_name,
              f.name as fee_name,
              fc.name as category_name,
              CONCAT(u.first_name, ' ', u.last_name) as staff_name
              FROM transactions t
              JOIN patients p ON t.patient_id = p.id
              JOIN fees f ON t.fee_id = f.id
              JOIN fee_categories fc ON f.category_id = fc.id
              JOIN users u ON t.created_by = u.id
              WHERE DATE(t.transaction_date) = ?";
}

$params = [$date];
$types = "s";

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

$query .= " ORDER BY t.transaction_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="daily_sales_' . $date . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output Excel content
echo "<table border='1'>";
echo "<tr>";
echo "<th>Date/Time</th>";
echo "<th>Reference #</th>";
echo "<th>Patient Name</th>";
echo "<th>Service</th>";
echo "<th>Category</th>";
echo "<th>Amount</th>";
echo "<th>Discount</th>";
echo "<th>Net Amount</th>";
echo "<th>Payment Method</th>";
echo "<th>Status</th>";
echo "<th>Staff</th>";
echo "</tr>";

foreach ($transactions as $transaction) {
    $refNum = $transaction['reference_number'] ?? 'TXN-' . $transaction['id'];
    $amount = (float)$transaction['amount'];
    $discount = (float)($transaction['discount_amount'] ?? 0);
    $netAmount = $amount - $discount;
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($transaction['transaction_date']) . "</td>";
    echo "<td>" . htmlspecialchars($refNum) . "</td>";
    echo "<td>" . htmlspecialchars($transaction['patient_name']) . "</td>";
    echo "<td>" . htmlspecialchars($transaction['fee_name'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($transaction['category_name'] ?? 'N/A') . "</td>";
    echo "<td>" . number_format($amount, 2) . "</td>";
    echo "<td>" . number_format($discount, 2) . "</td>";
    echo "<td>" . number_format($netAmount, 2) . "</td>";
    echo "<td>" . htmlspecialchars($transaction['payment_method']) . "</td>";
    echo "<td>" . htmlspecialchars($transaction['payment_status']) . "</td>";
    echo "<td>" . htmlspecialchars($transaction['staff_name']) . "</td>";
    echo "</tr>";
}

echo "</table>";

$stmt->close();
$conn->close();
?>

