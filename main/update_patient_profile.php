<?php
/**
 * Update Patient Profile Settings
 * 
 * This file allows patient users to update their own account settings:
 * - Account information (email, phone) - updates both patients and patient_users tables
 * - Profile image upload
 * - Password change
 * 
 * Access: All logged-in patient users
 */
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';
requirePatientLogin(); // Only patient users can access this file

header('Content-Type: application/json');

$patient_user = getCurrentPatientUser();
if (!$patient_user) {
    echo json_encode(['success' => false, 'message' => 'Patient user not found']);
    exit();
}

$conn = getDBConnection();
$success = false;
$message = '';
$errors = [];

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
                    $check_column = $conn->query("SHOW COLUMNS FROM patient_users LIKE 'profile_image'");
                    $has_profile_image = $check_column && $check_column->num_rows > 0;
                    
                    if (!$has_profile_image) {
                        $errors[] = "Profile image feature is not available in the database.";
                    } else {
                        // Generate unique filename
                        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                        $filename = 'uploads/profile_patient_' . $patient_user['id'] . '_' . time() . '.' . $extension;
                        
                        // Create uploads directory if it doesn't exist
                        if (!is_dir('uploads')) {
                            mkdir('uploads', 0777, true);
                        }
                        
                        // Delete old profile image if it exists
                        if (!empty($patient_user['profile_image']) && file_exists($patient_user['profile_image'])) {
                            @unlink($patient_user['profile_image']);
                        }
                        
                        // Move uploaded file
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filename)) {
                            // Update database
                            $stmt = $conn->prepare("UPDATE patient_users SET profile_image = ? WHERE id = ?");
                            $stmt->bind_param("si", $filename, $patient_user['id']);
                            
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
        } elseif (!password_verify($current_password, $patient_user['password'])) {
            $errors[] = "Current password is incorrect";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE patient_users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $patient_user['id']);
            
            if ($stmt->execute()) {
                $success = true;
                $message = "Password updated successfully";
            } else {
                $errors[] = "Error updating password: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'update_account_info') {
        // Handle account information update (email, phone)
        // Note: first_name and last_name are sent but patients cannot change them (only admin can)
        // We update both patient_users and patients tables for email and phone
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? ''); // Should be 10 digits, we'll add +63
        
        // Validation
        if (empty($email)) {
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
                // Check if email is already in use by another patient user
                $check_email_stmt = $conn->prepare("SELECT id FROM patient_users WHERE email = ? AND id != ?");
                $check_email_stmt->bind_param("si", $email, $patient_user['id']);
                $check_email_stmt->execute();
                $email_result = $check_email_stmt->get_result();
                
                if ($email_result->num_rows > 0) {
                    $errors[] = "Email address is already in use by another account";
                } else {
                    $check_email_stmt->close();
                    
                    // Get patient_id from patient_users
                    $patient_id = $patient_user['patient_id'];
                    
                    // Start transaction to update both patient_users and patients tables
                    $conn->begin_transaction();
                    
                    try {
                        // Update patient_users table
                        $update_user_stmt = $conn->prepare("UPDATE patient_users SET email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                        $update_user_stmt->bind_param("ssi", $email, $phone, $patient_user['id']);
                        
                        if (!$update_user_stmt->execute()) {
                            throw new Exception("Error updating patient user account: " . $update_user_stmt->error);
                        }
                        $update_user_stmt->close();
                        
                        // Update patients table
                        $update_patient_stmt = $conn->prepare("UPDATE patients SET email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                        $update_patient_stmt->bind_param("ssi", $email, $phone, $patient_id);
                        
                        if (!$update_patient_stmt->execute()) {
                            throw new Exception("Error updating patient information: " . $update_patient_stmt->error);
                        }
                        $update_patient_stmt->close();
                        
                        // Commit transaction
                        $conn->commit();
                        $success = true;
                        $message = "Contact details updated successfully";
                        
                        // Update session variables to reflect changes immediately
                        $_SESSION['email'] = $email;
                        $_SESSION['phone'] = $phone;
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $errors[] = $e->getMessage();
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
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => !empty($errors) ? implode('. ', $errors) : 'An error occurred']);
}

