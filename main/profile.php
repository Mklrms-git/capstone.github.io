<?php
define('MHAVIS_EXEC', true);
$page_title = "My Profile";
$active_page = "profile";
require_once __DIR__ . '/config/init.php';
requireLogin();

// Get current user data - always fetch fresh from database
// getCurrentUser() already handles session fallback for null/empty values
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit();
}

// Ensure status is set to Active if empty
if (empty($user['status']) || trim($user['status']) === '') {
    $user['status'] = 'Active';
}

// Format last login
$last_login_display = 'Never';
if (!empty($user['last_login'])) {
    $last_login_timestamp = strtotime($user['last_login']);
    $time_diff = time() - $last_login_timestamp;
    
    if ($time_diff < 60) {
        $last_login_display = 'Just now';
    } elseif ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        $last_login_display = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        $last_login_display = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_diff < 604800) {
        $days = floor($time_diff / 86400);
        $last_login_display = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        $last_login_display = date('M j, Y g:i A', $last_login_timestamp);
    }
}

// Get profile image path
$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'img/defaultDP.jpg';
if (!empty($user['profile_image']) && !file_exists($user['profile_image'])) {
    $profile_image = 'img/defaultDP.jpg';
}

include 'includes/header.php';
?>

<style>
    .profile-container {
        padding: 0;
    }

    .profile-container .dashboard-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .profile-container .card-header {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        padding: 1.25rem 1.5rem;
    }

    .profile-container .card-header h5 {
        margin: 0;
        font-weight: 600;
        color: #1e293b;
        font-size: 1.25rem;
    }

    .profile-container .card-body {
        padding: 1.5rem;
    }

    .profile-container .info-card {
        background: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }

    .profile-container .info-card p {
        margin-bottom: 0.75rem;
    }

    .profile-container .info-card p:last-child {
        margin-bottom: 0;
    }

    .profile-container h6 {
        color: #334155;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .profile-container .profile-image-upload-container {
        text-align: center;
    }
    
    .profile-container .profile-image-preview {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .profile-container .profile-preview-img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .profile-container .profile-preview-img:hover {
        border-color: #0D92F4;
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.2);
    }
    
    .profile-container .profile-image-upload-container .input-group {
        max-width: 400px;
        margin: 0 auto;
    }
    
    .profile-container #profile-message,
    .profile-container #image-message,
    .profile-container #password-message {
        margin-bottom: 1.5rem;
    }

    .alert-message {
        display: none;
    }
</style>

