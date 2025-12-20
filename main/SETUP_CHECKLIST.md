# Patient Approval System - Setup Checklist

**System Status:** ✅ Already Implemented  
**Your Task:** Configuration & Testing

---

## ☐ Step 1: Verify System Files (2 minutes)

Run the verification script to check all components:

```
http://localhost/mhavis/mhavis/verify_system_setup.php
```

**Expected Result:** See a report showing:
- ✅ Database connection working
- ✅ All required tables exist
- ✅ All required files present

**If errors appear:**
- [ ] Go to Step 2 (Database Setup)

---

## ☐ Step 2: Database Setup (3 minutes)

### Check if tables exist:
```sql
USE mhavis;
SHOW TABLES LIKE '%patient%';
SHOW TABLES LIKE '%notification%';
SHOW TABLES LIKE '%email%';
```

### If tables are missing, import SQL file:

**Option A: Via phpMyAdmin**
1. [ ] Open: http://localhost/phpmyadmin
2. [ ] Select `mhavis` database
3. [ ] Click "Import" tab
4. [ ] Choose file: `C:\xampp\htdocs\mhavis\mhavis\sql\patient_module_tables.sql`
5. [ ] Click "Go"
6. [ ] Verify: Should see "Import has been successfully finished"

**Option B: Via Command Line**
```bash
# Open Command Prompt (CMD)
cd C:\xampp\mysql\bin
mysql -u root mhavis < C:\xampp\htdocs\mhavis\mhavis\sql\patient_module_tables.sql
```

### Verify tables created:
```sql
SELECT COUNT(*) FROM patient_registration_requests;
SELECT COUNT(*) FROM patient_users;
SELECT COUNT(*) FROM notifications;
SELECT COUNT(*) FROM email_queue;
```

**Expected:** All queries should work without errors

---

## ☐ Step 3: Email Configuration (5 minutes)

### For Gmail (Recommended):

1. [ ] **Enable 2-Factor Authentication**
   - Go to: https://myaccount.google.com/security
   - Enable 2-Step Verification

2. [ ] **Generate App Password**
   - Go to: https://myaccount.google.com/apppasswords
   - Select "Mail" and your device
   - Click "Generate"
   - Copy the 16-character password (e.g., `abcd efgh ijkl mnop`)

3. [ ] **Update Configuration**
   - Open: `C:\xampp\htdocs\mhavis\mhavis\process_notifications.php`
   - Find lines 8-13
   - Replace with your credentials:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');        // ← Change this
