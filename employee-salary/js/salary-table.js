/* Salary Management - Table-specific JavaScript */

// Table management variables
let sortColumn = null;
let sortDirection = 'asc';
let tableData = [];

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
 * Initialize table functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeTableFeatures();
});

/**
 * Initialize table-specific features
 */
function initializeTableFeatures() {
    setupTableSorting();
    setupTableContextMenu();
    setupTableKeyboardNavigation();
    setupTableResize();
}

/**
 * Setup table column sorting
 */
function setupTableSorting() {
    const table = document.getElementById('salaryTable');
    if (!table) return;
    
    const headers = table.querySelectorAll('thead th');
    headers.forEach((header, index) => {
        // Skip checkbox column and action column
        if (index === 0 || index === headers.length - 1) return;
        
        header.style.cursor = 'pointer';
        header.style.userSelect = 'none';
        header.style.position = 'relative';
        
        // Add sort indicator
        const sortIndicator = document.createElement('span');
        sortIndicator.className = 'sort-indicator';
        sortIndicator.innerHTML = '<i class="fas fa-sort"></i>';
        sortIndicator.style.marginLeft = '0.5rem';
        sortIndicator.style.opacity = '0.5';
        header.appendChild(sortIndicator);
        
        header.addEventListener('click', () => {
            sortTable(index, header);
        });
    });
}

/**
 * Sort table by column
 */
function sortTable(columnIndex, headerElement) {
    const table = document.getElementById('salaryTable');
    if (!table) return;
    
    const headers = table.querySelectorAll('thead th');
    const columnName = getColumnName(columnIndex);
    
    // Update sort direction
    if (sortColumn === columnIndex) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortDirection = 'asc';
        sortColumn = columnIndex;
    }
    
    // Update sort indicators
    headers.forEach((header, index) => {
        const indicator = header.querySelector('.sort-indicator i');
        if (!indicator) return;
        
        if (index === columnIndex) {
            indicator.className = sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            indicator.style.opacity = '1';
        } else {
            indicator.className = 'fas fa-sort';
            indicator.style.opacity = '0.5';
        }
    });
    
    // Re-load data with sort parameters
    loadSalaryDataWithSort(columnName, sortDirection);
}

/**
 * Get column name for sorting
 */
function getColumnName(columnIndex) {
    const columnNames = [
        null, // checkbox
        'name',
        'department',
        'base_salary',
        'present_days',
        'working_days',
        'late_days',
        'leave_days',
        'overtime_hours',
        'overtime_amount',
        'travel_allowance',
        'bonus_amount',
        'total_deductions',
        'tax_deduction',
        'pf_contribution',
        'net_salary',
        'status',
        null // actions
    ];
    
    return columnNames[columnIndex];
}

/**
 * Load salary data with sorting
 */
