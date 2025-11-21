# Add More Entry Section - Detailed Explanation

## Overview
The "Add More Entry" section allows users to add **multiple line items** to a single payment entry. Each line item represents a separate payment to a recipient (Labour, Vendor, Supplier, etc.).

---

## Visual Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                    Entry #1                              [❌]    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ Type         │  │ To (Recipient)│  │ For          │         │
│  │ (Dropdown)   │  │ (Dropdown)   │  │ (Textarea)   │         │
│  │              │  │              │  │              │         │
│  │ - Labour     │  │ - Name 1     │  │ Foundation   │         │
│  │ - Material   │  │ - Name 2     │  │ work labour  │         │
│  │ - Supplier   │  │ - Name 3     │  │              │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐                            │
│  │ Amount       │  │ Payment Mode │                            │
│  │ (₹)          │  │ (Dropdown)   │                            │
│  │              │  │              │                            │
│  │ 15000        │  │ - Cash       │                            │
│  │              │  │ - Cheque     │                            │
│  │              │  │ - Multiple   │                            │
│  │              │  │ - Online     │                            │
│  └──────────────┘  └──────────────┘                            │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ [📎 Attach File]  ✓ invoice.pdf (245.50 KB)            │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Entry Amount:    ₹15,000.00                             │   │
│  │ Accepted Amount: ₹15,000.00                             │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

[+ Add More Entry]
```

---

## 5 Main Form Fields

### 1️⃣ **Type (Required)**
**Purpose:** Categorize what the payment is for

**Available Options:**
```
- Labour                    → Unskilled labour workers
- Labour Skilled            → Specialized/skilled workers
- Material Steel            → Steel material suppliers
- Material Bricks           → Brick material suppliers
- Supplier Cement           → Cement suppliers
- Supplier Sand Aggregate   → Sand & aggregate suppliers
```

**UI:**
- Type: `<select>` dropdown (required field marked with *)
- Icon: 🏷️ (Tag icon)
- Default: "Select Type"

**Behavior:**
```javascript
// When user selects a type, recipients dynamically load
typeSelect.addEventListener('change', function() {
    loadRecipientsByTypeForEntry(this.value, recipientSelect);
});
```

---

### 2️⃣ **To / Recipient (Required)**
**Purpose:** Select WHO receives this payment

**Dynamic Loading:**
Based on the selected "Type", the system calls an API endpoint to fetch matching recipients:

```
Type Selected → API Call → Recipients Loaded

Labour → /handlers/get_labour_recipients.php?type=labour
         ↓
         Fetches all labour workers from database
         ↓
         Returns: [
            {id: 1, name: "John Worker"},
            {id: 2, name: "Mike Labor"},
            {id: 3, name: "Ram Singh"}
         ]

Material Steel → /handlers/get_vendor_recipients.php?type=material_steel
                 ↓
                 Fetches all steel material vendors
                 ↓
                 Returns: [
                    {id: 10, name: "Steel Corp India"},
                    {id: 11, name: "Metro Steel"}
                 ]
