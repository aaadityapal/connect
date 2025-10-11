<!-- Custom Instant Modal -->
<div class="work-report-modal" id="instantModal" style="display: none; z-index: 3000;">
    <div class="work-report-content" style="max-width: 600px;">
        <div class="work-report-header">
            <h3><i class="fas fa-exclamation-circle"></i> Missing Punch Records</h3>
            <button class="close-modal" id="closeInstantModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="work-report-body">
            <div id="missingPunchesContent">
                <div class="loading-content">
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #4a6cf7; margin-bottom: 15px;"></i>
                        <h4 style="margin-bottom: 15px;">Loading Missing Punch Records</h4>
                        <p>Please wait while we fetch your missing punch information...</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="work-report-footer">
            <div class="modal-suppression-checkbox" style="text-align: left; margin-bottom: 15px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" id="suppressModalCheckbox" style="margin-right: 10px;">
                    <span>Do not show this for 24 hours</span>
                </label>
            </div>
            <button class="submit-btn" id="acknowledgeBtn">
                <i class="fas fa-check"></i> Acknowledge
            </button>
        </div>
    </div>
</div>

<style>
/* Additional styles for missing punches display */
.missing-punch-item {
    display: flex;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid #4a6cf7;
    transition: all 0.3s ease;
    position: relative;
}

.missing-punch-item:hover {
    background: #f0f0f0;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.missing-punch-item {
    cursor: pointer;
}

.missing-punch-item.punch-out {
    border-left-color: #10b981;
}

.missing-punch-item i {
    font-size: 20px;
    margin-right: 15px;
    width: 24px;
    text-align: center;
}

.missing-punch-item.punch-in i {
    color: #4a6cf7;
}

.missing-punch-item.punch-out i {
    color: #10b981;
}

.missing-punch-details {
    flex: 1;
}

.missing-punch-date {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 4px;
}

.missing-punch-type {
    font-size: 14px;
    color: #7f8c8d;
}

.missing-punch-status {
    font-weight: 600;
    color: #e74c3c;
    font-size: 14px;
}

.missing-punch-timer {
    font-size: 13px;
    color: #e74c3c;
    font-weight: 600;
    background: #ffeaea;
    padding: 5px 10px;
    border-radius: 20px;
    margin-top: 8px;
    display: inline-block;
}

.missing-punch-timer.warning {
    color: #f39c12;
    background: #fff9e6;
}

.missing-punch-timer.safe {
    color: #27ae60;
    background: #e8f7ef;
}

