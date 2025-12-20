<?php
/**
 * Weekly Cleanup Script for Rejected Records
 * 
 * This script automatically deletes rejected appointment requests and 
 * rejected patient registration requests older than 7 days.
 * 
 * Should be scheduled to run weekly via Windows Task Scheduler / Linux Cron.
 * 
 * Usage:
 *   php cleanup_weekly_rejected.php
 * 
 * For Windows Task Scheduler (weekly on Sunday at 2 AM):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\mhavis\mhavis\cleanup_weekly_rejected.php
 * 
 * For Linux Cron (weekly on Sunday at 2 AM):
 *   0 2 * * 0 /usr/bin/php /path/to/mhavis/cleanup_weekly_rejected.php
 */

define('MHAVIS_EXEC', true);

// Load database configuration
require_once __DIR__ . '/config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Configuration
$RETENTION_DAYS = 7; // Delete rejected records older than 7 days
$LOG_FILE = __DIR__ . '/logs/cleanup_weekly_rejected.log';

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

try {
    logMessage("=== Starting Weekly Cleanup for Rejected Records ===");
    
    // Get database connection
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Calculate cutoff date (7 days ago)
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$RETENTION_DAYS} days"));
    logMessage("Cutoff date: Records older than $cutoff_date will be deleted");
    
    $total_deleted = 0;
    $results = [];
    
    // 1. Delete rejected appointment requests
    logMessage("--- Processing rejected appointment requests ---");
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
        logMessage("No rejected appointment requests to delete");
        $results['rejected_appointments'] = 0;
    }
    
    // 2. Delete rejected patient registration requests
    logMessage("--- Processing rejected patient registration requests ---");
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
        logMessage("No rejected patient registration requests to delete");
        $results['rejected_registrations'] = 0;
    }
    
    // Update last cleanup time in system_config
    $conn->query("CREATE TABLE IF NOT EXISTS system_config (
        config_key VARCHAR(100) PRIMARY KEY,
        config_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $update_stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) 
                                  VALUES ('last_weekly_cleanup', NOW()) 
                                  ON DUPLICATE KEY UPDATE config_value = NOW()");
    $update_stmt->execute();
    $update_stmt->close();
    
    logMessage("=== Weekly cleanup completed successfully ===");
    logMessage("Total records deleted: $total_deleted");
    logMessage("Summary: " . json_encode($results));
    
    // Return success for command line usage
    if (php_sapi_name() === 'cli') {
        exit(0);
    }
    
    return [
        'success' => true,
        'total_deleted' => $total_deleted,
        'cutoff_date' => $cutoff_date,
        'results' => $results
    ];
    
} catch (Exception $e) {
    $error_msg = "Error during weekly cleanup: " . $e->getMessage();
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

