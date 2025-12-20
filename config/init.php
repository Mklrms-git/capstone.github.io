<?php
// Prevent direct access
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Load PHPMailer autoloader
require_once __DIR__ . '/../includes/PHPmailer/autoload.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'mhavis');

// Database connection function
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            $conn->set_charset("utf8mb4");
        }
        return $conn;
    }
}

// Initialize database connection
$conn = getDBConnection();

// Common security functions
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if ($input instanceof mysqli) {
            return mysqli_real_escape_string($input, $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        // Admin is now the top-level role with full system access
        return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
    }
}

if (!function_exists('isDoctor')) {
    function isDoctor() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'Doctor';
    }
}

// Admin is now the top-level role with full system access

if (!function_exists('hasRole')) {
    function hasRole($required_role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        // Check if patient is logged in - redirect to unauthorized page
        if (isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id'])) {
            header('Location: unauthorized.php');
            exit();
        }
        
        requireLogin();
        // Admin is now the top-level role with full access
        if (!isAdmin()) {
            header('Location: unauthorized.php');
            exit();
        }
    }
}

if (!function_exists('requireDoctor')) {
    function requireDoctor() {
        // Check if patient is logged in - redirect to unauthorized page
        if (isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id'])) {
            header('Location: unauthorized.php');
            exit();
        }
        
        requireLogin();
        if (!isDoctor()) {
            header('Location: unauthorized.php');
            exit();
        }
    }
}

// Admin role has full system access including user management

if (!function_exists('requireRole')) {
    function requireRole($required_role) {
        // Check if patient is logged in - redirect to unauthorized page
        // Patients should not access Admin or Doctor pages
        if (isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id'])) {
            header('Location: unauthorized.php');
            exit();
        }
        
        requireLogin();
        if (!hasRole($required_role)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
}

if (!function_exists('regenerateSession')) {
    function regenerateSession() {
        if (!isset($_SESSION['last_regeneration'])) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        } else {
            $interval = 60 * 30; // 30 minutes
            if (time() - $_SESSION['last_regeneration'] >= $interval) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
}

if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout() {
        $max_lifetime = 3600; // 1 hour
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $max_lifetime)) {
            session_unset();
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('Y-m-d', strtotime($date));
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₱' . number_format($amount, 2);
    }
}

if (!function_exists('getUserDetails')) {
    function getUserDetails($userId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

if (!function_exists('successAlert')) {
    function successAlert($message) {
        return "<div class='alert alert-success' role='alert'>" . htmlspecialchars($message) . "</div>";
    }
}

if (!function_exists('errorAlert')) {
    function errorAlert($message) {
        return "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($message) . "</div>";
    }
}

// Call security functions if session is active
if (session_status() === PHP_SESSION_ACTIVE) {
    regenerateSession();
    checkSessionTimeout();
} 