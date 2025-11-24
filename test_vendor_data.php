<?php
require_once(__DIR__ . '/config/db_connect.php');

// Check table structure
echo "<h2>Table Structure:</h2>";
$result = $pdo->query("DESCRIBE pm_vendor_registry_master");
$columns = $result->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($columns);
echo "</pre>";

// Get sample data
echo "<h2>Sample Data (First 5 rows):</h2>";
$result = $pdo->query("SELECT * FROM pm_vendor_registry_master LIMIT 5");
$samples = $result->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($samples);
echo "</pre>";

// Count by vendor_type_category
echo "<h2>Count by vendor_type_category:</h2>";
$result = $pdo->query("SELECT vendor_type_category, COUNT(*) as count FROM pm_vendor_registry_master GROUP BY vendor_type_category");
$counts = $result->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($counts);
echo "</pre>";

// Get distinct combinations
echo "<h2>Distinct vendor_type_category, vendor_category_type pairs:</h2>";
$result = $pdo->query("SELECT DISTINCT vendor_type_category, vendor_category_type FROM pm_vendor_registry_master ORDER BY vendor_type_category");
$pairs = $result->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($pairs);
echo "</pre>";
?>
