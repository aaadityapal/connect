<?php
/**
 * Test API for Payment Records
 * Tests the fetch_vendor_payment_records.php API
 */

session_start();

// Simulate logged in user
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

// Test vendor ID
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 24;

// Include database connection
require_once 'config/db_connect.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Records API Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; }
        .test-section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #4f46e5; border-radius: 4px; }
        .test-section h2 { margin-top: 0; color: #2a4365; font-size: 1.1em; }
        .error { color: #e53e3e; background: #fff5f5; padding: 10px; border-radius: 4px; }
        .success { color: #22543d; background: #f0fdf4; padding: 10px; border-radius: 4px; }
        .code { background: #333; color: #0f0; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 0.9em; max-height: 400px; overflow-y: auto; }
        .info { background: #dbeafe; color: #0c4a6e; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; color: #2a4365; }
    </style>
</head>
<body>
    <div class="container">
        <h1>💳 Payment Records API Test</h1>
        
        <div class="test-section">
            <h2>Test Configuration</h2>
            <p><strong>Vendor ID:</strong> <?php echo $vendor_id; ?></p>
            <p><strong>Test URL:</strong> <code>fetch_vendor_payment_records.php?vendor_id=<?php echo $vendor_id; ?></code></p>
        </div>

        <div class="test-section">
            <h2>Database Connection</h2>
            <?php
            try {
                $test_query = "SELECT COUNT(*) as count FROM tbl_payment_entry_master_records";
                $test_stmt = $pdo->query($test_query);
                $test_result = $test_stmt->fetch(PDO::FETCH_ASSOC);
                echo '<div class="success">✓ Database connected successfully</div>';
                echo '<p>Total payment entries in system: <strong>' . $test_result['count'] . '</strong></p>';
            } catch (Exception $e) {
                echo '<div class="error">✗ Database connection failed: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <div class="test-section">
            <h2>Testing Payment Records Query</h2>
            <?php
            try {
                // Test the main query
                $query = "
                    SELECT 
                        m.payment_entry_id,
                        m.project_type_category,
                        m.project_name_reference,
                        COALESCE(p.name, m.project_name_reference) as project_name,
                        m.payment_amount_base,
                        m.payment_date_logged,
                        m.payment_mode_selected,
                        m.entry_status_current,
                        l.line_item_entry_id,
                        l.recipient_id_reference,
                        l.line_item_amount
                    FROM 
                        tbl_payment_entry_master_records m
                    INNER JOIN 
                        tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
                    LEFT JOIN 
                        projects p ON m.project_name_reference = p.id
                    WHERE 
                        l.recipient_id_reference = :vendor_id
                    ORDER BY 
                        m.payment_date_logged DESC
                    LIMIT 10
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([':vendor_id' => $vendor_id]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($results)) {
                    echo '<div class="info">ℹ️ No payment records found for vendor ID ' . $vendor_id . '</div>';
                } else {
                    echo '<div class="success">✓ Found ' . count($results) . ' payment records</div>';
                    echo '<table>';
                    echo '<thead><tr><th>Payment ID</th><th>Project</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($results as $row) {
                        echo '<tr>';
                        echo '<td>' . $row['payment_entry_id'] . '</td>';
                        echo '<td>' . ($row['project_name'] ?: 'N/A') . '</td>';
                        echo '<td>₹' . number_format($row['payment_amount_base'], 2) . '</td>';
                        echo '<td>' . date('d M Y', strtotime($row['payment_date_logged'])) . '</td>';
                        echo '<td>' . $row['entry_status_current'] . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                }
            } catch (Exception $e) {
                echo '<div class="error">✗ Query failed: ' . $e->getMessage() . '</div>';
                echo '<p><strong>SQL Query:</strong></p>';
                echo '<div class="code">' . htmlspecialchars($query ?? '') . '</div>';
            }
            ?>
        </div>

        <div class="test-section">
            <h2>Testing Projects Table</h2>
            <?php
            try {
                $project_query = "SELECT COUNT(*) as count FROM projects LIMIT 1";
                $project_stmt = $pdo->query($project_query);
                $project_result = $project_stmt->fetch(PDO::FETCH_ASSOC);
                echo '<div class="success">✓ Projects table exists</div>';
                echo '<p>Total projects in system: <strong>' . $project_result['count'] . '</strong></p>';
                
                // Show sample projects
                $sample_query = "SELECT id, name FROM projects LIMIT 5";
                $sample_stmt = $pdo->query($sample_query);
                $sample_results = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($sample_results)) {
                    echo '<p><strong>Sample Projects:</strong></p>';
                    echo '<table>';
                    echo '<thead><tr><th>ID</th><th>Name</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($sample_results as $row) {
                        echo '<tr><td>' . $row['id'] . '</td><td>' . $row['name'] . '</td></tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                }
            } catch (Exception $e) {
                echo '<div class="error">✗ Projects table error: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <div class="test-section">
            <h2>API Response Test</h2>
            <p>Click below to test the actual API:</p>
            <a href="fetch_vendor_payment_records.php?vendor_id=<?php echo $vendor_id; ?>" 
               style="display: inline-block; background: #4f46e5; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; margin: 10px 0;">
                Test API (Vendor ID: <?php echo $vendor_id; ?>)
            </a>
        </div>

        <div class="test-section">
            <h2>Query Structure Info</h2>
            <div class="info">
                <strong>Main Query uses:</strong><br>
                • Table: <code>tbl_payment_entry_master_records</code> (m)<br>
                • Join: <code>tbl_payment_entry_line_items_detail</code> (l)<br>
                • Join: <code>projects</code> (p) on m.project_name_reference = p.id<br>
                • Filter: recipient_id_reference = :vendor_id
            </div>
        </div>
    </div>
</body>
</html>
