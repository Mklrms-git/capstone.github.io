<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';

// Initialize login attempts if not set
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

// // Optional: Block login after 5 failed attempts
// $block_time = 900; // 15 minutes
// if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < $block_time) {
//     $remaining = $block_time - (time() - $_SESSION['last_attempt']);
//     die("Too many failed attempts. Please try again in " . ceil($remaining/60) . " minutes.");
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';

    // FIRST: Check if this username belongs to a patient user (prevent patient login on admin portal)
    $stmt = $conn->prepare("SELECT id FROM patient_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $patient_check = $stmt->get_result();
    
    if ($patient_check && $patient_check->num_rows > 0) {
        // This username belongs to a patient account
        $error_message = "Invalid username or password. Please use the patient login portal.";
        // Increment login attempts on failure
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt'] = time();
    } else {
        // Now lookup admin/doctor by username
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Reset login attempts on success
            $_SESSION['login_attempts'] = 0;

            // Update last login
            // First check if column exists, if not add it
            $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
            if ($check_column->num_rows == 0) {
                // Column doesn't exist, add it
                $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
            }
            
            // Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("i", $user['id']);
                if (!$update_stmt->execute()) {
                    error_log("Failed to update last_login for user ID " . $user['id'] . ": " . $update_stmt->error);
                }
                $update_stmt->close();
            } else {
                error_log("Failed to prepare last_login update statement: " . $conn->error);
            }

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['phone'] = $user['phone'] ?? '';
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['token'] = bin2hex(random_bytes(32));
            session_regenerate_id(true);

            // Redirect based on role
            // Admin is now the top-level role with full system access
            if ($user['role'] === 'Admin') {
                header('Location: admin_dashboard.php');
                exit();
            } elseif ($user['role'] === 'Doctor') {
                header('Location: doctor_dashboard.php');
                exit();
            } else {
                $error_message = "Unauthorized role. Contact admin.";
            }
        } else {
            // Password mismatch
            $error_message = "Invalid username or password.";
        }
    } else {
        // No user found
        $error_message = "Invalid username or password.";
    }

    // Increment login attempts on failure
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt'] = time();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mhavis Medical & Diagnostic Center</title>
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
                            <h4>Mhavis Medical & Diagnostic Center</h4>
                        </div>

                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required 
                                       value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
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
                            <a href="forgot_password.php" class="text-muted">Forgot Password?</a>
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
    </script>
</body>
</html>
