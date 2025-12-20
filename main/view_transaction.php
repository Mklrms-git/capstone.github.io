<?php
define('MHAVIS_EXEC', true);
$page_title = "View Transaction";
$active_page = "daily_sales";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

// Check if page is loaded in iframe
$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';

// Get transaction ID from URL
$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if transaction_items table exists
$checkTransactionItems = $conn->query("SHOW TABLES LIKE 'transaction_items'");
$hasTransactionItems = $checkTransactionItems->num_rows > 0;

// Get transaction details
$query = "SELECT t.*,
          CONCAT(p.first_name, ' ', p.last_name) as patient_name,
          CONCAT(u.first_name, ' ', u.last_name) as staff_name,
          CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
          a.appointment_date,
          a.appointment_time
          FROM transactions t
          JOIN patients p ON t.patient_id = p.id
          JOIN users u ON t.created_by = u.id
          LEFT JOIN appointments a ON t.appointment_id = a.id
          LEFT JOIN users d ON a.doctor_id = d.id
          WHERE t.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $transactionId);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    $redirectUrl = "daily_sales.php?error=Transaction not found";
    if ($is_iframe) {
        $redirectUrl .= "&iframe=1";
    }
    header("Location: $redirectUrl");
    exit;
}

// Get all services for this transaction
$services = [];
if ($hasTransactionItems) {
    // Get services from transaction_items
    // Check if fee_name column exists in transaction_items
    $checkFeeName = $conn->query("SHOW COLUMNS FROM transaction_items LIKE 'fee_name'");
    $hasFeeNameColumn = $checkFeeName->num_rows > 0;
    
    if ($hasFeeNameColumn) {
        $itemsQuery = "SELECT ti.*, COALESCE(NULLIF(ti.fee_name, ''), f.name) as fee_name, fc.name as category_name
                       FROM transaction_items ti
                       LEFT JOIN fees f ON ti.fee_id = f.id
                       LEFT JOIN fee_categories fc ON f.category_id = fc.id
                       WHERE ti.transaction_id = ?
                       ORDER BY ti.id ASC";
    } else {
        $itemsQuery = "SELECT ti.*, f.name as fee_name, fc.name as category_name
                       FROM transaction_items ti
                       LEFT JOIN fees f ON ti.fee_id = f.id
                       LEFT JOIN fee_categories fc ON f.category_id = fc.id
                       WHERE ti.transaction_id = ?
                       ORDER BY ti.id ASC";
    }
    
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param("i", $transactionId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    while ($item = $itemsResult->fetch_assoc()) {
        $feeName = !empty($item['fee_name']) ? $item['fee_name'] : 'Service';
        $services[] = [
            'name' => $feeName,
            'category' => !empty($item['category_name']) ? $item['category_name'] : '',
            'quantity' => isset($item['quantity']) ? $item['quantity'] : 1,
            'unit_price' => isset($item['unit_price']) ? $item['unit_price'] : null,
            'total_price' => isset($item['total_price']) ? $item['total_price'] : null
        ];
    }
}

// If no services found in transaction_items, get from transaction's fee_id
if (empty($services) && $transaction['fee_id']) {
    $feeQuery = "SELECT f.name as fee_name, fc.name as category_name
                 FROM fees f
                 LEFT JOIN fee_categories fc ON f.category_id = fc.id
                 WHERE f.id = ?";
    $feeStmt = $conn->prepare($feeQuery);
    $feeStmt->bind_param("i", $transaction['fee_id']);
    $feeStmt->execute();
    $feeResult = $feeStmt->get_result();
    
    if ($fee = $feeResult->fetch_assoc()) {
        $services[] = [
            'name' => $fee['fee_name'],
            'category' => $fee['category_name'] ?: '',
            'quantity' => 1,
            'unit_price' => null,
            'total_price' => null
        ];
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Transaction Details</h4>
        <div>
            <a href="print_receipt.php?id=<?php echo $transactionId; ?>" class="btn btn-secondary me-2" target="_blank">
                <i class="fas fa-print"></i> Print Receipt
            </a>
            <a href="daily_sales.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Daily Sales
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Transaction saved successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Transaction Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Transaction Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold">Transaction ID</label>
                            <p class="mb-0">#<?php echo str_pad($transaction['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Date & Time</label>
                            <p class="mb-0">
                                <?php echo date('F j, Y g:i A', strtotime($transaction['transaction_date'])); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Patient Name</label>
                            <p class="mb-0"><?php echo htmlspecialchars($transaction['patient_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Services</label>
                            <div class="mb-0">
                                <?php if (!empty($services)): ?>
                                    <?php foreach ($services as $service): ?>
                                        <div class="mb-2">
                                            <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                            <?php if (!empty($service['category'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    Category: <?php echo htmlspecialchars($service['category']); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if (isset($service['quantity']) && $service['quantity'] > 1): ?>
                                                <br>
                                                <small class="text-muted">
                                                    Quantity: <?php echo $service['quantity']; ?>
                                                    <?php if (isset($service['unit_price'])): ?>
                                                        × <?php echo formatCurrency($service['unit_price']); ?>
                                                        = <?php echo formatCurrency($service['total_price']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php elseif (isset($service['total_price'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    Price: <?php echo formatCurrency($service['total_price']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="mb-0 text-muted">No services listed</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($transaction['appointment_id']): ?>
                            <div class="col-md-6">
                                <label class="fw-bold">Appointment</label>
                                <p class="mb-0">
                                    <?php 
                                    echo date('F j, Y', strtotime($transaction['appointment_date'])); ?>
                                    at 
                                    <?php echo date('g:i A', strtotime($transaction['appointment_time'])); ?>
                                    <br>
                                    <small class="text-muted">
                                        with Dr. <?php echo htmlspecialchars($transaction['doctor_name']); ?>
                                    </small>
                                </p>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="fw-bold">Staff</label>
                            <p class="mb-0"><?php echo htmlspecialchars($transaction['staff_name']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Card -->
            <?php if ($transaction['notes']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notes</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Payment Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="fw-bold">Amount</label>
                        <p class="mb-0"><?php echo formatCurrency($transaction['amount']); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Discount</label>
                        <p class="mb-0"><?php echo formatCurrency($transaction['discount_amount']); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Net Amount</label>
                        <p class="mb-0 h4 text-primary">
                            <?php echo formatCurrency($transaction['amount'] - $transaction['discount_amount']); ?>
                        </p>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="fw-bold">Payment Method</label>
                        <p class="mb-0">
                            <span class="badge bg-info"><?php echo $transaction['payment_method']; ?></span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Payment Status</label>
                        <p class="mb-0">
                            <span class="badge bg-<?php 
                                echo $transaction['payment_status'] === 'Completed' ? 'success' : 
                                    ($transaction['payment_status'] === 'Pending' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo $transaction['payment_status']; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($transaction['payment_status'] === 'Pending'): ?>
                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                           class="btn btn-warning w-100 mb-2">
                            <i class="fas fa-edit"></i> Edit Transaction
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 