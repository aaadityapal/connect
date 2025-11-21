# Payment Entry System - Complete Workflow Diagram

## 1. MAIN PAYMENT ENTRY WORKFLOW

```
╔════════════════════════════════════════════════════════════════════════════════╗
║                     PAYMENT ENTRY CREATION FLOW                               ║
╚════════════════════════════════════════════════════════════════════════════════╝

┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 1: USER OPENS DASHBOARD                                                  │
│ ├─ Purchase Manager logs in                                                    │
│ ├─ Views "purchase_manager_dashboard.php"                                      │
│ └─ Clicks "Add Payment Entry" button                                           │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 2: MODAL OPENS                                                            │
│ ├─ "add_payment_entry_modal.php" displays                                      │
│ ├─ Form loads with empty fields                                               │
│ └─ JavaScript initializes:                                                     │
│    ├─ openPaymentEntryModal()                                                  │
│    ├─ initializeFormDefaults()                                                 │
│    └─ loadAuthorizedUsers()                                                    │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 3: USER SELECTS PROJECT TYPE                                              │
│ ├─ Dropdown: Architecture / Interior / Construction                            │
│ ├─ onChange event triggers: loadProjectsByType()                               │
│ └─ Fetches from: get_projects_by_type.php?projectType=X                        │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 4: PROJECT NAME DROPDOWN POPULATES                                        │
│ ├─ Dynamic list of projects for selected type loads                            │
│ ├─ User selects specific project                                               │
│ └─ Project ID is captured for database storage                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 5: USER FILLS PAYMENT DETAILS                                             │
│ ├─ Payment Date: Pick from date picker                                         │
│ ├─ Amount: Enter payment amount (₹)                                            │
│ ├─ Payment Done By: Select authorized user                                     │
│ │  └─ Fetches from: get_active_users.php                                       │
│ ├─ Payment Mode: Select method                                                 │
│ │  ├─ Single: Cash/Cheque/Bank Transfer/etc                                    │
│ │  ├─ Split Payment                                                            │
│ │  └─ Multiple Acceptance (Advanced)                                           │
│ └─ Proof Image: Upload (PDF/JPG/PNG, max 5MB)                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
                        ┌───────────────────────┐
                        │  Payment Mode Check   │
                        └───────────┬───────────┘
                                    ↓
                ┌───────────────────────────────────────┐
                ↓                                       ↓
        ┌──────────────┐                      ┌────────────────────────┐
        │ SINGLE MODE  │                      │ MULTIPLE ACCEPTANCE    │
        │ (Simpler)    │                      │ MODE (Advanced)        │
        └──────────────┘                      └────────────────────────┘
                ↓                                       ↓
        └─ Just main proof          ┌─ Add multiple payment methods
          └─ Simple entry            │  ├─ Cash: amount + media
                                     │  ├─ Cheque: ref + media
                                     │  ├─ Bank Transfer: ref + media
                                     │  └─ UPI/Credit Card: ref + media
                                     └─ Calculate total received vs. main amount
                                        ├─ Match validation
                                        └─ Show warning if mismatch
```

---

## 2. LINE ITEMS (ADDITIONAL ENTRIES) WORKFLOW

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 6: ADD LINE ITEMS (Optional)                                              │
│ ├─ Click "Add More Entry" button (can add multiple)                            │
│ └─ For each line item:                                                         │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
            ╔════════════════════════════════════════╗
            ║    ENTRY #1, #2, #3... (Loop)          ║
            ╚════════════════════════════════════════╝
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ LINE ITEM CONFIGURATION                                                         │
│                                                                                 │
│ ├─ Type: Labour / Labour Skilled / Material Steel / Material Bricks / etc.     │
│ │  └─ onChange: loadRecipientsByType(type)                                     │
│ │     └─ Fetches from: get_labour_recipients.php or get_vendor_recipients.php │
│ │                                                                               │
│ ├─ Recipient: Auto-populated dropdown based on type                            │
│ │  └─ Shows: [Name] [ID]                                                       │
│ │                                                                               │
│ ├─ Payment Done Via (User): Select which user processed this payment           │
│ │  └─ Fetches from: get_active_users.php                                       │
│ │                                                                               │
│ ├─ For (Description): Text field explaining what payment is for               │
│ │                                                                               │
│ ├─ Amount: Entry amount (₹) - MUST BE ≤ MAIN AMOUNT                            │
│ │  └─ Real-time validation warning if exceeds                                  │
│ │                                                                               │
│ ├─ Payment Mode: Single or Multiple Acceptance                                 │
│ │                                                                               │
│ ├─ If Multiple Acceptance Mode:                                                │
│ │  ├─ Add Method button appears                                                │
│ │  ├─ Select method type for each split                                        │
│ │  ├─ Enter amount for each method                                             │
│ │  ├─ Show entry amount vs. accepted amount                                    │
│ │  └─ Media upload per method                                                  │
│ │                                                                               │
│ └─ Media Upload: Attach proof/receipt for this line item                      │
│    └─ Allowed: PDF, JPG, PNG, MP4, MOV, AVI (max 50MB)                        │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
            ┌──────────────────────────────────────┐
            │ Add Another Entry or Continue?        │
            └──────────┬───────────────────────────┘
                       ↓
            ┌──────────────────────────────────────┐
            │ Click "Add More Entry" → Loop Above   │
            │ or                                    │
            │ Click "Save Payment Entry" → Next    │
            └──────────────────────────────────────┘
