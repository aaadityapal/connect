# Recently Added Records Section - Complete Explanation

## Overview
The **"Recently Added Records"** section is a sophisticated payment entry display component that shows all recently added payment entries with expandable details, filtering, pagination, and interactive features.

---

## 1. HTML Structure

```html
<!-- Recently Added Records Section -->
<div class="recent-records-section">
    
    <!-- Header Container -->
    <div class="recent-records-header-container">
        
        <!-- Title with Toggle Button -->
        <div class="recent-records-title-wrapper">
            <button class="recent-records-toggle-btn" id="recentRecordsToggleBtn">
                <i class="fas fa-chevron-down"></i>
            </button>
            <h3 class="recent-records-header">Recently Added Records</h3>
        </div>
        
        <!-- Minimalist Date Filter -->
        <div class="records-date-filter-minimal">
            <input type="date" id="recordsDateFrom" class="mini-date-input">
            <input type="date" id="recordsDateTo" class="mini-date-input">
            <button class="mini-filter-btn apply" id="applyRecordsFilterBtn">
                <i class="fas fa-check"></i>
            </button>
            <button class="mini-filter-btn reset" id="resetRecordsFilterBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tabs-container">
        <button class="tab-btn active" data-tab="entries-tab">
            <i class="fas fa-receipt"></i>Recent Entries
        </button>
    </div>

    <!-- Tab Content - Payment Entries -->
    <div class="tab-content active" id="entries-tab">
        <div id="entriesContainer">
            <!-- Dynamically populated by loadPaymentEntries() -->
        </div>
    </div>
</div>
```

---

## 2. Data Loading Flow

### **Step 1: Initialize on Page Load**
When the dashboard loads, it automatically calls:
```javascript
loadPaymentEntries(10, 1); // Loads first page with 10 entries
```

### **Step 2: API Request**
The function makes a fetch request to:
```
GET /get_payment_entries.php?limit=10&offset=0&search=&status=&dateFrom=&dateTo=&projectType=&vendorCategory=&paidBy=
```

**Parameters Sent**:
| Parameter | Default | Purpose |
|-----------|---------|---------|
| `limit` | 10 | Records per page |
| `offset` | 0 | Starting position for pagination |
| `search` | '' | Search term in payment entries |
| `status` | '' | Filter by status (draft/submitted/pending/approved/rejected) |
| `dateFrom` | '' | Filter from date |
| `dateTo` | '' | Filter to date |
| `projectType` | '' | Filter by project type (Architecture/Interior/Construction) |
| `vendorCategory` | '' | Filter by vendor category |
| `paidBy` | '' | Filter by user who made payment |

### **Step 3: API Response Structure**
```json
{
  "success": true,
  "data": [
    {
      "payment_entry_id": 1,
      "project_name": "Office Renovation",
      "project_type": "interior",
      "grand_total": 50000,
      "payment_date": "2025-11-24",
      "status": "approved",
      "payment_mode": "bank_transfer",
      "authorized_by": "John Doe",
      "files_attached": 3,
      "paid_to": [
        {
          "name": "ABC Contractors",
          "type": "vendor",
          "category": "Material Supplier",
          "vendor_category": "Material Supplier",
          "amount": 30000,
          "acceptance_methods": ["bank_transfer"],
          "paid_by_user": "John Doe"
        },
        {
          "name": "Rajesh Kumar",
          "type": "labour",
          "category": "Skilled Labour",
          "vendor_category": null,
          "amount": 20000,
          "acceptance_methods": ["cash"],
          "paid_by_user": "John Doe"
        }
      ]
    }
  ],
  "pagination": {
    "currentPage": 1,
    "totalPages": 5,
    "total": 45,
    "limit": 10
  }
}
```

---

## 3. Entry Display Structure

Each payment entry is rendered as a **card with expandable details**:

