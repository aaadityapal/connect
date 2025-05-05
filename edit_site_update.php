<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/dashboard/dashboard_cards.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get the update ID from URL parameter
$update_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify the update exists
$stmt = $pdo->prepare("SELECT su.*, s.site_name 
                      FROM site_updates su
                      JOIN sites s ON su.site_id = s.id
                      WHERE su.id = ?");
$stmt->execute([$update_id]);
$update = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$update) {
    $_SESSION['error'] = "Site update not found.";
    header("Location: site_updates.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // 1. Update main site update record
        $updateStmt = $pdo->prepare("UPDATE site_updates 
                                    SET update_date = ?, notes = ?, updated_by = ?, last_updated = NOW() 
                                    WHERE id = ?");
        $updateStmt->execute([
            $_POST['update_date'],
            $_POST['notes'],
            $_SESSION['user_id'],
            $update_id
        ]);
        
        // 2. Delete existing vendors and laborers to replace with new ones
        $pdo->exec("DELETE FROM update_vendors WHERE update_id = $update_id");
        $pdo->exec("DELETE FROM update_laborers WHERE update_id = $update_id");
        
        // 3. Process vendors
        if (!empty($_POST['vendors'])) {
            foreach ($_POST['vendors'] as $vendor) {
                // Insert vendor
                $vendorStmt = $pdo->prepare("INSERT INTO update_vendors 
                                           (update_id, vendor_name, vendor_type, contact, amount) 
                                           VALUES (?, ?, ?, ?, ?)");
                $vendorStmt->execute([
                    $update_id,
                    $vendor['name'],
                    $vendor['type'],
                    $vendor['contact'],
                    $vendor['amount']
                ]);
                
                $vendor_id = $pdo->lastInsertId();
                
                // Process laborers for this vendor
                if (!empty($vendor['laborers'])) {
                    foreach ($vendor['laborers'] as $laborer) {
                        $laborerStmt = $pdo->prepare("INSERT INTO update_laborers 
                                                    (update_id, vendor_id, name, attendance_day, attendance_night, 
                                                    wages_per_day, overtime_hours, overtime_rate) 
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $laborerStmt->execute([
                            $update_id,
                            $vendor_id,
                            $laborer['name'],
                            $laborer['attendance_day'],
                            $laborer['attendance_night'],
                            $laborer['wages_per_day'],
                            $laborer['overtime_hours'],
                            $laborer['overtime_rate']
                        ]);
                    }
                }
            }
        }
        
        // 4. Process company laborers
        if (!empty($_POST['company_laborers'])) {
            foreach ($_POST['company_laborers'] as $laborer) {
                $laborerStmt = $pdo->prepare("INSERT INTO update_laborers 
                                            (update_id, vendor_id, name, attendance_day, attendance_night, 
                                            wages_per_day, overtime_hours, overtime_rate, is_company_labor) 
                                            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 1)");
                $laborerStmt->execute([
                    $update_id,
                    $laborer['name'],
                    $laborer['attendance_day'],
                    $laborer['attendance_night'],
                    $laborer['wages_per_day'],
                    $laborer['overtime_hours'],
                    $laborer['overtime_rate']
                ]);
            }
        }
        
        // 5. Delete existing expenses
        $pdo->exec("DELETE FROM update_expenses WHERE update_id = $update_id");
        
        // 6. Process travel expenses
        if (!empty($_POST['travel_expenses'])) {
            foreach ($_POST['travel_expenses'] as $expense) {
                $expenseStmt = $pdo->prepare("INSERT INTO update_expenses 
                                           (update_id, expense_type, from_location, to_location, 
                                           travel_mode, distance, amount) 
                                           VALUES (?, 'travel', ?, ?, ?, ?, ?)");
                $expenseStmt->execute([
                    $update_id,
                    $expense['from'],
                    $expense['to'],
                    $expense['mode'],
                    $expense['distance'],
                    $expense['amount']
                ]);
            }
        }
        
        // 7. Process beverage expenses
        if (!empty($_POST['beverages'])) {
            foreach ($_POST['beverages'] as $beverage) {
                $beverageStmt = $pdo->prepare("INSERT INTO update_expenses 
                                            (update_id, expense_type, description, amount) 
                                            VALUES (?, 'beverage', ?, ?)");
                $beverageStmt->execute([
                    $update_id,
                    $beverage['description'],
                    $beverage['amount']
                ]);
            }
        }
        
        // 8. Delete existing work progress
        $pdo->exec("DELETE FROM update_work_progress WHERE update_id = $update_id");
        
        // 9. Process work progress
        if (!empty($_POST['work_progress'])) {
            foreach ($_POST['work_progress'] as $work) {
                $workStmt = $pdo->prepare("INSERT INTO update_work_progress 
                                        (update_id, work_title, completed, details, remarks) 
                                        VALUES (?, ?, ?, ?, ?)");
                $workStmt->execute([
                    $update_id,
                    $work['title'],
                    $work['completed'],
                    $work['details'],
                    $work['remarks']
                ]);
            }
        }
        
        // 10. Delete existing inventory
        $pdo->exec("DELETE FROM update_inventory WHERE update_id = $update_id");
        
        // 11. Process inventory
        if (!empty($_POST['inventory'])) {
            foreach ($_POST['inventory'] as $item) {
                $inventoryStmt = $pdo->prepare("INSERT INTO update_inventory 
                                             (update_id, item_name, category, quantity, type, notes) 
                                             VALUES (?, ?, ?, ?, ?, ?)");
                $inventoryStmt->execute([
                    $update_id,
                    $item['name'],
                    $item['category'],
                    $item['quantity'],
                    $item['type'],
                    $item['notes']
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Site update has been successfully updated.";
        header("Location: view_site_update.php?id=$update_id");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating site update: " . $e->getMessage();
    }
}

// Get existing data for the form
// Vendors and their laborers
$vendorsStmt = $pdo->prepare("SELECT * FROM update_vendors WHERE update_id = ?");
$vendorsStmt->execute([$update_id]);
$vendors = $vendorsStmt->fetchAll(PDO::FETCH_ASSOC);

// Company laborers
$companyLaborersStmt = $pdo->prepare("SELECT * FROM update_laborers WHERE update_id = ? AND is_company_labor = 1");
$companyLaborersStmt->execute([$update_id]);
$companyLaborers = $companyLaborersStmt->fetchAll(PDO::FETCH_ASSOC);

// Travel expenses
$travelExpensesStmt = $pdo->prepare("SELECT * FROM update_expenses WHERE update_id = ? AND expense_type = 'travel'");
$travelExpensesStmt->execute([$update_id]);
$travelExpenses = $travelExpensesStmt->fetchAll(PDO::FETCH_ASSOC);

// Beverage expenses
$beveragesStmt = $pdo->prepare("SELECT * FROM update_expenses WHERE update_id = ? AND expense_type = 'beverage'");
$beveragesStmt->execute([$update_id]);
$beverages = $beveragesStmt->fetchAll(PDO::FETCH_ASSOC);

// Work progress
$workProgressStmt = $pdo->prepare("SELECT * FROM update_work_progress WHERE update_id = ?");
$workProgressStmt->execute([$update_id]);
$workProgress = $workProgressStmt->fetchAll(PDO::FETCH_ASSOC);

// Inventory
$inventoryStmt = $pdo->prepare("SELECT * FROM update_inventory WHERE update_id = ?");
$inventoryStmt->execute([$update_id]);
$inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

// For each vendor, get their laborers
foreach ($vendors as &$vendor) {
    $laborersStmt = $pdo->prepare("SELECT * FROM update_laborers WHERE update_id = ? AND vendor_id = ?");
    $laborersStmt->execute([$update_id, $vendor['id']]);
    $vendor['laborers'] = $laborersStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($vendor); // Break the reference

// Page title
$pageTitle = "Edit Site Update - " . $update['site_name'];
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1 class="page-title">
        <i class="fas fa-edit"></i> Edit Site Update
        <span class="site-name"><?= htmlspecialchars($update['site_name']) ?></span>
    </h1>
    
    <!-- Dashboard Cards Section -->
    <div class="dashboard-section mb-4">
        <h2 class="section-title mb-3">
            <i class="fas fa-tachometer-alt"></i> Dashboard Overview
        </h2>
        <?= renderDashboardCards($pdo, $_SESSION['user_id']) ?>
    </div>
    
    <form id="editSiteUpdateForm" method="post" action="" class="mb-5">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-info-circle"></i> Basic Information</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="update_date">Update Date</label>
                    <input type="date" class="form-control" id="update_date" name="update_date" value="<?= htmlspecialchars($update['update_date']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($update['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Vendors and Laborers Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title"><i class="fas fa-truck-loading"></i> Vendors & Contractors</h2>
                <button type="button" class="btn btn-sm btn-primary add-vendor">
                    <i class="fas fa-plus"></i> Add Vendor
                </button>
            </div>
            <div class="card-body">
                <div id="vendors-container">
                    <!-- Vendors will be loaded here -->
                    <?php if (!empty($vendors)): ?>
                        <?php foreach ($vendors as $index => $vendor): ?>
                            <div class="vendor-item card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 class="h5 mb-0">Vendor Details</h3>
                                    <button type="button" class="btn btn-sm btn-danger remove-vendor">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Vendor Name</label>
                                                <input type="text" class="form-control" name="vendors[<?= $index ?>][name]" value="<?= htmlspecialchars($vendor['vendor_name']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Vendor Type</label>
                                                <input type="text" class="form-control" name="vendors[<?= $index ?>][type]" value="<?= htmlspecialchars($vendor['vendor_type']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Contact</label>
                                                <input type="text" class="form-control" name="vendors[<?= $index ?>][contact]" value="<?= htmlspecialchars($vendor['contact']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Amount</label>
                                                <input type="number" step="0.01" class="form-control" name="vendors[<?= $index ?>][amount]" value="<?= htmlspecialchars($vendor['amount']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Laborers for this vendor -->
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 class="h6">Laborers</h4>
                                        <button type="button" class="btn btn-sm btn-info add-laborer">
                                            <i class="fas fa-plus"></i> Add Laborer
                                        </button>
                                    </div>
                                    <div class="laborers-container">
                                        <?php if (!empty($vendor['laborers'])): ?>
                                            <?php foreach ($vendor['laborers'] as $labIdx => $laborer): ?>
                                                <div class="laborer-item card mb-3">
                                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                                        <span class="h6 mb-0">Laborer</span>
                                                        <button type="button" class="btn btn-sm btn-danger remove-laborer">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label>Name</label>
                                                                    <input type="text" class="form-control" name="vendors[<?= $index ?>][laborers][<?= $labIdx ?>][name]" value="<?= htmlspecialchars($laborer['name']) ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label>Wages Per Day</label>
                                                                    <input type="number" step="0.01" class="form-control" name="vendors[<?= $index ?>][laborers][<?= $labIdx ?>][wages_per_day]" value="<?= htmlspecialchars($laborer['wages_per_day']) ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <label>Day Shift</label>
                                                                    <select class="form-control" name="vendors[<?= $index ?>][laborers][<?= $labIdx ?>][attendance_day]">
                                                                        <option value="P" <?= $laborer['attendance_day'] == 'P' ? 'selected' : '' ?>>Present</option>
                                                                        <option value="A" <?= $laborer['attendance_day'] == 'A' ? 'selected' : '' ?>>Absent</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <label>Night Shift</label>
                                                                    <select class="form-control" name="vendors[<?= $index ?>][laborers][<?= $labIdx ?>][attendance_night]">
                                                                        <option value="P" <?= $laborer['attendance_night'] == 'P' ? 'selected' : '' ?>>Present</option>
                                                                        <option value="A" <?= $laborer['attendance_night'] == 'A' ? 'selected' : '' ?>>Absent</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <label>Overtime Hours</label>
                                                                    <input type="text" class="form-control" name="vendors[<?= $index ?>][laborers][<?= $labIdx ?>][overtime_hours]" value="<?= htmlspecialchars($laborer['overtime_hours']) ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <label>Overtime Rate</label>
                                                                    <input type="number" step="0.01" class="form-control" name="vendors[<?= $index ?>][laborers][<?= $labIdx ?>][overtime_rate]" value="<?= htmlspecialchars($laborer['overtime_rate']) ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($vendors)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No vendors added yet. Use the "Add Vendor" button to add vendors.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Company Laborers Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title"><i class="fas fa-hard-hat"></i> Company Laborers</h2>
                <button type="button" class="btn btn-sm btn-primary add-company-laborer">
                    <i class="fas fa-plus"></i> Add Company Laborer
                </button>
            </div>
            <div class="card-body">
                <div id="company-laborers-container">
                    <!-- Company Laborers will be loaded here -->
                    <?php if (!empty($companyLaborers)): ?>
                        <?php foreach ($companyLaborers as $index => $laborer): ?>
                            <div class="company-laborer-item card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center py-2">
                                    <span class="h6 mb-0">Company Laborer</span>
                                    <button type="button" class="btn btn-sm btn-danger remove-company-laborer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" class="form-control" name="company_laborers[<?= $index ?>][name]" value="<?= htmlspecialchars($laborer['name']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Wages Per Day</label>
                                                <input type="number" step="0.01" class="form-control" name="company_laborers[<?= $index ?>][wages_per_day]" value="<?= htmlspecialchars($laborer['wages_per_day']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Day Shift</label>
                                                <select class="form-control" name="company_laborers[<?= $index ?>][attendance_day]">
                                                    <option value="P" <?= $laborer['attendance_day'] == 'P' ? 'selected' : '' ?>>Present</option>
                                                    <option value="A" <?= $laborer['attendance_day'] == 'A' ? 'selected' : '' ?>>Absent</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Night Shift</label>
                                                <select class="form-control" name="company_laborers[<?= $index ?>][attendance_night]">
                                                    <option value="P" <?= $laborer['attendance_night'] == 'P' ? 'selected' : '' ?>>Present</option>
                                                    <option value="A" <?= $laborer['attendance_night'] == 'A' ? 'selected' : '' ?>>Absent</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Overtime Hours</label>
                                                <input type="text" class="form-control" name="company_laborers[<?= $index ?>][overtime_hours]" value="<?= htmlspecialchars($laborer['overtime_hours']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Overtime Rate</label>
                                                <input type="number" step="0.01" class="form-control" name="company_laborers[<?= $index ?>][overtime_rate]" value="<?= htmlspecialchars($laborer['overtime_rate']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($companyLaborers)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No company laborers added yet. Use the "Add Company Laborer" button to add company laborers.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Expenses Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Expenses</h2>
            </div>
            <div class="card-body">
                <!-- Travel Expenses -->
                <div class="expense-section mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5"><i class="fas fa-route"></i> Travel Expenses</h3>
                        <button type="button" class="btn btn-sm btn-primary add-travel-expense">
                            <i class="fas fa-plus"></i> Add Travel Expense
                        </button>
                    </div>
                    
                    <div id="travel-expenses-container">
                        <?php if (!empty($travelExpenses)): ?>
                            <?php foreach ($travelExpenses as $index => $expense): ?>
                                <div class="travel-expense-item card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <span class="h6 mb-0">Travel Expense</span>
                                        <button type="button" class="btn btn-sm btn-danger remove-travel-expense">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>From</label>
                                                    <input type="text" class="form-control" name="travel_expenses[<?= $index ?>][from]" value="<?= htmlspecialchars($expense['from_location']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>To</label>
                                                    <input type="text" class="form-control" name="travel_expenses[<?= $index ?>][to]" value="<?= htmlspecialchars($expense['to_location']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Mode</label>
                                                    <select class="form-control" name="travel_expenses[<?= $index ?>][mode]">
                                                        <option value="car" <?= $expense['travel_mode'] == 'car' ? 'selected' : '' ?>>Car</option>
                                                        <option value="bike" <?= $expense['travel_mode'] == 'bike' ? 'selected' : '' ?>>Bike</option>
                                                        <option value="public" <?= $expense['travel_mode'] == 'public' ? 'selected' : '' ?>>Public Transport</option>
                                                        <option value="other" <?= $expense['travel_mode'] == 'other' ? 'selected' : '' ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Distance (km)</label>
                                                    <input type="number" step="0.1" class="form-control" name="travel_expenses[<?= $index ?>][distance]" value="<?= htmlspecialchars($expense['distance']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Amount (₹)</label>
                                                    <input type="number" step="0.01" class="form-control" name="travel_expenses[<?= $index ?>][amount]" value="<?= htmlspecialchars($expense['amount']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($travelExpenses)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No travel expenses added yet.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Beverage Expenses -->
                <div class="expense-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5"><i class="fas fa-coffee"></i> Refreshments</h3>
                        <button type="button" class="btn btn-sm btn-primary add-beverage">
                            <i class="fas fa-plus"></i> Add Refreshment
                        </button>
                    </div>
                    
                    <div id="beverages-container">
                        <?php if (!empty($beverages)): ?>
                            <?php foreach ($beverages as $index => $beverage): ?>
                                <div class="beverage-item card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <span class="h6 mb-0">Refreshment</span>
                                        <button type="button" class="btn btn-sm btn-danger remove-beverage">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <label>Description</label>
                                                    <input type="text" class="form-control" name="beverages[<?= $index ?>][description]" value="<?= htmlspecialchars($beverage['description']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Amount (₹)</label>
                                                    <input type="number" step="0.01" class="form-control" name="beverages[<?= $index ?>][amount]" value="<?= htmlspecialchars($beverage['amount']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($beverages)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No refreshments added yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- More sections for Work Progress and Inventory will be added here -->
        
        <div class="form-group text-center mt-4">
            <button type="submit" class="btn btn-lg btn-success">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="view_site_update.php?id=<?= $update_id ?>" class="btn btn-lg btn-secondary ml-2">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<!-- Templates for JavaScript to use when adding new items -->
<template id="vendor-template">
    <div class="vendor-item card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="h5 mb-0">Vendor Details</h3>
            <button type="button" class="btn btn-sm btn-danger remove-vendor">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Vendor Name</label>
                        <input type="text" class="form-control" name="vendors[{index}][name]" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Vendor Type</label>
                        <input type="text" class="form-control" name="vendors[{index}][type]">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Contact</label>
                        <input type="text" class="form-control" name="vendors[{index}][contact]">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" class="form-control" name="vendors[{index}][amount]">
                    </div>
                </div>
            </div>
            
            <!-- Laborers for this vendor -->
            <hr>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="h6">Laborers</h4>
                <button type="button" class="btn btn-sm btn-info add-laborer">
                    <i class="fas fa-plus"></i> Add Laborer
                </button>
            </div>
            <div class="laborers-container">
                <!-- Laborers will be added here -->
            </div>
        </div>
    </div>
</template>

<template id="laborer-template">
    <div class="laborer-item card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span class="h6 mb-0">Laborer</span>
            <button type="button" class="btn btn-sm btn-danger remove-laborer">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="vendors[{vendorIndex}][laborers][{index}][name]">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Wages Per Day</label>
                        <input type="number" step="0.01" class="form-control" name="vendors[{vendorIndex}][laborers][{index}][wages_per_day]">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Day Shift</label>
                        <select class="form-control" name="vendors[{vendorIndex}][laborers][{index}][attendance_day]">
                            <option value="P">Present</option>
                            <option value="A">Absent</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Night Shift</label>
                        <select class="form-control" name="vendors[{vendorIndex}][laborers][{index}][attendance_night]">
                            <option value="P">Present</option>
                            <option value="A">Absent</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Overtime Hours</label>
                        <input type="text" class="form-control" name="vendors[{vendorIndex}][laborers][{index}][overtime_hours]">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Overtime Rate</label>
                        <input type="number" step="0.01" class="form-control" name="vendors[{vendorIndex}][laborers][{index}][overtime_rate]">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
$(document).ready(function() {
    let vendorIndex = <?= !empty($vendors) ? count($vendors) : 0 ?>;
    
    // Add vendor
    $('.add-vendor').click(function() {
        let template = $('#vendor-template').html();
        template = template.replace(/{index}/g, vendorIndex);
        $('#vendors-container').append(template);
        vendorIndex++;
    });
    
    // Remove vendor
    $(document).on('click', '.remove-vendor', function() {
        $(this).closest('.vendor-item').remove();
    });
    
    // Add laborer
    $(document).on('click', '.add-laborer', function() {
        const vendorItem = $(this).closest('.vendor-item');
        const laborersContainer = vendorItem.find('.laborers-container');
        const currentVendorIndex = vendorItem.index();
        const laborerCount = laborersContainer.children().length;
        
        let template = $('#laborer-template').html();
        template = template.replace(/{vendorIndex}/g, currentVendorIndex);
        template = template.replace(/{index}/g, laborerCount);
        
        laborersContainer.append(template);
    });
    
    // Remove laborer
    $(document).on('click', '.remove-laborer', function() {
        $(this).closest('.laborer-item').remove();
    });
});
</script>

<?php include 'includes/footer.php'; ?> 