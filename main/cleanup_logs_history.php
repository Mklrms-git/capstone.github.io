<?php
/**
 * Cleanup Logs History Script
 * 
 * This script handles all automatic cleanup tasks:
 * - Weekly cleanup: Rejected appointment requests and registration requests (every 7 days)
 * - Monthly cleanup: Approved records and activity logs (every 30 days)
 * 
 * The script automatically determines what needs to be cleaned based on:
 * - Last cleanup timestamps stored in system_config
 * - Retention periods (7 days for rejected, 30 days for approved/logs)
 * 
 * Should be scheduled to run daily via Windows Task Scheduler / Linux Cron.
 * 
 * Usage:
 *   php cleanup_logs_history.php
 * 
 * For Windows Task Scheduler (daily at 2:00 AM):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\mhavis\mhavis\cleanup_logs_history.php
 * 
 * For Linux Cron (daily at 2:00 AM):
 *   0 2 * * * /usr/bin/php /path/to/mhavis/mhavis/cleanup_logs_history.php
 */

define('MHAVIS_EXEC', true);

// Load database configuration
require_once __DIR__ . '/config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Configuration
$WEEKLY_RETENTION_DAYS = 7;   // Delete rejected records older than 7 days
$MONTHLY_RETENTION_DAYS = 30; // Delete approved records and logs older than 30 days
$LOG_FILE = __DIR__ . '/logs/cleanup_logs_history.log';

// Create logs directory if it doesn't exist
$log_dir = dirname($LOG_FILE);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

/**
 * Log a message to file and optionally to console
 */
function logMessage($message, $toConsole = true) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    
    // Write to log file
    file_put_contents($LOG_FILE, $logEntry, FILE_APPEND);
    
    // Output to console if running from command line
    if ($toConsole && php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

/**
 * Cleanup rejected appointment requests and registration requests (weekly)
 */
function cleanupWeeklyRejected($conn, $cutoff_date) {
    $total_deleted = 0;
    $results = [];
    
    logMessage("--- Starting Weekly Cleanup: Rejected Records ---");
    
    // 1. Delete rejected appointment requests
    logMessage("Processing rejected appointment requests...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'Rejected' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $rejected_appointments_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $rejected_appointments_count rejected appointment request(s) to delete");
    
    if ($rejected_appointments_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM appointment_requests WHERE status = 'Rejected' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            $total_deleted += $deleted_count;
            logMessage("Successfully deleted $deleted_count rejected appointment request(s)");
            $results['rejected_appointments'] = $deleted_count;
        } else {
            throw new Exception("Failed to delete rejected appointment requests: " . $delete_stmt->error);
        }
        $delete_stmt->close();
    } else {
        $results['rejected_appointments'] = 0;
    }
    
    // 2. Delete rejected patient registration requests
    logMessage("Processing rejected patient registration requests...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_registration_requests WHERE status = 'Rejected' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $rejected_registrations_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $rejected_registrations_count rejected patient registration request(s) to delete");
    
    if ($rejected_registrations_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM patient_registration_requests WHERE status = 'Rejected' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            $total_deleted += $deleted_count;
            logMessage("Successfully deleted $deleted_count rejected patient registration request(s)");
            $results['rejected_registrations'] = $deleted_count;
        } else {
            throw new Exception("Failed to delete rejected patient registration requests: " . $delete_stmt->error);
        }
        $delete_stmt->close();
    } else {
        $results['rejected_registrations'] = 0;
    }
    
    logMessage("--- Weekly Cleanup Complete: $total_deleted record(s) deleted ---");
    
    return [
        'total_deleted' => $total_deleted,
        'results' => $results
    ];
}

/**
 * Cleanup approved records and logs (monthly)
 */
