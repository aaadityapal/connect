<!-- Edit Labour Modal -->
<div class="modal fade" id="editLabourModal" tabindex="-1" aria-labelledby="editLabourModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content edit-labour-modal">
            <div class="modal-header edit-labour-modal-header">
                <h5 class="modal-title edit-labour-modal-title" id="editLabourModalLabel">
                    <i class="fas fa-edit me-2"></i>
                    Edit Labour Details
                </h5>
                <button type="button" class="btn-close edit-labour-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body edit-labour-modal-body">
                <!-- Loading Indicator -->
                <div class="text-center py-4" id="editLabourLoader" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading labour details...</p>
                </div>
                
                <!-- Success Message -->
                <div class="alert alert-success" id="editLabourSuccess" style="display: none;">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="editLabourSuccessMessage">Labour updated successfully!</span>
                </div>
                
                <!-- Error Message -->
                <div class="alert alert-danger" id="editLabourError" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="editLabourErrorMessage">Failed to update labour</span>
                </div>
                
                <!-- Edit Form -->
                <form id="editLabourForm" enctype="multipart/form-data" style="display: none;">
                    <input type="hidden" id="editLabourId" name="labour_id">
                    
                    <!-- Personal Information Section -->
                    <div class="labour-edit-section">
                        <div class="labour-edit-header">
                            <i class="fas fa-user me-2"></i>
                            <span>Personal Information</span>
                        </div>
                        <div class="labour-edit-grid">
                            <div class="labour-edit-item">
                                <label for="editLabourFullName">Full Name <span class="required">*</span></label>
                                <input type="text" class="labour-edit-control" id="editLabourFullName" name="full_name" required>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourPosition">Position <span class="required">*</span></label>
                                <select class="labour-edit-control" id="editLabourPosition" name="position" required onchange="handleEditPositionChange()">
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
                                <input type="text" class="labour-edit-control" id="editLabourPositionCustom" name="position_custom" 
                                       placeholder="Enter custom position" style="display: none; margin-top: 0.5rem;">
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourType">Labour Type <span class="required">*</span></label>
                                <select class="labour-edit-control" id="editLabourType" name="labour_type" required>
                                    <option value="">Select Labour Type</option>
                                    <option value="permanent_labour">Permanent Labour</option>
                                    <option value="chowk_labour">Chowk Labour</option>
                                    <option value="vendor_labour">Vendor Labour</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourPhone">Phone Number <span class="required">*</span></label>
                                <input type="tel" class="labour-edit-control" id="editLabourPhone" name="phone_number" required>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourAltPhone">Alternative Number</label>
                                <input type="tel" class="labour-edit-control" id="editLabourAltPhone" name="alternative_number">
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourJoinDate">Join Date <span class="required">*</span></label>
                                <input type="date" class="labour-edit-control" id="editLabourJoinDate" name="join_date" required>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourSalary">Daily Salary</label>
                                <input type="number" class="labour-edit-control" id="editLabourSalary" name="daily_salary" 
                                       placeholder="Enter daily salary" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div class="labour-edit-section">
                        <div class="labour-edit-header">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span>Address Information</span>
                        </div>
                        <div class="labour-edit-grid">
                            <div class="labour-edit-item full-width">
                                <label for="editLabourAddress">Address</label>
                                <textarea class="labour-edit-control" id="editLabourAddress" name="address" rows="2"></textarea>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourCity">City</label>
                                <input type="text" class="labour-edit-control" id="editLabourCity" name="city">
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourState">State</label>
                                <input type="text" class="labour-edit-control" id="editLabourState" name="state">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Information Section -->
                    <div class="labour-edit-section">
                        <div class="labour-edit-header">
                            <i class="fas fa-id-card me-2"></i>
                            <span>Document Information</span>
                        </div>
                        <div class="labour-edit-grid">
                            <div class="labour-edit-item">
                                <label for="editLabourAadhar">Aadhar Card Number</label>
                                <input type="text" class="labour-edit-control" id="editLabourAadhar" name="aadhar_card">
                                <div class="document-upload-section">
                                    <label for="editAadharFile" class="file-upload-label">
                                        <i class="fas fa-upload me-2"></i>Upload Aadhar Card Image
                                    </label>
                                    <input type="file" class="labour-file-input" id="editAadharFile" name="aadhar_file" accept="image/*,.pdf">
                                    <div class="current-file-info" id="editAadharCurrentFile" style="display: none;">
                                        <small class="text-muted">Current file: <span id="editAadharFileName"></span></small>
                                    </div>
                                </div>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourPan">PAN Card Number</label>
                                <input type="text" class="labour-edit-control" id="editLabourPan" name="pan_card">
                                <div class="document-upload-section">
                                    <label for="editPanFile" class="file-upload-label">
                                        <i class="fas fa-upload me-2"></i>Upload PAN Card Image
                                    </label>
                                    <input type="file" class="labour-file-input" id="editPanFile" name="pan_file" accept="image/*,.pdf">
                                    <div class="current-file-info" id="editPanCurrentFile" style="display: none;">
                                        <small class="text-muted">Current file: <span id="editPanFileName"></span></small>
                                    </div>
                                </div>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourVoter">Voter ID Number</label>
                                <input type="text" class="labour-edit-control" id="editLabourVoter" name="voter_id">
                                <div class="document-upload-section">
                                    <label for="editVoterFile" class="file-upload-label">
                                        <i class="fas fa-upload me-2"></i>Upload Voter ID Image
                                    </label>
                                    <input type="file" class="labour-file-input" id="editVoterFile" name="voter_file" accept="image/*,.pdf">
                                    <div class="current-file-info" id="editVoterCurrentFile" style="display: none;">
                                        <small class="text-muted">Current file: <span id="editVoterFileName"></span></small>
                                    </div>
                                </div>
                            </div>
                            <div class="labour-edit-item">
                                <label for="editLabourOther">Other Document</label>
                                <input type="text" class="labour-edit-control" id="editLabourOther" name="other_document">
                                <div class="document-upload-section">
                                    <label for="editOtherFile" class="file-upload-label">
                                        <i class="fas fa-upload me-2"></i>Upload Other Document Image
                                    </label>
                                    <input type="file" class="labour-file-input" id="editOtherFile" name="other_file" accept="image/*,.pdf">
                                    <div class="current-file-info" id="editOtherCurrentFile" style="display: none;">
                                        <small class="text-muted">Current file: <span id="editOtherFileName"></span></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3" style="font-size: 0.85rem;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Document number fields are optional. You can either enter document numbers or upload document images (or both).
                            If you upload a new image, it will replace any existing document image.
                        </div>
                    </div>
                    
                    <!-- Additional Information Section -->
                    <div class="labour-edit-section">
                        <div class="labour-edit-header">
                            <i class="fas fa-sticky-note me-2"></i>
                            <span>Additional Information</span>
                        </div>
                        <div class="labour-edit-grid">
                            <div class="labour-edit-item full-width">
                                <label for="editLabourNotes">Notes</label>
                                <textarea class="labour-edit-control" id="editLabourNotes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer edit-labour-modal-footer">
                <button type="button" class="labour-btn labour-btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </button>
                <button type="button" class="labour-btn labour-btn-success" id="saveLabourChanges" style="display: none;">
                    <i class="fas fa-save me-2"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Edit Labour Modal Styles */
