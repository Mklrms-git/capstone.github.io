<?php
define('MHAVIS_EXEC', true);
$page_title = "User Account Management";
$active_page = "user-management";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

// Ensure last_login column exists in users table
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
if ($check_column->num_rows == 0) {
    // Column doesn't exist, add it
    $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
}

// Handle user actions
$action = $_GET['action'] ?? '';
$user_type = $_GET['type'] ?? 'all';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle status toggle
if ($action === 'toggle_status' && $user_id) {
    $type = $_GET['type'] ?? '';
    
    if ($type === 'staff') {
        // Toggle staff user (Admin/Doctor) status
        $stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $new_status = $user['status'] === 'Active' ? 'Inactive' : 'Active';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "User status updated successfully.";
            } else {
                $_SESSION['error_message'] = "Error updating user status.";
            }
        }
    } elseif ($type === 'patient') {
        // Toggle patient user status
        $stmt = $conn->prepare("SELECT status FROM patient_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            // Cycle through statuses: Active -> Suspended -> Active
            $new_status = $user['status'] === 'Active' ? 'Suspended' : 'Active';
            $stmt = $conn->prepare("UPDATE patient_users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Patient user status updated successfully.";
            } else {
                $_SESSION['error_message'] = "Error updating patient user status.";
            }
        }
    }
    
    $redirect_type = $_GET['type_filter'] ?? $user_type;
    header("Location: user_management.php?type=" . urlencode($redirect_type));
    exit();
}

// Handle user deletion
if ($action === 'delete' && $user_id) {
    $type = $_GET['type'] ?? '';
    
    if ($type === 'staff') {
        // Check user before deletion
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            // Check for dependencies
            $check_appointments = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
            $check_appointments->bind_param("i", $user_id);
            $check_appointments->execute();
            $appt_result = $check_appointments->get_result();
            $appt_count = $appt_result->fetch_assoc()['count'];
            
            if ($appt_count > 0 && $user['role'] === 'Doctor') {
                $_SESSION['error_message'] = "Cannot delete user. They have existing appointments.";
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "User deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Error deleting user.";
                }
            }
        }
    } elseif ($type === 'patient') {
        // Delete patient user (cascade will handle patient record if needed)
        $stmt = $conn->prepare("DELETE FROM patient_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Patient user deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting patient user.";
        }
    }
    
    $redirect_type = $_GET['type_filter'] ?? $user_type;
    header("Location: user_management.php?type=" . urlencode($redirect_type));
    exit();
}

