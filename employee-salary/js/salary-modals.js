/* Salary Management - Modal JavaScript Functions */

// Modal state management
let currentEmployeeData = null;
let currentStep = 1;
let bulkProcessData = {
    selectedEmployees: [],
    settings: {},
    results: null
};

/**
 * Open salary detail modal for an employee
 * Currently showing dummy data - will be replaced with API calls later
 */
async function openSalaryDetailModal(employeeId) {
    try {
        const modal = document.getElementById('salaryDetailModal');
        if (!modal) return;
        
        // Show loading state
        showModalLoading(modal);
        modal.classList.add('show');
        modal.style.display = 'flex';
        
        // Simulate API delay
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Generate dummy employee details
        const employeeData = generateDummyEmployeeDetails(employeeId);
        
        currentEmployeeData = employeeData;
        populateSalaryDetailModal(employeeData);
        loadSalaryHistory(employeeId);
        
    } catch (error) {
        console.error('Error opening salary detail modal:', error);
        showErrorMessage('Failed to load employee salary details');
        closeSalaryDetailModal();
    }
}

/**
 * Generate dummy employee details for demonstration
 * TODO: Replace with actual API call to get-employee-salary-details.php
 */
function generateDummyEmployeeDetails(employeeId) {
    const departments = ['HR', 'Engineering', 'Sales', 'Marketing', 'Finance'];
    const positions = ['Manager', 'Senior Executive', 'Executive', 'Assistant Manager', 'Team Lead'];
    
    const baseSalary = 25000 + (Math.random() * 75000);
    const workingDays = 22;
    const presentDays = Math.floor(18 + Math.random() * 4);
    const absentDays = workingDays - presentDays;
    const lateDays = Math.floor(Math.random() * 5);
    const leaveDays = Math.floor(Math.random() * 3);
    const perDayRate = baseSalary / 30;
    
    const overtimeAmount = Math.random() * 5000;
    const travelAmount = Math.random() * 3000;
    const bonusAmount = Math.random() * 8000;
    const otherEarnings = Math.random() * 2000;
    const totalEarnings = baseSalary + overtimeAmount + travelAmount + bonusAmount + otherEarnings;
    
    const absenceDeduction = absentDays * perDayRate;
    const lateDeduction = lateDays * 500;
    const taxDeduction = baseSalary * 0.1;
    const pfDeduction = baseSalary * 0.12;
    const esiDeduction = baseSalary * 0.0075;
    const otherDeductions = Math.random() * 1000;
    const totalDeductions = absenceDeduction + lateDeduction + taxDeduction + pfDeduction + esiDeduction + otherDeductions;
    
    return {
        id: employeeId,
        name: `Employee ${employeeId.toString().padStart(2, '0')}`,
        employee_id: `EMP${(1000 + parseInt(employeeId)).toString()}`,
        department: departments[Math.floor(Math.random() * departments.length)],
        position: positions[Math.floor(Math.random() * positions.length)],
        status: 'Active',
        avatar: null,
        
        // Salary details
        base_salary: baseSalary,
        per_day_rate: perDayRate,
        working_days: workingDays,
        present_days: presentDays,
        absent_days: absentDays,
        late_days: lateDays,
        leave_days: leaveDays,
        
        // Earnings
        basic_earning: baseSalary,
        overtime_amount: overtimeAmount,
        travel_amount: travelAmount,
        bonus_amount: bonusAmount,
        other_earnings: otherEarnings,
        total_earnings: totalEarnings,
        
        // Deductions
        absence_deduction: absenceDeduction,
        late_deduction: lateDeduction,
        tax_deduction: taxDeduction,
        pf_deduction: pfDeduction,
        esi_deduction: esiDeduction,
        other_deductions: otherDeductions,
        total_deductions: totalDeductions,
        
        // Net salary
        net_salary: totalEarnings - totalDeductions
    };
}

/**
 * Populate salary detail modal with employee data
 */
