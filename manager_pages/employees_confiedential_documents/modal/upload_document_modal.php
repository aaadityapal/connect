<div class="upload-modal-overlay" id="uploadDocModal" aria-hidden="true">
    <div class="upload-modal-card" role="dialog" aria-modal="true" aria-labelledby="uploadDocTitle">
        <div class="upload-modal-header">
            <h3 id="uploadDocTitle">
                <i data-lucide="file-lock-2" style="width:16px;height:16px;"></i>
                <span>Upload Employee Document</span>
            </h3>
            <button type="button" class="upload-modal-close" id="closeUploadDocModal" aria-label="Close upload modal">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>

        <form id="uploadDocumentForm" class="upload-modal-form" enctype="multipart/form-data">
            <div class="upload-form-grid">
                <label class="upload-field">
                    <span>
                        <i data-lucide="tag" style="width:14px;height:14px;"></i>
                        <em>Document Type</em>
                    </span>
                    <select name="document_type" id="documentType" required>
                        <option value="">Select document type</option>
                        <option value="salary-slip">Salary Slip</option>
                        <option value="joining-letter">Joining Letter</option>
                        <option value="increment-letter">Increment Letter</option>
                        <option value="appraisal-letter">Appraisal Letter</option>
                        <option value="promotion-letter">Promotion Letter</option>
                        <option value="warning-letter">Warning Letter</option>
                        <option value="experience-letter">Experience Letter</option>
                        <option value="relieving-letter">Relieving Letter</option>
                        <option value="termination-letter">Termination Letter</option>
                        <option value="probation-confirmation-letter">Probation Confirmation Letter</option>
                        <option value="id-proof">ID Proof</option>
                        <option value="address-proof">Address Proof</option>
                        <option value="education-certificate">Education Certificate</option>
                        <option value="bank-details">Bank Details</option>
                        <option value="background-verification">Background Verification</option>
                        <option value="policy-acknowledgement">Policy Acknowledgement</option>
                        <option value="nda">NDA Document</option>
                        <option value="custom">Custom</option>
                        <option value="other">Other</option>
                    </select>
                </label>

                <label class="upload-field" id="customDocumentTypeField" hidden>
                    <span>
                        <i data-lucide="pencil-line" style="width:14px;height:14px;"></i>
                        <em>Custom Document Type</em>
                    </span>
                    <input type="text" name="custom_document_type" id="customDocumentType" placeholder="Enter custom document type">
                </label>

                <label class="upload-field">
                    <span>
                        <i data-lucide="calendar-days" style="width:14px;height:14px;"></i>
                        <em>Document Date</em>
                    </span>
                    <input type="date" name="document_date" id="documentDate" required>
                </label>

                <label class="upload-field upload-field-full">
                    <span>
                        <i data-lucide="file-text" style="width:14px;height:14px;"></i>
                        <em>Document Name</em>
                    </span>
                    <input type="text" name="document_name" id="documentName" placeholder="Example: Salary Slip - Apr 2026" required>
                </label>

                <label class="upload-field upload-field-full">
                    <span>
                        <i data-lucide="paperclip" style="width:14px;height:14px;"></i>
                        <em>Upload Media</em>
                    </span>
                    <input type="file" name="document_file" id="documentFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    <small>Allowed: PDF, JPG, PNG, DOC, DOCX (max 10MB suggested)</small>
                </label>

                <label class="upload-field">
                    <span>
                        <i data-lucide="calendar-check-2" style="width:14px;height:14px;"></i>
                        <em>Expiry Date (Optional)</em>
                    </span>
                    <input type="date" name="expiry_date" id="expiryDate">
                </label>

                <label class="upload-field">
                    <span>
                        <i data-lucide="eye" style="width:14px;height:14px;"></i>
                        <em>Visibility</em>
                    </span>
                    <select name="visibility_mode" id="visibilityMode" required>
                        <option value="all">To all</option>
                        <option value="specific_users">According to user IDs</option>
                    </select>
                </label>

                <label class="upload-field upload-field-full" id="visibilityUserIdsField" hidden>
                    <span>
                        <i data-lucide="users" style="width:14px;height:14px;"></i>
                        <em>User IDs</em>
                    </span>
                    <input type="text" name="visibility_user_ids" id="visibilityUserIds" placeholder="Example: 12, 18, 27">
                    <small>Enter comma-separated user IDs allowed to view this document.</small>
                </label>

                <label class="upload-field upload-field-full">
                    <span>
                        <i data-lucide="notebook-pen" style="width:14px;height:14px;"></i>
                        <em>Notes</em>
                    </span>
                    <textarea name="notes" id="documentNotes" rows="3" placeholder="Add internal remarks, verification info, or context."></textarea>
                </label>
            </div>

            <div class="upload-modal-actions">
                <button type="button" class="upload-btn-cancel" id="cancelUploadDocModal">Cancel</button>
                <button type="submit" class="upload-btn-submit">
                    <i data-lucide="upload-cloud" style="width:15px;height:15px;"></i>
                    <span>Upload Document</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="upload-progress-overlay" id="uploadProgressOverlay" aria-hidden="true" hidden>
    <div class="upload-progress-card" role="status" aria-live="polite">
        <div class="upload-spinner" aria-hidden="true"></div>
        <h4>File Upload In Progress</h4>
        <p>Please wait, file is uploading...</p>
    </div>
</div>

<div class="ui-notice-overlay" id="uiNoticeModal" aria-hidden="true" hidden>
    <div class="ui-notice-card" role="dialog" aria-modal="true" aria-labelledby="uiNoticeTitle" aria-describedby="uiNoticeMessage">
        <h4 id="uiNoticeTitle">Notification</h4>
        <p id="uiNoticeMessage">Action completed.</p>
        <div class="ui-notice-actions">
            <button type="button" id="uiNoticeOkBtn">OK</button>
        </div>
    </div>
</div>

<div class="ui-confirm-overlay" id="uiConfirmModal" aria-hidden="true" hidden>
    <div class="ui-confirm-card" role="dialog" aria-modal="true" aria-labelledby="uiConfirmTitle" aria-describedby="uiConfirmMessage">
        <h4 id="uiConfirmTitle">Confirm Action</h4>
        <p id="uiConfirmMessage">Are you sure?</p>
        <div class="ui-confirm-details" id="uiConfirmDetails"></div>
        <div class="ui-confirm-actions">
            <button type="button" class="btn-cancel" id="uiConfirmCancelBtn">Cancel</button>
            <button type="button" class="btn-confirm" id="uiConfirmOkBtn">Confirm</button>
        </div>
    </div>
</div>
