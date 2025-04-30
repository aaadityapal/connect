<?php
// This file contains the modal code for viewing site update details
?>

<!-- Site Update Details Modal -->
<div class="update-details-modal" id="siteUpdateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-clipboard-list"></i> <span id="modal-site-name">Site Update Details</span></h2>
            <div class="modal-actions">
                <button type="button" class="btn btn-primary edit-update-btn" onclick="openEditUpdateForm()">
                    <i class="fas fa-edit"></i> Edit Update
                </button>
                <button type="button" class="modal-close" onclick="closeSiteUpdateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="modal-body">
            <!-- Main site update info -->
            <div class="update-info-section">
                <div class="update-meta">
                    <div class="meta-item"><i class="far fa-calendar-alt"></i> <span id="modal-update-date"></span></div>
                    <div class="meta-item"><i class="far fa-user"></i> <span id="modal-created-by"></span></div>
                </div>
            </div>

            <!-- Vendors and Laborers Section -->
            <div class="modal-section" id="vendors-section">
                <h3><i class="fas fa-truck-loading"></i> Vendors & Contractors</h3>
                <div class="vendors-container" id="modal-vendors">
                    <!-- Vendors and laborers will be loaded here -->
                    <div class="loading">Loading vendors...</div>
                </div>
            </div>

            <!-- Company Laborers Section -->
            <div class="modal-section" id="company-laborers-section">
                <h3><i class="fas fa-hard-hat"></i> Company Laborers</h3>
                <div class="company-laborers-container" id="modal-company-laborers">
                    <!-- Company laborers will be loaded here -->
                    <div class="loading">Loading company laborers...</div>
                </div>
            </div>

            <!-- Expenses Section -->
            <div class="modal-section" id="expenses-section">
                <h3><i class="fas fa-money-bill-wave"></i> Expenses</h3>
                
                <!-- Travel Expenses Sub-section -->
                <div class="expense-subsection">
                    <h4><i class="fas fa-route"></i> Travel Expenses</h4>
                    <div class="travel-expenses-container" id="modal-travel-expenses">
                        <!-- Travel expenses will be loaded here -->
                        <div class="loading">Loading travel expenses...</div>
                    </div>
                </div>
                
                <!-- Beverages Sub-section -->
                <div class="expense-subsection">
                    <h4><i class="fas fa-coffee"></i> Refreshments</h4>
                    <div class="beverages-container" id="modal-beverages">
                        <!-- Beverages will be loaded here -->
                        <div class="loading">Loading refreshments...</div>
                    </div>
                </div>
                
                <div class="total-amount-wrapper">
                    <div class="total-value">
                        <label><i class="fas fa-rupee-sign"></i> Grand Total:</label>
                        <div class="grand-total" id="modal-grand-total">₹0.00</div>
                    </div>
                </div>
            </div>

            <!-- Work Progress Section -->
            <div class="modal-section" id="work-progress-section">
                <h3><i class="fas fa-chart-line"></i> Work Progress</h3>
                <div class="work-progress-container" id="modal-work-progress">
                    <!-- Work progress items will be loaded here -->
                    <div class="loading">Loading work progress...</div>
                </div>
            </div>

            <!-- Inventory Section -->
            <div class="modal-section" id="inventory-section">
                <h3><i class="fas fa-warehouse"></i> Inventory</h3>
                
                <div class="inventory-categories">
                    <span class="inventory-category received"><i class="fas fa-arrow-circle-down"></i> Received</span>
                    <span class="inventory-category available"><i class="fas fa-box"></i> Available</span>
                    <span class="inventory-category consumed"><i class="fas fa-arrow-circle-up"></i> Consumed</span>
                </div>
                
                <div class="inventory-container" id="modal-inventory">
                    <!-- Inventory items will be loaded here -->
                    <div class="loading">Loading inventory...</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSiteUpdateModal()">Close</button>
            <a href="#" id="view-full-details-link" class="btn btn-primary">
                <i class="fas fa-external-link-alt"></i> Full Details
            </a>
        </div>
    </div>
