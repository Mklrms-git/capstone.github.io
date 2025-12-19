-- Migration Script: Convert Super Admin to Admin Role
-- This script converts all Super Admin users to Admin role
-- Run this script after deploying the code changes that make Admin the top-level role
--
-- IMPORTANT: 
-- 1. Backup your database before running this script
-- 2. This is a one-way migration - Super Admin role will be removed
-- 3. All Super Admin users will become Admin users with full access

-- Step 1: Convert all Super Admin users to Admin
UPDATE users 
SET role = 'Admin',
    updated_at = NOW()
WHERE role = 'Super Admin';

-- Step 2: Verify the conversion
SELECT 
    'Conversion Verification' AS check_type,
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ PASS - All Super Admin users converted to Admin'
        ELSE CONCAT('✗ FAIL - ', COUNT(*), ' Super Admin user(s) still exist')
    END AS result
FROM users 
WHERE role = 'Super Admin';

-- Step 3: Show converted users
SELECT 
    id,
    username,
    first_name,
    last_name,
    email,
    role,
    status,
    updated_at AS converted_at
FROM users 
WHERE role = 'Admin'
ORDER BY updated_at DESC
LIMIT 20;

-- Step 4: Optional - Remove Super Admin from ENUM (uncomment if you want to remove it completely)
-- Note: This will fail if there are still Super Admin users in the database
-- ALTER TABLE users MODIFY COLUMN role ENUM('Admin', 'Doctor') NOT NULL;

-- Final verification
SELECT 
    'Migration Summary' AS summary,
    (SELECT COUNT(*) FROM users WHERE role = 'Admin') AS total_admins,
    (SELECT COUNT(*) FROM users WHERE role = 'Super Admin') AS remaining_super_admins,
    (SELECT COUNT(*) FROM users WHERE role = 'Doctor') AS total_doctors,
    CASE 
        WHEN (SELECT COUNT(*) FROM users WHERE role = 'Super Admin') = 0 
        THEN '✓ Migration completed successfully - All Super Admin users are now Admin'
        ELSE '⚠ Warning - Some Super Admin users still exist'
    END AS migration_status;

