# MHAVIS System - Complete Pre-Deployment Testing List

**System:** Mhavis Medical & Diagnostic Center  
**Version:** 1.0  
**Purpose:** Comprehensive list of all functions and features that need testing before deployment

---

## 📖 How to Use This Checklist

1. **For detailed testing instructions**, refer to **`TESTING_INSTRUCTIONS_GUIDE.md`** which contains step-by-step instructions for each type of test.

2. **Some test items below have detailed instructions** embedded - look for the "How to Test:" sections.

3. **For items without detailed instructions**, use the patterns in `TESTING_INSTRUCTIONS_GUIDE.md`:
   - Security tests → See "Security Testing Instructions"
   - Authentication tests → See "Authentication Testing Instructions"
   - Form tests → See "User Management Testing Instructions"
   - API tests → See "API Endpoint Testing Instructions"
   - And so on...

4. **Quick Reference:** Use `TESTING_QUICK_REFERENCE.md` for a condensed checklist of essential tests.

---

## 📋 Quick Navigation

1. [Critical Security Testing](#1-critical-security-testing) ⚠️ **MUST PASS**
2. [Authentication & Authorization](#2-authentication--authorization) ⚠️ **MUST PASS**
3. [User Management Functions](#3-user-management-functions)
4. [Patient Registration & Approval System](#4-patient-registration--approval-system)
5. [Appointment System](#5-appointment-system)
6. [Medical Records Management](#6-medical-records-management)
7. [Billing & Transactions](#7-billing--transactions)
8. [Notification System](#8-notification-system)
9. [File Upload & Management](#9-file-upload--management)
10. [API Endpoints & Data Retrieval](#10-api-endpoints--data-retrieval)
11. [Dashboard & Reporting](#11-dashboard--reporting)
12. [UI/UX & Frontend](#12-uiux--frontend)
13. [Database & Data Integrity](#13-database--data-integrity)
14. [Email Integration](#14-email-integration)
15. [Print & Export Functions](#15-print--export-functions)
16. [Performance & Load](#16-performance--load)
17. [Browser & Device Compatibility](#17-browser--device-compatibility)
18. [Error Handling & Edge Cases](#18-error-handling--edge-cases)
19. [Configuration & Environment](#19-configuration--environment)
20. [Backup & Recovery](#20-backup--recovery)

---

## 1. Critical Security Testing ⚠️ **MUST PASS**

### 1.1 SQL Injection Prevention

#### Test login form with: `' OR '1'='1`
**How to Test:**
1. Navigate to `http://localhost/mhavis/mhavis/login.php`
2. In the username field, enter: `' OR '1'='1`
3. Enter any password (e.g., `test`)
4. Click "Login"
5. **Expected Result:** Login should fail with "Invalid username or password" error. The system should NOT log you in. 

- DONE - 

#### Test search fields with: `'; DROP TABLE users; --`
**How to Test:**
1. Log in as admin
2. Navigate to any page with a search field (e.g., `patients.php`, `doctors.php`)
3. In the search field, enter: `'; DROP TABLE users; --`
4. Submit the search
5. **Expected Result:** Search should either return no results or show an error message. The `users` table should still exist (check database).
6. Verify table exists: Run `SHOW TABLES LIKE 'users';` in MySQL

- DONE - 

#### Test all input fields with: `1' UNION SELECT * FROM users --`
**How to Test:**
1. Test on patient registration form (`patient_registration.php`):
   - Enter in first name field: `1' UNION SELECT * FROM users --`
   - Fill other required fields normally
   - Submit form
   - **Expected Result:** Form should reject or sanitize the input, no user data should be exposed
2. Test on appointment booking form:
   - Enter in reason field: `1' UNION SELECT * FROM users --`
   - Submit form
   - **Expected Result:** Input should be sanitized, no SQL execution
3. Test on medical record forms:
   - Enter in diagnosis field: `1' UNION SELECT * FROM users --`
   - Submit form
   - **Expected Result:** Input should be sanitized

#### Verify all queries use prepared statements
**How to Test:**
1. Open key PHP files in a code editor:
   - `login.php`
   - `patient_login.php`
   - `add_patient.php`
   - `add_doctor.php`
   - `submit_appointment_request.php`
2. Search for SQL queries (look for `$conn->query(` or `mysqli_query`)
3. **Expected Result:** All queries should use `$conn->prepare()` and `bind_param()`, NOT direct string concatenation like `"SELECT * FROM users WHERE id = " . $id`

#### Test appointment booking form with SQL injection attempts
**How to Test:**
1. Log in as a patient
2. Navigate to `patient_appointment.php`
3. Fill the form and in the "Reason for Visit" field, enter: `' OR '1'='1`
4. Submit the appointment request
5. **Expected Result:** Form should submit normally, input should be sanitized, no SQL errors

#### Test patient registration form with SQL injection attempts
**How to Test:**
1. Navigate to `patient_registration.php`
2. In the "First Name" field, enter: `admin'--`
3. Fill other required fields normally
4. Submit the form
5. **Expected Result:** Registration should proceed normally, input should be sanitized

#### Test medical record forms with SQL injection attempts
**How to Test:**
1. Log in as admin/doctor
2. Navigate to add medical record page
3. In diagnosis field, enter: `'; DROP TABLE medical_records; --`
4. Submit the form
5. **Expected Result:** Form should submit, input sanitized, table should still exist

#### Test transaction forms with SQL injection attempts
**How to Test:**
1. Log in as admin
2. Navigate to `add_transaction.php`
3. In any text field, enter: `1' UNION SELECT password FROM users --`
4. Submit the form
5. **Expected Result:** Form should work normally, no data exposure

#### Verify no raw SQL concatenation exists
**How to Test:**
1. Use grep or search in code editor for patterns:
   - `$conn->query("SELECT ... WHERE id = " . $var`
   - `mysqli_query($conn, "SELECT ... WHERE id = " . $var`
2. **Expected Result:** Should find ZERO results. All queries should use prepared statements.

### 1.2 XSS (Cross-Site Scripting) Prevention

#### Test all input fields with: `<script>alert('XSS')</script>`
**How to Test:**
1. Navigate to patient registration form (`patient_registration.php`)
2. In "First Name" field, enter: `<script>alert('XSS')</script>`
3. Fill other required fields and submit
4. **Expected Result:** 
   - Form should accept the input
   - When viewing the data later, it should display as text: `<script>alert('XSS')</script>`
   - NO alert popup should appear
   - Check database: The value should be stored as-is (not executed)

#### Test with: `<img src=x onerror=alert('XSS')>`
**How to Test:**
1. Log in as admin
2. Navigate to add patient page (`add_patient.php`)
3. In "First Name" field, enter: `<img src=x onerror=alert('XSS')>`
4. Submit the form
5. View the patient record
6. **Expected Result:** Should display as text, no alert popup, no broken image

#### Test with: `javascript:alert('XSS')`
**How to Test:**
1. In any URL parameter field or search field, try: `javascript:alert('XSS')`
2. **Expected Result:** Should be treated as text, not executed

#### Verify `sanitize()` function is used on all inputs
**How to Test:**
1. Open key form processing files:
   - `add_patient.php`
   - `add_doctor.php`
   - `patient_registration.php`
   - `submit_appointment_request.php`
2. Search for `$_POST` or `$_GET` usage
3. **Expected Result:** Should see `sanitize($_POST['field'])` or similar sanitization

#### Verify `htmlspecialchars()` is used on all outputs
**How to Test:**
1. Open display files:
   - `patients.php`
   - `doctors.php`
   - `patient_record.php`
2. Look for `echo` or `<?= ?>` statements displaying user data
3. **Expected Result:** Should see `htmlspecialchars($data)` or `sanitize($data)` before output

#### Test patient registration form with XSS payloads
**How to Test:**
1. Go to `patient_registration.php`
2. Enter in various fields:
   - First Name: `<script>alert('XSS')</script>`
   - Last Name: `<img src=x onerror=alert(1)>`
   - Address: `javascript:alert('XSS')`
3. Submit form
4. After admin approval, log in as patient and view profile
5. **Expected Result:** All fields should display as text, no script execution

#### Test appointment notes with XSS payloads
**How to Test:**
1. Log in as patient
2. Book an appointment, in "Reason for Visit" enter: `<script>alert('XSS')</script>`
3. Submit appointment
4. Log in as admin, view the appointment
5. **Expected Result:** Should display as text, no alert

#### Test medical record fields with XSS payloads
**How to Test:**
1. Log in as doctor/admin
2. Add a medical record
3. In "Diagnosis" field, enter: `<script>document.cookie</script>`
4. Save the record
5. View the record
6. **Expected Result:** Should display as text, no script execution

#### Verify ENT_QUOTES flag is used
**How to Test:**
1. Open `config/functions.php`
2. Find the `sanitize()` function
3. Check the `htmlspecialchars()` call
4. **Expected Result:** Should see `htmlspecialchars($input, ENT_QUOTES, 'UTF-8')` - the `ENT_QUOTES` flag is important

### 1.3 CSRF (Cross-Site Request Forgery) Protection
- [ ] Verify CSRF tokens on all forms (if implemented)
- [ ] Test form submission from external site
- [ ] Verify session validation on form submissions
- [ ] Test appointment approval/rejection forms
- [ ] Test patient registration approval forms
- [ ] Test transaction creation forms

### 1.4 File Upload Security

#### Test upload of PHP files (`.php`, `.phtml`)
**How to Test:**
1. Create a test PHP file: `test.php` with content: `<?php phpinfo(); ?>`
2. Log in as admin
3. Navigate to add doctor page (`add_doctor.php`)
4. Try to upload `test.php` as profile image
5. **Expected Result:** Upload should be REJECTED with error message like "Invalid file type"
6. Verify file is NOT in `uploads/` directory

#### Test upload of executable files (`.exe`, `.bat`, `.sh`)
**How to Test:**
1. Create a test file: `test.exe` (or any executable)
2. Try to upload as profile image or medical record attachment
3. **Expected Result:** Should be rejected, error message shown

#### Test upload of script files (`.js`, `.html`)
**How to Test:**
1. Create `test.js` with: `alert('XSS')`
2. Try to upload as attachment
3. **Expected Result:** Should be rejected (if validation is strict) or at least not executable

#### Verify file type validation (MIME type checking)
**How to Test:**
1. Create a file `test.jpg` but rename it to `test.php`
2. Try to upload it
3. Check the upload code (e.g., `add_doctor.php`) for MIME type checking
4. **Expected Result:** Code should check `$_FILES['file']['type']` or use `finfo_file()` to verify actual file type, not just extension

#### Verify file size limits are enforced
**How to Test:**
1. Create a large image file (> 5MB) for profile image
2. Try to upload it
3. **Expected Result:** Should show error "File size exceeds limit" or similar
4. Check PHP settings: `php.ini` should have `upload_max_filesize` and `post_max_size` configured

#### Test path traversal: `../../../etc/passwd`
**How to Test:**
1. Create a file named: `../../../etc/passwd` (or `..\..\..\windows\system32\config\sam` on Windows)
2. Try to upload it
3. **Expected Result:** 
   - File name should be sanitized
   - Should not be able to access system files
   - Check upload code for `basename()` usage to prevent path traversal

#### Verify uploaded files are stored outside web root (if possible)
**How to Test:**
1. Check where files are stored (likely `uploads/` directory)
2. Try to access uploaded file directly: `http://localhost/mhavis/mhavis/uploads/filename.jpg`
3. **Expected Result:** 
   - If accessible, ensure files are validated before serving
   - Or files should be served through a PHP script that validates access

#### Test profile image upload security
**How to Test:**
1. Log in as admin
2. Go to add/edit doctor page
3. Try uploading:
   - Valid image (`.jpg`, `.png`) - should work
   - PHP file renamed to `.jpg` - should be rejected
   - Large file (>5MB) - should be rejected
4. **Expected Result:** Only valid images under size limit should be accepted

#### Test medical record attachment security
**How to Test:**
1. Log in as doctor/admin
2. Add a medical record
3. Try uploading various file types as attachment
4. **Expected Result:** Only allowed file types (PDF, images) should be accepted

#### Test PRC ID upload security
**How to Test:**
1. Log in as admin
2. Add/edit doctor
3. Try uploading PRC ID with:
   - Valid PDF/image - should work
   - Executable file - should be rejected
   - Large file (>10MB) - should be rejected
4. **Expected Result:** Only valid document types under size limit accepted

### 1.5 Session Security
- [ ] Verify session timeout works
- [ ] Test session fixation prevention
- [ ] Verify session cookies have HttpOnly flag
- [ ] Verify session cookies have Secure flag (if HTTPS)
- [ ] Test session hijacking prevention
- [ ] Verify session regeneration on login
- [ ] Test concurrent sessions from same user

### 1.6 Access Control
- [ ] Test direct URL access to protected pages
- [ ] Verify `MHAVIS_EXEC` constant protection works
- [ ] Test unauthorized access to admin pages
- [ ] Test unauthorized access to doctor pages
- [ ] Test unauthorized access to patient pages
- [ ] Verify patients can only view their own data
- [ ] Verify doctors can only view assigned patients (if applicable)
- [ ] Test role-based access restrictions

---

## 2. Authentication & Authorization ⚠️ **MUST PASS**

### 2.1 Admin/Staff Authentication

#### Admin login works (`login.php`)

##### Valid credentials log in successfully
**How to Test:**
1. Navigate to `http://localhost/mhavis/mhavis/login.php`
2. Enter valid admin username and password
3. Click "Login"
4. **Expected Result:** 
   - Should redirect to `admin_dashboard.php`
   - Should see admin dashboard
   - URL should show you're logged in

##### Invalid credentials are rejected
**How to Test:**
1. Go to login page
2. Enter wrong username: `wronguser`
3. Enter wrong password: `wrongpass`
4. Click "Login"
5. **Expected Result:** 
   - Should show error: "Invalid username or password"
   - Should stay on login page
   - Should NOT redirect to dashboard

##### Session is created on successful login
**How to Test:**
1. Log in with valid credentials
2. Open browser Developer Tools (F12)
3. Go to Application/Storage tab → Cookies
4. Look for session cookie (usually `PHPSESSID`)
5. **Expected Result:** Session cookie should exist

##### User data is stored in session correctly
**How to Test:**
1. Log in as admin
2. Check session variables by temporarily adding to a page:
   ```php
   <?php print_r($_SESSION); ?>
   ```
3. **Expected Result:** Should see:
   - `user_id`
   - `username`
   - `first_name`
   - `last_name`
   - `role` = "Admin"
   - `last_activity`
   - `token`

#### Admin logout works (`logout.php`)

##### Session is destroyed
**How to Test:**
1. Log in as admin
2. Click logout link/button
3. Check browser cookies (F12 → Application → Cookies)
4. **Expected Result:** Session cookie should be deleted or expired

##### User is redirected to login page
**How to Test:**
1. Log in as admin
2. Click logout
3. **Expected Result:** Should redirect to `login.php`

##### Cannot access protected pages after logout
**How to Test:**
1. Log in as admin
2. Log out
3. Try to access `admin_dashboard.php` directly via URL
4. **Expected Result:** Should redirect to `login.php` (cannot access)

#### Password security

##### Passwords are hashed (not plain text)
**How to Test:**
1. Log in to MySQL: `mysql -u root mhavis`
2. Run: `SELECT id, username, password FROM users LIMIT 1;`
3. **Expected Result:** 
   - Password should start with `$2y$` (bcrypt hash)
   - Should NOT be readable plain text
   - Should be 60 characters long

##### Password verification works correctly
**How to Test:**
1. Log in with correct password - should work
2. Log in with wrong password - should fail
3. **Expected Result:** Only correct password should allow login

##### Password update requires current password (`update_password.php`)
**How to Test:**
1. Log in as admin
2. Navigate to password update page
3. Try to change password WITHOUT entering current password
4. **Expected Result:** Should show error "Current password required"
5. Enter wrong current password
6. **Expected Result:** Should show error "Current password incorrect"
7. Enter correct current password and new password
8. **Expected Result:** Password should update successfully

### 2.2 Patient Authentication
- [ ] Patient login works (`patient_login.php`)
  - [ ] Valid credentials log in successfully
  - [ ] Invalid credentials are rejected
  - [ ] Pending accounts cannot log in
  - [ ] Rejected accounts cannot log in
  - [ ] Session is created correctly
- [ ] Patient logout works (`patient_logout.php`)
  - [ ] Session is destroyed
  - [ ] User is redirected appropriately
- [ ] Login attempt tracking
  - [ ] Failed attempts are logged
  - [ ] Account lockout works (if implemented)

### 2.3 Session Management
- [ ] `requireLogin()` function works correctly
- [ ] `requireRole()` function works correctly
- [ ] `requireAdmin()` function works correctly
- [ ] `requirePatientLogin()` function works correctly
- [ ] Session timeout redirects to login
- [ ] Session persists across page navigation
- [ ] Session data is validated on each request

### 2.4 Authorization Checks
- [ ] Unauthorized access redirects to `unauthorized.php`
- [ ] Role-based page access works
- [ ] Permission checks are enforced
- [ ] Patient data isolation works

---

## 3. User Management Functions

### 3.1 Admin/Staff User Management
- [ ] Add admin/staff user (`registration.php`)
  - [ ] All required fields are validated
  - [ ] User is created in database
  - [ ] Password is hashed correctly
  - [ ] Email uniqueness is enforced
- [ ] Edit admin/staff user
  - [ ] Updates persist correctly
  - [ ] Password can be updated
  - [ ] Profile information updates
- [ ] User profile page (`profile.php`)
  - [ ] Displays correct user information
  - [ ] Profile updates work
  - [ ] Profile image upload works

### 3.2 Doctor Management
- [ ] Add doctor (`add_doctor.php`)
  - [ ] All required fields are saved
  - [ ] Doctor is added to `users` table
  - [ ] Doctor is added to `doctors` table
  - [ ] Profile image upload works
  - [ ] PRC ID upload works (if provided)
  - [ ] Doctor is linked to department
  - [ ] Department ID is synced between tables
  - [ ] Username uniqueness is enforced
  - [ ] Email uniqueness is enforced
- [ ] Edit doctor (`edit_doctor.php`)
  - [ ] Updates persist correctly
  - [ ] Image updates work
  - [ ] Department changes are reflected
  - [ ] Both `users` and `doctors` tables are updated
- [ ] Doctor list (`doctors.php`)
  - [ ] All doctors are displayed
  - [ ] Search/filter works
  - [ ] Doctor details are correct
  - [ ] Department information displays correctly
- [ ] Doctor schedule management
  - [ ] Schedule can be set/updated
  - [ ] Schedule displays correctly

### 3.3 Patient Management (Admin Side)
- [ ] Add patient (`add_patient.php`)
  - [ ] All required fields are saved
  - [ ] Patient number is generated correctly
  - [ ] Duplicate patient numbers are prevented
  - [ ] Patient is created in `patients` table
- [ ] Edit patient (`edit_patient.php`)
  - [ ] Updates persist correctly
  - [ ] Patient number cannot be changed
  - [ ] All fields update correctly
- [ ] Patient list (`patients.php`)
  - [ ] All patients are displayed
  - [ ] Search/filter works
  - [ ] Patient details are correct
- [ ] Patient record view (`patient_record.php`)
  - [ ] All patient information displays
  - [ ] Medical history displays
  - [ ] Appointments display
  - [ ] Transactions display

### 3.4 Patient Profile Management
- [ ] Update patient profile (`update_patient_profile.php`)
  - [ ] Profile updates persist
  - [ ] Profile image upload works
  - [ ] Image validation works (type, size)
  - [ ] All fields update correctly
- [ ] Password update (`update_password.php`)
  - [ ] Current password verification works
  - [ ] New password is hashed
  - [ ] Password update succeeds

---

## 4. Patient Registration & Approval System

### 4.1 Patient Registration

#### Patient registration form (`patient_registration.php`)

##### All fields are displayed correctly
**How to Test:**
1. Navigate to `http://localhost/mhavis/mhavis/patient_registration.php`
2. **Expected Result:** Should see all fields:
   - Personal Information: First Name, Last Name, Middle Name, Date of Birth, Gender, Civil Status
   - Contact: Email, Phone, Address
   - Emergency Contact: Name, Relationship, Phone
   - Medical: Blood Type, Allergies, Medical Conditions
   - Account: Username, Password, Confirm Password

##### Form validation works
**How to Test:**
1. Leave all fields empty and submit
2. **Expected Result:** Should show validation errors for required fields
3. Fill fields with invalid data (e.g., invalid email)
4. **Expected Result:** Should show specific error messages

##### Required fields are enforced
**How to Test:**
1. Fill only optional fields, leave required fields empty
2. Submit form
3. **Expected Result:** 
   - Form should NOT submit
   - Required fields should be highlighted
   - Error messages should appear

##### Email format validation works
**How to Test:**
1. Enter invalid email: `notanemail`
2. **Expected Result:** Should show error "Invalid email format"
3. Enter valid email: `test@example.com`
4. **Expected Result:** Should accept it

##### Phone number validation works
**How to Test:**
1. Enter invalid phone: `123` (too short)
2. **Expected Result:** Should show error or reject
3. Enter valid phone: `09123456789`
4. **Expected Result:** Should accept it

##### Date validation works
**How to Test:**
1. Enter future date of birth
2. **Expected Result:** Should show error "Date cannot be in the future"
3. Enter valid past date
4. **Expected Result:** Should accept it

##### Registration request is created in database
**How to Test:**
1. Fill form completely with valid data
2. Submit form
3. Check database:
   ```sql
   SELECT * FROM patient_registration_requests 
   ORDER BY id DESC LIMIT 1;
   ```
4. **Expected Result:** 
   - New record should exist
   - Status should be "Pending"
   - All data should be saved correctly

##### Patient receives confirmation message
**How to Test:**
1. Submit registration form
2. **Expected Result:** 
   - Should see success message: "Registration submitted successfully"
   - Message should indicate admin approval is required

##### Registration data is saved to `patient_registration_requests` table
**How to Test:**
1. Submit registration
2. Run SQL query:
   ```sql
   SELECT * FROM patient_registration_requests 
   WHERE email = 'test@example.com';
   ```
3. **Expected Result:** 
   - Record should exist
   - All fields should match what was entered
   - `status` should be "Pending"
   - `created_at` should be recent timestamp

### 4.2 Admin Approval Interface
- [ ] Admin can view pending registrations (`admin_patient_registrations.php`)
  - [ ] Pending registrations are listed
  - [ ] Registration details are displayed
  - [ ] Search/filter works
- [ ] Admin can approve registration
  - [ ] Patient account is created in `patient_users` table
  - [ ] Patient is created in `patients` table
  - [ ] Email notification is queued
  - [ ] In-app notification is created
  - [ ] Status is updated to "Approved"
  - [ ] Patient can log in after approval
- [ ] Admin can reject registration
  - [ ] Email notification is queued
  - [ ] In-app notification is created (if patient has account)
  - [ ] Status is updated to "Rejected"
  - [ ] Patient cannot log in after rejection
  - [ ] Rejection reason is saved (if provided)

### 4.3 Registration Status Flow
- [ ] Pending status prevents login
- [ ] Approved status allows login
- [ ] Rejected status prevents login
- [ ] Status changes are logged

---

## 5. Appointment System

### 5.1 Patient Appointment Booking

#### Patient can access booking page (`patient_appointment.php`)

##### Page loads correctly
**How to Test:**
1. Log in as patient
2. Navigate to `patient_appointment.php` or click "Book Appointment" link
3. **Expected Result:** 
   - Page should load without errors
   - No PHP errors in page source
   - All elements visible

##### Form is displayed
**How to Test:**
1. On appointment booking page
2. **Expected Result:** Should see form with:
   - Department dropdown
   - Doctor dropdown
   - Date picker
   - Time slot selection
   - Reason for visit field
   - Urgency level selection
   - Submit button

#### Department selection

##### Department dropdown loads correctly
**How to Test:**
1. On appointment booking page
2. Click Department dropdown
3. **Expected Result:** 
   - Dropdown should open
   - Should show list of departments (Internal Medicine, Orthopedic, etc.)
   - All departments should be listed

##### All departments are listed
**How to Test:**
1. Check dropdown options
2. Compare with database:
   ```sql
   SELECT name FROM departments ORDER BY name;
   ```
3. **Expected Result:** All departments from database should appear in dropdown

#### Doctor selection (`get_doctors_by_department.php`)

##### Doctor dropdown updates based on department
**How to Test:**
1. On appointment booking page
2. Select "Internal Medicine" from department dropdown
3. Watch the Doctor dropdown
4. **Expected Result:** 
   - Doctor dropdown should become enabled (if it was disabled)
   - Should show doctors from Internal Medicine department
   - Should update within 1-2 seconds

##### Only active doctors are shown
**How to Test:**
1. Select a department
2. Check doctor dropdown
3. Verify in database:
   ```sql
   SELECT u.first_name, u.last_name, u.status 
   FROM users u 
   JOIN doctors d ON u.id = d.user_id 
   WHERE d.department_id = [selected_dept_id] AND u.status = 'Active';
   ```
4. **Expected Result:** Only active doctors should appear in dropdown

##### Empty departments show no doctors
**How to Test:**
1. Select a department that has no doctors assigned
2. Check doctor dropdown
3. **Expected Result:** 
   - Dropdown should show "Select Doctor" or be empty
   - Should NOT show any doctor names
   - Dropdown should remain enabled

##### AJAX request works correctly
**How to Test:**
1. Open browser Developer Tools (F12)
2. Go to Network tab
3. Select a department from dropdown
4. Look for request to `get_doctors_by_department.php`
5. **Expected Result:** 
   - Should see HTTP request (status 200)
   - Request should include `department_id` parameter
   - Response should be JSON

##### JSON response is valid
**How to Test:**
1. Select a department
2. In Network tab, click on `get_doctors_by_department.php` request
3. Check Response tab
4. **Expected Result:** 
   - Should be valid JSON format
   - Should be an array of doctor objects
   - Each object should have: `id`, `first_name`, `last_name`, `email`, etc.
5. Test directly: `http://localhost/mhavis/mhavis/get_doctors_by_department.php?department_id=3`
6. **Expected Result:** Should return valid JSON array
- [ ] Date selection
  - [ ] Date picker works
  - [ ] Past dates are disabled
  - [ ] Date validation works
- [ ] Time slot selection (`get_available_time_slots.php`)
  - [ ] Available time slots are displayed
  - [ ] Booked slots are excluded
  - [ ] Doctor schedule is respected
  - [ ] Business hours are enforced
  - [ ] Time slot validation prevents double booking
- [ ] Appointment request submission (`submit_appointment_request.php`)
  - [ ] All required fields are validated
  - [ ] Appointment request is saved
  - [ ] Status is set to "Pending"
  - [ ] Notification is created
  - [ ] Success message is displayed
  - [ ] Patient sees request in their list

### 5.2 Appointment Approval (Admin/Doctor)
- [ ] Admin can view requests (`admin_appointment_requests.php`)
  - [ ] All pending requests are listed
  - [ ] Request details are displayed
  - [ ] Filter by status works
  - [ ] Search works
- [ ] Doctor can view requests (`doctor_decide_appointment.php`)
  - [ ] Only doctor's requests are shown
  - [ ] Request details are displayed
- [ ] Appointment details (`get_appointment_details.php`)
  - [ ] Complete information is returned
  - [ ] Patient information is included
  - [ ] Doctor information is included
  - [ ] JSON format is correct
- [ ] Approve appointment (`update_appointment_status.php`)
  - [ ] Status is updated to "Approved"
  - [ ] Notification is sent to patient
  - [ ] Email notification is queued
  - [ ] In-app notification is created
  - [ ] Appointment appears in calendar
- [ ] Reject appointment
  - [ ] Status is updated to "Rejected"
  - [ ] Notification is sent to patient
  - [ ] Email notification is queued
  - [ ] Reason is saved (if provided)

### 5.3 Appointment Management
- [ ] Appointments list (`appointments.php`, `get_appointments.php`)
  - [ ] All appointments are displayed
  - [ ] Filter by status works (Pending, Approved, Rejected, Completed, Cancelled)
  - [ ] Filter by date range works
  - [ ] Filter by doctor works
  - [ ] Filter by patient works
  - [ ] Search works
  - [ ] Pagination works (if implemented)
- [ ] Doctor-specific appointments (`get_doctor_appointments.php`)
  - [ ] Only doctor's appointments are returned
  - [ ] Filtering works
  - [ ] JSON format is correct
- [ ] Patient-specific appointments (`get_patient_appointments.php`)
  - [ ] Only patient's appointments are returned
  - [ ] Filtering works
  - [ ] JSON format is correct
- [ ] Patient appointment views
  - [ ] `my_appointments.php` displays correctly
  - [ ] `patient_requests.php` displays correctly
  - [ ] Appointment status is shown correctly
  - [ ] Calendar integration works

### 5.4 Appointment Scheduling
- [ ] Doctor schedule (`get_doctor_schedule.php`)
  - [ ] Schedule is returned correctly
  - [ ] Date filtering works
  - [ ] JSON format is correct
- [ ] Doctor availability (`get_doctor_availability.php`)
  - [ ] Availability is calculated correctly
  - [ ] Booked appointments are excluded
  - [ ] Date ranges work correctly
- [ ] Time slot conflicts
  - [ ] Conflicts are detected
  - [ ] Double booking is prevented
- [ ] Business hours
  - [ ] Business hours are enforced
  - [ ] Holiday/closed days are handled

### 5.5 Appointment Status Updates
- [ ] Status transitions are valid
- [ ] Status updates are logged
- [ ] Notifications are sent on status change
- [ ] Calendar updates reflect status changes

---

## 6. Medical Records Management

### 6.1 Medical Records
- [ ] Add medical record (`add_medical_record.php`)
  - [ ] All fields are saved correctly
  - [ ] Patient is linked correctly
  - [ ] Doctor is linked correctly
  - [ ] Date/time is saved correctly
  - [ ] Chief complaint is saved
  - [ ] Diagnosis is saved
  - [ ] Treatment is saved
  - [ ] Prescription is saved
  - [ ] Lab results are saved
  - [ ] Vital signs are saved (JSON format)
  - [ ] Notes are saved
  - [ ] Attachments are saved (if applicable)
  - [ ] Next appointment date is saved
- [ ] Edit medical record (`edit_medical_record.php`)
  - [ ] Updates persist correctly
  - [ ] All fields can be updated
  - [ ] History is maintained (if implemented)
- [ ] Medical records list
  - [ ] All records are displayed
  - [ ] Search/filter works
  - [ ] Records are linked to appointments
  - [ ] Patient information displays correctly

### 6.2 Medical History
- [ ] Add medical history (`add_medical_history.php`)
  - [ ] All fields are saved correctly
  - [ ] Date validation works
  - [ ] Patient is linked correctly
  - [ ] Condition/illness is saved
  - [ ] Treatment history is saved
  - [ ] Date ranges are validated
- [ ] Medical history list
  - [ ] All history items are displayed
  - [ ] Search/filter works
  - [ ] Chronological order is maintained
- [ ] Patient medical history view (`patient_medical_history.php`)
  - [ ] Patient can view their history
  - [ ] All history items are displayed
  - [ ] Formatting is correct

### 6.3 Patient Vitals
- [ ] Add vitals (`add_vitals.php`)
  - [ ] All vital signs are saved correctly
  - [ ] Blood pressure is saved
  - [ ] Temperature is saved
  - [ ] Heart rate is saved
  - [ ] Respiratory rate is saved
  - [ ] Weight is saved
  - [ ] Height is saved
  - [ ] BMI is calculated correctly
  - [ ] Numeric validation works
  - [ ] Date/time is saved correctly
- [ ] Vitals list
  - [ ] All vitals are displayed
  - [ ] Chronological order is maintained
- [ ] Vitals chart/graph (if implemented)
  - [ ] Chart displays correctly
  - [ ] Data is accurate
- [ ] Patient vitals view (`patient_vitals.php`)
  - [ ] Patient can view their vitals
  - [ ] All vitals are displayed
  - [ ] Formatting is correct

### 6.4 Prescriptions
- [ ] Prescriptions are linked to medical records
- [ ] Prescription list displays correctly
- [ ] Prescription details are complete
  - [ ] Medication name
  - [ ] Dosage
  - [ ] Frequency
  - [ ] Duration
  - [ ] Instructions
- [ ] Patient prescriptions view (`patient_prescriptions.php`)
  - [ ] Patient can view prescriptions
  - [ ] All prescriptions are displayed
  - [ ] Formatting is correct
- [ ] Prescription printing (if implemented)
  - [ ] Print layout is correct
  - [ ] All information is visible

### 6.5 Patient Notes
- [ ] Add patient note (`patient_notes.php`)
  - [ ] Note type is saved correctly
  - [ ] Note content is saved correctly
  - [ ] Attachment upload works
  - [ ] Doctor is linked correctly
  - [ ] Date/time is saved correctly
- [ ] Patient notes list
  - [ ] All notes are displayed
  - [ ] Search/filter works
  - [ ] Chronological order is maintained
- [ ] Note attachments
  - [ ] Attachments are accessible
  - [ ] File download works
  - [ ] File preview works (if implemented)
- [ ] Patient notes view
  - [ ] Patient can view notes (if allowed)
  - [ ] Formatting is correct

### 6.6 Patient Overview
- [ ] Patient overview (`patient_overview.php`)
  - [ ] All data is aggregated correctly
  - [ ] Recent records are displayed
  - [ ] Statistics are accurate
  - [ ] Links to detailed views work
  - [ ] Medical records summary
  - [ ] Appointments summary
  - [ ] Vitals summary
  - [ ] Prescriptions summary

---

## 7. Billing & Transactions

### 7.1 Transaction Management
- [ ] Add transaction (`add_transaction.php`)
  - [ ] All fields are saved correctly
  - [ ] Patient is linked correctly
  - [ ] Items are saved correctly
  - [ ] Item quantities are saved
  - [ ] Item prices are saved
  - [ ] Subtotal is calculated correctly
  - [ ] Tax is calculated correctly (if applicable)
  - [ ] Total is calculated correctly
  - [ ] Payment method is saved
  - [ ] Payment status is saved
  - [ ] Date/time is saved correctly
  - [ ] Transaction number is generated
- [ ] Edit transaction (`edit_transaction.php`)
  - [ ] Updates persist correctly
  - [ ] Totals are recalculated
  - [ ] Items can be added/removed
  - [ ] Payment status can be updated
- [ ] View transaction (`view_transaction.php`)
  - [ ] All details are displayed correctly
  - [ ] Items are listed correctly
  - [ ] Totals are displayed correctly
  - [ ] Patient information is shown
  - [ ] Transaction number is shown
  - [ ] Date/time is shown

### 7.2 Fee Management
- [ ] Fee configuration (`fees.php`)
  - [ ] Fees can be added
  - [ ] Fees can be edited
  - [ ] Fees can be deleted (if implemented)
  - [ ] Fee categories work correctly
  - [ ] Fee amounts are saved correctly
- [ ] Fee application
  - [ ] Fees are applied correctly to transactions
  - [ ] Fee updates are reflected in new transactions
  - [ ] Fee history is maintained (if implemented)

### 7.3 Receipt Generation
- [ ] Receipt preview (`preview.php`)
  - [ ] All details are displayed correctly
  - [ ] Formatting is correct
  - [ ] Totals are accurate
  - [ ] Patient information is shown
  - [ ] Transaction number is shown
  - [ ] Date/time is shown
  - [ ] Items are listed correctly
- [ ] Print receipt (`print_receipt.php`)
  - [ ] Print layout is correct
  - [ ] All information is visible
  - [ ] Currency formatting is correct (₱)
  - [ ] Print styles are applied (`assets/css/print.css`)
  - [ ] Print dialog works
  - [ ] Print to PDF works
  - [ ] Page breaks work correctly

### 7.4 Reports
- [ ] Daily sales report (`daily_sales.php`)
  - [ ] Date filtering works
  - [ ] Totals are accurate
  - [ ] Transactions are listed correctly
  - [ ] Summary statistics are correct
- [ ] Report analytics (`report_analytics.php`)
  - [ ] Date range filtering works
  - [ ] Filters are applied correctly
  - [ ] Data is aggregated correctly
  - [ ] Charts/graphs display correctly (if implemented)
  - [ ] Export works (if implemented)

### 7.5 Currency & Formatting
- [ ] Currency formatting (`formatCurrency()`)
  - [ ] Displays as ₱X,XXX.XX
  - [ ] Handles null/empty values
  - [ ] Handles decimal values correctly
  - [ ] Handles large numbers correctly
- [ ] Date formatting (`formatDate()`)
  - [ ] Formats correctly
  - [ ] Handles null/empty values
- [ ] Time formatting (`formatTime()`)
  - [ ] Formats correctly
  - [ ] Handles null/empty values

---

## 8. Notification System

### 8.1 In-App Notifications
- [ ] Notification creation
  - [ ] `createNotification()` works for patients
  - [ ] `createAdminNotification()` works for admins
  - [ ] Notifications are saved to database
  - [ ] Notification types are correct
- [ ] Notification display
  - [ ] Notification list displays correctly
  - [ ] Unread count is accurate (`getUnreadNotificationCount()`)
  - [ ] Unread count for admin is accurate (`getUnreadAdminNotificationCount()`)
  - [ ] Notification badges update correctly
- [ ] Notification actions
  - [ ] Mark notification as read (`mark_notification_read.php`)
  - [ ] Mark all as read (`mark_all_notifications_read.php`)
  - [ ] Delete notification (`delete_notification.php`)
- [ ] Notification types
  - [ ] Registration approved
  - [ ] Registration rejected
  - [ ] Appointment approved
  - [ ] Appointment rejected
  - [ ] Appointment reminder
  - [ ] Medical record updated
  - [ ] Prescription added
  - [ ] General notifications

### 8.2 Email Notifications
- [ ] Email queue system
  - [ ] Emails are queued in `email_queue` table
  - [ ] Queue status is tracked
  - [ ] Failed emails are logged
- [ ] Email sending (`sendEmailNotification()`)
  - [ ] PHPMailer is configured correctly
  - [ ] SMTP settings are correct
  - [ ] Emails are sent successfully
  - [ ] Email retry mechanism works (max 3 attempts)
- [ ] Email templates
  - [ ] Registration approval email is correct
  - [ ] Registration rejection email is correct
  - [ ] Appointment approval email is correct
  - [ ] Appointment rejection email is correct
  - [ ] Appointment reminder email is correct
  - [ ] Medical record update email is correct
  - [ ] Email formatting is correct (HTML/text)
  - [ ] Email links work correctly
- [ ] Email processing (`process_notifications.php`)
  - [ ] Queue is processed correctly
  - [ ] Status updates are saved
  - [ ] Error handling works
  - [ ] Cron job is configured (if automated)
- [ ] Test email (`test_email_system.php`)
  - [ ] Test email sends successfully
  - [ ] Configuration is verified

---

## 9. File Upload & Management

### 9.1 File Upload Validation
- [ ] File type validation
  - [ ] Images: JPG, PNG, GIF are accepted
  - [ ] Documents: PDF, DOC, DOCX are accepted
  - [ ] Invalid types are rejected
  - [ ] MIME type validation works
- [ ] File size validation
  - [ ] Profile images: 5MB max
  - [ ] Medical record attachments: 5MB max
  - [ ] Patient notes: 100MB max
  - [ ] PRC ID: 10MB max
  - [ ] Files exceeding limits are rejected
- [ ] File name sanitization
  - [ ] Special characters are handled
  - [ ] Unique file names are generated
  - [ ] File extensions are preserved
- [ ] File upload errors
  - [ ] Upload errors are handled
  - [ ] Error messages are clear

### 9.2 File Storage
- [ ] Files are saved to correct directories
  - [ ] Profile images: `uploads/profile_images/`
  - [ ] Medical record attachments: `uploads/`
  - [ ] Patient notes: `uploads/notes/`
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

## 10. API Endpoints & Data Retrieval

### 10.1 Doctor Endpoints
- [ ] `get_doctors_by_department.php`
  - [ ] Returns JSON format
  - [ ] Filters by department correctly
  - [ ] Returns only active doctors
  - [ ] Handles invalid department IDs
  - [ ] Returns empty array for departments with no doctors
  - [ ] Response time is acceptable
- [ ] `get_doctor_schedule.php`
  - [ ] Returns schedule for specified doctor
  - [ ] Date filtering works
  - [ ] Handles invalid doctor IDs
  - [ ] JSON format is correct
- [ ] `get_doctor_availability.php`
  - [ ] Returns availability correctly
  - [ ] Excludes booked appointments
  - [ ] Handles date ranges correctly
  - [ ] JSON format is correct
- [ ] `get_doctor_overview.php`
  - [ ] Aggregates data correctly
  - [ ] Statistics are accurate
  - [ ] Appointment counts are correct
  - [ ] JSON format is correct

### 10.2 Appointment Endpoints
- [ ] `get_appointments.php`
  - [ ] Returns JSON format
  - [ ] Filtering works (status, date, doctor, patient)
  - [ ] Pagination works (if implemented)
  - [ ] Response time is acceptable
- [ ] `get_doctor_appointments.php`
  - [ ] Returns only doctor's appointments
  - [ ] Filtering works
  - [ ] JSON format is correct
- [ ] `get_patient_appointments.php`
  - [ ] Returns only patient's appointments
  - [ ] Filtering works
  - [ ] JSON format is correct
- [ ] `get_appointment_details.php`
  - [ ] Returns complete appointment information
  - [ ] Handles invalid appointment IDs
  - [ ] JSON format is correct
- [ ] `get_available_time_slots.php`
  - [ ] Returns available time slots
  - [ ] Excludes booked appointments
  - [ ] Respects doctor schedule
  - [ ] Handles date validation
  - [ ] JSON format is correct

### 10.3 Data Endpoints
- [ ] PH LGU data (`assets/data/ph_lgu_data.json`)
  - [ ] File loads correctly
  - [ ] JSON parsing works
  - [ ] Data is accessible where needed
- [ ] Drugs data (`assets/data/drugs.json`)
  - [ ] File loads correctly
  - [ ] JSON parsing works
  - [ ] Data is accessible where needed
- [ ] Error handling for missing files
  - [ ] Graceful error handling
  - [ ] User-friendly error messages

### 10.4 API Security
- [ ] Direct access to API endpoints is restricted (if needed)
- [ ] Authentication is required (if implemented)
- [ ] Input validation is performed
- [ ] SQL injection is prevented
- [ ] XSS is prevented in responses

---

## 11. Dashboard & Reporting

### 11.1 Admin Dashboard
- [ ] Admin dashboard (`admin_dashboard.php`)
  - [ ] Page loads correctly
  - [ ] Metrics are displayed
  - [ ] Statistics are accurate
  - [ ] Recent activities are shown
  - [ ] Quick links work
  - [ ] Charts/graphs display (if implemented)

### 11.2 Doctor Dashboard
- [ ] Doctor dashboard (`doctor_dashboard.php`)
  - [ ] Page loads correctly
  - [ ] Metrics are displayed
  - [ ] Statistics are accurate
  - [ ] Today's appointments are shown
  - [ ] Upcoming appointments are shown
  - [ ] Quick links work

### 11.3 Patient Dashboard
- [ ] Patient dashboard (`patient_dashboard.php`)
  - [ ] Page loads correctly
  - [ ] Calendar displays correctly
  - [ ] Upcoming appointments are shown
  - [ ] Notifications are displayed
  - [ ] Quick actions work
  - [ ] Links to other pages work

### 11.4 Reports
- [ ] Report analytics (`report_analytics.php`)
  - [ ] Filters work correctly
  - [ ] Results are accurate
  - [ ] Date ranges work
  - [ ] Charts/graphs display (if implemented)
  - [ ] Export works (if implemented)
- [ ] Daily sales (`daily_sales.php`)
  - [ ] Date filtering works
  - [ ] Totals are accurate
  - [ ] Transactions are listed correctly

---

## 12. UI/UX & Frontend

### 12.1 Page Layout
- [ ] Header displays correctly (`includes/header.php`)
  - [ ] Logo displays
  - [ ] Navigation menu works
  - [ ] User information displays
  - [ ] Logout link works
- [ ] Footer displays correctly (`includes/footer.php`)
  - [ ] Footer content displays
  - [ ] Links work
- [ ] Navigation menu
  - [ ] All links work
  - [ ] Active page is highlighted
  - [ ] Role-based menu items display correctly
- [ ] Sidebar (if applicable)
  - [ ] Displays correctly
  - [ ] Links work
- [ ] Page titles are correct
- [ ] Breadcrumbs work (if implemented)

### 12.2 Forms
- [ ] All forms display correctly
- [ ] Form validation messages are clear
- [ ] Required fields are marked
- [ ] Form submission works
- [ ] Form reset works
- [ ] Auto-fill works (if implemented)
- [ ] Date pickers work correctly
- [ ] Time pickers work correctly
- [ ] Dropdowns populate correctly
- [ ] File upload fields work

### 12.3 Tables & Lists
- [ ] Data tables display correctly
- [ ] Pagination works (if implemented)
- [ ] Sorting works (if implemented)
- [ ] Filtering works
- [ ] Search works
- [ ] Empty states are handled
- [ ] Loading states are shown

### 12.4 Modals & Dialogs
- [ ] Modals open correctly
- [ ] Modals close correctly
- [ ] Form submissions in modals work
- [ ] Modal content loads correctly
- [ ] Backdrop click closes modal (if implemented)

### 12.5 Styling
- [ ] CSS files load correctly
  - [ ] `assets/css/style.css`
  - [ ] `assets/css/login.css`
  - [ ] `assets/css/print.css`
- [ ] Styles are applied correctly
- [ ] No broken CSS links
- [ ] Print styles work correctly
- [ ] Responsive styles work

### 12.6 Images & Assets
- [ ] Logo displays correctly (`img/logo.png`)
- [ ] Background images load (`img/bg.jpg`, `img/bg2.jpg`)
- [ ] Default profile images work (`img/defaultDP.jpg`)
- [ ] All image paths are correct
- [ ] Image alt text is provided
- [ ] Images load quickly

### 12.7 JavaScript Functionality
- [ ] AJAX requests work correctly
- [ ] Form submissions via AJAX work
- [ ] Dynamic content updates work
- [ ] No JavaScript errors in console
- [ ] JavaScript is minified/optimized (if applicable)
- [ ] Calendar integration works (FullCalendar)
- [ ] Dropdown updates work (department → doctor)

### 12.8 User Feedback
- [ ] Success messages display correctly
- [ ] Error messages display correctly
- [ ] Loading indicators work
- [ ] Confirmation dialogs work
- [ ] Toast notifications work (if implemented)
- [ ] Form validation feedback is clear

---

## 13. Database & Data Integrity

### 13.1 Database Schema
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
  - [ ] `fee_categories`
  - [ ] `notifications`
  - [ ] `email_queue`
  - [ ] `departments`
  - [ ] `patient_registration_requests`

### 13.2 Database Migrations
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

### 13.3 Database Constraints
- [ ] Foreign key constraints are properly set
- [ ] Unique constraints are enforced (email, username, patient_number)
- [ ] NOT NULL constraints are enforced where needed
- [ ] Default values are set correctly
- [ ] Indexes are created for frequently queried columns

### 13.4 Data Integrity
- [ ] No orphaned records
  - [ ] Doctors without users
  - [ ] Appointments without patients
  - [ ] Medical records without patients
  - [ ] Transactions without patients
- [ ] Referential integrity is maintained
- [ ] Cascade deletes work correctly (if implemented)
- [ ] Data relationships are consistent
- [ ] Department assignments are consistent between `users` and `doctors` tables

### 13.5 Database Performance
- [ ] Queries execute in reasonable time (< 1 second for most queries)
- [ ] Slow query log is monitored
- [ ] Database indexes are optimized
- [ ] No N+1 query problems
- [ ] Connection pooling works (if implemented)

### 13.6 Database Connection
- [ ] Database connection works (`config/database.php`)
- [ ] Database uses utf8mb4 charset
- [ ] Connection error handling works correctly
- [ ] Database credentials are secure (not in version control)
- [ ] Connection is closed properly

---

## 14. Email Integration

### 14.1 Email Configuration
- [ ] SMTP settings are correct
- [ ] SMTP authentication works
- [ ] Port configuration is correct (587 for TLS, 465 for SSL)
- [ ] SSL/TLS encryption works
- [ ] Email credentials are secure
- [ ] PHPMailer is properly configured

### 14.2 Email Sending
- [ ] Test email sends successfully (`test_email_system.php`)
- [ ] Email queue processes correctly
- [ ] Email retry mechanism works
- [ ] Failed emails are logged
- [ ] Email delivery is reliable
- [ ] Email headers are correct

### 14.3 Email Templates
- [ ] Registration approval email is correct
- [ ] Registration rejection email is correct
- [ ] Appointment approval email is correct
- [ ] Appointment rejection email is correct
- [ ] Appointment reminder email is correct
- [ ] Medical record update email is correct
- [ ] Email formatting is correct (HTML/text)
- [ ] Email links work correctly
- [ ] Email content is personalized

---

## 15. Print & Export Functions

### 15.1 Receipt Printing
- [ ] Receipt preview displays correctly
- [ ] Print layout is correct
- [ ] All information is visible
- [ ] Totals are accurate
- [ ] Print styles are applied (`assets/css/print.css`)
- [ ] Print dialog works
- [ ] Print to PDF works
- [ ] Page breaks work correctly
- [ ] Margins are correct

### 15.2 Report Generation
- [ ] Reports generate correctly
- [ ] Data is accurate
- [ ] Formatting is correct
- [ ] Charts/graphs display (if implemented)
- [ ] Reports can be exported (if implemented)
- [ ] Date ranges work correctly
- [ ] Filters are applied correctly
- [ ] Export formats work (PDF, Excel, CSV)

### 15.3 Print Styles
- [ ] Print CSS is loaded correctly
- [ ] Unnecessary elements are hidden
- [ ] Page breaks work correctly
- [ ] Margins are correct
- [ ] Font sizes are appropriate
- [ ] Colors are appropriate for printing

---

## 16. Performance & Load

### 16.1 Page Load Times
- [ ] Homepage loads in < 2 seconds
- [ ] Dashboard loads in < 3 seconds
- [ ] List pages load in < 3 seconds
- [ ] Form pages load in < 2 seconds
- [ ] Report pages load in < 5 seconds
- [ ] API endpoints respond in < 1 second

### 16.2 Database Performance
- [ ] Queries execute in < 1 second
- [ ] No N+1 query problems
- [ ] Database indexes are optimized
- [ ] Connection pooling works (if implemented)
- [ ] Slow queries are identified and optimized

### 16.3 File Upload Performance
- [ ] Small files (< 1MB) upload quickly
- [ ] Large files (5MB) upload within reasonable time
- [ ] Progress indicators work (if implemented)
- [ ] Upload doesn't timeout

### 16.4 Concurrent Users
- [ ] System handles 10 concurrent users
- [ ] System handles 50 concurrent users
- [ ] System handles 100 concurrent users
- [ ] No session conflicts
- [ ] No data conflicts
- [ ] Database connections are managed properly

### 16.5 Resource Usage
- [ ] Memory usage is reasonable
- [ ] CPU usage is reasonable
- [ ] Disk space is monitored
- [ ] Database connections are closed properly
- [ ] File handles are closed properly

---

## 17. Browser & Device Compatibility

### 17.1 Desktop Browsers
- [ ] Chrome (latest version)
  - [ ] All features work
  - [ ] No console errors
  - [ ] Styles render correctly
- [ ] Firefox (latest version)
  - [ ] All features work
  - [ ] No console errors
  - [ ] Styles render correctly
- [ ] Edge (latest version)
  - [ ] All features work
  - [ ] No console errors
  - [ ] Styles render correctly
- [ ] Safari (latest version, if applicable)
  - [ ] All features work
  - [ ] No console errors
  - [ ] Styles render correctly

### 17.2 Browser Features
- [ ] JavaScript is enabled
- [ ] Cookies are enabled
- [ ] Local storage works (if used)
- [ ] Session storage works (if used)
- [ ] AJAX requests work

### 17.3 Mobile Devices
- [ ] iPhone (Safari)
  - [ ] Layout adapts correctly
  - [ ] Forms are usable
  - [ ] Touch interactions work
- [ ] Android (Chrome)
  - [ ] Layout adapts correctly
  - [ ] Forms are usable
  - [ ] Touch interactions work
- [ ] Tablet (iPad, Android tablet)
  - [ ] Layout adapts correctly
  - [ ] Forms are usable
  - [ ] Touch interactions work

### 17.4 Responsive Design
- [ ] Layout adapts to small screens
- [ ] Navigation works on mobile
- [ ] Forms are usable on mobile
- [ ] Tables are scrollable/adaptable
- [ ] Images scale correctly
- [ ] Text is readable without zooming
- [ ] Date pickers work on mobile
- [ ] File uploads work on mobile

---

## 18. Error Handling & Edge Cases

### 18.1 Form Validation Errors
- [ ] Empty required fields show errors
- [ ] Invalid email format shows error
- [ ] Invalid phone format shows error
- [ ] Invalid date format shows error
- [ ] Password mismatch shows error
- [ ] File size exceeded shows error
- [ ] Invalid file type shows error
- [ ] Duplicate entries show error

### 18.2 Database Errors
- [ ] Connection failure is handled gracefully
- [ ] Query errors are logged
- [ ] User-friendly error messages are shown
- [ ] No sensitive information is exposed
- [ ] Database timeout is handled

### 18.3 Network Errors
- [ ] AJAX failures are handled
- [ ] Timeout errors are handled
- [ ] Offline mode is handled (if applicable)
- [ ] Network interruption is handled

### 18.4 Edge Cases
- [ ] Very long text inputs are handled
- [ ] Special characters are handled
- [ ] Unicode characters are handled
- [ ] Empty data sets are handled
- [ ] Null values are handled
- [ ] Duplicate submissions are prevented
- [ ] Concurrent edits are handled
- [ ] Very large numbers are handled
- [ ] Negative numbers are handled (where applicable)
- [ ] Zero values are handled

### 18.5 Error Pages
- [ ] 404 error page works
- [ ] 403 error page works (`unauthorized.php`)
- [ ] 500 error page works
- [ ] Error pages are user-friendly
- [ ] Error pages include navigation

---

## 19. Configuration & Environment

### 19.1 Server Requirements
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

### 19.2 File Permissions
- [ ] `uploads/` directory exists and is writable (755 or 777)
- [ ] `uploads/notes/` directory exists and is writable
- [ ] `uploads/profile_images/` directory exists and is writable
- [ ] `backups/` directory exists and is writable
- [ ] Configuration files are readable but not publicly accessible
- [ ] `.htaccess` file is in place (if using Apache)

### 19.3 Path Configuration
- [ ] All file paths use correct directory separators
- [ ] Relative paths work correctly
- [ ] Absolute paths are configured correctly
- [ ] Base URL is configured correctly

### 19.4 Error Reporting
- [ ] Error reporting is disabled for production
- [ ] Error logging is enabled
- [ ] Error log file is writable
- [ ] Custom error pages are configured (if applicable)

### 19.5 Configuration Files
- [ ] `config/database.php` is configured correctly
- [ ] `config/auth.php` functions work
- [ ] `config/functions.php` functions work
- [ ] `config/init.php` initializes correctly
- [ ] `config/patient_auth.php` functions work

---

## 20. Backup & Recovery

### 20.1 Database Backup
- [ ] Backup script works
- [ ] Backup files are created correctly
- [ ] Backup includes all tables
- [ ] Backup files are stored securely
- [ ] Backup can be scheduled (cron job)
- [ ] Backup file naming is consistent

### 20.2 Database Restore
- [ ] Restore from backup works
- [ ] All data is restored correctly
- [ ] Relationships are maintained
- [ ] No data loss occurs
- [ ] Restore process is tested

### 20.3 Data Migration
- [ ] Migration scripts work correctly
- [ ] Data is migrated without loss
- [ ] Data integrity is maintained
- [ ] Rollback works (if implemented)
- [ ] Migration is tested on staging

### 20.4 Data Export
- [ ] Data can be exported (if implemented)
- [ ] Export format is correct
- [ ] All relevant data is included
- [ ] Export file is generated correctly

---

## 📊 Testing Summary

### Priority Levels
- **🔴 CRITICAL** - Must pass before deployment (Security, Authentication, Core Functions)
- **🟠 HIGH** - Should pass before deployment (Notifications, File Uploads, Data Validation)
- **🟡 MEDIUM** - Important but can be done after deployment (UI/UX, Performance, Browser Compatibility)
- **🟢 LOW** - Can be done post-deployment (Advanced Features, Edge Cases)

### Testing Checklist Summary
- **Total Test Items:** ~500+
- **Critical Tests:** ~150
- **High Priority Tests:** ~150
- **Medium Priority Tests:** ~150
- **Low Priority Tests:** ~50

### Recommended Testing Schedule
- **Week 1:** Critical & High Priority Tests
- **Week 2:** Medium Priority Tests & Bug Fixes
- **Week 3:** Low Priority Tests & Final Review

---

## ✅ Pre-Deployment Sign-Off

**Testing Completed By:** ________________  
**Date:** ________________  
**Status:** [ ] Ready for Deployment [ ] Needs Fixes  
**Critical Issues Found:** ________________  
**High Priority Issues Found:** ________________  
**Approved By:** ________________  
**Date:** ________________

---

**END OF TESTING LIST**

