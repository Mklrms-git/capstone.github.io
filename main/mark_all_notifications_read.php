<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

header('Content-Type: application/json');

requirePatientLogin();

$patient_user = getCurrentPatientUser();
$conn = getDBConnection();

// Mark all notifications as read for this patient
$update_stmt = $conn->prepare("UPDATE notifications 
                               SET is_read = 1 
                               WHERE recipient_id = ? 
                               AND recipient_type = 'Patient' 
                               AND is_read = 0");
$update_stmt->bind_param("i", $patient_user['id']);

if ($update_stmt->execute()) {
    $affected_rows = $update_stmt->affected_rows;
    echo json_encode([
        'success' => true, 
        'message' => 'All notifications marked as read',
        'count' => $affected_rows
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
}
?>