```

---

## 3. FORM SUBMISSION & BACKEND PROCESSING

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 7: FORM VALIDATION (Client-Side)                                          │
│ ├─ Check all required fields filled                                            │
│ ├─ Validate entry amounts ≤ main amount                                        │
│ ├─ Verify proof image uploaded                                                 │
│ ├─ If Multiple Acceptance: at least one method filled                          │
│ └─ If errors: Display error messages, prevent submission                       │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 8: PREPARE FORM DATA                                                      │
│ ├─ Collect all form values into JavaScript object                              │
│ ├─ Convert arrays to JSON:                                                     │
│ │  ├─ multipleAcceptance = JSON array of methods                               │
│ │  └─ additionalEntries = JSON array of line items                             │
│ └─ Create FormData with files and JSON data                                    │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 9: SEND TO BACKEND                                                        │
│ ├─ Endpoint: handlers/payment_entry_handler.php (POST)                         │
│ ├─ Method: FETCH with FormData                                                 │
│ ├─ Sends:                                                                       │
│ │  ├─ Form fields (projectType, projectName, amount, etc.)                     │
│ │  ├─ File: proofImage                                                         │
│ │  ├─ Files: acceptanceMedia_0, acceptanceMedia_1, etc.                       │
│ │  ├─ Files: entryMedia_0, entryMedia_1, etc.                                  │
│ │  ├─ JSON: multipleAcceptance                                                 │
│ │  ├─ JSON: additionalEntries                                                  │
│ │  └─ Files: entryMethodMedia_0_0, entryMethodMedia_0_1, etc.                  │
│ └─ Show "Saving..." loading state                                              │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
╔════════════════════════════════════════════════════════════════════════════════╗
║                    BACKEND PROCESSING                                          ║
║            handlers/payment_entry_handler.php                                  ║
╚════════════════════════════════════════════════════════════════════════════════╝
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 10: AUTHENTICATION CHECK                                                  │
│ ├─ Verify user is logged in (check $_SESSION['user_id'])                       │
│ ├─ If not: Return 401 Unauthorized                                             │
│ └─ If yes: Continue processing                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 11: BEGIN DATABASE TRANSACTION                                            │
│ ├─ $pdo->beginTransaction()                                                    │
│ ├─ Purpose: Ensure atomic operation (all-or-nothing)                           │
│ └─ If error: Auto rollback all changes                                         │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 12: PROCESS PROOF IMAGE                                                   │
│ ├─ File validation:                                                             │
│ │  ├─ Check MIME type: JPG/PNG/PDF only                                        │
│ │  ├─ Check size: Max 5MB                                                      │
│ │  └─ Generate unique filename: proof_[timestamp]_[random].ext                 │
│ ├─ Move to: uploads/payment_proofs/                                            │
│ ├─ Store path in database: /uploads/payment_proofs/proof_[unique].jpg          │
│ └─ Save: filename, size, MIME type                                             │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 13: INSERT MAIN PAYMENT ENTRY                                             │
│ ├─ Table: tbl_payment_entry_master_records                                     │
│ ├─ Insert with fields:                                                         │
│ │  ├─ project_type_category                                                    │
│ │  ├─ project_name_reference                                                   │
│ │  ├─ project_id_fk                                                            │
│ │  ├─ payment_amount_base ← Main amount                                        │
│ │  ├─ payment_date_logged                                                      │
│ │  ├─ authorized_user_id_fk                                                    │
│ │  ├─ payment_mode_selected                                                    │
│ │  ├─ payment_proof_document_path                                              │
│ │  ├─ entry_status_current = 'submitted' ← STATUS SET HERE                     │
│ │  └─ created_by_user_id                                                       │
│ ├─ Get: $payment_entry_id = $pdo->lastInsertId()                               │
│ └─ This ID links everything together                                           │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 14: PROCESS MULTIPLE ACCEPTANCE METHODS (if applicable)                   │
│ ├─ Check: if payment_mode === 'multiple_acceptance'                            │
│ ├─ For each method in JSON array:                                              │
│ │  ├─ Validate: method_type, amount > 0                                        │
│ │  ├─ Upload: acceptanceMedia file (if provided)                               │
│ │  │  └─ Validate: allowedTypes, maxSize = 50MB                                │
│ │  ├─ Insert into: tbl_payment_acceptance_methods_primary                      │
│ │  │  ├─ payment_entry_id_fk = $payment_entry_id                               │
│ │  │  ├─ payment_method_type (cash, cheque, etc.)                              │
│ │  │  ├─ amount_received_value                                                 │
│ │  │  ├─ reference_number_cheque                                               │
│ │  │  ├─ supporting_document_path                                              │
│ │  │  └─ method_sequence_order (1, 2, 3...)                                    │
│ │  └─ Register file in: tbl_payment_entry_file_attachments_registry            │
│ │     ├─ attachment_type = 'acceptance_method_media'                           │
│ │     ├─ Calculate SHA256 hash                                                 │
│ │     └─ File counter++                                                        │
│ └─ End loop                                                                     │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 15: PROCESS LINE ITEMS (Additional Entries)                               │
│ ├─ Check: if isset($_POST['additionalEntries'])                                │
│ ├─ JSON decode to array                                                        │
│ ├─ For each entry in array:                                                    │
│ │  ├─ Validate: recipient_type, amount > 0                                     │
│ │  ├─ Upload: entryMedia file (if provided)                                    │
│ │  ├─ Insert into: tbl_payment_entry_line_items_detail                         │
│ │  │  ├─ payment_entry_master_id_fk = $payment_entry_id                        │
│ │  │  ├─ recipient_type_category                                               │
│ │  │  ├─ recipient_id_reference                                                │
│ │  │  ├─ recipient_name_display                                                │
│ │  │  ├─ line_item_amount                                                      │
│ │  │  ├─ line_item_payment_mode                                                │
│ │  │  ├─ line_item_paid_via_user_id                                            │
│ │  │  ├─ line_item_sequence_number                                             │
│ │  │  ├─ line_item_status = 'pending' ← LINE ITEM STATUS                       │
│ │  │  └─ line_item_media_upload_path                                           │
│ │  ├─ Get: $line_item_id = $pdo->lastInsertId()                                │
│ │  ├─ Register media file in attachment registry                               │
│ │  │  └─ attachment_type = 'line_item_media'                                   │
│ │  └─ Check: if line_item_payment_mode === 'multiple_acceptance'               │
│ │     └─ Process Line Item Acceptance Methods (nested)                         │
│ │        ├─ For each method:                                                   │
│ │        ├─ Upload: entryMethodMedia_X_Y                                       │
│ │        ├─ Insert into: tbl_payment_acceptance_methods_line_items             │
│ │        │  ├─ line_item_entry_id_fk = $line_item_id                           │
│ │        │  ├─ payment_entry_master_id_fk = $payment_entry_id                  │
│ │        │  ├─ method_type_category                                            │
│ │        │  ├─ method_amount_received                                          │
│ │        │  └─ method_supporting_media_path                                    │
│ │        └─ Register file in attachment registry                               │
│ │           └─ attachment_type = 'line_item_method_media'                      │
│ └─ End loop                                                                     │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 16: REGISTER MAIN PROOF IMAGE FILE                                        │
│ ├─ Insert into: tbl_payment_entry_file_attachments_registry                    │
│ ├─ Fields:                                                                      │
│ │  ├─ payment_entry_master_id_fk = $payment_entry_id                           │
│ │  ├─ attachment_type = 'proof_image'                                          │
│ │  ├─ attachment_reference_id = 'proofImage'                                   │
│ │  ├─ attachment_file_original_name                                            │
│ │  ├─ attachment_file_stored_path                                              │
│ │  ├─ attachment_file_size_bytes                                               │
│ │  ├─ attachment_file_mime_type                                                │
│ │  ├─ attachment_integrity_hash = SHA256(file) ← For verification              │
│ │  └─ uploaded_by_user_id                                                      │
│ └─ file_counter++                                                              │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 17: CALCULATE TOTALS & VALIDATE                                           │
│ ├─ Query line items sum: SUM(line_item_amount)                                 │
│ ├─ Query acceptance methods sum: SUM(amount_received_value)                    │
│ ├─ VALIDATE: total_line_items ≤ main_amount                                    │
│ │  └─ If NOT: throw Exception → ROLLBACK all changes                           │
│ ├─ Calculate: grand_total = main_amount (NOT sum of items)                     │
│ │  └─ Line items are BREAKDOWNS, not additions                                 │
│ └─ Proceed if validation passes                                                │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 18: INSERT SUMMARY TOTALS                                                 │
│ ├─ Table: tbl_payment_entry_summary_totals                                     │
│ ├─ Insert:                                                                      │
│ │  ├─ payment_entry_master_id_fk = $payment_entry_id                           │
│ │  ├─ total_amount_main_payment = $amount                                      │
│ │  ├─ total_amount_acceptance_methods = $total_acceptance                      │
│ │  ├─ total_amount_line_items = $total_line_items                              │
│ │  ├─ total_amount_grand_aggregate = $grand_total                              │
│ │  ├─ acceptance_methods_count                                                 │
│ │  ├─ line_items_count                                                         │
│ │  └─ total_files_attached = $file_counter                                     │
│ └─ For reporting and analytics                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 19: INSERT AUDIT LOG                                                      │
│ ├─ Table: tbl_payment_entry_audit_activity_log                                 │
│ ├─ Insert:                                                                      │
│ │  ├─ payment_entry_id_fk = $payment_entry_id                                  │
│ │  ├─ audit_action_type = 'created'                                            │
│ │  ├─ audit_change_description = 'Payment entry created and submitted'         │
│ │  ├─ audit_performed_by_user_id = current_user_id                             │
│ │  ├─ audit_ip_address_captured = $_SERVER['REMOTE_ADDR']                      │
│ │  └─ audit_user_agent_info = $_SERVER['HTTP_USER_AGENT']                      │
│ └─ For security and accountability                                             │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 20: COMMIT TRANSACTION                                                    │
│ ├─ $pdo->commit()                                                               │
│ ├─ All changes saved to database                                               │
│ └─ If any error occurred earlier: would have rolled back                       │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────┐
│ STEP 21: RETURN SUCCESS RESPONSE                                               │
│ ├─ HTTP 200 OK                                                                  │
│ ├─ JSON Response:                                                               │
│ │  ├─ success: true                                                             │
│ │  ├─ message: "Payment entry saved successfully"                              │
│ │  ├─ payment_entry_id: $payment_entry_id                                      │
│ │  ├─ grand_total: $grand_total                                                │
│ │  └─ files_attached: $file_counter                                            │
│ └─ Frontend receives and processes                                             │
└─────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
                    ┌─────────────────────────────────┐
                    │ SUCCESS! Entry created           │
                    └─────────────────────────────────┘
                                    ↓
        ┌───────────────────────────────────────────────────┐
        ├─ Show success alert                              │
        ├─ Close modal                                      │
        ├─ Refresh dashboard                               │
        └─ Display new entry in "Recently Added Records"   │
```

