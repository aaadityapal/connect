<!-- Add Labour Modal - Minimalistic Design matching Vendor Modal -->
<div id="addLabourModal" class="labour-modal-hidden" data-modal="labour">
    <div class="labour-modal-backdrop">
        <div class="labour-modal-container">
            <div class="labour-modal-header">
                <h5 class="labour-modal-title">
                    <i class="bi bi-people-fill me-2"></i>
                    Add New Labour Worker
                </h5>
                <button type="button" class="labour-close-btn" onclick="LabourModal.hide()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="labour-modal-body">
                <form id="addLabourForm" enctype="multipart/form-data">
                    <div class="row g-4">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <div class="labour-section-header">
                                <i class="bi bi-person me-2"></i>
                                <span>Basic Information</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="labour-form-group">
                                <label for="labourFullName" class="labour-form-label">
                                    <i class="bi bi-person me-1"></i>
                                    Full Name <span class="labour-required">*</span>
                                </label>
                                <input type="text" class="labour-form-control" id="labourFullName" name="full_name" placeholder="Enter full name" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="labour-form-group">
                                <label for="labourPhone" class="labour-form-label">
                                    <i class="bi bi-telephone me-1"></i>
                                    Phone Number <span class="labour-required">*</span>
                                </label>
                                <input type="tel" class="labour-form-control" id="labourPhone" name="phone_number" placeholder="Enter phone number" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="labour-form-group">
                                <label for="labourEmail" class="labour-form-label">
                                    <i class="bi bi-envelope me-1"></i>
                                    Email Address
                                </label>
                                <input type="email" class="labour-form-control" id="labourEmail" name="email" placeholder="Enter email address">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="labour-form-group">
                                <label for="labourSkillType" class="labour-form-label">
                                    <i class="bi bi-tools me-1"></i>
                                    Skill Type
                                </label>
                                <select class="labour-form-control" id="labourSkillType" name="skill_type">
                                    <option value="unskilled">Unskilled</option>
                                    <option value="semi_skilled">Semi-Skilled</option>
                                    <option value="skilled">Skilled</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="helper">Helper</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Documents Section -->
                        <div class="col-12">
                            <div class="labour-section-header">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <span>Documents</span>
                                <button type="button" class="labour-section-toggle ms-auto" data-target="documentsContent" title="Toggle Documents">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12 labour-section-content collapsed" id="documentsContent">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="labour-form-group">
                                        <label for="aadharCard" class="labour-form-label">
                                            <i class="bi bi-file-earmark-text me-1"></i>
                                            Aadhar Card
                                        </label>
                                        <input type="file" class="labour-form-control" id="aadharCard" name="aadhar_card" accept=".jpg,.jpeg,.png,.pdf">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="labour-form-group">
                                        <label for="panCard" class="labour-form-label">
                                            <i class="bi bi-file-earmark-text me-1"></i>
                                            PAN Card
                                        </label>
                                        <input type="file" class="labour-form-control" id="panCard" name="pan_card" accept=".jpg,.jpeg,.png,.pdf">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="labour-form-group">
                                        <label for="otherDocument" class="labour-form-label">
                                            <i class="bi bi-file-earmark-text me-1"></i>
                                            Other Document
                                        </label>
                                        <input type="file" class="labour-form-control" id="otherDocument" name="other_document" accept=".jpg,.jpeg,.png,.pdf">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="labour-form-group">
                                <label for="labourNotes" class="labour-form-label">
                                    <i class="bi bi-chat-text me-1"></i>
                                    Notes
                                </label>
                                <textarea class="labour-form-control labour-textarea" id="labourNotes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="labour-modal-footer">
                <button type="button" class="labour-btn labour-btn-cancel" onclick="LabourModal.hide()">
                    Cancel
                </button>
                <button type="button" class="labour-btn labour-btn-save" id="saveLabourBtn">
                    Add Labour Worker
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Minimalistic Labour Modal Styles - Matching Vendor Modal */
.labour-modal-hidden {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
}

.labour-modal-visible {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

#addLabourModal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 999999 !important;
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}

.labour-modal-backdrop {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background-color: rgba(0, 0, 0, 0.5) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 20px !important;
    box-sizing: border-box !important;
}

.labour-modal-container {
    background: white !important;
    border: none !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
    width: 100% !important;
    max-width: 600px !important;
    max-height: 90vh !important;
    position: relative !important;
    animation: modalSlideIn 0.3s ease-out !important;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.labour-modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border-bottom: 1px solid #dee2e6 !important;
    padding: 1.5rem 2rem !important;
    border-radius: 12px 12px 0 0 !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}

.labour-modal-title {
    font-size: 1.1rem !important;
    font-weight: 500 !important;
    color: #495057 !important;
    margin: 0 !important;
    display: flex !important;
    align-items: center !important;
}

