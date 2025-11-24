# Purchase Manager Expense Tracking System - Complete Explanation

## Overview
This is a comprehensive expense tracking system built with PHP, MySQL, and JavaScript. It's designed for a Purchase Manager role to manage vendors, labour records, and payment entries with complex transaction handling.

---

## System Architecture

### 1. **Main Dashboard** (`purchase_manager_dashboard.php`)
**Purpose**: Central hub for all expense tracking operations
- Displays filter section for searching records by date, payment type, and status
- Shows "Add Records" section with buttons to add vendors, labour, and payment entries
- Recently Added Records section with tabs for vendors, labours, and payment entries
- Management section with detailed tabs displaying all records

**Key Features**:
- Pagination for viewing records in chunks (10 per page)
- Filter and search functionality
- Tab-based navigation between different record types
- Responsive grid layout

---

## 2. Three Main Data Models

### **A. VENDOR MANAGEMENT**

#### Files Involved:
- `modals/add_vendor_modal.php` - Form for adding vendors
- `handlers/add_vendor_handler.php` - Backend processing
- `purchase_manager_dashboard.php` - Display and management

#### Add Vendor Modal (`add_vendor_modal.php`)
**Form Structure**:
```
├── Basic Information
│   ├── Full Name (required)
│   ├── Phone Number (10 digits, required)
│   ├── Alternative Phone (optional)
│   └── Email Address (required)
│
├── Vendor Type Selection
│   ├── Labour Contractor (19 predefined + custom option)
│   ├── Material Contractor (20 predefined + custom option)
│   ├── Material Supplier (20 predefined + custom option)
│   └── Custom Type Input (if selected)
│
├── Banking Details (Collapsible)
│   ├── Bank Name
│   ├── Account Number
│   ├── IFSC Code
│   ├── Account Type (Savings/Current/Business)
│   └── QR Code Upload (optional)
│
├── GST Details (Collapsible)
│   ├── GST Number
│   ├── State
│   └── GST Type (CGST/SGST/IGST/UGST)
│
└── Address Details (Collapsible)
    ├── Street Address
    ├── City
    ├── State
    └── Zip Code (6 digits)
```

#### Vendor Handler (`add_vendor_handler.php`)
**Processing Steps**:
1. Validate user is Purchase Manager
2. Validate all required fields
3. Check for duplicate vendors (name + phone + type)
4. Handle QR Code file upload with validation
5. Determine vendor category type based on selected type
6. Insert vendor record into `pm_vendor_registry_master` table
7. Generate unique vendor code: `VN/[TYPE]/YYYY/MM/###`
   - TYPE: LC (Labour), MC (Material Contractor), MS (Material Supplier), XX (Other)
   - Example: `VN/LC/2025/11/001`
8. Return success with vendor code and ID

**Database Table**: `pm_vendor_registry_master`
- Stores all vendor information including banking, GST, and address details
- Status: active/inactive/suspended/archived
- Supports both predefined and custom vendor types

---

### **B. LABOUR MANAGEMENT**

#### Files Involved:
- `modals/add_labour_modal.php` - Form for adding labour
- `handlers/add_labour_handler.php` - Backend processing
- `purchase_manager_dashboard.php` - Display and management

#### Add Labour Modal (`add_labour_modal.php`)
**Form Structure**:
```
├── Personal Information
│   ├── Full Name (required)
│   ├── Contact Number (10 digits, required)
│   ├── Alternative Number (optional)
│   ├── Join Date (optional)
│   ├── Labour Type (dropdown)
│   └── Daily Salary (optional)
│
├── Labour ID Proof (Collapsible)
│   ├── Aadhar Card Upload
│   ├── PAN Card Upload
│   ├── Voter ID Upload
│   └── Other Document Upload
│
└── Address Information
    ├── Street Address
    ├── City
    ├── State
    └── Zip Code (6 digits)
```

#### Labour Handler (`add_labour_handler.php`)
**Processing Steps**:
1. Validate user authentication
2. Validate required fields (name, contact, labour type)
3. Check for duplicate labour records (name + contact + type)
4. Handle file uploads for ID proofs (Aadhar, PAN, Voter ID, Other)
   - Allowed types: JPEG, PNG, GIF, PDF
   - Max size: 5MB per file
5. Generate unique labour code: `LB-YYYYMMDD-XXXXXX`
6. Insert labour record into `labour_records` table
7. Return success with labour code and ID

