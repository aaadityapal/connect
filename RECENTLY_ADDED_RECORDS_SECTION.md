# Recently Added Records Section - Detailed Explanation

## Overview

The **"Recently Added Records"** section is a dashboard component in the Purchase Manager dashboard that displays payment entries with dynamic filtering, expandable details, and inline actions. It's designed to give users a quick overview of recent payment transactions with comprehensive filtering capabilities.

---

## Section Location & Structure

### **HTML Structure (Lines 1107-1140):**

```html
<!-- Recently Added Records Section -->
<div class="recent-records-section">
    <div class="recent-records-header-container">
        <h3 class="recent-records-header">Recently Added Records</h3>
        
        <!-- Minimalist Date Range Filter -->
        <div class="records-date-filter-minimal">
            <input type="date" id="recordsDateFrom" class="mini-date-input" placeholder="From">
            <input type="date" id="recordsDateTo" class="mini-date-input" placeholder="To">
            <button class="mini-filter-btn apply" id="applyRecordsFilterBtn">
                <i class="fas fa-check"></i>
            </button>
            <button class="mini-filter-btn reset" id="resetRecordsFilterBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <button class="tab-btn active" data-tab="entries-tab">
            <i class="fas fa-receipt"></i>Recent Entries
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content active" id="entries-tab">
        <div id="entriesContainer">
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>Loading payment entries...</p>
            </div>
        </div>
    </div>
</div>
```

---

## Component Breakdown

### **1. Header Section**

```
┌────────────────────────────────────────────────────────────┐
│ Recently Added Records         [Date From] [Date To] [↓]   │
└────────────────────────────────────────────────────────────┘
```

**Elements:**
- **Title:** "Recently Added Records"
- **Date Range Inputs:** 
  - `recordsDateFrom` - Start date for filtering
  - `recordsDateTo` - End date for filtering
- **Filter Buttons:**
  - `applyRecordsFilterBtn` - Apply the date range filter
  - `resetRecordsFilterBtn` - Clear the date range filter

---

### **2. Tab Navigation**

```
┌────────────────────────────────────────────┐
│ 📄 Recent Entries                          │
└────────────────────────────────────────────┘
```

**Currently:** Only has **"Recent Entries"** tab
- Uses `data-tab="entries-tab"` for tab identification
- Tab becomes active on page load
- Can be extended to add other tabs (Vendors, Labours)

---

### **3. Data Container**

**Element ID:** `entriesContainer`
- Initially shows loading spinner
- Gets populated with payment entries via `loadPaymentEntries()` function
- Dynamically generates table rows for each entry

---

## JavaScript - loadPaymentEntries() Function

### **Function Signature:**
```javascript
function loadPaymentEntries(
    limit = 10,           // Records per page
    page = 1,             // Current page number
    search = '',          // Search filter (future use)
    status = '',          // Status filter
    dateFrom = '',        // Start date
    dateTo = '',          // End date
    projectType = '',     // Project type filter
    vendorCategory = ''   // Vendor category filter
)
```

### **Step-by-Step Process:**

#### **Step 1: Save State**
```javascript
entriesPaginationState.limit = limit;
entriesPaginationState.currentPage = page;
entriesPaginationState.search = search;
entriesPaginationState.status = status;
entriesPaginationState.dateFrom = dateFrom;
entriesPaginationState.dateTo = dateTo;
entriesPaginationState.projectType = projectType;
entriesPaginationState.vendorCategory = vendorCategory;
```

Stores pagination and filter parameters for later use.

#### **Step 2: Calculate Offset**
```javascript
const offset = (page - 1) * limit;
```

Example:
- Page 1, Limit 10 → Offset 0 (records 0-9)
- Page 2, Limit 10 → Offset 10 (records 10-19)

#### **Step 3: Show Loading State**
```javascript
entriesContainer.innerHTML = `
    <div class="loading-spinner">
        <i class="fas fa-spinner"></i>
        <p>Loading payment entries...</p>
    </div>
`;
```

Shows spinning loader while fetching data.

#### **Step 4: Build Query Parameters**
```javascript
const params = new URLSearchParams({
    limit: limit,
    offset: offset,
    search: search,
    status: status,
    dateFrom: dateFrom,
    dateTo: dateTo,
    projectType: projectType,
    vendorCategory: vendorCategory
});
```

Creates query string: `?limit=10&offset=0&search=&status=&dateFrom=...`

#### **Step 5: Fetch from API**
```javascript
fetch(`get_payment_entries.php?${params.toString()}`)
```

Calls backend API endpoint to get filtered payment entries.

