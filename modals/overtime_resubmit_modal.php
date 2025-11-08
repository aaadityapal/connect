<?php
/**
 * Overtime Resubmit Modal
 * This modal is displayed when a user wants to resubmit a rejected overtime request
 */
?>
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Overtime Resubmit Modal -->
<div class="overtime-resubmit-modal" id="overtimeResubmitModal">
    <div class="overtime-resubmit-modal-content">
        <div class="overtime-resubmit-modal-header">
            <h3><i class="fas fa-redo"></i> Resubmit Overtime Request</h3>
            <span class="overtime-resubmit-close">&times;</span>
        </div>
        <div class="overtime-resubmit-modal-body">
            <div class="overtime-resubmit-info">
                <p><i class="fas fa-info-circle"></i> Review and update your overtime request before resubmitting:</p>
                
                <div class="overtime-resubmit-details">
                    <!-- Date and Shift End in one row -->
                    <div class="overtime-resubmit-row">
                        <div class="overtime-resubmit-field">
                            <label><i class="fas fa-calendar-alt"></i> Date:</label>
                            <span id="resubmitDate">-</span>
                        </div>
                        <div class="overtime-resubmit-field">
                            <label><i class="fas fa-clock"></i> Shift End:</label>
                            <span id="resubmitShiftEnd">-</span>
                        </div>
                    </div>
                    
                    <!-- Punch Out and OT Hours in one row -->
                    <div class="overtime-resubmit-row">
                        <div class="overtime-resubmit-field">
                            <label><i class="fas fa-user-clock"></i> Punch Out:</label>
                            <span id="resubmitPunchOut">-</span>
                        </div>
                        <div class="overtime-resubmit-field">
                            <label><i class="fas fa-hourglass-half"></i> OT Hours:</label>
                            <span id="resubmitOtHours">-</span>
                        </div>
                    </div>
                    
                    <!-- Work Report -->
                    <div class="overtime-resubmit-field">
                        <label><i class="fas fa-file-alt"></i> Work Report:</label>
                        <textarea id="resubmitWorkReport" placeholder="Enter your work report..." maxlength="500"></textarea>
                    </div>
                    
                    <!-- Overtime Description -->
                    <div class="overtime-resubmit-field">
                        <label><i class="fas fa-comment-alt"></i> Overtime Description (min 15 words):</label>
                        <textarea id="resubmitOvertimeDescription" placeholder="Describe what you did during overtime..." required></textarea>
                        <div class="word-counter">
                            <span id="resubmitWordCount">0</span>/15 words
                        </div>
                    </div>
                    
                    <!-- Rejection Reason -->
                    <div class="overtime-resubmit-rejection">
                        <label><i class="fas fa-exclamation-triangle"></i> Rejection Reason:</label>
                        <div class="rejection-reason" id="rejectionReason">
                            No rejection reason provided
                        </div>
                    </div>
                </div>
                
                <div class="overtime-resubmit-confirmation">
                    <label>
                        <input type="checkbox" id="resubmitConfirm" required>
                        <i class="fas fa-check-circle"></i> I confirm that I have addressed the issues
                    </label>
                </div>
            </div>
        </div>
        <div class="overtime-resubmit-modal-footer">
            <button id="cancelResubmit" class="cancel-resubmit-btn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="submitResubmit" class="submit-resubmit-btn" disabled>
                <i class="fas fa-paper-plane"></i> Resubmit
            </button>
        </div>
    </div>
</div>

<style>
.overtime-resubmit-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow: auto;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.overtime-resubmit-modal-content {
    background-color: #fff;
    margin: 8% auto;
    padding: 0;
    width: 90%;
    max-width: 450px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    position: relative;
}

.overtime-resubmit-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    background-color: #fff;
    color: #333;
    border-radius: 10px 10px 0 0;
}

.overtime-resubmit-modal-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
}

.overtime-resubmit-modal-header h3 i {
    margin-right: 10px;
    color: #2196f3;
}

.overtime-resubmit-close {
    font-size: 24px;
    cursor: pointer;
    color: #999;
    transition: color 0.2s;
    font-weight: normal;
}

