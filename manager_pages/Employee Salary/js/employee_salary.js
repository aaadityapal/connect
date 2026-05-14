document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.filter-form');
    const monthSelect = document.getElementById('month');
    const yearSelect = document.getElementById('year');
    const tableScrollTop = document.getElementById('tableScrollTop');
    const tableScrollTopInner = document.getElementById('tableScrollTopInner');
    const tableScrollMain = document.getElementById('tableScrollMain');
    const analyticsTable = document.querySelector('.analytics-table');
    const saveAllBtn = document.getElementById('saveAllSalariesBtn');

    // Load data if filters are already selected
    const selectedMonth = monthSelect.value;
    const selectedYear = yearSelect.value;
    
    if (selectedMonth && selectedYear) {
        loadAnalyticsData(selectedMonth, selectedYear);
    }

    if (tableScrollTop && tableScrollTopInner && tableScrollMain && analyticsTable) {
        const syncScrollWidth = () => {
            tableScrollTopInner.style.width = `${analyticsTable.scrollWidth}px`;
        };

        syncScrollWidth();
        window.addEventListener('resize', syncScrollWidth);

        tableScrollTop.addEventListener('scroll', () => {
            tableScrollMain.scrollLeft = tableScrollTop.scrollLeft;
        });

        tableScrollMain.addEventListener('scroll', () => {
            tableScrollTop.scrollLeft = tableScrollMain.scrollLeft;
        });

        const enableDragScroll = (el) => {
            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;

            el.addEventListener('mousedown', (e) => {
                if (e.button !== 0) return;
                isDown = true;
                startX = e.pageX - el.offsetLeft;
                scrollLeft = el.scrollLeft;
                el.classList.add('is-dragging');
            });

            el.addEventListener('mouseleave', () => {
                isDown = false;
                el.classList.remove('is-dragging');
            });

            el.addEventListener('mouseup', () => {
                isDown = false;
                el.classList.remove('is-dragging');
            });

            el.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - el.offsetLeft;
                const walk = (x - startX);
                el.scrollLeft = scrollLeft - walk;
            });
        };

        enableDragScroll(tableScrollMain);
        enableDragScroll(tableScrollTop);
    }

    if (saveAllBtn) {
        saveAllBtn.addEventListener('click', saveAllSalariesSnapshot);
    }


    // Optional: Auto-submit when filters change
    // monthSelect.addEventListener('change', () => form.submit());
    // yearSelect.addEventListener('change', () => form.submit());
});

function toNumber(value, fallback = 0) {
    const num = Number(value);
    return Number.isFinite(num) ? num : fallback;
}

function buildSalarySnapshotRows() {
    const dataById = window.analyticsDataById || {};
    const rows = [];

    Object.keys(dataById).forEach((key) => {
        const emp = dataById[key] || {};
        const salaryCalcDays = toNumber(emp.salary_calculated_days, 0);
        const baseSalary = toNumber(emp.base_salary, 0);
        const grossSalary = toNumber(emp.gross_salary, baseSalary);
        const workingDays = Math.max(1, toNumber(emp.working_days, 0));
        const tdsPct = toNumber(emp.tds_percentage, 0);
        const tdsRate = tdsPct / 100;

        const netSalary = salaryCalcDays * (grossSalary / workingDays);
        const netTds = netSalary * tdsRate;
        const payableAfterDeduction = Math.max(0, netSalary - netTds);
        const overtimeAmount = toNumber(emp.overtime_amount, 0);
        const overtimeHours = toNumber(emp.overtime_hours, 0);
        const otTds = overtimeAmount * tdsRate;
        const payableOtAfterDeduction = Math.max(0, overtimeAmount - otTds);
        const totalTdsAmount = netTds + otTds;
        const totalPayableSalary = Math.max(0, payableAfterDeduction + payableOtAfterDeduction);
        const payableSalary = Math.max(0, baseSalary * (1 - tdsRate));

        rows.push({
            user_id: toNumber(emp.id, 0),
            employee_id: String(emp.employee_id || ''),
            employee_name: String(emp.name || ''),
            role: String(emp.role || ''),
            gross_salary: grossSalary,
            base_salary: baseSalary,
            tds_percentage: tdsPct,
            payable_salary: payableSalary,
            working_days: toNumber(emp.working_days, 0),
            present_days: toNumber(emp.present_days, 0),
            late_days: toNumber(emp.late_days, 0),
            one_hour_late: toNumber(emp.one_hour_late, 0),
            leave_taken: toNumber(emp.leave_taken, 0),
            leave_deduction: toNumber(emp.leave_deduction, 0),
            late_deduction: toNumber(emp.late_deduction, 0),
            one_hour_late_deduction: toNumber(emp.one_hour_late_deduction, 0),
            fourth_saturday_deduction: toNumber(emp.fourth_saturday_deduction, 0),
            penalty_days: toNumber(emp.penalty_days, 0),
            salary_calculated_days: salaryCalcDays,
            net_payable_salary: netSalary,
            net_payable_salary_tds: netTds,
            payable_salary_after_deduction: payableAfterDeduction,
            overtime_hours: overtimeHours,
            overtime_amount: overtimeAmount,
            ot_tds: otTds,
            payable_ot_after_deduction: payableOtAfterDeduction,
            total_tds_amount: totalTdsAmount,
            total_payable_salary: totalPayableSalary
        });
    });

    return rows;
}

function saveAllSalariesSnapshot() {
    const month = document.getElementById('month')?.value;
    const year = document.getElementById('year')?.value;
    const button = document.getElementById('saveAllSalariesBtn');

    if (!month || !year) {
        showNotification('Warning', 'Please select month and year before saving.', 'warning');
        return;
    }

    const rows = buildSalarySnapshotRows();
    if (!rows.length) {
        showNotification('Warning', 'No data available to save. Please load analytics first.', 'warning');
        return;
    }

    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    fetch('save_salary_snapshot.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            month: parseInt(month, 10),
            year: parseInt(year, 10),
            rows: rows
        })
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        })
        .then((data) => {
            if (data && data.status === 'success') {
                showNotification('Success', data.message || 'All salaries saved successfully.', 'success');
            } else {
                showNotification('Error', data.message || 'Failed to save salaries.', 'error');
            }
        })
        .catch(() => {
            showNotification('Error', 'Error saving salaries. Please try again.', 'error');
        })
        .finally(() => {
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-database"></i> Save All Salaries';
            }
        });
}

// Notification Modal Functions
function showNotification(title, message, type = 'success') {
    const modal = document.getElementById('notificationModal');
    const icon = document.getElementById('notificationIcon');
    const titleEl = document.getElementById('notificationTitle');
    const messageEl = document.getElementById('notificationMessage');

    titleEl.textContent = title;
    messageEl.textContent = message;

    // Update icon based on type
    if (type === 'success') {
        icon.innerHTML = '<i class="fas fa-check-circle" style="color: #22c55e;"></i>';
    } else if (type === 'error') {
        icon.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>';
    } else if (type === 'warning') {
        icon.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>';
    } else if (type === 'info') {
        icon.innerHTML = '<i class="fas fa-info-circle" style="color: #3b82f6;"></i>';
    }

    modal.style.display = 'block';
}

function closeNotificationModal() {
    const modal = document.getElementById('notificationModal');
    modal.style.display = 'none';
}

// Close notification modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('notificationModal');
    if (event.target === modal) {
        closeNotificationModal();
    }
});

function loadAnalyticsData(month, year) {
    const tableBody = document.getElementById('analyticsTableBody');
    
    // Show loading state
    tableBody.innerHTML = `
        <tr>
            <td colspan="13" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin"></i> Loading data...
            </td>
        </tr>
    `;

    // Fetch data from backend
    fetch(`../../fetch_monthly_analytics_data.php?month=${month}&year=${year}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' && data.data.length > 0) {
                populateTable(data.data);
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 40px; color: #a0aec0;">
                            <i class="fas fa-inbox"></i> No data available for the selected period
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading data:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="13" style="text-align: center; padding: 40px; color: #e53e3e;">
                        <i class="fas fa-exclamation-circle"></i> Error loading data. Please try again.
                    </td>
                </tr>
            `;
        });
}

