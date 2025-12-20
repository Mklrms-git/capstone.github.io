<?php
define('MHAVIS_EXEC', true);
$page_title = "Add Transaction";
$active_page = "daily_sales";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

// Function to check if column exists
function checkColumnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result->num_rows > 0;
}

// Function to get valid ENUM values from database
function getEnumValues($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $type = $row['Type'];
        if (preg_match("/^enum\((.+)\)$/", $type, $matches)) {
            $enum_values = [];
            $values = str_getcsv($matches[1], ',', "'");
            return $values;
        }
    }
    return [];
}

// Check if page is loaded in iframe (check both GET and POST)
$is_iframe = (isset($_GET['iframe']) && $_GET['iframe'] == '1') || 
             (isset($_POST['iframe']) && $_POST['iframe'] == '1');

// Get return date for navigation (check both GET and POST)
$returnDate = isset($_GET['date']) ? sanitize($_GET['date']) : 
              (isset($_POST['date']) ? sanitize($_POST['date']) : date('Y-m-d'));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $selectedFees = $_POST['selected_fees'] ?? [];
    $department = sanitize($_POST['department']); // OPD, ER, INWARD
    $totalAmount = (float)$_POST['total_amount'];
    $discountType = sanitize($_POST['discount_type'] ?? 'none');
    $discountAmount = (float)($_POST['discount_amount'] ?? 0);
    $netAmount = $totalAmount - $discountAmount;
    $paymentMethod = sanitize($_POST['payment_method']);
    $paymentStatus = sanitize($_POST['payment_status']);
    $notes = sanitize($_POST['notes']);
    $userId = $_SESSION['user_id'];

    // Clean and validate payment data - remove any hidden characters
    $paymentStatus = trim($paymentStatus);
    $paymentMethod = trim($paymentMethod);
    
    // Based on your database structure, these are the exact valid values
    $validPaymentStatuses = ['Pending', 'Completed', 'Refunded']; // From ENUM
    $validPaymentMethods = ['Cash', 'Gcash']; // From ENUM
    
    // Add debugging
    error_log("Valid Payment Statuses from DB: " . implode(', ', $validPaymentStatuses));
    error_log("Valid Payment Methods from DB: " . implode(', ', $validPaymentMethods));
    error_log("Payment Status received: '" . $paymentStatus . "'");
    error_log("Payment Method received: '" . $paymentMethod . "'");
    
    // Check if the submitted values match any valid values (case-insensitive)
    $matchedPaymentStatus = null;
    $matchedPaymentMethod = null;
    
    foreach ($validPaymentStatuses as $validStatus) {
        if (strcasecmp($validStatus, $paymentStatus) === 0) {
            $matchedPaymentStatus = $validStatus;
            break;
        }
    }
    
    foreach ($validPaymentMethods as $validMethod) {
        if (strcasecmp($validMethod, $paymentMethod) === 0) {
            $matchedPaymentMethod = $validMethod;
            break;
        }
    }
    
    if ($matchedPaymentStatus === null) {
        error_log("Invalid payment status: '$paymentStatus'. Allowed values: " . implode(', ', $validPaymentStatuses));
        $error = "Invalid payment status: '$paymentStatus'. Allowed values: " . implode(', ', $validPaymentStatuses);
    } elseif ($matchedPaymentMethod === null) {
        error_log("Invalid payment method: '$paymentMethod'. Allowed values: " . implode(', ', $validPaymentMethods));
        $error = "Invalid payment method: '$paymentMethod'. Allowed values: " . implode(', ', $validPaymentMethods);
    } else {
        // Use the matched values (with correct case)
        $paymentStatus = $matchedPaymentStatus;
        $paymentMethod = $matchedPaymentMethod;
        
        error_log("Using matched values - Status: '$paymentStatus', Method: '$paymentMethod'");
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // For single fee transactions (compatible with daily_sales.php)
            $primaryFeeId = null;
            $primaryFeeAmount = 0;
            
            if (!empty($selectedFees)) {
                $firstFee = reset($selectedFees);
                $primaryFeeId = (int)$firstFee['fee_id'];
                $primaryFeeAmount = (float)$firstFee['total_price'];
            }

            // Insert main transaction record - let's be more explicit about the columns
            $stmt = $conn->prepare("INSERT INTO transactions (
                patient_id, fee_id, amount, discount_type, discount_amount, 
                payment_method, payment_status, notes, created_by, transaction_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("iidsdssis", 
                $patientId, $primaryFeeId, $totalAmount, $discountType, $discountAmount,
                $paymentMethod, $paymentStatus, $notes, $userId
            );

            if (!$stmt->execute()) {
                throw new Exception("Error creating transaction: " . $stmt->error);
            }

            $transactionId = $conn->insert_id;

            // Check if transaction_items table exists and insert items for detailed tracking
            $tableExists = $conn->query("SHOW TABLES LIKE 'transaction_items'")->num_rows > 0;
            
            if ($tableExists) {
                $itemStmt = $conn->prepare("INSERT INTO transaction_items (
                    transaction_id, fee_id, department, quantity, unit_price, total_price
                ) VALUES (?, ?, ?, ?, ?, ?)");

                foreach ($selectedFees as $feeData) {
                    $feeId = (int)$feeData['fee_id'];
                    $quantity = (int)$feeData['quantity'];
                    $unitPrice = (float)$feeData['unit_price'];
                    $totalPrice = $quantity * $unitPrice;

                    $itemStmt->bind_param("isisdd", 
                        $transactionId, $feeId, $department, $quantity, $unitPrice, $totalPrice
                    );

                    if (!$itemStmt->execute()) {
                        throw new Exception("Error adding transaction item: " . $itemStmt->error);
                    }

                    // Update fee usage statistics for analytics (if columns exist)
                    $hasUsageCount = checkColumnExists($conn, 'fees', 'usage_count');
                    $hasTotalRevenue = checkColumnExists($conn, 'fees', 'total_revenue');
                    
                    if ($hasUsageCount && $hasTotalRevenue) {
                        $updateFeeStats = $conn->prepare("UPDATE fees SET 
                            usage_count = COALESCE(usage_count, 0) + ?, 
                            total_revenue = COALESCE(total_revenue, 0) + ?
                            WHERE id = ?");
                        $updateFeeStats->bind_param("idi", $quantity, $totalPrice, $feeId);
                        $updateFeeStats->execute();
                    }
                }
            }

            $conn->commit();
            
            // Always redirect back to daily_sales.php when coming from there (iframe mode or return_to)
            // Check POST first (form submission), then GET (initial page load)
            $return_to = isset($_POST['return_to']) ? $_POST['return_to'] : 
                        (isset($_GET['return_to']) ? $_GET['return_to'] : '');
            
            // Check iframe from POST (form submission) or GET (initial load)
            $post_is_iframe = isset($_POST['iframe']) && $_POST['iframe'] == '1';
            $get_is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';
            $final_is_iframe = $post_is_iframe || $get_is_iframe;
            
            // Get date from POST (form submission) or GET (initial load) or use today
            $post_date = isset($_POST['date']) ? sanitize($_POST['date']) : null;
            $get_date = isset($_GET['date']) ? sanitize($_GET['date']) : null;
            $final_date = $post_date ?: ($get_date ?: date('Y-m-d'));
            
            // Validate date format
            if (empty($final_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $final_date)) {
                $final_date = date('Y-m-d');
            }
            
            if ($final_is_iframe || $return_to === 'daily_sales') {
                // Redirect back to daily_sales.php with date and iframe parameter
                // Always redirect to daily_sales.php when coming from daily revenue page
                // Use simple relative URL since both files are in the same directory
                $redirectUrl = "daily_sales.php?date=" . urlencode($final_date);
                if ($final_is_iframe) {
                    $redirectUrl .= "&iframe=1";
                }
                // Add success message
                $redirectUrl .= "&success=" . urlencode("Transaction added successfully");
            } else {
                // Default: redirect to view transaction page
                $redirectUrl = "view_transaction.php?id=" . (int)$transactionId . "&success=1";
            }
            
            // Ensure redirect URL is valid and properly formatted
            if (empty($redirectUrl)) {
                $redirectUrl = "daily_sales.php?date=" . urlencode(date('Y-m-d'));
                if ($final_is_iframe) {
                    $redirectUrl .= "&iframe=1";
                }
            }
            
            // Log for debugging
            error_log("Redirecting to: " . $redirectUrl);
            error_log("Final is_iframe: " . ($final_is_iframe ? 'true' : 'false'));
            error_log("Return to: " . $return_to);
            error_log("Final date: " . $final_date);
            error_log("POST iframe: " . (isset($_POST['iframe']) ? $_POST['iframe'] : 'not set'));
            error_log("GET iframe: " . (isset($_GET['iframe']) ? $_GET['iframe'] : 'not set'));
            
            // Ensure no output before header
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            header("Location: " . $redirectUrl);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
            error_log("Transaction error: " . $error);
        }
    }
}

