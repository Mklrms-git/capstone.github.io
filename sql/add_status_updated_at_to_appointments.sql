-- Add status_updated_at column to appointments table
-- This column tracks when the appointment status was last manually updated
-- Used for automatic settlement after 7 days of inactivity

ALTER TABLE `appointments` 
ADD COLUMN `status_updated_at` TIMESTAMP NULL DEFAULT NULL 
AFTER `updated_at`;

-- Initialize status_updated_at with updated_at for existing appointments
-- This ensures existing appointments have a baseline timestamp
UPDATE `appointments` 
SET `status_updated_at` = `updated_at` 
WHERE `status_updated_at` IS NULL;

-- Create index for efficient querying
CREATE INDEX `idx_status_updated_at` ON `appointments` (`status_updated_at`);

