# Excel Export - Visual Workflow & Architecture

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                    WEB BROWSER (Client Side)                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Purchase Manager Dashboard (purchase_manager_dashboard.php)       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ Recently Added Records Section                              │   │
│  ├─────────────────────────────────────────────────────────────┤   │
│  │ From Date: [2025-01-01]  To Date: [2025-12-31]             │   │
│  │                                                              │   │
│  │ [✓ Apply] [✗ Reset] [📊 Export to Excel] ← Button          │   │
│  │                            ↓ onclick event                  │   │
│  │                                                              │   │
│  │ JavaScript Handler:                                         │   │
│  │ 1. Get dateFrom & dateTo from inputs                       │   │
│  │ 2. Validate dates (From < To)                              │   │
│  │ 3. Build URL params (encodeURIComponent)                   │   │
│  │ 4. Show loading state ("Exporting...")                     │   │
│  │ 5. window.location.href = "export_payment_entries_..."    │   │
│  │                                                              │   │
│  │                    ↓ HTTP GET Request                       │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
└──────────────────┬──────────────────────────────────────────────────┘
                   │
                   │ GET /export_payment_entries_excel.php?dateFrom=...&dateTo=...
                   │
┌──────────────────▼──────────────────────────────────────────────────┐
│                  WEB SERVER (Apache/PHP)                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  export_payment_entries_excel.php                                   │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                                                              │   │
│  │  1. SESSION CHECK                                          │   │
│  │     ├─ Verify $_SESSION['user_id']                         │   │
│  │     └─ Return 401 if not authenticated                     │   │
│  │                                                              │   │
│  │  2. PARAMETER RETRIEVAL                                    │   │
│  │     ├─ Get dateFrom from $_GET                            │   │
│  │     └─ Get dateTo from $_GET                              │   │
│  │                                                              │   │
│  │  3. DATA FETCHING (3 Database Queries)                     │   │
│  │     ├─ Query 1: Main Payments                             │   │
│  │     │  SELECT m.*, u.username, s.totals                   │   │
│  │     │  FROM tbl_payment_entry_master_records m             │   │
│  │     │  LEFT JOIN users u ...                              │   │
│  │     │  LEFT JOIN tbl_payment_entry_summary_totals s ...    │   │
│  │     │  WHERE payment_date BETWEEN :dateFrom AND :dateTo    │   │
│  │     │                                                       │   │
│  │     ├─ Query 2: Line Items (Batch)                        │   │
│  │     │  SELECT * FROM tbl_payment_entry_line_items_detail   │   │
│  │     │  WHERE payment_entry_master_id IN (...)             │   │
│  │     │                                                       │   │
│  │     └─ Query 3: Acceptance Methods (Batch)                │   │
│  │        SELECT * FROM tbl_payment_acceptance_methods_primary│   │
│  │        WHERE payment_entry_id_fk IN (...)                 │   │
│  │                                                              │   │
│  │  4. DATA GROUPING                                          │   │
│  │     ├─ Group line items by payment ID                     │   │
│  │     └─ Group acceptance methods by payment ID             │   │
│  │                                                              │   │
│  │  5. HTML GENERATION                                        │   │
│  │     ├─ Header with date range info                        │   │
│  │     ├─ For each payment:                                  │   │
│  │     │  ├─ Payment main section (colored)                  │   │
│  │     │  ├─ Line items rows (colored)                       │   │
│  │     │  └─ Acceptance methods rows (colored)               │   │
│  │     └─ Footer with summary totals                         │   │
│  │                                                              │   │
│  │  6. FILENAME GENERATION                                    │   │
│  │     ├─ timestamp = date('YmdHis')  [20251125143025]       │   │
│  │     ├─ random = bin2hex(random_bytes(4))  [a7f2b4c3]      │   │
│  │     └─ filename = 'PaymentExport_' . timestamp . '_'       │   │
│  │                    . random . '.xls'                       │   │
│  │                                                              │   │
│  │  7. HEADERS & DOWNLOAD                                     │   │
│  │     ├─ Content-Type: application/vnd.ms-excel             │   │
│  │     ├─ Content-Disposition: attachment; filename=...      │   │
│  │     ├─ Cache-Control: must-revalidate                     │   │
│  │     └─ Output HTML content                                │   │
│  │                                                              │   │
│  │  8. ERROR HANDLING                                         │   │
│  │     ├─ Try-Catch block wraps everything                   │   │
│  │     ├─ Log errors to error_log()                          │   │
│  │     └─ Return appropriate HTTP status codes               │   │
│  │                                                              │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
└──────────────────┬──────────────────────────────────────────────────┘
                   │
                   │ HTTP Response + File Content
                   │ Content-Type: application/vnd.ms-excel
                   │ Content-Disposition: attachment; filename="PaymentExport_..."
                   │
