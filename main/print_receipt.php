<?php
define('MHAVIS_EXEC', true);
$page_title = "Print Receipt";
require_once __DIR__ . '/config/init.php';
requireLogin();

$conn = getDBConnection();

// Get transaction ID from URL
$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transactionId) {
    die('Invalid transaction ID');
}

// Get transaction details with patient info
$query = "SELECT t.*,
          CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) as patient_name,
          p.phone as patient_phone,
          p.address as patient_address,
          CONCAT(u.first_name, ' ', u.last_name) as staff_name
          FROM transactions t
          JOIN patients p ON t.patient_id = p.id
          JOIN users u ON t.created_by = u.id
          WHERE t.id = ?";
          
// Ensure department is included - check if column exists
$checkDept = $conn->query("SHOW COLUMNS FROM transactions LIKE 'department'");
$hasDepartment = $checkDept->num_rows > 0;

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $transactionId);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    die('Transaction not found');
}

// Check if transaction_items table exists and get items
$tableExists = $conn->query("SHOW TABLES LIKE 'transaction_items'")->num_rows > 0;
$transactionItems = [];

if ($tableExists) {
    $itemsQuery = "SELECT ti.*, f.name as fee_name, fc.name as category_name
                   FROM transaction_items ti
                   JOIN fees f ON ti.fee_id = f.id
                   LEFT JOIN fee_categories fc ON f.category_id = fc.id
                   WHERE ti.transaction_id = ?
                   ORDER BY fc.name, f.name";
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param("i", $transactionId);
    $itemsStmt->execute();
    $transactionItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Clinic Information - Update these values as needed
$clinicName = "MHAVIS Medical Clinic";
$clinicAddress = "123 Medical Street, Health District";
$clinicCity = "Manila, Philippines";
$clinicPhone = "(02) 1234-5678";
$clinicEmail = "info@mhavisclinic.com";

// Calculate totals
$subtotal = $transaction['amount'];
$discountAmount = $transaction['discount_amount'] ?? 0;
$netAmount = $subtotal - $discountAmount;

// If transaction items exist, recalculate from items
if (!empty($transactionItems)) {
    $subtotal = 0;
    foreach ($transactionItems as $item) {
        $subtotal += $item['total_price'];
    }
    $netAmount = $subtotal - $discountAmount;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
    <title>Receipt #<?php echo str_pad($transactionId, 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 20px;
        }

        .receipt-container {
            max-width: 300px;
            margin: 0 auto;
            background: #fff;
            padding: 15px;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .clinic-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            display: block;
        }

        .clinic-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .clinic-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .clinic-info {
            font-size: 10px;
            line-height: 1.5;
            color: #333;
        }

        .receipt-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .receipt-section {
            margin-bottom: 15px;
        }

        .receipt-section-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }

        .receipt-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }

        .receipt-line label {
            font-weight: bold;
        }

        .receipt-line.value {
            text-align: right;
        }

        .items-table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
        }

        .items-table th {
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            padding: 5px 0;
            border-bottom: 1px solid #000;
        }

        .items-table td {
            font-size: 10px;
            padding: 3px 0;
        }

        .items-table .item-name {
            width: 60%;
        }

        .items-table .item-qty {
            width: 15%;
            text-align: center;
        }

        .items-table .item-price {
            width: 25%;
            text-align: right;
        }

        .items-table .item-total {
            text-align: right;
            font-weight: bold;
        }

        .totals-section {
            margin-top: 15px;
            border-top: 2px dashed #000;
            padding-top: 10px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 11px;
        }

        .total-line.grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            color: #666;
        }

        .payment-info {
            background: #f5f5f5;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
        }

        .payment-info .receipt-line {
            margin-bottom: 5px;
        }

        .discount-info {
            color: #d32f2f;
        }

        @media print {
            body {
                padding: 0;
            }

            .receipt-container {
                max-width: 100%;
                padding: 10px;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }

        @media screen {
            .print-button {
                text-align: center;
                margin-bottom: 20px;
            }

            .print-button button {
                background: #007bff;
                color: #fff;
                border: none;
                padding: 10px 20px;
                font-size: 14px;
                cursor: pointer;
                border-radius: 4px;
            }

            .print-button button:hover {
                background: #0056b3;
            }
        }
    </style>
</head>
<body>
    <div class="print-button no-print">
        <button onclick="window.print()">🖨️ Print Receipt</button>
    </div>

    <div class="receipt-container">
        <!-- Clinic Header -->
        <div class="receipt-header">
            <div class="clinic-logo">
                <?php if (file_exists(__DIR__ . '/img/logo.png')): ?>
                    <img src="img/logo.png" alt="Clinic Logo">
                <?php elseif (file_exists(__DIR__ . '/img/logo2.jpeg')): ?>
                    <img src="img/logo2.jpeg" alt="Clinic Logo">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; background: #007bff; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
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
        </div>

        <!-- Receipt Title -->
        <div class="receipt-title">Official Receipt</div>

        <!-- Transaction Details -->
        <div class="receipt-section">
            <div class="receipt-line">
                <span><strong>Receipt #:</strong></span>
                <span class="value"><?php echo str_pad($transactionId, 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="receipt-line">
                <span><strong>Date:</strong></span>
                <span class="value"><?php echo date('M d, Y h:i A', strtotime($transaction['transaction_date'])); ?></span>
            </div>
            <?php if (!empty($transaction['department'])): ?>
                <?php 
                $deptName = '';
                switch(strtoupper($transaction['department'])) {
                    case 'OPD': $deptName = 'Outpatient Department'; break;
                    case 'ER': $deptName = 'Emergency Room'; break;
                    case 'INWARD': $deptName = 'In-Patient/Ward'; break;
                    default: $deptName = $transaction['department'];
                }
                ?>
                <div class="receipt-line">
                    <span><strong>Department:</strong></span>
                    <span class="value"><?php echo htmlspecialchars($deptName); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <!-- Patient Information -->
        <div class="receipt-section">
            <div class="receipt-section-title">Patient Information</div>
            <div class="receipt-line">
                <span><strong>Name:</strong></span>
                <span class="value" style="text-align: right; flex: 1; margin-right: 10px;"><?php echo htmlspecialchars($transaction['patient_name']); ?></span>
            </div>
            <?php if ($transaction['patient_phone']): ?>
            <div class="receipt-line">
                <span><strong>Contact:</strong></span>
                <span class="value"><?php echo htmlspecialchars(formatPhoneNumber($transaction['patient_phone'])); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <!-- Services/Items -->
        <div class="receipt-section">
            <div class="receipt-section-title">Services/Items</div>
            <?php if (!empty($transactionItems)): ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th class="item-name">Item</th>
                            <th class="item-qty">Qty</th>
                            <th class="item-price">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactionItems as $item): ?>
                            <tr>
                                <td class="item-name">
                                    <?php echo htmlspecialchars($item['fee_name']); ?>
                                    <?php if (!empty($item['category_name'])): ?>
                                        <br><small style="font-size: 8px; color: #666;"><?php echo htmlspecialchars($item['category_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="item-qty"><?php echo $item['quantity']; ?></td>
                                <td class="item-price"><?php echo formatCurrency($item['total_price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- Fallback to single fee if transaction_items doesn't exist -->
                <div class="receipt-line">
                    <span><?php 
                        // Try to get fee name from transaction
                        $feeQuery = "SELECT f.name FROM fees f JOIN transactions t ON f.id = t.fee_id WHERE t.id = ?";
                        $feeStmt = $conn->prepare($feeQuery);
                        $feeStmt->bind_param("i", $transactionId);
                        $feeStmt->execute();
                        $feeResult = $feeStmt->get_result();
                        if ($feeResult->num_rows > 0) {
                            $fee = $feeResult->fetch_assoc();
                            echo htmlspecialchars($fee['name']);
                        } else {
                            echo 'Service Fee';
                        }
                    ?></span>
                    <span class="value"><?php echo formatCurrency($subtotal); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <!-- Payment Summary -->
        <div class="receipt-section">
            <div class="receipt-section-title">Payment Summary</div>
            <div class="totals-section">
                <div class="total-line">
                    <span>Subtotal:</span>
                    <span><?php echo formatCurrency($subtotal); ?></span>
                </div>
                <?php if ($discountAmount > 0): ?>
                    <div class="total-line discount-info">
                        <span>Discount (<?php echo htmlspecialchars($transaction['discount_type'] ?? 'N/A'); ?>):</span>
                        <span>-<?php echo formatCurrency($discountAmount); ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-line grand-total">
                    <span>TOTAL:</span>
                    <span><?php echo formatCurrency($netAmount); ?></span>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Payment Information -->
        <div class="payment-info">
            <div class="receipt-section-title">Payment Details</div>
            <div class="receipt-line">
                <span><strong>Payment Method:</strong></span>
                <span class="value"><?php echo htmlspecialchars($transaction['payment_method']); ?></span>
            </div>
            <div class="receipt-line">
                <span><strong>Status:</strong></span>
                <span class="value"><?php echo htmlspecialchars($transaction['payment_status']); ?></span>
            </div>
            <?php if ($transaction['notes']): ?>
                <div class="receipt-line" style="margin-top: 5px;">
                    <span><strong>Notes:</strong></span>
                    <span class="value" style="text-align: left; flex: 1; margin-left: 10px; font-size: 9px;"><?php echo htmlspecialchars($transaction['notes']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Thank you for your visit!</div>
            <div style="margin-top: 5px;">This is a computer-generated receipt.</div>
            <div style="margin-top: 5px;">Processed by: <?php echo htmlspecialchars($transaction['staff_name']); ?></div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // Uncomment the line below if you want auto-print
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>

