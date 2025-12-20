<?php
/**
 * API endpoint to get user profile image path for any user type
 * Supports Admin, Doctor, and Patient users
 * Used for real-time profile image updates
 */
define('MHAVIS_EXEC', true);
require_once 'config/init.php';

header('Content-Type: application/json');

// Check if user is logged in (either admin/doctor or patient)
$isAdminOrDoctor = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$isPatient = isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id']);

if (!$isAdminOrDoctor && !$isPatient) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
    exit();
}

$conn = getDBConnection();
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0; // For backward compatibility (patients.id)
$user_type = isset($_GET['user_type']) ? trim($_GET['user_type']) : ''; // 'admin', 'doctor', or 'patient'

// Support backward compatibility: if patient_id is provided, look up patient_users.id
if ($patient_id > 0 && $user_id <= 0) {
    $stmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ? LIMIT 1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = (int)$row['id'];
        $user_type = 'patient';
    }
    $stmt->close();
}

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID or patient ID']);
    exit();
}

// Get current user info for permission checking
$currentUserId = 0;
$currentUserRole = '';
$currentPatientUserId = 0;

if ($isAdminOrDoctor) {
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $currentUserRole = $_SESSION['role'] ?? '';
} else if ($isPatient) {
    $currentPatientUserId = $_SESSION['patient_user_id'] ?? 0;
}

$profile_image = null;
$last_updated = null;
$user_found = false;

// Determine which table to query based on user_type or try to detect
if (empty($user_type)) {
    // Try to detect user type by checking both tables
    // First check users table (admin/doctor)
    $stmt = $conn->prepare("SELECT id, profile_image, updated_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_type = 'admin_or_doctor';
        $row = $result->fetch_assoc();
        $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : null;
        $last_updated = $row['updated_at'] ?? null;
        $user_found = true;
    }
    $stmt->close();
    
    // If not found in users table, check patient_users table
    if (!$user_found) {
        $stmt = $conn->prepare("SELECT id, profile_image, updated_at FROM patient_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user_type = 'patient';
            $row = $result->fetch_assoc();
            $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : null;
            $last_updated = $row['updated_at'] ?? null;
            $user_found = true;
        }
        $stmt->close();
    }
} else {
    // User type is specified, query appropriate table
    if ($user_type === 'admin' || $user_type === 'doctor' || $user_type === 'admin_or_doctor') {
        $stmt = $conn->prepare("SELECT id, profile_image, updated_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : null;
            $last_updated = $row['updated_at'] ?? null;
            $user_found = true;
        }
        $stmt->close();
    } else if ($user_type === 'patient') {
        $stmt = $conn->prepare("SELECT id, profile_image, updated_at FROM patient_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : null;
            $last_updated = $row['updated_at'] ?? null;
            $user_found = true;
        }
        $stmt->close();
    }
}

if (!$user_found) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Permission checking based on user type
if ($user_type === 'patient') {
    // For patient users:
    // - Patient can only view their own profile image
    // - Admin can view any patient's profile image
    // - Doctor can view assigned patients' profile images
    if ($isPatient) {
        // Patient viewing their own image
        if ($currentPatientUserId != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized - You can only view your own profile image']);
            exit();
        }
    } else if ($isAdminOrDoctor) {
        if ($currentUserRole === 'Admin') {
            // Admin can view any patient - verify patient exists
            $stmt = $conn->prepare("SELECT id FROM patient_users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $stmt->close();
                echo json_encode(['success' => false, 'message' => 'Patient not found']);
                exit();
            }
            $stmt->close();
        } else if ($currentUserRole === 'Doctor') {
            // Doctor can only view assigned patients
            $stmt = $conn->prepare("SELECT DISTINCT pu.id 
                FROM patient_users pu
                JOIN patients p ON pu.patient_id = p.id
                JOIN appointments a ON p.id = a.patient_id
                JOIN doctors d ON a.doctor_id = d.id
                JOIN users u ON d.user_id = u.id
                WHERE pu.id = ? AND u.id = ?");
            $stmt->bind_param("ii", $user_id, $currentUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $stmt->close();
                echo json_encode(['success' => false, 'message' => 'Patient not found or access denied']);
                exit();
            }
            $stmt->close();
        }
    }
} else if ($user_type === 'admin' || $user_type === 'doctor' || $user_type === 'admin_or_doctor') {
    // For admin/doctor users:
    // - Users can view their own profile image
    // - Admin can view any admin/doctor profile image
    // - Doctor can only view their own profile image
    if ($isAdminOrDoctor) {
        if ($currentUserRole === 'Admin') {
            // Admin can view any user's profile image - already verified above
        } else if ($currentUserRole === 'Doctor') {
            // Doctor can only view their own profile image
            if ($currentUserId != $user_id) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized - You can only view your own profile image']);
                exit();
            }
        }
    } else if ($isPatient) {
        // Patients cannot view admin/doctor profile images
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
}

// Return the profile image path with timestamp for cache busting
echo json_encode([
    'success' => true,
    'profile_image' => $profile_image,
    'last_updated' => $last_updated,
    'timestamp' => time(),
    'user_type' => $user_type
]);