#### **Step 6: Build Table HTML**
```javascript
if (data.success && data.data && data.data.length > 0) {
    let html = '<div class="vendor-table-wrapper">';
    
    // Add table header
    html += '<div class="vendor-row-header">';
    html += '<div>Project Name</div>';
    html += '<div>Paid To</div>';
    html += '<div>Payment Date</div>';
    html += '<div>Grand Total</div>';
    html += '<div>Payment Mode</div>';
    html += '<div>Files</div>';
    html += '<div>Actions</div>';
    html += '</div>';
    
    // Add rows for each entry
    data.data.forEach(entry => {
        // ... build row HTML
    });
    
    html += '</div>';
}
```

---

## Table Structure - What Gets Displayed

### **Table Header (Row):**

| Column | Purpose |
|--------|---------|
| **Project Name** | Name of the project (with filter dropdown) |
| **Paid To** | List of recipients (with filter dropdown) |
| **Payment Date** | Date of payment entry |
| **Grand Total** | Total amount (highlighted in green) |
| **Payment Mode** | Method of payment (cash, cheque, etc) |
| **Files** | Count of attached files (clickable) |
| **Actions** | View, Edit, Delete, Expand buttons |

---

## Data Row Example

### **Sample Entry Data Structure:**
```javascript
{
    payment_entry_id: 5,
    project_name: "ABC Project",
    project_type: "Construction",
    payment_date: "2025-11-20",
    grand_total: "50000.00",
    payment_mode: "cash",
    files_attached: 3,
    status: "pending",
    paid_to: [
        {
            name: "John Worker",
            type: "labour",
            amount: "15000.00",
            vendor_category: "skilled"
        },
        {
            name: "Steel Vendor",
            type: "vendor",
            amount: "20000.00",
            vendor_category: "material_steel"
        }
    ]
}
```

---

## Rendered Row HTML Example

```html
<div class="vendor-row">
    <!-- Project Name -->
    <div class="vendor-cell">ABC Project</div>
    
    <!-- Paid To List -->
    <div class="vendor-cell">
        <div class="paid-to-list">
            <div class="paid-to-item labour">👷 John Worker [skilled]</div>
            <div class="paid-to-item vendor">👤 Steel Vendor [material_steel]</div>
        </div>
    </div>
    
    <!-- Payment Date -->
    <div class="vendor-cell"><small>20/11/2025</small></div>
    
    <!-- Grand Total -->
    <div class="vendor-cell" style="font-weight: 700; color: #38a169;">₹50,000.00</div>
    
    <!-- Payment Mode -->
    <div class="vendor-cell">
        <small style="background: #f0f4f8; padding: 4px 8px; border-radius: 4px;">CASH</small>
    </div>
    
    <!-- Files Attached (Clickable) -->
    <div class="vendor-cell">
        <span onclick="openPaymentFilesModal(5)">
            <i class="fas fa-file"></i> 3
        </span>
    </div>
    
    <!-- Actions Buttons -->
    <div class="vendor-actions">
        <!-- Expand Button -->
        <button onclick="togglePaymentEntryExpand(5)" title="Expand Details">
            <i class="fas fa-chevron-down"></i>
        </button>
        
        <!-- View Button (Blue) -->
        <button onclick="viewPaymentEntry(5)" title="View Details">
            <i class="fas fa-eye"></i>
        </button>
        
        <!-- Edit Button (Orange) -->
        <button onclick="editPaymentEntry(5)">
            <i class="fas fa-edit"></i>
        </button>
        
        <!-- Delete Button (Red) -->
        <button onclick="deletePaymentEntry(5)">
            <i class="fas fa-trash"></i>
        </button>
    </div>
</div>

<!-- Expandable Details Section (Initially Hidden) -->
<div class="entry-details-container" id="entry-details-5" style="display: none;">
    <!-- Detailed information about the entry -->
</div>
```

---

## Expandable Details Section

When user clicks the **expand button** (chevron), detailed information is revealed:

### **Details Layout:**

```
┌─────────────────────────────────────────────────────────┐
│ PROJECT NAME     PROJECT TYPE    MAIN AMOUNT   DATE    │
│ ABC Project      Construction    ₹50,000      20/11   │
├─────────────────────────────────────────────────────────┤
│ PAID TO          TYPE      AMOUNT PAID    CATEGORY  [Proofs] │
│ John Worker      labour    ₹15,000.00     skilled   [Button] │
│ Steel Vendor     vendor    ₹20,000.00     material  [Button] │
└─────────────────────────────────────────────────────────┘
```

### **Details HTML Structure:**

