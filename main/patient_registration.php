<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';

// AJAX handler for fetching patient data removed - Patient ID no longer required for Existing Patient type

$error_message = '';
$success_message = '';
// Flag to indicate when a New patient tried to register with an already-used email
$email_exists_for_new_patient = false;

// Initialize form variables for display
$first_name = $last_name = $middle_name = $suffix = $date_of_birth = $sex = $civil_status = '';
$is_senior_citizen = $is_pwd = 0;
$senior_citizen_id = $pwd_id = $phone = $email = $address = '';
$emergency_contact_name = $emergency_contact_relationship = $emergency_contact_phone = '';
$patient_type = '';
$blood_type = $allergies = $chief_complaint = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $middle_name = htmlspecialchars(trim($_POST['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $suffix = htmlspecialchars(trim($_POST['suffix'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $civil_status = htmlspecialchars(trim($_POST['civil_status'] ?? ''), ENT_QUOTES, 'UTF-8');
    $is_senior_citizen = isset($_POST['is_senior_citizen']) ? 1 : 0;
    $senior_citizen_id = htmlspecialchars(trim($_POST['senior_citizen_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
    $pwd_id = htmlspecialchars(trim($_POST['pwd_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(trim($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8');
    // Address components for server-side validation
    $region = htmlspecialchars(trim($_POST['region'] ?? ''), ENT_QUOTES, 'UTF-8');
    $province = htmlspecialchars(trim($_POST['province'] ?? ''), ENT_QUOTES, 'UTF-8');
    $city = htmlspecialchars(trim($_POST['city'] ?? ''), ENT_QUOTES, 'UTF-8');
    $barangay = htmlspecialchars(trim($_POST['barangay'] ?? ''), ENT_QUOTES, 'UTF-8');
    $emergency_contact_name = htmlspecialchars(trim($_POST['emergency_contact_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $emergency_contact_relationship = htmlspecialchars(trim($_POST['emergency_contact_relationship'] ?? ''), ENT_QUOTES, 'UTF-8');
    $emergency_contact_phone = htmlspecialchars(trim($_POST['emergency_contact_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $existing_patient_id = $_POST['existing_patient_id'] ?? null;
    $blood_type = htmlspecialchars(trim($_POST['blood_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $allergies = htmlspecialchars(trim($_POST['allergies'] ?? ''), ENT_QUOTES, 'UTF-8');
    $chief_complaint = htmlspecialchars(trim($_POST['chief_complaint'] ?? ''), ENT_QUOTES, 'UTF-8');
	// Auto-generate a secure temporary password for the patient; sent upon approval
	// Generate 12-char password with letters and numbers
	$bytes = random_bytes(9); // 12 chars after base64url trimming non-alnum
	$base = rtrim(strtr(base64_encode($bytes), '+/', 'AZ'), '='); // ensure URL-safe
	// Filter to alnum and ensure length 12
	$generated_password = substr(preg_replace('/[^A-Za-z0-9]/', '', $base), 0, 12);
	if (strlen($generated_password) < 12) {
		$generated_password = strtoupper(bin2hex(random_bytes(6))); // fallback 12 hex chars
	}
	$password = $generated_password;
	$confirm_password = $generated_password;

    // Combine +63 prefix with the input (which should be 10 digits)
    $phone = !empty($phone) ? '+63' . preg_replace('/[^\d]/', '', $phone) : '';
    $emergency_contact_phone = !empty($emergency_contact_phone) ? '+63' . preg_replace('/[^\d]/', '', $emergency_contact_phone) : '';
    
    // Handle file upload for ID attachment (if Senior Citizen or PWD - only for new patients)
    $id_attachment_path = '';
    $patient_type = $_POST['patient_type'] ?? '';
    // Only process file upload for new patients (Senior/PWD fields are only visible for new patients)
    if ($patient_type === 'New' && ($is_senior_citizen || $is_pwd) && isset($_FILES['id_attachment']) && $_FILES['id_attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/patient_id_attachments/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        $fileName = $_FILES['id_attachment']['name'];
        $fileType = $_FILES['id_attachment']['type'];
        $fileSize = $_FILES['id_attachment']['size'];
        $tmpName = $_FILES['id_attachment']['tmp_name'];
        
        // Validate file type
        if (!in_array($fileType, $allowedTypes)) {
            $error_message = "Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.";
        }
        // Validate file size
        elseif ($fileSize > $maxFileSize) {
            $error_message = "File size too large. Maximum size is 5MB.";
        } else {
            // Generate unique filename
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $uniqueFileName;
            
            // Move uploaded file
            if (move_uploaded_file($tmpName, $filePath)) {
                $id_attachment_path = $filePath;
            } else {
                $error_message = "Failed to upload file. Please try again.";
            }
        }
    } elseif ($patient_type === 'New' && ($is_senior_citizen || $is_pwd) && (!isset($_FILES['id_attachment']) || $_FILES['id_attachment']['error'] !== UPLOAD_ERR_OK)) {
        // Validate that file is required when Senior/PWD is checked (only for new patients)
        if (!isset($_FILES['id_attachment']) || $_FILES['id_attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            $error_message = "ID attachment is required for Senior Citizen or PWD registration. Please upload a picture of yourself holding your ID or a clear picture/scan of your ID card.";
        } elseif ($_FILES['id_attachment']['error'] !== UPLOAD_ERR_OK) {
            $error_message = "Error uploading file. Please try again.";
        }
    }
    
    // Helper function to validate name fields (no numbers allowed)
    // Note: Empty values are allowed (middle_name is optional)
    function isValidName($name) {
        if (empty($name)) return true; // Empty is allowed for optional fields like middle_name
        // Allow letters, spaces, hyphens, apostrophes, and periods (common in names)
        // Reject if contains any digits
        return !preg_match('/\d/', $name);
    }
    
    // Validation
    if (empty($patient_type)) {
        $error_message = "Please select a patient type.";
    } elseif ($patient_type === 'Existing') {
        // For existing patients, only validate: first_name, last_name, date_of_birth, email
        if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($email)) {
            $error_message = "Please fill in all required fields (First Name, Last Name, Date of Birth, and Email Address).";
        } elseif (!isValidName($first_name)) {
            $error_message = "First name cannot contain numbers. Please enter a valid name.";
        } elseif (!isValidName($last_name)) {
            $error_message = "Last name cannot contain numbers. Please enter a valid name.";
        } elseif (!empty($middle_name) && !isValidName($middle_name)) {
            $error_message = "Middle name cannot contain numbers. Please enter a valid name.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Validation passed for existing patient
        }
    } else {
        // For new patients, validate all fields
        if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($sex) || 
            empty($civil_status) || empty($phone) || empty($email) || empty($address) || 
            empty($emergency_contact_name) || empty($emergency_contact_relationship) || 
            empty($emergency_contact_phone)) {
            $error_message = "Please fill in all required fields.";
        } elseif (empty($region) || empty($province) || empty($city) || empty($barangay)) {
            // Server-side validation for address components
            $error_message = "Please select all address fields (Region, Province, City/Municipality, and Barangay).";
        } elseif (empty($address)) {
            // Fallback: if address is empty but components are selected, build it from components
            if (!empty($region) && !empty($province) && !empty($city) && !empty($barangay)) {
                $address = "{$barangay}, {$city}, {$province}, {$region}";
            } else {
                $error_message = "Please complete the address information.";
            }
        } elseif (!isValidName($first_name)) {
            $error_message = "First name cannot contain numbers. Please enter a valid name.";
        } elseif (!isValidName($last_name)) {
            $error_message = "Last name cannot contain numbers. Please enter a valid name.";
        } elseif (!empty($middle_name) && !isValidName($middle_name)) {
            $error_message = "Middle name cannot contain numbers. Please enter a valid name.";
        } elseif (!isValidName($emergency_contact_name)) {
            $error_message = "Emergency contact name cannot contain numbers. Please enter a valid name.";
        } elseif (empty($blood_type)) {
            $error_message = "Blood type is required for new patients.";
        } elseif ($is_senior_citizen && $is_pwd) {
            $error_message = "Senior Citizen and PWD cannot both be checked. Please select only one.";
        } elseif ($is_senior_citizen && empty($senior_citizen_id)) {
            $error_message = "Senior Citizen ID is required when Senior Citizen is checked.";
        } elseif ($is_senior_citizen && !empty($date_of_birth)) {
            // Validate age for senior citizen (must be 60 years or older)
            $birth_date = new DateTime($date_of_birth);
            $today = new DateTime();
            $age = $today->diff($birth_date)->y;
            if ($age < 60) {
                $error_message = "Warning: The birth date indicates the patient is {$age} years old. Senior Citizen status requires age 60 or older. Please verify the birth date or uncheck Senior Citizen if incorrect.";
            }
        } elseif ($is_pwd && empty($pwd_id)) {
            $error_message = "PWD ID is required when PWD is checked.";
        } elseif (!preg_match('/^\d{10}$/', preg_replace('/[^\d]/', '', $_POST['phone'] ?? ''))) {
            $error_message = "Phone number must be exactly 10 digits. Country code +63 is fixed.";
        } elseif (!preg_match('/^\d{10}$/', preg_replace('/[^\d]/', '', $_POST['emergency_contact_phone'] ?? ''))) {
            $error_message = "Emergency contact phone must be exactly 10 digits. Country code +63 is fixed.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Validation passed for new patient
        }
    }
    
    // Continue with processing if no validation errors
    if (empty($error_message)) {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM patient_users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // For NEW patients, block if email is already used.
            // For EXISTING patients, allow submission so admin can review and decide.
            if ($result->num_rows > 0 && $patient_type === 'New') {
                $error_message = "Email already registered. Please use a different email or try logging in.";
                $email_exists_for_new_patient = true;
            } else {
                // Normalize existing patient id for "New" patient type
                if ($patient_type === 'New') {
                    $existing_patient_id = null; // ensure NULL to avoid FK issues
                } else {
                    // For Existing patients, set existing_patient_id to null (no Patient ID required)
                    $existing_patient_id = null;
                    // Set default values for fields not required for existing patients
                    $sex = '';
                    $civil_status = '';
                    $phone = '';
                    $address = '';
                    $region = '';
                    $province = '';
                    $city = '';
                    $barangay = '';
                    $emergency_contact_name = '';
                    $emergency_contact_relationship = '';
                    $emergency_contact_phone = '';
                    $is_senior_citizen = 0;
                    $senior_citizen_id = '';
                    $is_pwd = 0;
                    $pwd_id = '';
                }
                
                if (empty($error_message)) {
                    // Store plain text password temporarily in registration request
                    // It will be hashed when the account is created after approval
                    
                    // Store additional fields in medical_history as JSON since table may not have all columns
                    // This includes: suffix, civil_status, is_senior_citizen, senior_citizen_id, 
                    // is_pwd, pwd_id, emergency_contact_relationship, chief_complaint (for new patients)
                    // id_attachment_path (for Senior/PWD)
                    $additional_data = json_encode([
                        'suffix' => $suffix,
                        'civil_status' => $patient_type === 'Existing' ? '' : $civil_status,
                        'is_senior_citizen' => $is_senior_citizen,
                        'senior_citizen_id' => $senior_citizen_id,
                        'is_pwd' => $is_pwd,
                        'pwd_id' => $pwd_id,
                        'emergency_contact_relationship' => $patient_type === 'Existing' ? '' : $emergency_contact_relationship,
                        'chief_complaint' => $patient_type === 'New' ? $chief_complaint : null,
                        'id_attachment_path' => $id_attachment_path
                    ]);
                    
                    // For existing patients, set medical fields to null
                    if ($patient_type === 'Existing') {
                        $blood_type = null;
                        $allergies = null;
                    }
                    
                    // Insert registration request
					$stmt = $conn->prepare("INSERT INTO patient_registration_requests 
						(first_name, last_name, middle_name, date_of_birth, sex, phone, email, address, 
						 emergency_contact_name, emergency_contact_phone, patient_type, existing_patient_id, blood_type, allergies, medical_history, password) 
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
					// Bind types: 15 strings and 1 integer (existing_patient_id)
                    // Store plain text password (will be hashed during approval)
					$stmt->bind_param("sssssssssssissss", $first_name, $last_name, $middle_name, 
                        $date_of_birth, $sex, $phone, $email, $address, $emergency_contact_name, 
						$emergency_contact_phone, $patient_type, $existing_patient_id, $blood_type, 
						$allergies, $additional_data, $password);
                    
                    if ($stmt->execute()) {
                        $request_id = $conn->insert_id;
                        
                        // Create notification for all admins
                        require_once 'config/patient_auth.php';
                        try {
                            if (function_exists('createAdminNotification')) {
                                createAdminNotification('Appointment_Reminder', // Using existing type, will be filtered by recipient_type
                                    'New Patient Registration Request',
                                    "A new patient registration request has been submitted.\n\n" .
                                    "Patient: {$first_name} {$last_name}\n" .
                                    "Email: {$email}\n" .
                                    "Phone: {$phone}\n" .
                                    "Patient Type: {$patient_type}\n" .
                                    "Submitted: " . date('M j, Y g:i A'),
                                    'System');
                            }
                        } catch (Exception $e) {
                            error_log("Error creating admin notification: " . $e->getMessage());
                            // Continue anyway - notification failure shouldn't block registration
                        }
                        
                        // Redirect to patient login page with success message
                        // Pass patient type to show appropriate message
                        $redirect_url = 'patient_login.php?registered=1&type=' . urlencode($patient_type);
                        header('Location: ' . $redirect_url);
                        exit();
                    } else {
                        $error_message = "Registration failed. Please try again.";
                        if (!empty($stmt->error)) {
                            // Append minimal error detail for debugging during development
                            $error_message .= " (" . htmlspecialchars($stmt->error) . ")";
                        }
                    }
                }
            }
    }
}

// Get existing patients for dropdown (if patient type is Existing)
$existing_patients = [];
if (isset($_POST['patient_type']) && $_POST['patient_type'] === 'Existing') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, date_of_birth FROM patients ORDER BY last_name, first_name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $existing_patients[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - Mhavis Medical & Diagnostic Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
    <style>
        /* Override login.css to allow scrolling */
        html, body {
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh;
        }
        
        .form-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .section-title {
            color: #0D92F4;
            border-bottom: 2px solid #0D92F4;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .patient-type-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body 
style="background-color: #000A99; background-image: linear-gradient(rgba(0, 10, 153, 0.3), rgba(0, 10, 153, 0.3)), url('img/bg7.jpeg'); background-position: center center; background-attachment: fixed; background-size: cover; background-repeat: no-repeat;">


    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="text-center mb-4">
                    <img src="img/logo2.jpeg" alt="Mhavis Logo" class="mb-3" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover;">
                    <h2 class="text-black">Patient Registration</h2>
                    <p class="text-black">Mhavis Medical & Diagnostic Center</p>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

				<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
					<!-- Patient Type Section -->
					<div class="form-section">
						<h4 class="section-title">Patient Type</h4>
						<div class="patient-type-section">
							<div class="form-check">
								<input class="form-check-input" type="radio" name="patient_type" id="new_patient" value="New" 
									   <?php echo ($patient_type ?? '') === 'New' ? 'checked' : ''; ?> required onchange="toggleMedicalInfoSection()">
								<label class="form-check-label" for="new_patient">
									<strong>New Patient</strong> - I am registering for the first time
								</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="patient_type" id="existing_patient" value="Existing" 
									   <?php echo ($patient_type ?? '') === 'Existing' ? 'checked' : ''; ?> required onchange="toggleMedicalInfoSection()">
								<label class="form-check-label" for="existing_patient">
									<strong>Existing Patient</strong> - I have been treated here before
								</label>
							</div>
						</div>
						
						<!-- Patient ID section removed for Existing Patient type -->
					</div>

					<!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">Personal Information</h4>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" id="first_name" class="form-control existing-patient-field" required 
                                       pattern="[A-Za-z\s\-'\.]+" 
                                       title="First name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                       value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                                       oninput="validateNameField(this)">
                                <div class="invalid-feedback">First name cannot contain numbers.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" class="form-control existing-patient-field" 
                                       pattern="[A-Za-z\s\-'\.]*" 
                                       title="Middle name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                       value="<?php echo htmlspecialchars($middle_name ?? ''); ?>"
                                       oninput="validateNameField(this)">
                                <div class="invalid-feedback">Middle name cannot contain numbers.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" id="last_name" class="form-control existing-patient-field" required 
                                       pattern="[A-Za-z\s\-'\.]+" 
                                       title="Last name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                       value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                                       oninput="validateNameField(this)">
                                <div class="invalid-feedback">Last name cannot contain numbers.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Suffix</label>
                                <select class="form-control existing-patient-field" name="suffix" id="suffix">
                                    <option value="">Select Suffix (Optional)</option>
                                    <option value="Jr." <?php echo (isset($suffix) && $suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo (isset($suffix) && $suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="II" <?php echo (isset($suffix) && $suffix === 'II') ? 'selected' : ''; ?>>II</option>
                                    <option value="III" <?php echo (isset($suffix) && $suffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo (isset($suffix) && $suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_birth" id="date_of_birth" class="form-control existing-patient-field" required max="<?php echo date('Y-m-d'); ?>" 
                                       value="<?php echo $date_of_birth ?? ''; ?>" onchange="validateSeniorAge()">
                            </div>
                        </div>
                        <div class="row new-patient-only">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sex <span class="text-danger">*</span></label>
                                <select name="sex" id="sex" class="form-control" required>
                                    <option value="">Select Sex</option>
                                    <option value="Male" <?php echo ($sex ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($sex ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Civil Status <span class="text-danger">*</span></label>
                                <select class="form-control" name="civil_status" id="civil_status" required>
                                    <option value="">Select Civil Status</option>
                                    <option value="Single" <?php echo (isset($civil_status) && $civil_status === 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (isset($civil_status) && $civil_status === 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo (isset($civil_status) && $civil_status === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo (isset($civil_status) && $civil_status === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="Separated" <?php echo (isset($civil_status) && $civil_status === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                </select>
                            </div>
                        </div>
                        <!-- Senior Citizen and PWD Information -->
                        <div class="row new-patient-only">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_senior_citizen" id="is_senior_citizen" value="1" 
                                           <?php echo (isset($is_senior_citizen) && $is_senior_citizen) ? 'checked' : ''; ?> onchange="toggleSeniorFields()">
                                    <label class="form-check-label" for="is_senior_citizen">
                                        <strong>Senior Citizen</strong>
                                    </label>
                                </div>
                                <div id="senior_fields" style="display: <?php echo (isset($is_senior_citizen) && $is_senior_citizen) ? 'block' : 'none'; ?>; margin-top: 10px;">
                                    <div id="senior_age_warning" class="alert alert-warning" style="display: none; margin-bottom: 10px;">
                                        <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> <span id="senior_age_warning_text"></span>
                                    </div>
                                    <label class="form-label">Senior Citizen ID Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="senior_citizen_id"
                                           value="<?php echo isset($senior_citizen_id) ? htmlspecialchars($senior_citizen_id) : ''; ?>"
                                           placeholder="Enter Senior Citizen ID">
                                    <div class="mt-3">
                                        <label class="form-label">ID Attachment <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" name="id_attachment" id="senior_id_attachment" 
                                               accept="image/jpeg,image/jpg,image/png,image/gif,application/pdf" disabled>
                                        <small class="form-text text-muted">Upload a picture of yourself holding your Senior Citizen ID, or upload a clear picture/scan of your ID card. Accepted formats: JPG, PNG, GIF, PDF (Max 5MB)</small>
                                        <div id="senior_attachment_preview" class="mt-2" style="display: none;">
                                            <img id="senior_attachment_img" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                                            <p id="senior_attachment_filename" class="mt-1 text-muted small"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_pwd" id="is_pwd" value="1" 
                                           <?php echo (isset($is_pwd) && $is_pwd) ? 'checked' : ''; ?> onchange="togglePwdFields()">
                                    <label class="form-check-label" for="is_pwd">
                                        <strong>Person with Disability (PWD)</strong>
                                    </label>
                                </div>
                                <div id="pwd_fields" style="display: <?php echo (isset($is_pwd) && $is_pwd) ? 'block' : 'none'; ?>; margin-top: 10px;">
                                    <label class="form-label">PWD ID Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="pwd_id"
                                           value="<?php echo isset($pwd_id) ? htmlspecialchars($pwd_id) : ''; ?>"
                                           placeholder="Enter PWD ID">
                                    <div class="mt-3">
                                        <label class="form-label">ID Attachment <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" name="id_attachment" id="pwd_id_attachment" 
                                               accept="image/jpeg,image/jpg,image/png,image/gif,application/pdf" disabled>
                                        <small class="form-text text-muted">Upload a picture of yourself holding your PWD ID, or upload a clear picture/scan of your ID card. Accepted formats: JPG, PNG, GIF, PDF (Max 5MB)</small>
                                        <div id="pwd_attachment_preview" class="mt-2" style="display: none;">
                                            <img id="pwd_attachment_img" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                                            <p id="pwd_attachment_filename" class="mt-1 text-muted small"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">Contact Information</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3 new-patient-only">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">+63</span>
                                    <input type="tel" name="phone" id="phone-input" class="form-control" required pattern="^\d{10}$" inputmode="numeric" maxlength="10" placeholder="9123456789"
                                           value="<?php echo htmlspecialchars(preg_replace('/^\+63|^0/', '', phoneToInputFormat($phone ?? ''))); ?>">
                                </div>
                                <small class="text-muted">Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="email" class="form-control existing-patient-field" required 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
                            </div>
                        </div>
                        <!-- Address Section -->
                        <div class="mb-3 new-patient-only">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Region <span class="text-danger">*</span></label>
                                    <select id="region" name="region" class="form-control" onchange="loadProvinces()" required>
                                        <option value="">Select Region</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Province <span class="text-danger">*</span></label>
                                    <select id="province" name="province" class="form-control" onchange="loadCities()" required>
                                        <option value="">Select Province</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">City/Municipality <span class="text-danger">*</span></label>
                                    <select id="city" name="city" class="form-control" onchange="loadBarangays()" required>
                                        <option value="">Select City</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Barangay <span class="text-danger">*</span></label>
                                    <select id="barangay" name="barangay" class="form-control" onchange="combineAddress()" required>
                                        <option value="">Select Barangay</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Hidden input to store full address (fallback for JS-disabled scenarios) -->
                            <input type="hidden" id="full_address" name="address" value="<?php echo htmlspecialchars($address ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <!-- Emergency Contact Section -->
                    <div class="form-section new-patient-only">
                        <h4 class="section-title">Emergency Contact</h4>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emergency Contact Name <span class="text-danger">*</span></label>
                                <input type="text" name="emergency_contact_name" class="form-control" required 
                                       pattern="[A-Za-z\s\-'\.]+" 
                                       title="Emergency contact name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                       value="<?php echo htmlspecialchars($emergency_contact_name ?? ''); ?>"
                                       oninput="validateNameField(this)">
                                <div class="invalid-feedback">Emergency contact name cannot contain numbers.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Relationship to Emergency Contact <span class="text-danger">*</span></label>
                                <select class="form-control" name="emergency_contact_relationship" required>
                                    <option value="">Select Relationship</option>
                                    <option value="Spouse" <?php echo (isset($emergency_contact_relationship) && $emergency_contact_relationship === 'Spouse') ? 'selected' : ''; ?>>Spouse</option>
                                    <option value="Parent" <?php echo (isset($emergency_contact_relationship) && $emergency_contact_relationship === 'Parent') ? 'selected' : ''; ?>>Parent</option>
                                    <option value="Child" <?php echo (isset($emergency_contact_relationship) && $emergency_contact_relationship === 'Child') ? 'selected' : ''; ?>>Child</option>
                                    <option value="Sibling" <?php echo (isset($emergency_contact_relationship) && $emergency_contact_relationship === 'Sibling') ? 'selected' : ''; ?>>Sibling</option>
                                    <option value="Friend" <?php echo (isset($emergency_contact_relationship) && $emergency_contact_relationship === 'Friend') ? 'selected' : ''; ?>>Friend</option>
                                    <option value="Guardian" <?php echo (isset($emergency_contact_relationship) && $emergency_contact_relationship === 'Guardian') ? 'selected' : ''; ?>>Guardian</option>
                                    <option value="Other" <?php echo (isset($emergency_contact_relationship) && $emergency_contact_relationship === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emergency Contact Phone <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">+63</span>
                                    <input type="tel" name="emergency_contact_phone" id="emergency-phone-input" class="form-control" required pattern="^\d{10}$" inputmode="numeric" maxlength="10" placeholder="9123456789"
                                           value="<?php echo htmlspecialchars(preg_replace('/^\+63|^0/', '', phoneToInputFormat($emergency_contact_phone ?? ''))); ?>">
                                </div>
                                <small class="text-muted">Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.</small>
                            </div>
                        </div>
                    </div>


					<!-- Patient Type Section moved above -->

                    <!-- Medical Information Section (Only for New Patients) -->
                    <div class="form-section" id="medical_info_section">
                        <h4 class="section-title">Medical Information</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Blood Type <span class="text-danger">*</span></label>
                                <select name="blood_type" id="blood_type" class="form-control">
                                    <option value="">Select Blood Type</option>
                                    <?php
                                    $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                                    foreach ($bloodTypes as $type) {
                                        $selected = (($blood_type ?? '') === $type) ? 'selected' : '';
                                        $safeType = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
                                        echo "<option value=\"{$safeType}\" {$selected}>{$safeType}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Known Allergies</label>
                                <input type="text" name="allergies" id="allergies" class="form-control" placeholder="List any allergies"
                                       value="<?php echo htmlspecialchars($allergies ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Chief Complaint</label>
                                <textarea name="chief_complaint" id="chief_complaint" class="form-control" rows="3" 
                                          placeholder="Please describe your main reason for seeking medical attention..."><?php echo htmlspecialchars($chief_complaint ?? ''); ?></textarea>
                                <small class="text-muted">Describe your primary health concern or reason for this visit.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">Account Information</h4>
						<div class="alert alert-info" role="alert">
							For security, your password will be automatically generated by the system and included in the approval email. You will log in using your Patient ID and this password. Please change it after your first login.
						</div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg px-5">Submit Registration</button>
                        <div class="mt-3">
                            <a href="patient_login.php" class="text-white">Already have an account? Login here</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Define showAlert function for compatibility (if needed elsewhere)
        function showAlert(message, title = 'Information', icon = 'info') {
            return Swal.fire({
                icon: icon,
                title: title,
                text: message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#0d6efd'
            });
        }
    </script>
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

        // Patient ID section removed - no longer needed for Existing Patient type

        // Address dropdown functionality
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
                populateRegions();
            })
            .catch(error => console.error('Error loading location data:', error));

        function populateRegions() {
            const regionSelect = document.getElementById('region');
            const savedRegion = regionSelect.getAttribute('data-saved-value') || '<?php echo htmlspecialchars($region ?? '', ENT_QUOTES, 'UTF-8'); ?>';
            regionSelect.innerHTML = '<option value="">Select Region</option>';
            for (let region in PH_LOCATIONS) {
                const selected = (region === savedRegion) ? 'selected' : '';
                regionSelect.innerHTML += `<option value="${region}" ${selected}>${region}</option>`;
            }
            // If region was saved, trigger province loading
            if (savedRegion) {
                setTimeout(() => {
                    loadProvinces();
                }, 100);
            }
        }

        function loadProvinces() {
            const region = document.getElementById('region').value;
            const provinceSelect = document.getElementById('province');
            const savedProvince = '<?php echo htmlspecialchars($province ?? '', ENT_QUOTES, 'UTF-8'); ?>';
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            if (region && PH_LOCATIONS[region]) {
                for (let province in PH_LOCATIONS[region]) {
                    const selected = (province === savedProvince) ? 'selected' : '';
                    provinceSelect.innerHTML += `<option value="${province}" ${selected}>${province}</option>`;
                }
            }
            document.getElementById('city').innerHTML = '<option value="">Select City</option>';
            document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
            combineAddress();
            // If province was saved, trigger city loading
            if (savedProvince && region) {
                setTimeout(() => {
                    loadCities();
                }, 100);
            }
        }

        function loadCities() {
            const region = document.getElementById('region').value;
            const province = document.getElementById('province').value;
            const citySelect = document.getElementById('city');
            const savedCity = '<?php echo htmlspecialchars($city ?? '', ENT_QUOTES, 'UTF-8'); ?>';
            citySelect.innerHTML = '<option value="">Select City</option>';
            if (region && province && PH_LOCATIONS[region] && PH_LOCATIONS[region][province]) {
                for (let city in PH_LOCATIONS[region][province]) {
                    const selected = (city === savedCity) ? 'selected' : '';
                    citySelect.innerHTML += `<option value="${city}" ${selected}>${city}</option>`;
                }
            }
            document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
            combineAddress();
            // If city was saved, trigger barangay loading
            if (savedCity && region && province) {
                setTimeout(() => {
                    loadBarangays();
                }, 100);
            }
        }

        function loadBarangays() {
            const region = document.getElementById('region').value;
            const province = document.getElementById('province').value;
            const city = document.getElementById('city').value;
            const barangaySelect = document.getElementById('barangay');
            const savedBarangay = '<?php echo htmlspecialchars($barangay ?? '', ENT_QUOTES, 'UTF-8'); ?>';
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            if (region && province && city && PH_LOCATIONS[region] && PH_LOCATIONS[region][province] && PH_LOCATIONS[region][province][city]) {
                PH_LOCATIONS[region][province][city].forEach(brgy => {
                    const selected = (brgy === savedBarangay) ? 'selected' : '';
                    barangaySelect.innerHTML += `<option value="${brgy}" ${selected}>${brgy}</option>`;
                });
            }
            combineAddress();
        }

        function combineAddress() {
            const r = document.getElementById("region").value;
            const p = document.getElementById("province").value;
            const c = document.getElementById("city").value;
            const b = document.getElementById("barangay").value;
            if (r && p && c && b) {
                const fullAddress = `${b}, ${c}, ${p}, ${r}`;
                document.getElementById("full_address").value = fullAddress;
            } else {
                document.getElementById("full_address").value = "";
            }
        }
        
        // Fallback: Ensure address is set on form submit even if JavaScript combineAddress wasn't called
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.needs-validation');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const region = document.getElementById("region").value;
                    const province = document.getElementById("province").value;
                    const city = document.getElementById("city").value;
                    const barangay = document.getElementById("barangay").value;
                    
                    // Build address from components if not already set
                    if (region && province && city && barangay) {
                        const fullAddress = `${barangay}, ${city}, ${province}, ${region}`;
                        document.getElementById("full_address").value = fullAddress;
                    }
                });
            }
        });
        
        // Phone number input validation - only allow digits, max 10 digits
        function setupPhoneInput(inputId) {
            const phoneInput = document.getElementById(inputId);
            if (phoneInput) {
                // Only allow numeric input
                phoneInput.addEventListener('input', function(e) {
                    // Remove any non-digit characters
                    this.value = this.value.replace(/[^\d]/g, '');
                    // Limit to 10 digits
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                });
                
                // Prevent non-numeric characters on keypress
                phoneInput.addEventListener('keypress', function(e) {
                    // Allow: backspace, delete, tab, escape, enter, and numbers
                    if ([46, 8, 9, 27, 13, 110].indexOf(e.keyCode) !== -1 ||
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
                
                // Prevent paste of non-numeric content
                phoneInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const numericOnly = paste.replace(/[^\d]/g, '').slice(0, 10);
                    this.value = numericOnly;
                });
            }
        }
        
        // Toggle Medical Information section and other fields based on patient type
        function toggleMedicalInfoSection() {
            const newPatientRadio = document.getElementById('new_patient');
            const existingPatientRadio = document.getElementById('existing_patient');
            const medicalInfoSection = document.getElementById('medical_info_section');
            const bloodTypeSelect = document.getElementById('blood_type');
            const allergiesInput = document.getElementById('allergies');
            const chiefComplaintTextarea = document.getElementById('chief_complaint');
            
            const isExistingPatient = existingPatientRadio && existingPatientRadio.checked;
            const isNewPatient = newPatientRadio && newPatientRadio.checked;
            
            // Toggle medical info section
            if (medicalInfoSection) {
                if (isNewPatient) {
                    // Show medical info section for new patients
                    medicalInfoSection.style.display = 'block';
                    if (bloodTypeSelect) {
                        bloodTypeSelect.setAttribute('required', 'required');
                    }
                } else if (isExistingPatient) {
                    // Hide medical info section for existing patients
                    medicalInfoSection.style.display = 'none';
                    if (bloodTypeSelect) {
                        bloodTypeSelect.removeAttribute('required');
                        bloodTypeSelect.value = '';
                    }
                    if (allergiesInput) {
                        allergiesInput.value = '';
                    }
                    if (chiefComplaintTextarea) {
                        chiefComplaintTextarea.value = '';
                    }
                }
            }
            
            // Toggle visibility of new-patient-only sections
            const newPatientOnlyElements = document.querySelectorAll('.new-patient-only');
            newPatientOnlyElements.forEach(function(element) {
                if (isExistingPatient) {
                    element.style.display = 'none';
                } else if (isNewPatient) {
                    element.style.display = '';
                }
            });
            
            // Toggle required attributes for fields based on patient type
            if (isExistingPatient) {
                // Remove required from fields that are not needed for existing patients
                const sexSelect = document.getElementById('sex');
                const civilStatusSelect = document.getElementById('civil_status');
                const phoneInput = document.getElementById('phone-input');
                const regionSelect = document.getElementById('region');
                const provinceSelect = document.getElementById('province');
                const citySelect = document.getElementById('city');
                const barangaySelect = document.getElementById('barangay');
                const emergencyContactName = document.querySelector('input[name="emergency_contact_name"]');
                const emergencyContactRelationship = document.querySelector('select[name="emergency_contact_relationship"]');
                const emergencyContactPhone = document.getElementById('emergency-phone-input');
                
                if (sexSelect) {
                    sexSelect.removeAttribute('required');
                    sexSelect.value = '';
                }
                if (civilStatusSelect) {
                    civilStatusSelect.removeAttribute('required');
                    civilStatusSelect.value = '';
                }
                if (phoneInput) {
                    phoneInput.removeAttribute('required');
                    phoneInput.value = '';
                }
                if (regionSelect) {
                    regionSelect.removeAttribute('required');
                    regionSelect.value = '';
                }
                if (provinceSelect) {
                    provinceSelect.removeAttribute('required');
                    provinceSelect.value = '';
                }
                if (citySelect) {
                    citySelect.removeAttribute('required');
                    citySelect.value = '';
                }
                if (barangaySelect) {
                    barangaySelect.removeAttribute('required');
                    barangaySelect.value = '';
                }
                if (emergencyContactName) {
                    emergencyContactName.removeAttribute('required');
                    emergencyContactName.value = '';
                }
                if (emergencyContactRelationship) {
                    emergencyContactRelationship.removeAttribute('required');
                    emergencyContactRelationship.value = '';
                }
                if (emergencyContactPhone) {
                    emergencyContactPhone.removeAttribute('required');
                    emergencyContactPhone.value = '';
                }
                
                // Ensure existing patient required fields remain required
                const firstNameInput = document.getElementById('first_name');
                const lastNameInput = document.getElementById('last_name');
                const dobInput = document.getElementById('date_of_birth');
                const emailInput = document.getElementById('email');
                
                if (firstNameInput) firstNameInput.setAttribute('required', 'required');
                if (lastNameInput) lastNameInput.setAttribute('required', 'required');
                if (dobInput) dobInput.setAttribute('required', 'required');
                if (emailInput) emailInput.setAttribute('required', 'required');
            } else if (isNewPatient) {
                // Restore required attributes for new patients
                const sexSelect = document.getElementById('sex');
                const civilStatusSelect = document.getElementById('civil_status');
                const phoneInput = document.getElementById('phone-input');
                const regionSelect = document.getElementById('region');
                const provinceSelect = document.getElementById('province');
                const citySelect = document.getElementById('city');
                const barangaySelect = document.getElementById('barangay');
                const emergencyContactName = document.querySelector('input[name="emergency_contact_name"]');
                const emergencyContactRelationship = document.querySelector('select[name="emergency_contact_relationship"]');
                const emergencyContactPhone = document.getElementById('emergency-phone-input');
                
                if (sexSelect) sexSelect.setAttribute('required', 'required');
                if (civilStatusSelect) civilStatusSelect.setAttribute('required', 'required');
                if (phoneInput) phoneInput.setAttribute('required', 'required');
                if (regionSelect) regionSelect.setAttribute('required', 'required');
                if (provinceSelect) provinceSelect.setAttribute('required', 'required');
                if (citySelect) citySelect.setAttribute('required', 'required');
                if (barangaySelect) barangaySelect.setAttribute('required', 'required');
                if (emergencyContactName) emergencyContactName.setAttribute('required', 'required');
                if (emergencyContactRelationship) emergencyContactRelationship.setAttribute('required', 'required');
                if (emergencyContactPhone) emergencyContactPhone.setAttribute('required', 'required');
            }
        }

        // Function to validate name fields (prevent numbers)
        function validateNameField(input) {
            const value = input.value;
            // Remove any numbers from the input
            const cleanedValue = value.replace(/\d/g, '');
            if (value !== cleanedValue) {
                input.value = cleanedValue;
                input.setCustomValidity('Name fields cannot contain numbers.');
                input.reportValidity();
            } else {
                input.setCustomValidity('');
            }
        }
        
        // Handle file upload preview
        function setupFilePreview(inputId, previewId, imgId, filenameId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const img = document.getElementById(imgId);
            const filename = document.getElementById(filenameId);
            
            if (input && preview && img && filename) {
                input.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        filename.textContent = 'Selected: ' + file.name;
                        
                        // Show preview for images
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                img.src = e.target.result;
                                preview.style.display = 'block';
                            };
                            reader.readAsDataURL(file);
                        } else {
                            // For PDFs, just show filename
                            img.style.display = 'none';
                            preview.style.display = 'block';
                        }
                        
                        // Validate file size (5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size exceeds 5MB. Please choose a smaller file.');
                            input.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                        
                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Invalid file type. Please upload JPG, PNG, GIF, or PDF files only.');
                            input.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }
        }

        // Setup phone inputs on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupPhoneInput('phone-input');
            setupPhoneInput('emergency-phone-input');
            toggleSeniorFields();
            togglePwdFields();
            toggleMedicalInfoSection(); // Initialize medical info section visibility
            
            // Setup file preview handlers
            setupFilePreview('senior_id_attachment', 'senior_attachment_preview', 'senior_attachment_img', 'senior_attachment_filename');
            setupFilePreview('pwd_id_attachment', 'pwd_attachment_preview', 'pwd_attachment_img', 'pwd_attachment_filename');
            
            // Add event listeners to name fields to prevent numbers
            const nameFields = ['first_name', 'last_name', 'middle_name', 'emergency_contact_name'];
            nameFields.forEach(function(fieldName) {
                const field = document.querySelector('input[name="' + fieldName + '"]');
                if (field) {
                    // Prevent typing numbers
                    field.addEventListener('keypress', function(e) {
                        if (/\d/.test(e.key)) {
                            e.preventDefault();
                            this.setCustomValidity('Name fields cannot contain numbers.');
                            this.reportValidity();
                        } else {
                            this.setCustomValidity('');
                        }
                    });
                    
                    // Handle paste events
                    field.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const paste = (e.clipboardData || window.clipboardData).getData('text');
                        // Remove numbers from pasted text
                        const cleanedPaste = paste.replace(/\d/g, '');
                        const start = this.selectionStart;
                        const end = this.selectionEnd;
                        this.value = this.value.substring(0, start) + cleanedPaste + this.value.substring(end);
                        this.setSelectionRange(start + cleanedPaste.length, start + cleanedPaste.length);
                        if (paste !== cleanedPaste) {
                            this.setCustomValidity('Numbers were removed from the pasted text.');
                            this.reportValidity();
                        } else {
                            this.setCustomValidity('');
                        }
                    });
                }
            });
        });

        // Function to calculate age from date of birth
        function calculateAge(dateOfBirth) {
            if (!dateOfBirth) return null;
            const birth = new Date(dateOfBirth);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            return age;
        }

        // Function to validate senior citizen age
        function validateSeniorAge() {
            const checkbox = document.getElementById('is_senior_citizen');
            const dateOfBirthInput = document.getElementById('date_of_birth');
            const warningDiv = document.getElementById('senior_age_warning');
            const warningText = document.getElementById('senior_age_warning_text');
            
            if (!checkbox || !dateOfBirthInput || !warningDiv || !warningText) return;
            
            // Only show warning if senior citizen is checked
            if (checkbox.checked && dateOfBirthInput.value) {
                const age = calculateAge(dateOfBirthInput.value);
                if (age !== null) {
                    if (age < 60) {
                        warningText.textContent = `The birth date indicates the patient is ${age} years old. Senior Citizen status requires age 60 or older. Please verify the birth date or uncheck Senior Citizen if incorrect.`;
                        warningDiv.style.display = 'block';
                    } else {
                        warningDiv.style.display = 'none';
                    }
                } else {
                    warningDiv.style.display = 'none';
                }
            } else {
                warningDiv.style.display = 'none';
            }
        }

        function toggleSeniorFields() {
            const checkbox = document.getElementById('is_senior_citizen');
            const fields = document.getElementById('senior_fields');
            const seniorIdInput = document.querySelector('input[name="senior_citizen_id"]');
            const seniorAttachmentInput = document.getElementById('senior_id_attachment');
            const pwdCheckbox = document.getElementById('is_pwd');
            const pwdAttachmentInput = document.getElementById('pwd_id_attachment');
            if (checkbox && fields) {
                if (checkbox.checked) {
                    // Uncheck PWD if Senior Citizen is checked
                    if (pwdCheckbox && pwdCheckbox.checked) {
                        pwdCheckbox.checked = false;
                        togglePwdFields(); // Update PWD fields visibility
                    }
                    fields.style.display = 'block';
                    if (seniorIdInput) {
                        seniorIdInput.setAttribute('required', 'required');
                    }
                    if (seniorAttachmentInput) {
                        seniorAttachmentInput.setAttribute('required', 'required');
                        seniorAttachmentInput.disabled = false;
                    }
                    // Clear and hide PWD attachment if it was set
                    if (pwdAttachmentInput) {
                        pwdAttachmentInput.removeAttribute('required');
                        pwdAttachmentInput.value = '';
                        pwdAttachmentInput.disabled = true;
                        const pwdPreview = document.getElementById('pwd_attachment_preview');
                        if (pwdPreview) pwdPreview.style.display = 'none';
                    }
                    // Validate age when checkbox is checked
                    validateSeniorAge();
                } else {
                    fields.style.display = 'none';
                    if (seniorIdInput) {
                        seniorIdInput.removeAttribute('required');
                        seniorIdInput.value = '';
                    }
                    if (seniorAttachmentInput) {
                        seniorAttachmentInput.removeAttribute('required');
                        seniorAttachmentInput.value = '';
                        seniorAttachmentInput.disabled = true;
                        const seniorPreview = document.getElementById('senior_attachment_preview');
                        if (seniorPreview) seniorPreview.style.display = 'none';
                    }
                    // Hide warning when unchecked
                    const warningDiv = document.getElementById('senior_age_warning');
                    if (warningDiv) {
                        warningDiv.style.display = 'none';
                    }
                }
            }
        }

        function togglePwdFields() {
            const checkbox = document.getElementById('is_pwd');
            const fields = document.getElementById('pwd_fields');
            const pwdIdInput = document.querySelector('input[name="pwd_id"]');
            const pwdAttachmentInput = document.getElementById('pwd_id_attachment');
            const seniorCheckbox = document.getElementById('is_senior_citizen');
            const seniorAttachmentInput = document.getElementById('senior_id_attachment');
            if (checkbox && fields) {
                if (checkbox.checked) {
                    // Uncheck Senior Citizen if PWD is checked
                    if (seniorCheckbox && seniorCheckbox.checked) {
                        seniorCheckbox.checked = false;
                        toggleSeniorFields(); // Update Senior fields visibility
                    }
                    fields.style.display = 'block';
                    if (pwdIdInput) {
                        pwdIdInput.setAttribute('required', 'required');
                    }
                    if (pwdAttachmentInput) {
                        pwdAttachmentInput.setAttribute('required', 'required');
                        pwdAttachmentInput.disabled = false;
                    }
                    // Clear and hide Senior attachment if it was set
                    if (seniorAttachmentInput) {
                        seniorAttachmentInput.removeAttribute('required');
                        seniorAttachmentInput.value = '';
                        seniorAttachmentInput.disabled = true;
                        const seniorPreview = document.getElementById('senior_attachment_preview');
                        if (seniorPreview) seniorPreview.style.display = 'none';
                    }
                } else {
                    fields.style.display = 'none';
                    if (pwdIdInput) {
                        pwdIdInput.removeAttribute('required');
                        pwdIdInput.value = '';
                    }
                    if (pwdAttachmentInput) {
                        pwdAttachmentInput.removeAttribute('required');
                        pwdAttachmentInput.value = '';
                        pwdAttachmentInput.disabled = true;
                        const pwdPreview = document.getElementById('pwd_attachment_preview');
                        if (pwdPreview) pwdPreview.style.display = 'none';
                    }
                }
            }
        }

        // Form validation function
        (function() {
            const form = document.querySelector('.needs-validation');
            if (form) {
                form.addEventListener('submit', function(event) {
                    // Get patient type first
                    const patientType = document.querySelector('input[name="patient_type"]:checked');
                    const isExistingPatient = patientType && patientType.value === 'Existing';
                    const isNewPatient = patientType && patientType.value === 'New';
                    
                    if (!patientType) {
                        event.preventDefault();
                        event.stopPropagation();
                        alert('Please select a patient type.');
                        return false;
                    }
                    
                    if (isExistingPatient) {
                        // For existing patients, only validate: first_name, last_name, date_of_birth, email
                        const firstName = document.getElementById('first_name').value.trim();
                        const lastName = document.getElementById('last_name').value.trim();
                        const dob = document.getElementById('date_of_birth').value;
                        const email = document.getElementById('email').value.trim();
                        
                        if (!firstName || !lastName || !dob || !email) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please fill in all required fields (First Name, Last Name, Date of Birth, and Email Address).');
                            return false;
                        }
                        
                        // Validate name fields don't contain numbers
                        const nameFields = ['first_name', 'last_name', 'middle_name'];
                        for (let i = 0; i < nameFields.length; i++) {
                            const field = document.querySelector('input[name="' + nameFields[i] + '"]');
                            if (field && field.value) {
                                if (/\d/.test(field.value)) {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    const fieldLabel = field.previousElementSibling ? field.previousElementSibling.textContent.trim() : nameFields[i];
                                    alert(fieldLabel + ' cannot contain numbers. Please enter a valid name.');
                                    field.focus();
                                    return false;
                                }
                            }
                        }
                        
                        // Validate email format
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(email)) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please enter a valid email address.');
                            document.getElementById('email').focus();
                            return false;
                        }
                        
                        // Check HTML5 validation for required fields only
                        const requiredFields = form.querySelectorAll('.existing-patient-field[required]');
                        let hasInvalidField = false;
                        requiredFields.forEach(function(field) {
                            if (!field.validity.valid) {
                                hasInvalidField = true;
                            }
                        });
                        
                        if (hasInvalidField) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please fill in all required fields correctly.');
                            return false;
                        }
                        
                        form.classList.add('was-validated');
                        return true;
                    } else if (isNewPatient) {
                        // For new patients, validate all fields
                        // Ensure address components are selected
                        const region = document.getElementById('region').value;
                        const province = document.getElementById('province').value;
                        const city = document.getElementById('city').value;
                        const barangay = document.getElementById('barangay').value;
                        
                        if (!region || !province || !city || !barangay) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please select all address fields (Region, Province, City/Municipality, and Barangay)');
                            return false;
                        }
                        
                        // Ensure address hidden field is set (fallback for JS-disabled scenarios)
                        combineAddress();
                        const address = document.getElementById('full_address').value;
                        if (!address || address.trim() === '') {
                            // Build address from components as fallback
                            if (region && province && city && barangay) {
                                document.getElementById('full_address').value = `${barangay}, ${city}, ${province}, ${region}`;
                            } else {
                                event.preventDefault();
                                event.stopPropagation();
                                alert('Please complete the address information');
                                return false;
                            }
                        }
                        
                        // Validate that Senior Citizen and PWD are not both checked
                        const isSeniorCitizen = document.getElementById('is_senior_citizen').checked;
                        const isPwd = document.getElementById('is_pwd').checked;
                        if (isSeniorCitizen && isPwd) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Senior Citizen and PWD cannot both be checked. Please select only one.');
                            return false;
                        }
                        
                        // Validate conditional fields
                        const seniorId = document.querySelector('input[name="senior_citizen_id"]').value;
                        if (isSeniorCitizen && !seniorId.trim()) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please enter Senior Citizen ID Number');
                            return false;
                        }
                        
                        // Validate Senior Citizen ID attachment
                        if (isSeniorCitizen) {
                            const seniorAttachment = document.getElementById('senior_id_attachment');
                            if (!seniorAttachment || !seniorAttachment.files || seniorAttachment.files.length === 0) {
                                event.preventDefault();
                                event.stopPropagation();
                                alert('Please upload an ID attachment. You can upload a picture of yourself holding your Senior Citizen ID or a clear picture/scan of your ID card.');
                                return false;
                            }
                            // Validate file size
                            const file = seniorAttachment.files[0];
                            if (file && file.size > 5 * 1024 * 1024) {
                                event.preventDefault();
                                event.stopPropagation();
                                alert('File size exceeds 5MB. Please choose a smaller file.');
                                return false;
                            }
                        }
                        
                        // Validate senior citizen age
                        if (isSeniorCitizen) {
                            const dateOfBirth = document.getElementById('date_of_birth').value;
                            if (dateOfBirth) {
                                const age = calculateAge(dateOfBirth);
                                if (age !== null && age < 60) {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    alert(`Warning: The birth date indicates the patient is ${age} years old. Senior Citizen status requires age 60 or older. Please verify the birth date or uncheck Senior Citizen if incorrect.`);
                                    return false;
                                }
                            }
                        }
                        
                        const pwdId = document.querySelector('input[name="pwd_id"]').value;
                        if (isPwd && !pwdId.trim()) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please enter PWD ID Number');
                            return false;
                        }
                        
                        // Validate PWD ID attachment
                        if (isPwd) {
                            const pwdAttachment = document.getElementById('pwd_id_attachment');
                            if (!pwdAttachment || !pwdAttachment.files || pwdAttachment.files.length === 0) {
                                event.preventDefault();
                                event.stopPropagation();
                                alert('Please upload an ID attachment. You can upload a picture of yourself holding your PWD ID or a clear picture/scan of your ID card.');
                                return false;
                            }
                            // Validate file size
                            const file = pwdAttachment.files[0];
                            if (file && file.size > 5 * 1024 * 1024) {
                                event.preventDefault();
                                event.stopPropagation();
                                alert('File size exceeds 5MB. Please choose a smaller file.');
                                return false;
                            }
                        }
                        
                        // Validate medical information
                        const bloodType = document.getElementById('blood_type').value;
                        if (!bloodType || bloodType.trim() === '') {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please select a blood type (required for new patients)');
                            return false;
                        }
                        
                        // Validate phone numbers
                        const phoneInput = document.getElementById('phone-input');
                        const emergencyPhoneInput = document.getElementById('emergency-phone-input');
                        
                        if (phoneInput && phoneInput.value) {
                            const phoneDigits = phoneInput.value.replace(/\D/g, '');
                            if (phoneDigits.length !== 10) {
                                event.preventDefault();
                                event.stopPropagation();
                                alert('Phone number must be exactly 10 digits. Country code +63 is fixed.');
                                phoneInput.focus();
                                return false;
                            }
                        }
                        
                        if (emergencyPhoneInput && emergencyPhoneInput.value) {
                            const emergencyPhoneDigits = emergencyPhoneInput.value.replace(/\D/g, '');
                            if (emergencyPhoneDigits.length !== 10) {
                                event.preventDefault();
                                event.stopPropagation();
                                alert('Emergency contact phone must be exactly 10 digits. Country code +63 is fixed.');
                                emergencyPhoneInput.focus();
                                return false;
                            }
                        }
                        
                        // Validate name fields don't contain numbers
                        const nameFields = ['first_name', 'last_name', 'middle_name', 'emergency_contact_name'];
                        for (let i = 0; i < nameFields.length; i++) {
                            const field = document.querySelector('input[name="' + nameFields[i] + '"]');
                            if (field && field.value) {
                                if (/\d/.test(field.value)) {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    const fieldLabel = field.previousElementSibling ? field.previousElementSibling.textContent.trim() : nameFields[i];
                                    alert(fieldLabel + ' cannot contain numbers. Please enter a valid name.');
                                    field.focus();
                                    return false;
                                }
                            }
                        }
                        
                        // Check HTML5 validation and show specific error messages
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                            
                            // Find the first invalid field and show its error message
                            const invalidFields = form.querySelectorAll(':invalid');
                            if (invalidFields.length > 0) {
                                const firstInvalid = invalidFields[0];
                                let errorMessage = 'Please correct the following errors:\n\n';
                                
                                // Collect all validation errors
                                invalidFields.forEach(function(field) {
                                    const fieldLabel = field.previousElementSibling ? 
                                        field.previousElementSibling.textContent.trim().replace(/\*/g, '').trim() : 
                                        field.name || field.id;
                                    
                                    if (field.validity.valueMissing) {
                                        errorMessage += `• ${fieldLabel} is required.\n`;
                                    } else if (field.validity.patternMismatch) {
                                        errorMessage += `• ${fieldLabel}: ${field.title || 'Invalid format. Please check your input.'}\n`;
                                    } else if (field.validity.typeMismatch) {
                                        errorMessage += `• ${fieldLabel}: Invalid format.\n`;
                                    } else if (!field.validity.valid) {
                                        errorMessage += `• ${fieldLabel}: ${field.validationMessage || 'Invalid input.'}\n`;
                                    }
                                });
                                
                                alert(errorMessage);
                                firstInvalid.focus();
                            } else {
                                alert('Please fill in all required fields correctly.');
                            }
                            return false;
                        }
                        form.classList.add('was-validated');
                    }
                }, false);
            }
        })();
    </script>
</body>
</html>
