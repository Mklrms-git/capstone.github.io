<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/auth.php';
require_once 'config/patient_auth.php';
require_once 'process_notifications.php';
$page_title = "Patient Registrations";
$active_page = "patient-registrations";

// Start output buffering to prevent unwanted output
ob_start();

// Require admin login
requireRole('Admin');

$conn = getDBConnection();
if (!$conn) {
    die('Database connection failed');
}
$message = '';
$error = '';
// Detect AJAX request
$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// Handle AJAX request for fetching registration request data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_request_data') {
    header('Content-Type: application/json');
    
    $request_id = (int)($_POST['request_id'] ?? 0);
    
    if ($request_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM patient_registration_requests WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $request = $result->fetch_assoc();
            echo json_encode(['success' => true, 'request' => $request]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    }
    exit;
}

// Handle AJAX request for searching potential existing patients
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_existing_patients') {
    header('Content-Type: application/json');
    
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    $matches = [];
    
    // Build WHERE conditions
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Exact name match
    if ($first_name && $last_name) {
        $where_conditions[] = "(first_name = ? AND last_name = ?)";
        $params[] = $first_name;
        $params[] = $last_name;
        $param_types .= "ss";
        
        // Fuzzy name match
        $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ?)";
        $params[] = "%" . $first_name . "%";
        $params[] = "%" . $last_name . "%";
        $param_types .= "ss";
    }
    
    // Date of birth match
    if ($date_of_birth) {
        $where_conditions[] = "date_of_birth = ?";
        $params[] = $date_of_birth;
        $param_types .= "s";
    }
    
    // Email match
    if ($email) {
        $where_conditions[] = "email = ?";
        $params[] = $email;
        $param_types .= "s";
    }
    
    // Phone match (clean phone number for comparison)
    if ($phone) {
        $clean_phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
        $where_conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?";
        $params[] = "%" . $clean_phone . "%";
        $param_types .= "s";
    }
    
    if (!empty($where_conditions)) {
        $query = "SELECT *, 
                  CASE 
                    WHEN first_name = ? AND last_name = ? AND date_of_birth = ? THEN 1
                    WHEN first_name = ? AND last_name = ? THEN 2
                    WHEN email = ? THEN 3
                    WHEN REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ? THEN 4
                    ELSE 5
                  END as match_score
                  FROM patients 
                  WHERE (" . implode(" OR ", $where_conditions) . ")
                  ORDER BY match_score ASC, created_at DESC
                  LIMIT 10";
        
        // Add match score parameters (need to match CASE statement positions)
        $match_params = [
            $first_name ?: '', $last_name ?: '', $date_of_birth ?: '',
            $first_name ?: '', $last_name ?: '',
            $email ?: '',
            "%" . (isset($clean_phone) && $clean_phone ? $clean_phone : '') . "%"
        ];
        $match_types = "sssssss";
        
        // Combine parameters
        $all_params = array_merge($match_params, $params);
        $all_types = $match_types . $param_types;
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($all_types, ...$all_params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // Format date of birth for display
                if ($row['date_of_birth']) {
                    $row['formatted_dob'] = date('M j, Y', strtotime($row['date_of_birth']));
                    $row['date_of_birth'] = date('Y-m-d', strtotime($row['date_of_birth']));
                }
                $matches[] = $row;
            }
            $stmt->close();
        }
    }
    
    echo json_encode(['success' => true, 'matches' => $matches, 'count' => count($matches)]);
    exit;
}