```javascript
// Top row with main details
html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">';

html += '<div style="border-left: 3px solid #3182ce; padding: 8px 12px; background: white;">';
html += '<div style="font-size: 0.65em; font-weight: 700; text-transform: uppercase;">PROJECT NAME</div>';
html += `<div style="font-weight: 700; color: #1a365d;">${entry.project_name}</div>`;
html += '</div>';

html += '<div style="border-left: 3px solid #38a169; padding: 8px 12px; background: white;">';
html += '<div style="font-size: 0.65em; font-weight: 700; text-transform: uppercase;">PROJECT TYPE</div>';
html += `<div style="font-weight: 700; color: #276749;">${entry.project_type}</div>`;
html += '</div>';

// ... more detail items

html += '</div>';

// Paid To section
entry.paid_to.forEach((recipient, index) => {
    html += '<div style="display: grid; grid-template-columns: 1fr 0.8fr 1.2fr 0.9fr 0.7fr; gap: 10px;">';
    
    html += `<div style="border-left: 3px solid #6b5b95;">
                <div style="font-size: 0.6em; font-weight: 700;">PAID TO</div>
                <div>${recipient.name}</div>
            </div>`;
    
    // ... more recipient details
    
    html += `<button onclick="openPaymentFilesModal(${entry.payment_entry_id})">
                <i class="fas fa-paperclip"></i> Proofs
            </button>`;
    
    html += '</div>';
});
```

---

## Action Buttons

### **1. Expand Button (Chevron)**
```javascript
togglePaymentEntryExpand(entryId)
```
- **Purpose:** Show/hide detailed entry information
- **Visual:** Chevron icon rotates 180°
- **State:** Toggled between display: block and display: none

### **2. View Button (Eye Icon - Blue)**
```javascript
viewPaymentEntry(entryId)
```
- **Purpose:** Open detailed payment entry modal
- **Calls:** `openPaymentEntryDetailsModal(entryId)`
- **Color:** #3182ce (blue)

### **3. Edit Button (Pencil Icon - Orange)**
```javascript
editPaymentEntry(entryId)
```
- **Purpose:** Edit the payment entry
- **Current:** Shows alert "Edit payment entry for ID"
- **TODO:** Open payment entry edit modal
- **Color:** #d69e2e (orange)

### **4. Delete Button (Trash Icon - Red)**
```javascript
deletePaymentEntry(entryId)
```
- **Purpose:** Delete the payment entry with confirmation
- **Process:**
  1. Show confirmation dialog
  2. If confirmed, send DELETE request to `delete_payment_entry.php`
  3. Reload entries on success
- **Color:** #e53e3e (red)

---

## Files Attached Column

### **Display:**
```
📄 3    ← Clickable
```

Shows number of files attached to the entry.

### **On Click:**
```javascript
onclick="openPaymentFilesModal(entryId)"
```

Opens **"Payment Entry Files Registry Modal"** which shows:
- All attached files (proof images, acceptance method media, line item media)
- File details (name, size, date, type)
- Download/view options
- Verification status

---

## Filters Section

### **1. Date Range Filters (Header)**

```
[📅 From Date] [📅 To Date] [✓ Apply] [✕ Reset]
```

**Functions:**
```javascript
// Apply filter
applyRecordsFilterBtn.addEventListener('click', function() {
    const dateFrom = recordsDateFromInput.value;
    const dateTo = recordsDateToInput.value;
    
    if (dateFrom && dateTo && dateFrom > dateTo) {
        alert('From Date cannot be after To Date');
        return;
    }
    
    // Reload data with date filters
    loadPaymentEntries(10, 1, '', '', dateFrom, dateTo);
});

// Reset filter
resetRecordsFilterBtn.addEventListener('click', function() {
    recordsDateFromInput.value = '';
    recordsDateToInput.value = '';
    loadPaymentEntries(10, 1, '', '', '', '');
});
```

### **2. Project Name Filter (Dropdown)**

```
🔽 Project Name [⊙ Filter]
  ☐ All Projects
  ☐ Architecture
  ☐ Interior
  ☐ Construction
```

**Behavior:**
- Dropdown toggles visibility on button click
- User selects project type
- Reloads entries with selected project filter

### **3. Paid To Filter (Dropdown)**

```
🔽 Paid To [⊙ Filter]
  ☐ All Categories
  ☐ Labour
  ☐ Material Steel
  ☐ Supplier Cement
  ... (dynamically populated)
```

**Behavior:**
- Fetches vendor categories from `get_vendor_categories.php`
- Populates dropdown dynamically
- Filters entries by selected category

---

## Pagination

**When more than 10 records exist:**

```
Showing 1 to 10 of 50 records
[← Previous] [1] [2] [3] [4] [5] [Next →]
```

**JavaScript:**
```javascript
if (data.pagination.totalPages > 1) {
    // Build pagination HTML
    html += '<div class="pagination-container">';
    html += `<div class="pagination-info">Showing... records</div>`;
    
    // Previous button
    html += `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''}>← Previous</button>`;
    
    // Page numbers
    for (let i = 1; i <= data.pagination.totalPages; i++) {
        html += `<button class="pagination-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    
    // Next button
    html += `<button class="pagination-btn" ${page === totalPages ? 'disabled' : ''}>Next →</button>`;
    html += '</div>';
}

