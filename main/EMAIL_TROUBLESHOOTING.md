# 🔧 Email System Troubleshooting Guide

## Issue Fixed: Emails Not Being Sent

### ✅ What Was Wrong
The main issue was in `admin_appointment_requests.php`:
- The file was checking if `processEmailQueue()` function exists **before** loading the file that contains it
- This caused the function to never be called, so emails stayed in the queue without being sent

### ✅ What Was Fixed
1. **Added `require_once 'process_notifications.php'` at the top** of `admin_appointment_requests.php`
2. **Simplified email processing** - removed redundant function checks
3. **Added error logging** to track email queue operations
4. **Created diagnostic tool** to test the email system

---

## 🧪 Testing the Fix

### Step 1: Use the Diagnostic Tool
1. Open your browser and navigate to: `http://localhost/mhavis/test_email_system.php`
2. Review all diagnostic tests - they should all show ✓ (green checkmarks)
3. Click "Send Test Email" button
4. Check your email inbox

### Step 2: Test with Real Appointment
1. Log in as a patient
2. Submit an appointment request
3. Log in as admin
4. Go to "Appointment Request Management"
5. Click "Approve" on the request
6. Patient should receive email within seconds

### Step 3: Check Email Queue
Run this SQL query to see email status:
```sql
SELECT * FROM email_queue ORDER BY created_at DESC LIMIT 10;
```

Look for:
- `status = 'Sent'` ✓ Good - email was sent
- `status = 'Pending'` ⚠️ Email waiting to be sent
- `status = 'Failed'` ✗ Email failed - check error_message column

---

## 📋 Common Issues & Solutions

### 1. **Emails Still Pending in Queue**

**Symptom:** Emails show `status = 'Pending'` in database

**Solutions:**
- Open `test_email_system.php` and click "Process Email Queue Now"
- Check if PHPMailer is installed: Look for `includes/PHPMailer/` directory
- Run manually: `php process_notifications.php` in terminal

### 2. **PHPMailer Class Not Found**

**Symptom:** Error: "Class 'PHPMailer\PHPMailer\PHPMailer' not found"

**Solutions:**
```bash
# Option 1: Install via Composer
cd mhavis
composer require phpmailer/phpmailer

# Option 2: Download manually
# Download from https://github.com/PHPMailer/PHPMailer/releases
# Extract to includes/PHPMailer/
```

### 3. **SMTP Authentication Failed**

**Symptom:** Error: "SMTP Error: Could not authenticate"

**Solutions:**

**For Gmail:**
1. Go to Google Account settings
2. Enable "Less secure app access" (if available)
3. **OR** Use App Password (recommended):
   - Go to Security → 2-Step Verification → App passwords
   - Create new app password for "Mail"
   - Use that password in `process_notifications.php`

**Update SMTP credentials:**
```php
// In process_notifications.php
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password'); // App password, not regular password
```

### 4. **Connection Timeout**

**Symptom:** Error: "SMTP connect() failed" or timeout

**Solutions:**
- Check firewall - allow outgoing connections on port 587
- Try alternative ports:
  ```php
  // In process_notifications.php
  define('SMTP_PORT', 465); // Instead of 587
  $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // Instead of STARTTLS
  ```
- Verify internet connection
- Check if ISP blocks SMTP ports

### 5. **Emails Go to Spam**

**Symptom:** Emails delivered but in spam folder

**Solutions:**
- Use a professional email (not free Gmail/Yahoo)
- Add SPF record to domain
- Use authenticated SMTP
- Include plain text alternative
- Avoid spam trigger words

### 6. **Emails Not Appearing in Patient Dashboard**

**Symptom:** Email sent but notification not showing in dashboard

**Solutions:**
- Check notifications table:
  ```sql
  SELECT * FROM notifications WHERE recipient_type = 'Patient' ORDER BY created_at DESC;
  ```
- Verify patient is logged in (check session)
- Clear browser cache
- Check `includes/header.php` for notification dropdown

---

## 🔍 Debug Checklist

Use this checklist to diagnose issues:

