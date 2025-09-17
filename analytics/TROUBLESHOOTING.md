# Production Issue Troubleshooting Guide

## 🚨 URGENT FIX for Current Error

**Error:** Missing tables `salary_payments`, `overtime` and column `u.manager_id`

### IMMEDIATE SOLUTION:
1. **Run the automatic setup:** Go to `your-domain.com/hr/analytics/setup_missing_tables.php`
2. **Or manually run SQL:** Execute the `create_missing_tables.sql` file in your database
3. **Then try:** The main dashboard should work after table creation

---

## Quick Fix Steps

### Step 1: Run Diagnostics
1. Open your browser and go to: `your-domain.com/hr/analytics/debug_production_issues.php`
2. This will show you exactly what's wrong

### Step 2: Try Safe Mode
If the main dashboard is broken, use the safe mode version:
1. Go to: `your-domain.com/hr/analytics/salary_analytics_safe_mode.php`
2. This version has simplified calculations and should work even if some tables are missing

### Step 3: Common Issues and Solutions

#### Issue: Database Connection Error
**Symptoms:** "Database connection not established" or similar error
**Solution:**
1. Check if MySQL service is running
2. Verify database credentials in `config/db_connect.php`
3. Ensure database exists and is accessible

#### Issue: Missing Tables Error
**Symptoms:** "Table doesn't exist" errors
**Solution:**
1. Run the table creation scripts in this order:
   - `create_incremented_salary_table.sql`
   - `create_short_leave_preferences_table.sql`
2. Check if all required tables exist using the diagnostic script

#### Issue: Complex Query Timeout
**Symptoms:** Page loads forever or times out
**Solution:**
1. Use the safe mode version temporarily
2. Check MySQL slow query log
3. Consider adding database indexes

#### Issue: Session/Permission Error  
**Symptoms:** Redirected to login or access denied
**Solution:**
1. Ensure user is logged in with HR role
2. Check session configuration
3. Verify user permissions in database

#### Issue: Memory/Timeout Error
**Symptoms:** "Maximum execution time exceeded" or memory errors
**Solution:**
1. Increase PHP memory limit and execution time
2. Use safe mode version which processes less data
3. Add pagination to large datasets

### Step 4: Emergency Database Tables Creation

If tables are missing, run these SQL commands:

```sql
-- Create short_leave_preferences table
CREATE TABLE IF NOT EXISTS short_leave_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filter_month VARCHAR(7) NOT NULL,
    use_for_one_hour_late TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_month (user_id, filter_month)
);

-- Create salary_payments table if missing
CREATE TABLE IF NOT EXISTS salary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create overtime table if missing  
CREATE TABLE IF NOT EXISTS overtime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Step 5: PHP Configuration Check

Ensure these PHP settings are adequate:
```ini
memory_limit = 512M
max_execution_time = 300
display_errors = On (for debugging, Off for production)
log_errors = On
error_log = /path/to/logs/php_errors.log
```

### Step 6: File Permissions

Ensure these directories/files have proper permissions:
- `analytics/` directory: readable and writable
- `logs/` directory: writable  
- `config/db_connect.php`: readable
- All `.php` files: readable

## File Descriptions

1. **debug_production_issues.php** - Comprehensive diagnostic script
2. **salary_analytics_safe_mode.php** - Simplified version that works with minimal requirements
3. **salary_analytics_dashboard.php** - Full-featured version (may have issues in production)

## Support Information

If issues persist:
1. Check the diagnostic report from `debug_production_issues.php`
2. Review PHP error logs at `logs/salary_analytics_errors.log`
3. Share the diagnostic report for further assistance

## Performance Optimization

For large datasets:
1. Add database indexes on frequently queried columns
2. Consider implementing pagination
3. Use the safe mode version for better performance
4. Cache calculated results where possible