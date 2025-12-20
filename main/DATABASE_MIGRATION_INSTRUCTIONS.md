# Database Migration Instructions

## IMPORTANT: Run This SQL Script First!

Before testing the appointment fixes, you **MUST** run the database migration script to update the ENUM values.

### Step 1: Backup Your Database
```sql
-- Create a backup first!
mysqldump -u root -p mhavis > backup_before_migration.sql
```

### Step 2: Run the Migration Script

**Option A: Via phpMyAdmin**
1. Open phpMyAdmin
2. Select your database (`mhavis`)
3. Click on the "SQL" tab
4. Copy and paste the contents of `sql/update_appointment_status_to_lowercase.sql`
5. Click "Go" to execute

**Option B: Via Command Line**
```bash
mysql -u root -p mhavis < sql/update_appointment_status_to_lowercase.sql
```

**Option C: Via MySQL Client**
```sql
USE mhavis;
SOURCE sql/update_appointment_status_to_lowercase.sql;
```

### Step 3: Verify the Migration

Run this query to verify:
```sql
SHOW COLUMNS FROM appointments WHERE Field = 'status';
```

You should see:
```
Type: enum('scheduled','ongoing','settled','cancelled')
```

### Step 4: Check Existing Data

Verify that all existing appointments have valid statuses:
```sql
SELECT DISTINCT status FROM appointments ORDER BY status;
```

You should only see: `scheduled`, `ongoing`, `settled`, `cancelled`

### What the Migration Does

1. **Migrates existing data:**
   - `Scheduled`, `Confirmed`, `pending` → `scheduled`
   - `In Progress`, `Ongoing` → `ongoing`
   - `Completed`, `completed`, `Settled` → `settled`
   - `Cancelled`, `No Show`, `declined` → `cancelled`

2. **Updates the ENUM definition:**
   - Changes from: `ENUM('Scheduled','Confirmed','In Progress','Ongoing','Completed','Settled','Cancelled','No Show')`
   - Changes to: `ENUM('scheduled','ongoing','settled','cancelled')`

### Troubleshooting

**If you get an error about invalid ENUM values:**
- Some appointments may have statuses not covered by the migration
- Check with: `SELECT id, status FROM appointments WHERE status NOT IN ('scheduled','ongoing','settled','cancelled');`
- Manually update those records before running the ALTER TABLE command

**If migration fails:**
- Restore from backup
- Check for any custom status values in your database
- Update the migration script to include those values

### After Migration

1. Run the test script: `test_appointments.php`
2. Test appointment creation and updates manually
3. Verify all status dropdowns show the correct options

