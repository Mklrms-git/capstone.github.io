<?php
/**
 * Migration script to add created_by and updated_by columns to medical_records table
 */

// Bypass authentication for migration
define('MHAVIS_EXEC', true);
$_SESSION = []; // Initialize empty session to avoid auth issues

// Load database config directly
require_once __DIR__ . '/config/database.php';

// Database connection
function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error . "\n");
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed!\n");
}

echo "Running migration: Add created_by and updated_by columns to medical_records table...\n\n";

// Function to check if a column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Function to check if an index exists
function indexExists($conn, $table, $indexName) {
    $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
    return $result && $result->num_rows > 0;
}

$successCount = 0;
$errorCount = 0;

// Step 1: Add created_by column
echo "Step 1: Checking created_by column...\n";
if (columnExists($conn, 'medical_records', 'created_by')) {
    echo "  ⚠ Column 'created_by' already exists - skipping.\n\n";
} else {
    echo "  Adding created_by column...\n";
    $sql = "ALTER TABLE `medical_records` ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `status`";
    if ($conn->query($sql)) {
        echo "  ✓ Success: created_by column added\n\n";
        $successCount++;
    } else {
        echo "  ✗ Error: " . $conn->error . "\n\n";
        $errorCount++;
    }
}

// Step 2: Add updated_by column
echo "Step 2: Checking updated_by column...\n";
if (columnExists($conn, 'medical_records', 'updated_by')) {
    echo "  ⚠ Column 'updated_by' already exists - skipping.\n\n";
} else {
    echo "  Adding updated_by column...\n";
    $sql = "ALTER TABLE `medical_records` ADD COLUMN `updated_by` int(11) DEFAULT NULL AFTER `created_by`";
    if ($conn->query($sql)) {
        echo "  ✓ Success: updated_by column added\n\n";
        $successCount++;
    } else {
        echo "  ✗ Error: " . $conn->error . "\n\n";
        $errorCount++;
    }
}

// Step 3: Add index on created_by
echo "Step 3: Checking index on created_by...\n";
if (indexExists($conn, 'medical_records', 'idx_medical_records_created_by')) {
    echo "  ⚠ Index 'idx_medical_records_created_by' already exists - skipping.\n\n";
} else {
    // Make sure column exists before creating index
    if (columnExists($conn, 'medical_records', 'created_by')) {
        echo "  Creating index on created_by...\n";
        $sql = "CREATE INDEX `idx_medical_records_created_by` ON `medical_records` (`created_by`)";
        if ($conn->query($sql)) {
            echo "  ✓ Success: Index created\n\n";
            $successCount++;
        } else {
            echo "  ✗ Error: " . $conn->error . "\n\n";
            $errorCount++;
        }
    } else {
        echo "  ✗ Error: Cannot create index - column 'created_by' does not exist\n\n";
        $errorCount++;
    }
}

// Step 4: Add index on updated_by
echo "Step 4: Checking index on updated_by...\n";
if (indexExists($conn, 'medical_records', 'idx_medical_records_updated_by')) {
    echo "  ⚠ Index 'idx_medical_records_updated_by' already exists - skipping.\n\n";
} else {
    // Make sure column exists before creating index
    if (columnExists($conn, 'medical_records', 'updated_by')) {
        echo "  Creating index on updated_by...\n";
        $sql = "CREATE INDEX `idx_medical_records_updated_by` ON `medical_records` (`updated_by`)";
        if ($conn->query($sql)) {
            echo "  ✓ Success: Index created\n\n";
            $successCount++;
        } else {
            echo "  ✗ Error: " . $conn->error . "\n\n";
            $errorCount++;
        }
    } else {
        echo "  ✗ Error: Cannot create index - column 'updated_by' does not exist\n\n";
        $errorCount++;
    }
}

// Verify the migration
echo "Verification:\n";
$createdByExists = columnExists($conn, 'medical_records', 'created_by');
$updatedByExists = columnExists($conn, 'medical_records', 'updated_by');
echo "  created_by column: " . ($createdByExists ? "✓ Exists" : "✗ Missing") . "\n";
echo "  updated_by column: " . ($updatedByExists ? "✓ Exists" : "✗ Missing") . "\n\n";

echo "Migration completed!\n";
echo "Success: $successCount operations\n";
echo "Errors: $errorCount operations\n\n";

if ($errorCount > 0 || !$createdByExists || !$updatedByExists) {
    echo "⚠ Warning: Migration may not have completed successfully.\n";
    echo "Please check the errors above and verify the columns exist.\n";
    exit(1);
} else {
    echo "✓ Migration completed successfully!\n";
    echo "The medical_records table now has created_by and updated_by columns.\n";
    exit(0);
}