---

## 4. ERROR HANDLING WORKFLOW

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ ERROR SCENARIOS & HANDLING                                                      │
└─────────────────────────────────────────────────────────────────────────────────┘

ERROR 1: Authentication Failed
├─ Check: !isset($_SESSION['user_id'])
├─ Response: HTTP 401 Unauthorized
├─ JSON: { success: false, message: 'User not authenticated' }
└─ Frontend: Redirect to login page

ERROR 2: Missing Required Fields
├─ Check: !$project_type || !$project_name || $amount <= 0 || !$authorized_user_id
├─ Action: throw Exception('Missing required fields...')
├─ Response: HTTP 400 Bad Request
└─ Frontend: Show error message in modal

ERROR 3: Invalid Proof File
├─ Check: File type not in allowed list
├─ Action: throw Exception('Invalid proof file type...')
├─ Response: HTTP 400 Bad Request
├─ Database: ROLLBACK all changes
└─ Frontend: Display file error

ERROR 4: Line Items Exceed Main Amount
├─ Check: $total_line_items > $amount
├─ Action: throw Exception('Total line item amount exceeds main payment...')
├─ Response: HTTP 400 Bad Request
├─ Database: ROLLBACK all changes
└─ Frontend: Alert user to adjust amounts

ERROR 5: Database Connection Error
├─ Any PDO exception
├─ Action: Catch exception, rollback transaction
├─ Response: HTTP 400 Bad Request
├─ Log: error_log('Payment Entry Handler Error: ' . $message)
└─ Frontend: Display generic error message

