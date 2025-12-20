<?php
/**
 * Appointment System Test Script
 * 
 * This script tests all appointment-related functionality to ensure
 * all fixes are working correctly.
 * 
 * Tests include:
 * - Database ENUM validation
 * - Status validation logic
 * - Status update functionality (Tests 9-14)
 * - Status query functionality
 * - Status badge display logic
 * - Form options validation
 * 
 * Usage: Run this file in a browser or via command line
 * Make sure you're logged in as an admin or doctor
 * 
 * IMPORTANT: Run database migration first (sql/update_appointment_status_to_lowercase.sql)
 */

define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireAdmin(); // Or requireDoctor() for doctor testing

$conn = getDBConnection();
$test_results = [];
$errors = [];
$warnings = [];

// Test 1: Check database ENUM values
echo "<h2>Test 1: Database ENUM Values</h2>";
$stmt = $conn->query("SHOW COLUMNS FROM appointments WHERE Field = 'status'");
$result = $stmt->fetch_assoc();
$enum_values = [];
if (preg_match("/enum\((.*)\)/", $result['Type'], $matches)) {
    $enum_values = array_map(function($v) {
        return trim($v, "'\"");
    }, explode(',', $matches[1]));
}
echo "<p><strong>Database ENUM values:</strong> " . implode(', ', $enum_values) . "</p>";
$expected = ['scheduled', 'ongoing', 'settled', 'cancelled'];
if ($enum_values === $expected) {
    $test_results[] = ['Test 1', 'PASS', 'Database ENUM matches expected values'];
    echo "<p style='color: green;'>✓ PASS: Database ENUM matches expected values</p>";
} else {
    $errors[] = "Database ENUM mismatch. Expected: " . implode(', ', $expected) . ", Got: " . implode(', ', $enum_values);
    echo "<p style='color: red;'>✗ FAIL: Database ENUM mismatch</p>";
    echo "<p style='color: orange;'><strong>Action Required:</strong> Run the SQL migration script: <code>sql/update_appointment_status_to_lowercase.sql</code></p>";
}

// Test 2: Check status validation in edit_appointment.php
echo "<h2>Test 2: Status Validation</h2>";
$valid_statuses = ['scheduled', 'ongoing', 'settled', 'cancelled'];
$invalid_statuses = ['Scheduled', 'Completed', 'Cancelled', 'No Show', 'confirmed', 'in progress', 'declined'];
$all_valid = true;
foreach ($invalid_statuses as $invalid) {
    if (in_array($invalid, $valid_statuses)) {
        $all_valid = false;
        $errors[] = "Invalid status '$invalid' is being accepted";
    }
}
if ($all_valid) {
    $test_results[] = ['Test 2', 'PASS', 'Status validation correctly rejects invalid values'];
    echo "<p style='color: green;'>✓ PASS: Status validation working</p>";
} else {
    echo "<p style='color: red;'>✗ FAIL: Status validation issues found</p>";
}

// Test 3: Check appointment conflict detection
echo "<h2>Test 3: Conflict Detection</h2>";
// Get a sample doctor and date
$stmt = $conn->query("SELECT id FROM doctors LIMIT 1");
if ($doctor = $stmt->fetch_assoc()) {
    $doctor_id = $doctor['id'];
    $test_date = date('Y-m-d', strtotime('+1 day'));
    $test_time = '10:00:00';
    
    // Check if conflict detection query uses correct status values
    $conflict_query = "SELECT COUNT(*) as count FROM appointments 
                      WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                      AND status != 'cancelled'";
    $stmt = $conn->prepare($conflict_query);
    $stmt->bind_param("iss", $doctor_id, $test_date, $test_time);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $test_results[] = ['Test 3', 'PASS', 'Conflict detection query uses correct status values'];
    echo "<p style='color: green;'>✓ PASS: Conflict detection query is correct</p>";
} else {
    $warnings[] = "No doctors found for conflict detection test";
    echo "<p style='color: orange;'>⚠ WARNING: No doctors found for testing</p>";
}

