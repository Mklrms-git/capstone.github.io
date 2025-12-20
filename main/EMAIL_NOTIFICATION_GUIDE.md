# Email Notification System for Patient Appointment Approvals

## Overview
This guide explains the enhanced email notification system that sends emails to patients when admins approve or reject their appointment booking requests.

## Features Implemented

### 1. **Dual Notification System**
When an admin approves/rejects a patient booking request, the patient receives:
- ✉️ **Email Notification** - Sent to their registered email address
- 📱 **In-App Notification** - Displayed in their patient dashboard

### 2. **Professional HTML Email Templates**

#### Approval Email Features:
- ✓ **Professional Design** - Clean, modern HTML layout with color-coded sections
- 📅 **Complete Appointment Details** - Doctor name, date, time, and reason for visit
- ⏰ **Important Reminders** - Highlighted reminder to arrive 15 minutes early
- 🎨 **Color Coding** - Green header for positive approval notification

#### Rejection Email Features:
- 📧 **Empathetic Messaging** - Professional yet compassionate wording
- 📝 **Admin Notes** - Displays the reason for rejection (if provided)
- 💡 **Next Steps** - Guides patient on how to submit a new request
- 🎨 **Color Coding** - Red header for status update, blue section for next steps

### 3. **Immediate Email Delivery**
- Emails are **queued** in the database
- **Automatically processed** and sent immediately upon approval/rejection
- No delay between admin action and patient notification

### 4. **Email Queue Management**
- Uses `email_queue` database table
- Tracks email status: Pending, Sent, Failed
- Retry mechanism for failed emails
- Logging for debugging

## How It Works

### Workflow for Appointment Approval:

```
1. Admin clicks "Approve" button
   ↓
2. System creates appointment record
   ↓
3. Email notification is queued
   ↓
4. Email is immediately processed and sent
   ↓
5. In-app notification is created
   ↓
6. Patient receives email + sees notification in dashboard
```

### Workflow for Appointment Rejection:

```
1. Admin clicks "Reject" button
   ↓
2. System updates request status
   ↓
3. Rejection email is queued
   ↓
4. Email is immediately processed and sent
   ↓
5. In-app notification is created
   ↓
6. Patient receives email + sees notification in dashboard
```

## Files Modified

### 1. `admin_appointment_requests.php`
**Location:** Lines 131-192 (Approval), Lines 256-319 (Rejection)

**Changes:**
- ✅ Added HTML email templates with professional styling
- ✅ Integrated immediate email queue processing
- ✅ Enhanced email content with formatted dates and times
- ✅ Added visual indicators (icons, color coding)
- ✅ Included contextual information for patients

**Key Functions:**
```php
// Queue email notification
sendEmailNotification($email, $name, $subject, $body, 'html');

// Process email queue immediately
processEmailQueue();

// Create in-app notification
createNotification('Patient', $patient_user_id, $type, $title, $message, 'System');
```

### 2. `process_notifications.php`
**Already Exists** - Handles email queue processing

**Key Functions:**
- `processEmailQueue()` - Processes pending emails
- `sendEmail()` - Sends emails via PHPMailer/SMTP

### 3. `config/patient_auth.php`
**Already Exists** - Contains notification helper functions

**Key Functions:**
- `sendEmailNotification()` - Queues emails in database
- `createNotification()` - Creates in-app notifications

## Email Configuration

Email settings are configured in `process_notifications.php`:

```php
SMTP_HOST: smtp.gmail.com
SMTP_PORT: 587
SMTP_USERNAME: pjsbandal2004@gmail.com
SMTP_PASSWORD: grbmwivsfuvytmey
SMTP_FROM_EMAIL: pjsbandal2004@gmail.com
SMTP_FROM_NAME: Mhavis Medical & Diagnostic Center
```

## Database Tables

### `email_queue` Table
Stores queued emails for processing:
- `id` - Primary key
- `to_email` - Recipient email
- `to_name` - Recipient name
- `subject` - Email subject
- `body` - Email content (HTML or text)
- `body_type` - 'html' or 'text'
- `status` - 'Pending', 'Sent', 'Failed'
- `attempts` - Number of send attempts
- `created_at` - When queued
- `sent_at` - When successfully sent

### `notifications` Table
Stores in-app notifications:
- `id` - Primary key
- `recipient_type` - 'Patient' or 'Admin'
- `recipient_id` - User ID
- `type` - Notification type
- `title` - Notification title
- `message` - Notification content
- `sent_via` - 'Email' or 'System'
- `is_read` - Read status
- `created_at` - Timestamp

## Testing the System

