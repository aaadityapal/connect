// ===================================
// Global Variables
// ===================================
let alternativeNumberCount = 0;
let siteVisitCount = 0;
const form = document.getElementById('projectIntakeForm');
const progressFill = document.getElementById('progressFill');
const progressCircleFill = document.getElementById('progressCircleFill');
const progressPercentage = document.getElementById('progressPercentage');
const addAlternativeNumberBtn = document.getElementById('addAlternativeNumber');
const alternativeNumbersContainer = document.getElementById('alternativeNumbersContainer');
const addSiteVisitBtn = document.getElementById('addSiteVisit');
const siteVisitsContainer = document.getElementById('siteVisitsContainer');
const successMessage = document.getElementById('successMessage');
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const sidePanel = document.getElementById('sidePanel');

// ===================================
// Reference Number Generation
// ===================================
function generateReferenceNumber() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0'); // Get month (01-12)
    
    // Get existing projects from localStorage
    let projects = [];
    try {
        const localData = localStorage.getItem('projectIntakeFormData');
        if (localData) {
            const parsed = JSON.parse(localData);
            projects = Array.isArray(parsed) ? parsed : [];
        }
    } catch (e) {
        console.error('Error parsing localStorage data:', e);
        projects = [];
    }
    
    // Ensure projects is an array
    if (!Array.isArray(projects)) {
        projects = [];
    }
    
    // Count projects created in current month/year
    const currentMonthProjects = projects.filter(p => {
        if (p && p.referenceNumber) {
            const parts = p.referenceNumber.split('/');
            return parts[1] === String(year) && parts[2] === month;
        }
        return false;
    });
    
    const serialNumber = String(currentMonthProjects.length + 1).padStart(3, '0');
    const referenceNumber = `AH/${year}/${month}/${serialNumber}`;
    
    return referenceNumber;
}

function autoGenerateReferenceNumber() {
    const referenceNumberField = document.getElementById('referenceNumber');
    if (referenceNumberField) {
        referenceNumberField.value = generateReferenceNumber();
        referenceNumberField.focus();
    }
}

// ===================================
// Form Progress Tracking
// ===================================
function updateProgress() {
    const formElements = form.querySelectorAll('input[required], select[required], textarea[required]');
    let filledCount = 0;

    formElements.forEach(element => {
        if (element.value.trim() !== '') {
            filledCount++;
        }
    });

    const progress = (filledCount / formElements.length) * 100;
    progressFill.style.width = `${progress}%`;
}

// ===================================
// Site Visit Auto Calculation
// ===================================
function calculateSiteVisitRemains() {
    const totalVisits = parseInt(document.getElementById('totalSiteVisits').value) || 0;
    const completedVisits = parseInt(document.getElementById('siteVisitCompleted').value) || 0;
    const remainingVisits = Math.max(0, totalVisits - completedVisits);
    
    document.getElementById('siteVisitRemains').value = remainingVisits;
    updateProgress();
}

// Add event listeners to all form inputs for progress tracking
document.addEventListener('DOMContentLoaded', () => {
    // Generate and set reference number
    const referenceNumberField = document.getElementById('referenceNumber');
    if (referenceNumberField) {
        const newRefNumber = generateReferenceNumber();
        referenceNumberField.value = newRefNumber;
        console.log('Generated Reference Number:', newRefNumber);
    }

    // Add listeners for site visit calculation
    const totalVisitsField = document.getElementById('totalSiteVisits');
    const completedVisitsField = document.getElementById('siteVisitCompleted');
    
    if (totalVisitsField) {
        totalVisitsField.addEventListener('input', calculateSiteVisitRemains);
        totalVisitsField.addEventListener('change', calculateSiteVisitRemains);
    }
    
    if (completedVisitsField) {
        completedVisitsField.addEventListener('input', calculateSiteVisitRemains);
        completedVisitsField.addEventListener('change', calculateSiteVisitRemains);
    }

    const formInputs = form.querySelectorAll('input, select, textarea');
    formInputs.forEach(input => {
        input.addEventListener('input', updateProgress);
        input.addEventListener('change', updateProgress);
    });

    // Initial progress update
    updateProgress();
});

