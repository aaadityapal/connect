<?php
/**
 * Site Update Form
 * 
 * This file contains the form for adding site updates.
 * It is included in the main site_updates.php page.
 */

// Process form submission if submitted directly to this file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update'])) {
    // Include database connection
    require_once '../../config/db_connect.php';
    
    // Get form data
    $site_name = $_POST['site_name'] ?? '';
    $update_date = $_POST['update_date'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Validate form data
    $errors = [];
    
    if (empty($site_name)) {
        $errors[] = "Site name is required";
    }
    
    if (empty($update_date)) {
        $errors[] = "Date is required";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        $insert_query = "INSERT INTO site_updates (site_name, update_date, created_by, created_at) 
                         VALUES (?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ssi", $site_name, $update_date, $user_id);
        
        if ($insert_stmt->execute()) {
            // Set success message and redirect
            $_SESSION['update_success'] = "Update added successfully!";
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?success=update_added');
            exit();
        } else {
            $errors[] = "Error adding update: " . $conn->error;
        }
    }
    
    // If errors, set error message
    if (!empty($errors)) {
        $_SESSION['update_errors'] = $errors;
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}
?>

<!-- Update Form Modal -->
<div class="update-modal" id="update-form-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Update</h3>
            <button type="button" class="modal-close" onclick="hideUpdateModal()">&times;</button>
        </div>
        <form id="update-form" class="update-form" method="POST" action="site_updates/forms/process_update.php" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="site-name" class="required-field">Site Name</label>
                    <i class="fas fa-building"></i>
                    <input type="text" id="site-name" name="site_name" class="form-control" placeholder="Enter site name">
                </div>
                <div class="form-group">
                    <label for="update-date" class="required-field">Date</label>
                    <i class="fas fa-calendar-alt"></i>
                    <input type="text" id="update-date" name="update_date" class="form-control datepicker" placeholder="Select date">
                </div>
            </div>
            
            <!-- Vendors Section -->
            <div class="vendors-section">
                <div class="section-header">
                    <h4 class="section-title">Vendors</h4>
                    <button type="button" class="btn-add-item" id="add-vendor-btn" title="Add New Vendor">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div id="vendors-container">
                    <!-- Vendors will be added here dynamically -->
                </div>
                
                <!-- Bottom Add Vendor Button -->
                <div class="bottom-add-vendor-container">
                    <button type="button" class="bottom-btn-add" id="bottom-add-vendor-btn" title="Add New Vendor">
                        <i class="fas fa-plus"></i> Add Vendor
                    </button>
                </div>
            </div>
            
            <!-- Company Labour Section -->
            <div class="company-labours-section">
                <div class="section-header">
                    <h4 class="section-title">Company Labour</h4>
                    <button type="button" class="btn-add-item" id="add-company-labour-btn" title="Add Company Labour">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div id="company-labours-container">
                    <!-- Company Labours will be added here dynamically -->
                </div>
                
                <!-- Bottom Add Company Labour Button -->
                <div class="bottom-add-company-labour-container">
                    <button type="button" class="bottom-btn-add" id="bottom-add-company-labour-btn" title="Add Company Labour">
                        <i class="fas fa-plus"></i> Add Company Labour
                    </button>
                </div>
            </div>

            <!-- Travel Expenses Section -->
            <div class="travel-expenses-section">
                <div class="section-header">
                    <h4 class="section-title">Travel Expenses</h4>
                    <button type="button" class="btn-add-item" id="add-travel-expense-btn" title="Add Travel Expense">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div id="travel-expenses-container">
                    <!-- Travel expenses will be added here dynamically -->
                </div>
                
                <!-- Bottom Add Travel Expense Button -->
                <div class="bottom-add-travel-expense-container">
                    <button type="button" class="bottom-btn-add" id="bottom-add-travel-expense-btn" title="Add Travel Expense">
                        <i class="fas fa-plus"></i> Add Travel Expense
                    </button>
                </div>
            </div>
            
            <!-- Beverages Section -->
            <div class="beverages-section">
                <div class="section-header">
                    <h4 class="section-title">Beverages</h4>
                    <button type="button" class="btn-add-item" id="add-beverage-btn" title="Add Beverage">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div id="beverages-container">
                    <!-- Beverages will be added here dynamically -->
                </div>
                
                <!-- Bottom Add Beverage Button -->
                <div class="bottom-add-beverage-container">
                    <button type="button" class="bottom-btn-add" id="bottom-add-beverage-btn" title="Add Beverage">
                        <i class="fas fa-plus"></i> Add Beverage
                    </button>
                </div>
            </div>
            
            <!-- Work Progress Section -->
            <div class="work-progress-section">
                <div class="section-header">
                    <h4 class="section-title">Work Progress</h4>
                    <button type="button" class="btn-add-item" id="add-work-progress-btn" title="Add Work Progress">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div id="work-progress-container">
                    <!-- Work progress items will be added here dynamically -->
                </div>
                
                <!-- Bottom Add Work Progress Button -->
                <div class="bottom-add-work-progress-container">
                    <button type="button" class="bottom-btn-add" id="bottom-add-work-progress-btn" title="Add Work Progress">
                        <i class="fas fa-plus"></i> Add Work Progress
                    </button>
                </div>
            </div>
            
            <!-- Inventory Section -->
            <div class="inventory-section">
                <div class="section-header">
                    <h4 class="section-title">Inventory</h4>
                    <button type="button" class="btn-add-item" id="add-inventory-btn" title="Add Inventory Item">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div id="inventory-container">
                    <!-- Inventory items will be added here dynamically -->
                </div>
                
                <!-- Bottom Add Inventory Button -->
                <div class="bottom-add-inventory-container">
                    <button type="button" class="bottom-btn-add" id="bottom-add-inventory-btn" title="Add Inventory Item">
                        <i class="fas fa-plus"></i> Add Inventory Item
                    </button>
                </div>
            </div>
            
            <!-- Wages Summary Section -->
            <div class="wages-summary-section">
                <div class="section-header">
                    <h4 class="section-title">Wages Summary</h4>
                    <i class="fas fa-calculator summary-icon"></i>
                </div>
                
                <div class="summary-container">
                    <!-- Vendor Labour Wages -->
                    <div class="summary-group">
                        <div class="summary-label">
                            <i class="fas fa-hard-hat"></i>
                            <span>Vendor Labour Wages</span>
                        </div>
                        <div class="summary-value">
                            <span class="currency">₹</span>
                            <span id="vendor-labour-total">0.00</span>
                        </div>
                    </div>
                    
                    <!-- Company Labour Wages -->
                    <div class="summary-group">
                        <div class="summary-label">
                            <i class="fas fa-user-hard-hat"></i>
                            <span>Company Labour Wages</span>
                        </div>
                        <div class="summary-value">
                            <span class="currency">₹</span>
                            <span id="company-labour-total">0.00</span>
                        </div>
                    </div>
                    
                    <!-- Miscellaneous Payments -->
                    <div class="summary-group">
                        <div class="summary-label">
                            <i class="fas fa-receipt"></i>
                            <span>Miscellaneous Payments</span>
                        </div>
                        
                        <!-- Travel Expenses -->
                        <div class="summary-subgroup">
                            <div class="summary-sublabel">
                                <i class="fas fa-car"></i>
                                <span>Travel Expenses</span>
                            </div>
                            <div class="summary-value">
                                <span class="currency">₹</span>
                                <span id="travel-expenses-total">0.00</span>
                            </div>
                        </div>
                        
                        <!-- Beverages -->
                        <div class="summary-subgroup">
                            <div class="summary-sublabel">
                                <i class="fas fa-coffee"></i>
                                <span>Beverages</span>
                            </div>
                            <div class="summary-value">
                                <span class="currency">₹</span>
                                <span id="beverages-total">0.00</span>
                            </div>
                        </div>
                        
                        <!-- Miscellaneous Total -->
                        <div class="summary-subgroup subtotal">
                            <div class="summary-sublabel">
                                <span>Subtotal</span>
                            </div>
                            <div class="summary-value">
                                <span class="currency">₹</span>
                                <span id="miscellaneous-total">0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grand Total -->
                    <div class="summary-group grand-total">
                        <div class="summary-label">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Grand Total</span>
                        </div>
                        <div class="summary-value">
                            <span class="currency">₹</span>
                            <span id="grand-total">0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Calculate Button -->
                <div class="calculate-container">
                    <button type="button" id="calculate-totals" class="btn btn-primary calculate-btn">
                        <i class="fas fa-calculator"></i> Calculate Totals
                    </button>
                </div>
            </div>
            
            <div class="btn-container">
                <button type="button" id="cancel-update" class="btn btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="submit_update" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Update
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden templates for dynamic content -->
<div id="template-container" style="display: none;">
    <!-- Vendor template -->
    <div id="vendor-template">
        <div class="vendor-item" data-vendor-id="{VENDOR_ID}">
            <div class="vendor-header">
                <div class="vendor-title">
                    <i class="fas fa-hard-hat"></i>
                    <span>Vendor #<span class="vendor-number">{VENDOR_NUMBER}</span></span>
                </div>
                <button type="button" class="btn-remove" onclick="removeVendor({VENDOR_ID})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            
            <div class="vendor-form">
                <div class="form-group">
                    <label for="vendor-type-{VENDOR_ID}" class="required-field">Vendor Type</label>
                    <i class="fas fa-tools"></i>
                    <select id="vendor-type-{VENDOR_ID}" name="vendors[{VENDOR_ID}][type]" class="form-control vendor-type-select" onchange="updateVendorIcon(this, {VENDOR_ID})">
                        <option value="">Select vendor type</option>
                        <option value="Carpenter">Carpenter</option>
                        <option value="Electrician">Electrician</option>
                        <option value="Plumber">Plumber</option>
                        <option value="Mason">Mason</option>
                        <option value="Painter">Painter</option>
                        <option value="HVAC">HVAC Technician</option>
                        <option value="Roofer">Roofer</option>
                        <option value="Landscaper">Landscaper</option>
                        <option value="Concrete">Concrete Worker</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="vendor-name-{VENDOR_ID}" class="required-field">Name</label>
                        <i class="fas fa-user"></i>
                        <input type="text" id="vendor-name-{VENDOR_ID}" name="vendors[{VENDOR_ID}][name]" class="form-control" placeholder="Vendor name">
                    </div>
                    <div class="form-group">
                        <label for="vendor-contact-{VENDOR_ID}" class="required-field">Contact Number</label>
                        <i class="fas fa-phone-alt"></i>
                        <input type="text" id="vendor-contact-{VENDOR_ID}" name="vendors[{VENDOR_ID}][contact]" class="form-control" placeholder="Contact number">
                    </div>
                </div>
                
                <!-- Laborers Section -->
                <div class="laborer-section">
                    <div class="laborer-header">
                        <h5 class="section-title">Laborers</h5>
                        <button type="button" class="btn-add-item" onclick="addLaborer({VENDOR_ID})" title="Add Laborer">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="laborers-container" id="laborers-container-{VENDOR_ID}">
                        <!-- Laborers will be added here dynamically -->
                    </div>
                    
                    <!-- Bottom Add Laborer Button -->
                    <div class="bottom-add-laborer-container">
                        <button type="button" class="bottom-btn-add" onclick="addLaborer({VENDOR_ID})" title="Add Laborer">
                            <i class="fas fa-plus"></i> Add Laborer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Laborer template -->
    <div id="laborer-template">
        <div class="laborer-item" data-laborer-id="{LABORER_ID}">
            <div class="vendor-header">
                <div class="vendor-title">
                    <i class="fas fa-user-hard-hat"></i>
                    <span>Laborer #<span class="laborer-number">{LABORER_NUMBER}</span></span>
                </div>
                <button type="button" class="btn-remove" onclick="removeLaborer({VENDOR_ID}, {LABORER_ID})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            
            <div class="laborer-form">
                <div class="form-group">
                    <label for="laborer-name-{VENDOR_ID}-{LABORER_ID}" class="required-field">Name</label>
                    <i class="fas fa-user"></i>
                    <input type="text" id="laborer-name-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][name]" class="form-control" placeholder="Laborer name">
                </div>
                <div class="form-group">
                    <label for="laborer-contact-{VENDOR_ID}-{LABORER_ID}">Contact Number</label>
                    <i class="fas fa-phone-alt"></i>
                    <input type="text" id="laborer-contact-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][contact]" class="form-control" placeholder="Contact number (optional)">
                </div>

                <div class="attendance-note">
                    <small><i class="fas fa-info-circle"></i> Morning and afternoon attendance each count for half a day's wages. Present for full day = full wages.</small>
                </div>

                <!-- Row 1: Morning Attendance, Afternoon Attendance, Wages per day, Total Wages -->
                <div class="form-row attendance-wages-row">
                    <div class="form-group col-3">
                        <label for="laborer-morning-{VENDOR_ID}-{LABORER_ID}" title="Morning Attendance - counts for half a day's wage">M.A</label>
                        <i class="fas fa-sun"></i>
                        <select id="laborer-morning-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][morning]" class="form-control" onchange="calculateLaborerTotal({VENDOR_ID}, {LABORER_ID})">
                            <option value="P">Present</option>
                            <option value="A">Absent</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label for="laborer-evening-{VENDOR_ID}-{LABORER_ID}" title="Afternoon Attendance - counts for half a day's wage">A.A</label>
                        <i class="fas fa-moon"></i>
                        <select id="laborer-evening-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][evening]" class="form-control" onchange="calculateLaborerTotal({VENDOR_ID}, {LABORER_ID})">
                            <option value="P">Present</option>
                            <option value="A">Absent</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label for="laborer-wages-{VENDOR_ID}-{LABORER_ID}">Wages per day</label>
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" id="laborer-wages-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][wages]" class="form-control wages-input" min="0" step="1" placeholder="Daily wages" onchange="calculateLaborerTotal({VENDOR_ID}, {LABORER_ID})" oninput="updateOTRate({VENDOR_ID}, {LABORER_ID})">
                    </div>
                    <div class="form-group col-3">
                        <label for="laborer-day-total-{VENDOR_ID}-{LABORER_ID}" title="Total wages based on attendance: Present whole day = full wages, Half day = half wages">Total Wages</label>
                        <i class="fas fa-money-bill"></i>
                        <input type="number" id="laborer-day-total-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][day_total]" class="form-control day-total-input" readonly placeholder="Day total">
                    </div>
                </div>

                <!-- Row 2: OT Hours, OT Wages, Total OT -->
                <div class="form-row ot-row">
                    <div class="form-group col-4">
                        <label for="laborer-ot-hours-{VENDOR_ID}-{LABORER_ID}">Overtime</label>
                        <div class="overtime-container">
                            <div class="overtime-input-group">
                                <div class="overtime-inputs">
                                    <div class="overtime-hours">
                                        <input type="number" id="laborer-ot-hours-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][ot_hours]" class="form-control ot-hours-input" min="0" step="1" placeholder="Hours" onchange="calculateLaborerTotal({VENDOR_ID}, {LABORER_ID})" value="0">
                                        <span>hrs</span>
                                    </div>
                                    <div class="overtime-minutes">
                                        <input type="number" id="laborer-ot-minutes-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][ot_minutes]" class="form-control ot-minutes-input" min="0" max="59" step="1" placeholder="Minutes" onchange="calculateLaborerTotal({VENDOR_ID}, {LABORER_ID})" value="0">
                                        <span>min</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-4">
                        <label for="laborer-ot-rate-{VENDOR_ID}-{LABORER_ID}">OT Wages</label>
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" id="laborer-ot-rate-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][ot_rate]" class="form-control ot-rate-input" min="0" step="1" placeholder="Overtime rate" onchange="calculateLaborerTotal({VENDOR_ID}, {LABORER_ID})">
                    </div>
                    <div class="form-group col-4">
                        <label for="laborer-ot-amount-{VENDOR_ID}-{LABORER_ID}">Total OT</label>
                        <i class="fas fa-calculator"></i>
                        <input type="number" id="laborer-ot-amount-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][ot_amount]" class="form-control ot-amount-input" readonly placeholder="OT total">
                    </div>
                </div>
                
                <!-- Row 3: Total Amount -->
                <div class="form-group total-amount-container">
                    <div class="total-amount-wrapper">
                        <div class="total-value">
                            <label for="laborer-total-{VENDOR_ID}-{LABORER_ID}">Total Amount (₹)</label>
                            <input type="number" id="laborer-total-{VENDOR_ID}-{LABORER_ID}" name="vendors[{VENDOR_ID}][laborers][{LABORER_ID}][total]" class="form-control grand-total-input" readonly placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Company Labour template -->
    <div id="company-labour-template">
        <div class="company-labour-item" data-company-labour-id="{COMPANY_LABOUR_ID}">
            <div class="vendor-header">
                <div class="vendor-title">
                    <i class="fas fa-user-hard-hat"></i>
                    <span>Company Labour #<span class="company-labour-number">{COMPANY_LABOUR_NUMBER}</span></span>
                </div>
                <button type="button" class="btn-remove" onclick="removeCompanyLabour({COMPANY_LABOUR_ID})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            
            <div class="laborer-form">
                    <div class="form-group">
                    <label for="company-labour-name-{COMPANY_LABOUR_ID}" class="required-field">Name</label>
                    <i class="fas fa-user"></i>
                    <input type="text" id="company-labour-name-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][name]" class="form-control" placeholder="Labour name">
                    </div>
                    <div class="form-group">
                    <label for="company-labour-contact-{COMPANY_LABOUR_ID}">Contact Number</label>
                    <i class="fas fa-phone-alt"></i>
                    <input type="text" id="company-labour-contact-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][contact]" class="form-control" placeholder="Contact number (optional)">
                </div>

                <div class="attendance-note">
                    <small><i class="fas fa-info-circle"></i> Morning and afternoon attendance each count for half a day's wages. Present for full day = full wages.</small>
                </div>

                <!-- Row 1: Morning Attendance, Afternoon Attendance, Wages per day, Total Wages -->
                <div class="form-row attendance-wages-row">
                    <div class="form-group col-3">
                        <label for="company-labour-morning-{COMPANY_LABOUR_ID}" title="Morning Attendance - counts for half a day's wage">M.A</label>
                        <i class="fas fa-sun"></i>
                        <select id="company-labour-morning-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][morning]" class="form-control" onchange="calculateCompanyLabourTotal({COMPANY_LABOUR_ID})">
                            <option value="P">Present</option>
                            <option value="A">Absent</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label for="company-labour-evening-{COMPANY_LABOUR_ID}" title="Afternoon Attendance - counts for half a day's wage">A.A</label>
                        <i class="fas fa-moon"></i>
                        <select id="company-labour-evening-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][evening]" class="form-control" onchange="calculateCompanyLabourTotal({COMPANY_LABOUR_ID})">
                            <option value="P">Present</option>
                            <option value="A">Absent</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label for="company-labour-wages-{COMPANY_LABOUR_ID}">Wages per day</label>
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" id="company-labour-wages-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][wages]" class="form-control wages-input" min="0" step="1" placeholder="Daily wages" onchange="calculateCompanyLabourTotal({COMPANY_LABOUR_ID})" oninput="updateCompanyOTRate({COMPANY_LABOUR_ID})">
                    </div>
                    <div class="form-group col-3">
                        <label for="company-labour-day-total-{COMPANY_LABOUR_ID}">Total Wages</label>
                        <i class="fas fa-money-bill"></i>
                        <input type="number" id="company-labour-day-total-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][day_total]" class="form-control day-total-input" readonly placeholder="Day total">
                    </div>
                </div>

                <!-- Row 2: OT Hours, OT Wages, Total OT -->
                <div class="form-row ot-row">
                    <div class="form-group col-4">
                        <label for="company-labour-ot-hours-{COMPANY_LABOUR_ID}">Overtime</label>
                        <div class="overtime-container">
                            <div class="overtime-input-group">
                                <div class="overtime-inputs">
                                    <div class="overtime-hours">
                                        <input type="number" id="company-labour-ot-hours-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][ot_hours]" class="form-control ot-hours-input" min="0" step="1" placeholder="Hours" onchange="calculateCompanyLabourTotal({COMPANY_LABOUR_ID})" value="0">
                                        <span>hrs</span>
                                    </div>
                                    <div class="overtime-minutes">
                                        <input type="number" id="company-labour-ot-minutes-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][ot_minutes]" class="form-control ot-minutes-input" min="0" max="59" step="1" placeholder="Minutes" onchange="calculateCompanyLabourTotal({COMPANY_LABOUR_ID})" value="0">
                                        <span>min</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-4">
                        <label for="company-labour-ot-rate-{COMPANY_LABOUR_ID}">OT Wages</label>
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" id="company-labour-ot-rate-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][ot_rate]" class="form-control ot-rate-input" min="0" step="1" placeholder="Overtime rate" onchange="calculateCompanyLabourTotal({COMPANY_LABOUR_ID})">
                    </div>
                    <div class="form-group col-4">
                        <label for="company-labour-ot-amount-{COMPANY_LABOUR_ID}">Total OT</label>
                        <i class="fas fa-calculator"></i>
                        <input type="number" id="company-labour-ot-amount-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][ot_amount]" class="form-control ot-amount-input" readonly placeholder="OT total">
                    </div>
                </div>
                
                <!-- Row 3: Total Amount -->
                <div class="form-group total-amount-container">
                    <div class="total-amount-wrapper">
                        <div class="total-value">
                            <label for="company-labour-total-{COMPANY_LABOUR_ID}">Total Amount (₹)</label>
                            <input type="number" id="company-labour-total-{COMPANY_LABOUR_ID}" name="company_labours[{COMPANY_LABOUR_ID}][total]" class="form-control grand-total-input" readonly placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Travel Expense template -->
    <div id="travel-expense-template">
        <div class="travel-expense-item" data-travel-expense-id="{TRAVEL_EXPENSE_ID}">
            <div class="vendor-header">
                <div class="vendor-title">
                    <i class="fas fa-car"></i>
                    <span>Travel Expense #<span class="travel-expense-number">{TRAVEL_EXPENSE_NUMBER}</span></span>
                </div>
                <button type="button" class="btn-remove" onclick="removeTravelExpense({TRAVEL_EXPENSE_ID})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            
            <div class="travel-expense-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="travel-from-{TRAVEL_EXPENSE_ID}" class="required-field">From</label>
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="travel-from-{TRAVEL_EXPENSE_ID}" name="travel_expenses[{TRAVEL_EXPENSE_ID}][from]" class="form-control" placeholder="Starting location">
                    </div>
                    <div class="form-group">
                        <label for="travel-to-{TRAVEL_EXPENSE_ID}" class="required-field">To</label>
                        <i class="fas fa-map-marker"></i>
                        <input type="text" id="travel-to-{TRAVEL_EXPENSE_ID}" name="travel_expenses[{TRAVEL_EXPENSE_ID}][to]" class="form-control" placeholder="Destination">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="travel-mode-{TRAVEL_EXPENSE_ID}" class="required-field">Mode of Transport</label>
                        <i class="fas fa-bus"></i>
                        <select id="travel-mode-{TRAVEL_EXPENSE_ID}" name="travel_expenses[{TRAVEL_EXPENSE_ID}][mode]" class="form-control">
                            <option value="">Select transport mode</option>
                            <option value="Car">Car</option>
                            <option value="Bike">Bike</option>
                            <option value="Taxi">Taxi</option>
                            <option value="Bus">Bus</option>
                            <option value="Train">Train</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="travel-km-{TRAVEL_EXPENSE_ID}">KM Travelled</label>
                        <i class="fas fa-road"></i>
                        <input type="number" min="0" step="0.1" id="travel-km-{TRAVEL_EXPENSE_ID}" name="travel_expenses[{TRAVEL_EXPENSE_ID}][km]" class="form-control" placeholder="Distance in kilometers">
                    </div>
                </div>
                
                <!-- Amount -->
                <div class="form-group total-amount-container">
                    <div class="total-amount-wrapper">
                        <div class="total-value">
                            <label for="travel-amount-{TRAVEL_EXPENSE_ID}">Amount (₹)</label>
                            <input type="number" id="travel-amount-{TRAVEL_EXPENSE_ID}" name="travel_expenses[{TRAVEL_EXPENSE_ID}][amount]" class="form-control grand-total-input" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Beverage template -->
    <div id="beverage-template">
        <div class="beverage-item" data-beverage-id="{BEVERAGE_ID}">
            <div class="vendor-header">
                <div class="vendor-title">
                    <i class="fas fa-coffee"></i>
                    <span>Beverage #<span class="beverage-number">{BEVERAGE_NUMBER}</span></span>
                </div>
                <button type="button" class="btn-remove" onclick="removeBeverage({BEVERAGE_ID})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            
            <div class="beverage-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="beverage-type-{BEVERAGE_ID}" class="required-field">Beverage Type</label>
                        <i class="fas fa-glass-whiskey"></i>
                        <select id="beverage-type-{BEVERAGE_ID}" name="beverages[{BEVERAGE_ID}][type]" class="form-control" onchange="updateBeverageIcon(this, {BEVERAGE_ID})">
                            <option value="">Select beverage type</option>
                            <option value="Tea">Tea</option>
                            <option value="Coffee">Coffee</option>
                            <option value="Water">Water</option>
                            <option value="Soft Drink">Soft Drink</option>
                            <option value="Juice">Juice</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="beverage-name-{BEVERAGE_ID}" class="required-field">Name/Description</label>
                        <i class="fas fa-tag"></i>
                        <input type="text" id="beverage-name-{BEVERAGE_ID}" name="beverages[{BEVERAGE_ID}][name]" class="form-control" placeholder="Beverage name or description">
                    </div>
                </div>
                
                <!-- Amount -->
                <div class="form-group total-amount-container">
                    <div class="total-amount-wrapper">
                        <div class="total-value">
                            <label for="beverage-amount-{BEVERAGE_ID}">Amount (₹)</label>
                            <input type="number" id="beverage-amount-{BEVERAGE_ID}" name="beverages[{BEVERAGE_ID}][amount]" class="form-control grand-total-input" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Work Progress template -->
    <div id="work-progress-template">
        <div class="work-progress-item" data-work-progress-id="{WORK_PROGRESS_ID}">
            <div class="vendor-header">
                <div class="vendor-title">
                    <i class="fas fa-hard-hat"></i>
                    <span>Work Progress #<span class="work-progress-number">{WORK_PROGRESS_NUMBER}</span></span>
                </div>
                <button type="button" class="btn-remove" onclick="removeWorkProgress({WORK_PROGRESS_ID})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            
            <div class="work-progress-form">
                <!-- Work Category Dropdown -->
                <div class="form-group">
                    <label for="work-category-{WORK_PROGRESS_ID}" class="required-field">Work Category</label>
                    <i class="fas fa-clipboard-list"></i>
                    <select id="work-category-{WORK_PROGRESS_ID}" name="work_progress[{WORK_PROGRESS_ID}][category]" class="form-control work-category-select" onchange="updateWorkTypeOptions({WORK_PROGRESS_ID}); updateWorkIcon({WORK_PROGRESS_ID})">
                        <option value="">Select work category</option>
                        <option value="Civil Work">Civil Work</option>
                        <option value="Interior Work">Interior Work</option>
                        <option value="Facade Work">Facade Work</option>
                        <option value="Finishing Work">Finishing Work</option>
                    </select>
                </div>
                
                <!-- Work Type - Options will be populated based on category selection -->
                <div class="form-group">
                    <label for="work-type-{WORK_PROGRESS_ID}" class="required-field">Type of Work</label>
                    <i class="fas fa-tools"></i>
                    <select id="work-type-{WORK_PROGRESS_ID}" name="work_progress[{WORK_PROGRESS_ID}][type]" class="form-control">
                        <option value="">Select work category first</option>
                    </select>
                </div>
                
                <!-- Work Completed Status -->
                <div class="form-group">
                    <label for="work-done-{WORK_PROGRESS_ID}" class="required-field">Work Done?</label>
                    <i class="fas fa-check-circle"></i>
                    <select id="work-done-{WORK_PROGRESS_ID}" name="work_progress[{WORK_PROGRESS_ID}][done]" class="form-control">
                        <option value="">Select status</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                
                <!-- Remarks -->
                <div class="form-group">
                    <label for="work-remarks-{WORK_PROGRESS_ID}">Remarks</label>
                    <i class="fas fa-comment"></i>
                    <textarea id="work-remarks-{WORK_PROGRESS_ID}" name="work_progress[{WORK_PROGRESS_ID}][remarks]" class="form-control" rows="3" placeholder="Add any remarks or notes"></textarea>
                </div>
                
                <!-- Media Upload Section -->
                <div class="media-upload-section">
                    <h6 class="upload-section-title"><i class="fas fa-camera"></i> Photos & Videos</h6>
                    
                    <!-- Unified media container -->
                    <div class="media-items-container" id="media-items-container-{WORK_PROGRESS_ID}">
                        <!-- Initial media upload item -->
                        <div class="media-upload-item" data-item-id="1">
                            <div class="media-upload-header">
                                <span class="media-item-title">Media Item #1</span>
                                <button type="button" class="btn-remove-media" onclick="removeMediaItem({WORK_PROGRESS_ID}, 1)" title="Remove media item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="media-upload-body">
                                <div class="form-group">
                                    <div class="media-upload-input">
                                        <input type="file" id="work-media-{WORK_PROGRESS_ID}-1" name="work_progress_media_{WORK_PROGRESS_ID}[]" class="form-control file-upload media-file-input" accept="image/*,video/*" onchange="previewMedia(this, {WORK_PROGRESS_ID}, 1)">
                                        <label for="work-media-{WORK_PROGRESS_ID}-1" class="media-upload-label">
                                            <i class="fas fa-cloud-upload-alt"></i> Choose Photo/Video
                                        </label>
                                    </div>
                                    <div class="media-type-indicator" id="media-type-{WORK_PROGRESS_ID}-1"></div>
                                </div>
                                <div class="media-preview-container" id="media-preview-container-{WORK_PROGRESS_ID}-1">
                                    <!-- Preview will be shown here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add More Media Button -->
                    <div class="add-more-media-container">
                        <button type="button" class="btn-add-more-media" onclick="addMoreMediaItem({WORK_PROGRESS_ID})">
                            <i class="fas fa-plus"></i> Add Another Photo/Video
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inventory Item template -->
    <div id="inventory-template">
        <div class="inventory-item" data-inventory-id="{INVENTORY_ID}">
            <div class="vendor-header">
                <div class="vendor-title">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory Item #<span class="inventory-number">{INVENTORY_NUMBER}</span></span>
                </div>
                <button type="button" class="btn-remove" onclick="removeInventory({INVENTORY_ID})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            
            <div class="inventory-form">
                <!-- Inventory Type -->
                <div class="form-group">
                    <label for="inventory-type-{INVENTORY_ID}" class="required-field">Inventory Type</label>
                    <i class="fas fa-clipboard-check"></i>
                    <select id="inventory-type-{INVENTORY_ID}" name="inventory[{INVENTORY_ID}][type]" class="form-control inventory-type-select">
                        <option value="">Select inventory type</option>
                        <option value="Received">Received Item</option>
                        <option value="Available">Available Item on Site</option>
                        <option value="Consumed">Item Consumed</option>
                    </select>
                </div>
                
                <!-- Material Selection -->
                <div class="form-group">
                    <label for="inventory-material-{INVENTORY_ID}" class="required-field">Select Material</label>
                    <i class="fas fa-cubes"></i>
                    <select id="inventory-material-{INVENTORY_ID}" name="inventory[{INVENTORY_ID}][material]" class="form-control inventory-material-select" onchange="updateInventoryIcon({INVENTORY_ID})">
                        <option value="">Select material</option>
                        <option value="Cement">Cement</option>
                        <option value="Sand">Sand</option>
                        <option value="Aggregates">Aggregates</option>
                        <option value="Bricks">Bricks</option>
                        <option value="Steel">Steel</option>
                        <option value="Wood">Wood</option>
                        <option value="Paint">Paint</option>
                        <option value="Tiles">Tiles</option>
                        <option value="Glass">Glass</option>
                        <option value="Electrical">Electrical Items</option>
                        <option value="Plumbing">Plumbing Items</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Tools">Tools</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <!-- Quantity and Unit -->
                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="inventory-quantity-{INVENTORY_ID}" class="required-field">Quantity</label>
                        <i class="fas fa-balance-scale"></i>
                        <input type="number" id="inventory-quantity-{INVENTORY_ID}" name="inventory[{INVENTORY_ID}][quantity]" class="form-control" min="0" step="0.01" placeholder="Enter quantity">
                    </div>
                    <div class="form-group col-6">
                        <label for="inventory-unit-{INVENTORY_ID}" class="required-field">Unit</label>
                        <i class="fas fa-ruler-combined"></i>
                        <select id="inventory-unit-{INVENTORY_ID}" name="inventory[{INVENTORY_ID}][unit]" class="form-control">
                            <option value="">Select unit</option>
                            <option value="Nos">Nos (Numbers)</option>
                            <option value="Kg">Kg (Kilograms)</option>
                            <option value="Bags">Bags</option>
                            <option value="Ton">Ton (Metric Ton)</option>
                            <option value="CFT">CFT (Cubic Feet)</option>
                            <option value="CUM">CUM (Cubic Meter)</option>
                            <option value="Sqft">Sqft (Square Feet)</option>
                            <option value="Sqm">Sqm (Square Meter)</option>
                            <option value="Rmt">Rmt (Running Meter)</option>
                            <option value="Ltr">Ltr (Liters)</option>
                            <option value="Ml">Ml (Milliliters)</option>
                            <option value="Bundle">Bundle</option>
                            <option value="Coil">Coil</option>
                            <option value="Box">Box</option>
                            <option value="Roll">Roll</option>
                            <option value="Set">Set</option>
                        </select>
                    </div>
                </div>
                
                <!-- Notes Field -->
                <div class="form-group">
                    <label for="inventory-notes-{INVENTORY_ID}">Notes</label>
                    <i class="fas fa-sticky-note"></i>
                    <textarea id="inventory-notes-{INVENTORY_ID}" name="inventory[{INVENTORY_ID}][notes]" class="form-control" rows="2" placeholder="Add any notes about this material"></textarea>
                </div>
                
                <!-- Bill Picture Upload -->
                <div class="form-group bill-upload-container">
                    <label class="required-field">Bill Picture</label>
                    <div class="media-upload-input">
                        <input type="file" id="inventory-bill-{INVENTORY_ID}" name="inventory_bill_{INVENTORY_ID}" class="form-control file-upload media-file-input" accept="image/*" onchange="previewBill(this, {INVENTORY_ID})">
                        <label for="inventory-bill-{INVENTORY_ID}" class="media-upload-label">
                            <i class="fas fa-receipt"></i> Upload Bill Picture
                        </label>
                    </div>
                    <div class="bill-preview-container" id="bill-preview-{INVENTORY_ID}">
                        <!-- Bill preview will be shown here -->
                    </div>
                </div>
                
                <!-- Media Upload Section (Similar to Work Progress) -->
                <div class="media-upload-section">
                    <h6 class="upload-section-title"><i class="fas fa-camera"></i> Material Photos & Videos</h6>
                    
                    <!-- Unified media container -->
                    <div class="media-items-container" id="inventory-media-container-{INVENTORY_ID}">
                        <!-- Initial media upload item -->
                        <div class="media-upload-item" data-item-id="1">
                            <div class="media-upload-header">
                                <span class="media-item-title">Media Item #1</span>
                                <button type="button" class="btn-remove-media" onclick="removeInventoryMediaItem({INVENTORY_ID}, 1)" title="Remove media item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="media-upload-body">
                                <div class="form-group">
                                    <div class="media-upload-input">
                                        <input type="file" id="inventory-media-{INVENTORY_ID}-1" name="inventory_media_{INVENTORY_ID}[]" class="form-control file-upload media-file-input" accept="image/*,video/*" onchange="previewInventoryMedia(this, {INVENTORY_ID}, 1)">
                                        <label for="inventory-media-{INVENTORY_ID}-1" class="media-upload-label">
                                            <i class="fas fa-cloud-upload-alt"></i> Choose Photo/Video
                                        </label>
                                    </div>
                                    <div class="media-type-indicator" id="inventory-media-type-{INVENTORY_ID}-1"></div>
                                </div>
                                <div class="media-preview-container" id="inventory-media-preview-{INVENTORY_ID}-1">
                                    <!-- Preview will be shown here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add More Media Button -->
                    <div class="add-more-media-container">
                        <button type="button" class="btn-add-more-media" onclick="addMoreInventoryMedia({INVENTORY_ID})">
                            <i class="fas fa-plus"></i> Add Another Photo/Video
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Ensure the form JavaScript is properly loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Form DOM loaded in inline script');
    
    // Check if add vendor button exists
    const addVendorBtn = document.getElementById('add-vendor-btn');
    const bottomAddVendorBtn = document.getElementById('bottom-add-vendor-btn');
    
    // Check if add company labour button exists
    const addCompanyLabourBtn = document.getElementById('add-company-labour-btn');
    const bottomAddCompanyLabourBtn = document.getElementById('bottom-add-company-labour-btn');
    
    // Check if add travel expense button exists
    const addTravelExpenseBtn = document.getElementById('add-travel-expense-btn');
    const bottomAddTravelExpenseBtn = document.getElementById('bottom-add-travel-expense-btn');
    
    // Check if add beverage button exists
    const addBeverageBtn = document.getElementById('add-beverage-btn');
    const bottomAddBeverageBtn = document.getElementById('bottom-add-beverage-btn');
    
    // Check if add work progress button exists
    const addWorkProgressBtn = document.getElementById('add-work-progress-btn');
    const bottomAddWorkProgressBtn = document.getElementById('bottom-add-work-progress-btn');
    
    // Check if add inventory button exists
    const addInventoryBtn = document.getElementById('add-inventory-btn');
    const bottomAddInventoryBtn = document.getElementById('bottom-add-inventory-btn');
    
    // Function to add vendor that will be used by both buttons
    const addVendorFunction = function() {
        console.log('Add vendor button clicked in inline script');
        
        // Get the counter from the global scope if it exists, or start at 1
        window.vendorCounter = window.vendorCounter || 0;
        window.vendorCounter++;
        
        const vendorId = window.vendorCounter;
        
        // Initialize laborer counter for this vendor
        window.laborerCounters = window.laborerCounters || {};
        window.laborerCounters[vendorId] = 0;
        
        // Get template content
        const templateDiv = document.getElementById('vendor-template');
        if (!templateDiv) {
            console.error("Vendor template not found in inline script");
            return;
        }
        
        // Get HTML and replace placeholders
        const templateContent = templateDiv.innerHTML;
        const vendorHtml = templateContent
            .replace(/{VENDOR_ID}/g, vendorId)
            .replace(/{VENDOR_NUMBER}/g, vendorId);
        
        // Get the vendors container and append new vendor
        const vendorsContainer = document.getElementById('vendors-container');
        if (!vendorsContainer) {
            console.error("Vendors container not found in inline script");
            return;
        }
        
        // Append new vendor HTML
        vendorsContainer.insertAdjacentHTML('beforeend', vendorHtml);
        console.log('Vendor added with ID: ' + vendorId + ' in inline script');
        
        // Scroll to the newly added vendor after a short delay
        setTimeout(() => {
            const vendorElement = document.querySelector(`.vendor-item[data-vendor-id="${vendorId}"]`);
            if (vendorElement) {
                vendorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    };
    
    if (addVendorBtn) {
        console.log('Add vendor button found in inline script');
        addVendorBtn.onclick = addVendorFunction;
    } else {
        console.error('Add vendor button not found in inline script!');
    }
    
    // Add event listener to bottom add vendor button
    if (bottomAddVendorBtn) {
        console.log('Bottom add vendor button found in inline script');
        bottomAddVendorBtn.onclick = addVendorFunction;
    }
    
    // Add the global function for adding laborers
    window.addLaborer = function(vendorId) {
        console.log('Add laborer clicked for vendor ID: ' + vendorId);
        
        // Get or initialize laborer counter for this vendor
        window.laborerCounters = window.laborerCounters || {};
        window.laborerCounters[vendorId] = window.laborerCounters[vendorId] || 0;
        window.laborerCounters[vendorId]++;
        
        const laborerId = window.laborerCounters[vendorId];
        
        // Get template content
        const templateDiv = document.getElementById('laborer-template');
        if (!templateDiv) {
            console.error("Laborer template not found");
            return;
        }
        
        // Get HTML and replace placeholders
        const templateContent = templateDiv.innerHTML;
        const laborerHtml = templateContent
            .replace(/{VENDOR_ID}/g, vendorId)
            .replace(/{LABORER_ID}/g, laborerId)
            .replace(/{LABORER_NUMBER}/g, laborerId);
        
        // Get the laborers container for this vendor and append new laborer
        const laborersContainer = document.getElementById('laborers-container-' + vendorId);
        if (!laborersContainer) {
            console.error("Laborers container not found for vendor ID: " + vendorId);
            return;
        }
        
        // Append new laborer HTML
        laborersContainer.insertAdjacentHTML('beforeend', laborerHtml);
        console.log('Laborer added with ID: ' + laborerId + ' for vendor ID: ' + vendorId);
        
        // Scroll to the newly added laborer after a short delay
        setTimeout(() => {
            const laborerElement = document.querySelector(`#laborers-container-${vendorId} .laborer-item[data-laborer-id="${laborerId}"]`);
            if (laborerElement) {
                laborerElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    };
    
    // Add the global function for removing laborers
    window.removeLaborer = function(vendorId, laborerId) {
        console.log('Remove laborer clicked for vendor ID: ' + vendorId + ', laborer ID: ' + laborerId);
        
        const laborerItem = document.querySelector(`.laborer-item[data-laborer-id="${laborerId}"]`);
        if (laborerItem && laborerItem.parentNode) {
            laborerItem.parentNode.removeChild(laborerItem);
            console.log('Laborer removed with ID: ' + laborerId);
            
            // Renumber remaining laborers for this vendor
            const remainingLaborers = document.querySelectorAll(`#laborers-container-${vendorId} .laborer-item`);
            remainingLaborers.forEach((laborer, index) => {
                const laborerNumber = index + 1;
                const numberElement = laborer.querySelector('.laborer-number');
                if (numberElement) {
                    numberElement.textContent = laborerNumber;
                }
            });
        }
    };
    
    // Add the global function for removing vendors
    window.removeVendor = function(vendorId) {
        console.log('Remove vendor clicked for vendor ID: ' + vendorId);
        
        const vendorItem = document.querySelector(`.vendor-item[data-vendor-id="${vendorId}"]`);
        if (vendorItem && vendorItem.parentNode) {
            vendorItem.parentNode.removeChild(vendorItem);
            console.log('Vendor removed with ID: ' + vendorId);
            
            // Renumber remaining vendors
            const remainingVendors = document.querySelectorAll('.vendor-item');
            remainingVendors.forEach((vendor, index) => {
                const vendorNumber = index + 1;
                const numberElement = vendor.querySelector('.vendor-number');
                if (numberElement) {
                    numberElement.textContent = vendorNumber;
                }
                
                // Update vendor title text if needed
                const titleText = vendor.querySelector('.vendor-title span');
                if (titleText) {
                    const typeSelect = vendor.querySelector('.vendor-type-select');
                    const vendorType = typeSelect ? typeSelect.value : '';
                    if (vendorType) {
                        titleText.textContent = `${vendorType} #${vendorNumber}`;
                    } else {
                        titleText.textContent = `Vendor #${vendorNumber}`;
                    }
                }
            });
        }
    };
    
    // Add the global function for updating vendor icons based on type
    window.updateVendorIcon = function(selectElement, vendorId) {
        const vendorType = selectElement.value;
        const vendorTitleIcon = document.querySelector(`.vendor-item[data-vendor-id="${vendorId}"] .vendor-title i`);
        
        if (vendorTitleIcon) {
            // Remove existing classes
            vendorTitleIcon.className = '';
            
            // Add appropriate icon class based on vendor type
            switch(vendorType) {
                case 'Carpenter':
                    vendorTitleIcon.className = 'fas fa-hammer';
                    break;
                case 'Electrician':
                    vendorTitleIcon.className = 'fas fa-bolt';
                    break;
                case 'Plumber':
                    vendorTitleIcon.className = 'fas fa-faucet';
                    break;
                case 'Mason':
                    vendorTitleIcon.className = 'fas fa-brush';
                    break;
                case 'Painter':
                    vendorTitleIcon.className = 'fas fa-paint-roller';
                    break;
                case 'HVAC':
                    vendorTitleIcon.className = 'fas fa-fan';
                    break;
                case 'Roofer':
                    vendorTitleIcon.className = 'fas fa-home';
                    break;
                case 'Landscaper':
                    vendorTitleIcon.className = 'fas fa-tree';
                    break;
                case 'Concrete':
                    vendorTitleIcon.className = 'fas fa-ruler';
                    break;
                default:
                    vendorTitleIcon.className = 'fas fa-hard-hat';
            }
        }
    };
    
    // Add function to calculate laborer totals
    window.calculateLaborerTotal = function(vendorId, laborerId) {
        // Get all input fields
        const morningAttendanceSelect = document.getElementById(`laborer-morning-${vendorId}-${laborerId}`);
        const eveningAttendanceSelect = document.getElementById(`laborer-evening-${vendorId}-${laborerId}`);
        const wagesInput = document.getElementById(`laborer-wages-${vendorId}-${laborerId}`);
        const dayTotalInput = document.getElementById(`laborer-day-total-${vendorId}-${laborerId}`);
        const otHoursInput = document.getElementById(`laborer-ot-hours-${vendorId}-${laborerId}`);
        const otMinutesInput = document.getElementById(`laborer-ot-minutes-${vendorId}-${laborerId}`);
        const otRateInput = document.getElementById(`laborer-ot-rate-${vendorId}-${laborerId}`);
        const otAmountInput = document.getElementById(`laborer-ot-amount-${vendorId}-${laborerId}`);
        const totalInput = document.getElementById(`laborer-total-${vendorId}-${laborerId}`);
        
        if (!wagesInput || !otHoursInput || !otMinutesInput || !otRateInput || !otAmountInput || !totalInput) {
            console.error("Some laborer input fields could not be found");
            return;
        }
        
        // Get values from inputs
        const morningAttendance = morningAttendanceSelect.value;
        const eveningAttendance = eveningAttendanceSelect.value;
        const wages = parseFloat(wagesInput.value) || 0;
        const otHours = parseInt(otHoursInput.value) || 0;
        const otMinutes = parseInt(otMinutesInput.value) || 0;
        const otRate = parseFloat(otRateInput.value) || 0;
        
        // Calculate morning attendance factor (0 = absent, 0.5 = present)
        let morningFactor = morningAttendance === 'P' ? 0.5 : 0;
        
        // Calculate evening attendance factor (0 = absent, 0.5 = present)
        let eveningFactor = eveningAttendance === 'P' ? 0.5 : 0;
        
        // Combined attendance factor (morning and afternoon each count for half a day)
        const combinedFactor = morningFactor + eveningFactor;
        
        // Calculate day wages based on attendance
        const dayTotal = wages * combinedFactor;
        
        // Calculate total overtime hours (convert minutes to decimal hours)
        const totalOTHours = otHours + (otMinutes / 60);
        
        // Calculate overtime amount
        const otAmount = totalOTHours * otRate;
        
        // Calculate final total
        const total = dayTotal + otAmount;
        
        // Update the fields
        dayTotalInput.value = dayTotal.toFixed(2);
        otAmountInput.value = otAmount.toFixed(2);
        totalInput.value = total.toFixed(2);
        
        console.log(`Calculated totals for laborer ${laborerId}:`, { 
            wages, 
            morningAttendance,
            morningFactor,
            eveningAttendance,
            eveningFactor,
            combinedFactor,
            dayTotal,
            otHours,
            otMinutes,
            totalOTHours,
            otRate, 
            otAmount, 
            total 
        });
    };

    // Function to automatically update OT rate when wages change and OT rate is empty
    window.updateOTRate = function(vendorId, laborerId) {
        const wagesInput = document.getElementById(`laborer-wages-${vendorId}-${laborerId}`);
        const otRateInput = document.getElementById(`laborer-ot-rate-${vendorId}-${laborerId}`);
        
        // Only update if OT rate is empty
        if (!otRateInput.value) {
            const wages = parseFloat(wagesInput.value) || 0;
            const otRate = wages / 8 * 1.5; // 1.5x hourly wage assuming 8-hour day
            otRateInput.value = otRate.toFixed(2);
        }
    };

    // Add company labour counter
    window.companyLabourCounter = 0;
    
    // Function to add company labour
    const addCompanyLabourFunction = function() {
        console.log('Add company labour button clicked');
        
        window.companyLabourCounter++;
        const companyLabourId = window.companyLabourCounter;
        
        // Get template content
        const templateDiv = document.getElementById('company-labour-template');
        if (!templateDiv) {
            console.error("Company labour template not found");
            return;
        }
        
        // Get HTML and replace placeholders
        const templateContent = templateDiv.innerHTML;
        const companyLabourHtml = templateContent
            .replace(/{COMPANY_LABOUR_ID}/g, companyLabourId)
            .replace(/{COMPANY_LABOUR_NUMBER}/g, companyLabourId);
        
        // Get the company labours container and append new company labour
        const companyLaboursContainer = document.getElementById('company-labours-container');
        if (!companyLaboursContainer) {
            console.error("Company labours container not found");
            return;
        }
        
        // Append new company labour HTML
        companyLaboursContainer.insertAdjacentHTML('beforeend', companyLabourHtml);
        console.log('Company labour added with ID: ' + companyLabourId);
        
        // Scroll to the newly added company labour after a short delay
        setTimeout(() => {
            const companyLabourElement = document.querySelector(`.company-labour-item[data-company-labour-id="${companyLabourId}"]`);
            if (companyLabourElement) {
                companyLabourElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    };
    
    // Set up add company labour buttons
    if (addCompanyLabourBtn) {
        addCompanyLabourBtn.onclick = addCompanyLabourFunction;
    }
    
    if (bottomAddCompanyLabourBtn) {
        bottomAddCompanyLabourBtn.onclick = addCompanyLabourFunction;
    }

    // Add the global function for removing company labours
    window.removeCompanyLabour = function(companyLabourId) {
        console.log('Remove company labour clicked for ID: ' + companyLabourId);
        
        const companyLabourItem = document.querySelector(`.company-labour-item[data-company-labour-id="${companyLabourId}"]`);
        if (companyLabourItem && companyLabourItem.parentNode) {
            companyLabourItem.parentNode.removeChild(companyLabourItem);
            console.log('Company labour removed with ID: ' + companyLabourId);
            
            // Renumber remaining company labours
            const remainingCompanyLabours = document.querySelectorAll('.company-labour-item');
            remainingCompanyLabours.forEach((companyLabour, index) => {
                const companyLabourNumber = index + 1;
                const numberElement = companyLabour.querySelector('.company-labour-number');
                if (numberElement) {
                    numberElement.textContent = companyLabourNumber;
                }
            });
        }
    };
    
    // Add function to calculate company labour totals
    window.calculateCompanyLabourTotal = function(companyLabourId) {
        // Get all input fields
        const morningAttendanceSelect = document.getElementById(`company-labour-morning-${companyLabourId}`);
        const eveningAttendanceSelect = document.getElementById(`company-labour-evening-${companyLabourId}`);
        const wagesInput = document.getElementById(`company-labour-wages-${companyLabourId}`);
        const dayTotalInput = document.getElementById(`company-labour-day-total-${companyLabourId}`);
        const otHoursInput = document.getElementById(`company-labour-ot-hours-${companyLabourId}`);
        const otMinutesInput = document.getElementById(`company-labour-ot-minutes-${companyLabourId}`);
        const otRateInput = document.getElementById(`company-labour-ot-rate-${companyLabourId}`);
        const otAmountInput = document.getElementById(`company-labour-ot-amount-${companyLabourId}`);
        const totalInput = document.getElementById(`company-labour-total-${companyLabourId}`);
        
        if (!wagesInput || !otHoursInput || !otMinutesInput || !otRateInput || !otAmountInput || !totalInput) {
            console.error("Some company labour input fields could not be found");
            return;
        }
        
        // Get values from inputs
        const morningAttendance = morningAttendanceSelect.value;
        const eveningAttendance = eveningAttendanceSelect.value;
        const wages = parseFloat(wagesInput.value) || 0;
        const otHours = parseInt(otHoursInput.value) || 0;
        const otMinutes = parseInt(otMinutesInput.value) || 0;
        const otRate = parseFloat(otRateInput.value) || 0;
        
        // Calculate morning attendance factor (0 = absent, 0.5 = present)
        let morningFactor = morningAttendance === 'P' ? 0.5 : 0;
        
        // Calculate evening attendance factor (0 = absent, 0.5 = present)
        let eveningFactor = eveningAttendance === 'P' ? 0.5 : 0;
        
        // Combined attendance factor (morning and afternoon each count for half a day)
        const combinedFactor = morningFactor + eveningFactor;
        
        // Calculate day wages based on attendance
        const dayTotal = wages * combinedFactor;
        
        // Calculate total overtime hours (convert minutes to decimal hours)
        const totalOTHours = otHours + (otMinutes / 60);
        
        // Calculate overtime amount
        const otAmount = totalOTHours * otRate;
        
        // Calculate final total
        const total = dayTotal + otAmount;
        
        // Update the fields
        dayTotalInput.value = dayTotal.toFixed(2);
        otAmountInput.value = otAmount.toFixed(2);
        totalInput.value = total.toFixed(2);
        
        console.log(`Calculated totals for company labour ${companyLabourId}:`, { 
            wages, 
            morningAttendance,
            morningFactor,
            eveningAttendance,
            eveningFactor,
            combinedFactor,
            dayTotal,
            otHours,
            otMinutes,
            totalOTHours,
            otRate, 
            otAmount, 
            total 
        });
    };
    
    // Function to automatically update company labour OT rate when wages change
    window.updateCompanyOTRate = function(companyLabourId) {
        const wagesInput = document.getElementById(`company-labour-wages-${companyLabourId}`);
        const otRateInput = document.getElementById(`company-labour-ot-rate-${companyLabourId}`);
        
        // Only update if OT rate is empty
        if (!otRateInput.value) {
            const wages = parseFloat(wagesInput.value) || 0;
            const otRate = wages / 8 * 1.5; // 1.5x hourly wage assuming 8-hour day
            otRateInput.value = otRate.toFixed(2);
        }
    };

    // Add travel expense counter
    window.travelExpenseCounter = 0;
    
    // Function to add travel expense
    const addTravelExpenseFunction = function() {
        console.log('Add travel expense button clicked');
        
        window.travelExpenseCounter++;
        const travelExpenseId = window.travelExpenseCounter;
        
        // Get template content
        const templateDiv = document.getElementById('travel-expense-template');
        if (!templateDiv) {
            console.error("Travel expense template not found");
            return;
        }
        
        // Get HTML and replace placeholders
        const templateContent = templateDiv.innerHTML;
        const travelExpenseHtml = templateContent
            .replace(/{TRAVEL_EXPENSE_ID}/g, travelExpenseId)
            .replace(/{TRAVEL_EXPENSE_NUMBER}/g, travelExpenseId);
        
        // Get the travel expenses container and append new travel expense
        const travelExpensesContainer = document.getElementById('travel-expenses-container');
        if (!travelExpensesContainer) {
            console.error("Travel expenses container not found");
            return;
        }
        
        // Append new travel expense HTML
        travelExpensesContainer.insertAdjacentHTML('beforeend', travelExpenseHtml);
        console.log('Travel expense added with ID: ' + travelExpenseId);
        
        // Scroll to the newly added travel expense after a short delay
        setTimeout(() => {
            const travelExpenseElement = document.querySelector(`.travel-expense-item[data-travel-expense-id="${travelExpenseId}"]`);
            if (travelExpenseElement) {
                travelExpenseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    };
    
    // Set up add travel expense buttons
    if (addTravelExpenseBtn) {
        addTravelExpenseBtn.onclick = addTravelExpenseFunction;
    }
    
    if (bottomAddTravelExpenseBtn) {
        bottomAddTravelExpenseBtn.onclick = addTravelExpenseFunction;
    }
    
    // Add the global function for removing travel expenses
    window.removeTravelExpense = function(travelExpenseId) {
        console.log('Remove travel expense clicked for ID: ' + travelExpenseId);
        
        const travelExpenseItem = document.querySelector(`.travel-expense-item[data-travel-expense-id="${travelExpenseId}"]`);
        if (travelExpenseItem && travelExpenseItem.parentNode) {
            travelExpenseItem.parentNode.removeChild(travelExpenseItem);
            console.log('Travel expense removed with ID: ' + travelExpenseId);
            
            // Renumber remaining travel expenses
            const remainingTravelExpenses = document.querySelectorAll('.travel-expense-item');
            remainingTravelExpenses.forEach((travelExpense, index) => {
                const travelExpenseNumber = index + 1;
                const numberElement = travelExpense.querySelector('.travel-expense-number');
                if (numberElement) {
                    numberElement.textContent = travelExpenseNumber;
                }
            });
        }
    };

    // Add beverage counter
    window.beverageCounter = 0;
    
    // Function to add beverage
    const addBeverageFunction = function() {
        console.log('Add beverage button clicked');
        
        window.beverageCounter++;
        const beverageId = window.beverageCounter;
        
        // Get template content
        const templateDiv = document.getElementById('beverage-template');
        if (!templateDiv) {
            console.error("Beverage template not found");
            return;
        }
        
        // Get HTML and replace placeholders
        const templateContent = templateDiv.innerHTML;
        const beverageHtml = templateContent
            .replace(/{BEVERAGE_ID}/g, beverageId)
            .replace(/{BEVERAGE_NUMBER}/g, beverageId);
        
        // Get the beverages container and append new beverage
        const beveragesContainer = document.getElementById('beverages-container');
        if (!beveragesContainer) {
            console.error("Beverages container not found");
            return;
        }
        
        // Append new beverage HTML
        beveragesContainer.insertAdjacentHTML('beforeend', beverageHtml);
        console.log('Beverage added with ID: ' + beverageId);
        
        // Scroll to the newly added beverage after a short delay
        setTimeout(() => {
            const beverageElement = document.querySelector(`.beverage-item[data-beverage-id="${beverageId}"]`);
            if (beverageElement) {
                beverageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    };
    
    // Set up add beverage buttons
    if (addBeverageBtn) {
        addBeverageBtn.onclick = addBeverageFunction;
    }
    
    if (bottomAddBeverageBtn) {
        bottomAddBeverageBtn.onclick = addBeverageFunction;
    }
    
    // Add the global function for removing beverages
    window.removeBeverage = function(beverageId) {
        console.log('Remove beverage clicked for ID: ' + beverageId);
        
        const beverageItem = document.querySelector(`.beverage-item[data-beverage-id="${beverageId}"]`);
        if (beverageItem && beverageItem.parentNode) {
            beverageItem.parentNode.removeChild(beverageItem);
            console.log('Beverage removed with ID: ' + beverageId);
            
            // Renumber remaining beverages
            const remainingBeverages = document.querySelectorAll('.beverage-item');
            remainingBeverages.forEach((beverage, index) => {
                const beverageNumber = index + 1;
                const numberElement = beverage.querySelector('.beverage-number');
                if (numberElement) {
                    numberElement.textContent = beverageNumber;
                }
            });
        }
    };
    
    // Add the global function for updating beverage icons based on type
    window.updateBeverageIcon = function(selectElement, beverageId) {
        const beverageType = selectElement.value;
        const beverageTitleIcon = document.querySelector(`.beverage-item[data-beverage-id="${beverageId}"] .vendor-title i`);
        
        if (beverageTitleIcon) {
            // Remove existing classes
            beverageTitleIcon.className = '';
            
            // Add appropriate icon class based on beverage type
            switch(beverageType) {
                case 'Tea':
                    beverageTitleIcon.className = 'fas fa-mug-hot';
                    break;
                case 'Coffee':
                    beverageTitleIcon.className = 'fas fa-coffee';
                    break;
                case 'Water':
                    beverageTitleIcon.className = 'fas fa-tint';
                    break;
                case 'Soft Drink':
                    beverageTitleIcon.className = 'fas fa-glass-whiskey';
                    break;
                case 'Juice':
                    beverageTitleIcon.className = 'fas fa-wine-glass';
                    break;
                default:
                    beverageTitleIcon.className = 'fas fa-coffee';
            }
        }
    };

    // Add work progress counter
    window.workProgressCounter = 0;
    
    // Work type options based on category
    window.workTypeOptions = {
        'Civil Work': [
            'Foundation Work', 
            'Concrete Pouring', 
            'Brick/Block Work', 
            'Plastering', 
            'Waterproofing', 
            'RCC Work', 
            'Excavation', 
            'Other Civil Work'
        ],
        'Interior Work': [
            'Flooring', 
            'Ceiling Work', 
            'Wall Finishing', 
            'Carpentry', 
            'Furniture Installation', 
            'Kitchen Work', 
            'Bathroom Fitting', 
            'Other Interior Work'
        ],
        'Facade Work': [
            'Glass Installation', 
            'Cladding', 
            'Paint Work', 
            'Stone Work', 
            'External Plastering', 
            'Other Facade Work'
        ],
        'Finishing Work': [
            'Painting', 
            'Polishing', 
            'Tile Work', 
            'Trim Work', 
            'Final Cleaning', 
            'Punch List Items', 
            'Other Finishing Work'
        ]
    };
    
    // Function to update work type options based on category selection
    window.updateWorkTypeOptions = function(workProgressId) {
        const categorySelect = document.getElementById(`work-category-${workProgressId}`);
        const typeSelect = document.getElementById(`work-type-${workProgressId}`);
        
        if (!categorySelect || !typeSelect) {
            console.error('Category or type select not found');
            return;
        }
        
        const selectedCategory = categorySelect.value;
        
        // Clear existing options
        typeSelect.innerHTML = '';
        
        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = selectedCategory ? 'Select work type' : 'Select work category first';
        typeSelect.appendChild(defaultOption);
        
        // If a category is selected, add corresponding type options
        if (selectedCategory && window.workTypeOptions[selectedCategory]) {
            window.workTypeOptions[selectedCategory].forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = type;
                typeSelect.appendChild(option);
            });
        }
    };
    
    // Function to update work progress icon based on category
    window.updateWorkIcon = function(workProgressId) {
        const categorySelect = document.getElementById(`work-category-${workProgressId}`);
        const workTitleIcon = document.querySelector(`.work-progress-item[data-work-progress-id="${workProgressId}"] .vendor-title i`);
        
        if (categorySelect && workTitleIcon) {
            const category = categorySelect.value;
            
            // Remove existing classes
            workTitleIcon.className = '';
            
            // Set icon based on category
            switch(category) {
                case 'Civil Work':
                    workTitleIcon.className = 'fas fa-hard-hat';
                    break;
                case 'Interior Work':
                    workTitleIcon.className = 'fas fa-couch';
                    break;
                case 'Facade Work':
                    workTitleIcon.className = 'fas fa-building';
                    break;
                case 'Finishing Work':
                    workTitleIcon.className = 'fas fa-paint-roller';
                    break;
                default:
                    workTitleIcon.className = 'fas fa-hard-hat';
            }
        }
    };
    
    // Function to add work progress
    const addWorkProgressFunction = function() {
        console.log('Add work progress button clicked');
        
        window.workProgressCounter++;
        const workProgressId = window.workProgressCounter;
        
        // Get template content
        const templateDiv = document.getElementById('work-progress-template');
        if (!templateDiv) {
            console.error("Work progress template not found");
            return;
        }
        
        // Get HTML and replace placeholders
        const templateContent = templateDiv.innerHTML;
        const workProgressHtml = templateContent
            .replace(/{WORK_PROGRESS_ID}/g, workProgressId)
            .replace(/{WORK_PROGRESS_NUMBER}/g, workProgressId);
        
        // Get the work progress container and append new work progress
        const workProgressContainer = document.getElementById('work-progress-container');
        if (!workProgressContainer) {
            console.error("Work progress container not found");
            return;
        }
        
        // Append new work progress HTML
        workProgressContainer.insertAdjacentHTML('beforeend', workProgressHtml);
        console.log('Work progress added with ID: ' + workProgressId);
        
        // Set up file upload preview for the new work progress item
        setupFilePreview(workProgressId);
        
        // Scroll to the newly added work progress after a short delay
        setTimeout(() => {
            const workProgressElement = document.querySelector(`.work-progress-item[data-work-progress-id="${workProgressId}"]`);
            if (workProgressElement) {
                workProgressElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    };
    
    // Set up add work progress buttons
    if (addWorkProgressBtn) {
        addWorkProgressBtn.onclick = addWorkProgressFunction;
    }
    
    if (bottomAddWorkProgressBtn) {
        bottomAddWorkProgressBtn.onclick = addWorkProgressFunction;
    }
    
    // Add the global function for removing work progress
    window.removeWorkProgress = function(workProgressId) {
        console.log('Remove work progress clicked for ID: ' + workProgressId);
        
        const workProgressItem = document.querySelector(`.work-progress-item[data-work-progress-id="${workProgressId}"]`);
        if (workProgressItem && workProgressItem.parentNode) {
            workProgressItem.parentNode.removeChild(workProgressItem);
            console.log('Work progress removed with ID: ' + workProgressId);
            
            // Renumber remaining work progress items
            const remainingWorkProgress = document.querySelectorAll('.work-progress-item');
            remainingWorkProgress.forEach((workProgress, index) => {
                const workProgressNumber = index + 1;
                const numberElement = workProgress.querySelector('.work-progress-number');
                if (numberElement) {
                    numberElement.textContent = workProgressNumber;
                }
            });
        }
    };
    
    // Function to set up file upload preview
    function setupFilePreview(workProgressId) {
        // This function is intentionally left empty as we now handle the preview 
        // directly through the previewMedia function triggered on file input change
    }
    
    // Function to add more media items
    window.addMoreMediaItem = function(workProgressId) {
        // Get the container
        const container = document.getElementById(`media-items-container-${workProgressId}`);
        if (!container) {
            console.error('Media items container not found');
            return;
        }
        
        // Get current items to determine next ID
        const currentItems = container.querySelectorAll('.media-upload-item');
        const nextItemId = currentItems.length + 1;
        
        // Create new media item
        const newItem = document.createElement('div');
        newItem.className = 'media-upload-item';
        newItem.setAttribute('data-item-id', nextItemId);
        
        newItem.innerHTML = `
            <div class="media-upload-header">
                <span class="media-item-title">Media Item #${nextItemId}</span>
                <button type="button" class="btn-remove-media" onclick="removeMediaItem(${workProgressId}, ${nextItemId})" title="Remove media item">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="media-upload-body">
                <div class="form-group">
                    <div class="media-upload-input">
                        <input type="file" id="work-media-${workProgressId}-${nextItemId}" name="work_progress_media_${workProgressId}[]" class="form-control file-upload media-file-input" accept="image/*,video/*" onchange="previewMedia(this, ${workProgressId}, ${nextItemId})">
                        <label for="work-media-${workProgressId}-${nextItemId}" class="media-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Photo/Video
                        </label>
                    </div>
                    <div class="media-type-indicator" id="media-type-${workProgressId}-${nextItemId}"></div>
                </div>
                <div class="media-preview-container" id="media-preview-container-${workProgressId}-${nextItemId}">
                    <!-- Preview will be shown here -->
                </div>
            </div>
        `;
        
        // Add to container
        container.appendChild(newItem);
        
        // Scroll to the newly added item
        newItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    
    // Function to remove media item
    window.removeMediaItem = function(workProgressId, itemId) {
        const item = document.querySelector(`#media-items-container-${workProgressId} .media-upload-item[data-item-id="${itemId}"]`);
        if (item && item.parentNode) {
            item.parentNode.removeChild(item);
            
            // Renumber remaining items
            const remainingItems = document.querySelectorAll(`#media-items-container-${workProgressId} .media-upload-item`);
            remainingItems.forEach((item, index) => {
                const newId = index + 1;
                const oldId = item.getAttribute('data-item-id');
                
                // Update item ID attribute
                item.setAttribute('data-item-id', newId);
                
                // Update title
                const titleElement = item.querySelector('.media-item-title');
                if (titleElement) {
                    titleElement.textContent = `Media Item #${newId}`;
                }
                
                // Update remove button
                const removeButton = item.querySelector('.btn-remove-media');
                if (removeButton) {
                    removeButton.setAttribute('onclick', `removeMediaItem(${workProgressId}, ${newId})`);
                }
                
                // Update file input ID and label
                const fileInput = item.querySelector('.media-file-input');
                const fileLabel = item.querySelector('.media-upload-label');
                if (fileInput) {
                    const newInputId = `work-media-${workProgressId}-${newId}`;
                    fileInput.id = newInputId;
                    if (fileLabel) {
                        fileLabel.setAttribute('for', newInputId);
                    }
                }
                
                // Update preview container ID
                const previewContainer = item.querySelector('.media-preview-container');
                if (previewContainer) {
                    previewContainer.id = `media-preview-container-${workProgressId}-${newId}`;
                }
                
                // Update type indicator ID
                const typeIndicator = item.querySelector('.media-type-indicator');
                if (typeIndicator) {
                    typeIndicator.id = `media-type-${workProgressId}-${newId}`;
                }
            });
        }
    };
    
    // Function to preview uploaded media
    window.previewMedia = function(input, workProgressId, itemId) {
        const previewContainer = document.getElementById(`media-preview-container-${workProgressId}-${itemId}`);
        const typeIndicator = document.getElementById(`media-type-${workProgressId}-${itemId}`);
        
        if (!previewContainer || !typeIndicator) {
            console.error('Preview container or type indicator not found');
            return;
        }
        
        // Clear previous preview
        previewContainer.innerHTML = '';
        typeIndicator.innerHTML = '';
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (file.type.startsWith('image/')) {
                    // Handle image preview
                    previewContainer.innerHTML = `<img src="${e.target.result}" class="media-preview-image" alt="Preview">`;
                    typeIndicator.innerHTML = '<span class="badge badge-success"><i class="fas fa-image"></i> Photo</span>';
                } else if (file.type.startsWith('video/')) {
                    // Handle video preview
                    previewContainer.innerHTML = `
                        <video controls class="media-preview-video">
                            <source src="${e.target.result}" type="${file.type}">
                            Your browser does not support video playback.
                        </video>
                    `;
                    typeIndicator.innerHTML = '<span class="badge badge-primary"><i class="fas fa-video"></i> Video</span>';
                }
                
                // Add filename
                const fileNameElement = document.createElement('div');
                fileNameElement.className = 'media-file-name';
                fileNameElement.textContent = file.name.length > 25 ? file.name.substring(0, 22) + '...' : file.name;
                previewContainer.appendChild(fileNameElement);
            };
            
            reader.readAsDataURL(file);
        }
    };
    
    // Function to set up media upload within work progress
    function setupFilePreview(workProgressId) {
        // This function is intentionally left empty as we now handle the preview 
        // directly through the previewMedia function triggered on file input change
    }

    // Add inventory counter
    window.inventoryCounter = 0;
    
    // Function to update inventory icon based on material selection
    window.updateInventoryIcon = function(inventoryId) {
        const materialSelect = document.getElementById(`inventory-material-${inventoryId}`);
        const inventoryTitleIcon = document.querySelector(`.inventory-item[data-inventory-id="${inventoryId}"] .vendor-title i`);
        
        if (materialSelect && inventoryTitleIcon) {
            const material = materialSelect.value;
            
            // Remove existing classes
            inventoryTitleIcon.className = '';
            
            // Set icon based on material
            switch(material) {
                case 'Cement':
                    inventoryTitleIcon.className = 'fas fa-box';
                    break;
                case 'Sand':
                case 'Aggregates':
                    inventoryTitleIcon.className = 'fas fa-mountain';
                    break;
                case 'Bricks':
                    inventoryTitleIcon.className = 'fas fa-th-large';
                    break;
                case 'Steel':
                    inventoryTitleIcon.className = 'fas fa-grip-lines';
                    break;
                case 'Wood':
                    inventoryTitleIcon.className = 'fas fa-tree';
                    break;
                case 'Paint':
                    inventoryTitleIcon.className = 'fas fa-fill-drip';
                    break;
                case 'Tiles':
                    inventoryTitleIcon.className = 'fas fa-border-all';
                    break;
                case 'Glass':
                    inventoryTitleIcon.className = 'fas fa-glasses';
                    break;
                case 'Electrical':
                    inventoryTitleIcon.className = 'fas fa-plug';
                    break;
                case 'Plumbing':
                    inventoryTitleIcon.className = 'fas fa-faucet';
                    break;
                case 'Hardware':
                    inventoryTitleIcon.className = 'fas fa-tools';
                    break;
                case 'Equipment':
                    inventoryTitleIcon.className = 'fas fa-truck';
                    break;
                case 'Tools':
                    inventoryTitleIcon.className = 'fas fa-hammer';
                    break;
                default:
                    inventoryTitleIcon.className = 'fas fa-boxes';
            }
        }
    };
    
    // Function to add inventory item
    const addInventoryFunction = function() {
        console.log('Add inventory button clicked');
        
        window.inventoryCounter++;
        const inventoryId = window.inventoryCounter;
        
        // Get template content
        const templateDiv = document.getElementById('inventory-template');
        if (!templateDiv) {
            console.error("Inventory template not found");
            return;
        }
        
        // Get HTML and replace placeholders
        const templateContent = templateDiv.innerHTML;
        const inventoryHtml = templateContent
            .replace(/{INVENTORY_ID}/g, inventoryId)
            .replace(/{INVENTORY_NUMBER}/g, inventoryId);
        
        // Get the inventory container and append new inventory item
        const inventoryContainer = document.getElementById('inventory-container');
        if (!inventoryContainer) {
            console.error("Inventory container not found");
            return;
        }
        
        // Append new inventory HTML
        inventoryContainer.insertAdjacentHTML('beforeend', inventoryHtml);
        console.log('Inventory item added with ID: ' + inventoryId);
        
        // Scroll to the newly added inventory item after a short delay
        setTimeout(() => {
            const inventoryElement = document.querySelector(`.inventory-item[data-inventory-id="${inventoryId}"]`);
            if (inventoryElement) {
                inventoryElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    };
    
    // Set up add inventory buttons
    if (addInventoryBtn) {
        addInventoryBtn.onclick = addInventoryFunction;
    }
    
    if (bottomAddInventoryBtn) {
        bottomAddInventoryBtn.onclick = addInventoryFunction;
    }
    
    // Function to remove inventory item
    window.removeInventory = function(inventoryId) {
        console.log('Remove inventory clicked for ID: ' + inventoryId);
        
        const inventoryItem = document.querySelector(`.inventory-item[data-inventory-id="${inventoryId}"]`);
        if (inventoryItem && inventoryItem.parentNode) {
            inventoryItem.parentNode.removeChild(inventoryItem);
            console.log('Inventory removed with ID: ' + inventoryId);
            
            // Renumber remaining inventory items
            const remainingInventory = document.querySelectorAll('.inventory-item');
            remainingInventory.forEach((inventory, index) => {
                const inventoryNumber = index + 1;
                const numberElement = inventory.querySelector('.inventory-number');
                if (numberElement) {
                    numberElement.textContent = inventoryNumber;
                }
            });
        }
    };
    
    // Function to preview bill picture
    window.previewBill = function(input, inventoryId) {
        const previewContainer = document.getElementById(`bill-preview-${inventoryId}`);
        
        if (!previewContainer) {
            console.error('Bill preview container not found');
            return;
        }
        
        // Clear previous preview
        previewContainer.innerHTML = '';
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (file.type.startsWith('image/')) {
                    // Handle image preview
                    const previewWrapper = document.createElement('div');
                    previewWrapper.className = 'bill-preview-wrapper';
                    
                    const img = document.createElement('img');
                    img.className = 'bill-preview-image';
                    img.src = e.target.result;
                    img.alt = 'Bill Preview';
                    
                    const indicator = document.createElement('div');
                    indicator.className = 'bill-indicator';
                    indicator.innerHTML = '<span class="badge badge-success"><i class="fas fa-receipt"></i> Bill Uploaded</span>';
                    
                    const fileName = document.createElement('div');
                    fileName.className = 'bill-file-name';
                    fileName.textContent = file.name.length > 25 ? file.name.substring(0, 22) + '...' : file.name;
                    
                    previewWrapper.appendChild(img);
                    previewWrapper.appendChild(indicator);
                    previewWrapper.appendChild(fileName);
                    
                    previewContainer.appendChild(previewWrapper);
                }
            };
            
            reader.readAsDataURL(file);
        }
    };
    
    // Function to add more inventory media items
    window.addMoreInventoryMedia = function(inventoryId) {
        // Get the container
        const container = document.getElementById(`inventory-media-container-${inventoryId}`);
        if (!container) {
            console.error('Inventory media items container not found');
            return;
        }
        
        // Get current items to determine next ID
        const currentItems = container.querySelectorAll('.media-upload-item');
        const nextItemId = currentItems.length + 1;
        
        // Create new media item
        const newItem = document.createElement('div');
        newItem.className = 'media-upload-item';
        newItem.setAttribute('data-item-id', nextItemId);
        
        newItem.innerHTML = `
            <div class="media-upload-header">
                <span class="media-item-title">Media Item #${nextItemId}</span>
                <button type="button" class="btn-remove-media" onclick="removeInventoryMediaItem(${inventoryId}, ${nextItemId})" title="Remove media item">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="media-upload-body">
                <div class="form-group">
                    <div class="media-upload-input">
                        <input type="file" id="inventory-media-${inventoryId}-${nextItemId}" name="inventory_media_${inventoryId}[]" class="form-control file-upload media-file-input" accept="image/*,video/*" onchange="previewInventoryMedia(this, ${inventoryId}, ${nextItemId})">
                        <label for="inventory-media-${inventoryId}-${nextItemId}" class="media-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Photo/Video
                        </label>
                    </div>
                    <div class="media-type-indicator" id="inventory-media-type-${inventoryId}-${nextItemId}"></div>
                </div>
                <div class="media-preview-container" id="inventory-media-preview-${inventoryId}-${nextItemId}">
                    <!-- Preview will be shown here -->
                </div>
            </div>
        `;
        
        // Add to container
        container.appendChild(newItem);
        
        // Scroll to the newly added item
        newItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    
    // Function to remove inventory media item
    window.removeInventoryMediaItem = function(inventoryId, itemId) {
        const item = document.querySelector(`#inventory-media-container-${inventoryId} .media-upload-item[data-item-id="${itemId}"]`);
        if (item && item.parentNode) {
            item.parentNode.removeChild(item);
            
            // Renumber remaining items
            const remainingItems = document.querySelectorAll(`#inventory-media-container-${inventoryId} .media-upload-item`);
            remainingItems.forEach((item, index) => {
                const newId = index + 1;
                const oldId = item.getAttribute('data-item-id');
                
                // Update item ID attribute
                item.setAttribute('data-item-id', newId);
                
                // Update title
                const titleElement = item.querySelector('.media-item-title');
                if (titleElement) {
                    titleElement.textContent = `Media Item #${newId}`;
                }
                
                // Update remove button
                const removeButton = item.querySelector('.btn-remove-media');
                if (removeButton) {
                    removeButton.setAttribute('onclick', `removeInventoryMediaItem(${inventoryId}, ${newId})`);
                }
                
                // Update file input ID and label
                const fileInput = item.querySelector('.media-file-input');
                const fileLabel = item.querySelector('.media-upload-label');
                if (fileInput) {
                    const newInputId = `inventory-media-${inventoryId}-${newId}`;
                    fileInput.id = newInputId;
                    if (fileLabel) {
                        fileLabel.setAttribute('for', newInputId);
                    }
                }
                
                // Update preview container ID
                const previewContainer = item.querySelector('.media-preview-container');
                if (previewContainer) {
                    previewContainer.id = `inventory-media-preview-${inventoryId}-${newId}`;
                }
                
                // Update type indicator ID
                const typeIndicator = item.querySelector('.media-type-indicator');
                if (typeIndicator) {
                    typeIndicator.id = `inventory-media-type-${inventoryId}-${newId}`;
                }
            });
        }
    };
    
    // Function to preview uploaded inventory media
    window.previewInventoryMedia = function(input, inventoryId, itemId) {
        const previewContainer = document.getElementById(`inventory-media-preview-${inventoryId}-${itemId}`);
        const typeIndicator = document.getElementById(`inventory-media-type-${inventoryId}-${itemId}`);
        
        if (!previewContainer || !typeIndicator) {
            console.error('Preview container or type indicator not found');
            return;
        }
        
        // Clear previous preview
        previewContainer.innerHTML = '';
        typeIndicator.innerHTML = '';
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (file.type.startsWith('image/')) {
                    // Handle image preview
                    previewContainer.innerHTML = `<img src="${e.target.result}" class="media-preview-image" alt="Preview">`;
                    typeIndicator.innerHTML = '<span class="badge badge-success"><i class="fas fa-image"></i> Photo</span>';
                } else if (file.type.startsWith('video/')) {
                    // Handle video preview
                    previewContainer.innerHTML = `
                        <video controls class="media-preview-video">
                            <source src="${e.target.result}" type="${file.type}">
                            Your browser does not support video playback.
                        </video>
                    `;
                    typeIndicator.innerHTML = '<span class="badge badge-primary"><i class="fas fa-video"></i> Video</span>';
                }
                
                // Add filename
                const fileNameElement = document.createElement('div');
                fileNameElement.className = 'media-file-name';
                fileNameElement.textContent = file.name.length > 25 ? file.name.substring(0, 22) + '...' : file.name;
                previewContainer.appendChild(fileNameElement);
            };
            
            reader.readAsDataURL(file);
        }
    };
    
    // Setup calculate button
    const calculateBtn = document.getElementById('calculate-totals');
    if (calculateBtn) {
        calculateBtn.addEventListener('click', calculateAllTotals);
    }
    
    // Function to calculate all totals
    function calculateAllTotals() {
        calculateVendorLabourTotal();
        calculateCompanyLabourTotal();
        calculateTravelExpensesTotal();
        calculateBeveragesTotal();
        calculateMiscellaneousTotal();
        calculateGrandTotal();
    }
    
    // Calculate vendor labour total
    function calculateVendorLabourTotal() {
        let total = 0;
        
        // Get all vendor laborers
        const laborerTotals = document.querySelectorAll('input[id^="laborer-total-"]');
        
        // Sum up all laborer totals
        laborerTotals.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
        });
        
        // Update the display
        const vendorLabourTotalElement = document.getElementById('vendor-labour-total');
        if (vendorLabourTotalElement) {
            vendorLabourTotalElement.textContent = total.toFixed(2);
        }
        
        return total;
    }
    
    // Calculate company labour total
    function calculateCompanyLabourTotal() {
        let total = 0;
        
        // Get all company laborers
        const companyLabourTotals = document.querySelectorAll('input[id^="company-labour-total-"]');
        
        // Sum up all company labour totals
        companyLabourTotals.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
        });
        
        // Update the display
        const companyLabourTotalElement = document.getElementById('company-labour-total');
        if (companyLabourTotalElement) {
            companyLabourTotalElement.textContent = total.toFixed(2);
        }
        
        return total;
    }
    
    // Calculate travel expenses total
    function calculateTravelExpensesTotal() {
        let total = 0;
        
        // Get all travel expenses
        const travelExpensesAmounts = document.querySelectorAll('input[id^="travel-amount-"]');
        
        // Sum up all travel expenses
        travelExpensesAmounts.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
        });
        
        // Update the display
        const travelExpensesTotalElement = document.getElementById('travel-expenses-total');
        if (travelExpensesTotalElement) {
            travelExpensesTotalElement.textContent = total.toFixed(2);
        }
        
        return total;
    }
    
    // Calculate beverages total
    function calculateBeveragesTotal() {
        let total = 0;
        
        // Get all beverage amounts
        const beverageAmounts = document.querySelectorAll('input[id^="beverage-amount-"]');
        
        // Sum up all beverage amounts
        beverageAmounts.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
        });
        
        // Update the display
        const beveragesTotalElement = document.getElementById('beverages-total');
        if (beveragesTotalElement) {
            beveragesTotalElement.textContent = total.toFixed(2);
        }
        
        return total;
    }
    
    // Calculate miscellaneous total (travel + beverages)
    function calculateMiscellaneousTotal() {
        const travelTotal = calculateTravelExpensesTotal();
        const beveragesTotal = calculateBeveragesTotal();
        const miscTotal = travelTotal + beveragesTotal;
        
        // Update the display
        const miscellaneousTotalElement = document.getElementById('miscellaneous-total');
        if (miscellaneousTotalElement) {
            miscellaneousTotalElement.textContent = miscTotal.toFixed(2);
        }
        
        return miscTotal;
    }
    
    // Calculate grand total
    function calculateGrandTotal() {
        const vendorLabourTotal = calculateVendorLabourTotal();
        const companyLabourTotal = calculateCompanyLabourTotal();
        const miscellaneousTotal = calculateMiscellaneousTotal();
        
        const grandTotal = vendorLabourTotal + companyLabourTotal + miscellaneousTotal;
        
        // Update the display
        const grandTotalElement = document.getElementById('grand-total');
        if (grandTotalElement) {
            grandTotalElement.textContent = grandTotal.toFixed(2);
        }
        
        return grandTotal;
    }
});

// Add this at the end of the script section, before the closing script tag
// Ensure the modal close button works properly
document.addEventListener('DOMContentLoaded', function() {
    // Get the close button and modal
    const closeBtn = document.querySelector('.modal-close');
    const modal = document.getElementById('update-form-modal');
    const cancelBtn = document.getElementById('cancel-update');
    
    console.log('Setting up modal close handlers', {closeBtn, modal, cancelBtn});
    
    // Define the hideUpdateModal function in the global scope if it doesn't exist
    if (typeof window.hideUpdateModal !== 'function') {
        window.hideUpdateModal = function() {
            console.log('Closing modal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
                
                // Reset form
                const form = document.getElementById('update-form');
                if (form) {
                    form.reset();
                }
            }
        };
    }
    
    // Attach event listeners
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            console.log('Close button clicked');
            window.hideUpdateModal();
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            console.log('Cancel button clicked');
            e.preventDefault();
            window.hideUpdateModal();
        });
    }
    
    // Close when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            window.hideUpdateModal();
        }
    });
});
</script> 

