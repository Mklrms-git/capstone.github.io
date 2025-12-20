-- Fix Admin User (ID 17) - Update with your actual information
-- Replace the values below with the actual admin's information

UPDATE users 
SET 
    phone = '+639123456789',  -- Replace with actual phone number
    address = 'BARANGAY_NAME, CITY_NAME, PROVINCE_NAME, REGION_NAME'  -- Replace with actual address
WHERE 
    id = 17 
    AND role = 'Admin'
    AND username = 'admin';

-- Example with actual values:
-- UPDATE users 
-- SET 
--     phone = '+639123456789',
--     address = 'LANGKAAN I, DASMARIÑAS CITY, CAVITE, REGION IV-A'
-- WHERE 
--     id = 17 
--     AND role = 'Admin';

-- Verify the update:
SELECT id, first_name, last_name, email, phone, address, role 
FROM users 
WHERE id = 17;