</div>

<!-- Make sure SweetAlert2 is included -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<!-- Additional styling for the media viewer -->
<style>
/* Ensure SweetAlert2 appears above the modal */
.swal2-container {
    z-index: 10000 !important;
}

/* Style the image container in SweetAlert2 */
.swal2-popup.swal2-modal {
    padding: 1.5em;
    background-color: rgba(255, 255, 255, 0.95);
    max-width: 95%;
    width: auto !important;
}

/* Make the image preview larger */
.media-viewer-popup {
    width: auto !important;
    max-width: 800px !important;
}

.media-viewer-image {
    max-width: 100% !important;
    max-height: 80vh !important;
    object-fit: contain !important;
}

/* Make sure SweetAlert backdrop is above the modal */
.swal2-backdrop-show {
    z-index: 9999 !important;
}

/* Add close button styling */
.swal2-close {
    position: absolute !important;
    top: 10px !important;
    right: 10px !important;
    background-color: rgba(255, 255, 255, 0.7) !important;
    border-radius: 50% !important;
    width: 36px !important;
    height: 36px !important;
    font-size: 1.7em !important;
    color: #666 !important;
}

/* Modal header actions styling */
.update-details-modal .modal-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.update-details-modal .edit-update-btn {
    background-color: #4a76a8;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.2s ease;
}

.update-details-modal .edit-update-btn:hover {
    background-color: #3d6593;
}

.update-details-modal .edit-update-btn i {
    font-size: 14px;
}