```

**UI:**
- Type: `<select>` dropdown (required field marked with *)
- Icon: 👤 (User icon)
- Default: "Loading..." → Changes to "Select Recipient" or "No recipients found"
- Disabled: Until Type is selected

**Code:**
```javascript
function loadRecipientsByTypeForEntry(type, recipientSelect) {
    let endpoint = '';
    
    if (type === 'labour') {
        endpoint = 'get_labour_recipients.php?type=labour';
    } else if (type === 'material_steel') {
        endpoint = 'get_vendor_recipients.php?type=material_steel';
    }
    // ... etc
    
    fetch(endpoint)
        .then(response => response.json())
        .then(data => {
            // Populate dropdown with recipients
        });
}
```

---

### 3️⃣ **For / Description (Optional)**
**Purpose:** Describe what the payment is for

**UI:**
- Type: `<textarea>` (small, multi-line text box)
- Icon: 📋 (Clipboard icon)
- Placeholder: "Describe what this payment is for..."
- Max height: ~50px (can expand)
- NOT required (no asterisk)

**Examples:**
```
"Foundation work labour for Phase 2"
"Steel rods delivery - 50 tons"
"Cement supply - 100 bags"
"Labour - 15 days work"
```

**Storage:**
Saved as `payment_description_notes` in database

---

### 4️⃣ **Amount (Required)**
**Purpose:** How much to pay for this entry

**UI:**
- Type: `<input type="number">` 
- Icon: ₹ (Rupee sign)
- Step: 0.01 (allows decimal values)
- Min: 0
- Placeholder: "0.00"
- Example input: `15000`, `5000.50`, `10000`

**Validation:**
- Must be > 0
- Cannot be empty
- Only numeric values allowed

**Features:**
- Real-time calculation if using "Multiple Acceptance" payment mode
- Updates "Entry Amount" display
- Compared with "Accepted Amount" to show mismatch warning

---

### 5️⃣ **Payment Mode (Required)**
**Purpose:** How is this line item being paid?

**Available Options:**
```
Split Payment         → Payment split across multiple items
Multiple Acceptance   → Paid via multiple methods (Cash + Cheque, etc.)
Cash                  → Direct cash payment
Cheque                → Cheque payment
Bank Transfer         → Bank transfer
Credit Card           → Credit card payment
Online Payment        → Online/Digital payment
UPI                   → UPI payment
```

**UI:**
- Type: `<select>` dropdown (required field marked with *)
- Icon: 💳 (Credit card icon)
- Default: "Select Payment Method"

**Special Behavior - "Multiple Acceptance" Mode:**
If user selects **"Multiple Acceptance"**, a hidden section reveals:

```
┌─────────────────────────────────────────────────┐
│ MULTIPLE ACCEPTANCE SECTION (appears)           │
├─────────────────────────────────────────────────┤
│                                                 │
│ Payment Method  │ Amount      │ Ref   │ Upload │ Remove
│ ┌───────────┐   │ ┌────────┐  │ ┌──┐ │ ┌────┐ │ [X]
│ │ Cash      │   │ │ 5000   │  │    │ │[📎]│ │
│ └───────────┘   │ └────────┘  │    │ │ └────┘ │
│
│ [+ Add Method]
│
│ Entry Amount:      ₹15,000.00
│ Accepted Amount:   ₹5,000.00
│ ⚠️ Amount mismatch (needs ₹10,000 more)
│
└─────────────────────────────────────────────────┘
```

---

## File Attachment Section

### **Attach File Button**
**Purpose:** Upload supporting documents/proofs for this entry

**UI:**
```
┌─────────────────────────────────────────┐
│ [📎 Attach File]  ✓ invoice.pdf (245 KB)│
└─────────────────────────────────────────┘
```

**Features:**
- Styled as a button with paperclip icon
- Gradient background (purple)
- Hover effect (slight scale change)
- Shows file preview on upload

**Accepted File Types:**
```
Images:  .jpg, .jpeg, .png
Video:   .mp4, .mov, .avi
Docs:    .pdf
```

**File Size Limits:**
- Maximum: **50 MB** per file
- For comparison: Main payment proof is 5 MB

**Upload Handling:**
```javascript
// When file selected
mediaFileInput.addEventListener('change', function(e) {
    handleEntryMediaUpload(entryId, this.files[0]);
});