// Add click handlers
document.querySelectorAll('.pagination-btn[data-page]').forEach(btn => {
    btn.addEventListener('click', function() {
        const pageNum = parseInt(this.getAttribute('data-page'));
        loadPaymentEntries(10, pageNum, '', '', '', '');
    });
});
```

---

## Data Flow Diagram

```
┌──────────────────────────────────────┐
│  User Opens Dashboard (Page Load)    │
└────────────────┬─────────────────────┘
                 │
                 ↓
    ┌─────────────────────────────┐
    │ loadPaymentEntries(10,1)    │
    │ limit: 10                   │
    │ page: 1                     │
    │ (No filters)                │
    └─────────────┬───────────────┘
                  │
                  ↓
    ┌──────────────────────────────────┐
    │ Build Query Parameters:          │
    │ ?limit=10&offset=0&dateFrom=...  │
    └─────────────┬────────────────────┘
                  │
                  ↓
    ┌──────────────────────────────────┐
    │ Fetch from:                      │
    │ get_payment_entries.php          │
    └─────────────┬────────────────────┘
                  │
                  ↓
    ┌──────────────────────────────────┐
    │ API Returns:                     │
    │ {                               │
    │   success: true,                │
    │   data: [{...}, {...}],         │
    │   pagination: {...}             │
    │ }                               │
    └─────────────┬────────────────────┘
                  │
                  ↓
    ┌──────────────────────────────────┐
    │ Build HTML:                      │
    │ - Table header                  │
    │ - Data rows                     │
    │ - Pagination buttons            │
    │ - Filter dropdowns              │
    └─────────────┬────────────────────┘
                  │
                  ↓
    ┌──────────────────────────────────┐
    │ Insert HTML into                │
    │ #entriesContainer               │
    └──────────────────────────────────┘
```

---

## User Interactions

### **Scenario 1: Filter by Date**
```
User enters: From Date = 2025-11-01, To Date = 2025-11-30
User clicks: Apply Filter
↓
loadPaymentEntries(10, 1, '', '', '2025-11-01', '2025-11-30')
↓
API returns only entries between those dates
↓
Table refreshes with filtered data
```

### **Scenario 2: View Entry Details**
```
User clicks: Eye icon on a row
↓
viewPaymentEntry(entryId)
↓
openPaymentEntryDetailsModal(entryId)
↓
Modal opens showing complete entry details
```

### **Scenario 3: Expand Entry**
```
User clicks: Chevron down icon
↓
togglePaymentEntryExpand(entryId)
↓
entry-details-{entryId} div changes display from 'none' to 'grid'
↓
Detailed information slides into view
↓
Chevron rotates 180°
```

### **Scenario 4: View Attached Files**
```
User clicks: "📄 3" (Files count)
↓
openPaymentFilesModal(entryId)
↓
Payment Entry Files Registry Modal opens
↓
Shows all attached files with details
```

### **Scenario 5: Delete Entry**
```
User clicks: Trash icon
↓
deletePaymentEntry(entryId)
↓
Confirmation dialog appears
↓
User confirms
↓
DELETE request sent to delete_payment_entry.php
↓
If success:
   - Show success alert
   - Reload entries with current filters
↓
If error:
   - Show error alert with message
```

---

## Styling & Appearance

### **Color Scheme:**

| Element | Color | Hex | Purpose |
|---------|-------|-----|---------|
| Header | Dark Blue | #2a4365 | Primary color |
| Grand Total | Green | #38a169 | Highlight important amount |
| View Button | Blue | #3182ce | Friendly action |
| Edit Button | Orange | #d69e2e | Caution action |
| Delete Button | Red | #e53e3e | Dangerous action |
| Expand Button | Gray | #718096 | Neutral action |

### **Responsive Design:**

- **Desktop:** Full table layout with all columns visible
- **Tablet:** Auto-wrap columns, adjusted spacing
- **Mobile:** Stack layout, compact buttons

---

## Summary - What Recently Added Records Does

**Main Function:** Display recent payment entries in a filterable, paginated table with inline details and actions.

**Key Features:**
1. ✅ Auto-loads on page load
2. ✅ Date range filtering
3. ✅ Project type filtering
4. ✅ Vendor category filtering
5. ✅ Pagination for large datasets
6. ✅ Expandable row details
7. ✅ Inline actions (View, Edit, Delete)
8. ✅ File attachment viewing
9. ✅ Status indicators
10. ✅ Amount highlighting

**Data Source:** `get_payment_entries.php` API endpoint

**Associated Modals:**
- Payment Entry Details Modal (View)
- Payment Entry Files Registry Modal (View Files)
- Payment Entry Edit Modal (Edit - TODO)