function populateSalaryDetailModal(employee) {
    // Basic employee info
    document.getElementById('modalEmployeeName').textContent = employee.name;
    document.getElementById('employeeFullName').textContent = employee.name;
    document.getElementById('employeeId').textContent = employee.employee_id;
    document.getElementById('employeeDepartment').textContent = employee.department || 'N/A';
    document.getElementById('employeePosition').textContent = employee.position || 'N/A';
    document.getElementById('employeeStatus').textContent = employee.status;
    document.getElementById('salaryPeriod').textContent = formatMonthYear(getCurrentMonth());
    
    // Set employee avatar
    const avatarImg = document.getElementById('employeeAvatar');
    if (avatarImg) {
        avatarImg.src = employee.avatar || '../images/default-avatar.png';
        avatarImg.alt = employee.name;
    }
    
    // Basic salary information
    document.getElementById('baseSalaryAmount').textContent = `₹${formatNumber(employee.base_salary)}`;
    document.getElementById('perDayRate').textContent = `₹${formatNumber(employee.per_day_rate)}`;
    document.getElementById('workingDays').textContent = employee.working_days;
    document.getElementById('presentDays').textContent = employee.present_days;
    
    // Attendance details
    document.getElementById('presentDaysCount').textContent = employee.present_days;
    document.getElementById('absentDaysCount').textContent = employee.absent_days;
    document.getElementById('lateDaysCount').textContent = employee.late_days;
    document.getElementById('leaveDaysCount').textContent = employee.leave_days;
    
    // Earnings
    document.getElementById('basicEarning').textContent = `₹${formatNumber(employee.basic_earning)}`;
    document.getElementById('overtimeEarning').textContent = `₹${formatNumber(employee.overtime_amount)}`;
    document.getElementById('travelEarning').textContent = `₹${formatNumber(employee.travel_amount)}`;
    document.getElementById('bonusEarning').textContent = `₹${formatNumber(employee.bonus_amount)}`;
    document.getElementById('otherEarning').textContent = `₹${formatNumber(employee.other_earnings)}`;
    document.getElementById('totalEarnings').textContent = `₹${formatNumber(employee.total_earnings)}`;
    
    // Deductions
    document.getElementById('absenceDeduction').textContent = `₹${formatNumber(employee.absence_deduction)}`;
    document.getElementById('lateDeduction').textContent = `₹${formatNumber(employee.late_deduction)}`;
    document.getElementById('taxDeduction').textContent = `₹${formatNumber(employee.tax_deduction)}`;
    document.getElementById('pfDeduction').textContent = `₹${formatNumber(employee.pf_deduction)}`;
    document.getElementById('esiDeduction').textContent = `₹${formatNumber(employee.esi_deduction)}`;
    document.getElementById('otherDeductions').textContent = `₹${formatNumber(employee.other_deductions)}`;
    document.getElementById('totalDeductions').textContent = `₹${formatNumber(employee.total_deductions)}`;
    
    // Net salary
    document.getElementById('netEarnings').textContent = `₹${formatNumber(employee.total_earnings)}`;
    document.getElementById('netDeductions').textContent = `₹${formatNumber(employee.total_deductions)}`;
    document.getElementById('netSalaryAmount').textContent = `₹${formatNumber(employee.net_salary)}`;
}

/**
 * Load salary history for an employee
 * Currently showing dummy data - will be replaced with API call later
 */
async function loadSalaryHistory(employeeId) {
    try {
        // Generate dummy salary history
        const historyData = generateDummySalaryHistory(employeeId);
        populateSalaryHistory(historyData);
        
    } catch (error) {
        console.error('Error loading salary history:', error);
        // Don't show error for history, it's not critical
    }
}

/**
 * Generate dummy salary history
 * TODO: Replace with actual API call to get-salary-history.php
 */
function generateDummySalaryHistory(employeeId) {
    const history = [];
    const statuses = ['processed', 'approved', 'pending'];
    
    // Generate last 6 months of history
    for (let i = 1; i <= 6; i++) {
        const date = new Date();
        date.setMonth(date.getMonth() - i);
        const monthYear = date.toISOString().substr(0, 7);
        
        const baseSalary = 25000 + (Math.random() * 75000);
        const netSalary = baseSalary + (Math.random() * 10000) - (Math.random() * 15000);
        
        history.push({
            month_year: monthYear,
            base_salary: baseSalary,
            net_salary: Math.max(netSalary, baseSalary * 0.7), // Ensure net salary is reasonable
            status: statuses[Math.floor(Math.random() * statuses.length)]
        });
    }
    
    return history;
}

/**
 * Populate salary history table
 */
