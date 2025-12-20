<?php
/**
 * PHPMailer Autoloader - Fixed Version
 * Loads PHPMailer classes with proper error handling
 */

// Method 1: Try autoloader
spl_autoload_register(function ($class) {
    // Base directory for PHPMailer namespace
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/src/';
    
    // Check if the class uses the PHPMailer namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separator with directory separator
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// Method 2: Manual loading as fallback
$phpmailer_src = __DIR__ . '/src/';

// Load core classes manually to ensure they're available
$core_classes = [
    'Exception.php',
    'PHPMailer.php',
    'SMTP.php'
];

foreach ($core_classes as $class_file) {
    $file_path = $phpmailer_src . $class_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        error_log("PHPMailer: Missing file - $file_path");
    }
}

// Verify PHPMailer is loaded
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    error_log("PHPMailer: Failed to load PHPMailer class");
    // Log the actual path being checked
    error_log("PHPMailer: Looking in directory - " . $phpmailer_src);
}