function populateTable(employees) {
    const tableBody = document.getElementById('analyticsTableBody');
    let html = '';
    const totals = {
        netPayable: 0,
        netPayableTds: 0,
        payableAfterDeduction: 0,
        overtimeAmount: 0,
        otTds: 0,
        payableOtAfterDeduction: 0,
        totalTdsAmount: 0,
        totalPayableSalary: 0
    };

    employees.forEach(emp => {
        // store employee data for modal lookups
        window.analyticsDataById = window.analyticsDataById || {};
        window.analyticsDataById[emp.id] = emp;
        const salaryCalcDays = Number(emp.salary_calculated_days || 0);
        const grossSalary = Number(emp.gross_salary || 0);
        const workingDays = Number(emp.working_days || 1);
        const tdsPct = Number(emp.tds_percentage || 0) / 100;
        const netSalary = salaryCalcDays * (grossSalary / workingDays);
        const netTds = netSalary * tdsPct;
        const netPayable = netSalary;
        const payableAfterDeduction = Math.max(0, netSalary - netTds);
        const overtimeAmount = Number(emp.overtime_amount || 0);
        const otTds = overtimeAmount * tdsPct;
        const payableOtAfterDeduction = Math.max(0, overtimeAmount - otTds);
        const totalTdsAmount = netTds + otTds;
        const totalPayableSalary = Math.max(0, payableAfterDeduction + payableOtAfterDeduction);

        totals.netPayable += netPayable;
        totals.netPayableTds += netTds;
        totals.payableAfterDeduction += payableAfterDeduction;
        totals.overtimeAmount += overtimeAmount;
        totals.otTds += otTds;
        totals.payableOtAfterDeduction += payableOtAfterDeduction;
        totals.totalTdsAmount += totalTdsAmount;
        totals.totalPayableSalary += totalPayableSalary;
        html += `
            <tr data-user-id="${emp.id}">
                <td>${emp.employee_id || 'N/A'}</td>
                <td>${emp.name || 'N/A'}</td>
                <td>${emp.role || 'N/A'}</td>
                <td class="tds-total-cell">₹${formatNumber(Number(emp.base_salary || 0).toFixed(2))}</td>
                <td class="tds-cell">
                    <span id="tds-value-${emp.id}" style="font-weight:600;">${Number(emp.tds_percentage || 0).toFixed(2)}%</span>
                    <span class="info-icon" style="cursor:pointer; margin-left:4px;" title="Click Edit to update TDS">
                        <i class="fas fa-percent" style="font-size:0.75rem; color:#718096;"></i>
                    </span>
                </td>
                <td title="Payable Salary = Gross Salary − TDS%">₹${formatNumber((Number(emp.base_salary || 0) * (1 - Number(emp.tds_percentage || 0) / 100)).toFixed(2))}</td>
                <td>
                    ${emp.working_days || 0}
                    <span class="info-icon" data-type="working-days" onclick="showWorkingDaysDetails(${emp.id}, '${emp.name}', ${emp.working_days}, 0)" style="cursor: pointer;">
                        <i class="fas fa-info-circle"></i>
                        <span class="info-tooltip">Click to see details</span>
                    </span>
                </td>
                <td>
                    ${emp.present_days || 0}
                    <span class="info-icon" data-type="present-days" onclick="showPresentDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                        <i class="fas fa-info-circle"></i>
                        <span class="info-tooltip">Days with both punch in and punch out</span>
                    </span>
                </td>
                <td>
                    ${emp.late_days || 0}
                    <span class="info-icon" data-type="late-days" onclick="showLateDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                        <i class="fas fa-info-circle"></i>
                        <span class="info-tooltip">Days late by more than 15 minutes</span>
                    </span>
                </td>
                <td>
                    ${emp.one_hour_late || 0}
                    <span class="info-icon" data-type="one-hour-late" onclick="showOneHourLateDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                        <i class="fas fa-info-circle"></i>
                        <span class="info-tooltip">Days late by 1 hour or more</span>
                    </span>
                </td>
                <td>
                    ${emp.leave_taken || 0}
                    <span class="info-icon" data-type="leave-taken" onclick="showLeaveDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                        <i class="fas fa-info-circle"></i>
                        <span class="info-tooltip">Approved leave days</span>
                    </span>
                </td>
                <td>
                    ₹${formatNumber(emp.leave_deduction || 0)}
                    <span class="info-icon" data-type="leave-deduction" onclick="showLeaveDeductionDetails(${emp.id}, '${emp.name}', ${emp.leave_deduction || 0})" style="cursor: pointer;">
                        <i class="fas fa-info-circle"></i>
                        <span class="info-tooltip">Leave deduction breakdown</span>
                    </span>
                </td>
                <td>₹${formatNumber(emp.late_deduction || 0)}</td>
                <td>₹${formatNumber(emp.one_hour_late_deduction || 0)}</td>
                <td>₹${formatNumber(emp.fourth_saturday_deduction || 0)}</td>
                <td>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <button type="button" class="penalty-btn penalty-decrease" onclick="openPenaltyModal(${emp.id}, '${emp.name}', 'decrease')" title="Decrease by 0.5 days">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span id="penalty-value-${emp.id}" style="min-width: 40px; text-align: center; font-weight: 600;">${(emp.penalty_days || 0).toFixed(1)}</span>
                        <button type="button" class="penalty-btn penalty-increase" onclick="openPenaltyModal(${emp.id}, '${emp.name}', 'increase')" title="Increase by 0.5 days">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </td>
                <td>
                    ${(emp.salary_calculated_days || 0).toFixed(2)}
                    <span class="info-icon" style="cursor:pointer; margin-left:6px;" onclick="showSalaryCalcDetails(${emp.id})">
                        <i class="fas fa-info-circle"></i>
                    </span>
                </td>
                <td>
                    ₹${formatNumber(netPayable.toFixed(2))}
                </td>
                <td>
                    ₹${formatNumber(netTds.toFixed(2))}
                </td>
                <td>
                    ₹${formatNumber(payableAfterDeduction.toFixed(2))}
                </td>
                <td>
                    ${emp.overtime_hours || 0}
                    <span class="info-icon" data-type="overtime-hours" onclick="showOvertimeDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                        <i class="fas fa-info-circle"></i>
                        <span class="info-tooltip">Overtime hours breakdown</span>
                    </span>
                </td>
                <td>
                    ₹${formatNumber(overtimeAmount.toFixed(2))}
                </td>
                <td>
                    ₹${formatNumber(otTds.toFixed(2))}
                </td>
                <td>
                    ₹${formatNumber(payableOtAfterDeduction.toFixed(2))}
                </td>
                <td>
                    ₹${formatNumber(totalTdsAmount.toFixed(2))}
                </td>
                <td style="background:#fef9c3; font-weight:700; color:#713f12;">
                    ₹${formatNumber(totalPayableSalary.toFixed(2))}
                </td>
                <td style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="action-btn edit-btn" title="Edit" onclick="editEmployee('${emp.employee_id}', ${emp.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="action-btn paid-btn" title="Mark as Paid" onclick="markAsPaid('${emp.employee_id}')">
                        <i class="fas fa-check-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    html += `
        <tr class="totals-row">
            <td colspan="17" style="text-align:right; font-weight:700;">Total</td>
            <td>₹${formatNumber(totals.netPayable.toFixed(2))}</td>
            <td>₹${formatNumber(totals.netPayableTds.toFixed(2))}</td>
            <td>₹${formatNumber(totals.payableAfterDeduction.toFixed(2))}</td>
            <td></td>
            <td>₹${formatNumber(totals.overtimeAmount.toFixed(2))}</td>
            <td>₹${formatNumber(totals.otTds.toFixed(2))}</td>
            <td>₹${formatNumber(totals.payableOtAfterDeduction.toFixed(2))}</td>
            <td>₹${formatNumber(totals.totalTdsAmount.toFixed(2))}</td>
            <td style="background:#fef9c3; font-weight:700; color:#713f12;">₹${formatNumber(totals.totalPayableSalary.toFixed(2))}</td>
            <td></td>
        </tr>
    `;

    tableBody.innerHTML = html;
}

function formatNumber(num) {
    return Number(num).toLocaleString('en-IN');
}

function editEmployee(employeeId, userId) {
    // Find employee data from table
    const rows = document.querySelectorAll('.analytics-table tbody tr');
    let employeeData = null;

    rows.forEach(row => {
        const rowEmployeeId = row.cells[0].innerText;
        if (rowEmployeeId === employeeId) {
            employeeData = {
                id: rowEmployeeId,
                name: row.cells[1].innerText,
                role: row.cells[2].innerText,
                currentSalary: row.cells[3].innerText
            };
        }
    });

    if (employeeData) {
        // Populate modal with employee data
        document.getElementById('modalEmployeeId').innerText = employeeData.id;
        document.getElementById('modalEmployeeName').innerText = employeeData.name;
        document.getElementById('modalCurrentSalary').innerText = employeeData.currentSalary;
        
        // Extract numeric value from salary
        const salaryValue = parseFloat(employeeData.currentSalary.replace(/[₹,]/g, ''));
        document.getElementById('newBaseSalary').value = salaryValue;

        // Populate TDS from stored data
        const empData = (window.analyticsDataById || {})[userId];
        document.getElementById('newTdsPercentage').value = empData ? Number(empData.tds_percentage || 0).toFixed(2) : '0.00';
        document.getElementById('baseSalaryEffectiveFrom').value = empData && empData.base_salary_effective_from ? empData.base_salary_effective_from : '';
        document.getElementById('tdsEffectiveFrom').value = empData && empData.tds_effective_from ? empData.tds_effective_from : '';

        // Store employee data for form submission
        document.getElementById('editSalaryForm').dataset.employeeId = employeeId;
        document.getElementById('editSalaryForm').dataset.userId = userId; // Store actual user_id

        // Show modal
        document.getElementById('editSalaryModal').style.display = 'block';
    } else {
        showNotification('Error', 'Employee not found', 'error');
    }
}

function closeEditModal() {
    document.getElementById('editSalaryModal').style.display = 'none';
    document.getElementById('editSalaryForm').reset();
    document.getElementById('newTdsPercentage').value = '0.00';
    document.getElementById('baseSalaryEffectiveFrom').value = '';
    document.getElementById('tdsEffectiveFrom').value = '';
    document.getElementById('formMessage').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const editModal = document.getElementById('editSalaryModal');
    const workingDaysModal = document.getElementById('workingDaysModal');
    
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === workingDaysModal) {
        closeWorkingDaysModal();
    }
}

// Handle salary form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editSalaryForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveSalaryRecord();
        });
    }
});

function saveSalaryRecord() {
    const form = document.getElementById('editSalaryForm');
    const employeeId = form.dataset.employeeId;
    const userId = parseInt(form.dataset.userId);
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    const baseSalary = parseFloat(document.getElementById('newBaseSalary').value);
    const tdsPercentage = parseFloat(document.getElementById('newTdsPercentage').value) || 0;
    const baseSalaryEffectiveFrom = document.getElementById('baseSalaryEffectiveFrom').value || null;
    const tdsEffectiveFrom = document.getElementById('tdsEffectiveFrom').value || null;
    const remarks = document.getElementById('salaryRemarks').value;

    if (!month || !year) {
        showFormMessage('Please select month and year', 'error');
        return;
    }

    if (isNaN(baseSalary) || baseSalary < 0) {
        showFormMessage('Please enter a valid salary (0 or greater)', 'error');
        return;
    }

    if (tdsPercentage < 0 || tdsPercentage > 100) {
        showFormMessage('TDS percentage must be between 0 and 100', 'error');
        return;
    }

    if (!userId || userId <= 0) {
        showFormMessage('Invalid user ID', 'error');
        return;
    }

    // Show loading spinner
    document.querySelector('.save-text').style.display = 'none';
    document.getElementById('saveSpinner').style.display = 'block';

    const payload = {
        employee_id: employeeId,
        user_id: userId,
        base_salary: baseSalary,
        base_salary_effective_from: baseSalaryEffectiveFrom,
        tds_percentage: tdsPercentage,
        tds_effective_from: tdsEffectiveFrom,
        month: parseInt(month),
        year: parseInt(year),
        remarks: remarks
    };

    console.log('Sending payload:', payload);

    fetch('../../save_salary_record.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        // Hide loading spinner
        document.querySelector('.save-text').style.display = 'inline';
        document.getElementById('saveSpinner').style.display = 'none';

        if (data.status === 'success') {
            showFormMessage('Salary record saved successfully!', 'success');
            setTimeout(() => {
                closeEditModal();
                // Reload table data
                const month = document.getElementById('month').value;
                const year = document.getElementById('year').value;
                loadAnalyticsData(month, year);
            }, 1500);
        } else {
            showFormMessage(data.message || 'Error saving salary record', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.querySelector('.save-text').style.display = 'inline';
        document.getElementById('saveSpinner').style.display = 'none';
        showFormMessage('Error saving salary record. Please try again.', 'error');
    });
}

function showFormMessage(message, type) {
    const messageDiv = document.getElementById('formMessage');
    messageDiv.innerText = message;
    messageDiv.style.display = 'block';
    
    if (type === 'success') {
        messageDiv.style.background = '#c6f6d5';
        messageDiv.style.color = '#22543d';
        messageDiv.style.border = '1px solid #9ae6b4';
    } else {
        messageDiv.style.background = '#fed7d7';
        messageDiv.style.color = '#742a2a';
        messageDiv.style.border = '1px solid #fc8787';
    }
}

function showWorkingDaysDetails(userId, employeeName, month, year) {
    // Store current data for API call
    const currentMonth = document.getElementById('month').value;
    const currentYear = document.getElementById('year').value;
    
    // Fetch working days details from the backend
    fetch(`../../get_working_days_details.php?user_id=${userId}&month=${currentMonth}&year=${currentYear}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                // Populate modal with data
                document.getElementById('detailEmployeeName').innerText = employeeName;
                document.getElementById('detailMonthYear').innerText = data.monthYear;
                document.getElementById('detailTotalWorkingDays').innerText = data.workingDays;
                document.getElementById('detailTotalDays').innerText = data.totalDays;
                
                // Weekly offs
                document.getElementById('detailWeeklyOffs').innerText = data.weeklyOffsCount;
                let weeklyOffsText = data.weeklyOffs.length > 0 ? data.weeklyOffs.join(', ') : 'None';
                let weeklyOffsBreakdown = data.weeklyOffsBreakdown || 'No breakdown available';
                document.getElementById('detailWeeklyOffsDetails').innerText = `${weeklyOffsText} (${weeklyOffsBreakdown})`;
                
                // Populate weekly off dates
                const weeklyOffsDatesDiv = document.getElementById('weeklyOffsDates');
                const toggleWeeklyOffDatesBtn = document.getElementById('toggleWeeklyOffDates');
                
                if (data.weeklyOffDates && data.weeklyOffDates.length > 0) {
                    let datesHTML = '';
                    data.weeklyOffDates.forEach(dateObj => {
                        datesHTML += `<div style="padding: 4px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem;">
                            <strong>${dateObj.date}</strong> - ${dateObj.fullDate} (${dateObj.day})
                        </div>`;
                    });
                    weeklyOffsDatesDiv.innerHTML = datesHTML;
                    toggleWeeklyOffDatesBtn.style.display = 'inline-block';
                    
                    // Toggle button functionality
                    toggleWeeklyOffDatesBtn.onclick = function() {
                        if (weeklyOffsDatesDiv.style.display === 'none') {
                            weeklyOffsDatesDiv.style.display = 'block';
                            toggleWeeklyOffDatesBtn.innerText = 'Hide Dates';
                            toggleWeeklyOffDatesBtn.style.background = '#cbd5e0';
                        } else {
                            weeklyOffsDatesDiv.style.display = 'none';
                            toggleWeeklyOffDatesBtn.innerText = 'Show Dates';
                            toggleWeeklyOffDatesBtn.style.background = '#e2e8f0';
                        }
                    };
                }
                
                // Holidays
                document.getElementById('detailHolidaysCount').innerText = data.holidaysCount;
                let holidaysList = data.holidays.length > 0 ? data.holidays.join(', ') : 'None';
                document.getElementById('detailHolidaysList').innerText = holidaysList;
                
                // Populate holiday dates
                const holidaysDatesDiv = document.getElementById('holidaysDates');
                const toggleHolidayDatesBtn = document.getElementById('toggleHolidayDates');
                
                if (data.holidayDetailedDates && data.holidayDetailedDates.length > 0) {
                    let datesHTML = '';
                    data.holidayDetailedDates.forEach(holiday => {
                        datesHTML += `<div style="padding: 4px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem;">
                            <strong>${holiday.date}</strong> - ${holiday.fullDate} (${holiday.day}) - ${holiday.name}
                        </div>`;
                    });
                    holidaysDatesDiv.innerHTML = datesHTML;
                    toggleHolidayDatesBtn.style.display = 'inline-block';
                    
                    // Toggle button functionality
                    toggleHolidayDatesBtn.onclick = function() {
                        if (holidaysDatesDiv.style.display === 'none') {
                            holidaysDatesDiv.style.display = 'block';
                            toggleHolidayDatesBtn.innerText = 'Hide Dates';
                            toggleHolidayDatesBtn.style.background = '#cbd5e0';
                        } else {
                            holidaysDatesDiv.style.display = 'none';
                            toggleHolidayDatesBtn.innerText = 'Show Dates';
                            toggleHolidayDatesBtn.style.background = '#e2e8f0';
                        }
                    };
                }
                
                // Calculation
                document.getElementById('detailCalculation').innerText = 
                    `${data.totalDays} - ${data.weeklyOffsCount} - ${data.holidaysCount} = ${data.workingDays}`;
                
                // Show modal
                document.getElementById('workingDaysModal').style.display = 'block';
            } else {
                showNotification('Error', 'Error fetching working days details: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', 'Error loading working days details', 'error');
        });
}