<div class="container-fluid profile-container">
    <div class="dashboard-card">
        <div class="card-header">
            <h5><i class="fas fa-user me-2"></i>Profile Information</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Left Side: Account Information -->
                <div class="col-12 col-lg-6">
                    <div class="info-card">
                        <h6 class="mb-3"><i class="fas fa-user-circle me-2 text-primary"></i>Account Information</h6>
                        <p class="mb-2"><strong>Full Name:</strong> 
                            <span id="display-full-name">
                                <?php 
                                $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                                echo !empty($full_name) ? htmlspecialchars($full_name) : 'Not set'; 
                                ?>
                            </span>
                        </p>
                        <p class="mb-2"><strong>Email:</strong> 
                            <span id="display-email">
                                <?php echo !empty($user['email']) && trim($user['email']) !== '' ? htmlspecialchars($user['email']) : 'Not set'; ?>
                            </span>
                        </p>
                        <p class="mb-2"><strong>Phone:</strong> 
                            <span id="display-phone">
                                <?php echo !empty($user['phone']) && trim($user['phone']) !== '' ? htmlspecialchars(formatPhoneNumber($user['phone'])) : 'Not set'; ?>
                            </span>
                        </p>
                        <p class="mb-2"><strong>Username:</strong> 
                            <span id="display-username">
                                <?php 
                                // Get username from user array (which prioritizes database but falls back to session)
                                $username = '';
                                if (!empty($user['username']) && trim($user['username']) !== '') {
                                    $username = trim($user['username']);
                                } elseif (!empty($_SESSION['username']) && trim($_SESSION['username']) !== '') {
                                    // Final fallback to session if user array doesn't have it
                                    $username = trim($_SESSION['username']);
                                }
                                echo !empty($username) ? htmlspecialchars($username) : 'Not set'; 
                                ?>
                            </span>
                        </p>
                        <hr class="my-3">
                        <h6 class="mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Account Status</h6>
                        <p class="mb-2"><strong>Status:</strong> 
                            <?php 
                            // Ensure status defaults to 'Active' if empty or null
                            $status = !empty($user['status']) && trim($user['status']) !== '' ? trim($user['status']) : 'Active';
                            ?>
                            <span class="badge bg-<?php echo $status === 'Active' ? 'success' : 'warning'; ?> ms-2" id="display-status">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </p>
                        <?php 
                        $user_role = $user['role'] ?? null;
                        if ($user_role !== 'Doctor' && $user_role !== 'Admin' && $user_role !== null): ?>
                            <p class="mb-0"><strong>Role:</strong> <?php echo htmlspecialchars($user_role); ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><strong>Last Login:</strong> <?php echo htmlspecialchars($last_login_display); ?></p>
                        
                        <?php if ($user_role === 'Doctor'): ?>
                            <hr class="my-3">
                            <h6 class="mb-3"><i class="fas fa-stethoscope me-2 text-primary"></i>Professional Information</h6>
                            <p class="mb-2"><strong>Specialization:</strong> 
                                <?php echo !empty($user['specialization']) && trim($user['specialization']) !== '' ? htmlspecialchars($user['specialization']) : 'Not set'; ?>
                            </p>
                            <p class="mb-2"><strong>PRC Number:</strong> 
                                <?php echo !empty($user['prc_number']) && trim($user['prc_number']) !== '' ? htmlspecialchars($user['prc_number']) : 'Not set'; ?>
                            </p>
                            <?php if (!empty($user['license_number']) && trim($user['license_number']) !== ''): ?>
                                <p class="mb-2"><strong>License Number:</strong> 
                                    <?php echo htmlspecialchars($user['license_number']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($user['license_type']) && trim($user['license_type']) !== ''): ?>
                                <p class="mb-0"><strong>License Type:</strong> 
                                    <?php echo htmlspecialchars($user['license_type']); ?>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Side: Editable Fields (Account Settings, Profile Image & Password) -->
                <div class="col-12 col-lg-6">
                    <div class="info-card">
                        <h6 class="mb-3"><i class="fas fa-edit me-2 text-primary"></i>Account Settings</h6>
                        
                       
                       <!-- Profile Image Upload -->
                       <div class="mb-4">
                            <label class="form-label"><i class="fas fa-image me-2"></i>Profile Picture</label>
                            <div class="alert alert-message" id="image-message" role="alert" style="display: none;"></div>
                            <div class="profile-image-upload-container">
                                <div class="profile-image-preview">
                                    <img id="profile-image-preview" src="<?php echo htmlspecialchars($profile_image); ?>" 
                                         alt="Profile Picture" class="profile-preview-img"
                                         onerror="this.src='img/defaultDP.jpg'">
                                </div>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="profile_image" 
                                           accept="image/jpeg,image/png,image/gif,image/jpg" name="profile_image">
                                    <button class="btn btn-outline-primary" type="button" id="upload-image-btn">
                                        <i class="fas fa-upload me-2"></i>Upload
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Accepted formats: JPG, PNG, GIF. Max size: 5MB
                                </small>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                       
                        <!-- Profile Information (Editable) -->
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-address-card me-2"></i>Profile Information</label>
                            <div class="alert alert-message" id="profile-message" role="alert" style="display: none;"></div>
                            <div class="mb-3">
                                <label class="form-label small">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" 
                                       placeholder="Enter your first name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" 
                                       placeholder="Enter your last name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                       placeholder="Enter your email address" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">+63</span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo !empty($user['phone']) ? htmlspecialchars(preg_replace('/^\+63|^0/', '', phoneToInputFormat($user['phone']))) : ''; ?>" 
                                           pattern="^\d{10}$" inputmode="numeric" maxlength="10"
                                           placeholder="9123456789" required>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Enter 10 digits only (e.g., 9123456789). Country code +63 is fixed.
                                </small>
                            </div>
                            <button class="btn btn-success" type="button" id="update-profile-btn">
                                <i class="fas fa-save me-2"></i>Save Profile Information
                            </button>
                        </div>
                        
                        <hr class="my-4">

                        
                        <!-- Password Change -->
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-lock me-2"></i>Change Password</label>
                            <div class="alert alert-message" id="password-message" role="alert" style="display: none;"></div>
                            <div class="mb-2">
                                <input type="password" class="form-control" id="current_password" 
                                       placeholder="Current Password" name="current_password">
                            </div>
                            <div class="mb-2">
                                <input type="password" class="form-control" id="new_password" 
                                       placeholder="New Password" name="new_password" minlength="8">
                            </div>
                            <div class="mb-2">
                                <input type="password" class="form-control" id="confirm_password" 
                                       placeholder="Confirm New Password" name="confirm_password" minlength="8">
                            </div>
                            <button class="btn btn-primary" type="button" id="update-password-btn">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Password must be at least 8 characters long
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile Information Update
    const updateProfileBtn = document.getElementById('update-profile-btn');
    
    if (updateProfileBtn) {
        updateProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phoneValue = document.getElementById('phone').value.trim();
            
            // Validation
            if (!firstName || !lastName || !email || !phoneValue) {
                showProfileMessage('Please fill in all required fields.', 'warning');
                return;
            }
            
            // Normalize phone number (add +63 prefix if needed)
            let phone = phoneValue;
            if (!phone.startsWith('+63')) {
                if (phone.startsWith('0')) {
                    phone = '+63' + phone.substring(1);
                } else {
                    phone = '+63' + phone;
                }
            }
            
            const formData = new FormData();
            formData.append('action', 'update_account_info');
            formData.append('first_name', firstName);
            formData.append('last_name', lastName);
            formData.append('email', email);
            formData.append('phone', phone);
            
            updateProfileBtn.disabled = true;
            updateProfileBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            
            fetch('update_staff_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                updateProfileBtn.disabled = false;
                updateProfileBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Profile Information';
                
                if (data.success) {
                    showProfileMessage(data.message, 'success');
                    
                    // Use database values from response if available, otherwise use form values
                    const dbValues = data.database_values || {};
                    const displayFirstName = dbValues.first_name || firstName;
                    const displayLastName = dbValues.last_name || lastName;
                    const displayEmail = dbValues.email || email;
                    const displayPhone = dbValues.phone || phone;
                    
                    // Update displayed values on the left side
                    document.getElementById('display-full-name').textContent = displayFirstName + ' ' + displayLastName;
                    document.getElementById('display-email').textContent = displayEmail || 'Not set';
                    document.getElementById('display-phone').textContent = formatPhoneNumber(displayPhone) || 'Not set';
                    
                    // Update form input values to match database values
                    document.getElementById('first_name').value = displayFirstName;
                    document.getElementById('last_name').value = displayLastName;
                    document.getElementById('email').value = displayEmail;
                    // Format phone for input (remove +63 and leading 0 if present)
                    const phoneForInput = displayPhone.replace(/^\+63/, '').replace(/^0/, '');
                    document.getElementById('phone').value = phoneForInput;
                    
                    // Update header name and profile info if it exists
                    const headerName = document.querySelector('.user-profile span');
                    if (headerName) {
                        headerName.textContent = displayFirstName + ' ' + displayLastName;
                    }
                } else {
                    showProfileMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                updateProfileBtn.disabled = false;
                updateProfileBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Profile Information';
                showProfileMessage('An error occurred. Please try again.', 'danger');
            });
        });
    }
    
    function showProfileMessage(message, type) {
        const messageDiv = document.getElementById('profile-message');
        messageDiv.className = 'alert alert-message alert-' + type;
        messageDiv.style.display = 'block';
        messageDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>' + message;
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
    
    function formatPhoneNumber(phone) {
        if (!phone) return '';
        // Remove +63 and format
        let cleaned = phone.replace(/^\+63/, '').replace(/^0/, '');
        if (cleaned.length === 10) {
            return cleaned.substring(0, 3) + '-' + cleaned.substring(3, 6) + '-' + cleaned.substring(6);
        }
        return phone;
    }
    
    // Profile Image Upload
    const profileImageInput = document.getElementById('profile_image');
    const imagePreview = document.getElementById('profile-image-preview');
    const uploadImageBtn = document.getElementById('upload-image-btn');
    
    // Preview image when file is selected
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    showImageMessage('Invalid file type. Only JPG, PNG and GIF are allowed.', 'danger');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showImageMessage('File size too large. Maximum size is 5MB.', 'danger');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    if (uploadImageBtn) {
        uploadImageBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!profileImageInput.files || profileImageInput.files.length === 0) {
                showImageMessage('Please select an image file first.', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('profile_image', profileImageInput.files[0]);
            
            uploadImageBtn.disabled = true;
            uploadImageBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            
            fetch('update_staff_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                uploadImageBtn.disabled = false;
                uploadImageBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload';
                
                if (data.success) {
                    showImageMessage(data.message, 'success');
                    profileImageInput.value = '';
                    
                    // Update profile images in real-time
                    if (data.image_path) {
                        const timestamp = new Date().getTime();
                        const newImagePath = data.image_path + '?t=' + timestamp;
                        
                        // Update preview (same element)
                        imagePreview.src = newImagePath;
                        
                        // Update header profile image
                        const headerProfileImg = document.getElementById('header-profile-image');
                        if (headerProfileImg) {
                            headerProfileImg.src = newImagePath;
                        }
                    }
                } else {
                    showImageMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                uploadImageBtn.disabled = false;
                uploadImageBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload';
                showImageMessage('An error occurred. Please try again.', 'danger');
            });
        });
    }
    
    function showImageMessage(message, type) {
        const messageDiv = document.getElementById('image-message');
        messageDiv.className = 'alert alert-message alert-' + type;
        messageDiv.style.display = 'block';
        messageDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>' + message;
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
    
    // Password Update
    const updatePasswordBtn = document.getElementById('update-password-btn');
    
    if (updatePasswordBtn) {
        updatePasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validation
            if (!currentPassword || !newPassword || !confirmPassword) {
                showPasswordMessage('Please fill in all password fields.', 'warning');
                return;
            }
            
            if (newPassword.length < 8) {
                showPasswordMessage('New password must be at least 8 characters long.', 'warning');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showPasswordMessage('New password and confirm password do not match.', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            
            updatePasswordBtn.disabled = true;
            updatePasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            
            fetch('update_staff_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                updatePasswordBtn.disabled = false;
                updatePasswordBtn.innerHTML = '<i class="fas fa-key me-2"></i>Update Password';
                
                if (data.success) {
                    showPasswordMessage(data.message, 'success');
                    document.getElementById('current_password').value = '';
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                } else {
                    showPasswordMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                updatePasswordBtn.disabled = false;
                updatePasswordBtn.innerHTML = '<i class="fas fa-key me-2"></i>Update Password';
                showPasswordMessage('An error occurred. Please try again.', 'danger');
            });
        });
    }
    
    function showPasswordMessage(message, type) {
        const messageDiv = document.getElementById('password-message');
        messageDiv.className = 'alert alert-message alert-' + type;
        messageDiv.style.display = 'block';
        messageDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>' + message;
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>

