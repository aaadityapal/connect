<?php
/**
 * Debug Missing Punch Out Modal
 * This modal is displayed when a user clicks on a notification for a missing punch-out
 */
?>

<!-- Missing Punch Out Modal -->
<div class="missing-punch-out-modal" id="missingPunchOutModal">
    <div class="missing-punch-out-modal-content">
        <div class="missing-punch-out-modal-header">
            <h3>Missing Punch Out (Debug)</h3>
            <span class="missing-punch-out-close">&times;</span>
        </div>
        <div class="missing-punch-out-modal-body">
            <div class="missing-punch-out-info">
                <p>Please provide the time and reason for your missing punch-out:</p>
                <div class="missing-punch-out-date">
                    <label for="missingPunchOutDate">Date:</label>
                    <input type="text" id="missingPunchOutDate" readonly>
                </div>
                <div class="missing-punch-out-time">
                    <label for="missingPunchOutTime">Punch Out Time:</label>
                    <input type="time" id="missingPunchOutTime" required>
                </div>
                <div class="missing-punch-out-reason">
                    <label for="missingPunchOutReason">Reason (min 15 words):</label>
                    <textarea id="missingPunchOutReason" maxlength="200" placeholder="Enter reason for missing punch-out (minimum 15 words)" required></textarea>
                    <div class="word-counter">
                        <span id="missingPunchOutWordCount">0</span>/15 words minimum
                    </div>
                </div>
                <div class="work-report-section">
                    <label for="missingPunchOutWorkReport">Work Report (min 20 words):</label>
                    <textarea id="missingPunchOutWorkReport" maxlength="1000" placeholder="Please provide a detailed work report (minimum 20 words)" required></textarea>
                    <div class="word-counter">
                        <span id="workReportWordCount">0</span>/20 words minimum
                    </div>
                </div>
                <div class="missing-punch-out-confirmation">
                    <label>
                        <input type="checkbox" id="missingPunchOutConfirm" required>
                        I confirm that the information provided is accurate
                    </label>
                </div>
            </div>
        </div>
        <div class="missing-punch-out-modal-footer">
            <button id="submitMissingPunchOut" class="submit-missing-punch-out-btn" disabled>
                Submit Missing Punch Out
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
    background-color: rgba(0,0,0,0.7);
    overflow: auto;
}

.missing-punch-out-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    animation: modalFadeIn 0.3s;
    position: relative;
}

@keyframes modalFadeIn {
    from {opacity: 0; transform: translateY(-20px);}
    to {opacity: 1; transform: translateY(0);}
}

.missing-punch-out-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    background: linear-gradient(145deg, #2196F3, #1976D2);
    color: white;
    border-radius: 10px 10px 0 0;
}

.missing-punch-out-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.missing-punch-out-close {
    font-size: 24px;
    cursor: pointer;
    color: white;
    transition: color 0.2s;
}

.missing-punch-out-close:hover {
    color: #f0f0f0;
}

.missing-punch-out-modal-body {
    padding: 20px;
}

.missing-punch-out-info p {
    margin-top: 0;
    color: #333;
    font-size: 16px;
    line-height: 1.5;
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
    font-weight: 600;
    color: #444;
}

.missing-punch-out-date input,
.missing-punch-out-time input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    box-sizing: border-box;
}

.missing-punch-out-date input[readonly] {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.missing-punch-out-reason textarea,
.work-report-section textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    min-height: 80px;
    box-sizing: border-box;
}

.missing-punch-out-reason textarea:focus,
.work-report-section textarea:focus {
    border-color: #2196F3;
    outline: none;
    box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
}

.word-counter {
    text-align: right;
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.word-counter.insufficient {
    color: #f44336;
    font-weight: bold;
}

.missing-punch-out-confirmation {
    margin-top: 15px;
}

.missing-punch-out-confirmation label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-weight: normal;
    color: #444;
    cursor: pointer;
}

