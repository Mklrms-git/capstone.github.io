-- Medical History and Medical Records Migration
-- This file contains the new table structures and migration steps

-- 1. Create new medical_history table
CREATE TABLE IF NOT EXISTS `medical_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `history_type` enum('allergies','medications','past_history','immunization','procedures','substance','family','menstrual','sexual','obstetric','growth') NOT NULL,
  `history_details` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient_id` (`patient_id`),
  KEY `idx_doctor_id` (`doctor_id`),
  KEY `idx_history_type` (`history_type`),
  CONSTRAINT `fk_medical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_medical_history_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Create new medical_records table (replacing the old one)
CREATE TABLE IF NOT EXISTS `medical_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `lab_results` text DEFAULT NULL,
  `vital_signs` json DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `attachments` text DEFAULT NULL,
  `next_appointment_date` date DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient_id` (`patient_id`),
  KEY `idx_doctor_id` (`doctor_id`),
  KEY `idx_visit_date` (`visit_date`),
  CONSTRAINT `fk_medical_records_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_medical_records_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create medical_record_attachments table for file uploads
CREATE TABLE IF NOT EXISTS `medical_record_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medical_record_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_medical_record_id` (`medical_record_id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_attachments_medical_record` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Migration: Move existing medical history data from medical_records to medical_history
-- First, backup the existing data
CREATE TABLE IF NOT EXISTS `medical_records_backup` AS SELECT * FROM `medical_records`;

-- Insert existing history data into new medical_history table
INSERT INTO `medical_history` (`patient_id`, `doctor_id`, `history_type`, `history_details`, `status`, `created_at`, `updated_at`)
SELECT 
    `patient_id`,
    `doctor_id`,
    CASE 
        WHEN `history_type` IN ('allergies','medications','past_history','immunization','procedures','substance','family','menstrual','sexual','obstetric','growth') 
        THEN `history_type`
        ELSE 'past_history'
    END as `history_type`,
    `history_details`,
    COALESCE(`status`, 'active') as `status`,
    `created_at`,
    `updated_at`
FROM `medical_records` 
WHERE `history_type` IS NOT NULL AND `history_type` != '';

-- 5. Clean up old medical_records table and recreate with new structure
-- Drop the old table (after backup)
-- DROP TABLE `medical_records`;

-- Recreate with new structure (this will be done by the CREATE TABLE above)
-- The new medical_records table will be empty and ready for actual medical records

-- 6. Create indexes for better performance
CREATE INDEX `idx_medical_history_patient_type` ON `medical_history` (`patient_id`, `history_type`);
CREATE INDEX `idx_medical_records_patient_date` ON `medical_records` (`patient_id`, `visit_date`);
CREATE INDEX `idx_medical_records_doctor_date` ON `medical_records` (`doctor_id`, `visit_date`);

-- 7. Create views for easier querying
CREATE OR REPLACE VIEW `patient_medical_history_view` AS
SELECT 
    mh.*,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
    u.specialization as doctor_specialization
FROM `medical_history` mh
JOIN `patients` p ON mh.patient_id = p.id
JOIN `users` u ON mh.doctor_id = u.id
WHERE mh.status = 'active';

CREATE OR REPLACE VIEW `patient_medical_records_view` AS
SELECT 
    mr.*,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
    u.specialization as doctor_specialization
FROM `medical_records` mr
JOIN `patients` p ON mr.patient_id = p.id
JOIN `users` u ON mr.doctor_id = u.id
WHERE mr.status != 'cancelled'; 