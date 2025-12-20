<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';

// This script can be run directly or via web interface
// It will update the admin user with the specified information

$conn = getDBConnection();

// Admin user ID (usually 17 based on the database)
$adminId = 17;

// Get current admin info
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, address, username FROM users WHERE id = ? AND role = 'Admin'");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Admin user not found! Please check the admin ID.");
}

// Configuration - UPDATE THESE VALUES
$newPhone = '+639123456789';  // Change this to the actual admin phone number
$newAddress = 'LANGKAAN I, DASMARIÑAS CITY, CAVITE, REGION IV-A';  // Change this to the actual admin address

// If running via web with POST data, use those values
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $newPhone = !empty($_POST['phone']) ? normalizePhoneNumber($_POST['phone']) : $newPhone;
    $newAddress = !empty($_POST['address']) ? sanitize($_POST['address']) : $newAddress;
}

// Validate phone number
if (!empty($newPhone) && !preg_match('/^\+63\d{10}$/', $newPhone)) {
    // Try to normalize it
    $newPhone = normalizePhoneNumber($newPhone);
}

// Update admin user
$stmt = $conn->prepare("UPDATE users SET phone = ?, address = ? WHERE id = ? AND role = 'Admin'");
$stmt->bind_param("ssi", $newPhone, $newAddress, $adminId);

$success = false;
$message = '';

if ($stmt->execute()) {
    $success = true;
    $message = "Admin user updated successfully!";
    
    // Get updated info
    $stmt_refresh = $conn->prepare("SELECT id, first_name, last_name, email, phone, address FROM users WHERE id = ?");
    $stmt_refresh->bind_param("i", $adminId);
    $stmt_refresh->execute();
    $updated = $stmt_refresh->get_result()->fetch_assoc();
    $stmt_refresh->close();
} else {
    $message = "Error updating admin: " . $stmt->error;
}

$stmt->close();

// If running via CLI
if (php_sapi_name() === 'cli') {
    if ($success) {
        echo "✅ SUCCESS: " . $message . "\n\n";
        echo "Updated Admin Information:\n";
        echo "ID: " . $updated['id'] . "\n";
        echo "Name: " . $updated['first_name'] . " " . $updated['last_name'] . "\n";
        echo "Email: " . $updated['email'] . "\n";
        echo "Phone: " . ($updated['phone'] ?: 'N/A') . "\n";
        echo "Address: " . ($updated['address'] ?: 'N/A') . "\n";
    } else {
        echo "❌ ERROR: " . $message . "\n";
    }
    exit;
}

// Web interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Admin User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .admin-card {
            max-width: 600px;
            margin: 50px auto;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-card">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-user-shield me-2"></i>Update Admin User</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php else: ?>
                        <?php if ($message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Current Admin Info -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Current Admin Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-bordered mb-0">
                                <tr>
                                    <td width="30%"><strong>ID</strong></td>
                                    <td><?php echo htmlspecialchars($admin['id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Username</strong></td>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Name</strong></td>
                                    <td><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email</strong></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Phone</strong></td>
                                    <td>
                                        <?php if (empty($admin['phone'])): ?>
                                            <span class="badge bg-danger status-badge">MISSING</span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars(formatPhoneNumber($admin['phone'])); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Address</strong></td>
                                    <td>
                                        <?php if (empty($admin['address'])): ?>
                                            <span class="badge bg-danger status-badge">MISSING</span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($admin['address']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Update Form -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Update Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-phone me-2"></i>Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars(phoneToInputFormat($admin['phone'] ?: $newPhone)); ?>" 
                                           pattern="^(\+63|0)\d{9,10}$" 
                                           placeholder="09123456789 or +639123456789" 
                                           required>
                                    <small class="text-muted">Format: 09123456789 or +639123456789</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-map-marker-alt me-2"></i>Address <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" name="address" rows="3" 
                                              placeholder="Barangay, City, Province, Region" 
                                              required><?php echo htmlspecialchars($admin['address'] ?: $newAddress); ?></textarea>
                                    <small class="text-muted">Format: Barangay, City/Municipality, Province, Region</small>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Note:</strong> This will update the admin user (ID: <?php echo $adminId; ?>) in the database.
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Update Admin User
                                    </button>
                                    <a href="profile.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Profile
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($success && isset($updated)): ?>
                        <div class="card mt-3 border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Updated Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-bordered mb-0">
                                    <tr>
                                        <td width="30%"><strong>Phone</strong></td>
                                        <td class="text-success">
                                            <strong><?php echo htmlspecialchars(formatPhoneNumber($updated['phone'])); ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Address</strong></td>
                                        <td class="text-success">
                                            <strong><?php echo htmlspecialchars($updated['address']); ?></strong>
                                        </td>
                                    </tr>
                                </table>
                                <div class="mt-3">
                                    <a href="profile.php" class="btn btn-success">
                                        <i class="fas fa-check me-2"></i>View Updated Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



