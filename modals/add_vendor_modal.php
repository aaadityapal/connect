<!-- Add Vendor Modal -->
<div class="modal" id="addVendorModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Vendor</h2>
            <button class="close-btn" id="closeVendorModal">&times;</button>
        </div>

        <form id="vendorForm">
            <!-- Basic Information Section -->
                <div class="form-group-with-icon">
                    <label for="vendorName">Full Name</label>
                    <i class="fas fa-user"></i>
                    <input type="text" id="vendorName" name="vendorName" placeholder="e.g., ABC Enterprises" required>
                </div>

            <div class="form-group-two-cols">
                <div class="form-group-with-icon">
                    <label for="vendorPhone">Phone Number</label>
                    <i class="fas fa-phone"></i>
                    <input type="tel" id="vendorPhone" name="vendorPhone" placeholder="9876543210" maxlength="10" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number" required>
                </div>
                <div class="form-group-with-icon">
                    <label for="vendorAltPhone">Alternative Number</label>
                    <i class="fas fa-phone"></i>
                    <input type="tel" id="vendorAltPhone" name="vendorAltPhone" placeholder="Optional - 9876543210" maxlength="10" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number">
                </div>
            </div>

                <div class="form-group-with-icon">
                    <label for="vendorEmail">Email Address</label>
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="vendorEmail" name="vendorEmail" placeholder="name@company.com" required>
                </div>

            <!-- Vendor Type Section -->
                <div class="form-group">
                <label for="vendorType">Vendor Type</label>
                <select id="vendorType" name="vendorType" required>
                    <option value="" disabled selected>Select Vendor Type</option>
                    
                    <!-- Labour Contractor Options -->
                    <optgroup label="Labour Contractor" id="labourContractorGroup">
                        <option value="labour_carpenter">Carpenter Labour Contractor</option>
                        <option value="labour_civil">Civil Labour Contractor</option>
                        <option value="labour_electrical">Electrical Labour Contractor</option>
                        <option value="labour_flooring">Flooring Labour Contractor</option>
                        <option value="labour_glass">Glass & Glazing Labour Contractor</option>
                        <option value="labour_granite">Granite & Marble Labour Contractor</option>
                        <option value="labour_hvac">HVAC Labour Contractor</option>
                        <option value="labour_landscaping">Landscaping Labour Contractor</option>
                        <option value="labour_painting">Painting Labour Contractor</option>
                        <option value="labour_plumbing">Plumbing Labour Contractor</option>
                        <option value="labour_pop">POP Labour Contractor</option>
                        <option value="labour_roofing">Roofing Labour Contractor</option>
                        <option value="labour_semi_skilled">Semi-Skilled Labour Contractor</option>
                        <option value="labour_skilled">Skilled Labour Contractor</option>
                        <option value="labour_specialized">Specialized Labour Contractor</option>
                        <option value="labour_tile">Tile Labour Contractor</option>
                        <option value="labour_unskilled">Unskilled Labour Contractor</option>
                        <option value="labour_upvc">UPVC Labour Contractor</option>
                        <option value="labour_custom">+ Custom Labour Contractor</option>
                    </optgroup>
                    
                    <!-- Material Contractor Options -->
                    <optgroup label="Material Contractor" id="materialContractorGroup">
                        <option value="material_bricks">Bricks Material Contractor</option>
                        <option value="material_cement">Cement Material Contractor</option>
                        <option value="material_concrete">Concrete Material Contractor</option>
                        <option value="material_doors">Doors Material Contractor</option>
                        <option value="material_dust">Dust Material Contractor</option>
                        <option value="material_electrical">Electrical Material Contractor</option>
                        <option value="material_fixtures">Fixtures Material Contractor</option>
                        <option value="material_general">General Material Contractor</option>
                        <option value="material_glass">Glass Material Contractor</option>
                        <option value="material_hardware">Hardware Material Contractor</option>
                        <option value="material_hvac">HVAC Material Contractor</option>
                        <option value="material_insulation">Insulation Material Contractor</option>
                        <option value="material_mechanical">Mechanical Material Contractor</option>
                        <option value="material_paints">Paints Material Contractor</option>
                        <option value="material_plumbing">Plumbing Material Contractor</option>
                        <option value="material_sand">Sand Material Contractor</option>
                        <option value="material_steel">Steel Material Contractor</option>
                        <option value="material_tiles">Tiles Material Contractor</option>
                        <option value="material_wood">Wood Material Contractor</option>
                        <option value="material_custom">+ Custom Material Contractor</option>
                    </optgroup>
                    
                    <!-- Material Supplier Options -->
                    <optgroup label="Material Supplier" id="materialSupplierGroup">
                        <option value="supplier_bricks">Bricks Material Supplier</option>
                        <option value="supplier_cement">Cement Material Supplier</option>
                        <option value="supplier_concrete">Concrete Material Supplier</option>
                        <option value="supplier_doors">Doors Material Supplier</option>
                        <option value="supplier_electrical">Electrical Material Supplier</option>
                        <option value="supplier_equipment">Equipment Material Supplier</option>
                        <option value="supplier_fixtures">Fixtures Material Supplier</option>
                        <option value="supplier_glass">Glass Material Supplier</option>
                        <option value="supplier_hardware">Hardware Material Supplier</option>
                        <option value="supplier_hvac">HVAC Material Supplier</option>
                        <option value="supplier_insulation">Insulation Material Supplier</option>
                        <option value="supplier_paints">Paints Material Supplier</option>
                        <option value="supplier_plumbing">Plumbing Material Supplier</option>
                        <option value="supplier_sand_aggregate">Sand Material Supplier</option>
                        <option value="supplier_sanitary">Sanitary Ware Material Supplier</option>
                        <option value="supplier_steel">Steel Material Supplier</option>
                        <option value="supplier_tiles">Tiles Material Supplier</option>
                        <option value="supplier_tools">Tools Material Supplier</option>
                        <option value="supplier_windows">Windows Material Supplier</option>
                        <option value="supplier_wood">Wood Material Supplier</option>
                        <option value="supplier_custom">+ Custom Material Supplier</option>
                    </optgroup>
                    
                    <!-- Custom Vendor Types (Dynamically Populated) -->
                    <optgroup label="Recently Added Custom Types" id="customVendorTypesGroup">
                    </optgroup>
                </select>
            </div>

            <!-- Custom Vendor Type Input -->
                <div class="custom-vendor-input" id="customVendorInput" style="display: none;">
                <div class="custom-input-header">
                    <label for="customVendorText">Enter Custom Vendor Type</label>
                    <button type="button" class="back-btn" id="backToSelectBtn">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
                <input type="text" id="customVendorText" placeholder="e.g., Electrical Contractor, Plumbing Specialist, etc.">
            </div>

            <!-- Banking Details Section -->
            <div class="section-divider"></div>
            <div class="section-header">
                <i class="fas fa-building"></i>
                <h3>Banking Details</h3>
                <button type="button" class="section-toggle-btn" id="bankingToggleBtn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>

            <div class="section-content collapsed" id="bankingContent">
                <div class="form-group-with-icon">
                    <label for="bankName">Bank Name</label>
                    <i class="fas fa-bank"></i>
                    <input type="text" id="bankName" name="bankName" placeholder="e.g., State Bank of India">
                </div>

                <div class="form-group-with-icon">
                    <label for="accountNumber">Account Number</label>
                    <i class="fas fa-credit-card"></i>
                    <input type="text" id="accountNumber" name="accountNumber" placeholder="123456789012">
                </div>

                <div class="form-group-two-cols">
                    <div class="form-group-with-icon">
                        <label for="ifscCode">IFSC Code</label>
                        <i class="fas fa-code"></i>
                        <input type="text" id="ifscCode" name="ifscCode" placeholder="e.g., ABNA0123456">
                    </div>
                    <div class="form-group">
                        <label for="accountType">Account Type</label>
                        <select id="accountType" name="accountType">
                            <option value="" disabled selected>Select Account Type</option>
                            <option value="savings">Savings</option>
                            <option value="current">Current</option>
                            <option value="business">Business</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="qrCode">QR Code Upload <span style="color: #a0aec0; font-size: 0.9em;">(Optional)</span></label>
                    <div class="file-upload-container">
                        <input type="file" id="qrCode" name="qrCode" accept="image/*" class="file-input">
                        <label for="qrCode" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Click to upload QR code</span>
                        </label>
                        <div class="file-name" id="qrFileName"></div>
                    </div>
                </div>
            </div>

            <!-- GST Details Section -->
            <div class="section-divider"></div>
            <div class="section-header">
                <i class="fas fa-receipt"></i>
                <h3>GST Details</h3>
                <button type="button" class="section-toggle-btn" id="gstToggleBtn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>

            <div class="section-content collapsed" id="gstContent">
                <div class="form-group-with-icon">
                    <label for="gstNumber">GST Number</label>
                    <i class="fas fa-barcode"></i>
                    <input type="text" id="gstNumber" name="gstNumber" placeholder="22AAAAA0000A1Z5">
                </div>

                <div class="form-group-two-cols">
                    <div class="form-group-with-icon">
                        <label for="state">State</label>
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="state" name="state" placeholder="e.g., Maharashtra">
                    </div>
                    <div class="form-group">
                        <label for="gstType">GST Type</label>
                        <select id="gstType" name="gstType">
                            <option value="" disabled selected>Select GST Type</option>
                            <option value="cgst">CGST</option>
                            <option value="sgst">SGST</option>
                            <option value="igst">IGST</option>
                            <option value="ugst">UGST</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Address Details Section -->
            <div class="section-divider"></div>
            <div class="section-header">
                <i class="fas fa-map-location-dot"></i>
                <h3>Address Details</h3>
                <button type="button" class="section-toggle-btn" id="addressToggleBtn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>

            <div class="section-content collapsed" id="addressContent">
                <div class="form-group-with-icon">
                    <label for="streetAddress">Street Address</label>
                    <i class="fas fa-road"></i>
                    <input type="text" id="streetAddress" name="streetAddress" placeholder="House / Street / Locality">
                </div>

                <div class="form-group-two-cols">
                    <div class="form-group-with-icon">
                        <label for="city">City</label>
                        <i class="fas fa-city"></i>
                        <input type="text" id="city" name="city" placeholder="e.g., Mumbai">
                    </div>
                    <div class="form-group-with-icon">
                        <label for="addressState">State</label>
                        <i class="fas fa-map"></i>
                        <input type="text" id="addressState" name="addressState" placeholder="e.g., Maharashtra">
                    </div>
                </div>

                <div class="form-group-with-icon">
                    <label for="zipCode">Zip Code</label>
                    <i class="fas fa-hashtag"></i>
                    <input type="text" id="zipCode" name="zipCode" placeholder="400001" maxlength="6" pattern="[0-9]{6}" title="Please enter a valid 6-digit zip code">
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" id="cancelVendorBtn">Cancel</button>
                <button type="submit" class="modal-btn submit">Add Vendor</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s ease;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background-color: white;
        padding: 40px;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 0;
        border-bottom: none;
    }

    .modal-header h2 {
        font-size: 1.4em;
        color: #2a4365;
        font-weight: 500;
        margin: 0;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 1.8em;
        color: #a0aec0;
        cursor: pointer;
        transition: color 0.2s ease;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close-btn:hover {
        color: #2a4365;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-size: 0.85em;
        color: #2a4365;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.95em;
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        box-sizing: border-box;
        background-color: #f9fafb;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #2a4365;
        box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
        background-color: white;
    }

    .form-group-with-icon {
        position: relative;
        margin-bottom: 20px;
    }

    .form-group-with-icon label {
        display: block;
        font-size: 0.85em;
        color: #2a4365;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group-with-icon i {
        position: absolute;
        left: 15px;
        top: 38px;
        color: #4a5568;
        font-size: 0.95em;
        pointer-events: none;
    }

    .form-group-with-icon input {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.95em;
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        box-sizing: border-box;
        background-color: #f9fafb;
    }

    .form-group-with-icon input:focus {
        outline: none;
        border-color: #2a4365;
        box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
        background-color: white;
    }

    /* Stronger placeholder color so it remains visible on light backgrounds */
    .form-group-with-icon input::placeholder,
    .form-group input::placeholder,
    .custom-vendor-input input::placeholder,
    input::placeholder,
    textarea::placeholder {
        color: #94a3b8; /* slate-400 */
        opacity: 1; /* ensure consistent rendering across browsers */
    }

    .form-group-two-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form-group-two-cols .form-group-with-icon {
        margin-bottom: 0;
    }

    .custom-vendor-input {
        margin-bottom: 20px;
    }

    .custom-input-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .custom-input-header label {
        font-size: 0.85em;
        color: #2a4365;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
    }

    .back-btn {
        background: none;
        border: none;
        color: #2a4365;
        cursor: pointer;
        font-size: 0.85em;
        font-weight: 600;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
    }

    .back-btn:hover {
        color: #1a365d;
    }

    .back-btn i {
        font-size: 0.9em;
    }

    .custom-vendor-input input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.95em;
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        box-sizing: border-box;
        background-color: #f9fafb;
    }

    .custom-vendor-input input:focus {
        outline: none;
        border-color: #2a4365;
        box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
        background-color: white;
    }

    .custom-vendor-input input::placeholder {
        color: #cbd5e0;
    }

    /* Section Styling */
    .section-divider {
        height: 1px;
        background-color: #e2e8f0;
        margin: 30px 0 25px 0;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .section-header i {
        color: #2a4365;
        font-size: 1.1em;
    }

    .section-header h3 {
        font-size: 1em;
        color: #2a4365;
        font-weight: 600;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        flex: 1;
    }

    .section-toggle-btn {
        background: none;
        border: none;
        color: #2a4365;
        cursor: pointer;
        font-size: 1em;
        transition: transform 0.3s ease;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .section-toggle-btn:hover {
        color: #1a365d;
    }

    .section-toggle-btn.active i {
        transform: rotate(180deg);
    }

    .section-content {
        max-height: 1000px;
        overflow: hidden;
        transition: all 0.3s ease;
        margin-bottom: 0;
    }

    .section-content.collapsed {
        max-height: 0;
        opacity: 0;
        margin-bottom: 0;
    }

    /* File Upload Styling */
    .file-upload-container {
        position: relative;
        border: 2px dashed #e2e8f0;
        border-radius: 8px;
        padding: 30px 20px;
        text-align: center;
        transition: all 0.2s ease;
    }

    .file-upload-container:hover {
        border-color: #2a4365;
        background-color: #f9fafb;
    }

    .file-input {
        display: none;
    }

    .file-upload-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        color: #718096;
    }

    .file-upload-label i {
        font-size: 1.8em;
        color: #2a4365;
    }

    .file-upload-label span {
        font-size: 0.9em;
        font-weight: 500;
    }

    .file-name {
        margin-top: 12px;
        font-size: 0.85em;
        color: #4a5568;
        display: none;
    }

    .file-name.active {
        display: block;
    }

    .file-name i {
        margin-right: 6px;
        color: #38a169;
    }

    .vendor-type-section {
        margin-bottom: 30px;
    }

    .vendor-type-title {
        font-size: 0.85em;
        color: #2a4365;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .vendor-type-options {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .vendor-type-option {
        display: flex;
        align-items: center;
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .vendor-type-option:hover {
        background-color: #f7fafc;
        border-color: #2a4365;
    }

    .vendor-type-option input[type="radio"] {
        margin-right: 12px;
        cursor: pointer;
        width: 18px;
        height: 18px;
    }

    .vendor-type-option label {
        margin: 0;
        font-size: 0.95em;
        font-weight: 500;
        cursor: pointer;
        text-transform: none;
        letter-spacing: normal;
        text-transform: capitalize;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .modal-btn {
        flex: 1;
        padding: 12px 20px;
        border: none;
        border-radius: 6px;
        font-size: 0.95em;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .modal-btn.submit {
        background: #2a4365;
        color: white;
    }

    .modal-btn.submit:hover {
        background: #1a365d;
        box-shadow: 0 4px 12px rgba(42, 67, 101, 0.2);
    }

    .modal-btn.cancel {
        background: #f0f4f8;
        color: #2a4365;
        border: 1px solid #e2e8f0;
    }

    .modal-btn.cancel:hover {
        background: #e2e8f0;
        border-color: #cbd5e0;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const addVendorModal = document.getElementById('addVendorModal');
        const closeVendorModal = document.getElementById('closeVendorModal');
        const cancelVendorBtn = document.getElementById('cancelVendorBtn');
        const vendorForm = document.getElementById('vendorForm');

        // Load custom vendor types on page load
        loadCustomVendorTypes();

        if (closeVendorModal) {
            closeVendorModal.addEventListener('click', function() {
                addVendorModal.classList.remove('active');
            });
        }

        if (cancelVendorBtn) {
            cancelVendorBtn.addEventListener('click', function() {
                addVendorModal.classList.remove('active');
            });
        }

        // Close modal when clicking outside of modal content
        if (addVendorModal) {
            addVendorModal.addEventListener('click', function(e) {
                if (e.target === addVendorModal) {
                    addVendorModal.classList.remove('active');
                }
            });
        }

        // Load custom vendor types from database
        function loadCustomVendorTypes() {
            fetch('handlers/get_custom_vendor_types.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Object.keys(data.data).length > 0) {
                        // Clear previous custom options
                        const labourGroup = document.getElementById('labourContractorGroup');
                        const materialGroup = document.getElementById('materialContractorGroup');
                        const supplierGroup = document.getElementById('materialSupplierGroup');
                        
                        // Remove existing custom options
                        labourGroup.querySelectorAll('option[data-custom="true"]').forEach(opt => opt.remove());
                        materialGroup.querySelectorAll('option[data-custom="true"]').forEach(opt => opt.remove());
                        supplierGroup.querySelectorAll('option[data-custom="true"]').forEach(opt => opt.remove());
                        
                        // Add custom vendor types to their respective groups
                        for (const [category, vendors] of Object.entries(data.data)) {
                            let targetGroup = null;
                            
                            if (category.toLowerCase().includes('labour')) {
                                targetGroup = labourGroup;
                            } else if (category.toLowerCase().includes('material contractor')) {
                                targetGroup = materialGroup;
                            } else if (category.toLowerCase().includes('material supplier')) {
                                targetGroup = supplierGroup;
                            }
                            
                            if (targetGroup) {
                                // Sort vendors alphabetically
                                const sortedVendors = vendors.sort((a, b) => a.localeCompare(b));
                                
                                sortedVendors.forEach(vendor => {
                                    const newOption = document.createElement('option');
                                    newOption.value = vendor;
                                    newOption.textContent = vendor;
                                    newOption.setAttribute('data-custom', 'true');
                                    
                                    // Find the correct position to insert alphabetically
                                    let inserted = false;
                                    const options = Array.from(targetGroup.querySelectorAll('option'));
                                    
                                    for (let i = 0; i < options.length; i++) {
                                        const currentOptionText = options[i].textContent.toLowerCase();
                                        const newOptionText = vendor.toLowerCase();
                                        
                                        if (newOptionText < currentOptionText) {
                                            targetGroup.insertBefore(newOption, options[i]);
                                            inserted = true;
                                            break;
                                        }
                                    }
                                    
                                    // If not inserted yet, append at the end
                                    if (!inserted) {
                                        targetGroup.appendChild(newOption);
                                    }
                                });
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading custom vendor types:', error);
                });
        }

        // Handle form submission
        if (vendorForm) {
            vendorForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get basic information
                const vendorName = document.getElementById('vendorName').value.trim();
                const vendorPhone = document.getElementById('vendorPhone').value.trim();
                const vendorAltPhone = document.getElementById('vendorAltPhone').value.trim();
                const vendorEmail = document.getElementById('vendorEmail').value.trim();
                let vendorType = document.getElementById('vendorType').value;
                let customVendorType = '';
                let vendorCategory = '';
                let isCustom = 0; // Track if custom vendor type

                // Validate basic required fields
                if (!vendorName) {
                    alert('Please enter vendor name');
                    return;
                }
                if (!vendorPhone || !/^[0-9]{10}$/.test(vendorPhone)) {
                    alert('Please enter a valid 10-digit phone number');
                    return;
                }
                if (!vendorEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(vendorEmail)) {
                    alert('Please enter a valid email address');
                    return;
                }
                if (!vendorType) {
                    alert('Please select a vendor type');
                    return;
                }

                // Check if custom vendor type is selected
                if (vendorType.endsWith('_custom')) {
                    customVendorType = document.getElementById('customVendorText').value.trim();
                    if (!customVendorType) {
                        alert('Please enter a custom vendor type');
                        document.getElementById('customVendorText').focus();
                        return;
                    }
                    // Determine vendor category based on the selected custom option
                    if (vendorType.startsWith('labour_')) {
                        vendorCategory = 'Labour Contractor';
                    } else if (vendorType.startsWith('material_') && vendorType !== 'material_custom') {
                        vendorCategory = 'Material Contractor';
                    } else if (vendorType === 'material_custom') {
                        vendorCategory = 'Material Supplier';
                    } else if (vendorType.startsWith('supplier_')) {
                        vendorCategory = 'Material Supplier';
                    }
                    vendorType = customVendorType;
                    isCustom = 1; // Mark as custom vendor type
                }

                // Validate postal code if provided
                const zipCode = document.getElementById('zipCode').value.trim();
                if (zipCode && !/^[0-9]{6}$/.test(zipCode)) {
                    alert('Postal code must be 6 digits');
                    return;
                }

                // Create FormData object to handle file upload
                const formData = new FormData();
                
                // Basic Information
                formData.append('vendorName', vendorName);
                formData.append('vendorPhone', vendorPhone);
                formData.append('vendorAltPhone', vendorAltPhone);
                formData.append('vendorEmail', vendorEmail);
                formData.append('vendorType', vendorType);
                formData.append('isCustom', isCustom); // Add custom vendor flag
                if (vendorCategory) {
                    formData.append('vendorCategory', vendorCategory);
                }
                
                // Banking Details
                formData.append('bankName', document.getElementById('bankName').value.trim());
                formData.append('accountNumber', document.getElementById('accountNumber').value.trim());
                formData.append('ifscCode', document.getElementById('ifscCode').value.trim());
                formData.append('accountType', document.getElementById('accountType').value);
                
                // GST Details
                formData.append('gstNumber', document.getElementById('gstNumber').value.trim());
                formData.append('state', document.getElementById('state').value.trim());
                formData.append('gstType', document.getElementById('gstType').value);
                
                // Address Details
                formData.append('streetAddress', document.getElementById('streetAddress').value.trim());
                formData.append('city', document.getElementById('city').value.trim());
                formData.append('addressState', document.getElementById('addressState').value.trim());
                formData.append('zipCode', zipCode);
                
                // File Upload
                const qrCodeFile = document.getElementById('qrCode').files[0];
                if (qrCodeFile) {
                    formData.append('qrCode', qrCodeFile);
                }

                // Show loading state
                const submitBtn = vendorForm.querySelector('.modal-btn.submit');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Adding...';

                // Send data via AJAX
                fetch('handlers/add_vendor_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Vendor added successfully!\nVendor Code: ' + data.vendor_unique_code + '\nName: ' + data.vendor_name);
                        vendorForm.reset();
                        document.getElementById('customVendorInput').style.display = 'none';
                        document.getElementById('vendorType').style.display = 'block';
                        document.getElementById('qrFileName').innerHTML = '';
                        document.getElementById('qrFileName').classList.remove('active');
                        addVendorModal.classList.remove('active');
                        
                        // Refresh recipient dropdowns in payment entry modal
                        if (window.refreshEntryRecipients) {
                            window.refreshEntryRecipients();
                        }
                        
                        // Refresh custom vendor types after successful addition
                        loadCustomVendorTypes();
                    } else {
                        alert('Error: ' + (data.message || 'An error occurred'));
                        console.error('Server response:', data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while adding the vendor. Please check the console for details.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            });
        }

        // Handle vendor type change
        const vendorTypeSelect = document.getElementById('vendorType');
        const customVendorInput = document.getElementById('customVendorInput');
        const backToSelectBtn = document.getElementById('backToSelectBtn');

        if (vendorTypeSelect) {
            vendorTypeSelect.addEventListener('change', function() {
                if (this.value.endsWith('_custom')) {
                    vendorTypeSelect.style.display = 'none';
                    customVendorInput.style.display = 'block';
                    document.getElementById('customVendorText').focus();
                } else {
                    customVendorInput.style.display = 'none';
                    vendorTypeSelect.style.display = 'block';
                }
            });
        }

        if (backToSelectBtn) {
            backToSelectBtn.addEventListener('click', function(e) {
                e.preventDefault();
                customVendorInput.style.display = 'none';
                vendorTypeSelect.style.display = 'block';
                vendorTypeSelect.value = '';
                document.getElementById('customVendorText').value = '';
                vendorTypeSelect.focus();
            });
        }

        // Handle QR Code file upload
        const qrCodeInput = document.getElementById('qrCode');
        const qrFileName = document.getElementById('qrFileName');

        if (qrCodeInput) {
            qrCodeInput.addEventListener('change', function(e) {
                if (this.files && this.files.length > 0) {
                    const fileName = this.files[0].name;
                    qrFileName.innerHTML = '<i class="fas fa-check-circle"></i>' + fileName;
                    qrFileName.classList.add('active');
                } else {
                    qrFileName.innerHTML = '';
                    qrFileName.classList.remove('active');
                }
            });
        }

        // Handle section toggle buttons
        const bankingToggleBtn = document.getElementById('bankingToggleBtn');
        const gstToggleBtn = document.getElementById('gstToggleBtn');
        const addressToggleBtn = document.getElementById('addressToggleBtn');
        const bankingContent = document.getElementById('bankingContent');
        const gstContent = document.getElementById('gstContent');
        const addressContent = document.getElementById('addressContent');

        if (bankingToggleBtn) {
            bankingToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                bankingContent.classList.toggle('collapsed');
                bankingToggleBtn.classList.toggle('active');
            });
        }

        if (gstToggleBtn) {
            gstToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                gstContent.classList.toggle('collapsed');
                gstToggleBtn.classList.toggle('active');
            });
        }

        if (addressToggleBtn) {
            addressToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addressContent.classList.toggle('collapsed');
                addressToggleBtn.classList.toggle('active');
            });
        }
    });
</script>