function closeWorkingDaysModal() {
    document.getElementById('workingDaysModal').style.display = 'none';
}

function markAsPaid(employeeId) {
    showNotification('Info', `Mark as paid: ${employeeId}`, 'info');
    // Add your paid marking logic here
    console.log('Paid clicked for employee:', employeeId);
}

function generateReport() {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    
    if (!month || !year) {
        showNotification('Warning', 'Please select month and year', 'warning');
        return;
    }

    // Show loading state
    const reportBtn = event.target.closest('button');
    const originalText = reportBtn.innerHTML;
    reportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    reportBtn.disabled = true;

    // Use a more reliable CDN URL for XLSX
    const xslxUrl = 'https://unpkg.com/xlsx@latest/dist/xlsx.full.min.js';
    
    // Check if SheetJS is loaded, if not load it
    if (!window.XLSX) {
        const script = document.createElement('script');
        script.src = xslxUrl;
        script.async = true;
        
        script.onload = () => {
            console.log('XLSX library loaded successfully from:', xslxUrl);
            performReportGeneration(month, year, reportBtn, originalText);
        };
        
        script.onerror = () => {
            console.error('Failed to load XLSX library from:', xslxUrl);
            showNotification('Error', 'Error loading Excel library. Please try again.', 'error');
            reportBtn.innerHTML = originalText;
            reportBtn.disabled = false;
        };
        
        // Set a timeout to catch loading issues
        setTimeout(() => {
            if (!window.XLSX) {
                console.error('XLSX library failed to load within timeout');
                showNotification('Error', 'Error: Excel library took too long to load. Please try again.', 'error');
                reportBtn.innerHTML = originalText;
                reportBtn.disabled = false;
            }
        }, 15000);
        
        document.head.appendChild(script);
    } else {
        console.log('XLSX library already loaded');
        performReportGeneration(month, year, reportBtn, originalText);
    }
}

function performReportGeneration(month, year, reportBtn, originalText) {
    console.log('Starting report generation for month:', month, 'year:', year);
    
    // Fetch data from the generate report handler
    fetch(`../../generate_monthly_payroll_report.php?month=${month}&year=${year}`)
        .then(response => {
            console.log('Response received, status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (data.status !== 'success') {
                throw new Error(data.message || 'Report generation failed');
            }
            
            if (!data.data || data.data.length === 0) {
                throw new Error('No data available for report');
            }

            console.log('Creating Excel workbook...');

            // Create workbook
            const wb = XLSX.utils.book_new();
            
            // Prepare data for worksheet
            const headers = [
                'Employee ID',
                'Name',
                'Role',
                'Base Salary',
                'Working Days',
                'Present Days',
                'Late Days',
                '1+ Hour Late',
                'Leave Taken',
                'Leave Deduction',
                'Late Deduction',
                '1+ Hour Late Deduction',
                '4th Saturday Deduction',
                'Penalty Days',
                'Salary Calculated Days',
                'Net Payable Salary',
                'Net Payable Salary TDS',
                'Payable Salary After Deduction',
                'Overtime Hours',
                'Overtime Amount',
                'OT TDS',
                'Payable OT after Deduction',
                'Total TDS Amount (Govt. Amount)',
                'Total Payable Salary'
            ];

            // Create data rows
            const worksheetData = [headers];
            data.data.forEach(emp => {
                worksheetData.push([
                    emp.employee_id || '',
                    emp.name || '',
                    emp.role || '',
                    emp.base_salary || 0,
                    emp.working_days || 0,
                    emp.present_days || 0,
                    emp.late_days || 0,
                    emp.one_hour_late || 0,
                    emp.leave_taken || 0,
                    emp.leave_deduction || 0,
                    emp.late_deduction || 0,
                    emp.one_hour_late_deduction || 0,
                    emp.fourth_saturday_deduction || 0,
                    emp.penalty_days || 0,
                    emp.salary_calculated_days || 0,
                    Number((Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1)))).toFixed(2)),
                    Number((Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (Number(emp.tds_percentage || 0) / 100)).toFixed(2)),
                    Number(Math.max(0, Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (1 - Number(emp.tds_percentage || 0) / 100)).toFixed(2)),
                    emp.overtime_hours || 0,
                    emp.overtime_amount || 0,
                    Number((Number(emp.overtime_amount || 0) * (Number(emp.tds_percentage || 0) / 100)).toFixed(2)),
                    Number((Math.max(0, Number(emp.overtime_amount || 0) * (1 - Number(emp.tds_percentage || 0) / 100))).toFixed(2)),
                    Number((
                        Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (Number(emp.tds_percentage || 0) / 100)
                        + Number(emp.overtime_amount || 0) * (Number(emp.tds_percentage || 0) / 100)
                    ).toFixed(2)),
                    Number((Math.max(0,
                        Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (1 - Number(emp.tds_percentage || 0) / 100)
                        + Number(emp.overtime_amount || 0) * (1 - Number(emp.tds_percentage || 0) / 100)
                    )).toFixed(2))
                ]);
            });

            // Add empty row and summary section
            worksheetData.push([]);
            worksheetData.push(['PAYROLL SUMMARY']);
            worksheetData.push(['Total Net Salary (Without Overtime)', data.summary.total_salary_without_overtime]);
            worksheetData.push(['Total Overtime Amount', data.summary.total_overtime_amount]);
            worksheetData.push(['Total Final Salary (With Overtime)', data.summary.total_salary_with_overtime]);
            worksheetData.push(['Total Employees', data.summary.employee_count]);

            console.log('Creating worksheet...');
            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(worksheetData);

            console.log('Applying colorful header styling...');
            // Define color categories for headers
            const headerColors = {
                // Employee Info - Blue
                0: 'FF2E5090',  // Employee ID
                1: 'FF2E5090',  // Name
                2: 'FF2E5090',  // Role
                3: 'FF2E5090',  // Base Salary
                // Attendance - Green
                4: 'FF27AE60',  // Working Days
                5: 'FF27AE60',  // Present Days
                6: 'FFD32F2F',  // Late Days (Red)
                7: 'FFE64A19',  // 1+ Hour Late (Orange)
                8: 'FF8E44AD',  // Leave Taken (Purple)
                // Deductions - Red/Orange
                9: 'FFD32F2F',  // Leave Deduction
                10: 'FFE64A19', // Late Deduction
                11: 'FFE64A19', // 1+ Hour Late Deduction
                12: 'FFD32F2F', // 4th Saturday Deduction
                13: 'FFD32F2F', // Penalty Days
                // Salary Calculation - Teal
                14: 'FF16A085', // Salary Calculated Days
                15: 'FFE65100', // Net Payable Salary TDS (Deep Orange)
                16: 'FF16A085', // Net Payable Salary
                // Overtime - Cyan
                17: 'FF0097A7', // Overtime Hours
                18: 'FF0097A7', // Overtime Amount
                19: 'FFE65100', // OT TDS (Deep Orange)
                20: 'FF2E7D32', // Payable OT after Deduction (Dark Green)
                21: 'FF1B5E20'  // Total Payable Salary (Dark Green)
            };

            // Color header row with category colors
            for (let i = 0; i < headers.length; i++) {
                const cellRef = XLSX.utils.encode_cell({ r: 0, c: i });
                if (ws[cellRef]) {
                    ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: headerColors[i] || 'FF2E5090' } };
                    ws[cellRef].font = { color: { rgb: 'FFFFFFFF' }, bold: true, size: 14 };
                    ws[cellRef].alignment = { horizontal: 'center', vertical: 'center', wrapText: true };
                    ws[cellRef].border = {
                        top: { style: 'thin', color: { rgb: 'FFFFFFFF' } },
                        bottom: { style: 'thin', color: { rgb: 'FFFFFFFF' } },
                        left: { style: 'thin', color: { rgb: 'FFFFFFFF' } },
                        right: { style: 'thin', color: { rgb: 'FFFFFFFF' } }
                    };
                }
            }

            console.log('Applying colorful row colors...');
            // Define light colors for data rows (light versions of header colors)
            const rowLightColors = {
                0: 'FFC9D6E8',  // Light Blue
                1: 'FFC9D6E8',  // Light Blue
                2: 'FFC9D6E8',  // Light Blue
                3: 'FFC9D6E8',  // Light Blue
                4: 'FFC8E6C9',  // Light Green
                5: 'FFC8E6C9',  // Light Green
                6: 'FFFFCDD2',  // Light Red
                7: 'FFFFE0B2',  // Light Orange
                8: 'FFF3E5F5',  // Light Purple
                9: 'FFFFCDD2',  // Light Red
                10: 'FFFFE0B2', // Light Orange
                11: 'FFFFE0B2', // Light Orange
                12: 'FFFFCDD2', // Light Red
                13: 'FFFFCDD2', // Light Red
                14: 'FFB2DFDB', // Light Teal (Salary Calculated Days)
                15: 'FFFFE0B2', // Light Orange (Net Payable Salary TDS)
                16: 'FFB2DFDB', // Light Teal (Net Payable Salary)
                17: 'FFB3E5FC', // Light Cyan (Overtime Hours)
                18: 'FFB3E5FC', // Light Cyan (Overtime Amount)
                19: 'FFFFE0B2', // Light Orange (OT TDS)
                20: 'FFC8E6C9', // Light Green (Payable OT after Deduction)
                21: 'FFC8E6C9'  // Light Green (Total Payable Salary)
            };

            // Color data rows with light colors
            for (let i = 1; i < data.data.length + 1; i++) {
                for (let j = 0; j < headers.length; j++) {
                    const cellRef = XLSX.utils.encode_cell({ r: i, c: j });
                    if (ws[cellRef]) {
                        // Alternate between full light color and even lighter shade
                        const baseColor = rowLightColors[j] || 'FFF5F5F5';
                        if (i % 2 === 0) {
                            // Darker light shade for even rows
                            ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: baseColor } };
                        } else {
                            // Even lighter shade for odd rows
                            ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FFFAFAFA' } };
                        }
                        ws[cellRef].alignment = { horizontal: 'right', vertical: 'center' };
                        ws[cellRef].border = {
                            bottom: { style: 'thin', color: { rgb: 'FFE0E0E0' } },
                            right: { style: 'thin', color: { rgb: 'FFE0E0E0' } }
                        };
                    }
                }
            }

            console.log('Applying summary styling...');
            // Color summary section
            const summaryStartRow = data.data.length + 2;
            
            // Color SUMMARY header row - Dark Gradient Purple
            for (let j = 0; j < headers.length; j++) {
                const cellRef = XLSX.utils.encode_cell({ r: summaryStartRow, c: j });
                if (ws[cellRef]) {
                    ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FF6A4C93' } };
                    ws[cellRef].font = { color: { rgb: 'FFFFFFFF' }, bold: true, size: 12 };
                    ws[cellRef].alignment = { horizontal: 'left', vertical: 'center' };
                    ws[cellRef].border = {
                        top: { style: 'medium', color: { rgb: 'FF4A235A' } },
                        bottom: { style: 'medium', color: { rgb: 'FF4A235A' } }
                    };
                }
            }

            // Color summary data rows - Gradient pastel colors
            const summaryColors = ['FFE8D5F2', 'FFD4C9E8', 'FFD4C9E8', 'FFE8D5F2'];
            for (let i = summaryStartRow + 1; i < summaryStartRow + 5; i++) {
                for (let j = 0; j < headers.length; j++) {
                    const cellRef = XLSX.utils.encode_cell({ r: i, c: j });
                    if (ws[cellRef]) {
                        const colorIndex = i - summaryStartRow - 1;
                        ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: summaryColors[colorIndex] || 'FFE8D5F2' } };
                        ws[cellRef].font = { bold: j === 0, size: 11 };
                        ws[cellRef].alignment = { horizontal: j === 0 ? 'left' : 'right', vertical: 'center' };
                        ws[cellRef].border = {
                            bottom: { style: 'thin', color: { rgb: 'FFC9B1D8' } }
                        };
                    }
                }
            }

            console.log('Setting column widths...');
            // Set column widths
            ws['!cols'] = [
                { wch: 15 },  // Employee ID
                { wch: 20 },  // Name
                { wch: 15 },  // Role
                { wch: 15 },  // Base Salary
                { wch: 14 },  // Working Days
                { wch: 14 },  // Present Days
                { wch: 12 },  // Late Days
                { wch: 14 },  // 1+ Hour Late
                { wch: 12 },  // Leave Taken
                { wch: 16 },  // Leave Deduction
                { wch: 14 },  // Late Deduction
                { wch: 18 },  // 1+ Hour Late Deduction
                { wch: 18 },  // 4th Saturday Deduction
                { wch: 14 },  // Penalty Days
                { wch: 20 },  // Salary Calculated Days
                { wch: 20 },  // Net Payable Salary TDS
                { wch: 18 },  // Net Payable Salary
                { wch: 16 },  // Overtime Hours
                { wch: 16 },  // Overtime Amount
                { wch: 12 },  // OT TDS
                { wch: 22 },  // Payable OT after Deduction
                { wch: 15 }   // Final Salary
            ];

            console.log('Adding worksheet to workbook...');
            // Add worksheet to workbook
            XLSX.utils.book_append_sheet(wb, ws, 'Payroll Report');

            // Format the filename with month and year
            const monthName = new Date(year, month - 1).toLocaleString('default', { month: 'long' });
            const filename = `payroll_report_${monthName}_${year}.xlsx`;

            console.log('Writing file:', filename);
            // Write the file
            XLSX.writeFile(wb, filename);

            reportBtn.innerHTML = originalText;
            reportBtn.disabled = false;
            showNotification('Success', 'Report generated and downloaded successfully!', 'success');
        })
        .catch(error => {
            console.error('Error:', error);
            reportBtn.innerHTML = originalText;
            reportBtn.disabled = false;
            showNotification('Error', 'Error generating report. Please try again.', 'error');
        });
}