.update-details-modal .modal-close {
    background: transparent;
    border: none;
    color: #6c757d;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

/* Enhanced responsive styling for all screen sizes */
@media screen and (max-width: 1200px) {
    .update-details-modal .modal-content {
        max-width: 95%;
    }
}

@media screen and (max-width: 992px) {
    .update-details-modal .modal-content {
        max-width: 98%;
        margin: 20px auto;
    }
    
    .update-details-modal .modal-body {
        padding: 18px;
    }
    
    .update-details-modal .modal-section {
        padding: 15px;
    }
    
    /* Adjust laborer grid for medium screens */
    .update-details-modal .laborer-header,
    .update-details-modal .laborer-subheader,
    .update-details-modal .laborer-item {
        grid-template-columns: 1.2fr 1.2fr 1.2fr 1.5fr 1fr;
    }
}

@media screen and (max-width: 768px) {
    .update-details-modal .modal-content {
        margin: 10px auto;
        width: 98%;
    }
    
    .update-details-modal .modal-header {
        padding: 15px;
    }
    
    .update-details-modal .modal-title {
        font-size: 1.2rem;
    }
    
    .update-details-modal .modal-body {
        padding: 15px;
        max-height: 75vh;
    }
    
    .update-details-modal .modal-footer {
        padding: 12px 15px;
    }
    
    .update-details-modal .update-meta {
        flex-direction: column;
        gap: 10px;
    }
    
    .update-details-modal .expense-subsection {
        padding: 12px;
    }
    
    /* Vendor and laborer adjustments for tablets */
    .update-details-modal .vendor-header,
    .update-details-modal .vendor-contact {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .update-details-modal .vendor-type {
        align-self: flex-start;
    }
    
    /* Adjust laborer grid for tablets */
    .update-details-modal .laborer-header,
    .update-details-modal .laborer-subheader,
    .update-details-modal .laborer-item {
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
    }
    
    .update-details-modal .laborer-name-title,
    .update-details-modal .laborer-attendance-title,
    .update-details-modal .laborer-wages-title {
        grid-column: auto;
    }
    
    .update-details-modal .laborer-overtime-title,
    .update-details-modal .laborer-amount-title {
        display: none;
    }
    
    .update-details-modal .laborer-overtime,
    .update-details-modal .laborer-amount {
        grid-column: span 3;
        margin-top: 10px;
        background-color: #f8f9fa;
        padding: 8px;
        border-radius: 4px;
    }

    /* Add heading indicators for tablets */
    .update-details-modal .attendance-badge::before {
        content: "";
        display: none;
    }

    .update-details-modal .attendance-P.day::before {
        content: "Day: ";
        font-weight: 500;
        margin-right: 5px;
    }

    .update-details-modal .attendance-P.night::before {
        content: "Night: ";
        font-weight: 500;
        margin-right: 5px;
    }

    .update-details-modal .wages-per-day::before {
        content: "Per Day: ";
        font-weight: 500;
        margin-right: 5px;
        display: inline;
    }

    .update-details-modal .day-total::before {
        content: "Day Total: ";
        font-weight: 500;
        margin-right: 5px;
        display: inline;
    }
}

@media screen and (max-width: 576px) {
    .update-details-modal .modal-content {
        margin: 5px auto;
        width: 99%;
    }
    
    .update-details-modal .modal-header {
        padding: 12px;
    }
    
    .update-details-modal .modal-title {
        font-size: 1.1rem;
    }
    
    .update-details-modal .modal-body {
        padding: 12px;
        max-height: 80vh;
    }
    
    .update-details-modal .modal-section {
        padding: 12px;
        margin-bottom: 15px;
    }
    
    .update-details-modal .modal-section h3 {
        font-size: 1rem;
    }
    
    .update-details-modal .expense-subsection h4 {
        font-size: 0.9rem;
    }
    
    .update-details-modal .btn {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .inventory-categories {
        flex-direction: column;
        gap: 5px;
    }
    
    .update-details-modal .expense-item,
    .update-details-modal .vendor-item,
    .update-details-modal .inventory-item,
    .update-details-modal .work-item {
        padding: 12px;
    }
    
    /* Mobile specific vendor and laborer styling */
    .update-details-modal .laborer-header {
        display: none;
    }
    
    .update-details-modal .laborer-subheader {
        display: none;
    }
    
    .update-details-modal .laborer-item {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 15px;
        background-color: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        margin-bottom: 10px;
    }
    
    .update-details-modal .laborer-name {
        font-weight: 500;
        font-size: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid #f0f0f0;
        width: 100%;
    }
    
    .update-details-modal .laborer-attendance,
    .update-details-modal .laborer-wages,
    .update-details-modal .laborer-overtime,
    .update-details-modal .laborer-amount {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 10px;
        margin-left: 0;
        padding-left: 0;
        flex-wrap: wrap;
    }
    
    .update-details-modal .laborer-attendance {
        justify-content: flex-start;
    }

    .update-details-modal .laborer-attendance::before,
    .update-details-modal .laborer-wages::before,
    .update-details-modal .laborer-overtime::before {
        content: attr(data-label);
        font-weight: 500;
        min-width: 90px;
    }
    
    /* Enhanced labels for mobile */
    .update-details-modal .laborer-attendance {
        position: relative;
    }

    .update-details-modal .laborer-attendance::before {
        content: "Attendance:" !important;
    }

    .update-details-modal .laborer-wages::before {
        content: "Wages:" !important;
    }

    .update-details-modal .laborer-overtime::before {
        content: "Overtime:" !important;
    }

    .update-details-modal .attendance-badge {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .update-details-modal .attendance-P.day::before,
    .update-details-modal .attendance-P.night::before {
        position: absolute;
        top: -18px;
        left: 50%;
        transform: translateX(-50%);
        white-space: nowrap;
        font-size: 11px;
        color: #6c757d;
        font-weight: 500;
    }

    .update-details-modal .attendance-P.day::before {
        content: "Day Shift";
    }

    .update-details-modal .attendance-P.night::before {
        content: "Night Shift";
    }

    .update-details-modal .attendance-P.day {
        background-color: #fff7e6;
        color: #ff9800;
        margin-top: 20px;
    }

    .update-details-modal .attendance-P.night {
        background-color: #e8f4fd;
        color: #4a76a8;
        margin-top: 20px;
    }

    .update-details-modal .laborer-amount {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #f0f0f0;
        justify-content: space-between;
        font-weight: 500;
    }

    .update-details-modal .ot-hours,
    .update-details-modal .ot-rate,
    .update-details-modal .ot-amount {
        display: inline-flex;
        align-items: center;
        background-color: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        margin-right: 5px;
    }

    .update-details-modal .ot-hours::before {
        content: "Hours: ";
        font-weight: normal;
        margin-right: 5px;
    }

    .update-details-modal .ot-rate::before {
        content: "Rate: ";
        font-weight: normal;
        margin-right: 5px;
    }

    .update-details-modal .ot-amount::before {
        content: "Amount: ";
        font-weight: normal;
        margin-right: 5px;
    }

    .update-details-modal .wages-per-day::before {
        content: "Per Day: ";
        font-weight: normal;
        margin-right: 5px;
    }

    .update-details-modal .day-total::before {
        content: "Day Total: ";
        font-weight: normal;
        margin-right: 5px;
    }

    /* Add specific styling to handle the attendance badges like in the image */
    .update-details-modal .laborer-attendance-wrapper {
        display: flex;
        flex-direction: column;
        gap: 5px;
        margin-top: 10px;
    }

    .update-details-modal .attendance-row {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .update-details-modal .attendance-label {
        font-size: 13px;
        color: #6c757d;
        min-width: 90px;
        text-align: right;
    }

    .update-details-modal .attendance-value {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Add clear badge styling */
    .update-details-modal .attendance-sun-badge,
    .update-details-modal .attendance-moon-badge {
        display: flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
    }

    .update-details-modal .attendance-sun-badge {
        background-color: #fff7e6;
        color: #ff9800;
    }

    .update-details-modal .attendance-sun-badge i {
        margin-right: 5px;
        color: #ff9800;
    }

    .update-details-modal .attendance-moon-badge {
        background-color: #e8f4fd;
        color: #4a76a8;
    }

    .update-details-modal .attendance-moon-badge i {
        margin-right: 5px;
        color: #4a76a8;
    }

    /* Wages styling */
    .update-details-modal .wages-wrapper {
        display: flex;
        flex-direction: column;
        gap: 5px;
        margin-top: 10px;
    }

    .update-details-modal .wages-row {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .update-details-modal .wages-label {
        font-size: 13px;
        color: #6c757d;
        min-width: 90px;
        text-align: right;
    }

    .update-details-modal .wages-value {
        font-weight: 500;
        color: #495057;
    }
}

/* Update tablet specific labels */
@media screen and (max-width: 768px) {
    .update-details-modal .wages-per-day::before {
        content: "Per Day: ";
        font-weight: 500;
        margin-right: 5px;
        display: inline;
    }
    
    .update-details-modal .day-total::before {
        content: "Day Total: ";
        font-weight: 500;
        margin-right: 5px;
        display: inline;
    }
    
    /* For the new labor card layout */
    .update-details-modal .labor-info-label[data-label="rate"]::before {
        content: "Per Day";
    }
    
    .update-details-modal .labor-info-label[data-label="total"]::before {
        content: "Day Total";
    }
}

/* Fix for very small screens */
@media screen and (max-width: 360px) {
    .update-details-modal .modal-title {
        font-size: 1rem;
    }
    
    .update-details-modal .modal-section h3 {
        font-size: 0.9rem;
    }
    
    .update-details-modal .modal-footer {
        flex-direction: column;
        gap: 8px;
    }
    
    .update-details-modal .btn {
        width: 100%;
    }
    
    /* Extra small screen adjustments for labor items */
    .update-details-modal .laborer-attendance,
    .update-details-modal .laborer-wages,
    .update-details-modal .laborer-overtime {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .update-details-modal .laborer-attendance::before,
    .update-details-modal .laborer-wages::before,
    .update-details-modal .laborer-overtime::before {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .update-details-modal .attendance-badge {
        width: auto;
        min-width: 40px;
    }
    
    .update-details-modal .laborer-amount {
        flex-direction: row;
        justify-content: space-between;
    }
    
    /* Adjust Edit button on small screens */
    .update-details-modal .edit-update-btn {
        font-size: 12px;
        padding: 6px 10px;
    }
    
    .update-details-modal .edit-update-btn i {
        font-size: 12px;
    }
}

/* Add CSS to handle the specific labor layout from the image */
.update-details-modal .labor-card {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 15px;
    overflow: hidden;
}

.update-details-modal .labor-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.update-details-modal .labor-name {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.update-details-modal .labor-card-body {
    padding: 15px;
}

.update-details-modal .labor-info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.update-details-modal .labor-info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.update-details-modal .labor-info-label {
    font-size: 13px;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 5px;
}

.update-details-modal .labor-info-value {
    font-weight: 500;
    color: #495057;
}

.update-details-modal .labor-attendance {
    display: flex;
    gap: 10px;
}

.update-details-modal .labor-attendance-badge {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
    background-color: #f8f9fa;
}

.update-details-modal .labor-attendance-badge.day {
    background-color: #fff7e6;
    color: #ff9800;
}

.update-details-modal .labor-attendance-badge.night {
    background-color: #e8f4fd;
    color: #4a76a8;
}

.update-details-modal .labor-total {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    padding-top: 10px;
    margin-top: 10px;
    border-top: 1px solid #e9ecef;
    font-weight: 500;
}

/* Responsive adjustments for labor card */
@media screen and (max-width: 768px) {
    .update-details-modal .labor-info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media screen and (max-width: 576px) {
    .update-details-modal .labor-info-grid {
        grid-template-columns: 1fr;
    }
    
    .update-details-modal .labor-total {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<!-- Add JavaScript for Edit Update functionality -->
<script>
function openEditUpdateForm() {
    // Get the current update ID
    const updateId = $('#siteUpdateModal').data('update-id');
    
    // Redirect to the edit form or open in a new tab
    window.location.href = 'edit_site_update.php?id=' + updateId;
    
    // Alternatively, to open in a new tab:
    // window.open('edit_site_update.php?id=' + updateId, '_blank');
}

// Make sure the update ID is stored when opening the modal
function openSiteUpdateModal(updateId, siteName) {
    // Store the update ID in the modal for later use
    $('#siteUpdateModal').data('update-id', updateId);
    $('#modal-site-name').text(siteName);
    
    // Load the update details
    loadSiteUpdateDetails(updateId);
    
    // Show the modal
    $('#siteUpdateModal').fadeIn(300);
    $('body').addClass('modal-open');
}
</script>

<!-- Add jQuery if it's not already included in the main page -->
<script>
// Check if jQuery is already loaded
if (typeof jQuery === 'undefined') {
    // Create a script element to load jQuery
    const script = document.createElement('script');
    script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
    script.integrity = 'sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=';
    script.crossOrigin = 'anonymous';
    script.onload = function() {
        console.log('jQuery loaded successfully');
    };
    document.head.appendChild(script);
}

// Vanilla JS version of the openEditUpdateForm function as fallback
function openEditUpdateForm() {
    // Get the modal element
    const modal = document.getElementById('siteUpdateModal');
    
    // Get the update ID from the data attribute
    const updateId = modal.getAttribute('data-update-id');
    
    if (updateId) {
        // Redirect to the edit form
        window.location.href = 'edit_site_update.php?id=' + updateId;
    } else {
        console.error('Update ID not found');
        alert('Error: Could not determine which update to edit. Please try again.');
    }
}
</script>

<?php
// REVISED SQL commands to add updated_by column to site_updates table
// Execute these commands one by one in your database:
/*
-- First, add the new columns without constraints
ALTER TABLE site_updates 
ADD COLUMN updated_by INT DEFAULT NULL,
ADD COLUMN last_updated DATETIME DEFAULT NULL;

-- Then add the foreign key constraint separately
-- If you get an error, make sure the users table has a PRIMARY KEY on id
ALTER TABLE site_updates
ADD CONSTRAINT fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- If you continue to have issues, try this version without naming the constraint
-- ALTER TABLE site_updates
-- ADD FOREIGN KEY (updated_by) REFERENCES users(id);
*/
?> 