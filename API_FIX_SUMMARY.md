# Recipient Files Modal - API HTTP 500 Error Fix

## Problem
The APIs were returning HTTP 500 errors when trying to fetch recipient files:
- `get_recipient_line_item_files.php?name=AditityApal1` → 500 Error
- `Error fetching recipient files: Error: HTTP 500`

## Root Cause
The recipient object passed from the dashboard contains:
- `type` - "labour" or "vendor"
- `name` - recipient name
- `id` - recipient ID (recipient_id_reference in DB)
- `category` - recipient type category
- `vendor_category` - vendor category
- `amount` - amount paid

**The problem:** The APIs were trying to use `line_item_id` which doesn't exist in the recipient object.

## Solution

### 1. API Parameter Change
Updated both APIs to use `recipient_id` instead of `line_item_id`:

**Old:** `get_recipient_line_item_files.php?payment_entry_id=1&line_item_id=5`
**New:** `get_recipient_line_item_files.php?payment_entry_id=1&recipient_id=5`

### 2. Database Query Updated

**File:** `get_recipient_line_item_files.php`
```php
// Query now uses recipient_id_reference to find all files for this recipient
WHERE payment_entry_master_id_fk = :payment_entry_id
AND recipient_id_reference = :recipient_id
AND line_item_media_upload_path IS NOT NULL
AND line_item_media_upload_path != ''
```

**File:** `get_recipient_acceptance_files.php`
```php
// First find the line_item_entry_id for this recipient
SELECT line_item_entry_id FROM tbl_payment_entry_line_items_detail
WHERE recipient_id_reference = :recipient_id

// Then fetch acceptance method files using that line_item_id
WHERE line_item_entry_id_fk = :line_item_id
AND method_supporting_media_path IS NOT NULL
```

### 3. Modal JavaScript Updated

**File:** `modals/recipient_files_modal.php` - `fetchRecipientFiles()` function
```javascript
const recipientId = recipient.id || lineItemId;

// Now passes recipient_id to APIs
fetch(`get_recipient_line_item_files.php?payment_entry_id=${paymentEntryId}&recipient_id=${recipientId}...`)
fetch(`get_recipient_acceptance_files.php?payment_entry_id=${paymentEntryId}&recipient_id=${recipientId}...`)
```

## Files Modified

1. `/get_recipient_line_item_files.php`
   - Changed parameter from `line_item_id` to `recipient_id`
   - Updated query to use `recipient_id_reference`
   - Added proper headers and content-type

2. `/get_recipient_acceptance_files.php`
   - Changed parameter from `line_item_id` to `recipient_id`
   - Added sub-query to find line_item_id from recipient_id
   - Updated query to properly link acceptance methods
   - Added proper headers and content-type

3. `/modals/recipient_files_modal.php`
   - Updated `fetchRecipientFiles()` to use `recipient.id`
   - Updated API URLs to pass `recipient_id` parameter

## Database Mapping

**Recipient ID (from get_payment_entries.php):**
```
recipient.id = tbl_payment_entry_line_items_detail.recipient_id_reference
```

**Line Item ID (from database):**
```
line_item_id = tbl_payment_entry_line_items_detail.line_item_entry_id
```

**Query Flow:**
1. User clicks "Proofs" on recipient
2. Modal receives `recipient` object with `recipient.id` (the vendor/labour ID)
3. API queries `tbl_payment_entry_line_items_detail` WHERE `recipient_id_reference = recipient.id`
4. Gets all line item files for this recipient
5. For acceptance files, finds the corresponding `line_item_entry_id` first
6. Then queries `tbl_payment_acceptance_methods_line_items` using that line_item_entry_id

## Testing

✅ Now test by:
1. Opening dashboard
2. Expanding a payment entry
3. Clicking "Proofs" on any recipient
4. Both file tabs should load without HTTP 500 errors