ERROR 6: File Upload Directory Error
├─ Check: mkdir() fails
├─ Action: throw Exception('Failed to create upload directory')
├─ Response: HTTP 400 Bad Request
├─ Database: ROLLBACK all changes
└─ Frontend: Show server error
```

---

## 5. DATA FLOW DIAGRAM

```
┌────────────────────────────────────────────────────────────────────────────────────┐
│                            DATA FLOW ARCHITECTURE                                  │
└────────────────────────────────────────────────────────────────────────────────────┘

USER BROWSER                    SERVER                              DATABASE
═════════════════════════════════════════════════════════════════════════════════════

  │                                │                                    │
  │  1. Load Dashboard             │                                    │
  ├──────────────────────────────→ purchase_manager_dashboard.php       │
  │                                │                                    │
  │                                │  Query vendors/labours/entries     │
  │                                ├───────────────────────────────────→│
  │                                │  ←─── ResultSet                    │
  │  2. Display Dashboard          │←───────────────────────────────────│
  │←──────────────────────────────┤                                     │
  │                                │                                    │
  │  3. Click Add Payment Entry    │                                    │
  ├──────────────────────────────→ add_payment_entry_modal.php          │
  │                                │                                    │
  │  4. Modal Form Opens           │                                    │
  │←──────────────────────────────┤                                    │
  │                                │                                    │
  │  5. Select Project Type        │                                    │
  ├──────────────────────────────→ get_projects_by_type.php            │
  │                                │  Query projects                    │
  │                                ├───────────────────────────────────→│
  │  6. Display Projects           │  ←─── Project List                │
  │←──────────────────────────────┤←───────────────────────────────────│
  │                                │                                    │
  │  7. Fill Form + Upload Files   │                                    │
  │                                │                                    │
  │  8. Submit Form (FormData)     │                                    │
  ├──────────────────────────────→ payment_entry_handler.php           │
  │   ├─ Fields                    │                                    │
  │   ├─ proofImage file           │  9. Process Files                  │
  │   ├─ acceptanceMedia files     │  ├─ Move to uploads/               │
  │   ├─ entryMedia files          │  └─ Generate filenames             │
  │   ├─ JSON arrays               │                                    │
  │   └─ Timestamps                │  10. Begin Transaction             │
  │                                │                                    │
  │                                │  11. Insert Master Record          │
  │                                ├───────────────────────────────────→│
  │                                │   INSERT INTO                      │
  │                                │   tbl_payment_entry_master_records │
  │                                │  ←─── payment_entry_id             │
  │                                │←───────────────────────────────────│
  │                                │                                    │
  │                                │  12. Insert Acceptance Methods     │
  │                                ├───────────────────────────────────→│
  │                                │   INSERT INTO                      │
  │                                │   tbl_payment_acceptance_methods   │
  │                                │←───────────────────────────────────│
  │                                │                                    │
  │                                │  13. Insert Line Items             │
  │                                ├───────────────────────────────────→│
  │                                │   INSERT INTO                      │
  │                                │   tbl_payment_entry_line_items     │
  │                                │←───────────────────────────────────│
  │                                │                                    │
  │                                │  14. Register Files                │
  │                                ├───────────────────────────────────→│
  │                                │   INSERT INTO                      │
  │                                │   tbl_payment_entry_file_registry  │
  │                                │←───────────────────────────────────│
  │                                │                                    │
  │                                │  15. Insert Summary Totals         │
  │                                ├───────────────────────────────────→│
  │                                │   INSERT INTO                      │
  │                                │   tbl_payment_entry_summary_totals │
  │                                │←───────────────────────────────────│
  │                                │                                    │
  │                                │  16. Insert Audit Log              │
  │                                ├───────────────────────────────────→│
  │                                │   INSERT INTO                      │
  │                                │   tbl_payment_entry_audit_log      │
  │                                │←───────────────────────────────────│
  │                                │                                    │
  │                                │  17. COMMIT Transaction            │
  │  Success Response (JSON)       │                                    │
  │←──────────────────────────────┤  18. All data saved                 │
  │                                │                                    │
  │  19. Close Modal               │                                    │
  │  20. Refresh Dashboard         │                                    │
  │                                │                                    │
  │  21. Display New Entry         │                                    │
  │←──────────────────────────────┤                                    │
  │                                │                                    │