### Test Approval Flow:
1. Log in as a patient
2. Submit an appointment request
3. Log out and log in as admin
4. Go to "Appointment Request Management"
5. Click "Approve" on a pending request
6. Verify:
   - ✓ Success message appears
   - ✓ Email is sent to patient's email
   - ✓ Patient sees notification in dashboard
   - ✓ Email contains all appointment details

### Test Rejection Flow:
1. Submit another appointment request as patient
2. Log in as admin
3. Click "Reject" on the request
4. Add admin notes explaining reason
5. Verify:
   - ✓ Rejection email sent to patient
   - ✓ Admin notes included in email
   - ✓ Patient sees notification in dashboard
   - ✓ Email has professional, empathetic tone

## Email Template Preview

### Approval Email Structure:
```
┌─────────────────────────────────┐
│  ✓ Appointment Approved!        │  (Green Header)
├─────────────────────────────────┤
│ Dear [Patient Name],            │
│                                 │
│ Great news! Your appointment    │
│ request has been approved...    │
│                                 │
│ ┌─────────────────────────┐    │
│ │ 📅 Appointment Details  │    │  (Gray Box)
│ │ Doctor: Dr. [Name]      │    │
│ │ Date: [Date]            │    │
│ │ Time: [Time]            │    │
│ │ Reason: [Reason]        │    │
│ └─────────────────────────┘    │
│                                 │
│ ┌─────────────────────────┐    │
│ │ ⏰ Please arrive 15     │    │  (Yellow Warning Box)
│ │ minutes early...        │    │
│ └─────────────────────────┘    │
│                                 │
│ Best regards,                   │
│ Mhavis Medical & Diagnostic...  │
└─────────────────────────────────┘
```

### Rejection Email Structure:
```
┌─────────────────────────────────┐
│ Appointment Request Status...   │  (Red Header)
├─────────────────────────────────┤
│ Dear [Patient Name],            │
│                                 │
│ Thank you for your request...   │
│ Unfortunately, we cannot...     │
│                                 │
│ ┌─────────────────────────┐    │
│ │ Reason: [Admin Notes]   │    │  (Red Box)
│ └─────────────────────────┘    │
│                                 │
│ ┌─────────────────────────┐    │
│ │ 💡 What's Next?         │    │  (Blue Info Box)
│ │ You can submit a new... │    │
│ └─────────────────────────┘    │
│                                 │
│ Best regards,                   │
│ Mhavis Medical & Diagnostic...  │
└─────────────────────────────────┘
```

## Troubleshooting

### Email Not Sending?
1. **Check email queue:**
   ```sql
   SELECT * FROM email_queue ORDER BY created_at DESC LIMIT 10;
   ```
   - Look for status 'Failed' or 'Pending'

2. **Check error logs:**
   - PHP error log: Look for PHPMailer errors
   - Database: Check `error_message` column in `email_queue`

3. **Verify SMTP settings:**
   - Ensure Gmail account allows less secure apps
   - Use App Password if 2FA is enabled
   - Check SMTP credentials in `process_notifications.php`

4. **Test email function directly:**
   ```php
   require_once 'process_notifications.php';
   processEmailQueue();
   ```

### Notification Not Appearing in Dashboard?
1. **Check notifications table:**
   ```sql
   SELECT * FROM notifications WHERE recipient_id = [patient_user_id] ORDER BY created_at DESC;
   ```

2. **Verify patient is logged in:**
   - Session variable `patient_user_id` must be set

3. **Check header.php:**
   - Ensure notification dropdown is implemented

## Security Considerations

- ✅ **SQL Injection Prevention:** All queries use prepared statements
- ✅ **XSS Protection:** HTML is properly formatted and escaped
- ✅ **SMTP Security:** Uses TLS encryption (port 587)
- ✅ **Error Handling:** Try-catch blocks prevent system crashes
- ✅ **Logging:** Errors logged without exposing sensitive data

## Future Enhancements

Potential improvements:
- 📧 **Email Attachments** - Attach appointment confirmation PDF
- 📅 **Calendar Integration** - Add .ics calendar file
- 📊 **Notification Analytics** - Track email open rates
- ⏰ **Reminder Emails** - Send reminder before appointment
- 🌐 **Multi-language Support** - Translate emails based on patient preference

## Summary

The email notification system is now fully functional and provides:
- ✓ **Immediate email delivery** when admin approves/rejects appointments
- ✓ **Professional HTML emails** with clear, formatted information
- ✓ **Dual notifications** (email + in-app) for better patient communication
- ✓ **Robust error handling** to prevent system failures
- ✓ **Queue management** for reliable email delivery

Patients will now receive timely, professional email notifications for all appointment status changes, improving communication and patient experience.

