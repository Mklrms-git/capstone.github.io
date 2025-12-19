-- ============================================================================
-- Remove prc_expiry_date column from users table
-- ============================================================================
-- This script removes the prc_expiry_date column from the users table
-- as the PRC expiry date field has been removed from the application.
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
SET @columnname = 'prc_expiry_date';

-- Check if the column exists before attempting to drop it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
      AND TABLE_NAME = @tablename 
      AND COLUMN_NAME = @columnname
);

-- Drop the column if it exists
SET @preparedStatement = CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN IF EXISTS `', @columnname, '`;');
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Verify the column has been removed
SELECT 
  'Migration Verification' AS info,
  CASE 
    WHEN COUNT(*) = 0 THEN CONCAT('✓ ', @columnname, ' column has been removed successfully')
    ELSE CONCAT('✗ ', @columnname, ' column still exists')
  END AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
  AND COLUMN_NAME = @columnname;

-- Final success message
SELECT '============================================================================' AS '';
SELECT 'Migration completed successfully!' AS '';
SELECT 'The prc_expiry_date column has been removed from the users table.' AS '';
SELECT '============================================================================' AS '';





