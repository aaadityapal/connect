# Where File Attachments are Stored - Complete Answer

## Quick Answer

When a user attaches a file to an **Additional Entry** (line item), the file data is saved in **2 places**:

1. **File System** → `/uploads/entry_media/` directory
2. **Database** → Multiple tables

---

## File Storage Locations

### **Physical File Storage**
```
/Applications/XAMPP/xamppfiles/htdocs/connect/uploads/entry_media/entry_media_1734589012_a1b2c3d4.pdf
                                                     └─ Timestamp + Random hash + Extension
```

**Pattern:** `entry_media_[timestamp]_[random_hex].[extension]`

**Example:** `entry_media_1734589012_f8e4d9a2.pdf`

**Directory Structure:**
```
connect/
├── uploads/
│   ├── payment_proofs/           (Main payment proof images)
│   ├── acceptance_methods/       (Main payment method documents)
│   ├── entry_media/              ← Line item attachments stored HERE
│   │   ├── entry_media_1734589012_f8e4d9a2.pdf
│   │   ├── entry_media_1734589015_c3d7e9f1.jpg
│   │   └── entry_media_1734589018_b4a9c2e5.mp4
│   └── entry_method_media/       (Line item method documents)
```

---

## Database Tables & Columns

### **Table 1: `tbl_payment_entry_line_items_detail` (Main Entry Data)**

**When user attaches file to Entry, these columns are populated:**

| Column Name | Data Type | Content | Example |
|------------|-----------|---------|---------|
| `line_item_entry_id` | BIGINT | Unique entry ID | 1, 2, 3... |
| `payment_entry_master_id_fk` | BIGINT | Links to main payment | 5 |
| `recipient_type_category` | VARCHAR(100) | Type: Labour, Material, Supplier | "labour" |
| `recipient_id_reference` | INT | Recipient ID | 1, 2, 3... |
| `recipient_name_display` | VARCHAR(255) | Name of recipient | "John Worker" |
| `payment_description_notes` | TEXT | For/Description | "Foundation work" |
| `line_item_amount` | DECIMAL(15, 2) | Amount | 15000.00 |
| `line_item_payment_mode` | VARCHAR(50) | Payment method | "cash" |
| `line_item_sequence_number` | INT | Entry number | 1, 2, 3... |
| **`line_item_media_upload_path`** | VARCHAR(500) | **File URL path** | **`/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf`** |
| **`line_item_media_original_filename`** | VARCHAR(255) | **Original filename** | **`invoice.pdf`** |
| **`line_item_media_filesize_bytes`** | BIGINT | **File size** | **245000** |
| **`line_item_media_mime_type`** | VARCHAR(100) | **File type** | **`application/pdf`** |
| `line_item_status` | ENUM | Status | pending, verified, approved, rejected |
| `created_at_timestamp` | TIMESTAMP | Created when | 2025-11-20 10:30:00 |

**SQL Example:**
```sql
INSERT INTO tbl_payment_entry_line_items_detail (
    payment_entry_master_id_fk,
    recipient_type_category,
    recipient_id_reference,
    recipient_name_display,
    payment_description_notes,
    line_item_amount,
    line_item_payment_mode,
    line_item_sequence_number,
    line_item_media_upload_path,           ← FILE PATH
    line_item_media_original_filename,      ← ORIGINAL NAME
    line_item_media_filesize_bytes,         ← FILE SIZE
    line_item_media_mime_type               ← FILE TYPE
) VALUES (
    5,
    'labour',
    1,
    'John Worker',
    'Foundation work labour',
    15000.00,
    'cash',
    1,
    '/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf',  ← HERE
    'invoice.pdf',                                                  ← HERE
    245000,                                                         ← HERE
    'application/pdf'                                               ← HERE
);
```

---

### **Table 2: `tbl_payment_entry_file_attachments_registry` (Complete File Registry)**

**Central registry for ALL file attachments with integrity checking:**

| Column Name | Data Type | Content | Example |
|------------|-----------|---------|---------|
| `attachment_id` | BIGINT | Unique attachment ID | 1, 2, 3... |
| `payment_entry_master_id_fk` | BIGINT | Links to main payment | 5 |
| **`attachment_type_category`** | ENUM | **Type of file** | **`line_item_media`** |
| **`attachment_reference_id`** | VARCHAR(100) | **Reference ID** | **`entryMedia_0`** |
| **`attachment_file_original_name`** | VARCHAR(255) | **Original name** | **`invoice.pdf`** |
| **`attachment_file_stored_path`** | VARCHAR(500) | **Stored file path** | **`/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf`** |
| **`attachment_file_size_bytes`** | BIGINT | **File size** | **245000** |
| **`attachment_file_mime_type`** | VARCHAR(100) | **MIME type** | **`application/pdf`** |
| **`attachment_file_extension`** | VARCHAR(10) | **File extension** | **`pdf`** |
| `attachment_upload_timestamp` | TIMESTAMP | Upload time | 2025-11-20 10:30:00 |
| **`attachment_integrity_hash`** | VARCHAR(64) | **SHA256 hash** | **`a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2e1f4a7b9d2c5e8a1f4b7c9d2e5f8a1`** |
| `attachment_verification_status` | ENUM | Verification status | pending, verified, quarantined, deleted |
| `uploaded_by_user_id` | INT | User who uploaded | 3 |

