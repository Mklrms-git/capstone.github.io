-- Setup Doctor Schedules
-- This script adds default schedules for all doctors who don't have one yet

-- First, let's see which doctors don't have schedules
SELECT u.id, u.first_name, u.last_name
FROM users u
INNER JOIN doctors d ON u.id = d.user_id
WHERE u.role = 'doctor' 
AND NOT EXISTS (
    SELECT 1 FROM doctor_schedules WHERE doctor_id = u.id
);

-- Add default Monday-Friday 9 AM - 5 PM schedule with 12-1 PM lunch break
-- for all doctors who don't have a schedule yet

-- Note: You'll need to replace {doctor_user_id} with actual doctor user IDs

-- Example for doctor with user_id = 18:
INSERT INTO doctor_schedules (doctor_id, day_of_week, is_available, start_time, end_time, break_start, break_end) 
VALUES 
-- Monday
(18, '1', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Tuesday
(18, '2', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Wednesday
(18, '3', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Thursday
(18, '4', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Friday
(18, '5', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Saturday - Not available
(18, '6', 0, NULL, NULL, NULL, NULL),
-- Sunday - Not available
(18, '7', 0, NULL, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE 
    is_available = VALUES(is_available),
    start_time = VALUES(start_time),
    end_time = VALUES(end_time),
    break_start = VALUES(break_start),
    break_end = VALUES(break_end);

-- Example for doctor with user_id = 23:
INSERT INTO doctor_schedules (doctor_id, day_of_week, is_available, start_time, end_time, break_start, break_end) 
VALUES 
-- Monday
(23, '1', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Tuesday
(23, '2', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Wednesday
(23, '3', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Thursday
(23, '4', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Friday
(23, '5', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00'),
-- Saturday - Not available
(23, '6', 0, NULL, NULL, NULL, NULL),
-- Sunday - Not available
(23, '7', 0, NULL, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE 
    is_available = VALUES(is_available),
    start_time = VALUES(start_time),
    end_time = VALUES(end_time),
    break_start = VALUES(break_start),
    break_end = VALUES(break_end);

-- Alternative: Different schedules for different doctors

-- Example: Doctor with morning shifts only (8 AM - 12 PM)
-- Replace {doctor_user_id} with actual doctor user ID
-- INSERT INTO doctor_schedules (doctor_id, day_of_week, is_available, start_time, end_time, break_start, break_end) 
-- VALUES 
-- ({doctor_user_id}, '1', 1, '08:00:00', '12:00:00', NULL, NULL),
-- ({doctor_user_id}, '2', 1, '08:00:00', '12:00:00', NULL, NULL),
-- ({doctor_user_id}, '3', 1, '08:00:00', '12:00:00', NULL, NULL),
-- ({doctor_user_id}, '4', 1, '08:00:00', '12:00:00', NULL, NULL),
-- ({doctor_user_id}, '5', 1, '08:00:00', '12:00:00', NULL, NULL),
-- ({doctor_user_id}, '6', 0, NULL, NULL, NULL, NULL),
-- ({doctor_user_id}, '7', 0, NULL, NULL, NULL, NULL);

-- Example: Doctor with afternoon shifts only (1 PM - 7 PM)
-- Replace {doctor_user_id} with actual doctor user ID
-- INSERT INTO doctor_schedules (doctor_id, day_of_week, is_available, start_time, end_time, break_start, break_end) 
-- VALUES 
-- ({doctor_user_id}, '1', 1, '13:00:00', '19:00:00', NULL, NULL),
-- ({doctor_user_id}, '2', 1, '13:00:00', '19:00:00', NULL, NULL),
-- ({doctor_user_id}, '3', 1, '13:00:00', '19:00:00', NULL, NULL),
-- ({doctor_user_id}, '4', 1, '13:00:00', '19:00:00', NULL, NULL),
-- ({doctor_user_id}, '5', 1, '13:00:00', '19:00:00', NULL, NULL),
-- ({doctor_user_id}, '6', 1, '13:00:00', '19:00:00', NULL, NULL),
-- ({doctor_user_id}, '7', 0, NULL, NULL, NULL, NULL);

-- Verify schedules were created
SELECT ds.*, u.first_name, u.last_name,
    CASE ds.day_of_week
        WHEN '1' THEN 'Monday'
        WHEN '2' THEN 'Tuesday'
        WHEN '3' THEN 'Wednesday'
        WHEN '4' THEN 'Thursday'
        WHEN '5' THEN 'Friday'
        WHEN '6' THEN 'Saturday'
        WHEN '7' THEN 'Sunday'
    END as day_name
FROM doctor_schedules ds
INNER JOIN users u ON ds.doctor_id = u.id
ORDER BY ds.doctor_id, ds.day_of_week;