.no-missing-punches {
    text-align: center;
    padding: 30px 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.no-missing-punches i {
    font-size: 48px;
    color: #10b981;
    margin-bottom: 15px;
}

.error-message {
    text-align: center;
    padding: 30px 20px;
    background: #fff3f3;
    border-radius: 8px;
    border: 1px solid #ffcfcf;
    color: #c0392b;
}

.error-message i {
    font-size: 48px;
    color: #e74c3c;
    margin-bottom: 15px;
}

.loading-content {
    text-align: center;
    padding: 30px 20px;
}

.loading-content i {
    font-size: 48px;
    color: #4a6cf7;
    margin-bottom: 15px;
}

.alert-box {
    margin-bottom: 20px;
    padding: 15px;
    background: #fff3cd;
    border-radius: 8px;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.alert-box i {
    margin-right: 8px;
}

.scrollable-content {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 10px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.action-btn {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}

.action-btn.punch-in {
    background: #4a6cf7;
    color: white;
}

.action-btn.punch-out {
    background: #10b981;
    color: white;
}

.action-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
</style>

<script>
// Function to open the appropriate missing punch modal based on type
// This needs to be in global scope to be callable from HTML onclick attributes
function openMissingPunchModalByType(date, type) {
    // Hide the instant modal first
    const instantModal = document.getElementById('instantModal');
    if (instantModal) {
        instantModal.classList.remove('active');
        setTimeout(function() { 
            instantModal.style.display = 'none'; 
        }, 300);
    }
    
    // Wait a bit for the modal to close before opening the new one
    setTimeout(function() {
        // Make sure the modal functions are available
        if (typeof openMissingPunchModal !== 'function' || typeof openMissingPunchOutModal !== 'function') {
            // Try to initialize the modals
            if (typeof initMissingPunchModal === 'function') {
                console.log('Initializing missing punch in modal');
                initMissingPunchModal();
            }
            if (typeof initMissingPunchOutModal === 'function') {
                console.log('Initializing missing punch out modal');
                initMissingPunchOutModal();
            }
            
            // Wait a bit more for initialization
            setTimeout(function() {
                if (type === 'punch_in') {
                    if (typeof openMissingPunchModal === 'function') {
                        openMissingPunchModal(date);
                    } else {
                        console.error('Unable to open missing punch in modal - function not available');
                        alert('Unable to open missing punch in modal. Please try again.');
                    }
                } else if (type === 'punch_out') {
                    if (typeof openMissingPunchOutModal === 'function') {
                        openMissingPunchOutModal(date);
                    } else {
                        console.error('Unable to open missing punch out modal - function not available');
                        alert('Unable to open missing punch out modal. Please try again.');
                    }
                }
            }, 200);
        } else {
            if (type === 'punch_in') {
                // Open missing punch in modal
                openMissingPunchModal(date);
            } else if (type === 'punch_out') {
                // Open missing punch out modal
                openMissingPunchOutModal(date);
            }
        }
    }, 300);
}

// Show instant modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if the modal element exists before trying to use it
    const instantModal = document.getElementById('instantModal');
    if (!instantModal) {
        console.error('Instant modal not found');
        return;
    }
    
    const closeInstantModal = document.getElementById('closeInstantModal');
    const acknowledgeBtn = document.getElementById('acknowledgeBtn');
    const missingPunchesContent = document.getElementById('missingPunchesContent');
    const suppressModalCheckbox = document.getElementById('suppressModalCheckbox');

    // Function to show the instant modal
    function showInstantModal() {
        instantModal.style.display = 'flex';
        setTimeout(function() { 
            instantModal.classList.add('active'); 
        }, 10);
        
        // Fetch missing punches data
        fetchMissingPunches();
    }

    // Function to hide the instant modal
    function hideInstantModal() {
        // Check if the suppression checkbox is checked
        if (suppressModalCheckbox && suppressModalCheckbox.checked) {
            // Set modal suppression for 24 hours
            fetch('ajax_handlers/set_modal_suppression.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Modal suppression set for 24 hours');
                } else {
                    console.error('Error setting modal suppression:', data.message);
                }
            })
            .catch(error => {
                console.error('Error setting modal suppression:', error);
            });
        }
        
        instantModal.classList.remove('active');
        setTimeout(function() { 
            instantModal.style.display = 'none'; 
        }, 300);
    }
    
    // Function to check if modal should be shown
    function checkModalSuppression() {
        fetch('ajax_handlers/check_modal_suppression.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.suppressed) {
                        console.log('Modal is suppressed until:', data.suppressed_until);
                        // Don't show the modal
                        return;
                    } else {
                        // Check if there are missing punches before showing the modal
                        checkMissingPunchesAndShowModal();
                    }
                } else {
                    console.error('Error checking modal suppression:', data.message);
                    // Check if there are missing punches before showing the modal
                    checkMissingPunchesAndShowModal();
                }
            })
            .catch(error => {
                console.error('Error checking modal suppression:', error);
                // Check if there are missing punches before showing the modal
                checkMissingPunchesAndShowModal();
            });
    }
    
    // New function to check for missing punches before showing modal
    function checkMissingPunchesAndShowModal() {
        // Fetch both missing punches and submitted punches
        Promise.all([
            fetch('ajax_handlers/get_missing_punches.php').then(response => response.json()),
            fetch('ajax_handlers/get_submitted_punches.php').then(response => response.json())
        ])
        .then(([missingData, submittedData]) => {
            if (missingData.success && submittedData.success) {
                // Filter out submitted punches from missing punches
                const filteredMissingPunches = filterSubmittedPunches(missingData.data, submittedData.data);
                // Filter out today's punches
                const currentDate = new Date().toISOString().split('T')[0];
                const nonTodayPunches = filteredMissingPunches.filter(punch => punch.date !== currentDate);
                
                if (nonTodayPunches.length > 0) {
                    // Show the modal and display the data
                    showInstantModalWithData(nonTodayPunches);
                }
                // If no missing punches, don't show modal
            } else if (missingData.success) {
                // Filter out today's punches
                const currentDate = new Date().toISOString().split('T')[0];
                const nonTodayPunches = missingData.data.filter(punch => punch.date !== currentDate);
                
                if (nonTodayPunches.length > 0) {
                    // Show the modal and display the data
                    showInstantModalWithData(nonTodayPunches);
                }
                // If no missing punches, don't show modal
            } else {
                // In case of error, we might still want to show the modal to display the error
                showInstantModal();
            }
        })
        .catch(error => {
            console.error('Error checking missing punches:', error);
            // In case of error, we might still want to show the modal to display the error
            showInstantModal();
        });
    }
    
    // Modified function to show modal with pre-fetched data
    function showInstantModalWithData(missingPunches) {
        instantModal.style.display = 'flex';
        setTimeout(function() { 
            instantModal.classList.add('active'); 
        }, 10);
        
        // Display the pre-fetched data
        displayMissingPunches(missingPunches);
    }

    // Function to fetch missing punches data
    function fetchMissingPunches() {
        // Fetch both missing punches and submitted punches
        Promise.all([
            fetch('ajax_handlers/get_missing_punches.php').then(response => response.json()),
            fetch('ajax_handlers/get_submitted_punches.php').then(response => response.json())
        ])
        .then(([missingData, submittedData]) => {
            if (missingData.success && submittedData.success) {
                // Filter out submitted punches from missing punches
                const filteredMissingPunches = filterSubmittedPunches(missingData.data, submittedData.data);
                // Filter out today's punches
                const currentDate = new Date().toISOString().split('T')[0];
                const nonTodayPunches = filteredMissingPunches.filter(punch => punch.date !== currentDate);
                
                if (nonTodayPunches.length > 0) {
                    displayMissingPunches(nonTodayPunches);
                } else {
                    displayNoMissingPunches();
                }
            } else if (missingData.success) {
                // Filter out today's punches
                const currentDate = new Date().toISOString().split('T')[0];
                const nonTodayPunches = missingData.data.filter(punch => punch.date !== currentDate);
                
                if (nonTodayPunches.length > 0) {
                    displayMissingPunches(nonTodayPunches);
                } else {
                    displayNoMissingPunches();
                }
            } else {
                displayError('Failed to load missing punches data: ' + (missingData.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error fetching missing punches:', error);
            displayError('Error loading missing punches data. Please try again later.');
        });
    }

    // Function to filter out submitted punches
    function filterSubmittedPunches(missingPunches, submittedPunches) {
        // Create a set of submitted punch keys for quick lookup
        const submittedKeys = new Set();
        for (const key in submittedPunches) {
            submittedKeys.add(key);
        }
        
        // Filter out missing punches that have been submitted
        return missingPunches.filter(punch => {
            // Create a key to match against submitted punches
            const key = punch.date + '_' + punch.type;
            return !submittedKeys.has(key);
        });
    }

    // Function to display missing punches
    function displayMissingPunches(missingPunches) {
        // Sort punches by date (newest first - descending order)
        missingPunches.sort((a, b) => new Date(b.date) - new Date(a.date));
        
        // Group punches by date
        const groupedPunches = {};
        missingPunches.forEach(punch => {
            if (!groupedPunches[punch.date]) {
                groupedPunches[punch.date] = [];
            }
            groupedPunches[punch.date].push(punch);
        });

        // Create HTML for display
        let html = `
            <div class="alert-box">
                <p style="margin: 0; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> 
                    You have missing punch records from the last 15 days. You can submit these records within 15 days from the missed date to avoid being marked absent.
                </p>
            </div>
            <div class="scrollable-content">
        `;

        // Display each date with its missing punches
        for (const [date, punches] of Object.entries(groupedPunches)) {
            const formattedDate = new Date(date).toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            html += `<div style="margin-bottom: 20px;">`;
            html += `<h4 style="margin: 0 0 10px 0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 8px;">${formattedDate}</h4>`;
            
            // Sort punches for this date by type (punch_in first, then punch_out)
            punches.sort((a, b) => {
                if (a.type === b.type) return 0;
                return a.type === 'punch_in' ? -1 : 1;
            });
            
            punches.forEach(punch => {
                const punchType = punch.type === 'punch_in' ? 'Punch In' : 'Punch Out';
                const icon = punch.type === 'punch_in' ? 'fa-sign-in-alt' : 'fa-sign-out-alt';
                const cssClass = punch.type === 'punch_in' ? 'punch-in' : 'punch-out';
                
                // Calculate deadline (15 days from the missing punch date)
                // We need to set the deadline to the end of the 15th day, not the beginning
                const punchDate = new Date(punch.date);
                const deadline = new Date(punchDate);
                deadline.setDate(deadline.getDate() + 15);
                // Set to end of the day (23:59:59) to ensure the full day is included
                deadline.setHours(23, 59, 59, 999);
                
                // Calculate time remaining
                const now = new Date();
                const timeDiff = deadline.getTime() - now.getTime();
                const daysRemaining = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                const hoursRemaining = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutesRemaining = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                
                // Skip displaying this record if it's past the 15-day window
                if (timeDiff < 0) {
                    return; // Skip this punch
                }
                
                // Determine timer class based on time remaining
                let timerClass = 'missing-punch-timer';
                if (daysRemaining < 3) {
                    timerClass += ' warning'; // Orange for urgent (less than 3 days)
                } else {
                    timerClass += ' safe'; // Green for safe
                }
                
                // Format timer text
                let timerText;
                if (daysRemaining > 0) {
                    timerText = `Submit within ${daysRemaining}d ${hoursRemaining}h to avoid being marked absent`;
                } else {
                    timerText = `Submit within ${hoursRemaining}h ${minutesRemaining}m to avoid being marked absent`;
                }
                
                html += `
                    <div class="missing-punch-item ${cssClass}" data-date="${punch.date}" data-type="${punch.type}" style="cursor: pointer;" onclick="openMissingPunchModalByType('${punch.date}', '${punch.type}')">
                        <i class="fas ${icon}"></i>
                        <div class="missing-punch-details">
                            <div class="missing-punch-date">${punchType}</div>
                            <div class="missing-punch-type">Attendance Record ID: ${punch.id || 'N/A'}</div>
                            <div class="${timerClass}">${timerText}</div>
                        </div>
                        <div class="missing-punch-status">Missing</div>
                    </div>
                `;
            });

            html += `</div>`;
        }

        html += `</div>`;

        missingPunchesContent.innerHTML = html;
    }

    // Function to display when no missing punches
    function displayNoMissingPunches() {
        // Don't show the modal when there are no missing punches
        hideInstantModal();
    }

    // Function to display error message
    function displayError(message) {
        missingPunchesContent.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <h4 style="margin-bottom: 15px; color: #c0392b;">Error Loading Data</h4>
                <p style="margin: 0; font-size: 16px;">${message}</p>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <p style="margin: 0; font-size: 14px; color: #7f8c8d;">
                        <i class="fas fa-info-circle"></i> 
                        Please try refreshing the page or contact support if the issue persists.
                    </p>
                </div>
            </div>
        `;
    }

    // Event listeners for closing the modal
    if (closeInstantModal) {
        closeInstantModal.addEventListener('click', hideInstantModal);
    }
    
    if (acknowledgeBtn) {
        acknowledgeBtn.addEventListener('click', hideInstantModal);
    }

    // Show the modal instantly when page loads
    // Add a small delay to ensure all DOM elements are loaded
    setTimeout(function() {
        // Debug log to check if modals are loaded
        console.log('Checking for modal elements:');
        console.log('Missing punch in modal:', document.getElementById('missingPunchModal'));
        console.log('Missing punch out modal:', document.getElementById('missingPunchOutModal'));
        checkModalSuppression();
    }, 100);
});
</script>

<!-- Include the missing punch modals -->
<?php 
if (file_exists(__DIR__ . '/modals/missing_punch_modal.php')) {
    include __DIR__ . '/modals/missing_punch_modal.php';
} else if (file_exists('modals/missing_punch_modal.php')) {
    include 'modals/missing_punch_modal.php';
} else {
    echo "<!-- Missing punch in modal not found -->";
}

if (file_exists(__DIR__ . '/modals/missing_punch_out_modal.php')) {
    include __DIR__ . '/modals/missing_punch_out_modal.php';
} else if (file_exists('modals/missing_punch_out_modal.php')) {
    include 'modals/missing_punch_out_modal.php';
} else {
    echo "<!-- Missing punch out modal not found -->";
}
?>
