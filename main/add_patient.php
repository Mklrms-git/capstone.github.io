<?php

define('MHAVIS_EXEC', true);
$page_title = "Add New Patient";
$active_page = "patients";
require_once __DIR__ . '/config/init.php';
requireLogin();

$conn = getDBConnection();

// Function to generate unique patient ID
function generatePatientId($conn) {
    $currentYear = date('Y');
    $maxAttempts = 100; // Prevent infinite loops
    $attempt = 0;
    
    // Start by finding the highest existing number for this year
    $stmt = $conn->prepare("SELECT patient_number FROM patients WHERE patient_number LIKE ? ORDER BY patient_number DESC LIMIT 1");
    $pattern = "PT-{$currentYear}-%";
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $startCount = 1;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = $row['patient_number'];
        // Extract the number part (e.g., "PT-2025-00010" -> 10)
        if (preg_match('/PT-\d{4}-(\d+)/', $lastNumber, $matches)) {
            $startCount = (int)$matches[1] + 1;
        }
    }
    
    // Try to find a unique patient number
    do {
        $patientNumber = "PT-{$currentYear}-" . str_pad($startCount, 5, '0', STR_PAD_LEFT);
        
        // Check if this patient number already exists
        $checkStmt = $conn->prepare("SELECT id FROM patients WHERE patient_number = ?");
        $checkStmt->bind_param("s", $patientNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 0) {
            // Found a unique patient number
            return $patientNumber;
        }
        
        // If exists, increment and try again
        $startCount++;
        $attempt++;
        
        if ($attempt >= $maxAttempts) {
            // Fallback: use timestamp to ensure uniqueness
            $timestamp = time();
            return "PT-{$currentYear}-" . substr($timestamp, -5);
        }
    } while (true);
}

// Function to generate secure password
function generatePassword() {
    // Generate 12-char password with letters and numbers
    $bytes = random_bytes(9); // 12 chars after base64url trimming non-alnum
    $base = rtrim(strtr(base64_encode($bytes), '+/', 'AZ'), '='); // ensure URL-safe
    // Filter to alnum and ensure length 12
    $generated_password = substr(preg_replace('/[^A-Za-z0-9]/', '', $base), 0, 12);
    if (strlen($generated_password) < 12) {
        $generated_password = strtoupper(bin2hex(random_bytes(6))); // fallback 12 hex chars
    }
    return $generated_password;
}


// Helper function to validate name fields (no numbers allowed) - for duplicate confirmation
function isValidNameConfirm($name) {
    if (empty($name)) return true; // Empty is handled by required validation
    // Allow letters, spaces, hyphens, apostrophes, and periods (common in names)
    // Reject if contains any digits
    return !preg_match('/\d/', $name);
}