┌──────────────────▼──────────────────────────────────────────────────┐
│                       WEB BROWSER (Download)                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  File Download Dialog Appears                                      │
│  ┌──────────────────────────────────────────────────────┐         │
│  │ File: PaymentExport_20251125143025_a7f2b4c3.xls     │         │
│  │ Size: 45 KB                                          │         │
│  │ From: localhost                                      │         │
│  │                                                       │         │
│  │ [Save] [Cancel]                                     │         │
│  └──────────────────────────────────────────────────────┘         │
│                                                                      │
│  File Saved to: ~/Downloads/PaymentExport_*.xls                    │
│                                                                      │
│  User opens file in Excel → Colorful payment data displayed       │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📊 Data Flow Diagram

```
┌─────────────────────────────────┐
│   Database Tables               │
├─────────────────────────────────┤
│                                 │
│  tbl_payment_entry_             │
│  master_records                 │
│  ├─ payment_entry_id (PK)      │
│  ├─ project_name               │
│  ├─ payment_amount_base        │
│  ├─ payment_date_logged        │
│  ├─ payment_mode_selected      │
│  ├─ entry_status_current       │
│  └─ authorized_user_id_fk      │
│         ↓ FK                    │
│         │                       │
│    users Table                  │
│    ├─ id (PK)                 │
│    └─ username                │
│         ↑ FK                    │
│         │                       │
│  ┌──────┴──────┐               │
│  │             │               │
│  ├─ tbl_payment_entry_         │
│  │  summary_totals             │
│  │  ├─ summary_id (PK)        │
│  │  ├─ payment_entry_id_fk    │
│  │  ├─ total_amount_main...   │
│  │  ├─ acceptance_methods_cnt │
│  │  ├─ line_items_count       │
│  │  └─ total_files_attached   │
│  │         ↑                    │
│  │         │ FK                 │
│  ├─ tbl_payment_entry_         │
│  │  line_items_detail          │
│  │  ├─ line_item_entry_id(PK) │
│  │  ├─ payment_entry_master_id│
│  │  ├─ recipient_name         │
│  │  ├─ line_item_amount       │
│  │  └─ line_item_status       │
│  │         ↑ FK                │
│  └─ tbl_payment_acceptance_    │
│     methods_primary            │
│     ├─ acceptance_method_id    │
│     ├─ payment_entry_id_fk     │
│     ├─ payment_method_type     │
│     ├─ amount_received_value   │
│     └─ reference_number_cheque │
│                                 │
└─────────────────────────────────┘
        ↓ SELECT Queries (3)
        │
┌─────────────────────────────────┐
│   PHP Processing                │
├─────────────────────────────────┤
│                                 │
│ ┌──────────────────────────────┐│
│ │ Fetch All Payments           ││
│ │ (with date filtering)        ││
│ └──────────────────────────────┘│
│          ↓                       │
│ ┌──────────────────────────────┐│
│ │ Extract Payment IDs          ││
│ │ $paymentIds = array_column()  ││
│ └──────────────────────────────┘│
│          ↓                       │
│ ┌──────────────────────────────┐│
│ │ Fetch Line Items for All IDs ││
│ │ WHERE payment_id IN (...)     ││
│ └──────────────────────────────┘│
│          ↓                       │
│ ┌──────────────────────────────┐│
│ │ Fetch Acceptance Methods     ││
│ │ WHERE payment_id IN (...)     ││
│ └──────────────────────────────┘│
│          ↓                       │
│ ┌──────────────────────────────┐│
│ │ Group Data by Payment ID      ││
│ │ [paymentId] => [line items]   ││
│ │ [paymentId] => [methods]      ││
│ └──────────────────────────────┘│
│          ↓                       │
│ ┌──────────────────────────────┐│
│ │ Generate HTML Table           ││
│ │ with Colors & Styling        ││
│ └──────────────────────────────┘│
│                                 │
└─────────────────────────────────┘
        ↓ HTML Output
        │
┌─────────────────────────────────┐
│   Excel File Generation         │
├─────────────────────────────────┤
│                                 │
│ HTML String ──→ Set Headers    │
│                │                │
│                ├─ Content-Type │
│                ├─ Disposition  │
│                └─ Filename     │
│                │                │
│                └──→ Output HTML │
│                     to Browser  │
│                                 │
└─────────────────────────────────┘
        ↓ Browser Download
        │
    File Saved
```

