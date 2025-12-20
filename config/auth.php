<?php
// Prevent direct access
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    // Explicitly select all needed columns to ensure they are retrieved
    $stmt = $conn->prepare("SELECT id, first_name, last_name, username, email, phone, address, role, department_id, specialization, status, profile_image, created_at, updated_at, license_number, is_available, prc_number, license_type, prc_id_document, last_login FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    if (!$stmt->execute()) {
        error_log("Failed to execute statement: " . $stmt->error);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        error_log("No user found with ID: " . $_SESSION['user_id']);
        $stmt->close();
        return null;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Database values take precedence - only use session as fallback if database value is truly NULL or empty string
    // This ensures updated database values are always displayed after refresh
    if (($user['first_name'] === null || $user['first_name'] === '') && !empty($_SESSION['first_name'])) {
        $user['first_name'] = $_SESSION['first_name'];
    }
    if (($user['last_name'] === null || $user['last_name'] === '') && !empty($_SESSION['last_name'])) {
        $user['last_name'] = $_SESSION['last_name'];
    }
    if (($user['email'] === null || $user['email'] === '') && !empty($_SESSION['email'])) {
        $user['email'] = $_SESSION['email'];
    }
    if (($user['phone'] === null || $user['phone'] === '') && !empty($_SESSION['phone'])) {
        $user['phone'] = $_SESSION['phone'];
    }
    // Username should prioritize database value, but use session as fallback if database is NULL/empty
    // This ensures we show the username even if database value is missing, but database updates take precedence
    if (!isset($user['username']) || $user['username'] === null || trim($user['username']) === '') {
        // Only use session if database value is truly missing
        if (!empty($_SESSION['username']) && trim($_SESSION['username']) !== '') {
            $user['username'] = $_SESSION['username'];
        } else {
            $user['username'] = '';
        }
    }
    
    // Ensure status defaults to 'Active' if empty or null
    if (empty($user['status'])) {
        $user['status'] = 'Active';
    }
    
    return $user;
}

// Check if user has role
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Require role
function requireRole($role) {
    if (!hasRole($role)) {
        header('Location: unauthorized.php');
        exit();
    }
} 