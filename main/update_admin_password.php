<?php
/**
 * Script to update admin password to 'juan123'
 * Run this once to set the admin password
 */

define('MHAVIS_EXEC', true);
require_once 'config/init.php';

$conn = getDBConnection();

// Generate password hash for 'juan123'
$new_password = 'juan123';
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

// Update admin user password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $hashed_password);

if ($stmt->execute()) {
    echo "✓ Successfully updated admin password to 'juan123'\n";
    echo "You can now login with:\n";
    echo "  Username: admin\n";
    echo "  Password: juan123\n";
} else {
    echo "✗ Error updating password: " . $stmt->error . "\n";
}

$stmt->close();
?>

