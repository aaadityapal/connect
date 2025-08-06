<?php
// Enable error reporting for troubleshooting but hide from users
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Create a log file for debugging
$logFile = 'logs/export_errors.log';
if (!file_exists(dirname($logFile))) {
    @mkdir(dirname($logFile), 0777, true);
}

function logError($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    @error_log($logMessage, 3, $logFile);
}

try {
    logError("Export started - Recording parameters: " . json_encode($_GET));
    
    // Include database connection
    require_once 'config/db_connect.php';
    logError("Database connection included");

    // Session and authentication check
    session_start();
    logError("Session started");

    // Restrict access to specific roles
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // User not logged in, redirect to login page
        header("Location: login.php?redirect=labour_attendance.php");
        exit();
    }

    // Check if user has authorized role
    $authorized_roles = array('HR', 'Site Coordinator', 'Senior Manager (Site)', 'Purchase Manager');
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        // User doesn't have authorized role, redirect to unauthorized page
        header("Location: unauthorized.php");
        exit();
    }

    // Get filters from query params (same as in labour_attendance.php)
    $fromDateFilter = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d', strtotime('-7 days'));
    $toDateFilter = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $eventFilter = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    $vendorFilter = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
    $labourTypeFilter = isset($_GET['labour_type']) ? $_GET['labour_type'] : 'all';
    $labourNameFilter = isset($_GET['labour_name']) ? $_GET['labour_name'] : '';
    $siteTitle = '';

    logError("Filters initialized: from=$fromDateFilter, to=$toDateFilter, status=$statusFilter, event=$eventFilter, vendor=$vendorFilter, type=$labourTypeFilter, labour_name=$labourNameFilter");

    // Check if required tables exist
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_calendar_events'";
    $stmt = $pdo->query($tableCheckQuery);
    $tableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tableCheck || $tableCheck['table_exists'] == 0) {
        throw new Exception("Required tables not found in database. Please run the setup script first.");
    }

    // Get the site title if an event is selected
    if ($eventFilter > 0) {
        $siteQuery = "SELECT title FROM sv_calendar_events WHERE event_id = :event_id LIMIT 1";
        $stmt = $pdo->prepare($siteQuery);
        $stmt->execute([':event_id' => $eventFilter]);
        $siteResult = $stmt->fetch();
        if ($siteResult) {
            $siteTitle = $siteResult['title'];
        }
    }

    // Initialize arrays
    $companyLabours = [];
    $vendorLabours = [];

    // Check if company labours table exists
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_company_labours'";
    $stmt = $pdo->query($tableCheckQuery);
    $companyTableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Only fetch company labours if the table exists
    if ($companyTableCheck && $companyTableCheck['table_exists'] > 0) {
        // Check if is_deleted column exists
        $columnCheckQuery = "SELECT COUNT(*) AS column_exists FROM information_schema.columns 
                            WHERE table_schema = DATABASE() 
                            AND table_name = 'sv_company_labours' 
                            AND column_name = 'is_deleted'";
        $stmt = $pdo->query($columnCheckQuery);
        $columnCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        $isDeletedExists = ($columnCheck && $columnCheck['column_exists'] > 0);
        
        // Fetch company labours
        $companyLaboursQuery = "
            SELECT 
                cl.*,
                ce.title as event_name
            FROM 
                sv_company_labours cl
            LEFT JOIN 
                sv_calendar_events ce ON cl.event_id = ce.event_id
            WHERE 1=1
        ";
        
        // Add is_deleted check only if the column exists
        if ($isDeletedExists) {
            $companyLaboursQuery .= " AND cl.is_deleted = 0";
        }

        // Apply filters for company labours
        $params = [];
        if ($eventFilter > 0 && !empty($siteTitle)) {
            $companyLaboursQuery .= " AND ce.title = :site_title";
            $params[':site_title'] = $siteTitle;
        } elseif ($eventFilter > 0) {
            $companyLaboursQuery .= " AND cl.event_id = :event_id";
            $params[':event_id'] = $eventFilter;
        }

        // Apply date range filters
        if ($fromDateFilter && $toDateFilter) {
            $companyLaboursQuery .= " AND cl.attendance_date BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $fromDateFilter;
            $params[':to_date'] = $toDateFilter;
        }

        if ($statusFilter !== 'all') {
            $companyLaboursQuery .= " AND (cl.morning_attendance = :status OR cl.evening_attendance = :status)";
            $params[':status'] = $statusFilter;
        }
        
        // Apply labour name filter if specified
        if (!empty($labourNameFilter)) {
            $companyLaboursQuery .= " AND cl.labour_name = :labour_name";
            $params[':labour_name'] = $labourNameFilter;
        }

        $companyLaboursQuery .= " ORDER BY cl.attendance_date DESC, cl.sequence_number, cl.labour_name";

        $stmt = $pdo->prepare($companyLaboursQuery);
        $stmt->execute($params);
        $companyLabours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logError("Company labours fetched: " . count($companyLabours));
    }

    // Check if vendor labours table exists
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_vendor_labours'";
    $stmt = $pdo->query($tableCheckQuery);
    $vendorTableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Only fetch vendor labours if the table exists
    if ($vendorTableCheck && $vendorTableCheck['table_exists'] > 0) {
        // Check if is_deleted column exists
        $columnCheckQuery = "SELECT COUNT(*) AS column_exists FROM information_schema.columns 
                            WHERE table_schema = DATABASE() 
                            AND table_name = 'sv_vendor_labours' 
                            AND column_name = 'is_deleted'";
        $stmt = $pdo->query($columnCheckQuery);
        $columnCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        $isDeletedExists = ($columnCheck && $columnCheck['column_exists'] > 0);
        
        // Fetch vendor labours
        $vendorLaboursQuery = "
            SELECT 
                vl.*,
                ev.vendor_name,
                ev.vendor_type,
                ce.title as event_name
            FROM 
                sv_vendor_labours vl
            LEFT JOIN 
                sv_event_vendors ev ON vl.vendor_id = ev.vendor_id
            LEFT JOIN 
                sv_calendar_events ce ON ev.event_id = ce.event_id
            WHERE 1=1
        ";
        
        // Add is_deleted check only if the column exists
        if ($isDeletedExists) {
            $vendorLaboursQuery .= " AND vl.is_deleted = 0";
        }

        // Apply filters for vendor labours
        $vendorParams = [];
        if ($vendorFilter > 0) {
            $vendorLaboursQuery .= " AND vl.vendor_id = :vendor_id";
            $vendorParams[':vendor_id'] = $vendorFilter;
        } elseif ($eventFilter > 0 && !empty($siteTitle)) {
            $vendorLaboursQuery .= " AND ce.title = :site_title";
            $vendorParams[':site_title'] = $siteTitle;
        }

        // Apply date range filters
        if ($fromDateFilter && $toDateFilter) {
            $vendorLaboursQuery .= " AND vl.attendance_date BETWEEN :from_date AND :to_date";
            $vendorParams[':from_date'] = $fromDateFilter;
            $vendorParams[':to_date'] = $toDateFilter;
        }

        if ($statusFilter !== 'all') {
            $vendorLaboursQuery .= " AND (vl.morning_attendance = :status OR vl.evening_attendance = :status)";
            $vendorParams[':status'] = $statusFilter;
        }
        
        // Apply labour name filter if specified
        if (!empty($labourNameFilter)) {
            $vendorLaboursQuery .= " AND vl.labour_name = :labour_name";
            $vendorParams[':labour_name'] = $labourNameFilter;
        }

        $vendorLaboursQuery .= " ORDER BY vl.attendance_date DESC, ev.vendor_name, vl.sequence_number, vl.labour_name";

        $stmt = $pdo->prepare($vendorLaboursQuery);
        $stmt->execute($vendorParams);
        $vendorLabours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logError("Vendor labours fetched: " . count($vendorLabours));
    }

    // Combine both types of labours based on labour type filter
    if ($labourTypeFilter === 'all') {
        $allLabours = array_merge($companyLabours, $vendorLabours);
    } elseif ($labourTypeFilter === 'company') {
        $allLabours = $companyLabours;
    } elseif ($labourTypeFilter === 'vendor') {
        $allLabours = $vendorLabours;
    } else {
        $allLabours = [];
    }
    logError("Combined labours: " . count($allLabours));

    // Set filename
    $filename = "labour_attendance_" . date('Y-m-d');

    // Add date range to filename if specified
    if ($fromDateFilter && $toDateFilter) {
        $fromDate = date('d-M-Y', strtotime($fromDateFilter));
        $toDate = date('d-M-Y', strtotime($toDateFilter));
        $filename = "labour_attendance_{$fromDate}_to_{$toDate}";
    }
    
    // Add labour name to filename if specified
    if (!empty($labourNameFilter)) {
        $filename .= "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $labourNameFilter);
    }

    logError("Filename set: $filename");

    // Format dates for display
    $fromDateFormatted = date('d-M-Y', strtotime($fromDateFilter));
    $toDateFormatted = date('d-M-Y', strtotime($toDateFilter));

    // Generate HTML for Excel
    $html = '
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Labour Attendance</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                            <x:AutoFilter/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            body {
                font-family: Arial, sans-serif;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                border: 2px solid #4361ee;
            }
            td, th {
                vertical-align: middle;
                padding: 8px;
                border: 1px solid #bdc3c7;
                text-align: left;
                font-size: 12pt;
            }
            .title {
                background-color: #3a0ca3;
                color: white;
                font-weight: bold;
                font-size: 18pt;
                text-align: center;
                padding: 12px;
                border: 2px solid #3a0ca3;
            }
            .company-name {
                background-color: #4361ee;
                color: white;
                font-weight: bold;
                font-size: 14pt;
                text-align: center;
                padding: 10px;
            }
            .subtitle {
                background-color: #f8f9fa;
                font-weight: bold;
                padding: 10px;
                border: 1px solid #bdc3c7;
                font-size: 13pt;
            }
            .header {
                background-color: #4cc9f0;
                color: #000000;
                font-weight: bold;
                border: 1px solid #3a86ff;
                text-align: center;
                white-space: nowrap;
            }
            .color-coding {
                background-color: #f8f9fa;
                padding: 8px;
                font-size: 11pt;
            }
            .company-labour {
                color: #0077b6;
                font-weight: bold;
            }
            .vendor-labour {
                color: #e63946;
                font-weight: bold;
            }
            .status-present {
                color: #06d6a0;
                font-weight: bold;
                background-color: #e8fff3;
            }
            .status-absent {
                color: #e63946;
                font-weight: bold;
                background-color: #ffeeee;
            }
            .status-late {
                color: #f77f00;
                font-weight: bold;
                background-color: #fff5e6;
            }
            .company-row {
                background-color: #e9f6ff;
            }
            .vendor-row {
                background-color: #fff0f5;
            }
            .currency {
                text-align: right;
                color: #1a535c;
                font-weight: bold;
                white-space: nowrap;
            }
            .date-cell {
                white-space: nowrap;
                font-weight: 500;
            }
            .name-cell {
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <table border="1" cellpadding="5" cellspacing="0" width="100%">
            <tr>
                <td colspan="10" class="title">Labour Attendance Report</td>
            </tr>
            <tr>
                <td colspan="10" class="company-name">Construction HR Management System</td>
            </tr>
            <tr>
                <td colspan="10" class="subtitle">Date Range: ' . $fromDateFormatted . ' to ' . $toDateFormatted . 
                (!empty($labourNameFilter) ? ' | Labour: ' . htmlspecialchars($labourNameFilter) : '') . '</td>
            </tr>
            <tr>
                <td colspan="10" class="color-coding">
                    <div style="display: flex; justify-content: space-between;">
                        <span><b>Color Coding:</b> <span class="company-labour">■ Company Labour</span> | <span class="vendor-labour">■ Vendor Labour</span></span>
                        <span><b>Status:</b> <span class="status-present">Present</span> | <span class="status-absent">Absent</span> | <span class="status-late">Late</span></span>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="header">Date</td>
                <td class="header">ID</td>
                <td class="header">Name</td>
                <td class="header">Contact</td>
                <td class="header">Type</td>
                <td class="header">Site</td>
                <td class="header">Vendor</td>
                <td class="header">Morning</td>
                <td class="header">Evening</td>
                <td class="header">Wages</td>
            </tr>';

    // Add data rows
    if (empty($allLabours)) {
        $html .= '<tr><td colspan="10" style="text-align: center;">No records found for the selected filters.</td></tr>';
    } else {
        foreach ($allLabours as $index => $labour) {
            // Determine labour type
            $isCompany = isset($labour['company_labour_id']);
            $labourType = $isCompany ? 'Company Labour' : 'Vendor Labour';
            $rowClass = $isCompany ? 'company-row' : 'vendor-row';
            $typeClass = $isCompany ? 'company-labour' : 'vendor-labour';
            
            // Calculate wage based on attendance status
            $wageAmount = 0;
            $dailyWage = isset($labour['daily_wage']) ? $labour['daily_wage'] : 
                        (isset($labour['wage_rate']) ? $labour['wage_rate'] : 0);
            
            if (isset($labour['morning_attendance']) && isset($labour['evening_attendance'])) {
                if ($labour['morning_attendance'] === 'present' && $labour['evening_attendance'] === 'present') {
                    $wageAmount = $dailyWage; // Full day
                } elseif ($labour['morning_attendance'] === 'present' || $labour['evening_attendance'] === 'present') {
                    $wageAmount = $dailyWage / 2; // Half day
                } elseif ($labour['morning_attendance'] === 'late' && $labour['evening_attendance'] === 'present') {
                    $wageAmount = $dailyWage * 0.75; // 3/4 day for late morning but present evening
                } elseif ($labour['morning_attendance'] === 'present' && $labour['evening_attendance'] === 'late') {
                    $wageAmount = $dailyWage * 0.75; // 3/4 day for present morning but late evening
                } elseif ($labour['morning_attendance'] === 'late' || $labour['evening_attendance'] === 'late') {
                    $wageAmount = $dailyWage * 0.5; // Half day for only late in one session
                }
            }
            
            // Format morning attendance status
            $morningStatus = isset($labour['morning_attendance']) ? ucfirst($labour['morning_attendance']) : 'N/A';
            $morningClass = '';
            if ($morningStatus === 'Present') {
                $morningClass = 'status-present';
            } elseif ($morningStatus === 'Absent') {
                $morningClass = 'status-absent';
            } elseif ($morningStatus === 'Late') {
                $morningClass = 'status-late';
            }
            
            // Format evening attendance status
            $eveningStatus = isset($labour['evening_attendance']) ? ucfirst($labour['evening_attendance']) : 'N/A';
            $eveningClass = '';
            if ($eveningStatus === 'Present') {
                $eveningClass = 'status-present';
            } elseif ($eveningStatus === 'Absent') {
                $eveningClass = 'status-absent';
            } elseif ($eveningStatus === 'Late') {
                $eveningClass = 'status-late';
            }
            
            // Format date for display
            $attendanceDate = isset($labour['attendance_date']) ? date('d-M-Y', strtotime($labour['attendance_date'])) : 'N/A';
            
            // Add table row
            $html .= '<tr class="' . $rowClass . '">
                <td class="date-cell">' . $attendanceDate . '</td>
                <td>' . ($index + 1) . '</td>
                <td class="name-cell">' . htmlspecialchars($labour['labour_name'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($labour['contact_number'] ?? 'N/A') . '</td>
                <td class="' . $typeClass . '">' . $labourType . '</td>
                <td>' . htmlspecialchars($labour['event_name'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($labour['vendor_name'] ?? 'N/A') . '</td>
                <td class="' . $morningClass . '">' . $morningStatus . '</td>
                <td class="' . $eveningClass . '">' . $eveningStatus . '</td>
                <td class="currency">₹ ' . number_format($wageAmount, 2) . '</td>
            </tr>';
        }
    }

    // Close the HTML document
    $html .= '</table></body></html>';

    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output the Excel content
    echo $html;
    logError("Export completed successfully");
    exit(); // End script after output
    
} catch (Exception $e) {
    // Log error
    logError("Export error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Redirect back to the labour attendance page with error
    header("Location: labour_attendance.php?export_error=1");
    exit();
} 