```

---

## 6. DATABASE SCHEMA RELATIONSHIP DIAGRAM

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        DATABASE RELATIONSHIPS                                   │
└─────────────────────────────────────────────────────────────────────────────────┘

                    ┌──────────────────────────────────┐
                    │  users (users table)             │
                    │  ├─ id (PK)                      │
                    │  ├─ username                     │
                    │  └─ status                       │
                    └──────────────────────────────────┘
                            ↑        ↑         ↑
                            │        │         │
                ┌───────────┴────────┴────────┴───────────┐
                │                                         │
                │                                         │
    ┌───────────▼──────────────────────────────┐          │
    │ tbl_payment_entry_master_records (Main)  │          │
    │ ├─ payment_entry_id (PK)                 │          │
    │ ├─ project_type_category                 │          │
    │ ├─ project_name_reference                │          │
    │ ├─ payment_amount_base ← MAIN AMOUNT    │           │
    │ ├─ authorized_user_id_fk (FK→users.id)  ├──────────-┘
    │ ├─ created_by_user_id (FK→users.id)     ├──────────┐
    │ ├─ entry_status_current ← STATUS HERE   │          │
    │ ├─ payment_proof_document_path          │          │
    │ └─ created_timestamp_utc                │          │
    └───────┬──────────────────────────────────┘         │
            │                                            │
            │ 1:Many relationship                        │
            │                                            │
    ┌───────▼──────────────────────────────┐      ┌──────▼──────┐
    │ tbl_payment_acceptance_methods_      │      │ users table │
    │ primary (Main Payment Methods)       │      │ (for user)  │
    │ ├─ acceptance_method_id (PK)         │      └─────────────┘
    │ ├─ payment_entry_id_fk (FK)         │
    │ ├─ payment_method_type               │
    │ ├─ amount_received_value             │
    │ ├─ supporting_document_path          │
    │ └─ recorded_timestamp                │
    └───────┬──────────────────────────────┘
            │
            │ Links to files
            │
    ┌───────▼──────────────────────────────────┐
    │ tbl_payment_entry_file_attachments_      │
    │ registry (All file tracking)             │
    │ ├─ attachment_id (PK)                    │
    │ ├─ payment_entry_id_fk (FK)             │
    │ ├─ attachment_type_category              │
    │ │  ├─ 'proof_image'                      │
    │ │  ├─ 'acceptance_method_media'          │
    │ │  ├─ 'line_item_media'                  │
    │ │  └─ 'line_item_method_media'           │
    │ ├─ attachment_file_stored_path           │
    │ ├─ attachment_integrity_hash (SHA256)    │
    │ └─ uploaded_by_user_id                   │
    └────────────────────────────────────────┘


    ┌───────────────────────────────────────┐
    │ tbl_payment_entry_line_items_detail   │
    │ (Line Items/Recipients)               │
    │ ├─ line_item_entry_id (PK)            │
    │ ├─ payment_entry_id_fk (FK)          │
    │ ├─ recipient_type_category            │
    │ ├─ recipient_id_reference             │
    │ ├─ recipient_name_display             │
    │ ├─ line_item_amount                   │
    │ ├─ line_item_payment_mode             │
    │ ├─ line_item_status ← STATUS HERE     │
    │ └─ line_item_media_upload_path        │
    └───────┬───────────────────────────────┘
            │
            │ 1:Many relationship
            │
    ┌───────▼──────────────────────────────────────┐
    │ tbl_payment_acceptance_methods_               │
    │ line_items (Line Item Payment Methods)        │
    │ ├─ line_item_acceptance_method_id (PK)       │
    │ ├─ line_item_entry_id_fk (FK)               │
    │ ├─ method_type_category                      │
    │ ├─ method_amount_received                    │
    │ └─ method_supporting_media_path              │
    └────────────────────────────────────────────┘


    ┌──────────────────────────────────────┐
    │ tbl_payment_entry_summary_totals      │
    │ (For Reporting)                      │
    │ ├─ summary_id (PK)                   │
    │ ├─ payment_entry_id_fk (FK) [UNIQUE] │
    │ ├─ total_amount_main_payment          │
    │ ├─ total_amount_line_items            │
    │ ├─ total_amount_grand_aggregate       │
    │ ├─ line_items_count                   │
    │ └─ total_files_attached               │
    └──────────────────────────────────────┘


    ┌──────────────────────────────────────┐
    │ tbl_payment_entry_audit_activity_log  │
    │ (Audit Trail)                        │
    │ ├─ audit_log_id (PK)                 │
    │ ├─ payment_entry_id_fk (FK)          │
    │ ├─ audit_action_type                 │
    │ ├─ audit_performed_by_user_id (FK)   │
    │ ├─ audit_ip_address_captured         │
    │ └─ audit_action_timestamp_utc        │
    └──────────────────────────────────────┘


    ┌──────────────────────────────────────────┐
    │ tbl_payment_entry_status_transition_     │
    │ history (Status Changes)                 │
    │ ├─ status_history_id (PK)               │
    │ ├─ payment_entry_id_fk (FK)            │
    │ ├─ status_from_previous                 │
    │ ├─ status_to_current                    │
    │ ├─ status_changed_by_user_id (FK)      │
    │ └─ status_change_timestamp_utc          │
    └──────────────────────────────────────────┘
```

