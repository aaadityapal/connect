<?php
session_start();
require_once 'config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Update the date range logic at the top of the file
$current_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Calculate start and end dates for the selected month
$start_date = date('Y-m-01', strtotime("$current_year-$current_month-01"));
$end_date = date('Y-m-t', strtotime("$current_year-$current_month-01"));

// Fetch attendance records with work reports
$query = "SELECT 
            a.*, 
            DATE_FORMAT(a.date, '%d-%m-%Y') as formatted_date,
            TIME_FORMAT(a.punch_in, '%h:%i %p') as formatted_punch_in,
            TIME_FORMAT(a.punch_out, '%h:%i %p') as formatted_punch_out,
            s.shift_name,
            TIME_FORMAT(s.start_time, '%h:%i %p') as shift_start,
            TIME_FORMAT(s.end_time, '%h:%i %p') as shift_end
          FROM attendance a
          LEFT JOIN user_shifts us ON a.user_id = us.user_id
          LEFT JOIN shifts s ON us.shift_id = s.id
          WHERE a.user_id = ? 
          AND a.date BETWEEN ? AND ?
          GROUP BY a.id
          ORDER BY a.date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Sheet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --background-color: #f8fafc;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            padding: 20px;
            color: var(--text-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .header h1 {
            color: var(--secondary-color);
            font-size: 24px;
            margin: 0;
        }

        .date-filter {
            display: flex;
            gap: 15px;
            align-items: center;
            background: var(--background-color);
            padding: 12px;
            border-radius: 8px;
        }

        .month-year-picker {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .date-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-primary);
            background-color: white;
            cursor: pointer;
            min-width: 120px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%234a5568' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 12px) center;
            padding-right: 32px;
        }

        .date-select:hover {
            border-color: var(--primary-color);
        }

        .date-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }

        .filter-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #2980b9;
        }

        .worksheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .worksheet-table th {
            background: var(--secondary-color);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 500;
        }

        .worksheet-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .worksheet-table tr:hover {
            background-color: var(--background-color);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-present {
            background: #dcfce7;
            color: #166534;
        }

        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-holiday {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .work-report-cell {
            max-width: 300px;
            position: relative;
        }

        .work-report-preview {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }

        .time-cell {
            white-space: nowrap;
            font-family: 'Courier New', monospace;
        }

        .overtime {
            color: #dc2626;
            font-weight: 500;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px 0;
        }

        .pagination button {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination button:hover {
            background: var(--background-color);
        }

        .pagination button.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .date-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .worksheet-table {
                display: block;
                overflow-x: auto;
            }

            .month-year-picker {
                flex-direction: column;
                width: 100%;
            }
            
            .date-select {
                width: 100%;
            }
        }

        /* Attendance Overview Styles */
        .attendance-overview {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .overview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .overview-header h2 {
            font-size: 20px;
            color: var(--secondary-color);
            margin: 0;
        }

        .overview-header select {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-primary);
            background-color: white;
            cursor: pointer;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stat-info h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0 0 8px 0;
        }

        .stat-info p {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-top: 30px;
        }

        .chart-wrapper {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            height: 300px;
        }

        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .chart-wrapper {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Work Sheet History</h1>
            <div class="date-filter">
                <div class="month-year-picker">
                    <select id="monthSelect" class="date-select">
                        <?php
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March',
                            4 => 'April', 5 => 'May', 6 => 'June',
                            7 => 'July', 8 => 'August', 9 => 'September',
                            10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
                        foreach ($months as $num => $name) {
                            $selected = $currentMonth === $num ? 'selected' : '';
                            echo "<option value=\"$num\" $selected>$name</option>";
                        }
                        ?>
                    </select>

                    <select id="yearSelect" class="date-select">
                        <?php
                        $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                        $startYear = 2020; // You can adjust this start year
                        $endYear = (int)date('Y');
                        
                        for ($year = $endYear; $year >= $startYear; $year--) {
                            $selected = $currentYear === $year ? 'selected' : '';
                            echo "<option value=\"$year\" $selected>$year</option>";
                        }
                        ?>
                    </select>

                    <button class="filter-btn" onclick="filterMonthYear()">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </div>

        <div class="attendance-overview">
            <div class="overview-header">
                <h2>Attendance Overview</h2>
                <select id="overviewPeriod" onchange="updateOverviewStats()">
                    <option value="month">This Month</option>
                    <option value="quarter">Last 3 Months</option>
                    <option value="year">This Year</option>
                </select>
            </div>
            
            <div class="overview-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Present Days</h3>
                        <p id="presentDays">0</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Hours</h3>
                        <p id="totalHours">0</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Overtime Hours</h3>
                        <p id="overtimeHours">0</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Attendance Rate</h3>
                        <p id="attendanceRate">0%</p>
                    </div>
                </div>
            </div>
            
            <div class="charts-container">
                <div class="chart-wrapper">
                    <canvas id="attendanceChart"></canvas>
                </div>
                <div class="chart-wrapper">
                    <canvas id="hoursChart"></canvas>
                </div>
            </div>
        </div>

        <table class="worksheet-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Punch In</th>
                    <th>Punch Out</th>
                    <th>Working Hours</th>
                    <th>Overtime</th>
                    <th>Work Report</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['formatted_date']; ?></td>
                        <td><?php echo $row['shift_name'] . ' (' . $row['shift_start'] . ' - ' . $row['shift_end'] . ')'; ?></td>
                        <td class="time-cell"><?php echo $row['formatted_punch_in']; ?></td>
                        <td class="time-cell"><?php echo $row['formatted_punch_out'] ?: '-'; ?></td>
                        <td class="time-cell"><?php echo $row['working_hours'] ?: '-'; ?></td>
                        <td class="time-cell <?php echo $row['overtime_hours'] ? 'overtime' : ''; ?>">
                            <?php echo $row['overtime_hours'] ?: '-'; ?>
                        </td>
                        <td class="work-report-cell">
                            <?php if ($row['work_report']): ?>
                                <div class="work-report-preview" onclick="showWorkReport('<?php echo htmlspecialchars($row['work_report'], ENT_QUOTES); ?>', '<?php echo $row['formatted_date']; ?>')">
                                    <?php echo substr($row['work_report'], 0, 50) . '...'; ?>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        let attendanceChart;
        let hoursChart;
        
        // Store all attendance data for local filtering
        let attendanceData = [];

        // First, let's collect all the overtime data from the table when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            collectOvertimeData();
            updateOverviewStats();
        });
        
        function collectOvertimeData() {
            // Get all overtime cells from the table
            const overtimeCells = document.querySelectorAll('.worksheet-table tbody tr td:nth-child(6)');
            
            overtimeCells.forEach(cell => {
                const overtimeText = cell.textContent.trim();
                if (overtimeText !== '-') {
                    const dateCell = cell.parentElement.querySelector('td:first-child');
                    const date = dateCell.textContent.trim();
                    
                    attendanceData.push({
                        date: date,
                        overtime: overtimeText
                    });
                }
            });
        }

        async function fetchOverviewData(period) {
            try {
                const response = await fetch(`api/attendance_overview.php?period=${period}`);
                const data = await response.json();
                
                // Replace the overtime hours with our filtered calculation
                data.stats.originalOvertimeHours = data.stats.overtimeHours;
                data.stats.overtimeHours = calculateFilteredOvertime(period);
                
                // Also update the chart data
                if (data.chartData) {
                    const filteredMinutes = convertTimeToMinutes(data.stats.overtimeHours);
                    data.chartData.filteredOvertimeHours = filteredMinutes / 60; // Convert to hours for chart
                    data.chartData.overtimeHours = data.chartData.filteredOvertimeHours;
                }
                
                updateDashboardStats(data.stats);
                updateCharts(data.chartData);
            } catch (error) {
                console.error('Error fetching overview data:', error);
                
                // Fallback if API fails - use table data directly
                const stats = {
                    presentDays: countPresentDays(),
                    totalHours: "N/A",
                    overtimeHours: calculateFilteredOvertime(period),
                    attendanceRate: "N/A"
                };
                
                updateDashboardStats(stats);
            }
        }
        
        function calculateFilteredOvertime(period) {
            let totalFilteredMinutes = 0;
            
            // Loop through all the overtime entries
            attendanceData.forEach(entry => {
                // Parse the overtime value (format like "01:45:00" or "01:45")
                const overtimeMinutes = convertTimeToMinutes(entry.overtime);
                
                // Only count overtime that's 90 minutes (1:30) or more
                if (overtimeMinutes >= 90) {
                    totalFilteredMinutes += overtimeMinutes;
                }
            });
            
            // Format the total filtered overtime
            const filteredHours = Math.floor(totalFilteredMinutes / 60);
            const filteredMinutes = totalFilteredMinutes % 60;
            return `${String(filteredHours).padStart(2, '0')}:${String(filteredMinutes).padStart(2, '0')}`;
        }
        
        function convertTimeToMinutes(timeString) {
            if (!timeString || timeString === '-') return 0;
            
            const parts = timeString.split(':');
            const hours = parseInt(parts[0], 10) || 0;
            const minutes = parseInt(parts[1], 10) || 0;
            
            return (hours * 60) + minutes;
        }
        
        function countPresentDays() {
            const presentBadges = document.querySelectorAll('.status-badge.status-present');
            return presentBadges.length;
        }

        function updateDashboardStats(stats) {
            document.getElementById('presentDays').textContent = stats.presentDays;
            document.getElementById('totalHours').textContent = stats.totalHours;
            document.getElementById('overtimeHours').textContent = stats.overtimeHours;
            document.querySelector('.stat-card:nth-child(3) .stat-info h3').textContent = 'Overtime Hours (≥1:30)';
            document.getElementById('attendanceRate').textContent = `${stats.attendanceRate}%`;
        }

        function updateCharts(data) {
            // Skip chart updates if no data is available
            if (!data || !data.dates || !data.workingHours) {
                console.warn('Cannot update charts: missing chart data');
                return;
            }
            
            // Update Attendance Chart
            if (attendanceChart) {
                attendanceChart.destroy();
            }
            
            attendanceChart = new Chart(document.getElementById('attendanceChart'), {
                type: 'bar',
                data: {
                    labels: data.dates,
                    datasets: [{
                        label: 'Working Hours',
                        data: data.workingHours,
                        backgroundColor: 'rgba(52, 152, 219, 0.5)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Daily Working Hours'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Update Hours Distribution Chart
            if (hoursChart) {
                hoursChart.destroy();
            }
            
            // Use filtered overtime hours if available
            const overtimeHoursValue = data.filteredOvertimeHours || data.overtimeHours;
            
            hoursChart = new Chart(document.getElementById('hoursChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Regular Hours', 'Overtime Hours (≥1:30)'],
                    datasets: [{
                        data: [data.regularHours, overtimeHoursValue],
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.5)',
                            'rgba(231, 76, 60, 0.5)'
                        ],
                        borderColor: [
                            'rgba(52, 152, 219, 1)',
                            'rgba(231, 76, 60, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Hours Distribution'
                        }
                    }
                }
            });
        }

        function updateOverviewStats() {
            const period = document.getElementById('overviewPeriod').value;
            fetchOverviewData(period);
        }

        function filterMonthYear() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            
            // Calculate first and last day of selected month
            const firstDay = `${year}-${month.padStart(2, '0')}-01`;
            const lastDay = new Date(year, month, 0).toISOString().split('T')[0];
            
            // Update both table data and charts
            window.location.href = `work_sheet.php?month=${month}&year=${year}&start_date=${firstDay}&end_date=${lastDay}`;
        }

        function showWorkReport(report, date) {
            Swal.fire({
                title: `Work Report - ${date}`,
                html: `<div style="text-align: left; padding: 20px; background: #f8fafc; border-radius: 8px; margin-top: 20px;">
                    <pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">${report}</pre>
                </div>`,
                width: 600,
                confirmButtonText: 'Close',
                customClass: {
                    popup: 'work-report-popup'
                }
            });
        }
    </script>
</body>
</html>