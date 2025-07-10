<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "crm";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current date information
$currentDate = date('Y-m-d');
$currentDay = date('l');
$formattedDate = date('F j, Y');

// Default month and year
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('F');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$currentMonthNum = date('m', strtotime($currentMonth));

// Fetch company income data from database tables
// Get current month and year for filtering
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('F');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Convert month name to number
$monthNum = date('m', strtotime($selectedMonth . ' 1 ' . $selectedYear));

// Query to get income data grouped by date and project_type
$incomeQuery = "SELECT 
    DATE(pe.payment_date) as payment_date,
    pst.project_type,
    SUM(pe.payment_amount) as amount
FROM 
    hrm_project_payment_entries pe
JOIN 
    hrm_project_stage_payment_transactions pst ON pe.transaction_id = pst.transaction_id
WHERE 
    MONTH(pe.payment_date) = ? AND YEAR(pe.payment_date) = ?
GROUP BY 
    DATE(pe.payment_date), pst.project_type
ORDER BY 
    payment_date DESC";

try {
    $stmt = $conn->prepare($incomeQuery);
    $stmt->bind_param('ii', $monthNum, $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process the results into a structured format
    $incomeData = [];
    $dateData = [];
    
    // Initialize totals
    $totalArchitecture = 0;
    $totalInterior = 0;
    $totalConstruction = 0;
    
    // Additional query to get detailed information for each payment
$detailsQuery = "SELECT 
    DATE(pe.payment_date) as payment_date,
    pe.payment_id,
    pe.payment_amount,
    pe.payment_mode,
    pe.payment_reference,
    pst.transaction_id,
    pst.project_id,
    pst.project_name,
    pst.project_type,
    pst.client_name,
    pst.stage_number,
    pst.stage_notes
FROM 
    hrm_project_payment_entries pe
JOIN 
    hrm_project_stage_payment_transactions pst ON pe.transaction_id = pst.transaction_id
WHERE 
    MONTH(pe.payment_date) = ? AND YEAR(pe.payment_date) = ?
ORDER BY 
    pe.payment_date DESC";

$detailsStmt = $conn->prepare($detailsQuery);
$detailsStmt->bind_param('ii', $monthNum, $selectedYear);
$detailsStmt->execute();
$detailsResult = $detailsStmt->get_result();

// Store payment details indexed by date
$paymentDetails = [];
while ($detail = $detailsResult->fetch_assoc()) {
    $date = date('j M Y', strtotime($detail['payment_date']));
    if (!isset($paymentDetails[$date])) {
        $paymentDetails[$date] = [];
    }
    $paymentDetails[$date][] = $detail;
}

// Process each row from the database
while ($row = $result->fetch_assoc()) {
    $date = date('j M Y', strtotime($row['payment_date']));
    $projectType = $row['project_type'];
    $amount = floatval($row['amount']);
    
    // Initialize the date entry if it doesn't exist
    if (!isset($dateData[$date])) {
        $dateData[$date] = [
            'date' => $date,
            'architecture' => 0,
            'interior' => 0,
            'construction' => 0,
            'total' => 0,
            'details' => isset($paymentDetails[$date]) ? $paymentDetails[$date] : []
        ];
    }
    
    // Add the amount to the appropriate project type
    $dateData[$date][$projectType] += $amount;
    $dateData[$date]['total'] += $amount;
    
    // Add to the totals
    if ($projectType == 'architecture') {
        $totalArchitecture += $amount;
    } elseif ($projectType == 'interior') {
        $totalInterior += $amount;
    } elseif ($projectType == 'construction') {
        $totalConstruction += $amount;
    }
}
    
    // Convert the date data array to a flat array for the table
    $incomeData = array_values($dateData);
    
    // Calculate grand total
    $grandTotal = $totalArchitecture + $totalInterior + $totalConstruction;
    
} catch (Exception $e) {
    // If there's an error, provide some sample data
    error_log("Error fetching income data: " . $e->getMessage());
    
    // Sample fallback data
    $incomeData = [
        [
            'date' => date('j M Y'),
            'architecture' => 0,
            'interior' => 15000,
            'construction' => 0,
            'total' => 15000
        ],
        [
            'date' => date('j M Y', strtotime('-1 day')),
            'architecture' => 0,
            'interior' => 0,
            'construction' => 210000,
            'total' => 210000
        ]
    ];
    
    // Default totals
    $totalArchitecture = 0;
    $totalInterior = 15000;
    $totalConstruction = 210000;
    $grandTotal = 225000;
}

// Calculate manager commissions based on percentage of monthly income
$architectureCommissionRate = 0.05; // 5%
$interiorCommissionRate = 0.05; // 5% 
$constructionCommissionRate = 0.03; // 3%

// Sample data for manager payouts
// In a real application, this would come from database
$managers = [
    [
        'id' => 1,
        'name' => 'Rachana Pal',
        'type' => 'Senior Manager (Site)',
        'active' => true,
        'avatar' => 'R',
        'architecture_commission' => $totalArchitecture * $architectureCommissionRate,
        'interior_commission' => $totalInterior * $interiorCommissionRate,
        'construction_commission' => $totalConstruction * $constructionCommissionRate,
        'fixed_remuneration' => 30000.00,
        'total_commission' => ($totalArchitecture * $architectureCommissionRate) + 
                             ($totalInterior * $interiorCommissionRate) + 
                             ($totalConstruction * $constructionCommissionRate),
        'total_payable' => 30000.00 + ($totalArchitecture * $architectureCommissionRate) + 
                          ($totalInterior * $interiorCommissionRate) + 
                          ($totalConstruction * $constructionCommissionRate),
        'remaining_amount' => 0.00,
        'amount_paid' => 0.00
    ],
    [
        'id' => 2,
        'name' => 'Prabhat Arya',
        'type' => 'Senior Manager (Studio)',
        'active' => true,
        'avatar' => 'P',
        'architecture_commission' => $totalArchitecture * $architectureCommissionRate,
        'interior_commission' => $totalInterior * $interiorCommissionRate,
        'construction_commission' => 0.00,
        'fixed_remuneration' => 28000.00,
        'total_commission' => ($totalArchitecture * $architectureCommissionRate) + 
                             ($totalInterior * $interiorCommissionRate),
        'total_payable' => 28000.00 + ($totalArchitecture * $architectureCommissionRate) + 
                          ($totalInterior * $interiorCommissionRate),
        'remaining_amount' => 0.00,
        'amount_paid' => 0.00
    ],
    [
        'id' => 3,
        'name' => 'Yojna Sharma',
        'type' => 'Senior Manager (Studio)',
        'active' => true,
        'avatar' => 'Y',
        'architecture_commission' => $totalArchitecture * $architectureCommissionRate,
        'interior_commission' => $totalInterior * $interiorCommissionRate,
        'construction_commission' => 0.00,
        'fixed_remuneration' => 28000.00,
        'total_commission' => ($totalArchitecture * $architectureCommissionRate) + 
                             ($totalInterior * $interiorCommissionRate),
        'total_payable' => 28000.00 + ($totalArchitecture * $architectureCommissionRate) + 
                          ($totalInterior * $interiorCommissionRate),
        'remaining_amount' => 0.00,
        'amount_paid' => 0.00
    ]
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Analytics Dashboard</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --warning-color: #fca311;
            --danger-color: #ef233c;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Left Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary-color);
            color: white;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link {
            color: var(--dark-color);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover {
            background-color: rgba(67, 97, 238, 0.05);
            color: var(--primary-color);
        }

        .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Dashboard Header */
        .dashboard-header {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .date-display {
            font-size: 1.1rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        .dashboard-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        /* Cards */
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            height: 100%;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .stat-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.75rem;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Charts Container */
        .chart-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }

        /* Coming Soon Placeholder */
        .coming-soon {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 3rem;
            box-shadow: var(--box-shadow);
            text-align: center;
            margin-bottom: 2rem;
        }

        .coming-soon h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .coming-soon p {
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Company Income Section */
        .income-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }
        
        .income-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .income-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .income-filters {
            display: flex;
            gap: 0.5rem;
        }
        
        .income-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .income-table th {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 1px solid #e9ecef;
        }
        
        .income-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .income-table tr:last-child td {
            border-bottom: none;
            border-top: 1px solid #e9ecef;
            font-weight: 700;
        }
        
        .income-table .text-right {
            text-align: right;
        }
        
        .income-table .text-green {
            color: var(--success-color);
        }
        
        /* Payment details styles */
        .payment-details-badge {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .payment-details-badge:hover {
            background-color: var(--primary-color) !important;
        }
        
        .payment-details-card {
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-radius: 6px;
            overflow: hidden;
        }
        
        .payment-details-card .table-sm td, 
        .payment-details-card .table-sm th {
            padding: 0.5rem 0.75rem;
        }
        
        /* Enhanced modal styles */
        .modal-content {
            overflow: hidden;
        }
        
        .modal-header.bg-primary {
            background-color: var(--primary-color) !important;
            border-bottom: 0;
        }
        
        .modal-title {
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.04);
            transition: background-color 0.2s ease;
        }
        
        .card-header {
            border-bottom: 0;
        }
        
        .modal .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .modal .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08) !important;
        }
        
        .modal .progress {
            overflow: visible;
        }
        
        .modal .progress-bar {
            position: relative;
        }
        
        .modal .progress-bar::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 0 5px 5px 0;
        }
        
        /* Manager Payouts Section */
        .managers-section {
            margin-bottom: 2rem;
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
        }
        
        .managers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .manager-card {
            background-color: white;
            border-radius: var(--border-radius);
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .manager-header {
            padding: 1rem;
            color: white;
            font-weight: 600;
        }
        
        .site-manager {
            background-color: #36b9cc;
        }
        
        .studio-manager {
            background-color: #4e73df;
        }
        
        .manager-profile {
            display: flex;
            align-items: center;
            padding: 1rem;
            gap: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .manager-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #5a5c69;
        }
        
        .manager-info {
            flex: 1;
        }
        
        .manager-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .manager-status {
            display: inline-flex;
            align-items: center;
            color: #e74a3b;
            font-size: 0.875rem;
            gap: 0.25rem;
        }
        
        .manager-status.active {
            color: #1cc88a;
        }
        
        .manager-summary {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
            background-color: #f8fbff;
        }
        
        .total-payable {
            font-size: 1.75rem;
            font-weight: 700;
            color: #4e73df;
            margin-bottom: 0.75rem;
            text-shadow: 0 1px 1px rgba(0,0,0,0.1);
        }
        
        .remaining-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .remaining-details .green {
            color: #1cc88a;
        }
        
        .commission-details {
            padding: 1.25rem;
            flex-grow: 1;
        }
        
        .commission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            padding: 0.5rem 0;
        }
        
        .commission-bar {
            height: 6px;
            width: 100%;
            background-color: #eaecf4;
            border-radius: 3px;
            margin-bottom: 1.25rem;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .commission-progress {
            height: 100%;
            border-radius: 3px;
            box-shadow: 0 1px 1px rgba(255,255,255,0.5);
            transition: width 0.3s ease;
        }
        
        .progress-architecture {
            background-color: #4e73df;
        }
        
        .progress-interior {
            background-color: #36b9cc;
        }
        
        .progress-construction {
            background-color: #1cc88a;
        }
        
        .commission-total {
            padding: 1rem;
            border-top: 1px solid #e9ecef;
            margin-top: auto; /* Push to bottom of flex container */
        }
        
        .commission-breakdown {
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .commission-breakdown-item {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            padding: 0.5rem;
            border-radius: 0.25rem;
            background-color: #f8f9fa;
        }
        
        .commission-breakdown-item .value {
            color: #4e73df;
            font-weight: 500;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            margin-top: 1rem;
            color: #5a5c69;
            padding: 0.75rem;
            border-radius: 0.25rem;
            background-color: #eaedfe;
        }
        
        .total-row .value {
            color: #4e73df;
            font-size: 1.1rem;
        }
        
        .card-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1.25rem;
            border-top: 1px solid #e9ecef;
            background-color: #f8f9fa;
        }
        
        .btn-view {
            flex: 1;
            background-color: #4e73df;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-pay {
            flex: 1;
            background-color: #1cc88a;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-building"></i>
            <span>HR Portal</span>
        </div>
        <div class="nav-links">
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="manager_payouts.php" class="nav-link">
                <i class="bi bi-cash-stack"></i>
                <span>Manager Payouts</span>
            </a>
            <a href="company_analytics_dashboard.php" class="nav-link active">
                <i class="bi bi-graph-up"></i>
                <span>Company Statistics</span>
            </a>
            <a href="#" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Employees</span>
            </a>
            <a href="#" class="nav-link">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance</span>
            </a>
            <a href="#" class="nav-link">
                <i class="bi bi-briefcase"></i>
                <span>Projects</span>
            </a>
        </div>
    </div>

    <!-- Toggle Sidebar Button -->
    <div class="toggle-sidebar" id="toggleSidebar">
        <i class="bi bi-chevron-left"></i>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="dashboard-title">Company Statistics</h1>
                    <div class="date-display">
                        <i class="bi bi-calendar3"></i> <?php echo $currentDay; ?>, <?php echo $formattedDate; ?>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <button type="button" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> New Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Company Income Section -->
        <div class="income-section">
            <div class="income-header">
                <div class="income-title">Company Income</div>
                <div class="income-filters">
                    <select class="form-select form-select-sm" id="monthFilter">
                        <option value="January" <?php echo ($selectedMonth == 'January') ? 'selected' : ''; ?>>January</option>
                        <option value="February" <?php echo ($selectedMonth == 'February') ? 'selected' : ''; ?>>February</option>
                        <option value="March" <?php echo ($selectedMonth == 'March') ? 'selected' : ''; ?>>March</option>
                        <option value="April" <?php echo ($selectedMonth == 'April') ? 'selected' : ''; ?>>April</option>
                        <option value="May" <?php echo ($selectedMonth == 'May') ? 'selected' : ''; ?>>May</option>
                        <option value="June" <?php echo ($selectedMonth == 'June') ? 'selected' : ''; ?>>June</option>
                        <option value="July" <?php echo ($selectedMonth == 'July') ? 'selected' : ''; ?>>July</option>
                        <option value="August" <?php echo ($selectedMonth == 'August') ? 'selected' : ''; ?>>August</option>
                        <option value="September" <?php echo ($selectedMonth == 'September') ? 'selected' : ''; ?>>September</option>
                        <option value="October" <?php echo ($selectedMonth == 'October') ? 'selected' : ''; ?>>October</option>
                        <option value="November" <?php echo ($selectedMonth == 'November') ? 'selected' : ''; ?>>November</option>
                        <option value="December" <?php echo ($selectedMonth == 'December') ? 'selected' : ''; ?>>December</option>
                    </select>
                    <select class="form-select form-select-sm" id="yearFilter">
                        <?php 
                        $currentYear = date('Y');
                        for ($year = $currentYear - 2; $year <= $currentYear + 2; $year++) {
                            $selected = ($selectedYear == $year) ? 'selected' : '';
                            echo "<option value=\"$year\" $selected>$year</option>";
                        }
                        ?>
                    </select>
                    <button class="btn btn-primary btn-sm" id="applyFilter">
                        <i class="bi bi-funnel"></i> Apply
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="income-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-right">Architecture</th>
                            <th class="text-right">Interior</th>
                            <th class="text-right">Construction</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incomeData)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-3">
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No income data found for <?php echo $selectedMonth . ' ' . $selectedYear; ?>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($incomeData as $index => $income): ?>
                            <tr>
                                <td>
                                    <?php echo $income['date']; ?>
                                    <?php if (!empty($income['details'])): ?>
                                    <button type="button" class="btn btn-sm text-primary p-0 ms-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#incomeDetailsModal<?php echo $index; ?>"
                                            title="View Details">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">₹<?php echo number_format($income['architecture']); ?></td>
                                <td class="text-right">₹<?php echo number_format($income['interior']); ?></td>
                                <td class="text-right">₹<?php echo number_format($income['construction']); ?></td>
                                <td class="text-right">₹<?php echo number_format($income['total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td class="text-right">₹<?php echo number_format($totalArchitecture); ?></td>
                            <td class="text-right">₹<?php echo number_format($totalInterior); ?></td>
                            <td class="text-right">₹<?php echo number_format($totalConstruction); ?></td>
                            <td class="text-right text-green">₹<?php echo number_format($grandTotal); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Manager Payouts Section -->
        <div class="managers-section">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold">Manager Payouts</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <button class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Add Payout
                    </button>
                </div>
            </div>
            
            <!-- Manager Type Tabs -->
            <ul class="nav nav-tabs mt-3" id="managerTypeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-managers-tab" data-bs-toggle="tab" data-bs-target="#all-managers" type="button" role="tab" aria-controls="all-managers" aria-selected="true">
                        <i class="bi bi-people-fill me-1"></i> All Managers
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="studio-managers-tab" data-bs-toggle="tab" data-bs-target="#studio-managers" type="button" role="tab" aria-controls="studio-managers" aria-selected="false">
                        <i class="bi bi-building me-1"></i> Studio Managers
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="site-managers-tab" data-bs-toggle="tab" data-bs-target="#site-managers" type="button" role="tab" aria-controls="site-managers" aria-selected="false">
                        <i class="bi bi-geo-alt me-1"></i> Site Managers
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content pt-3" id="managerTypeContent">
                <!-- All Managers Tab -->
                <div class="tab-pane fade show active" id="all-managers" role="tabpanel" aria-labelledby="all-managers-tab">
                    <div class="managers-grid">
                <?php foreach ($managers as $manager): ?>
                    <div class="manager-card">
                        <div class="manager-header <?= strpos($manager['type'], 'Site') !== false ? 'site-manager' : 'studio-manager' ?>">
                            <?= htmlspecialchars($manager['type']) ?>
                        </div>
                        
                        <div class="manager-profile">
                            <div class="manager-avatar">
                                <?= htmlspecialchars($manager['avatar']) ?>
                            </div>
                            <div class="manager-info">
                                <div class="manager-name"><?= htmlspecialchars($manager['name']) ?></div>
                                <div class="manager-status <?= $manager['active'] ? 'active' : '' ?>">
                                    <i class="bi bi-circle-fill"></i> <?= $manager['active'] ? 'Active' : 'Inactive' ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="manager-summary">
                            <div class="total-payable">
                                ₹<?= number_format($manager['total_payable'], 2) ?>
                            </div>
                            <div class="remaining-details">
                                <div>Remaining Amount: <span class="red">₹<?= number_format($manager['remaining_amount'], 2) ?></span></div>
                                <div>Amount Paid: <span class="green">₹<?= number_format($manager['amount_paid'], 2) ?></span></div>
                            </div>
                        </div>
                        
                        <div class="commission-details">
                            <div class="commission-item">
                                <span><i class="bi bi-building"></i> Architecture (5% of ₹<?= number_format($totalArchitecture, 0) ?>)</span>
                                <span>₹<?= number_format($manager['architecture_commission'], 2) ?></span>
                            </div>
                            <div class="commission-bar">
                                <div class="commission-progress progress-architecture" style="width: <?= ($manager['architecture_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                            </div>
                            
                            <div class="commission-item">
                                <span><i class="bi bi-house"></i> Interior (5% of ₹<?= number_format($totalInterior, 0) ?>)</span>
                                <span>₹<?= number_format($manager['interior_commission'], 2) ?></span>
                            </div>
                            <div class="commission-bar">
                                <div class="commission-progress progress-interior" style="width: <?= ($manager['interior_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                            </div>
                            
                            <?php if (isset($manager['construction_commission']) && $manager['construction_commission'] > 0): ?>
                            <div class="commission-item">
                                <span><i class="bi bi-bricks"></i> Construction (3% of ₹<?= number_format($totalConstruction, 0) ?>)</span>
                                <span>₹<?= number_format($manager['construction_commission'], 2) ?></span>
                            </div>
                            <div class="commission-bar">
                                <div class="commission-progress progress-construction" style="width: <?= ($manager['construction_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="commission-total">
                            <div class="commission-breakdown">
                                <div class="commission-breakdown-item">
                                    <span>Architecture Commission</span>
                                    <span class="value">₹<?= number_format($manager['architecture_commission'], 2) ?></span>
                                </div>
                                <div class="commission-breakdown-item">
                                    <span>Interior Commission</span>
                                    <span class="value">₹<?= number_format($manager['interior_commission'], 2) ?></span>
                                </div>
                                <?php if (isset($manager['construction_commission']) && $manager['construction_commission'] > 0): ?>
                                <div class="commission-breakdown-item">
                                    <span>Construction Commission</span>
                                    <span class="value">₹<?= number_format($manager['construction_commission'], 2) ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="commission-breakdown-item">
                                    <span>Fixed Remuneration</span>
                                    <span class="value">₹<?= number_format($manager['fixed_remuneration'], 2) ?></span>
                                </div>
                            </div>
                            
                            <div class="total-row">
                                <span>Total Payable</span>
                                <span class="value">₹<?= number_format($manager['total_payable'], 2) ?></span>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <button class="btn-view">
                                <i class="bi bi-eye"></i> View Payout Details
                            </button>
                            <button class="btn-pay">
                                <i class="bi bi-cash"></i> Pay Amount
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                                </div>
                </div>
                
                <!-- Studio Managers Tab -->
                <div class="tab-pane fade" id="studio-managers" role="tabpanel" aria-labelledby="studio-managers-tab">
                    <div class="managers-grid">
                        <?php foreach ($managers as $manager): ?>
                            <?php if($manager['type'] == 'Senior Manager (Studio)'): ?>
                            <div class="manager-card">
                                <div class="manager-header studio-manager">
                                    <?= htmlspecialchars($manager['type']) ?>
                                </div>
                                
                                <div class="manager-profile">
                                    <div class="manager-avatar">
                                        <?= htmlspecialchars($manager['avatar']) ?>
                                    </div>
                                    <div class="manager-info">
                                        <div class="manager-name"><?= htmlspecialchars($manager['name']) ?></div>
                                        <div class="manager-status <?= $manager['active'] ? 'active' : '' ?>">
                                            <i class="bi bi-circle-fill"></i> <?= $manager['active'] ? 'Active' : 'Inactive' ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="manager-summary">
                                    <div class="total-payable">
                                        ₹<?= number_format($manager['total_payable'], 2) ?>
                                    </div>
                                    <div class="remaining-details">
                                        <div>Remaining Amount: <span class="red">₹<?= number_format($manager['remaining_amount'], 2) ?></span></div>
                                        <div>Amount Paid: <span class="green">₹<?= number_format($manager['amount_paid'], 2) ?></span></div>
                                    </div>
                                </div>
                                
                                <div class="commission-details">
                                    <div class="commission-item">
                                        <span><i class="bi bi-building"></i> Architecture (5% of ₹<?= number_format($totalArchitecture, 0) ?>)</span>
                                        <span>₹<?= number_format($manager['architecture_commission'], 2) ?></span>
                                    </div>
                                    <div class="commission-bar">
                                        <div class="commission-progress progress-architecture" style="width: <?= ($manager['architecture_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                                    </div>
                                    
                                    <div class="commission-item">
                                        <span><i class="bi bi-house"></i> Interior (5% of ₹<?= number_format($totalInterior, 0) ?>)</span>
                                        <span>₹<?= number_format($manager['interior_commission'], 2) ?></span>
                                    </div>
                                    <div class="commission-bar">
                                        <div class="commission-progress progress-interior" style="width: <?= ($manager['interior_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                                    </div>
                                    
                                    <?php if (isset($manager['construction_commission']) && $manager['construction_commission'] > 0): ?>
                                    <div class="commission-item">
                                        <span><i class="bi bi-bricks"></i> Construction (3% of ₹<?= number_format($totalConstruction, 0) ?>)</span>
                                        <span>₹<?= number_format($manager['construction_commission'], 2) ?></span>
                                    </div>
                                    <div class="commission-bar">
                                        <div class="commission-progress progress-construction" style="width: <?= ($manager['construction_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="commission-total">
                                    <div class="commission-breakdown">
                                        <div class="commission-breakdown-item">
                                            <span>Architecture Commission</span>
                                            <span class="value">₹<?= number_format($manager['architecture_commission'], 2) ?></span>
                                        </div>
                                        <div class="commission-breakdown-item">
                                            <span>Interior Commission</span>
                                            <span class="value">₹<?= number_format($manager['interior_commission'], 2) ?></span>
                                        </div>
                                        <?php if (isset($manager['construction_commission']) && $manager['construction_commission'] > 0): ?>
                                        <div class="commission-breakdown-item">
                                            <span>Construction Commission</span>
                                            <span class="value">₹<?= number_format($manager['construction_commission'], 2) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="commission-breakdown-item">
                                            <span>Fixed Remuneration</span>
                                            <span class="value">₹<?= number_format($manager['fixed_remuneration'], 2) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="total-row">
                                        <span>Total Payable</span>
                                        <span class="value">₹<?= number_format($manager['total_payable'], 2) ?></span>
                                    </div>
                                </div>
                                
                                <div class="card-actions">
                                    <button class="btn-view">
                                        <i class="bi bi-eye"></i> View Payout Details
                                    </button>
                                    <button class="btn-pay">
                                        <i class="bi bi-cash"></i> Pay Amount
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Site Managers Tab -->
                <div class="tab-pane fade" id="site-managers" role="tabpanel" aria-labelledby="site-managers-tab">
                    <div class="managers-grid">
                        <?php foreach ($managers as $manager): ?>
                            <?php if($manager['type'] == 'Senior Manager (Site)'): ?>
                            <div class="manager-card">
                                <div class="manager-header site-manager">
                                    <?= htmlspecialchars($manager['type']) ?>
                                </div>
                                
                                <div class="manager-profile">
                                    <div class="manager-avatar">
                                        <?= htmlspecialchars($manager['avatar']) ?>
                                    </div>
                                    <div class="manager-info">
                                        <div class="manager-name"><?= htmlspecialchars($manager['name']) ?></div>
                                        <div class="manager-status <?= $manager['active'] ? 'active' : '' ?>">
                                            <i class="bi bi-circle-fill"></i> <?= $manager['active'] ? 'Active' : 'Inactive' ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="manager-summary">
                                    <div class="total-payable">
                                        ₹<?= number_format($manager['total_payable'], 2) ?>
                                    </div>
                                    <div class="remaining-details">
                                        <div>Remaining Amount: <span class="red">₹<?= number_format($manager['remaining_amount'], 2) ?></span></div>
                                        <div>Amount Paid: <span class="green">₹<?= number_format($manager['amount_paid'], 2) ?></span></div>
                                    </div>
                                </div>
                                
                                <div class="commission-details">
                                    <div class="commission-item">
                                        <span><i class="bi bi-building"></i> Architecture (5% of ₹<?= number_format($totalArchitecture, 0) ?>)</span>
                                        <span>₹<?= number_format($manager['architecture_commission'], 2) ?></span>
                                    </div>
                                    <div class="commission-bar">
                                        <div class="commission-progress progress-architecture" style="width: <?= ($manager['architecture_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                                    </div>
                                    
                                    <div class="commission-item">
                                        <span><i class="bi bi-house"></i> Interior (5% of ₹<?= number_format($totalInterior, 0) ?>)</span>
                                        <span>₹<?= number_format($manager['interior_commission'], 2) ?></span>
                                    </div>
                                    <div class="commission-bar">
                                        <div class="commission-progress progress-interior" style="width: <?= ($manager['interior_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                                    </div>
                                    
                                    <?php if (isset($manager['construction_commission']) && $manager['construction_commission'] > 0): ?>
                                    <div class="commission-item">
                                        <span><i class="bi bi-bricks"></i> Construction (3% of ₹<?= number_format($totalConstruction, 0) ?>)</span>
                                        <span>₹<?= number_format($manager['construction_commission'], 2) ?></span>
                                    </div>
                                    <div class="commission-bar">
                                        <div class="commission-progress progress-construction" style="width: <?= ($manager['construction_commission'] / $manager['total_payable']) * 100 ?>%"></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="commission-total">
                                    <div class="commission-breakdown">
                                        <div class="commission-breakdown-item">
                                            <span>Architecture Commission</span>
                                            <span class="value">₹<?= number_format($manager['architecture_commission'], 2) ?></span>
                                        </div>
                                        <div class="commission-breakdown-item">
                                            <span>Interior Commission</span>
                                            <span class="value">₹<?= number_format($manager['interior_commission'], 2) ?></span>
                                        </div>
                                        <?php if (isset($manager['construction_commission']) && $manager['construction_commission'] > 0): ?>
                                        <div class="commission-breakdown-item">
                                            <span>Construction Commission</span>
                                            <span class="value">₹<?= number_format($manager['construction_commission'], 2) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="commission-breakdown-item">
                                            <span>Fixed Remuneration</span>
                                            <span class="value">₹<?= number_format($manager['fixed_remuneration'], 2) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="total-row">
                                        <span>Total Payable</span>
                                        <span class="value">₹<?= number_format($manager['total_payable'], 2) ?></span>
                                    </div>
                                </div>
                                
                                <div class="card-actions">
                                    <button class="btn-view">
                                        <i class="bi bi-eye"></i> View Payout Details
                                    </button>
                                    <button class="btn-pay">
                                        <i class="bi bi-cash"></i> Pay Amount
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
        <!-- Statistics Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(67, 97, 238, 0.1); color: var(--primary-color);">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <h3 class="stat-card-title">TOTAL REVENUE</h3>
                    <div class="stat-card-value">₹3,485,200</div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">+12.5%</span>
                        <small>vs last month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--accent-color);">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <h3 class="stat-card-title">ACTIVE PROJECTS</h3>
                    <div class="stat-card-value">28</div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">+3</span>
                        <small>new this month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(75, 181, 67, 0.1); color: var(--success-color);">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3 class="stat-card-title">TEAM MEMBERS</h3>
                    <div class="stat-card-value">42</div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">+5</span>
                        <small>since last quarter</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(252, 163, 17, 0.1); color: var(--warning-color);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3 class="stat-card-title">PRODUCTIVITY</h3>
                    <div class="stat-card-value">87%</div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-danger me-2">-2.3%</span>
                        <small>vs last week</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coming Soon Section -->
        <div class="coming-soon">
            <i class="bi bi-bar-chart-line" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1.5rem;"></i>
            <h3>Advanced Analytics Coming Soon</h3>
            <p>We're working on comprehensive analytics and reporting features that will be available here shortly. Check back for detailed insights into company performance, project metrics, and team productivity.</p>
            <button class="btn btn-outline-primary mt-3">
                <i class="bi bi-bell"></i> Notify Me When Ready
            </button>
        </div>
    </div>

    <!-- Income Details Modals -->
    <?php if (!empty($incomeData)): ?>
        <?php foreach ($incomeData as $index => $income): ?>
            <?php if (!empty($income['details'])): ?>
            <div class="modal fade" id="incomeDetailsModal<?php echo $index; ?>" tabindex="-1" aria-labelledby="incomeDetailsModalLabel<?php echo $index; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="incomeDetailsModalLabel<?php echo $index; ?>">
                                <i class="bi bi-calendar-check me-2"></i>Income Details for <?php echo $income['date']; ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 border-top">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="bi bi-building me-2 text-primary"></i>Project</th>
                                            <th><i class="bi bi-person-badge me-2 text-primary"></i>Client</th>
                                            <th><i class="bi bi-layers me-2 text-primary"></i>Stage</th>
                                            <th><i class="bi bi-currency-rupee me-2 text-primary"></i>Amount</th>
                                            <th><i class="bi bi-credit-card me-2 text-primary"></i>Payment Mode</th>
                                            <th><i class="bi bi-hash me-2 text-primary"></i>Reference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Group payments by transaction_id and stage_number
                                        $groupedPayments = [];
                                        foreach ($income['details'] as $detail) {
                                            $key = $detail['transaction_id'] . '-' . $detail['stage_number'];
                                            if (!isset($groupedPayments[$key])) {
                                                $groupedPayments[$key] = [
                                                    'transaction_id' => $detail['transaction_id'],
                                                    'project_id' => $detail['project_id'],
                                                    'project_name' => $detail['project_name'],
                                                    'project_type' => $detail['project_type'],
                                                    'client_name' => $detail['client_name'],
                                                    'stage_number' => $detail['stage_number'],
                                                    'stage_notes' => $detail['stage_notes'],
                                                    'total_amount' => 0,
                                                    'payments' => []
                                                ];
                                            }
                                            
                                            $groupedPayments[$key]['total_amount'] += $detail['payment_amount'];
                                            $groupedPayments[$key]['payments'][] = [
                                                'payment_id' => $detail['payment_id'],
                                                'payment_amount' => $detail['payment_amount'],
                                                'payment_mode' => $detail['payment_mode'],
                                                'payment_reference' => $detail['payment_reference']
                                            ];
                                        }
                                        
                                        foreach ($groupedPayments as $groupKey => $group): 
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($group['project_name']); ?></div>
                                                <div class="small text-muted">
                                                    <?php 
                                                    $badgeClass = '';
                                                    switch($group['project_type']) {
                                                        case 'architecture': $badgeClass = 'bg-primary'; break;
                                                        case 'interior': $badgeClass = 'bg-info'; break;
                                                        case 'construction': $badgeClass = 'bg-success'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($group['project_type']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($group['client_name']); ?></td>
                                            <td>
                                                Stage <?php echo $group['stage_number']; ?>
                                                <?php if (!empty($group['stage_notes'])): ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars(mb_substr($group['stage_notes'], 0, 30)) . (mb_strlen($group['stage_notes']) > 30 ? '...' : ''); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold">₹<?php echo number_format($group['total_amount'], 2); ?></td>
                                            <td>
                                                <?php if (count($group['payments']) == 1): ?>
                                                    <?php 
                                                    $payment = $group['payments'][0];
                                                    $modeIcon = '';
                                                    switch($payment['payment_mode']) {
                                                        case 'cash': $modeIcon = 'bi-cash'; break;
                                                        case 'upi': $modeIcon = 'bi-phone'; break;
                                                        case 'net_banking': $modeIcon = 'bi-bank'; break;
                                                        case 'cheque': $modeIcon = 'bi-credit-card-2-back'; break;
                                                        case 'credit_card': $modeIcon = 'bi-credit-card'; break;
                                                    }
                                                    ?>
                                                    <i class="bi <?php echo $modeIcon; ?> me-1"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_mode'])); ?>
                                                <?php else: ?>
                                                    <?php 
                                                    // Get first payment mode
                                                    $firstPayment = $group['payments'][0];
                                                    $modeIcon = '';
                                                    switch($firstPayment['payment_mode']) {
                                                        case 'cash': $modeIcon = 'bi-cash'; break;
                                                        case 'upi': $modeIcon = 'bi-phone'; break;
                                                        case 'net_banking': $modeIcon = 'bi-bank'; break;
                                                        case 'cheque': $modeIcon = 'bi-credit-card-2-back'; break;
                                                        case 'credit_card': $modeIcon = 'bi-credit-card'; break;
                                                    }
                                                    $remainingModes = count($group['payments']) - 1;
                                                    ?>
                                                    <i class="bi <?php echo $modeIcon; ?> me-1"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $firstPayment['payment_mode'])); ?>
                                                    <a href="#" class="badge bg-secondary ms-1 payment-details-badge" 
                                                       data-bs-toggle="collapse" 
                                                       data-bs-target="#paymentDetails-<?php echo $groupKey; ?>" 
                                                       aria-expanded="false">
                                                        +<?php echo $remainingModes; ?> more <?php echo $remainingModes == 1 ? 'mode' : 'modes'; ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (count($group['payments']) == 1): ?>
                                                    <?php echo !empty($group['payments'][0]['payment_reference']) ? htmlspecialchars($group['payments'][0]['payment_reference']) : '<span class="text-muted">-</span>'; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Multiple</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if (count($group['payments']) > 1): ?>
                                        <tr class="collapse" id="paymentDetails-<?php echo $groupKey; ?>">
                                            <td colspan="6" class="p-0">
                                                <div class="card m-2 border payment-details-card">
                                                    <div class="card-header bg-light py-2">
                                                        <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment Breakdown</h6>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <table class="table table-sm mb-0">
                                                            <thead>
                                                                <tr class="table-light">
                                                                    <th>Amount</th>
                                                                    <th>Payment Mode</th>
                                                                    <th>Reference</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($group['payments'] as $payment): ?>
                                                                <tr>
                                                                    <td class="fw-medium">₹<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                                                    <td>
                                                                        <?php 
                                                                        $modeIcon = '';
                                                                        switch($payment['payment_mode']) {
                                                                            case 'cash': $modeIcon = 'bi-cash'; break;
                                                                            case 'upi': $modeIcon = 'bi-phone'; break;
                                                                            case 'net_banking': $modeIcon = 'bi-bank'; break;
                                                                            case 'cheque': $modeIcon = 'bi-credit-card-2-back'; break;
                                                                            case 'credit_card': $modeIcon = 'bi-credit-card'; break;
                                                                        }
                                                                        ?>
                                                                        <i class="bi <?php echo $modeIcon; ?> me-1"></i>
                                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_mode'])); ?>
                                                                    </td>
                                                                    <td><?php echo !empty($payment['payment_reference']) ? htmlspecialchars($payment['payment_reference']) : '<span class="text-muted">-</span>'; ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-4 bg-light border-top">
                                <h5 class="mb-3 text-primary"><i class="bi bi-graph-up me-2"></i>Summary Analysis</h5>
                                <div class="row">
                                <div class="col-md-6">
                                    <div class="card shadow-sm border-0">
                                        <div class="card-header bg-white py-3">
                                            <h6 class="card-title mb-0 d-flex align-items-center">
                                                <i class="bi bi-pie-chart-fill me-2 text-primary"></i>
                                                <span>Summary by Project Type</span>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mt-3">
                                                <?php 
                                                $typeTotals = [
                                                    'architecture' => 0,
                                                    'interior' => 0,
                                                    'construction' => 0
                                                ];
                                                
                                                // Calculate totals using the original details to get accurate values
                                                foreach ($income['details'] as $detail) {
                                                    $typeTotals[$detail['project_type']] += $detail['payment_amount'];
                                                }
                                                ?>
                                                <?php if ($typeTotals['architecture'] > 0): ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span><i class="bi bi-building me-1"></i> Architecture</span>
                                                        <span>
                                                            ₹<?php echo number_format($typeTotals['architecture'], 2); ?>
                                                            <span class="badge rounded-pill bg-light text-primary ms-1">
                                                                <?php echo round(($typeTotals['architecture'] / $income['total']) * 100); ?>%
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height: 10px; border-radius: 5px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                                                        <div class="progress-bar bg-primary" role="progressbar" 
                                                            style="width: <?php echo ($typeTotals['architecture'] / $income['total']) * 100; ?>%; border-radius: 5px; transition: width 1s ease;"
                                                            aria-valuenow="<?php echo ($typeTotals['architecture'] / $income['total']) * 100; ?>" 
                                                            aria-valuemin="0" 
                                                            aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($typeTotals['interior'] > 0): ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span><i class="bi bi-house me-1"></i> Interior</span>
                                                        <span>
                                                            ₹<?php echo number_format($typeTotals['interior'], 2); ?>
                                                            <span class="badge rounded-pill bg-light text-info ms-1">
                                                                <?php echo round(($typeTotals['interior'] / $income['total']) * 100); ?>%
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height: 10px; border-radius: 5px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                                                        <div class="progress-bar bg-info" role="progressbar" 
                                                            style="width: <?php echo ($typeTotals['interior'] / $income['total']) * 100; ?>%; border-radius: 5px; transition: width 1s ease;"
                                                            aria-valuenow="<?php echo ($typeTotals['interior'] / $income['total']) * 100; ?>" 
                                                            aria-valuemin="0" 
                                                            aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($typeTotals['construction'] > 0): ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span><i class="bi bi-bricks me-1"></i> Construction</span>
                                                        <span>
                                                            ₹<?php echo number_format($typeTotals['construction'], 2); ?>
                                                            <span class="badge rounded-pill bg-light text-success ms-1">
                                                                <?php echo round(($typeTotals['construction'] / $income['total']) * 100); ?>%
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height: 10px; border-radius: 5px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                            style="width: <?php echo ($typeTotals['construction'] / $income['total']) * 100; ?>%; border-radius: 5px; transition: width 1s ease;"
                                                            aria-valuenow="<?php echo ($typeTotals['construction'] / $income['total']) * 100; ?>" 
                                                            aria-valuemin="0" 
                                                            aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card shadow-sm border-0">
                                        <div class="card-header bg-white py-3">
                                            <h6 class="card-title mb-0 d-flex align-items-center">
                                                <i class="bi bi-wallet-fill me-2 text-primary"></i>
                                                <span>Payment Methods</span>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mt-3">
                                                <?php 
                                                $modeTotals = [];
                                                // Use original details for accurate totals
                                                foreach ($income['details'] as $detail) {
                                                    $mode = $detail['payment_mode'];
                                                    if (!isset($modeTotals[$mode])) {
                                                        $modeTotals[$mode] = 0;
                                                    }
                                                    $modeTotals[$mode] += $detail['payment_amount'];
                                                }
                                                
                                                $colors = [
                                                    'cash' => 'success',
                                                    'upi' => 'primary',
                                                    'net_banking' => 'info',
                                                    'cheque' => 'warning',
                                                    'credit_card' => 'danger'
                                                ];
                                                
                                                foreach ($modeTotals as $mode => $amount): 
                                                    $colorClass = isset($colors[$mode]) ? $colors[$mode] : 'secondary';
                                                ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>
                                                            <?php 
                                                            $modeIcon = '';
                                                            switch($mode) {
                                                                case 'cash': $modeIcon = 'bi-cash'; break;
                                                                case 'upi': $modeIcon = 'bi-phone'; break;
                                                                case 'net_banking': $modeIcon = 'bi-bank'; break;
                                                                case 'cheque': $modeIcon = 'bi-credit-card-2-back'; break;
                                                                case 'credit_card': $modeIcon = 'bi-credit-card'; break;
                                                            }
                                                            ?>
                                                            <i class="bi <?php echo $modeIcon; ?> me-1"></i>
                                                            <?php echo ucfirst(str_replace('_', ' ', $mode)); ?>
                                                        </span>
                                                        <span>
                                                            ₹<?php echo number_format($amount, 2); ?>
                                                            <span class="badge rounded-pill bg-light text-<?php echo $colorClass; ?> ms-1">
                                                                <?php echo round(($amount / $income['total']) * 100); ?>%
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height: 10px; border-radius: 5px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                                                        <div class="progress-bar bg-<?php echo $colorClass; ?>" role="progressbar" 
                                                            style="width: <?php echo ($amount / $income['total']) * 100; ?>%; border-radius: 5px; transition: width 1s ease;"
                                                            aria-valuenow="<?php echo ($amount / $income['total']) * 100; ?>" 
                                                            aria-valuemin="0" 
                                                            aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i> Showing all transactions for <?php echo $income['date']; ?>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                        <i class="bi bi-check-lg me-2"></i>Done
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Toggle sidebar functionality
        const sidebar = document.getElementById('sidebar');
        const toggleSidebar = document.getElementById('toggleSidebar');
        const mainContent = document.getElementById('mainContent');
        
        toggleSidebar.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            toggleSidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
        
        // Filter functionality for Company Income
        document.getElementById('applyFilter').addEventListener('click', function() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;
            
            // Redirect to the same page with new filter parameters and preserve any other query params
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('month', month);
            currentUrl.searchParams.set('year', year);
            
            window.location.href = currentUrl.toString();
        });
        
        // Also make Enter key work on the filters
        document.querySelectorAll('#monthFilter, #yearFilter').forEach(filter => {
            filter.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('applyFilter').click();
                }
            });
        });
    </script>
</body>
</html> 