function handleEntryMediaUpload(entryId, file) {
    const maxSize = 50 * 1024 * 1024; // 50 MB
    const allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo'
    ];

    // Validate file size
    if (file.size > maxSize) {
        showError('File too large (max 50MB)');
        return;
    }

    // Validate file type
    if (!allowedTypes.includes(file.type)) {
        showError('Invalid file type');
        return;
    }

    // Show preview with file info
    const fileSize = (file.size / 1024).toFixed(2);
    previewDiv.innerHTML = `
        <span style="color: #22863a;">
            <i class="fas fa-check-circle"></i> 
            ${file.name} (${fileSize} KB)
        </span>
    `;
}
```

---

## Amount Summary Section

### **Entry Amount vs Accepted Amount**
Only appears when Payment Mode = "Multiple Acceptance"

```
┌─────────────────────────────────────────────────┐
│  Entry Amount: ₹15,000.00                       │
│  Accepted Amount: ₹15,000.00                    │
└─────────────────────────────────────────────────┘
```

**Purpose:** Track if multiple acceptance methods total to the entry amount

**Logic:**
```javascript
Entry Amount = Input from Amount field (15,000)
Accepted Amount = Sum of all Multiple Acceptance methods
                  (Cash 5,000 + Cheque 10,000 = 15,000)

If Entry Amount === Accepted Amount → ✅ OK
If Entry Amount !== Accepted Amount → ⚠️ Show warning
```

**Warning Message:**
```
⚠️ Amount mismatch (needs ₹10,000 more)
```

---

## Complete User Workflow

### **Step-by-Step Example:**

**Scenario:** Manager needs to pay ₹15,000 to labour worker for foundation work

#### Step 1: Click "Add More Entry"
```
Button clicked → New entry form appears with:
- Entry #1
- All fields empty
- Remove button (❌)
```

#### Step 2: Select Type
```
User clicks "Type" dropdown → Selects "Labour"
↓
System shows loading in "To" dropdown
↓
API call: get_labour_recipients.php?type=labour
↓
"To" dropdown now shows all labour workers
```

#### Step 3: Select Recipient
```
User clicks "To" dropdown → Selects "John Worker"
↓
Recipient is set to: John Worker (ID: 1)
```

#### Step 4: Add Description
```
User clicks "For" field → Types:
"Foundation work labour - 5 days"
↓
Stored for reference in database
```

#### Step 5: Enter Amount
```
User clicks "Amount" → Types: 15000
↓
Amount = ₹15,000.00
```

#### Step 6: Select Payment Mode
```
User clicks "Payment Mode" → Selects "Cash"
```

#### Step 7: Attach File (Optional)
```
User clicks "Attach File" → Selects invoice.pdf (200 KB)
↓
File validation:
- Size: 200 KB ✓ (< 50 MB)
- Type: PDF ✓ (in allowed list)
- Shows: "✓ invoice.pdf (200 KB)"
```

#### Step 8: Review & Save
```
Form looks like:
┌─ Entry #1 ─────────────────────────┐
│ Type: Labour                       │
│ To: John Worker                    │
│ For: Foundation work labour - 5 days
│ Amount: ₹15,000.00                │
│ Payment Mode: Cash                 │
│ File: ✓ invoice.pdf (200 KB)      │
└────────────────────────────────────┘

User clicks "Save Payment Entry" (main form submit)
↓
All entry data sent to backend in JSON format
```

---

## Multiple Entries Example

### **Real-World Scenario:** Building Payment
```
Main Payment Entry:
- Project: "Mall Construction Phase 2"
- Amount: ₹50,000
- Mode: Multiple Acceptance (Cash + Cheque)

Additional Entries (Line Items):
┌─────────────────────────────────────┐
│ Entry #1: Foundation Labour         │
│ To: John Worker                     │
│ Amount: ₹15,000                     │
│ Payment: Cash                        │
│ File: labour_receipt.pdf            │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Entry #2: Steel Material            │
│ To: Steel Corp India                │
│ Amount: ₹20,000                     │
│ Payment: Multiple Acceptance        │
│   - Cash: ₹10,000                   │
│   - Cheque: ₹10,000 (CHQ123456)    │
│ File: steel_invoice.pdf             │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Entry #3: Cement Supply             │
│ To: Cement Suppliers Ltd            │
│ Amount: ₹15,000                     │
│ Payment: Bank Transfer              │
│ File: cement_delivery_challan.pdf   │
└─────────────────────────────────────┘

