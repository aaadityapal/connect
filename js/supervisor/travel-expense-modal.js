/**
 * Travel Expense Modal JS
 * Handles the functionality for adding and managing travel expenses
 */

document.addEventListener('DOMContentLoaded', function() {
    // DOM elements with more robust verification
    const addTravelExpenseBtn = document.getElementById('addTravelExpenseBtn');
    const travelExpenseModal = document.getElementById('travelExpenseModal');
    
    // Check if we're on a page that has the travel expense functionality
    if (!addTravelExpenseBtn || !travelExpenseModal) {
        console.log('Travel expense modal functionality not available on this page.');
        return; // Exit gracefully without error
    }
    
    console.log('Travel expense modal functionality initialized.');
    
    // Continue with other element selectors, with checks
    const travelExpenseForm = document.getElementById('travelExpenseForm');
    if (!travelExpenseForm) {
        console.error('Travel expense form not found in the DOM');
        return;
    }
    
    const addExpenseEntryBtn = document.getElementById('addExpenseEntry');
    const resetExpenseFormBtn = document.getElementById('resetExpenseForm');
    const saveAllExpensesBtn = document.getElementById('saveAllExpenses');
    const expensesList = document.querySelector('.travel-expenses-list');
    const expensesSummary = document.querySelector('.travel-expenses-summary');
    const totalEntriesSpan = document.getElementById('totalEntries');
    const totalAmountSpan = document.getElementById('totalAmount');
    
    // Verify all required elements are present
    const missingElements = [];
    if (!addExpenseEntryBtn) missingElements.push('addExpenseEntry');
    if (!resetExpenseFormBtn) missingElements.push('resetExpenseForm');
    if (!saveAllExpensesBtn) missingElements.push('saveAllExpenses');
    if (!expensesList) missingElements.push('travel-expenses-list');
    if (!expensesSummary) missingElements.push('travel-expenses-summary');
    if (!totalEntriesSpan) missingElements.push('totalEntries');
    if (!totalAmountSpan) missingElements.push('totalAmount');
    
    if (missingElements.length > 0) {
        console.error('Missing DOM elements for travel expense modal:', missingElements.join(', '));
        return;
    }
    
    // Form elements
    const purposeInput = document.getElementById('purposeOfVisit');
    const modeInput = document.getElementById('modeOfTransport');
    const fromInput = document.getElementById('fromLocation');
    const toInput = document.getElementById('toLocation');
    const dateInput = document.getElementById('travelDate');
    const distanceInput = document.getElementById('approxDistance');
    const expenseInput = document.getElementById('totalExpense');
    const notesInput = document.getElementById('expenseNotes');
    
    // Add visual indicator for read-only fields
    expenseInput.addEventListener('focus', function() {
        if (this.readOnly) {
            this.blur(); // Remove focus if read-only
            showNotification('This field is automatically calculated based on distance for Bike and Car modes', 'info');
        }
    });
    
    // Check form elements
    const missingFormElements = [];
    if (!purposeInput) missingFormElements.push('purposeOfVisit');
    if (!modeInput) missingFormElements.push('modeOfTransport');
    if (!fromInput) missingFormElements.push('fromLocation');
    if (!toInput) missingFormElements.push('toLocation');
    if (!dateInput) missingFormElements.push('travelDate');
    if (!distanceInput) missingFormElements.push('approxDistance');
    if (!expenseInput) missingFormElements.push('totalExpense');
    if (!notesInput) missingFormElements.push('expenseNotes');
    
    if (missingFormElements.length > 0) {
        console.error('Missing form elements for travel expense modal:', missingFormElements.join(', '));
        return;
    }
    
    // Initialize array to store travel expense entries
    let travelExpenses = [];
    let entryIdCounter = 1;
    
    // Add file upload container
    const fileUploadContainer = document.createElement('div');
    fileUploadContainer.id = 'billUploadContainer';
    fileUploadContainer.className = 'form-group bill-upload-container';
    fileUploadContainer.style.display = 'none';
    fileUploadContainer.innerHTML = `
        <label for="billFile">Upload Taxi Bill (Required)<span class="text-danger">*</span></label>
        <div class="custom-file">
            <input type="file" class="custom-file-input" id="billFile" accept=".jpg,.jpeg,.png,.pdf" required>
            <label class="custom-file-label" for="billFile">Choose file...</label>
        </div>
        <small class="form-text text-muted">Please upload taxi bill receipt (JPG, PNG, or PDF only)</small>
        <div class="bill-preview mt-2" style="display: none;">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="bill-file-name">No file selected</span>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-bill-btn">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    <div class="bill-thumbnail mt-2" style="display: none;"></div>
                </div>
            </div>
        </div>
    `;
    
    // Insert before the notes field
    if (notesInput && notesInput.parentNode) {
        notesInput.parentNode.parentNode.insertBefore(fileUploadContainer, notesInput.parentNode);
    }
    
    // Listen for mode of transport changes
    modeInput.addEventListener('change', function() {
        if (this.value === 'Taxi') {
            fileUploadContainer.style.display = 'block';
            // Make expense field editable for Taxi
            expenseInput.readOnly = false;
        } else {
            fileUploadContainer.style.display = 'none';
            // Reset file input when mode is not Taxi
            const billFileInput = document.getElementById('billFile');
            if (billFileInput) {
                billFileInput.value = '';
                const billPreview = document.querySelector('.bill-preview');
                if (billPreview) billPreview.style.display = 'none';
                const billThumbnail = document.querySelector('.bill-thumbnail');
                if (billThumbnail) {
                    billThumbnail.style.display = 'none';
                    billThumbnail.innerHTML = '';
                }
                const billFileNameElem = document.querySelector('.bill-file-name');
                if (billFileNameElem) billFileNameElem.textContent = 'No file selected';
                const fileLabel = document.querySelector('.custom-file-label');
                if (fileLabel) fileLabel.textContent = 'Choose file...';
            }
        }
        
        // Auto-calculate expense based on distance for Bike and Car
        if (this.value === 'Bike' || this.value === 'Car') {
            // Make expense field read-only for Bike and Car
            expenseInput.readOnly = true;
            
            if (distanceInput.value) {
                const distance = parseFloat(distanceInput.value);
                if (!isNaN(distance)) {
                    const ratePerKm = this.value === 'Bike' ? 3.5 : 10; // 3.5 for Bike, 10 for Car
                    const calculatedExpense = distance * ratePerKm;
                    expenseInput.value = calculatedExpense.toFixed(2);
                }
            }
        } else {
            // Make expense field editable for other modes
            expenseInput.readOnly = false;
        }
    });
    
    // Handle file selection
    document.getElementById('billFile').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file selected';
        const fileLabel = document.querySelector('.custom-file-label');
        const billPreview = document.querySelector('.bill-preview');
        const billThumbnail = document.querySelector('.bill-thumbnail');
        const billFileNameElem = document.querySelector('.bill-file-name');
        
        if (fileLabel) fileLabel.textContent = fileName;
        if (billFileNameElem) billFileNameElem.textContent = fileName;
        
        // Show the preview container
        if (billPreview) billPreview.style.display = 'block';
        
        // If it's an image, show a thumbnail
        if (e.target.files[0]) {
            const file = e.target.files[0];
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    if (billThumbnail) {
                        billThumbnail.style.display = 'block';
                        billThumbnail.innerHTML = `<img src="${event.target.result}" class="img-thumbnail" style="max-height: 100px;" alt="Bill preview">`;
                    }
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                // For PDFs, just show an icon
                if (billThumbnail) {
                    billThumbnail.style.display = 'block';
                    billThumbnail.innerHTML = `<div class="pdf-icon"><i class="fas fa-file-pdf text-danger" style="font-size: 2rem;"></i><span class="ml-2">${file.name}</span></div>`;
                }
            }
        }
    });
    
    // Handle remove bill button click
    document.querySelector('.remove-bill-btn').addEventListener('click', function() {
        const billFileInput = document.getElementById('billFile');
        if (billFileInput) billFileInput.value = '';
        
        const fileLabel = document.querySelector('.custom-file-label');
        if (fileLabel) fileLabel.textContent = 'Choose file...';
        
        const billPreview = document.querySelector('.bill-preview');
        if (billPreview) billPreview.style.display = 'none';
        
        const billThumbnail = document.querySelector('.bill-thumbnail');
        if (billThumbnail) {
            billThumbnail.style.display = 'none';
            billThumbnail.innerHTML = '';
        }
        
        const billFileNameElem = document.querySelector('.bill-file-name');
        if (billFileNameElem) billFileNameElem.textContent = 'No file selected';
    });
    
    /**
     * Formats a date in YYYY-MM-DD format
     * @param {Date} date - The date object
     * @returns {string} - Formatted date string
     */
    function formatDateForDB(date) {
        if (!(date instanceof Date)) {
            // If it's a string, try to convert it to a Date object
            if (typeof date === 'string') {
                date = new Date(date);
                
                // Check if the date is valid
                if (isNaN(date.getTime())) {
                    console.error('Invalid date string:', date);
                    return ''; // Return empty string for invalid dates
                }
            } else {
                console.error('Invalid date input:', date);
                return ''; // Return empty string for invalid inputs
            }
        }
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Set default date to today
    dateInput.value = formatDateForDB(new Date());
    
    // Open modal when button is clicked
    addTravelExpenseBtn.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default link behavior
        e.stopPropagation(); // Stop event propagation
        
        // Check if Bootstrap's modal function is available
        if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
            $(travelExpenseModal).modal('show');
        } else {
            console.error('Bootstrap modal functionality not available');
            // Fallback behavior - simple display toggle
            travelExpenseModal.style.display = 'block';
        }
    });
    
    // Reset form when reset button is clicked
    resetExpenseFormBtn.addEventListener('click', resetForm);
    
    // Add expense entry when add button is clicked
    addExpenseEntryBtn.addEventListener('click', addExpenseEntry);
    
    // Save all expenses when save button is clicked
    saveAllExpensesBtn.addEventListener('click', saveAllExpenses);
    
    /**
     * Validates the expense form
     * @returns {boolean} - Whether the form is valid
     */
    function validateForm() {
        let isValid = true;
        
        // Simple validation - check if required fields have values
        if (!purposeInput.value.trim()) {
            markInvalid(purposeInput);
            isValid = false;
        } else {
            markValid(purposeInput);
        }
        
        if (!modeInput.value) {
            markInvalid(modeInput);
            isValid = false;
        } else {
            markValid(modeInput);
        }
        
        // Check if bill file is required for Taxi
        if (modeInput.value === 'Taxi') {
            const billFileInput = document.getElementById('billFile');
            if (!billFileInput || !billFileInput.files || billFileInput.files.length === 0) {
                if (billFileInput) markInvalid(billFileInput);
                isValid = false;
                showNotification('Please upload a taxi bill receipt', 'error');
            } else {
                if (billFileInput) markValid(billFileInput);
                
                // Validate file type
                const file = billFileInput.files[0];
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!validTypes.includes(file.type)) {
                    markInvalid(billFileInput);
                    isValid = false;
                    showNotification('Invalid file type. Please upload JPG, PNG, or PDF only', 'error');
                }
                
                // Validate file size (max 5MB)
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                if (file.size > maxSize) {
                    markInvalid(billFileInput);
                    isValid = false;
                    showNotification('File is too large. Maximum size is 5MB', 'error');
                }
            }
        }
        
        if (!fromInput.value.trim()) {
            markInvalid(fromInput);
            isValid = false;
        } else {
            markValid(fromInput);
        }
        
        if (!toInput.value.trim()) {
            markInvalid(toInput);
            isValid = false;
        } else {
            markValid(toInput);
        }
        
        if (!dateInput.value) {
            markInvalid(dateInput);
            isValid = false;
        } else {
            // Validate date format (YYYY-MM-DD)
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (!dateRegex.test(dateInput.value)) {
                markInvalid(dateInput);
                showNotification('Date must be in YYYY-MM-DD format', 'error');
                isValid = false;
            } else {
                // Check if it's a valid date
                const date = new Date(dateInput.value);
                if (isNaN(date.getTime())) {
                    markInvalid(dateInput);
                    showNotification('Invalid date', 'error');
                    isValid = false;
                } else {
                    markValid(dateInput);
                }
            }
        }
        
        if (!distanceInput.value || distanceInput.value <= 0) {
            markInvalid(distanceInput);
            isValid = false;
        } else {
            markValid(distanceInput);
        }
        
        if (!expenseInput.value || expenseInput.value <= 0) {
            markInvalid(expenseInput);
            isValid = false;
        } else {
            markValid(expenseInput);
        }
        
        return isValid;
    }
    
    /**
     * Marks a form field as invalid
     * @param {HTMLElement} field - The field to mark
     */
    function markInvalid(field) {
        if (field) field.classList.add('is-invalid');
    }
    
    /**
     * Marks a form field as valid
     * @param {HTMLElement} field - The field to mark
     */
    function markValid(field) {
        if (field) field.classList.remove('is-invalid');
    }
    
    /**
     * Adds a new expense entry
     */
    function addExpenseEntry() {
        if (!validateForm()) {
            return;
        }
        
        // Handle file upload for Taxi
        let billFileData = null;
        if (modeInput.value === 'Taxi') {
            const billFileInput = document.getElementById('billFile');
            if (billFileInput && billFileInput.files.length > 0) {
                // Get the file object
                const file = billFileInput.files[0];
                
                // Store file metadata in the expense entry
                billFileData = {
                    name: file.name,
                    type: file.type,
                    size: file.size,
                    file: file  // Store the actual file object
                };
                
                // Also store a preview if it's an image
                if (file.type.startsWith('image/')) {
                    try {
                        // Use FileReader to get data URL
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            billFileData.dataUrl = event.target.result;
                        };
                        reader.readAsDataURL(file);
                    } catch (err) {
                        console.error("Error reading file:", err);
                    }
                }
            } else if (window.lastBillFile) {
                // Reuse the last bill file info if there's no new file but we previously had one
                billFileData = { ...window.lastBillFile };
                console.log("Reusing previous file data:", billFileData.name);
            } else {
                // No file was selected and we don't have one stored
                showNotification('Please upload a taxi bill receipt', 'error');
                return;
            }
        }
        
        // Create expense entry object
        const expense = {
            id: entryIdCounter++,
            purpose: purposeInput.value.trim(),
            mode: modeInput.value,
            from: fromInput.value.trim(),
            to: toInput.value.trim(),
            date: formatDateForDB(dateInput.value),
            distance: parseFloat(distanceInput.value),
            amount: parseFloat(expenseInput.value),
            notes: notesInput.value.trim(),
            billFile: billFileData,
            status: addExpenseEntryBtn.dataset.status || 'pending'
        };
        
        // Add to expenses array
        travelExpenses.push(expense);
        
        // Add to UI
        addExpenseToUI(expense);
        
        // Update summary
        updateSummary();
        
        // Reset form
        resetForm();
        
        // Show success message
        showNotification('Expense added successfully', 'success');
    }
    
    /**
     * Adds an expense entry to the UI
     * @param {Object} expense - The expense entry
     */
    function addExpenseToUI(expense) {
        // Format date for display
        const formattedDate = formatDate(expense.date);
        
        // Store the original date in data attributes for later retrieval
        const originalDate = expense.date;
        
        // Create expense entry element
        const entryElement = document.createElement('div');
        entryElement.className = 'expense-entry new-entry';
        entryElement.dataset.id = expense.id;
        entryElement.dataset.entryNumber = travelExpenses.length;
        entryElement.dataset.status = expense.status || 'pending';
        
        // Create bill file info HTML if present
        let billFileHtml = '';
        if (expense.billFile) {
            billFileHtml = `
                <div class="bill-file-info">
                    <div class="detail-label">Bill Receipt</div>
                    <div class="detail-value">
                        <span class="badge badge-info">
                            <i class="fas fa-${expense.billFile.type.includes('pdf') ? 'file-pdf' : 'file-image'}"></i>
                            ${expense.billFile.name}
                        </span>
                    </div>
                </div>
            `;
        }
        
        // HTML for the entry
        entryElement.innerHTML = `
            <div class="entry-header">
                <h5 class="entry-title">
                    <span class="expense-entry-number">Expense #1:</span> ${expense.purpose}
                </h5>
                <div class="entry-actions">
                    <button class="btn btn-sm btn-outline-secondary edit-expense" data-id="${expense.id}">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-expense" data-id="${expense.id}">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </div>
            </div>
            <div class="entry-details">
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value" data-field="date" data-original-date="${originalDate}">${formattedDate}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Mode</div>
                    <div class="detail-value" data-field="mode">${expense.mode}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">From</div>
                    <div class="detail-value" data-field="from">${expense.from}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">To</div>
                    <div class="detail-value" data-field="to">${expense.to}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Distance</div>
                    <div class="detail-value" data-field="distance">${expense.distance} km</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Amount</div>
                    <div class="detail-value entry-amount" data-field="amount">₹${expense.amount.toFixed(2)}</div>
                </div>
                ${billFileHtml}
            </div>
            ${expense.notes ? `<div class="entry-notes">${expense.notes}</div>` : ''}
        `;
        
        // Add to expenses list
        expensesList.appendChild(entryElement);
        
        // Update entry numbers
        updateEntryNumbers();
        
        // Show the summary if not already visible
        expensesSummary.style.display = 'block';
        
        // Add event listeners for edit and delete buttons
        const editBtn = entryElement.querySelector('.edit-expense');
        if (editBtn) {
            editBtn.addEventListener('click', function() {
                editExpense(expense.id);
            });
        }
        
        const deleteBtn = entryElement.querySelector('.delete-expense');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                deleteExpense(expense.id);
            });
        }
        
        // Remove new-entry class after animation completes
        setTimeout(() => {
            entryElement.classList.remove('new-entry');
        }, 1500);
    }
    
    /**
     * Formats a date string to a more readable format
     * @param {string} dateString - The date string in YYYY-MM-DD format
     * @returns {string} - The formatted date
     */
    function formatDate(dateString) {
        try {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString(undefined, options);
        } catch (e) {
            console.error('Error formatting date:', e);
            return dateString; // Fallback to the original string
        }
    }
    
    /**
     * Updates the summary information
     */
    function updateSummary() {
        // Calculate totals
        const totalEntries = travelExpenses.length;
        const totalAmount = travelExpenses.reduce((sum, expense) => sum + expense.amount, 0);
        
        // Update UI
        totalEntriesSpan.textContent = totalEntries;
        totalAmountSpan.textContent = totalAmount.toFixed(2);
        
        // Show or hide summary based on entries
        expensesSummary.style.display = totalEntries > 0 ? 'block' : 'none';
    }
    
    /**
     * Edits an existing expense entry
     * @param {number} id - The ID of the expense to edit
     */
    function editExpense(id) {
        // Find the expense in the array
        const expense = travelExpenses.find(item => item.id === id);
        
        if (!expense) {
            console.error('Expense not found');
            showNotification('Expense not found', 'error');
            return;
        }
        
        // Populate form with expense data
        purposeInput.value = expense.purpose;
        modeInput.value = expense.mode;
        fromInput.value = expense.from;
        toInput.value = expense.to;
        dateInput.value = expense.date;
        distanceInput.value = expense.distance;
        expenseInput.value = expense.amount;
        notesInput.value = expense.notes || '';
        
        // Handle bill file display if this is a taxi expense
        if (expense.mode === 'Taxi') {
            // Show the bill upload container
            const fileUploadContainer = document.getElementById('billUploadContainer');
            if (fileUploadContainer) {
                fileUploadContainer.style.display = 'block';
            }
            
            // Show bill file details if available
            if (expense.billFile) {
                const billPreview = document.querySelector('.bill-preview');
                const billThumbnail = document.querySelector('.bill-thumbnail');
                const billFileNameElem = document.querySelector('.bill-file-name');
                const fileLabel = document.querySelector('.custom-file-label');
                
                if (billPreview) billPreview.style.display = 'block';
                if (billFileNameElem) billFileNameElem.textContent = expense.billFile.name;
                if (fileLabel) fileLabel.textContent = expense.billFile.name;
                
                // Show thumbnail if it's an image and we have the data URL
                if (billThumbnail && expense.billFile.type.startsWith('image/') && expense.billFile.dataUrl) {
                    billThumbnail.style.display = 'block';
                    billThumbnail.innerHTML = `<img src="${expense.billFile.dataUrl}" class="img-thumbnail" style="max-height: 100px;" alt="Bill preview">`;
                } else if (billThumbnail && expense.billFile.type === 'application/pdf') {
                    // For PDFs, just show an icon
                    billThumbnail.style.display = 'block';
                    billThumbnail.innerHTML = `<div class="pdf-icon"><i class="fas fa-file-pdf text-danger" style="font-size: 2rem;"></i><span class="ml-2">${expense.billFile.name}</span></div>`;
                }
            }
        }
        
        // Remove the expense from the array and UI
        const originalStatus = expense.status || 'pending';
        deleteExpense(id, false); // false means don't show notification
        
        // Change add button to update button
        addExpenseEntryBtn.textContent = 'Update Entry';
        addExpenseEntryBtn.dataset.editing = 'true';
        addExpenseEntryBtn.dataset.editId = id;
        addExpenseEntryBtn.dataset.status = originalStatus;
        
        // Scroll to form
        if (travelExpenseForm.scrollIntoView) {
            travelExpenseForm.scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    /**
     * Deletes an expense entry
     * @param {number} id - The ID of the expense to delete
     * @param {boolean} showNotif - Whether to show a notification
     */
    function deleteExpense(id, showNotif = true) {
        // Find the expense in the array
        const expenseIndex = travelExpenses.findIndex(item => item.id === id);
        
        if (expenseIndex === -1) {
            console.error('Expense not found');
            return;
        }
        
        // Remove from array
        travelExpenses.splice(expenseIndex, 1);
        
        // Remove from UI
        const entryElement = document.querySelector(`.expense-entry[data-id="${id}"]`);
        if (entryElement) {
            // Add fade-out animation
            entryElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            entryElement.style.opacity = '0';
            entryElement.style.transform = 'translateX(20px)';
            
            // Remove after animation
            setTimeout(() => {
                entryElement.remove();
                
                // Renumber remaining entries
                updateEntryNumbers();
                
                // Update summary
                updateSummary();
            }, 300);
        }
        
        // Show notification if needed
        if (showNotif) {
            showNotification('Expense deleted', 'info');
        }
    }
    
    /**
     * Updates the numbering of all entries
     */
    function updateEntryNumbers() {
        // Get all entry elements
        const entries = document.querySelectorAll('.expense-entry');
        
        // Update the entry number for each entry
        entries.forEach((entry, index) => {
            const number = index + 1;
            entry.dataset.entryNumber = number;
            
            // Update the entry number text
            const entryNumberText = entry.querySelector('.expense-entry-number');
            if (entryNumberText) {
                entryNumberText.textContent = `Expense #${number}:`;
            }
        });
    }
    
    /**
     * Resets the expense form
     */
    function resetForm() {
        // Reset form fields
        travelExpenseForm.reset();
        
        // Reset validation styles
        Array.from(travelExpenseForm.elements).forEach(field => {
            field.classList.remove('is-invalid');
        });
        
        // Reset date to today
        dateInput.value = formatDateForDB(new Date());
        
        // Hide bill upload container
        const fileUploadContainer = document.getElementById('billUploadContainer');
        if (fileUploadContainer) {
            fileUploadContainer.style.display = 'none';
        }
        
        // Reset file input
        const billFileInput = document.getElementById('billFile');
        if (billFileInput) {
            billFileInput.value = '';
            const billPreview = document.querySelector('.bill-preview');
            if (billPreview) billPreview.style.display = 'none';
            const billThumbnail = document.querySelector('.bill-thumbnail');
            if (billThumbnail) {
                billThumbnail.style.display = 'none';
                billThumbnail.innerHTML = '';
            }
            const billFileNameElem = document.querySelector('.bill-file-name');
            if (billFileNameElem) billFileNameElem.textContent = 'No file selected';
            const fileLabel = document.querySelector('.custom-file-label');
            if (fileLabel) fileLabel.textContent = 'Choose file...';
        }
        
        // Reset add button if it was in edit mode
        if (addExpenseEntryBtn.dataset.editing === 'true') {
            addExpenseEntryBtn.textContent = 'Add Entry';
            addExpenseEntryBtn.dataset.editing = 'false';
            delete addExpenseEntryBtn.dataset.editId;
            delete addExpenseEntryBtn.dataset.status;
            
            // Reset the window.lastBillFile when exiting edit mode
            window.lastBillFile = null;
        }
    }
    
    // Also reset lastBillFile when modal is hidden
    if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
        $(travelExpenseModal).on('hide.bs.modal', function() {
            window.lastBillFile = null;
        });
    } else {
        // Fallback for when jQuery is not available
        const closeButtons = travelExpenseModal.querySelectorAll('[data-dismiss="modal"]');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                window.lastBillFile = null;
            });
        });
    }
    
    /**
     * Saves all expense entries
     */
    function saveAllExpenses() {
        if (travelExpenses.length === 0) {
            showNotification('No expenses to save', 'warning');
            return;
        }
        
        // Show loading indicator
        const originalBtnText = saveAllExpensesBtn.innerHTML;
        saveAllExpensesBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveAllExpensesBtn.disabled = true;
        
        // Create FormData object for the main form submission
        const formData = new FormData();
        
        // Check if we have any taxi expenses
        const hasTaxiExpenses = travelExpenses.some(expense => expense.mode === 'Taxi');
        
        // Add each taxi bill file individually to formData
        travelExpenses.forEach((expense, index) => {
            if (expense.mode === 'Taxi' && expense.billFile) {
                // If this is a taxi expense, we need to handle the file
                
                // If we're handling an existing uploaded file
                if (expense.billFile.file instanceof File) {
                    // Append the actual file with a unique index
                    formData.append('bill_file_' + index, expense.billFile.file, expense.billFile.name);
                    console.log(`Attached existing file: bill_file_${index}`, expense.billFile.name);
                }
                // If we have a data URL from previous processing
                else if (expense.billFile.dataUrl) {
                    try {
                        // Convert data URL to Blob
                        const dataUrl = expense.billFile.dataUrl;
                        const byteString = atob(dataUrl.split(',')[1]);
                        const mimeString = dataUrl.split(',')[0].split(':')[1].split(';')[0];
                        const ab = new ArrayBuffer(byteString.length);
                        const ia = new Uint8Array(ab);
                        
                        for (let i = 0; i < byteString.length; i++) {
                            ia[i] = byteString.charCodeAt(i);
                        }
                        
                        const blob = new Blob([ab], {type: mimeString});
                        formData.append('bill_file_' + index, blob, expense.billFile.name);
                        console.log(`Created and attached blob: bill_file_${index}`, expense.billFile.name);
                    } catch (err) {
                        console.error('Error converting data URL to blob:', err);
                    }
                }
            }
        });
        
        // Check if we need to get a file from the current input
        const billFileInput = document.getElementById('billFile');
        if (hasTaxiExpenses && billFileInput && billFileInput.files && billFileInput.files.length > 0) {
            // Also attach the currently selected file as a fallback
            formData.append('bill_file', billFileInput.files[0]);
            console.log('Attached current file input:', billFileInput.files[0].name);
        }
        
        // Prepare clean expense data for JSON
        const expensesData = travelExpenses.map((expense, index) => {
            // Create a clean version without large data
            let cleanExpense = { ...expense };
            
            // If it's a taxi expense, add reference to the file
            if (expense.mode === 'Taxi') {
                // Remove any large data from the JSON
                if (cleanExpense.billFile) {
                    const { dataUrl, file, ...billFileMetadata } = cleanExpense.billFile;
                    cleanExpense.billFile = billFileMetadata;
                }
                
                // Add file reference flags
                cleanExpense.hasBillFile = true;
                cleanExpense.billFileIndex = index;
            }
            
            return cleanExpense;
        });
        
        // Add the expenses data as JSON
        formData.append('expenses', JSON.stringify(expensesData));
        
        // Log what we're sending
        console.log('Sending expenses with formData:', {
            hasTaxiExpenses,
            expenseCount: expensesData.length,
            formDataKeys: Array.from(formData.keys())
        });
        
        // Send the data to the server
        fetch('save_travel_expenses.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // Reset button state
            saveAllExpensesBtn.innerHTML = originalBtnText;
            saveAllExpensesBtn.disabled = false;
            
            if (data.success) {
                showNotification('All expenses saved successfully', 'success');
                
                // Update the main dashboard stats if possible
                try {
                    const expensesTotal = travelExpenses.reduce((sum, expense) => sum + expense.amount, 0);
                    const statCard = document.querySelector('.stat-card:nth-child(5)');
                    
                    if (statCard) {
                        const amountElement = statCard.querySelector('h3');
                        if (amountElement) {
                            const currentAmountText = amountElement.textContent;
                            const currentAmount = parseFloat(currentAmountText.replace('₹', '').replace(',', ''));
                            if (!isNaN(currentAmount)) {
                                const newAmount = currentAmount + expensesTotal;
                                
                                // Update the displayed amount
                                amountElement.textContent = '₹' + newAmount.toFixed(0);
                                
                                // Update the progress bar
                                const progressBar = statCard.querySelector('.progress-bar');
                                if (progressBar) {
                                    const newPercentage = Math.min(Math.floor((newAmount / 10000) * 100), 100);
                                    progressBar.style.width = newPercentage + '%';
                                    progressBar.setAttribute('aria-valuenow', newPercentage);
                                    
                                    // Update the percentage text
                                    const percentageText = statCard.querySelector('.stat-goal-text small:last-child');
                                    if (percentageText) {
                                        percentageText.textContent = newPercentage + '% Used';
                                    }
                                }
                            }
                        }
                    }
                } catch (e) {
                    console.error('Error updating dashboard stats:', e);
                }
                
                // Show approval notification if needed
                if (data.approval_needed) {
                    showNotification('Expenses require manager approval', 'info');
                }
                
                // Close modal
                if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
                    $(travelExpenseModal).modal('hide');
                } else {
                    travelExpenseModal.style.display = 'none';
                }
                
                // Clear the form and expenses array
                travelExpenses = [];
                expensesList.innerHTML = '';
                updateSummary();
                
                // Refresh the page after a short delay to show the newly added expense
                setTimeout(() => {
                    window.location.reload();
                }, 1000); // 1 second delay to allow user to see the success message
            } else {
                showNotification('Error saving expenses: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            // Reset button state
            saveAllExpensesBtn.innerHTML = originalBtnText;
            saveAllExpensesBtn.disabled = false;
            
            console.error('Error:', error);
            showNotification('Failed to save expenses: ' + error.message, 'error');
        });
    }
    
    /**
     * Shows a notification message
     * @param {string} message - The message to show
     * @param {string} type - The type of notification (success, error, warning, info)
     */
    function showNotification(message, type) {
        // Create custom toast container if it doesn't exist
        let toastContainer = document.getElementById('custom-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'custom-toast-container';
            toastContainer.style.position = 'fixed';
            toastContainer.style.top = '20px';
            toastContainer.style.right = '20px';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.style.backgroundColor = 'white';
        toast.style.borderRadius = '4px';
        toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        toast.style.padding = '15px';
        toast.style.marginBottom = '10px';
        toast.style.minWidth = '250px';
        toast.style.maxWidth = '350px';
        toast.style.position = 'relative';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        toast.style.transition = 'all 0.3s ease';
        
        // Set border color based on type
        if (type === 'success') {
            toast.style.borderLeft = '4px solid #2ecc71';
        } else if (type === 'error') {
            toast.style.borderLeft = '4px solid #e74c3c';
        } else if (type === 'warning') {
            toast.style.borderLeft = '4px solid #f39c12';
        } else if (type === 'info') {
            toast.style.borderLeft = '4px solid #3498db';
        }
        
        // Header
        const header = document.createElement('div');
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.marginBottom = '5px';
        
        const title = document.createElement('span');
        title.style.fontWeight = 'bold';
        title.textContent = type.charAt(0).toUpperCase() + type.slice(1);
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.background = 'none';
        closeBtn.style.border = 'none';
        closeBtn.style.fontSize = '20px';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.color = '#999';
        
        header.appendChild(title);
        header.appendChild(closeBtn);
        
        // Message
        const msgElement = document.createElement('div');
        msgElement.textContent = message;
        
        // Add to toast
        toast.appendChild(header);
        toast.appendChild(msgElement);
        
        // Add to container
        toastContainer.appendChild(toast);
        
        // Show animation
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 50);
        
        // Auto close after 5 seconds
        const autoCloseTimeout = setTimeout(() => {
            closeToast();
        }, 5000);
        
        // Close button handler
        closeBtn.addEventListener('click', () => {
            clearTimeout(autoCloseTimeout);
            closeToast();
        });
        
        // Close function
        function closeToast() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            
            // Remove from DOM after animation
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
        
        // Use toastr if available
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                positionClass: 'toast-top-right',
                closeButton: true,
                progressBar: true,
                timeOut: 5000
            };
            toastr[type](message);
            return;
        }
        
        // Use Bootstrap toast if available - as a fallback
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Toast !== 'undefined') {
            // Create a toast element
            const toastElement = document.createElement('div');
            toastElement.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type}`;
            toastElement.role = 'alert';
            toastElement.setAttribute('aria-live', 'assertive');
            toastElement.setAttribute('aria-atomic', 'true');
            
            toastElement.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            // Add to document
            document.body.appendChild(toastElement);
            
            // Initialize and show toast
            const bsToast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 5000
            });
            bsToast.show();
            
            // Remove after hidden
            toastElement.addEventListener('hidden.bs.toast', function () {
                toastElement.remove();
            });
            
            return;
        }
    }
    
    // Add a custom validator for the distance field
    distanceInput.addEventListener('input', function() {
        // Ensure the value is positive
        if (this.value < 0) {
            this.value = 0;
        }
        
        // Auto-calculate expense based on distance for Bike and Car
        const currentMode = modeInput.value;
        if ((currentMode === 'Bike' || currentMode === 'Car') && this.value) {
            // Make sure expense field is read-only
            expenseInput.readOnly = true;
            
            const distance = parseFloat(this.value);
            if (!isNaN(distance)) {
                const ratePerKm = currentMode === 'Bike' ? 3.5 : 10; // 3.5 for Bike, 10 for Car
                const calculatedExpense = distance * ratePerKm;
                expenseInput.value = calculatedExpense.toFixed(2);
            }
        }
    });
    
    // Add a custom validator for the expense field
    expenseInput.addEventListener('input', function() {
        // Ensure the value is positive
        if (this.value < 0) {
            this.value = 0;
        }
    });
}); 