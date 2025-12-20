<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

// Check if patient is logged in before destroying session
$is_patient = isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id']);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

// Redirect based on user type
if ($is_patient) {
	header('Location: patient_login.php');
} else {
	header('Location: login.php');
}
exit();
?>