define('SMTP_PASSWORD', 'abcdefghijklmnop');            // ← Change this (no spaces)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');      // ← Change this
define('SMTP_FROM_NAME', 'Mhavis Medical & Diagnostic Center');
```

4. [ ] **Save the file**

### For Other Email Providers:

**Yahoo Mail:**
- SMTP Host: `smtp.mail.yahoo.com`
- SMTP Port: `587` or `465`
- Enable "Allow apps that use less secure sign in"

**Outlook/Hotmail:**
- SMTP Host: `smtp-mail.outlook.com`
- SMTP Port: `587`
- Use your regular password

**Custom Domain:**
- Contact your hosting provider for SMTP settings

---

## ☐ Step 4: Test Patient Registration (5 minutes)

1. [ ] **Open Registration Page**
   ```
   http://localhost/mhavis/mhavis/patient_registration.php
   ```

2. [ ] **Fill Out Form**
   - First Name: `Test`
   - Last Name: `Patient`
   - Email: `your-test-email@gmail.com` (use a real email you can access)
   - Username: `testpatient`
   - Password: `Test1234`
   - Fill all other required fields

3. [ ] **Submit Form**
   - Should see: "Registration request submitted successfully!"

4. [ ] **Verify in Database**
   ```sql
   SELECT * FROM patient_registration_requests 
   WHERE email = 'your-test-email@gmail.com';
   ```
   - Should see your registration with status = 'Pending'

---

## ☐ Step 5: Test Admin Approval (5 minutes)

1. [ ] **Login as Admin**
   ```
   http://localhost/mhavis/mhavis/login.php
   ```
   - Use your admin credentials

2. [ ] **Open Registrations Management**
   ```
   http://localhost/mhavis/mhavis/admin_patient_registrations.php
   ```

3. [ ] **Review Pending Request**
   - Should see your test patient in "Pending Registration Requests"
   - Click "Approve" button

4. [ ] **View Details**
   - Modal should open showing full patient information
   - Add notes (optional): "Test approval"
   - Click "Confirm"

5. [ ] **Verify Success**
   - Should see: "Patient approved and email sent successfully."
   - Request should move to "Recently Processed Requests" section

6. [ ] **Check Database**
   ```sql
   -- Check patient user was created
   SELECT * FROM patient_users WHERE username = 'testpatient';
   
   -- Check email was queued
   SELECT * FROM email_queue ORDER BY created_at DESC LIMIT 1;
   
   -- Check notification was created
   SELECT * FROM notifications ORDER BY created_at DESC LIMIT 1;
   ```

---

## ☐ Step 6: Process Email Queue (2 minutes)

### Option A: Manual Processing (for testing)
1. [ ] **Open Email Processor**
   ```
   http://localhost/mhavis/mhavis/process_notifications.php
   ```
   - Should see: "Notifications processed."

2. [ ] **Check Email Status**
   ```sql
   SELECT * FROM email_queue 
   WHERE status = 'Sent' 
   ORDER BY created_at DESC 
   LIMIT 1;
   ```
   - Status should be 'Sent'

3. [ ] **Check Your Email**
   - Open your email inbox
   - Look for email from Mhavis Medical & Diagnostic Center
   - Subject: "Registration Approved — You Can Now Log In"

### Option B: Automatic Processing (for production)

**Windows Task Scheduler:**
1. [ ] Open Task Scheduler (search in Windows)
2. [ ] Click "Create Basic Task"
3. [ ] Name: "Mhavis Email Processor"
4. [ ] Trigger: "Daily" → Start time: 12:00 AM
5. [ ] Action: "Start a program"
6. [ ] Program: `C:\xampp\php\php.exe`
7. [ ] Arguments: `C:\xampp\htdocs\mhavis\mhavis\process_notifications.php`
8. [ ] Check "Repeat task every" → 5 minutes
9. [ ] Click Finish

---

## ☐ Step 7: Test Patient Login & Notifications (3 minutes)

1. [ ] **Open Patient Login**
   ```
   http://localhost/mhavis/mhavis/patient_login.php
   ```

2. [ ] **Login with Test Account**
   - Username: `testpatient`
   - Password: `Test1234`
   - Click "Login"

3. [ ] **Verify Dashboard Loads**
   - Should see: "Patient Dashboard"
   - Should see patient name in header

4. [ ] **Check Notifications**
   - Click "Notifications" in sidebar
   - Should see notification badge with count "1"
   - Should see notification: "Registration Approved"
   - Notification should have blue left border (unread)

5. [ ] **Test Notification Actions**
   - [ ] Click "Mark as read" button → Border should disappear
   - [ ] Refresh page → Notification should still be marked as read
   - [ ] Click delete icon → Notification should slide out and disappear

---

## ☐ Step 8: Test Rejection Flow (Optional - 5 minutes)

1. [ ] **Register Another Test Patient**
   - Email: `testpatient2@gmail.com`
   - Username: `testpatient2`

2. [ ] **Login as Admin**

3. [ ] **Open Registrations Management**

4. [ ] **Click "Reject" on New Request**

5. [ ] **Add Rejection Reason**
   - Notes: "Testing rejection flow"
   - Click "Confirm"

6. [ ] **Process Email Queue**
   ```
   http://localhost/mhavis/mhavis/process_notifications.php
   ```

7. [ ] **Check Rejection Email**
   - Subject: "Registration Status - Mhavis Medical & Diagnostic Center"
   - Should include rejection reason

8. [ ] **Verify No Patient Account Created**
   ```sql
   SELECT * FROM patient_users WHERE username = 'testpatient2';
   ```
   - Should return 0 rows

---

## ☐ Step 9: Verify All Components (2 minutes)

Run the verification script again to ensure everything is working:

```
http://localhost/mhavis/mhavis/verify_system_setup.php
```

**Expected Results:**
- ✅ All checks should pass (green)
- ✅ No critical errors
- ⚠️ Warnings are OK (e.g., "no pending requests")

---

## ☐ Step 10: Production Readiness (Optional)

### Security:
- [ ] Change default admin password
- [ ] Use strong passwords for all accounts
- [ ] Keep PHP and MySQL updated
- [ ] Backup database regularly

### Performance:
- [ ] Set up automatic email processing (Step 6, Option B)
- [ ] Monitor email queue for failures
- [ ] Set up database backups

### Monitoring:
- [ ] Check pending registrations daily
- [ ] Monitor failed emails weekly
- [ ] Review approval/rejection statistics monthly

---

## 🎉 Congratulations!

Your patient approval system is now fully configured and tested!

### Next Steps:
1. [ ] Read full guide: `PATIENT_APPROVAL_SYSTEM_GUIDE.md`
2. [ ] Keep quick reference handy: `QUICK_REFERENCE.md`
3. [ ] Train admin staff on approval process
4. [ ] Announce new registration system to patients

---

## 📊 Completion Status

Mark your progress:

- [ ] Step 1: Verify System Files
- [ ] Step 2: Database Setup
- [ ] Step 3: Email Configuration
- [ ] Step 4: Test Patient Registration
- [ ] Step 5: Test Admin Approval
- [ ] Step 6: Process Email Queue
- [ ] Step 7: Test Patient Login & Notifications
- [ ] Step 8: Test Rejection Flow (Optional)
- [ ] Step 9: Verify All Components
- [ ] Step 10: Production Readiness (Optional)

**Total Time:** ~25-30 minutes

---

## 🆘 Troubleshooting

**Stuck on a step?**
1. Check the full guide: `PATIENT_APPROVAL_SYSTEM_GUIDE.md`
2. Run verification: `verify_system_setup.php`
3. Check error logs:
   - PHP: `C:\xampp\php\logs\error.log`
   - Apache: `C:\xampp\apache\logs\error.log`
   - Browser Console: F12

**Common Issues:**
- **Email not sending:** Check SMTP credentials (Step 3)
- **Tables missing:** Re-run SQL file (Step 2)
- **Login fails:** Check password is correct
- **Notifications not showing:** Clear browser cache (Ctrl+Shift+Delete)

---

**Last Updated:** October 31, 2025  
**System Version:** 1.0  
**Status:** Ready for Configuration

---

## 📞 Need Help?

Re-run the verification script to diagnose issues:
```
http://localhost/mhavis/mhavis/verify_system_setup.php
```

It will tell you exactly what's working and what needs attention!