### **A. Main Row (Always Visible)**
```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Project Name | Paid To | Auth User | Payment Date | Grand Total | Status    │
├─────────────────────────────────────────────────────────────────────────────┤
│             │         │           │              │             │           │
│Office        │👤 ABC   │John Doe   │24-11-2025    │₹50,000      │APPROVED  │
│Renovation    │Contr.   │           │              │             │           │
│             │[Supplier]│           │              │             │           │
│             │          │           │              │             │           │
│             │👷 Rajesh │           │              │             │           │
│             │[Labour]  │           │              │             │           │
│             │          │           │              │             │           │
├─────────────────────────────────────────────────────────────────────────────┤
│ Payment Mode │ Files │ Actions                                                │
├─────────────────────────────────────────────────────────────────────────────┤
│BANK TRANSFER │ [📄 3]│ [↓] [👁] [✎] [🗑]                                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Columns in Main Row**:
1. **Project Name**: Name of the project (Office Renovation, House Renovation, etc.)
2. **Paid To**: List of recipients (Vendors or Labour) with emoji badges and categories
3. **Authorized By**: User who authorized the payment
4. **Payment Date**: Date when payment was made (formatted as DD-MM-YYYY)
5. **Grand Total**: Total amount with ₹ symbol, bold green text
6. **Status**: Status badge (DRAFT/SUBMITTED/PENDING/APPROVED/REJECTED)
7. **Payment Mode**: How payment was made (CASH, CHEQUE, BANK TRANSFER, etc.)
8. **Files**: Number of attached files with clickable button
9. **Actions**: 
   - ↓ (Expand/Collapse) - Shows more details
   - 👁 (View) - Opens details modal
   - ✎ (Edit) - Edit the entry
   - 🗑 (Delete) - Delete the entry

---

### **B. Expandable Details Section (Hidden by Default)**

When user clicks the **expand button (↓)**, the following details appear:

#### **Top Cards Section**:
```
┌──────────────┬──────────────┬──────────────┬──────────────┬──────────────┐
│ PROJECT NAME │ PROJECT TYPE │ MAIN AMOUNT  │ PAYMENT DATE │   STATUS     │
├──────────────┼──────────────┼──────────────┼──────────────┼──────────────┤
│ Office       │ Interior     │ ₹50,000      │ 24-11-2025   │ APPROVED     │
│ Renovation   │              │              │              │              │
└──────────────┴──────────────┴──────────────┴──────────────┴──────────────┘
```

**Display Format**:
- Each field has a left border with color
- Field name in UPPERCASE, small text, uppercase letters
- Value below in bold, larger text
- Each card has white background with subtle shadow

#### **Paid To Recipients Section** (for each recipient):
```
┌──────────────┬──────────┬──────────────┬──────────────┬──────────────┐
│  PAID TO     │  TYPE    │ AMOUNT PAID  │  CATEGORY    │ PAYMENT MODE │
├──────────────┼──────────┼──────────────┼──────────────┼──────────────┤
│ ABC Contr.   │ VENDOR   │ ₹30,000      │ Material     │ BANK         │
│              │          │              │ Supplier     │ TRANSFER     │
├──────────────┼──────────┼──────────────┼──────────────┼──────────────┤
│ PAID BY USER │ [📎 Proofs Button]                                     │
├──────────────┴──────────┴──────────────┴──────────────┴──────────────┤
```

**For each recipient, displays**:
- PAID TO: Name of vendor/labour
- TYPE: vendor or labour
- AMOUNT PAID: Amount given to this recipient
- CATEGORY: Vendor category (Material Supplier, Labour Contractor, etc.)
- PAYMENT MODE: How this recipient was paid (Cash, Cheque, Bank Transfer, etc.)
  - If multiple acceptance methods: Shows all methods separated by commas
- PAID BY: User who authorized this specific payment
- **Proofs Button**: Yellow button to view files attached to this payment

---

## 4. JavaScript Function: `loadPaymentEntries()`

### **Function Signature**
```javascript
function loadPaymentEntries(
    limit = 10,           // Records per page
    page = 1,            // Current page number
    search = '',         // Search term
    status = '',         // Status filter
    dateFrom = '',       // From date
    dateTo = '',         // To date
    projectType = '',    // Project type filter
    vendorCategory = '', // Vendor category filter
    paidBy = ''         // User filter
)
```

### **Function Flow**

#### **Phase 1: State Management**
```javascript
// Update global state
entriesPaginationState = {
    currentPage: page,
    limit: limit,
    totalPages: 1,
    search: search,
    status: status,
    dateFrom: dateFrom,
    dateTo: dateTo,
    projectType: projectType,
    vendorCategory: vendorCategory,
    paidBy: paidBy
};
```

#### **Phase 2: Build HTML String**
```javascript
let html = '<div class="vendor-table-wrapper">';