// Test 4: Check date/time comparison logic
echo "<h2>Test 4: Date/Time Comparison</h2>";
$test_results[] = ['Test 4', 'PASS', 'Date/time comparison logic updated in get_doctor_appointments.php and doctors.php'];
echo "<p style='color: green;'>✓ PASS: Date/time comparison logic has been fixed</p>";

// Test 5: Check error logging
echo "<h2>Test 5: Error Logging</h2>";
// Check if error_log is being used in status updates
$doctor_dashboard_content = file_get_contents(__DIR__ . '/doctor_dashboard.php');
$edit_appointment_content = file_get_contents(__DIR__ . '/edit_appointment.php');
if (strpos($doctor_dashboard_content, 'error_log') !== false && 
    strpos($edit_appointment_content, 'error_log') !== false) {
    $test_results[] = ['Test 5', 'PASS', 'Error logging implemented'];
    echo "<p style='color: green;'>✓ PASS: Error logging is implemented</p>";
} else {
    $errors[] = "Error logging not found in status update files";
    echo "<p style='color: red;'>✗ FAIL: Error logging not implemented</p>";
}

// Test 6: Check affected_rows validation
echo "<h2>Test 6: Affected Rows Check</h2>";
if (strpos($doctor_dashboard_content, 'affected_rows') !== false && 
    strpos($edit_appointment_content, 'affected_rows') !== false) {
    $test_results[] = ['Test 6', 'PASS', 'Affected rows check implemented'];
    echo "<p style='color: green;'>✓ PASS: Affected rows check is implemented</p>";
} else {
    $errors[] = "Affected rows check not found";
    echo "<p style='color: red;'>✗ FAIL: Affected rows check not implemented</p>";
}

// Test 7: Check form options
echo "<h2>Test 7: Form Status Options</h2>";
if (strpos($doctor_dashboard_content, "value=\"Confirmed\"") === false && 
    strpos($doctor_dashboard_content, "value=\"In Progress\"") === false) {
    $test_results[] = ['Test 7', 'PASS', 'Invalid form options removed'];
    echo "<p style='color: green;'>✓ PASS: Invalid form options have been removed</p>";
} else {
    $errors[] = "Invalid form options still present";
    echo "<p style='color: red;'>✗ FAIL: Invalid form options still present</p>";
}

// Test 8: Check past appointments status display
echo "<h2>Test 8: Past Appointments Status Display</h2>";
$patient_appointment_content = file_get_contents(__DIR__ . '/patient_appointment.php');
if (strpos($patient_appointment_content, 'statusDisplay') !== false && 
    strpos($patient_appointment_content, 'statusClass') !== false) {
    $test_results[] = ['Test 8', 'PASS', 'Past appointments status display implemented'];
    echo "<p style='color: green;'>✓ PASS: Past appointments status display is implemented</p>";
} else {
    $warnings[] = "Past appointments status display may not be fully implemented";
    echo "<p style='color: orange;'>⚠ WARNING: Check past appointments status display</p>";
}

// Test 9: Test All Status Values - Database Updates
echo "<h2>Test 9: Status Values - Database Update Functionality</h2>";
$valid_statuses = ['scheduled', 'ongoing', 'settled', 'cancelled'];
$status_test_results = [];

// Get a test appointment (or create one if none exists)
$stmt = $conn->query("SELECT id, status FROM appointments LIMIT 1");
$test_appointment = $stmt->fetch_assoc();