// Handle add/edit user (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $form_action = $_POST['action']; // 'add' or 'edit'
    $form_user_type = $_POST['user_type'] ?? ''; // 'staff' or 'patient'
    $form_role = $_POST['role'] ?? ''; // For staff: 'Admin', 'Doctor'
    
    $errors = [];
    
    if ($form_user_type === 'staff') {
        // Handle staff user (Admin, Doctor)
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $status = sanitize($_POST['status'] ?? 'Active');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // Validation - required fields differ for add vs edit
        if ($form_action === 'add') {
            // Add mode - all fields required
            if (empty($firstName)) $errors[] = "First name is required.";
            if (empty($lastName)) $errors[] = "Last name is required.";
            if (empty($username)) $errors[] = "Username is required.";
            if (empty($phone)) {
                $errors[] = "Phone number is required.";
            } elseif (!validatePhoneNumber($phone)) {
                $errors[] = "Invalid phone number format.";
            } else {
                $phone = normalizePhoneNumber($phone);
            }
            if (empty($address)) $errors[] = "Address is required.";
        } else {
            // Edit mode - validate only if provided
            if (!empty($phone) && !validatePhoneNumber($phone)) {
                $errors[] = "Invalid phone number format.";
            } else if (!empty($phone)) {
                $phone = normalizePhoneNumber($phone);
            }
        }
        
        // Email validation (required in add, validate format if provided in edit)
        if ($form_action === 'add' && empty($email)) {
            $errors[] = "Email is required.";
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        
        // Role validation (always required)
        if (empty($form_role) || !in_array($form_role, ['Admin', 'Doctor'])) {
            $errors[] = "Invalid role selected.";
        }
        
        // Specialization validation (required for doctors in add mode, validate if provided in edit)
        if ($form_role === 'Doctor') {
            if ($form_action === 'add' && empty($specialization)) {
                $errors[] = "Specialization is required for doctors.";
            }
        }
        
        // Password validation (required for add, optional for edit)
        if ($form_action === 'add') {
            if (empty($password)) {
                $errors[] = "Password is required.";
            } elseif (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters.";
            } elseif ($password !== $password_confirm) {
                $errors[] = "Passwords do not match.";
            }
        } elseif ($form_action === 'edit' && !empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters.";
            } elseif ($password !== $password_confirm) {
                $errors[] = "Passwords do not match.";
            }
        }
        
        if (empty($errors)) {
            $edit_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            
            // Check for duplicate username/email
            if ($form_action === 'add') {
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $errors[] = "Username or email already exists.";
                }
            } else {
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check_stmt->bind_param("ssi", $username, $email, $edit_user_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $errors[] = "Username or email already exists.";
                }
            }
            
            if (empty($errors)) {
                if ($form_action === 'add') {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email, role, phone, address, specialization, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssssss", $username, $hashed_password, $firstName, $lastName, $email, $form_role, $phone, $address, $specialization, $status);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = ucfirst($form_role) . " account created successfully.";
                    } else {
                        $_SESSION['error_message'] = "Error creating user: " . $stmt->error;
                    }
                    } else {
                        // Update existing user - get current values first
                        $current_stmt = $conn->prepare("SELECT first_name, last_name, email, username, phone, address, specialization, role FROM users WHERE id = ?");
                        $current_stmt->bind_param("i", $edit_user_id);
                        $current_stmt->execute();
                        $result = $current_stmt->get_result();
                        $current = $result->fetch_assoc();
                        $current_stmt->close();
                        
                        // Ensure we have current values
                        if (!$current) {
                            $errors[] = "User not found. Cannot update.";
                        } else {
                            // Store the old role to check if it changed
                            $old_role = $current['role'] ?? '';
                            
                            // Use provided values or keep current ones
                            // Trim all input values first
                            $firstName = trim($firstName);
                            $lastName = trim($lastName);
                            $email = trim($email);
                            $username = trim($username);
                            $phone = trim($phone);
                            $address = trim($address);
                            $specialization = trim($specialization);
                            
                            // Use provided values if they are not empty strings, otherwise keep current values
                            // This ensures that if a field is not changed in the form, we preserve the current database value
                            $firstName = ($firstName !== '') ? $firstName : (isset($current['first_name']) && $current['first_name'] !== null && $current['first_name'] !== '' ? $current['first_name'] : '');
                            $lastName = ($lastName !== '') ? $lastName : (isset($current['last_name']) && $current['last_name'] !== null && $current['last_name'] !== '' ? $current['last_name'] : '');
                            $email = ($email !== '') ? $email : (isset($current['email']) && $current['email'] !== null && $current['email'] !== '' ? $current['email'] : '');
                            $username = ($username !== '') ? $username : (isset($current['username']) && $current['username'] !== null && $current['username'] !== '' ? $current['username'] : '');
                            // For phone: if provided, it should already be normalized (line 160); otherwise use current
                            $phone = ($phone !== '') ? $phone : (isset($current['phone']) && $current['phone'] !== null && $current['phone'] !== '' ? $current['phone'] : '');
                            $address = ($address !== '') ? $address : (isset($current['address']) && $current['address'] !== null && $current['address'] !== '' ? $current['address'] : '');
                            $specialization = ($specialization !== '') ? $specialization : (isset($current['specialization']) && $current['specialization'] !== null && $current['specialization'] !== '' ? $current['specialization'] : '');
                            
                            // Ensure required fields are not empty
                            if (empty($firstName)) {
                                $errors[] = "First name cannot be empty.";
                            }
                            if (empty($lastName)) {
                                $errors[] = "Last name cannot be empty.";
                            }
                            if (empty($email)) {
                                $errors[] = "Email cannot be empty.";
                            }
                            if (empty($username)) {
                                $errors[] = "Username cannot be empty.";
                            }
                            
                            if (empty($errors)) {
                                if (!empty($password)) {
                                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, phone = ?, address = ?, specialization = ?, role = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
                                    $stmt->bind_param("ssssssssssi", $firstName, $lastName, $email, $username, $phone, $address, $specialization, $form_role, $status, $hashed_password, $edit_user_id);
                                } else {
                                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, phone = ?, address = ?, specialization = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
                                    $stmt->bind_param("sssssssssi", $firstName, $lastName, $email, $username, $phone, $address, $specialization, $form_role, $status, $edit_user_id);
                                }
                                
                                if ($stmt->execute()) {
                                    // If the role changed and this is the currently logged-in user, update their session
                                    if ($old_role !== $form_role && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $edit_user_id) {
                                        $_SESSION['role'] = $form_role;
                                    }
                                    
                                    $_SESSION['success_message'] = "User updated successfully.";
                                } else {
                                    $_SESSION['error_message'] = "Error updating user: " . $stmt->error;
                                }
                                $stmt->close();
                            }
                        }
                    }
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
    } elseif ($form_user_type === 'patient') {
        // Handle patient user
        $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $status = sanitize($_POST['status'] ?? 'Active');
        
        // Validation - required fields differ for add vs edit
        if ($form_action === 'add') {
            // Add mode - required fields
            if ($patient_id <= 0) {
                $errors[] = "Please select a patient.";
            }
            if (empty($username)) $errors[] = "Username is required.";
        }
        
        // Email validation (required in add, validate format if provided in edit)
        if ($form_action === 'add' && empty($email)) {
            $errors[] = "Email is required.";
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        
        // Phone validation (optional, but validate format if provided)
        if (!empty($phone) && !validatePhoneNumber($phone)) {
            $errors[] = "Invalid phone number format.";
        } else if (!empty($phone)) {
            $phone = normalizePhoneNumber($phone);
        }
        
        // Password validation
        if ($form_action === 'add') {
            if (empty($password)) {
                $errors[] = "Password is required.";
            } elseif (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters.";
            } elseif ($password !== $password_confirm) {
                $errors[] = "Passwords do not match.";
            }
        } elseif ($form_action === 'edit' && !empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters.";
            } elseif ($password !== $password_confirm) {
                $errors[] = "Passwords do not match.";
            }
        }
        
        if (empty($errors)) {
            $edit_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            
            // Check for duplicate username/email
            if ($form_action === 'add') {
                $check_stmt = $conn->prepare("SELECT id FROM patient_users WHERE username = ? OR email = ?");
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $errors[] = "Username or email already exists.";
                }
            } else {
                $check_stmt = $conn->prepare("SELECT id FROM patient_users WHERE (username = ? OR email = ?) AND id != ?");
                $check_stmt->bind_param("ssi", $username, $email, $edit_user_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $errors[] = "Username or email already exists.";
                }
            }
            
            if (empty($errors)) {
                if ($form_action === 'add') {
                    // Check if patient already has a user account
                    $check_stmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ?");
                    $check_stmt->bind_param("i", $patient_id);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) {
                        $_SESSION['error_message'] = "This patient already has a user account.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $conn->prepare("INSERT INTO patient_users (patient_id, username, password, email, phone, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssss", $patient_id, $username, $hashed_password, $email, $phone, $status);
                        
                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = "Patient user account created successfully.";
                        } else {
                            $_SESSION['error_message'] = "Error creating patient user: " . $stmt->error;
                        }
                    }
                } else {
                    // Update existing patient user - get current values first
                    $current_stmt = $conn->prepare("SELECT username, email, phone, patient_id FROM patient_users WHERE id = ?");
                    $current_stmt->bind_param("i", $edit_user_id);
                    $current_stmt->execute();
                    $current = $current_stmt->get_result()->fetch_assoc();
                    
                    if (!$current) {
                        $errors[] = "Patient user not found. Cannot update.";
                    } else {
                        // Use provided values or keep current ones
                        $username = !empty($username) ? $username : $current['username'];
                        $email = !empty($email) ? $email : $current['email'];
                        $phone = !empty($phone) ? $phone : ($current['phone'] ?? '');
                        $patient_id = $current['patient_id'];
                        
                        // Start transaction to update both patient_users and patients tables
                        $conn->begin_transaction();
                        
                        try {
                            // Update patient_users table
                            if (!empty($password)) {
                                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                                $stmt = $conn->prepare("UPDATE patient_users SET username = ?, email = ?, phone = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
                                $stmt->bind_param("sssssi", $username, $email, $phone, $status, $hashed_password, $edit_user_id);
                            } else {
                                $stmt = $conn->prepare("UPDATE patient_users SET username = ?, email = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ?");
                                $stmt->bind_param("ssssi", $username, $email, $phone, $status, $edit_user_id);
                            }
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Error updating patient user: " . $stmt->error);
                            }
                            $stmt->close();
                            
                            // Also update patients table to keep email and phone in sync
                            if (!empty($patient_id) && ($email || $phone)) {
                                // Get current patient data to preserve first_name and last_name
                                $patient_stmt = $conn->prepare("SELECT first_name, last_name, email as patient_email, phone as patient_phone FROM patients WHERE id = ?");
                                $patient_stmt->bind_param("i", $patient_id);
                                $patient_stmt->execute();
                                $patient_data = $patient_stmt->get_result()->fetch_assoc();
                                $patient_stmt->close();
                                
                                if ($patient_data) {
                                    // Use new email/phone if provided, otherwise keep existing
                                    $update_email = !empty($email) ? $email : $patient_data['patient_email'];
                                    $update_phone = !empty($phone) ? $phone : $patient_data['patient_phone'];
                                    
                                    // Only update if values actually changed
                                    if ($update_email !== $patient_data['patient_email'] || $update_phone !== $patient_data['patient_phone']) {
                                        $update_patient_stmt = $conn->prepare("UPDATE patients SET email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                                        $update_patient_stmt->bind_param("ssi", $update_email, $update_phone, $patient_id);
                                        
                                        if (!$update_patient_stmt->execute()) {
                                            throw new Exception("Error updating patient information: " . $update_patient_stmt->error);
                                        }
                                        $update_patient_stmt->close();
                                    }
                                }
                            }
                            
                            // Commit transaction
                            $conn->commit();
                            $_SESSION['success_message'] = "Patient user updated successfully.";
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $conn->rollback();
                            $errors[] = $e->getMessage();
                        }
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
    }
    
    $redirect_type = $_GET['type'] ?? $user_type;
    header("Location: user_management.php?type=" . urlencode($redirect_type));
    exit();
}

// Get user data for editing
$edit_user = null;
if ($action === 'edit' && $user_id) {
    $type = $_GET['type'] ?? '';
    if ($type === 'staff') {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_user = $result->fetch_assoc();
        if ($edit_user && !empty($edit_user['phone'])) {
            // Convert to input format (0xxxxxxxxxx) then remove leading 0 for 10-digit display
            $phoneInput = phoneToInputFormat($edit_user['phone']);
            $edit_user['phone'] = preg_replace('/^0/', '', $phoneInput);
        }
    } elseif ($type === 'patient') {
        $stmt = $conn->prepare("SELECT * FROM patient_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_user = $result->fetch_assoc();
        if ($edit_user && !empty($edit_user['phone'])) {
            // Convert to input format (0xxxxxxxxxx) then remove leading 0 for 10-digit display
            $phoneInput = phoneToInputFormat($edit_user['phone']);
            $edit_user['phone'] = preg_replace('/^0/', '', $phoneInput);
        }
    }
}

// Get success/error messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Search functionality
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Get statistics
$stats = [];
$stats['admins'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Admin'")->fetch_assoc()['count'];
$stats['doctors'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Doctor'")->fetch_assoc()['count'];
$stats['patient_users'] = $conn->query("SELECT COUNT(*) as count FROM patient_users")->fetch_assoc()['count'];
$stats['active_staff'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active'")->fetch_assoc()['count'];
$stats['active_patients'] = $conn->query("SELECT COUNT(*) as count FROM patient_users WHERE status = 'Active'")->fetch_assoc()['count'];

// Get users based on type
$staff_users = [];
$patient_users = [];

if ($user_type === 'all' || in_array($user_type, ['admin', 'doctor'])) {
    $role_condition = '';
    $params = [];
    $types = '';
    
    if ($user_type === 'admin') {
        $role_condition = "WHERE role = 'Admin'";
    } elseif ($user_type === 'doctor') {
        $role_condition = "WHERE role = 'Doctor'";
    } else {
        // Include all staff roles (Admin, Doctor)
        $role_condition = "WHERE role IN ('Admin', 'Doctor')";
    }
    
    if ($search) {
        $role_condition .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        $types = 'ssss';
    }
    
    $query = "SELECT * FROM users $role_condition ORDER BY 
        CASE role 
            WHEN 'Admin' THEN 1 
            WHEN 'Doctor' THEN 2 
        END, 
        first_name, last_name";
    
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $staff_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($user_type === 'all' || $user_type === 'patient') {
    $search_condition = '';
    $params = [];
    $types = '';
    
    if ($search) {
        $search_condition = "WHERE (pu.username LIKE ? OR pu.email LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        $types = 'ssss';
    }
    
    $query = "SELECT pu.*, p.first_name, p.last_name, p.date_of_birth, p.sex 
              FROM patient_users pu 
              LEFT JOIN patients p ON pu.patient_id = p.id 
              $search_condition 
              ORDER BY pu.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $patient_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

include 'includes/header.php';
?>

<style>
.user-management-main { padding: 20px; }
.stats-card { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white; 
    border-radius: 10px; 
    padding: 20px; 
    margin-bottom: 20px;
}
.stat-item { 
    text-align: center; 
    padding: 15px;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    margin: 5px;
}
.stat-number { 
    font-size: 2rem; 
    font-weight: bold; 
    margin-bottom: 5px;
}
.stat-label { 
    font-size: 0.9rem; 
    opacity: 0.9;
}
.user-tabs { 
    background: white; 
    border-bottom: 2px solid #dee2e6; 
    margin-bottom: 20px;
}
.user-tabs .nav-link { 
    border: none; 
    border-bottom: 3px solid transparent; 
    color: #6c757d; 
    padding: 15px 20px; 
    font-weight: 500;
}
.user-tabs .nav-link.active { 
    color: #007bff; 
    border-bottom-color: #007bff; 
    background: #f8f9fa;
}
.user-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}
.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    margin-right: 15px;
}
.role-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}
.role-admin { background: #dc3545; color: white; } /* Red for Admin */
.role-doctor { background: #007bff; color: white; } /* Blue for Doctor */
.role-patient { background: #28a745; color: white; } /* Green for Patient */
.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-suspended { background: #fff3cd; color: #856404; }
.status-pending { background: #d1ecf1; color: #0c5460; }
.action-buttons .btn {
    margin: 2px;
}
.search-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<div class="user-management-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users-cog me-2"></i>User Account Management</h2>
        <div>
            <?php if ($user_type === 'all' || in_array($user_type, ['admin', 'doctor'])): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal" onclick="setStaffForm('add', '<?php echo $user_type === 'admin' ? 'Admin' : ($user_type === 'doctor' ? 'Doctor' : ''); ?>')">
                    <i class="fas fa-plus me-2"></i>Add Staff User
                </button>
            <?php endif; ?>
            <?php if ($user_type === 'all' || $user_type === 'patient'): ?>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                    <i class="fas fa-plus me-2"></i>Add Patient User
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-card">
        <div class="row">
            <div class="col-md-3">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['admins']; ?></div>
                    <div class="stat-label">Admins</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['doctors']; ?></div>
                    <div class="stat-label">Doctors</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['patient_users']; ?></div>
                    <div class="stat-label">Patient Users</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['active_staff']; ?></div>
                    <div class="stat-label">Active Staff</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['active_patients']; ?></div>
                    <div class="stat-label">Active Patients</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Box -->
    <div class="search-box">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($user_type); ?>">
            <input type="text" name="search" class="form-control" placeholder="Search by name, email, or username..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Search</button>
            <?php if ($search): ?>
                <a href="user_management.php?type=<?php echo urlencode($user_type); ?>" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs user-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?php echo $user_type === 'all' ? 'active' : ''; ?>" href="user_management.php?type=all">
                <i class="fas fa-users me-2"></i>All Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $user_type === 'admin' ? 'active' : ''; ?>" href="user_management.php?type=admin">
                <i class="fas fa-user-tie me-2"></i>Admins
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $user_type === 'doctor' ? 'active' : ''; ?>" href="user_management.php?type=doctor">
                <i class="fas fa-user-md me-2"></i>Doctors
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $user_type === 'patient' ? 'active' : ''; ?>" href="user_management.php?type=patient">
                <i class="fas fa-user-injured me-2"></i>Patient Users
            </a>
        </li>
    </ul>

    <!-- Staff Users Section -->
    <?php if ($user_type === 'all' || in_array($user_type, ['admin', 'doctor'])): ?>
        <div class="mb-4">
            <h4 class="mb-3">
                <i class="fas fa-user-tie me-2"></i>
                <?php 
                if ($user_type === 'admin') echo 'Administrators';
                elseif ($user_type === 'doctor') echo 'Doctors';
                else echo 'Staff Users';
                ?>
            </h4>
            
            <?php if (empty($staff_users)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No staff users found.
                </div>
            <?php else: ?>
                <?php foreach ($staff_users as $user): ?>
                    <div class="user-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center flex-grow-1">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        <span class="role-badge role-<?php echo strtolower(str_replace(' ', '-', $user['role'])); ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </h5>
                                    <div class="text-muted small">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?><br>
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['phone']): ?>
                                            <br><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone']); ?>
                                        <?php endif; ?>
                                        <?php if ($user['role'] === 'Doctor' && !empty($user['specialization'])): ?>
                                            <br><i class="fas fa-stethoscope me-1"></i><?php echo htmlspecialchars($user['specialization']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="status-badge status-<?php echo strtolower($user['status']); ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                                <div class="action-buttons mt-2">
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="viewStaffUser(<?php echo htmlspecialchars(json_encode($user, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PARTIAL_OUTPUT_ON_ERROR)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="editStaffUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="user_management.php?action=toggle_status&id=<?php echo $user['id']; ?>&type=staff&type_filter=<?php echo urlencode($user_type); ?>" 
                                           class="btn btn-sm btn-<?php echo $user['status'] === 'Active' ? 'warning' : 'success'; ?> confirm-action"
                                           data-message="Are you sure you want to <?php echo $user['status'] === 'Active' ? 'deactivate' : 'activate'; ?> this user?">
                                            <i class="fas fa-<?php echo $user['status'] === 'Active' ? 'ban' : 'check'; ?>"></i>
                                        </a>
                                        <a href="user_management.php?action=delete&id=<?php echo $user['id']; ?>&type=staff&type_filter=<?php echo urlencode($user_type); ?>" 
                                           class="btn btn-sm btn-danger confirm-action"
                                           data-message="Are you sure you want to delete this user? This action cannot be undone.">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-info">Current User</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Patient Users Section -->
    <?php if ($user_type === 'all' || $user_type === 'patient'): ?>
        <div class="mb-4">
            <h4 class="mb-3">
                <i class="fas fa-user-injured me-2"></i>Patient Users
            </h4>
            
            <?php if (empty($patient_users)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No patient users found.
                </div>
            <?php else: ?>
                <?php foreach ($patient_users as $patient): ?>
                    <div class="user-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center flex-grow-1">
                                <div class="user-avatar">
                                    <?php 
                                    $initials = 'PU';
                                    if ($patient['first_name'] && $patient['last_name']) {
                                        $initials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
                                    }
                                    echo $initials;
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <?php 
                                        if ($patient['first_name'] && $patient['last_name']) {
                                            echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);
                                        } else {
                                            echo 'Patient #' . $patient['patient_id'];
                                        }
                                        ?>
                                        <span class="role-badge role-patient">Patient</span>
                                    </h5>
                                    <div class="text-muted small">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($patient['email']); ?><br>
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($patient['username']); ?>
                                        <?php if ($patient['phone']): ?>
                                            <br><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($patient['phone']); ?>
                                        <?php endif; ?>
                                        <?php if ($patient['date_of_birth']): ?>
                                            <br><i class="fas fa-birthday-cake me-1"></i><?php echo formatDate($patient['date_of_birth']); ?>
                                        <?php endif; ?>
                                        <?php if ($patient['last_login']): ?>
                                            <br><i class="fas fa-clock me-1"></i>Last login: <?php echo formatDateTime($patient['last_login']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="status-badge status-<?php echo strtolower($patient['status']); ?>">
                                    <?php echo htmlspecialchars($patient['status']); ?>
                                </span>
                                <div class="action-buttons mt-2">
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="viewPatientUser(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="editPatientUser(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="user_management.php?action=toggle_status&id=<?php echo $patient['id']; ?>&type=patient&type_filter=<?php echo urlencode($user_type); ?>" 
                                       class="btn btn-sm btn-<?php echo $patient['status'] === 'Active' ? 'warning' : 'success'; ?> confirm-action"
                                       data-message="Are you sure you want to <?php echo $patient['status'] === 'Active' ? 'suspend' : 'activate'; ?> this patient user?">
                                        <i class="fas fa-<?php echo $patient['status'] === 'Active' ? 'ban' : 'check'; ?>"></i>
                                    </a>
                                    <a href="user_management.php?action=delete&id=<?php echo $patient['id']; ?>&type=patient&type_filter=<?php echo urlencode($user_type); ?>" 
                                       class="btn btn-sm btn-danger confirm-action"
                                       data-message="Are you sure you want to delete this patient user? This action cannot be undone.">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Staff User Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i><span id="staffModalTitle">Add Staff User</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="staffUserForm" action="user_management.php?type=<?php echo urlencode($user_type); ?>">
                <input type="hidden" name="action" id="staffAction" value="add">
                <input type="hidden" name="user_type" value="staff">
                <input type="hidden" name="user_id" id="staffUserId" value="">
                <div class="modal-body">
                    <!-- Account Settings Section -->
                    <h6 class="mb-3 text-primary"><i class="fas fa-cog me-2"></i>Account Settings</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger" id="roleRequired">*</span></label>
                            <select class="form-select" name="role" id="staffRole">
                                <option value="">Select Role</option>
                                <option value="Admin">Admin</option>
                                <option value="Doctor">Doctor</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger" id="statusRequired">*</span></label>
                            <select class="form-select" name="status" id="staffStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Personal Information Section -->
                    <h6 class="mb-3 text-primary"><i class="fas fa-user me-2"></i>Personal Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger" id="firstNameRequired">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="staffFirstName" placeholder="Enter first name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger" id="lastNameRequired">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="staffLastName" placeholder="Enter last name">
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger" id="phoneRequired">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+63</span>
                                <input type="tel" class="form-control" name="phone" id="staffPhone" placeholder="9123456789" pattern="^\d{10}$" inputmode="numeric" maxlength="10">
                            </div>
                            <small class="text-muted">Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.</small>
                        </div>
                        <div class="col-md-6 mb-3" id="specializationField">
                            <label class="form-label">Specialization <span class="text-danger" id="specRequired">*</span></label>
                            <input type="text" class="form-control" name="specialization" id="staffSpecialization" placeholder="e.g., Cardiology">
                        </div>
                    </div>
                    <!-- Address Section -->
                    <h6 class="mb-3 text-primary"><i class="fas fa-map-marker-alt me-2"></i>Address</h6>
                    <div class="mb-3">
                        <label class="form-label">Street Address / Building / House Number</label>
                        <input type="text" class="form-control" name="street_address" id="staffStreetAddress" placeholder="e.g., 123 Main Street, Building A, Unit 5">
                        <small class="text-muted">Optional: Enter specific street address, building name, or house number</small>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Region <span class="text-danger" id="regionRequired">*</span></label>
                            <select id="staffRegion" class="form-select" onchange="loadStaffProvinces()">
                                <option value="">Select Region</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Province <span class="text-danger" id="provinceRequired">*</span></label>
                            <select id="staffProvince" class="form-select" onchange="loadStaffCities()">
                                <option value="">Select Province</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">City/Municipality <span class="text-danger" id="cityRequired">*</span></label>
                            <select id="staffCity" class="form-select" onchange="loadStaffBarangays()">
                                <option value="">Select City</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Barangay <span class="text-danger" id="barangayRequired">*</span></label>
                            <select id="staffBarangay" class="form-select" onchange="combineStaffAddress()">
                                <option value="">Select Barangay</option>
                            </select>
                        </div>
                    </div>
                    <!-- Hidden input to store full address -->
                    <input type="hidden" id="staffFullAddress" name="address">
                    <small class="text-muted d-block mt-2">Address format: Street, Barangay, City/Municipality, Province, Region</small>
                    
                    <!-- Login Credentials Section -->
                    <h6 class="mb-3 text-primary"><i class="fas fa-key me-2"></i>Login Credentials</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger" id="emailRequired">*</span></label>
                            <input type="email" class="form-control" name="email" id="staffEmail" placeholder="Enter email address">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger" id="usernameRequired">*</span></label>
                            <input type="text" class="form-control" name="username" id="staffUsername" placeholder="Enter username">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger" id="passRequired">*</span></label>
                            <input type="password" class="form-control" name="password" id="staffPassword" minlength="8" placeholder="Enter password">
                            <small class="text-muted" id="passHelp">Leave blank to keep current password (edit mode)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger" id="passConfirmRequired">*</span></label>
                            <input type="password" class="form-control" name="password_confirm" id="staffPasswordConfirm" minlength="8" placeholder="Confirm password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Staff User Modal -->
<div class="modal fade" id="viewStaffUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Staff User Account Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4 text-center">
                        <div class="user-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;" id="viewStaffAvatar">
                        </div>
                        <h5 id="viewStaffFullName" class="mb-1"></h5>
                        <span class="role-badge" id="viewStaffRoleBadge"></span>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">User ID</label>
                                <div class="fw-bold" id="viewStaffId"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Status</label>
                                <div id="viewStaffStatus"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Username</label>
                                <div class="fw-bold" id="viewStaffUsername"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Email</label>
                                <div class="fw-bold" id="viewStaffEmail"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-user me-1"></i>First Name</label>
                        <div class="fw-bold" id="viewStaffFirstName"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-user me-1"></i>Last Name</label>
                        <div class="fw-bold" id="viewStaffLastName"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-phone me-1"></i>Phone</label>
                        <div class="fw-bold" id="viewStaffPhone"></div>
                    </div>
                    <div class="col-md-6 mb-3" id="viewStaffSpecializationRow">
                        <label class="text-muted small"><i class="fas fa-stethoscope me-1"></i>Specialization</label>
                        <div class="fw-bold" id="viewStaffSpecialization"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i>Address</label>
                    <div class="fw-bold" id="viewStaffAddress"></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-calendar-plus me-1"></i>Account Created</label>
                        <div class="fw-bold" id="viewStaffCreatedAt"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-calendar-edit me-1"></i>Last Updated</label>
                        <div class="fw-bold" id="viewStaffUpdatedAt"></div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="text-muted small"><i class="fas fa-sign-in-alt me-1"></i>Last Login Date & Time</label>
                        <div class="fw-bold text-primary" id="viewStaffLastLogin" style="font-size: 1.1rem;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Patient User Modal -->
<div class="modal fade" id="viewPatientUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-injured me-2"></i>Patient User Account Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4 text-center">
                        <div class="user-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;" id="viewPatientAvatar">
                        </div>
                        <h5 id="viewPatientFullName" class="mb-1"></h5>
                        <span class="role-badge role-patient">Patient</span>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">User ID</label>
                                <div class="fw-bold" id="viewPatientId"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Patient ID</label>
                                <div class="fw-bold" id="viewPatientPatientId"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Status</label>
                                <div id="viewPatientStatus"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Username</label>
                                <div class="fw-bold" id="viewPatientUsername"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <h6 class="mb-3"><i class="fas fa-user-circle me-2"></i>Account Information</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-envelope me-1"></i>Email</label>
                        <div class="fw-bold" id="viewPatientEmail"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-phone me-1"></i>Phone</label>
                        <div class="fw-bold" id="viewPatientPhone"></div>
                    </div>
                </div>
                <hr>
                <h6 class="mb-3"><i class="fas fa-user me-2"></i>Patient Information</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">First Name</label>
                        <div class="fw-bold" id="viewPatientFirstName"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Last Name</label>
                        <div class="fw-bold" id="viewPatientLastName"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-birthday-cake me-1"></i>Date of Birth</label>
                        <div class="fw-bold" id="viewPatientDOB"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-venus-mars me-1"></i>Sex</label>
                        <div class="fw-bold" id="viewPatientSex"></div>
                    </div>
                </div>
                <hr>
                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Account Activity</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-calendar-plus me-1"></i>Account Created</label>
                        <div class="fw-bold" id="viewPatientCreatedAt"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-calendar-edit me-1"></i>Last Updated</label>
                        <div class="fw-bold" id="viewPatientUpdatedAt"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-sign-in-alt me-1"></i>Last Login</label>
                        <div class="fw-bold" id="viewPatientLastLogin"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><i class="fas fa-exclamation-triangle me-1"></i>Login Attempts</label>
                        <div class="fw-bold" id="viewPatientLoginAttempts"></div>
                    </div>
                </div>
                <div class="mb-3" id="viewPatientLockedRow" style="display: none;">
                    <label class="text-muted small"><i class="fas fa-lock me-1"></i>Locked Until</label>
                    <div class="fw-bold text-danger" id="viewPatientLockedUntil"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Patient User Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-injured me-2"></i><span id="patientModalTitle">Add Patient User</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="patientUserForm" action="user_management.php?type=<?php echo urlencode($user_type); ?>">
                <input type="hidden" name="action" id="patientAction" value="add">
                <input type="hidden" name="user_type" value="patient">
                <input type="hidden" name="user_id" id="patientUserId" value="">
                <div class="modal-body">
                    <!-- Patient Selection (Only for Add Mode) -->
                    <div class="mb-4" id="patientSelectField">
                        <h6 class="mb-3 text-primary"><i class="fas fa-user me-2"></i>Patient Selection</h6>
                        <label class="form-label">Select Patient <span class="text-danger" id="patientSelectRequired">*</span></label>
                        <select class="form-select" name="patient_id" id="patientSelect">
                            <option value="">Select a patient...</option>
                            <?php
                            $patients_stmt = $conn->query("SELECT p.id, p.first_name, p.last_name, p.patient_number, 
                                (SELECT COUNT(*) FROM patient_users WHERE patient_id = p.id) as has_account
                                FROM patients p 
                                ORDER BY p.last_name, p.first_name");
                            while ($p = $patients_stmt->fetch_assoc()):
                                if ($p['has_account'] > 0) continue; // Skip patients who already have accounts
                            ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['patient_number'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Only patients without existing accounts are shown</small>
                    </div>
                    
                    <!-- Account Settings Section -->
                    <h6 class="mb-3 text-primary"><i class="fas fa-cog me-2"></i>Account Settings</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger" id="patientStatusRequired">*</span></label>
                            <select class="form-select" name="status" id="patientStatus">
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Login Credentials Section -->
                    <h6 class="mb-3 text-primary"><i class="fas fa-key me-2"></i>Login Credentials</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger" id="patientUsernameRequired">*</span></label>
                            <input type="text" class="form-control" name="username" id="patientUsername" placeholder="Enter username">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger" id="patientEmailRequired">*</span></label>
                            <input type="email" class="form-control" name="email" id="patientEmail" placeholder="Enter email address">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Phone</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">+63</span>
                            <input type="tel" class="form-control" name="phone" id="patientPhone" placeholder="9123456789" pattern="^\d{10}$" inputmode="numeric" maxlength="10">
                        </div>
                        <small class="text-muted">Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.</small>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger" id="patientPassRequired">*</span></label>
                            <input type="password" class="form-control" name="password" id="patientPassword" minlength="8" placeholder="Enter password">
                            <small class="text-muted" id="patientPassHelp">Leave blank to keep current password (edit mode)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger" id="patientPassConfirmRequired">*</span></label>
                            <input type="password" class="form-control" name="password_confirm" id="patientPasswordConfirm" minlength="8" placeholder="Confirm password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Patient User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Philippine Locations Data
let PH_LOCATIONS = {};

// Load Philippine locations data
fetch('assets/data/ph_lgu_data.json')
  .then(response => response.json())
  .then(data => {
    for (const regionCode in data) {
      const regionName = data[regionCode].region_name;
      PH_LOCATIONS[regionName] = {};
      const provinces = data[regionCode].province_list;
      for (const provName in provinces) {
        PH_LOCATIONS[regionName][provName] = {};
        const cities = provinces[provName].municipality_list;
        for (const cityName in cities) {
          PH_LOCATIONS[regionName][provName][cityName] = cities[cityName].barangay_list;
        }
      }
    }
    populateStaffRegions();
  })
  .catch(error => {
    console.error('Error loading Philippine locations data:', error);
  });

function populateStaffRegions() {
  const regionSelect = document.getElementById('staffRegion');
  if (regionSelect) {
    regionSelect.innerHTML = '<option value="">Select Region</option>';
    for (let region in PH_LOCATIONS) {
      regionSelect.innerHTML += `<option value="${region}">${region}</option>`;
    }
  }
}

function loadStaffProvinces() {
  const region = document.getElementById('staffRegion').value;
  const provinceSelect = document.getElementById('staffProvince');
  provinceSelect.innerHTML = '<option value="">Select Province</option>';
  if (region && PH_LOCATIONS[region]) {
    for (let province in PH_LOCATIONS[region]) {
      provinceSelect.innerHTML += `<option value="${province}">${province}</option>`;
    }
  }
  document.getElementById('staffCity').innerHTML = '<option value="">Select City</option>';
  document.getElementById('staffBarangay').innerHTML = '<option value="">Select Barangay</option>';
  combineStaffAddress();
}

function loadStaffCities() {
  const region = document.getElementById('staffRegion').value;
  const province = document.getElementById('staffProvince').value;
  const citySelect = document.getElementById('staffCity');
  citySelect.innerHTML = '<option value="">Select City</option>';
  if (region && province && PH_LOCATIONS[region] && PH_LOCATIONS[region][province]) {
    for (let city in PH_LOCATIONS[region][province]) {
      citySelect.innerHTML += `<option value="${city}">${city}</option>`;
    }
  }
  document.getElementById('staffBarangay').innerHTML = '<option value="">Select Barangay</option>';
  combineStaffAddress();
}

function loadStaffBarangays() {
  const region = document.getElementById('staffRegion').value;
  const province = document.getElementById('staffProvince').value;
  const city = document.getElementById('staffCity').value;
  const barangaySelect = document.getElementById('staffBarangay');
  barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
  if (region && province && city && PH_LOCATIONS[region] && PH_LOCATIONS[region][province] && PH_LOCATIONS[region][province][city]) {
    PH_LOCATIONS[region][province][city].forEach(brgy => {
      barangaySelect.innerHTML += `<option value="${brgy}">${brgy}</option>`;
    });
  }
  combineStaffAddress();
}

function combineStaffAddress() {
  const street = document.getElementById('staffStreetAddress').value || '';
  const r = document.getElementById('staffRegion').value;
  const p = document.getElementById('staffProvince').value;
  const c = document.getElementById('staffCity').value;
  const b = document.getElementById('staffBarangay').value;
  
  let addressParts = [];
  if (street) addressParts.push(street);
  if (b) addressParts.push(b);
  if (c) addressParts.push(c);
  if (p) addressParts.push(p);
  if (r) addressParts.push(r);
  
  document.getElementById('staffFullAddress').value = addressParts.join(', ');
}

// Parse existing address to populate dropdowns
function parseStaffAddress(address) {
  if (!address) return;
  
  // Try to parse format: "Street, Barangay, City, Province, Region" or "Barangay, City, Province, Region"
  const parts = address.split(',').map(p => p.trim());
  
  if (parts.length >= 4) {
    // Assume last 4 parts are Barangay, City, Province, Region
    const barangay = parts[parts.length - 4];
    const city = parts[parts.length - 3];
    const province = parts[parts.length - 2];
    const region = parts[parts.length - 1];
    const street = parts.length > 4 ? parts.slice(0, parts.length - 4).join(', ') : '';
    
    // Set street address if exists
    if (street) {
      document.getElementById('staffStreetAddress').value = street;
    }
    
    // Set region and load provinces
    const regionSelect = document.getElementById('staffRegion');
    if (regionSelect && PH_LOCATIONS[region]) {
      regionSelect.value = region;
      loadStaffProvinces();
      
      // Wait a bit for provinces to load, then set province
      setTimeout(() => {
        const provinceSelect = document.getElementById('staffProvince');
        if (provinceSelect && PH_LOCATIONS[region][province]) {
          provinceSelect.value = province;
          loadStaffCities();
          
          // Wait a bit for cities to load, then set city
          setTimeout(() => {
            const citySelect = document.getElementById('staffCity');
            if (citySelect && PH_LOCATIONS[region][province][city]) {
              citySelect.value = city;
              loadStaffBarangays();
              
              // Wait a bit for barangays to load, then set barangay
              setTimeout(() => {
                const barangaySelect = document.getElementById('staffBarangay');
                if (barangaySelect) {
                  barangaySelect.value = barangay;
                  combineStaffAddress();
                }
              }, 100);
            }
          }, 100);
        }
      }, 100);
    }
  }
}

function setStaffForm(action, defaultRole = '') {
    document.getElementById('staffAction').value = action;
    document.getElementById('staffModalTitle').textContent = action === 'add' ? 'Add Staff User' : 'Edit Staff User';
    document.getElementById('staffUserForm').reset();
    document.getElementById('staffUserId').value = '';
    
    // Reset address fields
    document.getElementById('staffStreetAddress').value = '';
    document.getElementById('staffRegion').value = '';
    document.getElementById('staffProvince').innerHTML = '<option value="">Select Province</option>';
    document.getElementById('staffCity').innerHTML = '<option value="">Select City</option>';
    document.getElementById('staffBarangay').innerHTML = '<option value="">Select Barangay</option>';
    document.getElementById('staffFullAddress').value = '';
    
    // Get all form fields
    const roleField = document.getElementById('staffRole');
    const statusField = document.getElementById('staffStatus');
    const firstNameField = document.getElementById('staffFirstName');
    const lastNameField = document.getElementById('staffLastName');
    const emailField = document.getElementById('staffEmail');
    const usernameField = document.getElementById('staffUsername');
    const phoneField = document.getElementById('staffPhone');
    const regionField = document.getElementById('staffRegion');
    const provinceField = document.getElementById('staffProvince');
    const cityField = document.getElementById('staffCity');
    const barangayField = document.getElementById('staffBarangay');
    const specializationField = document.getElementById('staffSpecialization');
    const passwordField = document.getElementById('staffPassword');
    const passwordConfirmField = document.getElementById('staffPasswordConfirm');
    
    // Get required indicators
    const roleRequired = document.getElementById('roleRequired');
    const statusRequired = document.getElementById('statusRequired');
    const firstNameRequired = document.getElementById('firstNameRequired');
    const lastNameRequired = document.getElementById('lastNameRequired');
    const emailRequired = document.getElementById('emailRequired');
    const usernameRequired = document.getElementById('usernameRequired');
    const phoneRequired = document.getElementById('phoneRequired');
    const regionRequired = document.getElementById('regionRequired');
    const provinceRequired = document.getElementById('provinceRequired');
    const cityRequired = document.getElementById('cityRequired');
    const barangayRequired = document.getElementById('barangayRequired');
    const specRequired = document.getElementById('specRequired');
    const passRequired = document.getElementById('passRequired');
    const passConfirmRequired = document.getElementById('passConfirmRequired');
    
    if (action === 'add') {
        // Add mode - all fields required
        document.getElementById('staffRole').value = defaultRole;
        roleField.required = true;
        statusField.required = true;
        firstNameField.required = true;
        lastNameField.required = true;
        emailField.required = true;
        usernameField.required = true;
        phoneField.required = true;
        regionField.required = true;
        provinceField.required = true;
        cityField.required = true;
        barangayField.required = true;
        document.getElementById('staffFullAddress').required = true;
        passwordField.required = true;
        passwordConfirmField.required = true;
        
        // Show required indicators
        roleRequired.style.display = '';
        statusRequired.style.display = '';
        firstNameRequired.style.display = '';
        lastNameRequired.style.display = '';
        emailRequired.style.display = '';
        usernameRequired.style.display = '';
        phoneRequired.style.display = '';
        regionRequired.style.display = '';
        provinceRequired.style.display = '';
        cityRequired.style.display = '';
        barangayRequired.style.display = '';
        passRequired.style.display = '';
        passConfirmRequired.style.display = '';
        document.getElementById('passHelp').textContent = '';
    } else {
        // Edit mode - no fields required
        roleField.required = false;
        statusField.required = false;
        firstNameField.required = false;
        lastNameField.required = false;
        emailField.required = false;
        usernameField.required = false;
        phoneField.required = false;
        regionField.required = false;
        provinceField.required = false;
        cityField.required = false;
        barangayField.required = false;
        document.getElementById('staffFullAddress').required = false;
        passwordField.required = false;
        passwordConfirmField.required = false;
        
        // Hide required indicators
        roleRequired.style.display = 'none';
        statusRequired.style.display = 'none';
        firstNameRequired.style.display = 'none';
        lastNameRequired.style.display = 'none';
        emailRequired.style.display = 'none';
        usernameRequired.style.display = 'none';
        phoneRequired.style.display = 'none';
        regionRequired.style.display = 'none';
        provinceRequired.style.display = 'none';
        cityRequired.style.display = 'none';
        barangayRequired.style.display = 'none';
        passRequired.style.display = 'none';
        passConfirmRequired.style.display = 'none';
        document.getElementById('passHelp').textContent = 'Leave blank to keep current password';
    }
    
    // Show/hide specialization field based on role
    toggleSpecializationField();
}

function editStaffUser(user) {
    setStaffForm('edit');
    
    // Set user ID
    document.getElementById('staffUserId').value = user.id || '';
    
    // Populate all fields with current values
    document.getElementById('staffRole').value = user.role || '';
    document.getElementById('staffStatus').value = user.status || 'Active';
    document.getElementById('staffFirstName').value = user.first_name || '';
    document.getElementById('staffLastName').value = user.last_name || '';
    document.getElementById('staffEmail').value = user.email || '';
    document.getElementById('staffUsername').value = user.username || '';
    
    // Format phone number for display (remove +63 or 0 prefix, show only 10 digits)
    let phoneValue = user.phone || '';
    if (phoneValue) {
        // Remove +63, 63, or leading 0 to get 10 digits only
        phoneValue = phoneValue.replace(/^\+63|^63|^0/, '');
    }
    document.getElementById('staffPhone').value = phoneValue;
    
    document.getElementById('staffSpecialization').value = user.specialization || '';
    
    // Parse and populate address fields
    if (user.address) {
        parseStaffAddress(user.address);
    }
    
    // Clear password fields
    document.getElementById('staffPassword').value = '';
    document.getElementById('staffPasswordConfirm').value = '';
    
    toggleSpecializationField();
    
    new bootstrap.Modal(document.getElementById('addStaffModal')).show();
}

function toggleSpecializationField() {
    const role = document.getElementById('staffRole').value;
    const action = document.getElementById('staffAction').value;
    const specField = document.getElementById('specializationField');
    const specInput = document.getElementById('staffSpecialization');
    const specRequired = document.getElementById('specRequired');
    
    if (role === 'Doctor') {
        specField.style.display = '';
        // Only require in add mode
        specInput.required = (action === 'add');
        specRequired.style.display = (action === 'add') ? '' : 'none';
    } else {
        specField.style.display = 'none';
        specInput.required = false;
        specRequired.style.display = 'none';
    }
}

document.getElementById('staffRole').addEventListener('change', toggleSpecializationField);

// Add event listeners for address fields
document.addEventListener('DOMContentLoaded', function() {
    const streetField = document.getElementById('staffStreetAddress');
    if (streetField) {
        streetField.addEventListener('input', combineStaffAddress);
    }
    
    // Restrict phone input to digits only and limit to 10 digits
    const staffPhoneField = document.getElementById('staffPhone');
    const patientPhoneField = document.getElementById('patientPhone');
    
    function restrictPhoneInput(field) {
        if (!field) return;
        
        // Restrict input to digits only
        field.addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/\D/g, '');
            // Limit to 10 digits
            if (this.value.length > 10) {
                this.value = this.value.substring(0, 10);
            }
        });
        
        // Handle paste events
        field.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            // Extract only digits
            const digits = pastedText.replace(/\D/g, '').substring(0, 10);
            this.value = digits;
        });
        
        // Prevent non-numeric keypresses
        field.addEventListener('keypress', function(e) {
            // Allow: backspace, delete, tab, escape, enter, and numbers
            if ([46, 8, 9, 27, 13, 110, 190].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }
    
    restrictPhoneInput(staffPhoneField);
    restrictPhoneInput(patientPhoneField);
});

function editPatientUser(patient) {
    document.getElementById('patientAction').value = 'edit';
    document.getElementById('patientModalTitle').textContent = 'Edit Patient User';
    document.getElementById('patientUserId').value = patient.id || '';
    
    // Hide patient selection field in edit mode
    document.getElementById('patientSelectField').style.display = 'none';
    document.getElementById('patientSelect').required = false;
    
    // Get form fields
    const statusField = document.getElementById('patientStatus');
    const usernameField = document.getElementById('patientUsername');
    const emailField = document.getElementById('patientEmail');
    const phoneField = document.getElementById('patientPhone');
    const passwordField = document.getElementById('patientPassword');
    const passwordConfirmField = document.getElementById('patientPasswordConfirm');
    
    // Get required indicators
    const patientSelectRequired = document.getElementById('patientSelectRequired');
    const patientStatusRequired = document.getElementById('patientStatusRequired');
    const patientUsernameRequired = document.getElementById('patientUsernameRequired');
    const patientEmailRequired = document.getElementById('patientEmailRequired');
    const patientPassRequired = document.getElementById('patientPassRequired');
    const patientPassConfirmRequired = document.getElementById('patientPassConfirmRequired');
    
    // Remove required attributes in edit mode
    statusField.required = false;
    usernameField.required = false;
    emailField.required = false;
    passwordField.required = false;
    passwordConfirmField.required = false;
    
    // Hide required indicators
    patientSelectRequired.style.display = 'none';
    patientStatusRequired.style.display = 'none';
    patientUsernameRequired.style.display = 'none';
    patientEmailRequired.style.display = 'none';
    patientPassRequired.style.display = 'none';
    patientPassConfirmRequired.style.display = 'none';
    
    // Populate fields with current values
    statusField.value = patient.status || 'Active';
    usernameField.value = patient.username || '';
    emailField.value = patient.email || '';
    
    // Format phone number for display (remove +63 or 0 prefix, show only 10 digits)
    let phoneValue = patient.phone || '';
    if (phoneValue) {
        // Remove +63, 63, or leading 0 to get 10 digits only
        phoneValue = phoneValue.replace(/^\+63|^63|^0/, '');
    }
    phoneField.value = phoneValue;
    
    // Clear password fields
    passwordField.value = '';
    passwordConfirmField.value = '';
    document.getElementById('patientPassHelp').textContent = 'Leave blank to keep current password';
    
    new bootstrap.Modal(document.getElementById('addPatientModal')).show();
}

// Reset patient modal on close
document.getElementById('addPatientModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('patientAction').value = 'add';
    document.getElementById('patientModalTitle').textContent = 'Add Patient User';
    document.getElementById('patientUserForm').reset();
    document.getElementById('patientUserId').value = '';
    
    // Show patient selection field
    document.getElementById('patientSelectField').style.display = '';
    
    // Get form fields
    const patientSelect = document.getElementById('patientSelect');
    const statusField = document.getElementById('patientStatus');
    const usernameField = document.getElementById('patientUsername');
    const emailField = document.getElementById('patientEmail');
    const passwordField = document.getElementById('patientPassword');
    const passwordConfirmField = document.getElementById('patientPasswordConfirm');
    
    // Get required indicators
    const patientSelectRequired = document.getElementById('patientSelectRequired');
    const patientStatusRequired = document.getElementById('patientStatusRequired');
    const patientUsernameRequired = document.getElementById('patientUsernameRequired');
    const patientEmailRequired = document.getElementById('patientEmailRequired');
    const patientPassRequired = document.getElementById('patientPassRequired');
    const patientPassConfirmRequired = document.getElementById('patientPassConfirmRequired');
    
    // Set required attributes for add mode
    patientSelect.required = true;
    statusField.required = true;
    usernameField.required = true;
    emailField.required = true;
    passwordField.required = true;
    passwordConfirmField.required = true;
    
    // Show required indicators
    patientSelectRequired.style.display = '';
    patientStatusRequired.style.display = '';
    patientUsernameRequired.style.display = '';
    patientEmailRequired.style.display = '';
    patientPassRequired.style.display = '';
    patientPassConfirmRequired.style.display = '';
    document.getElementById('patientPassHelp').textContent = '';
});

// Password validation
document.getElementById('staffPasswordConfirm').addEventListener('input', function() {
    const password = document.getElementById('staffPassword').value;
    const confirm = this.value;
    if (password && confirm && password !== confirm) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('patientPasswordConfirm').addEventListener('input', function() {
    const password = document.getElementById('patientPassword').value;
    const confirm = this.value;
    if (password && confirm && password !== confirm) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// View Staff User Function
function viewStaffUser(user) {
    // Set avatar initials
    const initials = (user.first_name ? user.first_name.charAt(0) : '') + (user.last_name ? user.last_name.charAt(0) : '');
    document.getElementById('viewStaffAvatar').textContent = initials.toUpperCase();
    
    // Set basic info
    document.getElementById('viewStaffId').textContent = user.id || 'N/A';
    document.getElementById('viewStaffFullName').textContent = (user.first_name || '') + ' ' + (user.last_name || '');
    document.getElementById('viewStaffUsername').textContent = user.username || 'N/A';
    document.getElementById('viewStaffEmail').textContent = user.email || 'N/A';
    
    // Set role badge
    const roleBadge = document.getElementById('viewStaffRoleBadge');
    roleBadge.textContent = user.role || 'N/A';
    roleBadge.className = 'role-badge role-' + (user.role ? user.role.toLowerCase().replace(' ', '-') : '');
    
    // Set status badge
    const statusBadge = document.getElementById('viewStaffStatus');
    const status = user.status || 'N/A';
    statusBadge.innerHTML = '<span class="status-badge status-' + status.toLowerCase() + '">' + status + '</span>';
    
    // Set personal info
    document.getElementById('viewStaffFirstName').textContent = user.first_name || 'N/A';
    document.getElementById('viewStaffLastName').textContent = user.last_name || 'N/A';
    document.getElementById('viewStaffPhone').textContent = user.phone || 'N/A';
    document.getElementById('viewStaffAddress').textContent = user.address || 'N/A';
    
    // Set specialization (only for doctors)
    const specRow = document.getElementById('viewStaffSpecializationRow');
    const specField = document.getElementById('viewStaffSpecialization');
    if (user.role === 'Doctor' && user.specialization) {
        specRow.style.display = '';
        specField.textContent = user.specialization;
    } else {
        specRow.style.display = 'none';
        specField.textContent = 'N/A';
    }
    
    // Set dates
    document.getElementById('viewStaffCreatedAt').textContent = user.created_at ? formatDateTime(user.created_at) : 'N/A';
    document.getElementById('viewStaffUpdatedAt').textContent = user.updated_at ? formatDateTime(user.updated_at) : 'N/A';
    
    // Set last login with enhanced display
    const lastLoginElement = document.getElementById('viewStaffLastLogin');
    // Check if last_login exists and is not null/empty
    const lastLogin = user.last_login;
    if (lastLogin && lastLogin !== 'null' && lastLogin !== null && lastLogin !== '' && lastLogin !== '0000-00-00 00:00:00') {
        try {
            lastLoginElement.textContent = formatDateTime(lastLogin);
            lastLoginElement.className = 'fw-bold text-primary';
            lastLoginElement.style.fontSize = '1.1rem';
        } catch (e) {
            console.error('Error formatting last_login:', e, 'Value:', lastLogin);
            lastLoginElement.textContent = 'Invalid date format';
            lastLoginElement.className = 'fw-bold text-warning';
            lastLoginElement.style.fontSize = '1.1rem';
        }
    } else {
        lastLoginElement.textContent = 'Never logged in';
        lastLoginElement.className = 'fw-bold text-muted';
        lastLoginElement.style.fontSize = '1.1rem';
    }
    
    // Show modal
    new bootstrap.Modal(document.getElementById('viewStaffUserModal')).show();
}

// View Patient User Function
function viewPatientUser(patient) {
    // Set avatar initials
    const initials = (patient.first_name ? patient.first_name.charAt(0) : '') + (patient.last_name ? patient.last_name.charAt(0) : '');
    document.getElementById('viewPatientAvatar').textContent = (initials || 'PU').toUpperCase();
    
    // Set basic info
    document.getElementById('viewPatientId').textContent = patient.id || 'N/A';
    document.getElementById('viewPatientPatientId').textContent = patient.patient_id || 'N/A';
    document.getElementById('viewPatientUsername').textContent = patient.username || 'N/A';
    
    // Set full name
    const fullName = (patient.first_name && patient.last_name) 
        ? patient.first_name + ' ' + patient.last_name 
        : 'Patient #' + (patient.patient_id || 'N/A');
    document.getElementById('viewPatientFullName').textContent = fullName;
    
    // Set status badge
    const statusBadge = document.getElementById('viewPatientStatus');
    const status = patient.status || 'N/A';
    statusBadge.innerHTML = '<span class="status-badge status-' + status.toLowerCase() + '">' + status + '</span>';
    
    // Set account info
    document.getElementById('viewPatientEmail').textContent = patient.email || 'N/A';
    document.getElementById('viewPatientPhone').textContent = patient.phone || 'N/A';
    
    // Set patient info
    document.getElementById('viewPatientFirstName').textContent = patient.first_name || 'N/A';
    document.getElementById('viewPatientLastName').textContent = patient.last_name || 'N/A';
    document.getElementById('viewPatientDOB').textContent = patient.date_of_birth ? formatDate(patient.date_of_birth) : 'N/A';
    document.getElementById('viewPatientSex').textContent = patient.sex || 'N/A';
    
    // Set account activity
    document.getElementById('viewPatientCreatedAt').textContent = patient.created_at ? formatDateTime(patient.created_at) : 'N/A';
    document.getElementById('viewPatientUpdatedAt').textContent = patient.updated_at ? formatDateTime(patient.updated_at) : 'N/A';
    document.getElementById('viewPatientLastLogin').textContent = patient.last_login ? formatDateTime(patient.last_login) : 'Never';
    document.getElementById('viewPatientLoginAttempts').textContent = patient.login_attempts !== undefined ? patient.login_attempts : '0';
    
    // Set locked until if applicable
    const lockedRow = document.getElementById('viewPatientLockedRow');
    const lockedField = document.getElementById('viewPatientLockedUntil');
    if (patient.locked_until) {
        lockedRow.style.display = '';
        lockedField.textContent = formatDateTime(patient.locked_until);
    } else {
        lockedRow.style.display = 'none';
    }
    
    // Show modal
    new bootstrap.Modal(document.getElementById('viewPatientUserModal')).show();
}

// Helper function to format date - Returns format: "December 07, 2025"
function formatDate(dateString) {
    if (!dateString || dateString === 'null' || dateString === null || dateString === '') return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: '2-digit'
        };
        return date.toLocaleDateString('en-US', options);
    } catch (e) {
        return 'N/A';
    }
}

// Helper function to format date and time - Returns format: "December 07, 2025 1:14 AM"
function formatDateTime(dateString) {
    if (!dateString || dateString === 'null' || dateString === null || dateString === '') return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        
        const dateOptions = { 
            year: 'numeric', 
            month: 'long', 
            day: '2-digit'
        };
        const timeOptions = {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        const dateStr = date.toLocaleDateString('en-US', dateOptions);
        const timeStr = date.toLocaleTimeString('en-US', timeOptions);
        return dateStr + ' ' + timeStr;
    } catch (e) {
        return 'N/A';
    }
}

// Handle confirm-action links
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-action').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-message');
            const href = this.getAttribute('href');
            confirmDialog(message, 'Confirm', 'Cancel').then(function(confirmed) {
                if (confirmed && href) {
                    window.location.href = href;
                }
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

