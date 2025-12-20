<?php
/**
 * Automatic Cleanup Helper
 * 
 * This file contains functions that can be called from admin pages
 * to automatically run cleanup tasks when admins access the pages.
 * 
 * The cleanup runs silently in the background and only executes
 * when enough time has passed since the last cleanup.
 */

if (!defined('MHAVIS_EXEC')) {
    define('MHAVIS_EXEC', true);
}

require_once __DIR__ . '/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Configuration
define('WEEKLY_RETENTION_DAYS', 7);   // Delete rejected records older than 7 days
define('MONTHLY_RETENTION_DAYS', 30); // Delete approved records and logs older than 30 days

/**
 * Run automatic cleanup for logs history
 * This function can be called from admin pages to trigger cleanup automatically
 * 
 * @param mysqli $conn Database connection
 * @param bool $silent If true, errors won't throw exceptions (for page integration)
 * @return array Result array with status and deleted counts
 */
function runAutomaticCleanup($conn = null, $silent = true) {
    // Use provided connection or get new one
    if ($conn === null) {
        $conn = getDBConnection();
        $close_connection = true;
    } else {
        $close_connection = false;
    }
    
    if (!$conn) {
        if ($silent) {
            error_log("Cleanup failed: Database connection failed");
            return ['success' => false, 'error' => 'Database connection failed'];
        }
        throw new Exception("Database connection failed");
    }
    
    try {
        // Ensure system_config table exists
        $conn->query("CREATE TABLE IF NOT EXISTS system_config (
            config_key VARCHAR(100) PRIMARY KEY,
            config_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $total_weekly_deleted = 0;
        $total_monthly_deleted = 0;
        $weekly_ran = false;
        $monthly_ran = false;
        
        // ========== WEEKLY CLEANUP: Rejected Records ==========
        $weekly_cutoff_date = date('Y-m-d H:i:s', strtotime("-" . WEEKLY_RETENTION_DAYS . " days"));
        $last_weekly_cleanup = getLastCleanupTime($conn, 'last_weekly_cleanup');
        
        $should_run_weekly = false;
        if ($last_weekly_cleanup === null) {
            $should_run_weekly = true;
        } else {
            $last_weekly_time = strtotime($last_weekly_cleanup);
            $days_since_last_weekly = (time() - $last_weekly_time) / 86400;
            if ($days_since_last_weekly >= 6) {
                $should_run_weekly = true;
            }
        }
        
        if ($should_run_weekly) {
            $weekly_result = cleanupWeeklyRejected($conn, $weekly_cutoff_date);
            $total_weekly_deleted = $weekly_result['total_deleted'];
            $weekly_ran = true;
            updateLastCleanupTime($conn, 'last_weekly_cleanup');
        }
        
        // ========== MONTHLY CLEANUP: Approved Records & Logs ==========
        $monthly_cutoff_date = date('Y-m-d H:i:s', strtotime("-" . MONTHLY_RETENTION_DAYS . " days"));
        $last_monthly_cleanup = getLastCleanupTime($conn, 'last_monthly_cleanup');
        
        $should_run_monthly = false;
        if ($last_monthly_cleanup === null) {
            $should_run_monthly = true;
        } else {
            $last_monthly_time = strtotime($last_monthly_cleanup);
            $days_since_last_monthly = (time() - $last_monthly_time) / 86400;
            if ($days_since_last_monthly >= 28) {
                $should_run_monthly = true;
            }
        }
        
        if ($should_run_monthly) {
            $monthly_result = cleanupMonthlyApproved($conn, $monthly_cutoff_date);
            $total_monthly_deleted = $monthly_result['total_deleted'];
            $monthly_ran = true;
            updateLastCleanupTime($conn, 'last_monthly_cleanup');
        }
        
        $result = [
            'success' => true,
            'weekly_ran' => $weekly_ran,
            'monthly_ran' => $monthly_ran,
            'weekly_deleted' => $total_weekly_deleted,
            'monthly_deleted' => $total_monthly_deleted,
            'total_deleted' => $total_weekly_deleted + $total_monthly_deleted
        ];
        
        // Log silently if cleanup ran
        if ($weekly_ran || $monthly_ran) {
            error_log("Automatic cleanup executed: Weekly=" . ($weekly_ran ? "Yes" : "No") . 
                     " (" . $total_weekly_deleted . " records), Monthly=" . ($monthly_ran ? "Yes" : "No") . 
                     " (" . $total_monthly_deleted . " records)");
        }
        
        return $result;
        
    } catch (Exception $e) {
        $error_msg = "Error during automatic cleanup: " . $e->getMessage();
        error_log($error_msg);
        
        if ($silent) {
            return ['success' => false, 'error' => $error_msg];
        }
        
        throw $e;
    } finally {
        if ($close_connection && isset($conn)) {
            $conn->close();
        }
    }
}

/**
 * Cleanup rejected appointment requests and registration requests (weekly)
 */
function cleanupWeeklyRejected($conn, $cutoff_date) {
    $total_deleted = 0;
    
    // 1. Delete rejected appointment requests
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'Rejected' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $rejected_appointments_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    if ($rejected_appointments_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM appointment_requests WHERE status = 'Rejected' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        if ($delete_stmt->execute()) {
            $total_deleted += $delete_stmt->affected_rows;
        }
        $delete_stmt->close();
    }
    
    // 2. Delete rejected patient registration requests
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_registration_requests WHERE status = 'Rejected' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $rejected_registrations_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    if ($rejected_registrations_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM patient_registration_requests WHERE status = 'Rejected' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        if ($delete_stmt->execute()) {
            $total_deleted += $delete_stmt->affected_rows;
        }
        $delete_stmt->close();
    }
    
    return ['total_deleted' => $total_deleted];
}

/**
 * Cleanup approved records and logs (monthly)
 */
function cleanupMonthlyApproved($conn, $cutoff_date) {
    $total_deleted = 0;
    
    // 1. Delete approved appointment requests
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'Approved' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    if (($count_row['count'] ?? 0) > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM appointment_requests WHERE status = 'Approved' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        if ($delete_stmt->execute()) {
            $total_deleted += $delete_stmt->affected_rows;
        }
        $delete_stmt->close();
    }
    $count_stmt->close();
    
    // 2. Delete settled/cancelled appointments
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE status IN ('settled', 'cancelled') AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    if (($count_row['count'] ?? 0) > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM appointments WHERE status IN ('settled', 'cancelled') AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        if ($delete_stmt->execute()) {
            $total_deleted += $delete_stmt->affected_rows;
        }
        $delete_stmt->close();
    }
    $count_stmt->close();
    
    // 3. Delete approved patient registration requests
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_registration_requests WHERE status = 'Approved' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    if (($count_row['count'] ?? 0) > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM patient_registration_requests WHERE status = 'Approved' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        if ($delete_stmt->execute()) {
            $total_deleted += $delete_stmt->affected_rows;
        }
        $delete_stmt->close();
    }
    $count_stmt->close();
    
    // 4. Delete old activity logs
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE created_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    if (($count_row['count'] ?? 0) > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        if ($delete_stmt->execute()) {
            $total_deleted += $delete_stmt->affected_rows;
        }
        $delete_stmt->close();
    }
    $count_stmt->close();
    
    // 5. Delete old notifications (read notifications)
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 1 AND created_at < ?");
    if ($count_stmt) {
        $count_stmt->bind_param("s", $cutoff_date);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        if (($count_row['count'] ?? 0) > 0) {
            $delete_stmt = $conn->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < ?");
            $delete_stmt->bind_param("s", $cutoff_date);
            if ($delete_stmt->execute()) {
                $total_deleted += $delete_stmt->affected_rows;
            }
            $delete_stmt->close();
        }
        $count_stmt->close();
    }
    
    return ['total_deleted' => $total_deleted];
}

/**
 * Get last cleanup time from system_config
 */
function getLastCleanupTime($conn, $config_key) {
    $stmt = $conn->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("s", $config_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['config_value'];
    }
    
    $stmt->close();
    return null;
}

/**
 * Update last cleanup time in system_config
 */
function updateLastCleanupTime($conn, $config_key) {
    $update_stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) 
                                  VALUES (?, NOW()) 
                                  ON DUPLICATE KEY UPDATE config_value = NOW()");
    $update_stmt->bind_param("s", $config_key);
    $update_stmt->execute();
    $update_stmt->close();
}

