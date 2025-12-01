<?php
/**
 * VENDOR PAYMENT RECORDS IMPLEMENTATION
 * 
 * Implementation Summary:
 * This implementation fetches payment records for vendors from the payment entry tables
 * and displays them in the vendor details modal's Payment Records section.
 * 
 * FILES CREATED/MODIFIED:
 * 1. fetch_vendor_payment_records.php - Backend API to fetch payment records
 * 2. includes/vendor_payment_records_integration.js - Frontend integration
 * 3. modals/vendor_details_modal.php - Updated to include payment records
 * 
 * DATABASE TABLES USED:
 * - tbl_payment_entry_master_records (main payment entries)
 * - tbl_payment_entry_line_items_detail (line items with vendor/labour references)
 * - tbl_payment_acceptance_methods_primary (main payment methods)
 * - tbl_payment_acceptance_methods_line_items (line item payment methods)
 * - tbl_payment_entry_summary_totals (payment totals)
 * - pm_vendor_registry_master (vendor master data)
 */

// ============================================================================
// IMPLEMENTATION OVERVIEW
// ============================================================================

/**
 * FLOW:
 * 
 * 1. USER ACTION:
 *    - User clicks eye icon to view vendor details
 *    - viewVendor(vendorId) is called
 *    - openVendorDetailsModal(vendorId) opens the modal
 * 
 * 2. MODAL LOADS:
 *    - Modal displays with loading spinner
 *    - fetch_vendor_details.php API is called
 *    - Vendor data is displayed in all sections
 * 
 * 3. PAYMENT RECORDS INJECTION:
 *    - After vendor details are displayed (setTimeout 100ms)
 *    - Payment Records section container is detected
 *    - injectPaymentRecordsIntoModal() is called with vendor_id
 * 
 * 4. FETCH PAYMENT RECORDS:
 *    - fetch_vendor_payment_records.php is called with vendor_id
 *    - Backend queries multiple tables:
 *      a. Check line items where vendor is recipient
 *      b. Check main payments where vendor is authorized/creator
 *      c. Fetch acceptance methods for each payment
 *      d. Return JSON with all payment data
 * 
 * 5. DISPLAY PAYMENT RECORDS:
 *    - Frontend generates HTML table
 *    - Shows: Date, Project, Amount, Mode, Status
 *    - Displays acceptance methods as sub-rows
 *    - Color-coded status badges
 *    - Hover effects on rows
 * 
 * 6. USER INTERACTION:
 *    - User can expand/collapse Payment Records section
 *    - Can view payment methods and amounts
 *    - Can see payment status at a glance
 */

// ============================================================================
// API ENDPOINT
// ============================================================================

/**
 * fetch_vendor_payment_records.php
 * 
 * URL: fetch_vendor_payment_records.php?vendor_id={id}
 * Method: GET
 * 
 * Response Format:
 * {
 *     "success": true,
 *     "data": [
 *         {
 *             "payment_entry_id": 1,
 *             "project_type_category": "Architecture",
 *             "project_name_reference": "Project ABC",
 *             "payment_date_logged": "2025-11-29",
 *             "payment_amount_base": 50000,
 *             "payment_mode_selected": "Bank Transfer",
 *             "entry_status_current": "approved",
 *             "line_item_entry_id": 1,
 *             "recipient_type_category": "Material Vendor",
 *             "recipient_name_display": "ABC Corporation",
 *             "line_item_amount": 25000,
 *             "acceptance_methods": [
 *                 {
 *                     "method_type": "Bank Transfer",
 *                     "amount": 25000,
 *                     "reference": "TXN-123456",
 *                     "media_path": "/uploads/proof.pdf",
 *                     "recorded_at": "2025-11-29 10:30:00"
 *                 }
 *             ]
 *         }
 *     ],
 *     "count": 1,
 *     "message": "Payment records found"
 * }
 */

// ============================================================================
// QUERY LOGIC
// ============================================================================

/**
 * PRIMARY QUERY: Fetch line items where vendor is recipient
 * 
 * SELECT m.payment_entry_id, m.project_name_reference, m.payment_date_logged, 
 *        l.line_item_amount, l.recipient_name_display, ...
 * FROM tbl_payment_entry_master_records m
 * LEFT JOIN tbl_payment_entry_line_items_detail l 
 *    ON m.payment_entry_id = l.payment_entry_master_id_fk
 * WHERE l.recipient_id_reference = ? 
 *   AND l.recipient_type_category LIKE '%Vendor%'
 * 
 * This finds all line items where this vendor is listed as recipient
 */

/**
 * ACCEPTANCE METHODS: For each line item, fetch payment methods
 * 
 * SELECT method_type_category, method_amount_received, 
 *        method_reference_identifier, method_supporting_media_path, ...
 * FROM tbl_payment_acceptance_methods_line_items
 * WHERE line_item_entry_id_fk = ?
 * 
 * Shows how payment was made (Cash, Cheque, Bank Transfer, etc.)
 */

