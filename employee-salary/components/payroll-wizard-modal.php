<!-- Payroll Wizard Modal -->
<div id="payrollWizardModal" class="modal">
    <div class="modal-content payroll-wizard-modal">
        <div class="modal-header">
            <h2>
                <i class="fas fa-magic"></i>
                Payroll Processing Wizard
            </h2>
            <span class="close" onclick="closePayrollWizardModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div class="wizard-intro">
                <div class="intro-content">
                    <i class="fas fa-magic wizard-icon"></i>
                    <h3>Welcome to Payroll Wizard</h3>
                    <p>This wizard will guide you through the complete payroll processing for the selected month. 
                       It will automatically calculate salaries, apply deductions, and generate reports.</p>
                </div>
                
                <div class="wizard-features">
                    <div class="feature-item">
                        <i class="fas fa-calculator"></i>
                        <span>Automatic Salary Calculation</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Smart Deduction Management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Instant Payslip Generation</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Comprehensive Reports</span>
                    </div>
                </div>
            </div>

            <div class="wizard-setup">
                <h3>Payroll Configuration</h3>
                
                <div class="config-section">
                    <h4>Basic Settings</h4>
                    <div class="config-grid">
                        <div class="config-item">
                            <label for="payrollMonth">Payroll Month:</label>
                            <input type="month" id="payrollMonth" value="<?php echo date('Y-m'); ?>">
                        </div>
                        <div class="config-item">
                            <label for="paymentDate">Payment Date:</label>
                            <input type="date" id="paymentDate" value="<?php echo date('Y-m-t'); ?>">
                        </div>
                        <div class="config-item">
                            <label for="workingDaysOverride">Working Days Override:</label>
                            <input type="number" id="workingDaysOverride" placeholder="Auto-calculated" min="1" max="31">
                        </div>
                    </div>
                </div>

                <div class="config-section">
                    <h4>Attendance Rules</h4>
                    <div class="rules-grid">
                        <div class="rule-item">
                            <input type="checkbox" id="enableLateDeduction" checked>
                            <label for="enableLateDeduction">Enable Late Deduction</label>
                            <div class="rule-details">
                                <input type="number" value="15" min="1" max="60"> minutes grace period
                            </div>
                        </div>
                        <div class="rule-item">
                            <input type="checkbox" id="enableOvertimePayment" checked>
                            <label for="enableOvertimePayment">Enable Overtime Payment</label>
                            <div class="rule-details">
                                <input type="number" value="2" step="0.1" min="1" max="5">x overtime rate
                            </div>
                        </div>
                        <div class="rule-item">
                            <input type="checkbox" id="enableHalfDayRule" checked>
                            <label for="enableHalfDayRule">Enable Half Day Rule</label>
                            <div class="rule-details">
                                After <input type="number" value="60" min="30" max="120"> minutes late
                            </div>
                        </div>
                    </div>
                </div>

                <div class="config-section">
                    <h4>Deduction Settings</h4>
                    <div class="deduction-grid">
                        <div class="deduction-item">
                            <input type="checkbox" id="enableTDS" checked>
                            <label for="enableTDS">Tax Deduction (TDS)</label>
                            <select class="deduction-select">
                                <option value="auto">Auto Calculate</option>
                                <option value="fixed">Fixed Percentage</option>
                                <option value="slab">Income Slab Based</option>
                            </select>
                        </div>
                        <div class="deduction-item">
                            <input type="checkbox" id="enablePF" checked>
                            <label for="enablePF">Provident Fund</label>
                            <input type="number" value="12" min="0" max="25" step="0.1" class="deduction-input">%
                        </div>
                        <div class="deduction-item">
                            <input type="checkbox" id="enableESI" checked>
                            <label for="enableESI">ESI Contribution</label>
                            <input type="number" value="0.75" min="0" max="5" step="0.25" class="deduction-input">%
                        </div>
                    </div>
                </div>

                <div class="config-section">
                    <h4>Output Options</h4>
                    <div class="output-options">
                        <div class="option-item">
                            <input type="checkbox" id="generatePayslips" checked>
                            <label for="generatePayslips">Generate Individual Payslips (PDF)</label>
                        </div>
                        <div class="option-item">
                            <input type="checkbox" id="generateSummaryReport" checked>
                            <label for="generateSummaryReport">Generate Payroll Summary Report</label>
                        </div>
                        <div class="option-item">
                            <input type="checkbox" id="generateTaxReport">
                            <label for="generateTaxReport">Generate Tax Deduction Report</label>
                        </div>
                        <div class="option-item">
                            <input type="checkbox" id="generateBankFile">
                            <label for="generateBankFile">Generate Bank Transfer File</label>
                        </div>
                        <div class="option-item">
                            <input type="checkbox" id="sendEmailNotifications">
                            <label for="sendEmailNotifications">Send Email Notifications to Employees</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wizard-preview">
                <h3>Processing Preview</h3>
                <div class="preview-stats">
                    <div class="preview-item">
                        <span class="preview-label">Total Employees:</span>
                        <span class="preview-value" id="previewEmployeeCount">0</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Estimated Processing Time:</span>
                        <span class="preview-value" id="previewProcessingTime">2-3 minutes</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Total Salary Budget:</span>
                        <span class="preview-value" id="previewSalaryBudget">₹0</span>
                    </div>
                </div>
            </div>

            <!-- Processing Status -->
            <div class="processing-section hidden" id="wizardProcessingSection">
                <h3>Processing Payroll...</h3>
                <div class="processing-steps">
                    <div class="processing-step" id="step1">
                        <i class="fas fa-spinner fa-spin step-icon"></i>
                        <span class="step-text">Calculating attendance data...</span>
                    </div>
                    <div class="processing-step" id="step2">
                        <i class="fas fa-clock step-icon"></i>
                        <span class="step-text">Processing salary calculations...</span>
                    </div>
                    <div class="processing-step" id="step3">
                        <i class="fas fa-clock step-icon"></i>
                        <span class="step-text">Applying deductions...</span>
                    </div>
                    <div class="processing-step" id="step4">
                        <i class="fas fa-clock step-icon"></i>
                        <span class="step-text">Generating reports...</span>
                    </div>
                    <div class="processing-step" id="step5">
                        <i class="fas fa-clock step-icon"></i>
                        <span class="step-text">Finalizing payroll...</span>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" id="wizardProgressFill"></div>
                </div>
                <div class="progress-text" id="wizardProgressText">0% Complete</div>
            </div>

            <!-- Results Section -->
            <div class="results-section hidden" id="wizardResultsSection">
                <div class="results-header">
                    <i class="fas fa-check-circle success-icon"></i>
                    <h3>Payroll Processing Complete!</h3>
                </div>
                
                <div class="results-summary">
                    <div class="result-item">
                        <span class="result-label">Employees Processed:</span>
                        <span class="result-value" id="resultEmployeeCount">0</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Total Processing Time:</span>
                        <span class="result-value" id="resultProcessingTime">0:00</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Total Net Payable:</span>
                        <span class="result-value" id="resultNetPayable">₹0</span>
                    </div>
                </div>
                
                <div class="download-links">
                    <a href="#" class="download-link" id="downloadPayslips">
                        <i class="fas fa-file-pdf"></i>
                        Download All Payslips (ZIP)
                    </a>
                    <a href="#" class="download-link" id="downloadSummary">
                        <i class="fas fa-file-excel"></i>
                        Download Summary Report
                    </a>
                    <a href="#" class="download-link" id="downloadBankFile">
                        <i class="fas fa-university"></i>
                        Download Bank Transfer File
                    </a>
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closePayrollWizardModal()">Cancel</button>
                <button class="btn btn-primary" id="startProcessingBtn" onclick="startPayrollProcessing()">
                    <i class="fas fa-play"></i>
                    Start Processing
                </button>
                <button class="btn btn-success hidden" id="completeBtn" onclick="completePayrollProcessing()">
                    <i class="fas fa-check"></i>
                    Complete
                </button>
            </div>
        </div>
    </div>
</div>