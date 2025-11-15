<?php
// Add Labour Modal
// This file is intended to be included into a page (example: purchase_manager_dashboard.php)
?>

<div id="addLabourModal" class="modal" aria-hidden="true">
    <div class="modal-overlay" data-modal-close></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="addLabourTitle">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <i class="fas fa-hard-hat modal-title-icon" aria-hidden="true"></i>
                <h2 id="addLabourTitle">Add Labour</h2>
            </div>
            <button type="button" class="modal-close" aria-label="Close" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="addLabourForm" class="modal-form">
            <div class="modal-body">
                <h3 class="section-title">Personal Information</h3>

                <label class="form-group">
                    <span class="label-text">Full name</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" name="full_name" id="labour_full_name" required />
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">Contact number</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                        <input type="tel" name="contact_number" id="labour_contact_number" placeholder="9876543210" maxlength="10" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number" required />
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">Alternative number</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-phone-alt"></i></span>
                        <input type="tel" name="alt_number" id="labour_alt_number" placeholder="Optional - 9876543210" maxlength="10" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number" />
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">Join date</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-calendar-day"></i></span>
                        <input type="date" name="join_date" id="labour_join_date" />
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">Labour type</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-layer-group"></i></span>
                        <select name="labour_type" id="labour_type">
                            <option value="">Select type</option>
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporary</option>
                            <option value="vendor">Vendor</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">Daily salary</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-money-bill-wave"></i></span>
                        <input type="number" name="daily_salary" id="labour_daily_salary" step="0.01" min="0" />
                    </div>
                </label>

                <!-- Labour ID Proof Section -->
                <div class="section-divider"></div>
                <div class="section-header">
                    <i class="fas fa-id-card"></i>
                    <h3>Labour ID Proof</h3>
                    <button type="button" class="section-toggle-btn" id="idProofToggleBtn" aria-expanded="false">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <div class="section-content collapsed" id="idProofContent">
                    <div class="document-grid">
                        <!-- Aadhar Card Upload -->
                        <div class="form-group document-upload">
                            <span class="label-text">Aadhar Card</span>
                            <div class="file-upload-container">
                                <input type="file" id="aadhar_card" name="aadhar_card" accept="image/*,.pdf" class="file-input">
                                <label for="aadhar_card" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload</span>
                                </label>
                                <div class="file-name" id="aadhar_file_name"></div>
                            </div>
                        </div>

                        <!-- PAN Card Upload -->
                        <div class="form-group document-upload">
                            <span class="label-text">PAN Card</span>
                            <div class="file-upload-container">
                                <input type="file" id="pan_card" name="pan_card" accept="image/*,.pdf" class="file-input">
                                <label for="pan_card" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload</span>
                                </label>
                                <div class="file-name" id="pan_file_name"></div>
                            </div>
                        </div>

                        <!-- Voter ID Upload -->
                        <div class="form-group document-upload">
                            <span class="label-text">Voter ID</span>
                            <div class="file-upload-container">
                                <input type="file" id="voter_id" name="voter_id" accept="image/*,.pdf" class="file-input">
                                <label for="voter_id" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload</span>
                                </label>
                                <div class="file-name" id="voter_file_name"></div>
                            </div>
                        </div>

                        <!-- Other Document Upload -->
                        <div class="form-group document-upload">
                            <span class="label-text">Other Document</span>
                            <div class="file-upload-container">
                                <input type="file" id="other_document" name="other_document" accept="image/*,.pdf" class="file-input">
                                <label for="other_document" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload</span>
                                </label>
                                <div class="file-name" id="other_file_name"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Information Section -->
                <div class="section-divider"></div>
                <div class="section-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Address Information</h3>
                </div>

                <label class="form-group">
                    <span class="label-text">Street Address</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-road"></i></span>
                        <input type="text" name="street_address" id="labour_street_address" placeholder="House / Street / Locality" />
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">City</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-city"></i></span>
                        <input type="text" name="city" id="labour_city" placeholder="e.g., Mumbai" />
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">State</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-map"></i></span>
                        <input type="text" name="state" id="labour_state" placeholder="e.g., Maharashtra" />
                    </div>
                </label>

                <label class="form-group">
                    <span class="label-text">Zip Code</span>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="fas fa-hashtag"></i></span>
                        <input type="text" name="zip_code" id="labour_zip_code" placeholder="400001" maxlength="6" pattern="[0-9]{6}" />
                    </div>
                </label>

            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            </div>
        </form>
        <div class="modal-toast" aria-hidden="true"></div>
    </div>
</div>

<style>
/* Minimal, balanced modal styles */
#addLabourModal {
    display: none;
    position: fixed;
    inset: 0;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
}
#addLabourModal.active { display: flex; }