// Check if is_active column exists in patients table
$hasPatientIsActive = checkColumnExists($conn, 'patients', 'is_active');

// Get all active patients with Senior/PWD status
if ($hasPatientIsActive) {
    $patientsQuery = "SELECT id, CONCAT(first_name, ' ', last_name) as name, phone, 
                             is_senior_citizen, senior_citizen_id, is_pwd, pwd_id
                     FROM patients 
                     WHERE is_active = 1
                     ORDER BY first_name, last_name";
} else {
    $patientsQuery = "SELECT id, CONCAT(first_name, ' ', last_name) as name, phone,
                             is_senior_citizen, senior_citizen_id, is_pwd, pwd_id
                     FROM patients 
                     ORDER BY first_name, last_name";
}
$patients = $conn->query($patientsQuery)->fetch_all(MYSQLI_ASSOC);

// Check if is_active column exists in fees table
$hasFeesIsActive = checkColumnExists($conn, 'fees', 'is_active');

// Get all active fees with categories and department-specific pricing
if ($hasFeesIsActive) {
    $feesQuery = "SELECT f.*, fc.name as category_name 
                  FROM fees f
                  JOIN fee_categories fc ON f.category_id = fc.id
                  WHERE f.is_active = 1
                  ORDER BY fc.name, f.name";
} else {
    $feesQuery = "SELECT f.*, fc.name as category_name 
                  FROM fees f
                  JOIN fee_categories fc ON f.category_id = fc.id
                  ORDER BY fc.name, f.name";
}
$fees = $conn->query($feesQuery)->fetch_all(MYSQLI_ASSOC);