async function loadSalaryDataWithSort(sortColumn, sortDirection) {
    try {
        showLoadingState();
        
        const selectedMonth = getCurrentMonth();
        
        const params = new URLSearchParams({
            month: selectedMonth,
            page: currentPage,
            limit: employeesPerPage,
            search: currentFilters.search,
            role: currentFilters.role,
            status: currentFilters.status,
            sort_column: sortColumn || '',
            sort_direction: sortDirection || 'asc'
        });
        
        const response = await fetch(`api/get_employees.php?${params}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Generate salary data for real employees
            const salaryData = generateSalaryDataForEmployees(data.employees);
            tableData = salaryData.employees;
            renderSalaryTable(salaryData.employees);
            updatePagination(data.pagination);
            updateStatistics(data.statistics);
        } else {
            throw new Error(data.message || 'Failed to load salary data');
        }
        
    } catch (error) {
        console.error('Error loading sorted salary data:', error);
        showErrorMessage('Failed to load salary data. Please try again.');
        renderEmptyTable();
    } finally {
        hideLoadingState();
    }
}

/**
 * Setup table context menu
 */
function setupTableContextMenu() {
    const table = document.getElementById('salaryTable');
    if (!table) return;
    
    // Create context menu
    const contextMenu = createContextMenu();
    document.body.appendChild(contextMenu);
    
    // Add context menu to table rows
    table.addEventListener('contextmenu', function(e) {
        const row = e.target.closest('tr');
        if (!row || !row.dataset.employeeId) {
            return;
        }
        
        e.preventDefault();
        showContextMenu(e, row.dataset.employeeId, contextMenu);
    });
    
    // Hide context menu on click outside
    document.addEventListener('click', function() {
        hideContextMenu(contextMenu);
    });
}

/**
 * Create context menu element
 */
function createContextMenu() {
    const menu = document.createElement('div');
    menu.className = 'table-context-menu';
    menu.style.cssText = `
        position: fixed;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        padding: 0.5rem 0;
        z-index: 10000;
        display: none;
        min-width: 150px;
    `;
    
    const menuItems = [
        { icon: 'fas fa-eye', text: 'View Details', action: 'view' },
        { icon: 'fas fa-edit', text: 'Edit Salary', action: 'edit' },
        { icon: 'fas fa-download', text: 'Download Payslip', action: 'download' },
        { icon: 'fas fa-check', text: 'Approve Salary', action: 'approve' },
        { divider: true },
        { icon: 'fas fa-envelope', text: 'Send Email', action: 'email' },
        { icon: 'fas fa-print', text: 'Print Payslip', action: 'print' }
    ];
    
    menuItems.forEach(item => {
        if (item.divider) {
            const divider = document.createElement('div');
            divider.style.cssText = 'height: 1px; background: #e5e7eb; margin: 0.5rem 0;';
            menu.appendChild(divider);
        } else {
            const menuItem = document.createElement('div');
            menuItem.className = 'context-menu-item';
            menuItem.style.cssText = `
                padding: 0.5rem 1rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                font-size: 0.875rem;
                color: #374151;
                transition: background-color 0.1s;
            `;
            
            menuItem.innerHTML = `
                <i class="${item.icon}" style="width: 1rem;"></i>
                <span>${item.text}</span>
            `;
            
            menuItem.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f3f4f6';
            });
            
            menuItem.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'transparent';
            });
            
            menuItem.addEventListener('click', function() {
                handleContextMenuAction(item.action, menu.dataset.employeeId);
                hideContextMenu(menu);
            });
            
            menu.appendChild(menuItem);
        }
    });
    
    return menu;
}

/**
 * Show context menu
 */
function showContextMenu(event, employeeId, menu) {
    menu.dataset.employeeId = employeeId;
    menu.style.display = 'block';
    
    // Position menu
    const x = event.clientX;
    const y = event.clientY;
    const menuRect = menu.getBoundingClientRect();
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;
    
    // Adjust position if menu would go off screen
    const adjustedX = (x + menuRect.width > windowWidth) ? x - menuRect.width : x;
    const adjustedY = (y + menuRect.height > windowHeight) ? y - menuRect.height : y;
    
    menu.style.left = `${adjustedX}px`;
    menu.style.top = `${adjustedY}px`;
}

/**
 * Hide context menu
 */
function hideContextMenu(menu) {
    menu.style.display = 'none';
    menu.dataset.employeeId = '';
}

/**
 * Handle context menu actions
 */
function handleContextMenuAction(action, employeeId) {
    const employee = tableData.find(emp => emp.id == employeeId);
    if (!employee) return;
    
    switch (action) {
        case 'view':
            viewEmployeeSalary(employeeId);
            break;
        case 'edit':
            editEmployeeSalary(employeeId);
            break;
        case 'download':
            downloadPayslip(employeeId);
            break;
        case 'approve':
            approveSingleSalary(employeeId);
            break;
        case 'email':
            sendPayslipEmail(employeeId);
            break;
        case 'print':
            printPayslip(employeeId);
            break;
        default:
            console.warn('Unknown action:', action);
    }
}

/**
 * Setup table keyboard navigation
 */
function setupTableKeyboardNavigation() {
    const table = document.getElementById('salaryTable');
    if (!table) return;
    
    let selectedRowIndex = -1;
    
    document.addEventListener('keydown', function(e) {
        // Only handle keyboard navigation when table is in focus
        if (!table.contains(document.activeElement) && document.activeElement !== table) {
            return;
        }
        
        const rows = table.querySelectorAll('tbody tr[data-employee-id]');
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                selectedRowIndex = Math.min(selectedRowIndex + 1, rows.length - 1);
                highlightRow(rows, selectedRowIndex);
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                selectedRowIndex = Math.max(selectedRowIndex - 1, 0);
                highlightRow(rows, selectedRowIndex);
                break;
                
            case 'Enter':
                e.preventDefault();
                if (selectedRowIndex >= 0 && rows[selectedRowIndex]) {
                    const employeeId = rows[selectedRowIndex].dataset.employeeId;
                    viewEmployeeSalary(employeeId);
                }
                break;
                
            case 'Escape':
                selectedRowIndex = -1;
                clearRowHighlight(rows);
                break;
        }
    });
    
    // Make table focusable
    table.tabIndex = 0;
    table.style.outline = 'none';
}

/**
 * Highlight table row
 */
function highlightRow(rows, index) {
    // Clear previous highlights
    rows.forEach(row => {
        row.style.backgroundColor = '';
        row.style.outline = '';
    });
    
    // Highlight selected row
    if (index >= 0 && rows[index]) {
        rows[index].style.backgroundColor = '#eff6ff';
        rows[index].style.outline = '2px solid #3b82f6';
        rows[index].scrollIntoView({ block: 'nearest' });
    }
}

/**
 * Clear row highlight
 */
function clearRowHighlight(rows) {
    rows.forEach(row => {
        row.style.backgroundColor = '';
        row.style.outline = '';
    });
}

/**
 * Setup table column resizing
 */
function setupTableResize() {
    const table = document.getElementById('salaryTable');
    if (!table) return;
    
    const headers = table.querySelectorAll('thead th');
    
    headers.forEach((header, index) => {
        // Skip checkbox and action columns
        if (index === 0 || index === headers.length - 1) return;
        
        const resizeHandle = document.createElement('div');
        resizeHandle.className = 'resize-handle';
        resizeHandle.style.cssText = `
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            cursor: col-resize;
            background: transparent;
        `;
        
        resizeHandle.addEventListener('mousedown', function(e) {
            startColumnResize(e, header);
        });
        
        header.style.position = 'relative';
        header.appendChild(resizeHandle);
    });
}

/**
 * Start column resize
 */
function startColumnResize(e, header) {
    e.preventDefault();
    
    const startX = e.clientX;
    const startWidth = parseInt(document.defaultView.getComputedStyle(header).width, 10);
    
    function doResize(e) {
        const newWidth = startWidth + e.clientX - startX;
        header.style.width = newWidth + 'px';
    }
    
    function stopResize() {
        document.removeEventListener('mousemove', doResize);
        document.removeEventListener('mouseup', stopResize);
    }
    
    document.addEventListener('mousemove', doResize);
    document.addEventListener('mouseup', stopResize);
}

/**
 * Approve single salary
 */
async function approveSingleSalary(employeeId) {
    if (!confirm('Are you sure you want to approve this salary?')) {
        return;
    }
    
    try {
        const response = await fetch('api/approve-salary.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                employee_id: employeeId,
                month: getCurrentMonth()
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to approve salary');
        }
        
        const data = await response.json();
        
        if (data.success) {
            showSuccessMessage('Salary approved successfully');
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
 * Send payslip via email
 */
async function sendPayslipEmail(employeeId) {
    try {
        const response = await fetch('api/send-payslip-email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                employee_id: employeeId,
                month: getCurrentMonth()
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to send email');
        }
        
        const data = await response.json();
        
        if (data.success) {
            showSuccessMessage('Payslip email sent successfully');
        } else {
            throw new Error(data.message || 'Failed to send email');
        }
        
    } catch (error) {
        console.error('Error sending email:', error);
        showErrorMessage('Failed to send payslip email');
    }
}

/**
 * Print payslip
 */
function printPayslip(employeeId) {
    const printUrl = `api/print-payslip.php?employee_id=${employeeId}&month=${getCurrentMonth()}`;
    window.open(printUrl, '_blank');
}

/**
 * Export table to Excel
 */
function exportTableToExcel() {
    const table = document.getElementById('salaryTable');
    if (!table) return;
    
    // Create a new table with only visible data
    const exportTable = table.cloneNode(true);
    
    // Remove action column and checkboxes
    const rows = exportTable.querySelectorAll('tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        if (cells.length > 0) {
            cells[0].remove(); // Remove checkbox column
            if (cells.length > 1) {
                cells[cells.length - 1].remove(); // Remove action column
            }
        }
    });
    
    // Convert to CSV
    const csv = tableToCSV(exportTable);
    downloadCSV(csv, `salary-data-${getCurrentMonth()}.csv`);
}

/**
 * Convert table to CSV
 */
function tableToCSV(table) {
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        
        cells.forEach(cell => {
            let cellText = cell.textContent.trim();
            // Escape quotes and wrap in quotes if contains comma
            if (cellText.includes(',') || cellText.includes('"')) {
                cellText = '"' + cellText.replace(/"/g, '""') + '"';
            }
            rowData.push(cellText);
        });
        
        csv.push(rowData.join(','));
    });
    
    return csv.join('\n');
}

/**
 * Download CSV file
 */
function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Get current month helper function
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

// Export table functions
window.SalaryTable = {
    exportTableToExcel,
    approveSingleSalary,
    sendPayslipEmail,
    printPayslip
};

// Make functions globally available
Object.assign(window, window.SalaryTable);