function cleanupMonthlyApproved($conn, $cutoff_date) {
    $total_deleted = 0;
    $results = [];
    
    logMessage("--- Starting Monthly Cleanup: Approved Records & Logs ---");
    
    // 1. Delete approved appointment requests
    logMessage("Processing approved appointment requests...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'Approved' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $approved_appointments_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $approved_appointments_count approved appointment request(s) to delete");
    
    if ($approved_appointments_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM appointment_requests WHERE status = 'Approved' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            $total_deleted += $deleted_count;
            logMessage("Successfully deleted $deleted_count approved appointment request(s)");
            $results['approved_appointment_requests'] = $deleted_count;
        } else {
            throw new Exception("Failed to delete approved appointment requests: " . $delete_stmt->error);
        }
        $delete_stmt->close();
    } else {
        $results['approved_appointment_requests'] = 0;
    }
    
    // 2. Delete settled/cancelled appointments
    logMessage("Processing settled/cancelled appointments...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE status IN ('settled', 'cancelled') AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $completed_appointments_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $completed_appointments_count settled/cancelled appointment(s) to delete");
    
    if ($completed_appointments_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM appointments WHERE status IN ('settled', 'cancelled') AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            $total_deleted += $deleted_count;
            logMessage("Successfully deleted $deleted_count completed/cancelled appointment(s)");
            $results['completed_appointments'] = $deleted_count;
        } else {
            throw new Exception("Failed to delete completed appointments: " . $delete_stmt->error);
        }
        $delete_stmt->close();
    } else {
        $results['completed_appointments'] = 0;
    }
    
    // 3. Delete approved patient registration requests
    logMessage("Processing approved patient registration requests...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_registration_requests WHERE status = 'Approved' AND updated_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $approved_registrations_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $approved_registrations_count approved patient registration request(s) to delete");
    
    if ($approved_registrations_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM patient_registration_requests WHERE status = 'Approved' AND updated_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            $total_deleted += $deleted_count;
            logMessage("Successfully deleted $deleted_count approved patient registration request(s)");
            $results['approved_registrations'] = $deleted_count;
        } else {
            throw new Exception("Failed to delete approved patient registration requests: " . $delete_stmt->error);
        }
        $delete_stmt->close();
    } else {
        $results['approved_registrations'] = 0;
    }
    
    // 4. Delete old activity logs
    logMessage("Processing activity logs...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE created_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $activity_logs_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $activity_logs_count activity log(s) to delete");
    
    if ($activity_logs_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            $total_deleted += $deleted_count;
            logMessage("Successfully deleted $deleted_count activity log(s)");
            $results['activity_logs'] = $deleted_count;
        } else {
            throw new Exception("Failed to delete activity logs: " . $delete_stmt->error);
        }
        $delete_stmt->close();
    } else {
        $results['activity_logs'] = 0;
    }
    
    // 5. Delete old notifications (read notifications older than retention period)
    logMessage("Processing old notifications...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 1 AND created_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $notifications_count = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $notifications_count read notification(s) to delete");
    
    if ($notifications_count > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            $total_deleted += $deleted_count;
            logMessage("Successfully deleted $deleted_count read notification(s)");
            $results['notifications'] = $deleted_count;
        } else {
            logMessage("Warning: Failed to delete notifications: " . $delete_stmt->error);
            // Don't throw exception for notifications as the table might not exist
            $results['notifications'] = 0;
        }
        $delete_stmt->close();
    } else {
        $results['notifications'] = 0;
    }
    
    logMessage("--- Monthly Cleanup Complete: $total_deleted record(s) deleted ---");
    
    return [
        'total_deleted' => $total_deleted,
        'results' => $results
    ];
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
    $conn->query("CREATE TABLE IF NOT EXISTS system_config (
        config_key VARCHAR(100) PRIMARY KEY,
        config_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $update_stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) 
                                  VALUES (?, NOW()) 
                                  ON DUPLICATE KEY UPDATE config_value = NOW()");
    $update_stmt->bind_param("s", $config_key);
    $update_stmt->execute();
    $update_stmt->close();
}