function exportToExcel() {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    
    if (!month || !year) {
        showNotification('Warning', 'Please select month and year', 'warning');
        return;
    }

    // Show loading state
    const exportBtn = event.target.closest('button');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    exportBtn.disabled = true;

    // Use a more reliable CDN URL for XLSX
    const xslxUrl = 'https://unpkg.com/xlsx@latest/dist/xlsx.full.min.js';
    
    // Check if SheetJS is loaded, if not load it
    if (!window.XLSX) {
        const script = document.createElement('script');
        script.src = xslxUrl;
        script.async = true;
        
        script.onload = () => {
            console.log('XLSX library loaded successfully from:', xslxUrl);
            performExport(month, year, exportBtn, originalText);
        };
        
        script.onerror = () => {
            console.error('Failed to load XLSX library from:', xslxUrl);
            showNotification('Error', 'Error loading Excel library. Please try again.', 'error');
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        };
        
        // Set a timeout to catch loading issues
        setTimeout(() => {
            if (!window.XLSX) {
                console.error('XLSX library failed to load within timeout');
                showNotification('Error', 'Error: Excel library took too long to load. Please try again.', 'error');
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }
        }, 15000);
        
        document.head.appendChild(script);
    } else {
        console.log('XLSX library already loaded');
        performExport(month, year, exportBtn, originalText);
    }
}

function performExport(month, year, exportBtn, originalText) {
    console.log('Starting export for month:', month, 'year:', year);
    
    // Fetch data from the export handler
    fetch(`../../export_monthly_analytics_excel_data_handler.php?month=${month}&year=${year}`)
        .then(response => {
            console.log('Response received, status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (data.status !== 'success') {
                throw new Error(data.message || 'Export failed');
            }
            
            if (!data.data || data.data.length === 0) {
                throw new Error('No data available for export');
            }

            console.log('Creating Excel workbook...');

            // Create workbook
            const wb = XLSX.utils.book_new();
            
            // Prepare data for worksheet
            const headers = [
                'Employee ID',
                'Name',
                'Role',
                'Base Salary',
                'Working Days',
                'Present Days',
                'Late Days',
                '1+ Hour Late',
                'Leave Taken',
                'Leave Deduction',
                'Late Deduction',
                '1+ Hour Late Deduction',
                '4th Saturday Deduction',
                'Salary Calculated Days',
                'Net Payable Salary',
                'Net Payable Salary TDS',
                'Payable Salary After Deduction',
                'Overtime Hours',
                'Overtime Amount',
                'OT TDS',
                'Payable OT after Deduction',
                'Total TDS Amount (Govt. Amount)',
                'Total Payable Salary'
            ];

            // Create data rows
            const worksheetData = [headers];
            data.data.forEach(emp => {
                worksheetData.push([
                    emp.employee_id || '',
                    emp.name || '',
                    emp.role || '',
                    emp.base_salary || 0,
                    emp.working_days || 0,
                    emp.present_days || 0,
                    emp.late_days || 0,
                    emp.one_hour_late || 0,
                    emp.leave_taken || 0,
                    emp.leave_deduction || 0,
                    emp.late_deduction || 0,
                    emp.one_hour_late_deduction || 0,
                    emp.fourth_saturday_deduction || 0,
                    emp.salary_calculated_days || 0,
                    Number((Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1)))).toFixed(2)),
                    Number((Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (Number(emp.tds_percentage || 0) / 100)).toFixed(2)),
                    Number(Math.max(0, Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (1 - Number(emp.tds_percentage || 0) / 100)).toFixed(2)),
                    emp.overtime_hours || 0,
                    emp.overtime_amount || 0,
                    Number((Number(emp.overtime_amount || 0) * (Number(emp.tds_percentage || 0) / 100)).toFixed(2)),
                    Number((Math.max(0, Number(emp.overtime_amount || 0) * (1 - Number(emp.tds_percentage || 0) / 100))).toFixed(2)),
                    Number((
                        Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (Number(emp.tds_percentage || 0) / 100)
                        + Number(emp.overtime_amount || 0) * (Number(emp.tds_percentage || 0) / 100)
                    ).toFixed(2)),
                    Number((Math.max(0,
                        Number(emp.salary_calculated_days || 0) * (Number(emp.gross_salary || 0) / (Number(emp.working_days || 1))) * (1 - Number(emp.tds_percentage || 0) / 100)
                        + Number(emp.overtime_amount || 0) * (1 - Number(emp.tds_percentage || 0) / 100)
                    )).toFixed(2))
                ]);
            });

            // Add empty row and summary section
            worksheetData.push([]);
            worksheetData.push(['SUMMARY']);
            worksheetData.push(['Total Salary (Without Overtime)', data.summary.total_salary_without_overtime]);
            worksheetData.push(['Total Salary (With Overtime)', data.summary.total_salary_with_overtime]);
            worksheetData.push(['Total Overtime Amount', data.summary.total_overtime_amount]);
            worksheetData.push(['Total Employees', data.summary.employee_count]);

            console.log('Creating worksheet...');
            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(worksheetData);

            console.log('Applying header styling...');
            // Color header row - Dark Blue (#366092)
            for (let i = 0; i < headers.length; i++) {
                const cellRef = XLSX.utils.encode_cell({ r: 0, c: i });
                if (ws[cellRef]) {
                    ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FF366092' } };
                    ws[cellRef].font = { color: { rgb: 'FFFFFFFF' }, bold: true };
                }
            }

            console.log('Applying alternating row colors...');
            // Color data rows with alternating pattern - Light Gray
            for (let i = 1; i < data.data.length + 1; i++) {
                if (i % 2 === 0) {
                    for (let j = 0; j < headers.length; j++) {
                        const cellRef = XLSX.utils.encode_cell({ r: i, c: j });
                        if (ws[cellRef]) {
                            ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FFF5F5F5' } };
                        }
                    }
                }
            }

            console.log('Applying summary styling...');
            // Color summary section
            const summaryStartRow = data.data.length + 2;
            
            // Color SUMMARY header row - Dark Blue
            for (let j = 0; j < headers.length; j++) {
                const cellRef = XLSX.utils.encode_cell({ r: summaryStartRow, c: j });
                if (ws[cellRef]) {
                    ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FF366092' } };
                    ws[cellRef].font = { color: { rgb: 'FFFFFFFF' }, bold: true };
                }
            }

            // Color summary data rows - Light Blue
            for (let i = summaryStartRow + 1; i < summaryStartRow + 5; i++) {
                for (let j = 0; j < headers.length; j++) {
                    const cellRef = XLSX.utils.encode_cell({ r: i, c: j });
                    if (ws[cellRef]) {
                        ws[cellRef].fill = { patternType: 'solid', fgColor: { rgb: 'FFE8F0F8' } };
                    }
                }
            }

            console.log('Setting column widths...');
            // Set column widths
            ws['!cols'] = [
                { wch: 15 },  // Employee ID
                { wch: 20 },  // Name
                { wch: 15 },  // Role
                { wch: 15 },  // Base Salary
                { wch: 12 },  // Working Days
                { wch: 12 },  // Present Days
                { wch: 12 },  // Late Days
                { wch: 12 },  // 1+ Hour Late
                { wch: 12 },  // Leave Taken
                { wch: 15 },  // Leave Deduction
                { wch: 15 },  // Late Deduction
                { wch: 15 },  // 1+ Hour Late Deduction
                { wch: 15 },  // 4th Saturday Deduction
                { wch: 15 },  // Salary Calculated Days
                { wch: 18 },  // Net Payable Salary TDS
                { wch: 18 },  // Net Payable Salary
                { wch: 15 },  // Overtime Hours
                { wch: 15 },  // Overtime Amount
                { wch: 12 },  // OT TDS
                { wch: 22 },  // Payable OT after Deduction
                { wch: 15 }   // Final Salary
            ];

            console.log('Appending sheet to workbook...');
            // Add worksheet to workbook
            XLSX.utils.book_append_sheet(wb, ws, 'Analytics');

            // Generate filename
            const monthName = document.querySelector('select[name="month"] option:checked').text;
            const filename = `Analytics_${monthName}_${year}_${new Date().getTime()}.xlsx`;

            console.log('Writing file:', filename);
            // Write file
            XLSX.writeFile(wb, filename);

            console.log('Export completed successfully');
            
            // Reset button
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;

            // Show success message
            showNotification('Success', 'Export completed successfully!', 'success');
        })
        .catch(error => {
            console.error('Error in export:', error);
            showNotification('Error', 'Error exporting data: ' + error.message, 'error');
            
            // Reset button
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        });
}