---

## 🎨 Excel File Structure

```
┌────────────────────────────────────────────────────────────────┐
│  PAYMENT ENTRIES EXPORT REPORT                 [Dark Blue #1a365d]
├────────────────────────────────────────────────────────────────┤
│  Date Range: 01-Jan-2025 to 31-Dec-2025 | Total: 25 | Gen: ... [Light Blue]
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ╔══════════════════════════════════════════════════════════╗ │
│  ║ Payment Entry #1 - Project ABC                [Blue]     ║ │
│  ╠══════════════════════════════════════════════════════════╣ │
│  ║ Project Type: Architecture | Date: 25-Nov-2025         [Light Blue] ║ │
│  ║ Mode: Bank Transfer | Status: APPROVED                 [Light Blue] ║ │
│  ║ Main Amount: ₹50,000 | Grand Total: ₹50,000            [Light Blue] ║ │
│  ╠══════════════════════════════════════════════════════════╣ │
│  ║ LINE ITEMS (2)                             [Green #48bb78] ║ │
│  ╠──────────────────────────────────────────────────────────╣ │
│  ║ Item #1: ABC Vendor           | Steel | ₹30,000 [Light Green] ║ │
│  ║ Item #2: XYZ Labour           | Labour| ₹20,000 [Light Green] ║ │
│  ║ Subtotal: ₹50,000                                 [Gold] ║ │
│  ╠══════════════════════════════════════════════════════════╣ │
│  ║ PAYMENT METHODS (2)                      [Orange #ed8936] ║ │
│  ╠──────────────────────────────────────────────────────────╣ │
│  ║ Method #1: Bank Transfer | ₹30,000 | TXN#123... [Light Orange] ║ │
│  ║ Method #2: Cheque       | ₹20,000 | CHQ#001...  [Light Orange] ║ │
│  ║ Total: ₹50,000                                  [Gold] ║ │
│  ╚══════════════════════════════════════════════════════════╝ │
│                                                                │
│  [Repeat for each payment entry...]                           │
│                                                                │
├────────────────────────────────────────────────────────────────┤
│  SUMMARY: 25 Entries | 45 Line Items | 50 Methods [Dark Blue]│
│  GRAND TOTAL: ₹1,250,000                        [Gold/Bold]  │
└────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Request/Response Cycle

```
REQUEST (Browser → Server):
────────────────────────────────────────
GET /export_payment_entries_excel.php HTTP/1.1
Host: localhost
Cookie: PHPSESSID=abc123...
?dateFrom=2025-01-01&dateTo=2025-12-31


PROCESSING (Server):
────────────────────────────────────────
1. Check: isset($_SESSION['user_id'])
   └─ If no: STOP, return 401

2. Get: $dateFrom = '2025-01-01'
   Get: $dateTo = '2025-12-31'

3. Query 1: 
   SELECT * FROM payments WHERE date BETWEEN ...

4. Query 2:
   SELECT * FROM line_items WHERE payment_id IN (...)

5. Query 3:
   SELECT * FROM methods WHERE payment_id IN (...)

6. Build: HTML table with colors