// 1. Build Header Row with Filter Buttons
html += '<div class="vendor-row-header">';
// Project Name with filter dropdown
// Paid To with filter dropdown
// Payment Date
// Grand Total
// Status with filter dropdown
// Payment Mode
// Files
// Actions
html += '</div>';

// 2. For Each Entry in data.data
data.data.forEach(entry => {
    // Build main row
    // Build expandable details row
});

html += '</div>';
```

#### **Phase 3: Build Header Row with Excel-Style Filters**

**Column 1: Project Name with Filter**
```html
<div class="project-filter-container">
    <span>Project Name</span>
    <button class="project-filter-btn" id="projectFilterToggle" title="Filter by Project Type">
        <i class="fas fa-filter"></i>
    </button>
    <div class="project-filter-dropdown excel-filter-dropdown" id="projectFilterDropdown">
        <div class="excel-filter-header">
            <input type="text" class="excel-filter-search" placeholder="Search...">
            <div class="excel-filter-actions">
                <button class="excel-filter-apply-btn">Apply</button>
                <button class="excel-filter-clear-btn">Clear</button>
            </div>
        </div>
        <div class="excel-filter-list">
            <div class="filter-option" data-type="">All Projects</div>
            <div class="filter-option" data-type="Architecture">
                <input type="checkbox"> Architecture
            </div>
            <div class="filter-option" data-type="Interior">
                <input type="checkbox"> Interior
            </div>
            <div class="filter-option" data-type="Construction">
                <input type="checkbox"> Construction
            </div>
        </div>
    </div>
</div>
```

**Column 2: Paid To with Filter**
```html
<div class="project-filter-container">
    <span>Paid To</span>
    <button class="project-filter-btn" id="vendorCategoryFilterToggle">
        <i class="fas fa-filter"></i>
    </button>
    <div class="project-filter-dropdown excel-filter-dropdown" id="vendorCategoryFilterDropdown">
        <!-- Similar structure with vendor categories -->
    </div>
</div>
```

**Column 3: Paid By with Filter**
```html
<div class="project-filter-container">
    <span>Paid By</span>
    <button class="project-filter-btn" id="paidByFilterToggle">
        <i class="fas fa-filter"></i>
    </button>
    <div class="project-filter-dropdown excel-filter-dropdown" id="paidByFilterDropdown">
        <!-- Similar structure with users -->
    </div>
</div>
```

#### **Phase 4: Build Main Entry Row**

For each entry in the API response:

```javascript
html += '<div class="vendor-row">';

// 1. Project Name (simple text)
html += `<div class="vendor-cell">${entry.project_name}</div>`;

// 2. Paid To (list of recipients with badges)
let paidToHtml = '<div class="paid-to-list">';
if (entry.paid_to && entry.paid_to.length > 0) {
    entry.paid_to.forEach(recipient => {
        const emoji = recipient.type === 'vendor' ? '👤' : '👷';
        const category = recipient.vendor_category ? ` [${recipient.vendor_category}]` : '';
        paidToHtml += `<div class="paid-to-item ${recipient.type}">
            ${emoji} ${recipient.name}${category}
        </div>`;
    });
} else {
    paidToHtml += '<div class="paid-to-item">No data</div>';
}
paidToHtml += '</div>';
html += `<div class="vendor-cell">${paidToHtml}</div>`;