// ===================================
// Alternative Number Management
// ===================================
function addAlternativeNumber() {
    alternativeNumberCount++;

    const numberItem = document.createElement('div');
    numberItem.className = 'alternative-number-item';
    numberItem.id = `altNumber-${alternativeNumberCount}`;

    numberItem.innerHTML = `
        <div class="form-group">
            <label for="altName${alternativeNumberCount}">
                Contact Name
            </label>
            <input 
                type="text" 
                id="altName${alternativeNumberCount}" 
                name="alternativeContacts[${alternativeNumberCount}][name]" 
                placeholder="e.g., John Doe"
            >
        </div>
        <div class="form-group">
            <label for="altNumber${alternativeNumberCount}">
                Contact Number
            </label>
            <input 
                type="tel" 
                id="altNumber${alternativeNumberCount}" 
                name="alternativeContacts[${alternativeNumberCount}][number]" 
                placeholder="+91 98765 43210"
            >
        </div>
        <button type="button" class="btn-remove" onclick="removeAlternativeNumber(${alternativeNumberCount})">
            <i class="fas fa-trash-alt"></i>
            Remove
        </button>
    `;

    alternativeNumbersContainer.appendChild(numberItem);

    // Add event listeners for progress tracking
    const inputs = numberItem.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('input', updateProgress);
    });

    // Apply phone formatting to the number input
    const phoneInput = numberItem.querySelector(`#altNumber${alternativeNumberCount}`);
    if (phoneInput) {
        formatPhoneNumber(phoneInput);
    }

    // Smooth scroll to the new input
    setTimeout(() => {
        const nameInput = numberItem.querySelector(`#altName${alternativeNumberCount}`);
        nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        nameInput.focus();
    }, 100);
}

function removeAlternativeNumber(id) {
    const numberItem = document.getElementById(`altNumber-${id}`);

    // Add fade out animation
    numberItem.style.animation = 'fadeOut 0.3s ease-out';

    setTimeout(() => {
        numberItem.remove();
        updateProgress();
        renumberAlternativeNumbers();
    }, 300);
}

// Renumber alternative numbers after removal
function renumberAlternativeNumbers() {
    const items = alternativeNumbersContainer.querySelectorAll('.alternative-number-item');
    items.forEach((item, index) => {
        const number = index + 1;

        // Update input names to reflect new numbering
        const nameInput = item.querySelector('input[type="text"]');
        const phoneInput = item.querySelector('input[type="tel"]');

        if (nameInput) {
            nameInput.name = `alternativeContacts[${number}][name]`;
        }
        if (phoneInput) {
            phoneInput.name = `alternativeContacts[${number}][number]`;
        }
    });
}

// Add keyframe for fade out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(-20px);
        }
    }
`;
document.head.appendChild(style);

// ===================================
// Site Visit Management
// ===================================
function addSiteVisit() {
    siteVisitCount++;

    const visitItem = document.createElement('div');
    visitItem.className = 'site-visit-item';
    visitItem.id = `siteVisit-${siteVisitCount}`;

    visitItem.innerHTML = `
        <div class="site-visit-card">
            <div class="site-visit-header">
                <div class="site-visit-number">
                    <span>Visit #${siteVisitCount}</span>
                </div>
                <div class="visit-status-toggle">
                    <input 
                        type="checkbox" 
                        id="visitCompleted${siteVisitCount}" 
                        name="siteVisits[${siteVisitCount}][completed]" 
                        class="visit-completed-checkbox"
                        onchange="updateSiteVisitStats()"
                    >
                    <label for="visitCompleted${siteVisitCount}">
                        <i class="fas fa-check"></i> Completed
                    </label>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="visitDate${siteVisitCount}">
                        Visit Date <span class="required">*</span>
                    </label>
                    <input 
                        type="date" 
                        id="visitDate${siteVisitCount}" 
                        name="siteVisits[${siteVisitCount}][date]" 
                        required
                    >
                </div>
            </div>

            <div class="form-group full-width">
                <label>
                    Participants <span class="required">*</span>
                </label>
                <div id="participantsContainer${siteVisitCount}" class="participants-container">
                    <!-- Participant entries will be added here dynamically -->
                </div>
                <button type="button" class="btn-add-participant" onclick="addParticipant(${siteVisitCount})">
                    <i class="fas fa-plus-circle"></i>
                    Add Participant
                </button>
            </div>

            <div class="form-group full-width">
                <label for="visitRemarks${siteVisitCount}">
                    Remarks (Optional)
                </label>
                <textarea 
                    id="visitRemarks${siteVisitCount}" 
                    name="siteVisits[${siteVisitCount}][remarks]" 
                    rows="3"
                    placeholder="Add any observations, issues, or notes from this site visit..."
                ></textarea>
            </div>

            <button type="button" class="btn-remove" onclick="removeSiteVisit(${siteVisitCount})">
                <i class="fas fa-trash-alt"></i>
                Remove Visit
            </button>
        </div>
    `;

    siteVisitsContainer.appendChild(visitItem);

    // Add event listeners for progress tracking
    const inputs = visitItem.querySelectorAll('input[type="text"], input[type="date"], textarea');
    inputs.forEach(input => {
        input.addEventListener('input', updateProgress);
        input.addEventListener('change', updateProgress);
    });

    // Smooth scroll to the new input
    setTimeout(() => {
        const dateInput = visitItem.querySelector(`#visitDate${siteVisitCount}`);
        dateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        dateInput.focus();
    }, 100);

    // Update stats
    updateSiteVisitStats();
}

