-- Create notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('patient','staff') NOT NULL DEFAULT 'patient',
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `user_type` (`user_type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample notifications for testing (optional - remove in production)
-- INSERT INTO `notifications` (`user_id`, `user_type`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
-- (1, 'patient', 'appointment_confirmed', 'Appointment Confirmed', 'Your appointment with Dr. Smith on Dec 15, 2024 at 10:00 AM has been confirmed.', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
-- (1, 'patient', 'medical_record', 'Medical Records Updated', 'Your medical records have been updated. Please review the changes.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
-- (1, 'patient', 'appointment_reminder', 'Appointment Reminder', 'You have an appointment tomorrow at 2:00 PM with Dr. Johnson.', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
-- (1, 'patient', 'prescription', 'New Prescription Available', 'A new prescription has been added to your account by Dr. Smith.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY));

