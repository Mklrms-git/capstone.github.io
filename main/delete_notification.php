<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

header('Content-Type: application/json');

requirePatientLogin();

$patient_user = getCurrentPatientUser();
$notification_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

$conn = getDBConnection();

// Verify the notification belongs to this patient, then delete it
$delete_stmt = $conn->prepare("DELETE FROM notifications 
                               WHERE id = ? 
                               AND recipient_id = ? 
                               AND recipient_type = 'Patient'");
$delete_stmt->bind_param("ii", $notification_id, $patient_user['id']);

if ($delete_stmt->execute()) {
    if ($delete_stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
}
?>

