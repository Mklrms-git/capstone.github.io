<?php
// Test script to diagnose patient profile database issues
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Patient Profile Database Diagnostic</h2>";

// Check if patient is logged in
if (!isPatientLoggedIn()) {
    echo "<p style='color: red;'>❌ Patient is NOT logged in. Session ID: " . ($_SESSION['patient_user_id'] ?? 'not set') . "</p>";
    echo "<p>Please log in first to test the profile query.</p>";
    exit;
}

echo "<p style='color: green;'>✅ Patient is logged in. Session ID: " . $_SESSION['patient_user_id'] . "</p>";

// Test database connection
$conn = getDBConnection();
if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
    exit;
}
echo "<p style='color: green;'>✅ Database connection successful</p>";

// Check if patient_users table exists
$tables_check = $conn->query("SHOW TABLES LIKE 'patient_users'");
if (!$tables_check || $tables_check->num_rows === 0) {
    echo "<p style='color: red;'>❌ patient_users table does not exist!</p>";
    exit;
}
echo "<p style='color: green;'>✅ patient_users table exists</p>";

// Check if patients table exists
$tables_check = $conn->query("SHOW TABLES LIKE 'patients'");
if (!$tables_check || $tables_check->num_rows === 0) {
    echo "<p style='color: red;'>❌ patients table does not exist!</p>";
    exit;
}
echo "<p style='color: green;'>✅ patients table exists</p>";

// Check if patient_user exists
$stmt = $conn->prepare("SELECT * FROM patient_users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['patient_user_id']);
$stmt->execute();
$result = $stmt->get_result();
$patient_user_row = $result->fetch_assoc();
$stmt->close();

if (!$patient_user_row) {
    echo "<p style='color: red;'>❌ Patient user record not found in patient_users table for ID: " . $_SESSION['patient_user_id'] . "</p>";
    exit;
}
echo "<p style='color: green;'>✅ Patient user record found in patient_users table</p>";
echo "<pre>Patient User Data: " . print_r($patient_user_row, true) . "</pre>";

// Check if patient record exists
if (!isset($patient_user_row['patient_id'])) {
    echo "<p style='color: red;'>❌ patient_id is missing from patient_users record!</p>";
    exit;
}

$patient_id = $patient_user_row['patient_id'];
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient_row = $result->fetch_assoc();
$stmt->close();

if (!$patient_row) {
    echo "<p style='color: red;'>❌ Patient record not found in patients table for patient_id: " . $patient_id . "</p>";
    echo "<p>This is likely the issue! The JOIN in getCurrentPatientUser() will fail if the patient record doesn't exist.</p>";
    exit;
}
echo "<p style='color: green;'>✅ Patient record found in patients table</p>";
echo "<pre>Patient Data: " . print_r($patient_row, true) . "</pre>";

// Test the actual query used in getCurrentPatientUser
echo "<h3>Testing getCurrentPatientUser() query:</h3>";

// Check if profile_image column exists
$check_column = $conn->query("SHOW COLUMNS FROM patient_users LIKE 'profile_image'");
$has_profile_image = $check_column && $check_column->num_rows > 0;
$profile_image_select = $has_profile_image ? ', pu.profile_image' : ', NULL as profile_image';

$query = "SELECT pu.*, p.first_name, p.last_name, p.email, p.phone, p.date_of_birth, p.sex" . $profile_image_select . " 
         FROM patient_users pu 
         JOIN patients p ON pu.patient_id = p.id 
         WHERE pu.id = ?";

echo "<p>Query: <code>" . htmlspecialchars($query) . "</code></p>";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "<p style='color: red;'>❌ Failed to prepare query: " . $conn->error . "</p>";
    exit;
}

$stmt->bind_param("i", $_SESSION['patient_user_id']);
if (!$stmt->execute()) {
    echo "<p style='color: red;'>❌ Failed to execute query: " . $stmt->error . "</p>";
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$combined_data = $result->fetch_assoc();
$stmt->close();

if (!$combined_data) {
    echo "<p style='color: red;'>❌ Query returned no results! This is the problem.</p>";
    echo "<p>The JOIN between patient_users and patients is failing.</p>";
} else {
    echo "<p style='color: green;'>✅ Query successful! Data retrieved:</p>";
    echo "<pre>" . print_r($combined_data, true) . "</pre>";
    
    // Test getCurrentPatientUser function
    echo "<h3>Testing getCurrentPatientUser() function:</h3>";
    $user_data = getCurrentPatientUser();
    if ($user_data) {
        echo "<p style='color: green;'>✅ getCurrentPatientUser() returned data successfully</p>";
        echo "<pre>" . print_r($user_data, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ getCurrentPatientUser() returned null or false</p>";
    }
}

echo "<hr>";
echo "<p><strong>Diagnosis Complete</strong></p>";
echo "<p>If you see any red ❌ errors above, those are the issues preventing the profile tab from working.</p>";
?>

