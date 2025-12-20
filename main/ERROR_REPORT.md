# Code Error Report - Mhavis Medical System

This document lists all errors and potential issues found in the codebase.

## 🔴 CRITICAL SECURITY ISSUES (SQL Injection Vulnerabilities)

### 1. **patient_record.php - Line 11**
**Issue:** Direct variable interpolation in SQL query (SQL Injection vulnerability)
```php
$result = $conn->query("SELECT last_name FROM users WHERE username = '$username' LIMIT 1");
```
**Fix:** Use prepared statement:
```php
$stmt = $conn->prepare("SELECT last_name FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
```

### 2. **patient_record.php - Line 129**
**Issue:** Direct variable interpolation in SQL query (SQL Injection vulnerability)
```php
$patients = $conn->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
```
**Fix:** Use prepared statement:
```php
$stmt = $conn->prepare("SELECT * FROM patients ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$patients = $stmt->get_result();
```

### 3. **admin_dashboard.php - Line 21**
**Issue:** Direct variable interpolation in SQL query (though $today is from date(), it's still bad practice)
```php
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$today'");
```
**Fix:** Use prepared statement:
```php
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
```

### 4. **admin_dashboard.php - Lines 28-30, 43-45, 49-50**
**Issue:** Multiple direct variable interpolations in SQL queries
```php
$result = $conn->query("SELECT SUM(amount - COALESCE(discount_amount, 0)) as total FROM transactions 
                       WHERE DATE(transaction_date) BETWEEN '$firstDayOfMonth' AND '$today'
                       AND payment_status = 'Completed'");
```
**Fix:** Use prepared statements for all queries.

### 5. **doctors.php - Lines 445, 456**
**Issue:** Direct variable interpolation with $viewing_doctor_id (SQL Injection vulnerability)
```php
$debug_result = $conn->query("SELECT a.*, p.first_name, p.last_name FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id WHERE a.doctor_id = $viewing_doctor_id ORDER BY a.appointment_date ASC, a.appointment_time ASC");
$all_debug = $conn->query("SELECT * FROM appointments WHERE doctor_id = $viewing_doctor_id ORDER BY appointment_date ASC, appointment_time ASC");
```
**Fix:** Use prepared statements:
```php
$stmt = $conn->prepare("SELECT a.*, p.first_name, p.last_name FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id WHERE a.doctor_id = ? ORDER BY a.appointment_date ASC, a.appointment_time ASC");
$stmt->bind_param("i", $viewing_doctor_id);
$stmt->execute();
$debug_result = $stmt->get_result();
```

### 6. **patient_record.php - Line 126**
**Issue:** Direct query without prepared statement (though no user input, still inconsistent)
```php
$total = $conn->query("SELECT COUNT(*) as total FROM patients")->fetch_assoc()['total'];
```
**Note:** This is less critical as there's no user input, but should be consistent with prepared statements.

## 🟡 LOGIC ERRORS

### 7. **config/init.php - Lines 54-55**
**Issue:** Bug in sanitize function - checks if $input instanceof mysqli which doesn't make sense
```php
function sanitize($input) {
    if ($input instanceof mysqli) {
        return mysqli_real_escape_string($input, $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
```
**Problem:** The condition `$input instanceof mysqli` will never be true for user input. The function should check if it's a string/array and sanitize accordingly.
**Fix:** Remove the mysqli check or fix the logic:
```php
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
```

### 8. **config/init.php - Duplicate Function Definitions**
**Issue:** Functions are defined multiple times with `if (!function_exists())` checks, which can lead to confusion and maintenance issues. The functions are already defined in `config/functions.php` and `config/auth.php`, but `init.php` redefines them.
**Impact:** Code duplication and potential inconsistencies.

## 🟠 SECURITY & ARCHITECTURE ISSUES

### 9. **patient_record.php - Missing Proper Initialization**
**Issue:** File doesn't use the proper `config/init.php` initialization. It creates its own database connection:
```php
session_start();
$conn = new mysqli('localhost', 'root', '', 'mhavis');
```
**Problem:** 
- Hardcoded database credentials
- Doesn't use the centralized database connection function
- Doesn't follow the project's initialization pattern
- Missing proper authentication checks (uses old session check)
**Fix:** Replace with:
```php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireAdmin();
$conn = getDBConnection();
```

### 10. **Multiple Files - Direct query() Usage**
**Issue:** Many files use `$conn->query()` directly instead of prepared statements, even when user input is involved indirectly.
**Files affected:**
- `add_patient.php` (lines 88, 224) - Column checks (acceptable but inconsistent)
- `add_doctor.php` (multiple lines) - Column checks (acceptable but inconsistent)
- `admin_dashboard.php` (multiple lines) - Should use prepared statements
- `patient_prescriptions.php` (line 96) - No user input, but inconsistent
- Many other files for column/table existence checks

**Note:** Column existence checks (`SHOW COLUMNS`) are generally safe as they don't involve user data, but for consistency and best practices, consider using information_schema queries with prepared statements.

## 🟢 MINOR ISSUES & BEST PRACTICES

### 11. **login.php - Line 78-79**
**Issue:** Login attempts are incremented even when the user is found but password is wrong, and also when no user is found. This is actually correct behavior, but the code structure could be clearer.

### 12. **add_patient.php - Email Uniqueness Handling**
**Issue:** Lines 92-104 modify email addresses to ensure uniqueness by appending timestamps. While functional, this could lead to confusion. Consider showing a warning to the user instead.

### 13. **config/init.php - Error Display Settings**
**Issue:** Lines 11-12 have error display enabled:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```
**Problem:** This should be disabled in production for security reasons.
**Fix:** Use environment-based configuration:
```php
if (getenv('APP_ENV') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
```

### 14. **Multiple Files - Missing Error Handling**
**Issue:** Many database queries don't check for errors before using results.
**Example:** `admin_dashboard.php` line 12:
```php
$result = $conn->query("SELECT COUNT(*) as count FROM patients");
$totalPatients = $result->fetch_assoc()['count'];
```
**Fix:** Add error checking:
```php
$result = $conn->query("SELECT COUNT(*) as count FROM patients");
if ($result) {
    $totalPatients = $result->fetch_assoc()['count'];
} else {
    $totalPatients = 0;
    error_log("Database error: " . $conn->error);
}
```

## 📋 SUMMARY

**Total Issues Found:** 14
- **Critical Security Issues:** 6 (SQL Injection vulnerabilities)
- **Logic Errors:** 2
- **Security & Architecture Issues:** 2
- **Minor Issues & Best Practices:** 4

## 🔧 PRIORITY FIXES

1. **HIGH PRIORITY:** Fix all SQL injection vulnerabilities (#1-6)
2. **HIGH PRIORITY:** Fix sanitize function bug (#7)
3. **MEDIUM PRIORITY:** Refactor patient_record.php to use proper initialization (#9)
4. **MEDIUM PRIORITY:** Add error handling to database queries (#14)
5. **LOW PRIORITY:** Clean up duplicate function definitions (#8)
6. **LOW PRIORITY:** Configure error display for production (#13)

## 📝 NOTES

- Most `SHOW COLUMNS` and `SHOW TABLES` queries are acceptable as they're used for schema checks, not user data queries.
- The codebase generally uses prepared statements well, but there are several exceptions that need attention.
- Consider implementing a centralized error logging system.
- Consider using an ORM or query builder for better security and maintainability.





