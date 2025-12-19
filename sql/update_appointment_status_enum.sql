-- Update appointment status ENUM to: scheduled, ongoing, settled, cancelled
-- This migration updates the appointments table status column

ALTER TABLE `appointments` 
MODIFY COLUMN `status` ENUM('scheduled', 'ongoing', 'settled', 'cancelled') NOT NULL DEFAULT 'scheduled';

-- Note: If you have existing data with old status values, you may need to migrate them first:
-- UPDATE appointments SET status = 'scheduled' WHERE status IN ('pending', 'confirmed');
-- UPDATE appointments SET status = 'settled' WHERE status = 'completed';
-- UPDATE appointments SET status = 'cancelled' WHERE status IN ('cancelled', 'declined');
-- UPDATE appointments SET status = 'ongoing' WHERE status IN ('in progress', 'In Progress');

