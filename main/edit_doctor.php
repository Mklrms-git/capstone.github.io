<?php
define('MHAVIS_EXEC', true);
$page_title = "Edit Doctor";
$active_page = "doctors";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();
$doctorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'Doctor'");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: doctors.php');
    exit();
}

$doctor = $result->fetch_assoc();

// Ensure phone is formatted for display on initial load
if (!empty($doctor['phone'])) {
    $doctor['phone'] = phoneToInputFormat($doctor['phone']);
}

// Parse address properly
$addressParts = explode(", ", $doctor['address'] ?? '');
$barangayValue = trim($addressParts[0] ?? '');
$cityValue = trim($addressParts[1] ?? '');
$provinceValue = trim($addressParts[2] ?? '');
$regionValue = trim($addressParts[3] ?? '');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $username = sanitize($_POST['username']);
    $specialization = sanitize($_POST['specialization']);
    $address = sanitize($_POST['address']);
    
    // New PRC fields
    $prcNumber = sanitize($_POST['prc_number']);
    $licenseType = sanitize($_POST['license_type']);

    $phone = '';
    if (!empty($_POST['phone'])) {
        if (!validatePhoneNumber($_POST['phone'])) {
            $errors[] = "Invalid phone number format. Use format 09123456789 or +639123456789.";
        } else {
            $phone = normalizePhoneNumber($_POST['phone']);
        }
    } else {
        $errors[] = "Phone number is required.";
    }

    // Validate all required fields are not empty
    if (empty(trim($firstName))) {
        $errors[] = "First name is required and cannot be empty.";
    }
    if (empty(trim($lastName))) {
        $errors[] = "Last name is required and cannot be empty.";
    }
    if (empty(trim($email))) {
        $errors[] = "Email is required and cannot be empty.";
    }
    if (empty(trim($username))) {
        $errors[] = "Username is required and cannot be empty.";
    }
    if (empty(trim($prcNumber))) {
        $errors[] = "PRC number is required and cannot be empty.";
    }
    if (empty(trim($licenseType))) {
        $errors[] = "License type is required and cannot be empty.";
    }
    if (empty(trim($specialization))) {
        $errors[] = "Specialization is required and cannot be empty.";
    }
    
    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {
        // Check for duplicate email, username, or PRC number (excluding current doctor)
        // First check if prc_number column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_number'");
        $prcColumnExists = $checkColumn && $checkColumn->num_rows > 0;
        
        if ($prcColumnExists) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ? OR prc_number = ?) AND id != ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $email, $username, $prcNumber, $doctorId);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $errors[] = "Database error: " . $conn->error;
                $result = null;
            }
        } else {
            // If prc_number column doesn't exist, check only email and username
            $stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $email, $username, $doctorId);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $errors[] = "Database error: " . $conn->error;
                $result = null;
            }
        }

        if ($result && $result->num_rows > 0) {
            $errors[] = "Email, username, or PRC number already exists.";
        } else if (empty($errors)) {
            $profileImage = $doctor['profile_image'] ?? '';
            $prcIdImage = $doctor['prc_id_document'] ?? null;

            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 5 * 1024 * 1024;

                if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                    $errors[] = "Invalid profile image file type. Only JPG, PNG and GIF are allowed.";
                } elseif ($_FILES['profile_image']['size'] > $maxSize) {
                    $errors[] = "Profile image file size too large. Maximum size is 5MB.";
                } else {
                    $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'uploads/profile_' . time() . '_' . uniqid() . '.' . $extension;
                    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filename)) {
                        if ($profileImage && $profileImage !== 'uploads/default-profile.png' && file_exists($profileImage)) {
                            unlink($profileImage);
                        }
                        $profileImage = $filename;
                    } else {
                        $errors[] = "Error uploading profile image.";
                    }
                }
            }

            // Handle PRC ID/Government ID upload
            if (empty($errors) && isset($_FILES['prc_id']) && $_FILES['prc_id']['error'] === 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                $maxSize = 10 * 1024 * 1024; // 10MB for documents

                if (!in_array($_FILES['prc_id']['type'], $allowedTypes)) {
                    $errors[] = "Invalid PRC ID file type. Only JPG, PNG, GIF, and PDF are allowed.";
                } elseif ($_FILES['prc_id']['size'] > $maxSize) {
                    $errors[] = "PRC ID file size too large. Maximum size is 10MB.";
                } else {
                    $extension = pathinfo($_FILES['prc_id']['name'], PATHINFO_EXTENSION);
                    $filename = 'uploads/prc_' . time() . '_' . uniqid() . '.' . $extension;
                    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                    if (move_uploaded_file($_FILES['prc_id']['tmp_name'], $filename)) {
                        if ($prcIdImage && file_exists($prcIdImage)) {
                            unlink($prcIdImage);
                        }
                        $prcIdImage = $filename;
                    } else {
                        $errors[] = "Error uploading PRC ID document.";
                    }
                }
            }

            if (empty($errors)) {
                // Lookup or create department based on specialization
                $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ?");
                $stmt->bind_param("s", $specialization);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $department_id = $result->fetch_assoc()['id'];
                } else {
                    $default_color = '#6c757d';
                    $stmt = $conn->prepare("INSERT INTO departments (name, color) VALUES (?, ?)");
                    $stmt->bind_param("ss", $specialization, $default_color);
                    $stmt->execute();
                    $department_id = $stmt->insert_id;
                }

                // Check which PRC columns exist
                $checkPrcNumber = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_number'");
                $checkLicenseType = $conn->query("SHOW COLUMNS FROM users LIKE 'license_type'");
                $checkPrcIdDoc = $conn->query("SHOW COLUMNS FROM users LIKE 'prc_id_document'");
                
                $prcNumberExists = $checkPrcNumber && $checkPrcNumber->num_rows > 0;
                $licenseTypeExists = $checkLicenseType && $checkLicenseType->num_rows > 0;
                $prcIdDocExists = $checkPrcIdDoc && $checkPrcIdDoc->num_rows > 0;

                // Build query dynamically based on which columns exist
                $query = "UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, specialization = ?, phone = ?, address = ?, profile_image = ?, department_id = ?";
                $params = [$username, $firstName, $lastName, $email, $specialization, $phone, $address, $profileImage, $department_id];
                $types = "ssssssssi";

                if ($prcNumberExists) {
                    $query .= ", prc_number = ?";
                    $params[] = $prcNumber;
                    $types .= "s";
                }
                if ($licenseTypeExists) {
                    $query .= ", license_type = ?";
                    $params[] = $licenseType;
                    $types .= "s";
                }
                if ($prcIdDocExists) {
                    $query .= ", prc_id_document = ?";
                    $params[] = $prcIdImage;
                    $types .= "s";
                }

                if (!empty($_POST['password'])) {
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $query .= ", password = ?";
                    $params[] = $hashedPassword;
                    $types .= "s";
                }

                $query .= " WHERE id = ?";
                $params[] = $doctorId;
                $types .= "i";

                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    $errors[] = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param($types, ...$params);

                    if ($stmt->execute()) {
                        $success = "Doctor information updated successfully.";
                        // Refresh doctor data
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->bind_param("i", $doctorId);
                        $stmt->execute();
                        $doctor = $stmt->get_result()->fetch_assoc();
                        
                        // Format phone for display
                        if (!empty($doctor['phone'])) {
                            $doctor['phone'] = phoneToInputFormat($doctor['phone']);
                        }
                        
                        // Update address parts
                        $addressParts = explode(", ", $doctor['address'] ?? '');
                        $barangayValue = trim($addressParts[0] ?? '');
                        $cityValue = trim($addressParts[1] ?? '');
                        $provinceValue = trim($addressParts[2] ?? '');
                        $regionValue = trim($addressParts[3] ?? '');
                    } else {
                        $errors[] = "Error updating doctor information: " . $stmt->error;
                    }
                }
            }
        }
    }
    
    // If there are errors, preserve POST values for form display
    if (!empty($errors)) {
        $doctor['first_name'] = isset($_POST['first_name']) ? sanitize($_POST['first_name']) : ($doctor['first_name'] ?? '');
        $doctor['last_name'] = isset($_POST['last_name']) ? sanitize($_POST['last_name']) : ($doctor['last_name'] ?? '');
        $doctor['email'] = isset($_POST['email']) ? sanitize($_POST['email']) : ($doctor['email'] ?? '');
        $doctor['username'] = isset($_POST['username']) ? sanitize($_POST['username']) : ($doctor['username'] ?? '');
        // Preserve phone in input format (POST value is already in input format)
        $doctor['phone'] = isset($_POST['phone']) ? sanitize($_POST['phone']) : (isset($doctor['phone']) ? phoneToInputFormat($doctor['phone']) : '');
        $doctor['address'] = isset($_POST['address']) ? sanitize($_POST['address']) : ($doctor['address'] ?? '');
        $doctor['specialization'] = isset($_POST['specialization']) ? sanitize($_POST['specialization']) : ($doctor['specialization'] ?? '');
        $doctor['prc_number'] = isset($_POST['prc_number']) ? sanitize($_POST['prc_number']) : ($doctor['prc_number'] ?? '');
        $doctor['license_type'] = isset($_POST['license_type']) ? sanitize($_POST['license_type']) : ($doctor['license_type'] ?? '');
        
        // Update address parts from POST or existing
        $addressParts = explode(", ", $doctor['address']);
        $barangayValue = trim($addressParts[0] ?? '');
        $cityValue = trim($addressParts[1] ?? '');
        $provinceValue = trim($addressParts[2] ?? '');
        $regionValue = trim($addressParts[3] ?? '');
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Edit Doctor</h5>
                <a href="doctors.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $e): ?>
                                <li><?php echo $e; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Personal Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Personal Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" required value="<?php echo htmlspecialchars($doctor['first_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" required value="<?php echo htmlspecialchars($doctor['last_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required value="<?php echo htmlspecialchars($doctor['email'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required pattern="^(\+63|0)\d{9,10}$" inputmode="tel"
                                placeholder="09123456789 or +639123456789" value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                            <small class="text-muted">Format: 09123456789 or +639123456789 (will be saved as +63...)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <div class="row">
                              <div class="col-md-3 mb-3">
                                <label class="form-label">Region</label>
                                <select id="region" class="form-select" onchange="loadProvinces()"></select>
                              </div>
                              <div class="col-md-3 mb-3">
                                <label class="form-label">Province</label>
                                <select id="province" class="form-select" onchange="loadCities()"></select>
                              </div>
                              <div class="col-md-3 mb-3">
                                <label class="form-label">City/Municipality</label>
                                <select id="city" class="form-select" onchange="loadBarangays()"></select>
                              </div>
                              <div class="col-md-3 mb-3">
                                <label class="form-label">Barangay</label>
                                <select id="barangay" class="form-select" onchange="combineAddress()"></select>
                              </div>
                            </div>
                            <input type="hidden" id="full_address" name="address" value="<?= htmlspecialchars($doctor['address'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Professional Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Professional Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Specialization <span class="text-danger">*</span></label>
                            <select class="form-control" name="specialization" required>
                                <option value="">Select Specialization</option>
                                <?php
                                $specializations = ["Cardiology", "ENT", "Internal Medicine", "OBG-YN", "ORTHO", "Pedia", "Psychiatry", "Surgery"];
                                foreach ($specializations as $spec):
                                ?>
                                    <option value="<?= $spec ?>" <?= (($doctor['specialization'] ?? '') === $spec) ? 'selected' : '' ?>><?= $spec ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">PRC Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="prc_number" required placeholder="e.g., 0123456" value="<?php echo htmlspecialchars($doctor['prc_number'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">License Type <span class="text-danger">*</span></label>
                            <select class="form-control" name="license_type" required>
                                <option value="">Select License Type</option>
                                <option value="MD" <?php echo (($doctor['license_type'] ?? '') === 'MD') ? 'selected' : ''; ?>>Doctor of Medicine (MD)</option>
                                <option value="DMD" <?php echo (($doctor['license_type'] ?? '') === 'DMD') ? 'selected' : ''; ?>>Doctor of Dental Medicine (DMD)</option>
                                <option value="DDS" <?php echo (($doctor['license_type'] ?? '') === 'DDS') ? 'selected' : ''; ?>>Doctor of Dental Surgery (DDS)</option>
                                <option value="DVM" <?php echo (($doctor['license_type'] ?? '') === 'DVM') ? 'selected' : ''; ?>>Doctor of Veterinary Medicine (DVM)</option>
                                <option value="RN" <?php echo (($doctor['license_type'] ?? '') === 'RN') ? 'selected' : ''; ?>>Registered Nurse (RN)</option>
                                <option value="RPh" <?php echo (($doctor['license_type'] ?? '') === 'RPh') ? 'selected' : ''; ?>>Registered Pharmacist (RPh)</option>
                                <option value="RPT" <?php echo (($doctor['license_type'] ?? '') === 'RPT') ? 'selected' : ''; ?>>Registered Physical Therapist (RPT)</option>
                                <option value="Other" <?php echo (($doctor['license_type'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Account Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Account Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required value="<?php echo htmlspecialchars($doctor['username'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="password" minlength="6">
                                <small class="text-muted">Leave blank to keep current password. Minimum 6 characters if changing.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Document Uploads Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Document Uploads</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Profile Picture</label>
                                <?php if (!empty($doctor['profile_image'] ?? '')): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars($doctor['profile_image'] ?? ''); ?>" alt="Profile" class="rounded" style="max-height: 100px;">
                                        <div class="small text-muted">Current profile picture</div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="profile_image" accept="image/*">
                                <small class="text-muted">Max file size: 5MB. Allowed formats: JPG, PNG, GIF</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PRC ID / Government ID</label>
                                <?php if (!empty($doctor['prc_id_document'] ?? '')): ?>
                                    <div class="mb-2">
                                        <?php 
                                        $prcDoc = $doctor['prc_id_document'] ?? '';
                                        $fileExt = strtolower(pathinfo($prcDoc, PATHINFO_EXTENSION));
                                        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?php echo htmlspecialchars($prcDoc); ?>" alt="PRC ID" class="rounded" style="max-height: 100px;">
                                        <?php else: ?>
                                            <div class="alert alert-info p-2">
                                                <i class="fas fa-file-pdf"></i> 
                                                <a href="<?php echo htmlspecialchars($prcDoc); ?>" target="_blank">View Current PRC Document</a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="small text-muted">Current PRC ID document</div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="prc_id" accept="image/*,.pdf">
                                <small class="text-muted">Max file size: 10MB. Allowed formats: JPG, PNG, GIF, PDF</small>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="doctors.php" class="btn btn-danger">Cancel</a>
                        <button type="submit" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
                
                <script>
                // Additional client-side validation
                document.querySelector('form').addEventListener('submit', function(e) {
                    const requiredFields = {
                        'first_name': 'First name',
                        'last_name': 'Last name',
                        'email': 'Email',
                        'phone': 'Phone',
                        'username': 'Username',
                        'specialization': 'Specialization',
                        'prc_number': 'PRC number',
                        'license_type': 'License type'
                    };
                    
                    let hasErrors = false;
                    let errorMessages = [];
                    
                    for (const [fieldName, fieldLabel] of Object.entries(requiredFields)) {
                        const field = document.querySelector(`[name="${fieldName}"]`);
                        if (field) {
                            const value = field.value.trim();
                            if (!value) {
                                hasErrors = true;
                                errorMessages.push(`${fieldLabel} is required and cannot be empty.`);
                                field.classList.add('is-invalid');
                            } else {
                                field.classList.remove('is-invalid');
                            }
                        }
                    }
                    
                    // Validate address if any part is selected
                    const region = document.getElementById('region').value;
                    const province = document.getElementById('province').value;
                    const city = document.getElementById('city').value;
                    const barangay = document.getElementById('barangay').value;
                    
                    // If any address part is selected, all should be selected
                    if (region || province || city || barangay) {
                        if (!region || !province || !city || !barangay) {
                            hasErrors = true;
                            errorMessages.push('Please complete all address fields (Region, Province, City, and Barangay).');
                        }
                    }
                    
                    if (hasErrors) {
                        e.preventDefault();
                        alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
                        return false;
                    }
                });
                </script>
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

        // Pre-select values
        const selectedRegion = <?= json_encode($regionValue) ?>;
        const selectedProvince = <?= json_encode($provinceValue) ?>;
        const selectedCity = <?= json_encode($cityValue) ?>;
        const selectedBarangay = <?= json_encode($barangayValue) ?>;

        setTimeout(() => {
            document.getElementById('region').value = selectedRegion;
            loadProvinces();
            setTimeout(() => {
                document.getElementById('province').value = selectedProvince;
                loadCities();
                setTimeout(() => {
                    document.getElementById('city').value = selectedCity;
                    loadBarangays();
                    setTimeout(() => {
                        document.getElementById('barangay').value = selectedBarangay;
                        combineAddress();
                    }, 100);
                }, 100);
            }, 100);
        }, 100);
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
    document.getElementById("full_address").value = `${b}, ${c}, ${p}, ${r}`;
}
</script>

<?php include 'includes/footer.php'; ?>