**Database Table**: `labour_records`
- Stores labour information with document paths
- Status: active/inactive
- Supports multiple document uploads

---

### **C. PAYMENT ENTRY MANAGEMENT** ⚠️ **THIS IS THE MOST COMPLEX**

#### Files Involved:
- `modals/add_payment_entry_modal.php` - Form for payment entries
- `handlers/payment_entry_handler.php` - Backend processing
- `purchase_manager_dashboard.php` - Display and management

#### Add Payment Entry Modal (`add_payment_entry_modal.php`)
**Form Structure**:
```
├── Toggle: Single Payment ↔ Multiple Payments

IF SINGLE PAYMENT:
├── Basic Payment Info (3-column grid)
│   ├── Project Type (Architecture/Interior/Construction)
│   ├── Project Name (dropdown, populated based on type)
│   ├── Payment Date (required)
│   ├── Amount (required)
│   ├── Payment Done By (Authorized User dropdown)
│   └── Payment Mode (Cash/Cheque/Bank Transfer/Credit Card/Online/UPI/Multiple Acceptance)
│
├── IF Payment Mode = "Multiple Acceptance":
│   ├── Multiple Acceptance Section
│   │   ├── Add Payment Method Button
│   │   └── Payment Method Rows (repeating):
│   │       ├── Payment Method Select
│   │       ├── Amount Received
│   │       ├── Reference/Cheque Number
│   │       ├── Media Upload
│   │       └── Remove Button
│   │
│   └── Acceptance Summary Box (shows total vs received vs difference)
│
├── Payment Proof Image Upload (drag & drop)
│   └── Accepts: PDF, JPG, PNG (max 5MB)
│
├── Additional Entries Section
│   ├── "Add More Entry" Button
│   └── Entry Items (repeating, max unlimited):
│       ├── Entry Number Header
│       ├── Type Select (Labour/Material Supplier/Contractor)
│       ├── Recipient Select (populated based on type)
│       ├── Description Text
│       ├── Amount (required)
│       ├── Payment Done Via (User dropdown)
│       ├── Payment Mode Select
│       │
│       ├── IF Entry Payment Mode = "Multiple Acceptance":
│       │   └── Entry Acceptance Methods (similar structure as main)
│       │
│       └── Entry Media Upload
│
└── Form Actions
    ├── Cancel Button
    └── Save Payment Entry Button

IF MULTIPLE PAYMENTS:
└── Payments Table (not fully implemented yet)
```

#### Payment Entry Handler (`payment_entry_handler.php`)
**Processing Steps** (This is a 7-step transaction):

**STEP 1: Insert Main Payment Entry**
- Validates all required fields
- Handles proof image upload (5MB max, validates MIME type)
- Creates upload directory if doesn't exist
- Generates unique filename: `proof_[timestamp]_[random].ext`
- Inserts into `tbl_payment_entry_master_records`
- Returns payment_entry_id

**STEP 2: Insert Multiple Acceptance Methods** (if applicable)
- Loops through each acceptance method
- Handles media upload for each method (50MB max)
- Validates file types: images, PDF, video
- Inserts into `tbl_payment_acceptance_methods_primary`
- Registers files in registry

**STEP 3: Insert Additional Entries (Line Items)**
- Loops through each additional entry
- Validates recipient type and amount
- Handles entry media upload (50MB max)
- Inserts into `tbl_payment_entry_line_items_detail`

**STEP 4: Insert Line Item Acceptance Methods** (if entry has multiple acceptance)
- Nested loop for each entry's acceptance methods
- Handles media for each method
- Inserts into `tbl_payment_acceptance_methods_line_items`

**STEP 5: Register Main Proof Image File**
- Calculates SHA256 file hash for integrity
- Inserts into `tbl_payment_entry_file_attachments_registry`

**STEP 6: Insert Summary Totals**
- Calculates total from line items
- Calculates total acceptance amounts
- **CRITICAL VALIDATION**: Line items total cannot exceed main amount
- Grand total = main payment amount (not sum of line items)
- Inserts into `tbl_payment_entry_summary_totals`

**STEP 7: Insert Audit Log**
- Records action, user, IP address, user agent
- Inserts into `tbl_payment_entry_audit_activity_log`

**All or Nothing**: Uses database transactions
- Begins transaction at start
- Commits all changes if successful
- Rolls back everything if any error occurs

