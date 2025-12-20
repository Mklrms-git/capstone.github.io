<?php
// Prevent direct access
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mhavis');

// Database connection function
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

// Close database connection on script end
register_shutdown_function(function() {
    $conn = getDBConnection();
    if ($conn) {
        $conn->close();
    }
}); 