.overtime-resubmit-close:hover {
    color: #666;
}

.overtime-resubmit-modal-body {
    padding: 12px 16px;
    background-color: #fff;
}

.overtime-resubmit-info p {
    margin-top: 0;
    color: #555;
    font-size: 13px;
    line-height: 1.3;
    margin-bottom: 12px;
    background-color: #f8f9fa;
    padding: 8px 10px;
    border-radius: 5px;
    border-left: 2px solid #2196f3;
}

.overtime-resubmit-info p i {
    margin-right: 8px;
    color: #2196f3;
}

.overtime-resubmit-details {
    margin-bottom: 20px;
}

.overtime-resubmit-field {
    margin-bottom: 10px;
}

.overtime-resubmit-row {
    display: flex;
    gap: 10px;
    margin-bottom: 8px;
}

.overtime-resubmit-row .overtime-resubmit-field {
    flex: 1;
    margin-bottom: 0;
}

.overtime-resubmit-field label {
    display: block;
    margin-bottom: 3px;
    font-weight: 500;
    color: #444;
    font-size: 12px;
}

.overtime-resubmit-field label i {
    margin-right: 8px;
    color: #2196f3;
}

.overtime-resubmit-field span {
    display: block;
    padding: 6px 8px;
    background-color: #ffffff;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 12px;
    color: #555;
}

.overtime-resubmit-field textarea {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: inherit;
    font-size: 12px;
    resize: vertical;
    box-sizing: border-box;
    transition: all 0.15s ease;
    background-color: #ffffff;
}

.overtime-resubmit-field textarea:focus {
    border-color: #2196f3;
    outline: none;
    box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
}

#resubmitWorkReport {
    min-height: 50px;
}

#resubmitOvertimeDescription {
    min-height: 60px;
}

.word-counter {
    text-align: right;
    font-size: 12px;
    color: #888;
    margin-top: 5px;
}

.word-counter.insufficient {
    color: #e53935;
}

.word-counter.sufficient {
    color: #4caf50;
}

.rejection-reason {
    padding: 8px 10px;
    background-color: #fff3f3;
    border: 1px solid #ffcdd2;
    border-radius: 5px;
    color: #c62828;
    font-size: 12px;
    min-height: 40px;
}

.overtime-resubmit-confirmation {
    margin-top: 15px;
}

.overtime-resubmit-confirmation label {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-weight: normal;
    color: #444;
    cursor: pointer;
    font-size: 12px;
    background-color: #f8f9fa;
    padding: 8px 10px;
    border-radius: 5px;
    border-left: 2px solid #00bcd4;
}

.overtime-resubmit-confirmation label i {
    margin-top: 3px;
    color: #00bcd4;
}

.overtime-resubmit-confirmation input[type="checkbox"] {
    margin-top: 3px;
    transform: scale(1.1);
}

.overtime-resubmit-modal-footer {
    padding: 12px 16px;
    text-align: right;
    border-top: 1px solid #eee;
    background-color: #fff;
    border-radius: 0 0 10px 10px;
    display: flex;
    justify-content: flex-end;
    gap: 6px;
}

