<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';

// Require admin login for security
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Get current admin user info
$adminId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, address, role, specialization FROM users WHERE id = ? AND role = 'Admin'");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Admin user not found!");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $rawPhone = sanitize($_POST['phone'] ?? '');
    $phone = !empty($rawPhone) ? normalizePhoneNumber($rawPhone) : '';
    $address = sanitize($_POST['address'] ?? '');
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($rawPhone) || empty($address)) {
        $error = "All fields are required.";
    } elseif (!validatePhoneNumber($rawPhone)) {
        $error = "Invalid phone number format. Please use 09123456789 or +639123456789.";
    } else {
        // Check for duplicate email
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $adminId);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            $error = "Email address already exists. Please use a different email.";
        } else {
            // Update admin user
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $address, $adminId);
            
            if ($stmt->execute()) {
                $success = "Admin user information updated successfully!";
                // Refresh admin data
                $stmt_refresh = $conn->prepare("SELECT id, first_name, last_name, email, phone, address, role, specialization FROM users WHERE id = ?");
                $stmt_refresh->bind_param("i", $adminId);
                $stmt_refresh->execute();
                $admin = $stmt_refresh->get_result()->fetch_assoc();
                $stmt_refresh->close();
            } else {
                $error = "Error updating admin: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Parse address for form
$addressParts = explode(", ", $admin['address'] ?? '');
$barangayValue = trim($addressParts[0] ?? '');
$cityValue = trim($addressParts[1] ?? '');
$provinceValue = trim($addressParts[2] ?? '');
$regionValue = trim($addressParts[3] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Admin User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-user-shield me-2"></i>Fix Admin User Information</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Current Status:</strong> 
                            <?php 
                            $missingFields = [];
                            if (empty($admin['phone'])) $missingFields[] = 'Phone';
                            if (empty($admin['address'])) $missingFields[] = 'Address';
                            
                            if (empty($missingFields)) {
                                echo '<span class="text-success">All required fields are filled.</span>';
                            } else {
                                echo '<span class="text-warning">Missing fields: ' . implode(', ', $missingFields) . '</span>';
                            }
                            ?>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="update_admin" value="1">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="phone" required 
                                       pattern="^(\+63|0)\d{9,10}$" inputmode="tel"
                                       placeholder="09123456789 or +639123456789" 
                                       value="<?php echo htmlspecialchars(phoneToInputFormat($admin['phone'] ?? '')); ?>">
                                <small class="text-muted">Format: 09123456789 or +639123456789</small>
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
                                <input type="hidden" id="full_address" name="address" 
                                       value="<?php echo htmlspecialchars($admin['address'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="profile.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Profile
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Admin Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Current Admin Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">Field</th>
                                <th>Value</th>
                            </tr>
                            <tr>
                                <td><strong>ID</strong></td>
                                <td><?php echo htmlspecialchars($admin['id']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Name</strong></td>
                                <td><?php echo htmlspecialchars(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email</strong></td>
                                <td><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Phone</strong></td>
                                <td><?php 
                                    $phone = !empty($admin['phone']) ? formatPhoneNumber($admin['phone']) : '';
                                    echo htmlspecialchars($phone ?: 'N/A (MISSING)'); 
                                ?></td>
                            </tr>
                            <tr>
                                <td><strong>Address</strong></td>
                                <td><?php echo htmlspecialchars(!empty($admin['address']) ? $admin['address'] : 'N/A (MISSING)'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Role</strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($admin['role'] ?? 'Admin'); ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Address management functions (same as profile.php)
        let PH_LOCATIONS = {};
        const selectedRegion = <?php echo json_encode($regionValue); ?>;
        const selectedProvince = <?php echo json_encode($provinceValue); ?>;
        const selectedCity = <?php echo json_encode($cityValue); ?>;
        const selectedBarangay = <?php echo json_encode($barangayValue); ?>;

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

                // Pre-select address values
                if (selectedRegion) {
                    setTimeout(() => {
                        const regionSelect = document.getElementById('region');
                        if (regionSelect && regionSelect.querySelector(`option[value="${selectedRegion}"]`)) {
                            regionSelect.value = selectedRegion;
                            loadProvinces();
                            setTimeout(() => {
                                if (selectedProvince) {
                                    const provinceSelect = document.getElementById('province');
                                    if (provinceSelect && provinceSelect.querySelector(`option[value="${selectedProvince}"]`)) {
                                        provinceSelect.value = selectedProvince;
                                        loadCities();
                                        setTimeout(() => {
                                            if (selectedCity) {
                                                const citySelect = document.getElementById('city');
                                                if (citySelect && citySelect.querySelector(`option[value="${selectedCity}"]`)) {
                                                    citySelect.value = selectedCity;
                                                    loadBarangays();
                                                    setTimeout(() => {
                                                        if (selectedBarangay) {
                                                            const barangaySelect = document.getElementById('barangay');
                                                            if (barangaySelect && barangaySelect.querySelector(`option[value="${selectedBarangay}"]`)) {
                                                                barangaySelect.value = selectedBarangay;
                                                            }
                                                            combineAddress();
                                                        }
                                                    }, 300);
                                                }
                                            }
                                        }, 300);
                                    }
                                }
                            }, 300);
                        }
                    }, 300);
                }
            })
            .catch(error => console.error('Error loading location data:', error));

        function populateRegions() {
            const regionSelect = document.getElementById('region');
            if (!regionSelect) return;
            regionSelect.innerHTML = '<option value="">Select Region</option>';
            for (let region in PH_LOCATIONS) {
                regionSelect.innerHTML += `<option value="${region}">${region}</option>`;
            }
        }

        function loadProvinces() {
            const region = document.getElementById('region').value;
            const provinceSelect = document.getElementById('province');
            if (!provinceSelect) return;
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            if (PH_LOCATIONS[region]) {
                for (let province in PH_LOCATIONS[region]) {
                    provinceSelect.innerHTML += `<option value="${province}">${province}</option>`;
                }
            }
            const citySelect = document.getElementById('city');
            const barangaySelect = document.getElementById('barangay');
            if (citySelect) citySelect.innerHTML = '<option value="">Select City</option>';
            if (barangaySelect) barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            combineAddress();
        }

        function loadCities() {
            const region = document.getElementById('region').value;
            const province = document.getElementById('province').value;
            const citySelect = document.getElementById('city');
            if (!citySelect) return;
            citySelect.innerHTML = '<option value="">Select City</option>';
            if (PH_LOCATIONS[region] && PH_LOCATIONS[region][province]) {
                for (let city in PH_LOCATIONS[region][province]) {
                    citySelect.innerHTML += `<option value="${city}">${city}</option>`;
                }
            }
            const barangaySelect = document.getElementById('barangay');
            if (barangaySelect) barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            combineAddress();
        }

        function loadBarangays() {
            const region = document.getElementById('region').value;
            const province = document.getElementById('province').value;
            const city = document.getElementById('city').value;
            const barangaySelect = document.getElementById('barangay');
            if (!barangaySelect) return;
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            if (PH_LOCATIONS[region] && PH_LOCATIONS[region][province] && PH_LOCATIONS[region][province][city]) {
                PH_LOCATIONS[region][province][city].forEach(brgy => {
                    barangaySelect.innerHTML += `<option value="${brgy}">${brgy}</option>`;
                });
            }
            combineAddress();
        }

        function combineAddress() {
            const r = document.getElementById("region")?.value || '';
            const p = document.getElementById("province")?.value || '';
            const c = document.getElementById("city")?.value || '';
            const b = document.getElementById("barangay")?.value || '';
            const fullAddressInput = document.getElementById("full_address");
            if (fullAddressInput && r && p && c && b) {
                fullAddressInput.value = `${b}, ${c}, ${p}, ${r}`;
            } else if (fullAddressInput && (!r || !p || !c || !b)) {
                fullAddressInput.value = '';
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    combineAddress();
                    const region = document.getElementById('region')?.value || '';
                    const province = document.getElementById('province')?.value || '';
                    const city = document.getElementById('city')?.value || '';
                    const barangay = document.getElementById('barangay')?.value || '';
                    
                    if (!region || !province || !city || !barangay) {
                        e.preventDefault();
                        alert('Please complete all address fields (Region, Province, City, and Barangay).');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>



