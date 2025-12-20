<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';

$error_message = '';
$success_message = '';

// Check if OTP is verified
if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified'] || !isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_user_type']) || !isset($_SESSION['reset_username'])) {
    header('Location: patient_forgot_password.php');
    exit();
}

$user_id = $_SESSION['reset_user_id'];
$user_type = $_SESSION['reset_user_type'];
$username = $_SESSION['reset_username'];

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password)) {
        $error_message = "Please enter a new password.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Verify username matches before resetting password
        $verify_stmt = $conn->prepare("SELECT id FROM patient_users WHERE id = ? AND username = ?");
        $verify_stmt->bind_param("is", $user_id, $username);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 1) {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in patient_users table
            $stmt = $conn->prepare("UPDATE patient_users SET password = ? WHERE id = ? AND username = ?");
            $stmt->bind_param("sis", $hashed_password, $user_id, $username);
            
            if ($stmt->execute()) {
                // Clear session variables
                unset($_SESSION['otp_verified']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_username']);
                unset($_SESSION['reset_user_type']);
                
                $success_message = "Password reset successfully! You can now login with your new password.";
            } else {
                $error_message = "An error occurred. Please try again.";
            }
        } else {
            $error_message = "Account verification failed. Please start the password reset process again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Patient Portal - Mhavis Medical & Diagnostic Center</title>
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
                    <i class="bi bi-house-door"></i> Home
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
                            <h4>Reset Password</h4>
                            <h5 class="text-muted">Patient Portal</h5>
                            <p class="text-muted">Enter your new password</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                                <hr>
                                <a href="patient_login.php" class="btn btn-primary w-100">Go to Login</a>
                            </div>
                        <?php else: ?>
                        <form method="POST" class="needs-validation" novalidate id="resetForm">
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required 
                                       minlength="8" placeholder="Enter new password">
                                <div class="form-text">Password must be at least 8 characters long</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required 
                                       minlength="8" placeholder="Confirm new password">
                                <div class="invalid-feedback" id="passwordMatchFeedback"></div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                        </form>
                        <?php endif; ?>
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
        var form = document.getElementById('resetForm');
        if (!form) return;
        
        var newPassword = document.getElementById('new_password');
        var confirmPassword = document.getElementById('confirm_password');
        var passwordMatchFeedback = document.getElementById('passwordMatchFeedback');
        
        function validatePasswordMatch() {
            if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                confirmPassword.classList.add('is-invalid');
                if (passwordMatchFeedback) {
                    passwordMatchFeedback.textContent = 'Passwords do not match';
                }
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('is-invalid');
            }
        }
        
        newPassword.addEventListener('input', validatePasswordMatch);
        confirmPassword.addEventListener('input', validatePasswordMatch);
        
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })()
    </script>
</body>
</html>

