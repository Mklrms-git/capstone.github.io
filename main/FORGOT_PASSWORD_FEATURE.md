# Forgot Password Feature - Implementation Guide

## Overview
A complete "Forgot Password" feature has been implemented for both admin/doctor users and patient users. The feature uses OTP (One-Time Password) verification via email for secure password reset.

## Features Implemented

### 1. Email Entry Forms
- **Admin/Doctor**: `forgot_password.php` - Accessible from `login.php`
- **Patient**: `patient_forgot_password.php` - Accessible from `patient_login.php`

### 2. OTP Verification Pages
- **Admin/Doctor**: `verify_otp.php`
- **Patient**: `patient_verify_otp.php`

### 3. Password Reset Pages
- **Admin/Doctor**: `reset_password.php`
- **Patient**: `patient_reset_password.php`

### 4. Database Table
- **Table**: `password_reset_otp`
- **Auto-creation**: The table is automatically created when accessing the forgot password pages if it doesn't exist

## User Flow

### For Admin/Doctor Users:
1. Click "Forgot Password?" link on `login.php`
2. Enter email address on `forgot_password.php`
3. Receive OTP code via email
4. Enter OTP code on `verify_otp.php`
5. Enter new password and confirm password on `reset_password.php`
6. Redirected to login page

### For Patient Users:
1. Click "Forgot Password?" link on `patient_login.php`
2. Enter email address on `patient_forgot_password.php`
3. Receive OTP code via email
4. Enter OTP code on `patient_verify_otp.php`
5. Enter new password and confirm password on `patient_reset_password.php`
6. Redirected to patient login page

## Security Features

1. **OTP Expiration**: OTP codes expire after 15 minutes
2. **One-Time Use**: Each OTP can only be used once
3. **Email Validation**: Validates email format before sending OTP
4. **Password Requirements**: Minimum 8 characters required
5. **Password Confirmation**: Ensures passwords match before reset
6. **Session Security**: Uses session variables to track reset process
7. **Email Enumeration Protection**: Doesn't reveal if email exists in system

## Database Schema

The `password_reset_otp` table structure:
```sql
CREATE TABLE password_reset_otp (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    user_type ENUM('admin', 'patient') NOT NULL,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_otp (email, otp_code),
    INDEX idx_expires (expires_at)
);
```

## Email Template

The OTP email includes:
- Professional HTML design with Mhavis branding
- Large, easy-to-read 6-digit OTP code
- Expiration warning (15 minutes)
- Security notice

## Setup Instructions

### Automatic Setup (Recommended)
The database table is automatically created when you first access either forgot password page. No manual setup required!

### Manual Setup (Optional)
If you prefer to create the table manually, run:
```sql
-- Via phpMyAdmin or MySQL command line
source sql/password_reset_otp.sql;
```

Or execute the SQL directly:
```sql
CREATE TABLE IF NOT EXISTS password_reset_otp (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    user_type ENUM('admin', 'patient') NOT NULL,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_otp (email, otp_code),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## Testing Checklist

- [ ] Access forgot password from admin login page
- [ ] Access forgot password from patient login page
- [ ] Enter valid email address
- [ ] Receive OTP code via email
- [ ] Verify OTP code
- [ ] Reset password with matching passwords
- [ ] Reset password with non-matching passwords (should fail)
- [ ] Reset password with short password (should fail)
- [ ] Try expired OTP code (should fail)
- [ ] Try used OTP code again (should fail)
- [ ] Login with new password

## Files Created/Modified

### New Files:
- `forgot_password.php` - Admin/doctor email entry
- `patient_forgot_password.php` - Patient email entry
- `verify_otp.php` - Admin/doctor OTP verification
- `patient_verify_otp.php` - Patient OTP verification
- `reset_password.php` - Admin/doctor password reset
- `patient_reset_password.php` - Patient password reset
- `sql/password_reset_otp.sql` - Database table creation script

### Modified Files:
- `patient_login.php` - Added "Forgot Password?" link

## Notes

- The feature uses the existing email system (`process_notifications.php`) with PHPMailer
- OTP codes are 6-digit numeric codes
- Password reset links are accessible without login
- All pages include proper navigation and branding
- Form validation is handled both client-side and server-side

