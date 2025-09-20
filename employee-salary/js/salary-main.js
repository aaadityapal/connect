/* Employee Salary Management - Main JavaScript */

// Global variables
let currentPage = 1;
let totalPages = 1;
let employeesPerPage = 30;
let totalEmployees = 0;
let currentFilters = {
    search: '',
    role: '',
    status: ''
};

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeSalaryPage();
    setupEventListeners();
    loadSalaryData();
});

/**
 * Initialize the salary management page
 */
function initializeSalaryPage() {
    console.log('Initializing Employee Salary Management...');
    
    // Set current month if not specified
    const monthSelect = document.getElementById('monthSelect');
    if (monthSelect && !monthSelect.value) {
        monthSelect.value = new Date().toISOString().substr(0, 7);
    }
    
    // Initialize tooltips if needed
    initializeTooltips();
}

/**
 * Setup event listeners for the page
 */
function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('employeeSearch');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentFilters.search = this.value;
                currentPage = 1;
                loadSalaryData();
            }, 500);
        });
    }
    
    // Filter functionality
    const roleFilter = document.getElementById('roleFilter');
    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            currentFilters.role = this.value;
            currentPage = 1;
            loadSalaryData();
        });
    }
    
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            currentFilters.status = this.value;
            currentPage = 1;
            loadSalaryData();
        });
    }
    
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('employeeSearch');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

/**
 * Get current month from URL parameter or month selector
 */
function getCurrentMonth() {
    // First check URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const monthFromUrl = urlParams.get('month');
    
    if (monthFromUrl) {
        return monthFromUrl;
    }
    
    // Then check month selector
    const monthSelect = document.getElementById('monthSelect');
    if (monthSelect && monthSelect.value) {
        return monthSelect.value;
    }
    
    // Default to current month
    return new Date().toISOString().substr(0, 7);
}

/**
 * Load salary data for the current month and filters
 * Fetches real employee data from users table
 */