**Database Tables Created**:
- `tbl_payment_entry_master_records` - Main payment entry
- `tbl_payment_acceptance_methods_primary` - Multiple acceptance methods
- `tbl_payment_entry_line_items_detail` - Additional entries/line items
- `tbl_payment_acceptance_methods_line_items` - Acceptance methods for line items
- `tbl_payment_entry_summary_totals` - Summary calculations
- `tbl_payment_entry_file_attachments_registry` - File tracking with integrity hash
- `tbl_payment_entry_audit_activity_log` - Audit trail

---

## 3. Key Technical Features

### **File Upload Handling**
- Drag & drop support for payment proof
- Multiple file uploads for different sections
- MIME type validation
- File size validation (5MB for proofs, 50MB for others)
- Unique filename generation: `[type]_[timestamp]_[random].[ext]`
- SHA256 hashing for integrity verification
- Directory creation with proper permissions

### **Validation System**
- Client-side validation (HTML5 + JavaScript)
- Server-side validation (PHP)
- Phone number validation (10 digits)
- Email validation
- Zip code validation (6 digits)
- Amount validation (positive numbers)
- Duplicate checking (vendors, labour)

### **Database Transactions**
Used in payment entry handler to ensure data consistency:
- Begin transaction
- Execute all steps
- Commit if successful
- Rollback on any error

### **Security Features**
- Session-based authentication check
- Role-based access (Purchase Manager only)
- CSRF token support (commented out, can be enabled)
- File type validation
- Database prepared statements
- Error logging

### **UI/UX Features**
- Modal dialogs for data entry
- Collapsible sections for optional data
- Custom vendor type support
- Dynamic form fields (project name based on type)
- Toast notifications for success/error
- Loading states on buttons
- Real-time calculation of totals
- Responsive grid layout
- Tab-based navigation

---

## 4. Database Design

### **Key Relationships**:
```
VENDORS (pm_vendor_registry_master)
├── Unique Code: VN/[TYPE]/YYYY/MM/###
├── Status: active, inactive, suspended, archived
└── Category Type: Labour Contractor, Material Supplier, Material Contractor

LABOUR (labour_records)
├── Unique Code: LB-YYYYMMDD-XXXXXX
├── Status: active
└── Document Paths: aadhar_card, pan_card, voter_id, other_document

PAYMENT ENTRIES (tbl_payment_entry_master_records)
├── Master Record
├── Multiple Acceptance Methods (optional)
├── Line Items (Additional Entries)
│   └── Each may have Multiple Acceptance Methods
├── File Attachments Registry
├── Summary Totals
└── Audit Log
```

---

## 5. Critical Business Logic

### **Amount Validation in Payment Entries**
```
Main Payment Amount: ₹1000

Line Item 1: ₹400 ✓
Line Item 2: ₹500 ✓
Line Item 3: ₹150 ✗ (Would exceed 1000)

Total Line Items: ₹1000 (cannot exceed main amount)
Grand Total: ₹1000 (always equals main amount, not sum of items)
```

### **Multiple Acceptance Calculation**
```
Main Amount: ₹1000

Cash: ₹400
Cheque: ₹300
Bank Transfer: ₹300
Total Received: ₹1000 ✓

Warning if totals don't match
```

### **Duplicate Prevention**
- Vendors: Duplicate detected by (Name + Phone + Type)
- Labour: Duplicate detected by (Name + Contact + Type)
- Returns error with existing record ID if duplicate found

---

## 6. Form Workflows

### **Adding a Vendor**
1. Click "Add Vendor" button → Modal opens
2. Fill basic info (name, phone, email)
3. Select vendor type from predefined options
4. Optional: Expand Banking, GST, Address sections
5. Optional: Upload QR code
6. Submit → Handler validates → Generates code → Inserts record → Success message

### **Adding Labour**
1. Click "Add Labour" button → Modal opens
2. Fill required info (name, contact, type)
3. Optional: Expand ID Proof section → Upload documents
4. Optional: Fill address info
5. Submit → Handler validates → Generates code → Inserts record → Success message

### **Adding Payment Entry** (Complex)
1. Click "Add Payment" button → Modal opens
2. Fill main info (project type → project name, date, amount, authorized user)
3. Select payment mode
4. If "Multiple Acceptance":
   - Add payment methods (cash, cheque, etc.)
   - Enter amounts for each
   - Optional: Upload media for each