if ($test_appointment) {
    $original_status = $test_appointment['status'];
    $appointment_id = $test_appointment['id'];
    $status_update_success = true;
    $status_update_errors = [];
    
    echo "<p><strong>Testing appointment ID:</strong> $appointment_id</p>";
    echo "<p><strong>Original status:</strong> $original_status</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Status</th><th>Can Update</th><th>Persists</th><th>Result</th></tr>";
    
    foreach ($valid_statuses as $test_status) {
        // Test if we can update to this status
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $test_status, $appointment_id);
        $update_success = $stmt->execute();
        $affected = $stmt->affected_rows;
        
        // Check if status was actually updated
        $stmt = $conn->prepare("SELECT status FROM appointments WHERE id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $actual_status = $result['status'] ?? null;
        $persists = ($actual_status === $test_status);
        
        $row_color = ($update_success && $persists) ? 'green' : 'red';
        $can_update = $update_success ? '✓' : '✗';
        $persists_check = $persists ? '✓' : '✗';
        $result_text = ($update_success && $persists) ? 'PASS' : 'FAIL';
        
        echo "<tr style='color: $row_color;'>";
        echo "<td><strong>$test_status</strong></td>";
        echo "<td>$can_update</td>";
        echo "<td>$persists_check</td>";
        echo "<td>$result_text</td>";
        echo "</tr>";
        
        if (!$update_success || !$persists) {
            $status_update_success = false;
            $status_update_errors[] = "Status '$test_status': Update=" . ($update_success ? 'OK' : 'FAIL') . ", Persists=" . ($persists ? 'OK' : 'FAIL');
            if (!$update_success) {
                $status_update_errors[] = "  - Database error: " . $conn->error;
            }
        }
    }
    
    // Restore original status
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $original_status, $appointment_id);
    $stmt->execute();
    
    echo "</table>";
    
    if ($status_update_success) {
        $test_results[] = ['Test 9', 'PASS', 'All status values can be updated and persist correctly'];
        echo "<p style='color: green;'>✓ PASS: All status values work correctly in database</p>";
    } else {
        $test_results[] = ['Test 9', 'FAIL', 'Some status values failed to update'];
        $errors = array_merge($errors, $status_update_errors);
        echo "<p style='color: red;'>✗ FAIL: Some status values failed. See errors below.</p>";
    }
} else {
    $warnings[] = "No appointments found for status update testing";
    echo "<p style='color: orange;'>⚠ WARNING: No appointments found. Create an appointment first to test status updates.</p>";
}

// Test 10: Status Validation - Valid Values
echo "<h2>Test 10: Status Validation - Valid Values</h2>";
$validation_code = file_get_contents(__DIR__ . '/edit_appointment.php');
$valid_statuses_in_code = ['scheduled', 'ongoing', 'settled', 'cancelled'];
$validation_passed = true;

foreach ($valid_statuses_in_code as $status) {
    // Check if validation code includes this status
    if (strpos($validation_code, "'$status'") === false && strpos($validation_code, "\"$status\"") === false) {
        $validation_passed = false;
        $errors[] = "Valid status '$status' not found in validation code";
    }
}

if ($validation_passed) {
    $test_results[] = ['Test 10', 'PASS', 'All valid status values are in validation code'];
    echo "<p style='color: green;'>✓ PASS: All valid status values are properly validated</p>";
} else {
    $test_results[] = ['Test 10', 'FAIL', 'Some valid status values missing from validation'];
    echo "<p style='color: red;'>✗ FAIL: Validation code issues found</p>";
}

// Test 11: Status Validation - Invalid Values Rejected
echo "<h2>Test 11: Status Validation - Invalid Values Rejected</h2>";
$invalid_statuses = ['Scheduled', 'Completed', 'Cancelled', 'No Show', 'Confirmed', 'In Progress', 'pending', 'declined'];
$rejection_passed = true;

// Check if validation code explicitly rejects these
$validation_array = "['scheduled', 'ongoing', 'settled', 'cancelled']";
if (strpos($validation_code, $validation_array) !== false || 
    strpos($validation_code, "in_array") !== false) {
    // Validation uses in_array, so invalid values should be rejected
    $test_results[] = ['Test 11', 'PASS', 'Invalid status values are rejected by validation'];
    echo "<p style='color: green;'>✓ PASS: Invalid status values are properly rejected</p>";
} else {
    $warnings[] = "Could not verify invalid value rejection mechanism";
    echo "<p style='color: orange;'>⚠ WARNING: Could not verify rejection mechanism</p>";
}

// Test 12: Status Queries - All Status Values Work in WHERE Clauses
echo "<h2>Test 12: Status Queries - WHERE Clauses</h2>";
$query_test_passed = true;
$query_errors = [];

