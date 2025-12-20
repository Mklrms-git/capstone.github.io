# Profile Contact Information Update - Testing Guide

## Prerequisites
1. Access to the application with a valid user account (Admin, Doctor, or Super Admin)
2. Access to phpMyAdmin or MySQL command line to verify database changes
3. Browser with developer tools (F12) enabled

## Test Environment Setup

### 1. Get Your User ID
Before testing, get your user ID from the database:
```sql
SELECT id, username, email, phone FROM users WHERE username = 'your_username';
```
Note down your user ID for verification queries.

### 2. Open Browser Developer Tools
- Press F12 to open developer tools
- Go to "Network" tab
- Filter by "XHR" or "Fetch" to see AJAX requests
- Keep "Console" tab open to see any JavaScript errors

---

## Test Cases

### ✅ Test 1: Update Email with Unique Email

**Steps:**
1. Navigate to `profile.php`
2. Note your current email in the "Personal Information" panel (left side)
3. In the "Account Settings" form (right side), enter a NEW unique email address
4. Click "Save Profile Information" button
5. Watch for success message

**Expected Results:**
- ✅ Success message: "Profile information updated successfully!"
- ✅ Email in "Personal Information" panel updates immediately (without page refresh)
- ✅ Form input shows the new email
- ✅ No JavaScript errors in console

**Database Verification:**
```sql
SELECT id, email, phone FROM users WHERE id = YOUR_USER_ID;
```
- Email should match what you entered

**AJAX Response Check:**
- In Network tab, find the request to `profile.php`
- Check Response tab - should show JSON with `success: true` and `email: "your_new_email"`

---

### ✅ Test 2: Update Phone with Valid Phone Number

**Steps:**
1. Navigate to `profile.php`
2. Note your current phone in the "Personal Information" panel
3. In the phone field, enter a valid 10-digit number (e.g., `9123456789`)
4. Click "Save Profile Information"

**Expected Results:**
- ✅ Success message appears
- ✅ Phone in "Personal Information" panel updates to `+639123456789` format
- ✅ Form input shows `9123456789` (without +63 prefix)
- ✅ No errors

**Database Verification:**
```sql
SELECT id, email, phone FROM users WHERE id = YOUR_USER_ID;
```
- Phone should be stored as `+639123456789`

**AJAX Response Check:**
- Response should include `phone: "+639123456789"`

---

### ✅ Test 3: Update Both Email and Phone Together

**Steps:**
1. Navigate to `profile.php`
2. Enter a new email address
3. Enter a new phone number (10 digits)
4. Click "Save Profile Information"

**Expected Results:**
- ✅ Both fields update successfully
- ✅ Both display correctly in "Personal Information" panel
- ✅ Success message appears

**Database Verification:**
```sql
SELECT id, email, phone FROM users WHERE id = YOUR_USER_ID;
```
- Both email and phone should be updated

---

### ✅ Test 4: Clear Email (Set to Empty)

**Steps:**
1. Navigate to `profile.php`
2. If you have an email, clear the email field (delete all text)
3. Leave email field empty
4. Click "Save Profile Information"

**Expected Results:**
- ✅ Success message appears
- ✅ Email in "Personal Information" panel shows "N/A"
- ✅ Email field in form is empty

**Database Verification:**
```sql
SELECT id, email, phone FROM users WHERE id = YOUR_USER_ID;
```
- Email should be empty string `''` or NULL

**AJAX Response Check:**
- Response should include `email: ""` (empty string)

---

### ✅ Test 5: Clear Phone (Set to Empty)

**Steps:**
1. Navigate to `profile.php`
2. If you have a phone, clear the phone field
3. Leave phone field empty
4. Click "Save Profile Information"

**Expected Results:**
- ✅ Success message appears
- ✅ Phone in "Personal Information" panel shows "N/A"
- ✅ Phone field in form is empty

**Database Verification:**
```sql
SELECT id, email, phone FROM users WHERE id = YOUR_USER_ID;
```
- Phone should be empty string `''` or NULL

---

### ✅ Test 6: Duplicate Email Error Handling

**Steps:**
1. First, check what emails exist in the system:
   ```sql
   SELECT id, email FROM users WHERE id != YOUR_USER_ID LIMIT 5;
   ```
2. Navigate to `profile.php`
3. Enter an email that belongs to another user (from step 1)
4. Click "Save Profile Information"

**Expected Results:**
- ❌ Error message: "Email address already exists. Please use a different email."
- ❌ Email is NOT updated in database
- ✅ If you also changed name or phone, those should still update (if no other errors)

**Database Verification:**
```sql
SELECT id, email FROM users WHERE id = YOUR_USER_ID;
```
- Email should remain unchanged (your original email)

**AJAX Response Check:**
- Response should show `success: false` and error message

---

### ✅ Test 7: Invalid Email Format

**Steps:**
1. Navigate to `profile.php`
2. Enter invalid email format (e.g., `invalid-email`, `test@`, `@domain.com`)
3. Click "Save Profile Information"

