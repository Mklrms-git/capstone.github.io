-- Alternative Fix: Keep Title Case Format (Matches Current Database Style)
-- This adds missing status values to the existing ENUM structure

-- Step 1: Migrate any lowercase or invalid statuses to Title Case
UPDATE appointments SET status = 'Scheduled' 
WHERE status IN ('scheduled', 'pending', 'confirmed');

UPDATE appointments SET status = 'Completed' 
WHERE status IN ('completed', 'settled');

UPDATE appointments SET status = 'Cancelled' 
WHERE status IN ('cancelled', 'no_show', 'no show');

-- Note: 'ongoing' and 'In Progress' will need to map to existing values
-- We'll use 'Scheduled' as a placeholder, or you can add a new status
UPDATE appointments SET status = 'Scheduled' 
WHERE status IN ('ongoing', 'In Progress', 'in progress');

-- Step 2: Update ENUM to include all needed values
-- This keeps the Title Case format but adds missing values
ALTER TABLE `appointments` 
MODIFY COLUMN `status` ENUM('Scheduled', 'Confirmed', 'In Progress', 'Ongoing', 'Completed', 'Settled', 'Cancelled', 'No Show') NOT NULL DEFAULT 'Scheduled';

-- Step 3: Verify the update
-- Run: SELECT DISTINCT status FROM appointments;

-- NOTES:
-- 1. This keeps Title Case format matching current database style
-- 2. Includes all status values that might be used:
--    - Scheduled (default, new appointments)
--    - Confirmed (doctor confirmed)
--    - In Progress / Ongoing (appointment happening now)
--    - Completed / Settled (appointment finished)
--    - Cancelled (cancelled by user/doctor)
--    - No Show (patient didn't show up)
-- 3. After this migration, update PHP code to use Title Case values
-- 4. Forms should show: Scheduled, Confirmed, In Progress, Completed, Cancelled, No Show

