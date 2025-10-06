<?php
/**
 * Missing Punch Out Modal
 * This modal is displayed when a user clicks on a notification for a missing punch-out
 */
?>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Missing Punch Out Modal -->
<div class="missing-punch-out-modal" id="missingPunchOutModal">
    <div class="missing-punch-out-modal-content">
        <div class="missing-punch-out-modal-header">
            <h3><i class="fas fa-clock"></i> Missing Punch Out</h3>
            <span class="missing-punch-out-close">&times;</span>
        </div>
        <div class="missing-punch-out-modal-body">
            <div class="missing-punch-out-info">
                <p><i class="fas fa-info-circle"></i> Please provide the time and reason for your missing punch-out:</p>
                <div class="missing-punch-out-date">
                    <label for="missingPunchOutDate"><i class="fas fa-calendar-alt"></i> Date:</label>
                    <input type="text" id="missingPunchOutDate" readonly>
                </div>
                <div class="missing-punch-out-time">
                    <label for="missingPunchOutTime"><i class="fas fa-business-time"></i> Punch Out Time:</label>
                    <input type="time" id="missingPunchOutTime" required>
                </div>
                <div class="missing-punch-out-reason">
                    <label for="missingPunchOutReason"><i class="fas fa-comment-alt"></i> Reason (min 15 words):</label>
                    <textarea id="missingPunchOutReason" maxlength="200" placeholder="Enter reason for missing punch-out (minimum 15 words)" required></textarea>
                    <div class="word-counter">
                        <span id="missingPunchOutWordCount">0</span>/15 words minimum
                    </div>
                </div>
                <div class="work-report-section">
                    <label for="missingPunchOutWorkReport"><i class="fas fa-file-alt"></i> Work Report (min 20 words):</label>
                    <textarea id="missingPunchOutWorkReport" maxlength="1000" placeholder="Please provide a detailed work report (minimum 20 words)" required></textarea>
                    <div class="word-counter">
                        <span id="workReportWordCount">0</span>/20 words minimum
                    </div>
                </div>
                <div class="missing-punch-out-confirmation">
                    <label>
                        <input type="checkbox" id="missingPunchOutConfirm" required>
                        <i class="fas fa-check-circle"></i> I confirm that the information provided is accurate
                    </label>
                </div>
            </div>
        </div>
        <div class="missing-punch-out-modal-footer">
            <button id="submitMissingPunchOut" class="submit-missing-punch-out-btn" disabled>
                <i class="fas fa-paper-plane"></i> Submit Missing Punch Out
            </button>
        </div>
    </div>
</div>

<style>
.missing-punch-out-modal {
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

.missing-punch-out-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: 18px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    position: relative;
}

.missing-punch-out-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #eee;
    background: linear-gradient(120deg, #e0f7fa, #f5f5f5);
    color: #333;
    border-radius: 18px 18px 0 0;
}

.missing-punch-out-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.missing-punch-out-modal-header h3 i {
    margin-right: 10px;
    color: #0097a7;
}

.missing-punch-out-close {
    font-size: 24px;
    cursor: pointer;
    color: #999;
    transition: color 0.2s;
    font-weight: normal;
}

.missing-punch-out-close:hover {
    color: #666;
}

.missing-punch-out-modal-body {
    padding: 20px 25px;
    background-color: #fafafa;
}

.missing-punch-out-info p {
    margin-top: 0;
    color: #555;
    font-size: 15px;
    line-height: 1.5;
    margin-bottom: 20px;
    background-color: #e1f5fe;
    padding: 12px 15px;
    border-radius: 8px;
    border-left: 4px solid #00bcd4;
}

.missing-punch-out-info p i {
    margin-right: 8px;
    color: #0097a7;
}

.missing-punch-out-date,
.missing-punch-out-time,
.missing-punch-out-reason,
.work-report-section {
    margin-bottom: 20px;
}

.missing-punch-out-date label,
.missing-punch-out-time label,
.missing-punch-out-reason label,
.work-report-section label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #444;
    font-size: 14px;
}

.missing-punch-out-date label i,
.missing-punch-out-time label i,
.missing-punch-out-reason label i,
.work-report-section label i {
    margin-right: 8px;
    color: #0097a7;
}

.missing-punch-out-date input,
.missing-punch-out-time input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
    box-sizing: border-box;
    background-color: #ffffff;
    transition: all 0.3s ease;
}

