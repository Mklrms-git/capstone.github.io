-- ============================================================================
-- Add PRC Number and License Type columns to users table
-- ============================================================================
-- This script adds the prc_number and license_type columns to the users table
-- to support doctor professional information display.
--
-- Instructions:
--   1. Open phpMyAdmin (http://localhost/phpmyadmin)
--   2. Select your database
--   3. Click on the SQL tab
--   4. Copy and paste this entire script
--   5. Click Go
--   6. Verify the output shows success messages
--
-- Note: This script is safe to run multiple times
-- ============================================================================

SET @dbname = DATABASE();
SET @tablename = 'users';

-- Check if prc_number column exists
SET @prc_number_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
      AND TABLE_NAME = @tablename 
      AND COLUMN_NAME = 'prc_number'
);

-- Check if license_type column exists
SET @license_type_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
      AND TABLE_NAME = @tablename 
      AND COLUMN_NAME = 'license_type'
);

-- Check if prc_id_document column exists
SET @prc_id_document_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
      AND TABLE_NAME = @tablename 
      AND COLUMN_NAME = 'prc_id_document'
);

-- Add prc_number column if it doesn't exist
SET @sql = IF(@prc_number_exists = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `prc_number` VARCHAR(50) DEFAULT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add license_type column if it doesn't exist
SET @sql = IF(@license_type_exists = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `license_type` VARCHAR(50) DEFAULT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add prc_id_document column if it doesn't exist
SET @sql = IF(@prc_id_document_exists = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `prc_id_document` VARCHAR(255) DEFAULT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the columns have been added
SELECT 
  'Migration Verification' AS info,
  CASE 
    WHEN COUNT(*) > 0 THEN CONCAT('✓ prc_number column exists')
    ELSE CONCAT('✗ prc_number column missing')
  END AS prc_number_status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
  AND COLUMN_NAME = 'prc_number';

SELECT 
  'Migration Verification' AS info,
  CASE 
    WHEN COUNT(*) > 0 THEN CONCAT('✓ license_type column exists')
    ELSE CONCAT('✗ license_type column missing')
  END AS license_type_status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
  AND COLUMN_NAME = 'license_type';

SELECT 
  'Migration Verification' AS info,
  CASE 
    WHEN COUNT(*) > 0 THEN CONCAT('✓ prc_id_document column exists')
    ELSE CONCAT('✗ prc_id_document column missing')
  END AS prc_id_document_status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
  AND COLUMN_NAME = 'prc_id_document';

-- Final success message
SELECT '============================================================================' AS '';
SELECT 'Migration completed successfully!' AS '';
SELECT 'The prc_number, license_type, and prc_id_document columns have been added to the users table.' AS '';
SELECT '============================================================================' AS '';