- [ ] PHPMailer installed and autoloaded?
- [ ] SMTP credentials correct in `process_notifications.php`?
- [ ] `email_queue` table exists in database?
- [ ] Emails being queued (check `email_queue` table)?
- [ ] `processEmailQueue()` being called after approval?
- [ ] PHP error logs checked for errors?
- [ ] Port 587 or 465 accessible (not blocked by firewall)?
- [ ] Gmail "Less secure apps" enabled OR App Password used?
- [ ] Test email works from diagnostic tool?

---

## 📊 Monitoring Email Queue

### View Queue Status
```sql
-- See all pending emails
SELECT * FROM email_queue WHERE status = 'Pending';

-- See failed emails with reasons
SELECT id, to_email, subject, error_message, attempts 
FROM email_queue 
WHERE status = 'Failed';

-- See sent emails
SELECT * FROM email_queue WHERE status = 'Sent' ORDER BY sent_at DESC LIMIT 10;
```

### Clear Failed Emails
```sql
DELETE FROM email_queue WHERE status = 'Failed';
```

### Reset Pending Email
```sql
-- If email stuck, reset attempts
UPDATE email_queue SET attempts = 0 WHERE id = [email_id];
```

---

## 🚀 Automated Email Processing

For production, set up automated processing:

### Windows (Task Scheduler)
1. Open Task Scheduler
2. Create new task
3. Trigger: Every 5 minutes
4. Action: `C:\xampp\php\php.exe C:\xampp\htdocs\mhavis\process_notifications.php`

### Linux (Cron)
```bash
# Edit crontab
crontab -e

# Add this line
*/5 * * * * /usr/bin/php /path/to/mhavis/process_notifications.php
```

---

## 📝 Error Logs Location

### XAMPP (Windows)
- Apache: `C:\xampp\apache\logs\error.log`
- PHP: `C:\xampp\php\logs\php_error_log`

### Linux
- Apache: `/var/log/apache2/error.log`
- PHP-FPM: `/var/log/php-fpm/error.log`
- System: `/var/log/syslog`

### Check Logs
```bash
# Windows (PowerShell)
Get-Content C:\xampp\apache\logs\error.log -Tail 50

# Linux
tail -f /var/log/apache2/error.log
```

---

## 🎯 Quick Fix Summary

### Files Modified:
1. **`admin_appointment_requests.php`**
   - Line 6: Added `require_once 'process_notifications.php';`
   - Line 186-191: Simplified email processing with logging
   - Line 315-320: Simplified rejection email processing with logging

### New Files:
1. **`test_email_system.php`** - Diagnostic tool for testing emails
2. **`EMAIL_TROUBLESHOOTING.md`** - This guide
3. **`EMAIL_NOTIFICATION_GUIDE.md`** - Full documentation

---

## ✅ Verification Steps

After applying the fix:

1. ✅ Open `test_email_system.php` - all tests should pass
2. ✅ Send test email from diagnostic tool
3. ✅ Check email inbox
4. ✅ Approve a real appointment request
5. ✅ Patient receives HTML email
6. ✅ Patient sees in-app notification
7. ✅ Check logs for success messages

---

## 💡 Pro Tips

1. **Use the diagnostic tool regularly** to monitor email system health
2. **Check error logs** after each approval if emails not arriving
3. **Test with different email providers** (Gmail, Yahoo, Outlook)
4. **Monitor email_queue table** for stuck or failed emails
5. **Set up automated queue processing** for production
6. **Keep App Password secure** - don't commit to version control

---

## 🆘 Still Not Working?

If emails still not working after following this guide:

1. Run diagnostic tool: `http://localhost/mhavis/test_email_system.php`
2. Check PHP error log for specific error messages
3. Verify SMTP credentials are correct
4. Try sending test email directly from diagnostic tool
5. Check email_queue table for error messages
6. Test with a different email provider
7. Contact hosting provider about SMTP restrictions

---

## 📧 Support

For additional help:
- Check `EMAIL_NOTIFICATION_GUIDE.md` for system overview
- Review `process_notifications.php` for SMTP configuration
- Check database schema in `sql/` directory
- Review error logs for specific error messages

---

**Last Updated:** November 2025  
**Status:** ✅ Fixed and Tested

