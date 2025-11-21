# Payment Entry Status Display Guide

## WHERE STATUS IS SHOWN IN PURCHASE_MANAGER_DASHBOARD.php

---

## 1. STATUS FILTER SECTION (Line 1103-1111)

### Location in Dashboard
At the top of the page, in the **Filters** section

### HTML Code
```html
<!-- Status Filter -->
<div class="filter-group">
    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="">Select Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="completed">Completed</option>
    </select>
</div>
```

### Purpose
- Users can **filter payment entries by status**
- Dropdown allows selecting: Pending, Approved, Rejected, or Completed
- Filter is applied via the "Apply Filter" button

### Visual Representation
```
┌─────────────────────────────────────────┐
│ FILTERS                                 │
├─────────────────────────────────────────┤
│ From Date    │  To Date                 │
│ Payment Type │  Status ← YOU ARE HERE   │
│              │  ▼                       │
│              │  ┌─────────────┐         │
│              │  │ Pending     │         │
│              │  │ Approved    │         │
│              │  │ Rejected    │         │
│              │  │ Completed   │         │
│              │  └─────────────┘         │
│                                         │
│ [Apply Filter] [Reset]                 │
└─────────────────────────────────────────┘
```

---

## 2. VENDOR/LABOUR STATUS DISPLAY (Lines 1463, 1596)

### For Vendors (Line 1463)
```javascript
html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${vendor.vendor_status}</span></div>`;
```

### For Labours (Line 1596)
```javascript
html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${labour.status}</span></div>`;
```

### Status Classes & Colors
```css
.vendor-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 700;
    text-transform: capitalize;
}

.vendor-status.active {
    background-color: #c6f6d5;  /* GREEN - Active */
    color: #22543d;
}

.vendor-status.inactive {
    background-color: #fed7d7;  /* RED - Inactive */
    color: #742a2a;
}

.vendor-status.suspended {
    background-color: #feebc8;  /* AMBER - Suspended */
    color: #7c2d12;
}

.vendor-status.archived {
    background-color: #cbd5e0;  /* GRAY - Archived */
    color: #2d3748;
}
```

### Visual Display
```
┌─────────────────────────────────────────┐
│ VENDORS / LABOURS TABLE                 │
├─────────────────────────────────────────┤
│ Code  │ Name      │ Type  │ Status      │
├───────┼───────────┼───────┼─────────────┤
│ V001  │ Sharma    │ Steel │ [ACTIVE]    │ ← Green Badge
│ V002  │ Patel     │ Steel │ [INACTIVE]  │ ← Red Badge
│ V003  │ Kumar     │ Labor │ [SUSPENDED] │ ← Amber Badge
│ L001  │ Ram       │ Labor │ [ARCHIVED]  │ ← Gray Badge
└─────────────────────────────────────────┘
```

---

## 3. PAYMENT ENTRY STATUS IN TABLE (CURRENTLY NOT SHOWN)

### Current Issue
**Status is NOT currently displayed in the payment entries table!**

The payment entries table shows:
- Project Name
- Paid To
- Payment Date
- Grand Total ✓
- Payment Mode ✓
- Files ✓
- Actions ✓

**BUT NO STATUS COLUMN!**

### Table Header (Line 1716-1723)
```javascript
html += '<div class="vendor-row-header">';
html += '<div class="project-filter-container"><span>Project Name</span>...</div>';
html += '<div class="project-filter-container"><span>Paid To</span>...</div>';
html += '<div>Payment Date</div>';
html += '<div>Grand Total</div>';
html += '<div>Payment Mode</div>';
html += '<div>Files</div>';
html += '<div>Actions</div>';  // ← NO STATUS COLUMN HERE
html += '</div>';
```

### Visual of Current Display
```
┌──────────────────────────────────────────────────────────────────────┐
│ RECENTLY ADDED RECORDS - PAYMENT ENTRIES                            │
├──────────────────────────────────────────────────────────────────────┤
│ Project  │ Paid To │ Date    │ Amount    │ Mode │ Files │ Actions  │
│ Name     │         │         │ (₹)       │      │ Count │          │
├──────────┼─────────┼─────────┼───────────┼──────┼───────┼──────────┤
│ Site A   │ Ram     │ 20-11-25│ ₹100,000  │ Cash │ 2     │ 👁 ✏ 🗑  │
│ Site B   │ Kumar   │ 21-11-25│ ₹50,000   │ UPI  │ 1     │ 👁 ✏ 🗑  │
│ Site C   │ Patel   │ 21-11-25│ ₹75,000   │ Bank │ 3     │ 👁 ✏ 🗑  │
│          │         │         │           │      │       │          │
│ ❌ STATUS COLUMN MISSING HERE! ❌                                    │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 4. EXPANDED DETAILS SECTION (Line 1764+)

When you click the **chevron/expand button**, payment details open:

### What's Shown in Expanded View
```javascript
// Top row with main details
html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 16px;">';

html += '<div>PROJECT NAME</div>';        // ← Shown
html += '<div>PROJECT TYPE</div>';        // ← Shown
html += '<div>MAIN AMOUNT</div>';         // ← Shown
html += '<div>PAYMENT DATE</div>';        // ← Shown
// ❌ STATUS NOT SHOWN HERE EITHER!

