<?php
// edit_patient.php

define('MHAVIS_EXEC', true);
$page_title = "Edit Patient";
$active_page = "patients";
require_once __DIR__ . '/config/init.php';
requireLogin();

$conn = getDBConnection();

// Admin can edit patient records

$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: patients.php');
    exit();
}

$patient = $result->fetch_assoc();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name']);
    $middleName = sanitize($_POST['middle_name'] ?? '');
    $lastName = sanitize($_POST['last_name']);
    $dateOfBirth = sanitize($_POST['date_of_birth']);
    $sex = sanitize($_POST['sex']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $emergencyContactName = sanitize($_POST['emergency_contact_name']);
    $emergencyContactRelationship = sanitize($_POST['emergency_contact_relationship']);
    $bloodType = sanitize($_POST['blood_type']);

    // Helper function to validate name fields (no numbers allowed)
    function isValidName($name) {
        if (empty($name)) return true; // Empty is allowed for optional fields like middle_name
        // Allow letters, spaces, hyphens, apostrophes, and periods (common in names)
        // Reject if contains any digits
        return !preg_match('/\d/', $name);
    }

    // Validate name fields
    if (!isValidName($firstName)) {
        $errors[] = "First name cannot contain numbers. Please enter a valid name.";
    }
    if (!empty($middleName) && !isValidName($middleName)) {
        $errors[] = "Middle name cannot contain numbers. Please enter a valid name.";
    }
    if (!isValidName($lastName)) {
        $errors[] = "Last name cannot contain numbers. Please enter a valid name.";
    }

    $phone = '';
    if (empty($_POST['phone'])) {
        $errors[] = "Phone number is required.";
    } else {
        $phone = normalizePhoneNumber($_POST['phone']);
        if (!validatePhoneNumber($_POST['phone'])) {
            $errors[] = "Invalid phone number format. Please use 09123456789 or +639123456789.";
        }
    }
    
    if (empty($_POST['email'])) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (strpos($email, '@') === false) {
        $errors[] = "Email must contain @ symbol.";
    } elseif (strpos($email, '.') === false || strpos($email, '@') >= strpos($email, '.')) {
        $errors[] = "Email must contain a valid domain (e.g., .com, .net, .org).";
    }

    $emergencyContactPhone = '';
    if (!empty($_POST['emergency_contact_phone'])) {
        $emergencyContactPhone = normalizePhoneNumber($_POST['emergency_contact_phone']);
        if (!validatePhoneNumber($_POST['emergency_contact_phone'])) {
            $errors[] = "Invalid emergency contact number. Please use 09123456789 or +639123456789.";
        }
    }

    if (!$firstName || !$lastName || !$dateOfBirth || !$sex || empty($_POST['phone']) || empty($_POST['email'])) {
        $errors[] = "Please fill in all required fields.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE patients SET 
            first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, sex = ?, phone = ?, email = ?, address = ?, 
            emergency_contact_name = ?, relationship = ?, emergency_contact_phone = ?, blood_type = ?
            WHERE id = ?");
        $stmt->bind_param("ssssssssssssi",
            $firstName, $middleName, $lastName, $dateOfBirth, $sex, $phone, $email, $address,
            $emergencyContactName, $emergencyContactRelationship, $emergencyContactPhone, $bloodType, $patientId
        );

        if ($stmt->execute()) {
            $success = "Patient information updated successfully.";
            $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $patient = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "Error updating patient information.";
        }
    }
    
    // If there are errors, preserve POST values for form display
    if (!empty($errors)) {
        $patient['first_name'] = $_POST['first_name'] ?? $patient['first_name'];
        $patient['middle_name'] = $_POST['middle_name'] ?? $patient['middle_name'];
        $patient['last_name'] = $_POST['last_name'] ?? $patient['last_name'];
        $patient['date_of_birth'] = $_POST['date_of_birth'] ?? $patient['date_of_birth'];
        $patient['sex'] = $_POST['sex'] ?? $patient['sex'];
        $patient['email'] = $_POST['email'] ?? $patient['email'];
        // Preserve phone in input format (POST value is already in input format)
        $patient['phone'] = isset($_POST['phone']) ? sanitize($_POST['phone']) : (isset($patient['phone']) ? phoneToInputFormat($patient['phone']) : '');
        $patient['address'] = $_POST['address'] ?? $patient['address'];
        $patient['emergency_contact_name'] = $_POST['emergency_contact_name'] ?? $patient['emergency_contact_name'];
        $patient['relationship'] = $_POST['emergency_contact_relationship'] ?? $patient['relationship'];
        // Preserve emergency contact phone in input format
        $patient['emergency_contact_phone'] = isset($_POST['emergency_contact_phone']) ? sanitize($_POST['emergency_contact_phone']) : (isset($patient['emergency_contact_phone']) ? phoneToInputFormat($patient['emergency_contact_phone']) : '');
        $patient['blood_type'] = $_POST['blood_type'] ?? $patient['blood_type'];
        
        // Update address parts from POST or existing
        $addressParts = explode(", ", $patient['address']);
        $barangayValue = $addressParts[0] ?? '';
        $cityValue = $addressParts[1] ?? '';
        $provinceValue = $addressParts[2] ?? '';
        $regionValue = $addressParts[3] ?? '';
    }
}

