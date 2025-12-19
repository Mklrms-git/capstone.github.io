-- Migration: Remove explicit username usage for patient registration/login
-- Purpose: Patients will log in using Patient ID (patients.patient_number) + password

-- 1) patient_registration_requests: drop username column (no longer collected)
ALTER TABLE patient_registration_requests
    DROP COLUMN username;

-- 2) patient_users: keep username for backward compatibility but make it nullable
--    and drop the unique constraint, then optionally backfill username with patient_number.
-- NOTE: The implicit unique index created by 'username VARCHAR(50) UNIQUE NOT NULL'
--       is usually named 'username' in MySQL. Adjust name if your RDBMS names it differently.
ALTER TABLE patient_users
    DROP INDEX username;

ALTER TABLE patient_users
    MODIFY COLUMN username VARCHAR(50) NULL;

-- Optional backfill: set username = patient_number for existing rows (if desired)
UPDATE patient_users pu
JOIN patients p ON pu.patient_id = p.id
SET pu.username = p.patient_number
WHERE pu.username IS NULL OR pu.username = '';

-- After running this migration, ensure the application is redeployed.
-- New login uses patients.patient_number + password exclusively.


