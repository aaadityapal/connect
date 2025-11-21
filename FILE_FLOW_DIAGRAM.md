# File Attachment - Visual Data Flow Diagram

## COMPLETE FLOW: User Uploads File to Entry

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           USER INTERFACE                                    │
│                    "Attach File" Button Clicked                             │
│                    Select: invoice.pdf (200 KB)                             │
└────────────────────────────┬────────────────────────────────────────────────┘
                             │
                             ↓
              ┌──────────────────────────────────┐
              │   CLIENT-SIDE VALIDATION         │
              ├──────────────────────────────────┤
              │ ✓ File size: 200 KB < 50 MB     │
              │ ✓ Type: .pdf (allowed)          │
              │ ✓ Show preview: invoice.pdf     │
              └────────────┬─────────────────────┘
                           │
                           ↓
              ┌──────────────────────────────────┐
              │    FORM SUBMISSION (POST)        │
              ├──────────────────────────────────┤
              │ endpoint: payment_entry_handler  │
              │ Files: {                         │
              │   entryMedia_0: invoice.pdf     │
              │ }                               │
              │ Data: {                          │
              │   type: "labour",                │
              │   recipientId: 1,                │
              │   amount: 15000,                 │
              │   ...                            │
              │ }                               │
              └────────────┬─────────────────────┘
                           │
                           ↓
    ┌──────────────────────────────────────────────────────────┐
    │           payment_entry_handler.php (Backend)            │
    ├──────────────────────────────────────────────────────────┤
    │                                                          │
    │  STEP 1: Get file from $_FILES                          │
    │  ─────────────────────────────────                      │
    │  $_FILES['entryMedia_0'] = {                            │
    │      'name' => 'invoice.pdf',                           │
    │      'tmp_name' => '/tmp/php_upload_xyz',               │
    │      'size' => 204800,                                  │
    │      'type' => 'application/pdf',                       │
    │      'error' => 0                                       │
    │  }                                                      │
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 2: Validate file                                  │
    │  ────────────────────────                               │
    │  if (file_size > 50MB) → ERROR ✗                       │
    │  if (!in_array(file_type, allowed)) → ERROR ✗          │
    │  if (!is_uploaded_file()) → ERROR ✗                    │
    │  ✓ All checks passed                                    │
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 3: Create upload directory                        │
    │  ──────────────────────────────────                     │
    │  $upload_dir = '/connect/uploads/entry_media/'          │
    │  mkdir($upload_dir, 0755, true)                         │
    │  Directory created ✓                                    │
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 4: Generate unique filename                       │
    │  ──────────────────────────────────                     │
    │  $timestamp = time()              // 1734589012         │
    │  $random_hex = random_bytes(4)    // f8e4d9a2           │
    │  $extension = 'pdf'               // from type          │
    │  $unique_filename = 'entry_media_1734589012_f8e4d9a2.pdf'
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 5: Move file to storage                           │
    │  ──────────────────────────────                         │
    │  from: /tmp/php_upload_xyz                              │
    │  to:   /connect/uploads/entry_media/entry_media_...pdf  │
    │  move_uploaded_file($tmp, $final_path) ✓               │
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 6: Calculate integrity hash                       │
    │  ────────────────────────────────────                   │
    │  $file_hash = hash_file('sha256', $filepath)            │
    │  Result: a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2...           │
    │          (64-char SHA256 string)                        │
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 7: Insert into LINE ITEMS table                   │
    │  ──────────────────────────────────────                 │
    │  INSERT INTO tbl_payment_entry_line_items_detail (      │
    │      line_item_entry_id: AUTO,                          │
    │      payment_entry_master_id_fk: 5,                     │
    │      recipient_type_category: 'labour',                 │
    │      recipient_id_reference: 1,                         │
    │      recipient_name_display: 'John Worker',             │
    │      payment_description_notes: 'Foundation work',      │
    │      line_item_amount: 15000.00,                        │
    │      line_item_payment_mode: 'cash',                    │
    │      line_item_sequence_number: 1,                      │
    │      *** line_item_media_upload_path:                   │
    │          '/uploads/entry_media/...pdf',                │
    │      *** line_item_media_original_filename:             │
    │          'invoice.pdf',                                │
    │      *** line_item_media_filesize_bytes:                │
    │          204800,                                        │
    │      *** line_item_media_mime_type:                     │
    │          'application/pdf'                              │
    │  )                                                      │
    │  Row inserted: ID = 1 ✓                                 │
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 8: Register in FILE REGISTRY table                │
    │  ───────────────────────────────────────                │
    │  INSERT INTO tbl_payment_entry_file_attachments_registry (
    │      attachment_id: AUTO,                               │
    │      payment_entry_master_id_fk: 5,                     │
    │      *** attachment_type_category: 'line_item_media',   │
    │      *** attachment_reference_id: 'entryMedia_0',       │
    │      *** attachment_file_original_name: 'invoice.pdf',  │
    │      *** attachment_file_stored_path:                   │
    │          '/uploads/entry_media/...pdf',                │
    │      *** attachment_file_size_bytes: 204800,            │
    │      *** attachment_file_mime_type: 'application/pdf',  │
    │      *** attachment_file_extension: 'pdf',              │
    │      attachment_upload_timestamp: NOW(),                │
    │      *** attachment_integrity_hash:                     │
    │          'a3f8d9c2e1b4f...',                           │
    │      attachment_verification_status: 'pending',         │
    │      uploaded_by_user_id: 3                             │
    │  )                                                      │
    │  Row inserted: ID = 1 ✓                                 │
    │                                                          │
    │  ↓                                                       │
    │                                                          │
    │  STEP 9: Commit transaction                             │
    │  ──────────────────────────                             │
    │  $pdo->commit()                                          │
    │  All changes permanent ✓                                │
    │                                                          │
    └────────────┬─────────────────────────────────────────────┘
                 │
                 ↓
    ┌──────────────────────────────────────┐
    │     RESPONSE TO CLIENT (JSON)        │
    ├──────────────────────────────────────┤
    │ {                                    │
    │   "success": true,                   │
    │   "message": "Entry saved",          │
    │   "payment_entry_id": 5,             │
    │   "grand_total": 50000,              │
    │   "files_attached": 1                │
    │ }                                    │
    └────────────┬────────────────────────┘
                 │
                 ↓
    ┌──────────────────────────────────────┐
    │  CLIENT: Success Message             │
    │  Modal closes, dashboard reloads     │
    │  New entry visible with file ✓       │
    └──────────────────────────────────────┘
