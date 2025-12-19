-- Add patient_number column to patients table
-- This script adds a unique patient ID field for better patient identification

-- Add the patient_number column
ALTER TABLE `patients` 
ADD COLUMN `patient_number` VARCHAR(20) UNIQUE NULL AFTER `id`;

-- Create an index on patient_number for faster lookups
CREATE INDEX `idx_patient_number` ON `patients` (`patient_number`);

-- Update existing patients with generated patient numbers
-- This will generate patient numbers for existing records
SET @row_number = 0;
UPDATE `patients` 
SET `patient_number` = CONCAT('PT-', YEAR(created_at), '-', LPAD((@row_number := @row_number + 1), 5, '0'))
WHERE `patient_number` IS NULL;

-- Make patient_number NOT NULL after populating existing records
ALTER TABLE `patients` 
MODIFY COLUMN `patient_number` VARCHAR(20) UNIQUE NOT NULL;
