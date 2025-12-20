# Patient Approval System - Quick Reference Card

## 🚀 System Status: ✅ FULLY IMPLEMENTED

---

## 📋 Quick Links

| Function | URL |
|----------|-----|
| **Patient Registration** | `http://localhost/mhavis/mhavis/patient_registration.php` |
| **Admin Approvals** | `http://localhost/mhavis/mhavis/admin_patient_registrations.php` |
| **Patient Login** | `http://localhost/mhavis/mhavis/patient_login.php` |
| **System Verification** | `http://localhost/mhavis/mhavis/verify_system_setup.php` |
| **Process Email Queue** | `http://localhost/mhavis/mhavis/process_notifications.php` |

---

## ⚙️ Setup Checklist (5 Minutes)

### 1. Database Setup
```sql
-- Import tables (if not already done)
-- Via phpMyAdmin: Import file 'sql/patient_module_tables.sql'
-- Or via command line:
mysql -u root mhavis < sql/patient_module_tables.sql
```

### 2. Email Configuration
Edit `process_notifications.php` (lines 8-13):
```php
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
```

**Gmail App Password:**
1. Enable 2FA on Google account
2. Visit: https://myaccount.google.com/apppasswords
3. Generate password for "Mail"
4. Use the 16-character password

### 3. Test the System
1. Register a test patient → `patient_registration.php`
2. Login as admin → Approve the registration
3. Process email queue → Visit `process_notifications.php`
4. Login as patient → Check notifications in dashboard

---

## 🔄 Complete Workflow

```
PATIENT REGISTERS
    ↓
Admin sees pending request in admin_patient_registrations.php
    ↓
Admin clicks "Approve" or "Reject" + adds notes
    ↓
System creates patient account (if approved)
    ↓
Email queued → sent to patient
    ↓
In-app notification created
    ↓
Patient logs in → sees notification in dashboard
```

---

## 📊 Database Tables

| Table | Purpose |
|-------|---------|
| `patient_registration_requests` | Stores registration requests |
| `patient_users` | Patient login accounts |
| `patients` | Patient medical records |
| `notifications` | In-app notifications |
| `email_queue` | Queued emails |

---

## 🔍 Quick Checks

### Check Pending Requests
```sql
SELECT first_name, last_name, email, created_at 
FROM patient_registration_requests 
WHERE status = 'Pending';
```

### Check Email Queue
```sql
SELECT status, COUNT(*) 
FROM email_queue 
GROUP BY status;
```

### Check Notifications
```sql
SELECT n.title, n.is_read, pu.username 
FROM notifications n
JOIN patient_users pu ON n.recipient_id = pu.id
WHERE n.recipient_type = 'Patient'
ORDER BY n.created_at DESC 
LIMIT 10;
```

---

## 🎯 Key Files

| File | Purpose |
|------|---------|
| `patient_registration.php` | Patient registration form |
| `admin_patient_registrations.php` | Admin approval interface |
| `patient_dashboard.php` | Patient dashboard with notifications |
| `process_notifications.php` | Email processor |
| `config/patient_auth.php` | Authentication functions |

---

## 🐛 Common Issues

### Emails not sending?
- Check `email_queue` table for failed emails
- Verify SMTP credentials in `process_notifications.php`
- Visit `process_notifications.php` to manually process queue

### Notifications not showing?
- Clear browser cache
- Check `notifications` table exists
- Verify patient_user_id matches in database

### Can't approve registrations?
- Check browser console (F12) for errors
- Verify PHP error logs: `C:\xampp\php\logs\error.log`
- Ensure all database tables exist

---

## 📧 Email Templates

### Approval Email
```
Subject: Registration Approved — You Can Now Log In

Hi [Patient Name],

Your registration has been approved by the clinic admin. 
You can now log in to your account and start using the system.

Best regards,
Mhavis Medical & Diagnostic Center
```

### Rejection Email
```
Subject: Registration Status - Mhavis Medical & Diagnostic Center

Dear [Patient Name],

Thank you for your interest in registering with Mhavis Medical & Diagnostic Center.

Unfortunately, we cannot approve your registration at this time.

Reason: [Admin Notes]

If you have any questions, please contact us.

Best regards,
Mhavis Medical & Diagnostic Center
```

---

## 🔔 Notification Types

| Type | Icon | Color | Description |
|------|------|-------|-------------|
| `Registration_Approved` | ✅ user-check | Green | Account approved |
| `Registration_Rejected` | ❌ user-times | Red | Account rejected |
| `Appointment_Approved` | 📅 calendar-check | Green | Appointment confirmed |
| `Appointment_Rejected` | ⛔ times-circle | Red | Appointment declined |
| `Appointment_Reminder` | ⏰ clock | Yellow | Upcoming appointment |
| `Medical_Record_Updated` | 📝 file-medical | Blue | Records updated |

---

## 🛠️ Admin Actions

### Approve Registration
1. Go to `admin_patient_registrations.php`
2. Find pending request
3. Click "Approve"
4. Add optional notes
5. Click "Confirm"

**Result:**
- Patient account created
- Email sent to patient
- Notification added to patient dashboard

### Reject Registration
1. Go to `admin_patient_registrations.php`
2. Find pending request
3. Click "Reject"
4. Add rejection reason in notes (required)
5. Click "Confirm"

**Result:**
- Registration request marked as rejected
- Email sent with rejection reason
- No patient account created

---

## 📱 Patient Features

### After Approval
- Login at `patient_login.php`
- View notifications in dashboard
- Book appointments
- View medical records
- View prescriptions
- Update profile

### Notification Actions
- ✓ Mark as read
- 🗑️ Delete notification
- ✓✓ Mark all as read

---

## 🔒 Security Features

- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Login attempt tracking
- ✅ Account lockout (after failed attempts)
- ✅ Session management
- ✅ Role-based access control

---

## 📈 Monitoring

### Daily Checks
1. Pending registrations → `admin_patient_registrations.php`
2. Failed emails → Check `email_queue` where status='Failed'
3. Unread notifications → Patient dashboard

### Weekly Reports
```sql
-- Approval rate this week
SELECT 
    status,
    COUNT(*) as count
FROM patient_registration_requests
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY status;
```

---

## 🎓 Training Guide

### For Admins
1. Review pending registrations daily
2. Check patient information carefully
3. Add clear notes when rejecting
4. Monitor email delivery status

### For Patients
1. Fill registration form completely
2. Use valid email address
3. Wait for admin approval (usually 24 hours)
4. Check email and dashboard for approval
5. Login and start using the system

---

## 🆘 Emergency Contacts

| Issue | Solution |
|-------|----------|
| System down | Check XAMPP services (Apache, MySQL) |
| Database error | Verify `mhavis` database exists |
| Email not working | Check SMTP credentials |
| Can't login | Reset password or check account status |

---

## 📞 Support

**Documentation:**
- Full guide: `PATIENT_APPROVAL_SYSTEM_GUIDE.md`
- System verification: `verify_system_setup.php`

**Logs:**
- PHP errors: `C:\xampp\php\logs\error.log`
- Apache errors: `C:\xampp\apache\logs\error.log`
- Browser console: Press F12 in browser

---

**Last Updated:** October 31, 2025  
**Status:** ✅ System Ready  
**Version:** 1.0

---

## 🎉 You're All Set!

Your patient approval system is **fully functional**. Just configure email settings and start using it!

**Need help?** Run the verification script:
```
http://localhost/mhavis/mhavis/verify_system_setup.php
```