7. Generate: PaymentExport_20251125143025_a7f2b4c3.xls

8. Set headers and output


RESPONSE (Server → Browser):
────────────────────────────────────────
HTTP/1.1 200 OK
Content-Type: application/vnd.ms-excel; charset=UTF-8
Content-Disposition: attachment; filename="PaymentExport_20251125143025_a7f2b4c3.xls"
Cache-Control: must-revalidate, post-check=0, pre-check=0
Pragma: public
Content-Length: 45823

[HTML content as Excel file]


DOWNLOAD (Browser):
────────────────────────────────────────
File Dialog: Save As
Default Name: PaymentExport_20251125143025_a7f2b4c3.xls
Location: ~/Downloads/
File Saved ✓
```

---

## 🔐 Security Flow

```
User Action → Validation Chain
│
├─ Authentication Check
│  ├─ Check: isset($_SESSION['user_id'])
│  ├─ Result: Authenticated ✓
│  └─ Proceed: YES
│
├─ Input Validation
│  ├─ Check: dateFrom format (YYYY-MM-DD)
│  ├─ Check: dateTo format (YYYY-MM-DD)
│  ├─ Check: dateFrom <= dateTo
│  └─ Result: Valid ✓
│
├─ SQL Injection Protection
│  ├─ Method: PDO Prepared Statements
│  ├─ Parameter Binding: :dateFrom, :dateTo
│  ├─ Execution: execute([':dateFrom' => $value])
│  └─ Result: Safe ✓
│
├─ XSS Protection
│  ├─ All output: htmlspecialchars()
│  ├─ User data: htmlspecialchars($payment['name'])
│  └─ Result: Safe ✓
│
├─ Error Handling
│  ├─ Try-Catch: wraps entire operation
│  ├─ Logging: error_log('...')
│  ├─ Response: Generic error message
│  └─ Result: No info leakage ✓
│
└─ File Download
   ├─ Header: Content-Disposition: attachment
   ├─ Type: application/vnd.ms-excel
   └─ Result: File download, not execution ✓
```

---

## 📈 Performance Timeline

```
Action: Click Export Button
│
├─ T+0ms: JavaScript executes
│         Get date inputs
│         Validate dates
│
├─ T+10ms: Build URL with parameters
│          Show loading state
│
├─ T+20ms: Send HTTP GET request
│
├─ T+50ms: Server: Check session
│          PASS
│
├─ T+100ms: Server: Query 1 - Fetch payments
│           (Query time depends on data size)
│
├─ T+150ms: Server: Query 2 - Fetch line items
│           (Query time depends on data size)
│
├─ T+200ms: Server: Query 3 - Fetch methods
│           (Query time depends on data size)
│
├─ T+250ms: Server: Group data in PHP
│           Build HTML string
│
├─ T+300ms: Server: Set headers
│           Output HTML content
│
├─ T+350ms: Browser: Receive response
│           Recognize as Excel file
│
├─ T+400ms: Browser: Show save dialog
│
├─ T+450ms: User clicks "Save"
│           File saved to disk
│
└─ T+500ms: Complete
           File available in Downloads folder
```

---

## 🎯 Key Decision Points

```
┌─ User clicks "Export to Excel"
│
├─ Decision 1: Are dates provided?
│  ├─ YES: Use WHERE clause with BETWEEN
│  └─ NO: Fetch all records
│
├─ Decision 2: Is user authenticated?
│  ├─ YES: Proceed with export
│  └─ NO: Return 401 error
│
├─ Decision 3: Are there any records?
│  ├─ YES: Generate Excel file
│  └─ NO: Return error message
│
├─ Decision 4: Generate unique filename?
│  ├─ TIMESTAMP: Milliseconds since epoch
│  ├─ RANDOM: Random 8-char hex string
│  └─ RESULT: Virtually unique filename
│
└─ Decision 5: Send as download?
   ├─ YES: Set download headers
   └─ Browser saves file to disk
```

---

**Diagram Version:** 1.0  
**Last Updated:** 25-Nov-2025  
**Created For:** Payment Entries Excel Export System