function removeSiteVisit(id) {
    const visitItem = document.getElementById(`siteVisit-${id}`);

    // Add fade out animation
    visitItem.style.animation = 'fadeOut 0.3s ease-out';

    setTimeout(() => {
        visitItem.remove();
        updateProgress();
        renumberSiteVisits();
        updateSiteVisitStats();
    }, 300);
}

// Renumber site visits after removal
function renumberSiteVisits() {
    const items = siteVisitsContainer.querySelectorAll('.site-visit-item');
    items.forEach((item, index) => {
        const number = index + 1;
        
        // Update the visit number display
        const visitNumberSpan = item.querySelector('.site-visit-number span');
        if (visitNumberSpan) {
            visitNumberSpan.textContent = `Visit #${number}`;
        }

        // Update input names to reflect new numbering
        const dateInput = item.querySelector('input[type="date"]');
        const participantsInput = item.querySelector('input[type="text"]');
        const remarksInput = item.querySelector('textarea');
        const removeBtn = item.querySelector('.btn-remove');

        if (dateInput) {
            dateInput.name = `siteVisits[${number}][date]`;
        }
        if (participantsInput) {
            participantsInput.name = `siteVisits[${number}][participants]`;
        }
        if (remarksInput) {
            remarksInput.name = `siteVisits[${number}][remarks]`;
        }
        if (removeBtn) {
            removeBtn.onclick = function() {
                removeSiteVisit(parseInt(item.id.split('-')[1]));
            };
        }
    });
}

// Auto-calculate site visit statistics
function updateSiteVisitStats() {
    const totalInput = document.getElementById('totalSiteVisits');
    const completedInput = document.getElementById('siteVisitCompleted');
    const remainsInput = document.getElementById('siteVisitRemains');
    
    // Count completed site visits
    const completedCheckboxes = siteVisitsContainer.querySelectorAll('.visit-completed-checkbox:checked');
    const completedCount = completedCheckboxes.length;
    
    // Get total from input
    const total = parseInt(totalInput.value) || 0;
    
    // Calculate remaining
    const remaining = Math.max(0, total - completedCount);
    
    // Update the display fields
    completedInput.value = completedCount;
    remainsInput.value = remaining;
}

// ===================================
// Participant Management
// ===================================
let participantCounts = {}; // Track participant count for each visit

