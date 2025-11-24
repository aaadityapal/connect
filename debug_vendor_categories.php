<?php
// Debug script to check vendor categories

require_once(__DIR__ . '/config/db_connect.php');

// Check if table exists and has data
$checkTable = "SELECT COUNT(*) as count FROM pm_vendor_registry_master";
$stmt = $pdo->prepare($checkTable);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Total Vendors in pm_vendor_registry_master: " . $result['count'] . "</h2>";

// Get all distinct vendor_type_category values
$query = "SELECT DISTINCT vendor_type_category FROM pm_vendor_registry_master WHERE vendor_type_category IS NOT NULL ORDER BY vendor_type_category ASC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Vendor Type Categories Found:</h3>";
echo "<pre>";
print_r($categories);
echo "</pre>";

// Get sample data
$query = "SELECT vendor_type_category, vendor_category_type FROM pm_vendor_registry_master LIMIT 20";
$stmt = $pdo->prepare($query);
$stmt->execute();
$samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Sample Data:</h3>";
echo "<pre>";
print_r($samples);
echo "</pre>";
?>
