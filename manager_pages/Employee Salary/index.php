<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Get current month and year for defaults
$currentMonth = date('m');
$currentYear = date('Y');

// Get selected filters from request
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;

// Validate inputs
$selectedMonth = intval($selectedMonth);
$selectedYear = intval($selectedYear);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Analytics Dashboard</title>
    <link rel="stylesheet" href="../employees_profile/style.css">
    <link rel="stylesheet" href="../../studio_users/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/employee_salary.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
</head>
<body class="el-1">
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <div class="container">
        <div class="dashboard-header">
            <h1 class="header-title">
                <i class="fas fa-chart-line"></i> Monthly Analytics Dashboard
            </h1>

            <form method="GET" class="filter-form">
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="month">
                            <i class="fas fa-calendar-alt"></i> Select Month
                        </label>
                        <select id="month" name="month" required>
                            <option value="">-- Choose Month --</option>
                            <?php
                            $months = [
                                '01' => 'January',
                                '02' => 'February',
                                '03' => 'March',
                                '04' => 'April',
                                '05' => 'May',
                                '06' => 'June',
                                '07' => 'July',
                                '08' => 'August',
                                '09' => 'September',
                                '10' => 'October',
                                '11' => 'November',
                                '12' => 'December'
                            ];
                            foreach ($months as $num => $name) {
                                $selected = ($selectedMonth == $num) ? 'selected' : '';
                                echo "<option value=\"$num\" $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="year">
                            <i class="fas fa-calendar"></i> Select Year
                        </label>
                        <select id="year" name="year" required>
                            <option value="">-- Choose Year --</option>
                            <?php
                            $startYear = 2020;
                            $endYear = date('Y') + 2;
                            for ($year = $endYear; $year >= $startYear; $year--) {
                                $selected = ($selectedYear == $year) ? 'selected' : '';
                                echo "<option value=\"$year\" $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-filter">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="index.php" class="btn btn-reset">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <?php if ($selectedMonth && $selectedYear) : ?>
            <div class="selected-filters active">
                <strong>Filters Applied:</strong>
                <span class="filter-badge">
                    <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-section">
            <?php if ($selectedMonth && $selectedYear) : ?>
        <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="color: #1a202c; font-size: 1.3rem; margin: 0;">Analytics for <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?></h2>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-filter" onclick="generateReport()" style="margin: 0;">
                                <i class="fas fa-file-alt"></i> Generate Report
                            </button>
                            <button type="button" class="btn btn-filter" onclick="exportToExcel()" style="margin: 0;">
                                <i class="fas fa-download"></i> Export to Excel
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-scroll-top" id="tableScrollTop">
                        <div class="table-scroll-top-inner" id="tableScrollTopInner"></div>
                    </div>
                    <div class="table-scroll" id="tableScrollMain">
                        <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Gross Salary</th>
                                <th>TDS (%)</th>
                                <th>Payable Salary</th>
                                <th>Working Days</th>
                                <th>Present Days</th>
                                <th>Late Days</th>
                                <th>1+ Hour Late</th>
                                <th>Leave Taken</th>
                                <th>Leave Deduction</th>
                                <th>Late Deduction</th>
                                <th>1+ Leave Hour Late Deduction</th>
                                <th>4th Saturday Missing Deduction</th>
                                <th>Penalty</th>
                                <th>Salary Calculated Days
                                    <span class="info-icon" title="How it's calculated">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="info-tooltip">Calculated = present days + casual (full) + half-day (0.5) + compensate (0.5) - late deductions (every 3 late = 0.5 day) - 1+hr late (0.5 per) - 4th Saturday penalty (2 days)</span>
                                    </span>
                                </th>
                                <th>Net Payable Salary</th>
                                <th>Net Payable Salary TDS<br><small style="font-weight:400; font-size:0.75rem;">(Net × TDS%)</small></th>
                                <th>Payable Salary After Deduction<br><small style="font-weight:400; font-size:0.75rem;">(Net Payable - TDS)</small></th>
                                <th>Overtime Hours</th>
                                <th>Overtime Amount</th>
                                <th>OT TDS</th>
                                <th>Payable OT after Deduction<br><small style="font-weight:400; font-size:0.75rem;">(OT - TDS)</small></th>
                                <th>Total TDS Amount<br><small style="font-weight:400; font-size:0.75rem;">(Govt. Amount)</small></th>
                                <th style="background:#fef9c3; color:#713f12;">Total Payable Salary<br><small style="font-weight:400; font-size:0.75rem;">(Net Payable + Payable OT after Deduction)</small></th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="analyticsTableBody">
                            <tr>
                                <td colspan="15" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        </tbody>
                        </table>
                    </div>
                </div>
            <?php else : ?>
                <div class="data-display">
                    <i class="fas fa-filter"></i>
                    <h2>Select Filters to View Analytics</h2>
                    <p>Please select a month and year from the filters above to view the analytics data.</p>
                </div>
            <?php endif; ?>
            </div>


            <!-- Salary Calculated Days Modal -->
            <div id="salaryCalcModal" class="modal">
        <div class="modal-content" style="max-width:800px; max-height:85vh; display:flex; flex-direction:column;">
            <span class="close-modal" onclick="closeSalaryCalcModal()" style="position:absolute; right:18px; top:10px; cursor:pointer;">&times;</span>
            <h2 style="margin-top:0; text-align:center;">Salary Calculated Days Breakdown</h2>
            <div id="salaryCalcContainer" style="background:#f8f9fa; padding:18px; border-radius:6px; overflow-y:auto; flex-grow:1;">
                <!-- Filled dynamically -->
            </div>
            <div class="modal-buttons" style="margin-top:12px;">
                <button class="btn-cancel" onclick="closeSalaryCalcModal()">Close</button>
            </div>
        </div>
            </div>

            <!-- Shared Short Leave Floating Popup -->
            <div id="shortLeavePopup" style="
                display:none; position:fixed; z-index:9999;
                background:#fff; border:1px solid #e2e8f0;
                border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.15);
                padding:16px 18px; width:280px;
                font-family:inherit;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-weight:700; font-size:0.88rem; color:#1e293b;">Apply Short Leave</span>
                    <button onclick="closeShortLeavePopup()" type="button"
                        style="background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1.1rem; line-height:1; padding:0;">&times;</button>
                </div>
                <p id="slPopupDate" style="font-size:0.78rem; color:#64748b; margin:0 0 10px 0;"></p>
                <input id="slPopupReason" type="text" placeholder="Enter reason (required)..."
                    style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:6px;
                           font-size:0.83rem; box-sizing:border-box; outline:none;">
                <div style="display:flex; gap:8px; margin-top:10px;">
                    <button id="slPopupSaveBtn" type="button" onclick="submitShortLeavePopup()"
                        style="flex:1; padding:7px; background:#2563eb; color:#fff; border:none;
                               border-radius:6px; font-size:0.82rem; font-weight:600; cursor:pointer;">
                        &#10003; Save &amp; Apply
                    </button>
                    <button type="button" onclick="closeShortLeavePopup()"
                        style="padding:7px 12px; background:#f1f5f9; color:#475569; border:none;
                               border-radius:6px; font-size:0.82rem; cursor:pointer;">
                        Cancel
                    </button>
                </div>
            </div>

            <!-- Edit Salary Modal -->
            <div id="editSalaryModal" class="modal">
        <div class="modal-content" style="max-width:600px; max-height:88vh; display:flex; flex-direction:column; padding:0; overflow:hidden; position:relative;">
            <span class="close-modal" onclick="closeEditModal()" style="position:absolute; right:18px; top:14px; cursor:pointer;">&#215;</span>

            <!-- Fixed Header -->
            <div style="padding:24px 30px 16px; border-bottom:1px solid #f0f0f0; flex-shrink:0;">
                <h2 style="margin:0 0 14px 0;">Edit Salary Details</h2>
                <div id="employeeInfo" style="background:#f8f9fa; padding:12px 15px; border-radius:6px;">
                    <p style="margin:4px 0;"><strong>Employee ID:</strong> <span id="modalEmployeeId"></span></p>
                    <p style="margin:4px 0;"><strong>Name:</strong> <span id="modalEmployeeName"></span></p>
                    <p style="margin:4px 0;"><strong>Current Salary:</strong> <span id="modalCurrentSalary"></span></p>
                </div>
            </div>

            <!-- Scrollable Form -->
            <form id="editSalaryForm" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                <div style="padding:20px 30px; overflow-y:auto; flex:1;">

                <!-- Gross Salary Section -->
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:14px; margin-bottom:16px;">
                    <p style="font-weight:700; color:#0369a1; margin:0 0 12px 0; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.04em;">
                        <i class="fas fa-rupee-sign"></i> Gross Salary
                    </p>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label for="newBaseSalary">Amount (₹) <small style="color:#6b7280; font-weight:400;">(this is the Gross — Payable = Gross − TDS%)</small></label>
                        <input type="number" id="newBaseSalary" name="newBaseSalary" step="0.01" min="0" required placeholder="e.g. 33000">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="baseSalaryEffectiveFrom">Effective From</label>
                        <input type="date" id="baseSalaryEffectiveFrom" name="baseSalaryEffectiveFrom">
                        <small style="color:#718096; font-size:0.82rem;">Date from which this gross salary applies. Leave blank if immediately.</small>
                    </div>
                </div>

                <!-- TDS Section -->
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:14px; margin-bottom:16px;">
                    <p style="font-weight:700; color:#b45309; margin:0 0 12px 0; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.04em;">
                        <i class="fas fa-percent"></i> TDS Percentage
                    </p>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label for="newTdsPercentage">Percentage (%)</label>
                        <input type="number" id="newTdsPercentage" name="newTdsPercentage" step="0.01" min="0" max="100" placeholder="e.g. 10 for 10%">
                        <small style="color:#718096; font-size:0.82rem;">Enter 0–100. Leave blank for 0%.</small>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="tdsEffectiveFrom">Effective From</label>
                        <input type="date" id="tdsEffectiveFrom" name="tdsEffectiveFrom">
                        <small style="color:#718096; font-size:0.82rem;">Date from which this TDS % applies. Leave blank if immediately.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="salaryRemarks">Remarks (Optional)</label>
                    <textarea id="salaryRemarks" name="salaryRemarks" rows="3" placeholder="Add any notes..."></textarea>
                </div>

                <div id="formMessage" style="margin-top:12px; padding:10px; border-radius:6px; display:none;"></div>

                </div><!-- end scrollable area -->

                <!-- Pinned Footer Buttons -->
                <div class="modal-buttons" style="padding:16px 30px; border-top:1px solid #f0f0f0; flex-shrink:0; margin:0;">
                    <button type="submit" class="btn-save">
                        <span class="save-text">Save Changes</span>
                        <span class="loading-spinner" id="saveSpinner">
                            <div class="spinner"></div>
                        </span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
            </div>

            <!-- Working Days Details Modal -->
            <div id="workingDaysModal" class="modal">
        <div class="modal-content" style="max-width: 600px; max-height: 85vh; display: flex; flex-direction: column;">
            <span class="close-modal" onclick="closeWorkingDaysModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0;">Working Days Calculation Details</h2>
            
            <div id="workingDaysDetails" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p><strong>Employee:</strong> <span id="detailEmployeeName"></span></p>
                <p><strong>Month/Year:</strong> <span id="detailMonthYear"></span></p>
                <p><strong>Total Working Days:</strong> <span id="detailTotalWorkingDays" style="font-size: 1.2rem; color: #2d3748; font-weight: bold;"></span></p>
                
                <div style="margin-top: 20px;">
                    <h3 style="color: #1a202c; font-size: 1rem; margin-bottom: 15px;">Calculation Breakdown:</h3>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Total Days in Month:</strong> <span id="detailTotalDays"></span></p>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Weekly Off Days:</strong> <span id="detailWeeklyOffs"></span></p>
                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #718096;"><span id="detailWeeklyOffsDetails"></span></p>
                        <div id="weeklyOffsDates" style="margin-top: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f9fafb; display: none;">
                        </div>
                        <button type="button" id="toggleWeeklyOffDates" style="margin-top: 8px; padding: 6px 12px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: #4a5568; display: none;">Show Dates</button>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Office Holidays:</strong> <span id="detailHolidaysCount"></span></p>
                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #718096;"><span id="detailHolidaysList"></span></p>
                        <div id="holidaysDates" style="margin-top: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f9fafb; display: none;">
                        </div>
                        <button type="button" id="toggleHolidayDates" style="margin-top: 8px; padding: 6px 12px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: #4a5568; display: none;">Show Dates</button>
                    </div>
                    
                    <div style="background: #dcfce7; padding: 15px; border-radius: 6px; border-left: 4px solid #22c55e;">
                        <p style="margin: 0;"><strong style="color: #166534;">Working Days = Total Days - Weekly Offs - Holidays</strong></p>
                        <p style="margin: 8px 0 0 0; font-size: 0.9rem; color: #166534;">
                            <span id="detailCalculation"></span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeWorkingDaysModal()" style="flex: 1;">Close</button>
            </div>
        </div>
            </div>

            <!-- Present Days Modal -->
            <div id="presentDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closePresentDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Present Days</h2>

            <div id="presentDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="presentDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="presentDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Punch Out</th>
                            <th style="padding:8px; text-align:left;">Working Hr.</th>
                            <th style="padding:8px; text-align:left;">Overtime Hr.</th>
                        </tr>
                    </thead>
                    <tbody id="presentDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closePresentDaysModal()">Close</button>
            </div>
        </div>
            </div>

            <!-- Late Days Modal -->
            <div id="lateDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLateDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Late Days</h2>

            <div id="lateDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="lateDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="lateDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Shift Start</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Minutes Late</th>
                            <th style="padding:8px; text-align:left;">Short Leave</th>
                                <th style="padding:8px; text-align:left; background:#eff6ff; color:#1d4ed8; min-width:120px; white-space:nowrap;">Apply Short Leave</th>
                        </tr>
                    </thead>
                    <tbody id="lateDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLateDaysModal()">Close</button>
            </div>
        </div>
            </div>

            <!-- 1+ Hour Late Modal -->
            <div id="oneHourLateDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeOneHourLateDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">1+ Hour Late Days</h2>

            <div id="oneHourLateDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="oneHourLateDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="oneHourLateDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Shift Start</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Minutes Late</th>
                            <th style="padding:8px; text-align:left;">Short Leave</th>
                                <th style="padding:8px; text-align:left; background:#eff6ff; color:#1d4ed8; min-width:120px; white-space:nowrap;">Apply Short Leave</th>
                        </tr>
                    </thead>
                    <tbody id="oneHourLateDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeOneHourLateDaysModal()">Close</button>
            </div>
        </div>
            </div>

            <!-- Leave Details Modal -->
            <div id="leaveDetailsModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLeaveDetailsModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Leave Details</h2>

            <div id="leaveDetailsContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="leaveDetailsUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="leaveDetailsTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date Range</th>
                            <th style="padding:8px; text-align:left;">Leave Type</th>
                            <th style="padding:8px; text-align:left;">Days</th>
                            <th style="padding:8px; text-align:left;">Reason</th>
                        </tr>
                    </thead>
                    <tbody id="leaveDetailsTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLeaveDetailsModal()">Close</button>
            </div>
        </div>
            </div>

            <!-- Leave Deduction Details Modal -->
            <div id="leaveDeductionModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLeaveDeductionModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Leave Deduction Details</h2>

            <div id="leaveDeductionContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="leaveDeductionUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                
                <!-- Summary Section -->
                <div id="deductionSummary" style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #4CAF50;">
                    <h3 style="margin-top: 0; margin-bottom: 10px; color: #333;">Deduction Summary</h3>
                    <p style="margin: 5px 0;"><strong>Total Deduction:</strong> <span id="totalDeductionAmount" style="color: #d32f2f; font-weight: bold;">₹0</span></p>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">Based on approved leaves in the selected month</p>
                </div>

                <!-- Deduction Breakdown Table -->
                <table id="leaveDeductionTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Leave Type</th>
                            <th style="padding:8px; text-align:center;">Days</th>
                            <th style="padding:8px; text-align:right;">Deduction (₹)</th>
                            <th style="padding:8px; text-align:left;">Reason</th>
                        </tr>
                    </thead>
                    <tbody id="leaveDeductionTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLeaveDeductionModal()">Close</button>
            </div>
        </div>
            </div>

            <!-- Overtime Details Modal -->
            <div id="overtimeDetailsModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 98%; max-width: 1200px; position: relative;">
            <span class="close-modal" onclick="closeOvertimeDetailsModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Overtime Details</h2>

            <div id="overtimeDetailsContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <div style="text-align:center; padding:20px;"><div class="spinner"></div><p>Loading overtime details...</p></div>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeOvertimeDetailsModal()">Close</button>
            </div>
        </div>
            </div>

            <!-- Penalty Adjustment Modal -->
            <div id="penaltyModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-modal" onclick="closePenaltyModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px; text-align: center;">Adjust Penalty Days</h2>
            
            <div id="penaltyInfo" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p><strong>Employee:</strong> <span id="penaltyEmployeeName"></span></p>
                <p><strong>Current Penalty Days:</strong> <span id="penaltyCurrentValue" style="font-size: 1.2rem; color: #2d3748; font-weight: bold;"></span></p>
                <p><strong>Action:</strong> <span id="penaltyAction" style="font-size: 1.1rem; color: #4299e1; font-weight: 600;"></span></p>
            </div>

            <form id="penaltyAdjustmentForm">
                <div class="form-group">
                    <label for="penaltyReason">Reason for Adjustment (Minimum 10 words)</label>
                    <textarea id="penaltyReason" name="penaltyReason" rows="4" placeholder="Please provide at least 10 words explaining the reason for this penalty adjustment..." required style="resize: vertical;"></textarea>
                    <div style="font-size: 0.8rem; color: #718096; margin-top: 5px;">
                        Word count: <span id="wordCount">0</span>/10
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn-save" id="penaltySubmitBtn" disabled>
                        Confirm Adjustment
                    </button>
                    <button type="button" class="btn-cancel" onclick="closePenaltyModal()">Cancel</button>
                </div>
            </form>

            <div id="penaltyMessage" style="margin-top: 15px; padding: 10px; border-radius: 6px; display: none;"></div>
        </div>
            </div>

            <!-- Notification Modal -->
            <div id="notificationModal" class="modal" style="z-index: 9999;">
        <div class="modal-content" style="max-width: 500px; text-align: center; padding: 40px 30px;">
            <div id="notificationIcon" style="font-size: 3rem; margin-bottom: 15px;">
                <i class="fas fa-check-circle" style="color: #22c55e;"></i>
            </div>
            <h2 id="notificationTitle" style="margin: 15px 0; color: #1a202c; font-size: 1.3rem;">Success</h2>
            <p id="notificationMessage" style="color: #4a5568; font-size: 0.95rem; margin: 0; line-height: 1.6;"></p>
            <div style="margin-top: 25px;">
                <button onclick="closeNotificationModal()" class="btn-cancel" style="min-width: 100px; padding: 12px 24px; font-size: 1rem;">OK</button>
            </div>
        </div>
            </div>

        </main>
    </div>

    <script src="js/employee_salary.js"></script>
</body>
</html>