// Present Days modal logic
function showPresentDaysDetails(userId, employeeName) {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;

    if (!month || !year) {
        showNotification('Warning', 'Please select month and year', 'warning');
        return;
    }

    // Clear previous data
    document.getElementById('presentDaysTbody').innerHTML = '';
    document.getElementById('presentDaysUserInfo').innerText = `Loading present days for ${employeeName}...`;

    fetch(`../../get_present_days.php?user_id=${userId}&month=${month}&year=${year}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.status !== 'success') {
                document.getElementById('presentDaysUserInfo').innerText = 'No records found';
                return;
            }

            const records = data.records || [];
            const leaves = data.leaves || {};
            const weeklyOffs = (data.weekly_offs || []).map(d => String(d).trim().toLowerCase());
            
            // Build a map of records by date for quick lookup
            const recMap = {};
            records.forEach(r => { recMap[r.date] = r; });

            // Determine last day of month
            const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate();
            const monthIndex = parseInt(month) - 1;
            const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

            let totalPresentCount = 0;
            let html = '';
            const tbody = document.getElementById('presentDaysTbody');

            for (let day = 1; day <= lastDay; day++) {
                const dt = new Date(parseInt(year), monthIndex, day);
                const year_pad = dt.getFullYear();
                const month_pad = String(dt.getMonth() + 1).padStart(2, '0');
                const day_pad = String(dt.getDate()).padStart(2, '0');
                const iso = `${year_pad}-${month_pad}-${day_pad}`;
                const displayDate = (('0' + dt.getDate()).slice(-2)) + '-' + monthNames[dt.getMonth()] + '-' + dt.getFullYear();
                const dayName = dt.toLocaleDateString('en-US', { weekday: 'long' });
                const isWeekly = weeklyOffs.indexOf(dayName.toLowerCase()) !== -1;

                const rec = recMap[iso];
                const dayLeaves = leaves[iso] || [];
                
                let leaveBadgesHtml = '';
                let paidLeaveCredit = 0;
                
                dayLeaves.forEach(lv => {
                    const lType = lv.type.toLowerCase();
                    const isPaid = lType.includes('casual') || lType.includes('compensate');
                    
                    if (isPaid) {
                        paidLeaveCredit += lv.duration;
                    }
                    
                    let badgeColor = 'background:#f3f4f6; color:#374151;';
                    if (lType.includes('casual')) {
                        badgeColor = 'background:#dcfce7; color:#166534;';
                    } else if (lType.includes('compensate')) {
                        badgeColor = 'background:#fef9c3; color:#854d0e;';
                    } else if (lType.includes('unpaid')) {
                        badgeColor = 'background:#fee2e2; color:#991b1b;';
                    } else if (lType.includes('half')) {
                        badgeColor = 'background:#e0e7ff; color:#3730a3;';
                    }
                    
                    leaveBadgesHtml += `<span class="badge" style="margin-left:8px; ${badgeColor} padding:4px 6px; border-radius:4px; font-size:0.75rem;">${lv.type}</span>`;
                });

                let punchCredit = 0;
                if (rec) {
                    punchCredit = (rec.status === 'half_day' ? 0.5 : 1.0);
                }

                // Total credit for the day is physical punch only (to match dashboard present_days)
                const dailyCredit = punchCredit;
                totalPresentCount += dailyCredit;

                if (rec) {
                    const highlight = rec.is_weekly_off ? 'background:#fff7ed;' : '';
                    const weeklyBadge = rec.is_weekly_off ? '<span class="badge badge-warning" style="margin-left:8px; background:#fef3c7; color:#92400e; padding:4px 6px; border-radius:4px; font-size:0.75rem;">Weekly Off</span>' : '';
                    const presentOnWeeklyOffNote = rec.is_weekly_off ? ' <strong style="color:#92400e; font-size:0.9rem;">(Present on weekly off)</strong>' : '';
                    
                    const _otDisplay = (() => {
                        if (!rec.overtime_hours) return '-';
                        const _p = rec.overtime_hours.split(':');
                        const _totalMins = parseInt(_p[0] || 0) * 60 + parseInt(_p[1] || 0);
                        return _totalMins >= 90 ? rec.overtime_hours : '-';
                    })();
                    const _inBtn  = rec.punch_in_photo  ? ` <button onclick="showAttendancePhoto('${encodeURIComponent(rec.punch_in_photo)}','Punch In')" style="background:none;border:none;cursor:pointer;font-size:1rem;padding:0 2px;" title="View Punch In Photo">📷</button>` : '';
                    const _outBtn = rec.punch_out_photo ? ` <button onclick="showAttendancePhoto('${encodeURIComponent(rec.punch_out_photo)}','Punch Out')" style="background:none;border:none;cursor:pointer;font-size:1rem;padding:0 2px;" title="View Punch Out Photo">📷</button>` : '';
                    
                    const halfDayBadge = rec.status === 'half_day' ? '<span class="badge" style="margin-left:8px; background:#e0e7ff; color:#3730a3; padding:4px 6px; border-radius:4px; font-size:0.75rem;">Half Day</span>' : '';
                    
                    html += `<tr style="border-bottom:1px solid #e2e8f0; ${highlight}"><td style="padding:8px">${displayDate} ${weeklyBadge} ${halfDayBadge} ${leaveBadgesHtml}</td><td style="padding:8px">${dayName}${presentOnWeeklyOffNote}</td><td style="padding:8px">${rec.punch_in || '-'}${_inBtn}</td><td style="padding:8px">${rec.punch_out || '-'}${_outBtn}</td><td style="padding:8px">${rec.working_hours || '-'}</td><td style="padding:8px">${_otDisplay}</td></tr>`;
                } else {
                    // No record for this date
                    const weeklyBadge = isWeekly ? '<span class="badge" style="margin-left:8px; background:#f1f5f9; color:#4a5568; padding:3px 6px; border-radius:4px; font-size:0.75rem;">Weekly Off</span>' : '';
                    html += `<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:8px">${displayDate} ${weeklyBadge} ${leaveBadgesHtml}</td><td style="padding:8px">${dayName}</td><td style="padding:8px">-</td><td style="padding:8px">-</td><td style="padding:8px">-</td><td style="padding:8px">-</td></tr>`;
                }
            }

            document.getElementById('presentDaysUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${totalPresentCount.toFixed(1)} present day(s)`;
            tbody.innerHTML = html;
            document.getElementById('presentDaysModal').style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('presentDaysUserInfo').innerText = 'Error loading present days';
        });
}

function closePresentDaysModal() {
    document.getElementById('presentDaysModal').style.display = 'none';
}

/**
 * Show an attendance photo (punch-in or punch-out) in an inline lightbox.
 * Handles three storage formats:
 *   1. data:image/... base64 string  → used directly
 *   2. http/https URL                → used directly
 *   3. bare filename / relative path → prepended with ../../uploads/attendance/
 */
function showAttendancePhoto(encodedPath, label) {
    const raw = decodeURIComponent(encodedPath);
    let src;
    if (!raw || raw === 'null') {
        alert('No photo available.');
        return;
    }
    if (raw.startsWith('data:image') || raw.startsWith('http')) {
        src = raw;
    } else if (raw.includes('uploads/attendance/')) {
        // Already has the folder prefix; resolve from connect root
        src = '../../' + raw.replace(/^\/+/, '');
    } else {
        // Bare filename — build full path relative to connect root
        src = '../../uploads/attendance/' + raw.replace(/.*\//, '');
    }

    // Remove any existing lightbox
    const existing = document.getElementById('_attPhotoLightbox');
    if (existing) existing.remove();

    // Build lightbox
    const overlay = document.createElement('div');
    overlay.id = '_attPhotoLightbox';
    overlay.style.cssText = [
        'position:fixed', 'inset:0', 'z-index:99999',
        'background:rgba(0,0,0,0.82)',
        'display:flex', 'align-items:center', 'justify-content:center',
        'flex-direction:column', 'gap:12px'
    ].join(';');

    // Close on backdrop click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.remove();
    });

    const box = document.createElement('div');
    box.style.cssText = 'background:#fff;border-radius:12px;padding:20px;max-width:90vw;max-height:90vh;display:flex;flex-direction:column;align-items:center;gap:12px;overflow:auto;';

    // Header
    const header = document.createElement('div');
    header.style.cssText = 'width:100%;display:flex;justify-content:space-between;align-items:center;';
    const title = document.createElement('span');
    title.textContent = label + ' Photo';
    title.style.cssText = 'font-weight:600;font-size:1rem;color:#1a202c;';
    const closeBtn = document.createElement('button');
    closeBtn.textContent = '✕';
    closeBtn.style.cssText = 'background:none;border:none;font-size:1.25rem;cursor:pointer;color:#718096;line-height:1;';
    closeBtn.onclick = () => overlay.remove();
    header.appendChild(title);
    header.appendChild(closeBtn);

    // Image
    const img = document.createElement('img');
    img.alt = label + ' photo';
    img.style.cssText = 'max-width:75vw;max-height:70vh;object-fit:contain;border-radius:6px;display:block;';
    img.onerror = function() {
        img.style.display = 'none';
        const errMsg = document.createElement('p');
        errMsg.textContent = '⚠️ Could not load photo. The file may be missing or the path is incorrect.';
        errMsg.style.cssText = 'color:#e53e3e;font-size:0.9rem;text-align:center;padding:20px;';
        box.appendChild(errMsg);
    };
    img.src = src;

    box.appendChild(header);
    box.appendChild(img);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
}

// Late Days modal logic
function showLateDaysDetails(userId, employeeName) {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;

    if (!month || !year) {
        showNotification('Warning', 'Please select month and year', 'warning');
        return;
    }

    document.getElementById('lateDaysTbody').innerHTML = '';
    document.getElementById('lateDaysUserInfo').innerText = `Loading late days for ${employeeName}...`;

    fetch(`../../get_late_days.php?user_id=${userId}&month=${month}&year=${year}`)
        .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
        .then(data => {
            if (data.status !== 'success') {
                document.getElementById('lateDaysUserInfo').innerText = 'No records found';
                return;
            }

            const records = data.records || [];
            const balance = Number(data.short_leave_balance || 0);

            document.getElementById('lateDaysUserInfo').innerHTML =
                `${employeeName} &mdash; ${data.monthYear} &mdash; ${records.length} late day(s)
                <span style="margin-left:12px; padding:3px 10px; border-radius:12px;
                    background:${balance > 0 ? '#dcfce7' : '#fee2e2'};
                    color:${balance > 0 ? '#166534' : '#991b1b'};
                    font-size:0.82rem; font-weight:600;">
                    Short Leave Balance: ${balance}
                </span>`;

            const tbody = document.getElementById('lateDaysTbody');
            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="padding:12px; text-align:center; color:#718096;">No late punch-in records found for this period.</td></tr>';
            } else {
                let html = '';
                records.forEach(r => {
                    const alreadyApplied = r.short_leave !== 'N/A';
                    const slEligible = r.is_sl_eligible !== false; // punch_in <= shift_start + 90 min
                    const canApply = balance > 0 && !alreadyApplied && slEligible;
                    const cbId = `sl-late-${userId}-${r.date}`;
                    const reasonId = `reason-late-${userId}-${r.date}`;
                    const btnId = `btn-late-${userId}-${r.date}`;

                    // Action cell content
                    let actionCell;
                    if (alreadyApplied) {
                        actionCell = `<span style="color:#166534; font-size:0.78rem; font-weight:600; white-space:nowrap; display:inline-block;">&#10003; Done</span>`;
                    } else if (!slEligible) {
                        actionCell = `<span title="Punch-in beyond shift start + 90 min"
                            style="color:#94a3b8; font-size:0.75rem; white-space:nowrap; display:inline-block; cursor:help;">&#x26D4; Not eligible</span>`;
                    } else {
                        actionCell = `<label title="${balance > 0 ? 'Apply short leave for this day' : 'No short leave balance'}"
                            style="display:inline-flex; align-items:center; gap:5px; white-space:nowrap;
                                   cursor:${canApply ? 'pointer' : 'not-allowed'}; opacity:${canApply ? '1' : '0.4'}">
                            <input type="checkbox" id="${cbId}"
                                ${canApply ? '' : 'disabled'}
                                onchange="openShortLeavePopup(this, ${userId}, '${r.date}', '${r.displayDate}', 'late')">
                            <span style="font-size:0.78rem; white-space:nowrap; color:#374151;">Apply SL</span>
                        </label>`;
                    }

                    html += `
                    <tr id="row-late-${r.date}" style="border-bottom:1px solid #e2e8f0; background:#fef3c7;">
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.displayDate}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.day}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.shift_start_time}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.punch_in}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.minutes_late} min</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${alreadyApplied
                            ? `<span style="color:#166534; font-weight:600;">&#10003; ${r.short_leave}</span>`
                            : '<span style="color:#9ca3af;">N/A</span>'}
                        </td>
                        <td style="padding:8px 12px; text-align:center; white-space:nowrap;">${actionCell}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            }

            document.getElementById('lateDaysModal').style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('lateDaysUserInfo').innerText = 'Error loading late days';
        });
}