---

## 7. COMPLETE STATUS FLOW DIAGRAM

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                         STATUS TRANSITION WORKFLOW                             │
└────────────────────────────────────────────────────────────────────────────────┘

USER SUBMITS FORM
        │
        ▼
    ╔═══════════════╗
    ║ BACKEND START ║
    ╚═════╤═════════╝
          │
          ▼
    ┌────────────────┐
    │ DRAFT Status   │ ← Initial (not used in current implementation)
    │ (DB field)     │
    └────────────────┘
          │
          ▼
    INSERT INTO tbl_payment_entry_master_records
    entry_status_current = 'submitted' ← **STATUS SET TO SUBMITTED**
          │
          ▼
    INSERT INTO tbl_payment_entry_audit_activity_log
    ├─ audit_action_type = 'created'
    ├─ audit_performed_by_user_id = current_user_id
    └─ audit_action_timestamp_utc = NOW()
          │
          ▼
    COMMIT TRANSACTION
          │
          ▼
    RESPONSE TO USER: SUCCESS
    { success: true, payment_entry_id: 1001 }
          │
          ▼
    MODAL CLOSES
    DASHBOARD REFRESHES
          │
          ▼
    ╔═══════════════════════════════════════════════════════════════════════════╗
    ║                    FRONTEND SHOWS NEW ENTRY                              ║
    ║  Status in Dashboard: SUBMITTED (Awaiting Manager Review)                 ║
    ╚═══════════════════════════════════════════════════════════════════════════╝
          │
          ▼
    MANAGER REVIEWS ENTRY (Not in current system, future feature)
          │
    ┌─────┴─────┬─────────────┬──────────────┐
    ▼           ▼             ▼              ▼
