# File Attachment Storage - Quick Reference

## ANSWER: Where Files Are Saved When User Attaches to Entry

### **TWO PLACES:**

```
┌─────────────────────────────────────────────────────────────────────┐
│                       FILE ATTACHMENT FLOW                          │
└─────────────────────────────────────────────────────────────────────┘

1. PHYSICAL FILE SYSTEM
   └─ Directory: /connect/uploads/entry_media/
      └─ Filename: entry_media_1734589012_f8e4d9a2.pdf
         (Pattern: entry_media_[timestamp]_[random_hex].[extension])

2. DATABASE (Two Tables Store Different Data)
   ├─ Table: tbl_payment_entry_line_items_detail
   │  ├─ Column: line_item_media_upload_path = "/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf"
   │  ├─ Column: line_item_media_original_filename = "invoice.pdf"
   │  ├─ Column: line_item_media_filesize_bytes = 204800
   │  └─ Column: line_item_media_mime_type = "application/pdf"
   │
   └─ Table: tbl_payment_entry_file_attachments_registry
      ├─ Column: attachment_file_stored_path = "/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf"
      ├─ Column: attachment_file_original_name = "invoice.pdf"
      ├─ Column: attachment_file_size_bytes = 204800
      ├─ Column: attachment_file_mime_type = "application/pdf"
      ├─ Column: attachment_integrity_hash = "a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2..." (SHA256)
      └─ Column: attachment_type_category = "line_item_media"
```

---

## TABLE 1: tbl_payment_entry_line_items_detail

**Stores the entry data INCLUDING the file info:**

```sql
INSERT INTO tbl_payment_entry_line_items_detail (
    line_item_entry_id,              ← Auto ID: 1, 2, 3...
    payment_entry_master_id_fk,      ← Links to main payment: 5
    recipient_type_category,          ← "labour"
    recipient_id_reference,           ← 1
    recipient_name_display,           ← "John Worker"
    payment_description_notes,        ← "Foundation work"
    line_item_amount,                 ← 15000.00
    line_item_payment_mode,           ← "cash"
    line_item_sequence_number,        ← 1
    line_item_media_upload_path,      ← FILE PATH
    line_item_media_original_filename, ← ORIGINAL NAME
    line_item_media_filesize_bytes,   ← FILE SIZE
    line_item_media_mime_type,        ← FILE TYPE
    line_item_status,                 ← "pending"
    created_at_timestamp              ← 2025-11-20 10:30:45
);
```

**4 Columns Store File Data:**
| Column | Example Value |
|--------|---------------|
| `line_item_media_upload_path` | `/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf` |
| `line_item_media_original_filename` | `invoice.pdf` |
| `line_item_media_filesize_bytes` | `204800` |
| `line_item_media_mime_type` | `application/pdf` |

---

## TABLE 2: tbl_payment_entry_file_attachments_registry

**Central registry for ALL file attachments (audit + integrity):**

```sql
INSERT INTO tbl_payment_entry_file_attachments_registry (
    attachment_id,                  ← Auto ID: 1, 2, 3...
    payment_entry_master_id_fk,     ← 5
    attachment_type_category,       ← "line_item_media" (enum)
    attachment_reference_id,        ← "entryMedia_0"
    attachment_file_original_name,  ← "invoice.pdf"
    attachment_file_stored_path,    ← FILE PATH
    attachment_file_size_bytes,     ← FILE SIZE
    attachment_file_mime_type,      ← FILE TYPE
    attachment_file_extension,      ← "pdf"
    attachment_upload_timestamp,    ← 2025-11-20 10:30:45
    attachment_verification_status, ← "pending"
    attachment_integrity_hash,      ← SHA256 HASH
    uploaded_by_user_id             ← 3
);
```

**7 Columns Store File Data:**
| Column | Example Value |
|--------|---------------|
| `attachment_file_original_name` | `invoice.pdf` |
| `attachment_file_stored_path` | `/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf` |
| `attachment_file_size_bytes` | `204800` |
| `attachment_file_mime_type` | `application/pdf` |
| `attachment_file_extension` | `pdf` |
| `attachment_integrity_hash` | `a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2...` |
| `attachment_type_category` | `line_item_media` |

---

## What Gets Saved - Complete Example

### **User Uploads:**
```
File: invoice.pdf (200 KB)
Entry: John Worker, ₹15,000, Foundation work
```

### **FILE SYSTEM:**
```
/Applications/XAMPP/xamppfiles/htdocs/connect/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
Size: 204,800 bytes
Type: PDF Document
```

### **DATABASE - LINE ITEMS TABLE:**
```
line_item_media_upload_path         : /uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
line_item_media_original_filename   : invoice.pdf
line_item_media_filesize_bytes      : 204800
line_item_media_mime_type           : application/pdf
```

