<!-- Salary Detail Modal -->
<div id="salaryDetailModal" class="modal">
    <div class="modal-content salary-detail-modal">
        <div class="modal-header">
            <h2>
                <i class="fas fa-user-circle"></i>
                <span id="modalEmployeeName">Employee Salary Details</span>
            </h2>
            <span class="close" onclick="closeSalaryDetailModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <!-- Employee Info Section -->
            <div class="employee-info-section">
                <div class="employee-avatar">
                    <img id="employeeAvatar" src="../images/default-avatar.png" alt="Employee Photo">
                </div>
                <div class="employee-basic-info">
                    <h3 id="employeeFullName">Employee Name</h3>
                    <p class="employee-id">ID: <span id="employeeId">EMP001</span></p>
                    <p class="employee-dept">Department: <span id="employeeDepartment">HR</span></p>
                    <p class="employee-position">Position: <span id="employeePosition">Manager</span></p>
                    <div class="employment-status">
                        <span class="status-badge" id="employeeStatus">Active</span>
                    </div>
                </div>
                <div class="salary-period-info">
                    <div class="period-card">
                        <i class="fas fa-calendar-alt"></i>
                        <div>
                            <h4 id="salaryPeriod">March 2025</h4>
                            <p>Salary Period</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Salary Breakdown Section -->
            <div class="salary-breakdown-section">
                <h3>Salary Breakdown</h3>
                
                <!-- Basic Salary Info -->
                <div class="breakdown-card basic-salary-card">
                    <div class="card-header">
                        <i class="fas fa-money-bill-wave"></i>
                        <h4>Basic Salary Information</h4>
                    </div>
                    <div class="card-content">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Base Salary</label>
                                <span class="amount" id="baseSalaryAmount">₹50,000</span>
                            </div>
                            <div class="info-item">
                                <label>Per Day Rate</label>
                                <span class="amount" id="perDayRate">₹1,667</span>
                            </div>
                            <div class="info-item">
                                <label>Working Days</label>
                                <span id="workingDays">30</span>
                            </div>
                            <div class="info-item">
                                <label>Present Days</label>
                                <span id="presentDays">28</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Details -->
                <div class="breakdown-card attendance-card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h4>Attendance Details</h4>
                    </div>
                    <div class="card-content">
                        <div class="attendance-grid">
                            <div class="attendance-item present">
                                <div class="attendance-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="attendance-info">
                                    <span class="count" id="presentDaysCount">28</span>
                                    <label>Present Days</label>
                                </div>
                            </div>
                            <div class="attendance-item absent">
                                <div class="attendance-icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="attendance-info">
                                    <span class="count" id="absentDaysCount">2</span>
                                    <label>Absent Days</label>
                                </div>
                            </div>
                            <div class="attendance-item late">
                                <div class="attendance-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="attendance-info">
                                    <span class="count" id="lateDaysCount">3</span>
                                    <label>Late Days</label>
                                </div>
                            </div>
                            <div class="attendance-item leave">
                                <div class="attendance-icon">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <div class="attendance-info">
                                    <span class="count" id="leaveDaysCount">2</span>
                                    <label>Leave Days</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Earnings Section -->
                <div class="breakdown-card earnings-card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i>
                        <h4>Earnings</h4>
                    </div>
                    <div class="card-content">
                        <div class="earnings-list">
                            <div class="earning-item">
                                <label>Basic Salary (Present Days)</label>
                                <span class="amount positive" id="basicEarning">₹46,667</span>
                            </div>
                            <div class="earning-item">
                                <label>Overtime Amount</label>
                                <span class="amount positive" id="overtimeEarning">₹2,500</span>
                            </div>
                            <div class="earning-item">
                                <label>Travel Allowance</label>
                                <span class="amount positive" id="travelEarning">₹1,200</span>
                            </div>
                            <div class="earning-item">
                                <label>Performance Bonus</label>
                                <span class="amount positive" id="bonusEarning">₹3,000</span>
                            </div>
                            <div class="earning-item">
                                <label>Other Allowances</label>
                                <span class="amount positive" id="otherEarning">₹500</span>
                            </div>
                            <div class="earning-item total">
                                <label><strong>Total Earnings</strong></label>
                                <span class="amount positive total-amount" id="totalEarnings">₹53,867</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deductions Section -->
                <div class="breakdown-card deductions-card">
                    <div class="card-header">
                        <i class="fas fa-minus-circle"></i>
                        <h4>Deductions</h4>
                    </div>
                    <div class="card-content">
                        <div class="deductions-list">
                            <div class="deduction-item">
                                <label>Absence Deduction</label>
                                <span class="amount negative" id="absenceDeduction">₹3,333</span>
                            </div>
                            <div class="deduction-item">
                                <label>Late Deduction</label>
                                <span class="amount negative" id="lateDeduction">₹750</span>
                            </div>
                            <div class="deduction-item">
                                <label>Tax Deduction (TDS)</label>
                                <span class="amount negative" id="taxDeduction">₹2,500</span>
                            </div>
                            <div class="deduction-item">
                                <label>PF Contribution</label>
                                <span class="amount negative" id="pfDeduction">₹1,800</span>
                            </div>
                            <div class="deduction-item">
                                <label>ESI Contribution</label>
                                <span class="amount negative" id="esiDeduction">₹200</span>
                            </div>
                            <div class="deduction-item">
                                <label>Other Deductions</label>
                                <span class="amount negative" id="otherDeductions">₹100</span>
                            </div>
                            <div class="deduction-item total">
                                <label><strong>Total Deductions</strong></label>
                                <span class="amount negative total-amount" id="totalDeductions">₹8,683</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Net Salary Section -->
                <div class="breakdown-card net-salary-card">
                    <div class="card-header">
                        <i class="fas fa-wallet"></i>
                        <h4>Net Salary</h4>
                    </div>
                    <div class="card-content">
                        <div class="net-salary-calculation">
                            <div class="calculation-row">
                                <span>Total Earnings:</span>
                                <span class="amount positive" id="netEarnings">₹53,867</span>
                            </div>
                            <div class="calculation-row">
                                <span>Total Deductions:</span>
                                <span class="amount negative" id="netDeductions">₹8,683</span>
                            </div>
                            <hr>
                            <div class="calculation-row final">
                                <span><strong>Net Payable Amount:</strong></span>
                                <span class="amount net-amount" id="netSalaryAmount">₹45,184</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Section -->
            <div class="modal-actions">
                <div class="action-buttons">
                    <button class="btn btn-outline" onclick="downloadPayslip()">
                        <i class="fas fa-download"></i>
                        Download Payslip
                    </button>
                    <button class="btn btn-warning" onclick="openEditSalaryModal()">
                        <i class="fas fa-edit"></i>
                        Edit Salary
                    </button>
                    <button class="btn btn-success" onclick="approveSalary()">
                        <i class="fas fa-check"></i>
                        Approve Salary
                    </button>
                </div>
            </div>

            <!-- Salary History Section -->
            <div class="salary-history-section">
                <h3>Salary History</h3>
                <div class="history-table-wrapper">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Base Salary</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="salaryHistoryBody">
                            <!-- Salary history will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>