┌─────────┐ ┌─────────┐ ┌──────────┐ ┌──────────────┐
│APPROVED │ │REJECTED │ │ PENDING  │ │IN PROGRESS  │
└─────────┘ └─────────┘ └──────────┘ └──────────────┘
    │           │           │              │
    ├───────────┼───────────┴──────────────┘
    │           │
    ▼           ▼
┌──────────────────────────────────┐   ┌──────────────────────────┐
│INSERT INTO                        │   │INSERT INTO               │
│tbl_payment_entry_approval_        │   │tbl_payment_entry_        │
│records_final                      │   │rejection_reasons_detail  │
│├─ approved_by_user_id            │   │├─ rejected_by_user_id    │
│├─ approval_timestamp_utc         │   │├─ rejection_reason_code  │
│├─ approval_clearance_code        │   │├─ rejection_description  │
│└─ approval_notes_comments        │   │└─ resubmission_requested │
└──────────────────────────────────┘   └──────────────────────────┘
    │                                   │
    ├───────────────────┬───────────────┘
    │                   │
    ▼                   ▼
INSERT INTO tbl_payment_entry_status_transition_history
├─ status_from_previous = 'submitted'
├─ status_to_current = 'approved' or 'rejected'
├─ status_changed_by_user_id
└─ status_change_reason_notes
    │
    ▼
COMMIT TRANSACTION
    │
    ▼