Total: Main (₹50,000) + Line Items (₹15k + ₹20k + ₹15k) 
     = ₹100,000
```

---

## Data Submission Format

When form is submitted, each entry is sent as:

```javascript
{
  type: "labour",                    // Field 1: Type
  recipientId: 1,                   // Field 2: Recipient ID
  recipientName: "John Worker",     // Recipient name
  description: "Foundation work",   // Field 3: For/Description
  amount: 15000,                    // Field 4: Amount
  paymentMode: "cash",              // Field 5: Payment Mode
  mediaFile: "entryMedia_0",        // Field 6: Attached file reference
  acceptanceMethods: [              // If paymentMode = "multiple_acceptance"
    {
      method: "cash",
      amount: 5000,
      mediaFile: "entryMethodMedia_0_0"
    },
    {
      method: "cheque",
      amount: 10000,
      reference: "CHQ789",
      mediaFile: "entryMethodMedia_0_1"
    }
  ]
}
```

---

## Backend Processing

### **Database Tables Used:**

1. **tbl_payment_entry_line_items_detail**
   - Stores each entry's: type, recipient, description, amount, mode
   
2. **tbl_payment_acceptance_methods_line_items**
   - If paymentMode = "multiple_acceptance", stores each method
   
3. **tbl_payment_entry_file_attachments_registry**
   - Stores file metadata with SHA256 integrity hash

### **SQL Insert Example:**
```sql
INSERT INTO tbl_payment_entry_line_items_detail (
    payment_entry_master_id_fk,    -- Links to main payment
    recipient_type_category,        -- "labour"
    recipient_id_reference,         -- 1
    recipient_name_display,         -- "John Worker"
    payment_description_notes,      -- "Foundation work"
    line_item_amount,              -- 15000
    line_item_payment_mode,        -- "cash"
    line_item_sequence_number,     -- 1, 2, 3...
    line_item_media_upload_path,   -- "/uploads/entry_media/..."
    line_item_status              -- "pending"
) VALUES (...)
```

---

## Key Features Summary

| Feature | Details |
|---------|---------|
| **Dynamic Recipients** | Loaded based on Type selection |
| **Multiple Entries** | Unlimited line items per payment |
| **File Upload** | PDFs, Images, Videos up to 50 MB |
| **Real-time Validation** | Amount checks, file type/size |
| **Amount Tracking** | Entry vs Accepted amount comparison |
| **Multiple Methods** | Each entry can use multiple payment methods |
| **Remove Entries** | Delete individual entries anytime |
| **Renumbering** | Auto-renumbers entries after deletion |
| **Database Linked** | All data stored with foreign keys |

---

## Form Validation

Before submission, the system validates:

```javascript
✓ Type is selected
✓ Recipient is selected  
✓ Amount > 0
✓ Payment Mode is selected
✓ If Multiple Acceptance:
    ✓ At least one method added
    ✓ Each method has: type, amount
    ✓ (Optional) File upload for method
✓ Main entry file (proof) is uploaded
```

If validation fails:
```
Alert shown: "Error: Please fill in all required fields"
Form submission blocked
```

---

## Error Handling

### **Client-Side Errors:**
- Missing required fields → Show alert
- Invalid file type → Show red error: "Invalid file type"
- File too large → Show red error: "File too large (max 50MB)"
- Recipient loading failed → Show "Error loading recipients"

### **Server-Side Errors:**
- Database insert failed → Transaction rolls back
- File upload failed → All data discarded
- Invalid data → 400 error returned with message

---

## Summary

The "Add More Entry" section is a **powerful feature** that allows:

1. ✅ **Multiple recipient payments** in one entry
2. ✅ **Flexible payment methods** per recipient
3. ✅ **Document attachment** for each recipient payment
4. ✅ **Real-time validation** with user feedback
5. ✅ **Clean UI** with intuitive form fields
6. ✅ **Complete audit trail** stored in database

This section essentially transforms a simple payment form into a **complex expense tracking system** capable of handling real-world payment scenarios.