**SQL Example:**
```sql
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
    5,
    'line_item_media',                                             ← TYPE
    'entryMedia_0',                                                ← REFERENCE
    'invoice.pdf',                                                 ← ORIGINAL NAME
    '/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf',   ← PATH
    245000,                                                        ← SIZE
    'application/pdf',                                             ← MIME TYPE
    'pdf',                                                         ← EXTENSION
    'a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2e1f4a7b9d2c5e8a1f4b7c9d2e5f8a1',  ← SHA256
    3                                                              ← USER ID
);
```

---

### **Table 3: `tbl_payment_entry_line_items_detail` (Line Item Methods)**

**If Entry uses "Multiple Acceptance" payment mode, methods also store files:**

| Column Name | Data Type | Content |
|------------|-----------|---------|
| `line_item_acceptance_method_id` | BIGINT | Method ID |
| `line_item_entry_id_fk` | BIGINT | Links to line item |
| `method_type_category` | VARCHAR(50) | Cash, Cheque, Bank Transfer, etc. |
| `method_amount_received` | DECIMAL(15, 2) | Amount for this method |
| **`method_supporting_media_path`** | VARCHAR(500) | **File path** |
| **`method_supporting_media_filename`** | VARCHAR(255) | **Original filename** |
| **`method_supporting_media_size`** | BIGINT | **File size** |
| **`method_supporting_media_type`** | VARCHAR(100) | **MIME type** |

---

## Complete Data Flow

### **When User Uploads File to Entry:**

```
USER ACTION:
┌─────────────────────────────────┐
│ User clicks "Attach File"       │
│ Selects: invoice.pdf (200 KB)   │
└────────┬────────────────────────┘
         │
         ↓
FILE VALIDATION (Client-side):
┌─────────────────────────────────┐
│ ✓ File size < 50 MB             │
│ ✓ File type = .pdf              │
│ ✓ Show preview: invoice.pdf     │
└────────┬────────────────────────┘
         │
         ↓
FORM SUBMISSION:
┌──────────────────────────────────────┐
│ POST to payment_entry_handler.php    │
│ + All other form fields              │
│ + FILE: invoice.pdf (binary data)    │
└────────┬─────────────────────────────┘
         │
         ↓
BACKEND PROCESSING:
┌────────────────────────────────────────────────────┐
│ Step 1: Validate file                              │
│   - Check MIME type                                │
│   - Check file size (< 50 MB)                      │
│   - Validate is uploaded file                      │
│                                                    │
│ Step 2: Create upload directory                    │
│   - mkdir /uploads/entry_media/                    │
│                                                    │
│ Step 3: Generate unique filename                   │
│   - entry_media_1734589012_f8e4d9a2.pdf           │
│   - Timestamp + Random hex + Extension             │
│                                                    │
│ Step 4: Move file to storage                       │
│   - move_uploaded_file($tmp, $path)                │
│   - File now at: /uploads/entry_media/...         │
│                                                    │
│ Step 5: Calculate SHA256 hash                      │
│   - hash_file('sha256', $filepath)                 │
│   - For integrity verification                     │
│                                                    │
│ Step 6: Insert to line item table                  │
│   - line_item_media_upload_path                    │
│   - line_item_media_original_filename              │
│   - line_item_media_filesize_bytes                 │
│   - line_item_media_mime_type                      │
│                                                    │
│ Step 7: Register in file registry                  │
│   - attachment_type_category = 'line_item_media'   │
│   - attachment_reference_id = 'entryMedia_0'       │
│   - attachment_integrity_hash = SHA256             │
│                                                    │
└────────┬─────────────────────────────────────────┘
         │
         ↓
SUCCESS RESPONSE:
┌──────────────────────────────────┐
│ JSON:                            │
│ {                                │
│   "success": true,               │
│   "payment_entry_id": 5,         │
│   "files_attached": 1,           │
│   "grand_total": 50000           │
│ }                                │
└──────────────────────────────────┘
```

---

## Real Example with All Data

### **Scenario: Paying labour worker with file upload**

**User enters:**
- Type: Labour
- Recipient: John Worker (ID: 1)
- Description: Foundation work - 5 days
- Amount: ₹15,000
- Payment Mode: Cash
- File: invoice.pdf (200 KB)

