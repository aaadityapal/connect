<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="inventory_export_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Get filter parameters from URL
$selected_site = isset($_GET['site']) ? $_GET['site'] : '';
$inventory_type = isset($_GET['inventory_type']) ? $_GET['inventory_type'] : 'all';
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : 0;

// Fetch inventory items based on filters
$inventory_query = "SELECT i.*, e.title as site_name, e.event_date 
                    FROM sv_inventory_items i
                    JOIN sv_calendar_events e ON i.event_id = e.event_id
                    WHERE 1=1";

$params = [];

if (!empty($selected_site)) {
    $inventory_query .= " AND e.title = ?";
    $params[] = $selected_site;
}

if ($inventory_type != 'all') {
    $inventory_query .= " AND i.inventory_type = ?";
    $params[] = $inventory_type;
}

// Add month and year filters
if ($selected_month > 0) {
    $inventory_query .= " AND MONTH(i.created_at) = ?";
    $params[] = $selected_month;
}

if ($selected_year > 0) {
    $inventory_query .= " AND YEAR(i.created_at) = ?";
    $params[] = $selected_year;
}

$inventory_query .= " ORDER BY i.created_at DESC";
$inventory_stmt = $pdo->prepare($inventory_query);
$inventory_stmt->execute($params);
$inventory_items = $inventory_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create material stats summary
$material_stats = [];

foreach ($inventory_items as $item) {
    // Group materials by type to combine quantities
    $material_type = $item['material_type'];
    if (!isset($material_stats[$material_type])) {
        $material_stats[$material_type] = [
            'received' => 0,
            'consumed' => 0,
            'other' => 0,
            'total' => 0,
            'unit' => $item['unit'],
            'latest_date' => $item['created_at']
        ];
    }
    
    if ($item['inventory_type'] == 'received') {
        $material_stats[$material_type]['received'] += floatval($item['quantity']);
    } else if ($item['inventory_type'] == 'consumed') {
        $material_stats[$material_type]['consumed'] += floatval($item['quantity']);
    } else {
        $material_stats[$material_type]['other'] += floatval($item['quantity']);
    }
    
    $material_stats[$material_type]['total'] += floatval($item['quantity']);
    
    // Update latest date if this item is newer
    if (strtotime($item['created_at']) > strtotime($material_stats[$material_type]['latest_date'])) {
        $material_stats[$material_type]['latest_date'] = $item['created_at'];
    }
}

// Output Excel content
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Inventory Export</x:Name>
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
        }
        th, td {
            border: 1px solid #000000;
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .summary-table {
            margin-bottom: 20px;
        }
        h2 {
            font-size: 18px;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .received {
            background-color: #CCFFCC; /* Light green */
        }
        .consumed {
            background-color: #FFCCCC; /* Light red */
        }
        .positive-balance {
            color: #006600; /* Dark green */
            font-weight: bold;
        }
        .negative-balance {
            color: #990000; /* Dark red */
            font-weight: bold;
        }
        .zero-balance {
            color: #666666; /* Gray */
        }
    </style>
</head>
<body>
    <h2>Material Summary</h2>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Latest Date</th>
                <th>Material Type</th>
                <th>Received</th>
                <th>Consumed</th>
                <th>Balance</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($material_stats as $material_type => $stats): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($stats['latest_date'])); ?></td>
                    <td><?php echo htmlspecialchars($material_type); ?></td>
                    <td><?php echo number_format($stats['received'], 2); ?></td>
                    <td><?php echo number_format($stats['consumed'], 2); ?></td>
                    <?php 
                    $balance = $stats['received'] - $stats['consumed'];
                    $balance_class = 'zero-balance';
                    if ($balance > 0) {
                        $balance_class = 'positive-balance';
                    } elseif ($balance < 0) {
                        $balance_class = 'negative-balance';
                    }
                    ?>
                    <td class="<?php echo $balance_class; ?>"><?php echo number_format($balance, 2); ?></td>
                    <td><?php echo htmlspecialchars($stats['unit']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Inventory Items</h2>
    <table>
        <thead>
            <tr>
                <th>Date Added</th>
                <th>Site</th>
                <th>Material Type</th>
                <th>Inventory Type</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inventory_items as $item): ?>
                <tr class="<?php echo $item['inventory_type']; ?>">
                    <td><?php echo date('d M Y', strtotime($item['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($item['site_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['material_type']); ?></td>
                    <td><?php echo ucfirst(htmlspecialchars($item['inventory_type'])); ?></td>
                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                    <td><?php echo htmlspecialchars($item['remarks']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html> 