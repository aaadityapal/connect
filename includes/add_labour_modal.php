<!-- Add Labour Modal -->
<div class="modal fade" id="addLabourModal" tabindex="-1" aria-labelledby="addLabourModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLabourModalLabel">
                    <i class="fas fa-users me-2"></i>
                    Add New Labour
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Loader -->
                <div class="loader-overlay" id="labourLoader" style="display: none;">
                    <div class="loader-content">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Processing your request...</p>
                    </div>
                </div>
                <form id="addLabourForm">
                    <!-- Hidden field for current user tracking -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <input type="hidden" name="created_by" value="<?php echo $_SESSION['user_id']; ?>">
                        <input type="hidden" name="updated_by" value="<?php echo $_SESSION['user_id']; ?>">
                    <?php endif; ?>
                    <!-- Personal Information Section -->
                    <div class="labour-section">
                        <h6 class="section-title mb-3">
                            <i class="fas fa-user me-2"></i>
                            Personal Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="labourFullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" class="form-control" id="labourFullName" name="fullName" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="labourPosition" class="form-label">Position/Role <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-hard-hat input-icon" id="labourPositionIcon"></i>
                                    <select class="form-select" id="labourPosition" name="position" required onchange="handleLabourPositionChange()">
                                        <option value="">Select Position</option>
                                        <option value="construction_worker">Construction Worker</option>
                                        <option value="mason">Mason</option>
                                        <option value="carpenter">Carpenter</option>
                                        <option value="electrician">Electrician</option>
                                        <option value="plumber">Plumber</option>
                                        <option value="painter">Painter</option>
                                        <option value="welder">Welder</option>
                                        <option value="foreman">Foreman</option>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="driver">Driver</option>
                                        <option value="security_guard">Security Guard</option>
                                        <option value="cleaner">Cleaner</option>
                                        <option value="other">Other</option>
                                        <option value="custom">Custom Position</option>
                                    </select>
                                    <input type="text" class="form-control" id="labourPositionCustom" name="positionCustom" 
                                           placeholder="Enter custom position" style="display: none;" required>
                                    <button type="button" class="custom-back-btn" id="labourPositionBackBtn" 
                                            onclick="backToPositionDropdown()" style="display: none;" title="Back to dropdown">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="labourPhone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" class="form-control" id="labourPhone" name="phoneNumber" placeholder="Enter phone number" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="labourAltNumber" class="form-label">Alternative Number</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-mobile-alt input-icon"></i>
                                    <input type="tel" class="form-control" id="labourAltNumber" name="alternativeNumber" placeholder="Enter alternative number">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="labourJoinDate" class="form-label">Join Date <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-calendar-plus input-icon"></i>
                                    <input type="date" class="form-control" id="labourJoinDate" name="joinDate" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="labourType" class="form-label">Labour Type <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-user-tag input-icon"></i>
                                    <select class="form-select" id="labourType" name="labourType" required>
                                        <option value="">Select Labour Type</option>
                                        <option value="permanent_labour">Permanent Labour</option>
                                        <option value="chowk_labour">Chowk Labour</option>
                                        <option value="vendor_labour">Vendor Labour</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="labourSalary" class="form-label">Daily Salary</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-dollar-sign input-icon"></i>
                                    <input type="number" class="form-control" id="labourSalary" name="salary" 
                                           placeholder="Enter daily salary" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Labour ID Proof Section -->
                    <div class="labour-section">
                        <div class="section-header" onclick="toggleLabourSection('idProof')">
                            <h6 class="section-title mb-0">
                                <i class="fas fa-id-card me-2"></i>
                                Labour ID Proof Documents
                            </h6>
                            <button type="button" class="section-toggle-btn collapsed" id="idProofToggleBtn">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="section-content collapsed" id="idProofContent">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="aadharCard" class="form-label">Aadhar Card</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="form-control file-input" id="aadharCard" name="aadharCard" 
                                               accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                                        <div class="file-upload-display">
                                            <i class="fas fa-cloud-upload-alt file-icon"></i>
                                            <span class="file-text">Choose Aadhar Card file</span>
                                        </div>
                                    </div>
                                    <small class="text-muted">Supported formats: PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="panCard" class="form-label">PAN Card</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="form-control file-input" id="panCard" name="panCard" 
                                               accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                                        <div class="file-upload-display">
                                            <i class="fas fa-cloud-upload-alt file-icon"></i>
                                            <span class="file-text">Choose PAN Card file</span>
                                        </div>
                                    </div>
                                    <small class="text-muted">Supported formats: PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="voterCard" class="form-label">Voter ID / Driving License</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="form-control file-input" id="voterCard" name="voterCard" 
                                               accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                                        <div class="file-upload-display">
                                            <i class="fas fa-cloud-upload-alt file-icon"></i>
                                            <span class="file-text">Choose ID document file</span>
                                        </div>
                                    </div>
                                    <small class="text-muted">Supported formats: PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="otherDocument" class="form-label">Other Document</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="form-control file-input" id="otherDocument" name="otherDocument" 
                                               accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                                        <div class="file-upload-display">
                                            <i class="fas fa-cloud-upload-alt file-icon"></i>
                                            <span class="file-text">Choose other document file</span>
                                        </div>
                                    </div>
                                    <small class="text-muted">Supported formats: PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="labour-section">
                        <div class="section-header" onclick="toggleLabourSection('contact')">
                            <h6 class="section-title mb-0">
                                <i class="fas fa-address-book me-2"></i>
                                Address Information
                            </h6>
                            <button type="button" class="section-toggle-btn collapsed" id="contactToggleBtn">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="section-content collapsed" id="contactContent">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="labourAddress" class="form-label">Address</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-home input-icon"></i>
                                        <input type="text" class="form-control" id="labourAddress" name="address" 
                                               placeholder="Enter full address">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="labourCity" class="form-label">City</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-city input-icon"></i>
                                        <input type="text" class="form-control" id="labourCity" name="city" 
                                               placeholder="Enter city">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="labourState" class="form-label">State/Province</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-map input-icon"></i>
                                        <input type="text" class="form-control" id="labourState" name="state" 
                                               placeholder="Enter state/province">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes Section -->
                    <div class="labour-section">
                        <h6 class="section-title mb-3">
                            <i class="fas fa-sticky-note me-2"></i>
                            Additional Notes
                        </h6>
                        <div class="mb-3">
                            <label for="labourNotes" class="form-label">Notes</label>
                            <div class="input-icon-wrapper textarea-wrapper">
                                <i class="fas fa-sticky-note input-icon textarea-icon"></i>
                                <textarea class="form-control" id="labourNotes" name="notes" rows="3" 
                                    placeholder="Enter any additional information..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="submitLabourForm()">
                    <i class="fas fa-save me-2"></i>
                    Save Labour
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Add Labour Modal Styles */
.modal-lg { max-width: 800px; }
.modal-content { border-radius: 16px; border: none; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); }
/* Increase modal z-index to ensure it appears above other elements */
#addLabourModal { z-index: 1060 !important; }
.modal-header { border-bottom: 1px solid #f1f3f4; padding: 1.5rem 2rem; border-radius: 16px 16px 0 0; background-color: #fafbfc; }
.modal-title { font-size: 1.25rem; font-weight: 600; color: #1f2937; }
.modal-body { padding: 2rem; background-color: #ffffff; }
.modal-footer { border-top: 1px solid #f1f3f4; padding: 1.5rem 2rem; border-radius: 0 0 16px 16px; background-color: #fafbfc; }
.labour-section { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f3f4; }
.labour-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.section-title { font-size: 0.95rem; font-weight: 600; color: #374151; display: flex; align-items: center; margin-bottom: 1rem; }
.section-title i { color: #6b7280; margin-right: 0.5rem; font-size: 1rem; }
.section-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0.75rem 1rem; margin: 0 -1rem 1rem; border-radius: 10px; transition: all 0.2s ease; }
.section-header:hover { background-color: #f9fafb; }
.section-toggle-btn { background: none; border: none; font-size: 1rem; color: #6b7280; cursor: pointer; transition: all 0.3s ease; padding: 0.5rem; border-radius: 6px; }
.section-toggle-btn:hover { background-color: #f3f4f6; color: #374151; }
.section-toggle-btn i { transition: transform 0.3s ease; }
.section-toggle-btn.collapsed i { transform: rotate(-90deg); }
.section-content { overflow: hidden; transition: all 0.3s ease; margin-top: 1rem; }
.section-content.collapsed { max-height: 0; margin-top: 0; margin-bottom: 0; }
.form-label { font-weight: 500; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem; }
.input-icon-wrapper { position: relative; display: flex; align-items: center; }
.input-icon { position: absolute; left: 1rem; color: #9ca3af; font-size: 0.9rem; z-index: 2; transition: color 0.2s ease; }
.textarea-icon { top: 1rem; position: absolute; }
.custom-back-btn { position: absolute; right: 0.75rem; background: none; border: none; color: #6b7280; font-size: 0.9rem; cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: all 0.2s ease; z-index: 3; display: flex; align-items: center; justify-content: center; }
.custom-back-btn:hover { background-color: #f3f4f6; color: #374151; }
.form-control, .form-select { border: 1px solid #e5e7eb; border-radius: 14px; padding: 0.875rem 1rem 0.875rem 2.75rem; transition: all 0.2s ease; font-size: 0.875rem; background-color: #fafbfc; box-shadow: none; }
.form-control:focus, .form-select:focus { border-color: #3b82f6; background-color: #ffffff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); outline: none; }
.form-control:focus + .input-icon, .form-select:focus + .input-icon { color: #3b82f6; }
.textarea-wrapper textarea { padding-left: 2.75rem; resize: vertical; min-height: 80px; }
.text-danger { color: #ef4444 !important; }
.btn { border-radius: 10px; padding: 0.75rem 1.5rem; font-weight: 500; font-size: 0.875rem; transition: all 0.2s ease; border: none; }
.btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
.btn-primary:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4); transform: translateY(-1px); }
.btn-secondary { background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
.btn-secondary:hover { background-color: #e5e7eb; color: #1f2937; border-color: #d1d5db; }
.form-select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 16px 12px; padding-right: 2.5rem; }
.is-invalid { border-color: #ef4444 !important; background-color: #fef2f2 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important; }
.is-invalid + .input-icon { color: #ef4444 !important; }

/* File Upload Styles */
.file-upload-wrapper { position: relative; cursor: pointer; }
.file-input { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2; }
.file-upload-display { border: 2px dashed #e5e7eb; border-radius: 14px; padding: 1.5rem; text-align: center; background-color: #fafbfc; transition: all 0.2s ease; min-height: 80px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.file-upload-display:hover { border-color: #3b82f6; background-color: #f8faff; }
.file-upload-wrapper:hover .file-upload-display { border-color: #3b82f6; background-color: #f8faff; }
.file-icon { font-size: 1.5rem; color: #9ca3af; margin-bottom: 0.5rem; transition: color 0.2s ease; }
.file-upload-display:hover .file-icon { color: #3b82f6; }
.file-text { font-size: 0.875rem; color: #6b7280; font-weight: 500; }
.file-upload-display:hover .file-text { color: #3b82f6; }
.file-upload-wrapper.has-file .file-upload-display { border-color: #10b981; background-color: #f0fdf4; }
.file-upload-wrapper.has-file .file-icon { color: #10b981; }
.file-upload-wrapper.has-file .file-text { color: #10b981; }

/* Loader Styles */
.loader-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    border-radius: 16px;
}

.loader-content {
    text-align: center;
    color: #374151;
}

.loader-content p {
    margin: 10px 0 0;
    font-weight: 500;
}
</style>

<script>
function handleLabourPositionChange() {
    const positionSelect = document.getElementById('labourPosition');
    const positionCustom = document.getElementById('labourPositionCustom');
    const positionIcon = document.getElementById('labourPositionIcon');
    const backBtn = document.getElementById('labourPositionBackBtn');
    
    if (positionSelect.value === 'custom') {
        positionSelect.style.display = 'none';
        positionCustom.style.display = 'block';
        positionCustom.style.paddingRight = '3rem';
        backBtn.style.display = 'flex';
        positionIcon.className = 'fas fa-edit input-icon';
        setTimeout(() => positionCustom.focus(), 100);
        positionSelect.removeAttribute('required');
        positionCustom.setAttribute('required', 'required');
    }
}

function backToPositionDropdown() {
    const positionSelect = document.getElementById('labourPosition');
    const positionCustom = document.getElementById('labourPositionCustom');
    const positionIcon = document.getElementById('labourPositionIcon');
    const backBtn = document.getElementById('labourPositionBackBtn');
    
    positionSelect.style.display = 'block';
    positionCustom.style.display = 'none';
    backBtn.style.display = 'none';
    positionIcon.className = 'fas fa-hard-hat input-icon';
    positionSelect.value = '';
    positionCustom.value = '';
    positionSelect.setAttribute('required', 'required');
    positionCustom.removeAttribute('required');
    positionCustom.classList.remove('is-invalid');
    positionSelect.classList.remove('is-invalid');
}

function toggleLabourSection(sectionType) {
    const content = document.getElementById(sectionType + 'Content');
    const toggleBtn = document.getElementById(sectionType + 'ToggleBtn');
    content.classList.toggle('collapsed');
    toggleBtn.classList.toggle('collapsed');
    localStorage.setItem('labour' + sectionType.charAt(0).toUpperCase() + sectionType.slice(1) + 'Collapsed', content.classList.contains('collapsed'));
}

function initializeLabourSections() {
    const sections = ['contact', 'idProof'];
    sections.forEach(section => {
        const isCollapsed = localStorage.getItem('labour' + section.charAt(0).toUpperCase() + section.slice(1) + 'Collapsed') === 'true';
        if (isCollapsed) {
            const content = document.getElementById(section + 'Content');
            const toggleBtn = document.getElementById(section + 'ToggleBtn');
            if (content && toggleBtn) {
                content.classList.add('collapsed');
                toggleBtn.classList.add('collapsed');
            }
        }
    });
}

function submitLabourForm() {
    // Show loader
    const loader = document.getElementById('labourLoader');
    if (loader) {
        loader.style.display = 'flex';
    }
    
    const form = document.getElementById('addLabourForm');
    const formData = new FormData(form);
    
    const positionSelect = document.getElementById('labourPosition');
    const positionCustom = document.getElementById('labourPositionCustom');
    
    let positionValue = '';
    if (positionCustom.style.display !== 'none' && positionCustom.value.trim()) {
        positionValue = positionCustom.value.trim();
        formData.set('position', positionValue);
    } else if (positionSelect.value) {
        positionValue = positionSelect.value;
    }
    
    const requiredFields = ['fullName', 'phoneNumber', 'joinDate', 'labourType'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    if (!positionValue) {
        if (positionCustom.style.display !== 'none') {
            positionCustom.classList.add('is-invalid');
        } else {
            positionSelect.classList.add('is-invalid');
        }
        isValid = false;
    } else {
        positionCustom.classList.remove('is-invalid');
        positionSelect.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        showNotification('warning', 'Please fill in all required fields.');
        return;
    }
    
    // Send the data to the server using AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/save_labour.php', true);
    xhr.onload = function() {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    // Show success message with animation
                    showNotification('success', 'Labour added successfully!');
                    
                    // Reset form and close modal
                    form.reset();
                    backToPositionDropdown();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addLabourModal'));
                    modal.hide();
                    
                    // Refresh labour list if available
                    if (typeof loadLabours === 'function') {
                        loadLabours();
                    }
                } else {
                    showNotification('error', 'Error: ' + response.message);
                }
            } catch (e) {
                showNotification('error', 'An error occurred while processing the response.');
                console.error(e);
            }
        } else {
            showNotification('error', 'Request failed. Please try again.');
        }
    };
    xhr.onerror = function() {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        showNotification('error', 'Request failed. Please check your connection.');
    };
    xhr.send(formData);
}

document.getElementById('addLabourModal').addEventListener('shown.bs.modal', function () {
    initializeLabourSections();
    backToPositionDropdown();
});

// File upload handling
function updateFileName(input) {
    const wrapper = input.closest('.file-upload-wrapper');
    const fileText = wrapper.querySelector('.file-text');
    const fileIcon = wrapper.querySelector('.file-icon');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (file.size > maxSize) {
            showNotification('warning', 'File size should not exceed 5MB');
            input.value = '';
            return;
        }
        
        wrapper.classList.add('has-file');
        fileText.textContent = file.name;
        fileIcon.className = 'fas fa-check-circle file-icon';
    } else {
        wrapper.classList.remove('has-file');
        fileText.textContent = fileText.getAttribute('data-original') || 'Choose file';
        fileIcon.className = 'fas fa-cloud-upload-alt file-icon';
    }
}

// Add notification system to the page
function createNotificationSystem() {
    // Create notification container if it doesn't exist
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }

    // Add notification styles
    const style = document.createElement('style');
    style.textContent = `
        .notification {
            padding: 15px 25px;
            margin-bottom: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
            display: flex;
            align-items: center;
            min-width: 300px;
            max-width: 500px;
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.4s ease-out;
            pointer-events: auto;
            position: relative;
            overflow: hidden;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .notification::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .notification::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: rgba(255, 255, 255, 0.6);
            transform-origin: left;
            animation: countdown linear forwards;
        }
        
        .notification.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .notification.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .notification.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .notification-icon {
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .notification-message {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: white;
            opacity: 0.7;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0;
            margin-left: 10px;
            transition: opacity 0.2s;
        }
        
        .notification-close:hover {
            opacity: 1;
        }
        
        @keyframes countdown {
            from {
                transform: scaleX(1);
            }
            to {
                transform: scaleX(0);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .notification.error.show {
            animation: shake 0.8s ease;
        }
    `;
    document.head.appendChild(style);
}

// Show notification function
function showNotification(type, message, duration = 5000) {
    // Create notification container if it doesn't exist
    if (!document.getElementById('notification-container')) {
        createNotificationSystem();
    }
    
    const container = document.getElementById('notification-container');
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Set icon based on notification type
    let icon = '';
    let title = '';
    
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle notification-icon"></i>';
            title = 'Success';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle notification-icon"></i>';
            title = 'Error';
            break;
        case 'info':
            icon = '<i class="fas fa-info-circle notification-icon"></i>';
            title = 'Information';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle notification-icon"></i>';
            title = 'Warning';
            break;
    }
    
    // Set notification content
    notification.innerHTML = `
        ${icon}
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add notification to container
    container.appendChild(notification);
    
    // Set animation duration for countdown
    notification.style.setProperty('--duration', `${duration}ms`);
    
    // Show notification with animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Handle close button click
    const closeButton = notification.querySelector('.notification-close');
    closeButton.addEventListener('click', () => {
        removeNotification(notification);
    });
    
    // Auto-remove notification after duration
    setTimeout(() => {
        removeNotification(notification);
    }, duration);
}

// Remove notification with animation
function removeNotification(notification) {
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(50px)';
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.parentElement.removeChild(notification);
        }
    }, 400);
}

// Initialize notification system
createNotificationSystem();
</script>