.labour-close-btn {
    background: #ffffff !important;
    border: 2px solid #dc3545 !important;
    color: #dc3545 !important;
    font-size: 1.8rem !important;
    font-weight: bold !important;
    opacity: 1 !important;
    transition: all 0.3s ease !important;
    border-radius: 6px !important;
    padding: 0.4rem 0.6rem !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2) !important;
    min-width: 36px !important;
    min-height: 36px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative !important;
    cursor: pointer !important;
}

.labour-close-btn span {
    font-size: 1.8rem !important;
    line-height: 1 !important;
    color: #dc3545 !important;
}

.labour-close-btn:hover {
    background: #dc3545 !important;
    color: #ffffff !important;
    border-color: #c82333 !important;
    transform: scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3) !important;
}

.labour-close-btn:hover span {
    color: #ffffff !important;
}

.labour-modal-body {
    padding: 2rem !important;
    background: #fdfdfd !important;
    max-height: 60vh !important;
    overflow-y: auto !important;
}

.labour-section-header {
    font-size: 0.9rem !important;
    font-weight: 500 !important;
    color: #6c757d !important;
    padding: 0.75rem 0 !important;
    border-bottom: 1px solid #f1f3f4 !important;
    margin-bottom: 1rem !important;
    display: flex !important;
    align-items: center !important;
    cursor: pointer !important;
}

.labour-section-header i {
    color: #495057 !important;
    font-size: 0.85rem !important;
}

.labour-section-toggle {
    background: transparent !important;
    border: none !important;
    color: #6c757d !important;
    padding: 0.25rem !important;
    border-radius: 4px !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    margin-left: auto !important;
}

.labour-section-toggle:hover {
    background-color: #e9ecef !important;
    color: #495057 !important;
    transform: scale(1.1) !important;
}

.labour-section-toggle i {
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: inline-block !important;
    font-size: 0.9rem !important;
}

.labour-section-toggle i.rotated {
    transform: rotate(180deg) !important;
}

.labour-section-content {
    transition: all 0.3s ease !important;
    overflow: hidden !important;
}

.labour-section-content.collapsed {
    display: none !important;
}

.labour-form-group {
    margin-bottom: 0 !important;
}

.labour-form-label {
    font-size: 0.85rem !important;
    font-weight: 500 !important;
    color: #495057 !important;
    margin-bottom: 0.5rem !important;
    display: block !important;
}

.labour-form-label i {
    color: #6c757d !important;
    font-size: 0.8rem !important;
}

.labour-required {
    color: #dc3545 !important;
    font-weight: 400 !important;
}

.labour-form-control {
    width: 100% !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.9rem !important;
    border: 1px solid #e9ecef !important;
    border-radius: 8px !important;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%) !important;
    color: #495057 !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
    font-family: inherit !important;
    outline: none !important;
}

.labour-form-control:focus {
    border-color: #a8d5f2 !important;
    box-shadow: 0 0 0 3px rgba(168, 213, 242, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08) !important;
    background: linear-gradient(145deg, #ffffff 0%, #f0f8ff 100%) !important;
    transform: translateY(-1px) !important;
}

.labour-form-control::placeholder {
    color: #adb5bd !important;
    font-size: 0.85rem !important;
    font-style: italic !important;
}

select.labour-form-control {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") !important;
    background-position: right 0.75rem center !important;
    background-repeat: no-repeat !important;
    background-size: 1rem !important;
    appearance: none !important;
    cursor: pointer !important;
}

.labour-textarea {
    resize: vertical !important;
    min-height: 80px !important;
}

.labour-modal-footer {
    background: #f8f9fa !important;
    border-top: 1px solid #dee2e6 !important;
    padding: 1.25rem 2rem !important;
    display: flex !important;
    justify-content: flex-end !important;
    gap: 0.75rem !important;
}

.labour-btn {
    padding: 0.75rem 1.5rem !important;
    font-size: 0.9rem !important;
    font-weight: 500 !important;
    border-radius: 8px !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    min-width: 100px !important;
}

.labour-btn-cancel {
    background: #ffffff !important;
    color: #6c757d !important;
    border: 1px solid #dee2e6 !important;
}

.labour-btn-save {
    background: linear-gradient(135deg, #495057 0%, #343a40 100%) !important;
    color: #ffffff !important;
}

.labour-btn-save:hover {
    background: linear-gradient(135deg, #343a40 0%, #212529 100%) !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(52, 58, 64, 0.3) !important;
}

/* Grid System */
.row { 
    display: flex !important; 
    flex-wrap: wrap !important; 
    margin: -8px !important; 
    width: calc(100% + 16px) !important;
}
.col-12 { 
    flex: 0 0 100% !important; 
    padding: 8px !important; 
    box-sizing: border-box !important;
}
.col-md-6 { 
    flex: 0 0 50% !important; 
    padding: 8px !important; 
    box-sizing: border-box !important;
}
.g-4 > * { 
    margin-bottom: 1.5rem !important; 
}

@media (max-width: 768px) {
    .col-md-6 { 
        flex: 0 0 100% !important; 
    }
    .labour-modal-header, .labour-modal-body, .labour-modal-footer { 
        padding-left: 1.5rem !important; 
        padding-right: 1.5rem !important; 
    }
}
</style>