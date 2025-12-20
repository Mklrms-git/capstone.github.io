# Authorization Testing Checklist

This document lists all pages that should redirect patients to the unauthorized page when accessed.

**Testing Instructions:**
1. Log in as a patient using `patient_login.php`
2. Try to access each URL below
3. **Expected Result:** Should redirect to `unauthorized.php`
4. Verify the unauthorized page shows "Go to Patient Dashboard" button

---

## 🔴 ADMIN-ONLY PAGES (requireAdmin() or requireRole('Admin'))

### Main Admin Pages
- [/] `http://localhost/mhavis/mhavis/admin_dashboard.php`
- [/] `http://localhost/mhavis/mhavis/admin_patient_registrations.php`
- [/] `http://localhost/mhavis/mhavis/admin_appointment_requests.php`
- [/] `http://localhost/mhavis/mhavis/fees.php`
- [/] `http://localhost/mhavis/mhavis/daily_sales.php`
- [/] `http://localhost/mhavis/mhavis/appointments.php`

### Doctor Management
- [ ] `http://localhost/mhavis/mhavis/doctors.php`
- [ ] `http://localhost/mhavis/mhavis/add_doctor.php`
- [ ] `http://localhost/mhavis/mhavis/edit_doctor.php`

### Appointment Management
- [ ] `http://localhost/mhavis/mhavis/add_appointment.php`
- [ ] `http://localhost/mhavis/mhavis/update_appointment_status.php`
- [ ] `http://localhost/mhavis/mhavis/view_transaction.php`

### Transaction Management
- [ ] `http://localhost/mhavis/mhavis/add_transaction.php`

### API/JSON Endpoints (Admin)
- [ ] `http://localhost/mhavis/mhavis/get_doctor_schedule.php`
- [ ] `http://localhost/mhavis/mhavis/get_doctor_overview.php`
- [ ] `http://localhost/mhavis/mhavis/get_doctor_appointments.php`
- [ ] `http://localhost/mhavis/mhavis/get_appointment_details.php`

---

## 🔵 DOCTOR-ONLY PAGES (requireDoctor() or requireRole('Doctor'))

### Doctor Dashboard & Features
- [ ] `http://localhost/mhavis/mhavis/doctor_dashboard.php`
- [ ] `http://localhost/mhavis/mhavis/doctor_decide_appointment.php`
- [ ] `http://localhost/mhavis/mhavis/my_appointments.php` (Note: This is for doctors, not patients)

---

## 🟡 ADMIN/DOCTOR SHARED PAGES (requireLogin() - but should block patients)

These pages use `requireLogin()` but should only be accessible by Admin/Doctor users (not patients). They check roles internally.

### Patient Management
- [ ] `http://localhost/mhavis/mhavis/patients.php` (Admin: "Patient Records" / Doctor: "My Patients")
- [ ] `http://localhost/mhavis/mhavis/add_patient.php`
- [ ] `http://localhost/mhavis/mhavis/edit_patient.php`

### Medical Records
- [ ] `http://localhost/mhavis/mhavis/add_medical_record.php`
- [ ] `http://localhost/mhavis/mhavis/edit_medical_record.php`
- [ ] `http://localhost/mhavis/mhavis/add_medical_history.php`
- [ ] `http://localhost/mhavis/mhavis/patient_notes.php`
- [ ] `http://localhost/mhavis/mhavis/add_vitals.php`

### Transactions
- [ ] `http://localhost/mhavis/mhavis/edit_transaction.php`

### Reports & Analytics
- [ ] `http://localhost/mhavis/mhavis/report_analytics.php`

### Other Admin/Doctor Pages
- [ ] `http://localhost/mhavis/mhavis/profile.php` (Admin/Doctor profile, not patient)
- [ ] `http://localhost/mhavis/mhavis/print_receipt.php`
- [ ] `http://localhost/mhavis/mhavis/get_appointments.php` (API endpoint)

---

## 📝 TESTING NOTES

### Pages That Should NOT Redirect Patients
The following pages are specifically for patients and should NOT redirect:
- `patient_dashboard.php`
- `my_appointments.php` (patient version - if exists)
- `patient_medical_records.php`
- `patient_medical_history.php`
- `patient_prescriptions.php`
- `patient_notes.php` (patient version)
- `patient_vitals.php`
- `patient_overview.php`
- `patient_record.php`

### Test Cases

#### Test Case 1: Admin Dashboard Access
1. Log in as patient
2. Navigate to: `http://localhost/mhavis/mhavis/admin_dashboard.php`
3. **Expected:** Redirected to `unauthorized.php`
4. **Verify:** Unauthorized page shows "Go to Patient Dashboard" button

#### Test Case 2: Doctor Dashboard Access
1. Log in as patient
2. Navigate to: `http://localhost/mhavis/mhavis/doctor_dashboard.php`
3. **Expected:** Redirected to `unauthorized.php`
4. **Verify:** Unauthorized page shows "Go to Patient Dashboard" button

#### Test Case 3: Admin Management Pages
1. Log in as patient
2. Try accessing:
   - `doctors.php`
   - `add_doctor.php`
   - `edit_doctor.php`
   - `fees.php`
   - `admin_patient_registrations.php`
3. **Expected:** All redirect to `unauthorized.php`

#### Test Case 4: Direct URL Access
1. Log in as patient
2. Type any admin/doctor URL directly in browser
3. **Expected:** Redirected to `unauthorized.php`
4. **Verify:** Cannot bypass by direct URL access

---

## ✅ COMPLETION CHECKLIST

- [ ] All Admin-only pages tested
- [ ] All Doctor-only pages tested
- [ ] All Admin/Doctor shared pages tested
- [ ] API/JSON endpoints tested
- [ ] Unauthorized page displays correctly
- [ ] "Go to Patient Dashboard" button works
- [ ] Patient cannot bypass authorization
- [ ] Direct URL access is blocked

---

## 🔍 AUTHORIZATION FUNCTIONS USED

The following functions are used for authorization:

1. **`requireAdmin()`** - Checks for patient session, then admin role
2. **`requireDoctor()`** - Checks for patient session, then doctor role
3. **`requireRole('Admin')`** - Checks for patient session, then specific role
4. **`requireLogin()`** - Only checks for staff login (admin/doctor), not patient

All these functions now check if a patient is logged in first and redirect to `unauthorized.php` before checking for admin/doctor roles.

---

**Last Updated:** 2025-01-XX
**Total Pages to Test:** ~30+ pages


