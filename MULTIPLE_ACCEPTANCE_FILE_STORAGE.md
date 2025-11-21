# Multiple Acceptance Method - File Attachment Storage

## Overview

When a user adds **multiple acceptance methods to an entry** and uploads attachments to each method, the files are stored using a **hierarchical structure** across different locations.

---

## File Storage Hierarchy

### **Scenario: Entry with Multiple Acceptance Methods**

```
Main Entry (₹15,000)
│
├─── Acceptance Method #1 (Cash - ₹8,000)
│    └─ Attached File: cash_receipt.pdf
│
├─── Acceptance Method #2 (Cheque - ₹5,000)
│    └─ Attached File: cheque_scan.jpg
│
└─── Acceptance Method #3 (Bank Transfer - ₹2,000)
     └─ Attached File: bank_slip.png
```

When user uploads files to these methods, each file is saved **independently**.

---

## File Storage Locations

### **1. PHYSICAL FILE SYSTEM**

```
/uploads/entry_method_media/
├── entry_method_media_1734589012_a4f8c2d9.pdf      ← Cash receipt (Method #1)
├── entry_method_media_1734589015_b3e7d1f4.jpg      ← Cheque scan (Method #2)
└── entry_method_media_1734589018_c2d6e0a5.png      ← Bank slip (Method #3)
```

**File Naming Pattern:**
```
entry_method_media_[UNIX_TIMESTAMP]_[8_CHAR_RANDOM_HEX].[EXTENSION]
```

**Example breakdown:**
- `entry_method_media_1734589012_a4f8c2d9.pdf`
  - `entry_method_media` = Type indicator
  - `1734589012` = Unix timestamp (when uploaded)
  - `a4f8c2d9` = Random hex (prevents collisions)
  - `.pdf` = File extension

---

## Database Storage - 2 Tables

### **TABLE 1: tbl_payment_acceptance_methods_line_items**

This table stores **method data + file metadata** for line item acceptance methods.

**Relevant Columns:**

| Column Name | Data Type | Purpose | Example |
|---|---|---|---|
| `line_item_acceptance_method_id` | BIGINT | Primary Key | 1, 2, 3 |
| `line_item_entry_id_fk` | BIGINT | Links to line item | 5 |
| `payment_entry_master_id_fk` | BIGINT | Links to main payment | 10 |
| `method_type_category` | VARCHAR(50) | Payment method | "cash", "cheque", "bank_transfer" |
| `method_amount_received` | DECIMAL(15,2) | Amount for this method | 8000.00 |
| `method_reference_identifier` | VARCHAR(100) | Cheque no., TX ID, etc | "CHQ123456" |
| **`method_supporting_media_path`** | VARCHAR(500) | **FILE LOCATION** | "/uploads/entry_method_media/entry_method_media_1734589012_a4f8c2d9.pdf" |
| **`method_supporting_media_filename`** | VARCHAR(255) | **ORIGINAL FILENAME** | "cash_receipt.pdf" |
| **`method_supporting_media_size`** | BIGINT | **FILE SIZE IN BYTES** | 204800 |
| **`method_supporting_media_type`** | VARCHAR(100) | **MIME TYPE** | "application/pdf" |
| `method_display_sequence` | INT | Display order | 1, 2, 3 |
| `method_recorded_at` | TIMESTAMP | When recorded | 2025-11-20 10:30:45 |

---

### **TABLE 2: tbl_payment_entry_file_attachments_registry**

This table stores **audit trail + integrity hash** for all files.

**Relevant Columns:**

