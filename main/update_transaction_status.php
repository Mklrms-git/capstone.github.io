<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? (int)$input['id'] : 0;
$new_status = isset($input['status']) ? sanitize($input['status']) : '';

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit();
}

// Validate status
$valid_statuses = ['Completed', 'Pending', 'Refunded'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status. Allowed values: ' . implode(', ', $valid_statuses)]);
    exit();
}

$conn = getDBConnection();

// Update transaction status
$stmt = $conn->prepare("UPDATE transactions SET payment_status = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("si", $new_status, $transaction_id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Transaction status updated successfully']);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Error updating transaction status']);
}

$conn->close();
?>

