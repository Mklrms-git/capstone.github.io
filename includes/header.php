<?php
if (!defined('MHAVIS_EXEC')) {
    define('MHAVIS_EXEC', true);
}
require_once 'config/init.php';

// Check if user is logged in (staff or patient)
$is_staff_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_patient_logged_in = isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id']);

if (!$is_staff_logged_in && !$is_patient_logged_in) {
    header('Location: login.php');
    exit();
}

// Get user's full name and profile image
if ($is_staff_logged_in) {
    $user_id = $_SESSION['user_id'];
    // Query database to get updated staff information (name, email, phone, username, profile_image, role)
    $stmt = $conn->prepare("SELECT first_name, last_name, email, phone, username, profile_image, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $full_name = $user['first_name'] . ' ' . $user['last_name'];
    $profile_image = $user['profile_image'] ?? 'img/default-avatar.png';
    
    // Update session variables to keep them in sync with database
    if (!empty($user['first_name'])) {
        $_SESSION['first_name'] = $user['first_name'];
    }
    if (!empty($user['last_name'])) {
        $_SESSION['last_name'] = $user['last_name'];
    }
    if (!empty($user['email'])) {
        $_SESSION['email'] = $user['email'];
    }
    if (!empty($user['phone'])) {
        $_SESSION['phone'] = $user['phone'];
    }
    if (!empty($user['username'])) {
        $_SESSION['username'] = $user['username'];
    }
    // Update role from database - this ensures role changes take effect immediately
    if (!empty($user['role'])) {
        $_SESSION['role'] = $user['role'];
    }
    
    $user_role = $_SESSION['role'];
    
    // Get pending request counts for Admin users
    $pending_registration_count = 0;
    $pending_appointment_count = 0;
    if ($user_role === 'Admin') {
        // Get pending patient registration requests count
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_registration_requests WHERE status = 'Pending'");
        if ($count_stmt) {
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
            $pending_registration_count = $count_row['count'] ?? 0;
            $count_stmt->close();
        }
        
        // Get pending appointment requests count
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'Pending'");
        if ($count_stmt) {
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
            $pending_appointment_count = $count_row['count'] ?? 0;
            $count_stmt->close();
        }
    }
} else {
    // Patient user - get profile image and name from database for consistency
    $patient_user_id = $_SESSION['patient_user_id'];
    $check_column = $conn->query("SHOW COLUMNS FROM patient_users LIKE 'profile_image'");
    $has_profile_image = $check_column->num_rows > 0;
    
    $profile_image_select = $has_profile_image ? ', pu.profile_image' : ', NULL as profile_image';
    
    // Query database to get updated patient information (name, email, phone, profile_image)
    $stmt = $conn->prepare("SELECT pu.*, p.first_name, p.last_name, p.email, p.phone" . $profile_image_select . " 
                           FROM patient_users pu 
                           JOIN patients p ON pu.patient_id = p.id 
                           WHERE pu.id = ?");
    $stmt->bind_param("i", $patient_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();
    $stmt->close();
    
    // Get profile image
    if ($has_profile_image && !empty($patient_data['profile_image'])) {
        $profile_image = $patient_data['profile_image'];
    } else {
        $profile_image = 'img/defaultDP.jpg';
    }
    
    // Get full name from database (updated info)
    $full_name = ($patient_data['first_name'] ?? $_SESSION['first_name'] ?? '') . ' ' . ($patient_data['last_name'] ?? $_SESSION['last_name'] ?? '');
    
    // Update session variables to keep them in sync
    if (!empty($patient_data['first_name'])) {
        $_SESSION['first_name'] = $patient_data['first_name'];
    }
    if (!empty($patient_data['last_name'])) {
        $_SESSION['last_name'] = $patient_data['last_name'];
    }
    if (!empty($patient_data['email'])) {
        $_SESSION['email'] = $patient_data['email'];
    }
    if (!empty($patient_data['phone'])) {
        $_SESSION['phone'] = $patient_data['phone'];
    }
    
    $user_role = 'Patient';
}

// Check if page is loaded in iframe
$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';
    
// Get patient notification count (only if patient logged in)
if ($is_patient_logged_in) {
    $patient_unread_count = 0;
    $patient_notifications = [];
    $conn_notif = getDBConnection();
    $unread_stmt = $conn_notif->prepare("SELECT COUNT(*) as count FROM notifications 
                                         WHERE recipient_id = ? 
                                         AND recipient_type = 'Patient' 
                                         AND is_read = 0");
    if ($unread_stmt) {
        $unread_stmt->bind_param("i", $patient_user_id);
        if ($unread_stmt->execute()) {
            $unread_result = $unread_stmt->get_result()->fetch_assoc();
            $patient_unread_count = $unread_result ? (int)$unread_result['count'] : 0;
        }
        $unread_stmt->close();
    }
    
    // Get notifications for modal (limit to 5 for initial load)
    $notif_page = isset($_GET['notif_page']) ? max(1, (int)$_GET['notif_page']) : 1;
    $notif_limit = 5;
    $notif_offset = ($notif_page - 1) * $notif_limit;
    
    // Get total count
    $total_notif_stmt = $conn_notif->prepare("SELECT COUNT(*) as total FROM notifications 
                                             WHERE recipient_id = ? 
                                             AND recipient_type = 'Patient'");
    $total_notifications = 0;
    if ($total_notif_stmt) {
        $total_notif_stmt->bind_param("i", $patient_user_id);
        if ($total_notif_stmt->execute()) {
            $total_result = $total_notif_stmt->get_result()->fetch_assoc();
            $total_notifications = $total_result ? (int)$total_result['total'] : 0;
        }
        $total_notif_stmt->close();
    }
    
    $notif_stmt = $conn_notif->prepare("SELECT * FROM notifications 
                                        WHERE recipient_id = ? 
                                        AND recipient_type = 'Patient'
                                        ORDER BY created_at DESC 
                                        LIMIT ? OFFSET ?");
    if ($notif_stmt) {
        $notif_stmt->bind_param("iii", $patient_user_id, $notif_limit, $notif_offset);
        if ($notif_stmt->execute()) {
            $patient_notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $notif_stmt->close();
    }
    
    $total_notif_pages = ceil($total_notifications / $notif_limit);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Mhavis Medical & Diagnostic Center'; ?></title>
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- FullCalendar -->
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/main.min.css' rel='stylesheet' />
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Custom styles -->
    <style>
        :root {
            --primary-color: #0c1a6a;
            --secondary-color: #1a2fa0;
            --light-bg: #f8f9fa;
        }
        
        .sidebar {
            background-color: var(--primary-color);
            min-height: 100vh;
            color: white;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            transition: opacity 0.3s ease-in-out;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease-in-out;
        }
        
        /* Iframe mode - hide sidebar and top bar */
        body.iframe-mode .sidebar,
        body.iframe-mode .sidebar-overlay {
            display: none !important;
        }
        
        body.iframe-mode .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        
        body.iframe-mode .top-bar {
            display: none !important;
        }
        
        body.iframe-mode .container-fluid {
            padding: 0 !important;
        }
        
        .mobile-menu-btn {
            display: none;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 1.2rem;
            cursor: pointer;
            margin-right: 15px;
        }
        
        .mobile-menu-btn:hover {
            background: var(--secondary-color);
        }
        
        .mobile-menu-btn i {
            font-size: 1.5rem;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }

        .nav-link:hover {
            color: white;
            background-color: var(--secondary-color);
        }

        .nav-link.active {
            background-color: var(--secondary-color);
            color: white;
        }

        .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .top-bar {
            background-color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .top-bar h4 {
            margin: 0;
            font-size: 1.5rem;
            flex: 1;
            min-width: 200px;
        }
        
        .top-bar-left {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .dropdown-menu {
            min-width: 200px;
        }
        
        .dropdown-item i {
            width: 20px;
            margin-right: 10px;
        }
        
        /* Responsive Design */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .mobile-menu-btn {
                display: inline-block;
            }
            
            .top-bar {
                padding: 12px 15px;
            }
            
            .top-bar h4 {
                font-size: 1.25rem;
            }
            
            .user-profile span {
                display: none;
            }
            
            .sidebar-header img {
                width: 60px;
                height: 60px;
            }
            
            .sidebar-header h5 {
                font-size: 1rem;
            }
            
            .nav-link {
                padding: 10px 15px;
                font-size: 0.95rem;
            }
            
            .nav-link i {
                width: 20px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 575.98px) {
            .top-bar {
                padding: 10px 12px;
            }
            
            .top-bar h4 {
                font-size: 1.1rem;
            }
            
            .user-profile img {
                width: 35px;
                height: 35px;
            }
            
            .main-content {
                padding: 12px;
            }
            
            .sidebar {
                width: 100%;
                max-width: 280px;
            }
            
            .sidebar-header {
                padding: 15px;
            }
            
            .nav-link {
                padding: 12px 15px;
            }
        }
    </style>
</head>
<body<?php echo $is_iframe ? ' class="iframe-mode"' : ''; ?>>
    <!-- Sidebar -->
    <div class="sidebar"<?php echo $is_iframe ? ' style="display: none;"' : ''; ?>>
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Mhavis Logo">
            <h5>Mhavis Medical</h5>
        </div>
        <nav class="mt-3">
            <?php if ($is_patient_logged_in): ?>
            <!-- Patient Navigation -->
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link patient-nav-link <?php echo $active_page === 'profile' ? 'active' : ''; ?>" href="patient_dashboard.php#profile" data-section="profile">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link patient-nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>" href="patient_dashboard.php#dashboard" data-section="dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link patient-nav-link <?php echo $active_page === 'appointments' ? 'active' : ''; ?>" href="patient_dashboard.php#appointments" data-section="appointments">
                        <i class="fas fa-calendar-alt"></i> My Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link patient-nav-link <?php echo $active_page === 'book-appointment' ? 'active' : ''; ?>" href="patient_dashboard.php#book-appointment" data-section="book-appointment">
                        <i class="fas fa-plus-circle"></i> Book Appointment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link patient-nav-link <?php echo $active_page === 'medical-records' ? 'active' : ''; ?>" href="patient_dashboard.php#medical-records" data-section="medical-records">
                        <i class="fas fa-file-medical"></i> Medical Records
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link patient-nav-link <?php echo $active_page === 'prescriptions' ? 'active' : ''; ?>" href="patient_dashboard.php#prescriptions" data-section="prescriptions">
                        <i class="fas fa-prescription-bottle-alt"></i> Prescriptions
                    </a>
                </li>
            </ul>
            <?php elseif ($is_staff_logged_in && isAdmin()): ?>
            <!-- Admin Navigation -->
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'profile' ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'patients' ? 'active' : ''; ?>" href="patients.php">
                        <i class="fas fa-users"></i> Patient Records
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'doctors' ? 'active' : ''; ?>" href="doctors.php">
                        <i class="fas fa-user-md"></i> Doctor Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'appointments' ? 'active' : ''; ?>" href="appointments.php">
                        <i class="fas fa-calendar-plus"></i> Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'patient-registrations' ? 'active' : ''; ?>" href="admin_patient_registrations.php">
                        <span><i class="fas fa-user-plus"></i> Patient Registrations</span>
                        <?php if ($pending_registration_count > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?php echo $pending_registration_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'appointment-requests' ? 'active' : ''; ?>" href="admin_appointment_requests.php">
                        <span><i class="fas fa-calendar-check"></i> Appointment Request</span>
                        <?php if ($pending_appointment_count > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?php echo $pending_appointment_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <!-- Note: User Management, Fee Management, Daily Revenue, and Report Analytics are now integrated into the Admin Dashboard -->
            <?php else: ?>
            <!-- Doctor Navigation -->
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'profile' ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>" href="doctor_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'patients' ? 'active' : ''; ?>" href="patients.php">
                        <i class="fas fa-users"></i> Patient Records
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page === 'schedules' ? 'active' : ''; ?>" href="doctor_schedules.php">
                        <i class="fas fa-calendar-alt"></i> Schedules
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"<?php echo $is_iframe ? ' style="display: none;"' : ''; ?>></div>

    <!-- Main Content -->
    <div class="main-content"<?php echo $is_iframe ? ' style="margin-left: 0; padding: 0;"' : ''; ?>>
        <!-- Top Bar -->
        <div class="top-bar"<?php echo $is_iframe ? ' style="display: none;"' : ''; ?>>
            <div class="top-bar-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <h4><?php echo $page_title ?? 'Dashboard'; ?></h4>
            </div>
            <div class="d-flex align-items-center">
                <?php if ($is_staff_logged_in && isset($user_role) && $user_role === 'Doctor' && isset($doctorUnreadCount)): ?>
                <button class="btn btn-outline-secondary position-relative me-3" id="openDoctorNotifications" data-bs-toggle="modal" data-bs-target="#doctorNotificationsModal" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($doctorUnreadCount)): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="doctorUnreadBadge">
                            <?php echo intval($doctorUnreadCount); ?>
                        </span>
                    <?php endif; ?>
                </button>
                <?php elseif ($is_patient_logged_in): ?>
                <button class="btn btn-outline-secondary position-relative me-3" id="openPatientNotifications" data-bs-toggle="modal" data-bs-target="#patientNotificationsModal" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if (isset($patient_unread_count) && $patient_unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="patientUnreadBadge">
                            <?php echo intval($patient_unread_count); ?>
                        </span>
                    <?php endif; ?>
                </button>
                <?php endif; ?>
                <div class="user-profile dropdown">
                <a href="#" class="dropdown-toggle text-decoration-none text-dark" data-bs-toggle="dropdown">
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" 
                         onerror="this.src='img/defaultDP.jpg'" 
                         id="header-profile-image">
                    <span class="ms-2"><?php echo htmlspecialchars($full_name); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item text-danger" href="<?php echo $is_patient_logged_in ? 'patient_logout.php' : 'logout.php'; ?>">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
                </div>
            </div>
        </div>

        <!-- Page Content Container -->
        <div class="container-fluid"<?php echo $is_iframe ? ' style="padding: 0;"' : ''; ?>>
            <!-- Content will be injected here -->
            
    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            if (mobileMenuBtn && sidebar) {
                function toggleSidebar() {
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                    document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                }
                
                mobileMenuBtn.addEventListener('click', toggleSidebar);
                
                if (sidebarOverlay) {
                    sidebarOverlay.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    });
                }
                
                // Close sidebar when clicking on a nav link (mobile)
                // Note: Patient navigation links are handled by patient_dashboard.php script
                // which prevents default and handles section switching
                const navLinks = document.querySelectorAll('.sidebar .nav-link:not(.patient-nav-link)');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        // Only close sidebar on mobile - don't interfere with patient navigation
                        // patient_dashboard.php will handle preventDefault for patient-nav-link
                        if (window.innerWidth <= 991.98) {
                            sidebar.classList.remove('active');
                            sidebarOverlay.classList.remove('active');
                            document.body.style.overflow = '';
                        }
                    });
                });
                
                // Handle window resize
                let resizeTimer;
                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function() {
                        if (window.innerWidth > 991.98) {
                            sidebar.classList.remove('active');
                            sidebarOverlay.classList.remove('active');
                            document.body.style.overflow = '';
                        }
                    }, 250);
                });
            }
        });
    </script>

    <!-- Patient Notifications Modal -->
    <?php if ($is_patient_logged_in): ?>
    <div class="modal fade" id="patientNotificationsModal" tabindex="-1" aria-labelledby="patientNotificationsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patientNotificationsModalLabel">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </h5>
                    <?php if (!empty($patient_notifications)): ?>
                        <button class="btn btn-sm btn-outline-primary" id="mark-all-read-header">
                            <i class="fas fa-check-double me-1"></i>Mark All as Read
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($patient_notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No notifications yet</p>
                            <p class="text-muted small">You'll see your appointment updates and messages here.</p>
                        </div>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($patient_notifications as $notification): ?>
                                <div class="notification-item-header <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                    <div class="notification-icon">
                                        <?php
                                        // Set icon based on notification type
                                        $icon = 'fa-bell';
                                        $icon_color = 'primary';
                                        
                                        switch ($notification['type']) {
                                            case 'Appointment_Approved':
                                                $icon = 'fa-calendar-check';
                                                $icon_color = 'success';
                                                break;
                                            case 'Appointment_Rejected':
                                                $icon = 'fa-times-circle';
                                                $icon_color = 'danger';
                                                break;
                                            case 'Appointment_Reminder':
                                                $icon = 'fa-clock';
                                                $icon_color = 'warning';
                                                break;
                                            case 'Appointment_Rescheduled':
                                                $icon = 'fa-calendar-alt';
                                                $icon_color = 'info';
                                                break;
                                            case 'Registration_Approved':
                                                $icon = 'fa-user-check';
                                                $icon_color = 'success';
                                                break;
                                            case 'Registration_Rejected':
                                                $icon = 'fa-user-times';
                                                $icon_color = 'danger';
                                                break;
                                            case 'Medical_Record_Updated':
                                                $icon = 'fa-file-medical';
                                                $icon_color = 'info';
                                                break;
                                            case 'Prescription_Added':
                                                $icon = 'fa-prescription-bottle-alt';
                                                $icon_color = 'info';
                                                break;
                                            default:
                                                $icon = 'fa-envelope';
                                                $icon_color = 'primary';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?> text-<?php echo $icon_color; ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <h6 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="notification-time text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php
                                            $time_ago = time() - strtotime($notification['created_at']);
                                            if ($time_ago < 60) {
                                                echo 'Just now';
                                            } elseif ($time_ago < 3600) {
                                                echo floor($time_ago / 60) . ' minutes ago';
                                            } elseif ($time_ago < 86400) {
                                                echo floor($time_ago / 3600) . ' hours ago';
                                            } elseif ($time_ago < 604800) {
                                                echo floor($time_ago / 86400) . ' days ago';
                                            } else {
                                                echo date('M j, Y', strtotime($notification['created_at']));
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if (!$notification['is_read']): ?>
                                            <button class="btn btn-sm btn-link mark-read-header" title="Mark as read">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-link text-danger delete-notification-header" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($total_notif_pages > 1): ?>
                            <nav aria-label="Notification pagination" class="mt-3">
                                <ul class="pagination pagination-sm justify-content-center mb-0">
                                    <li class="page-item <?php echo $notif_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link notification-page-link" href="#" data-page="<?php echo $notif_page - 1; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_notif_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $notif_page ? 'active' : ''; ?>">
                                            <a class="page-link notification-page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $notif_page >= $total_notif_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link notification-page-link" href="#" data-page="<?php echo $notif_page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .notification-item-header {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.2s ease;
        }

        .notification-item-header:hover {
            background-color: #f8fafc;
        }

        .notification-item-header.unread {
            background-color: #eff6ff;
            border-left: 3px solid #0D92F4;
        }

        .notification-item-header:last-child {
            border-bottom: none;
        }

        .notification-item-header .notification-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f1f5f9;
            border-radius: 50%;
            margin-right: 1rem;
        }

        .notification-item-header .notification-icon i {
            font-size: 1.1rem;
        }

        .notification-item-header .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-item-header .notification-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .notification-item-header .notification-message {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .notification-item-header .notification-time {
            font-size: 0.75rem;
        }

        .notification-item-header .notification-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
            margin-left: 0.5rem;
        }

        .notification-item-header .btn-link {
            padding: 0.25rem 0.5rem;
            color: #64748b;
            text-decoration: none;
        }

        .notification-item-header .btn-link:hover {
            color: #0D92F4;
        }

        .notifications-list {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>

    <script>
        // Patient notification functionality in header
        function updatePatientNotificationBadge() {
            fetch('get_patient_notifications.php?count_only=1')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('patientUnreadBadge');
                    if (data.unread_count > 0) {
                        if (badge) {
                            badge.textContent = data.unread_count;
                        } else {
                            // Create badge if it doesn't exist
                            const btn = document.getElementById('openPatientNotifications');
                            if (btn) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                                newBadge.id = 'patientUnreadBadge';
                                newBadge.textContent = data.unread_count;
                                btn.appendChild(newBadge);
                            }
                        }
                    } else {
                        if (badge) {
                            badge.remove();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating notification badge:', error);
                });
        }

        function reloadNotificationModal(page = 1) {
            fetch(`get_patient_notifications.php?page=${page}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modalBody = document.querySelector('#patientNotificationsModal .modal-body');
                        if (modalBody) {
                            if (data.notifications.length === 0 && data.pagination.current_page === 1) {
                                modalBody.innerHTML = `
                                    <div class="text-center py-5">
                                        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">No notifications yet</p>
                                        <p class="text-muted small">You'll see your appointment updates and messages here.</p>
                                    </div>
                                `;
                                const markAllBtn = document.getElementById('mark-all-read-header');
                                if (markAllBtn) markAllBtn.remove();
                            } else {
                                let html = '<div class="notifications-list">';
                                data.notifications.forEach(notification => {
                                    const iconMap = {
                                        'Appointment_Approved': {icon: 'fa-calendar-check', color: 'success'},
                                        'Appointment_Rejected': {icon: 'fa-times-circle', color: 'danger'},
                                        'Appointment_Reminder': {icon: 'fa-clock', color: 'warning'},
                                        'Appointment_Rescheduled': {icon: 'fa-calendar-alt', color: 'info'},
                                        'Registration_Approved': {icon: 'fa-user-check', color: 'success'},
                                        'Registration_Rejected': {icon: 'fa-user-times', color: 'danger'},
                                        'Medical_Record_Updated': {icon: 'fa-file-medical', color: 'info'},
                                        'Prescription_Added': {icon: 'fa-prescription-bottle-alt', color: 'info'}
                                    };
                                    const iconInfo = iconMap[notification.type] || {icon: 'fa-envelope', color: 'primary'};
                                    
                                    const timeAgo = Math.floor((new Date() - new Date(notification.created_at)) / 1000);
                                    let timeText = 'Just now';
                                    if (timeAgo >= 604800) {
                                        timeText = new Date(notification.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
                                    } else if (timeAgo >= 86400) {
                                        timeText = Math.floor(timeAgo / 86400) + ' days ago';
                                    } else if (timeAgo >= 3600) {
                                        timeText = Math.floor(timeAgo / 3600) + ' hours ago';
                                    } else if (timeAgo >= 60) {
                                        timeText = Math.floor(timeAgo / 60) + ' minutes ago';
                                    }
                                    
                                    html += `
                                        <div class="notification-item-header ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                                            <div class="notification-icon">
                                                <i class="fas ${iconInfo.icon} text-${iconInfo.color}"></i>
                                            </div>
                                            <div class="notification-content">
                                                <h6 class="notification-title">${escapeHtml(notification.title)}</h6>
                                                <p class="notification-message">${escapeHtml(notification.message)}</p>
                                                <small class="notification-time text-muted">
                                                    <i class="fas fa-clock me-1"></i>${timeText}
                                                </small>
                                            </div>
                                            <div class="notification-actions">
                                                ${!notification.is_read ? '<button class="btn btn-sm btn-link mark-read-header" title="Mark as read"><i class="fas fa-check"></i></button>' : ''}
                                                <button class="btn btn-sm btn-link text-danger delete-notification-header" title="Delete"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    `;
                                });
                                html += '</div>';
                                
                                // Add pagination if needed
                                if (data.pagination && data.pagination.total_pages > 1) {
                                    html += '<nav aria-label="Notification pagination" class="mt-3">';
                                    html += '<ul class="pagination pagination-sm justify-content-center mb-0">';
                                    
                                    // Previous button
                                    html += `<li class="page-item ${data.pagination.current_page <= 1 ? 'disabled' : ''}">`;
                                    html += `<a class="page-link notification-page-link" href="#" data-page="${data.pagination.current_page - 1}">Previous</a>`;
                                    html += '</li>';
                                    
                                    // Page numbers
                                    for (let i = 1; i <= data.pagination.total_pages; i++) {
                                        html += `<li class="page-item ${i == data.pagination.current_page ? 'active' : ''}">`;
                                        html += `<a class="page-link notification-page-link" href="#" data-page="${i}">${i}</a>`;
                                        html += '</li>';
                                    }
                                    
                                    // Next button
                                    html += `<li class="page-item ${data.pagination.current_page >= data.pagination.total_pages ? 'disabled' : ''}">`;
                                    html += `<a class="page-link notification-page-link" href="#" data-page="${data.pagination.current_page + 1}">Next</a>`;
                                    html += '</li>';
                                    
                                    html += '</ul>';
                                    html += '</nav>';
                                }
                                
                                modalBody.innerHTML = html;
                                
                                // Re-attach event listeners
                                attachNotificationEventListeners();
                                
                                // Attach pagination event listeners
                                document.querySelectorAll('.notification-page-link').forEach(link => {
                                    link.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        const page = parseInt(this.dataset.page);
                                        if (page > 0) {
                                            currentNotificationPage = page;
                                            reloadNotificationModal(page);
                                        }
                                    });
                                });
                            }
                        }
                        updatePatientNotificationBadge();
                        
                        // Also update dashboard notifications section if visible
                        const dashboardSection = document.getElementById('notifications-section');
                        if (dashboardSection && dashboardSection.classList.contains('active')) {
                            // If notifications section is visible, reload it
                            setTimeout(() => {
                                location.reload(); // Reload to sync dashboard notifications section
                            }, 500);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error reloading notifications:', error);
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function attachNotificationEventListeners() {
            // Mark single notification as read
            document.querySelectorAll('.mark-read-header').forEach(button => {
                button.addEventListener('click', function() {
                    const notificationItem = this.closest('.notification-item-header');
                    const notificationId = notificationItem.dataset.id;
                    
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id=' + notificationId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update badge immediately
                            updatePatientNotificationBadge();
                            // Reload modal to reflect changes
                            reloadNotificationModal(currentNotificationPage);
                        } else {
                            showAlert('Error: ' + data.message, 'Error', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error marking notification as read:', error);
                        showAlert('Error marking notification as read. Please try again.', 'Error', 'error');
                    });
                });
            });
            
            // Delete notification
            document.querySelectorAll('.delete-notification-header').forEach(button => {
                button.addEventListener('click', function() {
                    confirmDialog('Are you sure you want to delete this notification?', 'Delete', 'Cancel').then(function(confirmed) {
                        if (!confirmed) return;
                    
                        const notificationItem = button.closest('.notification-item-header');
                        const notificationId = notificationItem.dataset.id;
                        const notificationsList = document.querySelector('.notifications-list');
                        const currentNotificationsCount = notificationsList ? notificationsList.querySelectorAll('.notification-item-header').length : 0;
                        
                        fetch('delete_notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'id=' + notificationId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // If we deleted the last item on the current page and we're not on page 1, go to previous page
                                let pageToLoad = currentNotificationPage;
                                if (currentNotificationsCount === 1 && currentNotificationPage > 1) {
                                    pageToLoad = currentNotificationPage - 1;
                                    currentNotificationPage = pageToLoad;
                                }
                                reloadNotificationModal(pageToLoad);
                            } else {
                                showAlert('Error: ' + data.message, 'Error', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting notification:', error);
                            showAlert('Error deleting notification. Please try again.', 'Error', 'error');
                        });
                    });
                });
            });
        }

        // Track current notification page
        let currentNotificationPage = 1;
        
        // Mark single notification as read (header modal)
        document.addEventListener('DOMContentLoaded', function() {
            attachNotificationEventListeners();
            
            // Add pagination event listeners for PHP-generated links
            document.querySelectorAll('.notification-page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = parseInt(this.dataset.page);
                    if (page > 0) {
                        currentNotificationPage = page;
                        reloadNotificationModal(page);
                    }
                });
            });
            
            // Mark all notifications as read (header modal)
            const markAllReadHeaderBtn = document.getElementById('mark-all-read-header');
            if (markAllReadHeaderBtn) {
                markAllReadHeaderBtn.addEventListener('click', function() {
                    fetch('mark_all_notifications_read.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            reloadNotificationModal(currentNotificationPage);
                            showAlert('All notifications marked as read!', 'Success', 'success');
                        } else {
                            showAlert('Error: ' + data.message, 'Error', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error marking all notifications as read:', error);
                        showAlert('Error marking all notifications as read. Please try again.', 'Error', 'error');
                    });
                });
            }
        });
    </script>
    <?php endif; ?> 