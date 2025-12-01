<?php
/**
 * Test Payment Records Query
 * 
 * Run this file to test vendor payment record queries
 * URL: http://localhost/connect/test_payment_records.php?vendor_id=1
 */

session_start();

// Get database connection
require_once 'config/db_connect.php';

// Get vendor ID from request or use test value
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 1;

echo "<h1>Payment Records Test</h1>";
echo "<p>Testing vendor ID: <strong>" . $vendor_id . "</strong></p>";

// First, let's check the database tables
echo "<h2>Database Tables Check</h2>";

$tables = [
    'tbl_payment_entry_master_records',
    'tbl_payment_entry_line_items_detail',
    'pm_vendor_registry_master',
    'tbl_payment_acceptance_methods_line_items'
];

foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM $table");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>$table: <strong>" . $result['cnt'] . " rows</strong></p>";
}

// Test Query 1: Search in line items
echo "<h2>Query 1: Line Items with Vendor as Recipient</h2>";

$query1 = "
    SELECT 
        l.line_item_entry_id,
        l.recipient_id_reference,
        l.recipient_type_category,
        m.payment_entry_id,
        m.project_name_reference,
        m.payment_amount_base
    FROM 
        tbl_payment_entry_line_items_detail l
    INNER JOIN
        tbl_payment_entry_master_records m ON m.payment_entry_id = l.payment_entry_master_id_fk
    WHERE 
        l.recipient_id_reference = :vendor_id
    LIMIT 10
";

try {
    $stmt = $pdo->prepare($query1);
    $stmt->execute([':vendor_id' => $vendor_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found <strong>" . count($results) . " records</strong></p>";
    
    if (count($results) > 0) {
        echo "<pre>";
        print_r($results);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>No records found with recipient_id_reference = $vendor_id</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test Query 2: Check all recipient types in line items
echo "<h2>Query 2: Sample Recipient Types in Database</h2>";

$query2 = "
    SELECT DISTINCT 
        recipient_id_reference,
        recipient_type_category
    FROM 
        tbl_payment_entry_line_items_detail
    LIMIT 20
";

try {
    $stmt = $pdo->prepare($query2);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Recipient ID</th><th>Recipient Type</th></tr>";
    foreach ($results as $row) {
        echo "<tr><td>" . $row['recipient_id_reference'] . "</td><td>" . $row['recipient_type_category'] . "</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test Query 3: Check vendor existence
echo "<h2>Query 3: Check Vendor in Registry</h2>";

$query3 = "
    SELECT 
        vendor_registry_id,
        vendor_name_primary
    FROM 
        pm_vendor_registry_master
    WHERE 
        vendor_registry_id = :vendor_id
";

try {
    $stmt = $pdo->prepare($query3);
    $stmt->execute([':vendor_id' => $vendor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<p><strong>Vendor Found:</strong> " . $result['vendor_name_primary'] . "</p>";
    } else {
        echo "<p style='color: orange;'>Vendor ID $vendor_id not found in vendor registry</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test Query 4: Complete joined query (without WHERE clause) to see structure
echo "<h2>Query 4: Sample Payment Records (First 5)</h2>";

$query4 = "
    SELECT 
        m.payment_entry_id,
        m.project_name_reference,
        m.payment_amount_base,
        l.line_item_entry_id,
        l.recipient_id_reference,
        l.recipient_type_category
    FROM 
        tbl_payment_entry_master_records m
    INNER JOIN 
        tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
    LIMIT 5
";

try {
    $stmt = $pdo->prepare($query4);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Payment ID</th><th>Project</th><th>Amount</th><th>Recipient ID</th><th>Recipient Type</th></tr>";
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>" . $row['payment_entry_id'] . "</td>";
        echo "<td>" . $row['project_name_reference'] . "</td>";
        echo "<td>" . $row['payment_amount_base'] . "</td>";
        echo "<td>" . $row['recipient_id_reference'] . "</td>";
        echo "<td>" . $row['recipient_type_category'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

?>

<hr>
<p><small>This is a diagnostic test file. Safe to keep or delete.</small></p>
