-- Patient User Module Database Tables
-- This file contains the necessary tables for the patient user module

-- Patient Users table (for patient authentication)
CREATE TABLE IF NOT EXISTS patient_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    status ENUM('Pending', 'Active', 'Suspended', 'Rejected') DEFAULT 'Pending',
    verification_token VARCHAR(255),
    verification_expires DATETIME,
    last_login DATETIME,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_patient_email (patient_id, email)
);

-- Patient Registration Requests table
CREATE TABLE IF NOT EXISTS patient_registration_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    date_of_birth DATE NOT NULL,
    sex ENUM('Male', 'Female', 'Other') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    emergency_contact_name VARCHAR(100) NOT NULL,
    emergency_contact_phone VARCHAR(20) NOT NULL,
    blood_type VARCHAR(5),
    allergies TEXT,
    medical_history TEXT,
    patient_type ENUM('New', 'Existing') NOT NULL,
    existing_patient_id INT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    admin_notes TEXT,
    processed_by INT NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (existing_patient_id) REFERENCES patients(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Appointment Requests table
CREATE TABLE IF NOT EXISTS appointment_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_user_id INT NOT NULL,
    doctor_id INT NOT NULL,
    department_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Rescheduled') DEFAULT 'Pending',
    admin_notes TEXT,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    appointment_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_user_id) REFERENCES patient_users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_type ENUM('Patient', 'Admin', 'Doctor') NOT NULL,
    recipient_id INT NOT NULL,
    type ENUM('Registration_Approved', 'Registration_Rejected', 'Appointment_Approved', 'Appointment_Rejected', 'Appointment_Reminder', 'Appointment_Rescheduled') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_via ENUM('Email', 'System') NOT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES patient_users(id) ON DELETE CASCADE
);

-- Email Queue table for background processing
CREATE TABLE IF NOT EXISTS email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    to_email VARCHAR(100) NOT NULL,
    to_name VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    body_type ENUM('text', 'html') DEFAULT 'html',
    status ENUM('Pending', 'Sent', 'Failed') DEFAULT 'Pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt DATETIME NULL,
    error_message TEXT NULL,
    scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patient Login Sessions table
CREATE TABLE IF NOT EXISTS patient_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_user_id) REFERENCES patient_users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
);

-- Add indexes for better performance
CREATE INDEX idx_patient_users_status ON patient_users(status);
CREATE INDEX idx_patient_users_email ON patient_users(email);
CREATE INDEX idx_registration_requests_status ON patient_registration_requests(status);
CREATE INDEX idx_appointment_requests_status ON appointment_requests(status);
CREATE INDEX idx_notifications_recipient ON notifications(recipient_type, recipient_id);
CREATE INDEX idx_notifications_unread ON notifications(is_read, created_at);
CREATE INDEX idx_email_queue_status ON email_queue(status, scheduled_at);