function closeLateDaysModal() {
    document.getElementById('lateDaysModal').style.display = 'none';
}

function showOneHourLateDaysDetails(userId, employeeName) {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;

    if (!month || !year) {
        showNotification('Warning', 'Please select month and year', 'warning');
        return;
    }

    document.getElementById('oneHourLateDaysTbody').innerHTML = '';
    document.getElementById('oneHourLateDaysUserInfo').innerText = `Loading 1+ hour late days for ${employeeName}...`;

    fetch(`../../get_one_hour_late_days.php?user_id=${userId}&month=${month}&year=${year}`)
        .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
        .then(data => {
            if (data.status !== 'success') {
                document.getElementById('oneHourLateDaysUserInfo').innerText = 'No records found';
                return;
            }

            const records = data.records || [];
            const balance = Number(data.short_leave_balance || 0);

            document.getElementById('oneHourLateDaysUserInfo').innerHTML =
                `${employeeName} &mdash; ${data.monthYear} &mdash; ${records.length} very late day(s)
                <span style="margin-left:12px; padding:3px 10px; border-radius:12px;
                    background:${balance > 0 ? '#dcfce7' : '#fee2e2'};
                    color:${balance > 0 ? '#166534' : '#991b1b'};
                    font-size:0.82rem; font-weight:600;">
                    Short Leave Balance: ${balance}
                </span>`;

            const tbody = document.getElementById('oneHourLateDaysTbody');
            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="padding:12px; text-align:center; color:#718096;">No 1+ hour late punch-in records found for this period.</td></tr>';
            } else {
                let html = '';
                records.forEach(r => {
                    const alreadyApplied = r.short_leave !== 'N/A';
                    const slEligible = r.is_sl_eligible !== false; // punch_in <= shift_start + 90 min
                    const canApply = balance > 0 && !alreadyApplied && slEligible;
                    const cbId = `sl-1hr-${userId}-${r.date}`;
                    const reasonId = `reason-1hr-${userId}-${r.date}`;
                    const btnId = `btn-1hr-${userId}-${r.date}`;

                    // Action cell content
                    let actionCell;
                    if (alreadyApplied) {
                        actionCell = `<span style="color:#166534; font-size:0.78rem; font-weight:600; white-space:nowrap; display:inline-block;">&#10003; Done</span>`;
                    } else if (!slEligible) {
                        actionCell = `<span title="Punch-in beyond shift start + 90 min"
                            style="color:#94a3b8; font-size:0.75rem; white-space:nowrap; display:inline-block; cursor:help;">&#x26D4; Not eligible</span>`;
                    } else {
                        actionCell = `<label title="${balance > 0 ? 'Apply short leave for this day' : 'No short leave balance'}"
                            style="display:inline-flex; align-items:center; gap:5px; white-space:nowrap;
                                   cursor:${canApply ? 'pointer' : 'not-allowed'}; opacity:${canApply ? '1' : '0.4'}">
                            <input type="checkbox" id="${cbId}"
                                ${canApply ? '' : 'disabled'}
                                onchange="openShortLeavePopup(this, ${userId}, '${r.date}', '${r.displayDate}', 'one_hour_late')">
                            <span style="font-size:0.78rem; white-space:nowrap; color:#374151;">Apply SL</span>
                        </label>`;
                    }

                    html += `
                    <tr id="row-1hr-${r.date}" style="border-bottom:1px solid #e2e8f0; background:#fee2e2;">
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.displayDate}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.day}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.shift_start_time}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.punch_in}</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${r.minutes_late} min</td>
                        <td style="padding:8px 12px; font-size:0.85rem;">${alreadyApplied
                            ? `<span style="color:#166534; font-weight:600;">&#10003; ${r.short_leave}</span>`
                            : '<span style="color:#9ca3af;">N/A</span>'}
                        </td>
                        <td style="padding:8px 12px; text-align:center; white-space:nowrap;">${actionCell}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            }

            document.getElementById('oneHourLateDaysModal').style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('oneHourLateDaysUserInfo').innerText = 'Error loading 1+ hour late days';
        });
}

function closeOneHourLateDaysModal() {
    document.getElementById('oneHourLateDaysModal').style.display = 'none';
}

// ─── Short Leave Floating Popup ───────────────────────────────────────────────
let _slPopup = { userId: null, date: null, lateType: null, checkboxEl: null };
let _slJustOpened = false; // guard: prevent click-outside closing the popup in the same tick it opened

function openShortLeavePopup(checkboxEl, userId, date, displayDate, lateType) {
    if (!checkboxEl.checked) { closeShortLeavePopup(); return; }

    _slPopup = { userId, date, lateType, checkboxEl };

    const popup = document.getElementById('shortLeavePopup');
    const reasonInput = document.getElementById('slPopupReason');
    const saveBtn = document.getElementById('slPopupSaveBtn');

    document.getElementById('slPopupDate').textContent = `Date: ${displayDate}`;
    reasonInput.value = '';
    reasonInput.style.borderColor = '#d1d5db';
    reasonInput.placeholder = 'Enter reason (required)...';

    // Always reset button state in case a previous apply left it in a bad state
    saveBtn.disabled = false;
    saveBtn.innerHTML = '&#10003; Save &amp; Apply';

    // Position popup near the checkbox
    const rect = checkboxEl.getBoundingClientRect();
    const popupW = 280;
    let left = rect.right + 8;
    if (left + popupW > window.innerWidth - 10) left = rect.left - popupW - 8;
    let top = rect.top - 20;
    if (top + 160 > window.innerHeight) top = window.innerHeight - 170;

    popup.style.left = left + 'px';
    popup.style.top  = top  + 'px';
    popup.style.display = 'block';

    // Set guard flag so click-outside listener ignores this same click event
    _slJustOpened = true;
    setTimeout(() => { _slJustOpened = false; }, 0);

    reasonInput.focus();
}

function closeShortLeavePopup() {
    const popup = document.getElementById('shortLeavePopup');
    popup.style.display = 'none';
    // Uncheck the checkbox if cancelled
    if (_slPopup.checkboxEl) _slPopup.checkboxEl.checked = false;
    _slPopup = { userId: null, date: null, lateType: null, checkboxEl: null };
}

function submitShortLeavePopup() {
    const reason = document.getElementById('slPopupReason').value.trim();
    const saveBtn = document.getElementById('slPopupSaveBtn');
    const reasonInput = document.getElementById('slPopupReason');

    if (!reason) {
        reasonInput.style.borderColor = '#ef4444';
        reasonInput.placeholder = 'Reason is required!';
        return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    fetch('../../apply_short_leave_for_late_day.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: _slPopup.userId,
            date: _slPopup.date,
            reason,
            late_type: _slPopup.lateType
        })
    })
    .then(r => r.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '&#10003; Save &amp; Apply';

        if (data.status === 'success') {
            // Update the row
            const prefix = _slPopup.lateType === 'late' ? 'row-late' : 'row-1hr';
            const row = document.getElementById(`${prefix}-${_slPopup.date}`);
            if (row) {
                row.cells[5].innerHTML = '<span style="color:#166534; font-weight:600;">&#10003; Short Leave</span>';
                row.cells[6].innerHTML = '<span style="color:#166534; font-size:0.78rem; font-weight:600;">&#10003; Done</span>';
            }
            // Update balance badge
            const infoId = _slPopup.lateType === 'late' ? 'lateDaysUserInfo' : 'oneHourLateDaysUserInfo';
            const tbodyId = _slPopup.lateType === 'late' ? 'lateDaysTbody' : 'oneHourLateDaysTbody';
            const badge = document.querySelector(`#${infoId} span`);
            if (badge) {
                const newBal = Number(data.remaining_balance || 0);
                badge.textContent = `Short Leave Balance: ${newBal}`;
                badge.style.background = newBal > 0 ? '#dcfce7' : '#fee2e2';
                badge.style.color      = newBal > 0 ? '#166534' : '#991b1b';
                // Only disable checkboxes inside THIS user's modal tbody
                if (newBal <= 0) {
                    const modalTbody = document.getElementById(tbodyId);
                    if (modalTbody) {
                        modalTbody.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                            if (!cb.checked) {
                                cb.disabled = true;
                                const label = cb.closest('label');
                                if (label) {
                                    label.style.cursor  = 'not-allowed';
                                    label.style.opacity = '0.4';
                                }
                            }
                        });
                    }
                }
            }
            // Close popup cleanly (don't uncheck — it stays checked as visual confirmation)
            _slPopup.checkboxEl = null;
            document.getElementById('shortLeavePopup').style.display = 'none';
            _slPopup = { userId: null, date: null, lateType: null, checkboxEl: null };

            showNotification('Success', 'Short leave applied and approved', 'success');
        } else {
            showNotification('Error', data.message || 'Failed to apply short leave', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        saveBtn.disabled = false;
        saveBtn.innerHTML = '&#10003; Save &amp; Apply';
        showNotification('Error', 'Network error. Please try again.', 'error');
    });
}

// Close popup when clicking outside
document.addEventListener('click', function(e) {
    // If popup was just opened in this same event tick, ignore
    if (_slJustOpened) return;

    const popup = document.getElementById('shortLeavePopup');
    if (popup && popup.style.display !== 'none') {
        // Allow clicks inside popup, on the checkbox, or on the label wrapping it
        const isInsidePopup = popup.contains(e.target);
        const isCheckbox    = !!e.target.closest('[id^="sl-"]');
        const isLabel       = !!e.target.closest('label');
        if (!isInsidePopup && !isCheckbox && !isLabel) {
            closeShortLeavePopup();
        }
    }
});

function showLeaveDetails(userId, employeeName) {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;

    if (!month || !year) {
        showNotification('Warning', 'Please select month and year', 'warning');
        return;
    }

    // Clear previous data
    document.getElementById('leaveDetailsTbody').innerHTML = '';
    document.getElementById('leaveDetailsUserInfo').innerText = `Loading leave details for ${employeeName}...`;

    fetch(`../../get_leave_records.php?user_id=${userId}&month=${month}&year=${year}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.status !== 'success') {
                document.getElementById('leaveDetailsUserInfo').innerText = 'No records found';
                return;
            }

            const records = data.records || [];
            const leaveCount = records.length;
            document.getElementById('leaveDetailsUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${leaveCount} leave application(s)`;

            const tbody = document.getElementById('leaveDetailsTbody');
            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#718096;">No approved leave records found for this period.</td></tr>';
            } else {
                let html = '';
                records.forEach(r => {
                    html += `<tr style="border-bottom:1px solid #e2e8f0; background:#fef9e7;"><td style="padding:12px 16px">${r.date_range}</td><td style="padding:12px 16px">${r.leave_type}</td><td style="padding:12px 16px text-align:center;">${r.num_days}</td><td style="padding:12px 16px">${r.reason}</td></tr>`;
                });
                tbody.innerHTML = html;
            }

            document.getElementById('leaveDetailsModal').style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('leaveDetailsUserInfo').innerText = 'Error loading leave details';
        });
}

