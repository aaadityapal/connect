<?php
// Enable error reporting for troubleshooting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'config/db_connect.php';

echo '<html><head><title>Create Test Data</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
    .container { max-width: 800px; margin: 0 auto; }
    h1 { color: #4361ee; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    .message { padding: 15px; margin: 20px 0; border-radius: 4px; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .action-btn { background: #4361ee; color: white; border: none; padding: 10px 15px; 
                 border-radius: 4px; cursor: pointer; margin-top: 20px; }
    .action-btn:hover { background: #3a0ca3; }
    .back-link { display: inline-block; margin-top: 20px; color: #4361ee; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #f8f9fa; }
</style>';
echo '</head><body>';
echo '<div class="container">';
echo '<h1>Create Test Data for Labour Attendance</h1>';

// Function to check if a table exists
function tableExists($pdo, $tableName) {
    $query = "SELECT COUNT(*) AS table_exists FROM information_schema.tables 
              WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tableName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result && $result['table_exists'] > 0);
}

// Function to get count of records in a table
function getRecordCount($pdo, $tableName) {
    if (!tableExists($pdo, $tableName)) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) AS count FROM $tableName";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['count'] : 0;
}

// Function to add is_deleted column if it doesn't exist
function addIsDeletedColumn($pdo, $tableName) {
    // Check if column exists
    $columnCheckQuery = "SELECT COUNT(*) AS column_exists FROM information_schema.columns 
                         WHERE table_schema = DATABASE() 
                         AND table_name = ? 
                         AND column_name = 'is_deleted'";
    $stmt = $pdo->prepare($columnCheckQuery);
    $stmt->execute([$tableName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || $result['column_exists'] == 0) {
        // Add is_deleted column
        $alterQuery = "ALTER TABLE $tableName ADD COLUMN is_deleted TINYINT(1) DEFAULT 0";
        $pdo->exec($alterQuery);
        return true;
    }
    
    return false;
}

// Check if form was submitted
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'create_test_data') {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // 1. Check and create test calendar events (construction sites)
        if (!tableExists($pdo, 'sv_calendar_events')) {
            throw new Exception("Calendar events table (sv_calendar_events) does not exist. Run the database setup first.");
        }
        
        $sitesCount = getRecordCount($pdo, 'sv_calendar_events');
        
        if ($sitesCount === 0) {
            // Create sample construction sites
            $sitesData = [
                ['Construction Site 1', 'Residential Building Project', '2025-01-01', '2025-12-31'],
                ['Construction Site 2', 'Commercial Complex', '2025-01-01', '2025-12-31'],
                ['Construction Site 3', 'Highway Project', '2025-01-01', '2025-12-31']
            ];
            
            $siteQuery = "INSERT INTO sv_calendar_events (title, description, start_date, end_date, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
            $siteStmt = $pdo->prepare($siteQuery);
            
            foreach ($sitesData as $site) {
                $siteStmt->execute($site);
            }
            
            echo '<div class="message success">Created 3 test construction sites</div>';
        } else {
            echo '<div class="message success">Using existing construction sites (' . $sitesCount . ' found)</div>';
        }
        
        // 2. Check and create test vendors
        if (!tableExists($pdo, 'sv_event_vendors')) {
            // Create the vendors table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `sv_event_vendors` (
                    `vendor_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `event_id` INT NOT NULL,
                    `vendor_name` VARCHAR(255) NOT NULL,
                    `vendor_type` VARCHAR(100),
                    `contact_person` VARCHAR(255),
                    `contact_number` VARCHAR(20),
                    `sequence_number` INT,
                    `is_deleted` TINYINT(1) DEFAULT 0,
                    `created_by` INT,
                    `updated_by` INT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            echo '<div class="message success">Created sv_event_vendors table</div>';
        }
        
        $vendorsCount = getRecordCount($pdo, 'sv_event_vendors');
        
        if ($vendorsCount === 0) {
            // Get all event IDs
            $eventQuery = "SELECT event_id FROM sv_calendar_events ORDER BY event_id LIMIT 3";
            $eventStmt = $pdo->query($eventQuery);
            $eventIds = $eventStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($eventIds)) {
                throw new Exception("No construction sites found to associate vendors with.");
            }
            
            // Create sample vendors
            $vendorsData = [
                [$eventIds[0], 'ABC Construction Supplies', 'Material Supplier', 'John Smith', '9876543210', 1],
                [$eventIds[0], 'XYZ Labour Contractors', 'Labour Contractor', 'Jane Doe', '8765432109', 2],
                [$eventIds[1], 'PQR Equipment Rentals', 'Equipment Supplier', 'Bob Johnson', '7654321098', 1]
            ];
            
            $vendorQuery = "INSERT INTO sv_event_vendors (event_id, vendor_name, vendor_type, contact_person, 
                           contact_number, sequence_number, is_deleted, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
            $vendorStmt = $pdo->prepare($vendorQuery);
            
            foreach ($vendorsData as $vendor) {
                $vendorStmt->execute($vendor);
            }
            
            echo '<div class="message success">Created 3 test vendors</div>';
        } else {
            echo '<div class="message success">Using existing vendors (' . $vendorsCount . ' found)</div>';
        }
        
        // 3. Check and create company labours table
        if (!tableExists($pdo, 'sv_company_labours')) {
            // Create the company labours table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `sv_company_labours` (
                    `company_labour_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `event_id` INT NOT NULL,
                    `labour_name` VARCHAR(255) NOT NULL,
                    `contact_number` VARCHAR(20),
                    `sequence_number` INT,
                    `is_deleted` TINYINT(1) DEFAULT 0,
                    `created_by` INT,
                    `updated_by` INT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `morning_attendance` ENUM('present', 'absent', 'late') DEFAULT NULL,
                    `evening_attendance` ENUM('present', 'absent', 'late') DEFAULT NULL,
                    `attendance_date` DATE,
                    `daily_wage` DECIMAL(10,2) DEFAULT 0,
                    FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            echo '<div class="message success">Created sv_company_labours table</div>';
        } else {
            // Add is_deleted column if it doesn't exist
            if (addIsDeletedColumn($pdo, 'sv_company_labours')) {
                echo '<div class="message success">Added is_deleted column to sv_company_labours table</div>';
            }
        }
        
        // 4. Check and create vendor labours table
        if (!tableExists($pdo, 'sv_vendor_labours')) {
            // Create the vendor labours table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `sv_vendor_labours` (
                    `labour_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `vendor_id` INT NOT NULL,
                    `labour_name` VARCHAR(255) NOT NULL,
                    `contact_number` VARCHAR(20),
                    `sequence_number` INT,
                    `is_deleted` TINYINT(1) DEFAULT 0,
                    `created_by` INT,
                    `updated_by` INT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `morning_attendance` ENUM('present', 'absent', 'late') DEFAULT NULL,
                    `evening_attendance` ENUM('present', 'absent', 'late') DEFAULT NULL,
                    `attendance_date` DATE,
                    `wage_rate` DECIMAL(10,2) DEFAULT 0,
                    FOREIGN KEY (`vendor_id`) REFERENCES `sv_event_vendors`(`vendor_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            echo '<div class="message success">Created sv_vendor_labours table</div>';
        } else {
            // Add is_deleted column if it doesn't exist
            if (addIsDeletedColumn($pdo, 'sv_vendor_labours')) {
                echo '<div class="message success">Added is_deleted column to sv_vendor_labours table</div>';
            }
        }
        
        // 5. Create sample company labour records
        $companyLaboursCount = getRecordCount($pdo, 'sv_company_labours');
        
        if ($companyLaboursCount === 0) {
            // Get all event IDs
            $eventQuery = "SELECT event_id FROM sv_calendar_events ORDER BY event_id LIMIT 3";
            $eventStmt = $pdo->query($eventQuery);
            $eventIds = $eventStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($eventIds)) {
                throw new Exception("No construction sites found to associate company labours with.");
            }
            
            // Sample company labour data
            $labourData = [
                // Format: event_id, name, contact, date, morning, evening, wage, sequence
                [$eventIds[0], 'Rahul Sharma', '9876543210', date('Y-m-d'), 'present', 'present', 500.00, 1],
                [$eventIds[0], 'Amit Kumar', '8765432109', date('Y-m-d'), 'present', 'absent', 500.00, 2],
                [$eventIds[1], 'Priya Singh', '7654321098', date('Y-m-d'), 'present', 'present', 450.00, 1],
                [$eventIds[1], 'Vikram Patel', '6543210987', date('Y-m-d'), 'late', 'present', 450.00, 2],
                [$eventIds[2], 'Neha Gupta', '5432109876', date('Y-m-d'), 'present', 'present', 550.00, 1]
            ];
            
            $labourQuery = "INSERT INTO sv_company_labours (event_id, labour_name, contact_number, attendance_date, 
                           morning_attendance, evening_attendance, daily_wage, sequence_number, is_deleted, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())";
            $labourStmt = $pdo->prepare($labourQuery);
            
            foreach ($labourData as $labour) {
                $labourStmt->execute($labour);
            }
            
            echo '<div class="message success">Created 5 test company labour records</div>';
        } else {
            echo '<div class="message success">Using existing company labour records (' . $companyLaboursCount . ' found)</div>';
        }
        
        // 6. Create sample vendor labour records
        $vendorLaboursCount = getRecordCount($pdo, 'sv_vendor_labours');
        
        if ($vendorLaboursCount === 0) {
            // Get all vendor IDs
            $vendorQuery = "SELECT vendor_id FROM sv_event_vendors ORDER BY vendor_id LIMIT 3";
            $vendorStmt = $pdo->query($vendorQuery);
            $vendorIds = $vendorStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($vendorIds)) {
                throw new Exception("No vendors found to associate vendor labours with.");
            }
            
            // Sample vendor labour data
            $labourData = [
                // Format: vendor_id, name, contact, date, morning, evening, wage, sequence
                [$vendorIds[0], 'Rajesh Kumar', '9876543210', date('Y-m-d'), 'present', 'present', 450.00, 1],
                [$vendorIds[0], 'Sunil Verma', '8765432109', date('Y-m-d'), 'present', 'absent', 450.00, 2],
                [$vendorIds[1], 'Manoj Singh', '7654321098', date('Y-m-d'), 'absent', 'present', 400.00, 1],
                [$vendorIds[1], 'Dinesh Joshi', '6543210987', date('Y-m-d'), 'present', 'present', 400.00, 2],
                [$vendorIds[2], 'Anand Mishra', '5432109876', date('Y-m-d'), 'present', 'late', 500.00, 1]
            ];
            
            $labourQuery = "INSERT INTO sv_vendor_labours (vendor_id, labour_name, contact_number, attendance_date, 
                           morning_attendance, evening_attendance, wage_rate, sequence_number, is_deleted, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())";
            $labourStmt = $pdo->prepare($labourQuery);
            
            foreach ($labourData as $labour) {
                $labourStmt->execute($labour);
            }
            
            echo '<div class="message success">Created 5 test vendor labour records</div>';
        } else {
            echo '<div class="message success">Using existing vendor labour records (' . $vendorLaboursCount . ' found)</div>';
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo '<div class="message success"><strong>All test data created successfully!</strong></div>';
        echo '<p>You should now be able to see labour attendance records in the main page.</p>';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo '<div class="message error"><strong>Error:</strong> ' . $e->getMessage() . '</div>';
    }
}

// Display current table status
echo '<h2>Current Database Status</h2>';
echo '<table>';
echo '<tr><th>Table</th><th>Exists</th><th>Record Count</th></tr>';

$tables = [
    'sv_calendar_events' => 'Construction Sites',
    'sv_event_vendors' => 'Vendors',
    'sv_company_labours' => 'Company Labour Records',
    'sv_vendor_labours' => 'Vendor Labour Records'
];

foreach ($tables as $tableName => $description) {
    $exists = tableExists($pdo, $tableName);
    $count = $exists ? getRecordCount($pdo, $tableName) : 'N/A';
    
    echo '<tr>';
    echo '<td>' . $tableName . ' (' . $description . ')</td>';
    echo '<td>' . ($exists ? 'Yes' : 'No') . '</td>';
    echo '<td>' . $count . '</td>';
    echo '</tr>';
}

echo '</table>';

// Create form for generating test data
echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
echo '<input type="hidden" name="action" value="create_test_data">';
echo '<button type="submit" class="action-btn">Generate Test Data</button>';
echo '</form>';

echo '<a href="labour_attendance.php" class="back-link">← Back to Labour Attendance</a>';
echo '</div></body></html>'; 