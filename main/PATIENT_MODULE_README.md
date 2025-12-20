# Patient User Module - Mhavis Medical & Diagnostic Center

## Overview
The Patient User Module is a comprehensive system that allows patients to register, log in, and manage their healthcare appointments through a dedicated patient portal. The system includes admin verification, appointment booking, and automated notifications.

## Features

### Patient Registration
- **Registration Form**: Complete patient registration with personal, contact, and medical information
- **Patient Type Selection**: Choose between "New Patient" or "Existing Patient"
- **Admin Verification**: All registrations require admin approval before activation
- **Email Notifications**: Automatic email notifications for registration status

### Patient Authentication
- **Secure Login**: Username/password authentication for patients
- **Session Management**: Secure session handling with timeout protection
- **Login Attempts**: Protection against brute force attacks
- **Account Status**: Support for pending, active, suspended, and rejected accounts

### Patient Dashboard
- **Real-time Calendar**: FullCalendar integration showing appointment schedules
- **Appointment Management**: View upcoming appointments and appointment history
- **Quick Actions**: Easy access to book appointments and view records
- **Notifications**: Real-time notification system for important updates

### Appointment Booking
- **Department Selection**: Choose from available medical departments
- **Doctor Selection**: Select from doctors in the chosen department
- **Time Slot Availability**: Real-time checking of available appointment times
- **Appointment Requests**: Submit appointment requests for admin approval
- **Urgency Levels**: Specify urgency level (Low, Medium, High)

### Admin Management
- **Registration Approval**: Admin interface to approve/reject patient registrations
- **Appointment Approval**: Admin interface to approve/reject appointment requests
- **Patient Management**: View and manage patient accounts
- **Notification Management**: Monitor and manage system notifications

### Notification System
- **Email Notifications**: Automated email sending for various events
- **System Notifications**: In-app notification system
- **Queue Management**: Background processing of notifications

## Installation

### 1. Database Setup
Run the database setup script to create necessary tables:
```bash
php setup_patient_module.php
```

Or manually execute the SQL file:
```sql
-- Execute the contents of sql/patient_module_tables.sql
```

### 2. Configuration
Update the following configuration files:

#### Email Configuration (process_notifications.php)
```php
define('SMTP_HOST', 'your-smtp-host');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@mhavis.com');
define('SMTP_FROM_NAME', 'Mhavis Medical & Diagnostic Center');
```


### 3. Cron Job Setup
Set up a daily cron job to process notifications:
```bash
# Add to crontab (crontab -e)
0 9 * * * /path/to/your/project/cron_notifications.sh
```

## File Structure

### Core Files
- `patient_registration.php` - Patient registration form
- `patient_login.php` - Patient login page
- `patient_dashboard.php` - Main patient dashboard
- `patient_logout.php` - Patient logout handler

### Admin Management
- `admin_patient_registrations.php` - Admin interface for registration approval
- `admin_appointment_requests.php` - Admin interface for appointment approval

### API Endpoints
- `get_patient_appointments.php` - Get patient appointments for calendar
- `get_doctors_by_department.php` - Get doctors by department
- `get_available_time_slots.php` - Get available appointment times
- `submit_appointment_request.php` - Submit appointment request

### Configuration
- `config/patient_auth.php` - Patient authentication functions
- `process_notifications.php` - Notification processing system
- `setup_patient_module.php` - Database setup script

### Database
- `sql/patient_module_tables.sql` - Database schema for patient module

## Database Tables

### Core Tables
- `patient_users` - Patient authentication accounts
- `patient_registration_requests` - Pending registration requests
- `appointment_requests` - Pending appointment requests
- `notifications` - System notifications
- `patient_sessions` - Patient login sessions

### Queue Tables
- `email_queue` - Email notification queue

## Usage

### Patient Registration Flow
1. Patient visits `patient_registration.php`
2. Fills out registration form
3. Admin reviews registration in `admin_patient_registrations.php`
4. Admin approves/rejects registration
5. Patient receives email notification
6. Approved patients can log in via `patient_login.php`

### Appointment Booking Flow
1. Patient logs in to dashboard
2. Clicks "Book Appointment"
3. Selects department and doctor
4. Chooses preferred date and time
5. Submits appointment request
6. Admin reviews request in `admin_appointment_requests.php`
7. Admin approves/rejects request
8. Patient receives notification of approval/rejection

### Notification System
- **Registration Approval**: Email sent when registration is approved/rejected
- **Appointment Approval**: Email sent when appointment is approved
- **Appointment Reminders**: Automated reminders 1-2 days before appointment
- **System Notifications**: In-app notifications for various events

## Security Features

### Authentication
- Password hashing using PHP's `password_hash()`
- Session token generation and validation
- Login attempt limiting
- Account lockout after failed attempts

### Data Protection
- Input sanitization and validation
- SQL injection prevention with prepared statements
- XSS protection with proper output escaping
- CSRF protection through session tokens

### Access Control
- Role-based access control
- Patient-specific data isolation
- Admin-only access to management interfaces

## Customization

### Email Templates
Modify email content in the notification functions:
- Registration approval emails
- Appointment approval emails
- Appointment reminder emails

### UI Customization
- Modify CSS in `assets/css/style.css`
- Update dashboard layout in `patient_dashboard.php`
- Customize form styling in registration and login pages

## Troubleshooting

### Common Issues

#### Email Notifications Not Working
1. Check SMTP configuration in `process_notifications.php`
2. Verify email credentials and permissions
3. Check server mail configuration
4. Review email queue table for failed messages

3. Ensure proper API credentials

#### Calendar Not Loading
1. Check FullCalendar CDN links
2. Verify appointment data format
3. Check browser console for JavaScript errors

#### Login Issues
1. Verify database connection
2. Check session configuration
3. Review login attempt limits

### Log Files
- Check PHP error logs for application errors
- Monitor `/var/log/mhavis_notifications.log` for cron job execution
- Review database logs for query issues

## Support

For technical support or questions about the Patient User Module:
1. Check the troubleshooting section above
2. Review the code comments for implementation details
3. Test with sample data to verify functionality
4. Check database table structures for data integrity

## Version History

### v1.0.0
- Initial release of Patient User Module
- Complete patient registration and authentication system
- Appointment booking and management
- Admin approval workflows
- Email notification system
- Real-time calendar integration