// Get valid ENUM values for the form - these match your database exactly
$validPaymentStatusesForForm = ['Pending', 'Completed', 'Refunded'];
$validPaymentMethodsForForm = ['Cash', 'Gcash'];

include 'includes/header.php';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="btn-group">
            <a href="daily_sales.php?date=<?php echo $returnDate; ?><?php echo $is_iframe ? '&iframe=1' : ''; ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Quick Info Card -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Transaction Information</h6>
                    <p class="mb-0 opacity-75">
                        All transactions will be recorded for <?php echo date('F j, Y', strtotime($returnDate)); ?>. 
                        Make sure to select the appropriate department for correct pricing.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Transaction Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="transactionForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="return_to" value="daily_sales">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($returnDate); ?>">
                <?php if ($is_iframe): ?>
                    <input type="hidden" name="iframe" value="1">
                <?php endif; ?>
                <!-- Hidden inputs to ensure discount values are always submitted -->
                <input type="hidden" name="discount_type" id="discount_type_hidden" value="none">
                <input type="hidden" name="discount_amount" id="discount_amount_hidden" value="0">
                <div class="row g-3">
                    <!-- Patient Selection -->
                    <div class="col-md-8">
                        <label class="form-label">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" class="form-select" required id="patientSelect">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        data-phone="<?php echo htmlspecialchars(formatPhoneNumber($patient['phone'] ?? '')); ?>"
                                        data-senior="<?php echo $patient['is_senior_citizen'] ? '1' : '0'; ?>"
                                        data-senior-id="<?php echo htmlspecialchars($patient['senior_citizen_id'] ?? ''); ?>"
                                        data-pwd="<?php echo $patient['is_pwd'] ? '1' : '0'; ?>"
                                        data-pwd-id="<?php echo htmlspecialchars($patient['pwd_id'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($patient['name']); ?>
                                    <?php if ($patient['phone']): ?>
                                        - <?php echo htmlspecialchars(formatPhoneNumber($patient['phone'])); ?>
                                    <?php endif; ?>
                                    <?php if ($patient['is_senior_citizen']): ?>
                                        <span class="badge bg-warning text-dark ms-1">Senior</span>
                                    <?php endif; ?>
                                    <?php if ($patient['is_pwd']): ?>
                                        <span class="badge bg-info text-white ms-1">PWD</span>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <i class="fas fa-user-plus text-muted me-1"></i>
                            Can't find the patient? <a href="add_patient.php" target="_blank">Add new patient</a>
                        </div>
                        <!-- Patient Status Display -->
                        <div id="patientStatus" class="mt-2" style="display: none;">
                            <div class="alert alert-info py-2">
                                <i class="fas fa-info-circle me-2"></i>
                                <span id="patientStatusText"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Department Selection -->
                    <div class="col-md-4">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="department" id="department" class="form-select" required onchange="updateFeePrices()">
                            <option value="">Select Department</option>
                            <option value="OPD">Out Patient Department (OPD)</option>
                            <option value="ER">Emergency Room (ER)</option>
                            <option value="INWARD">In-Patient/Ward</option>
                        </select>
                        <div class="form-text">
                            <i class="fas fa-info-circle text-muted me-1"></i>
                            Department affects service pricing
                        </div>
                    </div>

                    <!-- Services/Fees Selection -->
                    <div class="col-12">
                        <label class="form-label">Services/Fees <span class="text-danger">*</span></label>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Select Services</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addServiceRow()">
                                    <i class="fas fa-plus"></i> Add Service
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="servicesContainer">
                                    <!-- Service rows will be added here dynamically -->
                                </div>
                                <div class="text-muted small mt-2">
                                    <i class="fas fa-info-circle"></i> Select department first to see appropriate pricing.
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

                    <!-- Discount Section -->
                    <div id="discountSection" class="col-md-6" style="display: none;">
                        <label class="form-label">Discount Type</label>
                        <select id="discount_type" class="form-select" onchange="calculateDiscount()">
                            <option value="none">No Discount</option>
                            <option value="pwd">PWD (20%)</option>
                            <option value="senior">Senior Citizen (20%)</option>
                        </select>
                    </div>

                    <div id="discountAmountSection" class="col-md-6" style="display: none;">
                        <label class="form-label">Discount Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" id="discount_amount" class="form-control" 
                                   step="0.01" min="0" value="0" readonly onchange="calculateNetAmount()">
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

                    <!-- Payment Method - Using database values -->
                    <div class="col-md-6">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="">Select Payment Method</option>
                            <?php foreach ($validPaymentMethodsForForm as $method): ?>
                                <option value="<?php echo htmlspecialchars($method); ?>">
                                    <?php echo ucfirst(htmlspecialchars($method)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Payment Status - Using database values -->
                    <div class="col-md-6">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select" required>
                            <option value="">Select Payment Status</option>
                            <?php foreach ($validPaymentStatusesForForm as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" 
                                        <?php echo (strtolower($status) === 'completed') ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes or comments about this transaction"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="col-12">
                        <hr>
                        <div class="d-flex gap-2 justify-content-between">
                            <div>
                                <button type="submit" class="btn btn-success" id="submitBtn">
                                    <i class="fas fa-save"></i> Save Transaction
                            </button>
                                <a href="daily_sales.php?date=<?php echo $returnDate; ?><?php echo $is_iframe ? '&iframe=1' : ''; ?>" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Fee data for JavaScript -->
<script>
const feesData = <?php echo json_encode($fees); ?>;
let serviceRowCounter = 0;
let saveAndContinue = false;

function addServiceRow() {
    const department = document.getElementById('department').value;
    if (!department) {
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
        
        // Get department-specific price - fallback to 'amount' if department-specific columns don't exist
        let price = 0;
        switch(department) {
            case 'OPD': 
                price = fee.opd_amount || fee.amount || 0; 
                break;
            case 'ER': 
                price = fee.er_amount || fee.amount || 0; 
                break;
            case 'INWARD': 
                price = fee.inward_amount || fee.amount || 0; 
                break;
            default:
                price = fee.amount || 0;
        }
        
        feeOptions += `<option value="${fee.id}" data-amount="${price}">${fee.name} (₱${parseFloat(price).toFixed(2)})</option>`;
    });
    if (currentCategory !== '') feeOptions += '</optgroup>';
    
    row.innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Service/Fee</label>
                <select name="selected_fees[${serviceRowCounter}][fee_id]" class="form-select service-select" 
                        onchange="updateServicePrice(${serviceRowCounter})" required>
                    ${feeOptions}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Quantity</label>
                <input type="number" name="selected_fees[${serviceRowCounter}][quantity]" 
                       class="form-control quantity-input" value="1" min="1" 
                       onchange="updateServiceTotal(${serviceRowCounter})" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Unit Price</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" name="selected_fees[${serviceRowCounter}][unit_price]" 
                           class="form-control unit-price" step="0.01" readonly>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Total</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" name="selected_fees[${serviceRowCounter}][total_price]" 
                           class="form-control service-total" step="0.01" readonly>
                    <button type="button" class="btn btn-outline-danger" onclick="removeServiceRow(${serviceRowCounter})" title="Remove Service">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(row);
    updateSubmitButton();
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
        updateSubmitButton();
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
    const discountTypeHidden = document.getElementById('discount_type_hidden');
    const discountAmountHidden = document.getElementById('discount_amount_hidden');
    
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
    
    discountAmountField.value = discount.toFixed(2);
    
    // Sync with hidden inputs for form submission
    if (discountTypeHidden) discountTypeHidden.value = discountType;
    if (discountAmountHidden) discountAmountHidden.value = discount.toFixed(2);
    
    calculateNetAmount();
}

function calculateNetAmount() {
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
    const netAmount = Math.max(0, totalAmount - discountAmount);
    
    document.getElementById('net_amount').value = netAmount.toFixed(2);
}

function updateSubmitButton() {
    const serviceRows = document.querySelectorAll('.service-row');
    const submitBtn = document.getElementById('submitBtn');
    const saveAnotherBtn = document.getElementById('saveAnotherBtn');
    const department = document.getElementById('department').value;
    const patient = document.querySelector('select[name="patient_id"]').value;
    
    // Enable buttons if we have: patient selected, department selected, and at least one service
    const hasServices = serviceRows.length > 0;
    const hasValidServices = Array.from(serviceRows).every(row => {
        const select = row.querySelector('.service-select');
        return select && select.value !== '';
    });
    
    if (department && patient && hasServices && hasValidServices) {
        submitBtn.disabled = false;
        saveAnotherBtn.disabled = false;
        submitBtn.classList.remove('btn-secondary');
        submitBtn.classList.add('btn-primary');
        saveAnotherBtn.classList.remove('btn-secondary');
        saveAnotherBtn.classList.add('btn-success');
    } else {
        submitBtn.disabled = true;
        saveAnotherBtn.disabled = true;
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-secondary');
        saveAnotherBtn.classList.remove('btn-success');
        saveAnotherBtn.classList.add('btn-secondary');
    }
}

function saveAndAddAnother() {
    saveAndContinue = true;
    document.getElementById('transactionForm').submit();
}

function updatePatientStatus() {
    const patientSelect = document.getElementById('patientSelect');
    const patientStatus = document.getElementById('patientStatus');
    const patientStatusText = document.getElementById('patientStatusText');
    
    if (patientSelect.value) {
        const selectedOption = patientSelect.options[patientSelect.selectedIndex];
        const isSenior = selectedOption.dataset.senior === '1';
        const seniorId = selectedOption.dataset.seniorId;
        const isPwd = selectedOption.dataset.pwd === '1';
        const pwdId = selectedOption.dataset.pwdId;
        
        let statusText = '';
        if (isSenior && isPwd) {
            statusText = `Senior Citizen (ID: ${seniorId}) and PWD (ID: ${pwdId}) - Both discounts will be applied automatically`;
        } else if (isSenior) {
            statusText = `Senior Citizen (ID: ${seniorId}) - 20% discount will be applied automatically`;
        } else if (isPwd) {
            statusText = `PWD (ID: ${pwdId}) - 20% discount will be applied automatically`;
        } else {
            patientStatus.style.display = 'none';
            return;
        }
        
        patientStatusText.textContent = statusText;
        patientStatus.style.display = 'block';
    } else {
        patientStatus.style.display = 'none';
    }
}

function applyAutomaticDiscount() {
    const patientSelect = document.getElementById('patientSelect');
    const discountTypeSelect = document.getElementById('discount_type');
    const discountSection = document.getElementById('discountSection');
    const discountAmountSection = document.getElementById('discountAmountSection');
    const discountTypeHidden = document.getElementById('discount_type_hidden');
    const discountAmountHidden = document.getElementById('discount_amount_hidden');
    
    if (patientSelect.value) {
        const selectedOption = patientSelect.options[patientSelect.selectedIndex];
        const isSenior = selectedOption.dataset.senior === '1';
        const isPwd = selectedOption.dataset.pwd === '1';
        
        if (isSenior || isPwd) {
            // Show discount fields
            discountSection.style.display = 'block';
            discountAmountSection.style.display = 'block';
            
            // Apply appropriate discount
            if (isSenior && isPwd) {
                // If both Senior and PWD, apply Senior discount (higher priority)
                discountTypeSelect.value = 'senior';
            } else if (isSenior) {
                discountTypeSelect.value = 'senior';
            } else if (isPwd) {
                discountTypeSelect.value = 'pwd';
            }
        } else {
            // Hide discount fields if patient is not Senior or PWD
            discountSection.style.display = 'none';
            discountAmountSection.style.display = 'none';
            discountTypeSelect.value = 'none';
            document.getElementById('discount_amount').value = '0.00';
            
            // Sync hidden inputs
            if (discountTypeHidden) discountTypeHidden.value = 'none';
            if (discountAmountHidden) discountAmountHidden.value = '0.00';
        }
        
        // Recalculate discount and net amount
        calculateDiscount();
    } else {
        // No patient selected, hide discount fields
        discountSection.style.display = 'none';
        discountAmountSection.style.display = 'none';
        discountTypeSelect.value = 'none';
        document.getElementById('discount_amount').value = '0.00';
        
        // Sync hidden inputs
        if (discountTypeHidden) discountTypeHidden.value = 'none';
        if (discountAmountHidden) discountAmountHidden.value = '0.00';
        
        calculateNetAmount();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for patient selection if available
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        $('#patientSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search and select patient...',
            allowClear: true
        });
        
        // Also handle Select2 change events
        $('#patientSelect').on('change', function() {
            updatePatientStatus();
            applyAutomaticDiscount();
            updateSubmitButton();
        });
    }
    
    // Initialize discount fields state
    applyAutomaticDiscount();
    
    // Update submit button when patient or department changes
    document.getElementById('patientSelect').addEventListener('change', function() {
        updatePatientStatus();
        applyAutomaticDiscount();
        updateSubmitButton();
    });
    
    document.getElementById('department').addEventListener('change', function() {
        updateSubmitButton();
        // Auto-add first service row when department is selected
        if (this.value && document.querySelectorAll('.service-row').length === 0) {
            addServiceRow();
        }
    });
    
    // Also update buttons when form fields change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('service-select') || 
            e.target.classList.contains('quantity-input')) {
            updateSubmitButton();
        }
    });
    
    // Handle custom discount input
    document.getElementById('discount_amount').addEventListener('input', function() {
        if (document.getElementById('discount_type').value === 'custom') {
            calculateNetAmount();
        }
    });

    // Form submission handling
    document.getElementById('transactionForm').addEventListener('submit', function(e) {
        if (saveAndContinue) {
            // Modify form action to redirect back to add transaction
            const form = this;
            const formData = new FormData(form);
            
            fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Clear form and reset
                    form.reset();
                    document.getElementById('servicesContainer').innerHTML = '';
                    serviceRowCounter = 0;
                    updateSubmitButton();
                    
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        Transaction saved successfully! You can add another transaction.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    form.parentNode.insertBefore(alert, form);
                    
                    // Reset flag
                    saveAndContinue = false;
                } else {
                    showAlert('Error saving transaction. Please try again.', 'Error', 'error');
                }
            })
            .catch(error => {
                showAlert('Error saving transaction. Please try again.', 'Error', 'error');
                console.error('Error:', error);
            });
            
            e.preventDefault();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            if (!document.getElementById('submitBtn').disabled) {
                document.getElementById('transactionForm').submit();
            }
        }
        
        // Ctrl + Enter to save and add another
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            if (!document.getElementById('saveAnotherBtn').disabled) {
                saveAndAddAnother();
            }
        }
    });
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
.quick-actions .btn {
    text-align: center;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
</style>

<?php include 'includes/footer.php'; ?>