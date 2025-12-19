-- ================================================
-- SQL Script to Create Doctor Departments Table
-- ================================================
-- This table stores the many-to-many relationship
-- between doctors and departments with professional
-- information specific to each department assignment
-- ================================================

-- Drop table if exists (for clean reinstall - comment out if you want to preserve data)
-- DROP TABLE IF EXISTS `doctor_departments`;

-- Create doctor_departments table
CREATE TABLE IF NOT EXISTS `doctor_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL COMMENT 'References users.id where role=Doctor',
  `department_id` int(11) NOT NULL COMMENT 'References departments.id',
  `specialization` varchar(100) DEFAULT NULL COMMENT 'Specialization for this department',
  `prc_number` varchar(50) DEFAULT NULL COMMENT 'PRC number for this department',
  `license_type` varchar(50) DEFAULT NULL COMMENT 'License type for this department',
  `prc_id_document` varchar(255) DEFAULT NULL COMMENT 'PRC ID document path for this department',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctor_department_unique` (`doctor_id`, `department_id`),
  KEY `idx_doctor_id` (`doctor_id`),
  KEY `idx_department_id` (`department_id`),
  CONSTRAINT `fk_doctor_departments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doctor_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrate existing doctor-department relationships
-- This assumes doctors.department_id and users.department_id exist
INSERT INTO `doctor_departments` (`doctor_id`, `department_id`, `specialization`, `prc_number`, `license_type`)
SELECT 
    u.id as doctor_id,
    COALESCE(d.department_id, u.department_id) as department_id,
    COALESCE(d.specialization, u.specialization) as specialization,
    COALESCE(u.prc_number, d.license_number) as prc_number,
    u.license_type as license_type
FROM users u
LEFT JOIN doctors d ON d.user_id = u.id
WHERE u.role = 'Doctor' 
  AND COALESCE(d.department_id, u.department_id) IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM doctor_departments dd 
    WHERE dd.doctor_id = u.id AND dd.department_id = COALESCE(d.department_id, u.department_id)
  );

