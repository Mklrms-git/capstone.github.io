-- Update doctor_leaves table to include all leave types
-- Run this migration to add new leave types: Annual, Sick, Maternity, Paternity, Parental Leave, Emergency Leave, Bereavement Leave

-- Step 1: Migrate existing data
-- Update 'Vacation' to 'Annual' (or you can choose another appropriate type)
UPDATE `doctor_leaves` SET `leave_type` = 'Annual' WHERE `leave_type` = 'Vacation';

-- Update 'Emergency' to 'Emergency Leave'
UPDATE `doctor_leaves` SET `leave_type` = 'Emergency Leave' WHERE `leave_type` = 'Emergency';

-- Step 2: Modify the enum column to include all new leave types
ALTER TABLE `doctor_leaves` 
MODIFY COLUMN `leave_type` ENUM(
    'Annual',
    'Sick',
    'Maternity',
    'Paternity',
    'Parental Leave',
    'Emergency Leave',
    'Bereavement Leave'
) NOT NULL;