.edit-labour-modal {
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    overflow: hidden;
}

.edit-labour-modal-header {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-bottom: 1px solid #dee2e6;
    padding: 1.5rem 2rem;
    border-radius: 12px 12px 0 0;
}

.edit-labour-modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #16a34a;
    margin: 0;
    display: flex;
    align-items: center;
}

.edit-labour-close-btn {
    background: #ffffff !important;
    border: 2px solid #6c757d !important;
    color: #6c757d !important;
    font-size: 1.8rem !important;
    font-weight: bold !important;
    opacity: 1 !important;
    transition: all 0.3s ease !important;
    border-radius: 6px !important;
    padding: 0.4rem 0.6rem !important;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.2) !important;
    min-width: 36px !important;
    min-height: 36px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.edit-labour-close-btn:hover {
    background: #6c757d !important;
    color: #ffffff !important;
    border-color: #5a6268 !important;
    transform: scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3) !important;
}

.edit-labour-modal-body {
    padding: 2rem;
    background: #fdfdfd;
    max-height: 70vh;
    overflow-y: auto;
}

.labour-edit-section {
    margin-bottom: 2rem;
    background: #ffffff;
    border: 1px solid #f1f3f4;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.labour-edit-section:last-child {
    margin-bottom: 0;
}

.labour-edit-header {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f8f9fa;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.labour-edit-header i {
    color: #16a34a;
    font-size: 0.9rem;
}

.labour-edit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
}

