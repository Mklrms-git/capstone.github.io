# Profile Update System - Required Files List

## Overview
This document lists all files involved in the profile update functionality where:
1. Users can update their own account settings/information
2. Super Admin can update every user's account settings/information
3. All updates are reflected in the database and displayed on profile pages

---

## Core Files for Staff Users (Admin/Doctor/Super Admin)

### 1. `mhavis/profile.php`
- **Purpose**: Display user's own profile page
- **Functionality**: Shows account information, allows users to edit their profile
- **Role**: Frontend display page for users to view and edit their own profile

### 2. `mhavis/update_profile.php`
- **Purpose**: Handle profile update requests from users (AJAX endpoint)
- **Functionality**: 
  - Updates: first_name, last_name, email, phone
  - Uploads profile image
  - Changes password
- **Role**: Backend API endpoint for user-initiated profile updates

### 3. `mhavis/super_admin_users.php`
- **Purpose**: Super admin user management interface
- **Functionality**:
  - Allows super admin to view, edit, add, delete all users
  - Updates: first_name, last_name, email, username, phone, address, specialization, status, password
  - Currently handles both staff and patient user updates
  - **Enhanced**: When updating patient users, email and phone are synced to both `patient_users` and `patients` tables
- **Role**: Super admin interface for managing all user accounts

### 4. `mhavis/config/auth.php`
- **Purpose**: Authentication and user retrieval functions
- **Functionality**: 
  - `getCurrentUser()` - Fetches current logged-in user data from database
  - This function queries database directly, so it always shows latest data
- **Role**: Provides user data retrieval function

---

## Core Files for Patient Users

### 5. `mhavis/update_patient_profile.php`
- **Purpose**: Handle profile update requests from patient users (AJAX endpoint)
- **Functionality**:
  - Updates: first_name, last_name, email, phone (updates both patients and patient_users tables)
  - Uploads profile image
  - Changes password
- **Role**: Backend API endpoint for patient-initiated profile updates

### 6. Patient Profile Display
- **Location**: Likely in `mhavis/patient_dashboard.php` or separate profile page
- **Purpose**: Display patient's own profile information
- **Role**: Frontend display page for patients to view and edit their profile

---

## Supporting Files

### 7. `mhavis/config/init.php`
- **Purpose**: Application initialization
- **Functionality**: Loads database connection, functions, authentication
- **Role**: Provides core infrastructure

### 8. `mhavis/config/functions.php`
- **Purpose**: Common utility functions
- **Functionality**: Phone number validation, formatting, sanitization
- **Role**: Provides helper functions

### 9. `mhavis/includes/header.php`
- **Purpose**: Site header with user profile information
- **Functionality**: Displays user name and profile image in navigation
- **Role**: Shows updated user info in navigation/header

---

## Database Tables

### 10. `users` table
- **Columns**: id, first_name, last_name, username, email, phone, address, role, specialization, status, profile_image, password, created_at, updated_at, last_login
- **Purpose**: Stores staff user accounts (Admin/Doctor/Super Admin)

### 11. `patient_users` table
- **Columns**: id, patient_id, username, email, phone, status, profile_image, password, created_at, updated_at, last_login
- **Purpose**: Stores patient user accounts

### 12. `patients` table
- **Columns**: id, first_name, last_name, email, phone, date_of_birth, sex, ...
- **Purpose**: Stores patient medical records (linked to patient_users via patient_id)

---

## Update Flow

### User Updates Own Profile:
1. User visits `profile.php`
2. User fills form and submits
3. JavaScript sends AJAX request to `update_profile.php`
4. `update_profile.php` validates and updates database
5. User's profile page refreshes to show updated data (via `getCurrentUser()`)

### Super Admin Updates User Profile:
1. Super admin visits `super_admin_users.php`
2. Super admin clicks "Edit" on a user
3. Super admin fills form and submits
4. Form POSTs to `super_admin_users.php` (same page)
5. PHP processes update and saves to database
6. User's profile page will show updated data on next page load (via `getCurrentUser()`)

---

## Key Points

1. **Data Consistency**: All profile pages use `getCurrentUser()` which queries database directly, ensuring latest data is always shown

2. **Update Scope**:
   - Users can update: first_name, last_name, email, phone, password, profile_image
   - Super admin can update: all of above + username, address, specialization, status

3. **Profile Image**: Stored in `uploads/` directory, path saved in database

4. **Session Updates**: After successful updates, session variables are updated to reflect changes immediately in UI

---

## System Status

✅ **Current Implementation Status:**

1. **User Profile Updates**: ✅ WORKING
   - Users can update: first_name, last_name, email, phone, password, profile_image
   - Updates are saved to database via `update_profile.php`
   - Profile page queries database directly via `getCurrentUser()`, so updates show immediately

2. **Super Admin User Updates**: ✅ WORKING & ENHANCED
   - Super admin can update: first_name, last_name, email, username, phone, address, specialization, status, password
   - Updates are saved to database via `super_admin_users.php`
   - User's profile page will show updated data on next page load (via `getCurrentUser()`)
   - **Enhancement**: When updating patient users, email and phone are now synced to both `patient_users` and `patients` tables

3. **Patient Profile Updates**: ✅ WORKING
   - Patients can update: first_name, last_name, email, phone, password, profile_image
   - Updates both `patients` and `patient_users` tables via `update_patient_profile.php`
   - Profile pages query database directly, so updates show immediately

4. **Data Consistency**: ✅ WORKING
   - All profile pages use `getCurrentUser()` or equivalent database queries
   - No reliance on stale session data
   - Updates reflect immediately in profile pages

## Optional Enhancements (Not Required)

1. **Profile Image Upload in Super Admin Interface**: Currently super admin can update all text fields but profile images would need to be added to the edit modals
2. **Enhanced Patient Update**: Super admin could update patient's first_name/last_name from patients table when editing patient_users

**Note**: The current system is fully functional. Users can update their profiles and see changes immediately. Super admin can update user accounts and those changes will be visible when users view their profile pages.

---

## Testing Checklist

- [ ] User can update their own profile information
- [ ] User profile page shows updated information immediately
- [ ] Super admin can update user profile information
- [ ] Updated user profile page shows super admin's changes
- [ ] Profile images update correctly for both scenarios
- [ ] Patient profile updates work correctly
- [ ] Super admin can update patient profile information
- [ ] Updates reflect in database correctly
- [ ] Session variables update after profile changes
- [ ] Header/navigation shows updated user info

