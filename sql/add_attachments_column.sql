-- Add attachments column to medical_records table
-- This allows storing file references as JSON for the current database structure

ALTER TABLE `medical_records` 
ADD COLUMN `attachments` TEXT DEFAULT NULL 
AFTER `notes`;

-- Add comment to explain the column usage
ALTER TABLE `medical_records` 
MODIFY COLUMN `attachments` TEXT DEFAULT NULL COMMENT 'JSON array of uploaded file information'; 