try {
    logMessage("=== Starting Cleanup Logs History Process ===");
    
    // Get database connection
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Ensure system_config table exists
    $conn->query("CREATE TABLE IF NOT EXISTS system_config (
        config_key VARCHAR(100) PRIMARY KEY,
        config_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $total_weekly_deleted = 0;
    $total_monthly_deleted = 0;
    $weekly_results = [];
    $monthly_results = [];
    $weekly_ran = false;
    $monthly_ran = false;
    
    // ========== WEEKLY CLEANUP: Rejected Records ==========
    logMessage("--- Checking Weekly Cleanup: Rejected Records ---");
    
    // Calculate cutoff date for rejected records (7 days ago)
    $weekly_cutoff_date = date('Y-m-d H:i:s', strtotime("-{$WEEKLY_RETENTION_DAYS} days"));
    logMessage("Weekly cutoff date: Records older than $weekly_cutoff_date");
    
    // Get last weekly cleanup time
    $last_weekly_cleanup = getLastCleanupTime($conn, 'last_weekly_cleanup');
    
    // Determine if weekly cleanup should run
    // Run if never run before, or if last run was more than 6 days ago (to ensure weekly execution)
    $should_run_weekly = false;
    
    if ($last_weekly_cleanup === null) {
        $should_run_weekly = true;
        logMessage("Weekly cleanup: Never run before, will run now");
    } else {
        $last_weekly_time = strtotime($last_weekly_cleanup);
        $days_since_last_weekly = (time() - $last_weekly_time) / 86400; // Convert to days
        
        if ($days_since_last_weekly >= 6) {
            $should_run_weekly = true;
            logMessage("Weekly cleanup: Last run was " . round($days_since_last_weekly, 1) . " days ago, will run now");
        } else {
            logMessage("Weekly cleanup: Last run was " . round($days_since_last_weekly, 1) . " days ago, skipping (runs weekly)");
        }
    }
    
    if ($should_run_weekly) {
        try {
            $weekly_result = cleanupWeeklyRejected($conn, $weekly_cutoff_date);
            $total_weekly_deleted = $weekly_result['total_deleted'];
            $weekly_results = $weekly_result['results'];
            $weekly_ran = true;
            updateLastCleanupTime($conn, 'last_weekly_cleanup');
            logMessage("Weekly cleanup completed successfully");
        } catch (Exception $e) {
            logMessage("Error during weekly cleanup: " . $e->getMessage());
            // Continue with monthly cleanup even if weekly fails
        }
    }
    
    // ========== MONTHLY CLEANUP: Approved Records & Logs ==========
    logMessage("--- Checking Monthly Cleanup: Approved Records & Logs ---");
    
    // Calculate cutoff date for approved records and logs (30 days ago)
    $monthly_cutoff_date = date('Y-m-d H:i:s', strtotime("-{$MONTHLY_RETENTION_DAYS} days"));
    logMessage("Monthly cutoff date: Records older than $monthly_cutoff_date");
    
    // Get last monthly cleanup time
    $last_monthly_cleanup = getLastCleanupTime($conn, 'last_monthly_cleanup');
    
    // Determine if monthly cleanup should run
    // Run if never run before, or if last run was more than 28 days ago (to ensure monthly execution)
    $should_run_monthly = false;
    
    if ($last_monthly_cleanup === null) {
        $should_run_monthly = true;
        logMessage("Monthly cleanup: Never run before, will run now");
    } else {
        $last_monthly_time = strtotime($last_monthly_cleanup);
        $days_since_last_monthly = (time() - $last_monthly_time) / 86400; // Convert to days
        
        if ($days_since_last_monthly >= 28) {
            $should_run_monthly = true;
            logMessage("Monthly cleanup: Last run was " . round($days_since_last_monthly, 1) . " days ago, will run now");
        } else {
            logMessage("Monthly cleanup: Last run was " . round($days_since_last_monthly, 1) . " days ago, skipping (runs monthly)");
        }
    }
    
    if ($should_run_monthly) {
        try {
            $monthly_result = cleanupMonthlyApproved($conn, $monthly_cutoff_date);
            $total_monthly_deleted = $monthly_result['total_deleted'];
            $monthly_results = $monthly_result['results'];
            $monthly_ran = true;
            updateLastCleanupTime($conn, 'last_monthly_cleanup');
            logMessage("Monthly cleanup completed successfully");
        } catch (Exception $e) {
            logMessage("Error during monthly cleanup: " . $e->getMessage());
        }
    }
    
    // ========== SUMMARY ==========
    logMessage("=== Cleanup Process Summary ===");
    logMessage("Weekly cleanup executed: " . ($weekly_ran ? "Yes" : "No") . " (" . $total_weekly_deleted . " record(s) deleted)");
    logMessage("Monthly cleanup executed: " . ($monthly_ran ? "Yes" : "No") . " (" . $total_monthly_deleted . " record(s) deleted)");
    logMessage("Total records deleted: " . ($total_weekly_deleted + $total_monthly_deleted));
    
    if (!empty($weekly_results)) {
        logMessage("Weekly cleanup breakdown: " . json_encode($weekly_results));
    }
    
    if (!empty($monthly_results)) {
        logMessage("Monthly cleanup breakdown: " . json_encode($monthly_results));
    }
    
    if (!$weekly_ran && !$monthly_ran) {
        logMessage("No cleanup tasks were due at this time");
    }
    
    logMessage("=== Cleanup Logs History Process Completed ===");
    
    // Return success for command line usage
    if (php_sapi_name() === 'cli') {
        exit(0);
    }
    
    return [
        'success' => true,
        'weekly_ran' => $weekly_ran,
        'monthly_ran' => $monthly_ran,
        'weekly_deleted' => $total_weekly_deleted,
        'monthly_deleted' => $total_monthly_deleted,
        'total_deleted' => $total_weekly_deleted + $total_monthly_deleted,
        'weekly_results' => $weekly_results,
        'monthly_results' => $monthly_results
    ];
    
} catch (Exception $e) {
    $error_msg = "Error during cleanup logs history: " . $e->getMessage();
    logMessage($error_msg);
    
    // Return error for command line usage
    if (php_sapi_name() === 'cli') {
        echo "ERROR: $error_msg\n";
        exit(1);
    }
    
    return [
        'success' => false,
        'error' => $error_msg
    ];
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