function closeLeaveDetailsModal() {
    document.getElementById('leaveDetailsModal').style.display = 'none';
}

function showLeaveDeductionDetails(userId, employeeName, totalDeduction) {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;

    if (!month || !year) {
        showNotification('Warning', 'Please select month and year', 'warning');
        return;
    }

    // Clear previous data
    document.getElementById('leaveDeductionTbody').innerHTML = '';
    document.getElementById('leaveDeductionUserInfo').innerText = `Loading leave deduction details for ${employeeName}...`;
    document.getElementById('totalDeductionAmount').innerText = `₹${totalDeduction.toLocaleString('en-IN')}`;

    fetch(`../../calculate_leave_deductions.php?user_id=${userId}&month=${month}&year=${year}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.status !== 'success') {
                document.getElementById('leaveDeductionUserInfo').innerText = 'Unable to load deduction details';
                document.getElementById('leaveDeductionTbody').innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#718096;">No deduction data available.</td></tr>';
                document.getElementById('leaveDeductionModal').style.display = 'block';
                return;
            }

            const deductions = data.deductions || {};
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            const monthName = monthNames[parseInt(month) - 1];
            document.getElementById('leaveDeductionUserInfo').innerText = `${employeeName} — ${monthName} ${year} Leave Deductions`;

            const tbody = document.getElementById('leaveDeductionTbody');
            const leaveDeductions = deductions.leave_deductions || [];

            if (leaveDeductions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#718096;">No approved leave records found for this period.</td></tr>';
            } else {
                let html = '';
                leaveDeductions.forEach(item => {
                    const deductionAmount = Number(item.deduction).toLocaleString('en-IN', { 
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    html += `<tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:12px 16px">${item.leave_type || 'Unknown'}</td>
                        <td style="padding:12px 16px; text-align:center;">${item.num_days}</td>
                        <td style="padding:12px 16px; text-align:right;">₹${deductionAmount}</td>
                        <td style="padding:12px 16px; font-size:12px; color:#666;">${item.deduction_type || 'N/A'}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            }

            // Update total deduction display
            const totalDeductionValue = deductions.total_deduction || 0;
            document.getElementById('totalDeductionAmount').innerText = `₹${Number(totalDeductionValue).toLocaleString('en-IN', { 
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;

            document.getElementById('leaveDeductionModal').style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('leaveDeductionUserInfo').innerText = 'Error loading deduction details';
            document.getElementById('leaveDeductionTbody').innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#d32f2f;">Failed to load deduction data.</td></tr>';
            document.getElementById('leaveDeductionModal').style.display = 'block';
        });
}

function closeLeaveDeductionModal() {
    document.getElementById('leaveDeductionModal').style.display = 'none';
}

function showOvertimeDetails(userId, employeeName) {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;

    const container = document.getElementById('overtimeDetailsContainer');
    container.innerHTML = '<div style="text-align:center; padding:20px;"><div class="spinner"></div><p>Loading overtime details...</p></div>';

    fetch(`../../fetch_user_overtime_detailed_breakdown.php?user_id=${userId}&month=${month}&year=${year}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to fetch overtime details');
            }

            const salaryInfo = data.salary_info;
            const overtimeSummary = data.overtime_summary;
            const records = data.overtime_records || [];

            let html = `
                <div style="background:#fff; padding:16px; border-radius:6px; margin-bottom:15px;">
                    <h3 style="margin:0 0 12px 0; color:#1a202c;">Employee Information</h3>
                    <p style="margin:4px 0;"><strong>Name:</strong> ${employeeName}</p>
                    <p style="margin:4px 0;"><strong>Base Salary:</strong> ₹${Number(salaryInfo.base_salary).toLocaleString('en-IN')}</p>
                    <p style="margin:4px 0;"><strong>Per Hour Salary:</strong> ₹${Number(salaryInfo.per_hour_salary).toLocaleString('en-IN')}</p>
                </div>

                <div style="background:#fff; padding:16px; border-radius:6px; margin-bottom:15px;">
                    <h3 style="margin:0 0 12px 0; color:#1a202c;">Salary Structure</h3>
                    <p style="margin:4px 0;"><strong>Daily Salary:</strong> ₹${Number(salaryInfo.per_day_salary).toLocaleString('en-IN')}</p>
                    <p style="margin:4px 0;"><strong>Shift Hours:</strong> ${Number(salaryInfo.shift_hours).toFixed(2)} hours</p>
                    <p style="margin:4px 0;"><strong>Working Days (Month):</strong> ${salaryInfo.working_days} days</p>
                </div>

                <div style="background:#fff; padding:16px; border-radius:6px; margin-bottom:15px;">
                    <h3 style="margin:0 0 12px 0; color:#1a202c;">Overtime Summary</h3>
                    <p style="margin:4px 0;"><strong>Total Overtime Hours:</strong> ${Number(overtimeSummary.total_hours).toFixed(2)} hours</p>
                    <p style="margin:4px 0; font-weight:600; color:#2d3748;"><strong>Total Overtime Amount:</strong> ₹${Number(overtimeSummary.total_amount).toLocaleString('en-IN')}</p>
                </div>
            `;

            if (records.length > 0) {
                html += `
                    <div style="background:#fff; padding:16px; border-radius:6px;">
                        <h3 style="margin:0 0 12px 0; color:#1a202c;">Overtime Records (${records.length} entries)</h3>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f8f9fa; border-bottom:2px solid #2d3748;">
                                    <th style="padding:10px 12px; text-align:left; font-weight:600; color:#1a202c; border-right:1px solid #e2e8f0;">Date</th>
                                    <th style="padding:10px 12px; text-align:center; font-weight:600; color:#1a202c; border-right:1px solid #e2e8f0;">Hours</th>
                                    <th style="padding:10px 12px; text-align:right; font-weight:600; color:#1a202c; border-right:1px solid #e2e8f0;">Amount</th>
                                    <th style="padding:10px 12px; text-align:left; font-weight:600; color:#1a202c;">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                records.forEach((record, index) => {
                    const dateObj = new Date(record.date);
                    const displayDate = dateObj.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                    const rowBg = index % 2 === 0 ? '#ffffff' : '#fafbfc';
                    
                    html += `
                        <tr style="background:${rowBg}; border-bottom:1px solid #f0f0f0;">
                            <td style="padding:10px 12px; border-right:1px solid #e2e8f0;">${displayDate}</td>
                            <td style="padding:10px 12px; text-align:center; border-right:1px solid #e2e8f0;">${Number(record.hours).toFixed(2)}</td>
                            <td style="padding:10px 12px; text-align:right; border-right:1px solid #e2e8f0; font-weight:500;">₹${Number(record.amount).toLocaleString('en-IN')}</td>
                            <td style="padding:10px 12px;">
                                ${record.description ? `<small style="color:#666;">${record.description}</small>` : '<small style="color:#aaa;">-</small>'}
                            </td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                html += `
                    <div style="background:#fee2e2; padding:16px; border-radius:6px; text-align:center; color:#991b1b;">
                        <p style="margin:0;">No overtime records found for this month</p>
                    </div>
                `;
            }

            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error fetching overtime details:', error);
            container.innerHTML = `
                <div style="background:#fee2e2; padding:16px; border-radius:6px;">
                    <h3 style="margin:0 0 10px 0; color:#991b1b;">Error Loading Details</h3>
                    <p style="margin:0; color:#991b1b; font-size:0.9rem;">${error.message}</p>
                    <p style="margin:8px 0 0 0; font-size:0.85rem; color:#7f1d1d;">Employee: ${employeeName} | Month: ${month}/${year}</p>
                </div>
            `;
        });

    document.getElementById('overtimeDetailsModal').style.display = 'block';
}

function closeOvertimeDetailsModal() {
    document.getElementById('overtimeDetailsModal').style.display = 'none';
}

// Penalty Modal Functions
function openPenaltyModal(userId, employeeName, action) {
    const modal = document.getElementById('penaltyModal');
    const currentValue = document.getElementById(`penalty-value-${userId}`).innerText;
    
    document.getElementById('penaltyEmployeeName').innerText = employeeName;
    document.getElementById('penaltyCurrentValue').innerText = currentValue + ' days';
    
    const actionText = action === 'increase' ? 'Increase by 0.5 days' : 'Decrease by 0.5 days';
    document.getElementById('penaltyAction').innerText = actionText;
    document.getElementById('penaltyAction').style.color = action === 'increase' ? '#22543d' : '#c53030';
    
    // Store user and action info for form submission
    document.getElementById('penaltyAdjustmentForm').dataset.userId = userId;
    document.getElementById('penaltyAdjustmentForm').dataset.action = action;
    document.getElementById('penaltyAdjustmentForm').dataset.employeeName = employeeName;
    
    // Reset form
    document.getElementById('penaltyReason').value = '';
    document.getElementById('wordCount').innerText = '0';
    document.getElementById('penaltySubmitBtn').disabled = true;
    document.getElementById('penaltyMessage').style.display = 'none';
    
    modal.style.display = 'block';
}

function closePenaltyModal() {
    document.getElementById('penaltyModal').style.display = 'none';
    document.getElementById('penaltyAdjustmentForm').reset();
    document.getElementById('penaltyMessage').style.display = 'none';
}

// Word count validation for penalty reason
document.addEventListener('DOMContentLoaded', function() {
    const reasonTextarea = document.getElementById('penaltyReason');
    const submitBtn = document.getElementById('penaltySubmitBtn');
    const wordCountDisplay = document.getElementById('wordCount');
    
    if (reasonTextarea) {
        reasonTextarea.addEventListener('input', function() {
            const words = this.value.trim().split(/\s+/).filter(word => word.length > 0).length;
            wordCountDisplay.innerText = words;
            
            // Enable submit button only if at least 10 words
            submitBtn.disabled = words < 10;
            
            // Change color based on word count
            if (words < 10) {
                wordCountDisplay.style.color = '#c53030';
            } else {
                wordCountDisplay.style.color = '#22543d';
            }
        });
    }
    
    // Handle penalty form submission
    const penaltyForm = document.getElementById('penaltyAdjustmentForm');
    if (penaltyForm) {
        penaltyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitPenaltyAdjustment();
        });
    }
});