.missing-punch-out-date input[readonly] {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.missing-punch-out-date input:focus,
.missing-punch-out-time input:focus {
    border-color: #00bcd4;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.1);
}

.missing-punch-out-reason textarea,
.work-report-section textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    min-height: 100px;
    box-sizing: border-box;
    transition: all 0.3s ease;
    background-color: #ffffff;
}

.missing-punch-out-reason textarea:focus,
.work-report-section textarea:focus {
    border-color: #00bcd4;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.1);
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

.missing-punch-out-confirmation {
    margin-top: 15px;
}

.missing-punch-out-confirmation label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-weight: normal;
    color: #444;
    cursor: pointer;
    font-size: 14px;
    background-color: #e8f5e9;
    padding: 12px 15px;
    border-radius: 8px;
    border-left: 4px solid #4caf50;
}

.missing-punch-out-confirmation label i {
    margin-top: 3px;
    color: #4caf50;
}

.missing-punch-out-confirmation input[type="checkbox"] {
    margin-top: 3px;
    transform: scale(1.1);
}

.missing-punch-out-modal-footer {
    padding: 20px 25px;
    text-align: center;
    border-top: 1px solid #eee;
    background: linear-gradient(120deg, #f5f5f5, #e0f7fa);
    border-radius: 0 0 18px 18px;
}

.submit-missing-punch-out-btn {
    padding: 12px 25px;
    background: linear-gradient(120deg, #00bcd4, #0097a7);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 5px rgba(0, 188, 212, 0.2);
}

.submit-missing-punch-out-btn:hover:not([disabled]) {
    background: linear-gradient(120deg, #0097a7, #00838f);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 188, 212, 0.3);
}

.submit-missing-punch-out-btn[disabled] {
    background: linear-gradient(120deg, #bdbdbd, #9e9e9e);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.submit-missing-punch-out-btn i {
    font-size: 14px;
}

@media (max-width: 768px) {
    .missing-punch-out-modal-content {
        width: 95%;
        margin: 5% auto;
        border-radius: 15px;
    }
    
    .missing-punch-out-modal-header {
        padding: 15px 20px;
        border-radius: 15px 15px 0 0;
    }
    
    .missing-punch-out-modal-header h3 {
        font-size: 16px;
    }
    
    .missing-punch-out-close {
        font-size: 22px;
    }
    
    .missing-punch-out-modal-body {
        padding: 15px 20px;
    }
    
    .missing-punch-out-info p {
        font-size: 14px;
    }
    
    .submit-missing-punch-out-btn {
        width: 100%;
        padding: 12px;
        font-size: 14px;
    }
    
    .missing-punch-out-modal-footer {
        padding: 15px 20px;
        border-radius: 0 0 15px 15px;
    }
}
</style>

<script>
// Function to open the missing punch out modal with a specific date
function openMissingPunchOutModal(date) {
    const modal = document.getElementById('missingPunchOutModal');
    const dateInput = document.getElementById('missingPunchOutDate');
    const timeInput = document.getElementById('missingPunchOutTime');
    const reasonInput = document.getElementById('missingPunchOutReason');
    const workReportInput = document.getElementById('missingPunchOutWorkReport');
    const reasonWordCount = document.getElementById('missingPunchOutWordCount');
    const workReportWordCount = document.getElementById('workReportWordCount');
    const confirmCheckbox = document.getElementById('missingPunchOutConfirm');
    const submitButton = document.getElementById('submitMissingPunchOut');
    
    // Set the date in the modal
    dateInput.value = date;
    
    // Clear previous values
    timeInput.value = '';
    reasonInput.value = '';
    workReportInput.value = '';
    reasonWordCount.textContent = '0';
    workReportWordCount.textContent = '0';
    confirmCheckbox.checked = false;
    submitButton.disabled = true;
    
    // Show the modal
    modal.style.display = 'block';
}

// Close modal function
function closeMissingPunchOutModal() {
    const modal = document.getElementById('missingPunchOutModal');
    modal.style.display = 'none';
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

// Function to initialize event listeners for the missing punch out modal
function initMissingPunchOutModal() {
    // Check if the modal elements exist
    if (!document.getElementById('missingPunchOutModal')) {
        // If not, wait a bit and try again (in case the DOM is still loading)
        setTimeout(initMissingPunchOutModal, 100);
        return;
    }
    
    const modal = document.getElementById('missingPunchOutModal');
    const closeBtn = document.querySelector('.missing-punch-out-close');
    const reasonInput = document.getElementById('missingPunchOutReason');
    const workReportInput = document.getElementById('missingPunchOutWorkReport');
    const timeInput = document.getElementById('missingPunchOutTime');
    const confirmCheckbox = document.getElementById('missingPunchOutConfirm');
    const submitButton = document.getElementById('submitMissingPunchOut');
    
    // Close modal when clicking the close button
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMissingPunchOutModal);
    }
    
    // Close modal when clicking outside the modal content
    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeMissingPunchOutModal();
            }
        });
    }
    
    // Word counting for reason textarea
    if (reasonInput) {
        reasonInput.addEventListener('input', function() {
            const wordCount = countWords(this.value);
            const wordCountElement = document.getElementById('missingPunchOutWordCount');
            if (wordCountElement) {
                wordCountElement.textContent = wordCount;
                
                if (wordCount < 15) {
                    wordCountElement.parentElement.classList.add('insufficient');
                } else {
                    wordCountElement.parentElement.classList.remove('insufficient');
                }
            }
            
            updatePunchOutSubmitButton();
        });
    }
    
    // Word counting for work report textarea
    if (workReportInput) {
        workReportInput.addEventListener('input', function() {
            const wordCount = countWords(this.value);
            const wordCountElement = document.getElementById('workReportWordCount');
            if (wordCountElement) {
                wordCountElement.textContent = wordCount;
                
                if (wordCount < 20) {
                    wordCountElement.parentElement.classList.add('insufficient');
                } else {
                    wordCountElement.parentElement.classList.remove('insufficient');
                }
            }
            
            updatePunchOutSubmitButton();
        });
    }
    
    // Update submit button when time changes
    if (timeInput) {
        timeInput.addEventListener('input', updatePunchOutSubmitButton);
    }
    
    // Update submit button when confirmation checkbox changes
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', updatePunchOutSubmitButton);
    }
    
    // Submit button click handler
    if (submitButton) {
        submitButton.addEventListener('click', function() {
            const date = document.getElementById('missingPunchOutDate').value;
            const time = document.getElementById('missingPunchOutTime').value;
            const reason = document.getElementById('missingPunchOutReason').value;
            const workReport = document.getElementById('missingPunchOutWorkReport').value;
            const reasonWordCount = countWords(reason);
            const workReportWordCount = countWords(workReport);
            
            // Validate inputs
            if (!time) {
                alert('Please enter the punch-out time.');
                return;
            }
            
            if (reasonWordCount < 15) {
                alert('Please provide a reason with at least 15 words.');
                return;
            }
            
            if (workReportWordCount < 20) {
                alert('Please provide a work report with at least 20 words.');
                return;
            }
            
            if (!confirmCheckbox.checked) {
                alert('Please confirm that the information provided is accurate.');
                return;
            }
            
            // Submit the data to the backend
            const formData = new FormData();
            formData.append('date', date);
            formData.append('time', time);
            formData.append('reason', reason);
            formData.append('work_report', workReport);
            formData.append('confirmed', confirmCheckbox.checked);
            
            fetch('ajax_handlers/submit_missing_punch_out.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Missing punch-out submitted successfully!');
                    closeMissingPunchOutModal();
                    
                    // Optionally refresh the notification count
                    // This would require calling the notification update function
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the missing punch-out. Please try again.');
            });
        });
    }
}

// Function to update the submit button state
function updatePunchOutSubmitButton() {
    const timeInput = document.getElementById('missingPunchOutTime');
    const reasonInput = document.getElementById('missingPunchOutReason');
    const workReportInput = document.getElementById('missingPunchOutWorkReport');
    const confirmCheckbox = document.getElementById('missingPunchOutConfirm');
    const submitButton = document.getElementById('submitMissingPunchOut');
    
    if (!timeInput || !reasonInput || !workReportInput || !confirmCheckbox || !submitButton) {
        return;
    }
    
    const reasonWordCount = countWords(reasonInput.value);
    const workReportWordCount = countWords(workReportInput.value);
    
    // Enable submit button only if all conditions are met
    const isTimeValid = timeInput.value !== '';
    const isReasonValid = reasonWordCount >= 15;
    const isWorkReportValid = workReportWordCount >= 20;
    const isConfirmed = confirmCheckbox.checked;
    
    submitButton.disabled = !(isTimeValid && isReasonValid && isWorkReportValid && isConfirmed);
}

// Initialize the modal when the script loads
initMissingPunchOutModal();
</script>