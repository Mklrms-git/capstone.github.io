-- ============================================================================
-- Name Field Validation: No Numbers Allowed
-- ============================================================================
-- This script checks for existing records in the database that contain
-- numbers in name fields (first_name, last_name, middle_name, emergency_contact_name).
-- 
-- Application-level validation has been implemented in patient_registration.php
-- to prevent numbers from being entered in name fields going forward.
--
-- Database Schema:
-- - patients table: first_name, last_name, middle_name, emergency_contact_name
-- - patient_registration_requests table: first_name, last_name, middle_name, emergency_contact_name
-- 
-- Note: MySQL CHECK constraints are supported in MySQL 8.0.16+, but for
-- better compatibility and flexibility, validation is handled at the
-- application level (PHP + JavaScript).
-- ============================================================================

-- Check for patients with numbers in name fields
SELECT 
    id,
    patient_number,
    first_name,
    middle_name,
    last_name,
    emergency_contact_name,
    email,
    created_at
FROM patients
WHERE 
    first_name REGEXP '[0-9]' OR
    last_name REGEXP '[0-9]' OR
    (middle_name IS NOT NULL AND middle_name REGEXP '[0-9]') OR
    emergency_contact_name REGEXP '[0-9]'
ORDER BY created_at DESC;

-- Check for patient registration requests with numbers in name fields
SELECT 
    id,
    first_name,
    middle_name,
    last_name,
    emergency_contact_name,
    email,
    status,
    created_at
FROM patient_registration_requests
WHERE 
    first_name REGEXP '[0-9]' OR
    last_name REGEXP '[0-9]' OR
    (middle_name IS NOT NULL AND middle_name REGEXP '[0-9]') OR
    emergency_contact_name REGEXP '[0-9]'
ORDER BY created_at DESC;

-- Count of affected records
SELECT 
    'patients' AS table_name,
    COUNT(*) AS records_with_numbers_in_names
FROM patients
WHERE 
    first_name REGEXP '[0-9]' OR
    last_name REGEXP '[0-9]' OR
    (middle_name IS NOT NULL AND middle_name REGEXP '[0-9]') OR
    emergency_contact_name REGEXP '[0-9]'
UNION ALL
SELECT 
    'patient_registration_requests' AS table_name,
    COUNT(*) AS records_with_numbers_in_names
FROM patient_registration_requests
WHERE 
    first_name REGEXP '[0-9]' OR
    last_name REGEXP '[0-9]' OR
    (middle_name IS NOT NULL AND middle_name REGEXP '[0-9]') OR
    emergency_contact_name REGEXP '[0-9]';

-- ============================================================================
-- Optional: Clean existing data (USE WITH CAUTION - Review before executing)
-- ============================================================================
-- WARNING: The following queries will modify existing data by removing
-- numbers from name fields. Review the SELECT queries above first to
-- identify which records will be affected.
-- 
-- Uncomment and execute only after reviewing the affected records.
-- ============================================================================

/*
-- Clean numbers from patients table (removes all digits from name fields)
UPDATE patients
SET 
    first_name = REGEXP_REPLACE(first_name, '[0-9]', ''),
    last_name = REGEXP_REPLACE(last_name, '[0-9]', ''),
    middle_name = CASE 
        WHEN middle_name IS NOT NULL THEN REGEXP_REPLACE(middle_name, '[0-9]', '')
        ELSE middle_name
    END,
    emergency_contact_name = REGEXP_REPLACE(emergency_contact_name, '[0-9]', '')
WHERE 
    first_name REGEXP '[0-9]' OR
    last_name REGEXP '[0-9]' OR
    (middle_name IS NOT NULL AND middle_name REGEXP '[0-9]') OR
    emergency_contact_name REGEXP '[0-9]';

-- Clean numbers from patient_registration_requests table (only for Pending requests)
-- Note: Approved/Rejected requests should probably be left as-is for historical accuracy
UPDATE patient_registration_requests
SET 
    first_name = REGEXP_REPLACE(first_name, '[0-9]', ''),
    last_name = REGEXP_REPLACE(last_name, '[0-9]', ''),
    middle_name = CASE 
        WHEN middle_name IS NOT NULL THEN REGEXP_REPLACE(middle_name, '[0-9]', '')
        ELSE middle_name
    END,
    emergency_contact_name = REGEXP_REPLACE(emergency_contact_name, '[0-9]', '')
WHERE 
    status = 'Pending' AND (
        first_name REGEXP '[0-9]' OR
        last_name REGEXP '[0-9]' OR
        (middle_name IS NOT NULL AND middle_name REGEXP '[0-9]') OR
        emergency_contact_name REGEXP '[0-9]'
    );
*/

-- ============================================================================
-- MySQL 8.0.16+ CHECK Constraint (Optional - for database-level validation)
-- ============================================================================
-- If you're using MySQL 8.0.16 or later and want database-level validation,
-- you can add CHECK constraints. However, application-level validation is
-- recommended as it provides better error messages and user experience.
-- ============================================================================

/*
-- Add CHECK constraint to patients table (MySQL 8.0.16+)
ALTER TABLE patients
ADD CONSTRAINT chk_first_name_no_numbers 
    CHECK (first_name NOT REGEXP '[0-9]'),
ADD CONSTRAINT chk_last_name_no_numbers 
    CHECK (last_name NOT REGEXP '[0-9]'),
ADD CONSTRAINT chk_middle_name_no_numbers 
    CHECK (middle_name IS NULL OR middle_name NOT REGEXP '[0-9]'),
ADD CONSTRAINT chk_emergency_contact_name_no_numbers 
    CHECK (emergency_contact_name NOT REGEXP '[0-9]');

-- Add CHECK constraint to patient_registration_requests table (MySQL 8.0.16+)
ALTER TABLE patient_registration_requests
ADD CONSTRAINT chk_reg_first_name_no_numbers 
    CHECK (first_name NOT REGEXP '[0-9]'),
ADD CONSTRAINT chk_reg_last_name_no_numbers 
    CHECK (last_name NOT REGEXP '[0-9]'),
ADD CONSTRAINT chk_reg_middle_name_no_numbers 
    CHECK (middle_name IS NULL OR middle_name NOT REGEXP '[0-9]'),
ADD CONSTRAINT chk_reg_emergency_contact_name_no_numbers 
    CHECK (emergency_contact_name NOT REGEXP '[0-9]');
*/