function addParticipant(visitId) {
    const container = document.getElementById(`participantsContainer${visitId}`);
    if (!container) return;

    // Initialize count for this visit if not exists
    if (!participantCounts[visitId]) {
        participantCounts[visitId] = 0;
    }
    participantCounts[visitId]++;

    const participantItem = document.createElement('div');
    participantItem.className = 'participant-item';
    participantItem.id = `participant-${visitId}-${participantCounts[visitId]}`;

    participantItem.innerHTML = `
        <div class="participant-input-group">
            <input 
                type="text" 
                name="siteVisits[${visitId}][participants][${participantCounts[visitId]}][name]" 
                placeholder="Enter participant name"
                class="participant-input"
                required
            >
            <button type="button" class="btn-remove-participant" onclick="removeParticipant(${visitId}, ${participantCounts[visitId]})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    container.appendChild(participantItem);

    // Add event listener for progress tracking
    const input = participantItem.querySelector('input');
    input.addEventListener('input', updateProgress);
    input.addEventListener('change', updateProgress);

    // Focus on the new input
    setTimeout(() => {
        input.focus();
    }, 50);

    updateProgress();
}

function removeParticipant(visitId, participantId) {
    const participantItem = document.getElementById(`participant-${visitId}-${participantId}`);
    
    if (participantItem) {
        // Add fade out animation
        participantItem.style.animation = 'fadeOut 0.3s ease-out';
        
        setTimeout(() => {
            participantItem.remove();
            updateProgress();
        }, 300);
    }
}

// ===================================
// Payment Stage Calculation
// ===================================
function calculateStageTotal(stageId) {
    const amountPayableInput = document.querySelector(`#stageAmountPayable${stageId}`);
    const gstPercentInput = document.querySelector(`#stageGstPercent${stageId}`);
    const gstAmountInput = document.querySelector(`#stageGstAmount${stageId}`);
    const totalPayableInput = document.querySelector(`#stageTotalPayable${stageId}`);

    if (!amountPayableInput || !gstPercentInput || !gstAmountInput || !totalPayableInput) {
        return;
    }

    const amountPayable = parseFloat(amountPayableInput.value) || 0;
    const gstPercent = parseFloat(gstPercentInput.value) || 0;

    // Calculate GST Amount
    const gstAmount = (amountPayable * gstPercent) / 100;

    // Calculate Total Payable
    const totalPayable = amountPayable + gstAmount;

    // Update the fields
    gstAmountInput.value = gstAmount.toFixed(2);
    totalPayableInput.value = totalPayable.toFixed(2);

    // Update progress
    updateProgress();
}

// ===================================
// Form Validation
// ===================================
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    // Remove all non-digit characters
    const cleanPhone = phone.replace(/\D/g, '');
    // Check if it has at least 10 digits
    return cleanPhone.length >= 10;
}

function showValidationError(element, message) {
    // Remove existing error if any
    const existingError = element.parentElement.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }

    // Create and show error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = 'var(--danger-color)';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;

    element.parentElement.appendChild(errorDiv);
    element.style.borderColor = 'var(--danger-color)';

    // Remove error on input
    element.addEventListener('input', function removeError() {
        errorDiv.remove();
        element.style.borderColor = '';
        element.removeEventListener('input', removeError);
    }, { once: true });
}

// ===================================
// Form Submission
// ===================================
form.addEventListener('submit', async (e) => {
    e.preventDefault();

    let isValid = true;

    // Validate email
    const emailInput = document.getElementById('clientEmail');
    if (!validateEmail(emailInput.value)) {
        showValidationError(emailInput, 'Please enter a valid email address');
        isValid = false;
    }

    // Validate WhatsApp number
    const whatsappInput = document.getElementById('clientWhatsapp');
    if (!validatePhone(whatsappInput.value)) {
        showValidationError(whatsappInput, 'Please enter a valid phone number (at least 10 digits)');
        isValid = false;
    }

    // Validate alternative numbers
    const altPhoneInputs = document.querySelectorAll('input[name^="alternativeContacts"][name$="[number]"]');
    altPhoneInputs.forEach(input => {
        if (input.value.trim() !== '' && !validatePhone(input.value)) {
            showValidationError(input, 'Please enter a valid phone number');
            isValid = false;
        }
    });

    if (!isValid) {
        return;
    }

    // Collect form data
    const formData = new FormData(form);
    const data = {
        referenceNumber: formData.get('referenceNumber'),
        projectType: formData.get('projectType'),
        projectName: formData.get('projectName'),
        siteAddress: formData.get('siteAddress'),
        clientEmail: formData.get('clientEmail'),
        clientWhatsapp: formData.get('clientWhatsapp'),
        alternativeContacts: [],
        paymentStages: [],
        submittedAt: new Date().toISOString()
    };

    // Process Alternative Contacts
    const altItems = document.querySelectorAll('.alternative-number-item');
    altItems.forEach((item) => {
        const nameInput = item.querySelector('input[name*="[name]"]');
        const numberInput = item.querySelector('input[name*="[number]"]');

        if (nameInput && numberInput && (nameInput.value.trim() || numberInput.value.trim())) {
            data.alternativeContacts.push({
                name: nameInput.value,
                number: numberInput.value
            });
        }
    });

    // Process Payment Stages
    const stageItems = document.querySelectorAll('.payment-stage-item');
    stageItems.forEach((item) => {
        const name = item.querySelector('input[name*="[name]"]').value;
        const amount = item.querySelector('input[name*="[amount]"]').value;
        const dueDate = item.querySelector('input[name*="[dueDate]"]').value;
        const status = item.querySelector('select[name*="[status]"]').value;
        const notes = item.querySelector('textarea[name*="[notes]"]').value;

        data.paymentStages.push({
            name,
            amount,
            dueDate,
            status,
            notes
        });
    });

    // Log the data (you can replace this with actual API call)
    console.log('Form Data:', data);

    // Show success message
    showSuccessMessage();

    // TODO: Send data to backend
    // Example:
    // try {
    //     const response = await fetch('process_project_intake.php', {
    //         method: 'POST',
    //         headers: {
    //             'Content-Type': 'application/json',
    //         },
    //         body: JSON.stringify(data)
    //     });
    //     
    //     if (response.ok) {
    //         showSuccessMessage();
    //     } else {
    //         throw new Error('Submission failed');
    //     }
    // } catch (error) {
    //     console.error('Error:', error);
    //     alert('There was an error submitting the form. Please try again.');
    // }
});

