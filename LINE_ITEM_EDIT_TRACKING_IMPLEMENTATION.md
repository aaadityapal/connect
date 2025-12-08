# Line-Item-Level Edit Tracking Implementation

## Overview
Instead of tracking edits at the **master entry level**, we now track edits at the **line item level**. Each line item has its own edit metadata, allowing independent tracking of what was actually modified.

## Database Changes

### New Columns Added to `tbl_payment_entry_line_items_detail`
- `edited_by` (INT(11), NULL) - User ID of who edited the line item
- `edited_at` (TIMESTAMP, NULL) - When the line item was edited
- `edit_count` (INT(11), DEFAULT 0) - Count of edits for this line item

## Architecture

### Master Entry Level
- No longer tracks edits at master level
- Master entry status remains independent
- Master `edit_count` still tracks overall entry edits (informational)

### Line Item Level
- Each line item tracks its own edit metadata
- "Edited" badge appears ONLY on line items that were actually modified
- When a rejected line item is edited:
  1. Status changes from "rejected" to "pending"
  2. Rejection info is cleared (rejected_by, rejected_at, rejection_reason)
  3. Edit tracking is set (edited_by = current user, edited_at = now)

## Code Changes

### Backend: `handlers/update_payment_entry_handler.php`

**Key Logic (Lines 263-285):**
```php
// For each line item:
if ($lineItemStatus === 'rejected') {
    // When rejected item is edited:
    $lineItemStatus = 'pending';              // Reset status
    $rejectedBy = null;                       // Clear rejection info
    $rejectedAt = null;
    $rejectionReason = null;
    $lineItemEditedBy = $_SESSION['user_id']; // Track who edited
    $lineItemEditedAt = date('Y-m-d H:i:s');  // Track when edited
    $lineItemEditCount++;                      // Increment count
} else {
    // For non-rejected items, also track the edit
    $lineItemEditedBy = $_SESSION['user_id'];
    $lineItemEditedAt = date('Y-m-d H:i:s');
    $lineItemEditCount++;
}
```

**Master Update (Simplified):**
- No longer sets edited_by/edited_at at master level
- Only updates basic entry fields and status
- edit_count still incremented (for audit trail)

### API: `fetch_complete_payment_entry_data_comprehensive.php`

**New Columns in Line Items Query:**
- `l.edited_by`
- `l.edited_at`
- `l.edit_count`
- `u_edited.username as edited_by_username` (with LEFT JOIN)

### Frontend: `payment_entry_reports.php`

**Updated "Edited" Badge Logic (Lines 2253-2265):**
```php
// Show "Edited" badge if the LINE ITEM has edit tracking data
if (!empty($item['edited_by']) && !empty($item['edited_at'])): 
    // Badge shows: "Edited by [Username] at [DateTime]"
endif;
```

## Behavior Changes

### Before
- Edit tracking at master entry level
- All line items show "Edited" badge if master entry was edited
- Rejected entries changing to pending didn't show any visual indicator

### After
- **Each line item has independent edit tracking**
- Only modified line items show "Edited" badge
- When rejected line item is edited:
  - Status changes to "Pending" immediately
  - "Edited" badge appears on that specific line item
  - Other line items unaffected
  - Rejection metadata completely cleared

## Benefits

1. **Precision**: See exactly which line items were modified
2. **Clarity**: Each line item's "Edited" status is independent
3. **Consistency**: Rejected items automatically reset to pending when edited
4. **Audit Trail**: Full history of who edited what and when

## Testing Checklist

- [ ] Edit entry with rejected line items
  - [ ] Verify master status unchanged
  - [ ] Verify rejected item status changes to "Pending"
  - [ ] Verify "Edited" badge appears on that line item only
  - [ ] Verify rejection info (rejected_by, etc.) is cleared

- [ ] Edit entry with all pending line items
  - [ ] Verify all line items get "Edited" badge
  - [ ] Verify edited_by and edited_at are set correctly
  - [ ] Verify edit_count is incremented

- [ ] Check API response
  - [ ] Verify edited_by, edited_at, edit_count in line items
  - [ ] Verify edited_by_username is populated correctly

## Files Modified

1. `add_line_item_edit_tracking.php` - Database schema migration (NEW)
2. `handlers/update_payment_entry_handler.php` - Backend logic update
3. `fetch_complete_payment_entry_data_comprehensive.php` - API data fetching
4. `payment_entry_reports.php` - UI badge display
