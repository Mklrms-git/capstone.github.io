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

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit();
}

$conn = getDBConnection();

// Start transaction
$conn->begin_transaction();

try {
    // Delete transaction items first (if they exist)
    $checkTransactionItems = $conn->query("SHOW TABLES LIKE 'transaction_items'");
    if ($checkTransactionItems->num_rows > 0) {
        $deleteItems = $conn->prepare("DELETE FROM transaction_items WHERE transaction_id = ?");
        $deleteItems->bind_param("i", $transaction_id);
        $deleteItems->execute();
        $deleteItems->close();
    }
    
    // Delete the main transaction
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $transaction_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error deleting transaction");
    }
    
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>

