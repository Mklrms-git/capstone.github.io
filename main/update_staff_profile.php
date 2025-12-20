<?php
/**
 * Update Staff Profile Settings
 * 
 * This file allows staff users (Admin, Doctor) to update their own account settings:
 * - Account information (first name, last name, email, phone)
 * - Profile image upload
 * - Password change
 * 
 * Access: All logged-in staff users (Admin, Doctor)
 */
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
requireLogin(); // Allows Admin and Doctor

header('Content-Type: application/json');

$user = getCurrentUser(); // Gets user from 'users' table (Admin, Doctor)
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$conn = getDBConnection();
$success = false;
$message = '';
$errors = [];
$verified_data = null; // For storing verified database values after update

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_image') {
        // Handle profile image upload
        if (isset($_FILES['profile_image'])) {
            $fileError = $_FILES['profile_image']['error'];
            
            // Check for specific upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive in php.ini.",
                    UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive in HTML form.",
                    UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
                    UPLOAD_ERR_NO_FILE => "No file was uploaded.",
                    UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
                    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                    UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
                ];
                $errors[] = $errorMessages[$fileError] ?? "Unknown upload error (Code: $fileError)";
            } elseif (!is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
                $errors[] = "Invalid file upload. Security check failed.";
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                $fileType = $_FILES['profile_image']['type'];
                $fileSize = $_FILES['profile_image']['size'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "Invalid file type. Only JPG, PNG and GIF are allowed.";
                } elseif ($fileSize > $maxSize) {
                    $errors[] = "File size too large. Maximum size is 5MB.";
                } else {
                    // Check if profile_image column exists
                    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
                    $has_profile_image = $check_column && $check_column->num_rows > 0;
                    
                    if (!$has_profile_image) {
                        $errors[] = "Profile image feature is not available in the database.";
                    } else {
                        // Generate unique filename
                        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                        $filename = 'uploads/profile_staff_' . $user['id'] . '_' . time() . '.' . $extension;
                        
                        // Create uploads directory if it doesn't exist
                        if (!is_dir('uploads')) {
                            mkdir('uploads', 0777, true);
                        }
                        
                        // Delete old profile image if it exists
                        if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                            @unlink($user['profile_image']);
                        }
                        
                        // Move uploaded file
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filename)) {
                            // Update database
                            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                            $stmt->bind_param("si", $filename, $user['id']);
                            
                            if ($stmt->execute()) {
                                $success = true;
                                $message = "Profile image updated successfully";
                                $image_path = $filename;
                                
                                // Update session variable for profile image
                                $_SESSION['profile_image'] = $filename;
                            } else {
                                // Delete uploaded file if database update fails
                                @unlink($filename);
                                $errors[] = "Error updating database: " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            $errors[] = "Error moving uploaded file. Please check directory permissions.";
                        }
                    }
                }
            }
        } else {
            $errors[] = "No file uploaded. Please select an image file.";
        }
    } elseif ($action === 'change_password') {
        // Handle password change
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password)) {
            $errors[] = "Current password is required";
        } elseif (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New password and confirm password do not match";
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($stmt->execute()) {
                $success = true;
                $message = "Password updated successfully";
            } else {
                $errors[] = "Error updating password: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'update_account_info') {
        // Handle account information update (name, email, phone)
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? ''); // May be 10 digits or already have +63 prefix - normalizePhoneNumber handles both
        
        // Validation
        if (empty($first_name)) {
            $errors[] = "First name is required";
        } elseif (empty($last_name)) {
            $errors[] = "Last name is required";
        } elseif (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } elseif (empty($phone)) {
            $errors[] = "Phone number is required";
        } else {
            // Normalize phone number (ensure it starts with +63)
            // The frontend sends 10 digits, we need to add +63 prefix
            $phone = normalizePhoneNumber($phone);
            if (!preg_match('/^\+63\d{10}$/', $phone)) {
                $errors[] = "Invalid phone number format. Please use format: 09123456789 (10 digits)";
            }
            
            if (empty($errors)) {
                // Check if email is already in use by another user
                $check_email_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_email_stmt->bind_param("si", $email, $user['id']);
                $check_email_stmt->execute();
                $email_result = $check_email_stmt->get_result();
                
                if ($email_result->num_rows > 0) {
                    $errors[] = "Email address is already in use by another account";
                } else {
                    $check_email_stmt->close();
                    
                    // Update user information
                    $update_stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $update_stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user['id']);
                    
                    if ($update_stmt->execute()) {
                        // Verify that the update actually affected a row
                        $affected_rows = $update_stmt->affected_rows;
                        $update_stmt->close();
                        
                        if ($affected_rows > 0) {
                            // Ensure autocommit is enabled (mysqli uses autocommit by default)
                            // This ensures the update is immediately committed to the database
                            if (!$conn->autocommit(true)) {
                                error_log("Warning: Failed to ensure autocommit for user ID: {$user['id']}");
                            }
                            
                            // Verify the update was actually saved by querying the database immediately
                            $verify_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
                            $verify_stmt->bind_param("i", $user['id']);
                            $verify_stmt->execute();
                            $verify_result = $verify_stmt->get_result();
                            $verified_data = $verify_result->fetch_assoc();
                            $verify_stmt->close();
                            
                            // Check if database has the updated values (use trim for comparison to handle whitespace)
                            if ($verified_data && 
                                trim($verified_data['first_name']) === trim($first_name) && 
                                trim($verified_data['last_name']) === trim($last_name) && 
                                trim($verified_data['email']) === trim($email) && 
                                trim($verified_data['phone']) === trim($phone)) {
                                
                                $success = true;
                                $message = "Account information updated successfully";
                                
                                // Update all session variables to reflect changes immediately
                                // IMPORTANT: Update session AFTER database is confirmed updated
                                $_SESSION['first_name'] = $first_name;
                                $_SESSION['last_name'] = $last_name;
                                $_SESSION['email'] = $email;
                                $_SESSION['phone'] = $phone;
                                
                                // Log successful update for debugging
                                error_log("Profile update successful for user ID: {$user['id']}, affected_rows: {$affected_rows}, verified: true");
                            } else {
                                // Database update didn't persist - log the mismatch
                                error_log("Profile update verification failed for user ID: {$user['id']}. Expected: first_name='{$first_name}', last_name='{$last_name}', email='{$email}', phone='{$phone}'. Got: " . json_encode($verified_data));
                                $errors[] = "Update was executed but verification failed. Database may not have been updated. Please check server logs.";
                            }
                        } else {
                            // No rows were affected - this shouldn't happen but handle it
                            error_log("Profile update failed: No rows affected for user ID: {$user['id']}");
                            $errors[] = "No changes were made. The information may already be up to date, or the user ID is invalid.";
                        }
                    } else {
                        error_log("Profile update SQL error for user ID: {$user['id']}: " . $update_stmt->error);
                        $errors[] = "Error updating account: " . $update_stmt->error;
                        $update_stmt->close();
                    }
                }
            }
        }
    } else {
        $errors[] = "Invalid action specified";
    }
} else {
    $errors[] = "Invalid request method";
}

if ($success) {
    $response = ['success' => true, 'message' => $message];
    if (isset($image_path)) {
        $response['image_path'] = $image_path;
    }
    
    // If this was an account info update, include the verified database values in response for debugging
    if (isset($_POST['action']) && $_POST['action'] === 'update_account_info' && isset($verified_data) && $verified_data) {
        $response['database_values'] = [
            'first_name' => $verified_data['first_name'],
            'last_name' => $verified_data['last_name'],
            'email' => $verified_data['email'],
            'phone' => $verified_data['phone']
        ];
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => !empty($errors) ? implode('. ', $errors) : 'An error occurred']);
}

