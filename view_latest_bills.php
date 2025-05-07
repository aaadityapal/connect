<?php
// view_latest_bills.php - Display the latest inventory bills from today
// First include the database connection
require_once 'config/db_connect.php';
// Then include the inventory media handler
require_once 'includes/inventory_media_handler.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Set the current date
$today = date('Y-m-d');

// Initialize the handler with PDO connection
$mediaHandler = new InventoryMediaHandler($pdo);

try {
    // Set default date filter to today
    $dateFilter = isset($_GET['date']) ? $_GET['date'] : $today;
    
    // Format the date for display
    $displayDate = date('l, F j, Y', strtotime($dateFilter));
    
    // Set appropriate page title based on if we're viewing today or a different date
    $pageTitle = ($dateFilter == $today) ? "Today's Inventory Bills" : "Inventory Bills for " . date('d-m-Y', strtotime($dateFilter));
    
    // Get the table structure to help debug issues
    $schema = [];
    try {
        $describeTable = $pdo->query("DESCRIBE event_inventory_items");
        if ($describeTable) {
            $schema = $describeTable->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        // Ignore this error
    }
    
    // Fetch bills directly from event_inventory_items table
    $dataSource = "event_inventory_items";
    $query = "SELECT id AS inventory_id, NULL AS file_path, remarks AS description, 'bill' AS media_type,
              item_name, total_price AS amount, created_at, supplier_name AS vendor_name,
              bill_number, bill_date, bill_picture, remarks, material
              FROM event_inventory_items
              WHERE DATE(created_at) = ?
              AND bill_picture IS NOT NULL
              ORDER BY created_at DESC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$dateFilter]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no bills found on specified date, show most recent bills
    if (empty($bills)) {
        $dataSource = "event_inventory_items (most recent)";
        $query = "SELECT id AS inventory_id, NULL AS file_path, remarks AS description, 'bill' AS media_type,
                  item_name, total_price AS amount, created_at, supplier_name AS vendor_name,
                  bill_number, bill_date, bill_picture, remarks, material
                  FROM event_inventory_items
                  WHERE bill_picture IS NOT NULL
                  ORDER BY created_at DESC
                  LIMIT 10";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Handle database errors
    $errorMessage = "Error fetching bills: " . $e->getMessage();
    $bills = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Construction Management</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .header {
            background: #34495e;
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .bill-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .bill-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .bill-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 15px;
        }
        
        .bill-body {
            padding: 15px;
        }
        
        .bill-preview {
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .bill-image {
            max-height: 300px;
            max-width: 100%;
            object-fit: contain;
        }
        
        .bill-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .no-bills {
            padding: 40px;
            text-align: center;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .back-btn {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><?= $pageTitle ?></h1>
            <p class="mb-0">Date: <?= $displayDate ?></p>
            <?php if (isset($dataSource)): ?>
            <p class="small text-light mt-1">Source: <?= htmlspecialchars($dataSource) ?> table</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row mb-4">
            <div class="col-md-6">
                <a href="site_supervision.php" class="btn btn-outline-secondary back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="col-md-6">
                <form method="GET" class="date-filter-form d-flex justify-content-md-end align-items-center">
                    <div class="input-group" style="max-width: 300px;">
                        <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (isset($_GET['date'])): ?>
                        <a href="view_latest_bills.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if(isset($errorMessage)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <!-- Debug Information for Database Structure -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Database Debug Information</h5>
            </div>
            <div class="card-body">
                <h6>event_inventory_items Table Structure:</h6>
                <?php if (!empty($schema)): ?>
                    <code class="d-block bg-light p-2">
                        <?= htmlspecialchars(implode(', ', $schema)) ?>
                    </code>
                <?php else: ?>
                    <p class="text-danger">Could not retrieve table structure.</p>
                <?php endif; ?>
                
                <!-- Try a raw SQL query to see what columns actually contain bill paths -->
                <?php
                try {
                    $rawSql = "SELECT * FROM event_inventory_items WHERE bill_picture IS NOT NULL LIMIT 1";
                    $rawResult = $pdo->query($rawSql);
                    if ($rawResult && $rawResult->rowCount() > 0) {
                        $sampleBill = $rawResult->fetch(PDO::FETCH_ASSOC);
                        echo '<h6 class="mt-3">Sample Bill Record:</h6>';
                        echo '<code class="d-block bg-light p-2 mb-2">';
                        foreach ($sampleBill as $key => $value) {
                            if (strpos($key, 'bill') !== false || strpos($key, 'file') !== false) {
                                echo '<strong>' . htmlspecialchars($key) . '</strong>: ' . htmlspecialchars($value) . '<br>';
                            }
                        }
                        echo '</code>';
                    }
                } catch (Exception $e) {
                    echo '<p class="text-danger">Error retrieving sample record: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
                ?>
            </div>
        </div>
        
        <div class="row">
            <?php if (count($bills) > 0): ?>
                <?php foreach ($bills as $bill): ?>
                    <div class="col-lg-6">
                        <div class="bill-card">
                            <div class="bill-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-invoice"></i> 
                                    <?= htmlspecialchars($bill['item_name']) ?>
                                </h5>
                            </div>
                            <div class="bill-body">
                                <div class="bill-preview">
                                    <?php 
                                    // Check for bill_picture from event_inventory_items
                                    if (!empty($bill['bill_picture']) && file_exists(dirname(__FILE__) . '/' . $bill['bill_picture'])): ?>
                                        <img src="<?= htmlspecialchars($bill['bill_picture']) ?>" class="bill-image" alt="Bill Image">
                                    <?php 
                                    // Check for file_path from inventory_media
                                    elseif (!empty($bill['file_path']) && file_exists(dirname(__FILE__) . '/' . $bill['file_path'])): ?>
                                        <img src="<?= htmlspecialchars($bill['file_path']) ?>" class="bill-image" alt="Bill Image">
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Bill image not found
                                            <div class="mt-2">
                                                <small>Path: <?= htmlspecialchars(!empty($bill['bill_picture']) ? $bill['bill_picture'] : 'No path available') ?></small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="bill-details">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong><i class="fas fa-user"></i> Supplier:</strong> <?= htmlspecialchars($bill['vendor_name']) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong><i class="fas fa-rupee-sign"></i> Amount:</strong> ₹<?= number_format($bill['amount'], 2) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong><i class="fas fa-file-invoice"></i> Bill Number:</strong> <?= $bill['bill_number'] ? htmlspecialchars($bill['bill_number']) : 'N/A' ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong><i class="fas fa-calendar-alt"></i> Bill Date:</strong> <?= $bill['bill_date'] ? date('d-m-Y', strtotime($bill['bill_date'])) : 'N/A' ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong><i class="fas fa-boxes"></i> Material:</strong> <?= $bill['material'] ? htmlspecialchars($bill['material']) : 'Not specified' ?></p>
                                        </div>
                                        <div class="col-12">
                                            <p><strong><i class="fas fa-comment-alt"></i> Description:</strong></p>
                                            <p><?= htmlspecialchars($bill['description']) ?></p>
                                        </div>
                                        <?php if ($bill['remarks']): ?>
                                        <div class="col-12">
                                            <p><strong><i class="fas fa-sticky-note"></i> Remarks:</strong></p>
                                            <p><?= htmlspecialchars($bill['remarks']) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-12">
                                            <p class="text-muted mb-0">
                                                <small><i class="fas fa-clock"></i> Added at: <?= date('h:i A', strtotime($bill['created_at'])) ?></small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="no-bills">
                        <i class="fas fa-receipt fa-3x mb-3 text-muted"></i>
                        <h4>No Bills Found</h4>
                        <p class="text-muted">There are no inventory bills recorded for today.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 