**Expected Results:**
- ❌ Error message: "Invalid email format."
- ❌ No database updates occur

**Database Verification:**
```sql
SELECT id, email FROM users WHERE id = YOUR_USER_ID;
```
- Email should remain unchanged

---

### ✅ Test 8: Invalid Phone Format

**Steps:**
1. Navigate to `profile.php`
2. Enter invalid phone:
   - Less than 10 digits: `12345`
   - More than 10 digits: `123456789012`
   - Contains letters: `abc1234567`
3. Click "Save Profile Information"

**Expected Results:**
- ❌ Error message: "Phone number must be exactly 10 digits."
- ❌ No database updates occur

**Note:** The form should prevent non-numeric input, but test with copy-paste

---

### ✅ Test 9: No Changes Detected

**Steps:**
1. Navigate to `profile.php`
2. Don't change any values (keep email, phone, name as they are)
3. Click "Save Profile Information"

**Expected Results:**
- ❌ Error message: "No changes detected. Please modify at least one field (name, email, or phone) before saving."
- ❌ No database updates occur

**Database Verification:**
```sql
SELECT id, email, phone, updated_at FROM users WHERE id = YOUR_USER_ID;
```
- `updated_at` timestamp should NOT change

---

### ✅ Test 10: Phone Format Variations

**Steps:**
Test with different phone input formats (all should normalize to +639123456789):

1. **10 digits only:** `9123456789`
   - Should save as: `+639123456789`
   - Display should show: `+639123456789`

2. **With leading 0:** `09123456789`
   - Should save as: `+639123456789`
   - Display should show: `+639123456789`

3. **With +63 prefix:** `+639123456789` (if pasted)
   - Should save as: `+639123456789`
   - Display should show: `+639123456789`

**Expected Results:**
- All formats should work correctly
- All should save in the same format: `+639123456789`
- Display should show: `+639123456789`

---

### ✅ Test 11: AJAX Response Structure

**Steps:**
1. Open browser developer tools (F12)
2. Go to Network tab
3. Update contact information
4. Find the request to `profile.php`
5. Check the Response

**Expected Response Structure:**
```json
{
    "success": true,
    "message": "Profile information updated successfully!",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+639123456789"
}
```

**For Empty Values:**
```json
{
    "success": true,
    "message": "Profile information updated successfully!",
    "first_name": "John",
    "last_name": "Doe",
    "email": "",
    "phone": ""
}
```

**Expected Results:**
- ✅ Response is valid JSON
- ✅ All fields are present (even if empty)
- ✅ Values match what was saved to database

---

### ✅ Test 12: Page Refresh After Update

**Steps:**
1. Update contact information
2. Wait for success message
3. Refresh the page (F5)

**Expected Results:**
- ✅ Updated values persist after refresh
- ✅ "Personal Information" panel shows updated values
- ✅ Form inputs show updated values

**Database Verification:**
```sql
SELECT id, email, phone FROM users WHERE id = YOUR_USER_ID;
```
- Values should match what you see on the page

---

## Common Issues to Check

### Issue: Display Not Updating After AJAX Success
**Check:**
1. Open browser console (F12 → Console tab)
2. Look for JavaScript errors
3. Check if `updatePersonalInfoPanel` function is being called
4. Verify AJAX response contains correct values

### Issue: Database Not Updating
**Check:**
1. Verify no PHP errors in server logs
2. Check database connection
3. Verify user has UPDATE permissions
4. Check if `$hasChanges` is being set correctly

### Issue: Duplicate Email Error When Email Is Unique
**Check:**
1. Verify email comparison logic (case-insensitive)
2. Check if email has trailing spaces
3. Verify database query for duplicate check

### Issue: Phone Not Formatting Correctly
**Check:**
1. Verify phone normalization function
2. Check if phone has exactly 10 digits after normalization
3. Verify database stores phone as `+63XXXXXXXXXX`

---

## Quick Verification Script

Run this SQL query after each test to verify changes:

```sql
-- Replace YOUR_USER_ID with your actual user ID
SELECT 
    id,
    first_name,
    last_name,
    email,
    phone,
    updated_at,
    TIMESTAMPDIFF(SECOND, updated_at, NOW()) as seconds_ago
FROM users 
WHERE id = YOUR_USER_ID;
```

This shows:
- Current values in database
- When the record was last updated
- How many seconds ago the update occurred

---

## Success Criteria

All tests should pass with:
- ✅ No JavaScript errors in console
- ✅ No PHP errors in server logs
- ✅ Database values match form inputs
- ✅ Display updates immediately (AJAX)
- ✅ Display persists after page refresh
- ✅ Error messages are clear and helpful
- ✅ Success messages appear for valid updates

---

## Reporting Issues

If any test fails, note:
1. Which test failed
2. Expected vs actual behavior
3. Browser console errors (if any)
4. Network tab response (if AJAX)
5. Database values before and after
6. PHP error logs (if any)