// 3. Authorized By (user name in colored badge)
html += `<div class="vendor-cell">
    <small style="background: #e0e7ff; padding: 4px 8px; border-radius: 4px; 
                   display: inline-block; color: #3730a3; font-weight: 600;">
        ${entry.authorized_by || 'N/A'}
    </small>
</div>`;

// 4. Payment Date (formatted DD-MM-YYYY)
const paymentDate = entry.payment_date ? 
    new Date(entry.payment_date).toLocaleDateString('en-GB', {
        year: 'numeric', month: '2-digit', day: '2-digit'
    }) : 'N/A';
html += `<div class="vendor-cell"><small>${paymentDate}</small></div>`;

// 5. Grand Total (bold green text with ₹ symbol)
const grandTotal = '₹' + parseFloat(entry.grand_total).toFixed(2);
html += `<div class="vendor-cell" style="font-weight: 700; color: #38a169; font-size: 0.95em;">
    ${grandTotal}
</div>`;

// 6. Status (colored badge)
const statusClass = entry.status.toLowerCase();
html += `<div class="vendor-cell">
    <span class="vendor-status ${statusClass}">
        ${entry.status.toUpperCase()}
    </span>
</div>`;

// 7. Payment Mode (uppercase text in badge)
html += `<div class="vendor-cell">
    <small style="background: #f0f4f8; padding: 4px 8px; border-radius: 4px;">
        ${entry.payment_mode.replace(/_/g, ' ').toUpperCase()}
    </small>
</div>`;

// 8. Files Count (clickable button to view files)
html += `<div class="vendor-cell">
    <span style="background: #edf2f7; color: #2a4365; padding: 6px 10px; 
                  border-radius: 4px; font-size: 0.85em; font-weight: 600; 
                  cursor: pointer;" onclick="openPaymentFilesModal(${entry.payment_entry_id})">
        <i class="fas fa-file"></i> ${entry.files_attached}
    </span>
</div>`;

// 9. Action Buttons
html += '<div class="vendor-actions">';

// Expand button
html += `<button class="expand-btn" onclick="togglePaymentEntryExpand(${entry.payment_entry_id})">
    <i class="fas fa-chevron-down"></i>
</button>`;

// View button
html += `<button class="view-btn" onclick="viewPaymentEntry(${entry.payment_entry_id})">
    <i class="fas fa-eye"></i>
</button>`;

// Edit button
html += `<button class="edit-btn" onclick="editPaymentEntry(${entry.payment_entry_id})">
    <i class="fas fa-edit"></i>
</button>`;

// Delete button
html += `<button class="delete-btn" onclick="deletePaymentEntry(${entry.payment_entry_id})">
    <i class="fas fa-trash"></i>
</button>`;

html += '</div>';
html += '</div>';
```

#### **Phase 5: Build Expandable Details Section**

```javascript
html += `<div class="entry-details-container" id="entry-details-${entry.payment_entry_id}" 
        style="display: none;">`;

// Top Cards (Project Name, Type, Amount, Date, Status)
html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">';

// Project Name Card
html += '<div style="border-left: 3px solid #3182ce; padding: 8px 12px; background: white;">';
html += '<div style="font-size: 0.65em; color: #2a4365; font-weight: 700; text-transform: uppercase;">PROJECT NAME</div>';
html += `<div style="font-weight: 700; color: #1a365d; font-size: 0.9em;">${entry.project_name}</div>`;
html += '</div>';

// Similar for Project Type, Main Amount, Payment Date, Status
// ...

html += '</div>';