async function loadSalaryData() {
    try {
        showLoadingState();
        
        // Get selected month
        const selectedMonth = getCurrentMonth();
        
        // Prepare API parameters
        const params = new URLSearchParams({
            page: currentPage,
            limit: employeesPerPage,
            search: currentFilters.search,
            role: currentFilters.role,
            status: currentFilters.status,
            month: selectedMonth // Pass selected month to API
        });
        
        // Fetch real employee data from API
        const response = await fetch(`api/get_employees.php?${params}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch employee data');
        }
        
        // Generate salary data for real employees
        const salaryData = generateSalaryDataForEmployees(data.employees);
        
        renderSalaryTable(salaryData.employees);
        updatePagination(data.pagination);
        updateStatistics(data.statistics);
        
    } catch (error) {
        console.error('Error loading salary data:', error);
        showErrorMessage('Failed to load employee data. Please try again.');
        renderEmptyTable();
    } finally {
        hideLoadingState();
    }
}

/**
 * Generate salary data for real employees from users table
 * Uses real employee names and IDs, but generates dummy salary calculations
 */
function generateSalaryDataForEmployees(realEmployees) {
    const roles = ['Software Engineer', 'Sales Manager', 'Marketing Specialist', 'HR Manager', 'Finance Analyst', 'Operations Manager', 'Team Lead', 'Senior Developer'];
    const statuses = ['processed', 'pending', 'review', 'approved'];
    const employees = [];
    
    // Process real employees and add salary calculations
    realEmployees.forEach((realEmployee, index) => {
        const baseSalary = 25000 + (Math.random() * 75000); // 25k to 100k
        // Use actual working days from API response instead of hardcoded value
        const workingDays = realEmployee.working_days || 22;
        const presentDays = Math.floor(18 + Math.random() * 4); // 18-22 days
        const lateDays = Math.floor(Math.random() * 5); // 0-4 days
        const leaveDays = workingDays - presentDays;
        const overtimeHours = `${Math.floor(Math.random() * 20)}:${Math.floor(Math.random() * 60).toString().padStart(2, '0')}`;
        const overtimeAmount = Math.random() * 5000;
        const travelAllowance = Math.random() * 3000;
        const bonusAmount = Math.random() * 8000;
        const taxDeduction = baseSalary * 0.1; // 10% tax
        const pfContribution = baseSalary * 0.12; // 12% PF
        const totalDeductions = taxDeduction + pfContribution + (lateDays * 500);
        const netSalary = baseSalary + overtimeAmount + travelAllowance + bonusAmount - totalDeductions;
        
        employees.push({
            id: realEmployee.id,
            name: realEmployee.username, // Real username from users table
            employee_id: realEmployee.unique_id, // Real unique_id from users table
            role: realEmployee.role || roles[Math.floor(Math.random() * roles.length)], // Use real role or fallback
            base_salary: baseSalary,
            present_days: presentDays,
            working_days: workingDays,
            late_days: lateDays,
            leave_days: leaveDays,
            overtime_hours: overtimeHours,
            overtime_amount: overtimeAmount,
            travel_allowance: travelAllowance,
            bonus_amount: bonusAmount,
            total_deductions: totalDeductions,
            tax_deduction: taxDeduction,
            pf_contribution: pfContribution,
            net_salary: netSalary,
            status: realEmployee.status || statuses[Math.floor(Math.random() * statuses.length)],
            avatar: null // Will show initials
        });
    });
    
    return {
        success: true,
        employees: employees
    };
}

/**
 * Render the salary table with employee data
 */
function renderSalaryTable(employees) {
    const tableBody = document.getElementById('salaryTableBody');
    if (!tableBody) return;
    
    if (!employees || employees.length === 0) {
        renderEmptyTable();
        return;
    }
    
    let html = '';
    
    employees.forEach(employee => {
        const statusClass = getStatusClass(employee.status);
        const statusBadge = `<span class="status-badge ${statusClass}">${employee.status}</span>`;
        
        html += `
            <tr data-employee-id="${employee.id}">
                <td>
                    <input type="checkbox" class="employee-checkbox" value="${employee.id}" 
                           onchange="updateSelectAllState()">
                </td>
                <td>
                    <div class="employee-info">
                        <div class="employee-avatar">
                            ${employee.avatar ? 
                                `<img src="${employee.avatar}" alt="${employee.name}">` : 
                                getInitials(employee.name)
                            }
                        </div>
                        <div class="employee-details">
                            <h4>${escapeHtml(employee.name)}</h4>
                            <p>ID: ${escapeHtml(employee.employee_id)}</p>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(employee.role || 'N/A')}</td>
                <td class="amount neutral">₹${formatNumber(employee.base_salary)}</td>
                <td class="text-center">${employee.working_days}</td>
                <td class="text-center">${employee.present_days}</td>
                <td class="text-center">${employee.late_days || 0}</td>
                <td class="text-center">${employee.leave_days || 0}</td>
                <td class="text-center">${employee.overtime_hours || '0:00'}</td>
                <td class="amount positive">₹${formatNumber(employee.overtime_amount || 0)}</td>
                <td class="amount positive">₹${formatNumber(employee.travel_allowance || 0)}</td>
                <td class="amount positive">₹${formatNumber(employee.bonus_amount || 0)}</td>
                <td class="amount negative">₹${formatNumber(employee.total_deductions)}</td>
                <td class="amount negative">₹${formatNumber(employee.tax_deduction || 0)}</td>
                <td class="amount negative">₹${formatNumber(employee.pf_contribution || 0)}</td>
                <td class="amount positive">₹${formatNumber(employee.net_salary)}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn view" onclick="viewEmployeeSalary(${employee.id})" 
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit" onclick="editEmployeeSalary(${employee.id})" 
                                title="Edit Salary">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn" onclick="downloadPayslip(${employee.id})" 
                                title="Download Payslip">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    
    // Add animation to rows
    setTimeout(() => {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            setTimeout(() => {
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
    }, 100);
}

/**
 * Render empty table state
 */
function renderEmptyTable() {
    const tableBody = document.getElementById('salaryTableBody');
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="18" class="text-center" style="padding: 3rem 1rem;">
                <div style="color: #6b7280; font-size: 1rem;">
                    <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No employees found matching your criteria.
                    <br>
                    <small style="margin-top: 0.5rem; display: block;">
                        Try adjusting your search or filter settings.
                    </small>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Update pagination controls
 */
function updatePagination(pagination) {
    if (!pagination) return;
    
    currentPage = pagination.current_page;
    totalPages = pagination.total_pages;
    totalEmployees = pagination.total_records;
    
    // Update pagination info
    const showingStart = document.getElementById('showingStart');
    const showingEnd = document.getElementById('showingEnd');
    const totalRecords = document.getElementById('totalRecords');
    
    if (showingStart) showingStart.textContent = pagination.start_record;
    if (showingEnd) showingEnd.textContent = pagination.end_record;
    if (totalRecords) totalRecords.textContent = pagination.total_records;
    
    // Update pagination buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    if (prevBtn) {
        prevBtn.disabled = currentPage <= 1;
        prevBtn.style.opacity = currentPage <= 1 ? '0.5' : '1';
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
        nextBtn.style.opacity = currentPage >= totalPages ? '0.5' : '1';
    }
    
    // Generate page numbers
    generatePageNumbers();
}

/**
 * Generate page number buttons
 */
function generatePageNumbers() {
    const pageNumbers = document.getElementById('pageNumbers');
    if (!pageNumbers) return;
    
    let html = '';
    
    // Calculate page range to show
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    
    // Show first page if not in range
    if (startPage > 1) {
        html += `<a href="#" class="page-number" onclick="goToPage(1)">1</a>`;
        if (startPage > 2) {
            html += `<span class="page-ellipsis">...</span>`;
        }
    }
    
    // Show page range
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPage ? 'active' : '';
        html += `<a href="#" class="page-number ${activeClass}" onclick="goToPage(${i})">${i}</a>`;
    }
    
    // Show last page if not in range
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="page-ellipsis">...</span>`;
        }
        html += `<a href="#" class="page-number" onclick="goToPage(${totalPages})">${totalPages}</a>`;
    }
    
    pageNumbers.innerHTML = html;
}

/**
 * Update statistics cards
 */
function updateStatistics(statistics) {
    if (!statistics) return;
    
    // Update stat cards if needed
    // This can be expanded based on the returned statistics
}

/**
 * Navigate to specific page
 */
function goToPage(page) {
    if (page >= 1 && page <= totalPages && page !== currentPage) {
        currentPage = page;
        loadSalaryData();
    }
}

/**
 * Navigate to previous page
 */
function previousPage() {
    if (currentPage > 1) {
        goToPage(currentPage - 1);
    }
}

/**
 * Navigate to next page
 */
function nextPage() {
    if (currentPage < totalPages) {
        goToPage(currentPage + 1);
    }
}

/**
 * Toggle select all checkboxes
 */
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
    
    employeeCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelectedCount();
}

/**
 * Update select all state based on individual checkboxes
 */
function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
    const checkedBoxes = document.querySelectorAll('.employee-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (checkedBoxes.length === employeeCheckboxes.length) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }
    
    updateSelectedCount();
}

/**
 * Update selected employee count
 */
function updateSelectedCount() {
    const checkedBoxes = document.querySelectorAll('.employee-checkbox:checked');
    const count = checkedBoxes.length;
    
    // Update any selected count displays
    const selectedCountElements = document.querySelectorAll('.selected-count');
    selectedCountElements.forEach(element => {
        element.textContent = `${count} selected`;
    });
}

/**
 * Show loading state
 */
function showLoadingState() {
    const tableBody = document.getElementById('salaryTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="18" class="loading-row">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        Loading employee salary data...
                    </div>
                </td>
            </tr>
        `;
    }
}

/**
 * Hide loading state
 */
function hideLoadingState() {
    // Loading state will be replaced by actual data or empty state
}

/**
 * Refresh the table data
 */
function refreshTable() {
    loadSalaryData();
    showSuccessMessage('Salary data refreshed successfully');
}

/**
 * Export salary data
 * Uses real employee data with generated salary calculations
 */
async function exportSalaryData() {
    try {
        // TODO: Implement actual export functionality with backend API
        showSuccessMessage('Export functionality will be implemented with backend API');
        
        // For now, show a placeholder message
        alert('Export feature will be available once backend API is implemented');
        
        /* Future implementation:
        const monthSelect = document.getElementById('monthSelect');
        const selectedMonth = monthSelect ? monthSelect.value : new Date().toISOString().substr(0, 7);
        
        const params = new URLSearchParams({
            month: selectedMonth,
            search: currentFilters.search,
            role: currentFilters.role,
            status: currentFilters.status,
            format: 'excel'
        });
        
        const response = await fetch(`api/export-salary-data.php?${params}`, {
            method: 'GET'
        });
        
        if (!response.ok) {
            throw new Error('Export failed');
        }
        
        // Download the file
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `salary-data-${selectedMonth}.xlsx`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showSuccessMessage('Salary data exported successfully');
        */
        
    } catch (error) {
        console.error('Export error:', error);
        showErrorMessage('Failed to export salary data');
    }
}

/**
 * View employee salary details
 */
function viewEmployeeSalary(employeeId) {
    // This will be implemented in salary-modals.js
    openSalaryDetailModal(employeeId);
}

/**
 * Edit employee salary
 */
function editEmployeeSalary(employeeId) {
    // This will be implemented in salary-modals.js
    openEditSalaryModal(employeeId);
}

/**
 * Download employee payslip
 * Currently shows placeholder - will be replaced with API call later
 */
async function downloadPayslip(employeeId) {
    try {
        // TODO: Replace with actual API call to download-payslip.php
        showSuccessMessage(`Payslip download for Employee ${employeeId} will be implemented with backend API`);
        
        // For now, show a placeholder message
        alert(`Payslip download for Employee ${employeeId} will be available once backend API is implemented`);
        
        /* Future implementation:
        const monthSelect = document.getElementById('monthSelect');
        const selectedMonth = monthSelect ? monthSelect.value : new Date().toISOString().substr(0, 7);
        
        const response = await fetch(`api/download-payslip.php?employee_id=${employeeId}&month=${selectedMonth}`, {
            method: 'GET'
        });
        
        if (!response.ok) {
            throw new Error('Download failed');
        }
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `payslip-${employeeId}-${selectedMonth}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showSuccessMessage('Payslip downloaded successfully');
        */
        
    } catch (error) {
        console.error('Download error:', error);
        showErrorMessage('Failed to download payslip');
    }
}

/**
 * Open bulk process modal
 */
function openBulkProcessModal() {
    // This will be implemented in salary-modals.js
    const modal = document.getElementById('bulkProcessModal');
    if (modal) {
        modal.classList.add('show');
        modal.style.display = 'flex';
    }
}

/**
 * Open payroll wizard modal
 */
function openPayrollWizard() {
    // This will be implemented in salary-modals.js
    const modal = document.getElementById('payrollWizardModal');
    if (modal) {
        modal.classList.add('show');
        modal.style.display = 'flex';
    }
}

/**
 * Close all modals
 */
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.remove('show');
        modal.style.display = 'none';
    });
}

