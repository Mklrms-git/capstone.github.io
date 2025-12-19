-- ================================================
-- SQL Script to Assign Doctors to Departments
-- ================================================
-- This script assigns doctors to their proper departments
-- based on their specialization field
-- ================================================

-- Update users table: Assign doctors to departments based on specialization
UPDATE users u
INNER JOIN departments d ON (
    -- Match specialization to department name
    (u.specialization = 'Cardiology' AND d.name = 'Cardiology') OR
    (u.specialization = 'ENT' AND d.name = 'ENT') OR
    (u.specialization = 'Internal Medicine' AND d.name = 'Internal Medicine') OR
    (u.specialization IN ('OBG-YN', 'OB-GYN', 'OBGYN') AND d.name = 'OB-GYN') OR
    (u.specialization IN ('ORTHO', 'Orthopedic', 'Orthopedics') AND d.name = 'Orthopedic') OR
    (u.specialization IN ('Pedia', 'Pediatrics', 'Pediatric') AND d.name = 'Pediatrics') OR
    (u.specialization = 'Psychiatry' AND d.name = 'Psychiatry') OR
    (u.specialization = 'Surgery' AND d.name = 'Surgery')
)
SET u.department_id = d.id
WHERE u.role = 'Doctor' AND u.department_id IS NULL;

-- Update doctors table: Sync department_id from users table
UPDATE doctors doc
INNER JOIN users u ON doc.user_id = u.id
SET doc.department_id = u.department_id
WHERE u.role = 'Doctor' AND u.department_id IS NOT NULL;

-- Verify the assignments
SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.specialization,
    d.name as department_name,
    u.department_id,
    doc.department_id as doctor_table_dept_id
FROM users u
LEFT JOIN departments d ON u.department_id = d.id
LEFT JOIN doctors doc ON doc.user_id = u.id
WHERE u.role = 'Doctor'
ORDER BY u.first_name, u.last_name;

