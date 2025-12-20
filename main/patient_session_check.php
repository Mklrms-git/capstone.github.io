<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

header('Content-Type: application/json');

// If not logged in at all
if (!isPatientLoggedIn()) {
	http_response_code(401);
	echo json_encode([
		'success' => false,
		'message' => 'Not logged in. Please sign in.'
	]);
	exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id FROM patient_users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['patient_user_id']);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
	// Session refers to a deleted account; clear session
	session_unset();
	session_destroy();

	http_response_code(401);
	echo json_encode([
		'success' => false,
		'message' => 'Your account no longer exists. Please log in again or contact Mhavis.'
	]);
	exit();
}

echo json_encode(['success' => true]);


