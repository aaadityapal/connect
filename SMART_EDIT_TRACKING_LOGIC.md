# Smart Edit Tracking - Only Track Actual Changes

## Problem Fixed
Previously, when editing a payment entry, **ALL line items** were marked as "edited" regardless of whether you actually changed them or not.

**Before (Wrong):**
- User edits only Line Item 1 (changes amount from 100 to 104)
- Lines 1, 2, 3, 4 all show `edited_by=33, edited_at=2025-12-07 11:33:46, edit_count=1`
- Result: All 4 line items show "Edited" badge ❌

**After (Correct):**
- User edits only Line Item 1 (changes amount from 100 to 104)
- Line 1: `edited_by=33, edited_at=2025-12-07 12:00:00, edit_count=1` → Shows "Edited" badge ✓
- Lines 2, 3, 4: `edited_by=NULL, edited_at=NULL, edit_count=0` → No "Edited" badge ✓

## How It Works

### 1. Fetch Original Data
Before updating, the handler fetches the **original line items** from the database:
```php
SELECT line_item_entry_id, line_item_amount, line_item_payment_mode, 
       line_item_paid_via_user_id, payment_description_notes, line_item_status
FROM tbl_payment_entry_line_items_detail
WHERE payment_entry_master_id_fk = :payment_entry_id
```

### 2. Compare Line Items
For each line item being updated, compare the new values with original values:
```php
$lineItemWasModified = 
    $origItem['line_item_amount'] != $newItem['line_item_amount'] ||
    $origItem['line_item_payment_mode'] != $newItem['line_item_payment_mode'] ||
    $origItem['line_item_paid_via_user_id'] != $newItem['line_item_paid_via_user_id'] ||
    $origItem['payment_description_notes'] != $newItem['payment_description_notes'];
```

### 3. Update Edit Tracking Only if Changed
```php
if ($lineItemStatus === 'rejected') {
    // Always update if rejected → resets to pending
    $lineItemEditedBy = $_SESSION['user_id'];
    $lineItemEditedAt = date('Y-m-d H:i:s');
    $lineItemEditCount++;
} elseif ($lineItemWasModified) {
    // Update only if data actually changed
    $lineItemEditedBy = $_SESSION['user_id'];
    $lineItemEditedAt = date('Y-m-d H:i:s');
    $lineItemEditCount++;
}
// If NOT modified → keep original values (don't change)
```

## Fields Checked for Changes
The handler detects if any of these fields changed:
- ✓ `line_item_amount` (₹ amount)
- ✓ `line_item_payment_mode` (cash, cheque, etc.)
- ✓ `line_item_paid_via_user_id` (payment done by)
- ✓ `payment_description_notes` (description)

## What Gets Updated
When a line item is detected as modified:
- `edited_by` → Set to current user ID
- `edited_at` → Set to current timestamp
- `edit_count` → Increment by 1

When a line item is NOT modified:
- `edited_by` → **Stays the same** (NULL or previous user)
- `edited_at` → **Stays the same** (NULL or previous timestamp)
- `edit_count` → **Stays the same** (not incremented)

## Special Cases

### Case 1: Rejected Line Item
If a line item was rejected and you edit ANY field:
- Status changes: rejected → pending
- Rejection info cleared (rejected_by, rejected_at, reason)
- Edit tracking updated (edited_by, edited_at, edit_count++)
- "Edited" badge appears

### Case 2: Rejected → Edited → Approved
Timeline:
1. Line item created as "Pending"
2. Manager rejects it (no edit tracking yet)
3. User edits it → Automatically changes to "Pending" + shows "Edited" badge
4. Manager approves it → "Edited" badge remains (shows history)

## Result for Users

**You will see:**
- "Edited" badge only on line items you actually modified
- Unmodified line items have NO badge, even if others were edited
- Precise tracking of which items were changed and when
- Cleaner, more accurate audit trail

## Code Location
File: `/handlers/update_payment_entry_handler.php`
- Lines 100-120: Fetch original line items
- Lines 280-340: Compare and apply edit tracking logic
