# Expand Toggle Button - Entry Details

## Toggle Function

```javascript
function togglePaymentEntryExpand(entryId) {
    const detailsContainer = document.getElementById(`entry-details-${entryId}`);
    const expandBtn = event.target.closest('.expand-btn');
    
    if (detailsContainer) {
        const isHidden = detailsContainer.style.display === 'none';
        
        if (isHidden) {
            // Expand
            detailsContainer.style.display = 'grid';
            expandBtn.style.transform = 'rotate(180deg)';
        } else {
            // Collapse
            detailsContainer.style.display = 'none';
            expandBtn.style.transform = 'rotate(0deg)';
        }
    }
}
```

**What it does:**
- Toggles `display: none/grid` on `entry-details-{entryId}` div
- Rotates chevron icon 180° when expanded

---

## Button HTML

```html
<button class="expand-btn" onclick="togglePaymentEntryExpand(5)">
    <i class="fas fa-chevron-down"></i>
</button>
```

---

## Details Shown When Expanded

### **Top Row - Main Details:**
```
┌─────────────────────────────────────────────────────────┐
│ PROJECT NAME  │ PROJECT TYPE │ MAIN AMOUNT │ PAYMENT DATE │
│ ABC Project   │ Construction │ ₹50,000     │ 20/11/2025   │
└─────────────────────────────────────────────────────────┘
```

### **For Each Recipient/Paid To:**
```
┌────────────────────────────────────────────────────────────┐
│ PAID TO     │ TYPE   │ AMOUNT PAID  │ CATEGORY   │ [Proofs] │
│ John Worker │ labour │ ₹15,000      │ skilled    │ Button   │
│ Vendor XYZ  │ vendor │ ₹20,000      │ material   │ Button   │
└────────────────────────────────────────────────────────────┘
```

### **Bottom - Payment Mode:**
```
┌────────────────────────────┐
│ PAYMENT MODE               │
│ Cash                       │
└────────────────────────────┘
```

---

## HTML Structure

```html
<!-- Main row with all action buttons (always visible) -->
<div class="vendor-row">
    <div>Project Name</div>
    <div>Paid To List</div>
    <div>Payment Date</div>
    <div>Grand Total</div>
    <div>Payment Mode</div>
    <div>Files Count</div>
    <div class="vendor-actions">
        <!-- Expand button here -->
        <button onclick="togglePaymentEntryExpand(5)">⋯</button>
        <!-- View, Edit, Delete buttons -->
    </div>
</div>

<!-- Details container (hidden by default) -->
<div id="entry-details-5" style="display: none; grid-column: 1 / -1; background: #f9fafb;">
    <!-- Main details in colored boxes -->
    <div>PROJECT NAME: ABC Project</div>
    <div>PROJECT TYPE: Construction</div>
    <div>MAIN AMOUNT: ₹50,000</div>
    <div>PAYMENT DATE: 20/11/2025</div>
    
    <!-- Recipients details -->
    <div>PAID TO: John Worker | TYPE: labour | AMOUNT: ₹15,000 | [Proofs]</div>
    <div>PAID TO: Vendor XYZ | TYPE: vendor | AMOUNT: ₹20,000 | [Proofs]</div>
    
    <!-- Payment mode -->
    <div>PAYMENT MODE: Cash</div>
</div>
```

---

## Data Fields Shown

| Field | Source |
|-------|--------|
| Project Name | `entry.project_name` |
| Project Type | `entry.project_type` |
| Main Amount | `entry.grand_total` |
| Payment Date | `entry.payment_date` |
| Paid To Name | `recipient.name` |
| Recipient Type | `recipient.type` (labour/vendor) |
| Amount Paid | `recipient.amount` |
| Category | `recipient.vendor_category` |
| Payment Mode | `entry.payment_mode` |

---

## Visual Behavior

**Before Click:**
```
⋮ [View] [Edit] [Delete]    ← Chevron pointing down
```

**After Click:**
```
⋯ [View] [Edit] [Delete]    ← Chevron pointing up (rotated 180°)

Details Section:
┌─────────────────────────────────────────┐
│ PROJECT NAME | PROJECT TYPE | AMOUNT... │
│ Additional recipient details...         │
└─────────────────────────────────────────┘
```

**Styling:**
- Container: `background: #f9fafb; border-top: 1px solid #e2e8f0; padding: 16px`
- Items: `border-left: 3px solid [color]; padding: 8px; background: white`
- Each color represents different field type
