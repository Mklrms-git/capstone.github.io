-- ============================================================================
-- Check Database Status for Super Admin System
-- ============================================================================
-- Run this script to check if your database has been updated for Super Admin
-- This will help you determine if you need to run the migration script
-- ============================================================================

USE mhavis;

-- Check 1: Does role ENUM include 'Super Admin'?
SELECT 
    'Role ENUM Check' AS check_name,
    COLUMN_TYPE AS current_value,
    CASE 
        WHEN COLUMN_TYPE LIKE '%Super Admin%' THEN '✓ PASS - Super Admin role exists'
        ELSE '✗ FAIL - Need to run migration (Super Admin not in ENUM)'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME = 'role';

-- Check 2: Does Super Admin user exist?
SELECT 
    'Super Admin User Check' AS check_name,
    COUNT(*) AS user_count,
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ PASS - Super Admin user exists'
        ELSE '✗ FAIL - Need to run migration (Super Admin user not found)'
    END AS status
FROM users 
WHERE role = 'Super Admin';

-- Check 3: List all Super Admin users (if any)
SELECT 
    'Super Admin Users List' AS check_name,
    id,
    username,
    email,
    first_name,
    last_name,
    status,
    created_at
FROM users 
WHERE role = 'Super Admin';

-- Check 4: Verify required columns exist
SELECT 
    'Required Columns Check' AS check_name,
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    CASE 
        WHEN COLUMN_NAME IN ('phone', 'address', 'specialization', 'status') THEN '✓ EXISTS'
        ELSE 'N/A'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME IN ('phone', 'address', 'specialization', 'status')
ORDER BY COLUMN_NAME;

-- Summary
SELECT 
    'SUMMARY' AS check_name,
    CASE 
        WHEN (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'users' 
                AND COLUMN_NAME = 'role') LIKE '%Super Admin%'
         AND (SELECT COUNT(*) FROM users WHERE role = 'Super Admin') > 0
        THEN '✓ DATABASE IS UPDATED - Super Admin system is ready!'
        ELSE '✗ DATABASE NEEDS UPDATE - Please run: sql/super_admin_complete_migration.sql'
    END AS status;

