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

// Get user info for display
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get unique sites list (grouped by title)
$sites_query = "SELECT 
                    MIN(event_id) as event_id,
                    title,
                    COUNT(event_id) as event_count,
                    MAX(event_date) as latest_date
                FROM 
                    sv_calendar_events 
                GROUP BY 
                    title 
                ORDER BY 
                    latest_date DESC";
$sites_stmt = $pdo->prepare($sites_query);
$sites_stmt->execute();
$sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);

// Default filter values
$selected_site = isset($_GET['site']) ? $_GET['site'] : (count($sites) > 0 ? $sites[0]['title'] : '');
$inventory_type = isset($_GET['inventory_type']) ? $_GET['inventory_type'] : 'all';
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : 0; // 0 means all months
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y'); // Current year as default

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

// Grouped statistics by material type
$material_stats = [];
$total_items = count($inventory_items);
$received_items = 0;
$consumed_items = 0;
$other_items = 0;

foreach ($inventory_items as $item) {
    // For inventory type counts
    if ($item['inventory_type'] == 'received') {
        $received_items++;
    } else if ($item['inventory_type'] == 'consumed') {
        $consumed_items++;
    } else {
        $other_items++;
    }
    
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

// Get material types for dropdown
$material_types_query = "SELECT DISTINCT material_type FROM sv_inventory_items ORDER BY material_type";
$material_types_stmt = $pdo->prepare($material_types_query);
$material_types_stmt->execute();
$material_types = $material_types_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materials Inventory</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    <link rel="stylesheet" href="css/inventory.css">
    
    <!-- Lightbox for image preview -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    
    <style>
        /* Hamburger menu for mobile */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: #1e3246;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px 14px;
            z-index: 1000;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: background-color 0.3s;
        }
        
        .mobile-menu-toggle:hover {
            background-color: #283d52;
        }
        
        .mobile-menu-toggle i {
            font-size: 1.5rem;
        }
        
        /* Folder Structure Styles */
        .folder-structure {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* List View (default) */
        .folder-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 5px;
        }
        
        .folder-item:hover {
            background-color: #f5f5f5;
        }
        
        .folder-icon {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        /* View Modes */
        /* Icons View */
        .view-icons .folder-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        
        .view-icons .folder-item {
            flex-direction: column;
            text-align: center;
            width: 120px;
            height: 120px;
            margin: 10px;
            padding: 15px 5px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .view-icons .folder-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
            background-color: #f8f9fa;
        }
        
        .view-icons .folder-icon {
            margin: 0 0 12px 0;
            font-size: 2.5rem;
            display: block;
            height: 45px;
        }
        
        .view-icons .folder-name {
            font-size: 0.85rem;
            width: 100%;
            max-height: 40px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.3;
            font-weight: 500;
            color: #495057;
            padding: 0 4px;
        }
        
        .view-icons .folder-date {
            display: none;
        }
        
        /* Tiles View */
        .view-tiles .folder-content {
            display: flex;
            flex-wrap: wrap;
        }
        
        .view-tiles .folder-item {
            width: calc(33.333% - 10px);
            margin: 5px;
            border: 1px solid #e9ecef;
        }
        
        .view-tiles .folder-icon {
            font-size: 1.5rem;
        }
        
        .view-tiles .folder-date {
            margin-left: auto;
            padding-left: 10px;
        }
        
        /* Folder Icon Colors */
        .folder-icon.folder {
            color: #ffc107;
        }
        
        .folder-icon.file-image {
            color: #28a745;
        }
        
        .folder-icon.file-pdf {
            color: #dc3545;
        }
        
        .folder-icon.file-video {
            color: #17a2b8;
        }
        
        .folder-name {
            flex-grow: 1;
        }
        
        .folder-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .breadcrumb-item {
            cursor: pointer;
        }
        
        .file-preview {
            text-align: center;
            padding: 10px;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 300px;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .left-panel {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .left-panel.mobile-visible {
                transform: translateX(0);
            }
            
            /* Hide the regular toggle button on mobile */
            .toggle-btn {
                display: none;
            }
        }
        
        /* View Modes - Responsive */
        /* Base responsive settings */
        @media (max-width: 991px) {
            .view-icons .folder-item {
                width: 110px;
                height: 110px;
                margin: 8px;
            }
            
            .view-icons .folder-icon {
                font-size: 2.2rem;
                margin-bottom: 8px;
            }
            
            .view-tiles .folder-item {
                width: calc(50% - 10px);
            }
        }
        
        @media (max-width: 767px) {
            .view-icons .folder-content {
                justify-content: center;
            }
            
            .view-icons .folder-item {
                width: 100px;
                height: 100px;
                margin: 6px;
                padding: 12px 4px;
            }
            
            .view-icons .folder-icon {
                font-size: 2rem;
                margin-bottom: 6px;
                height: 40px;
            }
            
            .view-icons .folder-name {
                font-size: 0.8rem;
            }
            
            .view-tiles .folder-item {
                width: 100%;
                margin: 5px 0;
            }
            
            .folder-item {
                padding: 8px 10px;
            }
        }
        
        @media (max-width: 480px) {
            .view-icons .folder-content {
                justify-content: space-between;
            }
            
            .view-icons .folder-item {
                width: calc(50% - 8px);
                height: auto;
                min-height: 90px;
                margin: 4px;
                padding: 10px 4px;
            }
            
            .view-options .btn-group {
                width: 100%;
                display: flex;
            }
            
            .view-options .btn {
                flex: 1;
                padding: 6px 4px;
            }
            
            .view-options .btn i {
                margin-right: 0;
            }
            
            .folder-navigation .breadcrumb {
                flex-wrap: nowrap;
                overflow-x: auto;
                white-space: nowrap;
                padding: 8px;
            }
            
            .folder-navigation .breadcrumb-item {
                float: none;
                display: inline-block;
            }
        }
        
        /* Fix for very small screens */
        @media (max-width: 320px) {
            .view-icons .folder-item {
                width: 100%;
                height: auto;
                min-height: 80px;
                margin: 4px 0;
                padding: 8px 4px;
                flex-direction: row;
                text-align: left;
            }
            
            .view-icons .folder-icon {
                margin: 0 10px 0 0;
                font-size: 1.8rem;
            }
            
            .view-icons .folder-name {
                text-align: left;
                padding-top: 10px;
            }
        }
        
        /* Ensure text readability across all devices */
        .folder-name {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            word-break: break-word;
        }
        
        .folder-item:hover .folder-name {
            color: #007bff;
        }
        
        /* File actions styling */
        .file-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #f8f9fa;
            color: #495057;
            margin-left: 5px;
            text-decoration: none;
            border: 1px solid #dee2e6;
            opacity: 0;
            transition: opacity 0.2s, transform 0.2s, background-color 0.2s;
        }
        
        .folder-item:hover .file-action {
            opacity: 1;
        }
        
        .file-action:hover {
            background-color: #007bff;
            color: white;
            transform: scale(1.1);
            text-decoration: none;
        }
        
        /* List view file actions */
        .folder-item .file-action {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .folder-item .file-action:last-of-type {
            right: 10px;
        }
        
        .folder-item {
            position: relative;
        }
        
        /* Icons view file actions */
        .view-icons .folder-item .file-action {
            position: absolute;
            right: 10px;
            top: 10px;
            transform: none;
            z-index: 2;
        }
        
        .view-icons .folder-item .file-action:last-of-type {
            right: 10px;
            top: 45px;
        }
        
        /* Tiles view file actions */
        .view-tiles .folder-item .file-action {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .view-tiles .folder-item .file-action:last-of-type {
            right: 45px;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Include Left Panel based on role -->
    <?php 
    if ($_SESSION['role'] == 'Site Supervisor') {
        include 'includes/supervisor_panel.php';
    } elseif ($_SESSION['role'] == 'Admin') {
        include 'includes/admin_panel.php';
    } else {
        include 'includes/worker_panel.php';
    }
    ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card header-card">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                            <div class="header-title mb-3 mb-md-0">
                                <div class="d-flex align-items-center">
                                    <div class="header-icon">
                                        <i class="fas fa-warehouse"></i>
                                    </div>
                                    <div>
                                        <h2>Materials Inventory</h2>
                                        <p class="text-muted mb-0">Manage site inventory - received, consumed, and remaining items</p>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInventoryModal">
                                    <i class="fas fa-plus-circle mr-1"></i> Add Inventory Item
                                </button>
                                <a href="export_inventory.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-success ml-2">
                                    <i class="fas fa-file-excel mr-1"></i> Export to Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h4 class="card-title">
                            <i class="fas fa-filter mr-2"></i> Filters
                        </h4>
                        <form id="inventoryFilterForm" method="GET" action="materials_inventory.php">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="siteSelect" class="form-label filter-label">Select Site</label>
                                    <select name="site" id="siteSelect" class="form-control custom-select">
                                        <option value="">All Sites</option>
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?php echo htmlspecialchars($site['title']); ?>" <?php echo ($selected_site == $site['title']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($site['title']); ?> 
                                                <?php if ($site['event_count'] > 1): ?>
                                                    <small>(<?php echo $site['event_count']; ?> events)</small>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="inventoryTypeSelect" class="form-label filter-label">Inventory Type</label>
                                    <select name="inventory_type" id="inventoryTypeSelect" class="form-control custom-select">
                                        <option value="all" <?php echo ($inventory_type == 'all') ? 'selected' : ''; ?>>All Types</option>
                                        <option value="received" <?php echo ($inventory_type == 'received') ? 'selected' : ''; ?>>Received</option>
                                        <option value="consumed" <?php echo ($inventory_type == 'consumed') ? 'selected' : ''; ?>>Consumed</option>
                                        <option value="other" <?php echo ($inventory_type == 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="monthSelect" class="form-label filter-label">Month</label>
                                    <select name="month" id="monthSelect" class="form-control custom-select">
                                        <option value="0" <?php echo ($selected_month == 0) ? 'selected' : ''; ?>>All Months</option>
                                        <option value="1" <?php echo ($selected_month == 1) ? 'selected' : ''; ?>>January</option>
                                        <option value="2" <?php echo ($selected_month == 2) ? 'selected' : ''; ?>>February</option>
                                        <option value="3" <?php echo ($selected_month == 3) ? 'selected' : ''; ?>>March</option>
                                        <option value="4" <?php echo ($selected_month == 4) ? 'selected' : ''; ?>>April</option>
                                        <option value="5" <?php echo ($selected_month == 5) ? 'selected' : ''; ?>>May</option>
                                        <option value="6" <?php echo ($selected_month == 6) ? 'selected' : ''; ?>>June</option>
                                        <option value="7" <?php echo ($selected_month == 7) ? 'selected' : ''; ?>>July</option>
                                        <option value="8" <?php echo ($selected_month == 8) ? 'selected' : ''; ?>>August</option>
                                        <option value="9" <?php echo ($selected_month == 9) ? 'selected' : ''; ?>>September</option>
                                        <option value="10" <?php echo ($selected_month == 10) ? 'selected' : ''; ?>>October</option>
                                        <option value="11" <?php echo ($selected_month == 11) ? 'selected' : ''; ?>>November</option>
                                        <option value="12" <?php echo ($selected_month == 12) ? 'selected' : ''; ?>>December</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="yearSelect" class="form-label filter-label">Year</label>
                                    <select name="year" id="yearSelect" class="form-control custom-select">
                                        <option value="0" <?php echo ($selected_year == 0) ? 'selected' : ''; ?>>All Years</option>
                                        <?php
                                        $current_year = date('Y');
                                        for ($year = $current_year; $year >= $current_year - 5; $year--) {
                                            echo '<option value="' . $year . '" ' . ($selected_year == $year ? 'selected' : '') . '>' . $year . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary filter-btn">
                                        <i class="fas fa-search mr-1"></i> Apply Filters
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary ml-2" onclick="window.location.href='materials_inventory.php'">
                                        <i class="fas fa-sync-alt mr-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-primary">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $total_items; ?></h3>
                            <p>Total Items</p>
                            <small>All inventory items</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-success">
                            <i class="fas fa-truck-loading"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $received_items; ?></h3>
                            <p>Received Items</p>
                            <small><?php echo round(($total_items > 0) ? ($received_items / $total_items) * 100 : 0, 1); ?>% of total</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-warning">
                            <i class="fas fa-cart-arrow-down"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $consumed_items; ?></h3>
                            <p>Consumed Items</p>
                            <small><?php echo round(($total_items > 0) ? ($consumed_items / $total_items) * 100 : 0, 1); ?>% of total</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-info">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $other_items; ?></h3>
                            <p>Other Items</p>
                            <small><?php echo round(($total_items > 0) ? ($other_items / $total_items) * 100 : 0, 1); ?>% of total</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Table Section -->
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h4 class="card-title">
                            <i class="fas fa-list mr-2"></i> Inventory Items
                            <?php if (!empty($selected_site)): ?>
                            <span class="selected-site-badge">
                                <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($selected_site); ?>
                            </span>
                            <?php endif; ?>
                        </h4>
                        
                        <?php if (!empty($material_stats)): ?>
                        <!-- Material Type Summary -->
                        <div class="material-summary mb-4">
                            <h5 class="mb-3"><i class="fas fa-chart-pie mr-2"></i> Material Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover material-stats-table">
                                    <thead>
                                        <tr>
                                            <th>Material Type</th>
                                            <th>Received</th>
                                            <th>Consumed</th>
                                            <th>Balance</th>
                                            <th>Unit</th>
                                            <th>Latest Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($material_stats as $material_type => $stats): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($material_type); ?></strong></td>
                                                <td class="text-success"><?php echo number_format($stats['received'], 2); ?></td>
                                                <td class="text-warning"><?php echo number_format($stats['consumed'], 2); ?></td>
                                                <td class="<?php echo ($stats['received'] - $stats['consumed'] > 0) ? 'text-primary' : 'text-danger'; ?>">
                                                    <strong><?php echo number_format($stats['received'] - $stats['consumed'], 2); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($stats['unit']); ?></td>
                                                <td><?php echo isset($stats['latest_date']) ? date('d M Y', strtotime($stats['latest_date'])) : ''; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-hover inventory-table">
                                <thead>
                                    <tr>
                                        <th>Site</th>
                                        <th>Material Type</th>
                                        <th>Inventory Type</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Images/Bills</th>
                                        <th>Date Added</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inventory_items)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-box-open empty-icon"></i>
                                                <p>No inventory items found for the selected filters.</p>
                                                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addInventoryModal">
                                                    <i class="fas fa-plus-circle mr-1"></i> Add First Item
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($inventory_items as $item): ?>
                                            <?php 
                                            // Fetch media files for this item
                                            $media_query = "SELECT * FROM sv_inventory_media WHERE inventory_id = ? ORDER BY sequence_number";
                                            $media_stmt = $pdo->prepare($media_query);
                                            $media_stmt->execute([$item['inventory_id']]);
                                            $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            $media_count = count($media_files);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['site_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['material_type']); ?></td>
                                                <td>
                                                    <?php
                                                    $badge_class = '';
                                                    $inventory_text = ucfirst($item['inventory_type']);
                                                    
                                                    switch ($item['inventory_type']) {
                                                        case 'received':
                                                            $badge_class = 'badge-success';
                                                            break;
                                                        case 'consumed':
                                                            $badge_class = 'badge-warning';
                                                            break;
                                                        default:
                                                            $badge_class = 'badge-info';
                                                    }
                                                    
                                                    echo '<span class="badge ' . $badge_class . '">' . $inventory_text . '</span>';
                                                    ?>
                                                </td>
                                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                                <td>
                                                    <?php if ($media_count > 0): ?>
                                                        <div class="media-preview">
                                                            <a href="#" class="view-media" data-toggle="modal" data-target="#viewMediaModal" data-inventory-id="<?php echo $item['inventory_id']; ?>">
                                                                <i class="fas fa-images mr-1"></i> 
                                                                <?php echo $media_count; ?> file<?php echo $media_count > 1 ? 's' : ''; ?>
                                                            </a>
                                                            <a href="#" class="view-folders ml-2" data-toggle="modal" data-target="#folderStructureModal" data-site="<?php echo htmlspecialchars($item['site_name']); ?>" data-date="<?php echo date('Y-m-d', strtotime($item['created_at'])); ?>">
                                                                <i class="fas fa-folder-open text-warning"></i>
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No media</span>
                                                        <a href="#" class="view-folders ml-2" data-toggle="modal" data-target="#folderStructureModal" data-site="<?php echo htmlspecialchars($item['site_name']); ?>" data-date="<?php echo date('Y-m-d', strtotime($item['created_at'])); ?>">
                                                            <i class="fas fa-folder-open text-warning"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($item['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group action-buttons">
                                                        <button type="button" class="btn btn-sm btn-outline-primary view-details" data-toggle="modal" data-target="#viewItemModal" data-inventory-id="<?php echo $item['inventory_id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-info edit-item" data-toggle="modal" data-target="#editInventoryModal" data-inventory-id="<?php echo $item['inventory_id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Inventory Modal -->
    <div class="modal fade" id="addInventoryModal" tabindex="-1" role="dialog" aria-labelledby="addInventoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addInventoryModalLabel">Add New Inventory Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addInventoryForm" action="process_inventory.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_id" class="form-label">Site/Event <span class="text-danger">*</span></label>
                                <select name="event_id" id="event_id" class="form-control" required>
                                    <option value="">Select Site/Event</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?php echo $site['event_id']; ?>">
                                            <?php echo htmlspecialchars($site['title']); ?>
                                            <?php if ($site['event_count'] > 1): ?>
                                                (<?php echo $site['event_count']; ?> events)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="inventory_type" class="form-label">Inventory Type <span class="text-danger">*</span></label>
                                <select name="inventory_type" id="inventory_type" class="form-control" required>
                                    <option value="received">Received</option>
                                    <option value="consumed">Consumed</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="material_type" class="form-label">Material Type <span class="text-danger">*</span></label>
                                <input type="text" name="material_type" id="material_type" class="form-control" list="material-types" required>
                                <datalist id="material-types">
                                    <?php foreach ($material_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="quantity" id="quantity" class="form-control" step="0.01" min="0" required>
                                    <div class="input-group-append">
                                        <input type="text" name="unit" id="unit" class="form-control" placeholder="Unit (kg, pcs, etc)" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea name="remarks" id="remarks" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Upload Images/Bills</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="media_files[]" id="mediaFiles" multiple accept="image/*,.pdf">
                                    <label class="custom-file-label" for="mediaFiles">Choose files</label>
                                </div>
                                <small class="form-text text-muted">You can upload multiple images or PDF files. Maximum 5 files, each max 5MB.</small>
                                <div class="upload-preview mt-3" id="uploadPreview"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Inventory Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Media Modal -->
    <div class="modal fade" id="viewMediaModal" tabindex="-1" role="dialog" aria-labelledby="viewMediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewMediaModalLabel">Media Files</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row" id="mediaGallery">
                        <!-- Media content will be loaded here via AJAX -->
                        <div class="col-12 text-center media-loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading media files...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Folder Structure Modal -->
    <div class="modal fade" id="folderStructureModal" tabindex="-1" role="dialog" aria-labelledby="folderStructureModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="folderStructureModalLabel">Folder Structure</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="folder-structure">
                        <div class="folder-navigation mb-3">
                            <ol class="breadcrumb bg-light" id="folderBreadcrumb">
                                <li class="breadcrumb-item active">Root</li>
                            </ol>
                        </div>
                        
                        <!-- View Filter Options -->
                        <div class="view-options mb-3">
                            <div class="btn-group btn-group-sm" role="group" aria-label="View options">
                                <button type="button" class="btn btn-outline-secondary active" data-view="list">
                                    <i class="fas fa-list"></i> List
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-view="icons">
                                    <i class="fas fa-th-large"></i> Large Icons
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-view="tiles">
                                    <i class="fas fa-th"></i> Tiles
                                </button>
                            </div>
                        </div>
                        
                        <div class="folder-content" id="folderContent">
                            <!-- Folder structure will be loaded here via JavaScript -->
                            <div class="text-center folder-loading">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Loading folder structure...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script src="js/inventory.js"></script>
    
    <script>
        // Toggle sidebar panel functionality - preserved from supervisor_panel.php
        function togglePanel() {
            const leftPanel = document.getElementById('leftPanel');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (leftPanel && mainContent) {
                leftPanel.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                if (toggleIcon) {
                    if (leftPanel.classList.contains('collapsed')) {
                        toggleIcon.classList.remove('fa-chevron-left');
                        toggleIcon.classList.add('fa-chevron-right');
                    } else {
                        toggleIcon.classList.remove('fa-chevron-right');
                        toggleIcon.classList.add('fa-chevron-left');
                    }
                }
            }
        }
        
        // Initialize when document is ready
        $(document).ready(function() {
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const leftPanel = document.getElementById('leftPanel');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (mobileMenuToggle && leftPanel) {
                mobileMenuToggle.addEventListener('click', function() {
                    leftPanel.classList.toggle('mobile-visible');
                    // Change icon based on panel state
                    const icon = this.querySelector('i');
                    if (leftPanel.classList.contains('mobile-visible')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
                
                // Close panel when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    const isClickInsidePanel = leftPanel.contains(event.target);
                    const isClickOnToggle = mobileMenuToggle.contains(event.target);
                    
                    if (!isClickInsidePanel && !isClickOnToggle && leftPanel.classList.contains('mobile-visible') && window.innerWidth <= 768) {
                        leftPanel.classList.remove('mobile-visible');
                        const icon = mobileMenuToggle.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
            }
            
            // Check if panel is already collapsed (from session storage or cookie)
            if (leftPanel && mainContent) {
                if (leftPanel.classList.contains('collapsed')) {
                    mainContent.classList.add('expanded');
                }
            }
            
            // Preview uploaded files
            $('#mediaFiles').change(function() {
                const files = this.files;
                const previewDiv = $('#uploadPreview');
                previewDiv.empty();
                
                if (files.length > 0) {
                    $('.custom-file-label').text(files.length + ' files selected');
                    
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        const reader = new FileReader();
                        
                        const fileDiv = $('<div class="upload-preview-item"></div>');
                        
                        reader.onload = function(e) {
                            if (file.type.startsWith('image/')) {
                                fileDiv.append('<img src="' + e.target.result + '" class="img-thumbnail">');
                            } else {
                                fileDiv.append('<div class="file-thumbnail"><i class="fas fa-file-pdf"></i></div>');
                            }
                            fileDiv.append('<p class="file-name">' + file.name + '</p>');
                        };
                        
                        reader.readAsDataURL(file);
                        previewDiv.append(fileDiv);
                    }
                } else {
                    $('.custom-file-label').text('Choose files');
                }
            });
            
            // Handle media view
            $('.view-media').on('click', function() {
                const inventoryId = $(this).data('inventory-id');
                $('#mediaGallery').html('<div class="col-12 text-center media-loading"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading media files...</p></div>');
                
                // AJAX call to get media files
                $.ajax({
                    url: 'get_inventory_media.php',
                    type: 'GET',
                    data: { inventory_id: inventoryId },
                    success: function(response) {
                        $('#mediaGallery').html(response);
                    },
                    error: function() {
                        $('#mediaGallery').html('<div class="col-12 text-center"><div class="alert alert-danger">Error loading media files.</div></div>');
                    }
                });
            });
            
            // Handle folder structure view
            $('.view-folders').on('click', function() {
                const siteName = $(this).data('site');
                const itemDate = $(this).data('date');
                
                // Reset breadcrumb
                $('#folderBreadcrumb').html('<li class="breadcrumb-item active">Root</li>');
                
                // Show loading
                $('#folderContent').html('<div class="text-center folder-loading"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading folder structure...</p></div>');
                
                // Generate folder structure
                generateFolderView('root', siteName, itemDate);
            });
            
            // Handle view mode changes
            $('.view-options button').on('click', function() {
                const viewMode = $(this).data('view');
                
                // Update active button
                $('.view-options button').removeClass('active');
                $(this).addClass('active');
                
                // Update view mode
                $('.folder-structure').removeClass('view-list view-icons view-tiles').addClass('view-' + viewMode);
                
                // Save preference in localStorage
                localStorage.setItem('folderViewMode', viewMode);
            });
            
            // Apply saved view mode preference when modal opens
            $('#folderStructureModal').on('show.bs.modal', function() {
                const savedViewMode = localStorage.getItem('folderViewMode') || 'list';
                
                // Update active button
                $('.view-options button').removeClass('active');
                $(`.view-options button[data-view="${savedViewMode}"]`).addClass('active');
                
                // Apply view mode class
                $('.folder-structure').removeClass('view-list view-icons view-tiles').addClass('view-' + savedViewMode);
            });
            
            // Generate folder view based on path
            function generateFolderView(path, siteName, itemDate) {
                // Show loading indicator
                $('#folderContent').html('<div class="text-center folder-loading"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading folder structure...</p></div>');
                
                // Fetch folder structure data from the server
                $.ajax({
                    url: 'get_folder_structure.php',
                    type: 'GET',
                    data: { 
                        path: path,
                        site: siteName,
                        date: itemDate
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            $('#folderContent').html('<div class="alert alert-danger">' + response.error + '</div>');
                            return;
                        }
                        
                        let content = '';
                        
                        // Generate content based on items
                        if (response.items.length === 0) {
                            content = '<div class="alert alert-info">No items found in this folder.</div>';
                        } else {
                            response.items.forEach(function(item) {
                                if (item.type === 'folder') {
                                    content += `
                                        <div class="folder-item" data-path="${item.path}" data-site="${siteName}" data-date="${itemDate}">
                                            <div class="folder-icon folder"><i class="fas fa-folder"></i></div>
                                            <div class="folder-name">${item.name}</div>
                                            ${item.date ? `<div class="folder-date">${item.date}</div>` : ''}
                                        </div>
                                    `;
                                } else if (item.type === 'image') {
                                    content += `
                                        <div class="folder-item">
                                            <div class="folder-icon file-image"><i class="fas fa-file-image"></i></div>
                                            <div class="folder-name">${item.name}</div>
                                            <div class="folder-date">${item.size}</div>
                                            <a href="${item.path}" class="file-action" data-lightbox="folder-gallery" data-title="${item.name}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="${item.path}" class="file-action" download="${item.name}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    `;
                                } else if (item.type === 'pdf') {
                                    content += `
                                        <div class="folder-item">
                                            <div class="folder-icon file-pdf"><i class="fas fa-file-pdf"></i></div>
                                            <div class="folder-name">${item.name}</div>
                                            <div class="folder-date">${item.size}</div>
                                            <a href="${item.path}" class="file-action" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="${item.path}" class="file-action" download="${item.name}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    `;
                                } else if (item.type === 'video') {
                                    content += `
                                        <div class="folder-item">
                                            <div class="folder-icon file-video"><i class="fas fa-file-video"></i></div>
                                            <div class="folder-name">${item.name}</div>
                                            <div class="folder-date">${item.size}</div>
                                            <a href="${item.path}" class="file-action" target="_blank">
                                                <i class="fas fa-play"></i>
                                            </a>
                                            <a href="${item.path}" class="file-action" download="${item.name}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    `;
                                } else {
                                    content += `
                                        <div class="folder-item">
                                            <div class="folder-icon file"><i class="fas fa-file"></i></div>
                                            <div class="folder-name">${item.name}</div>
                                            <div class="folder-date">${item.size}</div>
                                            <a href="${item.path}" class="file-action" download="${item.name}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    `;
                                }
                            });
                        }
                        
                        // Update the folder content
                        $('#folderContent').html(content);
                        
                        // Add click event to folder items
                        $('.folder-item').on('click', function(e) {
                            // Don't trigger folder click if clicking on a file action button
                            if ($(e.target).closest('.file-action').length) {
                                return;
                            }
                            
                            if ($(this).data('path')) {
                                const newPath = $(this).data('path');
                                const site = $(this).data('site');
                                const date = $(this).data('date');
                                
                                // Update breadcrumb
                                updateBreadcrumb(newPath);
                                
                                // Load new folder content
                                generateFolderView(newPath, site, date);
                            }
                        });
                    },
                    error: function() {
                        $('#folderContent').html('<div class="alert alert-danger">Error loading folder structure. Please try again.</div>');
                    }
                });
            }
            
            // Update breadcrumb based on path
            function updateBreadcrumb(path) {
                let breadcrumb = '<li class="breadcrumb-item"><a href="#" data-path="root">Root</a></li>';
                
                if (path.startsWith('year-')) {
                    const year = path.split('-')[1];
                    breadcrumb += `<li class="breadcrumb-item active">${year}</li>`;
                }
                else if (path.startsWith('month-')) {
                    const pathParts = path.split('-');
                    const year = pathParts[1];
                    const monthIndex = parseInt(pathParts[2]);
                    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    const monthName = monthNames[monthIndex - 1]; // Adjust for 1-based month from server
                    
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="year-${year}">${year}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item active">${monthName}</li>`;
                }
                else if (path.startsWith('site-')) {
                    const pathParts = path.split('-', 4);
                    const year = pathParts[1];
                    const monthIndex = parseInt(pathParts[2]);
                    const siteName = decodeURIComponent(pathParts[3]);
                    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    const monthName = monthNames[monthIndex - 1]; // Adjust for 1-based month from server
                    
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="year-${year}">${year}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="month-${year}-${monthIndex}">${monthName}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item active">${siteName}</li>`;
                }
                else if (path.startsWith('category-')) {
                    const pathParts = path.split('-', 5);
                    const year = pathParts[1];
                    const monthIndex = parseInt(pathParts[2]);
                    const siteName = decodeURIComponent(pathParts[3]);
                    const category = pathParts[4];
                    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    const monthName = monthNames[monthIndex - 1]; // Adjust for 1-based month from server
                    
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="year-${year}">${year}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="month-${year}-${monthIndex}">${monthName}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="site-${year}-${monthIndex}-${encodeURIComponent(siteName)}">${siteName}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item active">${category}</li>`;
                }
                else if (path.startsWith('day-')) {
                    const pathParts = path.split('-', 6);
                    const year = pathParts[1];
                    const monthIndex = parseInt(pathParts[2]);
                    const siteName = decodeURIComponent(pathParts[3]);
                    const category = pathParts[4];
                    const day = pathParts[5];
                    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    const monthName = monthNames[monthIndex - 1]; // Adjust for 1-based month from server
                    
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="year-${year}">${year}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="month-${year}-${monthIndex}">${monthName}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="site-${year}-${monthIndex}-${encodeURIComponent(siteName)}">${siteName}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item"><a href="#" data-path="category-${year}-${monthIndex}-${encodeURIComponent(siteName)}-${category}">${category}</a></li>`;
                    breadcrumb += `<li class="breadcrumb-item active">${day} ${monthName}</li>`;
                }
                
                $('#folderBreadcrumb').html(breadcrumb);
                
                // Add click event to breadcrumb items
                $('#folderBreadcrumb a').on('click', function(e) {
                    e.preventDefault();
                    const path = $(this).data('path');
                    const siteName = $('.view-folders').data('site');
                    const itemDate = $('.view-folders').data('date');
                    
                    // Update breadcrumb
                    updateBreadcrumb(path);
                    
                    // Load folder content
                    generateFolderView(path, siteName, itemDate);
                });
            }
        });
    </script>
</body>
</html> 
