<?php
/**
 * Missing Punch In Modal
 * This modal is displayed when a user clicks on a notification for a missing punch-in
 */
?>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Missing Punch In Modal -->
<div class="missing-punch-modal" id="missingPunchModal">
    <div class="missing-punch-modal-content">
        <div class="missing-punch-modal-header">
            <h3><i class="fas fa-clock"></i> Missing Punch In</h3>
            <span class="missing-punch-close">&times;</span>
        </div>
        <div class="missing-punch-modal-body">
            <div class="missing-punch-info">
                <p><i class="fas fa-info-circle"></i> Please provide the time and reason for your missing punch-in:</p>
                <div class="missing-punch-date">
                    <label for="missingPunchDate"><i class="fas fa-calendar-alt"></i> Date:</label>
                    <input type="text" id="missingPunchDate" readonly>
                </div>
                <div class="missing-punch-time">
                    <label for="missingPunchTime"><i class="fas fa-business-time"></i> Time:</label>
                    <input type="time" id="missingPunchTime" required>
                </div>
                <div class="missing-punch-reason">
                    <label for="missingPunchReason"><i class="fas fa-comment-alt"></i> Reason (max 15 words):</label>
                    <textarea id="missingPunchReason" maxlength="100" placeholder="Enter reason for missing punch-in" required></textarea>
                    <div class="word-counter">
                        <span id="missingPunchWordCount">0</span>/15 words
                    </div>
                </div>
                <div class="missing-punch-confirmation">
                    <label>
                        <input type="checkbox" id="missingPunchConfirm" required>
                        <i class="fas fa-check-circle"></i> I confirm that the information provided is accurate
                    </label>
                </div>
            </div>
        </div>
        <div class="missing-punch-modal-footer">
            <button id="submitMissingPunch" class="submit-missing-punch-btn" disabled>
                <i class="fas fa-paper-plane"></i> Submit Missing Punch
            </button>
        </div>
    </div>
</div>

<style>
.missing-punch-modal {
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

.missing-punch-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: 18px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    position: relative;
}

.missing-punch-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #eee;
    background: linear-gradient(120deg, #e8f5e9, #f5f5f5);
    color: #333;
    border-radius: 18px 18px 0 0;
}

.missing-punch-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.missing-punch-modal-header h3 i {
    margin-right: 10px;
    color: #4caf50;
}

.missing-punch-close {
    font-size: 24px;
    cursor: pointer;
    color: #999;
    transition: color 0.2s;
    font-weight: normal;
}

.missing-punch-close:hover {
    color: #666;
}

.missing-punch-modal-body {
    padding: 20px 25px;
    background-color: #fafafa;
}

.missing-punch-info p {
    margin-top: 0;
    color: #555;
    font-size: 15px;
    line-height: 1.5;
    margin-bottom: 20px;
    background-color: #e8f5e9;
    padding: 12px 15px;
    border-radius: 8px;
    border-left: 4px solid #4caf50;
}

.missing-punch-info p i {
    margin-right: 8px;
    color: #4caf50;
}

.missing-punch-date,
.missing-punch-time,
.missing-punch-reason {
    margin-bottom: 20px;
}

.missing-punch-date label,
.missing-punch-time label,
.missing-punch-reason label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #444;
    font-size: 14px;
}

.missing-punch-date label i,
.missing-punch-time label i,
.missing-punch-reason label i {
    margin-right: 8px;
    color: #4caf50;
}

.missing-punch-date input,
.missing-punch-time input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
    box-sizing: border-box;
    background-color: #ffffff;
    transition: all 0.3s ease;
}