function submitPenaltyAdjustment() {
    const form = document.getElementById('penaltyAdjustmentForm');
    const userId = form.dataset.userId;
    const action = form.dataset.action;
    const employeeName = form.dataset.employeeName;
    const reason = document.getElementById('penaltyReason').value.trim();
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    
    // Validate word count again
    const words = reason.split(/\s+/).filter(word => word.length > 0).length;
    if (words < 10) {
        showPenaltyMessage('Please provide at least 10 words for the reason', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('penaltySubmitBtn');
    const originalText = submitBtn.innerText;
    submitBtn.innerText = 'Processing...';
    submitBtn.disabled = true;
    
    // Calculate new penalty value
    const currentValue = parseFloat(document.getElementById(`penalty-value-${userId}`).innerText);
    const newValue = action === 'increase' ? currentValue + 0.5 : currentValue - 0.5;
    
    const payload = {
        user_id: parseInt(userId),
        action: action,
        current_penalty: currentValue,
        new_penalty: newValue,
        reason: reason,
        month: parseInt(month),
        year: parseInt(year)
    };
    
    console.log('Penalty adjustment payload:', payload);
    
    // Send to backend
    fetch('../../save_penalty_adjustment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.status === 'success') {
            showPenaltyMessage(`Penalty adjustment saved successfully for ${employeeName}! New penalty: ${newValue.toFixed(1)} days`, 'success');
            
            // Update the UI immediately with new value
            document.getElementById(`penalty-value-${userId}`).innerText = newValue.toFixed(1);
            
            // Reset button and close modal after delay
            setTimeout(() => {
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
                // Reload data to reflect changes in salary calculated days
                const currentMonth = document.getElementById('month').value;
                const currentYear = document.getElementById('year').value;
                loadAnalyticsData(currentMonth, currentYear);
                closePenaltyModal();
            }, 1500);
        } else {
            showPenaltyMessage(data.message || 'Error saving penalty adjustment', 'error');
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showPenaltyMessage('Error saving penalty adjustment. Please try again.', 'error');
        submitBtn.innerText = originalText;
        submitBtn.disabled = false;
    });
}

function showPenaltyMessage(message, type) {
    const messageDiv = document.getElementById('penaltyMessage');
    messageDiv.innerText = message;
    messageDiv.style.display = 'block';
    
    if (type === 'success') {
        messageDiv.style.background = '#c6f6d5';
        messageDiv.style.color = '#22543d';
        messageDiv.style.border = '1px solid #9ae6b4';
    } else {
        messageDiv.style.background = '#fed7d7';
        messageDiv.style.color = '#742a2a';
        messageDiv.style.border = '1px solid #fc8787';
    }
}

function showSalaryCalcDetails(userId) {
    const emp = (window.analyticsDataById || {})[userId];
    if (!emp) return;

    const workingDays = Number(emp.working_days || 0);
    const baseSalary = Number(emp.base_salary || 0);
    const grossSalary = Number(emp.gross_salary || baseSalary);
    const dailySalary = workingDays > 0 ? (grossSalary / workingDays) : 0;

    const presentDays = Number(emp.present_days || 0);
    const casual = Number(emp.casual_leave_days || 0);
    const half = Number(emp.half_day_leave_days || 0);
    const compensate = Number(emp.compensate_leave_days || 0);
    const backOffice = Number(emp.back_office_leave_days || 0);
    const backOfficeUnused = Number(emp.back_office_unused_paid_days || 0);
    const leaveTaken = Number(emp.leave_taken || 0);

    const regularLateDays = Number(emp.late_days || 0);
    const regularLateDeductionDays = Math.floor(regularLateDays / 3) * 0.5;
    const oneHourLate = Number(emp.one_hour_late || 0);
    const oneHourLateDeductionDays = oneHourLate * 0.5;

    const fourthSatPenalty = Number(emp.fourth_saturday_deduction || 0) > 0 ? 2 : 0;

    const salaryCalc = Number(emp.salary_calculated_days || 0).toFixed(2);

    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;

    const container = document.getElementById('salaryCalcContainer');
    container.innerHTML = `<div style="text-align:center; padding:20px;"><div class="spinner"></div><p>Loading leave details...</p></div>`;

    // Fetch detailed leave records
    fetch(`../../get_leave_records.php?user_id=${userId}&month=${month}&year=${year}`)
        .then(response => response.json())
        .then(data => {
            let leaveDetailsHtml = '';
            
            if (data.status === 'success' && data.records && data.records.length > 0) {
                leaveDetailsHtml = '<div style="background:#f0fdf4; border:1px solid #86efac; border-radius:4px; padding:8px; margin-bottom:8px;"><strong style="color:#166534;">Leave Details:</strong><ul style="margin:6px 0 0 20px; font-size:0.9rem; color:#166534;">';
                
                data.records.forEach(record => {
                    leaveDetailsHtml += `<li>${record.date_range} - ${record.leave_type}</li>`;
                });
                
                leaveDetailsHtml += '</ul></div>';
            } else {
                leaveDetailsHtml = '<div style="background:#fef3c7; border:1px solid #fcd34d; border-radius:4px; padding:8px; margin-bottom:8px; font-size:0.9rem; color:#92400e;">No leave records for this period</div>';
            }

            container.innerHTML = `
                <p style="font-weight:600; text-align:center;">${emp.name || ''} — Month: ${month} / ${year}</p>
                <div style="background:white; padding:12px; border-radius:6px; margin-bottom:10px;">
                    <p style="margin:6px 0;"><strong>Base Salary:</strong> ₹${Number(baseSalary).toLocaleString('en-IN')}</p>
                    <p style="margin:6px 0;"><strong>TDS (${Number(emp.tds_percentage || 0).toFixed(2)}%):</strong> ₹${Number(emp.tds_amount || 0).toLocaleString('en-IN')}</p>
                    <p style="margin:6px 0; color:#166534; font-weight:600;"><strong>Gross Salary:</strong> ₹${Number(grossSalary).toLocaleString('en-IN')}</p>
                    <p style="margin:6px 0;"><strong>Working Days:</strong> ${workingDays} &middot; <strong>Daily Rate (on Gross):</strong> ₹${dailySalary.toFixed(2)}</p>
                </div>

                <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                    <h3 style="margin:6px 0;">Credits</h3>
                    <p style="margin:4px 0;"><strong>${presentDays} (present)</strong> ${casual ? `+ <strong>${casual} (casual)</strong>` : ''}</p>
                    ${backOffice ? `<p style="margin:4px 0;"><strong>${backOffice} (Back Office leave)</strong></p>` : ''}
                    ${backOfficeUnused ? `<p style="margin:4px 0;"><strong>${backOfficeUnused} (Back Office unused paid)</strong></p>` : ''}
                    ${leaveDetailsHtml}
                    <p style="margin:8px 0 0 0;"><strong>Total leave days (approved):</strong> ${leaveTaken}</p>
                </div>

                <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                    <h3 style="margin:6px 0;">Deductions</h3>
                    ${half ? `<p style="margin:4px 0;"><strong>Half-day deduction:</strong> ${half}×0.5 = ${(half*0.5).toFixed(2)} days</p>` : ''}
                    <p style="margin:4px 0;"><strong>Regular late deduction days:</strong> ${regularLateDeductionDays} (${regularLateDays} late days)</p>
                    <p style="margin:4px 0;"><strong>1+ hour late deduction days:</strong> ${oneHourLateDeductionDays} (${oneHourLate} 1+ hour late days)</p>
                    <p style="margin:4px 0;"><strong>4th Saturday penalty days:</strong> ${fourthSatPenalty}</p>
                </div>

                <div style="background:#f8f9fa; padding:12px; border-radius:6px;">
                    <p style="margin:6px 0;"><strong>Calculation:</strong></p>
                    <p style="margin:4px 0; font-weight:600;">[ Credits ] - [ Deductions ] = <span style="color:#2d3748;">${salaryCalc} days</span></p>
                    <p style="margin:4px 0; font-size:12px; color:#666;">Credits: ${presentDays} + ${casual} + ${backOffice} + ${backOfficeUnused} = ${(presentDays + casual + backOffice + backOfficeUnused).toFixed(2)}<br>Deductions: ${(half*0.5).toFixed(2)} (half-day) + ${regularLateDeductionDays} + ${oneHourLateDeductionDays} + ${fourthSatPenalty} = ${((half*0.5) + regularLateDeductionDays + oneHourLateDeductionDays + fourthSatPenalty).toFixed(2)}</p>
                </div>
            `;
        })
        .catch(error => {
            console.error('Error fetching leave details:', error);
            container.innerHTML = `
                <p style="font-weight:600; text-align:center;">${emp.name || ''} — Month: ${month} / ${year}</p>
                <div style="background:white; padding:12px; border-radius:6px; margin-bottom:10px;">
                    <p style="margin:6px 0;"><strong>Base Salary:</strong> ₹${Number(baseSalary).toLocaleString('en-IN')}</p>
                    <p style="margin:6px 0;"><strong>Working Days:</strong> ${workingDays} &middot; <strong>Daily Salary:</strong> ₹${dailySalary.toFixed(2)}</p>
                </div>

                <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                    <h3 style="margin:6px 0;">Credits</h3>
                    <p style="margin:4px 0;"><strong>${presentDays} (present)</strong> ${casual ? `+ <strong>${casual} (casual)</strong>` : ''}</p>
                    ${backOffice ? `<p style="margin:4px 0;"><strong>${backOffice} (Back Office leave)</strong></p>` : ''}
                    ${backOfficeUnused ? `<p style="margin:4px 0;"><strong>${backOfficeUnused} (Back Office unused paid)</strong></p>` : ''}
                    <p style="margin:4px 0;"><strong>Total leave days (approved):</strong> ${leaveTaken}</p>
                </div>

                <div style="background:#fff; padding:12px; border-radius:6px; margin-bottom:10px;">
                    <h3 style="margin:6px 0;">Deductions</h3>
                    ${half ? `<p style="margin:4px 0;"><strong>Half-day deduction:</strong> ${half}×0.5 = ${(half*0.5).toFixed(2)} days</p>` : ''}
                    <p style="margin:4px 0;"><strong>Regular late deduction days:</strong> ${regularLateDeductionDays} (${regularLateDays} late days)</p>
                    <p style="margin:4px 0;"><strong>1+ hour late deduction days:</strong> ${oneHourLateDeductionDays} (${oneHourLate} 1+ hour late days)</p>
                    <p style="margin:4px 0;"><strong>4th Saturday penalty days:</strong> ${fourthSatPenalty}</p>
                </div>

                <div style="background:#f8f9fa; padding:12px; border-radius:6px;">
                    <p style="margin:6px 0;"><strong>Calculation:</strong></p>
                    <p style="margin:4px 0; font-weight:600;">[ Credits ] - [ Deductions ] = <span style="color:#2d3748;">${salaryCalc} days</span></p>
                    <p style="margin:4px 0; font-size:12px; color:#666;">Credits: ${presentDays} + ${casual} + ${backOffice} + ${backOfficeUnused} = ${(presentDays + casual + backOffice + backOfficeUnused).toFixed(2)}<br>Deductions: ${(half*0.5).toFixed(2)} (half-day) + ${regularLateDeductionDays} + ${oneHourLateDeductionDays} + ${fourthSatPenalty} = ${((half*0.5) + regularLateDeductionDays + oneHourLateDeductionDays + fourthSatPenalty).toFixed(2)}</p>
                </div>
            `;
        });

    document.getElementById('salaryCalcModal').style.display = 'block';
}

function closeSalaryCalcModal() {
    document.getElementById('salaryCalcModal').style.display = 'none';
}

// Extend window.onclick to close present days modal when clicking outside
const prevWindowOnclick = window.onclick;
window.onclick = function(event) {
    try {
        const presentModal = document.getElementById('presentDaysModal');
        const lateDaysModal = document.getElementById('lateDaysModal');
        const oneHourLateDaysModal = document.getElementById('oneHourLateDaysModal');
        const leaveDetailsModal = document.getElementById('leaveDetailsModal');
        const leaveDeductionModal = document.getElementById('leaveDeductionModal');
        const overtimeDetailsModal = document.getElementById('overtimeDetailsModal');
        const editModal = document.getElementById('editSalaryModal');
        const workingDaysModal = document.getElementById('workingDaysModal');
        const penaltyModal = document.getElementById('penaltyModal');

        if (event.target === editModal) {
            closeEditModal();
        }
        if (event.target === workingDaysModal) {
            closeWorkingDaysModal();
        }
        if (event.target === presentModal) {
            closePresentDaysModal();
        }
        if (event.target === lateDaysModal) {
            closeLateDaysModal();
        }
        if (event.target === oneHourLateDaysModal) {
            closeOneHourLateDaysModal();
        }
        if (event.target === leaveDetailsModal) {
            closeLeaveDetailsModal();
        }
        if (event.target === leaveDeductionModal) {
            closeLeaveDeductionModal();
        }
        if (event.target === overtimeDetailsModal) {
            closeOvertimeDetailsModal();
        }
        if (event.target === penaltyModal) {
            closePenaltyModal();
        }
    } catch (e) {
        // ignore
    }
    if (typeof prevWindowOnclick === 'function') prevWindowOnclick(event);
};