#addLabourModal .modal-overlay {
    position: absolute; inset: 0; background: rgba(10,10,10,0.45); backdrop-filter: blur(3px);
}

#addLabourModal .modal-dialog {
    position: relative;
    background: #fff;
    border-radius: 10px;
    max-width: 640px;
    width: 100%;
    margin: 20px;
    z-index: 2;
    overflow: hidden;
    border: 1px solid rgba(15,23,42,0.06);
    box-shadow: 0 10px 30px rgba(2,6,23,0.12);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

#addLabourModal .modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; border-bottom: 1px solid rgba(15,23,42,0.04);
}
#addLabourModal .modal-title-wrap { display:flex; align-items:center; gap:10px; }
#addLabourModal .modal-title-icon { color:#1f5a8a; font-size:18px; }
#addLabourModal .modal-header h2 { margin:0; font-weight:700; color:#0f1724; font-size:18px; }
#addLabourModal .modal-close { background:transparent; border:0; color:#6b7280; font-size:18px; cursor:pointer; }

#addLabourModal .modal-body { padding: 18px 22px; display:grid; grid-template-columns: repeat(2,1fr); gap:16px; overflow-y: auto; max-height: calc(90vh - 180px); }
#addLabourModal .section-title { grid-column:1/-1; margin-bottom:8px; color:#0f1724; font-weight:700; font-size:13px; }
#addLabourModal .form-group { display:flex; flex-direction:column; }
#addLabourModal .label-text { font-size:12px; color:#475569; margin-bottom:6px; }

/* input with left icons */
#addLabourModal .input-wrap { position:relative; }
#addLabourModal .input-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:14px; pointer-events:none; }

#addLabourModal input[type="text"],
#addLabourModal input[type="tel"],
#addLabourModal input[type="date"],
#addLabourModal input[type="number"],
#addLabourModal select {
    width:100%; padding:10px 12px 10px 40px; height:42px; border-radius:8px; border:1px solid #e6e9ee;
    font-size:14px; color:#0f1724; background:#fff; box-sizing:border-box;
}

#addLabourModal select { appearance: none; padding-right:36px; }

#addLabourModal input:focus, #addLabourModal select:focus {
    outline:none; border-color:#1f5a8a; box-shadow:0 8px 22px rgba(31,90,138,0.08);
}

#addLabourModal .modal-actions { display:flex; gap:10px; padding:14px 20px; border-top:1px solid rgba(15,23,42,0.03); justify-content:flex-end; }
#addLabourModal .btn { padding:10px 16px; border-radius:8px; cursor:pointer; border:none; font-weight:600; font-size:14px; min-width:84px; }
#addLabourModal .btn-primary { background:#1f5a8a; color:#fff; box-shadow:0 6px 18px rgba(31,90,138,0.12); }
#addLabourModal .btn-secondary { background:#fff; color:#374151; border:1px solid rgba(15,23,42,0.06); }

#addLabourModal .modal-toast { position:absolute; right:16px; top:14px; pointer-events:none; }
#addLabourModal .toast-message { background:#ecfdf5; color:#065f46; padding:8px 12px; border-radius:8px; border:1px solid rgba(6,95,70,0.08); font-size:13px; }

/* Section styles */
#addLabourModal .section-divider { grid-column:1/-1; height:1px; background:rgba(15,23,42,0.04); margin:12px 0; }
#addLabourModal .section-header { grid-column:1/-1; display:flex; align-items:center; gap:10px; margin-bottom:8px; }
#addLabourModal .section-header i { color:#1f5a8a; font-size:14px; }
#addLabourModal .section-header h3 { margin:0; font-weight:700; color:#0f1724; font-size:13px; flex:1; }
#addLabourModal .section-toggle-btn { background:transparent; border:0; color:#6b7280; cursor:pointer; font-size:14px; padding:0; width:20px; height:20px; display:flex; align-items:center; justify-content:center; transition:transform 0.2s ease; }
#addLabourModal .section-toggle-btn.active i { transform:rotate(180deg); }

/* Section content */
#addLabourModal .section-content { grid-column:1/-1; max-height:1200px; overflow:hidden; transition:all 0.3s ease; margin:0; }
#addLabourModal .section-content.collapsed { max-height:0; opacity:0; }

