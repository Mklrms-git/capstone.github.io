# MHAVIS System - Pre-Deployment Testing Checklist

**System:** Mhavis Medical & Diagnostic Center  
**Version:** 1.0  
**Last Updated:** 2025  
**Purpose:** Comprehensive testing checklist before production deployment

---

## 📋 Table of Contents

1. [Environment & Infrastructure Testing](#1-environment--infrastructure-testing)
2. [Database Testing](#2-database-testing)
3. [Authentication & Security Testing](#3-authentication--security-testing)
4. [User Management Testing](#4-user-management-testing)
5. [Appointment System Testing](#5-appointment-system-testing)
6. [Medical Records Testing](#6-medical-records-testing)
7. [Billing & Transactions Testing](#7-billing--transactions-testing)
8. [Notification System Testing](#8-notification-system-testing)
9. [File Upload & Management Testing](#9-file-upload--management-testing)
10. [API & Data Endpoints Testing](#10-api--data-endpoints-testing)
11. [UI/UX & Frontend Testing](#11-uiux--frontend-testing)
12. [Performance & Load Testing](#12-performance--load-testing)
13. [Browser Compatibility Testing](#13-browser-compatibility-testing)
14. [Mobile Responsiveness Testing](#14-mobile-responsiveness-testing)
15. [Error Handling & Edge Cases](#15-error-handling--edge-cases)
16. [Data Migration & Backup Testing](#16-data-migration--backup-testing)
17. [Email Integration Testing](#17-email-integration-testing)
18. [Print & Report Generation Testing](#18-print--report-generation-testing)
19. [Access Control & Authorization Testing](#19-access-control--authorization-testing)
20. [Data Validation & Sanitization Testing](#20-data-validation--sanitization-testing)

---

## 1. Environment & Infrastructure Testing

### 1.1 Server Requirements
- [ ] PHP version is 7.4 or higher
- [ ] MySQL version is 5.7 or higher
- [ ] Apache web server is running
- [ ] mod_rewrite is enabled
- [ ] Required PHP extensions are installed:
  - [ ] mysqli
  - [ ] session
  - [ ] json
  - [ ] mbstring
  - [ ] fileinfo
  - [ ] gd (for image processing)
  - [ ] openssl (for email)

### 1.2 File Permissions
- [ ] `uploads/` directory exists and is writable (755 or 777)
- [ ] `uploads/notes/` directory exists and is writable
- [ ] `uploads/profile_images/` directory exists and is writable
- [ ] `backups/` directory exists and is writable
- [ ] Configuration files are readable but not publicly accessible
- [ ] `.htaccess` file is in place (if using Apache)

### 1.3 Database Connection
- [ ] Database connection works (`config/database.php`)
- [ ] Database uses utf8mb4 charset
- [ ] Connection error handling works correctly
- [ ] Database credentials are secure (not in version control)

### 1.4 Path Configuration
- [ ] All file paths use correct directory separators
- [ ] Relative paths work correctly
- [ ] Absolute paths are configured correctly
- [ ] Base URL is configured correctly

### 1.5 Error Reporting
- [ ] Error reporting is disabled for production
- [ ] Error logging is enabled
- [ ] Error log file is writable
- [ ] Custom error pages are configured (if applicable)

---

## 2. Database Testing

### 2.1 Database Schema
- [ ] All required tables exist:
  - [ ] `users`
  - [ ] `patient_users`
  - [ ] `doctors`
  - [ ] `patients`
  - [ ] `appointments`
  - [ ] `appointment_requests`
  - [ ] `medical_records`
  - [ ] `medical_history`
  - [ ] `patient_vitals`
  - [ ] `prescriptions`
  - [ ] `patient_notes`
  - [ ] `transactions`
  - [ ] `fees`
  - [ ] `notifications`
  - [ ] `email_queue`
  - [ ] `departments`
  - [ ] `patient_registration_requests`

### 2.2 Database Migrations
- [ ] All SQL migration files have been executed:
  - [ ] `database.sql` or `mhavis.sql`
  - [ ] `patient_module_tables.sql`
  - [ ] `create_appointments_table.sql`
  - [ ] `create_notifications_table.sql`
  - [ ] `create_fee_tables.sql`
  - [ ] `add_attachments_column.sql`
  - [ ] `add_patient_number_column.sql`
  - [ ] `add_medical_record_notification.sql`
  - [ ] `sync_doctors_table.sql`
  - [ ] `assign_doctors_to_departments.sql`
  - [ ] `setup_doctor_schedules.sql`
  - [ ] `migrate_remove_username_from_patient_flow.sql`
  - [ ] `medical_history_records_migration.sql`

### 2.3 Database Constraints
- [ ] Foreign key constraints are properly set
- [ ] Unique constraints are enforced (email, username, patient_number)
- [ ] NOT NULL constraints are enforced where needed
- [ ] Default values are set correctly
- [ ] Indexes are created for frequently queried columns

### 2.4 Data Integrity
- [ ] No orphaned records (doctors without users, appointments without patients, etc.)
- [ ] Referential integrity is maintained
- [ ] Cascade deletes work correctly (if implemented)
- [ ] Data relationships are consistent

### 2.5 Database Performance
- [ ] Queries execute in reasonable time (< 1 second for most queries)
- [ ] Slow query log is monitored
- [ ] Database indexes are optimized
- [ ] No N+1 query problems

---

## 3. Authentication & Security Testing

### 3.1 Admin/Staff Authentication
- [ ] Admin login works (`login.php`)
- [ ] Admin logout works (`logout.php`)
- [ ] Session is created on successful login
- [ ] Session is destroyed on logout
- [ ] Invalid credentials are rejected
- [ ] Password is hashed (not plain text)
- [ ] Password reset functionality works (if implemented)

### 3.2 Patient Authentication
- [ ] Patient login works (`patient_login.php`)
- [ ] Patient logout works (`patient_logout.php`)
- [ ] Session management works correctly
- [ ] Invalid credentials are rejected
- [ ] Account lockout after failed attempts works
- [ ] Login attempt tracking works

### 3.3 Session Security
- [ ] Session timeout works correctly
- [ ] Session fixation is prevented
- [ ] Session hijacking protection is in place
- [ ] Session data is properly validated
- [ ] Session cookies are secure (HttpOnly, Secure flags)

### 3.4 Password Security
- [ ] Passwords are hashed using `password_hash()` (bcrypt)
- [ ] Password strength requirements are enforced
- [ ] Password confirmation matches
- [ ] Old passwords cannot be reused (if implemented)
- [ ] Password update requires current password

### 3.5 SQL Injection Prevention
- [ ] All database queries use prepared statements
- [ ] No direct string concatenation in SQL queries
- [ ] User input is properly bound to parameters
- [ ] Test with SQL injection attempts:
  - [ ] `' OR '1'='1`
  - [ ] `'; DROP TABLE users; --`
  - [ ] `1' UNION SELECT * FROM users --`

### 3.6 XSS (Cross-Site Scripting) Prevention
- [ ] All user input is sanitized using `sanitize()` function
- [ ] Output is escaped using `htmlspecialchars()`
- [ ] ENT_QUOTES flag is used for attribute protection
- [ ] Test with XSS attempts:
  - [ ] `<script>alert('XSS')</script>`
  - [ ] `<img src=x onerror=alert('XSS')>`
  - [ ] `javascript:alert('XSS')`

### 3.7 CSRF (Cross-Site Request Forgery) Protection
- [ ] CSRF tokens are implemented on forms
- [ ] CSRF tokens are validated on form submission
- [ ] Test CSRF protection with external form submission

### 3.8 File Access Security
- [ ] Direct file access is prevented (`MHAVIS_EXEC` constant check)
- [ ] Configuration files are not publicly accessible
- [ ] Uploaded files are validated before saving
- [ ] File paths cannot be manipulated (path traversal prevention)
- [ ] Test path traversal: `../../../etc/passwd`

### 3.9 Input Validation
- [ ] All form inputs are validated
- [ ] Required fields are enforced
- [ ] Data types are validated (email, phone, date, etc.)
- [ ] Input length limits are enforced
- [ ] Special characters are handled correctly

---

## 4. User Management Testing

### 4.1 Admin User Management
- [ ] Add admin/staff user works (`registration.php`)
- [ ] Edit admin/staff user works
- [ ] Delete admin/staff user works (if implemented)
- [ ] User list displays correctly
- [ ] User search/filter works
- [ ] User profile page displays correctly (`profile.php`)

### 4.2 Doctor Management
- [ ] Add doctor works (`add_doctor.php`)
  - [ ] All required fields are saved
  - [ ] Profile image upload works
  - [ ] PRC ID upload works (optional)
  - [ ] Doctor is linked to department
  - [ ] Doctor is added to both `users` and `doctors` tables
- [ ] Edit doctor works (`edit_doctor.php`)
  - [ ] Updates persist correctly
  - [ ] Image updates work
  - [ ] Department changes are reflected
- [ ] Doctor list displays correctly (`doctors.php`)
- [ ] Doctor search/filter works
- [ ] Doctor schedule management works

### 4.3 Patient Management
- [ ] Add patient works (`add_patient.php`)
  - [ ] All required fields are saved
  - [ ] Patient number is generated correctly
  - [ ] Duplicate patient numbers are prevented
- [ ] Edit patient works (`edit_patient.php`)
  - [ ] Updates persist correctly
  - [ ] Patient number cannot be changed
- [ ] Patient list displays correctly (`patients.php`)
- [ ] Patient search/filter works
- [ ] Patient profile page displays correctly (`patient_record.php`)

### 4.4 Patient Registration & Approval
- [ ] Patient registration form works (`patient_registration.php`)
  - [ ] All fields are validated
  - [ ] Registration request is created
  - [ ] Patient receives confirmation
- [ ] Admin can view pending registrations (`admin_patient_registrations.php`)
- [ ] Admin can approve registration
  - [ ] Patient account is created
  - [ ] Email notification is sent
  - [ ] Status is updated correctly
- [ ] Admin can reject registration
  - [ ] Email notification is sent
  - [ ] Status is updated correctly
- [ ] Approved patients can log in
- [ ] Rejected patients cannot log in

### 4.5 Profile Management
- [ ] Update profile works (`update_patient_profile.php`)
- [ ] Profile image upload works
- [ ] Profile image validation works (type, size)
- [ ] Profile updates persist correctly
- [ ] Password update works (`update_password.php`)
- [ ] Current password verification works

---

## 5. Appointment System Testing

### 5.1 Appointment Request (Patient)
- [ ] Patient can access appointment booking (`patient_appointment.php`)
- [ ] Department dropdown loads correctly
- [ ] Doctor dropdown updates based on department (`get_doctors_by_department.php`)
- [ ] Available time slots are displayed (`get_available_time_slots.php`)
- [ ] Time slot validation prevents double booking
- [ ] Appointment request submission works (`submit_appointment_request.php`)
- [ ] Required fields are validated
- [ ] Date validation works (no past dates)
- [ ] Time validation works (within business hours)

### 5.2 Appointment Approval (Admin/Doctor)
- [ ] Admin can view appointment requests (`admin_appointment_requests.php`)
- [ ] Doctor can view appointment requests (`doctor_decide_appointment.php`)
- [ ] Appointment details are displayed correctly (`get_appointment_details.php`)
- [ ] Approve appointment works (`update_appointment_status.php`)
  - [ ] Status is updated to "Approved"
  - [ ] Notification is sent to patient
  - [ ] Email notification is queued
- [ ] Reject appointment works
  - [ ] Status is updated to "Rejected"
  - [ ] Notification is sent to patient
  - [ ] Reason is saved (if provided)

### 5.3 Appointment Management
- [ ] Appointments list displays correctly (`appointments.php`, `get_appointments.php`)
- [ ] Filter by status works (Pending, Approved, Rejected, Completed, Cancelled)
- [ ] Filter by date range works
- [ ] Filter by doctor works
- [ ] Filter by patient works
- [ ] Doctor-specific appointments load (`get_doctor_appointments.php`)
- [ ] Patient-specific appointments load (`get_patient_appointments.php`)
- [ ] Patient can view their appointments (`my_appointments.php`, `patient_requests.php`)

### 5.4 Appointment Scheduling
- [ ] Doctor schedule is displayed correctly (`get_doctor_schedule.php`)
- [ ] Doctor availability is calculated correctly (`get_doctor_availability.php`)
- [ ] Time slot conflicts are detected
- [ ] Business hours are enforced
- [ ] Holiday/closed days are handled
- [ ] Appointment rescheduling works (if implemented)

### 5.5 Appointment Status Updates
- [ ] Status transitions are valid
- [ ] Status updates are logged
- [ ] Notifications are sent on status change
- [ ] Calendar updates reflect status changes

---

## 6. Medical Records Testing

### 6.1 Medical Records Management
- [ ] Add medical record works (`add_medical_record.php`)
  - [ ] All fields are saved correctly
  - [ ] Patient is linked correctly
  - [ ] Doctor is linked correctly
  - [ ] Date/time is saved correctly
- [ ] Edit medical record works (`edit_medical_record.php`)
  - [ ] Updates persist correctly
  - [ ] History is maintained (if implemented)
- [ ] Medical records list displays correctly
- [ ] Medical records search/filter works
- [ ] Medical records are linked to appointments

### 6.2 Medical History
- [ ] Add medical history works (`add_medical_history.php`)
  - [ ] All fields are saved correctly
  - [ ] Date validation works
  - [ ] Patient is linked correctly
- [ ] Medical history list displays correctly
- [ ] Medical history search/filter works
- [ ] Patient can view their medical history (`patient_medical_history.php`)

### 6.3 Patient Vitals
- [ ] Add vitals works (`add_vitals.php`)
  - [ ] All vital signs are saved correctly
  - [ ] Numeric validation works
  - [ ] Date/time is saved correctly
- [ ] Vitals list displays correctly
- [ ] Vitals chart/graph displays correctly (if implemented)
- [ ] Patient can view their vitals (`patient_vitals.php`)

### 6.4 Prescriptions
- [ ] Prescriptions are linked to medical records
- [ ] Prescription list displays correctly
- [ ] Prescription details are complete
- [ ] Patient can view prescriptions (`patient_prescriptions.php`)
- [ ] Prescription printing works (if implemented)

### 6.5 Patient Notes
- [ ] Add patient note works (`patient_notes.php`)
  - [ ] Note type is saved correctly
  - [ ] Note content is saved correctly
  - [ ] Attachment upload works
  - [ ] Doctor is linked correctly
- [ ] Patient notes list displays correctly
- [ ] Patient notes search/filter works
- [ ] Note attachments are accessible
- [ ] Patient can view notes (if allowed)

### 6.6 Patient Overview
- [ ] Patient overview displays correctly (`patient_overview.php`)
- [ ] All data is aggregated correctly
- [ ] Recent records are displayed
- [ ] Statistics are accurate
- [ ] Links to detailed views work

---

## 7. Billing & Transactions Testing

### 7.1 Transaction Management
- [ ] Add transaction works (`add_transaction.php`)
  - [ ] All fields are saved correctly
  - [ ] Patient is linked correctly
  - [ ] Items are saved correctly
  - [ ] Totals are calculated correctly
  - [ ] Date/time is saved correctly
- [ ] Edit transaction works (`edit_transaction.php`)
  - [ ] Updates persist correctly
  - [ ] Totals are recalculated
- [ ] View transaction works (`view_transaction.php`)
  - [ ] All details are displayed correctly
  - [ ] Items are listed correctly
  - [ ] Totals are displayed correctly

### 7.2 Fee Management
- [ ] Fee configuration works (`fees.php`)
- [ ] Fees are applied correctly to transactions
- [ ] Fee updates are reflected in new transactions
- [ ] Fee history is maintained (if implemented)

### 7.3 Receipt Generation
- [ ] Receipt preview works (`preview.php`)
  - [ ] All details are displayed correctly
  - [ ] Formatting is correct
  - [ ] Totals are accurate
- [ ] Print receipt works (`print_receipt.php`)
  - [ ] Print layout is correct
  - [ ] All information is visible
  - [ ] Currency formatting is correct (₱)
  - [ ] Print styles are applied (`assets/css/print.css`)

### 7.4 Reports
- [ ] Daily sales report works (`daily_sales.php`)
  - [ ] Date filtering works
  - [ ] Totals are accurate
  - [ ] Transactions are listed correctly
- [ ] Report analytics works (`report_analytics.php`)
  - [ ] Date range filtering works
  - [ ] Filters are applied correctly
  - [ ] Data is aggregated correctly
  - [ ] Charts/graphs display correctly (if implemented)

### 7.5 Currency & Formatting
- [ ] Currency formatting works (`formatCurrency()`)
  - [ ] Displays as ₱X,XXX.XX
  - [ ] Handles null/empty values
  - [ ] Handles decimal values correctly
- [ ] Date formatting works (`formatDate()`)
- [ ] Time formatting works (`formatTime()`)

---

## 8. Notification System Testing

### 8.1 In-App Notifications
- [ ] Notification creation works (`createNotification()`, `createAdminNotification()`)
- [ ] Notifications are saved to database
- [ ] Notification list displays correctly
- [ ] Unread count is accurate (`getUnreadNotificationCount()`)
- [ ] Mark notification as read works (`mark_notification_read.php`)
- [ ] Mark all as read works (`mark_all_notifications_read.php`)
- [ ] Delete notification works (`delete_notification.php`)
- [ ] Notification types are correct:
  - [ ] Registration approved/rejected
  - [ ] Appointment approved/rejected
  - [ ] Appointment reminder
  - [ ] Medical record updated
  - [ ] Prescription added
  - [ ] General notifications

### 8.2 Email Notifications
- [ ] Email queue system works (`email_queue` table)
- [ ] Email sending works (`sendEmailNotification()`)
- [ ] PHPMailer is configured correctly
- [ ] SMTP settings are correct (`process_notifications.php`)
- [ ] Email templates are correct
- [ ] Email retry mechanism works (max 3 attempts)
- [ ] Failed emails are logged
- [ ] Test email sending works (`test_email_system.php`)
- [ ] Email notifications are sent for:
  - [ ] Registration approval/rejection
  - [ ] Appointment approval/rejection
  - [ ] Appointment reminders
  - [ ] Medical record updates
  - [ ] Prescription notifications

### 8.3 Notification Processing
- [ ] Notification processor works (`process_notifications.php`)
- [ ] Queue is processed correctly
- [ ] Status updates are saved
- [ ] Error handling works
- [ ] Cron job is configured (if automated)

---

## 9. File Upload & Management Testing

### 9.1 File Upload Validation
- [ ] File type validation works:
  - [ ] Images: JPG, PNG, GIF
  - [ ] Documents: PDF, DOC, DOCX
  - [ ] Invalid types are rejected
- [ ] File size validation works:
  - [ ] Profile images: 5MB max
  - [ ] Medical record attachments: 5MB max
  - [ ] Patient notes: 100MB max
  - [ ] PRC ID: 10MB max
  - [ ] Files exceeding limits are rejected
- [ ] File name sanitization works
- [ ] Unique file names are generated
- [ ] File upload errors are handled

### 9.2 File Storage
- [ ] Files are saved to correct directories
- [ ] File paths are stored correctly in database
- [ ] File permissions are set correctly
- [ ] Duplicate file names are handled

### 9.3 File Access & Display
- [ ] Uploaded files are accessible
- [ ] File preview works (`preview.php`)
- [ ] File download works
- [ ] File path traversal is prevented
- [ ] Direct file access is restricted
- [ ] File MIME type validation works

### 9.4 File Deletion
- [ ] File deletion works (if implemented)
- [ ] Database records are updated
- [ ] Physical files are removed
- [ ] Orphaned files are handled

---

## 10. API & Data Endpoints Testing

### 10.1 Doctor Endpoints
- [ ] `get_doctors_by_department.php` works correctly
  - [ ] Returns JSON format
  - [ ] Filters by department correctly
  - [ ] Returns only active doctors
  - [ ] Handles invalid department IDs
  - [ ] Returns empty array for departments with no doctors
- [ ] `get_doctor_schedule.php` works correctly
  - [ ] Returns schedule for specified doctor
  - [ ] Date filtering works
  - [ ] Handles invalid doctor IDs
- [ ] `get_doctor_availability.php` works correctly
  - [ ] Returns availability correctly
  - [ ] Excludes booked appointments
  - [ ] Handles date ranges correctly
- [ ] `get_doctor_overview.php` works correctly
  - [ ] Aggregates data correctly
  - [ ] Statistics are accurate

### 10.2 Appointment Endpoints
- [ ] `get_appointments.php` works correctly
  - [ ] Returns JSON format
  - [ ] Filtering works (status, date, doctor, patient)
  - [ ] Pagination works (if implemented)
- [ ] `get_doctor_appointments.php` works correctly
  - [ ] Returns only doctor's appointments
  - [ ] Filtering works
- [ ] `get_patient_appointments.php` works correctly
  - [ ] Returns only patient's appointments
  - [ ] Filtering works
- [ ] `get_appointment_details.php` works correctly
  - [ ] Returns complete appointment information
  - [ ] Handles invalid appointment IDs
- [ ] `get_available_time_slots.php` works correctly
  - [ ] Returns available time slots
  - [ ] Excludes booked appointments
  - [ ] Respects doctor schedule
  - [ ] Handles date validation

### 10.3 Data Endpoints
- [ ] PH LGU data loads correctly (`assets/data/ph_lgu_data.json`)
- [ ] Drugs data loads correctly (`assets/data/drugs.json`)
- [ ] JSON parsing works correctly
- [ ] Error handling for missing files

### 10.4 API Security
- [ ] Direct access to API endpoints is restricted (if needed)
- [ ] Authentication is required (if implemented)
- [ ] Input validation is performed
- [ ] SQL injection is prevented
- [ ] XSS is prevented in responses

---

## 11. UI/UX & Frontend Testing

### 11.1 Page Layout
- [ ] Header displays correctly (`includes/header.php`)
- [ ] Footer displays correctly (`includes/footer.php`)
- [ ] Navigation menu works
- [ ] Sidebar displays correctly (if applicable)
- [ ] Page titles are correct
- [ ] Breadcrumbs work (if implemented)

### 11.2 Forms
- [ ] All forms display correctly
- [ ] Form validation messages are clear
- [ ] Required fields are marked
- [ ] Form submission works
- [ ] Form reset works
- [ ] Auto-fill works (if implemented)
- [ ] Date pickers work correctly
- [ ] Time pickers work correctly
- [ ] Dropdowns populate correctly

### 11.3 Tables & Lists
- [ ] Data tables display correctly
- [ ] Pagination works (if implemented)
- [ ] Sorting works (if implemented)
- [ ] Filtering works
- [ ] Search works
- [ ] Empty states are handled

### 11.4 Modals & Dialogs
- [ ] Modals open correctly
- [ ] Modals close correctly
- [ ] Form submissions in modals work
- [ ] Modal content loads correctly

### 11.5 Styling
- [ ] CSS files load correctly:
  - [ ] `assets/css/style.css`
  - [ ] `assets/css/login.css`
  - [ ] `assets/css/print.css`
- [ ] Styles are applied correctly
- [ ] No broken CSS links
- [ ] Print styles work correctly

### 11.6 Images & Assets
- [ ] Logo displays correctly (`img/logo.png`)
- [ ] Background images load (`img/bg.jpg`, `img/bg2.jpg`)
- [ ] Default profile images work (`img/defaultDP.jpg`)
- [ ] All image paths are correct
- [ ] Image alt text is provided

### 11.7 JavaScript Functionality
- [ ] AJAX requests work correctly
- [ ] Form submissions via AJAX work
- [ ] Dynamic content updates work
- [ ] No JavaScript errors in console
- [ ] JavaScript is minified/optimized (if applicable)

### 11.8 User Feedback
- [ ] Success messages display correctly
- [ ] Error messages display correctly
- [ ] Loading indicators work
- [ ] Confirmation dialogs work
- [ ] Toast notifications work (if implemented)

---

## 12. Performance & Load Testing

### 12.1 Page Load Times
- [ ] Homepage loads in < 2 seconds
- [ ] Dashboard loads in < 3 seconds
- [ ] List pages load in < 3 seconds
- [ ] Form pages load in < 2 seconds
- [ ] Report pages load in < 5 seconds

### 12.2 Database Performance
- [ ] Queries execute in < 1 second
- [ ] No N+1 query problems
- [ ] Database indexes are optimized
- [ ] Connection pooling works (if implemented)

### 12.3 File Upload Performance
- [ ] Small files (< 1MB) upload quickly
- [ ] Large files (5MB) upload within reasonable time
- [ ] Progress indicators work (if implemented)

### 12.4 Concurrent Users
- [ ] System handles 10 concurrent users
- [ ] System handles 50 concurrent users
- [ ] System handles 100 concurrent users
- [ ] No session conflicts
- [ ] No data conflicts

### 12.5 Resource Usage
- [ ] Memory usage is reasonable
- [ ] CPU usage is reasonable
- [ ] Disk space is monitored
- [ ] Database connections are closed properly

---

## 13. Browser Compatibility Testing

### 13.1 Desktop Browsers
- [ ] Chrome (latest version)
- [ ] Firefox (latest version)
- [ ] Edge (latest version)
- [ ] Safari (latest version, if applicable)
- [ ] Opera (latest version, if applicable)

### 13.2 Browser Features
- [ ] JavaScript is enabled
- [ ] Cookies are enabled
- [ ] Local storage works (if used)
- [ ] Session storage works (if used)

### 13.3 Browser-Specific Issues
- [ ] No CSS rendering issues
- [ ] No JavaScript errors
- [ ] Forms work correctly
- [ ] File uploads work correctly
- [ ] Print functionality works

---

## 14. Mobile Responsiveness Testing

### 14.1 Mobile Devices
- [ ] iPhone (Safari)
- [ ] Android (Chrome)
- [ ] Tablet (iPad, Android tablet)

### 14.2 Responsive Design
- [ ] Layout adapts to small screens
- [ ] Navigation works on mobile
- [ ] Forms are usable on mobile
- [ ] Tables are scrollable/adaptable
- [ ] Images scale correctly
- [ ] Touch interactions work
- [ ] Text is readable without zooming

### 14.3 Mobile-Specific Features
- [ ] Date pickers work on mobile
- [ ] File uploads work on mobile
- [ ] Camera access works (if implemented)
- [ ] GPS/location works (if implemented)

---

## 15. Error Handling & Edge Cases

### 15.1 Form Validation Errors
- [ ] Empty required fields show errors
- [ ] Invalid email format shows error
- [ ] Invalid phone format shows error
- [ ] Invalid date format shows error
- [ ] Password mismatch shows error
- [ ] File size exceeded shows error
- [ ] Invalid file type shows error

### 15.2 Database Errors
- [ ] Connection failure is handled gracefully
- [ ] Query errors are logged
- [ ] User-friendly error messages are shown
- [ ] No sensitive information is exposed

### 15.3 Network Errors
- [ ] AJAX failures are handled
- [ ] Timeout errors are handled
- [ ] Offline mode is handled (if applicable)

### 15.4 Edge Cases
- [ ] Very long text inputs are handled
- [ ] Special characters are handled
- [ ] Unicode characters are handled
- [ ] Empty data sets are handled
- [ ] Null values are handled
- [ ] Duplicate submissions are prevented
- [ ] Concurrent edits are handled

### 15.5 Error Pages
- [ ] 404 error page works
- [ ] 403 error page works (`unauthorized.php`)
- [ ] 500 error page works
- [ ] Error pages are user-friendly

---

## 16. Data Migration & Backup Testing

### 16.1 Database Backup
- [ ] Backup script works
- [ ] Backup files are created correctly
- [ ] Backup includes all tables
- [ ] Backup files are stored securely
- [ ] Backup can be scheduled (cron job)

### 16.2 Database Restore
- [ ] Restore from backup works
- [ ] All data is restored correctly
- [ ] Relationships are maintained
- [ ] No data loss occurs

### 16.3 Data Migration
- [ ] Migration scripts work correctly
- [ ] Data is migrated without loss
- [ ] Data integrity is maintained
- [ ] Rollback works (if implemented)

### 16.4 Data Export
- [ ] Data can be exported (if implemented)
- [ ] Export format is correct
- [ ] All relevant data is included

---

## 17. Email Integration Testing

### 17.1 Email Configuration
- [ ] SMTP settings are correct
- [ ] SMTP authentication works
- [ ] Port configuration is correct (587 for TLS, 465 for SSL)
- [ ] SSL/TLS encryption works
- [ ] Email credentials are secure

### 17.2 Email Sending
- [ ] Test email sends successfully (`test_email_system.php`)
- [ ] Email queue processes correctly
- [ ] Email retry mechanism works
- [ ] Failed emails are logged
- [ ] Email delivery is reliable

### 17.3 Email Templates
- [ ] Registration approval email is correct
- [ ] Registration rejection email is correct
- [ ] Appointment approval email is correct
- [ ] Appointment rejection email is correct
- [ ] Appointment reminder email is correct
- [ ] Medical record update email is correct
- [ ] Email formatting is correct (HTML/text)
- [ ] Email links work correctly

---

## 18. Print & Report Generation Testing

### 18.1 Receipt Printing
- [ ] Receipt preview displays correctly
- [ ] Print layout is correct
- [ ] All information is visible
- [ ] Totals are accurate
- [ ] Print styles are applied (`assets/css/print.css`)
- [ ] Print dialog works
- [ ] Print to PDF works

### 18.2 Report Generation
- [ ] Reports generate correctly
- [ ] Data is accurate
- [ ] Formatting is correct
- [ ] Charts/graphs display (if implemented)
- [ ] Reports can be exported (if implemented)
- [ ] Date ranges work correctly
- [ ] Filters are applied correctly

### 18.3 Print Styles
- [ ] Print CSS is loaded correctly
- [ ] Unnecessary elements are hidden
- [ ] Page breaks work correctly
- [ ] Margins are correct
- [ ] Font sizes are appropriate

---

## 19. Access Control & Authorization Testing

### 19.1 Role-Based Access
- [ ] Admin can access admin pages:
  - [ ] `admin_dashboard.php`
  - [ ] `admin_patient_registrations.php`
  - [ ] `admin_appointment_requests.php`
  - [ ] `fees.php`
  - [ ] `report_analytics.php`
  - [ ] `daily_sales.php`
- [ ] Doctor can access doctor pages:
  - [ ] `doctor_dashboard.php`
  - [ ] `doctor_decide_appointment.php`
- [ ] Patient can access patient pages:
  - [ ] `patient_dashboard.php`
  - [ ] `my_appointments.php`
  - [ ] `patient_medical_records.php`
  - [ ] `patient_medical_history.php`
  - [ ] `patient_prescriptions.php`
  - [ ] `patient_notes.php`
  - [ ] `patient_vitals.php`
  - [ ] `patient_overview.php`

### 19.2 Unauthorized Access Prevention
- [ ] Unauthorized users are redirected (`unauthorized.php`)
- [ ] Direct URL access to protected pages is blocked
- [ ] Session validation works (`requireLogin()`, `requireRole()`)
- [ ] Patient can only view their own data
- [ ] Doctors can only view their assigned patients (if applicable)

### 19.3 Permission Checks
- [ ] Create permissions are enforced
- [ ] Read permissions are enforced
- [ ] Update permissions are enforced
- [ ] Delete permissions are enforced

---

## 20. Data Validation & Sanitization Testing

### 20.1 Input Sanitization
- [ ] `sanitize()` function is used on all inputs
- [ ] HTML tags are escaped
- [ ] Special characters are handled
- [ ] SQL injection is prevented
- [ ] XSS is prevented

### 20.2 Data Validation
- [ ] Email format is validated
- [ ] Phone number format is validated
- [ ] Date format is validated
- [ ] Time format is validated
- [ ] Numeric values are validated
- [ ] Text length is validated
- [ ] Required fields are enforced

### 20.3 Output Escaping
- [ ] All output is escaped using `htmlspecialchars()`
- [ ] ENT_QUOTES flag is used
- [ ] UTF-8 encoding is used
- [ ] No raw user input is displayed

### 20.4 Data Type Validation
- [ ] Integers are validated
- [ ] Floats are validated
- [ ] Strings are validated
- [ ] Dates are validated
- [ ] Booleans are validated

---

## 📝 Testing Execution Notes

### Test Environment
- **Server:** ________________
- **Database:** ________________
- **PHP Version:** ________________
- **MySQL Version:** ________________
- **Browser:** ________________
- **Date:** ________________
- **Tester:** ________________

### Test Results Summary
- **Total Tests:** ________________
- **Passed:** ________________
- **Failed:** ________________
- **Blocked:** ________________
- **Pass Rate:** ________________%

### Critical Issues Found
1. ________________________________________________
2. ________________________________________________
3. ________________________________________________

### High Priority Issues Found
1. ________________________________________________
2. ________________________________________________
3. ________________________________________________

### Medium Priority Issues Found
1. ________________________________________________
2. ________________________________________________
3. ________________________________________________

### Notes & Recommendations
________________________________________________
________________________________________________
________________________________________________

---

## ✅ Sign-Off

**Testing Completed By:** ________________  
**Date:** ________________  
**Status:** [ ] Ready for Deployment [ ] Needs Fixes  
**Approved By:** ________________  
**Date:** ________________

---

**END OF TESTING CHECKLIST**

