<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireDoctor();

header('Content-Type: application/json');

$conn = getDBConnection();
$doctorId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // Check if doctor_leaves table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'doctor_leaves'");
    if ($table_check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Database table not found. Please run the SQL migration file: sql/create_doctor_leaves_table.sql']);
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
        // Add new leave
        $leave_type = $_POST['leave_type'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reason = $_POST['reason'] ?? '';
        
        // Validation
        if (empty($leave_type) || empty($start_date) || empty($end_date)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            exit();
        }
        
        $valid_leave_types = ['Annual', 'Sick', 'Maternity', 'Paternity', 'Parental Leave', 'Emergency Leave', 'Bereavement Leave'];
        if (!in_array($leave_type, $valid_leave_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid leave type']);
            exit();
        }
        
        // Validate dates
        $start = DateTime::createFromFormat('Y-m-d', $start_date);
        $end = DateTime::createFromFormat('Y-m-d', $end_date);
        
        if (!$start || !$end) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit();
        }
        
        if ($start > $end) {
            echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
            exit();
        }
        
        // Check for overlapping leaves
        $stmt = $conn->prepare("SELECT id FROM doctor_leaves 
                               WHERE doctor_id = ? AND status = 'Active' 
                               AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?) OR (start_date >= ? AND end_date <= ?))");
        $stmt->bind_param("issssss", $doctorId, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have an active leave during this period']);
            exit();
        }
        
        // Insert leave
        $stmt = $conn->prepare("INSERT INTO doctor_leaves (doctor_id, leave_type, start_date, end_date, reason, status) 
                               VALUES (?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("issss", $doctorId, $leave_type, $start_date, $end_date, $reason);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding leave: ' . $conn->error]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
        // Delete/Cancel leave
        $leave_id = $_POST['leave_id'] ?? 0;
        
        if (!$leave_id || !is_numeric($leave_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid leave ID']);
            exit();
        }
        
        // Verify ownership
        $stmt = $conn->prepare("SELECT id FROM doctor_leaves WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $leave_id, $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Leave not found or unauthorized']);
            exit();
        }
        
        // Cancel the leave (soft delete by setting status to Cancelled)
        $stmt = $conn->prepare("UPDATE doctor_leaves SET status = 'Cancelled' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $leave_id, $doctorId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave cancelled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error cancelling leave: ' . $conn->error]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'permanent_delete') {
        // Permanently delete leave
        $leave_id = $_POST['leave_id'] ?? 0;
        
        if (!$leave_id || !is_numeric($leave_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid leave ID']);
            exit();
        }
        
        // Verify ownership
        $stmt = $conn->prepare("SELECT id FROM doctor_leaves WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $leave_id, $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Leave not found or unauthorized']);
            exit();
        }
        
        // Permanently delete the leave
        $stmt = $conn->prepare("DELETE FROM doctor_leaves WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $leave_id, $doctorId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting leave: ' . $conn->error]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        // Get all leaves for the doctor
        $stmt = $conn->prepare("SELECT * FROM doctor_leaves 
                               WHERE doctor_id = ? 
                               ORDER BY start_date DESC, created_at DESC");
        $stmt->bind_param("i", $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = [
                'id' => $row['id'],
                'leave_type' => $row['leave_type'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'reason' => $row['reason'],
                'status' => $row['status'],
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode(['success' => true, 'leaves' => $leaves]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('Error in manage_doctor_leaves.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log('Fatal error in manage_doctor_leaves.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
}