/**
 * Utility function to get status CSS class
 */
function getStatusClass(status) {
    const statusClasses = {
        'processed': 'status-processed',
        'pending': 'status-pending',
        'review': 'status-review',
        'approved': 'status-processed'
    };
    return statusClasses[status] || 'status-pending';
}

/**
 * Utility function to get initials from name
 */
function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substr(0, 2);
}

/**
 * Utility function to escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Utility function to format numbers
 */
function formatNumber(number) {
    return new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number || 0);
}

/**
 * Show success message
 */
function showSuccessMessage(message) {
    // You can implement a toast notification system here
    console.log('Success:', message);
}

/**
 * Show error message
 */
function showErrorMessage(message) {
    // You can implement a toast notification system here
    console.error('Error:', message);
    alert(message); // Temporary solution
}

/**
 * Initialize tooltips (if using a tooltip library)
 */
function initializeTooltips() {
    // Initialize tooltips if using Bootstrap or another library
    // Example: $('[data-toggle="tooltip"]').tooltip();
}

// Export functions for use in other scripts
window.SalaryManagement = {
    loadSalaryData,
    refreshTable,
    exportSalaryData,
    viewEmployeeSalary,
    editEmployeeSalary,
    downloadPayslip,
    openBulkProcessModal,
    openPayrollWizard,
    goToPage,
    previousPage,
    nextPage,
    toggleSelectAll,
    updateSelectAllState
};