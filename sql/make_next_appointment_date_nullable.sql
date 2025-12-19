-- ============================================================================
-- Make next_appointment_date field nullable in medical_records table
-- ============================================================================
-- This script modifies the next_appointment_date column to allow NULL values
-- since this field is optional when creating medical records.
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
SET @columnname = 'next_appointment_date';

-- Check if column exists and modify it to allow NULL
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  CONCAT('ALTER TABLE `', @tablename, '` MODIFY COLUMN `', @columnname, '` date DEFAULT NULL;'),
  'SELECT "Column next_appointment_date does not exist. Skipping." AS result;'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Verify the changes
SELECT 
  'Migration Verification' AS info,
  COLUMN_NAME, 
  DATA_TYPE, 
  IS_NULLABLE, 
  COLUMN_DEFAULT,
  CASE 
    WHEN COLUMN_NAME = 'next_appointment_date' AND IS_NULLABLE = 'YES' THEN '✓ next_appointment_date is now nullable'
    ELSE '✗ Column still NOT NULL'
  END AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
  AND COLUMN_NAME = 'next_appointment_date';

-- Final success message
SELECT '============================================================================' AS '';
SELECT 'Migration completed successfully!' AS '';
SELECT 'The next_appointment_date field is now nullable.' AS '';
SELECT '============================================================================' AS '';





