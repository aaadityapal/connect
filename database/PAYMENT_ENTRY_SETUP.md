# Payment Entry System - Implementation Guide

## Overview
Complete payment entry modal system with database persistence, file uploads, and multi-level acceptance methods.

---

## Files Created

### 1. Database Schema
**File:** `/database/payment_entry_tables.sql`

**Tables Created (10 total):**

| Table | Purpose |
|-------|---------|
| `tbl_payment_entry_master_records` | Main payment entries with proof documents |
| `tbl_payment_acceptance_methods_primary` | Payment method breakdowns for main payment |
| `tbl_payment_entry_line_items_detail` | Additional line items/entries |
| `tbl_payment_acceptance_methods_line_items` | Payment methods for each line item |
| `tbl_payment_entry_file_attachments_registry` | Central file tracking registry |
| `tbl_payment_entry_audit_activity_log` | Audit trail for all activities |
| `tbl_payment_entry_summary_totals` | Aggregated totals for reporting |
| `tbl_payment_entry_status_transition_history` | Status change history |
| `tbl_payment_entry_rejection_reasons_detail` | Rejection details |
| `tbl_payment_entry_approval_records_final` | Final approval records |

**Views Created (2 total):**
- `vw_payment_entry_complete_details` - Complete payment entry details
- `vw_payment_entry_line_item_breakdown` - Line item breakdown with methods

---

### 2. Backend Handler
**File:** `/handlers/payment_entry_handler.php`

**Functionality:**
- Processes form submission from the modal
- Validates all input data
- Handles file uploads (proof images, method media, entry media)
- Manages database transactions
- Calculates totals and summaries
- Creates audit logs
- Returns JSON response

**Key Features:**
- Transaction support (rollback on error)
- File validation (type, size)
- SHA256 integrity hashing
- Multi-level file upload support
- Automatic directory creation
- Error handling and logging

**File Upload Support:**
- **Allowed Types:** JPG, PNG, PDF, MP4, MOV, AVI
- **Proof Image:** 5MB max
- **Other Media:** 50MB max

**Upload Structure:**
```
uploads/
├── payment_proofs/
├── payment_entries/
│   ├── acceptance_methods/
│   ├── entry_media/
│   └── entry_method_media/
```

---

### 3. Modal Integration
**File Modified:** `/modals/add_payment_entry_modal.php`

**Change:**
- Updated form submission endpoint from `add_payment_entry.php` to `../handlers/payment_entry_handler.php`
- Form now uses correct handler for database persistence

---

## Setup Instructions

### Step 1: Create Database Tables

**Option A: Using MySQL Command Line**
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/connect/database
mysql -u root -p crm < payment_entry_tables.sql
```

**Option B: Using phpMyAdmin**
1. Open phpMyAdmin
2. Select database: `crm`
3. Click "Import"
4. Choose `payment_entry_tables.sql`
5. Click "Go"

**Option C: Using Shell Script (macOS/Linux)**
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/connect/database
chmod +x setup_payment_tables.sh
./setup_payment_tables.sh
```

### Step 2: Verify Installation

Check that all tables were created:
```sql
USE crm;
SHOW TABLES LIKE 'tbl_payment%';
SHOW TABLES LIKE 'vw_payment%';
```

### Step 3: Create Upload Directories

```bash
mkdir -p /Applications/XAMPP/xamppfiles/htdocs/connect/uploads/payment_proofs
mkdir -p /Applications/XAMPP/xamppfiles/htdocs/connect/uploads/payment_entries/acceptance_methods
mkdir -p /Applications/XAMPP/xamppfiles/htdocs/connect/uploads/payment_entries/entry_media
mkdir -p /Applications/XAMPP/xamppfiles/htdocs/connect/uploads/payment_entries/entry_method_media

# Set write permissions
chmod -R 755 /Applications/XAMPP/xamppfiles/htdocs/connect/uploads/
```

### Step 4: Test the System

1. Open the payment entry modal in your application
2. Fill in all required fields:
   - Project Type
   - Project Name
   - Payment Amount
   - Payment Date
   - Authorized User
   - Payment Mode
   - Proof Image

3. Click "Save Payment Entry"

4. Check the response in browser console (F12 → Console)

---

## Data Structure

### Main Payment Entry
```json
{
  "paymentType": "single",
  "projectType": "architecture",
  "projectName": "Office Building",
  "amount": 50000.00,
  "paymentDate": "2024-11-17",
  "authorizedUserId": 5,
  "paymentMode": "multiple_acceptance",
  "proofImage": File
}
```

### Multiple Acceptance Methods
```json
{
  "multipleAcceptance": [
    {
      "method": "cash",
      "amount": 25000.00,
      "reference": null
    },
    {
      "method": "cheque",
      "amount": 25000.00,
      "reference": "CHQ123456"
    }
  ]
}
```

### Additional Entries
```json
{
  "additionalEntries": [
    {
      "type": "labour",
      "recipientId": 10,
      "recipientName": "Rajesh Kumar",
      "description": "Excavation work",
      "amount": 15000.00,
      "paymentMode": "cash",
      "lineNumber": 1,
      "acceptanceMethods": []
    }
  ]
}
```

---

## API Response Format

### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Payment entry saved successfully",
  "payment_entry_id": 1,
  "grand_total": 65000.00,
  "files_attached": 3
}
```

### Error Response (400 Bad Request)
```json
{
  "success": false,
  "message": "Error description here"
}
```

---

## Database Connection

The handler uses the database connection from:
```
/config/db_connect.php
```

**Connection Details:**
- **Type:** PDO (MySQL)
- **Host:** localhost
- **Database:** crm
- **Username:** root
- **Password:** (empty)
- **Charset:** utf8mb4
- **Timezone:** +05:30 (IST)

---

## File Storage

### Proof Image Path
```
uploads/payment_proofs/proof_1732032345_a1b2c3d4.jpg
```

### Acceptance Method Media Path
```
uploads/payment_entries/acceptance_methods/acceptance_methods_1732032345_a1b2c3d4.pdf
```

### Entry Media Path
```
uploads/payment_entries/entry_media/entry_media_1732032345_a1b2c3d4.jpg
```

### Entry Method Media Path
```
uploads/payment_entries/entry_method_media/entry_method_media_1732032345_a1b2c3d4.mp4
```

---

## Features

### 1. Transaction Support
- All or nothing approach
- Automatic rollback on error
- Data consistency guaranteed

### 2. File Management
- Multiple upload types
- Size validation
- MIME type validation
- SHA256 integrity checking
- Organized directory structure
- Automatic filename generation (prevents collisions)

### 3. Audit Trail
- Creation timestamp
- User tracking
- IP address logging
- User agent tracking
- Modification history

### 4. Status Management
- Draft, Submitted, Approved, Rejected, Pending states
- Status change history
- Change reason tracking
- Change timestamp recording

### 5. Rejection Handling
- Rejection reason codes
- Detailed descriptions
- Resubmission requests
- Attachment issue notes

### 6. Approval Tracking
- Approval user tracking
- Clearance codes
- Authorization amounts
- Reference document numbers

### 7. Reporting
- Summary totals view
- Line item breakdown view
- Complete details view
- Aggregated calculations

---

## Query Examples

### Get All Payment Entries
```sql
SELECT * FROM vw_payment_entry_complete_details 
WHERE entry_status_current = 'submitted'
ORDER BY created_timestamp_utc DESC;
```

### Get Payment Entry with Line Items
```sql
SELECT m.*, l.*
FROM tbl_payment_entry_master_records m
LEFT JOIN tbl_payment_entry_line_items_detail l 
  ON m.payment_entry_id = l.payment_entry_master_id_fk
WHERE m.payment_entry_id = 1;
```

### Get Acceptance Methods for Payment
```sql
SELECT * FROM tbl_payment_acceptance_methods_primary
WHERE payment_entry_id_fk = 1
ORDER BY method_sequence_order;
```

### Get All Attached Files
```sql
SELECT * FROM tbl_payment_entry_file_attachments_registry
WHERE payment_entry_master_id_fk = 1
ORDER BY attachment_upload_timestamp DESC;
```

### Get Audit Trail
```sql
SELECT * FROM tbl_payment_entry_audit_activity_log
WHERE payment_entry_id_fk = 1
ORDER BY audit_action_timestamp_utc DESC;
```

---

## Error Handling

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| Foreign key constraint error | User not found | Ensure user ID exists in users table |
| File upload failed | Permission denied | Check upload directory permissions (755) |
| File too large | Exceeds size limit | Reduce file size (5MB proof, 50MB other) |
| Invalid MIME type | Wrong file type | Use allowed formats: JPG, PNG, PDF, MP4, MOV, AVI |
| Database connection error | Database unavailable | Check database service and credentials |

---

## Security Features

1. **SQL Injection Prevention**
   - Prepared statements for all queries
   - Parameter binding

2. **File Upload Security**
   - MIME type validation
   - Size limits
   - Extension validation
   - Uploaded file verification

3. **Access Control**
   - Session validation
   - User authentication check
   - Activity logging

4. **Data Integrity**
   - SHA256 file hashing
   - Transaction support
   - Cascading deletes for referential integrity

5. **Error Handling**
   - Generic error messages to users
   - Detailed error logging for admins
   - No sensitive info exposure

---

## Maintenance

### Backup Database
```bash
mysqldump -u root -p crm > crm_backup_$(date +%Y%m%d_%H%M%S).sql
```

### Clear Old Uploads
```bash
# Remove files older than 90 days
find /Applications/XAMPP/xamppfiles/htdocs/connect/uploads/payment_* -mtime +90 -delete
```

### Database Statistics
```sql
SELECT 
    COUNT(*) as total_entries,
    COUNT(DISTINCT created_by_user_id) as unique_users,
    AVG(payment_amount_base) as avg_amount,
    SUM(payment_amount_base) as total_amount
FROM tbl_payment_entry_master_records;
```

---

## Support & Troubleshooting

### Check Handler Logs
```bash
tail -f /Applications/XAMPP/xamppfiles/htdocs/connect/logs/database_errors.log
```

### Verify Table Structure
```sql
DESCRIBE tbl_payment_entry_master_records;
DESCRIBE tbl_payment_entry_line_items_detail;
```

### Test File Upload
```php
echo ini_get('upload_max_filesize');
echo ini_get('post_max_size');
```

---

## Next Steps

1. ✅ Database schema created
2. ✅ Handler implemented
3. ✅ Modal integrated
4. 📋 Create retrieval API endpoints
5. 📋 Create editing/updating functionality
6. 📋 Create deletion/archiving functionality
7. 📋 Create reporting dashboard
8. 📋 Create approval workflow interface

---

**Last Updated:** November 17, 2024
**Version:** 1.0
**Database:** MySQL 5.7+
**PHP:** 7.4+
