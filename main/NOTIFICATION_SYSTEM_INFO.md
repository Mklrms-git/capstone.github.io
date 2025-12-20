# Patient Appointment Notification System

## Overview
The Mhavis Medical & Diagnostic Center has a comprehensive notification system that automatically notifies patients when their appointment requests are approved or rejected by the admin.

## Features

### 1. **In-App Notifications (System)**
When an admin approves or rejects an appointment:
- A notification is created in the `notifications` table
- Patients can view notifications in their dashboard under the "Notifications" section
- Notifications show with icons indicating the type (approved/rejected/reminder)
- Unread notifications are highlighted with a blue background
- Patients can mark notifications as read or delete them

### 2. **Email Notifications**
- Emails are automatically queued when appointments are approved/rejected
- Emails are sent via SMTP (Gmail by default)
- Email queue ensures reliable delivery with retry mechanism
- Emails include appointment details: doctor name, date, time, and reason

## How It Works

### When Admin Approves an Appointment:

1. **Appointment Creation**
   - Admin approves the appointment request
   - System creates an appointment record in the `appointments` table
   - Updates the appointment request status to "Approved"

2. **Email Notification**
   - An email is queued with appointment details
   - Subject: "Appointment Approved - Mhavis Medical Center"
   - Contains: Doctor name, date, time, reason, and instructions

3. **In-App Notifications (2 types)**
   - **Email Tracking Notification**: Records that an email was sent
   - **System Notification**: Shows in patient dashboard with full details

4. **Patient Dashboard Display**
   - Notifications appear in the Notifications section
   - Shows icon, title, message, and timestamp
   - Unread notifications are highlighted
   - Patient can mark as read or delete

### When Admin Rejects an Appointment:

1. **Request Update**
   - Status changed to "Rejected"
   - Admin notes are saved

2. **Email Notification**
   - Rejection email queued with reason
   - Subject: "Appointment Request Status - Mhavis Medical Center"

3. **In-App Notifications**
   - Email tracking notification
   - System notification with rejection details and admin notes

## File Structure

### Core Files:
- `admin_appointment_requests.php` - Admin interface for approving/rejecting appointments (lines 131-256)
- `patient_dashboard.php` - Patient dashboard with notification display (lines 516-617)
- `config/patient_auth.php` - Notification functions (lines 52-102)
- `process_notifications.php` - Email queue processor

### Notification Management:
- `mark_notification_read.php` - Mark single notification as read
- `mark_all_notifications_read.php` - Mark all notifications as read
- `delete_notification.php` - Delete notification

## Database Tables

### notifications
```sql
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_type ENUM('Patient', 'Admin', 'Doctor') NOT NULL,
    recipient_id INT NOT NULL,  -- patient_users.id
    type ENUM('Registration_Approved', 'Registration_Rejected', 
              'Appointment_Approved', 'Appointment_Rejected', 
              'Appointment_Reminder', 'Appointment_Rescheduled',
              'Medical_Record_Updated') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_via ENUM('Email', 'System') NOT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### email_queue
```sql
CREATE TABLE email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    to_email VARCHAR(100) NOT NULL,
    to_name VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    body_type ENUM('text', 'html') DEFAULT 'html',
    status ENUM('Pending', 'Sent', 'Failed') DEFAULT 'Pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Key Functions

### createNotification($recipient_type, $recipient_id, $type, $title, $message, $sent_via)
- Creates a notification record in the database
- Used for both email tracking and in-app notifications

### sendEmailNotification($to_email, $to_name, $subject, $body, $body_type)
- Queues an email for sending
- Emails are processed by `process_notifications.php`

## Email Configuration

Located in `process_notifications.php`:
```php
SMTP_HOST: 'smtp.gmail.com'
SMTP_PORT: 587
SMTP_USERNAME: 'noreply.mhavis@gmail.com'
SMTP_PASSWORD: '[App Password]'
```

## Processing Notifications

### Manual Processing:
```bash
php process_notifications.php
```

### Automated Processing (Recommended):
Set up a cron job to run every 5 minutes:
```cron
*/5 * * * * php /path/to/mhavis/process_notifications.php
```

Or use Windows Task Scheduler on Windows servers.

## Recent Bug Fixes

### Fixed: Notification ID Inconsistency (October 31, 2024)
- **Issue**: Notifications were created with `patient_user_id` but queried with `patient_id`
- **Impact**: Notifications were not showing up in patient dashboard
- **Fix**: Updated all notification queries to consistently use `patient_users.id`
- **Files Modified**:
  - `patient_dashboard.php`
  - `mark_notification_read.php`
  - `mark_all_notifications_read.php`
  - `delete_notification.php`

## Testing the System

### To Test Approval Notification:
1. Log in as a patient
2. Submit an appointment request
3. Log in as admin
4. Navigate to "Appointment Request Management"
5. Approve the appointment
6. Log back in as patient
7. Check the Notifications section in the dashboard
8. Check the patient's email inbox

### Expected Results:
- ✅ Notification appears in patient dashboard
- ✅ Email received with appointment details
- ✅ Notification shows as unread (blue highlight)
- ✅ Clicking "Mark as Read" removes highlight
- ✅ Delete button removes notification

## Notification Types

| Type | Icon | Color | Description |
|------|------|-------|-------------|
| Appointment_Approved | ✓ Calendar | Green | Appointment request approved |
| Appointment_Rejected | ✗ Circle | Red | Appointment request rejected |
| Appointment_Reminder | Clock | Yellow | Upcoming appointment reminder |
| Appointment_Rescheduled | Calendar | Blue | Appointment date/time changed |
| Registration_Approved | User Check | Green | Patient registration approved |
| Registration_Rejected | User X | Red | Patient registration rejected |
| Medical_Record_Updated | File Medical | Blue | Medical records updated |

## Troubleshooting

### Notifications Not Showing in Dashboard
- ✓ Fixed: Updated notification queries to use correct patient_user_id

### Emails Not Being Sent
1. Check `email_queue` table for pending emails
2. Run `php process_notifications.php` manually
3. Check SMTP credentials in `process_notifications.php`
4. Verify Gmail "Less secure app access" or use App Password
5. Check email_queue.error_message column for errors

### Email Delivery Issues
- Use Gmail App Password instead of regular password
- Enable 2-factor authentication on Gmail
- Generate App Password from Google Account settings
- Update SMTP_PASSWORD in `process_notifications.php`

## Security Considerations

1. **Access Control**: Only patients can see their own notifications
2. **Verification**: All notification operations verify patient ownership
3. **SQL Injection**: All queries use prepared statements
4. **XSS Prevention**: All output uses `htmlspecialchars()`

## Future Enhancements

- [ ] Real-time notifications using WebSockets
- [ ] Push notifications for mobile devices
- [ ] Notification preferences (email/system toggles)
- [ ] Notification sound alerts
- [ ] Desktop browser notifications
- [ ] Digest emails (daily/weekly summary)

## Support

For issues or questions about the notification system, contact the development team.

---

**Last Updated**: October 31, 2025
**Version**: 1.1
**Status**: ✅ Active and Working