| Column Name | Data Type | Purpose | Example |
|---|---|---|---|
| `attachment_id` | BIGINT | Primary Key | 1, 2, 3 |
| `payment_entry_master_id_fk` | BIGINT | Links to main payment | 10 |
| `attachment_type_category` | ENUM | Type of attachment | **"line_item_method_media"** |
| `attachment_reference_id` | VARCHAR(100) | Reference identifier | "entryMethodMedia_0_0", "entryMethodMedia_0_1" |
| `attachment_file_original_name` | VARCHAR(255) | Original filename | "cash_receipt.pdf" |
| `attachment_file_stored_path` | VARCHAR(500) | File location | "/uploads/entry_method_media/..." |
| `attachment_file_size_bytes` | BIGINT | File size | 204800 |
| `attachment_file_mime_type` | VARCHAR(100) | MIME type | "application/pdf" |
| `attachment_file_extension` | VARCHAR(10) | Extension | "pdf" |
| `attachment_integrity_hash` | VARCHAR(64) | SHA256 hash | "a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2..." |
| `attachment_verification_status` | ENUM | Status | "pending", "verified", "quarantined" |
| `attachment_upload_timestamp` | TIMESTAMP | Upload time | 2025-11-20 10:30:45 |
| `uploaded_by_user_id` | INT | Who uploaded | 3 |

---

## Real-World Example

### **User Scenario:**

Entry ID: 5, Line Item ID: 12, Amount: ₹15,000

**User adds 3 acceptance methods with files:**

```
Method #1: Cash - ₹8,000
  - Upload: cash_receipt.pdf (50 KB)
  
Method #2: Cheque - ₹5,000
  - Upload: cheque_scan.jpg (150 KB)
  
Method #3: Bank Transfer - ₹2,000
  - Upload: bank_slip.png (80 KB)
```

---

### **Result - Files Saved:**

#### **FILESYSTEM:**

```
/uploads/entry_method_media/
├── entry_method_media_1734589012_a4f8c2d9.pdf     (50 KB - Cash receipt)
├── entry_method_media_1734589015_b3e7d1f4.jpg     (150 KB - Cheque scan)
└── entry_method_media_1734589018_c2d6e0a5.png     (80 KB - Bank slip)
```

---

#### **DATABASE - tbl_payment_acceptance_methods_line_items:**

