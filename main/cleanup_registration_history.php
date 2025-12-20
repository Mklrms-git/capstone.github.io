<?php
/**
 * Cleanup Script for Patient Registration History
 * 
 * This script automatically deletes patient registration requests older than 7 days.
 * Can be run manually or scheduled via Windows Task Scheduler / Linux Cron.
 * 
 * Usage:
 *   php cleanup_registration_history.php
 * 
 * For Windows Task Scheduler:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\mhavis\mhavis\cleanup_registration_history.php
 * 
 * For Linux Cron (weekly on Sunday at 2 AM):
 *   0 2 * * 0 /usr/bin/php /path/to/mhavis/cleanup_registration_history.php
 */

define('MHAVIS_EXEC', true);

// Load database configuration
require_once __DIR__ . '/config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Configuration
$RETENTION_DAYS = 7; // Delete records older than 7 days
$LOG_FILE = __DIR__ . '/logs/cleanup_registration_history.log';

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
    logMessage("=== Starting Patient Registration History Cleanup ===");
    
    // Get database connection
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Calculate cutoff date (7 days ago)
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$RETENTION_DAYS} days"));
    logMessage("Cutoff date: Records older than $cutoff_date will be deleted");
    
    // First, count how many records will be deleted
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM patient_registration_requests WHERE created_at < ?");
    $count_stmt->bind_param("s", $cutoff_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $records_to_delete = $count_row['count'] ?? 0;
    $count_stmt->close();
    
    logMessage("Found $records_to_delete record(s) to delete");
    
    if ($records_to_delete > 0) {
        // Get breakdown by status before deletion
        $breakdown_stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM patient_registration_requests WHERE created_at < ? GROUP BY status");
        $breakdown_stmt->bind_param("s", $cutoff_date);
        $breakdown_stmt->execute();
        $breakdown_result = $breakdown_stmt->get_result();
        
        $status_breakdown = [];
        while ($row = $breakdown_result->fetch_assoc()) {
            $status_breakdown[$row['status']] = $row['count'];
        }
        $breakdown_stmt->close();
        
        logMessage("Breakdown by status: " . json_encode($status_breakdown));
        
        // Delete records older than 7 days
        $delete_stmt = $conn->prepare("DELETE FROM patient_registration_requests WHERE created_at < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        
        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;
            logMessage("Successfully deleted $deleted_count record(s)");
            
            // Update last cleanup time in a config table (if it exists)
            // Create a simple config table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS system_config (
                config_key VARCHAR(100) PRIMARY KEY,
                config_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $update_stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) 
                                          VALUES ('last_registration_cleanup', NOW()) 
                                          ON DUPLICATE KEY UPDATE config_value = NOW()");
            $update_stmt->execute();
            $update_stmt->close();
            
            logMessage("=== Cleanup completed successfully ===");
            
            // Return success for command line usage
            if (php_sapi_name() === 'cli') {
                exit(0);
            }
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'cutoff_date' => $cutoff_date,
                'status_breakdown' => $status_breakdown
            ];
        } else {
            throw new Exception("Failed to delete records: " . $delete_stmt->error);
        }
        
        $delete_stmt->close();
    } else {
        logMessage("No records to delete");
        logMessage("=== Cleanup completed (no action needed) ===");
        
        if (php_sapi_name() === 'cli') {
            exit(0);
        }
        
        return [
            'success' => true,
            'deleted_count' => 0,
            'message' => 'No records to delete'
        ];
    }
    
} catch (Exception $e) {
    $error_msg = "Error during cleanup: " . $e->getMessage();
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

