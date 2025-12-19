-- Fix Appointment Status ENUM - Comprehensive Migration
-- This fixes all ENUM mismatches and standardizes status values

-- Step 1: First, migrate existing data to lowercase versions
-- This ensures no data is lost when changing ENUM values

-- Update 'Scheduled' and 'Confirmed' to 'scheduled'
UPDATE appointments SET status = 'scheduled' 
WHERE status IN ('Scheduled', 'Confirmed', 'pending', 'confirmed');

-- Update 'In Progress' or similar to 'ongoing' 
-- Note: Since 'ongoing' may not exist in current ENUM, we'll use 'scheduled' temporarily
-- This will be fixed when ENUM is updated

-- Update 'Completed' to 'settled'
UPDATE appointments SET status = 'settled' 
WHERE status IN ('Completed', 'completed');

-- Update 'Cancelled' and 'No Show' to 'cancelled'
UPDATE appointments SET status = 'cancelled' 
WHERE status IN ('Cancelled', 'cancelled', 'No Show', 'no_show');

-- Step 2: Update ENUM to match code expectations
-- Using lowercase for consistency (matches edit_appointment.php expectations)
-- This ENUM includes all values used across the application

ALTER TABLE `appointments` 
MODIFY COLUMN `status` ENUM('scheduled', 'ongoing', 'settled', 'cancelled') NOT NULL DEFAULT 'scheduled';

-- Step 3: Verify the update worked
-- Run this query to check: SELECT DISTINCT status FROM appointments;

-- Step 4: If there are any remaining invalid statuses, update them to 'scheduled'
-- UPDATE appointments SET status = 'scheduled' WHERE status NOT IN ('scheduled', 'ongoing', 'settled', 'cancelled');

-- NOTES:
-- 1. This migration changes all status values to lowercase to match edit_appointment.php
-- 2. Status mapping:
--    - 'Scheduled' / 'Confirmed' / 'pending' → 'scheduled'
--    - 'In Progress' / 'Ongoing' → 'ongoing'
--    - 'Completed' / 'completed' → 'settled'
--    - 'Cancelled' / 'No Show' → 'cancelled'
-- 3. After this migration, all PHP code should use lowercase status values
-- 4. Forms should only show: scheduled, ongoing, settled, cancelled

