-- ============================================================================
-- Make prescription and vitals fields nullable in medical_records table
-- ============================================================================
-- This script modifies the prescription and vitals columns to allow NULL values
-- since these fields are optional when creating medical records.
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
SET @tablename = 'medical_records';

-- Step 1: Modify prescription column to allow NULL
SET @preparedStatement = CONCAT('ALTER TABLE `', @tablename, '` MODIFY COLUMN `prescription` text DEFAULT NULL;');
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Step 2: Modify vitals column to allow NULL
SET @preparedStatement = CONCAT('ALTER TABLE `', @tablename, '` MODIFY COLUMN `vitals` text DEFAULT NULL;');
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Step 3: Verify the changes
SELECT 
  'Migration Verification' AS info,
  COLUMN_NAME, 
  DATA_TYPE, 
  IS_NULLABLE, 
  COLUMN_DEFAULT,
  CASE 
    WHEN COLUMN_NAME = 'prescription' AND IS_NULLABLE = 'YES' THEN '✓ Prescription is now nullable'
    WHEN COLUMN_NAME = 'vitals' AND IS_NULLABLE = 'YES' THEN '✓ Vitals is now nullable'
    ELSE '✗ Column still NOT NULL'
  END AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
  AND COLUMN_NAME IN ('prescription', 'vitals');

-- Final success message
SELECT '============================================================================' AS '';
SELECT 'Migration completed successfully!' AS '';
SELECT 'The prescription and vitals fields are now nullable.' AS '';
SELECT '============================================================================' AS '';