// Handle duplicate confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_duplicate'])) {
    $firstName = sanitize($_POST['first_name']);
    $middleName = sanitize($_POST['middle_name']);
    $lastName = sanitize($_POST['last_name']);
    $suffix = sanitize($_POST['suffix']);
    $dateOfBirth = sanitize($_POST['date_of_birth']);
    $sex = sanitize($_POST['sex']);
    $civilStatus = sanitize($_POST['civil_status']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $emergencyContactName = sanitize($_POST['emergency_contact_name']);
    $emergencyContactRelationship = sanitize($_POST['emergency_contact_relationship']);
    $bloodType = sanitize($_POST['blood_type']);
    $chiefComplaint = sanitize($_POST['chief_complaint']);
    $isSeniorCitizen = isset($_POST['is_senior_citizen']) ? 1 : 0;
    $seniorCitizenId = sanitize($_POST['senior_citizen_id']);
    $isPwd = isset($_POST['is_pwd']) ? 1 : 0;
    $pwdId = sanitize($_POST['pwd_id']);
    $phone = sanitize($_POST['phone']);
    $emergencyContactPhone = sanitize($_POST['emergency_contact_phone']);
    $allergies = ''; // Default empty allergies field

    // Validate before inserting
    if (!isValidNameConfirm($firstName)) {
        $error = "First name cannot contain numbers. Please enter a valid name.";
    } elseif (!isValidNameConfirm($lastName)) {
        $error = "Last name cannot contain numbers. Please enter a valid name.";
    } elseif (!empty($middleName) && !isValidNameConfirm($middleName)) {
        $error = "Middle name cannot contain numbers. Please enter a valid name.";
    } elseif (!isValidNameConfirm($emergencyContactName)) {
        $error = "Emergency contact name cannot contain numbers. Please enter a valid name.";
    } elseif ($isSeniorCitizen && $isPwd) {
        $error = "Senior Citizen and PWD cannot both be checked. Please select only one.";
    } elseif ($isSeniorCitizen && !empty($dateOfBirth)) {
        // Validate age for senior citizen (must be 60 years or older)
        $birthDate = new DateTime($dateOfBirth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 60) {
            $error = "Warning: The birth date indicates the patient is {$age} years old. Senior Citizen status requires age 60 or older. Please verify the birth date or uncheck Senior Citizen if incorrect.";
        }
    }

    // Generate unique patient ID
    if (!isset($error)) {
        $patientNumber = generatePatientId($conn);

        // Check if patient_number column exists, if not use regular insert
        $checkColumn = $conn->query("SHOW COLUMNS FROM patients LIKE 'patient_number'");
        $hasPatientNumber = $checkColumn->num_rows > 0;

        // Handle email uniqueness by modifying email if it already exists
        if ($email) {
            $emailCheckStmt = $conn->prepare("SELECT id FROM patients WHERE email = ?");
            $emailCheckStmt->bind_param("s", $email);
            $emailCheckStmt->execute();
            $emailResult = $emailCheckStmt->get_result();
            
            if ($emailResult->num_rows > 0) {
                // Generate unique email by appending timestamp
                $timestamp = time();
                $emailParts = explode('@', $email);
                $email = $emailParts[0] . '_' . $timestamp . '@' . $emailParts[1];
            }
        }

        // Retry logic for handling race conditions
        $maxRetries = 3;
        $retryCount = 0;
        $insertSuccess = false;
        
        while ($retryCount < $maxRetries && !$insertSuccess) {
            if ($hasPatientNumber) {
                // Generate patient number if this is a retry
                if ($retryCount > 0) {
                    $patientNumber = generatePatientId($conn);
                }
                
                // Insert with patient_number column
                $stmt = $conn->prepare("INSERT INTO patients 
                    (patient_number, first_name, middle_name, last_name, suffix, date_of_birth, sex, civil_status, is_senior_citizen, senior_citizen_id, is_pwd, pwd_id, phone, email, 
                    address, emergency_contact_name, relationship, emergency_contact_phone, blood_type, chief_complaint, allergies) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssissssssssssss", $patientNumber, $firstName, $middleName, $lastName, $suffix, $dateOfBirth, $sex, $civilStatus, $isSeniorCitizen, $seniorCitizenId, $isPwd, $pwdId, $phone, $email, 
                    $address, $emergencyContactName, $emergencyContactRelationship, $emergencyContactPhone, $bloodType, $chiefComplaint, $allergies);
            } else {
                // Insert without patient_number column (fallback)
                $stmt = $conn->prepare("INSERT INTO patients 
                    (first_name, middle_name, last_name, suffix, date_of_birth, sex, civil_status, is_senior_citizen, senior_citizen_id, is_pwd, pwd_id, phone, email, 
                    address, emergency_contact_name, relationship, emergency_contact_phone, blood_type, chief_complaint, allergies) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssissssssssssss", $firstName, $middleName, $lastName, $suffix, $dateOfBirth, $sex, $civilStatus, $isSeniorCitizen, $seniorCitizenId, $isPwd, $pwdId, $phone, $email, 
                    $address, $emergencyContactName, $emergencyContactRelationship, $emergencyContactPhone, $bloodType, $chiefComplaint, $allergies);
            }

            if ($stmt->execute()) {
                $patientId = $conn->insert_id;
                $success = true;
                $successMessage = "Patient successfully added with ID: " . ($hasPatientNumber ? $patientNumber : $patientId);
                if ($email && strpos($email, '_') !== false) {
                    $successMessage .= "<br><small class='text-muted'>Note: Email was modified to ensure uniqueness: " . htmlspecialchars($email) . "</small>";
                }
                $insertSuccess = true;
            } else {
                // Check if it's a duplicate key error (1062)
                if ($conn->errno === 1062 && $hasPatientNumber && $retryCount < $maxRetries - 1) {
                    // Duplicate patient_number, retry with a new number
                    $retryCount++;
                    usleep(100000); // Wait 0.1 seconds before retry
                    continue;
                } else {
                    $error = "Error creating patient record: " . $conn->error . " (Error Code: " . $conn->errno . ")";
                    break;
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm_duplicate'])) {
    $firstName = sanitize($_POST['first_name']);
    $middleName = sanitize($_POST['middle_name']);
    $lastName = sanitize($_POST['last_name']);
    $suffix = sanitize($_POST['suffix']);
    $dateOfBirth = sanitize($_POST['date_of_birth']);
    $sex = sanitize($_POST['sex']);
    $civilStatus = sanitize($_POST['civil_status']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $emergencyContactName = sanitize($_POST['emergency_contact_name']);
    $emergencyContactRelationship = sanitize($_POST['emergency_contact_relationship']);
    $bloodType = sanitize($_POST['blood_type']);
    $chiefComplaint = sanitize($_POST['chief_complaint']);
    $isSeniorCitizen = isset($_POST['is_senior_citizen']) ? 1 : 0;
    $seniorCitizenId = sanitize($_POST['senior_citizen_id']);
    $isPwd = isset($_POST['is_pwd']) ? 1 : 0;
    $pwdId = sanitize($_POST['pwd_id']);

    $rawPhone = trim($_POST['phone']);
    $rawEmergency = trim($_POST['emergency_contact_phone']);

    // Validate that input contains exactly 10 digits
    $phoneDigits = preg_replace('/[^\d]/', '', $rawPhone);
    $emergencyDigits = preg_replace('/[^\d]/', '', $rawEmergency);

    if (empty($rawPhone) || strlen($phoneDigits) !== 10) {
        $error = "Patient phone number must be exactly 10 digits. Country code +63 is fixed.";
    }

    if (!isset($error) && (empty($rawEmergency) || strlen($emergencyDigits) !== 10)) {
        $error = "Emergency contact phone must be exactly 10 digits. Country code +63 is fixed.";
    }

    // Combine +63 prefix with the validated input (which is exactly 10 digits)
    $phone = !empty($rawPhone) && !isset($error) ? '+63' . $phoneDigits : '';
    $emergencyContactPhone = !empty($rawEmergency) && !isset($error) ? '+63' . $emergencyDigits : '';

    // Helper function to validate name fields (no numbers allowed)
    function isValidName($name) {
        if (empty($name)) return true; // Empty is handled by required validation
        // Allow letters, spaces, hyphens, apostrophes, and periods (common in names)
        // Reject if contains any digits
        return !preg_match('/\d/', $name);
    }

    // Validate all required fields
    if (!$firstName || !$lastName || !$dateOfBirth || !$sex || !$civilStatus || 
        !$email || !$address || !$emergencyContactName || !$emergencyContactRelationship || 
        !$bloodType || !$rawPhone || !$rawEmergency) {
        $error = "Please fill in all required fields";
    } elseif (!isValidName($firstName)) {
        $error = "First name cannot contain numbers. Please enter a valid name.";
    } elseif (!isValidName($lastName)) {
        $error = "Last name cannot contain numbers. Please enter a valid name.";
    } elseif (!empty($middleName) && !isValidName($middleName)) {
        $error = "Middle name cannot contain numbers. Please enter a valid name.";
    } elseif (!isValidName($emergencyContactName)) {
        $error = "Emergency contact name cannot contain numbers. Please enter a valid name.";
    } elseif ($isSeniorCitizen && $isPwd) {
        $error = "Senior Citizen and PWD cannot both be checked. Please select only one.";
    } elseif ($isSeniorCitizen && !$seniorCitizenId) {
        $error = "Senior Citizen ID is required when Senior Citizen is checked";
    } elseif ($isSeniorCitizen && !empty($dateOfBirth)) {
        // Validate age for senior citizen (must be 60 years or older)
        $birthDate = new DateTime($dateOfBirth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 60) {
            $error = "Warning: The birth date indicates the patient is {$age} years old. Senior Citizen status requires age 60 or older. Please verify the birth date or uncheck Senior Citizen if incorrect.";
        }
    } elseif ($isPwd && !$pwdId) {
        $error = "PWD ID is required when PWD is checked";
    } else {
        // Check for duplicate by name and date of birth
        $stmt = $conn->prepare("SELECT id, patient_number, first_name, middle_name, last_name, suffix, date_of_birth, sex, civil_status, phone, email, address, emergency_contact_name, emergency_contact_phone, relationship, blood_type, chief_complaint, is_senior_citizen, senior_citizen_id, is_pwd, pwd_id, created_at FROM patients WHERE first_name = ? AND last_name = ? AND date_of_birth = ?");
        $stmt->bind_param("sss", $firstName, $lastName, $dateOfBirth);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $duplicatePatient = $result->fetch_assoc();
            $duplicateWarning = true;
            $duplicateData = $duplicatePatient;
        }

        if (!isset($duplicateWarning) && $email) {
            $stmt = $conn->prepare("SELECT id FROM patients WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "A patient with this email address already exists. Please use a different email.";
            }
        }

        if (!isset($error) && !isset($duplicateWarning)) {
            // Generate unique patient ID
            $patientNumber = generatePatientId($conn);
            $allergies = ''; // Default empty allergies field

            // Check if patient_number column exists, if not use regular insert
            $checkColumn = $conn->query("SHOW COLUMNS FROM patients LIKE 'patient_number'");
            $hasPatientNumber = $checkColumn->num_rows > 0;

            // Retry logic for handling race conditions
            $maxRetries = 3;
            $retryCount = 0;
            $insertSuccess = false;
            
            while ($retryCount < $maxRetries && !$insertSuccess) {
                if ($hasPatientNumber) {
                    // Generate patient number if this is a retry
                    if ($retryCount > 0) {
                        $patientNumber = generatePatientId($conn);
                    }
                    
                    // Insert with patient_number column
                    $stmt = $conn->prepare("INSERT INTO patients 
                        (patient_number, first_name, middle_name, last_name, suffix, date_of_birth, sex, civil_status, is_senior_citizen, senior_citizen_id, is_pwd, pwd_id, phone, email, 
                        address, emergency_contact_name, relationship, emergency_contact_phone, blood_type, chief_complaint, allergies) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssssissssssssssss", $patientNumber, $firstName, $middleName, $lastName, $suffix, $dateOfBirth, $sex, $civilStatus, $isSeniorCitizen, $seniorCitizenId, $isPwd, $pwdId, $phone, $email, 
                        $address, $emergencyContactName, $emergencyContactRelationship, $emergencyContactPhone, $bloodType, $chiefComplaint, $allergies);
                } else {
                    // Insert without patient_number column (fallback)
                    $stmt = $conn->prepare("INSERT INTO patients 
                        (first_name, middle_name, last_name, suffix, date_of_birth, sex, civil_status, is_senior_citizen, senior_citizen_id, is_pwd, pwd_id, phone, email, 
                        address, emergency_contact_name, relationship, emergency_contact_phone, blood_type, chief_complaint, allergies) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssssissssssssssss", $firstName, $middleName, $lastName, $suffix, $dateOfBirth, $sex, $civilStatus, $isSeniorCitizen, $seniorCitizenId, $isPwd, $pwdId, $phone, $email, 
                        $address, $emergencyContactName, $emergencyContactRelationship, $emergencyContactPhone, $bloodType, $chiefComplaint, $allergies);
                }

                if ($stmt->execute()) {
                    $patientId = $conn->insert_id;
                    $success = true;
                    $successMessage = "Patient successfully added with ID: " . ($hasPatientNumber ? $patientNumber : $patientId);
                    $insertSuccess = true;
                } else {
                    // Check if it's a duplicate key error (1062)
                    if ($conn->errno === 1062 && $hasPatientNumber && $retryCount < $maxRetries - 1) {
                        // Duplicate patient_number, retry with a new number
                        $retryCount++;
                        usleep(100000); // Wait 0.1 seconds before retry
                        continue;
                    } else {
                        $error = ($conn->errno === 1062)
                            ? "Duplicate patient entry detected. Please verify patient information."
                            : "Error creating patient record: " . $conn->error . " (Error Code: " . $conn->errno . ")";
                        break;
                    }
                }
            }
        }
    }
}

include 'includes/header.php';
?>


<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Patient Information</h5>
                <a href="patients.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Success!</strong> <?php echo $successMessage; ?>
                        <div class="mt-2">
                            <a href="patients.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-list me-1"></i> View All Patients
                            </a>
                            <a href="add_patient.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-1"></i> Add Another Patient
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!isset($success) || !$success): ?>
                <?php if (isset($duplicateWarning) && $duplicateWarning): ?>
                    <!-- Duplicate Warning Modal -->
                    <div class="modal fade show" id="duplicateModal" tabindex="-1" style="display: block;" data-bs-backdrop="static" data-bs-keyboard="false">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Duplicate Patient Detected
                                    </h5>
                                </div>
                                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                    <div class="alert alert-warning">
                                        <strong>Warning:</strong> A patient with the same name and date of birth already exists in the system.
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Existing Patient Details:</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <strong>Patient ID:</strong><br>
                                                        <span class="text-primary"><?php echo htmlspecialchars($duplicateData['patient_number'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Full Name:</strong><br>
                                                        <?php 
                                                        $fullName = trim($duplicateData['first_name'] . ' ' . ($duplicateData['middle_name'] ? $duplicateData['middle_name'] . ' ' : '') . $duplicateData['last_name'] . ($duplicateData['suffix'] ? ' ' . $duplicateData['suffix'] : ''));
                                                        echo htmlspecialchars($fullName);
                                                        ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Date of Birth:</strong><br>
                                                        <?php echo date('F j, Y', strtotime($duplicateData['date_of_birth'])); ?>
                                                        <small class="text-muted">(<?php echo date_diff(date_create($duplicateData['date_of_birth']), date_create('today'))->y; ?> years old)</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Gender:</strong><br>
                                                        <?php echo htmlspecialchars($duplicateData['sex']); ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Civil Status:</strong><br>
                                                        <?php echo htmlspecialchars($duplicateData['civil_status'] ?: 'Not specified'); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <strong>Contact Number:</strong><br>
                                                        <i class="fas fa-phone text-success me-1"></i>
                                                        <?php echo htmlspecialchars(formatPhoneNumber($duplicateData['phone'] ?? '') ?: 'Not provided'); ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Email Address:</strong><br>
                                                        <i class="fas fa-envelope text-info me-1"></i>
                                                        <?php echo htmlspecialchars($duplicateData['email'] ?: 'Not provided'); ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Address:</strong><br>
                                                        <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                        <?php echo htmlspecialchars($duplicateData['address'] ?: 'Not provided'); ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Blood Type:</strong><br>
                                                        <i class="fas fa-tint text-danger me-1"></i>
                                                        <?php echo htmlspecialchars($duplicateData['blood_type'] ?: 'Not specified'); ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Emergency Contact:</strong><br>
                                                        <i class="fas fa-user-friends text-warning me-1"></i>
                                                        <?php 
                                                        if ($duplicateData['emergency_contact_name']) {
                                                            echo htmlspecialchars($duplicateData['emergency_contact_name']);
                                                            if ($duplicateData['relationship']) {
                                                                echo ' (' . htmlspecialchars($duplicateData['relationship']) . ')';
                                                            }
                                                            if ($duplicateData['emergency_contact_phone']) {
                                                                echo '<br><small class="text-muted">' . htmlspecialchars(formatPhoneNumber($duplicateData['emergency_contact_phone'])) . '</small>';
                                                            }
                                                        } else {
                                                            echo 'Not provided';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Special Status Information -->
                                            <?php if ($duplicateData['is_senior_citizen'] || $duplicateData['is_pwd']): ?>
                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <div class="alert alert-info">
                                                        <h6 class="mb-2"><i class="fas fa-info-circle me-1"></i> Special Status:</h6>
                                                        <div class="row">
                                                            <?php if ($duplicateData['is_senior_citizen']): ?>
                                                            <div class="col-md-6">
                                                                <i class="fas fa-user-clock text-primary me-1"></i>
                                                                <strong>Senior Citizen</strong>
                                                                <?php if ($duplicateData['senior_citizen_id']): ?>
                                                                    <br><small class="text-muted">ID: <?php echo htmlspecialchars($duplicateData['senior_citizen_id']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($duplicateData['is_pwd']): ?>
                                                            <div class="col-md-6">
                                                                <i class="fas fa-wheelchair text-secondary me-1"></i>
                                                                <strong>Person with Disability (PWD)</strong>
                                                                <?php if ($duplicateData['pwd_id']): ?>
                                                                    <br><small class="text-muted">ID: <?php echo htmlspecialchars($duplicateData['pwd_id']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Medical Information -->
                                            <?php if ($duplicateData['chief_complaint']): ?>
                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <div class="alert alert-light">
                                                        <h6 class="mb-2"><i class="fas fa-stethoscope text-primary me-1"></i> Chief Complaint:</h6>
                                                        <p class="mb-0"><?php echo htmlspecialchars($duplicateData['chief_complaint']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Registration Information -->
                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-plus me-1"></i>
                                                        Registered on: <?php echo date('F j, Y \a\t g:i A', strtotime($duplicateData['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <p class="mb-2"><strong>What would you like to do?</strong></p>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i><strong>Continue:</strong> Add this as a new patient with a unique ID (PT-2025-XXXXX)</li>
                                            <li><i class="fas fa-times text-danger me-2"></i><strong>Cancel:</strong> Go back and modify the information</li>
                                        </ul>
                                        
                                        <?php if (isset($_POST['email']) && $_POST['email']): ?>
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Email Note:</strong> If the email address already exists in the system, it will be automatically modified to ensure uniqueness (e.g., user_123456789@domain.com).
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <form method="POST" style="display: inline;">
                                        <!-- Preserve all form data -->
                                        <input type="hidden" name="confirm_duplicate" value="1">
                                        <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name']); ?>">
                                        <input type="hidden" name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name']); ?>">
                                        <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name']); ?>">
                                        <input type="hidden" name="suffix" value="<?php echo htmlspecialchars($_POST['suffix']); ?>">
                                        <input type="hidden" name="date_of_birth" value="<?php echo htmlspecialchars($_POST['date_of_birth']); ?>">
                                        <input type="hidden" name="sex" value="<?php echo htmlspecialchars($_POST['sex']); ?>">
                                        <input type="hidden" name="civil_status" value="<?php echo htmlspecialchars($_POST['civil_status']); ?>">
                                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_POST['email']); ?>">
                                        <input type="hidden" name="address" value="<?php echo htmlspecialchars($_POST['address']); ?>">
                                        <input type="hidden" name="emergency_contact_name" value="<?php echo htmlspecialchars($_POST['emergency_contact_name']); ?>">
                                        <input type="hidden" name="emergency_contact_relationship" value="<?php echo htmlspecialchars($_POST['emergency_contact_relationship']); ?>">
                                        <input type="hidden" name="blood_type" value="<?php echo htmlspecialchars($_POST['blood_type']); ?>">
                                        <input type="hidden" name="chief_complaint" value="<?php echo htmlspecialchars($_POST['chief_complaint']); ?>">
                                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($_POST['phone']); ?>">
                                        <input type="hidden" name="emergency_contact_phone" value="<?php echo htmlspecialchars($_POST['emergency_contact_phone']); ?>">
                                        <?php if (isset($_POST['is_senior_citizen'])): ?>
                                            <input type="hidden" name="is_senior_citizen" value="1">
                                        <?php endif; ?>
                                        <input type="hidden" name="senior_citizen_id" value="<?php echo htmlspecialchars($_POST['senior_citizen_id']); ?>">
                                        <?php if (isset($_POST['is_pwd'])): ?>
                                            <input type="hidden" name="is_pwd" value="1">
                                        <?php endif; ?>
                                        <input type="hidden" name="pwd_id" value="<?php echo htmlspecialchars($_POST['pwd_id']); ?>">
                                        
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus me-1"></i> Continue Adding Patient
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger" onclick="closeDuplicateModal()">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal backdrop -->
                    <div class="modal-backdrop fade show"></div>
                <?php endif; ?>

                <form method="POST" onsubmit="return validateForm()">
                    <!-- Name Fields -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="first_name" required 
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                                   title="First name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                   oninput="validateNameField(this, 'First name')">
                            <div class="invalid-feedback">First name cannot contain numbers.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" id="middle_name" 
                                   value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>"
                                   title="Middle name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                   oninput="validateNameField(this, 'Middle name')">
                            <div class="invalid-feedback">Middle name cannot contain numbers.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="last_name" required 
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                                   title="Last name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                   oninput="validateNameField(this, 'Last name')">
                            <div class="invalid-feedback">Last name cannot contain numbers.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Suffix</label>
                            <select class="form-select" name="suffix">
                                <option value="">Select Suffix (Optional)</option>
                                <option value="Jr." <?php echo (isset($_POST['suffix']) && $_POST['suffix'] === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo (isset($_POST['suffix']) && $_POST['suffix'] === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] === 'II') ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] === 'III') ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] === 'IV') ? 'selected' : ''; ?>>IV</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_of_birth" id="date_of_birth" required 
                                   max="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>"
                                   onchange="validateSeniorAge()">
                        </div>
                    </div>

                    <!-- Sex and Civil Status -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sex <span class="text-danger">*</span></label>
                            <select class="form-select" name="sex" required>
                                <option value="">Select Sex</option>
                                <option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Civil Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="civil_status" required>
                                <option value="">Select Civil Status</option>
                                <option value="Single" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Separated" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                            </select>
                        </div>
                    </div>

                    <!-- Senior Citizen and PWD Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_senior_citizen" id="is_senior_citizen" value="1" 
                                       <?php echo (isset($_POST['is_senior_citizen'])) ? 'checked' : ''; ?> onchange="toggleSeniorFields()">
                                <label class="form-check-label" for="is_senior_citizen">
                                    <strong>Senior Citizen</strong>
                                </label>
                            </div>
                            <div id="senior_fields" style="display: none; margin-top: 10px;">
                                <div id="senior_age_warning" class="alert alert-warning" style="display: none; margin-bottom: 10px;">
                                    <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> <span id="senior_age_warning_text"></span>
                                </div>
                                <label class="form-label">Senior Citizen ID Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="senior_citizen_id"
                                       value="<?php echo isset($_POST['senior_citizen_id']) ? htmlspecialchars($_POST['senior_citizen_id']) : ''; ?>"
                                       placeholder="Enter Senior Citizen ID">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_pwd" id="is_pwd" value="1" 
                                       <?php echo (isset($_POST['is_pwd'])) ? 'checked' : ''; ?> onchange="togglePwdFields()">
                                <label class="form-check-label" for="is_pwd">
                                    <strong>Person with Disability (PWD)</strong>
                                </label>
                            </div>
                            <div id="pwd_fields" style="display: none; margin-top: 10px;">
                                <label class="form-label">PWD ID Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="pwd_id"
                                       value="<?php echo isset($_POST['pwd_id']) ? htmlspecialchars($_POST['pwd_id']) : ''; ?>"
                                       placeholder="Enter PWD ID">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+63</span>
                                <input type="tel" class="form-control" name="phone" id="phone-input" required pattern="^\d{10}$" inputmode="numeric" maxlength="10" placeholder="9123456789" value="<?php echo isset($_POST['phone']) ? htmlspecialchars(preg_replace('/^\+63|^0/', '', phoneToInputFormat($_POST['phone']))) : ''; ?>">
                            </div>
                            <small class="text-muted">Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div class="mb-3">
                        <label class="form-label">Address <span class="text-danger">*</span></label>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Region <span class="text-danger">*</span></label>
                                <select id="region" class="form-select" required onchange="loadProvinces()"></select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Province <span class="text-danger">*</span></label>
                                <select id="province" class="form-select" required onchange="loadCities()"></select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">City/Municipality <span class="text-danger">*</span></label>
                                <select id="city" class="form-select" required onchange="loadBarangays()"></select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Barangay <span class="text-danger">*</span></label>
                                <select id="barangay" class="form-select" required onchange="combineAddress()"></select>
                            </div>
                        </div>
                        <!-- Hidden input to store full address -->
                        <input type="hidden" id="full_address" name="address" required>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Emergency Contact Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="emergency_contact_name" id="emergency_contact_name" required 
                                   value="<?php echo isset($_POST['emergency_contact_name']) ? htmlspecialchars($_POST['emergency_contact_name']) : ''; ?>"
                                   title="Emergency contact name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed."
                                   oninput="validateNameField(this, 'Emergency contact name')">
                            <div class="invalid-feedback">Emergency contact name cannot contain numbers.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Relationship to Emergency Contact <span class="text-danger">*</span></label>
                            <select class="form-select" name="emergency_contact_relationship" required>
                                <option value="">Select Relationship</option>
                                <option value="Spouse" <?php echo (isset($_POST['emergency_contact_relationship']) && $_POST['emergency_contact_relationship'] === 'Spouse') ? 'selected' : ''; ?>>Spouse</option>
                                <option value="Parent" <?php echo (isset($_POST['emergency_contact_relationship']) && $_POST['emergency_contact_relationship'] === 'Parent') ? 'selected' : ''; ?>>Parent</option>
                                <option value="Child" <?php echo (isset($_POST['emergency_contact_relationship']) && $_POST['emergency_contact_relationship'] === 'Child') ? 'selected' : ''; ?>>Child</option>
                                <option value="Sibling" <?php echo (isset($_POST['emergency_contact_relationship']) && $_POST['emergency_contact_relationship'] === 'Sibling') ? 'selected' : ''; ?>>Sibling</option>
                                <option value="Friend" <?php echo (isset($_POST['emergency_contact_relationship']) && $_POST['emergency_contact_relationship'] === 'Friend') ? 'selected' : ''; ?>>Friend</option>
                                <option value="Guardian" <?php echo (isset($_POST['emergency_contact_relationship']) && $_POST['emergency_contact_relationship'] === 'Guardian') ? 'selected' : ''; ?>>Guardian</option>
                                <option value="Other" <?php echo (isset($_POST['emergency_contact_relationship']) && $_POST['emergency_contact_relationship'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Emergency Contact Phone <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+63</span>
                                <input type="tel" class="form-control" name="emergency_contact_phone" id="emergency-phone-input" required pattern="^\d{10}$" inputmode="numeric" maxlength="10" placeholder="9123456789" value="<?php echo isset($_POST['emergency_contact_phone']) ? htmlspecialchars(preg_replace('/^\+63|^0/', '', phoneToInputFormat($_POST['emergency_contact_phone']))) : ''; ?>">
                            </div>
                            <small class="text-muted">Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.</small>
                        </div>
                    </div>

                    <!-- Medical Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Blood Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="blood_type" required>
                                <option value="">Select Blood Type</option>
                                <?php
                                $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                                foreach ($bloodTypes as $type):
                                ?>
                                    <option value="<?php echo $type; ?>" <?php echo (isset($_POST['blood_type']) && $_POST['blood_type'] === $type) ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chief Complaint</label>
                            <textarea class="form-control" name="chief_complaint" rows="2" placeholder="Primary reason for visit or main health concern"><?php echo isset($_POST['chief_complaint']) ? htmlspecialchars($_POST['chief_complaint']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Add Patient</button>
                        <a href="patients.php" class="btn btn-danger">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let PH_LOCATIONS = {};

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
  });

function populateRegions() {
  const regionSelect = document.getElementById('region');
  regionSelect.innerHTML = '<option value="">Select Region</option>';
  for (let region in PH_LOCATIONS) {
    regionSelect.innerHTML += `<option value="${region}">${region}</option>`;
  }
}

function loadProvinces() {
  const region = document.getElementById('region').value;
  const provinceSelect = document.getElementById('province');
  provinceSelect.innerHTML = '<option value="">Select Province</option>';
  for (let province in PH_LOCATIONS[region]) {
    provinceSelect.innerHTML += `<option value="${province}">${province}</option>`;
  }
  document.getElementById('city').innerHTML = '<option value="">Select City</option>';
  document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
  combineAddress();
}

function loadCities() {
  const region = document.getElementById('region').value;
  const province = document.getElementById('province').value;
  const citySelect = document.getElementById('city');
  citySelect.innerHTML = '<option value="">Select City</option>';
  for (let city in PH_LOCATIONS[region][province]) {
    citySelect.innerHTML += `<option value="${city}">${city}</option>`;
  }
  document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
  combineAddress();
}

function loadBarangays() {
  const region = document.getElementById('region').value;
  const province = document.getElementById('province').value;
  const city = document.getElementById('city').value;
  const barangaySelect = document.getElementById('barangay');
  barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
  PH_LOCATIONS[region][province][city].forEach(brgy => {
    barangaySelect.innerHTML += `<option value="${brgy}">${brgy}</option>`;
  });
  combineAddress();
}

function combineAddress() {
  const r = document.getElementById("region").value;
  const p = document.getElementById("province").value;
  const c = document.getElementById("city").value;
  const b = document.getElementById("barangay").value;
  if (r && p && c && b) {
    document.getElementById("full_address").value = `${b}, ${c}, ${p}, ${r}`;
  } else {
    document.getElementById("full_address").value = '';
  }
}

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

// Function to validate name fields (prevent numbers)
function validateNameField(input, fieldLabel) {
    const value = input.value;
    // Check if contains any digits
    if (/\d/.test(value)) {
        input.setCustomValidity(fieldLabel + ' cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed.');
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else {
        input.setCustomValidity('');
        input.classList.remove('is-invalid');
        if (value.length > 0) {
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
        }
    }
}

function toggleSeniorFields() {
  const checkbox = document.getElementById('is_senior_citizen');
  const fields = document.getElementById('senior_fields');
  const seniorIdInput = document.querySelector('input[name="senior_citizen_id"]');
  const pwdCheckbox = document.getElementById('is_pwd');
  
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
      // Validate age when checkbox is checked
      validateSeniorAge();
    } else {
      fields.style.display = 'none';
      if (seniorIdInput) {
        seniorIdInput.removeAttribute('required');
        seniorIdInput.value = '';
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
  const seniorCheckbox = document.getElementById('is_senior_citizen');
  
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
    } else {
      fields.style.display = 'none';
      if (pwdIdInput) {
        pwdIdInput.removeAttribute('required');
        pwdIdInput.value = '';
      }
    }
  }
}

// Initialize field visibility on page load
document.addEventListener('DOMContentLoaded', function() {
  toggleSeniorFields();
  togglePwdFields();
  
  // Add event listeners to name fields to prevent numbers
  const nameFields = ['first_name', 'middle_name', 'last_name', 'emergency_contact_name'];
  nameFields.forEach(fieldId => {
    const input = document.getElementById(fieldId);
    if (input) {
      // Prevent typing numbers
      input.addEventListener('keypress', function(e) {
        if (/\d/.test(e.key)) {
          e.preventDefault();
          this.setCustomValidity('Name fields cannot contain numbers.');
          this.classList.add('is-invalid');
        }
      });
      
      // Validate on input
      input.addEventListener('input', function() {
        const fieldLabel = fieldId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        validateNameField(this, fieldLabel);
      });
    }
  });
  
  // Validate senior age on date change if senior is checked
  const dateOfBirthInput = document.getElementById('date_of_birth');
  if (dateOfBirthInput) {
    dateOfBirthInput.addEventListener('change', function() {
      if (document.getElementById('is_senior_citizen').checked) {
        validateSeniorAge();
      }
    });
  }
});

// Function to close duplicate modal
function closeDuplicateModal() {
    // Redirect back to the form without the duplicate warning
    window.location.href = 'add_patient.php';
}

// Form validation function
function validateForm() {
    // Validate that Senior Citizen and PWD are not both checked
    const isSeniorCitizen = document.getElementById('is_senior_citizen').checked;
    const isPwd = document.getElementById('is_pwd').checked;
    if (isSeniorCitizen && isPwd) {
        alert('Senior Citizen and PWD cannot both be checked. Please select only one.');
        return false;
    }
    
    // Validate name fields don't contain numbers
    const nameFields = [
        { id: 'first_name', label: 'First name' },
        { id: 'middle_name', label: 'Middle name' },
        { id: 'last_name', label: 'Last name' },
        { id: 'emergency_contact_name', label: 'Emergency contact name' }
    ];
    
    for (const field of nameFields) {
        const input = document.getElementById(field.id);
        if (input && input.value && /\d/.test(input.value)) {
            alert(field.label + ' cannot contain numbers. Please enter a valid name.');
            input.focus();
            return false;
        }
    }
    
    // Ensure address is set
    const region = document.getElementById('region').value;
    const province = document.getElementById('province').value;
    const city = document.getElementById('city').value;
    const barangay = document.getElementById('barangay').value;
    
    if (!region || !province || !city || !barangay) {
        alert('Please select all address fields (Region, Province, City/Municipality, and Barangay)');
        return false;
    }
    
    // Ensure address hidden field is set
    combineAddress();
    const address = document.getElementById('full_address').value;
    if (!address || address.trim() === '') {
        alert('Please complete the address information');
        return false;
    }
    
    // Validate conditional fields
    const seniorId = document.querySelector('input[name="senior_citizen_id"]').value;
    if (isSeniorCitizen && !seniorId.trim()) {
        alert('Please enter Senior Citizen ID Number');
        return false;
    }
    
    // Validate senior citizen age
    if (isSeniorCitizen) {
        const dateOfBirth = document.getElementById('date_of_birth').value;
        if (dateOfBirth) {
            const age = calculateAge(dateOfBirth);
            if (age !== null && age < 60) {
                if (!confirm(`Warning: The birth date indicates the patient is ${age} years old. Senior Citizen status requires age 60 or older. Do you want to continue anyway?`)) {
                    return false;
                }
            }
        }
    }
    
    const pwdId = document.querySelector('input[name="pwd_id"]').value;
    if (isPwd && !pwdId.trim()) {
        alert('Please enter PWD ID Number');
        return false;
    }
    
    return true;
}

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

// Setup phone inputs on page load
document.addEventListener('DOMContentLoaded', function() {
    setupPhoneInput('phone-input');
    setupPhoneInput('emergency-phone-input');
});
</script>

<?php include 'includes/footer.php'; ?>