5. Upload payment proof image
6. Optional: Add additional entries
   - For each entry: Select type → recipient → amount
   - Optional: Add multiple acceptance methods for entry
   - Optional: Upload media
7. Submit → Handler runs 7-step transaction → Success message

---

## 7. Common Issues & Debugging

### **File Upload Issues**
- Check upload directory permissions (0755 or 0777)
- Verify MIME type configuration
- Check file size limits
- Ensure /uploads directory exists

### **Database Errors**
- Check table structure against INSERT statements
- Verify column names match exactly
- Ensure database user has proper permissions
- Check transaction support (InnoDB engine required)

### **Validation Issues**
- Phone numbers must be exactly 10 digits
- Email must be valid format
- Amounts must be positive numbers
- Zip codes must be exactly 6 digits

### **Payment Entry Specific Issues**
- Line items total cannot exceed main amount
- At least one file must be uploaded for proof
- If multiple acceptance selected, at least one method required
- Entry amounts validated individually

---

## 8. API Endpoints (from handlers)

```
POST /handlers/add_vendor_handler.php
POST /handlers/add_labour_handler.php
POST /handlers/payment_entry_handler.php
GET /get_projects_by_type.php
GET /get_active_users.php
GET /get_vendor_categories_for_entry.php
GET /get_labour_recipients.php
GET /get_vendor_recipients.php
GET /get_vendors.php
GET /get_labours.php
GET /get_payment_entries.php
```

---

## 9. File Structure
```
/connect/
├── purchase_manager_dashboard.php (main dashboard)
├── modals/
│   ├── add_vendor_modal.php
│   ├── add_labour_modal.php
│   ├── add_payment_entry_modal.php
│   ├── vendor_details_modal.php
│   ├── labour_details_modal.php
│   ├── payment_entry_details_modal.php
│   ├── vendor_edit_modal.php
│   ├── labour_edit_modal.php
│   └── payment_entry_files_registry_modal.php
├── handlers/
│   ├── add_vendor_handler.php
│   ├── add_labour_handler.php
│   ├── payment_entry_handler.php
│   ├── get_projects_by_type.php
│   ├── get_active_users.php
│   ├── get_vendor_categories_for_entry.php
│   ├── get_labour_recipients.php
│   ├── get_vendor_recipients.php
│   ├── get_vendors.php
│   ├── get_labours.php
│   └── get_payment_entries.php
├── uploads/
│   ├── vendor_qr_codes/
│   ├── labour_documents/
│   ├── payment_proofs/
│   ├── acceptance_methods/
│   ├── entry_media/
│   ├── entry_method_media/
│   └── default/
└── config/
    └── db_connect.php
```

---

## 10. Data Flow Summary

```
USER (Purchase Manager)
    ↓
Dashboard (purchase_manager_dashboard.php)
    ↓
    ├─→ Click "Add Vendor" → add_vendor_modal.php → add_vendor_handler.php → DB
    ├─→ Click "Add Labour" → add_labour_modal.php → add_labour_handler.php → DB
    └─→ Click "Add Payment" → add_payment_entry_modal.php → payment_entry_handler.php → DB
    ↓
Display Records
    ↓
    ├─→ Get Vendors → get_vendors.php → Display in Vendors Tab
    ├─→ Get Labours → get_labours.php → Display in Labours Tab
    └─→ Get Payment Entries → get_payment_entries.php → Display in Recently Added Tab
```

---

## 11. Important Notes

✅ **What's Working**:
- Vendor management (add, display, search, filter)
- Labour management (add, display, search, filter)
- Payment entry basic flow
- File uploads with validation
- Database transactions
- Responsive UI

⚠️ **Areas That May Need Attention**:
- Edit and delete functionality for payment entries
- Payment entry search and filter in dashboard
- Multiple payments toggle (marked as "will be implemented soon")
- Some API endpoints might need creation
- File download/preview functionality
- Payment entry details modal
- Recipient files modal

📝 **Next Steps to Consider**:
1. Create missing API endpoints (get_projects_by_type, get_labour_recipients, etc.)
2. Implement edit functionality for payment entries
3. Implement delete functionality with proper confirmation
4. Add search and filter for payment entries
5. Create download links for uploaded files
6. Add approval workflow for payment entries
7. Generate reports and analytics
8. Implement proper error handling and user feedback

---

This system is built to handle complex multi-level payment transactions with proper validation, file management, and audit trails. The most complex part is the payment entry handler with its 7-step transaction process.
