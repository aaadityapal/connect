# New Recipient Files Modal - Implementation Summary

## What Was Created

A new focused modal that displays **ONLY files for a specific recipient (labour/vendor)** from their expanded entry details.

---

## Files Created

### 1. **Modal File**
`/modals/recipient_files_modal.php`
- New modal with tabs for Line Item & Acceptance Method files
- Shows recipient name and category in header
- File stats (count, total size)
- Tab-based navigation

### 2. **API Endpoints**

**`get_recipient_line_item_files.php`**
- Fetches from: `tbl_payment_entry_line_items_detail`
- Returns columns: `line_item_media_upload_path`, `line_item_media_original_filename`, `line_item_media_filesize_bytes`, `line_item_media_mime_type`

**`get_recipient_acceptance_files.php`**
- Fetches from: `tbl_payment_acceptance_methods_line_items`
- Returns columns: `method_supporting_media_path`, `method_supporting_media_filename`, `method_supporting_media_size`, `method_supporting_media_type`, `method_type_category`

---

## Changes Made

### **Dashboard** (`purchase_manager_dashboard.php`)

1. **Added modal include** (line ~1242):
```php
<!-- Include Recipient Files Modal -->
<?php include 'modals/recipient_files_modal.php'; ?>
```

2. **Updated Proofs Button** (in expanded entry details):
   - **Old:** Called `openPaymentFilesModal()` showing ALL files
   - **New:** Calls `openRecipientFilesModal()` showing ONLY recipient's files

```javascript
// Old (shows all files)
onclick="openPaymentFilesModal(entryId, recipientData)"

// New (shows only recipient's files)
onclick="openRecipientFilesModal(paymentEntryId, recipientIndex, recipientJsonString)"
```

---

## Modal Behavior

### **When User Clicks "Proofs" Button:**

1. Modal opens with recipient name in header
2. Shows 2 tabs:
   - **Line Item** - Files from `tbl_payment_entry_line_items_detail`
   - **Acceptance Methods** - Files from `tbl_payment_acceptance_methods_line_items`

### **Data Displayed:**

**Tab 1 - Line Item Files:**
- File name
- File size
- Upload date
- Download button
- Preview button

**Tab 2 - Acceptance Method Files:**
- File name
- Payment method (Cash, Cheque, Bank Transfer, etc)
- File size
- Download button
- Preview button

### **Stats Shown:**
- Line Item Files count
- Acceptance Method Files count
- Total size of all files

---

## Data Flow

```
User clicks "Proofs" button in expanded entry
          ↓
openRecipientFilesModal(paymentId, index, recipientJSON)
          ↓
Parse recipient data (name, type, category)
          ↓
Fetch from 2 APIs:
  ├─ get_recipient_line_item_files.php
  └─ get_recipient_acceptance_files.php
          ↓
Display files in two tabs
```

---

## Database Queries

### **Line Item Files Query:**
```sql
SELECT 
    line_item_entry_id,
    line_item_media_upload_path,
    line_item_media_original_filename,
    line_item_media_filesize_bytes,
    line_item_media_mime_type
FROM tbl_payment_entry_line_items_detail
WHERE payment_entry_master_id_fk = ?
  AND line_item_entry_id = ?
  AND line_item_media_upload_path IS NOT NULL
```

### **Acceptance Files Query:**
```sql
SELECT 
    line_item_acceptance_method_id,
    method_type_category,
    method_supporting_media_path,
    method_supporting_media_filename,
    method_supporting_media_size,
    method_supporting_media_type
FROM tbl_payment_acceptance_methods_line_items
WHERE payment_entry_master_id_fk = ?
  AND line_item_entry_id_fk = ?
  AND method_supporting_media_path IS NOT NULL
```

---

## Key Features

✅ **Specific to Recipient** - Only shows files for that labour/vendor
✅ **Two Sources** - Shows both line item and acceptance method files
✅ **Tab Navigation** - Easy switching between file types
✅ **File Stats** - Shows count and total size
✅ **Download & Preview** - Download or preview files directly
✅ **Clean UI** - Minimalist design with status badges

---

## No Changes To

- Payment Entry Details Modal (still shows comprehensive payment info)
- Payment Files Registry Modal (still shows all files from registry)
- Any existing functionality

---

## How It Works

1. **Old Flow (All Files):**
   Files Tab → Shows ALL 4 types of files (proof, acceptance, line item, line item method)

2. **New Flow (Recipient-Specific):**
   Proofs Button → Shows ONLY 2 types for that recipient:
   - Line Item files (entry-specific upload)
   - Acceptance Method files (payment method-specific upload)