.cancel-resubmit-btn,
.submit-resubmit-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.cancel-resubmit-btn {
    background: linear-gradient(120deg, #f5f5f5, #e0e0e0);
    color: #666;
    border: 1px solid #ddd;
}

.cancel-resubmit-btn:hover {
    background: linear-gradient(120deg, #e0e0e0, #bdbdbd);
    transform: translateY(-2px);
}

.submit-resubmit-btn {
    background: linear-gradient(120deg, #2196f3, #1976d2);
    color: white;
    box-shadow: 0 2px 5px rgba(33, 150, 243, 0.2);
}

.submit-resubmit-btn:hover:not([disabled]) {
    background: linear-gradient(120deg, #1976d2, #1565c0);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(33, 150, 243, 0.3);
}

.submit-resubmit-btn[disabled] {
    background: linear-gradient(120deg, #bdbdbd, #9e9e9e);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.submit-resubmit-btn i {
    font-size: 14px;
}

@media (max-width: 768px) {
    .overtime-resubmit-modal-content {
        width: 95%;
        margin: 5% auto;
        border-radius: 12px;
    }
    
    .overtime-resubmit-modal-header {
        padding: 12px 15px;
        border-radius: 12px 12px 0 0;
    }
    
    .overtime-resubmit-modal-header h3 {
        font-size: 15px;
    }
    
    .overtime-resubmit-close {
        font-size: 20px;
    }
    
    .overtime-resubmit-modal-body {
        padding: 12px 15px;
    }
    
    .overtime-resubmit-info p {
        font-size: 13px;
    }
    
    .cancel-resubmit-btn,
    .submit-resubmit-btn {
        width: 100%;
        padding: 10px;
        font-size: 13px;
        justify-content: center;
    }
    
    .overtime-resubmit-modal-footer {
        padding: 12px 15px;
        border-radius: 0 0 12px 12px;
        flex-direction: column;
    }
    
    /* Stack fields on mobile */
    .overtime-resubmit-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .overtime-resubmit-row .overtime-resubmit-field {
        margin-bottom: 10px;
    }
}
</style>

<script>
// Function to open the overtime resubmit modal with request data
function openOvertimeResubmitModal(data) {
    const modal = document.getElementById('overtimeResubmitModal');
    
    // Populate modal with data
    if (document.getElementById('resubmitDate')) {
        document.getElementById('resubmitDate').textContent = data.date || '-';
    }
    
    if (document.getElementById('resubmitShiftEnd')) {
        document.getElementById('resubmitShiftEnd').textContent = data.shift_end_time || '-';
    }
    
    if (document.getElementById('resubmitPunchOut')) {
        document.getElementById('resubmitPunchOut').textContent = data.punch_out_time || '-';
    }
    
    if (document.getElementById('resubmitOtHours')) {
        document.getElementById('resubmitOtHours').textContent = data.ot_hours || '-';
    }
    
    if (document.getElementById('resubmitWorkReport')) {
        document.getElementById('resubmitWorkReport').value = data.work_report || '';
    }
    
    if (document.getElementById('resubmitOvertimeDescription')) {
        document.getElementById('resubmitOvertimeDescription').value = data.overtime_description || '';
    }
    
    if (document.getElementById('rejectionReason')) {
        document.getElementById('rejectionReason').textContent = data.rejection_reason || 'No rejection reason provided';
    }
    
    // Reset form elements
    if (document.getElementById('resubmitWordCount')) {
        document.getElementById('resubmitWordCount').textContent = '0';
        document.getElementById('resubmitWordCount').parentElement.classList.remove('insufficient', 'sufficient');
    }
    
    if (document.getElementById('resubmitConfirm')) {
        document.getElementById('resubmitConfirm').checked = false;
    }
    
    if (document.getElementById('submitResubmit')) {
        document.getElementById('submitResubmit').disabled = true;
    }
    
    // Store attendance ID in modal for later use
    if (modal) {
        modal.dataset.attendanceId = data.attendance_id || '';
    }
    
    // Show the modal
    if (modal) {
        modal.style.display = 'block';
    }
}

// Close modal function
function closeOvertimeResubmitModal() {
    const modal = document.getElementById('overtimeResubmitModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Word counting function
function countWords(text) {
    if (!text || typeof text !== 'string') {
        return 0;
    }
    
    // Remove extra spaces and split by whitespace
    const words = text.trim().split(/\s+/);
    
    // Filter out empty entries and entries that contain only special characters
    return words.filter(word => {
        // Keep only if the word contains at least one alphanumeric character
        return word.length > 0 && /[a-zA-Z0-9]/.test(word);
    }).length;
}

// Add a flag to track submission status
let isOvertimeResubmitSubmitting = false;

// Function to initialize event listeners for the overtime resubmit modal
function initOvertimeResubmitModal() {
    // Check if the modal elements exist
    if (!document.getElementById('overtimeResubmitModal')) {
        // If not, wait a bit and try again (in case the DOM is still loading)
        setTimeout(initOvertimeResubmitModal, 100);
        return;
    }
    
    const modal = document.getElementById('overtimeResubmitModal');
    const closeBtn = document.querySelector('.overtime-resubmit-close');
    const cancelBtn = document.getElementById('cancelResubmit');
    const overtimeDescription = document.getElementById('resubmitOvertimeDescription');
    const confirmCheckbox = document.getElementById('resubmitConfirm');
    const submitButton = document.getElementById('submitResubmit');
    
    // Close modal when clicking the close button
    if (closeBtn) {
        closeBtn.addEventListener('click', closeOvertimeResubmitModal);
    }
    
    // Close modal when clicking the cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeOvertimeResubmitModal);
    }
    
    // Close modal when clicking outside the modal content
    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeOvertimeResubmitModal();
            }
        });
    }
    
    // Word counting for overtime description textarea
    if (overtimeDescription) {
        overtimeDescription.addEventListener('input', function() {
            const wordCount = countWords(this.value);
            const wordCountElement = document.getElementById('resubmitWordCount');
            if (wordCountElement) {
                wordCountElement.textContent = wordCount;
                
                // Update styling based on word count
                wordCountElement.parentElement.classList.remove('insufficient', 'sufficient');
                if (wordCount < 15) {
                    wordCountElement.parentElement.classList.add('insufficient');
                } else {
                    wordCountElement.parentElement.classList.add('sufficient');
                }
            }
            
            updateResubmitButton();
        });
    }
    
    // Update submit button when confirmation checkbox changes
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', updateResubmitButton);
    }
    
    // Submit button click handler
    if (submitButton) {
        submitButton.addEventListener('click', function(event) {
            // Prevent default form submission
            event.preventDefault();
            
            // Check if already submitting
            if (isOvertimeResubmitSubmitting) {
                console.log('Submission already in progress, ignoring duplicate click');
                return;
            }
            
            // Set submitting flag
            isOvertimeResubmitSubmitting = true;
            
            // Disable the submit button to prevent double submission
            submitButton.disabled = true;
            
            const attendanceId = modal.dataset.attendanceId;
            const workReport = document.getElementById('resubmitWorkReport').value;
            const overtimeDescription = document.getElementById('resubmitOvertimeDescription').value;
            const wordCount = countWords(overtimeDescription);
            
            // Validate inputs
            if (wordCount < 15) {
                alert('Please provide a detailed overtime description of at least 15 words.');
                isOvertimeResubmitSubmitting = false;
                submitButton.disabled = false;
                return;
            }
            
            if (!confirmCheckbox.checked) {
                alert('Please confirm that you have addressed the issues mentioned in the rejection reason.');
                isOvertimeResubmitSubmitting = false;
                submitButton.disabled = false;
                return;
            }
            
            // Submit the data to the backend
            const requestData = {
                attendance_id: attendanceId,
                work_report: workReport,
                overtime_description: overtimeDescription
            };
            
            fetch('resubmit_overtime_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Overtime request resubmitted successfully!');
                    closeOvertimeResubmitModal();
                    
                    // Refresh the page or update the table to reflect the changes
                    if (typeof location !== 'undefined') {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                    // Re-enable the submit button on error
                    isOvertimeResubmitSubmitting = false;
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while resubmitting the overtime request. Please try again.');
                // Re-enable the submit button on error
                isOvertimeResubmitSubmitting = false;
                submitButton.disabled = false;
            });
        });
    }
}

// Function to update the submit button state
function updateResubmitButton() {
    const overtimeDescription = document.getElementById('resubmitOvertimeDescription');
    const confirmCheckbox = document.getElementById('resubmitConfirm');
    const submitButton = document.getElementById('submitResubmit');
    const wordCount = countWords(overtimeDescription.value);
    
    if (!overtimeDescription || !confirmCheckbox || !submitButton) {
        return;
    }
    
    // Enable submit button only if all conditions are met
    const isDescriptionValid = wordCount >= 15;
    const isConfirmed = confirmCheckbox.checked;
    
    submitButton.disabled = !(isDescriptionValid && isConfirmed);
}

// Initialize the modal when the script loads
if (typeof initOvertimeResubmitModal === 'function') {
    initOvertimeResubmitModal();
}
</script>