function showSuccessMessage() {
    successMessage.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// ===================================
// Form Reset Handler
// ===================================
form.addEventListener('reset', () => {
    // Clear all alternative numbers
    alternativeNumbersContainer.innerHTML = '';
    alternativeNumberCount = 0;

    // Reset progress bar
    setTimeout(() => {
        updateProgress();
    }, 100);

    // Remove any error messages
    document.querySelectorAll('.error-message').forEach(error => error.remove());

    // Reset border colors
    document.querySelectorAll('input, select, textarea').forEach(element => {
        element.style.borderColor = '';
    });
});

// ===================================
// Event Listeners
// ===================================
addAlternativeNumberBtn.addEventListener('click', addAlternativeNumber);
addSiteVisitBtn.addEventListener('click', addSiteVisit);

// Add listener to total site visits input to update stats
const totalSiteVisitsInput = document.getElementById('totalSiteVisits');
if (totalSiteVisitsInput) {
    totalSiteVisitsInput.addEventListener('input', updateSiteVisitStats);
    totalSiteVisitsInput.addEventListener('change', updateSiteVisitStats);
}

// ===================================
// Input Formatting
// ===================================

// Auto-format phone numbers as user types
function formatPhoneNumber(input) {
    input.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');

        // Format as +91 XXXXX XXXXX for Indian numbers
        if (value.length > 0) {
            if (!value.startsWith('91')) {
                value = '91' + value;
            }

            let formatted = '+91';
            if (value.length > 2) {
                formatted += ' ' + value.substring(2, 7);
            }
            if (value.length > 7) {
                formatted += ' ' + value.substring(7, 12);
            }

            e.target.value = formatted;
        }
    });
}

// Apply phone formatting to WhatsApp number
const whatsappInput = document.getElementById('clientWhatsapp');
formatPhoneNumber(whatsappInput);

// Apply formatting to alternative numbers when they're added
const originalAddFunction = addAlternativeNumber;
addAlternativeNumber = function () {
    originalAddFunction();
    const lastInput = alternativeNumbersContainer.querySelector('.alternative-number-item:last-child input');
    if (lastInput) {
        formatPhoneNumber(lastInput);
    }
};

// ===================================
// Keyboard Shortcuts
// ===================================
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + Enter to submit
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        form.dispatchEvent(new Event('submit'));
    }

    // Escape to close success message
    if (e.key === 'Escape' && !successMessage.classList.contains('hidden')) {
        location.reload();
    }
});

// ===================================
// Auto-save to localStorage (Optional)
// ===================================
// ===================================
// Auto-save to localStorage
// ===================================
function saveFormData() {
    const formData = new FormData(form);
    const data = {
        // Basic fields
        referenceNumber: formData.get('referenceNumber'),
        projectType: formData.get('projectType'),
        projectName: formData.get('projectName'),
        siteAddress: formData.get('siteAddress'),
        clientEmail: formData.get('clientEmail'),
        clientWhatsapp: formData.get('clientWhatsapp'),
        alternativeContacts: [],
        paymentStages: []
    };

    // Save Alternative Contacts
    const altItems = document.querySelectorAll('.alternative-number-item');
    altItems.forEach((item) => {
        const nameInput = item.querySelector('input[name*="[name]"]');
        const numberInput = item.querySelector('input[name*="[number]"]');
        if (nameInput && numberInput) {
            data.alternativeContacts.push({
                name: nameInput.value,
                number: numberInput.value
            });
        }
    });

    // Save Payment Stages
    const stageItems = document.querySelectorAll('.payment-stage-item');
    stageItems.forEach((item) => {
        const name = item.querySelector('input[name*="[name]"]').value;
        const amount = item.querySelector('input[name*="[amount]"]').value;
        const dueDate = item.querySelector('input[name*="[dueDate]"]').value;
        const status = item.querySelector('select[name*="[status]"]').value;
        const notes = item.querySelector('textarea[name*="[notes]"]').value;

        data.paymentStages.push({
            name,
            amount,
            dueDate,
            status,
            notes
        });
    });

    localStorage.setItem('projectIntakeFormData', JSON.stringify(data));
}