function populateSalaryHistory(history) {
    const historyBody = document.getElementById('salaryHistoryBody');
    if (!historyBody) return;
    
    if (!history || history.length === 0) {
        historyBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center" style="padding: 1rem; color: #6b7280;">
                    No salary history available
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    history.forEach(record => {
        const statusClass = getStatusClass(record.status);
        html += `
            <tr>
                <td>${formatMonthYear(record.month_year)}</td>
                <td>₹${formatNumber(record.base_salary)}</td>
                <td>₹${formatNumber(record.net_salary)}</td>
                <td><span class="status-badge ${statusClass}">${record.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="downloadHistoryPayslip('${record.month_year}')">
                        <i class="fas fa-download"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    historyBody.innerHTML = html;
}

/**
 * Close salary detail modal
 */
function closeSalaryDetailModal() {
    const modal = document.getElementById('salaryDetailModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        currentEmployeeData = null;
    }
}

/**
 * Open edit salary modal
 */
function openEditSalaryModal() {
    if (!currentEmployeeData) return;
    
    // For now, redirect to edit page
    // In future, this could be a modal form
    window.location.href = `../edit_salary.php?id=${currentEmployeeData.id}&month=${getCurrentMonth()}`;
}

/**
 * Approve salary for current employee
 */
async function approveSalary() {
    if (!currentEmployeeData) return;
    
    try {
        const response = await fetch('api/approve-salary.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                employee_id: currentEmployeeData.id,
                month: getCurrentMonth()
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to approve salary');
        }
        
        const data = await response.json();
        
        if (data.success) {
            showSuccessMessage('Salary approved successfully');
            closeSalaryDetailModal();
            refreshTable();
        } else {
            throw new Error(data.message || 'Failed to approve salary');
        }
        
    } catch (error) {
        console.error('Error approving salary:', error);
        showErrorMessage('Failed to approve salary');
    }
}

/**
 * Download payslip for current employee
 */
function downloadPayslip() {
    if (!currentEmployeeData) return;
    
    window.SalaryManagement.downloadPayslip(currentEmployeeData.id);
}

/**
 * Download historical payslip
 */
function downloadHistoryPayslip(month) {
    if (!currentEmployeeData) return;
    
    const url = `api/download-payslip.php?employee_id=${currentEmployeeData.id}&month=${month}`;
    window.open(url, '_blank');
}

/**
 * Open bulk process modal
 */
function openBulkProcessModal() {
    const modal = document.getElementById('bulkProcessModal');
    if (!modal) return;
    
    // Reset state
    currentStep = 1;
    bulkProcessData = {
        selectedEmployees: [],
        settings: {},
        results: null
    };
    
    // Show modal
    modal.classList.add('show');
    modal.style.display = 'flex';
    
    // Initialize first step
    showBulkProcessStep(1);
    loadBulkEmployeeList();
}

/**
 * Close bulk process modal
 */
function closeBulkProcessModal() {
    const modal = document.getElementById('bulkProcessModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        currentStep = 1;
        bulkProcessData = {
            selectedEmployees: [],
            settings: {},
            results: null
        };
    }
}

/**
 * Show specific step in bulk process
 */
function showBulkProcessStep(step) {
    // Update step indicator
    document.querySelectorAll('.step').forEach((stepEl, index) => {
        stepEl.classList.toggle('active', index + 1 === step);
    });
    
    // Show/hide step content
    document.querySelectorAll('.step-content').forEach((content, index) => {
        content.classList.toggle('hidden', index + 1 !== step);
    });
    
    // Update buttons
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const processBtn = document.getElementById('processBtn');
    
    if (prevBtn) prevBtn.style.display = step > 1 ? 'inline-flex' : 'none';
    if (nextBtn) nextBtn.style.display = step < 3 ? 'inline-flex' : 'none';
    if (processBtn) processBtn.style.display = step === 3 ? 'inline-flex' : 'none';
    
    currentStep = step;
}

/**
 * Go to next step in bulk process
 */
function nextStep() {
    if (currentStep < 3) {
        if (validateCurrentStep()) {
            showBulkProcessStep(currentStep + 1);
            if (currentStep === 3) {
                updateBulkProcessSummary();
            }
        }
    }
}

/**
 * Go to previous step in bulk process
 */
function previousStep() {
    if (currentStep > 1) {
        showBulkProcessStep(currentStep - 1);
    }
}

/**
 * Validate current step before proceeding
 */
function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            const selectedEmployees = document.querySelectorAll('.bulk-employee-checkbox:checked');
            if (selectedEmployees.length === 0) {
                showErrorMessage('Please select at least one employee');
                return false;
            }
            bulkProcessData.selectedEmployees = Array.from(selectedEmployees).map(cb => cb.value);
            return true;
            
        case 2:
            // Collect settings
            bulkProcessData.settings = {
                includeOvertime: document.getElementById('includeOvertime')?.checked,
                includeLateDeductions: document.getElementById('includeLateDeductions')?.checked,
                includeAbsenceDeductions: document.getElementById('includeAbsenceDeductions')?.checked,
                applyPerformanceBonus: document.getElementById('applyPerformanceBonus')?.checked,
                applyFestivalBonus: document.getElementById('applyFestivalBonus')?.checked,
                applyTaxDeduction: document.getElementById('applyTaxDeduction')?.checked,
                applyPFDeduction: document.getElementById('applyPFDeduction')?.checked,
                applyESIDeduction: document.getElementById('applyESIDeduction')?.checked
            };
            return true;
            
        default:
            return true;
    }
}

/**
 * Load employee list for bulk processing
 */
async function loadBulkEmployeeList() {
    try {
        const response = await fetch(`api/get-employees-for-bulk-process.php?month=${getCurrentMonth()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to fetch employees');
        }
        
        const data = await response.json();
        
        if (data.success) {
            renderBulkEmployeeList(data.employees);
        } else {
            throw new Error(data.message || 'Failed to load employees');
        }
        
    } catch (error) {
        console.error('Error loading bulk employee list:', error);
        showErrorMessage('Failed to load employee list');
    }
}

/**
 * Render employee list for bulk processing
 */
function renderBulkEmployeeList(employees) {
    const container = document.getElementById('bulkEmployeeList');
    if (!container) return;
    
    let html = '';
    employees.forEach(employee => {
        html += `
            <div class="employee-item">
                <input type="checkbox" class="bulk-employee-checkbox" value="${employee.id}" 
                       onchange="updateBulkSelectionCount()">
                <div class="employee-info">
                    <span class="employee-name">${escapeHtml(employee.name)}</span>
                    <span class="employee-details">${employee.department} • ₹${formatNumber(employee.base_salary)}</span>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * Toggle select all for bulk processing
 */
function toggleSelectAllBulk() {
    const selectAllCheckbox = document.getElementById('selectAllBulk');
    const employeeCheckboxes = document.querySelectorAll('.bulk-employee-checkbox');
    
    employeeCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkSelectionCount();
}

/**
 * Update bulk selection count
 */
function updateBulkSelectionCount() {
    const checkedBoxes = document.querySelectorAll('.bulk-employee-checkbox:checked');
    const count = checkedBoxes.length;
    
    const selectedCountElements = document.querySelectorAll('.selected-count');
    selectedCountElements.forEach(element => {
        element.textContent = `${count} selected`;
    });
}

/**
 * Update bulk process summary
 */
function updateBulkProcessSummary() {
    const selectedCount = bulkProcessData.selectedEmployees.length;
    
    document.getElementById('selectedEmployeeCount').textContent = selectedCount;
    
    // You can calculate and show more summary data here
    // For now, showing placeholder values
    document.getElementById('totalBaseSalary').textContent = '₹0';
    document.getElementById('estimatedNetPayable').textContent = '₹0';
}

/**
 * Process bulk salaries
 */
async function processBulkSalaries() {
    try {
        const processBtn = document.getElementById('processBtn');
        if (processBtn) {
            processBtn.disabled = true;
            processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }
        
        // Show processing status
        const processingStatus = document.getElementById('processingStatus');
        if (processingStatus) {
            processingStatus.classList.remove('hidden');
        }
        
        const response = await fetch('api/process-bulk-salaries.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                employee_ids: bulkProcessData.selectedEmployees,
                month: getCurrentMonth(),
                settings: bulkProcessData.settings
            })
        });
        
        if (!response.ok) {
            throw new Error('Bulk processing failed');
        }
        
        const data = await response.json();
        
        if (data.success) {
            bulkProcessData.results = data.results;
            showSuccessMessage('Bulk salary processing completed successfully');
            closeBulkProcessModal();
            refreshTable();
        } else {
            throw new Error(data.message || 'Bulk processing failed');
        }
        
    } catch (error) {
        console.error('Error in bulk processing:', error);
        showErrorMessage('Failed to process bulk salaries');
    } finally {
        const processBtn = document.getElementById('processBtn');
        if (processBtn) {
            processBtn.disabled = false;
            processBtn.innerHTML = '<i class="fas fa-cogs"></i> Process Salaries';
        }
    }
}

/**
 * Open payroll wizard modal
 */
function openPayrollWizardModal() {
    const modal = document.getElementById('payrollWizardModal');
    if (!modal) return;
    
    modal.classList.add('show');
    modal.style.display = 'flex';
    
    // Initialize wizard
    initializePayrollWizard();
}

/**
 * Close payroll wizard modal
 */
function closePayrollWizardModal() {
    const modal = document.getElementById('payrollWizardModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
    }
}

/**
 * Initialize payroll wizard
 */
function initializePayrollWizard() {
    // Set current month
    const monthInput = document.getElementById('payrollMonth');
    if (monthInput) {
        monthInput.value = getCurrentMonth();
    }
    
    // Set payment date to end of month
    const paymentDateInput = document.getElementById('paymentDate');
    if (paymentDateInput) {
        const endOfMonth = new Date();
        endOfMonth.setMonth(endOfMonth.getMonth() + 1, 0);
        paymentDateInput.value = endOfMonth.toISOString().split('T')[0];
    }
    
    // Load preview data
    loadPayrollPreview();
}

/**
 * Load payroll preview data
 */
async function loadPayrollPreview() {
    try {
        const response = await fetch(`api/get-payroll-preview.php?month=${getCurrentMonth()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to fetch preview data');
        }
        
        const data = await response.json();
        
        if (data.success) {
            updatePayrollPreview(data.preview);
        }
        
    } catch (error) {
        console.error('Error loading payroll preview:', error);
        // Don't show error for preview, it's not critical
    }
}

/**
 * Update payroll preview
 */
function updatePayrollPreview(preview) {
    if (!preview) return;
    
    document.getElementById('previewEmployeeCount').textContent = preview.employee_count || '0';
    document.getElementById('previewSalaryBudget').textContent = `₹${formatNumber(preview.total_budget || 0)}`;
    
    // Estimate processing time based on employee count
    const employeeCount = preview.employee_count || 0;
    const estimatedMinutes = Math.ceil(employeeCount / 10); // Rough estimate
    document.getElementById('previewProcessingTime').textContent = `${estimatedMinutes}-${estimatedMinutes + 1} minutes`;
}

/**
 * Start payroll processing
 */
async function startPayrollProcessing() {
    try {
        // Hide setup sections and show processing
        document.querySelector('.wizard-intro').classList.add('hidden');
        document.querySelector('.wizard-setup').classList.add('hidden');
        document.querySelector('.wizard-preview').classList.add('hidden');
        document.getElementById('wizardProcessingSection').classList.remove('hidden');
        
        // Disable start button
        const startBtn = document.getElementById('startProcessingBtn');
        if (startBtn) {
            startBtn.style.display = 'none';
        }
        
        // Start processing animation
        simulatePayrollProcessing();
        
        // Collect configuration
        const config = {
            month: document.getElementById('payrollMonth')?.value || getCurrentMonth(),
            payment_date: document.getElementById('paymentDate')?.value,
            working_days_override: document.getElementById('workingDaysOverride')?.value,
            settings: {
                enable_late_deduction: document.getElementById('enableLateDeduction')?.checked,
                enable_overtime_payment: document.getElementById('enableOvertimePayment')?.checked,
                enable_half_day_rule: document.getElementById('enableHalfDayRule')?.checked,
                enable_tds: document.getElementById('enableTDS')?.checked,
                enable_pf: document.getElementById('enablePF')?.checked,
                enable_esi: document.getElementById('enableESI')?.checked
            },
            output_options: {
                generate_payslips: document.getElementById('generatePayslips')?.checked,
                generate_summary_report: document.getElementById('generateSummaryReport')?.checked,
                generate_tax_report: document.getElementById('generateTaxReport')?.checked,
                generate_bank_file: document.getElementById('generateBankFile')?.checked,
                send_email_notifications: document.getElementById('sendEmailNotifications')?.checked
            }
        };
        
        const response = await fetch('api/process-full-payroll.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(config)
        });
        
        if (!response.ok) {
            throw new Error('Payroll processing failed');
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Show results
            setTimeout(() => {
                showPayrollResults(data.results);
            }, 5000); // Wait for animation to complete
        } else {
            throw new Error(data.message || 'Payroll processing failed');
        }
        
    } catch (error) {
        console.error('Error in payroll processing:', error);
        showErrorMessage('Failed to process payroll');
        closePayrollWizardModal();
    }
}

/**
 * Simulate payroll processing with visual feedback
 */
function simulatePayrollProcessing() {
    const steps = [
        { id: 'step1', text: 'Calculating attendance data...', duration: 1000 },
        { id: 'step2', text: 'Processing salary calculations...', duration: 1500 },
        { id: 'step3', text: 'Applying deductions...', duration: 1000 },
        { id: 'step4', text: 'Generating reports...', duration: 1500 },
        { id: 'step5', text: 'Finalizing payroll...', duration: 1000 }
    ];
    
    let currentStepIndex = 0;
    let totalProgress = 0;
    
    function processNextStep() {
        if (currentStepIndex < steps.length) {
            const step = steps[currentStepIndex];
            const stepElement = document.getElementById(step.id);
            
            // Mark current step as active
            if (stepElement) {
                const icon = stepElement.querySelector('.step-icon');
                icon.className = 'fas fa-spinner fa-spin step-icon';
                stepElement.classList.add('active');
            }
            
            // Update progress
            totalProgress = ((currentStepIndex + 1) / steps.length) * 100;
            const progressFill = document.getElementById('wizardProgressFill');
            const progressText = document.getElementById('wizardProgressText');
            
            if (progressFill) progressFill.style.width = `${totalProgress}%`;
            if (progressText) progressText.textContent = `${Math.round(totalProgress)}% Complete`;
            
            setTimeout(() => {
                // Mark step as complete
                if (stepElement) {
                    const icon = stepElement.querySelector('.step-icon');
                    icon.className = 'fas fa-check step-icon';
                    stepElement.classList.remove('active');
                    stepElement.classList.add('complete');
                }
                
                currentStepIndex++;
                processNextStep();
            }, step.duration);
        }
    }
    
    processNextStep();
}

/**
 * Show payroll processing results
 */
function showPayrollResults(results) {
    // Hide processing section
    document.getElementById('wizardProcessingSection').classList.add('hidden');
    
    // Show results section
    const resultsSection = document.getElementById('wizardResultsSection');
    resultsSection.classList.remove('hidden');
    
    // Update result values
    document.getElementById('resultEmployeeCount').textContent = results.employee_count || '0';
    document.getElementById('resultProcessingTime').textContent = results.processing_time || '0:00';
    document.getElementById('resultNetPayable').textContent = `₹${formatNumber(results.total_net_payable || 0)}`;
    
    // Update download links
    if (results.download_links) {
        const payslipsLink = document.getElementById('downloadPayslips');
        const summaryLink = document.getElementById('downloadSummary');
        const bankFileLink = document.getElementById('downloadBankFile');
        
        if (payslipsLink && results.download_links.payslips) {
            payslipsLink.href = results.download_links.payslips;
        }
        if (summaryLink && results.download_links.summary) {
            summaryLink.href = results.download_links.summary;
        }
        if (bankFileLink && results.download_links.bank_file) {
            bankFileLink.href = results.download_links.bank_file;
        }
    }
    
    // Show complete button
    const completeBtn = document.getElementById('completeBtn');
    if (completeBtn) {
        completeBtn.classList.remove('hidden');
    }
}

/**
 * Complete payroll processing
 */
function completePayrollProcessing() {
    closePayrollWizardModal();
    refreshTable();
    showSuccessMessage('Payroll processing completed successfully!');
}

/**
 * Show modal loading state
 */
function showModalLoading(modal) {
    const modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.innerHTML = `
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #3b82f6; margin-bottom: 1rem;"></i>
                <p style="color: #6b7280;">Loading employee details...</p>
            </div>
        `;
    }
}

/**
 * Get current month from URL or default
 */
function getCurrentMonth() {
    const urlParams = new URLSearchParams(window.location.search);
    const monthFromUrl = urlParams.get('month');
    
    if (monthFromUrl) {
        return monthFromUrl;
    }
    
    const monthSelect = document.getElementById('monthSelect');
    if (monthSelect && monthSelect.value) {
        return monthSelect.value;
    }
    
    return new Date().toISOString().substr(0, 7);
}

/**
 * Format month year for display
 */
function formatMonthYear(monthString) {
    const date = new Date(monthString + '-01');
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

// Export functions for global access
window.SalaryModals = {
    openSalaryDetailModal,
    closeSalaryDetailModal,
    openEditSalaryModal,
    approveSalary,
    downloadPayslip,
    openBulkProcessModal,
    closeBulkProcessModal,
    nextStep,
    previousStep,
    toggleSelectAllBulk,
    updateBulkSelectionCount,
    processBulkSalaries,
    openPayrollWizardModal,
    closePayrollWizardModal,
    startPayrollProcessing,
    completePayrollProcessing
};

// Make functions globally available
Object.assign(window, window.SalaryModals);