include 'includes/header.php';

$addressParts = explode(", ", $patient['address'] ?? '');
$barangayValue = $addressParts[0] ?? '';
$cityValue = $addressParts[1] ?? '';
$provinceValue = $addressParts[2] ?? '';
$regionValue = $addressParts[3] ?? '';
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Edit Patient</h5>
                <div>
                    <a href="patients.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back 
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div><?php endif; ?>

                <form method="POST">
                    <!-- Personal Information -->
                    <h6 class="text-muted mb-3 border-bottom pb-2"><i class="fas fa-user me-2"></i>Personal Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="first_name" value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>" pattern="[A-Za-z\s\-'\.]+" title="First name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed.">
                            <div class="invalid-feedback">First name cannot contain numbers.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" id="middle_name" value="<?= htmlspecialchars($patient['middle_name'] ?? '') ?>" pattern="[A-Za-z\s\-'\.]*" title="Middle name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed.">
                            <div class="invalid-feedback">Middle name cannot contain numbers.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="last_name" value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>" pattern="[A-Za-z\s\-'\.]+" title="Last name cannot contain numbers. Only letters, spaces, hyphens, apostrophes, and periods are allowed.">
                            <div class="invalid-feedback">Last name cannot contain numbers.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($patient['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sex *</label>
                            <select class="form-select" name="sex">
                                <option value="">Select Sex</option>
                                <option value="Male" <?= ($patient['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($patient['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($patient['sex'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="text-muted mb-3 mt-4 border-bottom pb-2"><i class="fas fa-phone me-2"></i>Contact Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required pattern="^(\+63|0)\d{9,10}$" inputmode="tel" placeholder="09123456789 or +639123456789" value="<?= isset($patient['phone']) && !empty($patient['phone']) ? htmlspecialchars(phoneToInputFormat($patient['phone'])) : '' ?>">
                            <small class="text-muted">Format: 09123456789 or +639123456789 (stored as +63...)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="email" required value="<?= htmlspecialchars($patient['email'] ?? '') ?>" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" title="Email must contain @ and a valid domain (e.g., .com, .net, .org)">
                            <div class="invalid-feedback">Email must contain @ and a valid domain (e.g., .com, .net, .org)</div>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <h6 class="text-muted mb-3 mt-4 border-bottom pb-2"><i class="fas fa-map-marker-alt me-2"></i>Address</h6>
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
                    <input type="hidden" name="address" id="full_address" value="<?= htmlspecialchars($patient['address'] ?? '') ?>">

                    <!-- Emergency Contact -->
                    <h6 class="text-muted mb-3 mt-4 border-bottom pb-2"><i class="fas fa-user-injured me-2"></i>Emergency Contact</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" name="emergency_contact_name" value="<?= htmlspecialchars($patient['emergency_contact_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Relationship to Emergency Contact</label>
                            <select class="form-select" name="emergency_contact_relationship">
                                <option value="">Select Relationship</option>
                                <option value="Spouse" <?= ($patient['relationship'] ?? '') === 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                                <option value="Parent" <?= ($patient['relationship'] ?? '') === 'Parent' ? 'selected' : '' ?>>Parent</option>
                                <option value="Child" <?= ($patient['relationship'] ?? '') === 'Child' ? 'selected' : '' ?>>Child</option>
                                <option value="Sibling" <?= ($patient['relationship'] ?? '') === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                                <option value="Friend" <?= ($patient['relationship'] ?? '') === 'Friend' ? 'selected' : '' ?>>Friend</option>
                                <option value="Guardian" <?= ($patient['relationship'] ?? '') === 'Guardian' ? 'selected' : '' ?>>Guardian</option>
                                <option value="Other" <?= ($patient['relationship'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="tel" class="form-control" name="emergency_contact_phone" pattern="^(\+63|0)\d{9,10}$" inputmode="tel" placeholder="09123456789 or +639123456789" value="<?= isset($patient['emergency_contact_phone']) && !empty($patient['emergency_contact_phone']) ? htmlspecialchars(phoneToInputFormat($patient['emergency_contact_phone'])) : '' ?>">
                            <small class="text-muted">Format: 09123456789 or +639123456789 (stored as +63...)</small>
                        </div>
                    </div>

                    <!-- Medical Information -->
                    <h6 class="text-muted mb-3 mt-4 border-bottom pb-2"><i class="fas fa-heartbeat me-2"></i>Medical Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select" name="blood_type">
                                <option value="">Select Blood Type</option>
                                <?php foreach (["A+","A-","B+","B-","O+","O-","AB+","AB-"] as $type): ?>
                                    <option value="<?= $type ?>" <?= ($patient['blood_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="patients.php" class="btn btn-danger">Cancel</a>
                        <button type="submit" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let PH_LOCATIONS = {};
fetch('assets/data/ph_lgu_data.json')
.then(res => res.json())
.then(data => {
  for (const rCode in data) {
    const rName = data[rCode].region_name;
    PH_LOCATIONS[rName] = {};
    for (const p in data[rCode].province_list) {
      PH_LOCATIONS[rName][p] = {};
      for (const c in data[rCode].province_list[p].municipality_list) {
        PH_LOCATIONS[rName][p][c] = data[rCode].province_list[p].municipality_list[c].barangay_list;
      }
    }
  }
  populateRegions();
  setTimeout(() => {
    document.getElementById('region').value = <?= json_encode($regionValue) ?>;
    loadProvinces();
    setTimeout(() => {
      document.getElementById('province').value = <?= json_encode($provinceValue) ?>;
      loadCities();
      setTimeout(() => {
        document.getElementById('city').value = <?= json_encode($cityValue) ?>;
        loadBarangays();
        setTimeout(() => {
          document.getElementById('barangay').value = <?= json_encode($barangayValue) ?>;
          combineAddress();
        }, 100);
      }, 100);
    }, 100);
  }, 100);
});

function populateRegions() {
  const regionSelect = document.getElementById('region');
  regionSelect.innerHTML = '<option value="">Select Region</option>';
  for (let r in PH_LOCATIONS) {
    regionSelect.innerHTML += `<option value="${r}">${r}</option>`;
  }
}
function loadProvinces() {
  const r = document.getElementById('region').value;
  const pSelect = document.getElementById('province');
  pSelect.innerHTML = '<option value="">Select Province</option>';
  for (let p in PH_LOCATIONS[r]) {
    pSelect.innerHTML += `<option value="${p}">${p}</option>`;
  }
  document.getElementById('city').innerHTML = '<option value="">Select City</option>';
  document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
  combineAddress();
}
function loadCities() {
  const r = document.getElementById('region').value;
  const p = document.getElementById('province').value;
  const cSelect = document.getElementById('city');
  cSelect.innerHTML = '<option value="">Select City</option>';
  for (let c in PH_LOCATIONS[r][p]) {
    cSelect.innerHTML += `<option value="${c}">${c}</option>`;
  }
  document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
  combineAddress();
}
function loadBarangays() {
  const r = document.getElementById('region').value;
  const p = document.getElementById('province').value;
  const c = document.getElementById('city').value;
  const bSelect = document.getElementById('barangay');
  bSelect.innerHTML = '<option value="">Select Barangay</option>';
  PH_LOCATIONS[r][p][c].forEach(b => {
    bSelect.innerHTML += `<option value="${b}">${b}</option>`;
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

// Function to validate email field
function validateEmailField(input) {
    const value = input.value;
    const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    
    if (value && !emailPattern.test(value)) {
        input.setCustomValidity('Email must contain @ and a valid domain (e.g., .com, .net, .org)');
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (value && (value.indexOf('@') === -1 || value.indexOf('.') === -1 || value.indexOf('@') >= value.lastIndexOf('.'))) {
        input.setCustomValidity('Email must contain @ and a valid domain (e.g., .com, .net, .org)');
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

// Initialize validation on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to name fields to prevent numbers
    const nameFields = [
        { id: 'first_name', label: 'First name' },
        { id: 'middle_name', label: 'Middle name' },
        { id: 'last_name', label: 'Last name' }
    ];
    
    nameFields.forEach(field => {
        const input = document.getElementById(field.id);
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
                validateNameField(this, field.label);
            });
            
            // Validate on blur
            input.addEventListener('blur', function() {
                validateNameField(this, field.label);
            });
        }
    });
    
    // Add event listener to email field
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            validateEmailField(this);
        });
        
        emailInput.addEventListener('blur', function() {
            validateEmailField(this);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