**What gets saved in database:**

### **tbl_payment_entry_line_items_detail**
```
line_item_entry_id              : 1
payment_entry_master_id_fk      : 5
recipient_type_category        : labour
recipient_id_reference          : 1
recipient_name_display          : John Worker
payment_description_notes       : Foundation work - 5 days
line_item_amount                : 15000.00
line_item_payment_mode          : cash
line_item_sequence_number       : 1
line_item_media_upload_path     : /uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
line_item_media_original_filename: invoice.pdf
line_item_media_filesize_bytes  : 204800
line_item_media_mime_type       : application/pdf
line_item_status                : pending
created_at_timestamp            : 2025-11-20 10:30:45
```

### **tbl_payment_entry_file_attachments_registry**
```
attachment_id                   : 1
payment_entry_master_id_fk      : 5
attachment_type_category        : line_item_media
attachment_reference_id         : entryMedia_0
attachment_file_original_name   : invoice.pdf
attachment_file_stored_path     : /uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
attachment_file_size_bytes      : 204800
attachment_file_mime_type       : application/pdf
attachment_file_extension       : pdf
attachment_upload_timestamp     : 2025-11-20 10:30:45
attachment_integrity_hash       : a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2e1f4a7b9d2c5e8a1f4b7c9d2e5f8a1
attachment_verification_status  : pending
uploaded_by_user_id             : 3
```

### **Physical File**
```
Path: /Applications/XAMPP/xamppfiles/htdocs/connect/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
Size: 204,800 bytes (200 KB)
Type: PDF document
```

---

## Database Query to Retrieve Entry with File

```sql
-- Get entry with file information
SELECT 
    l.line_item_entry_id,
    l.recipient_type_category,
    l.recipient_name_display,
    l.payment_description_notes,
    l.line_item_amount,
    l.line_item_media_original_filename,
    l.line_item_media_upload_path,
    l.line_item_media_filesize_bytes,
    f.attachment_integrity_hash,
    f.attachment_verification_status
FROM tbl_payment_entry_line_items_detail l
LEFT JOIN tbl_payment_entry_file_attachments_registry f
    ON l.payment_entry_master_id_fk = f.payment_entry_master_id_fk
    AND f.attachment_type_category = 'line_item_media'
WHERE l.payment_entry_master_id_fk = 5;
```

**Result:**
```
line_item_entry_id         : 1
recipient_type_category    : labour
recipient_name_display     : John Worker
payment_description_notes  : Foundation work - 5 days
line_item_amount           : 15000.00
line_item_media_original_filename: invoice.pdf
line_item_media_upload_path: /uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
line_item_media_filesize_bytes: 204800
attachment_integrity_hash  : a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2e1f4a7b9d2c5e8a1f4b7c9d2e5f8a1
attachment_verification_status: pending
```

---

## Backend Code - Where Files Are Saved

### **From `payment_entry_handler.php`:**

```php
// Step 3: Insert Additional Entries (Line Items)
if (isset($_POST['additionalEntries'])) {
    $entries = json_decode($_POST['additionalEntries'], true);

    foreach ($entries as $entry_index => $entry) {
        $recipient_type = $entry['type'] ?? null;
        $recipient_id = intval($entry['recipientId'] ?? 0);
        $recipient_name = $entry['recipientName'] ?? null;
        $description = $entry['description'] ?? null;
        $line_amount = floatval($entry['amount'] ?? 0);
        $line_payment_mode = $entry['paymentMode'] ?? 'cash';
        
        // Handle entry media upload ← FILE UPLOAD HAPPENS HERE
        $entry_media_path = null;
        $entry_media_filename = null;
        
        $entry_file_key = 'entryMedia_' . $entry_index;
        if (isset($_FILES[$entry_file_key]) && $_FILES[$entry_file_key]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$entry_file_key];
            
            // Call handleFileUpload() helper function
            $file_info = handleFileUpload($file, 'entry_media');
            
            if ($file_info) {
                $entry_media_path = $file_info['path'];           // /uploads/entry_media/...
                $entry_media_filename = $file_info['filename'];   // invoice.pdf
                $entry_media_size = $file_info['size'];          // 204800
                $entry_media_type = $file_info['mime'];          // application/pdf
            }
        }
        
        // Insert into tbl_payment_entry_line_items_detail
        $stmt = $pdo->prepare("
            INSERT INTO tbl_payment_entry_line_items_detail (
                payment_entry_master_id_fk,
                recipient_type_category,
                recipient_id_reference,
                recipient_name_display,
                payment_description_notes,
                line_item_amount,
                line_item_payment_mode,
                line_item_sequence_number,
                line_item_media_upload_path,          ← HERE
                line_item_media_original_filename,     ← HERE
                line_item_media_filesize_bytes,        ← HERE
                line_item_media_mime_type              ← HERE
            ) VALUES (...)
        ");
        
        $stmt->execute([
            ':entry_media_path' => $entry_media_path,
            ':entry_media_filename' => $entry_media_filename,
            ':entry_media_size' => $entry_media_size,
            ':entry_media_type' => $entry_media_type
        ]);
        
        $line_item_id = $pdo->lastInsertId();
        
        // Register entry media file in registry
        if ($entry_media_path) {
            registerFileAttachment(
                $pdo,
                $payment_entry_id,
                'line_item_media',                    ← attachment_type_category
                'entryMedia_' . $entry_index,         ← attachment_reference_id
                $entry_media_filename,                ← attachment_file_original_name
                $entry_media_path,                    ← attachment_file_stored_path
                $entry_media_size,                    ← attachment_file_size_bytes
                $entry_media_type,                    ← attachment_file_mime_type
                $current_user_id
            );
        }
    }
}
```