```

---

## Where Data Lands

### **FILE SYSTEM:**
```
/Applications/XAMPP/xamppfiles/htdocs/connect/
└── uploads/
    └── entry_media/
        └── entry_media_1734589012_f8e4d9a2.pdf ← ACTUAL FILE (204,800 bytes)
```

### **DATABASE - tbl_payment_entry_line_items_detail:**
```
Row 1:
┌─────────────────────────────────────────────────────────────┐
│ line_item_entry_id              │ 1                         │
│ payment_entry_master_id_fk      │ 5                         │
│ recipient_type_category         │ labour                    │
│ recipient_id_reference          │ 1                         │
│ recipient_name_display          │ John Worker               │
│ payment_description_notes       │ Foundation work           │
│ line_item_amount                │ 15000.00                  │
│ line_item_payment_mode          │ cash                      │
│ line_item_sequence_number       │ 1                         │
│ line_item_media_upload_path     │ /uploads/entry_media/...│
│ line_item_media_original_name   │ invoice.pdf               │
│ line_item_media_filesize_bytes  │ 204800                    │
│ line_item_media_mime_type       │ application/pdf           │
│ created_at_timestamp            │ 2025-11-20 10:30:45       │
└─────────────────────────────────────────────────────────────┘
```

### **DATABASE - tbl_payment_entry_file_attachments_registry:**
```
Row 1:
┌────────────────────────────────────────────────────────────────┐
│ attachment_id                   │ 1                            │
│ payment_entry_master_id_fk      │ 5                            │
│ attachment_type_category        │ line_item_media              │
│ attachment_reference_id         │ entryMedia_0                 │
│ attachment_file_original_name   │ invoice.pdf                  │
│ attachment_file_stored_path     │ /uploads/entry_media/...    │
│ attachment_file_size_bytes      │ 204800                       │
│ attachment_file_mime_type       │ application/pdf              │
│ attachment_file_extension       │ pdf                          │
│ attachment_upload_timestamp     │ 2025-11-20 10:30:45          │
│ attachment_integrity_hash       │ a3f8d9c2e1b4f7a9d5c8...   │
│ attachment_verification_status  │ pending                      │
│ uploaded_by_user_id             │ 3                            │
└────────────────────────────────────────────────────────────────┘
```

---

## Relationship Diagram

```
tbl_payment_entry_master_records (Main Payment)
│
├─ payment_entry_id = 5
│
└─→ tbl_payment_entry_line_items_detail (Entry/Line Item)
   │
   ├─ line_item_entry_id = 1
   ├─ payment_entry_master_id_fk = 5
   ├─ line_item_media_upload_path = "/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf"
   ├─ line_item_media_original_filename = "invoice.pdf"
   ├─ line_item_media_filesize_bytes = 204800
   └─ line_item_media_mime_type = "application/pdf"
      │
      └─→ tbl_payment_entry_file_attachments_registry (File Audit)
         │
         ├─ attachment_id = 1
         ├─ payment_entry_master_id_fk = 5
         ├─ attachment_type_category = "line_item_media"
         ├─ attachment_file_stored_path = "/uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf"
         ├─ attachment_integrity_hash = "a3f8d9c2e1b4f7a9d5c8e2f1b4a7d9c2..."
         └─ uploaded_by_user_id = 3
