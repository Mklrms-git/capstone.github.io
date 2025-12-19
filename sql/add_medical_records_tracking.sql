-- ============================================================================
-- Medical Records Tracking Migration
-- ============================================================================
-- This script adds created_by and updated_by columns to the medical_records table
-- to track who creates and updates each medical record.
--
-- Purpose:
--   - created_by: Tracks the user (admin or doctor) who created the record
--   - updated_by: Tracks the user (admin or doctor) who last updated the record
--
-- Instructions:
--   1. Open phpMyAdmin (http://localhost/phpmyadmin)
--   2. Select your database
--   3. Click on the SQL tab
--   4. Copy and paste this entire script
--   5. Click Go
--   6. Verify the output shows success messages
--
-- Note: This script is safe to run multiple times - it checks if columns exist
-- ============================================================================

-- Step 1: Check and add created_by column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'medical_records';
SET @columnname = 'created_by';

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT "✓ Column created_by already exists. Skipping." AS result;',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` int(11) DEFAULT NULL AFTER `status`;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 2: Check and add updated_by column if it doesn't exist
SET @columnname = 'updated_by';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT "✓ Column updated_by already exists. Skipping." AS result;',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` int(11) DEFAULT NULL AFTER `created_by`;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 3: Add foreign key constraint for created_by (optional, for data integrity)
-- Skip if constraint already exists
SET @constraintExists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND CONSTRAINT_NAME = 'fk_medical_records_created_by'
);

SET @preparedStatement = (SELECT IF(
  @constraintExists > 0,
  'SELECT "✓ Foreign key fk_medical_records_created_by already exists. Skipping." AS result;',
  CONCAT('ALTER TABLE `', @tablename, '` ADD CONSTRAINT `fk_medical_records_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;')
));
PREPARE addFK FROM @preparedStatement;
EXECUTE addFK;
DEALLOCATE PREPARE addFK;

-- Step 4: Add foreign key constraint for updated_by (optional, for data integrity)
SET @constraintExists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND CONSTRAINT_NAME = 'fk_medical_records_updated_by'
);

SET @preparedStatement = (SELECT IF(
  @constraintExists > 0,
  'SELECT "✓ Foreign key fk_medical_records_updated_by already exists. Skipping." AS result;',
  CONCAT('ALTER TABLE `', @tablename, '` ADD CONSTRAINT `fk_medical_records_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;')
));
PREPARE addFK FROM @preparedStatement;
EXECUTE addFK;
DEALLOCATE PREPARE addFK;

-- Step 5: Add index on created_by for better query performance
CREATE INDEX IF NOT EXISTS `idx_medical_records_created_by` ON `medical_records` (`created_by`);

-- Step 6: Add index on updated_by for better query performance
CREATE INDEX IF NOT EXISTS `idx_medical_records_updated_by` ON `medical_records` (`updated_by`);

-- Step 7: Verify the migration was successful
SELECT 
  'Migration Verification' AS info,
  COLUMN_NAME, 
  DATA_TYPE, 
  IS_NULLABLE, 
  COLUMN_DEFAULT,
  CASE 
    WHEN COLUMN_NAME = 'created_by' THEN '✓ Created by column'
    WHEN COLUMN_NAME = 'updated_by' THEN '✓ Updated by column'
  END AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
  AND COLUMN_NAME IN ('created_by', 'updated_by');

-- Final success message
SELECT '============================================================================' AS '';
SELECT 'Migration completed successfully!' AS '';
SELECT 'The medical_records table now tracks who creates and updates records.' AS '';
SELECT '============================================================================' AS '';