function loadFormData() {
    const savedData = localStorage.getItem('projectIntakeFormData');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);

            // Fill in basic fields
            const basicFields = ['referenceNumber', 'projectType', 'projectName', 'siteAddress', 'clientEmail', 'clientWhatsapp'];
            basicFields.forEach(key => {
                const element = document.getElementById(key);
                if (element && data[key]) {
                    element.value = data[key];
                }
            });

            // Restore Alternative Contacts
            if (data.alternativeContacts && Array.isArray(data.alternativeContacts)) {
                // Clear existing first
                alternativeNumbersContainer.innerHTML = '';
                alternativeNumberCount = 0;

                data.alternativeContacts.forEach(contact => {
                    addAlternativeNumber();
                    // Get the last added item
                    const items = alternativeNumbersContainer.querySelectorAll('.alternative-number-item');
                    const lastItem = items[items.length - 1];

                    if (lastItem) {
                        const nameInput = lastItem.querySelector('input[name*="[name]"]');
                        const numberInput = lastItem.querySelector('input[name*="[number]"]');
                        if (nameInput) nameInput.value = contact.name || '';
                        if (numberInput) numberInput.value = contact.number || '';
                    }
                });
            }

            // Restore Payment Stages
            if (data.paymentStages && Array.isArray(data.paymentStages)) {
                // Clear existing first
                paymentStagesContainer.innerHTML = '';
                // Note: unique IDs are generated on add, so we don't reset a counter per se, just add new ones

                data.paymentStages.forEach(stage => {
                    addPaymentStage();
                    // Get the last added item
                    const items = paymentStagesContainer.querySelectorAll('.payment-stage-item');
                    const lastItem = items[items.length - 1];

                    if (lastItem) {
                        lastItem.querySelector('input[name*="[name]"]').value = stage.name || '';
                        lastItem.querySelector('input[name*="[amount]"]').value = stage.amount || '';
                        lastItem.querySelector('input[name*="[dueDate]"]').value = stage.dueDate || '';
                        lastItem.querySelector('select[name*="[status]"]').value = stage.status || 'pending';
                        lastItem.querySelector('textarea[name*="[notes]"]').value = stage.notes || '';
                    }
                });
            }

            updateProgress();
        } catch (e) {
            console.error('Error loading saved data:', e);
        }
    }
}

// Auto-save every 2 seconds
setInterval(saveFormData, 2000);

// Load saved data on page load
document.addEventListener('DOMContentLoaded', () => {
    loadFormData();
});

// Clear saved data on successful submission
function clearSavedData() {
    localStorage.removeItem('projectIntakeFormData');
}

// Update the success message function
const originalShowSuccess = showSuccessMessage;
showSuccessMessage = function () {
    originalShowSuccess();
    clearSavedData();
};

console.log('Project Intake Form initialized successfully! 🚀');

// ===================================
// Mobile Menu Toggle
// ===================================
if (mobileMenuToggle && sidePanel) {
    mobileMenuToggle.addEventListener('click', () => {
        sidePanel.classList.toggle('active');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            if (!sidePanel.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                sidePanel.classList.remove('active');
            }
        }
    });

    // Close sidebar on window resize if going from mobile to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidePanel.classList.remove('active');
        }
    });
}

// ===================================
// Enhanced Progress Tracking
// ===================================
// Override the updateProgress function to include circular progress
const originalUpdateProgress = updateProgress;
updateProgress = function () {
    const formElements = form.querySelectorAll('input[required], select[required], textarea[required]');
    let filledCount = 0;

    formElements.forEach(element => {
        if (element.value.trim() !== '') {
            filledCount++;
        }
    });

    const progress = (filledCount / formElements.length) * 100;

    // Update linear progress bar
    progressFill.style.width = `${progress}%`;

    // Update circular progress indicator
    if (progressCircleFill && progressPercentage) {
        const circumference = 2 * Math.PI * 54; // radius = 54
        const offset = circumference - (progress / 100) * circumference;
        progressCircleFill.style.strokeDashoffset = offset;
        progressPercentage.textContent = `${Math.round(progress)}%`;
    }
};