.missing-punch-date input[readonly] {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.missing-punch-date input:focus,
.missing-punch-time input:focus {
    border-color: #4caf50;
    outline: none;
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.missing-punch-reason textarea {
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

.missing-punch-reason textarea:focus {
    border-color: #4caf50;
    outline: none;
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.word-counter {
    text-align: right;
    font-size: 12px;
    color: #888;
    margin-top: 5px;
}

.word-counter.exceeded {
    color: #e53935;
}

.missing-punch-confirmation {
    margin-top: 15px;
}

.missing-punch-confirmation label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-weight: normal;
    color: #444;
    cursor: pointer;
    font-size: 14px;
    background-color: #e1f5fe;
    padding: 12px 15px;
    border-radius: 8px;
    border-left: 4px solid #00bcd4;
}

.missing-punch-confirmation label i {
    margin-top: 3px;
    color: #00bcd4;
}

.missing-punch-confirmation input[type="checkbox"] {
    margin-top: 3px;
    transform: scale(1.1);
}

.missing-punch-modal-footer {
    padding: 20px 25px;
    text-align: center;
    border-top: 1px solid #eee;
    background: linear-gradient(120deg, #f5f5f5, #e8f5e9);
    border-radius: 0 0 18px 18px;
}

.submit-missing-punch-btn {
    padding: 12px 25px;
    background: linear-gradient(120deg, #4caf50, #43a047);
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
    box-shadow: 0 2px 5px rgba(76, 175, 80, 0.2);
}

.submit-missing-punch-btn:hover:not([disabled]) {
    background: linear-gradient(120deg, #43a047, #388e3c);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
}

.submit-missing-punch-btn[disabled] {
    background: linear-gradient(120deg, #bdbdbd, #9e9e9e);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.submit-missing-punch-btn i {
    font-size: 14px;
}

@media (max-width: 768px) {
    .missing-punch-modal-content {
        width: 95%;
        margin: 5% auto;
        border-radius: 15px;
    }
    
    .missing-punch-modal-header {
        padding: 15px 20px;
        border-radius: 15px 15px 0 0;
    }
    
    .missing-punch-modal-header h3 {
        font-size: 16px;
    }
    
    .missing-punch-close {
        font-size: 22px;
    }
    
    .missing-punch-modal-body {
        padding: 15px 20px;
    }
    
    .missing-punch-info p {
        font-size: 14px;
    }
    
    .submit-missing-punch-btn {
        width: 100%;
        padding: 12px;
        font-size: 14px;
    }
    
    .missing-punch-modal-footer {
        padding: 15px 20px;
        border-radius: 0 0 15px 15px;
    }
}
</style>

<script>
// Function to open the missing punch modal with a specific date
function openMissingPunchModal(date) {
    const modal = document.getElementById('missingPunchModal');
    const dateInput = document.getElementById('missingPunchDate');
    const timeInput = document.getElementById('missingPunchTime');
    const reasonInput = document.getElementById('missingPunchReason');
    const wordCount = document.getElementById('missingPunchWordCount');
    const confirmCheckbox = document.getElementById('missingPunchConfirm');
    const submitButton = document.getElementById('submitMissingPunch');
    
    // Set the date in the modal
    dateInput.value = date;
    
    // Clear previous values
    timeInput.value = '';
    reasonInput.value = '';
    wordCount.textContent = '0';
    confirmCheckbox.checked = false;
    submitButton.disabled = true;
    
    // Show the modal
    modal.style.display = 'block';
}

// Close modal function
function closeMissingPunchModal() {
    const modal = document.getElementById('missingPunchModal');
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

// Function to initialize event listeners for the missing punch modal
function initMissingPunchModal() {
    // Check if the modal elements exist
    if (!document.getElementById('missingPunchModal')) {
        // If not, wait a bit and try again (in case the DOM is still loading)
        setTimeout(initMissingPunchModal, 100);
        return;
    }
    
    const modal = document.getElementById('missingPunchModal');
    const closeBtn = document.querySelector('.missing-punch-close');
    const reasonInput = document.getElementById('missingPunchReason');
    const timeInput = document.getElementById('missingPunchTime');
    const confirmCheckbox = document.getElementById('missingPunchConfirm');
    const submitButton = document.getElementById('submitMissingPunch');
    
    // Close modal when clicking the close button
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMissingPunchModal);
    }
    
    // Close modal when clicking outside the modal content
    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeMissingPunchModal();
            }
        });
    }
    
    // Word counting for reason textarea
    if (reasonInput) {
        reasonInput.addEventListener('input', function() {
            const wordCount = countWords(this.value);
            const wordCountElement = document.getElementById('missingPunchWordCount');
            if (wordCountElement) {
                wordCountElement.textContent = wordCount;
                
                if (wordCount > 15) {
                    wordCountElement.parentElement.classList.add('exceeded');
                } else {
                    wordCountElement.parentElement.classList.remove('exceeded');
                }
            }
            
            updateSubmitButton();
        });
    }
    
    // Update submit button when time changes
    if (timeInput) {
        timeInput.addEventListener('input', updateSubmitButton);
    }
    
    // Update submit button when confirmation checkbox changes
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', updateSubmitButton);
    }
    
    // Submit button click handler
    if (submitButton) {
        submitButton.addEventListener('click', function() {
            const date = document.getElementById('missingPunchDate').value;
            const time = document.getElementById('missingPunchTime').value;
            const reason = document.getElementById('missingPunchReason').value;
            const wordCount = countWords(reason);
            
            // Validate inputs
            if (!time) {
                alert('Please enter the punch-in time.');
                return;
            }
            
            if (wordCount === 0) {
                alert('Please provide a reason for the missing punch-in.');
                return;
            }
            
            if (wordCount > 15) {
                alert('Please limit your reason to 15 words.');
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
            formData.append('confirmed', confirmCheckbox.checked);
            
            fetch('ajax_handlers/submit_missing_punch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Missing punch-in submitted successfully!');
                    closeMissingPunchModal();
                    
                    // Optionally refresh the notification count
                    // This would require calling the notification update function
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the missing punch-in. Please try again.');
            });
        });
    }
}

// Function to update the submit button state
function updateSubmitButton() {
    const timeInput = document.getElementById('missingPunchTime');
    const reasonInput = document.getElementById('missingPunchReason');
    const confirmCheckbox = document.getElementById('missingPunchConfirm');
    const submitButton = document.getElementById('submitMissingPunch');
    const wordCount = countWords(reasonInput.value);
    
    if (!timeInput || !reasonInput || !confirmCheckbox || !submitButton) {
        return;
    }
    
    // Enable submit button only if all conditions are met
    const isTimeValid = timeInput.value !== '';
    const isReasonValid = wordCount > 0 && wordCount <= 15;
    const isConfirmed = confirmCheckbox.checked;
    
    submitButton.disabled = !(isTimeValid && isReasonValid && isConfirmed);
}

// Initialize the modal when the script loads
initMissingPunchModal();
</script>