// Recipient Details Section
if (entry.paid_to && entry.paid_to.length > 0) {
    entry.paid_to.forEach((recipient, index) => {
        html += '<div style="display: grid; grid-template-columns: 1fr 0.8fr 1.2fr 0.9fr 0.8fr 0.9fr 0.7fr; gap: 12px;">';
        
        // For each recipient: Paid To, Type, Amount, Category, Payment Mode, Paid By, Proofs Button
        // ...
        
        html += '</div>';
    });
}

html += '</div>';
```

---

## 5. Key Features & Interactions

### **A. Expand/Collapse Entries**
- Click the **down chevron (↓)** button in actions column
- Reveals detailed information about payment
- Rotates chevron to indicate expanded state
- Can expand/collapse multiple entries independently

### **B. Excel-Style Filtering**
Each column header has a filter icon that opens a dropdown with:
- **Search box**: Filter options by typing
- **Checkboxes**: Select multiple options
- **Apply button**: Apply selected filters
- **Clear button**: Clear all selections

**Filter Columns**:
1. **Project Type**: Architecture, Interior, Construction
2. **Paid To**: Vendor categories and labour types
3. **Paid By**: Users who made payments

### **C. Action Buttons**
- **Expand (↓)**: Reveals expandable details below
- **View (👁)**: Opens payment entry details modal
- **Edit (✎)**: Opens edit modal for the entry
- **Delete (🗑)**: Deletes the payment entry with confirmation

### **D. Files Counter**
- Shows number of files attached to payment entry
- Clickable to open **Payment Entry Files Registry Modal**
- Lists all attachments with download links

### **E. Pagination**
- Shows: "Page X of Y (Total: Z entries)"
- Previous/Next buttons (disabled at boundaries)
- Direct page number buttons
- Ellipsis (...) when there are gaps
- All pagination buttons preserve filter state

### **F. Date Filter (Header)**
- **From Date** input and **To Date** input
- Apply button: Filters entries within date range
- Reset button: Clears date filter
- Located in the header for quick access

---

## 6. Data Displayed Per Entry

### **Main Row Data**:
```
✓ Project Name
✓ Paid To (list with emoji and category)
✓ Authorized By (user name)
✓ Payment Date (formatted)
✓ Grand Total (amount)
✓ Status
✓ Payment Mode
✓ File Count
✓ Actions (4 buttons)
```

### **Expandable Details**:
```
✓ Project Name (card)
✓ Project Type (card)
✓ Main Amount (card)
✓ Payment Date (card)
✓ Status (card)
✓ For Each Recipient:
  - Paid To Name
  - Type (vendor/labour)
  - Amount Paid
  - Category
  - Payment Mode(s)
  - Paid By User
  - Proofs Button
