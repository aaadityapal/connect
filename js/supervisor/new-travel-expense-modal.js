/**
 * New Travel Expense Modal JS
 * Handles the functionality for adding and managing travel expenses
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize event handlers for return trip confirmation modal
    const returnTripYesBtn = document.getElementById('returnTripYesBtn');
    const returnTripNoBtn = document.getElementById('returnTripNoBtn');
    
    if (returnTripYesBtn && returnTripNoBtn) {
        // These will be set up dynamically when the expense is added
        // See the addExpense function for the implementation
    }
    // DOM elements
    const addTravelExpenseBtn = document.getElementById('addTravelExpenseBtn');
    const newTravelExpenseModal = document.getElementById('newTravelExpenseModal');
    
    // Check if we're on a page that has the travel expense functionality
    if (!addTravelExpenseBtn || !newTravelExpenseModal) {
        console.log('New travel expense modal functionality not available on this page.');
        return; // Exit gracefully without error
    }
    
    console.log('New travel expense modal functionality initialized.');
    
    // Form elements
    const newTravelExpenseForm = document.getElementById('newTravelExpenseForm');
    const travelDateInput = document.getElementById('travelDate');
    const purposeInput = document.getElementById('purposeOfTravel');
    const fromInput = document.getElementById('fromLocation');
    const toInput = document.getElementById('toLocation');
    const modeInput = document.getElementById('modeOfTransport');
    const distanceInput = document.getElementById('distance');
    const amountInput = document.getElementById('amount');
    const notesInput = document.getElementById('notes');
    const billFileInput = document.getElementById('billFile');
    const billUploadContainer = document.getElementById('billUploadContainer');
    const meterPhotosContainer = document.getElementById('meterPhotosContainer');
    const meterStartPhotoInput = document.getElementById('meterStartPhoto');
    const meterEndPhotoInput = document.getElementById('meterEndPhoto');
    
    // Buttons
    const addExpenseBtn = document.getElementById('addExpenseBtn');
    const saveAllExpensesBtn = document.getElementById('saveAllExpensesBtn');
    
    // Table elements
    const addedExpensesTable = document.getElementById('addedExpensesTable');
    const addedExpensesTableBody = addedExpensesTable.querySelector('tbody');
    const totalExpenseAmountElement = document.getElementById('totalExpenseAmount');
    
    // Array to store expenses
    let expenses = [];
    let currentEditingIndex = null;
    
    // Set max date to today to prevent future dates
    const today = new Date();
    const todayFormatted = formatDateForInput(today);
    travelDateInput.setAttribute('max', todayFormatted);
    
    // Set min date to 15 days ago to prevent older dates
    const fifteenDaysAgo = new Date();
    fifteenDaysAgo.setDate(fifteenDaysAgo.getDate() - 15);
    const fifteenDaysAgoFormatted = formatDateForInput(fifteenDaysAgo);
    travelDateInput.setAttribute('min', fifteenDaysAgoFormatted);
    
    // Set default date to today
    travelDateInput.value = todayFormatted;
    
    // Event Listeners
    
    // Open modal when button is clicked
    addTravelExpenseBtn.addEventListener('click', function(e) {
        e.preventDefault();
        $(newTravelExpenseModal).modal('show');
    });
    
    // Handle modal shown event to check and update mode visibility
    $(newTravelExpenseModal).on('shown.bs.modal', function() {
        // Trigger mode change to ensure bill upload visibility is correct
        if (modeInput.value) {
            handleModeChange();
        }
    });
    
    // Mode of transport change
    modeInput.addEventListener('change', function() {
        handleModeChange();
    });
    
    // Distance input change
    distanceInput.addEventListener('input', function() {
        handleDistanceChange();
    });
    
    // Bill file change
    billFileInput.addEventListener('change', handleBillFileChange);
    
    // Remove bill button
    document.querySelector('.remove-bill-btn').addEventListener('click', removeBillFile);
    
    // Meter photo changes
    meterStartPhotoInput.addEventListener('change', function(e) {
        handleMeterPhotoChange(e, 'start');
    });
    
    meterEndPhotoInput.addEventListener('change', function(e) {
        handleMeterPhotoChange(e, 'end');
    });
    
    // Remove meter photo buttons
    document.querySelector('.remove-meter-start-btn').addEventListener('click', function() {
        removeMeterPhoto('start');
    });
    
    document.querySelector('.remove-meter-end-btn').addEventListener('click', function() {
        removeMeterPhoto('end');
    });
    
    // Add expense button
    addExpenseBtn.addEventListener('click', function() {
        if (validateForm()) {
            if (currentEditingIndex !== null) {
                updateExpense(currentEditingIndex);
            } else {
                addExpense();
            }
        }
    });
    
    // Save all expenses button
    saveAllExpensesBtn.addEventListener('click', saveAllExpenses);
    
    /**
     * Formats a date object for input[type=date] (YYYY-MM-DD)
     * @param {Date} date - The date to format
     * @returns {string} - Formatted date string
     */
    function formatDateForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    /**
     * Formats a date string for display
     * @param {string} dateString - The date string in YYYY-MM-DD format
     * @returns {string} - Formatted date for display
     */
    function formatDateForDisplay(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    /**
     * Formats a number as currency (₹)
     * @param {number} amount - The amount to format
     * @returns {string} - Formatted currency string
     */
    function formatCurrency(amount) {
        return '₹' + parseFloat(amount).toFixed(2);
    }
    

    
    /**
     * Handles mode of transport changes
     */
    function handleModeChange() {
        const selectedMode = modeInput.value;
        
        // Check if user is a Site Supervisor
        const userRole = document.body.getAttribute('data-user-role') || '';
        const isSiteSupervisor = userRole.toLowerCase().includes('supervisor');
        
        // Show/hide meter photos container based on mode and role
        if ((selectedMode === 'Bike' || selectedMode === 'Car') && !isSiteSupervisor) {
            meterPhotosContainer.style.display = 'flex';
        } else {
            meterPhotosContainer.style.display = 'none';
            // Reset meter photos when hidden
            removeMeterPhoto('start');
            removeMeterPhoto('end');
        }
        
        // Show/hide bill upload for Taxi, Bus, Train, Aeroplane and Other
        if (selectedMode === 'Taxi' || selectedMode === 'Bus' || selectedMode === 'Train' || selectedMode === 'Aeroplane' || selectedMode === 'Other') {
            billUploadContainer.style.display = 'block';
            // Update label based on mode
            const billLabel = document.querySelector('label[for="billFile"]');
            if (billLabel) {
                billLabel.innerHTML = `Upload ${selectedMode} Bill (Required)<span class="text-danger">*</span>`;
            }
            // Make amount field editable for these modes
            amountInput.readOnly = false;
        } else {
            billUploadContainer.style.display = 'none';
            removeBillFile();
            
            // Auto-calculate amount for Bike and Car
            if (selectedMode === 'Bike' || selectedMode === 'Car') {
                amountInput.readOnly = true;
                calculateAmount();
            } else {
                // Make amount field editable for other modes
                amountInput.readOnly = false;
            }
        }
    }
    

    
    /**
     * Calculates amount based on distance and mode
     */
    function calculateAmount() {
        const selectedMode = modeInput.value;
        const distance = parseFloat(distanceInput.value) || 0;
        
        if (selectedMode === 'Bike' || selectedMode === 'Car') {
            const ratePerKm = selectedMode === 'Bike' ? 3.5 : 10; // 3.5 for Bike, 10 for Car
            const calculatedAmount = distance * ratePerKm;
            amountInput.value = calculatedAmount.toFixed(2);
        }
    }
    
    /**
     * Handles distance input changes
     */
    function handleDistanceChange() {
        // Ensure positive value
        if (distanceInput.value < 0) {
            distanceInput.value = 0;
        }
        
        // Auto-calculate for Bike and Car
        const selectedMode = modeInput.value;
        if (selectedMode === 'Bike' || selectedMode === 'Car') {
            calculateAmount();
        }
    }
    
    /**
     * Handles bill file selection
     */
    function handleBillFileChange(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const fileName = file.name;
        const fileLabel = document.querySelector('.custom-file-label');
        const billPreview = document.querySelector('.bill-preview');
        const billThumbnail = document.querySelector('.bill-thumbnail');
        const billFileNameElem = document.querySelector('.bill-file-name');
        
        if (fileLabel) fileLabel.textContent = fileName;
        if (billFileNameElem) billFileNameElem.textContent = fileName;
        if (billPreview) billPreview.style.display = 'block';
        
        // Show thumbnail for images
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(event) {
                if (billThumbnail) {
                    billThumbnail.style.display = 'block';
                    billThumbnail.innerHTML = `<img src="${event.target.result}" class="img-thumbnail" alt="Bill preview">`;
                }
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            // Show icon for PDFs
            if (billThumbnail) {
                billThumbnail.style.display = 'block';
                billThumbnail.innerHTML = `<div class="pdf-icon"><i class="fas fa-file-pdf"></i> ${fileName}</div>`;
            }
        }
    }
    
    /**
     * Handles meter photo selection
     * @param {Event} e - The change event
     * @param {string} type - Either 'start' or 'end'
     */
    function handleMeterPhotoChange(e, type) {
        const file = e.target.files[0];
        if (!file) return;
        
        const fileName = file.name;
        const fileInput = type === 'start' ? meterStartPhotoInput : meterEndPhotoInput;
        const fileLabel = fileInput.nextElementSibling;
        const photoPreview = document.getElementById(`meter${type.charAt(0).toUpperCase() + type.slice(1)}PhotoPreview`);
        const photoThumbnail = photoPreview.querySelector('.meter-thumbnail');
        const fileNameElem = photoPreview.querySelector('.meter-file-name');
        
        if (fileLabel) fileLabel.textContent = fileName;
        if (fileNameElem) fileNameElem.textContent = fileName;
        if (photoPreview) photoPreview.style.display = 'block';
        
        // Show thumbnail for images
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(event) {
                if (photoThumbnail) {
                    photoThumbnail.style.display = 'block';
                    photoThumbnail.innerHTML = `<img src="${event.target.result}" class="img-thumbnail" alt="Meter ${type} photo preview">`;
                }
            };
            reader.readAsDataURL(file);
        }
    }
    
    /**
     * Removes the selected bill file
     */
    function removeBillFile() {
        billFileInput.value = '';
        
        const fileLabel = document.querySelector('.custom-file-label');
        const billPreview = document.querySelector('.bill-preview');
        const billThumbnail = document.querySelector('.bill-thumbnail');
        const billFileNameElem = document.querySelector('.bill-file-name');
        
        if (fileLabel) fileLabel.textContent = 'Choose file...';
        if (billPreview) billPreview.style.display = 'none';
        if (billThumbnail) {
            billThumbnail.style.display = 'none';
            billThumbnail.innerHTML = '';
        }
        if (billFileNameElem) billFileNameElem.textContent = 'No file selected';
    }
    
    /**
     * Removes the selected meter photo
     * @param {string} type - Either 'start' or 'end'
     */
    function removeMeterPhoto(type) {
        const fileInput = type === 'start' ? meterStartPhotoInput : meterEndPhotoInput;
        fileInput.value = '';
        
        const fileLabel = fileInput.nextElementSibling;
        const photoPreview = document.getElementById(`meter${type.charAt(0).toUpperCase() + type.slice(1)}PhotoPreview`);
        const photoThumbnail = photoPreview.querySelector('.meter-thumbnail');
        const fileNameElem = photoPreview.querySelector('.meter-file-name');
        
        if (fileLabel) fileLabel.textContent = 'Choose file...';
        if (photoPreview) photoPreview.style.display = 'none';
        if (photoThumbnail) {
            photoThumbnail.style.display = 'none';
            photoThumbnail.innerHTML = '';
        }
        if (fileNameElem) fileNameElem.textContent = 'No file selected';
    }
    
    /**
     * Validates the expense form
     * @returns {boolean} - Whether the form is valid
     */
    function validateForm() {
        let isValid = true;
        
        // Basic validation - check required fields
        const requiredFields = [
            { field: travelDateInput, name: 'Date' },
            { field: purposeInput, name: 'Purpose' },
            { field: fromInput, name: 'From location' },
            { field: toInput, name: 'To location' },
            { field: modeInput, name: 'Mode of transport' },
            { field: distanceInput, name: 'Distance' },
            { field: amountInput, name: 'Amount' }
        ];
        
        requiredFields.forEach(item => {
            if (!item.field.value.trim()) {
                showError(item.field, `${item.name} is required`);
                isValid = false;
            } else {
                clearError(item.field);
            }
        });
        
        // Validate date
        const selectedDate = new Date(travelDateInput.value);
        const today = new Date();
        
        // Set today's time to end of day to allow today's date
        today.setHours(23, 59, 59, 999);
        
        if (selectedDate > today) {
            showError(travelDateInput, 'Future dates are not allowed');
            isValid = false;
        }
        
        // Validate distance and amount
        if (parseFloat(distanceInput.value) <= 0) {
            showError(distanceInput, 'Distance must be greater than 0');
            isValid = false;
        }
        
        if (parseFloat(amountInput.value) <= 0) {
            showError(amountInput, 'Amount must be greater than 0');
            isValid = false;
        }
        
        // Check if user is a Site Supervisor
        const userRole = document.body.getAttribute('data-user-role') || '';
        const isSiteSupervisor = userRole.toLowerCase().includes('supervisor');
        
        // Validate meter photos only for Bike and Car (but exempt Site Supervisors)
        if ((modeInput.value === 'Bike' || modeInput.value === 'Car') && !isSiteSupervisor) {
            // Validate meter start photo
            if (!meterStartPhotoInput.files || meterStartPhotoInput.files.length === 0) {
                showError(meterStartPhotoInput, 'Meter start photo is required');
                isValid = false;
            } else {
                const file = meterStartPhotoInput.files[0];
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                
                if (!validTypes.includes(file.type)) {
                    showError(meterStartPhotoInput, 'Invalid file type. Please upload JPG or PNG only');
                    isValid = false;
                } else if (file.size > 5 * 1024 * 1024) { // 5MB max
                    showError(meterStartPhotoInput, 'File size too large. Maximum size is 5MB');
                    isValid = false;
                } else {
                    clearError(meterStartPhotoInput);
                }
            }
            
            // Validate meter end photo
            if (!meterEndPhotoInput.files || meterEndPhotoInput.files.length === 0) {
                showError(meterEndPhotoInput, 'Meter end photo is required');
                isValid = false;
            } else {
                const file = meterEndPhotoInput.files[0];
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                
                if (!validTypes.includes(file.type)) {
                    showError(meterEndPhotoInput, 'Invalid file type. Please upload JPG or PNG only');
                    isValid = false;
                } else if (file.size > 5 * 1024 * 1024) { // 5MB max
                    showError(meterEndPhotoInput, 'File size too large. Maximum size is 5MB');
                    isValid = false;
                } else {
                    clearError(meterEndPhotoInput);
                }
            }
        }
        
        // Validate bill file for Taxi, Bus, Train, Aeroplane and Other
        const requiresBill = ['Taxi', 'Bus', 'Train', 'Aeroplane', 'Other'].includes(modeInput.value);
        if (requiresBill && (!billFileInput.files || billFileInput.files.length === 0)) {
            showError(billFileInput, `Bill file is required for ${modeInput.value} expenses`);
            isValid = false;
        } else if (requiresBill && billFileInput.files && billFileInput.files.length > 0) {
            const file = billFileInput.files[0];
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            
            if (!validTypes.includes(file.type)) {
                showError(billFileInput, 'Invalid file type. Please upload JPG, PNG, or PDF only');
                isValid = false;
            } else if (file.size > 5 * 1024 * 1024) { // 5MB max
                showError(billFileInput, 'File size too large. Maximum size is 5MB');
                isValid = false;
            } else {
                clearError(billFileInput);
            }
        }
        
        return isValid;
    }
    
    /**
     * Shows an error message for a form field
     * @param {HTMLElement} field - The form field
     * @param {string} message - The error message
     */
    function showError(field, message) {
        field.classList.add('is-invalid');
        
        // Remove any existing error message
        const existingError = field.parentElement.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }
        
        // Create and append error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        field.parentElement.appendChild(errorDiv);
    }
    
    /**
     * Clears error styling and message for a form field
     * @param {HTMLElement} field - The form field
     */
    function clearError(field) {
        field.classList.remove('is-invalid');
        
        // Remove any existing error message
        const existingError = field.parentElement.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }
    }
    
    /**
     * Adds a new expense to the list
     */
    function addExpense() {
        // Create expense object
        const expense = {
            date: travelDateInput.value,
            purpose: purposeInput.value.trim(),
            from: fromInput.value.trim(),
            to: toInput.value.trim(),
            mode: modeInput.value,
            distance: parseFloat(distanceInput.value),
            amount: parseFloat(amountInput.value),
            notes: notesInput.value.trim(),
            status: 'pending'
        };
        
        // Add meter photos if provided
        if (meterStartPhotoInput.files && meterStartPhotoInput.files.length > 0) {
            const file = meterStartPhotoInput.files[0];
            expense.meterStartPhoto = {
                name: file.name,
                type: file.type,
                size: file.size,
                file: file
            };
        }
        
        if (meterEndPhotoInput.files && meterEndPhotoInput.files.length > 0) {
            const file = meterEndPhotoInput.files[0];
            expense.meterEndPhoto = {
                name: file.name,
                type: file.type,
                size: file.size,
                file: file
            };
        }
        
        // Add bill file info for Taxi, Bus, Train, Aeroplane and Other
        if (['Taxi', 'Bus', 'Train', 'Aeroplane', 'Other'].includes(modeInput.value) && billFileInput.files && billFileInput.files.length > 0) {
            const file = billFileInput.files[0];
            expense.billFile = {
                name: file.name,
                type: file.type,
                size: file.size,
                file: file
            };
        }
        
        // Add to expenses array
        expenses.push(expense);
        
        // Add to table
        addExpenseToTable(expense, expenses.length - 1);
        
        // Update total
        updateTotal();
        
        // Reset form
        resetForm();
        
        // Ask user if they want to add a return trip using a modal
        if (expense.from && expense.to) {
            // Store the current expense for later use
            window.currentExpense = expense;
            
            // Set the from and to values in the confirmation modal
            document.getElementById('returnTripFrom').textContent = expense.to;
            document.getElementById('returnTripTo').textContent = expense.from;
            
            // Show the confirmation modal
            $('#returnTripConfirmModal').modal('show');
        } else {
            // Show success message
            showNotification('Expense added successfully', 'success');
        }
        
        // Event handlers for return trip confirmation modal buttons
        document.getElementById('returnTripYesBtn').onclick = function() {
            // Hide the confirmation modal
            $('#returnTripConfirmModal').modal('hide');
            
            // Get the original expense
            const expense = window.currentExpense;
            
            // Create a new expense for the return trip
            const returnExpense = {
                date: expense.date,
                purpose: expense.purpose + " (Return)",
                from: expense.to,
                to: expense.from,
                mode: expense.mode,
                distance: expense.distance,
                amount: expense.amount,
                notes: expense.notes,
                status: 'pending'
            };
            
            // Copy meter photos if available (swap start and end)
            if (expense.mode === 'Bike' || expense.mode === 'Car') {
                if (expense.meterStartPhoto && expense.meterEndPhoto) {
                    returnExpense.meterStartPhoto = expense.meterEndPhoto;
                    returnExpense.meterEndPhoto = expense.meterStartPhoto;
                }
            }
            
            // Copy bill file if it's a mode that requires a bill
            if (['Taxi', 'Bus', 'Train', 'Aeroplane', 'Other'].includes(expense.mode) && expense.billFile) {
                returnExpense.billFile = expense.billFile;
            }
            
            // Add the return expense to the list
            expenses.push(returnExpense);
            
            // Add to table
            addExpenseToTable(returnExpense, expenses.length - 1);
            
            // Update total
            updateTotal();
            
            // Show success message
            showNotification('Expense and return trip added successfully', 'success');
            
            // Clear the stored expense
            window.currentExpense = null;
        };
        
        document.getElementById('returnTripNoBtn').onclick = function() {
            // Hide the confirmation modal
            $('#returnTripConfirmModal').modal('hide');
            
            // Show success message for single trip
            showNotification('Expense added successfully', 'success');
            
            // Clear the stored expense
            window.currentExpense = null;
        };
    }
    
    /**
     * Updates an existing expense
     * @param {number} index - The index of the expense to update
     */
    function updateExpense(index) {
        // Update expense object
        expenses[index] = {
            date: travelDateInput.value,
            purpose: purposeInput.value.trim(),
            from: fromInput.value.trim(),
            to: toInput.value.trim(),
            mode: modeInput.value,
            distance: parseFloat(distanceInput.value),
            amount: parseFloat(amountInput.value),
            notes: notesInput.value.trim(),
            status: expenses[index].status || 'pending'
        };
        
        // Update meter photos
        if (meterStartPhotoInput.files && meterStartPhotoInput.files.length > 0) {
            const file = meterStartPhotoInput.files[0];
            expenses[index].meterStartPhoto = {
                name: file.name,
                type: file.type,
                size: file.size,
                file: file
            };
        } else if (expenses[index].meterStartPhoto) {
            // Keep existing meter start photo if no new one is provided
        }
        
        if (meterEndPhotoInput.files && meterEndPhotoInput.files.length > 0) {
            const file = meterEndPhotoInput.files[0];
            expenses[index].meterEndPhoto = {
                name: file.name,
                type: file.type,
                size: file.size,
                file: file
            };
        } else if (expenses[index].meterEndPhoto) {
            // Keep existing meter end photo if no new one is provided
        }
        
        // Update bill file info for Taxi
        if (modeInput.value === 'Taxi' && billFileInput.files && billFileInput.files.length > 0) {
            const file = billFileInput.files[0];
            expenses[index].billFile = {
                name: file.name,
                type: file.type,
                size: file.size,
                file: file
            };
        } else if (expenses[index].billFile) {
            // Keep existing bill file if no new one is provided
        }
        
        // Update table row
        updateExpenseInTable(expenses[index], index);
        
        // Update total
        updateTotal();
        
        // Reset form and editing state
        resetForm();
        currentEditingIndex = null;
        addExpenseBtn.textContent = 'Add Expense';
        
        // Show success message
        showNotification('Expense updated successfully', 'success');
    }
    
    /**
     * Adds an expense to the table
     * @param {Object} expense - The expense object
     * @param {number} index - The index in the expenses array
     */
    function addExpenseToTable(expense, index) {
        // Remove "no expenses" row if present
        const noExpensesRow = addedExpensesTableBody.querySelector('.no-expenses-row');
        if (noExpensesRow) {
            noExpensesRow.remove();
        }
        
        // Create new row
        const row = document.createElement('tr');
        row.className = 'expense-row';
        row.dataset.index = index;
        
        // Format date for display
        const formattedDate = formatDateForDisplay(expense.date);
        
        // Create row content
        row.innerHTML = `
            <td>${formattedDate}</td>
            <td>${expense.purpose}</td>
            <td>${expense.from}</td>
            <td>${expense.to}</td>
            <td>${expense.mode}</td>
            <td>${expense.distance} km</td>
            <td>${formatCurrency(expense.amount)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-link btn-edit" data-index="${index}">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-link btn-delete" data-index="${index}">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;
        
        // Add to table
        addedExpensesTableBody.appendChild(row);
        
        // Add event listeners for edit and delete buttons
        row.querySelector('.btn-edit').addEventListener('click', function() {
            editExpense(parseInt(this.dataset.index));
        });
        
        row.querySelector('.btn-delete').addEventListener('click', function() {
            deleteExpense(parseInt(this.dataset.index));
        });
    }
    
    /**
     * Updates an expense in the table
     * @param {Object} expense - The updated expense object
     * @param {number} index - The index in the expenses array
     */
    function updateExpenseInTable(expense, index) {
        const row = addedExpensesTableBody.querySelector(`tr[data-index="${index}"]`);
        if (!row) return;
        
        // Format date for display
        const formattedDate = formatDateForDisplay(expense.date);
        
        // Update row cells
        const cells = row.querySelectorAll('td');
        cells[0].textContent = formattedDate;
        cells[1].textContent = expense.purpose;
        cells[2].textContent = expense.from;
        cells[3].textContent = expense.to;
        cells[4].textContent = expense.mode;
        cells[5].textContent = `${expense.distance} km`;
        cells[6].textContent = formatCurrency(expense.amount);
    }
    
    /**
     * Edits an expense
     * @param {number} index - The index of the expense to edit
     */
    function editExpense(index) {
        const expense = expenses[index];
        if (!expense) return;
        
        // Set form values
        travelDateInput.value = expense.date;
        purposeInput.value = expense.purpose;
        fromInput.value = expense.from;
        toInput.value = expense.to;
        modeInput.value = expense.mode;
        distanceInput.value = expense.distance;
        amountInput.value = expense.amount;
        notesInput.value = expense.notes || '';
        
        // Handle bill file display for Taxi
        handleModeChange();
        
        // Handle meter photos display
        if (expense.meterStartPhoto) {
            // Display meter start photo info
            const fileLabel = meterStartPhotoInput.nextElementSibling;
            const photoPreview = document.getElementById('meterStartPhotoPreview');
            const fileNameElem = photoPreview.querySelector('.meter-file-name');
            
            if (fileLabel) fileLabel.textContent = expense.meterStartPhoto.name;
            if (fileNameElem) fileNameElem.textContent = expense.meterStartPhoto.name;
            if (photoPreview) photoPreview.style.display = 'block';
        }
        
        if (expense.meterEndPhoto) {
            // Display meter end photo info
            const fileLabel = meterEndPhotoInput.nextElementSibling;
            const photoPreview = document.getElementById('meterEndPhotoPreview');
            const fileNameElem = photoPreview.querySelector('.meter-file-name');
            
            if (fileLabel) fileLabel.textContent = expense.meterEndPhoto.name;
            if (fileNameElem) fileNameElem.textContent = expense.meterEndPhoto.name;
            if (photoPreview) photoPreview.style.display = 'block';
        }
        
        // Set editing state
        currentEditingIndex = index;
        addExpenseBtn.textContent = 'Update Expense';
        
        // Scroll to form
        newTravelExpenseForm.scrollIntoView({ behavior: 'smooth' });
    }
    
    /**
     * Deletes an expense
     * @param {number} index - The index of the expense to delete
     */
    function deleteExpense(index) {
        if (!confirm('Are you sure you want to delete this expense?')) {
            return;
        }
        
        // Remove from array
        expenses.splice(index, 1);
        
        // Remove from table with animation
        const row = addedExpensesTableBody.querySelector(`tr[data-index="${index}"]`);
        if (row) {
            row.classList.add('fade-out');
            
            setTimeout(() => {
                row.remove();
                
                // Reset indices in the table
                updateTableIndices();
                
                // Show "no expenses" row if empty
                if (expenses.length === 0) {
                    addedExpensesTableBody.innerHTML = `
                        <tr class="no-expenses-row">
                            <td colspan="8" class="text-center">No expenses added yet</td>
                        </tr>
                    `;
                }
                
                // Update total
                updateTotal();
                
                // Reset editing state if deleting the currently edited expense
                if (currentEditingIndex === index) {
                    resetForm();
                    currentEditingIndex = null;
                    addExpenseBtn.textContent = 'Add Expense';
                }
                
                // Show notification
                showNotification('Expense deleted', 'info');
            }, 300);
        }
    }
    
    /**
     * Updates indices in the table after deletion
     */
    function updateTableIndices() {
        const rows = addedExpensesTableBody.querySelectorAll('tr.expense-row');
        rows.forEach((row, idx) => {
            row.dataset.index = idx;
            
            // Update edit and delete buttons
            const editBtn = row.querySelector('.btn-edit');
            const deleteBtn = row.querySelector('.btn-delete');
            
            if (editBtn) editBtn.dataset.index = idx;
            if (deleteBtn) deleteBtn.dataset.index = idx;
        });
    }
    
    /**
     * Updates the total amount
     */
    function updateTotal() {
        const total = expenses.reduce((sum, expense) => sum + expense.amount, 0);
        totalExpenseAmountElement.textContent = formatCurrency(total);
    }
    
    /**
     * Resets the form
     */
    function resetForm() {
        // Reset form fields
        newTravelExpenseForm.reset();
        
        // Reset date to today
        travelDateInput.value = formatDateForInput(new Date());
        
        // Reset bill file
        removeBillFile();
        
        // Reset meter photos
        removeMeterPhoto('start');
        removeMeterPhoto('end');
        
        // Hide bill upload container
        billUploadContainer.style.display = 'none';
        
        // Hide meter photos container
        meterPhotosContainer.style.display = 'none';
        
        // Clear any validation errors
        const invalidFields = newTravelExpenseForm.querySelectorAll('.is-invalid');
        invalidFields.forEach(field => clearError(field));
    }
    
    /**
     * Saves all expenses
     */
    function saveAllExpenses() {
        if (expenses.length === 0) {
            showNotification('No expenses to save', 'warning');
            return;
        }
        
        // Show loading state
        const originalBtnText = saveAllExpensesBtn.innerHTML;
        saveAllExpensesBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveAllExpensesBtn.disabled = true;
        
        // Create FormData for file uploads
        const formData = new FormData();
        
        // Add expense data
        const expensesData = expenses.map((expense, index) => {
            // Handle meter start photo uploads
            if (expense.meterStartPhoto && expense.meterStartPhoto.file) {
                formData.append(`meter_start_photo_${index}`, expense.meterStartPhoto.file, expense.meterStartPhoto.name);
                
                // Create a clean version without the file object
                const { file, ...meterStartPhotoMetadata } = expense.meterStartPhoto;
                expense.meterStartPhoto = meterStartPhotoMetadata;
                expense.meterStartPhotoIndex = index;
            }
            
            // Handle meter end photo uploads
            if (expense.meterEndPhoto && expense.meterEndPhoto.file) {
                formData.append(`meter_end_photo_${index}`, expense.meterEndPhoto.file, expense.meterEndPhoto.name);
                
                // Create a clean version without the file object
                const { file, ...meterEndPhotoMetadata } = expense.meterEndPhoto;
                expense.meterEndPhoto = meterEndPhotoMetadata;
                expense.meterEndPhotoIndex = index;
            }
            
            // Handle file uploads for Taxi, Bus, Train, Aeroplane and Other expenses
            if (['Taxi', 'Bus', 'Train', 'Aeroplane', 'Other'].includes(expense.mode) && expense.billFile && expense.billFile.file) {
                formData.append(`bill_file_${index}`, expense.billFile.file, expense.billFile.name);
                
                // Create a clean version without the file object
                const { file, ...billFileMetadata } = expense.billFile;
                expense.billFile = billFileMetadata;
                expense.billFileIndex = index;
            }
            
            return expense;
        });
        
        // Add the expenses data as JSON
        formData.append('expenses', JSON.stringify(expensesData));
        
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
                
                // Close modal
                $(newTravelExpenseModal).modal('hide');
                
                // Clear expenses
                expenses = [];
                
                // Refresh the page to show the newly added expenses
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
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
    }
});