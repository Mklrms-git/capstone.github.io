<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';

// Login attempts tracking removed per user request

$error_message = '';
$success_message = '';
$error_type = 'danger'; // Default error type

// Show message if redirected from successful registration
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $patient_type = isset($_GET['type']) ? htmlspecialchars($_GET['type'], ENT_QUOTES, 'UTF-8') : '';
    
    if ($patient_type === 'Existing') {
        $success_message = "Registration request submitted successfully! An administrator will review your request and notify you via email once approved. Your Patient ID and password will be included in the approval email.";
    } else {
        // Default message for New patients
        $success_message = "Registration request submitted successfully! An administrator will review your request and notify you via email once approved. Your Patient ID and password will be included in the approval email.";
    }
}

// Show message if redirected due to deleted account
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $error_message = "Your account does not exist anymore. Please register a new account or contact Mhavis.";
    $error_type = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_identifier = htmlspecialchars(trim($_POST['patient_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';

    // Proceed with login attempt using Patient ID (patient_number) or numeric ID
        // Match by patient_number or internal numeric id
        $byNumber = preg_match('/^[A-Za-z0-9\-]+$/', $patient_identifier) === 1;
        if ($byNumber) {
            $stmt = $conn->prepare("SELECT pu.*, p.first_name, p.last_name, p.email, p.phone, p.patient_number 
                                    FROM patient_users pu 
                                    JOIN patients p ON pu.patient_id = p.id 
                                    WHERE p.patient_number = ?");
            $stmt->bind_param("s", $patient_identifier);
        } else {
            $numeric_id = (int)$patient_identifier;
            $stmt = $conn->prepare("SELECT pu.*, p.first_name, p.last_name, p.email, p.phone, p.patient_number 
                                    FROM patient_users pu 
                                    JOIN patients p ON pu.patient_id = p.id 
                                    WHERE p.id = ?");
            $stmt->bind_param("i", $numeric_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check account status
            if ($user['status'] === 'Pending') {
                // Account is pending approval
                $error_message = "Your registration is still pending approval. Please wait for the admin to approve your account before logging in.";
                $error_type = 'warning';
            } elseif ($user['status'] === 'Rejected') {
                // Account was rejected
                $error_message = "Your registration has been rejected. Please contact the administration for more information.";
                $error_type = 'danger';
            } elseif ($user['status'] === 'Suspended') {
                // Account is suspended
                $error_message = "Your account has been suspended. Please contact the administration for more information.";
                $error_type = 'danger';
            } elseif ($user['status'] === 'Active') {
                // Account is active, proceed with password verification
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['patient_user_id'] = $user['id'];
                    $_SESSION['patient_id'] = $user['patient_id'];
                    $_SESSION['username'] = $user['patient_number']; // store patient number for display
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['role'] = 'Patient';
                    $_SESSION['last_activity'] = time();
                    $_SESSION['token'] = bin2hex(random_bytes(32));
                    session_regenerate_id(true);

                    // Update last login
                    $stmt = $conn->prepare("UPDATE patient_users SET last_login = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();

                    // Redirect to patient dashboard
                    header('Location: patient_dashboard.php');
                    exit();
                } else {
                    // Password mismatch for active account
                    $error_message = "Invalid patient ID or password.";
                }
            } else {
                // Unknown status
                $error_message = "Your account status is unclear. Please contact the administration.";
            }
        } else {
            // No matching patient account found
            $error_message = "Invalid patient ID or password.";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login - Mhavis Medical & Diagnostic Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
    <style>
        /* Hide browser default password reveal icon */
        input[type="password"]::-webkit-credentials-auto-fill-button,
        input[type="password"]::-webkit-strong-password-auto-fill-button {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        
        /* Hide Microsoft Edge password reveal button */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none !important;
        }
        
        .password-toggle-btn {
            color: #6c757d !important;
            transition: color 0.2s ease;
        }
        .password-toggle-btn i {
            color: #6c757d !important;
            transition: color 0.2s ease;
        }
        .password-toggle-btn:hover {
            color: #1218a5 !important;
        }
        .password-toggle-btn:hover i {
            color: #1218a5 !important;
        }
        .password-toggle-btn:focus {
            outline: none;
        }
    </style>
</head>
<body 
style="background-color: #000A99; background-image: linear-gradient(rgba(0, 10, 153, 0.3), rgba(0, 10, 153, 0.3)), url('img/bg7.jpeg'); background-position: center center; background-attachment: fixed; background-size: cover; background-repeat: no-repeat;">


    <!-- Header with Home Button -->
    <nav class="navbar navbar-expand-lg navbar-light" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 1rem 0;">
        <div class="container">
            <a class="navbar-brand" href="index.php" style="font-weight: 600; color: #1218a5;">
                <img src="img/logo2.jpeg" alt="Mhavis Logo" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;">
                Mhavis Medical Center
            </a>
            <div class="navbar-nav ms-auto">
                <a href="index.php" class="btn btn-outline-primary" style="border-radius: 20px;">
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
                            <img src="img/logo2.jpeg" alt="Mhavis Logo" class="mb-3" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover;">
                            <h4>Patient Portal</h4>
                            <h5>Mhavis Medical & Diagnostic Center</h5>
                        </div>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-<?php echo $error_type; ?>">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Patient ID</label>
                                <input type="text" name="patient_id" class="form-control" required 
                                       value="<?php echo isset($patient_identifier) ? htmlspecialchars($patient_identifier) : ''; ?>" placeholder="e.g., PT-2025-00009">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="password-input-wrapper" style="position: relative;">
                                    <input type="password" name="password" id="password" class="form-control" required style="padding-right: 45px;">
                                    <button type="button" class="password-toggle-btn" id="passwordToggle" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 5px; z-index: 10;">
                                        <i class="bi bi-eye" id="passwordIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>

                        <div class="text-center mt-4">
                            <a href="patient_forgot_password.php" class="text-muted d-block mb-2">Forgot Password?</a>
                            <a href="patient_registration.php" class="text-muted">Don't have an account? Register here</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Deleted Modal -->
    <div class="modal fade" id="accountDeletedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Session Ended</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Your account does not exist anymore. Please register a new account or contact Mhavis. You need to log in again to continue.
                </div>
                <div class="modal-footer">
                    <a href="patient_login.php" class="btn btn-primary">OK</a>
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

        // Password toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordIcon = document.getElementById('passwordIcon');

            if (passwordInput && passwordToggle && passwordIcon) {
                // Toggle password visibility
                passwordToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        passwordIcon.classList.remove('bi-eye');
                        passwordIcon.classList.add('bi-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        passwordIcon.classList.remove('bi-eye-slash');
                        passwordIcon.classList.add('bi-eye');
                    }
                    return false;
                });
            }
        });

        // Show modal if redirected due to deleted account
        (function () {
            const params = new URLSearchParams(window.location.search);
            if (params.get('deleted') === '1') {
                const modalEl = document.getElementById('accountDeletedModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            }
        })();
    </script>
</body>
</html>
