-- Add Medical_Record_Updated to the notification type enum
ALTER TABLE `notifications` 
MODIFY COLUMN `type` ENUM(
    'Registration_Approved',
    'Registration_Rejected',
    'Appointment_Approved',
    'Appointment_Rejected',
    'Appointment_Reminder',
    'Appointment_Rescheduled',
    'Medical_Record_Updated'
) NOT NULL;

-- Sample query to verify the change
-- SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'notifications' AND COLUMN_NAME = 'type';