// ===================================
// Payment Reminder Filters
// ===================================
document.addEventListener('DOMContentLoaded', () => {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const reminderItems = document.querySelectorAll('.reminder-item');

    if (filterBtns.length > 0) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                filterBtns.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                btn.classList.add('active');

                const filter = btn.dataset.filter;

                // Filter reminder items
                reminderItems.forEach(item => {
                    if (filter === 'all') {
                        item.style.display = 'block';
                    } else if (item.classList.contains(filter)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    }

    // Send Reminder Button Handlers
    const sendReminderBtns = document.querySelectorAll('.btn-send-reminder');
    if (sendReminderBtns.length > 0) {
        sendReminderBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const reminderItem = this.closest('.reminder-item');
                const projectRef = reminderItem.querySelector('.project-ref').textContent;
                const projectName = reminderItem.querySelector('.project-name').textContent;

                // Disable button and show loading state
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

                // Simulate sending reminder (replace with actual API call)
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-check"></i> Sent!';
                    this.style.background = 'var(--success-color)';

                    // Reset after 2 seconds
                    setTimeout(() => {
                        this.disabled = false;
                        this.innerHTML = '<i class="fab fa-whatsapp"></i> Send Reminder';
                        this.style.background = '#25D366';
                    }, 2000);

                    console.log(`Reminder sent for ${projectRef}: ${projectName}`);
                }, 1000);
            });
        });
    }
});

// ===================================
// Payment Stages Management
// ===================================
const paymentStagesContainer = document.getElementById('paymentStagesContainer');
const addPaymentStageBtn = document.getElementById('addPaymentStage');