```

---

## 7. Color Scheme

| Element | Color | RGB/Hex |
|---------|-------|---------|
| Project Name Border | Blue | #3182ce |
| Project Type Border | Green | #38a169 |
| Main Amount Border | Orange | #d69e2e |
| Payment Date Border | Pink | #d53f8c |
| Status Border | Purple | #9333ea |
| Paid To Border | Dark Purple | #6b5b95 |
| Type Border | Cyan | #0284c7 |
| Amount Paid Border | Green | #16a34a |
| Category Border | Pink | #d53f8c |
| Payment Mode Border | Orange | #ea580c |
| Paid By Border | Purple | #7c3aed |

---

## 8. Status Badges

| Status | Background | Text Color |
|--------|-----------|-----------|
| draft | #e0e7ff | #3730a3 |
| submitted | #fef3c7 | #92400e |
| pending | #dbeafe | #0c4a6e |
| approved | #dcfce7 | #166534 |
| rejected | #fee2e2 | #991b1b |

---

## 9. Empty States

**When no entries found**:
```
┌─────────────────────────┐
│   <i class="fas fa-receipt"></i>   │
│ No payment entries yet  │
│                         │
└─────────────────────────┘
```

**When loading**:
```
┌─────────────────────────┐
│   <spinner spinning>    │
│ Loading payment entries │
└─────────────────────────┘
```

**When error occurs**:
```
┌─────────────────────────┐
│  <exclamation icon>     │
│ Error loading entries   │
│ Please try again        │
└─────────────────────────┘
```

---

## 10. Important Functions

### **Load Function**
```javascript
loadPaymentEntries(limit, page, search, status, dateFrom, dateTo, projectType, vendorCategory, paidBy)
```
Loads and displays payment entries with filters and pagination

### **Toggle Expand Function**
```javascript
togglePaymentEntryExpand(paymentEntryId)
```
Shows/hides the expandable details section for an entry

### **View Function**
```javascript
viewPaymentEntry(paymentEntryId)
```
Opens the payment entry details modal

### **Files Modal Function**
```javascript
openPaymentFilesModal(paymentEntryId)
```
Opens modal showing all files attached to the payment entry

### **Recipient Files Function**
```javascript
openRecipientFilesModal(paymentEntryId, recipientIndex, recipientData)
```
Opens modal showing files for a specific recipient within a payment entry

---

## 11. Event Listeners

### **On Load**
- Auto-loads first page of payment entries
- Auto-initializes expand button icons
- Sets up filter dropdown listeners

### **Filter Interactions**
- Project Type filter: Checkbox selection, search, apply/clear
- Paid To filter: Checkbox selection, search, apply/clear
- Paid By filter: Checkbox selection, search, apply/clear
- Date filter: Apply/reset buttons

### **Pagination**
- Page buttons trigger loadPaymentEntries with filters preserved
- Filter dropdowns close on apply
- All filter state persists across page changes

---

## 12. Data Flow Diagram

```
Page Load
    ↓
loadPaymentEntries() called
    ↓
fetch GET /get_payment_entries.php
    ↓
Parse JSON response
    ↓
Build HTML string
    ├─ Header row with filter buttons
    ├─ For each entry:
    │  ├─ Main row (project, recipients, date, amount, etc.)
    │  └─ Expandable details (collapsible)
    └─ Pagination buttons
    ↓
Insert HTML into entriesContainer
    ↓
Initialize event listeners
    └─ Expand buttons
    └─ Filter toggles
    └─ Pagination clicks
    └─ Action buttons
```

---

## 13. Critical Notes

⚠️ **Important Behaviors**:
1. **Filter Persistence**: When applying filters, pagination resets to page 1
2. **Expand State**: Expanding an entry doesn't affect others
3. **File Counts**: Shows total files attached to payment entry
4. **Recipient Grouping**: Shows all recipients paid from single payment entry
5. **Amount Validation**: Each recipient's amount is part of the grand total
6. **Status Flow**: Entries progress from Draft → Submitted → Pending → Approved/Rejected

📌 **Performance Notes**:
- Loads 10 entries per page by default
- Uses pagination to handle large datasets
- Filters reduce data on server side
- HTML string building is efficient for large datasets

🔗 **Related Modals**:
- `payment_entry_details_modal.php` - View full details
- `payment_entry_files_registry_modal.php` - View all files
- `recipient_files_modal.php` - View recipient-specific files

---

## 14. Example Usage

```javascript
// Load all entries (first page)
loadPaymentEntries();

// Load with filters
loadPaymentEntries(
    10,                           // limit
    1,                           // page
    '',                          // search
    'approved',                  // status
    '2025-11-01',               // dateFrom
    '2025-11-30',               // dateTo
    'architecture',             // projectType
    'Material Supplier',        // vendorCategory
    'John Doe'                  // paidBy
);

// Expand an entry
togglePaymentEntryExpand(5);

// View entry details
viewPaymentEntry(5);

// View files
openPaymentFilesModal(5);

// View recipient files
openRecipientFilesModal(5, 0, recipientObject);
```

---

This section provides a complete, interactive view of all payment entries with filtering, pagination, and detailed drill-down capabilities!
