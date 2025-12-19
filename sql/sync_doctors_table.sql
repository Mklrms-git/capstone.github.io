-- ================================================
-- SQL Script to Sync Doctors Table with Users Table
-- ================================================
-- This script ensures all doctors in the users table
-- have corresponding entries in the doctors table
-- with proper department_id assignments
-- ================================================

-- Step 1: Insert missing doctors from users table into doctors table
INSERT INTO doctors (user_id, specialization, license_number, department_id)
SELECT 
    u.id as user_id,
    u.specialization,
    u.license_number,
    u.department_id
FROM users u
WHERE u.role = 'Doctor'
AND u.id NOT IN (SELECT user_id FROM doctors WHERE user_id IS NOT NULL);

-- Step 2: Update existing doctors table records with correct department_id from users table
UPDATE doctors d
INNER JOIN users u ON d.user_id = u.id
SET 
    d.department_id = u.department_id,
    d.specialization = COALESCE(d.specialization, u.specialization),
    d.license_number = COALESCE(d.license_number, u.license_number)
WHERE u.role = 'Doctor';

-- Step 3: Verify the sync (optional - shows count of doctors)
SELECT 
    'Total Doctors in Users Table' as Description,
    COUNT(*) as Count
FROM users 
WHERE role = 'Doctor'
UNION ALL
SELECT 
    'Total Doctors in Doctors Table' as Description,
    COUNT(*) as Count
FROM doctors
UNION ALL
SELECT 
    'Doctors with Department Assigned' as Description,
    COUNT(*) as Count
FROM doctors 
WHERE department_id IS NOT NULL;