html += '</div>';
```

### Expanded View Layout
```
┌─────────────────────────────────────────────────────┐
│ EXPANDED PAYMENT ENTRY DETAILS                      │
├─────────────────────────────────────────────────────┤
│ PROJECT NAME    PROJECT TYPE   MAIN AMOUNT  DATE    │
│ ┌──────────┐   ┌──────────┐   ┌────────┐   ┌────┐  │
│ │ Site A   │   │ Interior │   │ ₹100K  │   │ 20 │  │
│ └──────────┘   └──────────┘   └────────┘   └────┘  │
│                                                      │
│ ❌ NO STATUS HERE ❌                                 │
│                                                      │
├─────────────────────────────────────────────────────┤
│ RECIPIENTS BREAKDOWN (Line Items)                   │
├─────────────────────────────────────────────────────┤
│ PAID TO      TYPE       AMOUNT    CATEGORY  MODE    │
│ Ram Kumar    Labour     ₹50,000   Labor     Cash    │
│ Steel Inc.   Vendor     ₹30,000   Steel     Bank    │
│ Electrician  Labour Sk. ₹20,000   Skilled   UPI     │
└─────────────────────────────────────────────────────┘
```

---

## 5. JAVASCRIPT STATUS HANDLING

### How Status Data Flows
```javascript
// Line 1724-1725: Status comes from API response
const statusClass = entry.status.toLowerCase();
const grandTotal = '₹' + parseFloat(entry.grand_total).toFixed(2);
```

### But Status is NOT Used in Display!
```javascript
// Status is extracted but NEVER used in HTML rendering
const statusClass = entry.status.toLowerCase();  // ← Extracted
// ... but where is it displayed?
// 
// It's NOT displayed anywhere! ❌
```

---

## 6. WHERE STATUS SHOULD BE DISPLAYED

### Recommendation 1: Add Status Column in Table
```javascript
// Add this to the header (Line 1722)
html += '<div>Status</div>';

// Add this to each row (after Payment Mode)
html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${entry.status.toUpperCase()}</span></div>`;
```

### Recommendation 2: Add Status to Expanded Details
```javascript
// Add to expanded details section (Line 1760+)
html += '<div style="border-left: 3px solid #667eea; padding: 8px 12px; background: white; border-radius: 3px;">';
html += `<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase;">STATUS</div>`;
html += `<span class="vendor-status ${statusClass}" style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75em; font-weight: 700;">${entry.status.toUpperCase()}</span>`;
html += '</div>';
```

---

## 7. STATUS COLOR CODES FOR PAYMENT ENTRIES

### Suggested CSS Classes
```css
.vendor-status.submitted {
    background-color: #feebc8;  /* AMBER - Awaiting Review */
    color: #7c2d12;
}

.vendor-status.pending {
    background-color: #bee3f8;  /* BLUE - Under Review */
    color: #2c5282;
}

.vendor-status.approved {
    background-color: #c6f6d5;  /* GREEN - Approved */
    color: #22543d;
}

.vendor-status.rejected {
    background-color: #fed7d7;  /* RED - Rejected */
    color: #742a2a;
}

.vendor-status.draft {
    background-color: #cbd5e0;  /* GRAY - Draft */
    color: #2d3748;
}
```

---

## 8. COMPLETE STATUS VISIBILITY MAP

```
┌─────────────────────────────────────────────────────────────────────┐
│                    PURCHASE MANAGER DASHBOARD                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│ TOP SECTION:                                                         │
│ ┌──────────────────────────────────────────────────────────────┐   │
│ │ FILTERS                                                      │   │
│ │ ┌─────────────────────────────────────────────────────────┐  │   │
│ │ │ From Date  To Date   Payment Type  STATUS FILTER ✓     │  │   │
│ │ │                                    └─────────────┐      │  │   │
│ │ │                                       [Pending]   │      │  │   │
│ │ │                                       [Approved]  │      │  │   │
│ │ │                                       [Rejected]  │      │  │   │
│ │ └─────────────────────────────────────────────────────────┘  │   │
│ └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│ MIDDLE SECTION:                                                      │
│ ┌──────────────────────────────────────────────────────────────┐   │
│ │ VENDORS TABLE - Status Column ✓ (ACTIVE, INACTIVE, etc.)    │   │
│ └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│ BOTTOM SECTION:                                                      │
│ ┌──────────────────────────────────────────────────────────────┐   │
│ │ PAYMENT ENTRIES TABLE                                        │   │
│ │ Project│ Paid To│ Date│ Amount│ Mode│ Files│ Actions        │   │
│ │        │        │     │       │     │      │                │   │
│ │ ❌ STATUS COLUMN MISSING HERE - NEEDS TO BE ADDED           │   │
│ │                                                               │   │
│ │ When Expanded:                                              │   │
│ │ PROJECT NAME │ TYPE │ AMOUNT │ DATE                        │   │
│ │ ❌ STATUS NOT SHOWN IN EXPANDED VIEW EITHER                │   │
│ └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## SUMMARY

| Section | Status Shown? | Location |
|---------|---------------|----------|
| **Status Filter (Top)** | ✅ YES | Filter dropdown to filter entries |
| **Vendor Table** | ✅ YES | Status badge column |
| **Labour Table** | ✅ YES | Status badge column |
| **Payment Entries Table** | ❌ **NO** | **NEEDS TO BE ADDED** |
| **Expanded Payment Details** | ❌ **NO** | **NEEDS TO BE ADDED** |

---

## ACTION ITEMS

To fully implement status display for payment entries:

1. **Add Status Column** to payment entries table header
2. **Add Status Display** in each payment entry row
3. **Add Status** to expanded details section
4. **Apply CSS Classes** for color-coded status badges
5. **Test Filtering** to ensure filter works with displayed status

