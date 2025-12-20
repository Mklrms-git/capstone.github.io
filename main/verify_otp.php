<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';

$error_message = '';
$success_message = '';

// Check if email is in session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_type'])) {
    header('Location: forgot_password.php');
    exit();
}

$email = $_SESSION['reset_email'];
$user_type = $_SESSION['reset_user_type'];

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $error_message = "Please enter the OTP code.";
    } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
        $error_message = "OTP must be a 6-digit number.";
    } else {
        // Verify OTP
        $stmt = $conn->prepare("SELECT * FROM password_reset_otp 
                                WHERE email = ? AND otp_code = ? AND user_type = ? AND used = 0 AND expires_at > NOW() 
                                ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("sss", $email, $otp, $user_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $otp_record = $result->fetch_assoc();
            
            // Mark OTP as used
            $update_stmt = $conn->prepare("UPDATE password_reset_otp SET used = 1 WHERE id = ?");
            $update_stmt->bind_param("i", $otp_record['id']);
            $update_stmt->execute();
            
            // Store verification token in session
            $_SESSION['otp_verified'] = true;
            $_SESSION['reset_user_id'] = $otp_record['user_id'];
            // Username should already be in session from forgot_password.php, but ensure it's there
            if (!isset($_SESSION['reset_username'])) {
                // Fetch username from database
                if ($user_type === 'admin') {
                    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                } else {
                    $user_stmt = $conn->prepare("SELECT username FROM patient_users WHERE id = ?");
                }
                $user_stmt->bind_param("i", $otp_record['user_id']);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                if ($user_result->num_rows === 1) {
                    $user_data = $user_result->fetch_assoc();
                    $_SESSION['reset_username'] = $user_data['username'];
                }
            }
            
            // Redirect to password reset page
            header('Location: reset_password.php');
            exit();
        } else {
            $error_message = "Invalid or expired OTP code. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Mhavis Medical & Diagnostic Center</title>
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
                <a href="forgot_password.php" class="btn btn-outline-primary" style="border-radius: 20px;">
                    <i class="bi bi-arrow-left"></i> Back
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
                            <h4>Verify OTP</h4>
                            <p class="text-muted">Enter the 6-digit code sent to<br><strong><?php echo htmlspecialchars($email); ?></strong></p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">OTP Code</label>
                                <input type="text" name="otp" class="form-control text-center" required 
                                       maxlength="6" pattern="[0-9]{6}" 
                                       placeholder="000000"
                                       style="font-size: 24px; letter-spacing: 5px; font-weight: bold;">
                                <div class="form-text">Enter the 6-digit code from your email</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
                        </form>

                        <div class="text-center mt-4">
                            <a href="forgot_password.php" class="text-muted">Didn't receive the code? Resend OTP</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation and auto-focus
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
        
        // Auto-focus on OTP input
        document.querySelector('input[name="otp"]').focus();
        
        // Auto-advance on input (optional UX enhancement)
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    })()
    </script>
</body>
</html>

