<?php

define('MHAVIS_EXEC', true);
$page_title = "Add New Doctor";
$active_page = "doctors";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

// Get all departments for the form
$departments_query = "SELECT id, name, description FROM departments ORDER BY name";
$departments_result = $conn->query($departments_query);
$all_departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $all_departments[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name']);
    $middleName = sanitize($_POST['middle_name'] ?? '');
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $specialization = sanitize($_POST['specialization']);
    $address = sanitize($_POST['address']);
    $rawPhone = trim($_POST['phone'] ?? '');
    
    // New PRC fields
    $prcNumber = sanitize($_POST['prc_number']);
    $licenseType = sanitize($_POST['license_type']);

    // Validate phone number is provided and format is correct
    if (empty($rawPhone)) {
        $error = "Phone number is required.";
    } elseif (!preg_match('/^\d{10}$/', preg_replace('/[^\d]/', '', $rawPhone))) {
        $error = "Phone number must be exactly 10 digits. Country code +63 is fixed.";
    } else {
        // Combine +63 prefix with the input (which should be 10 digits)
        $phone = '+63' . preg_replace('/[^\d]/', '', $rawPhone);
        if (!validatePhoneNumber($phone)) {
            $error = "Invalid phone number format. Please enter exactly 10 digits.";
        }
    }

    // Validate address is provided
    if (!isset($error) && empty($address)) {
        $error = "Address is required. Please select Region, Province, City/Municipality, and Barangay.";
    }

    // Validate all required fields including professional information
    if (!isset($error) && (!$firstName || !$lastName || !$email || !$username || !$password || !$specialization || !$prcNumber || !$licenseType)) {
        $error = "Please fill in all required fields.";
    }

    if (!isset($error)) {
        // Check if prc_number column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_number'");
        $prcColumnExists = $checkColumn && $checkColumn->num_rows > 0;
        
        if ($prcColumnExists) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR prc_number = ?");
            if (!$stmt) {
                $error = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("sss", $username, $email, $prcNumber);
                $stmt->execute();
                $result = $stmt->get_result();
            }
        } else {
            // If prc_number column doesn't exist, check only email and username
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            if (!$stmt) {
                $error = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();
            }
        }

        if (!isset($error) && isset($result) && $result->num_rows > 0) {
            $error = $prcColumnExists ? "Username, email, or PRC number already exists." : "Username or email already exists.";
        } else if (!isset($error)) {
            $profileImage = 'uploads/default-profile.png';
            $prcIdImage = null;

            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 5 * 1024 * 1024;

                if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                    $error = "Invalid profile image file type. Only JPG, PNG and GIF are allowed.";
                } elseif ($_FILES['profile_image']['size'] > $maxSize) {
                    $error = "Profile image file size too large. Maximum size is 5MB.";
                } else {
                    $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'uploads/profile_' . time() . '_' . uniqid() . '.' . $extension;

                    if (!is_dir('uploads')) {
                        mkdir('uploads', 0777, true);
                    }

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filename)) {
                        $profileImage = $filename;
                    } else {
                        $error = "Error uploading profile image.";
                    }
                }
            }

            // Handle PRC ID/Government ID upload - make it required
            if (!isset($error)) {
                if (!isset($_FILES['prc_id']) || $_FILES['prc_id']['error'] === 4 || empty($_FILES['prc_id']['name'])) {
                    $error = "PRC ID / Government ID document is required.";
                } elseif ($_FILES['prc_id']['error'] !== 0) {
                    $error = "Error uploading PRC ID document. Please try again.";
                }
            }
            
            if (!isset($error) && isset($_FILES['prc_id']) && $_FILES['prc_id']['error'] === 0 && !empty($_FILES['prc_id']['name'])) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                $maxSize = 10 * 1024 * 1024; // 10MB for documents

                if (!in_array($_FILES['prc_id']['type'], $allowedTypes)) {
                    $error = "Invalid PRC ID file type. Only JPG, PNG, GIF, and PDF are allowed.";
                } elseif ($_FILES['prc_id']['size'] > $maxSize) {
                    $error = "PRC ID file size too large. Maximum size is 10MB.";
                } else {
                    $extension = pathinfo($_FILES['prc_id']['name'], PATHINFO_EXTENSION);
                    $filename = 'uploads/prc_' . time() . '_' . uniqid() . '.' . $extension;

                    if (!is_dir('uploads')) {
                        mkdir('uploads', 0777, true);
                    }

                    if (move_uploaded_file($_FILES['prc_id']['tmp_name'], $filename)) {
                        $prcIdImage = $filename;
                    } else {
                        $error = "Error uploading PRC ID document.";
                    }
                }
            }

            if (!isset($error)) {
                // Get department ID from primary department selection
                // The specialization field now contains the department name
                $primary_dept_id = isset($_POST['primary_department_id']) ? (int)$_POST['primary_department_id'] : 0;
                
                if ($primary_dept_id > 0) {
                    // Verify department exists
                    $stmt = $conn->prepare("SELECT id, name FROM departments WHERE id = ?");
                    if (!$stmt) {
                        $error = "Database error: " . $conn->error;
                    } else {
                        $stmt->bind_param("i", $primary_dept_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $dept_row = $result->fetch_assoc();
                            $department_id = $dept_row['id'];
                            // Use department name as specialization if not overridden
                            if (empty($specialization)) {
                                $specialization = $dept_row['name'];
                            }
                        } else {
                            $error = "Selected primary department does not exist.";
                        }
                    }
                } else {
                    // Fallback: Lookup or create department based on specialization name
                    $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ?");
                    if (!$stmt) {
                        $error = "Database error: " . $conn->error;
                    } else {
                        $stmt->bind_param("s", $specialization);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $department_id = $result->fetch_assoc()['id'];
                        } else {
                            $default_color = '#6c757d';
                            $stmt = $conn->prepare("INSERT INTO departments (name, color) VALUES (?, ?)");
                            if (!$stmt) {
                                $error = "Database error: " . $conn->error;
                            } else {
                                $stmt->bind_param("ss", $specialization, $default_color);
                                if (!$stmt->execute()) {
                                    $error = "Database error: " . $stmt->error;
                                } else {
                                    $department_id = $stmt->insert_id;
                                }
                            }
                        }
                    }
                }

                if (!isset($error)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'Doctor';

                    // Start transaction to ensure both inserts succeed or fail together
                    $conn->begin_transaction();

                    try {
                    // Insert into users table
                    // Check which columns exist before building the query
                    $checkMiddleName = $conn->query("SHOW COLUMNS FROM users LIKE 'middle_name'");
                    $checkPrcNumber = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_number'");
                    $checkLicenseType = $conn->query("SHOW COLUMNS FROM users LIKE 'license_type'");
                    $checkPrcIdDoc = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_id_document'");
                    
                    $hasMiddleName = $checkMiddleName && $checkMiddleName->num_rows > 0;
                    $hasPrcNumber = $checkPrcNumber && $checkPrcNumber->num_rows > 0;
                    $hasLicenseType = $checkLicenseType && $checkLicenseType->num_rows > 0;
                    $hasPrcIdDoc = $checkPrcIdDoc && $checkPrcIdDoc->num_rows > 0;
                    
                    if ($hasPrcNumber && $hasLicenseType && $hasPrcIdDoc) {
                        // All PRC columns exist
                        if ($hasMiddleName) {
                            // Include middle_name if column exists
                            $stmt = $conn->prepare("INSERT INTO users 
                                (username, password, first_name, middle_name, last_name, email, role, specialization, phone, address, profile_image, department_id, prc_number, license_type, prc_id_document) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            $stmt->bind_param("ssssssssssssiss", $username, $hashedPassword, $firstName, $middleName, $lastName, $email, $role, $specialization, $phone, $address, $profileImage, $department_id, $prcNumber, $licenseType, $prcIdImage);
                        } else {
                            // Without middle_name column
                            $stmt = $conn->prepare("INSERT INTO users 
                                (username, password, first_name, last_name, email, role, specialization, phone, address, profile_image, department_id, prc_number, license_type, prc_id_document) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            $stmt->bind_param("ssssssssssisss", $username, $hashedPassword, $firstName, $lastName, $email, $role, $specialization, $phone, $address, $profileImage, $department_id, $prcNumber, $licenseType, $prcIdImage);
                        }
                    } else {
                        // Some PRC columns don't exist, use basic query without PRC fields
                        if ($hasMiddleName) {
                            // Include middle_name if column exists
                            $stmt = $conn->prepare("INSERT INTO users 
                                (username, password, first_name, middle_name, last_name, email, role, specialization, phone, address, profile_image, department_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            $stmt->bind_param("sssssssssssi", $username, $hashedPassword, $firstName, $middleName, $lastName, $email, $role, $specialization, $phone, $address, $profileImage, $department_id);
                        } else {
                            // Without middle_name column
                            $stmt = $conn->prepare("INSERT INTO users 
                                (username, password, first_name, last_name, email, role, specialization, phone, address, profile_image, department_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            $stmt->bind_param("ssssssssssi", $username, $hashedPassword, $firstName, $lastName, $email, $role, $specialization, $phone, $address, $profileImage, $department_id);
                        }
                    }
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Database error: " . $stmt->error);
                    }
                    
                    // Get the newly created user ID
                    $user_id = $conn->insert_id;
                    
                    // Insert into doctors table with the user_id and department_id
                    $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialization, license_number, department_id) 
                                           VALUES (?, ?, ?, ?)");
                    if (!$stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $stmt->bind_param("issi", $user_id, $specialization, $prcNumber, $department_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Database error: " . $stmt->error);
                    }
                    
                    // Check if doctor_departments table exists and process multiple department assignments
                    $checkTable = $conn->query("SHOW TABLES LIKE 'doctor_departments'");
                    if ($checkTable && $checkTable->num_rows > 0) {
                        // Process additional department assignments
                        if (isset($_POST['departments']) && is_array($_POST['departments'])) {
                            foreach ($_POST['departments'] as $dept_index => $dept_data) {
                                if (!empty($dept_data['department_id'])) {
                                    $dept_id = (int)$dept_data['department_id'];
                                    $dept_specialization = sanitize($dept_data['specialization'] ?? '');
                                    $dept_prc_number = sanitize($dept_data['prc_number'] ?? '');
                                    $dept_license_type = sanitize($dept_data['license_type'] ?? '');
                                    $dept_prc_document = null;
                                    
                                    // Handle PRC document upload for this department
                                    if (isset($_FILES['departments']['tmp_name'][$dept_index]['prc_id_document']) 
                                        && $_FILES['departments']['error'][$dept_index]['prc_id_document'] === 0) {
                                        $file = $_FILES['departments'];
                                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                                        $maxSize = 10 * 1024 * 1024; // 10MB
                                        
                                        if (in_array($file['type'][$dept_index]['prc_id_document'], $allowedTypes) 
                                            && $file['size'][$dept_index]['prc_id_document'] <= $maxSize) {
                                            $extension = pathinfo($file['name'][$dept_index]['prc_id_document'], PATHINFO_EXTENSION);
                                            $filename = 'uploads/prc_dept_' . time() . '_' . uniqid() . '_' . $dept_index . '.' . $extension;
                                            
                                            if (!is_dir('uploads')) {
                                                mkdir('uploads', 0777, true);
                                            }
                                            
                                            if (move_uploaded_file($file['tmp_name'][$dept_index]['prc_id_document'], $filename)) {
                                                $dept_prc_document = $filename;
                                            }
                                        }
                                    }
                                    
                                    // Insert into doctor_departments table
                                    $stmt = $conn->prepare("INSERT INTO doctor_departments 
                                                           (doctor_id, department_id, specialization, prc_number, license_type, prc_id_document) 
                                                           VALUES (?, ?, ?, ?, ?, ?)");
                                    if ($stmt) {
                                        $stmt->bind_param("iissss", $user_id, $dept_id, $dept_specialization, 
                                                         $dept_prc_number, $dept_license_type, $dept_prc_document);
                                        $stmt->execute();
                                    }
                                    
                                    // Also add the primary department if not already added
                                    if ($dept_id == $department_id) {
                                        // Update with primary professional info if provided
                                        $stmt = $conn->prepare("UPDATE doctor_departments 
                                                               SET specialization = COALESCE(NULLIF(?, ''), specialization),
                                                                   prc_number = COALESCE(NULLIF(?, ''), prc_number),
                                                                   license_type = COALESCE(NULLIF(?, ''), license_type),
                                                                   prc_id_document = COALESCE(?, prc_id_document)
                                                               WHERE doctor_id = ? AND department_id = ?");
                                        if ($stmt) {
                                            $stmt->bind_param("ssssii", $dept_specialization, $dept_prc_number, 
                                                             $dept_license_type, $dept_prc_document, $user_id, $dept_id);
                                            $stmt->execute();
                                        }
                                    }
                                }
                            }
                            
                            // Ensure primary department is also in doctor_departments
                            $stmt = $conn->prepare("SELECT id FROM doctor_departments WHERE doctor_id = ? AND department_id = ?");
                            $stmt->bind_param("ii", $user_id, $department_id);
                            $stmt->execute();
                            if ($stmt->get_result()->num_rows == 0) {
                                // Add primary department with main professional info
                                $stmt = $conn->prepare("INSERT INTO doctor_departments 
                                                       (doctor_id, department_id, specialization, prc_number, license_type, prc_id_document) 
                                                       VALUES (?, ?, ?, ?, ?, ?)");
                                if ($stmt) {
                                    $stmt->bind_param("iissss", $user_id, $department_id, $specialization, 
                                                     $prcNumber, $licenseType, $prcIdImage);
                                    $stmt->execute();
                                }
                            }
                        } else {
                            // No additional departments, just add the primary one
                            $stmt = $conn->prepare("INSERT INTO doctor_departments 
                                                   (doctor_id, department_id, specialization, prc_number, license_type, prc_id_document) 
                                                   VALUES (?, ?, ?, ?, ?, ?)");
                            if ($stmt) {
                                $stmt->bind_param("iissss", $user_id, $department_id, $specialization, 
                                                 $prcNumber, $licenseType, $prcIdImage);
                                $stmt->execute();
                            }
                        }
                    }
                    
                    // Commit the transaction
                    $conn->commit();
                    
                    header('Location: doctors.php?success=1');
                    exit();
                    } catch (Exception $e) {
                        // Rollback on error
                        $conn->rollback();
                        $error = "Error creating doctor account: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

include 'includes/header.php';
?>


<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Add New Doctor</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Personal Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Personal Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+63</span>
                                <input type="tel" class="form-control" name="phone" id="phone-input" required pattern="^\d{10}$" inputmode="numeric" maxlength="10" placeholder="9123456789" value="<?php echo isset($_POST['phone']) ? htmlspecialchars(preg_replace('/^\+63|^0/', '', phoneToInputFormat($_POST['phone']))) : ''; ?>">
                            </div>
                            <small class="text-muted">Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.</small>
                        </div>

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
                            <input type="hidden" id="full_address" name="address" required>
                        </div>
                    </div>

                    <!-- Professional Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Primary Professional Information</h6>
                        <div class="mb-3">
                             <label class="form-label">Primary Department/Specialization <span class="text-danger">*</span></label>
                            <select class="form-control" name="specialization" id="primary_specialization" required onchange="updatePrimaryDept()">
                                <option value="">Select Primary Department</option>
                                <?php
                                // Use actual departments from database
                                foreach ($all_departments as $dept) {
                                    $selected = (isset($_POST['specialization']) && $_POST['specialization'] === $dept['name']) ? 'selected' : '';
                                    echo "<option value=\"{$dept['name']}\" data-dept-id=\"{$dept['id']}\" $selected>{$dept['name']}";
                                    if ($dept['description']) {
                                        echo " - {$dept['description']}";
                                    }
                                    echo "</option>";
                                }
                                ?>
                            </select>
                            <input type="hidden" name="primary_department_id" id="primary_department_id" value="">
                            <small class="text-muted">This will be the doctor's primary department assignment.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">PRC Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="prc_number" required placeholder="e.g., 0123456" value="<?php echo isset($_POST['prc_number']) ? htmlspecialchars($_POST['prc_number']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">License Type <span class="text-danger">*</span></label>
                            <select class="form-control" name="license_type" required>
                                <option value="">Select License Type</option>
                                <option value="MD" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'MD') ? 'selected' : ''; ?>>Doctor of Medicine (MD)</option>
                                <option value="DMD" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'DMD') ? 'selected' : ''; ?>>Doctor of Dental Medicine (DMD)</option>
                                <option value="DDS" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'DDS') ? 'selected' : ''; ?>>Doctor of Dental Surgery (DDS)</option>
                                <option value="DVM" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'DVM') ? 'selected' : ''; ?>>Doctor of Veterinary Medicine (DVM)</option>
                                <option value="RN" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'RN') ? 'selected' : ''; ?>>Registered Nurse (RN)</option>
                                <option value="RPh" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'RPh') ? 'selected' : ''; ?>>Registered Pharmacist (RPh)</option>
                                <option value="RPT" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'RPT') ? 'selected' : ''; ?>>Registered Physical Therapist (RPT)</option>
                                <option value="Other" <?php echo (isset($_POST['license_type']) && $_POST['license_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Additional Department Assignments Section -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-primary mb-0">Additional Department Licenses</h6>
                            <button type="button" class="btn btn-sm btn-success" id="addDepartmentBtn">
                                <i class="fas fa-plus"></i> Add Department
                            </button>
                        </div>
                        <p class="text-muted small mb-3">If the doctor has licenses for multiple departments, add them here with their professional information.</p>
                        
                        <div id="departmentsContainer">
                            <!-- Dynamic department entries will be added here -->
                        </div>
                    </div>

                    <!-- Account Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Account Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                        </div>
                    </div>

                    <!-- Document Uploads Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Document Uploads</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" name="profile_image" accept="image/*">
                                <small class="text-muted">Max file size: 5MB. Allowed formats: JPG, PNG, GIF</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PRC ID / Government ID <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="prc_id" accept="image/*,.pdf" required>
                                <small class="text-muted">Max file size: 10MB. Allowed formats: JPG, PNG, GIF, PDF</small>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="doctors.php" class="btn btn-danger">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Doctor</button>
                    </div>
                </form>
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

// Department management
let departmentCounter = 0;
const departmentsData = <?php echo json_encode($all_departments); ?>;

function updatePrimaryDept() {
  const primaryDeptSelect = document.getElementById('primary_specialization');
  const primaryDeptIdInput = document.getElementById('primary_department_id');
  const selectedOption = primaryDeptSelect.options[primaryDeptSelect.selectedIndex];
  
  if (selectedOption && selectedOption.dataset.deptId) {
    primaryDeptIdInput.value = selectedOption.dataset.deptId;
  } else {
    primaryDeptIdInput.value = '';
  }
  
  // Update all existing department selects to exclude primary
  updateAllDepartmentSelects();
}

function updateAllDepartmentSelects() {
  const primaryDeptSelect = document.getElementById('primary_specialization');
  const primaryDeptId = primaryDeptSelect.options[primaryDeptSelect.selectedIndex]?.dataset?.deptId;
  
  document.querySelectorAll('.department-select').forEach(select => {
    Array.from(select.options).forEach(option => {
      if (option.value == primaryDeptId && option.value !== '') {
        option.style.display = 'none';
        option.disabled = true;
        if (select.value == primaryDeptId) {
          select.value = '';
        }
      } else {
        option.style.display = '';
        option.disabled = false;
      }
    });
  });
}

function addDepartmentEntry() {
  const container = document.getElementById('departmentsContainer');
  const primaryDeptSelect = document.getElementById('primary_specialization');
  const primaryDeptId = primaryDeptSelect.options[primaryDeptSelect.selectedIndex]?.dataset?.deptId;
  
  const entry = document.createElement('div');
  entry.className = 'card mb-3 department-entry border';
  entry.dataset.index = departmentCounter;
  
  entry.innerHTML = `
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-hospital text-primary me-2"></i>Department License #${departmentCounter + 1}</h6>
        <button type="button" class="btn btn-sm btn-danger remove-department-btn">
          <i class="fas fa-times"></i> Remove
        </button>
      </div>
      
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Department <span class="text-danger">*</span></label>
          <select class="form-select department-select" name="departments[${departmentCounter}][department_id]" required>
            <option value="">-- Select Department --</option>
            ${departmentsData.map(dept => {
              if (dept.id == primaryDeptId) return '';
              return `<option value="${dept.id}">${dept.name}${dept.description ? ' - ' + dept.description : ''}</option>`;
            }).filter(opt => opt !== '').join('')}
          </select>
        </div>
        
        <div class="col-md-6 mb-3">
          <label class="form-label">Specialization for this Department</label>
          <input type="text" class="form-control" name="departments[${departmentCounter}][specialization]" 
                 placeholder="e.g., Cardiology, Internal Medicine">
        </div>
      </div>
      
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">PRC Number</label>
          <input type="text" class="form-control" name="departments[${departmentCounter}][prc_number]" 
                 placeholder="e.g., 0123456">
        </div>
        
        <div class="col-md-6 mb-3">
          <label class="form-label">License Type</label>
          <select class="form-select" name="departments[${departmentCounter}][license_type]">
            <option value="">-- Select License Type --</option>
            <option value="MD">Doctor of Medicine (MD)</option>
            <option value="DMD">Doctor of Dental Medicine (DMD)</option>
            <option value="DDS">Doctor of Dental Surgery (DDS)</option>
            <option value="DVM">Doctor of Veterinary Medicine (DVM)</option>
            <option value="RN">Registered Nurse (RN)</option>
            <option value="RPh">Registered Pharmacist (RPh)</option>
            <option value="RPT">Registered Physical Therapist (RPT)</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>
      
      <div class="mb-3">
        <label class="form-label">PRC ID / Government ID Document for this Department</label>
        <input type="file" class="form-control" name="departments[${departmentCounter}][prc_id_document]" 
               accept="image/*,.pdf">
        <small class="text-muted">Max file size: 10MB. Allowed formats: JPG, PNG, GIF, PDF</small>
      </div>
    </div>
  `;
  
  container.appendChild(entry);
  departmentCounter++;
  
  // Add remove button event listener
  entry.querySelector('.remove-department-btn').addEventListener('click', function() {
    entry.remove();
    updateDepartmentNumbers();
  });
  
  // Update department options when primary changes
  updateDepartmentOptions(entry);
}

function updateDepartmentNumbers() {
  const entries = document.querySelectorAll('.department-entry');
  entries.forEach((entry, index) => {
    const header = entry.querySelector('h6');
    header.innerHTML = `<i class="fas fa-hospital text-primary me-2"></i>Department License #${index + 1}`;
  });
}

function updateDepartmentOptions(entry) {
  const primaryDeptSelect = document.getElementById('primary_specialization');
  const primaryDeptId = primaryDeptSelect.options[primaryDeptSelect.selectedIndex]?.dataset?.deptId;
  const deptSelect = entry.querySelector('.department-select');
  
  if (deptSelect && primaryDeptId) {
    // Filter out primary department from options
    Array.from(deptSelect.options).forEach(option => {
      if (option.value == primaryDeptId) {
        option.style.display = 'none';
      }
    });
  }
}

// Form validation before submission
document.addEventListener('DOMContentLoaded', function() {
  // Initialize primary department ID
  updatePrimaryDept();
  
  // Add department button event
  document.getElementById('addDepartmentBtn').addEventListener('click', addDepartmentEntry);
  
  // Update department options when primary department changes
  document.getElementById('primary_specialization').addEventListener('change', updatePrimaryDept);
  
  const form = document.querySelector('form[method="POST"]');
  if (form) {
    form.addEventListener('submit', function(e) {
      const region = document.getElementById("region").value;
      const province = document.getElementById("province").value;
      const city = document.getElementById("city").value;
      const barangay = document.getElementById("barangay").value;
      
      if (!region || !province || !city || !barangay) {
        e.preventDefault();
        alert('Please select Region, Province, City/Municipality, and Barangay for the address.');
        return false;
      }
      
      // Ensure address is set before submission
      combineAddress();
      
      const address = document.getElementById("full_address").value;
      if (!address) {
        e.preventDefault();
        alert('Address is required. Please select all address fields.');
        return false;
      }
      
      // Validate department entries
      const departmentEntries = document.querySelectorAll('.department-entry');
      let hasErrors = false;
      departmentEntries.forEach((entry, index) => {
        const deptId = entry.querySelector('[name*="[department_id]"]').value;
        if (!deptId) {
          hasErrors = true;
          entry.classList.add('border-danger');
        } else {
          entry.classList.remove('border-danger');
        }
      });
      
      if (hasErrors) {
        e.preventDefault();
        alert('Please select a department for all additional department entries, or remove empty entries.');
        return false;
      }
      
      return true;
    });
  }
  
  // Phone number input validation - only allow digits, max 10 digits
  const phoneInput = document.getElementById('phone-input');
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
});
</script>

<?php include 'includes/footer.php'; ?>