<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';

// Destroy patient session
if (isset($_SESSION['patient_user_id'])) {
    // Log logout activity
    if (function_exists('logPatientActivity')) {
        logPatientActivity($_SESSION['patient_user_id'], 'Patient logged out');
    }
    
    // Clear patient session variables
    unset($_SESSION['patient_user_id']);
    unset($_SESSION['patient_id']);
    unset($_SESSION['username']);
    unset($_SESSION['first_name']);
    unset($_SESSION['last_name']);
    unset($_SESSION['email']);
    unset($_SESSION['phone']);
    unset($_SESSION['role']);
    unset($_SESSION['last_activity']);
    unset($_SESSION['token']);
}

// Destroy the session
session_destroy();

// Redirect to patient login
header('Location: patient_login.php');
exit();