// Handle AJAX request for fetching existing patient data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_existing_patient') {
    header('Content-Type: application/json');
    
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $existing_patient_id = $_POST['existing_patient_id'] ?? null;
    
    // Try to find patient by multiple criteria
    $patient = null;
    
    // If existing_patient_id is provided, use it
    if ($existing_patient_id) {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->bind_param("i", $existing_patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
        }
    }
    
    // If not found by ID, try matching by name and date of birth
    if (!$patient && $first_name && $last_name && $date_of_birth) {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE first_name = ? AND last_name = ? AND date_of_birth = ? LIMIT 1");
        $stmt->bind_param("sss", $first_name, $last_name, $date_of_birth);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
        }
    }
    
    // If still not found, try matching by email
    if (!$patient && isset($_POST['email']) && $_POST['email']) {
        $email = trim($_POST['email']);
        $stmt = $conn->prepare("SELECT * FROM patients WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
        }
    }
    
    if ($patient) {
        // Format date of birth for display
        if ($patient['date_of_birth']) {
            $patient['date_of_birth'] = date('Y-m-d', strtotime($patient['date_of_birth']));
        }
        echo json_encode(['success' => true, 'patient' => $patient]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No existing patient record found']);
    }
    exit;
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['request_id'] ?? '';
    $admin_notes = $_POST['admin_notes'] ?? '';
    $selected_existing_patient_id = !empty($_POST['existing_patient_id']) ? (int)$_POST['existing_patient_id'] : null;
    
    if ($action && $request_id) {
        $stmt = $conn->prepare("SELECT * FROM patient_registration_requests WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
					if ($request) {
            if ($action === 'approve') {
                // Start database transaction
                $conn->begin_transaction();
                
                try {
                    // Determine patient_id based on patient type
                    // Priority: 1) Selected from modal, 2) existing_patient_id from request, 3) treat as new patient
                    $existing_patient_id_to_use = $selected_existing_patient_id ?? ($request['existing_patient_id'] ?? null);
                    
                    // For "New Patient" type, ALWAYS create a new patient record
                    // For "Existing Patient" type, only use existing ID if one is provided
                    $should_create_new_patient = ($request['patient_type'] === 'New') || 
                                                 ($request['patient_type'] === 'Existing' && !$existing_patient_id_to_use);
                    
                    if (!$should_create_new_patient && $existing_patient_id_to_use) {
                        // Use existing patient ID - update existing patient record
                        $patient_id = $existing_patient_id_to_use;
                        
                        // Extract additional fields from medical_history JSON if present
                        $additional_data = [];
                        if (!empty($request['medical_history'])) {
                            $decoded = json_decode($request['medical_history'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $additional_data = $decoded;
                            }
                        }
                        
                        $suffix = $additional_data['suffix'] ?? null;
                        $civil_status = $additional_data['civil_status'] ?? null;
                        $is_senior_citizen = isset($additional_data['is_senior_citizen']) ? (int)$additional_data['is_senior_citizen'] : null;
                        $senior_citizen_id = $additional_data['senior_citizen_id'] ?? null;
                        $is_pwd = isset($additional_data['is_pwd']) ? (int)$additional_data['is_pwd'] : null;
                        $pwd_id = $additional_data['pwd_id'] ?? null;
                        $emergency_contact_relationship = $additional_data['emergency_contact_relationship'] ?? null;
                        
                        // Update existing patient record with any new information
                        $stmt = $conn->prepare("UPDATE patients SET 
                            phone = COALESCE(NULLIF(?, ''), phone),
                            email = COALESCE(NULLIF(?, ''), email),
                            address = COALESCE(NULLIF(?, ''), address),
                            emergency_contact_name = COALESCE(NULLIF(?, ''), emergency_contact_name),
                            relationship = COALESCE(?, relationship),
                            emergency_contact_phone = COALESCE(NULLIF(?, ''), emergency_contact_phone),
                            suffix = COALESCE(?, suffix),
                            civil_status = COALESCE(?, civil_status),
                            is_senior_citizen = COALESCE(?, is_senior_citizen),
                            senior_citizen_id = COALESCE(NULLIF(?, ''), senior_citizen_id),
                            is_pwd = COALESCE(?, is_pwd),
                            pwd_id = COALESCE(NULLIF(?, ''), pwd_id),
                            updated_at = NOW()
                            WHERE id = ?");
                        
                        $stmt->bind_param("sssssssssisssi", 
                            $request['phone'], $request['email'], $request['address'],
                            $request['emergency_contact_name'], $emergency_contact_relationship,
                            $request['emergency_contact_phone'], $suffix, $civil_status,
                            $is_senior_citizen, $senior_citizen_id, $is_pwd, $pwd_id,
                            $patient_id);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update existing patient record: " . $stmt->error);
                        }

									// Fetch patient number for existing patient
									$stmt = $conn->prepare("SELECT patient_number FROM patients WHERE id = ?");
									$stmt->bind_param("i", $patient_id);
									$stmt->execute();
									$rowPn = $stmt->get_result()->fetch_assoc();
									$patient_number = $rowPn['patient_number'] ?? '';
                    } else {
                        // ====================================================================
                        // CREATE NEW PATIENT RECORD IN MAIN PATIENTS TABLE
                        // ====================================================================
                        // IMPORTANT: For "New Patient" type registrations, this ALWAYS creates
                        // a new patient record in the patients table automatically upon approval.
                        // The admin does NOT need to manually create the record - approval alone
                        // generates it. The new patient will appear as an official, active patient.
                        //
                        // This also applies to "Existing Patient" type without a selected Patient ID.
                        // ====================================================================
                        
                        // Generate unique patient number
                        $year = date('Y');
                        $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(patient_number, -5) AS UNSIGNED)) as max_num 
                                                FROM patients 
                                                WHERE patient_number LIKE CONCAT('PT-', ?, '-%')");
                        $stmt->bind_param("s", $year);
                        $stmt->execute();
                        $result = $stmt->get_result()->fetch_assoc();
                        $next_num = ($result['max_num'] ?? 0) + 1;
                        $patient_number = sprintf('PT-%s-%05d', $year, $next_num);
                        
                        // Extract additional fields from medical_history JSON if present
                        $additional_data = [];
                        if (!empty($request['medical_history'])) {
                            $decoded = json_decode($request['medical_history'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $additional_data = $decoded;
                            }
                        }
                        
                        $suffix = $additional_data['suffix'] ?? '';
                        $civil_status = $additional_data['civil_status'] ?? '';
                        $is_senior_citizen = isset($additional_data['is_senior_citizen']) ? (int)$additional_data['is_senior_citizen'] : 0;
                        $senior_citizen_id = $additional_data['senior_citizen_id'] ?? '';
                        $is_pwd = isset($additional_data['is_pwd']) ? (int)$additional_data['is_pwd'] : 0;
                        $pwd_id = $additional_data['pwd_id'] ?? '';
                        $emergency_contact_relationship = $additional_data['emergency_contact_relationship'] ?? '';
                        
                        // Ensure blood_type and allergies have default values (NOT NULL constraint)
                        $blood_type = !empty($request['blood_type']) ? $request['blood_type'] : '';
                        $allergies = !empty($request['allergies']) ? $request['allergies'] : '';
                        
                        // Insert new patient record into the main patients table
                        // This creates the official patient record that will appear in the system
                        $stmt = $conn->prepare("INSERT INTO patients 
                            (patient_number, first_name, last_name, middle_name, suffix, date_of_birth, sex, civil_status, 
                             is_senior_citizen, senior_citizen_id, is_pwd, pwd_id, phone, email, address, 
                             emergency_contact_name, relationship, emergency_contact_phone, blood_type, allergies) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $stmt->bind_param("ssssssssisssssssssss", 
                            $patient_number,
                            $request['first_name'], $request['last_name'], $request['middle_name'], $suffix,
                            $request['date_of_birth'], $request['sex'], $civil_status,
                            $is_senior_citizen, $senior_citizen_id, $is_pwd, $pwd_id,
                            $request['phone'], $request['email'], $request['address'],
                            $request['emergency_contact_name'], $emergency_contact_relationship, $request['emergency_contact_phone'],
                            $blood_type, $allergies);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to create patient record: " . $stmt->error);
                        }
                        $patient_id = $conn->insert_id;
                        
                        // Log successful patient record creation for "New Patient" type
                        if ($request['patient_type'] === 'New') {
                            error_log("New patient record created automatically upon approval - Patient ID: {$patient_id}, Patient Number: {$patient_number}, Name: {$request['first_name']} {$request['last_name']}");
                        }
                    }
                    
                    // Hash the password that the patient provided during registration
                    $hashed_password = password_hash($request['password'], PASSWORD_DEFAULT);
                    
                    // Check if patient_user account already exists for this patient
                    $stmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ? LIMIT 1");
                    $stmt->bind_param("i", $patient_id);
                    $stmt->execute();
                    $existing_user = $stmt->get_result()->fetch_assoc();
                    
                    // Determine if this is linking to existing account (existing patient with or without user account)
                    $is_existing_account = ($existing_patient_id_to_use) ? true : false;
                    
                    if ($existing_user) {
                        // Update existing patient_user account with new credentials
                        $patient_user_id = $existing_user['id'];
                        $stmt = $conn->prepare("UPDATE patient_users 
                            SET password = ?, email = COALESCE(NULLIF(?, ''), email), 
                                phone = COALESCE(NULLIF(?, ''), phone), status = 'Active', updated_at = NOW()
                            WHERE id = ?");
                        $stmt->bind_param("sssi", $hashed_password, $request['email'], $request['phone'], $patient_user_id);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update existing patient user account: " . $stmt->error);
                        }
                    } else {
                        // Create new patient user account; set username to patient_number for backward compatibility
                        $stmt = $conn->prepare("INSERT INTO patient_users 
                            (patient_id, username, password, email, phone, status) 
                            VALUES (?, ?, ?, ?, ?, 'Active')");
                        
                        $stmt->bind_param("issss", $patient_id, $patient_number, 
                                         $hashed_password, $request['email'], $request['phone']);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to create patient user account: " . $stmt->error);
                        }
                        $patient_user_id = $conn->insert_id;
                    }
                    
                    // Update request status
                    $stmt = $conn->prepare("UPDATE patient_registration_requests 
                        SET status = 'Approved', processed_by = ?, processed_at = NOW(), admin_notes = ? 
                        WHERE id = ?");
                    $stmt->bind_param("isi", $_SESSION['user_id'], $admin_notes, $request_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update request status: " . $stmt->error);
                    }
                    
                    // Send approval email with the original credentials they registered with
                    $subject = "Registration Approved - Your Account Credentials";
                    $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/patient_login.php";
                    
                    if ($is_existing_account) {
                        // Email for existing account link
                        $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                            <div style='background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                                <h2 style='margin: 0;'>✓ Registration Approved!</h2>
                            </div>
                            <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                                <p style='font-size: 16px; color: #333;'>Dear <strong>{$request['first_name']} {$request['last_name']}</strong>,</p>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    Great news! Your patient registration has been approved and linked to your existing account by our admin team.
                                </p>
                                
                                <div style='background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;'>
                                    <h3 style='margin-top: 0; color: #333;'>🔑 Account Credentials</h3>
                                    <p style='margin: 10px 0;'><strong>Patient ID:</strong> {$patient_number}</p>
                                    <p style='margin: 10px 0;'><strong>Password:</strong> {$request['password']}</p>
                                </div>
                                
                                <div style='background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;'>
                                    <p style='margin: 0; font-size: 14px; color: #856404;'>
                                        🔐 <strong>Important:</strong> Please change your password immediately after your first login for security purposes.
                                    </p>
                                </div>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    You can now access your patient portal at: <a href='{$login_url}' style='color: #4CAF50;'>{$login_url}</a>
                                </p>
                                
                                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                                
                                <p style='font-size: 13px; color: #777; text-align: center; margin: 0;'>
                                    Best regards,<br>
                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>
                                    Healthcare Team
                                </p>
                            </div>
                        </div>
                        ";
                    } else {
                        // Email for new account
                        $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                            <div style='background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                                <h2 style='margin: 0;'>✓ Registration Approved!</h2>
                            </div>
                            <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                                <p style='font-size: 16px; color: #333;'>Dear <strong>{$request['first_name']} {$request['last_name']}</strong>,</p>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    Great news! Your patient registration has been approved by our admin team.
                                </p>
                                
                                <div style='background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;'>
                                    <h3 style='margin-top: 0; color: #333;'>🔑 Account Credentials</h3>
                                    <p style='margin: 10px 0;'><strong>Patient ID:</strong> {$patient_number}</p>
                                    <p style='margin: 10px 0;'><strong>Password:</strong> {$request['password']}</p>
                                </div>
                                
                                <div style='background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;'>
                                    <p style='margin: 0; font-size: 14px; color: #856404;'>
                                        🔐 <strong>Important:</strong> Please change your password immediately after your first login for security purposes.
                                    </p>
                                </div>
                                
                                <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                    You can now access your patient portal at: <a href='{$login_url}' style='color: #4CAF50;'>{$login_url}</a>
                                </p>
                                
                                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                                
                                <p style='font-size: 13px; color: #777; text-align: center; margin: 0;'>
                                    Best regards,<br>
                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>
                                    Healthcare Team
                                </p>
                            </div>
                        </div>
                        ";
                    }
                    
                    // Queue the email
                    if (!sendEmailNotification($request['email'], $request['first_name'] . ' ' . $request['last_name'], 
                                            $subject, $body, 'html')) {
                        throw new Exception("Failed to queue approval email");
                    }
                    
                    // Process the email queue immediately so the patient receives the email now
                    if (function_exists('processEmailQueue')) { 
                        processEmailQueue(); 
                    }
                    
                    // Clear the plain text password for security after sending email
                    $stmt = $conn->prepare("UPDATE patient_registration_requests SET password = '[REDACTED]' WHERE id = ?");
                    $stmt->bind_param("i", $request_id);
                    $stmt->execute();
                    
                    // Create notification
                    if (!createNotification('Patient', $patient_user_id, 'Registration_Approved', 
                            'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 'Email')) {
                        // Don't fail the transaction for notification creation failure
                        error_log("Failed to create notification for patient user ID: " . $patient_user_id);
                    }
                    
                    // Commit the transaction
                    $conn->commit();
                    $message = "Patient approved and email sent successfully.";
                    
                } catch (Exception $e) {
                    // Rollback the transaction
                    $conn->rollback();
                    $error = "Failed to approve registration: " . $e->getMessage();
                    error_log("Patient registration approval failed: " . $e->getMessage());
                }
            } elseif ($action === 'reject') {
                try {
                    // Update request status
                    $stmt = $conn->prepare("UPDATE patient_registration_requests 
                        SET status = 'Rejected', processed_by = ?, processed_at = NOW(), admin_notes = ? 
                        WHERE id = ?");
                    $stmt->bind_param("isi", $_SESSION['user_id'], $admin_notes, $request_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update request status: " . $stmt->error);
                    }
                    
                    // Send rejection email (queue then process)
                    $subject = "Registration Status Update - Mhavis Medical & Diagnostic Center";
                    
                    // Create HTML email body for better formatting
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                        <div style='background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                            <h2 style='margin: 0;'>Registration Status Update</h2>
                        </div>
                        <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                            <p style='font-size: 16px; color: #333;'>Dear <strong>{$request['first_name']} {$request['last_name']}</strong>,</p>
                            
                            <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                Thank you for your interest in registering with Mhavis Medical & Diagnostic Center.
                            </p>
                            
                            <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                Unfortunately, we are unable to approve your registration at this time.
                            </p>
                            
                            " . (!empty($admin_notes) ? "
                            <div style='background-color: #f8d7da; padding: 15px; margin: 20px 0; border-left: 4px solid #dc3545; border-radius: 4px;'>
                                <p style='margin: 0; font-size: 14px; color: #721c24;'>
                                    <strong>Reason:</strong> {$admin_notes}
                                </p>
                            </div>
                            " : "") . "
                            
                            <div style='background-color: #d1ecf1; padding: 15px; margin: 20px 0; border-left: 4px solid #0dcaf0; border-radius: 4px;'>
                                <p style='margin: 0; font-size: 14px; color: #055160;'>
                                    💡 <strong>What's Next?</strong><br>
                                    If you have any questions or would like to discuss this further, please contact us directly. We're here to help and can assist you with the registration process.
                                </p>
                            </div>
                            
                            <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                We appreciate your understanding.
                            </p>
                            
                            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                            
                            <p style='font-size: 13px; color: #777; text-align: center; margin: 0;'>
                                Best regards,<br>
                                <strong>Mhavis Medical & Diagnostic Center</strong><br>
                                Healthcare Team
                            </p>
                        </div>
                    </div>
                    ";
                    
                    if (!sendEmailNotification($request['email'], $request['first_name'] . ' ' . $request['last_name'], 
                                        $subject, $body, 'html')) {
                        throw new Exception("Failed to queue rejection email");
                    }
                    
                    if (function_exists('processEmailQueue')) { 
                        processEmailQueue(); 
                    }
                    
                    // Clear the plain text password for security
                    $stmt = $conn->prepare("UPDATE patient_registration_requests SET password = '[REDACTED]' WHERE id = ?");
                    $stmt->bind_param("i", $request_id);
                    $stmt->execute();
                    
                    $message = "Registration rejected. Patient has been notified via email.";
                    
                } catch (Exception $e) {
                    $error = "Failed to reject registration: " . $e->getMessage();
                    error_log("Patient registration rejection failed: " . $e->getMessage());
                }
            }
        } else {
            $error = "Request not found or already processed.";
        }
    }

    // If this is an AJAX request, respond with JSON and exit
    if ($isAjax) {
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        
        try {
            if (!empty($error)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $error]);
            } else {
                echo json_encode(['success' => true, 'message' => $message ?: 'Operation completed successfully.']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request.']);
        }
        exit;
    }
}

// Automatic cleanup of logs history (runs automatically when admin accesses this page)
try {
    require_once __DIR__ . '/config/cleanup_helper.php';
    runAutomaticCleanup($conn, true); // Silent mode - won't break page if cleanup fails
} catch (Exception $e) {
    error_log("Automatic cleanup error in admin_patient_registrations.php: " . $e->getMessage());
}

// Legacy: Automatic cleanup of old registration history (runs once per day)
// Note: This is now handled by runAutomaticCleanup above, but kept for backward compatibility
try {
    // Check if cleanup should run (once per day)
    $cleanup_check_stmt = $conn->prepare("SELECT config_value FROM system_config WHERE config_key = 'last_registration_cleanup'");
    $should_run_cleanup = false;
    
    if ($cleanup_check_stmt) {
        $cleanup_check_stmt->execute();
        $cleanup_result = $cleanup_check_stmt->get_result();
        
        if ($cleanup_result->num_rows > 0) {
            $last_cleanup = $cleanup_result->fetch_assoc()['config_value'];
            // Run cleanup if last cleanup was more than 24 hours ago
            $last_cleanup_time = strtotime($last_cleanup);
            $should_run_cleanup = (time() - $last_cleanup_time) >= 86400; // 24 hours
        } else {
            // Never run before, run it now
            $should_run_cleanup = true;
        }
        $cleanup_check_stmt->close();
    } else {
        // Table might not exist yet, try to create it
        $conn->query("CREATE TABLE IF NOT EXISTS system_config (
            config_key VARCHAR(100) PRIMARY KEY,
            config_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $should_run_cleanup = true;
    }
    
    // Run cleanup if needed
    if ($should_run_cleanup) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        // Count records to be deleted
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_registration_requests WHERE created_at < ?");
        $count_stmt->bind_param("s", $cutoff_date);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $records_to_delete = $count_row['count'] ?? 0;
        $count_stmt->close();
        
        if ($records_to_delete > 0) {
            // Delete old records
            $delete_stmt = $conn->prepare("DELETE FROM patient_registration_requests WHERE created_at < ?");
            $delete_stmt->bind_param("s", $cutoff_date);
            $delete_stmt->execute();
            $deleted_count = $delete_stmt->affected_rows;
            $delete_stmt->close();
            
            // Update last cleanup time
            $update_stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) 
                                          VALUES ('last_registration_cleanup', NOW()) 
                                          ON DUPLICATE KEY UPDATE config_value = NOW()");
            $update_stmt->execute();
            $update_stmt->close();
            
            // Log the cleanup (silently, don't show to user unless they check logs)
            error_log("Patient registration history cleanup: Deleted $deleted_count record(s) older than 7 days");
        } else {
            // Update last cleanup time even if nothing to delete
            $update_stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) 
                                          VALUES ('last_registration_cleanup', NOW()) 
                                          ON DUPLICATE KEY UPDATE config_value = NOW()");
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
} catch (Exception $e) {
    // Don't break the page if cleanup fails, just log it
    error_log("Automatic cleanup error in admin_patient_registrations.php: " . $e->getMessage());
}

// Get registration requests for each tab
$pending_requests = [];
$approved_requests = [];
$all_logs = [];

try {
    // Get pending requests
    $stmt = $conn->prepare("SELECT * FROM patient_registration_requests WHERE status = 'Pending' ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->execute();
        $pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Get approved requests
    // For existing patients: join on existing_patient_id
    // For new patients: join on email match (most reliable after approval)
    $stmt = $conn->prepare("SELECT prr.*, u.first_name as processed_by_name, u.last_name as processed_by_last_name,
                           COALESCE(p.patient_number, pu.username) as patient_number, 
                           COALESCE(pu.status, 'Active') as account_status
                           FROM patient_registration_requests prr 
                           LEFT JOIN users u ON prr.processed_by = u.id 
                           LEFT JOIN patients p ON (prr.existing_patient_id = p.id) OR 
                           (prr.patient_type = 'New' AND prr.email = p.email)
                           LEFT JOIN patient_users pu ON (p.id = pu.patient_id) OR (prr.email = pu.email)
                           WHERE prr.status = 'Approved' 
                           ORDER BY prr.processed_at DESC");
    if ($stmt) {
        $stmt->execute();
        $approved_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Get all logs (all requests)
    $stmt = $conn->prepare("SELECT prr.*, u.first_name as processed_by_name, u.last_name as processed_by_last_name 
                           FROM patient_registration_requests prr 
                           LEFT JOIN users u ON prr.processed_by = u.id 
                           ORDER BY prr.created_at DESC");
    if ($stmt) {
        $stmt->execute();
        $all_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Database query error in admin_patient_registrations.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registrations - Mhavis Medical & Diagnostic Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
    <style>
        /* Uniform Tab Styling */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-right: 4px;
        }
        
        .nav-tabs .nav-link:hover {
            color: #495057;
            border-bottom-color: #dee2e6;
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            background-color: transparent;
            border-bottom-color: #0d6efd;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            font-weight: 600;
        }
        
        /* Uniform Table Styling */
        .table {
            margin-bottom: 0;
            width: 100%;
            font-size: 0.95rem;
        }
        
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
            padding: 20px 20px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 20px 20px;
            vertical-align: middle;
            word-wrap: break-word;
            line-height: 1.7;
            font-size: 0.95rem;
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        /* Uniform Badge Styling */
        .badge {
            padding: 8px 14px;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: 6px;
            display: inline-block;
            white-space: nowrap;
        }
        
        /* Table cell content spacing */
        .table td strong {
            font-weight: 600;
            color: #212529;
        }
        
        .table td small {
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        /* Better column spacing */
        .table th:first-child,
        .table td:first-child {
            padding-left: 30px;
        }
        
        .table th:last-child,
        .table td:last-child {
            padding-right: 30px;
        }
        
        /* Uniform Empty State */
        .alert-info {
            border-left: 4px solid #0dcaf0;
            background-color: #e7f3f5;
            color: #055160;
            padding: 16px 20px;
        }
        
        .alert-info i {
            font-size: 1.2rem;
        }
        
        /* Uniform Button Group */
        .btn-group .btn {
            border-radius: 4px;
        }
        
        .btn-group .btn:first-child {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .btn-group .btn:last-child {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        /* Card Styling */
        .card {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 16px 20px;
        }
        
        .card-header h4 {
            margin: 0;
            font-weight: 600;
            color: #212529;
        }
        
        .card-body {
            padding: 40px 50px;
        }
        
        /* Improved spacing for tabs */
        .nav-tabs {
            margin-bottom: 35px;
        }
        
        /* Better table spacing */
        .table-responsive {
            margin-top: 25px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) > td {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .table-hover > tbody > tr:hover > td {
            background-color: #f8f9fa;
        }
        
        /* Better container spacing */
        .container-fluid {
            padding-left: 40px;
            padding-right: 40px;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        
        /* Tab content spacing */
        .tab-content {
            padding-top: 10px;
        }
        
        .tab-pane {
            min-height: 200px;
        }
        
        /* Alert spacing improvements */
        .alert {
            margin-bottom: 20px;
        }
        
        /* Empty state spacing */
        .alert-info {
            margin-top: 20px;
        }
        
        /* Column width improvements */
        .table th:nth-child(1),
        .table td:nth-child(1) {
            min-width: 180px;
        }
        
        .table th:nth-child(2),
        .table td:nth-child(2) {
            min-width: 220px;
        }
        
        .table th:nth-child(3),
        .table td:nth-child(3) {
            min-width: 150px;
        }
        
        .table th:nth-child(4),
        .table td:nth-child(4) {
            min-width: 160px;
        }
        
        .table th:nth-child(5),
        .table td:nth-child(5) {
            min-width: 130px;
        }
        
        .table th:nth-child(6),
        .table td:nth-child(6) {
            min-width: 140px;
        }
        
        .table th:nth-child(7),
        .table td:nth-child(7) {
            min-width: 150px;
        }
        
        .table th:nth-child(8),
        .table td:nth-child(8) {
            min-width: 180px;
        }
        
        /* Admin notes column - wider */
        .table th:last-child,
        .table td:last-child {
            min-width: 280px;
            max-width: 450px;
        }
        
        /* Better text wrapping for long content */
        .table td {
            word-break: break-word;
            overflow-wrap: break-word;
            max-width: 0;
        }
        
        /* Ensure table cells have proper spacing */
        .table td strong {
            display: block;
            margin-bottom: 2px;
        }
        
        /* Better spacing for table rows */
        .table tbody tr {
            height: auto;
            min-height: 60px;
        }
        
        /* Action buttons spacing */
        .btn-group {
            gap: 8px;
        }
        
        .btn-group .btn {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        /* Ensure buttons are clickable */
        .approve-btn, .reject-btn {
            cursor: pointer !important;
            pointer-events: auto !important;
            position: relative !important;
            z-index: 100 !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }
        
        .approve-btn:hover, .reject-btn:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }
        
        .approve-btn:active, .reject-btn:active {
            transform: scale(0.98);
        }
        
        .approve-btn:disabled, .reject-btn:disabled {
            cursor: not-allowed !important;
            opacity: 0.6;
            pointer-events: none !important;
        }
        
        /* Ensure button group doesn't block clicks */
        .btn-group {
            position: relative;
            z-index: 100;
            pointer-events: auto;
        }
        
        /* Ensure table cells don't block button clicks */
        .table td {
            position: relative;
        }
        
        .table td .btn-group {
            position: relative;
            z-index: 101;
        }
        
        /* Modal Styles for Existing Patients List */
        .existing-patient-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            background-color: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .existing-patient-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .existing-patient-card.selected {
            border-color: #0d6efd;
            background-color: #e7f3ff;
            box-shadow: 0 2px 12px rgba(13, 110, 253, 0.3);
        }
        
        .existing-patient-card .patient-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 4px;
        }
        
        .existing-patient-card .patient-info {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 2px;
        }
        
        .existing-patient-card .patient-badge {
            margin-top: 8px;
        }
        
        .match-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .match-indicator.exact {
            background-color: #198754;
        }
        
        .match-indicator.partial {
            background-color: #ffc107;
        }
        
        .no-matches-message {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .no-matches-message i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-4" id="registrationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                                    <i class="bi bi-check-circle me-1"></i>Registered Account
                                    <span class="badge bg-success ms-1"><?php echo count($approved_requests); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                                    <i class="bi bi-clock-history me-1"></i>For Approval
                                    <span class="badge bg-warning text-dark ms-1"><?php echo count($pending_requests); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                                    <i class="bi bi-journal-text me-1"></i>Logs
                                    <span class="badge bg-info text-dark ms-1"><?php echo count($all_logs); ?></span>
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="registrationTabsContent">
                            <!-- Registered/Approved Tab -->
                            <div class="tab-pane fade show active" id="approved" role="tabpanel">
                                <?php if (empty($approved_requests)): ?>
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span>No approved registration requests found.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Phone</th>
                                                    <th>Patient Number</th>
                                                    <th>Patient Type</th>
                                                    <th>Account Status</th>
                                                    <th>Processed By</th>
                                                    <th>Processed Date</th>
                                                    <th>Admin Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($approved_requests as $request): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                                                        <td><?php echo htmlspecialchars(formatPhoneNumber($request['phone'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-primary">
                                                                <?php echo htmlspecialchars($request['patient_number'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo ($request['patient_type'] ?? 'New') === 'New' ? 'primary' : 'info'; ?>">
                                                                <?php echo htmlspecialchars($request['patient_type'] ?? 'New'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo ($request['account_status'] ?? '') === 'Active' ? 'success' : 'secondary'; ?>">
                                                                <?php echo htmlspecialchars($request['account_status'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(trim(($request['processed_by_name'] ?? '') . ' ' . ($request['processed_by_last_name'] ?? '')) ?: 'N/A'); ?></td>
                                                        <td><?php echo $request['processed_at'] ? date('M j, Y g:i A', strtotime($request['processed_at'])) : 'N/A'; ?></td>
                                                        <td><small class="text-muted"><?php echo htmlspecialchars($request['admin_notes'] ?? '—'); ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- For Approval Tab -->
                            <div class="tab-pane fade" id="pending" role="tabpanel">
                                <?php if (empty($pending_requests)): ?>
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span>No registration requests pending approval.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Phone</th>
                                                    <th>Patient Type</th>
                                                    <th>Registration Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_requests as $request): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                                                        <td><?php echo htmlspecialchars(formatPhoneNumber($request['phone'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $request['patient_type'] === 'New' ? 'primary' : 'info'; ?>">
                                                                <?php echo htmlspecialchars($request['patient_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-sm btn-success approve-btn" 
                                                                        data-request-id="<?php echo htmlspecialchars($request['id']); ?>"
                                                                        data-action="approve"
                                                                        onclick="return handleRegistrationAction(<?php echo htmlspecialchars($request['id']); ?>, 'approve', this);"
                                                                        title="Approve Registration">
                                                                    <i class="bi bi-check-circle me-1"></i> Approve
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger reject-btn" 
                                                                        data-request-id="<?php echo htmlspecialchars($request['id']); ?>"
                                                                        data-action="reject"
                                                                        onclick="return handleRegistrationAction(<?php echo htmlspecialchars($request['id']); ?>, 'reject', this);"
                                                                        title="Reject Registration">
                                                                    <i class="bi bi-x-circle me-1"></i> Reject
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Logs Tab -->
                            <div class="tab-pane fade" id="logs" role="tabpanel">
                                <?php if (empty($all_logs)): ?>
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span>No registration request logs available.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Phone</th>
                                                    <th>Status</th>
                                                    <th>Patient Type</th>
                                                    <th>Registration Date</th>
                                                    <th>Processed By</th>
                                                    <th>Processed Date</th>
                                                    <th>Admin Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_logs as $request): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                                                        <td><?php echo htmlspecialchars(formatPhoneNumber($request['phone'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $request['status'] === 'Approved' ? 'success' : 
                                                                    ($request['status'] === 'Rejected' ? 'danger' : 'warning'); 
                                                            ?>">
                                                                <?php echo htmlspecialchars($request['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $request['patient_type'] === 'New' ? 'primary' : 'info'; ?>">
                                                                <?php echo htmlspecialchars($request['patient_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                        <td><?php echo htmlspecialchars(trim(($request['processed_by_name'] ?? 'N/A') . ' ' . ($request['processed_by_last_name'] ?? '')) ?: 'N/A'); ?></td>
                                                        <td><?php echo $request['processed_at'] ? date('M j, Y g:i A', strtotime($request['processed_at'])) : 'N/A'; ?></td>
                                                        <td><small class="text-muted"><?php echo htmlspecialchars($request['admin_notes'] ?? '—'); ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-lg-down modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registration Request Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 0;">
                    <div class="row g-0" style="height: 70vh;">
                        <!-- Left Side: Existing Patients -->
                        <div class="col-md-5 border-end" style="background-color: #f8f9fa; display: flex; flex-direction: column; max-height: 70vh;">
                            <!-- Header + Back Button -->
                            <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><i class="bi bi-search me-2"></i>Existing Patients</h6>
                                    <small class="text-muted d-block">Select a patient to compare with this registration or create a new account.</small>
                                </div>
                                <button type="button"
                                        id="backToListBtn"
                                        class="btn btn-sm btn-outline-secondary"
                                        style="display: none;"
                                        onclick="showExistingPatientsList()">
                                    <i class="bi bi-arrow-left"></i> Back
                                </button>
                            </div>

                            <!-- List Section -->
                            <div id="existingPatientsList" class="p-3" style="flex: 1 1 auto; overflow-y: auto;">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary mb-3" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted">Searching for existing patients...</p>
                                </div>
                            </div>

                            <!-- Selected Patient Details Section (replaces list when a patient is clicked) -->
                            <div id="selectedPatientDetails" style="flex: 1 1 auto; overflow-y: auto; display: none;">
                                <div id="selectedPatientDetailsContent" class="p-3"></div>
                            </div>
                        </div>
                        
                        <!-- Right Side: Registration Request Details -->
                        <div class="col-md-7" style="overflow-y: auto; max-height: 70vh;">
                            <div class="p-3 border-bottom bg-light sticky-top">
                                <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Registration Request Details</h6>
                                <div id="selectedPatientIndicator" style="display: none;" class="mt-2">
                                    <div class="alert alert-info mb-0 py-2">
                                        <i class="bi bi-link-45deg me-2"></i>
                                        <strong>Existing patient selected:</strong> <span id="selectedPatientName"></span> - This registration will be linked to an existing account if approved.
                                    </div>
                                </div>
                            </div>
                            <div class="p-3">
                                <div id="requestDetails"></div>
                                <form id="actionForm" class="mt-3">
                                    <input type="hidden" id="requestId" name="request_id">
                                    <input type="hidden" id="actionType" name="action">
                                    <input type="hidden" id="selectedPatientId" name="existing_patient_id" value="">
                                    <div class="mb-3">
                                        <label class="form-label">Admin Notes</label>
                                        <textarea class="form-control" name="admin_notes" rows="3" 
                                                  placeholder="Add notes about your decision..."></textarea>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="rejectAction" style="display: none;">Reject</button>
                    <button type="button" class="btn btn-success" id="confirmAction">Approve</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Make functions globally available immediately
        function handleRegistrationAction(requestId, action, buttonElement) {
            console.log('handleRegistrationAction called:', requestId, action);
            
            // Ensure showAlert is available
            if (typeof showAlert === 'undefined') {
                console.error('showAlert function is not defined. Make sure footer.php is included.');
                alert('Error: showAlert function is not available. Please refresh the page.');
                return false;
            }
            
            if (!requestId) {
                if (typeof showAlert !== 'undefined') {
                    showAlert('Error: Request ID is missing', 'Error', 'error');
                } else {
                    alert('Error: Request ID is missing');
                }
                return false;
            }
            
            // Store original HTML before modifying
            let originalHtml = '';
            if (buttonElement) {
                originalHtml = buttonElement.innerHTML;
                buttonElement.disabled = true;
                buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
            }
            
            // Fetch request data via AJAX
            const formData = new FormData();
            formData.append('action', 'get_request_data');
            formData.append('request_id', requestId);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Restore button
                if (buttonElement && originalHtml) {
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = originalHtml;
                }
                
                if (data.success && data.request) {
                    if (typeof showRequestDetails === 'function') {
                        showRequestDetails(data.request, action);
                    } else {
                        showAlert('showRequestDetails function not found', 'Error', 'error');
                    }
                } else {
                    showAlert('Error loading request details: ' + (data.message || 'Unknown error'), 'Error', 'error');
                }
            })
            .catch(error => {
                // Restore button
                if (buttonElement && originalHtml) {
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = originalHtml;
                }
                console.error('Error:', error);
                showAlert('Error loading request details. Please try again.', 'Error', 'error');
            });
            
            return false;
        }
        
        // Also make it available on window for explicit access
        window.handleRegistrationAction = handleRegistrationAction;
        
        let currentRequest = null;
        let currentAction = '';

        // Helper function to generate patient details HTML
        function generatePatientDetailsHtml(data, sideLabel, isExisting = false) {
            // Parse additional data if it's a registration request
            let additionalData = {};
            let suffix = '';
            let civilStatus = '';
            let isSeniorCitizen = 0;
            let seniorCitizenId = '';
            let isPwd = 0;
            let pwdId = '';
            let emergencyRelationship = '';
            
            if (data.medical_history && !isExisting) {
                try {
                    additionalData = JSON.parse(data.medical_history);
                    suffix = additionalData.suffix || '';
                    civilStatus = additionalData.civil_status || '';
                    isSeniorCitizen = additionalData.is_senior_citizen || 0;
                    seniorCitizenId = additionalData.senior_citizen_id || '';
                    isPwd = additionalData.is_pwd || 0;
                    pwdId = additionalData.pwd_id || '';
                    emergencyRelationship = additionalData.emergency_contact_relationship || '';
                } catch (e) {
                    // If not JSON, ignore
                }
            } else if (isExisting) {
                // For existing patients, get data directly from patient record
                suffix = data.suffix || '';
                civilStatus = data.civil_status || '';
                isSeniorCitizen = data.is_senior_citizen || 0;
                seniorCitizenId = data.senior_citizen_id || '';
                isPwd = data.is_pwd || 0;
                pwdId = data.pwd_id || '';
                emergencyRelationship = data.relationship || '';
            }
            
            const dob = data.date_of_birth ? new Date(data.date_of_birth).toLocaleDateString() : 'Not provided';
            const regDate = data.created_at ? new Date(data.created_at).toLocaleString() : (isExisting ? 'N/A' : 'Not provided');
            const patientNumber = data.patient_number || 'N/A';
            
            return `
                <div class="card h-100">
                    <div class="card-header bg-${isExisting ? 'success' : 'primary'} text-white">
                        <h6 class="mb-0"><strong>${sideLabel}</strong></h6>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        ${isExisting ? `
                        <div class="mb-3">
                            <p class="mb-1"><strong>Patient Number:</strong></p>
                            <p class="ms-3"><span class="badge bg-primary">${patientNumber}</span></p>
                        </div>
                        ` : ''}
                        <h6 class="text-primary border-bottom pb-2 mb-3">Personal Information</h6>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Full Name:</strong></p>
                            <p class="ms-3">${data.first_name || ''} ${data.middle_name || ''} ${data.last_name || ''} ${suffix}</p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Date of Birth:</strong></p>
                            <p class="ms-3">${dob}</p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Sex:</strong></p>
                            <p class="ms-3">${data.sex || 'Not specified'}</p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Civil Status:</strong></p>
                            <p class="ms-3">${civilStatus || 'Not specified'}</p>
                        </div>
                        
                        ${isSeniorCitizen ? `
                        <div class="mb-3">
                            <p class="mb-1"><strong>Senior Citizen:</strong></p>
                            <p class="ms-3"><span class="badge bg-info">Yes</span> ${seniorCitizenId ? `- ID: ${seniorCitizenId}` : ''}</p>
                        </div>
                        ` : ''}
                        
                        ${isPwd ? `
                        <div class="mb-3">
                            <p class="mb-1"><strong>Person with Disability (PWD):</strong></p>
                            <p class="ms-3"><span class="badge bg-warning text-dark">Yes</span> ${pwdId ? `- ID: ${pwdId}` : ''}</p>
                        </div>
                        ` : ''}
                        
                        <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Contact Information</h6>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Phone:</strong></p>
                            <p class="ms-3">${data.phone || 'Not provided'}</p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Email:</strong></p>
                            <p class="ms-3">${data.email || 'Not provided'}</p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Address:</strong></p>
                            <p class="ms-3">${data.address || 'Not provided'}</p>
                        </div>
                        
                        <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Emergency Contact</h6>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Name:</strong></p>
                            <p class="ms-3">${data.emergency_contact_name || 'Not provided'}</p>
                        </div>
                        ${emergencyRelationship ? `
                        <div class="mb-3">
                            <p class="mb-1"><strong>Relationship:</strong></p>
                            <p class="ms-3">${emergencyRelationship}</p>
                        </div>
                        ` : ''}
                        <div class="mb-3">
                            <p class="mb-1"><strong>Phone:</strong></p>
                            <p class="ms-3">${data.emergency_contact_phone || 'Not provided'}</p>
                        </div>
                        
                        <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Medical Information</h6>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Blood Type:</strong></p>
                            <p class="ms-3">${data.blood_type || 'Not specified'}</p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Allergies:</strong></p>
                            <p class="ms-3">${data.allergies || 'None reported'}</p>
                        </div>
                        
                        ${!isExisting ? `
                        <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Registration Information</h6>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Patient Type:</strong></p>
                            <p class="ms-3"><span class="badge bg-${data.patient_type === 'New' ? 'primary' : 'info'}">${data.patient_type || 'New'}</span></p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Registration Date:</strong></p>
                            <p class="ms-3">${regDate}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Function to render existing patients list
        function renderExistingPatientsList(matches, requestData) {
            const listContainer = document.getElementById('existingPatientsList');
            
            if (!matches || matches.length === 0) {
                listContainer.innerHTML = `
                    <div class="no-matches-message">
                        <i class="bi bi-check-circle"></i>
                        <h6 class="mt-3">No Existing Patients Found</h6>
                        <p>No matching patient records found in the database.</p>
                        <p class="small">This will be registered as a new patient.</p>
                    </div>
                `;
                return;
            }
            
            let html = `<div class="mb-2"><small class="text-muted">Found <strong>${matches.length}</strong> potential match(es)</small></div>`;
            
            matches.forEach((patient, index) => {
                const dob = patient.formatted_dob || (patient.date_of_birth ? new Date(patient.date_of_birth).toLocaleDateString() : 'N/A');
                const matchScore = patient.match_score || 5;
                const matchClass = matchScore <= 2 ? 'exact' : 'partial';
                const matchText = matchScore <= 2 ? 'Exact Match' : 'Possible Match';
                
                html += `
                    <div class="existing-patient-card" data-patient-id="${patient.id}" onclick="selectExistingPatient(${patient.id}, this)">
                        <div class="patient-name">
                            <span class="match-indicator ${matchClass}"></span>
                            ${patient.first_name} ${patient.middle_name || ''} ${patient.last_name} ${patient.suffix || ''}
                        </div>
                        <div class="patient-info">
                            <i class="bi bi-person-badge me-1"></i> Patient #: <strong>${patient.patient_number || 'N/A'}</strong>
                        </div>
                        <div class="patient-info">
                            <i class="bi bi-calendar me-1"></i> DOB: ${dob}
                        </div>
                        ${patient.email ? `<div class="patient-info"><i class="bi bi-envelope me-1"></i> ${patient.email}</div>` : ''}
                        ${patient.phone ? `<div class="patient-info"><i class="bi bi-telephone me-1"></i> ${patient.phone}</div>` : ''}
                        <div class="patient-badge">
                            <span class="badge bg-${matchScore <= 2 ? 'success' : 'warning'} text-dark">${matchText}</span>
                        </div>
                    </div>
                `;
            });
            
            listContainer.innerHTML = html;
            
            // Highlight any previously selected patient
            const selectedId = document.getElementById('selectedPatientId').value;
            if (selectedId) {
                document.querySelectorAll('.existing-patient-card').forEach(card => {
                    if (card.getAttribute('data-patient-id') === selectedId) {
                        card.classList.add('selected');
                    }
                });
            }
        }
        
        // Function to select an existing patient and show their details
        function selectExistingPatient(patientId, cardElement) {
            const currentSelectedId = document.getElementById('selectedPatientId').value;
            
            // If clicking the same patient, deselect them
            if (currentSelectedId && currentSelectedId == patientId) {
                // Deselect
                document.getElementById('selectedPatientId').value = '';
                if (cardElement) {
                    cardElement.classList.remove('selected');
                }
                
                // Hide details and show list
                const detailsSection = document.getElementById('selectedPatientDetails');
                const listContainer = document.getElementById('existingPatientsList');
                const backBtn = document.getElementById('backToListBtn');
                const indicator = document.getElementById('selectedPatientIndicator');
                const headerContent = document.querySelector('.col-md-5 .bg-light h6');
                
                if (detailsSection) detailsSection.style.display = 'none';
                if (listContainer) listContainer.style.display = 'block';
                if (backBtn) backBtn.style.display = 'none';
                if (indicator) indicator.style.display = 'none';
                if (headerContent) {
                    headerContent.innerHTML = '<i class="bi bi-search me-2"></i>Existing Patients';
                }
                return;
            }
            
            // Remove selection from all cards
            document.querySelectorAll('.existing-patient-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            if (cardElement) {
                cardElement.classList.add('selected');
            }
            
            // Store selected patient ID
            document.getElementById('selectedPatientId').value = patientId;
            
            const detailsSection = document.getElementById('selectedPatientDetails');
            const detailsContent = document.getElementById('selectedPatientDetailsContent');
            const listContainer = document.getElementById('existingPatientsList');
            const backBtn = document.getElementById('backToListBtn');
            
            // Hide list, show details and back button
            if (listContainer) listContainer.style.display = 'none';
            if (detailsSection) detailsSection.style.display = 'block';
            if (backBtn) backBtn.style.display = 'inline-block';
            
            // Show loading state in details section
            detailsContent.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted">Loading patient details...</p>
                </div>
            `;
            
            // Fetch full patient details
            const formData = new FormData();
            formData.append('action', 'get_existing_patient');
            formData.append('existing_patient_id', patientId);
            
            fetch('', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.patient) {
                    // Display full patient details using the existing helper function
                    const detailsHtml = generatePatientDetailsHtml(data.patient, 'Existing Patient Details', true);
                    detailsContent.innerHTML = detailsHtml;
                    
                    // Update header to show selection status
                    const headerContent = document.querySelector('.col-md-5 .bg-light h6');
                    if (headerContent) {
                        headerContent.innerHTML = '<i class="bi bi-person-check me-2"></i>Existing Patient Selected';
                    }
                    
                    // Show indicator on right side
                    const indicator = document.getElementById('selectedPatientIndicator');
                    const patientNameSpan = document.getElementById('selectedPatientName');
                    if (indicator) {
                        const fullName = `${data.patient.first_name || ''} ${data.patient.middle_name || ''} ${data.patient.last_name || ''} ${data.patient.suffix || ''}`.trim();
                        if (patientNameSpan) {
                            patientNameSpan.textContent = fullName;
                        }
                        indicator.style.display = 'block';
                    }
                } else {
                    detailsContent.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Unable to load patient details. Please try again.
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching patient details:', error);
                detailsContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle me-2"></i>
                        An error occurred while loading patient details.
                    </div>
                `;
            });
        }
        
        // Function to go back to the list view from details
        function showExistingPatientsList() {
            const listContainer = document.getElementById('existingPatientsList');
            const detailsSection = document.getElementById('selectedPatientDetails');
            const backBtn = document.getElementById('backToListBtn');
            
            if (detailsSection) detailsSection.style.display = 'none';
            if (listContainer) listContainer.style.display = 'block';
            if (backBtn) backBtn.style.display = 'none';
            
            // Update header text
            const headerContent = document.querySelector('.col-md-5 .bg-light h6');
            if (headerContent) {
                headerContent.innerHTML = '<i class="bi bi-search me-2"></i>Existing Patients';
            }
            
            // Highlight the selected card if there's a selected patient ID
            const selectedId = document.getElementById('selectedPatientId').value;
            if (selectedId) {
                document.querySelectorAll('.existing-patient-card').forEach(card => {
                    if (card.getAttribute('data-patient-id') === selectedId) {
                        card.classList.add('selected');
                    } else {
                        card.classList.remove('selected');
                    }
                });
                
                // Keep the indicator visible if patient is still selected
                const indicator = document.getElementById('selectedPatientIndicator');
                if (indicator) {
                    indicator.style.display = 'block';
                }
            } else {
                // Hide indicator if no patient selected
                const indicator = document.getElementById('selectedPatientIndicator');
                if (indicator) {
                    indicator.style.display = 'none';
                }
            }
        }
        
        // Make functions globally available
        window.selectExistingPatient = selectExistingPatient;
        window.showExistingPatientsList = showExistingPatientsList;
        
        // Function to search for existing patients
        function searchExistingPatients(request) {
            const formData = new FormData();
            formData.append('action', 'search_existing_patients');
            formData.append('first_name', request.first_name || '');
            formData.append('last_name', request.last_name || '');
            formData.append('date_of_birth', request.date_of_birth || '');
            formData.append('email', request.email || '');
            formData.append('phone', request.phone || '');
            
            fetch('', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderExistingPatientsList(data.matches || [], request);
                } else {
                    document.getElementById('existingPatientsList').innerHTML = `
                        <div class="no-matches-message">
                            <i class="bi bi-exclamation-triangle"></i>
                            <h6 class="mt-3">Error</h6>
                            <p>An error occurred while searching for existing patients.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error searching existing patients:', error);
                document.getElementById('existingPatientsList').innerHTML = `
                    <div class="no-matches-message">
                        <i class="bi bi-exclamation-triangle"></i>
                        <h6 class="mt-3">Error</h6>
                        <p>An error occurred while searching for existing patients.</p>
                    </div>
                `;
            });
        }

        function showRequestDetails(request, action) {
            currentRequest = request;
            currentAction = action;
            
            // Reset selected patient and UI state
            const selectedIdInput = document.getElementById('selectedPatientId');
            const detailsSection = document.getElementById('selectedPatientDetails');
            const listContainer = document.getElementById('existingPatientsList');
            const backBtn = document.getElementById('backToListBtn');
            const indicator = document.getElementById('selectedPatientIndicator');
            
            if (selectedIdInput) selectedIdInput.value = '';
            if (detailsSection) detailsSection.style.display = 'none';
            if (listContainer) listContainer.style.display = 'block';
            if (backBtn) backBtn.style.display = 'none';
            if (indicator) indicator.style.display = 'none';
            
            // Reset header text
            const headerContent = document.querySelector('.col-md-5 .bg-light h6');
            if (headerContent) {
                headerContent.innerHTML = '<i class="bi bi-search me-2"></i>Existing Patients';
            }
            
            // Clear any previous selections
            document.querySelectorAll('.existing-patient-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Search for existing patients immediately
            searchExistingPatients(request);
            
            // Parse additional data from medical_history JSON
            let additionalData = {};
            if (request.medical_history) {
                try {
                    additionalData = JSON.parse(request.medical_history);
                } catch (e) {
                    // If not JSON, ignore
                }
            }
            
            const suffix = additionalData.suffix || '';
            const civilStatus = additionalData.civil_status || '';
            const isSeniorCitizen = additionalData.is_senior_citizen || 0;
            const seniorCitizenId = additionalData.senior_citizen_id || '';
            const isPwd = additionalData.is_pwd || 0;
            const pwdId = additionalData.pwd_id || '';
            const emergencyRelationship = additionalData.emergency_contact_relationship || '';
            const chiefComplaint = additionalData.chief_complaint || '';
            
            // Format date of birth
            const dob = request.date_of_birth ? new Date(request.date_of_birth).toLocaleDateString() : 'Not provided';
            
            // Format registration date
            const regDate = request.created_at ? new Date(request.created_at).toLocaleString() : 'Not provided';
            
            let detailsHtml;
            
            // Generate request details HTML for right side
            // For existing patients, only show: First Name, Middle Name, Last Name, Suffix, Date of Birth, Email Address
            if (request.patient_type === 'Existing') {
                detailsHtml = `
                    <h6 class="text-primary border-bottom pb-2 mb-3">Personal Information</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>First Name:</strong></p>
                        <p class="ms-3">${request.first_name || ''}</p>
                    </div>
                    ${request.middle_name ? `
                    <div class="mb-3">
                        <p class="mb-1"><strong>Middle Name:</strong></p>
                        <p class="ms-3">${request.middle_name}</p>
                    </div>
                    ` : ''}
                    <div class="mb-3">
                        <p class="mb-1"><strong>Last Name:</strong></p>
                        <p class="ms-3">${request.last_name || ''}</p>
                    </div>
                    ${suffix ? `
                    <div class="mb-3">
                        <p class="mb-1"><strong>Suffix:</strong></p>
                        <p class="ms-3">${suffix}</p>
                    </div>
                    ` : ''}
                    <div class="mb-3">
                        <p class="mb-1"><strong>Date of Birth:</strong></p>
                        <p class="ms-3">${dob}</p>
                    </div>
                    
                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Contact Information</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Email Address:</strong></p>
                        <p class="ms-3">${request.email || 'Not provided'}</p>
                    </div>
                    
                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Registration Information</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Patient Type:</strong></p>
                        <p class="ms-3"><span class="badge bg-info">${request.patient_type || 'Existing'}</span></p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Registration Date:</strong></p>
                        <p class="ms-3">${regDate}</p>
                    </div>
                `;
            } else {
                // For new patients, show all fields
                detailsHtml = `
                    <h6 class="text-primary border-bottom pb-2 mb-3">Personal Information</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Full Name:</strong></p>
                        <p class="ms-3">${request.first_name || ''} ${request.middle_name || ''} ${request.last_name || ''} ${suffix}</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Date of Birth:</strong></p>
                        <p class="ms-3">${dob}</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Sex:</strong></p>
                        <p class="ms-3">${request.sex || 'Not specified'}</p>
                    </div>
                    ${civilStatus ? `
                    <div class="mb-3">
                        <p class="mb-1"><strong>Civil Status:</strong></p>
                        <p class="ms-3">${civilStatus}</p>
                    </div>
                    ` : ''}
                    ${isSeniorCitizen ? `
                    <div class="mb-3">
                        <p class="mb-1"><strong>Senior Citizen:</strong></p>
                        <p class="ms-3"><span class="badge bg-info">Yes</span> ${seniorCitizenId ? `- ID: ${seniorCitizenId}` : ''}</p>
                    </div>
                    ` : ''}
                    ${isPwd ? `
                    <div class="mb-3">
                        <p class="mb-1"><strong>Person with Disability (PWD):</strong></p>
                        <p class="ms-3"><span class="badge bg-warning text-dark">Yes</span> ${pwdId ? `- ID: ${pwdId}` : ''}</p>
                    </div>
                    ` : ''}
                    
                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Contact Information</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Phone:</strong></p>
                        <p class="ms-3">${request.phone || 'Not provided'}</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Email:</strong></p>
                        <p class="ms-3">${request.email || 'Not provided'}</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Address:</strong></p>
                        <p class="ms-3">${request.address || 'Not provided'}</p>
                    </div>
                    
                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Emergency Contact</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Name:</strong></p>
                        <p class="ms-3">${request.emergency_contact_name || 'Not provided'}</p>
                    </div>
                    ${emergencyRelationship ? `
                    <div class="mb-3">
                        <p class="mb-1"><strong>Relationship:</strong></p>
                        <p class="ms-3">${emergencyRelationship}</p>
                    </div>
                    ` : ''}
                    <div class="mb-3">
                        <p class="mb-1"><strong>Phone:</strong></p>
                        <p class="ms-3">${request.emergency_contact_phone || 'Not provided'}</p>
                    </div>
                    
                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Medical Information</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Blood Type:</strong></p>
                        <p class="ms-3">${request.blood_type || 'Not specified'}</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Allergies:</strong></p>
                        <p class="ms-3">${request.allergies || 'None reported'}</p>
                    </div>
                    ${chiefComplaint ? `
                    <div class="mb-3">
                        <p class="mb-1"><strong>Chief Complaint:</strong></p>
                        <p class="ms-3" style="white-space: pre-wrap;">${chiefComplaint}</p>
                    </div>
                    ` : ''}
                    
                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Registration Information</h6>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Patient Type:</strong></p>
                        <p class="ms-3"><span class="badge bg-primary">${request.patient_type || 'New'}</span></p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Registration Date:</strong></p>
                        <p class="ms-3">${regDate}</p>
                    </div>
                `;
            }
            
            document.getElementById('requestDetails').innerHTML = detailsHtml;
            document.getElementById('requestId').value = request.id;
            document.getElementById('actionType').value = action;
            
            // Show/hide buttons based on action
            const confirmBtn = document.getElementById('confirmAction');
            const rejectBtn = document.getElementById('rejectAction');
            
            if (action === 'approve') {
                confirmBtn.style.display = 'inline-block';
                confirmBtn.textContent = 'Approve';
                confirmBtn.className = 'btn btn-success';
                rejectBtn.style.display = 'none';
                
                // Set default message in Admin Notes field
                const adminNotesTextarea = document.querySelector('textarea[name="admin_notes"]');
                if (adminNotesTextarea) {
                    adminNotesTextarea.value = 'You can now log in to the Mhavis Patient Portal and make an appointment anytime, anywhere!';
                }
            } else {
                confirmBtn.style.display = 'none';
                rejectBtn.style.display = 'inline-block';
                rejectBtn.textContent = 'Reject';
                rejectBtn.className = 'btn btn-danger';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('requestModal'));
            modal.show();
            
            // Attach confirm handler after modal is shown
            setTimeout(function() {
                attachConfirmHandler();
            }, 300);
        }
        
        // Make selectExistingPatient globally available
        window.selectExistingPatient = selectExistingPatient;

        // Attach confirm action handler when DOM is ready
        function attachConfirmHandler() {
            const confirmBtn = document.getElementById('confirmAction');
            const rejectBtn = document.getElementById('rejectAction');
            
            // Handle approve button - remove old listener if exists, then add new one
            if (confirmBtn) {
                // Remove existing listener by cloning (clean way to remove all listeners)
                const newConfirmBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                
                // Add new listener
                newConfirmBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    submitAction('approve', this);
                });
            }
            
            // Handle reject button - remove old listener if exists, then add new one
            if (rejectBtn) {
                // Remove existing listener by cloning (clean way to remove all listeners)
                const newRejectBtn = rejectBtn.cloneNode(true);
                rejectBtn.parentNode.replaceChild(newRejectBtn, rejectBtn);
                
                // Add new listener
                newRejectBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    submitAction('reject', this);
                });
            }
        }
        
        // Function to submit action (approve or reject)
        function submitAction(action, buttonElement) {
            console.log('submitAction called:', action);
            
            const form = document.getElementById('actionForm');
            if (!form) {
                console.error('Form not found');
                showAlert('Form not found', 'Error', 'error');
                return;
            }
            
            const formData = new FormData(form);
            const requestId = document.getElementById('requestId').value;
            const selectedPatientId = document.getElementById('selectedPatientId').value;
            
            console.log('Request ID:', requestId, 'Action:', action, 'Selected Patient ID:', selectedPatientId);
            
            // Update action type
            document.getElementById('actionType').value = action;
            formData.set('action', action);
            formData.set('request_id', requestId);
            
            // Include existing_patient_id if selected and action is approve
            if (action === 'approve' && selectedPatientId) {
                formData.set('existing_patient_id', selectedPatientId);
            }
            
            if (!requestId || !action) {
                console.error('Missing request ID or action type', { requestId, action });
                showAlert('Missing request ID or action type', 'Error', 'error');
                return;
            }
            
            // Disable both buttons and show loading state
            // Get fresh references to buttons (in case they were cloned)
            const confirmBtn = document.getElementById('confirmAction');
            const rejectBtn = document.getElementById('rejectAction');
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.style.pointerEvents = 'none';
            }
            if (rejectBtn) {
                rejectBtn.disabled = true;
                rejectBtn.style.pointerEvents = 'none';
            }
            
            // Store original state of clicked button
            const originalHtml = buttonElement.innerHTML;
            const originalDisabled = buttonElement.disabled;
            buttonElement.disabled = true;
            buttonElement.style.pointerEvents = 'none';
            buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';

            console.log('Sending request...');
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(async (response) => {
                const contentType = response.headers.get('content-type') || '';
                if (!response.ok) {
                    let errMsg = 'Request failed';
                    if (contentType.includes('application/json')) {
                        const json = await response.json().catch(() => null);
                        if (json && json.message) errMsg = json.message;
                    } else {
                        errMsg = await response.text();
                    }
                    throw new Error(errMsg);
                }
                if (contentType.includes('application/json')) {
                    return response.json();
                }
                return { success: true };
            })
            .then((data) => {
                console.log('Response received:', data);
                
                if (!data.success) {
                    throw new Error(data.message || 'Request failed');
                }
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('requestModal'));
                if (modal) modal.hide();
                
                // Show success modal based on action type
                const actionType = action === 'approve' ? 'approved' : 'rejected';
                const icon = action === 'approve' ? 'success' : 'info';
                const title = action === 'approve' ? 'Registration Approved' : 'Registration Rejected';
                
                showAlert(
                    data.message || `Patient registration has been ${actionType} successfully.`,
                    title,
                    icon
                ).then(() => {
                    // Reload page to show updated data after user closes the modal
                    window.location.reload();
                });
            })
            .catch(error => {
                console.error('Error:', error);
                // Get fresh references in case buttons were recreated
                const confirmBtn = document.getElementById('confirmAction');
                const rejectBtn = document.getElementById('rejectAction');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.style.pointerEvents = 'auto';
                }
                if (rejectBtn) {
                    rejectBtn.disabled = false;
                    rejectBtn.style.pointerEvents = 'auto';
                }
                if (buttonElement) {
                    buttonElement.disabled = originalDisabled;
                    buttonElement.style.pointerEvents = 'auto';
                    buttonElement.innerHTML = originalHtml;
                }
                showAlert(error.message || 'An error occurred while processing the request. Please try again.', 'Error', 'error');
            });
        }

        // Function to handle button clicks (kept for backward compatibility with event delegation)
        function handleRequestAction(requestId, action) {
            // Use the same function as onclick handlers
            if (window.handleRegistrationAction) {
                const buttons = document.querySelectorAll(`[data-request-id="${requestId}"]`);
                const button = buttons.length > 0 ? buttons[0] : null;
                return window.handleRegistrationAction(requestId, action, button);
            }
            
            // Fallback if window.handleRegistrationAction not available
            if (!requestId) {
                showAlert('Error: Request ID is missing', 'Error', 'error');
                return;
            }
            
            // Fetch request data via AJAX
            const formData = new FormData();
            formData.append('action', 'get_request_data');
            formData.append('request_id', requestId);
            
            // Show loading state on all buttons for this request
            const buttons = document.querySelectorAll(`[data-request-id="${requestId}"]`);
            const originalHtmls = [];
            buttons.forEach(btn => {
                originalHtmls.push(btn.innerHTML);
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
            });
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Restore buttons
                buttons.forEach((btn, index) => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtmls[index];
                });
                
                if (data.success && data.request) {
                    if (typeof showRequestDetails === 'function') {
                        showRequestDetails(data.request, action);
                    } else {
                        showAlert('showRequestDetails function not found', 'Error', 'error');
                    }
                } else {
                    showAlert('Error loading request details: ' + (data.message || 'Unknown error'), 'Error', 'error');
                }
            })
            .catch(error => {
                // Restore buttons
                buttons.forEach((btn, index) => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtmls[index];
                });
                console.error('Error:', error);
                showAlert('Error loading request details. Please try again.', 'Error', 'error');
            });
        }
        
        // Make handleRequestAction globally available too
        window.handleRequestAction = handleRequestAction;

        // Event delegation as backup AND direct event listeners when tab is shown
        document.addEventListener('DOMContentLoaded', function() {
            // Attach confirm handler
            attachConfirmHandler();
            
            // Also attach when modal is shown (in case it's recreated)
            const requestModal = document.getElementById('requestModal');
            if (requestModal) {
                requestModal.addEventListener('shown.bs.modal', function() {
                    attachConfirmHandler();
                });
            }
            
            // Function to attach direct event listeners to buttons
            function attachButtonListeners() {
                const approveButtons = document.querySelectorAll('.approve-btn:not([data-listener-attached])');
                const rejectButtons = document.querySelectorAll('.reject-btn:not([data-listener-attached])');
                
                approveButtons.forEach(function(btn) {
                    // Mark as having listener attached to avoid duplicate processing
                    btn.setAttribute('data-listener-attached', 'true');
                    
                    // Add click listener (onclick handlers are preserved and will also fire)
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const requestId = this.getAttribute('data-request-id');
                        const action = this.getAttribute('data-action') || 'approve';
                        console.log('Approve button clicked (via addEventListener):', requestId);
                        if (requestId && window.handleRegistrationAction) {
                            window.handleRegistrationAction(requestId, action, this);
                        }
                        return false;
                    });
                });
                
                rejectButtons.forEach(function(btn) {
                    // Mark as having listener attached to avoid duplicate processing
                    btn.setAttribute('data-listener-attached', 'true');
                    
                    // Add click listener (onclick handlers are preserved and will also fire)
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const requestId = this.getAttribute('data-request-id');
                        const action = this.getAttribute('data-action') || 'reject';
                        console.log('Reject button clicked (via addEventListener):', requestId);
                        if (requestId && window.handleRegistrationAction) {
                            window.handleRegistrationAction(requestId, action, this);
                        }
                        return false;
                    });
                });
                
                console.log('Direct event listeners attached to', approveButtons.length, 'approve and', rejectButtons.length, 'reject buttons');
            }
            
            // Attach listeners immediately
            attachButtonListeners();
            
            // Re-attach when pending tab is shown (Bootstrap tab event)
            const pendingTab = document.getElementById('pending-tab');
            if (pendingTab) {
                pendingTab.addEventListener('shown.bs.tab', function() {
                    setTimeout(attachButtonListeners, 100);
                });
            }
            
            // Also use event delegation as ultimate backup
            document.addEventListener('click', function(e) {
                const approveBtn = e.target.closest('.approve-btn');
                const rejectBtn = e.target.closest('.reject-btn');
                
                if (approveBtn && !approveBtn.disabled && approveBtn.hasAttribute('data-request-id')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const requestId = approveBtn.getAttribute('data-request-id');
                    const action = approveBtn.getAttribute('data-action') || 'approve';
                    console.log('Approve button clicked (via delegation backup):', requestId);
                    if (requestId && window.handleRegistrationAction) {
                        window.handleRegistrationAction(requestId, action, approveBtn);
                    }
                    return false;
                }
                
                if (rejectBtn && !rejectBtn.disabled && rejectBtn.hasAttribute('data-request-id')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const requestId = rejectBtn.getAttribute('data-request-id');
                    const action = rejectBtn.getAttribute('data-action') || 'reject';
                    console.log('Reject button clicked (via delegation backup):', requestId);
                    if (requestId && window.handleRegistrationAction) {
                        window.handleRegistrationAction(requestId, action, rejectBtn);
                    }
                    return false;
                }
            }, true); // Use capture phase
            
            console.log('Button event handlers initialized (onclick + addEventListener + delegation)');
        });

    </script>
</body>
</html>