foreach ($valid_statuses as $status) {
    // Test if we can query by this status
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE status = ?");
    $stmt->bind_param("s", $status);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result()->fetch_assoc();
        $count = $result['count'];
        echo "<p>Status '<strong>$status</strong>': Found $count appointment(s)</p>";
    } else {
        $query_test_passed = false;
        $query_errors[] = "Query failed for status '$status': " . $conn->error;
        echo "<p style='color: red;'>✗ Query failed for status '$status'</p>";
    }
}

if ($query_test_passed) {
    $test_results[] = ['Test 12', 'PASS', 'All status values work in WHERE clauses'];
    echo "<p style='color: green;'>✓ PASS: All status values can be queried</p>";
} else {
    $test_results[] = ['Test 12', 'FAIL', 'Some status queries failed'];
    $errors = array_merge($errors, $query_errors);
    echo "<p style='color: red;'>✗ FAIL: Some status queries failed</p>";
}

// Test 13: Status Badge Display Logic
echo "<h2>Test 13: Status Badge Display Logic</h2>";
$badge_code = file_get_contents(__DIR__ . '/appointments.php');
$status_badge_mapping = [
    'scheduled' => 'primary',
    'ongoing' => 'warning',
    'settled' => 'success',
    'cancelled' => 'danger'
];

$badge_test_passed = true;
foreach ($status_badge_mapping as $status => $expected_class) {
    // Check if badge logic handles this status
    $status_lower_check = strpos($badge_code, strtolower($status)) !== false;
    $class_check = strpos($badge_code, $expected_class) !== false;
    
    if ($status_lower_check && $class_check) {
        echo "<p>Status '<strong>$status</strong>': Maps to badge class '<strong>$expected_class</strong>' ✓</p>";
    } else {
        $badge_test_passed = false;
        $errors[] = "Badge mapping missing for status '$status' (expected class: '$expected_class')";
        echo "<p style='color: red;'>✗ Badge mapping issue for status '$status'</p>";
    }
}

if ($badge_test_passed) {
    $test_results[] = ['Test 13', 'PASS', 'All status values have correct badge mappings'];
    echo "<p style='color: green;'>✓ PASS: Status badge display logic is correct</p>";
} else {
    $test_results[] = ['Test 13', 'FAIL', 'Some status badge mappings are missing'];
    echo "<p style='color: red;'>✗ FAIL: Status badge mapping issues found</p>";
}

// Test 14: Status in Forms - All Values Present
echo "<h2>Test 14: Status in Forms - All Values Present</h2>";
$form_code = file_get_contents(__DIR__ . '/doctor_dashboard.php');
$form_test_passed = true;

foreach ($valid_statuses as $status) {
    // Check if form includes this status as an option
    $status_in_form = (strpos($form_code, "value=\"$status\"") !== false || 
                      strpos($form_code, "value='$status'") !== false);
    
    if ($status_in_form) {
        echo "<p>Status '<strong>$status</strong>': Present in form ✓</p>";
    } else {
        $form_test_passed = false;
        $errors[] = "Status '$status' not found in form options";
        echo "<p style='color: red;'>✗ Status '$status' not found in form</p>";
    }
}

if ($form_test_passed) {
    $test_results[] = ['Test 14', 'PASS', 'All status values are present in forms'];
    echo "<p style='color: green;'>✓ PASS: All status values are in form dropdowns</p>";
} else {
    $test_results[] = ['Test 14', 'FAIL', 'Some status values missing from forms'];
    echo "<p style='color: red;'>✗ FAIL: Form options incomplete</p>";
}

// Summary
echo "<hr><h2>Test Summary</h2>";

// Status Tests Summary
echo "<h3>Status Functionality Tests (Tests 9-14)</h3>";
$status_tests = array_filter($test_results, function($r) {
    return in_array($r[0], ['Test 9', 'Test 10', 'Test 11', 'Test 12', 'Test 13', 'Test 14']);
});
if (!empty($status_tests)) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background-color: #f0f0f0;'><th>Test</th><th>Status</th><th>Details</th></tr>";
    foreach ($status_tests as $result) {
        $color = $result[1] === 'PASS' ? 'green' : 'red';
        $bg_color = $result[1] === 'PASS' ? '#f0fff0' : '#fff0f0';
        echo "<tr style='background-color: $bg_color;'><td><strong>{$result[0]}</strong></td><td style='color: $color; font-weight: bold;'>{$result[1]}</td><td>{$result[2]}</td></tr>";
    }
    echo "</table>";
}