UPDATE DASHBOARD DISPLAY
```

---

## 8. KEY FEATURES VISUALIZATION

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    PAYMENT ENTRY SYSTEM FEATURES                             │
└──────────────────────────────────────────────────────────────────────────────┘

1. HIERARCHICAL PAYMENT STRUCTURE
   ═════════════════════════════════════════════════════════════════════════════
   
   Main Payment (₹100,000)
   │
   ├─ Multiple Acceptance Methods
   │  ├─ Cash: ₹50,000 [Receipt.jpg]
   │  ├─ Cheque: ₹30,000 [Cheque Photo.png] (CHQ12345)
   │  └─ Bank Transfer: ₹20,000 [Receipt.pdf] (TXN987654)
   │
   └─ Line Items (Recipients)
      ├─ Labour - Ram Kumar: ₹60,000
      │  ├─ Cash: ₹30,000
      │  └─ UPI: ₹30,000
      ├─ Material - Steel Supplier: ₹25,000
      │  └─ Bank Transfer: ₹25,000
      └─ Labour Skilled - Electrician: ₹15,000
         └─ Cash: ₹15,000


2. FILE TRACKING SYSTEM
   ═════════════════════════════════════════════════════════════════════════════
   
   Main Proof Image
   ├─ Type: proof_image
   ├─ File: proof_1700000000_a1b2c3d4.jpg
   ├─ Size: 2.5 MB
   ├─ SHA256: a1b2c3d4e5f6g7h8...
   └─ Status: verified ✓
   
   Acceptance Method Media
   ├─ Type: acceptance_method_media
   ├─ Method 1: acceptance_0.jpg (Cash receipt)
   ├─ Method 2: acceptance_1.png (Cheque photo)
   └─ Method 3: acceptance_2.pdf (Bank receipt)
   
   Line Item Media
   ├─ Type: line_item_media
   ├─ Entry 1: entryMedia_0.jpg (Labour invoice)
   ├─ Entry 2: entryMedia_1.pdf (Material receipt)
   └─ Entry 3: entryMedia_2.mp4 (Work video)
   
   Line Item Method Media
   ├─ Type: line_item_method_media
   ├─ Entry 1, Method 1: entryMethodMedia_0_0.jpg
   ├─ Entry 1, Method 2: entryMethodMedia_0_1.jpg
   └─ Entry 2, Method 1: entryMethodMedia_1_0.pdf


3. VALIDATION CASCADE
   ═════════════════════════════════════════════════════════════════════════════
   
   Client-Side Validation
   ├─ All required fields filled? ✓
   ├─ Amount > 0? ✓
   ├─ Proof image uploaded? ✓
   ├─ Line items ≤ main amount? ✓
   └─ Multiple acceptance methods valid? ✓
       │
       ▼ (Pass all checks)
   Send FormData to Backend
       │
       ▼
   Server-Side Validation
   ├─ User authenticated? ✓
   ├─ File type valid (JPG/PNG/PDF)? ✓
   ├─ File size ≤ limits? ✓
   ├─ Line items ≤ main amount (Final check)? ✓
   └─ Database writable? ✓
       │
       ▼ (Pass all checks)
   Process and Save


4. AUDIT TRAIL TRACKING
   ═════════════════════════════════════════════════════════════════════════════
   
   Who: user_id (created_by_user_id, audit_performed_by_user_id)
   When: timestamp (created_timestamp_utc, audit_action_timestamp_utc)
   What: action_type (created, updated, submitted, approved, rejected)
   Where: ip_address (audit_ip_address_captured)
   With What: user_agent (audit_user_agent_info)
   Why: reason_notes (status_change_reason_notes, rejection_description)
```

---

## Summary: Complete User Journey

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                          COMPLETE USER JOURNEY                            ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

PHASE 1: ENTRY
├─ Manager logs into dashboard
├─ Clicks "Add Payment Entry"
└─ Modal form opens

PHASE 2: FORM FILLING
├─ Selects project type → projects load
├─ Selects specific project
├─ Enters payment date & amount
├─ Selects authorized user
├─ Selects payment mode
├─ Uploads proof image
├─ [Optional] Adds multiple acceptance methods
├─ [Optional] Adds line items with recipients
└─ [Optional] Adds multiple methods per line item

PHASE 3: SUBMISSION
├─ Client validates all inputs
├─ Collects all data into FormData
├─ Sends to backend via FETCH
└─ Shows loading state

PHASE 4: BACKEND PROCESSING
├─ Authenticates user
├─ Begins database transaction
├─ Processes proof image → uploads to server
├─ Inserts main payment entry (status = 'submitted')
├─ Inserts acceptance methods (if multiple acceptance)
├─ Inserts line items (if any)
├─ Processes line item methods (if applicable)
├─ Registers all files with SHA256 hashes
├─ Calculates and validates totals
├─ Inserts summary totals
├─ Inserts audit log
├─ Commits transaction
└─ Returns success response with payment_entry_id

PHASE 5: FRONTEND CONFIRMATION
├─ Modal closes
├─ Success message displays
├─ Dashboard refreshes
├─ New entry visible in "Recently Added Records"
├─ Entry shows:
│  ├─ Project name
│  ├─ Recipients paid to
│  ├─ Payment date
│  ├─ Amount (₹)
│  ├─ Payment mode
│  ├─ File count
│  └─ Actions (View, Edit, Delete)
└─ Status: SUBMITTED (Awaiting Manager Review)

PHASE 6: FUTURE OPERATIONS [To be implemented]
├─ Manager reviews entry
├─ Can approve or reject
├─ If rejected: reason stored, can resubmit
├─ If approved: clearance code generated
├─ Payment processed
└─ Status history tracked in audit logs

PHASE 7: REPORTS & ANALYTICS
├─ Dashboard filters by status, date, project type
├─ Summary totals used for reporting
├─ Audit logs provide full accountability
└─ File registry ensures document management
```