function addPaymentStage() {
    // Get current number of stages to determine the next stage number
    const currentStages = paymentStagesContainer.querySelectorAll('.payment-stage-item');
    const stageNumber = currentStages.length + 1;

    // Generate a unique ID using timestamp to avoid conflicts
    const uniqueId = Date.now();

    const stageItem = document.createElement('div');
    stageItem.className = 'payment-stage-item';
    stageItem.dataset.stageId = uniqueId;

    stageItem.innerHTML = `
        <div class="stage-header">
            <h4>Stage ${stageNumber}</h4>
            <button type="button" class="btn-remove-stage" onclick="removePaymentStage('${uniqueId}')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="stageName${uniqueId}">
                    Stage Name <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="stageName${uniqueId}" 
                    name="paymentStages[${stageNumber}][name]" 
                    placeholder="e.g., Foundation, Structural Work"
                    value="Stage ${stageNumber}"
                    readonly
                    required
                >
            </div>

            <div class="form-group">
                <label for="stageAmountPayable${uniqueId}">
                    Amount Payable (₹) <span class="required">*</span>
                </label>
                <input 
                    type="number" 
                    id="stageAmountPayable${uniqueId}" 
                    name="paymentStages[${stageNumber}][amountPayable]" 
                    class="amount-payable"
                    placeholder="0"
                    min="0"
                    step="1000"
                    onchange="calculateStageTotal('${uniqueId}')"
                    oninput="calculateStageTotal('${uniqueId}')"
                    required
                >
            </div>

            <div class="form-group">
                <label for="stageGstPercent${uniqueId}">
                    GST % <span class="required">*</span>
                </label>
                <input 
                    type="number" 
                    id="stageGstPercent${uniqueId}" 
                    name="paymentStages[${stageNumber}][gstPercent]" 
                    class="gst-percent"
                    placeholder="0"
                    min="0"
                    max="100"
                    step="0.1"
                    value="0"
                    onchange="calculateStageTotal('${uniqueId}')"
                    oninput="calculateStageTotal('${uniqueId}')"
                    required
                >
            </div>

            <div class="form-group">
                <label for="stageGstAmount${uniqueId}">
                    GST Amount (₹)
                </label>
                <input 
                    type="number" 
                    id="stageGstAmount${uniqueId}" 
                    name="paymentStages[${stageNumber}][gstAmount]" 
                    class="gst-amount"
                    placeholder="0"
                    min="0"
                    readonly
                >
            </div>

            <div class="form-group">
                <label for="stageTotalPayable${uniqueId}">
                    Total Payable (₹)
                </label>
                <input 
                    type="number" 
                    id="stageTotalPayable${uniqueId}" 
                    name="paymentStages[${stageNumber}][totalPayable]" 
                    class="total-payable"
                    placeholder="0"
                    min="0"
                    readonly
                >
            </div>

            <div class="form-group">
                <label for="stageDueDate${uniqueId}">
                    Due Date <span class="required">*</span>
                </label>
                <input 
                    type="date" 
                    id="stageDueDate${uniqueId}" 
                    name="paymentStages[${stageNumber}][dueDate]" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="stageStatus${uniqueId}">
                    Status
                </label>
                <select 
                    id="stageStatus${uniqueId}" 
                    name="paymentStages[${stageNumber}][status]"
                >
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="stageParticulars${uniqueId}">
                    Particulars (Optional)
                </label>
                <div id="particularsEditor${uniqueId}" class="quill-editor"></div>
                <input 
                    type="hidden" 
                    id="stageParticulars${uniqueId}" 
                    name="paymentStages[${stageNumber}][particulars]" 
                >
            </div>
        </div>
    `;

    paymentStagesContainer.appendChild(stageItem);

    // Set the stage name value explicitly
    const stageNameInput = stageItem.querySelector(`#stageName${uniqueId}`);
    if (stageNameInput) {
        stageNameInput.value = `Stage ${stageNumber}`;
    }

    // Add event listeners for progress tracking
    const inputs = stageItem.querySelectorAll('input[required], select[required], textarea[required]');
    inputs.forEach(input => {
        input.addEventListener('input', updateProgress);
        input.addEventListener('change', updateProgress);
    });

    // Add event listeners for amount and GST calculation
    const amountPayableInput = stageItem.querySelector(`#stageAmountPayable${uniqueId}`);
    const gstPercentInput = stageItem.querySelector(`#stageGstPercent${uniqueId}`);
    
    if (amountPayableInput) {
        amountPayableInput.addEventListener('input', () => calculateStageTotal(uniqueId));
        amountPayableInput.addEventListener('change', () => calculateStageTotal(uniqueId));
    }
    
    if (gstPercentInput) {
        gstPercentInput.addEventListener('input', () => calculateStageTotal(uniqueId));
        gstPercentInput.addEventListener('change', () => calculateStageTotal(uniqueId));
    }

    // Initialize Quill Editor for Particulars
    setTimeout(() => {
        const particularsEditor = new Quill(`#particularsEditor${uniqueId}`, {
            theme: 'snow',
            placeholder: 'Enter particulars and payment details...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link']
                ]
            }
        });

        // Store reference to the Quill editor
        stageItem.quillEditor = particularsEditor;

        // Save content to hidden input on change
        particularsEditor.on('text-change', function() {
            const delta = JSON.stringify(particularsEditor.getContents());
            const html = particularsEditor.root.innerHTML;
            document.getElementById(`stageParticulars${uniqueId}`).value = html;
            updateProgress();
        });
    }, 100);

    // Smooth scroll to the new stage
    setTimeout(() => {
        stageItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 100);

    updateProgress();
}

function removePaymentStage(id) {
    const stageItem = document.querySelector(`[data-stage-id="${id}"]`);

    if (!stageItem) return;

    // Add fade out animation
    stageItem.style.animation = 'fadeOut 0.3s ease-out';

    setTimeout(() => {
        stageItem.remove();
        updateProgress();
        renumberPaymentStages();
    }, 300);
}

// Renumber payment stages after removal
function renumberPaymentStages() {
    const items = paymentStagesContainer.querySelectorAll('.payment-stage-item');
    items.forEach((item, index) => {
        const stageNumber = index + 1;
        const header = item.querySelector('.stage-header h4');
        header.textContent = `Stage ${stageNumber}`;

        // Update stage name field value
        const stageNameInput = item.querySelector('input[id*="stageName"]');
        if (stageNameInput) {
            stageNameInput.value = `Stage ${stageNumber}`;
        }

        // Update input names to reflect new numbering
        const inputs = item.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.name && input.name.includes('paymentStages')) {
                const fieldName = input.name.match(/\[(\w+)\]$/)[1];
                input.name = `paymentStages[${stageNumber}][${fieldName}]`;
            }
        });
    });
}

// Event listener for add payment stage button
if (addPaymentStageBtn) {
    addPaymentStageBtn.addEventListener('click', addPaymentStage);
}