### **handleFileUpload() Helper Function:**

```php
function handleFileUpload($file, $upload_type = 'default') {
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi'
    ];

    $max_size = 50 * 1024 * 1024; // 50MB for entry media
    
    // Create upload directory
    $upload_dir = UPLOAD_BASE_DIR . $upload_type . '/';  // /connect/uploads/entry_media/
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = $allowed_types[$file['type']];
    $unique_filename = $upload_type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
    // Example: entry_media_1734589012_f8e4d9a2.pdf
    
    $file_path = $upload_dir . $unique_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }

    return [
        'path' => UPLOAD_BASE_URL . $upload_type . '/' . $unique_filename,
        'filename' => $file['name'],
        'size' => $file['size'],
        'mime' => $file['type']
    ];
}
```

### **registerFileAttachment() Helper Function:**

```php
function registerFileAttachment($pdo, $payment_entry_id, $attachment_type, $reference_id, 
                               $filename, $filepath, $filesize, $mime_type, $user_id) {
    
    // Calculate SHA256 hash for integrity verification
    $file_system_path = str_replace(UPLOAD_BASE_URL, UPLOAD_BASE_DIR, $filepath);
    $file_hash = hash_file('sha256', $file_system_path);
    
    // Insert into file registry
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
        ) VALUES (...)
    ");

    $file_extension = pathinfo($filename, PATHINFO_EXTENSION);

    $stmt->execute([
        ':payment_entry_id' => $payment_entry_id,
        ':attachment_type' => $attachment_type,           // 'line_item_media'
        ':reference_id' => $reference_id,                 // 'entryMedia_0'
        ':filename' => $filename,                         // 'invoice.pdf'
        ':filepath' => $filepath,                         // '/uploads/entry_media/...'
        ':filesize' => $filesize,                         // 204800
        ':mime_type' => $mime_type,                       // 'application/pdf'
        ':extension' => $file_extension,                  // 'pdf'
        ':file_hash' => $file_hash,                       // SHA256 hash
        ':user_id' => $user_id                            // 3
    ]);
}
```

---

## Summary - File Attachment Storage

| Aspect | Details |
|--------|---------|
| **Physical Storage** | `/uploads/entry_media/entry_media_[timestamp]_[hex].[ext]` |
| **Database Tables** | 1. `tbl_payment_entry_line_items_detail` (main entry data) |
| | 2. `tbl_payment_entry_file_attachments_registry` (file registry) |
| **Columns in Line Items** | `line_item_media_upload_path`, `line_item_media_original_filename`, `line_item_media_filesize_bytes`, `line_item_media_mime_type` |
| **Columns in Registry** | `attachment_file_stored_path`, `attachment_file_original_name`, `attachment_file_size_bytes`, `attachment_file_mime_type`, `attachment_integrity_hash` |
| **File Size Limit** | 50 MB for entry media |
| **Allowed Types** | PDF, JPG, PNG, MP4, MOV, AVI |
| **Integrity Check** | SHA256 hash stored in `attachment_integrity_hash` |
| **Security** | Unique filename with timestamp + random hex prevents name collision and overwrite attacks |

---

## Access the File Later

To retrieve the file and send it to user:

```php
// Query to get file info
$stmt = $pdo->prepare("
    SELECT line_item_media_upload_path, line_item_media_original_filename
    FROM tbl_payment_entry_line_items_detail
    WHERE line_item_entry_id = ?
");
$stmt->execute([$line_item_id]);
$result = $stmt->fetch();

// Serve the file
$file_path = $_SERVER['DOCUMENT_ROOT'] . $result['line_item_media_upload_path'];
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $result['line_item_media_original_filename'] . '"');
readfile($file_path);
```

This completes the file attachment storage mechanism!
