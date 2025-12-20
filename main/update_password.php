<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';

$conn = getDBConnection();
$username = 'admin';
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hashedPassword, $username);

if ($stmt->execute()) {
    echo "Password updated successfully!";
} else {
    echo "Error updating password: " . $conn->error;
} 