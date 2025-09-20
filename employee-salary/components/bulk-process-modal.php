<!-- Bulk Process Modal -->
<div id="bulkProcessModal" class="modal">
    <div class="modal-content bulk-process-modal">
        <div class="modal-header">
            <h2>
                <i class="fas fa-cogs"></i>
                Bulk Salary Processing
            </h2>
            <span class="close" onclick="closeBulkProcessModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div class="bulk-process-steps">
                <div class="step-indicator">
                    <div class="step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-title">Select Employees</span>
                    </div>
                    <div class="step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-title">Configure Settings</span>
                    </div>
                    <div class="step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-title">Review & Process</span>
                    </div>
                </div>

                <!-- Step 1: Select Employees -->
                <div class="step-content" id="step1Content">
                    <h3>Select Employees for Bulk Processing</h3>
                    <div class="employee-selection">
                        <div class="selection-filters">
                            <div class="filter-group">
                                <label>Department:</label>
                                <select id="bulkDepartmentFilter">
                                    <option value="">All Departments</option>
                                    <option value="HR">HR</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Sales">Sales</option>
                                    <option value="Marketing">Marketing</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Employment Type:</label>
                                <select id="bulkEmploymentFilter">
                                    <option value="">All Types</option>
                                    <option value="permanent">Permanent</option>
                                    <option value="contract">Contract</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="employee-list">
                            <div class="list-header">
                                <input type="checkbox" id="selectAllBulk" onchange="toggleSelectAllBulk()">
                                <label for="selectAllBulk">Select All Employees</label>
                                <span class="selected-count">0 selected</span>
                            </div>
                            <div class="employee-items" id="bulkEmployeeList">
                                <!-- Employee list will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Configure Settings -->
                <div class="step-content hidden" id="step2Content">
                    <h3>Configure Processing Settings</h3>
                    <div class="settings-grid">
                        <div class="setting-group">
                            <h4>Attendance Calculation</h4>
                            <div class="setting-item">
                                <input type="checkbox" id="includeOvertime" checked>
                                <label for="includeOvertime">Include Overtime Calculations</label>
                            </div>
                            <div class="setting-item">
                                <input type="checkbox" id="includeLateDeductions" checked>
                                <label for="includeLateDeductions">Apply Late Deductions</label>
                            </div>
                            <div class="setting-item">
                                <input type="checkbox" id="includeAbsenceDeductions" checked>
                                <label for="includeAbsenceDeductions">Apply Absence Deductions</label>
                            </div>
                        </div>
                        
                        <div class="setting-group">
                            <h4>Bonus & Allowances</h4>
                            <div class="setting-item">
                                <input type="checkbox" id="applyPerformanceBonus">
                                <label for="applyPerformanceBonus">Apply Performance Bonus</label>
                                <input type="number" class="setting-input" placeholder="Percentage" disabled>
                            </div>
                            <div class="setting-item">
                                <input type="checkbox" id="applyFestivalBonus">
                                <label for="applyFestivalBonus">Apply Festival Bonus</label>
                                <input type="number" class="setting-input" placeholder="Amount" disabled>
                            </div>
                        </div>
                        
                        <div class="setting-group">
                            <h4>Deductions</h4>
                            <div class="setting-item">
                                <input type="checkbox" id="applyTaxDeduction" checked>
                                <label for="applyTaxDeduction">Apply Tax Deduction (TDS)</label>
                            </div>
                            <div class="setting-item">
                                <input type="checkbox" id="applyPFDeduction" checked>
                                <label for="applyPFDeduction">Apply PF Contribution</label>
                            </div>
                            <div class="setting-item">
                                <input type="checkbox" id="applyESIDeduction" checked>
                                <label for="applyESIDeduction">Apply ESI Contribution</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Review & Process -->
                <div class="step-content hidden" id="step3Content">
                    <h3>Review and Process Salaries</h3>
                    <div class="review-summary">
                        <div class="summary-stats">
                            <div class="stat-item">
                                <span class="stat-label">Selected Employees:</span>
                                <span class="stat-value" id="selectedEmployeeCount">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Total Base Salary:</span>
                                <span class="stat-value" id="totalBaseSalary">₹0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Estimated Net Payable:</span>
                                <span class="stat-value" id="estimatedNetPayable">₹0</span>
                            </div>
                        </div>
                        
                        <div class="processing-options">
                            <div class="option-item">
                                <input type="radio" id="processOnly" name="processAction" value="process" checked>
                                <label for="processOnly">Process Salaries Only</label>
                            </div>
                            <div class="option-item">
                                <input type="radio" id="processAndApprove" name="processAction" value="approve">
                                <label for="processAndApprove">Process and Auto-Approve</label>
                            </div>
                            <div class="option-item">
                                <input type="radio" id="generatePayslips" name="processAction" value="payslips">
                                <label for="generatePayslips">Process and Generate Payslips</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="processing-status hidden" id="processingStatus">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <div class="status-text" id="statusText">Processing...</div>
                    </div>
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeBulkProcessModal()">Cancel</button>
                <button class="btn btn-secondary" id="prevStepBtn" onclick="previousStep()" style="display: none;">Previous</button>
                <button class="btn btn-primary" id="nextStepBtn" onclick="nextStep()">Next</button>
                <button class="btn btn-success" id="processBtn" onclick="processBulkSalaries()" style="display: none;">
                    <i class="fas fa-cogs"></i>
                    Process Salaries
                </button>
            </div>
        </div>
    </div>
</div>