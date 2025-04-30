<?php
session_start();
require_once 'config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate incoming parameters
if (!isset($_GET['site_name']) || !isset($_GET['date'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$site_name = $conn->real_escape_string($_GET['site_name']);
$update_date = $conn->real_escape_string($_GET['date']);

// Convert display date format (d M Y) to database format (Y-m-d)
$date_obj = DateTime::createFromFormat('d M Y', $update_date);
$db_date = $date_obj ? $date_obj->format('Y-m-d') : '';

if (empty($db_date)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Get the site update ID
$update_query = "SELECT * FROM site_updates WHERE user_id = ? AND site_name = ? AND update_date = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("iss", $user_id, $site_name, $db_date);
$stmt->execute();
$update_result = $stmt->get_result();

if ($update_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Site update not found']);
    exit();
}

$update_data = $update_result->fetch_assoc();
$site_update_id = $update_data['id'];

// Set headers for Excel HTML file
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $site_name . '_' . $update_date . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Start HTML Output
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Site Update</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 10px;
        }
        th, td {
            border: 2px solid #000;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 11pt;
        }
        th {
            background-color: #4472C4;
            color: white;
            font-weight: 900;
            font-size: 14pt;
            text-transform: uppercase;
        }
        .section-header {
            font-size: 16pt;
            font-weight: 900;
            background-color: #1F4E78;
            color: white;
            padding: 15px;
            margin-top: 20px;
            text-transform: uppercase;
            border: 3px solid #000;
            text-align: center;
        }
        .subsection-header {
            font-size: 14pt;
            font-weight: 900;
            background-color: #8EAADB;
            color: #000;
            padding: 10px;
            text-transform: uppercase;
            border: 2px solid #000;
            text-align: center;
        }
        .site-info {
            margin-bottom: 20px;
        }
        .site-info td:first-child {
            font-weight: 900;
            width: 20%;
            background-color: #D9E1F2;
            font-size: 12pt;
        }
        .site-info td:last-child {
            font-weight: bold;
            font-size: 12pt;
        }
        tr.total-row td {
            font-weight: 900;
            font-size: 14pt;
            background-color: #FFEB9C;
        }
        tr.grand-total td {
            font-weight: 900;
            font-size: 16pt;
            background-color: #FFD966;
            color: #C00000;
        }
    </style>
</head>
<body>
    <!-- Site Information -->
    <div class="section-header">Site Update Details</div>
    <table class="site-info">
        <tr>
            <td>Site Name:</td>
            <td><?php echo htmlspecialchars($site_name); ?></td>
        </tr>
        <tr>
            <td>Date:</td>
            <td><?php echo htmlspecialchars($update_date); ?></td>
        </tr>
        <tr>
            <td>Update Details:</td>
            <td><?php echo htmlspecialchars($update_data['update_details'] ?? ''); ?></td>
        </tr>
    </table>

    <!-- Vendors Information -->
    <div class="section-header">Vendors Information</div>
    <table>
        <tr>
            <th>Vendor Type</th>
            <th>Vendor Name</th>
            <th>Contact</th>
            <th>Work Description</th>
        </tr>
        <?php
        $vendors_query = "SELECT * FROM site_vendors WHERE site_update_id = ?";
        $stmt = $conn->prepare($vendors_query);
        $stmt->bind_param("i", $site_update_id);
        $stmt->execute();
        $vendors_result = $stmt->get_result();

        if ($vendors_result->num_rows === 0) {
            echo "<tr><td colspan='4'>No vendors found</td></tr>";
        } else {
            while ($vendor = $vendors_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($vendor['vendor_type']) . "</td>";
                echo "<td>" . htmlspecialchars($vendor['vendor_name']) . "</td>";
                echo "<td>" . htmlspecialchars($vendor['contact']) . "</td>";
                echo "<td>" . htmlspecialchars($vendor['work_description']) . "</td>";
                echo "</tr>";

                $vendor_id = $vendor['id'];
                
                // Get labours for this vendor
                $labours_query = "SELECT * FROM vendor_labours WHERE vendor_id = ?";
                $stmt_labours = $conn->prepare($labours_query);
                $stmt_labours->bind_param("i", $vendor_id);
                $stmt_labours->execute();
                $labours_result = $stmt_labours->get_result();
                
                if ($labours_result->num_rows > 0) {
                    echo "</table>";
                    echo "<div class='subsection-header'>Labour Details for Vendor: " . htmlspecialchars($vendor['vendor_name']) . "</div>";
                    echo "<table>";
                    echo "<tr>";
                    echo "<th>Labour Name</th>";
                    echo "<th>Mobile</th>";
                    echo "<th>Attendance</th>";
                    echo "<th>OT Hours</th>";
                    echo "<th>Wage (₹)</th>";
                    echo "<th>OT Amount (₹)</th>";
                    echo "<th>Total (₹)</th>";
                    echo "</tr>";
                    
                    while ($labour = $labours_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($labour['labour_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($labour['mobile']) . "</td>";
                        echo "<td>" . htmlspecialchars($labour['attendance']) . "</td>";
                        echo "<td>" . htmlspecialchars($labour['ot_hours']) . "</td>";
                        echo "<td>" . htmlspecialchars($labour['wage']) . "</td>";
                        echo "<td>" . htmlspecialchars($labour['ot_amount']) . "</td>";
                        echo "<td>" . htmlspecialchars($labour['total_amount']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table><br>";
                    echo "<table>"; // Start a new table for the next vendor
                }
            }
        }
        ?>
    </table>

    <!-- Company Labours Section -->
    <div class="section-header">Company Labours</div>
    <table>
        <tr>
            <th>Labour Name</th>
            <th>Mobile</th>
            <th>Attendance</th>
            <th>OT Hours</th>
            <th>Wage (₹)</th>
            <th>OT Amount (₹)</th>
            <th>Total (₹)</th>
        </tr>
        <?php
        $company_labours_query = "SELECT * FROM company_labours WHERE site_update_id = ?";
        $stmt = $conn->prepare($company_labours_query);
        $stmt->bind_param("i", $site_update_id);
        $stmt->execute();
        $company_labours_result = $stmt->get_result();

        if ($company_labours_result->num_rows === 0) {
            echo "<tr><td colspan='7'>No company labours found</td></tr>";
        } else {
            while ($labour = $company_labours_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($labour['labour_name']) . "</td>";
                echo "<td>" . htmlspecialchars($labour['mobile']) . "</td>";
                echo "<td>" . htmlspecialchars($labour['attendance']) . "</td>";
                echo "<td>" . htmlspecialchars($labour['ot_hours']) . "</td>";
                echo "<td>" . htmlspecialchars($labour['wage']) . "</td>";
                echo "<td>" . htmlspecialchars($labour['ot_amount']) . "</td>";
                echo "<td>" . htmlspecialchars($labour['total_amount']) . "</td>";
                echo "</tr>";
            }
        }
        ?>
    </table>

    <!-- Work Progress Section -->
    <div class="section-header">Work Progress</div>
    <table>
        <tr>
            <th>Work Type</th>
            <th>Status</th>
            <th>Category</th>
            <th>Remarks</th>
        </tr>
        <?php
        $work_progress_query = "SELECT * FROM work_progress WHERE site_update_id = ?";
        $stmt = $conn->prepare($work_progress_query);
        $stmt->bind_param("i", $site_update_id);
        $stmt->execute();
        $work_progress_result = $stmt->get_result();

        if ($work_progress_result->num_rows === 0) {
            echo "<tr><td colspan='4'>No work progress records found</td></tr>";
        } else {
            while ($progress = $work_progress_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($progress['work_type']) . "</td>";
                echo "<td>" . htmlspecialchars($progress['status']) . "</td>";
                echo "<td>" . htmlspecialchars($progress['category']) . "</td>";
                echo "<td>" . htmlspecialchars($progress['remarks']) . "</td>";
                echo "</tr>";
            }
        }
        ?>
    </table>

    <!-- Inventory Section -->
    <div class="section-header">Inventory</div>
    <table>
        <tr>
            <th>Material</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Standard Values/Notes</th>
        </tr>
        <?php
        $inventory_query = "SELECT * FROM inventory WHERE site_update_id = ?";
        $stmt = $conn->prepare($inventory_query);
        $stmt->bind_param("i", $site_update_id);
        $stmt->execute();
        $inventory_result = $stmt->get_result();

        if ($inventory_result->num_rows === 0) {
            echo "<tr><td colspan='4'>No inventory records found</td></tr>";
        } else {
            while ($item = $inventory_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($item['material']) . "</td>";
                echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
                echo "<td>" . htmlspecialchars($item['standard_values']) . "</td>";
                echo "</tr>";
            }
        }
        ?>
    </table>

    <!-- Expense Summary Section -->
    <div class="section-header">Expense Summary</div>
    <table>
        <tr>
            <th>Type</th>
            <th>Amount (₹)</th>
        </tr>
        <tr class="total-row">
            <td>Total Wages</td>
            <td><?php echo htmlspecialchars($update_data['total_wages']); ?></td>
        </tr>
        <tr class="total-row">
            <td>Total Miscellaneous Expenses</td>
            <td><?php echo htmlspecialchars($update_data['total_misc_expenses']); ?></td>
        </tr>
        <tr class="grand-total">
            <td>Grand Total</td>
            <td><?php echo htmlspecialchars($update_data['grand_total']); ?></td>
        </tr>
    </table>
</body>
</html>
<?php exit(); ?>