<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
requireDoctor();

$doctor_id = $_SESSION['user_id'];

$conn = getDBConnection();
$notifications = [];
$unread_count = 0;

// Check if only count is needed
$count_only = isset($_GET['count_only']) && $_GET['count_only'] == '1';

// Get unread count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications 
                               WHERE recipient_id = ? 
                               AND recipient_type = 'Doctor' 
                               AND is_read = 0");
if ($unread_stmt) {
    $unread_stmt->bind_param("i", $doctor_id);
    if ($unread_stmt->execute()) {
        $unread_result = $unread_stmt->get_result()->fetch_assoc();
        $unread_count = $unread_result ? (int)$unread_result['count'] : 0;
    }
    $unread_stmt->close();
}

if ($count_only) {
    // Return only count
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);
    exit;
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Get total count of notifications
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications 
                             WHERE recipient_id = ? 
                             AND recipient_type = 'Doctor'");
$total_count = 0;
if ($total_stmt) {
    $total_stmt->bind_param("i", $doctor_id);
    if ($total_stmt->execute()) {
        $total_result = $total_stmt->get_result()->fetch_assoc();
        $total_count = $total_result ? (int)$total_result['total'] : 0;
    }
    $total_stmt->close();
}

// Fetch notifications for this page
$notif_stmt = $conn->prepare("SELECT * FROM notifications 
                            WHERE recipient_id = ? 
                            AND recipient_type = 'Doctor'
                            ORDER BY created_at DESC 
                            LIMIT ? OFFSET ?");
if ($notif_stmt) {
    $notif_stmt->bind_param("iii", $doctor_id, $limit, $offset);
    if ($notif_stmt->execute()) {
        $notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $notif_stmt->close();
}

$total_pages = ceil($total_count / $limit);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_count' => $total_count,
        'limit' => $limit
    ]
]);
?>

