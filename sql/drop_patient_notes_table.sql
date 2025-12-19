-- ============================================================================
-- Drop patient_notes table
-- ============================================================================
-- This script removes the patient_notes table as it is no longer needed.
-- The notes functionality has been removed from the system.
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

-- Drop the patient_notes table if it exists
DROP TABLE IF EXISTS `patient_notes`;

-- Verify the table has been dropped
SELECT 
  'Migration Verification' AS info,
  CASE 
    WHEN COUNT(*) = 0 THEN '✓ patient_notes table has been dropped successfully'
    ELSE '✗ patient_notes table still exists'
  END AS status
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'patient_notes';

-- Final success message
SELECT '============================================================================' AS '';
SELECT 'Migration completed successfully!' AS '';
SELECT 'The patient_notes table has been removed from the database.' AS '';
SELECT '============================================================================' AS '';