**Row 1 (Method #1 - Cash):**
```sql
line_item_acceptance_method_id  | 1
line_item_entry_id_fk           | 12
payment_entry_master_id_fk      | 5
method_type_category            | cash
method_amount_received          | 8000.00
method_reference_identifier    | NULL
method_display_sequence         | 1
method_supporting_media_path    | /uploads/entry_method_media/entry_method_media_1734589012_a4f8c2d9.pdf
method_supporting_media_filename| cash_receipt.pdf
method_supporting_media_size    | 51200
method_supporting_media_type    | application/pdf
method_recorded_at              | 2025-11-20 10:30:45
```

**Row 2 (Method #2 - Cheque):**
```sql
line_item_acceptance_method_id  | 2
line_item_entry_id_fk           | 12
payment_entry_master_id_fk      | 5
method_type_category            | cheque
method_amount_received          | 5000.00
method_reference_identifier    | CHQ123456
method_display_sequence         | 2
method_supporting_media_path    | /uploads/entry_method_media/entry_method_media_1734589015_b3e7d1f4.jpg
method_supporting_media_filename| cheque_scan.jpg
method_supporting_media_size    | 153600
method_supporting_media_type    | image/jpeg
method_recorded_at              | 2025-11-20 10:31:02
```

**Row 3 (Method #3 - Bank Transfer):**
```sql
line_item_acceptance_method_id  | 3
line_item_entry_id_fk           | 12
payment_entry_master_id_fk      | 5
method_type_category            | bank_transfer
method_amount_received          | 2000.00
method_reference_identifier    | TXN987654321
method_display_sequence         | 3
method_supporting_media_path    | /uploads/entry_method_media/entry_method_media_1734589018_c2d6e0a5.png
method_supporting_media_filename| bank_slip.png
method_supporting_media_size    | 81920
method_supporting_media_type    | image/png
method_recorded_at              | 2025-11-20 10:31:30
```

---

#### **DATABASE - tbl_payment_entry_file_attachments_registry:**

**Row 1 (Cash receipt audit):**
```sql
attachment_id                   | 1
payment_entry_master_id_fk      | 5
attachment_type_category        | line_item_method_media
attachment_reference_id         | entryMethodMedia_0_0
attachment_file_original_name   | cash_receipt.pdf
attachment_file_stored_path     | /uploads/entry_method_media/entry_method_media_1734589012_a4f8c2d9.pdf
attachment_file_size_bytes      | 51200
attachment_file_mime_type       | application/pdf
attachment_file_extension       | pdf
attachment_integrity_hash       | a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2...
attachment_verification_status  | pending
attachment_upload_timestamp     | 2025-11-20 10:30:45
uploaded_by_user_id             | 3
```

**Row 2 (Cheque scan audit):**
```sql
attachment_id                   | 2
payment_entry_master_id_fk      | 5
attachment_type_category        | line_item_method_media
attachment_reference_id         | entryMethodMedia_0_1
attachment_file_original_name   | cheque_scan.jpg
attachment_file_stored_path     | /uploads/entry_method_media/entry_method_media_1734589015_b3e7d1f4.jpg
attachment_file_size_bytes      | 153600
attachment_file_mime_type       | image/jpeg
attachment_file_extension       | jpg
attachment_integrity_hash       | b4c7d9e2f1a3c8b5d6e7f8g9h0i1j2k3...
attachment_verification_status  | pending
attachment_upload_timestamp     | 2025-11-20 10:31:02
uploaded_by_user_id             | 3
```

**Row 3 (Bank slip audit):**
```sql
attachment_id                   | 3
payment_entry_master_id_fk      | 5
attachment_type_category        | line_item_method_media
attachment_reference_id         | entryMethodMedia_0_2
attachment_file_original_name   | bank_slip.png
attachment_file_stored_path     | /uploads/entry_method_media/entry_method_media_1734589018_c2d6e0a5.png
attachment_file_size_bytes      | 81920
attachment_file_mime_type       | image/png
attachment_file_extension       | png
attachment_integrity_hash       | c5d8e0f3g2b4a9d6c7e8f9g0h1i2j3k4...
attachment_verification_status  | pending
attachment_upload_timestamp     | 2025-11-20 10:31:30
uploaded_by_user_id             | 3
```

---

## Backend Processing Code

### **File Upload Handler:**

```php
// In payment_entry_handler.php (Line 376)

$line_method_file_key = 'entryMethodMedia_' . $entry_index . '_' . $method_index;

if (isset($_FILES[$line_method_file_key]) && $_FILES[$line_method_file_key]['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES[$line_method_file_key];
    
    // Validate and upload file
    $file_info = handleFileUpload($file, 'entry_method_media');
    
    if ($file_info) {
        $line_method_media_path = $file_info['path'];           // /uploads/entry_method_media/...pdf
        $line_method_media_filename = $file_info['filename'];   // cash_receipt.pdf
        $line_method_media_size = $file_info['size'];          // 51200
        $line_method_media_type = $file_info['mime'];          // application/pdf
    }
}
```

### **Database Insertion:**

```php
// Step 1: Insert into acceptance methods table
$stmt = $pdo->prepare("
    INSERT INTO tbl_payment_acceptance_methods_line_items (
        line_item_entry_id_fk,
        payment_entry_master_id_fk,
        method_type_category,
        method_amount_received,
        method_reference_identifier,
        method_display_sequence,
        method_supporting_media_path,        ← FILE PATH
        method_supporting_media_filename,    ← ORIGINAL FILENAME
        method_supporting_media_size,        ← FILE SIZE
        method_supporting_media_type         ← MIME TYPE
    ) VALUES (...)
");

// Step 2: Register in file attachments registry
if ($line_method_media_path) {
    registerFileAttachment(
        $pdo,
        $payment_entry_id,
        'line_item_method_media',                    // Type category
        'entryMethodMedia_' . $entry_index . '_' . $method_index,  // Reference
        $line_method_media_filename,                 // Original name
        $line_method_media_path,                     // File path
        $line_method_media_size,                     // File size
        $line_method_media_type,                     // MIME type
        $current_user_id                            // Who uploaded
    );
}
```

### **File Registry Function:**

```php
function registerFileAttachment(
    $pdo,
    $payment_entry_id,
    $attachment_type,        // 'line_item_method_media'
    $reference_id,          // 'entryMethodMedia_0_0', 'entryMethodMedia_0_1', etc
    $original_name,         // cash_receipt.pdf
    $file_path,             // /uploads/entry_method_media/entry_method_media_...pdf
    $file_size,             // 51200
    $file_mime,             // application/pdf
    $user_id               // 3
) {
    // Calculate SHA256 hash
    $file_hash = hash_file('sha256', $file_path);
    
    // Get file extension
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    
    // Insert into registry
    $stmt = $pdo->prepare("
        INSERT INTO tbl_payment_entry_file_attachments_registry (
            payment_entry_master_id_fk,
            attachment_type_category,
            attachment_reference_id,
            attachment_file_original_name,
            attachment_file_stored_path,
            attachment_file_size_bytes,
            attachment_file_mime_type,
            attachment_file_extension,
            attachment_integrity_hash,
            uploaded_by_user_id
        ) VALUES (
            :payment_id,
            :type,
            :ref_id,
            :name,
            :path,
            :size,
            :mime,
            :ext,
            :hash,
            :user_id
        )
    ");
    
    $stmt->execute([
        ':payment_id' => $payment_entry_id,
        ':type' => $attachment_type,
        ':ref_id' => $reference_id,
        ':name' => $original_name,
        ':path' => $file_path,
        ':size' => $file_size,
        ':mime' => $file_mime,
        ':ext' => $extension,
        ':hash' => $file_hash,
        ':user_id' => $user_id
    ]);
}
```

---

## Query to Retrieve Entry with All Acceptance Methods + Files

```sql
-- Get line item with all acceptance methods and their files
SELECT 
    li.line_item_entry_id,
    li.line_item_amount AS entry_total,
    am.line_item_acceptance_method_id,
    am.method_type_category,
    am.method_amount_received,
    am.method_reference_identifier,
    am.method_supporting_media_filename,
    am.method_supporting_media_path,
    am.method_supporting_media_size,
    f.attachment_integrity_hash,
    f.attachment_verification_status
FROM tbl_payment_entry_line_items_detail li
LEFT JOIN tbl_payment_acceptance_methods_line_items am
    ON li.line_item_entry_id = am.line_item_entry_id_fk
LEFT JOIN tbl_payment_entry_file_attachments_registry f
    ON li.payment_entry_master_id_fk = f.payment_entry_master_id_fk
    AND f.attachment_type_category = 'line_item_method_media'
WHERE li.line_item_entry_id = 12
ORDER BY am.method_display_sequence;

RESULT:
┌────────────────────────────────────────────────────────────┐
│ line_item_entry_id: 12                                     │
│ entry_total: 15000.00                                      │
├─ Method #1: cash                                            │
│   ├─ method_amount_received: 8000.00                       │
│   ├─ method_supporting_media_filename: cash_receipt.pdf    │
│   ├─ method_supporting_media_path: /uploads/entry_method...│
│   ├─ attachment_integrity_hash: a3f8d9c2e1b4f...          │
│   └─ attachment_verification_status: pending               │
├─ Method #2: cheque                                          │
│   ├─ method_amount_received: 5000.00                       │
│   ├─ method_reference_identifier: CHQ123456                │
│   ├─ method_supporting_media_filename: cheque_scan.jpg     │
│   ├─ method_supporting_media_path: /uploads/entry_method...│
│   ├─ attachment_integrity_hash: b4c7d9e2f1a3...           │
│   └─ attachment_verification_status: pending               │
└─ Method #3: bank_transfer                                   │
    ├─ method_amount_received: 2000.00                       │
    ├─ method_reference_identifier: TXN987654321             │
    ├─ method_supporting_media_filename: bank_slip.png       │
    ├─ method_supporting_media_path: /uploads/entry_method...│
    ├─ attachment_integrity_hash: c5d8e0f3g2b4...            │
    └─ attachment_verification_status: pending               │
```

---

## Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    USER INTERFACE                           │
│              Acceptance Method Form Fields                  │
│                                                             │
│  Method #1: Cash - ₹8000 [PDF File Upload] ✓              │
│  Method #2: Cheque - ₹5000 [JPG File Upload] ✓            │
│  Method #3: Bank Transfer - ₹2000 [PNG File Upload] ✓     │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ↓ Form Submission (POST)
    ┌────────────────────────────────────────────┐
    │  payment_entry_handler.php                 │
    │                                            │
    │  FOR EACH Acceptance Method:               │
    │  ─────────────────────────────             │
    │  1. Extract $_FILES['entryMethodMedia_0_0']│
    │  2. Validate file (size, type)             │
    │  3. Generate unique filename               │
    │     entry_method_media_[TS]_[HEX].[EXT]   │
    │  4. Move to /uploads/entry_method_media/   │
    │  5. Calculate SHA256 hash                  │
    │  6. Insert to methods table (4 columns)    │
    │  7. Register in file registry (audit)      │
    │                                            │
    └────────┬───────────────────────────────────┘
             │
      ┌──────┴──────┬──────────┬────────────┐
      ↓             ↓          ↓            ↓
 ┌─────────┐   ┌─────────┐  ┌──────┐   ┌────────┐
 │ FILE 1  │   │ FILE 2  │  │ FILE3│   │REGISTRY│
 │ PDF 50KB│   │ JPG150KB│  │PNG80K│   │ AUDIT  │
 │ Stored  │   │ Stored  │  │Stored│   │  HASH  │
 └─────────┘   └─────────┘  └──────┘   └────────┘
```

---

## Key Differences: Main Entry Files vs Acceptance Method Files

| Aspect | Main Entry File | Acceptance Method File |
|--------|-----------------|----------------------|
| **Table** | `tbl_payment_entry_line_items_detail` | `tbl_payment_acceptance_methods_line_items` |
| **Upload Directory** | `/uploads/entry_media/` | `/uploads/entry_method_media/` |
| **File Prefix** | `entry_media_` | `entry_method_media_` |
| **Column Names** | `line_item_media_*` | `method_supporting_media_*` |
| **Registry Category** | `line_item_media` | `line_item_method_media` |
| **Purpose** | Invoice/receipt for entry | Supporting docs for payment method |
| **Per Entry** | 1 file max | Multiple files (one per method) |
| **Example** | Invoice for labour work | Cheque copy, bank receipt, etc |

---

## Summary - FINAL ANSWER

### **When user adds multiple acceptance methods to an entry and uploads files:**

```
STORAGE LOCATION:
├─ Filesystem: /uploads/entry_method_media/entry_method_media_[TS]_[HEX].[EXT]
│
└─ Database (2 Tables):
   ├─ tbl_payment_acceptance_methods_line_items
   │  └─ Columns: method_supporting_media_path
   │             method_supporting_media_filename
   │             method_supporting_media_size
   │             method_supporting_media_type
   │
   └─ tbl_payment_entry_file_attachments_registry
      └─ Type: 'line_item_method_media'
         Columns: attachment_file_stored_path
                 attachment_file_original_name
                 attachment_integrity_hash (SHA256)
                 uploaded_by_user_id
                 attachment_verification_status
```

**Each acceptance method's file is stored independently** with unique filename, full audit trail, and integrity verification via SHA256 hashing.