/**
 * FALLBACK QUERY: If no line items found
 * 
 * Check if vendor is authorized user or created payment entry
 * Useful for vendors who submitted payment entries
 */

// ============================================================================
// DATABASE STRUCTURE MAPPING
// ============================================================================

/**
 * pm_vendor_registry_master:
 * - vendor_id (PK)
 * - vendor_full_name
 * - vendor_email_address
 * - vendor_phone_primary
 * - vendor_status
 * - ... (other vendor fields)
 * 
 * tbl_payment_entry_master_records:
 * - payment_entry_id (PK)
 * - project_name_reference
 * - payment_amount_base
 * - payment_date_logged
 * - payment_mode_selected
 * - entry_status_current (draft, submitted, approved, rejected, pending)
 * - authorized_user_id_fk (can link to vendor if vendor is a user)
 * - created_by_user_id (can link to vendor if vendor is a user)
 * 
 * tbl_payment_entry_line_items_detail:
 * - line_item_entry_id (PK)
 * - payment_entry_master_id_fk (FK to master)
 * - recipient_id_reference (FK to vendor, labour, supplier, etc.)
 * - recipient_type_category (e.g., "Material Vendor", "Labour Vendor")
 * - line_item_amount
 * - payment_description_notes
 * - line_item_payment_mode
 * - line_item_status
 * 
 * tbl_payment_acceptance_methods_line_items:
 * - line_item_acceptance_method_id (PK)
 * - line_item_entry_id_fk (FK to line item)
 * - method_type_category (Cash, Cheque, Bank Transfer, Online, UPI)
 * - method_amount_received
 * - method_reference_identifier (cheque no., transaction ID, etc.)
 * - method_supporting_media_path (attachment)
 * 
 * tbl_payment_entry_summary_totals:
 * - summary_id (PK)
 * - payment_entry_master_id_fk (1-to-1 with master)
 * - total_amount_grand_aggregate
 * - acceptance_methods_count
 * - line_items_count
 */

// ============================================================================
// JAVASCRIPT FUNCTIONS
// ============================================================================

/**
 * fetchVendorPaymentRecords(vendorId)
 * - Calls fetch_vendor_payment_records.php
 * - Returns Promise with payment records
 */

/**
 * generatePaymentRecordsHTML(paymentRecords)
 * - Creates HTML table from payment records
 * - Shows: Date, Project, Amount, Mode, Status
 * - Includes acceptance methods details
 * - Color-coded status badges
 */

/**
 * getStatusBadgeClass(status)
 * - Maps status to CSS class
 * - Styles: draft, submitted, pending, approved, rejected
 */

/**
 * injectPaymentRecordsIntoModal(vendorId, container)
 * - Fetches payment records
 * - Generates HTML
 * - Injects into payment section container
 * - Shows loading state while fetching
 */

// ============================================================================
// INTEGRATION WITH MODAL
// ============================================================================

/**
 * Changes made to vendor_details_modal.php:
 * 
 * 1. Added data-payment-section-container attribute to payment grid
 * 2. Updated openVendorDetailsModal() to call injectPaymentRecordsIntoModal()
 * 3. Added <script> tag to include vendor_payment_records_integration.js
 * 4. Payment Records section shows:
 *    - Loading spinner initially
 *    - Table with payment data once loaded
 *    - Empty state if no records
 */

// ============================================================================
// FEATURES
// ============================================================================

/**
 * DISPLAY FEATURES:
 * ✓ Payment date in Indian date format (DD MMM, YYYY)
 * ✓ Project name as primary display, type as secondary
 * ✓ Amount formatted with currency symbol (₹)
 * ✓ Payment mode (Cash, Cheque, Bank Transfer, etc.)
 * ✓ Status with color-coded badges
 * ✓ Expandable acceptance methods
 * ✓ Hover effects on rows
 * ✓ Responsive table layout
 * 
 * DATA SOURCES:
 * ✓ Multiple payment entry line items
 * ✓ Multiple payment acceptance methods
 * ✓ Payment totals and summaries
 * ✓ Full audit trail compatibility
 */

// ============================================================================
// ERROR HANDLING
// ============================================================================

/**
 * - Session validation (401 if not logged in)
 * - Vendor ID validation (400 if missing)
 * - Database connection errors (500)
 * - No records found (shows empty state)
 * - Failed fetch (shows error in container)
 */

// ============================================================================
// PERFORMANCE
// ============================================================================

/**
 * - Queries are optimized with JOINs
 * - Indexes on foreign keys for fast lookup
 * - Limit 100 records (configurable)
 * - Async loading doesn't block modal display
 * - Payment records load in background
 */

// ============================================================================
// FUTURE ENHANCEMENTS
// ============================================================================

/**
 * 1. Add filtering by status (approved, pending, rejected)
 * 2. Add date range filter
 * 3. Add export payment history to PDF
 * 4. Add payment summary statistics
 * 5. Add drill-down to full payment entry details
 * 6. Add payment method breakdown chart
 * 7. Add sort by date, amount, status
 */

?>