```

---

## Multiple Entries Example

```
Main Payment (ID: 5) - ₹50,000
│
├─── Entry #1 (line_item_entry_id: 1)
│    ├─ Type: Labour
│    ├─ Amount: ₹15,000
│    ├─ File: invoice.pdf (204 KB)
│    │  └─ Stored: /uploads/entry_media/entry_media_1734589012_f8e4d9a2.pdf
│    │  └─ Registry: attachment_id = 1
│    └─ Hash: a3f8d9c2e1b4f...
│
├─── Entry #2 (line_item_entry_id: 2)
│    ├─ Type: Material Steel
│    ├─ Amount: ₹20,000
│    ├─ File: steel_invoice.pdf (512 KB)
│    │  └─ Stored: /uploads/entry_media/entry_media_1734589015_c3d7e9f1.pdf
│    │  └─ Registry: attachment_id = 2
│    └─ Hash: b4c7d9e2f1a3...
│
└─── Entry #3 (line_item_entry_id: 3)
     ├─ Type: Supplier
     ├─ Amount: ₹15,000
     ├─ File: cement_receipt.jpg (850 KB)
     │  └─ Stored: /uploads/entry_media/entry_media_1734589018_b4a9c2e5.jpg
     │  └─ Registry: attachment_id = 3
     └─ Hash: c5d8e0f3g2b4...

Total Files in Registry: 3
Total Storage Used: 1.5 MB
```

---

## Query to Retrieve Entry + File

```sql
-- Get entry with file information
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
    AND f.attachment_type_category = 'line_item_media'
WHERE l.line_item_entry_id = 1;

RESULT:
┌────────────────────────────────────┐
│ line_item_entry_id: 1              │
│ recipient_name_display: John Worker│
│ line_item_amount: 15000.00         │
│ line_item_media_original_filename: │
│   invoice.pdf                      │
│ line_item_media_upload_path:       │
│   /uploads/entry_media/...pdf      │
│ line_item_media_filesize_bytes:    │
│   204800                           │
│ attachment_integrity_hash:         │
│   a3f8d9c2e1b4f...                │
│ attachment_verification_status:    │
│   pending                          │
└────────────────────────────────────┘
```

---

## Security Features

```
┌────────────────────────────────────────────────────────┐
│              FILE ATTACHMENT SECURITY                  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ 1. UNIQUE FILENAME                                    │
│    entry_media_[TIMESTAMP]_[RANDOM_HEX].[EXT]        │
│    └─ Prevents collision and accidental overwrites    │
│                                                        │
│ 2. FILE TYPE VALIDATION                               │
│    ✓ Check MIME type (application/pdf, image/jpeg)   │
│    ✓ Check file extension (.pdf, .jpg, .png, etc)    │
│    ✗ Reject: .exe, .bat, .php, etc                   │
│                                                        │
│ 3. FILE SIZE LIMIT                                    │
│    ✗ Reject: > 50 MB                                 │
│    ✓ Allow: ≤ 50 MB                                  │
│                                                        │
│ 4. INTEGRITY VERIFICATION                             │
│    SHA256(file_content) → attachment_integrity_hash  │
│    └─ Detect tampering/corruption                     │
│                                                        │
│ 5. UPLOAD VERIFICATION                                │
│    is_uploaded_file($_FILES['...']['tmp_name'])      │
│    └─ Ensure file came via HTTP POST                 │
│                                                        │
│ 6. AUDIT TRAIL                                        │
│    - Who uploaded (uploaded_by_user_id)              │
│    - When uploaded (attachment_upload_timestamp)      │
│    - Verification status (attachment_verification)    │
│                                                        │
│ 7. TRANSACTION SAFETY                                 │
│    - BEGIN TRANSACTION                               │
│    - Save file + Insert DB                           │
│    - COMMIT or ROLLBACK (all-or-nothing)             │
│                                                        │
└────────────────────────────────────────────────────────┘
```

---

## Summary

**FINAL ANSWER - WHERE FILE IS SAVED:**

```
┌─ PHYSICAL FILE SYSTEM
│  └─ /connect/uploads/entry_media/entry_media_[timestamp]_[hex].pdf
│
└─ DATABASE (2 Tables)
   ├─ tbl_payment_entry_line_items_detail
   │  ├─ line_item_media_upload_path
   │  ├─ line_item_media_original_filename
   │  ├─ line_item_media_filesize_bytes
   │  └─ line_item_media_mime_type
   │
   └─ tbl_payment_entry_file_attachments_registry
      ├─ attachment_file_stored_path
      ├─ attachment_file_original_name
      ├─ attachment_file_size_bytes
      ├─ attachment_file_mime_type
      ├─ attachment_integrity_hash (SHA256)
      ├─ uploaded_by_user_id
      └─ attachment_verification_status
```
