-- Update appointments table status ENUM to: scheduled, ongoing, settled, cancelled
-- This script migrates existing data and updates the ENUM definition

-- Step 1: Migrate existing status values to new lowercase values
UPDATE appointments SET status = 'scheduled' 
WHERE status IN ('Scheduled', 'Confirmed', 'pending', 'confirmed', 'Pending');

UPDATE appointments SET status = 'ongoing' 
WHERE status IN ('In Progress', 'Ongoing', 'in progress', 'In_Progress');

UPDATE appointments SET status = 'settled' 
WHERE status IN ('Completed', 'completed', 'Settled', 'settled');

UPDATE appointments SET status = 'cancelled' 
WHERE status IN ('Cancelled', 'cancelled', 'No Show', 'no show', 'no_show', 'No_Show', 'declined', 'Declined');

-- Step 2: Update the ENUM definition
ALTER TABLE `appointments` 
MODIFY COLUMN `status` ENUM('scheduled', 'ongoing', 'settled', 'cancelled') NOT NULL DEFAULT 'scheduled';

-- Step 3: Verify the change
-- SELECT DISTINCT status FROM appointments ORDER BY status;