.missing-punch-out-confirmation input[type="checkbox"] {
    margin-top: 3px;
}

.missing-punch-out-modal-footer {
    padding: 15px 20px;
    text-align: center;
    border-top: 1px solid #e0e0e0;
    background-color: #f8f9fa;
    border-radius: 0 0 10px 10px;
}

.submit-missing-punch-out-btn {
    padding: 12px 24px;
    background: linear-gradient(145deg, #4CAF50, #388E3C);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
    text-transform: uppercase;
}

.submit-missing-punch-out-btn:hover:not([disabled]) {
    background: linear-gradient(145deg, #45a049, #2E7D32);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(76, 175, 80, 0.4);
}

.submit-missing-punch-out-btn:active:not([disabled]) {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
}

.submit-missing-punch-out-btn[disabled] {
    background: linear-gradient(145deg, #B0BEC5, #90A4AE);
    cursor: not-allowed;
    opacity: 0.8;
    box-shadow: none;
}

@media (max-width: 768px) {
    .missing-punch-out-modal-content {
        width: 95%;
        margin: 5% auto;
    }
    
    .missing-punch-out-modal-header {
        padding: 12px 15px;
    }
    
    .missing-punch-out-modal-header h3 {
        font-size: 16px;
    }
    
    .missing-punch-out-close {
        font-size: 28px;
    }
    
    .missing-punch-out-modal-body {
        padding: 15px;
    }
    
    .missing-punch-out-info p {
        font-size: 14px;
    }
    
    .submit-missing-punch-out-btn {
        width: 100%;
        padding: 12px;
        font-size: 14px;
    }
}
</style>

<script>
// Debug function to log messages
function debugLog(message) {
    console.log('[Missing Punch Out Modal Debug] ' + message);
}

// Function to open the missing punch out modal with a specific date
function openMissingPunchOutModal(date) {
    debugLog('openMissingPunchOutModal called with date: ' + date);
    
    const modal = document.getElementById('missingPunchOutModal');
    const dateInput = document.getElementById('missingPunchOutDate');
    const timeInput = document.getElementById('missingPunchOutTime');
    const reasonInput = document.getElementById('missingPunchOutReason');
    const workReportInput = document.getElementById('missingPunchOutWorkReport');
    const reasonWordCount = document.getElementById('missingPunchOutWordCount');
    const workReportWordCount = document.getElementById('workReportWordCount');
    const confirmCheckbox = document.getElementById('missingPunchOutConfirm');
    const submitButton = document.getElementById('submitMissingPunchOut');
    
    debugLog('Modal elements found: ' + 
        'modal=' + !!modal + 
        ', dateInput=' + !!dateInput + 
        ', timeInput=' + !!timeInput + 
        ', reasonInput=' + !!reasonInput + 
        ', workReportInput=' + !!workReportInput + 
        ', reasonWordCount=' + !!reasonWordCount + 
        ', workReportWordCount=' + !!workReportWordCount + 
        ', confirmCheckbox=' + !!confirmCheckbox + 
        ', submitButton=' + !!submitButton);
    
    // Set the date in the modal
    if (dateInput) dateInput.value = date;
    
    // Clear previous values
    if (timeInput) timeInput.value = '';
    if (reasonInput) reasonInput.value = '';
    if (workReportInput) workReportInput.value = '';
    if (reasonWordCount) reasonWordCount.textContent = '0';
    if (workReportWordCount) workReportWordCount.textContent = '0';
    if (confirmCheckbox) confirmCheckbox.checked = false;
    if (submitButton) submitButton.disabled = true;
    
    // Show the modal
    if (modal) {
        modal.style.display = 'block';
        debugLog('Modal displayed');
    } else {
        debugLog('Modal element not found');
    }
    
    // Initialize event listeners if not already done
    initMissingPunchOutModal();
}

// Close modal function
function closeMissingPunchOutModal() {
    debugLog('closeMissingPunchOutModal called');
    const modal = document.getElementById('missingPunchOutModal');
    if (modal) {
        modal.style.display = 'none';
        debugLog('Modal closed');
    }
}

// Word counting function
function countWords(text) {
    debugLog('countWords called with text: "' + text + '"');
    
    if (!text || typeof text !== 'string') {
        debugLog('countWords returning 0 (empty or invalid text)');
        return 0;
    }
    
    // Remove extra spaces and split by whitespace
    const words = text.trim().split(/\s+/);
    debugLog('Words split: ' + JSON.stringify(words));
    
    // Filter out empty entries and entries that contain only special characters
    const filteredWords = words.filter(word => {
        // Keep only if the word contains at least one alphanumeric character
        const isValid = word.length > 0 && /[a-zA-Z0-9]/.test(word);
        debugLog('Word "' + word + '" is valid: ' + isValid);
        return isValid;
    });
    
    debugLog('Filtered words: ' + JSON.stringify(filteredWords) + ', count: ' + filteredWords.length);
    return filteredWords.length;
}

// Function to initialize event listeners for the missing punch out modal
function initMissingPunchOutModal() {
    debugLog('initMissingPunchOutModal called');
    
    // Check if the modal elements exist
    if (!document.getElementById('missingPunchOutModal')) {
        debugLog('Modal not found, will retry in 100ms');
        // If not, wait a bit and try again (in case the DOM is still loading)
        setTimeout(initMissingPunchOutModal, 100);
        return;
    }
    
    debugLog('Modal found, setting up event listeners');
    
    const modal = document.getElementById('missingPunchOutModal');
    const closeBtn = document.querySelector('.missing-punch-out-close');
    const reasonInput = document.getElementById('missingPunchOutReason');
    const workReportInput = document.getElementById('missingPunchOutWorkReport');
    const timeInput = document.getElementById('missingPunchOutTime');
    const confirmCheckbox = document.getElementById('missingPunchOutConfirm');
    const submitButton = document.getElementById('submitMissingPunchOut');
    
    debugLog('Elements for event listeners: ' + 
        'closeBtn=' + !!closeBtn + 
        ', reasonInput=' + !!reasonInput + 
        ', workReportInput=' + !!workReportInput + 
        ', timeInput=' + !!timeInput + 
        ', confirmCheckbox=' + !!confirmCheckbox + 
        ', submitButton=' + !!submitButton);
    
    // Close modal when clicking the close button
    if (closeBtn && !closeBtn.dataset.listenerAdded) {
        closeBtn.addEventListener('click', closeMissingPunchOutModal);
        closeBtn.dataset.listenerAdded = 'true';
        debugLog('Added close button event listener');
    }
    
    // Close modal when clicking outside the modal content
    if (modal && !modal.dataset.listenerAdded) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeMissingPunchOutModal();
            }
        });
        modal.dataset.listenerAdded = 'true';
        debugLog('Added modal click event listener');
    }
    
    // Word counting for reason textarea
    if (reasonInput && !reasonInput.dataset.listenerAdded) {
        reasonInput.addEventListener('input', function() {
            debugLog('Reason input changed');
            const wordCount = countWords(this.value);
            const wordCountElement = document.getElementById('missingPunchOutWordCount');
            if (wordCountElement) {
                wordCountElement.textContent = wordCount;
                debugLog('Updated reason word count: ' + wordCount);
                
                if (wordCount < 15) {
                    wordCountElement.parentElement.classList.add('insufficient');
                } else {
                    wordCountElement.parentElement.classList.remove('insufficient');
                }
            }
            
            updatePunchOutSubmitButton();
        });
        reasonInput.dataset.listenerAdded = 'true';
        debugLog('Added reason input event listener');
    }
    
    // Word counting for work report textarea
    if (workReportInput && !workReportInput.dataset.listenerAdded) {
        workReportInput.addEventListener('input', function() {
            debugLog('Work report input changed');
            const wordCount = countWords(this.value);
            const wordCountElement = document.getElementById('workReportWordCount');
            if (wordCountElement) {
                wordCountElement.textContent = wordCount;
                debugLog('Updated work report word count: ' + wordCount);
                
                if (wordCount < 20) {
                    wordCountElement.parentElement.classList.add('insufficient');
                } else {
                    wordCountElement.parentElement.classList.remove('insufficient');
                }
            }
            
            updatePunchOutSubmitButton();
        });
        workReportInput.dataset.listenerAdded = 'true';
        debugLog('Added work report input event listener');
    }
    
    // Update submit button when time changes
    if (timeInput && !timeInput.dataset.listenerAdded) {
        timeInput.addEventListener('input', updatePunchOutSubmitButton);
        timeInput.dataset.listenerAdded = 'true';
        debugLog('Added time input event listener');
    }
    
    // Update submit button when confirmation checkbox changes
    if (confirmCheckbox && !confirmCheckbox.dataset.listenerAdded) {
        confirmCheckbox.addEventListener('change', updatePunchOutSubmitButton);
        confirmCheckbox.dataset.listenerAdded = 'true';
        debugLog('Added confirm checkbox event listener');
    }
    
    // Submit button click handler
    if (submitButton && !submitButton.dataset.listenerAdded) {
        submitButton.addEventListener('click', function() {
            debugLog('Submit button clicked');
            const date = document.getElementById('missingPunchOutDate').value;
            const time = document.getElementById('missingPunchOutTime').value;
            const reason = document.getElementById('missingPunchOutReason').value;
            const workReport = document.getElementById('missingPunchOutWorkReport').value;
            const reasonWordCount = countWords(reason);
            const workReportWordCount = countWords(workReport);
            
            debugLog('Validation data: date=' + date + 
                ', time=' + time + 
                ', reasonWordCount=' + reasonWordCount + 
                ', workReportWordCount=' + workReportWordCount);
            
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
        submitButton.dataset.listenerAdded = 'true';
        debugLog('Added submit button event listener');
    }
}

// Function to update the submit button state
function updatePunchOutSubmitButton() {
    debugLog('updatePunchOutSubmitButton called');
    
    const timeInput = document.getElementById('missingPunchOutTime');
    const reasonInput = document.getElementById('missingPunchOutReason');
    const workReportInput = document.getElementById('missingPunchOutWorkReport');
    const confirmCheckbox = document.getElementById('missingPunchOutConfirm');
    const submitButton = document.getElementById('submitMissingPunchOut');
    
    if (!timeInput || !reasonInput || !workReportInput || !confirmCheckbox || !submitButton) {
        debugLog('Some elements not found, skipping update');
        return;
    }
    
    const reasonWordCount = countWords(reasonInput.value);
    const workReportWordCount = countWords(workReportInput.value);
    
    debugLog('Word counts - reason: ' + reasonWordCount + ', work report: ' + workReportWordCount);
    
    // Enable submit button only if all conditions are met
    const isTimeValid = timeInput.value !== '';
    const isReasonValid = reasonWordCount >= 15;
    const isWorkReportValid = workReportWordCount >= 20;
    const isConfirmed = confirmCheckbox.checked;
    
    debugLog('Validation results - time: ' + isTimeValid + 
        ', reason: ' + isReasonValid + 
        ', work report: ' + isWorkReportValid + 
        ', confirmed: ' + isConfirmed);
    
    const shouldBeEnabled = isTimeValid && isReasonValid && isWorkReportValid && isConfirmed;
    submitButton.disabled = !shouldBeEnabled;
    
    debugLog('Submit button disabled: ' + submitButton.disabled);
}

// Initialize the modal when the script loads
debugLog('Script loaded, initializing modal');
initMissingPunchOutModal();
</script>