/* Document upload styles */
#addLabourModal .document-grid { display:grid; grid-template-columns: repeat(2, 1fr); gap:14px; }
#addLabourModal .document-upload { margin-bottom:0; }
#addLabourModal .file-upload-container { border:2px dashed #e6e9ee; border-radius:8px; padding:20px 16px; text-align:center; transition:all 0.2s ease; cursor:pointer; }
#addLabourModal .file-upload-container:hover { border-color:#1f5a8a; background:#f8fafc; }
#addLabourModal .file-input { display:none; }
#addLabourModal .file-upload-label { display:flex; flex-direction:column; align-items:center; gap:8px; cursor:pointer; }
#addLabourModal .file-upload-label i { font-size:16px; color:#1f5a8a; }
#addLabourModal .file-upload-label span { font-size:12px; color:#6b7280; font-weight:500; }
#addLabourModal .file-name { margin-top:8px; font-size:11px; color:#059669; display:none; }
#addLabourModal .file-name.active { display:block; }

@media (max-width:640px) { 
    #addLabourModal .modal-body { grid-template-columns:1fr; }
    #addLabourModal .section-content { grid-column:1; }
    #addLabourModal .document-grid { grid-template-columns:1fr; }
}

/* Dialog transition */
#addLabourModal .modal-dialog { transition: transform 0.18s cubic-bezier(.2,.9,.3,1), opacity 0.12s ease; transform: translateY(6px); opacity:0; }
#addLabourModal.active .modal-dialog { transform: translateY(0); opacity:1; }
</style>

<script>
(function(){
    const modal = document.getElementById('addLabourModal');
    if (!modal) return;

    let lastActive = null;

    function closeModal() {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastActive) lastActive.focus();
    }

    function openModal() {
        lastActive = document.activeElement;
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        const first = modal.querySelector('input, select, textarea');
        if (first) first.focus();
    }

    // Close buttons / overlay
    modal.querySelectorAll('[data-modal-close]').forEach(el => {
        el.addEventListener('click', closeModal);
    });

    // Close on ESC
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    // Basic form handling (placeholder) with subtle toast instead of alert
    const form = document.getElementById('addLabourForm');
    const toastWrap = modal.querySelector('.modal-toast');
    function showToast(message, timeout = 2500) {
        if (!toastWrap) return;
        toastWrap.innerHTML = '';
        const div = document.createElement('div');
        div.className = 'toast-message';
        div.textContent = message;
        toastWrap.appendChild(div);
        toastWrap.setAttribute('aria-hidden', 'false');
        setTimeout(() => {
            if (div && div.parentNode) div.parentNode.removeChild(div);
            toastWrap.setAttribute('aria-hidden', 'true');
        }, timeout);
    }

    if (form) {
        form.addEventListener('submit', function(e){
            e.preventDefault();

            // Create FormData to handle file uploads
            const formData = new FormData(form);

            // Show loading state on submit button
            const submitBtn = form.querySelector('.btn-primary');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            // Send data via AJAX
            fetch('handlers/add_labour_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Labour added successfully! Code: ' + data.labour_code);
                    form.reset();
                    
                    // Reset file names
                    document.querySelectorAll('.file-name').forEach(el => {
                        el.innerHTML = '';
                        el.classList.remove('active');
                    });

                    // Close modal after brief delay
                    setTimeout(closeModal, 1500);
                } else if (data.is_duplicate) {
                    // Show duplicate warning with existing labour code
                    showToast('⚠️ Labour already exists! ID: ' + data.existing_code, 4000);
                } else {
                    showToast('Error: ' + data.message, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 3000);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }

    // Handle ID Proof section toggle
    const idProofToggleBtn = document.getElementById('idProofToggleBtn');
    const idProofContent = document.getElementById('idProofContent');
    if (idProofToggleBtn) {
        idProofToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            idProofContent.classList.toggle('collapsed');
            idProofToggleBtn.classList.toggle('active');
            idProofToggleBtn.setAttribute('aria-expanded', idProofToggleBtn.classList.contains('active'));
        });
    }

    // Handle file uploads for ID proof documents
    const fileInputs = [
        { id: 'aadhar_card', nameId: 'aadhar_file_name' },
        { id: 'pan_card', nameId: 'pan_file_name' },
        { id: 'voter_id', nameId: 'voter_file_name' },
        { id: 'other_document', nameId: 'other_file_name' }
    ];

    fileInputs.forEach(file => {
        const input = document.getElementById(file.id);
        const nameDisplay = document.getElementById(file.nameId);
        if (input) {
            input.addEventListener('change', function(e) {
                if (this.files && this.files.length > 0) {
                    const fileName = this.files[0].name;
                    nameDisplay.innerHTML = '<i class="fas fa-check-circle"></i> ' + fileName;
                    nameDisplay.classList.add('active');
                } else {
                    nameDisplay.innerHTML = '';
                    nameDisplay.classList.remove('active');
                }
            });
        }
    });

    // Expose openModal to global so other scripts can call it
    window.openAddLabourModal = openModal;
})();
</script>
