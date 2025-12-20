<?php
define('MHAVIS_EXEC', true);
$page_title = "Edit Transaction";
$active_page = "transactions";
require_once __DIR__ . '/config/init.php';
requireLogin();

// Get transaction ID
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transaction_id) {
    header('Location: daily_sales.php');
    exit();
}

$conn = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedFees = $_POST['selected_fees'] ?? [];
    $department = sanitize($_POST['department']);
    $totalAmount = (float)$_POST['total_amount'];
    $discountType = sanitize($_POST['discount_type']);
    $discountAmount = (float)$_POST['discount_amount'];
    $netAmount = $totalAmount - $discountAmount;
    $paymentMethod = sanitize($_POST['payment_method']);
    $paymentStatus = sanitize($_POST['payment_status']);
    $notes = sanitize($_POST['notes']);
    $userId = $_SESSION['user_id'];

    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get original transaction items for analytics rollback
        $originalItemsStmt = $conn->prepare("SELECT fee_id, quantity, total_price FROM transaction_items WHERE transaction_id = ?");
        $originalItemsStmt->bind_param("i", $transaction_id);
        $originalItemsStmt->execute();
        $originalItems = $originalItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Rollback analytics for original items
        foreach ($originalItems as $item) {
            $rollbackStats = $conn->prepare("UPDATE fees SET 
                usage_count = GREATEST(0, COALESCE(usage_count, 0) - ?), 
                total_revenue = GREATEST(0, COALESCE(total_revenue, 0) - ?)
                WHERE id = ?");
            $rollbackStats->bind_param("idi", $item['quantity'], $item['total_price'], $item['fee_id']);
            $rollbackStats->execute();
        }

        // Update primary fee for main transaction (compatibility with daily_sales.php)
        $primaryFeeId = null;
        if (!empty($selectedFees)) {
            $firstFee = reset($selectedFees);
            $primaryFeeId = (int)$firstFee['fee_id'];
        }

        // Update main transaction record
        $stmt = $conn->prepare("UPDATE transactions SET 
            fee_id = ?,
            amount = ?,
            discount_type = ?,
            discount_amount = ?,
            payment_method = ?,
            payment_status = ?,
            notes = ?,
            created_by = ?,
            updated_at = NOW()
            WHERE id = ?");

        $stmt->bind_param("idsdsssii", 
            $primaryFeeId, $totalAmount, $discountType, $discountAmount,
            $paymentMethod, $paymentStatus, $notes, $userId, $transaction_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Error updating transaction");
        }

        // Delete existing transaction items
        $deleteItems = $conn->prepare("DELETE FROM transaction_items WHERE transaction_id = ?");
        $deleteItems->bind_param("i", $transaction_id);
        $deleteItems->execute();

        // Insert new transaction items
        $itemStmt = $conn->prepare("INSERT INTO transaction_items (
            transaction_id, fee_id, department, quantity, unit_price, total_price
        ) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($selectedFees as $feeData) {
            $feeId = (int)$feeData['fee_id'];
            $quantity = (int)$feeData['quantity'];
            $unitPrice = (float)$feeData['unit_price'];
            $totalPrice = $quantity * $unitPrice;

            $itemStmt->bind_param("isisdd", 
                $transaction_id, $feeId, $department, $quantity, $unitPrice, $totalPrice
            );

            if (!$itemStmt->execute()) {
                throw new Exception("Error updating transaction item");
            }

            // Update fee usage statistics for analytics
            $updateFeeStats = $conn->prepare("UPDATE fees SET 
                usage_count = COALESCE(usage_count, 0) + ?, 
                total_revenue = COALESCE(total_revenue, 0) + ?
                WHERE id = ?");
            $updateFeeStats->bind_param("idi", $quantity, $totalPrice, $feeId);
            $updateFeeStats->execute();
        }

        $conn->commit();
        header("Location: view_transaction.php?id=$transaction_id&updated=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Get transaction details with items
$stmt = $conn->prepare("SELECT t.*, 
    p.first_name as patient_first_name, 
    p.last_name as patient_last_name,
    p.phone as patient_phone,
    u.first_name as staff_first_name,
    u.last_name as staff_last_name
    FROM transactions t
    JOIN patients p ON t.patient_id = p.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.id = ?");

$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    header('Location: daily_sales.php');
    exit();
}

// Get transaction items with department info
$itemsStmt = $conn->prepare("SELECT ti.*, f.name as fee_name, fc.name as category_name, 
    f.opd_amount, f.er_amount, f.inward_amount
    FROM transaction_items ti
    JOIN fees f ON ti.fee_id = f.id
    JOIN fee_categories fc ON f.category_id = fc.id
    WHERE ti.transaction_id = ?
    ORDER BY fc.name, f.name");
$itemsStmt->bind_param("i", $transaction_id);
$itemsStmt->execute();
$transactionItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Determine department from first item (if exists)
$currentDepartment = '';
if (!empty($transactionItems)) {
    $firstItem = $transactionItems[0];
    if (isset($firstItem['department'])) {
        $currentDepartment = $firstItem['department'];
    } else {
        // Try to determine from price comparison
        $unitPrice = $firstItem['unit_price'];
        if ($unitPrice == $firstItem['opd_amount']) $currentDepartment = 'OPD';
        elseif ($unitPrice == $firstItem['er_amount']) $currentDepartment = 'ER';
        elseif ($unitPrice == $firstItem['inward_amount']) $currentDepartment = 'INWARD';
    }
}

// Get all active fees for the form
$feesQuery = "SELECT f.*, fc.name as category_name 
              FROM fees f
              JOIN fee_categories fc ON f.category_id = fc.id
              WHERE f.is_active = 1
              ORDER BY fc.name, f.name";
$fees = $conn->query($feesQuery)->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit"></i> Edit Transaction</h2>
        <div>
            <a href="view_transaction.php?id=<?php echo $transaction_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View Transaction
            </a>
            <a href="daily_sales.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Transactions
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Transaction Info Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Transaction Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Transaction ID:</strong><br>
                    <span class="text-muted">#<?php echo str_pad($transaction['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="col-md-3">
                    <strong>Patient:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($transaction['patient_first_name'] . ' ' . $transaction['patient_last_name']); ?></span>
                    <?php if ($transaction['patient_phone']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars(formatPhoneNumber($transaction['patient_phone'])); ?></small>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <strong>Original Date:</strong><br>
                    <span class="text-muted"><?php echo date('M d, Y h:i A', strtotime($transaction['transaction_date'])); ?></span>
                </div>
                <div class="col-md-3">
                    <strong>Created By:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars(($transaction['staff_first_name'] ?? '') . ' ' . ($transaction['staff_last_name'] ?? '')); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Edit Transaction Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="editTransactionForm">
                <div class="row g-3">
                    <!-- Department Selection -->
                    <div class="col-md-12">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="department" id="department" class="form-select" required onchange="updateFeePrices()">
                            <option value="">Select Department</option>
                            <option value="OPD" <?php echo $currentDepartment === 'OPD' ? 'selected' : ''; ?>>Out Patient Department (OPD)</option>
                            <option value="ER" <?php echo $currentDepartment === 'ER' ? 'selected' : ''; ?>>Emergency Room (ER)</option>
                            <option value="INWARD" <?php echo $currentDepartment === 'INWARD' ? 'selected' : ''; ?>>In-Patient/Ward</option>
                        </select>
                    </div>

                    <!-- Services/Fees Section -->
                    <div class="col-12">
                        <label class="form-label">Services/Fees <span class="text-danger">*</span></label>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Selected Services</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addServiceRow()">
                                    <i class="fas fa-plus"></i> Add Service
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="servicesContainer">
                                    <!-- Existing services will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Summary -->
                    <div class="col-md-6">
                        <label class="form-label">Subtotal</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" id="subtotal" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Total Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="total_amount" id="total_amount" class="form-control" readonly>
                        </div>
                    </div>

                    <!-- Discount Type -->
                    <div class="col-md-6">
                        <label class="form-label">Discount Type</label>
                        <select name="discount_type" id="discount_type" class="form-select" onchange="calculateDiscount()" required>
                            <option value="none" <?php echo $transaction['discount_type'] === 'none' ? 'selected' : ''; ?>>No Discount</option>
                            <option value="pwd" <?php echo $transaction['discount_type'] === 'pwd' ? 'selected' : ''; ?>>PWD (20%)</option>
                            <option value="senior" <?php echo $transaction['discount_type'] === 'senior' ? 'selected' : ''; ?>>Senior Citizen (20%)</option>
                            <option value="employee" <?php echo $transaction['discount_type'] === 'employee' ? 'selected' : ''; ?>>Employee Discount (10%)</option>
                            <option value="custom" <?php echo $transaction['discount_type'] === 'custom' ? 'selected' : ''; ?>>Custom Discount</option>
                        </select>
                    </div>

                    <!-- Discount Amount -->
                    <div class="col-md-6">
                        <label class="form-label">Discount Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="discount_amount" id="discount_amount" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['discount_amount']; ?>" 
                                   onchange="calculateNetAmount()">
                        </div>
                    </div>

                    <!-- Net Amount -->
                    <div class="col-md-6">
                        <label class="form-label">Net Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" id="net_amount" class="form-control" readonly>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="col-md-6">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="Cash" <?php echo $transaction['payment_method'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Online" <?php echo $transaction['payment_method'] === 'Online' ? 'selected' : ''; ?>>GCash</option>
                            <option value="Card" <?php echo $transaction['payment_method'] === 'Card' ? 'selected' : ''; ?>>Credit/Debit Card</option>
                            <option value="Insurance" <?php echo $transaction['payment_method'] === 'Insurance' ? 'selected' : ''; ?>>Insurance</option>
                            <option value="Check" <?php echo $transaction['payment_method'] === 'Check' ? 'selected' : ''; ?>>Check</option>
                        </select>
                    </div>

                    <!-- Payment Status -->
                    <div class="col-md-6">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select" required>
                            <option value="Completed" <?php echo $transaction['payment_status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Pending" <?php echo $transaction['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Partial" <?php echo $transaction['payment_status'] === 'Partial' ? 'selected' : ''; ?>>Partial Payment</option>
                            <option value="Refunded" <?php echo $transaction['payment_status'] === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes or comments about this transaction"><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="col-12">
                        <hr>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Transaction
                            </button>
                            <a href="view_transaction.php?id=<?php echo $transaction_id; ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> View Transaction
                            </a>
                            <a href="daily_sales.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Fee data and existing items for JavaScript -->
<script>
const feesData = <?php echo json_encode($fees); ?>;
const existingItems = <?php echo json_encode($transactionItems); ?>;
let serviceRowCounter = 0;

function addServiceRow(existingData = null) {
    const department = document.getElementById('department').value;
    if (!department && !existingData) {
        alert('Please select a department first.');
        return;
    }

    serviceRowCounter++;
    const container = document.getElementById('servicesContainer');
    
    const row = document.createElement('div');
    row.className = 'service-row mb-3 p-3 border rounded';
    row.id = `service-row-${serviceRowCounter}`;
    
    let feeOptions = '<option value="">Select Service/Fee</option>';
    let currentCategory = '';
    
    feesData.forEach(fee => {
        if (currentCategory !== fee.category_name) {
            if (currentCategory !== '') feeOptions += '</optgroup>';
            currentCategory = fee.category_name;
            feeOptions += `<optgroup label="${fee.category_name}">`;
        }
        
        // Get department-specific price
        let price = 0;
        switch(department || (existingData ? existingData.department : '')) {
            case 'OPD': price = fee.opd_amount; break;
            case 'ER': price = fee.er_amount; break;
            case 'INWARD': price = fee.inward_amount; break;
        }
        
        const selected = existingData && existingData.fee_id == fee.id ? 'selected' : '';
        feeOptions += `<option value="${fee.id}" data-amount="${price}" ${selected}>${fee.name} (₱${parseFloat(price).toFixed(2)})</option>`;
    });
    if (currentCategory !== '') feeOptions += '</optgroup>';
    
    const quantity = existingData ? existingData.quantity : 1;
    const unitPrice = existingData ? existingData.unit_price : '';
    const total = existingData ? existingData.total_price : '';
    
    row.innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Service/Fee</label>
                <select name="selected_fees[${serviceRowCounter}][fee_id]" class="form-select service-select" 
                        onchange="updateServicePrice(${serviceRowCounter})" required>
                    ${feeOptions}
                </select>
                ${existingData ? `<small class="text-muted">Category: ${existingData.category_name}</small>` : ''}
            </div>
            <div class="col-md-2">
                <label class="form-label">Quantity</label>
                <input type="number" name="selected_fees[${serviceRowCounter}][quantity]" 
                       class="form-control quantity-input" value="${quantity}" min="1" 
                       onchange="updateServiceTotal(${serviceRowCounter})" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Unit Price</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" name="selected_fees[${serviceRowCounter}][unit_price]" 
                           class="form-control unit-price" step="0.01" value="${unitPrice}" readonly>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Total</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" name="selected_fees[${serviceRowCounter}][total_price]" 
                           class="form-control service-total" step="0.01" value="${total}" readonly>
                    <button type="button" class="btn btn-outline-danger" onclick="removeServiceRow(${serviceRowCounter})" title="Remove Service">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(row);
    
    // If this is existing data, trigger the price update
    if (existingData) {
        updateServicePrice(serviceRowCounter);
    }
}

function updateFeePrices() {
    // Update all existing service rows with new department pricing
    const serviceRows = document.querySelectorAll('.service-row');
    serviceRows.forEach(row => {
        const select = row.querySelector('.service-select');
        if (select.value) {
            const rowId = row.id.split('-')[2];
            updateServicePrice(rowId);
        }
    });
}

function removeServiceRow(rowId) {
    const row = document.getElementById(`service-row-${rowId}`);
    if (row) {
        row.remove();
        calculateTotalAmount();
    }
}

function updateServicePrice(rowId) {
    const row = document.getElementById(`service-row-${rowId}`);
    const select = row.querySelector('.service-select');
    const unitPriceInput = row.querySelector('.unit-price');
    
    if (select.value) {
        const selectedOption = select.options[select.selectedIndex];
        const price = selectedOption.dataset.amount;
        unitPriceInput.value = parseFloat(price).toFixed(2);
        updateServiceTotal(rowId);
    } else {
        unitPriceInput.value = '';
        updateServiceTotal(rowId);
    }
}

function updateServiceTotal(rowId) {
    const row = document.getElementById(`service-row-${rowId}`);
    const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
    const total = quantity * unitPrice;
    
    row.querySelector('.service-total').value = total.toFixed(2);
    calculateTotalAmount();
}

function calculateTotalAmount() {
    const serviceTotals = document.querySelectorAll('.service-total');
    let subtotal = 0;
    
    serviceTotals.forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('total_amount').value = subtotal.toFixed(2);
    
    calculateDiscount();
}

function calculateDiscount() {
    const discountType = document.getElementById('discount_type').value;
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    const discountAmountField = document.getElementById('discount_amount');
    
    let discount = 0;
    
    switch(discountType) {
        case 'pwd':
        case 'senior':
            discount = totalAmount * 0.20;
            discountAmountField.readOnly = true;
            break;
        case 'employee':
            discount = totalAmount * 0.10;
            discountAmountField.readOnly = true;
            break;
        case 'custom':
            discountAmountField.readOnly = false;
            discount = parseFloat(discountAmountField.value) || 0;
            break;
        default:
            discountAmountField.readOnly = true;
            discount = 0;
    }
    
    if (discountType !== 'custom') {
        discountAmountField.value = discount.toFixed(2);
    }
    
    calculateNetAmount();
}

function calculateNetAmount() {
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
    const netAmount = Math.max(0, totalAmount - discountAmount);
    
    document.getElementById('net_amount').value = netAmount.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    // Load existing transaction items
    existingItems.forEach(item => {
        addServiceRow(item);
    });
    
    // If no existing items, add empty row
    if (existingItems.length === 0) {
        addServiceRow();
    }
    
    // Calculate initial totals
    calculateTotalAmount();
    
    // Handle custom discount input
    document.getElementById('discount_amount').addEventListener('input', function() {
        if (document.getElementById('discount_type').value === 'custom') {
            calculateNetAmount();
        }
    });
    
    // Set initial discount field state
    const discountType = document.getElementById('discount_type').value;
    const discountAmountField = document.getElementById('discount_amount');
    
    if (discountType === 'custom') {
        discountAmountField.readOnly = false;
    } else {
        discountAmountField.readOnly = true;
    }
});
</script>

<style>
.service-row {
    background-color: #f8f9fa;
    transition: background-color 0.2s;
}
.service-row:hover {
    background-color: #e9ecef;
}
.btn-outline-danger {
    border-radius: 0 0.375rem 0.375rem 0;
}
</style>

<?php include 'includes/footer.php'; ?>