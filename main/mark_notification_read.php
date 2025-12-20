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

// Verify the notification belongs to this patient, then mark as read
$update_stmt = $conn->prepare("UPDATE notifications 
                               SET is_read = 1 
                               WHERE id = ? 
                               AND recipient_id = ? 
                               AND recipient_type = 'Patient'");
$update_stmt->bind_param("ii", $notification_id, $patient_user['id']);

if ($update_stmt->execute()) {
    if ($update_stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
}
?>