<!-- Add this style directly in the PHP file or link to an external CSS file -->
<style>
    /* Labor field styling */
    .attendance-wages-row, .ot-row {
        margin-bottom: 10px;
        padding: 8px;
        background-color: #f9f9f9;
        border-radius: 4px;
    }
    
    .attendance-wages-row {
        border-left: 3px solid #4CAF50; /* Green border for attendance row */
    }
    
    .ot-row {
        border-left: 3px solid #2196F3; /* Blue border for OT row */
    }
    
    .attendance-note {
        margin-bottom: 5px;
        color: #666;
        font-style: italic;
    }
    
    .attendance-note i {
        color: #4CAF50;
        margin-right: 3px;
    }
    
    /* Total Amount Styling - Minimalistic */
    .total-amount-container {
        margin: 15px 0;
    }
    
    .total-amount-wrapper {
        border-top: 1px solid #e0e0e0;
        padding-top: 10px;
    }
    
    .total-value {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .total-value label {
        font-weight: 500;
        font-size: 1rem;
        color: #333;
        margin: 0;
    }
    
    .grand-total-input {
        font-size: 1.3rem;
        font-weight: 600;
        color: #e53935;
        background-color: transparent;
        border: none;
        padding: 0;
        text-align: right;
        width: 50%;
        box-shadow: none;
        height: auto;
    }
    
    .grand-total-input:focus {
        outline: none;
        box-shadow: none;
    }
    
    /* Tooltip styles */
    label[title] {
        cursor: help;
        border-bottom: 1px dotted #666;
    }
    
    /* Responsive adjustments for smaller screens */
    @media (max-width: 768px) {
        .form-group.col-3, .form-group.col-4 {
            flex: 0 0 50%; /* Make two columns on smaller screens */
            max-width: 50%;
        }
        
        .attendance-wages-row, .ot-row {
            flex-wrap: wrap;
        }
    }
    
    /* Section styles */
    .company-labours-section,
    .travel-expenses-section,
    .beverages-section,
    .work-progress-section,
    .inventory-section {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
        width: 100%;
        display: block;
        box-sizing: border-box;
    }
    
    /* Item styles */
    .company-labour-item,
    .travel-expense-item,
    .beverage-item,
    .work-progress-item,
    .inventory-item {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 16px;
        border: 1px solid #e0e0e0;
        position: relative;
        transition: all 0.3s ease;
        width: 100%;
        display: block;
        box-sizing: border-box;
    }
    
    .company-labour-item:hover,
    .travel-expense-item:hover,
    .beverage-item:hover,
    .work-progress-item:hover,
    .inventory-item:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }
    
    /* Form styles */
    .travel-expense-form,
    .beverage-form,
    .work-progress-form,
    .inventory-form {
        display: grid;
        gap: 15px;
        width: 100%;
    }
    
    /* Bottom add button containers */
    .bottom-add-company-labour-container,
    .bottom-add-travel-expense-container,
    .bottom-add-beverage-container,
    .bottom-add-work-progress-container,
    .bottom-add-inventory-container {
        display: flex;
        justify-content: center;
        margin-top: 15px;
        padding-top: 10px;
    }
    
    /* Bill upload styling */
    .bill-upload-container {
        margin-top: 15px;
        margin-bottom: 15px;
    }
    
    .bill-preview-wrapper {
        margin-top: 15px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .bill-preview-image {
        max-width: 100%;
        max-height: 150px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .bill-indicator {
        margin-top: 8px;
        margin-bottom: 4px;
    }
    
    .bill-file-name {
        font-size: 0.8rem;
        color: #666;
        text-align: center;
    }
    
    /* Inventory specific icons */
    .fa-boxes, .fa-box, .fa-mountain, .fa-th-large,
    .fa-grip-lines, .fa-tree, .fa-fill-drip, .fa-border-all,
    .fa-glasses, .fa-plug, .fa-faucet, .fa-receipt {
        color: #ff9800; /* Orange color for inventory icons */
    }
    
    .fa-cubes, .fa-balance-scale, .fa-ruler-combined {
        color: #795548; /* Brown color for measurement icons */
    }
    
    .fa-sticky-note {
        color: #9c27b0; /* Purple for notes */
    }
    
    /* Icon colors */
    /* Transport mode icons */
    .fa-car, .fa-bus, .fa-road {
        color: #3498db;
    }
    
    .fa-map-marker-alt, .fa-map-marker {
        color: #e74c3c;
    }
    
    /* Beverage icons */
    .fa-coffee, .fa-mug-hot, .fa-wine-glass, .fa-glass-whiskey, .fa-tint {
        color: #8e44ad;
    }
    
    .fa-tag {
        color: #2ecc71;
    }
    
    /* Work progress specific styles */
    .work-progress-form textarea {
        resize: vertical;
        min-height: 60px;
    }
    
    .media-upload-section {
        margin-top: 15px;
        padding: 15px;
        background-color: #f5f5f5;
        border-radius: 8px;
        border: 1px dashed #ccc;
    }
    
    .file-upload {
        padding: 6px;
        border: 1px solid #ddd;
        background-color: #fff;
    }
    
    .media-preview {
        margin-top: 15px;
    }
    
    .preview-section {
        margin-bottom: 20px;
    }
    
    .preview-section h6 {
        margin-bottom: 10px;
        color: #555;
        font-weight: 600;
    }
    
    .preview-items {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .preview-item {
        width: 120px;
        text-align: center;
    }
    
    .media-thumbnail {
        width: 100%;
        height: 90px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .file-name {
        margin-top: 5px;
        font-size: 0.75rem;
        color: #666;
        word-break: break-word;
    }
    
    /* Work category icons */
    .fa-clipboard-list,
    .fa-tools,
    .fa-hard-hat,
    .fa-couch,
    .fa-building,
    .fa-paint-roller {
        color: #e67e22;
    }
    
    .fa-check-circle {
        color: #2ecc71;
    }
    
    .fa-comment {
        color: #7f8c8d;
    }
    
    .fa-images, 
    .fa-video {
        color: #3498db;
    }
    
    /* Work progress specific styles */
    .work-progress-form textarea {
        resize: vertical;
        min-height: 60px;
    }
    
    .media-upload-section {
        margin-top: 15px;
        padding: 15px;
        background-color: #f5f5f5;
        border-radius: 8px;
        border: 1px dashed #ccc;
    }
    
    .upload-section-title {
        margin-bottom: 15px;
        color: #555;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .media-items-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .media-upload-item {
        background-color: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        overflow: hidden;
    }
    
    .media-upload-header {
        background-color: #f0f0f0;
        padding: 8px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .media-item-title {
        font-weight: 500;
        color: #555;
    }
    
    .btn-remove-media {
        background: none;
        border: none;
        color: #e74c3c;
        cursor: pointer;
        padding: 2px 5px;
        font-size: 0.9rem;
    }
    
    .media-upload-body {
        padding: 15px;
    }
    
    .media-upload-input {
        position: relative;
    }
    
    .media-file-input {
        position: absolute;
        opacity: 0;
        width: 0.1px;
        height: 0.1px;
        overflow: hidden;
    }
    
    .media-upload-label {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        padding: 10px 15px;
        border: 2px dashed #3498db;
        border-radius: 4px;
        background-color: #f8f9fa;
        color: #3498db;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }
    
    .media-upload-label:hover {
        background-color: #e3f2fd;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .media-upload-label i {
        font-size: 1.2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 18px;
        position: relative;
        top: 1px;
    }
    
    .media-type-indicator {
        margin-top: 8px;
        display: flex;
        justify-content: center;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .badge-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .badge-primary {
        background-color: #cce5ff;
        color: #004085;
    }
    
    .media-preview-container {
        margin-top: 15px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .media-preview-image,
    .media-preview-video {
        max-width: 100%;
        max-height: 200px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .media-file-name {
        margin-top: 5px;
        font-size: 0.8rem;
        color: #666;
        text-align: center;
    }
    
    .add-more-media-container {
        margin-top: 15px;
        display: flex;
        justify-content: center;
    }
    
    .btn-add-more-media {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 15px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .btn-add-more-media:hover {
        background-color: #388E3C;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }

    /* Wages Summary Section Styles */
    .wages-summary-section {
        margin-top: 30px;
        padding: 20px;
        background-color: #f9f9f9;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    .summary-icon {
        font-size: 1.2rem;
        margin-left: 10px;
        color: #795548;
    }
    
    .summary-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-top: 15px;
    }
    
    .summary-group {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 15px;
        background-color: white;
    }
    
    .summary-label {
        display: flex;
        align-items: center;
        font-weight: 600;
        font-size: 1.1rem;
        color: #333;
        margin-bottom: 10px;
    }
    
    .summary-label i {
        margin-right: 10px;
        color: #555;
    }
    
    .summary-value {
        display: flex;
        justify-content: flex-end;
        font-size: 1.2rem;
        font-weight: 600;
        color: #333;
    }
    
    .currency {
        margin-right: 5px;
        color: #555;
    }
    
    .summary-subgroup {
        margin-left: 20px;
        margin-top: 10px;
        padding: 8px;
        border-left: 3px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .summary-sublabel {
        display: flex;
        align-items: center;
        font-weight: 500;
        color: #555;
    }
    
    .summary-sublabel i {
        margin-right: 8px;
    }
    
    .subtotal {
        margin-top: 15px;
        border-top: 1px dashed #e0e0e0;
        padding-top: 10px;
    }
    
    .grand-total {
        margin-top: 10px;
        background-color: #f5f5f5;
        border: 2px solid #4CAF50;
    }
    
    .grand-total .summary-label {
        color: #2E7D32;
    }
    
    .grand-total .summary-value {
        font-size: 1.5rem;
        color: #2E7D32;
    }
    
    .calculate-container {
        margin-top: 20px;
        display: flex;
        justify-content: center;
    }
    
    .calculate-btn {
        padding: 10px 20px;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
        background-color: #2196F3;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    
    .calculate-btn:hover {
        background-color: #1976D2;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .summary-subgroup {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .summary-value {
            margin-top: 5px;
        }
    }

    /* New overtime fields styling */
    .overtime-container {
        display: flex;
        align-items: center;
        width: 100%;
    }

    .overtime-input-group {
        display: flex;
        align-items: center;
        width: 100%;
    }

    .overtime-inputs {
        display: flex;
        flex: 1;
        gap: 10px;
    }

    .overtime-hours, .overtime-minutes {
        display: flex;
        align-items: center;
        background-color: #f9f9f9;
        border-radius: 4px;
        padding: 2px 5px;
    }

    .overtime-hours input, .overtime-minutes input {
        width: 70px;
        text-align: center;
        font-size: 1rem;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .overtime-hours span, .overtime-minutes span {
        margin-left: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        color: #555;
    }

    /* Add these additional styles for focus and hover states */
    .overtime-hours input:focus, .overtime-minutes input:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        outline: 0;
    }

    .overtime-hours input:hover, .overtime-minutes input:hover {
        border-color: #aaa;
    }
</style>

<!-- Add just before the end of the file -->
<script>
// Add this function to update the calculate laborer total function to handle hours and minutes
document.addEventListener('DOMContentLoaded', function() {
    // Override the original calculateLaborerTotal function to handle hours and minutes
    window.calculateLaborerTotal = function(vendorId, laborerId) {
        // Get all input fields
        const morningAttendanceSelect = document.getElementById(`laborer-morning-${vendorId}-${laborerId}`);
        const eveningAttendanceSelect = document.getElementById(`laborer-evening-${vendorId}-${laborerId}`);
        const wagesInput = document.getElementById(`laborer-wages-${vendorId}-${laborerId}`);
        const dayTotalInput = document.getElementById(`laborer-day-total-${vendorId}-${laborerId}`);
        const otHoursInput = document.getElementById(`laborer-ot-hours-${vendorId}-${laborerId}`);
        const otMinutesInput = document.getElementById(`laborer-ot-minutes-${vendorId}-${laborerId}`);
        const otRateInput = document.getElementById(`laborer-ot-rate-${vendorId}-${laborerId}`);
        const otAmountInput = document.getElementById(`laborer-ot-amount-${vendorId}-${laborerId}`);
        const totalInput = document.getElementById(`laborer-total-${vendorId}-${laborerId}`);
        
        // Get values from inputs
        const morningAttendance = morningAttendanceSelect.value;
        const eveningAttendance = eveningAttendanceSelect.value;
        const wages = parseFloat(wagesInput.value) || 0;
        const otHours = parseInt(otHoursInput.value) || 0;
        const otMinutes = parseInt(otMinutesInput.value) || 0;
        let otRate = parseFloat(otRateInput.value) || 0;
        
        // Auto-calculate OT rate if not provided or changed (1.5x hourly wage, assuming 8-hour day)
        if (!otRateInput.value || otRate === 0) {
            otRate = wages / 8 * 1.5;
            otRateInput.value = otRate.toFixed(2);
        }
        
        // Calculate morning attendance factor (0 = absent, 0.5 = present)
        let morningFactor = morningAttendance === 'P' ? 0.5 : 0;
        
        // Calculate evening attendance factor (0 = absent, 0.5 = present)
        let eveningFactor = eveningAttendance === 'P' ? 0.5 : 0;
        
        // Combined attendance factor (morning and afternoon each count for half a day)
        const combinedFactor = morningFactor + eveningFactor;
        
        // Calculate day wages based on attendance
        const dayTotal = wages * combinedFactor;
        
        // Calculate total overtime hours (convert minutes to decimal hours)
        const totalOTHours = otHours + (otMinutes / 60);
        
        // Calculate overtime amount
        const otAmount = totalOTHours * otRate;
        
        // Calculate final total
        const total = dayTotal + otAmount;
        
        // Update the fields
        dayTotalInput.value = dayTotal.toFixed(2);
        otAmountInput.value = otAmount.toFixed(2);
        totalInput.value = total.toFixed(2);
        
        console.log(`Calculated totals for laborer ${laborerId}:`, { 
            wages, 
            morningAttendance,
            morningFactor,
            eveningAttendance,
            eveningFactor,
            combinedFactor,
            dayTotal,
            otHours,
            otMinutes,
            totalOTHours,
            otRate, 
            otAmount, 
            total 
        });
        
        // Ensure values are stored in the form fields
        dayTotalInput.setAttribute('value', dayTotal.toFixed(2));
        otAmountInput.setAttribute('value', otAmount.toFixed(2));
        totalInput.setAttribute('value', total.toFixed(2));
    };
});

// Add this to the existing script - initialize placeholder text and manage field interaction
document.addEventListener('DOMContentLoaded', function() {
    // Improve overtime fields user experience
    const otHoursInputs = document.querySelectorAll('.ot-hours-input');
    const otMinutesInputs = document.querySelectorAll('.ot-minutes-input');
    
    // Set clear placeholder text
    otHoursInputs.forEach(input => {
        input.placeholder = 'Hours';
        
        // Ensure empty input shows as 0 for calculations
        input.addEventListener('blur', function() {
            if (this.value === '') {
                this.value = '0';
            }
        });
    });
    
    otMinutesInputs.forEach(input => {
        input.placeholder = 'Minutes';
        
        // Ensure empty input shows as 0 for calculations
        input.addEventListener('blur', function() {
            if (this.value === '') {
                this.value = '0';
            }
        });
        
        // Validate minutes (0-59)
        input.addEventListener('change', function() {
            const val = parseInt(this.value);
            if (val > 59) {
                this.value = '59';
            } else if (val < 0) {
                this.value = '0';
            }
        });
    });
});
</script>
