# ERROR REPORT: Past Appointments Not Showing

## Date: Generated Analysis Report

## Issues Found

### Issue #1: Admin Dashboard Calendar - Past Appointments Not Showing

**Location:** `mhavis/admin_dashboard.php`  
**Line:** 774  
**Severity:** HIGH

**Problem:**
The calendar query filters out ALL past appointments, only showing appointments from today onwards.

**Current Code:**
```php
$debugCalendarQuery = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.patient_id, a.doctor_id,
                       COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Patient') as patient_name,
                       COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown Doctor') as doctor_name,
                       COALESCE(a.notes, '') as notes,
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       u.first_name as doctor_first_name, u.last_name as doctor_last_name
                       FROM appointments a
                       LEFT JOIN patients p ON a.patient_id = p.id
                       LEFT JOIN doctors d ON a.doctor_id = d.id
                       LEFT JOIN users u ON d.user_id = u.id
                       WHERE a.appointment_date >= ? 
                       AND (a.status IS NULL OR UPPER(TRIM(a.status)) NOT IN ('CANCELLED', 'CANCELED'))
                       ORDER BY a.appointment_date, a.appointment_time";
```

**Root Cause:**
- The `WHERE a.appointment_date >= ?` condition (line 774) only selects appointments from the current date forward
- Past appointments (dates before today) are completely excluded from the query
- The bound parameter `$currentDate` (line 783) ensures only future/today's appointments are shown

**Impact:**
- Past appointments never appear in the admin dashboard calendar
- Historical appointment data is not visible to admins in calendar view
- Admins cannot see appointment history when navigating to past dates in the calendar

---

### Issue #2: Patient Record - Past Appointments Status Not Displayed

**Location:** `mhavis/patient_appointment.php`  
**Lines:** 133-161  
**Severity:** MEDIUM

**Problem:**
The past appointments section does not display the appointment status badge/indicator. Only date, time, and doctor name are shown.

**Current Code:**
```php
<?php foreach ($month_data['appointments'] as $appointment): ?>
    <div class="record-card mb-3" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; background: white; margin-left: 20px;">
        <div class="d-flex justify-content-between align-items-center">
            <div class="flex-grow-1 d-flex align-items-center">
                <i class="fas fa-calendar-check me-3" style="color: #6c757d; font-size: 1.5rem;"></i>
                <div>
                    <div style="color: #333; font-weight: 600; font-size: 0.95rem;">
                        <?= date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                    </div>
                    <small class="text-muted">
                        <?= date('g:i A', strtotime($appointment['appointment_time'])); ?>
                        <?php if (!empty($appointment['doctor_first_name']) && !empty($appointment['doctor_last_name'])): ?>
                            • Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <!-- Status badge is MISSING here -->
        </div>
    </div>
<?php endforeach; ?>
```

**Root Cause:**
- The past appointments display loop (lines 133-161) does not include any status badge or status information
- Unlike the upcoming appointments section or other appointment views, there's no status display code
- The `$appointment['status']` field is available but not being rendered

**Comparison:**
Other sections show status badges (e.g., `appointments.php` lines 408-419, `patient_dashboard.php` lines 1548-1555), but past appointments in patient records do not.

**Impact:**
- Users cannot see the status (Scheduled, Completed, Settled, Cancelled, etc.) of past appointments
- Reduces visibility of appointment history and outcomes
- Inconsistent UI compared to other appointment displays

---

### Issue #3: Potential Date Comparison Logic Issue (Minor)

**Location:** `mhavis/patient_appointment.php`  
**Lines:** 25-31  
**Severity:** LOW

**Problem:**
The date comparison only compares dates, not date+time, which could incorrectly categorize appointments from earlier today as "upcoming" instead of "past".

**Current Code:**
```php
$today = date('Y-m-d');

while ($appointment = $appointments->fetch_assoc()) {
    if ($appointment['appointment_date'] >= $today) {
        $upcoming_appointments[] = $appointment;
    } else {
        $past_appointments[] = $appointment;
    }
}
```

**Root Cause:**
- Only compares `appointment_date >= $today` without considering the time
- An appointment scheduled for 9:00 AM today would still show as "upcoming" even if it's currently 3:00 PM
- Should compare full datetime: `appointment_date + appointment_time < current_datetime`

**Impact:**
- Appointments from earlier today may appear in "upcoming" section when they should be in "past"
- Minor UX inconsistency

---

## Summary

1. **CRITICAL:** Admin dashboard calendar completely excludes past appointments due to date filter
2. **IMPORTANT:** Patient record past appointments don't show status badges
3. **MINOR:** Date comparison logic doesn't account for time component

## Recommended Fixes

1. Modify admin dashboard calendar query to include past appointments (remove or adjust the date filter)
2. Add status badge display in the past appointments section of patient_appointment.php
3. Update date comparison logic to consider both date and time for accurate categorization

## Files Affected

1. `mhavis/admin_dashboard.php` - Line 774 (calendar query)
2. `mhavis/patient_appointment.php` - Lines 133-161 (past appointments display)
3. `mhavis/patient_appointment.php` - Lines 25-31 (date comparison logic)

---

*Report generated after code analysis. No changes have been made to the codebase.*

