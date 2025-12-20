<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';

// Start output buffering for HTML output
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1, h2 {
            color: #333;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        pre {
            background-color: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .form-group {
            margin: 15px 0;
        }
        input[type="number"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        button {
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Database Connection Test</h1>

<?php
$conn = getDBConnection();
$allTestsPassed = true;

// Test 1: Database Connection
echo '<div class="container">';
echo '<h2>Test 1: Database Connection</h2>';
if ($conn->connect_error) {
    echo '<p class="error">❌ Connection failed: ' . htmlspecialchars($conn->connect_error) . '</p>';
    $allTestsPassed = false;
} else {
    echo '<p class="success">✅ Database connected successfully!</p>';
    echo '<p class="info">Host: ' . htmlspecialchars(DB_HOST) . '</p>';
    echo '<p class="info">Database: ' . htmlspecialchars(DB_NAME) . '</p>';
    echo '<p class="info">Charset: ' . htmlspecialchars($conn->character_set_name()) . '</p>';
}
echo '</div>';

if (!$allTestsPassed) {
    echo '</body></html>';
    ob_end_flush();
    exit;
}

// Test 2: Users Table Structure
echo '<div class="container">';
echo '<h2>Test 2: Users Table Structure</h2>';
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo '<table>';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($row['Field']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
        echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
        echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
        echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="error">❌ Error describing table: ' . htmlspecialchars($conn->error) . '</p>';
    $allTestsPassed = false;
}
echo '</div>';

// Test 3: Test Query (Same as profile.php)
echo '<div class="container">';
echo '<h2>Test 3: Profile Query Test</h2>';

// Get user_id from GET parameter or use session if available
$testUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if (isset($_SESSION['user_id']) && !$testUserId) {
    $testUserId = $_SESSION['user_id'];
    echo '<p class="info">ℹ️ Using session user_id: ' . $testUserId . '</p>';
}

if ($testUserId) {
    echo '<p class="info">Testing query for user ID: ' . $testUserId . '</p>';
    
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, address, role, specialization, profile_image, password FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $testUserId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            if ($user) {
                echo '<p class="success">✅ User found!</p>';
                echo '<table>';
                echo '<tr><th>Field</th><th>Value</th></tr>';
                foreach ($user as $key => $value) {
                    if ($key !== 'password') {
                        $displayValue = $value ?? 'NULL';
                        if (empty($displayValue) && $displayValue !== '0') {
                            $displayValue = '<em style="color: #999;">(empty)</em>';
                        }
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($displayValue) . '</td>';
                        echo '</tr>';
                    }
                }
                echo '</table>';
                
                // Test data normalization
                echo '<div class="test-section">';
                echo '<h3>Data Normalization Test</h3>';
                $normalized = [
                    'first_name' => !empty($user['first_name']) ? $user['first_name'] : '',
                    'last_name' => !empty($user['last_name']) ? $user['last_name'] : '',
                    'email' => !empty($user['email']) ? $user['email'] : '',
                    'phone' => !empty($user['phone']) ? $user['phone'] : '',
                    'address' => !empty($user['address']) ? $user['address'] : '',
                    'specialization' => !empty($user['specialization']) ? $user['specialization'] : '',
                ];
                echo '<table>';
                echo '<tr><th>Field</th><th>Original</th><th>Normalized</th></tr>';
                foreach ($normalized as $key => $normValue) {
                    $origValue = $user[$key] ?? 'NULL';
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($origValue ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($normValue ?: '(empty)') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';
            } else {
                echo '<p class="error">❌ User with ID ' . $testUserId . ' not found.</p>';
            }
        } else {
            echo '<p class="error">❌ Error executing query: ' . htmlspecialchars($stmt->error) . '</p>';
            $allTestsPassed = false;
        }
        $stmt->close();
    } else {
        echo '<p class="error">❌ Error preparing statement: ' . htmlspecialchars($conn->error) . '</p>';
        $allTestsPassed = false;
    }
} else {
    echo '<p class="info">ℹ️ No user ID provided. Enter a user ID below to test the query.</p>';
}

// Form to test with specific user ID
echo '<div class="test-section">';
echo '<h3>Test with Specific User ID</h3>';
echo '<form method="GET" action="">';
echo '<div class="form-group">';
echo '<label for="user_id">User ID: </label>';
echo '<input type="number" id="user_id" name="user_id" value="' . htmlspecialchars($testUserId ?? '') . '" min="1" required>';
echo '<button type="submit">Test Query</button>';
echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

// Test 4: Count Users
echo '<div class="container">';
echo '<h2>Test 4: User Statistics</h2>';
$result = $conn->query("SELECT COUNT(*) as total, role, COUNT(CASE WHEN phone = '' OR phone IS NULL THEN 1 END) as no_phone, COUNT(CASE WHEN address = '' OR address IS NULL THEN 1 END) as no_address FROM users GROUP BY role");
if ($result) {
    echo '<table>';
    echo '<tr><th>Role</th><th>Total Users</th><th>Missing Phone</th><th>Missing Address</th></tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($row['role']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($row['total']) . '</td>';
        echo '<td>' . htmlspecialchars($row['no_phone']) . '</td>';
        echo '<td>' . htmlspecialchars($row['no_address']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="error">❌ Error getting statistics: ' . htmlspecialchars($conn->error) . '</p>';
}
echo '</div>';

// Test 5: Sample Users
echo '<div class="container">';
echo '<h2>Test 5: Sample Users (First 5)</h2>';
$result = $conn->query("SELECT id, first_name, last_name, email, role, phone, address FROM users LIMIT 5");
if ($result) {
    echo '<table>';
    echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Address</th></tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
        echo '<td><strong>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
        echo '<td>' . htmlspecialchars($row['role']) . '</td>';
        echo '<td>' . htmlspecialchars($row['phone'] ?: '(empty)') . '</td>';
        echo '<td>' . htmlspecialchars(substr($row['address'] ?: '(empty)', 0, 50)) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="error">❌ Error getting sample users: ' . htmlspecialchars($conn->error) . '</p>';
}
echo '</div>';

// Summary
echo '<div class="container">';
echo '<h2>Test Summary</h2>';
if ($allTestsPassed) {
    echo '<p class="success">✅ All tests passed! Database connection is working correctly.</p>';
} else {
    echo '<p class="error">❌ Some tests failed. Please check the errors above.</p>';
}
echo '</div>';

$conn->close();
?>
</body>
</html>
<?php
ob_end_flush();
?>