### **DATABASE - FILE REGISTRY TABLE:**
```
attachment_file_stored_path         : /uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
attachment_file_original_name       : invoice.pdf
attachment_file_size_bytes          : 204800
attachment_file_mime_type           : application/pdf
attachment_file_extension           : pdf
attachment_integrity_hash           : a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2e1f4a7b9d2c5e8a1f4b7c9d2e5f8a1
attachment_type_category            : line_item_media
attachment_reference_id             : entryMedia_0
```

---

## Why TWO Tables?

| Purpose | Table |
|---------|-------|
| **Store entry data with file info** | `tbl_payment_entry_line_items_detail` |
| **Track ALL file uploads with integrity** | `tbl_payment_entry_file_attachments_registry` |
| **Audit trail** | Registry stores: upload time, user ID, verification status |
| **Integrity verification** | Registry stores SHA256 hash to verify file wasn't tampered |
| **Query convenience** | Entry table for quick access; Registry for comprehensive audit |

---

## File Size & Type Limits

| Limit | Value |
|-------|-------|
| **Max File Size** | 50 MB |
| **Allowed Types** | PDF, JPG, PNG, MP4, MOV, AVI |
| **Storage Location** | `/uploads/entry_media/` |
| **Filename Pattern** | `entry_media_[TIMESTAMP]_[HEX].ext` |

---

## SQL Query to Get Entry with File

```sql
-- Get entry with complete file information
SELECT 
    l.line_item_entry_id,
    l.recipient_name_display,
    l.line_item_amount,
    l.line_item_media_original_filename,
    l.line_item_media_upload_path,
    l.line_item_media_filesize_bytes,
    f.attachment_integrity_hash,
    f.attachment_verification_status
FROM tbl_payment_entry_line_items_detail l
LEFT JOIN tbl_payment_entry_file_attachments_registry f
    ON l.payment_entry_master_id_fk = f.payment_entry_master_id_fk
WHERE l.line_item_entry_id = 1;
```

---

## Backend Code Snippets

### **File Upload Handler:**
```php
// Step 1: Validate file
if (!in_array($file['type'], $allowed_types)) {
    throw new Exception('Invalid file type');
}

// Step 2: Create directory
$upload_dir = '/connect/uploads/entry_media/';
mkdir($upload_dir, 0755, true);

// Step 3: Generate unique filename
$unique_filename = 'entry_media_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';

// Step 4: Save file
move_uploaded_file($file['tmp_name'], $upload_dir . $unique_filename);

// Step 5: Insert into line items table
$stmt = $pdo->prepare("
    INSERT INTO tbl_payment_entry_line_items_detail (
        line_item_media_upload_path,
        line_item_media_original_filename,
        line_item_media_filesize_bytes,
        line_item_media_mime_type
    ) VALUES (?, ?, ?, ?)
");

// Step 6: Register in file registry (with SHA256 hash)
$file_hash = hash_file('sha256', $filepath);
$stmt = $pdo->prepare("
    INSERT INTO tbl_payment_entry_file_attachments_registry (
        attachment_file_stored_path,
        attachment_file_original_name,
        attachment_file_size_bytes,
        attachment_file_mime_type,
        attachment_integrity_hash,
        attachment_type_category
    ) VALUES (?, ?, ?, ?, ?, 'line_item_media')
");
```

---

## Summary Table

| Aspect | Details |
|--------|---------|
| **Physical File Location** | `/connect/uploads/entry_media/entry_media_[timestamp]_[hex].[ext]` |
| **Main Database Table** | `tbl_payment_entry_line_items_detail` |
| **File Info Table** | `tbl_payment_entry_file_attachments_registry` |
| **File Path Column** | `line_item_media_upload_path` & `attachment_file_stored_path` |
| **Original Filename** | `line_item_media_original_filename` & `attachment_file_original_name` |
| **File Size** | `line_item_media_filesize_bytes` & `attachment_file_size_bytes` |
| **MIME Type** | `line_item_media_mime_type` & `attachment_file_mime_type` |
| **Integrity** | SHA256 hash in `attachment_integrity_hash` |
| **Security** | Unique filename prevents collisions & overwrites |
| **Max Size** | 50 MB |
| **Allowed Types** | PDF, JPG, PNG, MP4, MOV, AVI |

---

**FINAL ANSWER:**
When user attaches file to entry:
✅ File saved physically in `/uploads/entry_media/` with unique name
✅ File info saved in `tbl_payment_entry_line_items_detail` (4 columns)
✅ File audit/integrity saved in `tbl_payment_entry_file_attachments_registry` (7 columns)
