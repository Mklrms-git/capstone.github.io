<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'process_notifications.php';

// Ensure password_reset_otp table exists
$table_check = $conn->query("SHOW TABLES LIKE 'password_reset_otp'");
if ($table_check->num_rows == 0) {
    // Create the table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS password_reset_otp (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        otp_code VARCHAR(6) NOT NULL,
        user_type ENUM('admin', 'patient') NOT NULL,
        user_id INT NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_otp (email, otp_code),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($create_table_sql);
}

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username)) {
        $error_message = "Please enter your username or patient number.";
    } elseif (empty($email)) {
        $error_message = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Check if username (can be username or patient_number) and email match the same account
        // First try matching by username in patient_users
        $stmt = $conn->prepare("SELECT pu.id, pu.username, p.first_name, p.last_name, pu.email, p.patient_number 
                                FROM patient_users pu 
                                JOIN patients p ON pu.patient_id = p.id 
                                WHERE pu.username = ? AND pu.email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // If not found by username, try matching by patient_number
        if ($result->num_rows === 0) {
            $stmt = $conn->prepare("SELECT pu.id, pu.username, p.first_name, p.last_name, pu.email, p.patient_number 
                                    FROM patient_users pu 
                                    JOIN patients p ON pu.patient_id = p.id 
                                    WHERE p.patient_number = ? AND pu.email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate 6-digit OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Set expiration time (15 minutes from now)
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store OTP in database
            $stmt = $conn->prepare("INSERT INTO password_reset_otp (email, otp_code, user_type, user_id, expires_at) VALUES (?, ?, 'patient', ?, ?)");
            $stmt->bind_param("ssis", $email, $otp, $user['id'], $expires_at);
            
            if ($stmt->execute()) {
                // Send OTP via email
                $subject = "Password Reset OTP - Mhavis Medical Center";
                $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #1218a5; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background-color: #f9f9f9; }
                        .otp-box { background-color: #fff; border: 2px solid #1218a5; padding: 20px; text-align: center; margin: 20px 0; }
                        .otp-code { font-size: 32px; font-weight: bold; color: #1218a5; letter-spacing: 5px; }
                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                        .warning { color: #d9534f; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Mhavis Medical & Diagnostic Center</h2>
                        </div>
                        <div class='content'>
                            <p>Dear {$user['first_name']} {$user['last_name']},</p>
                            <p>You have requested to reset your password for username: <strong>{$user['username']}</strong>. Please use the following OTP code to proceed:</p>
                            <div class='otp-box'>
                                <div class='otp-code'>{$otp}</div>
                            </div>
                            <p class='warning'>This OTP will expire in 15 minutes.</p>
                            <p>If you did not request this password reset, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>Mhavis Medical & Diagnostic Center Indang Cavite</p>
                            <p>This is an automated message. Please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                if (sendEmail($email, $user['first_name'] . ' ' . $user['last_name'], $subject, $email_body, 'html')) {
                    $success_message = "An OTP code has been sent to your email address. Please check your inbox.";
                    // Store email and username in session for next step
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_username'] = $user['username'];
                    $_SESSION['reset_user_type'] = 'patient';
                } else {
                    $error_message = "Failed to send OTP email. Please try again later.";
                }
            } else {
                $error_message = "An error occurred. Please try again.";
            }
        } else {
            // Don't reveal which field is incorrect (security best practice)
            $error_message = "Invalid username/patient number or email address. Please verify your credentials and try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Patient Portal - Mhavis Medical & Diagnostic Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
</head>
<body style="background: url('img/bg2.jpg') no-repeat center center fixed; background-size: cover;">

    <!-- Header with Home Button -->
    <nav class="navbar navbar-expand-lg navbar-light" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 1rem 0;">
        <div class="container">
            <a class="navbar-brand" href="index.php" style="font-weight: 600; color: #1218a5;">
                <img src="img/logo2.jpeg" alt="Mhavis Logo" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;">
                Mhavis Medical Center
            </a>
            <div class="navbar-nav ms-auto">
                <a href="patient_login.php" class="btn btn-outline-primary" style="border-radius: 20px;">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <img src="img/logo2.jpeg" alt="Mhavis Logo" class="mb-3" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                            <h4>Forgot Password</h4>
                            <h5 class="text-muted">Patient Portal</h5>
                            <p class="text-muted">Enter your username/patient number and email address to receive an OTP code</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                                <?php if (isset($_SESSION['reset_email'])): ?>
                                    <hr>
                                    <a href="patient_verify_otp.php" class="btn btn-primary w-100">Continue to OTP Verification</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!isset($_SESSION['reset_email']) || empty($success_message)): ?>
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Username or Patient Number</label>
                                <input type="text" name="username" class="form-control" required 
                                       value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                                       placeholder="Enter your username or patient number">
                                <div class="form-text">You can use your username or patient number (e.g., PT-2025-00009)</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" required 
                                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                                       placeholder="Enter your registered email">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Send OTP</button>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="patient_login.php" class="text-muted">Remember your password? Login here</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html>