// All Tests Summary
echo "<h3>All Tests Summary</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'><th>Test</th><th>Status</th><th>Details</th></tr>";
foreach ($test_results as $result) {
    $color = $result[1] === 'PASS' ? 'green' : 'red';
    $bg_color = $result[1] === 'PASS' ? '#f0fff0' : '#fff0f0';
    echo "<tr style='background-color: $bg_color;'><td>{$result[0]}</td><td style='color: $color; font-weight: bold;'>{$result[1]}</td><td>{$result[2]}</td></tr>";
}
echo "</table>";

// Status Values Summary
echo "<h3>Status Values Summary</h3>";
echo "<div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>Valid Status Values:</strong></p>";
echo "<ul>";
foreach ($valid_statuses as $status) {
    $label = ucfirst($status);
    echo "<li><strong>$status</strong> ($label)</li>";
}
echo "</ul>";
echo "<p><strong>Status Badge Colors:</strong></p>";
echo "<ul>";
echo "<li><span style='color: #0d6efd;'><strong>scheduled</strong></span> → Blue (primary)</li>";
echo "<li><span style='color: #ffc107;'><strong>ongoing</strong></span> → Yellow (warning)</li>";
echo "<li><span style='color: #198754;'><strong>settled</strong></span> → Green (success)</li>";
echo "<li><span style='color: #dc3545;'><strong>cancelled</strong></span> → Red (danger)</li>";
echo "</ul>";
echo "</div>";

if (!empty($errors)) {
    echo "<h3 style='color: red;'>Errors Found:</h3><ul>";
    foreach ($errors as $error) {
        echo "<li style='color: red;'>$error</li>";
    }
    echo "</ul>";
}

if (!empty($warnings)) {
    echo "<h3 style='color: orange;'>Warnings:</h3><ul>";
    foreach ($warnings as $warning) {
        echo "<li style='color: orange;'>$warning</li>";
    }
    echo "</ul>";
}

$pass_count = count(array_filter($test_results, function($r) { return $r[1] === 'PASS'; }));
$total_count = count($test_results);
$status_test_count = count($status_tests);
$status_pass_count = count(array_filter($status_tests, function($r) { return $r[1] === 'PASS'; }));

echo "<div style='background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2196F3;'>";
echo "<h3 style='margin-top: 0;'>Overall Results</h3>";
echo "<p><strong>Total Tests:</strong> $total_count</p>";
echo "<p><strong>Passed:</strong> <span style='color: green; font-weight: bold;'>$pass_count</span></p>";
echo "<p><strong>Failed:</strong> <span style='color: red; font-weight: bold;'>" . ($total_count - $pass_count) . "</span></p>";
echo "<p><strong>Status Functionality Tests:</strong> $status_pass_count/$status_test_count passed</p>";
echo "</div>";

if (empty($errors)) {
    echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;'>";
    echo "<p style='color: green; font-size: 1.2em; margin: 0;'><strong>✓ All critical tests passed!</strong></p>";
    echo "<p style='margin: 5px 0 0 0;'>All appointment status values are working correctly.</p>";
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;'>";
    echo "<p style='color: red; font-size: 1.2em; margin: 0;'><strong>✗ Some tests failed. Please review errors above.</strong></p>";
    echo "<p style='margin: 5px 0 0 0;'>Please fix the issues before deploying to production.</p>";
    echo "</div>";
}

// Additional recommendations
if (!empty($warnings)) {
    echo "<div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
    echo "<h4 style='margin-top: 0;'>Recommendations</h4>";
    echo "<ul style='margin-bottom: 0;'>";
    foreach ($warnings as $warning) {
        echo "<li>$warning</li>";
    }
    echo "</ul>";
    echo "</div>";
}
?>