.labour-edit-item {
    display: flex;
    flex-direction: column;
}

.labour-edit-item.full-width {
    grid-column: 1 / -1;
}

.labour-edit-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.required {
    color: #dc3545;
    font-weight: 400;
}

.labour-edit-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #ffffff;
    color: #495057;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.labour-edit-control:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08);
    background: #fafafa;
    transform: translateY(-1px);
}

.labour-edit-control:hover:not(:focus) {
    border-color: #bbb;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

select.labour-edit-control {
    cursor: pointer;
}

textarea.labour-edit-control {
    resize: vertical;
    min-height: 60px;
}

/* File Upload Styles */
.document-upload-section {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border: 1px dashed #dee2e6;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.document-upload-section:hover {
    border-color: #16a34a;
    background: #f0fdf4;
}

.file-upload-label {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    text-align: center;
    min-width: 180px;
}

.file-upload-label:hover {
    background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}

.labour-file-input {
    display: none;
}

.current-file-info {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
}

.file-preview {
    margin-top: 0.5rem;
    max-width: 100px;
    max-height: 100px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.edit-labour-modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 1.25rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.labour-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.labour-btn-secondary {
    background-color: #6c757d;
    color: #ffffff;
}

.labour-btn-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.labour-btn-success {
    background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
    color: #ffffff;
    border: 1px solid transparent;
}

.labour-btn-success:hover {
    background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}

.labour-btn-success:disabled {
    background: #adb5bd;
    color: #ffffff;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .edit-labour-modal-body {
        padding: 1.5rem;
        max-height: 60vh;
    }
    
    .labour-edit-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .labour-edit-section {
        padding: 1rem;
    }
    
    .edit-labour-modal-footer {
        padding: 1rem 1.5rem;
        flex-direction: column;
    }
    
    .labour-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Scrollbar Styling */
.edit-labour-modal-body::-webkit-scrollbar {
    width: 6px;
}

.edit-labour-modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.edit-labour-modal-body::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.edit-labour-modal-body::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>

<script>
function handleEditPositionChange() {
    const positionSelect = document.getElementById('editLabourPosition');
    const positionCustom = document.getElementById('editLabourPositionCustom');
    
    if (positionSelect.value === 'custom') {
        positionCustom.style.display = 'block';
        positionCustom.focus();
        positionSelect.removeAttribute('required');
        positionCustom.setAttribute('required', 'required');
    } else {
        positionCustom.style.display = 'none';
        positionCustom.value = '';
        positionSelect.setAttribute('required', 'required');
        positionCustom.removeAttribute('required');
    }
}

// Handle file input changes
document.addEventListener('DOMContentLoaded', function() {
    // File input change handlers
    const fileInputs = [
        { input: 'editAadharFile', label: 'editAadharLabel', current: 'editAadharCurrentFile', name: 'editAadharFileName' },
        { input: 'editPanFile', label: 'editPanLabel', current: 'editPanCurrentFile', name: 'editPanFileName' },
        { input: 'editVoterFile', label: 'editVoterLabel', current: 'editVoterCurrentFile', name: 'editVoterFileName' },
        { input: 'editOtherFile', label: 'editOtherLabel', current: 'editOtherCurrentFile', name: 'editOtherFileName' }
    ];
    
    fileInputs.forEach(config => {
        const input = document.getElementById(config.input);
        if (input) {
            input.addEventListener('change', function() {
                const currentFileInfo = document.getElementById(config.current);
                const fileName = document.getElementById(config.name);
                
                if (this.files.length > 0) {
                    const file = this.files[0];
                    if (fileName) {
                        fileName.textContent = file.name;
                    }
                    if (currentFileInfo) {
                        currentFileInfo.style.display = 'block';
                        currentFileInfo.innerHTML = `<small class="text-success"><i class="fas fa-check-circle me-1"></i>New file selected: ${file.name}</small>`;
                    }
                } else {
                    if (currentFileInfo) {
                        currentFileInfo.style.display = 'none';
                    }
                }
            });
        }
    });
});
</script>