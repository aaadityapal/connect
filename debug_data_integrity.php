<?php
require_once 'config/db_connect.php';

header('Content-Type: text/plain');

echo "Debug Analysis: Vendor Payment Linkages\n";
echo "=======================================\n\n";

// 1. Get List of Vendors and their IDs
$vendors_query = "SELECT vendor_id, vendor_full_name FROM pm_vendor_registry_master";
$stmt = $pdo->query($vendors_query);
$vendors = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ID => Name

echo "Checking top 20 vendors with payment discrepancies...\n\n";

// 2. For each vendor, check:
//    A. How many line items are linked to their ID
//    B. How many line items have their Name but NOT their ID
foreach ($vendors as $id => $name) {
    if (empty($name))
        continue;

    // A. Linked by ID
    $q1 = "SELECT COUNT(*) FROM tbl_payment_entry_line_items_detail WHERE recipient_id_reference = ?";
    $s1 = $pdo->prepare($q1);
    $s1->execute([$id]);
    $count_linked = $s1->fetchColumn();

    // B. Linked by Name (fuzzy match) but ID is NOT this ID
    $q2 = "SELECT COUNT(*) FROM tbl_payment_entry_line_items_detail WHERE recipient_name_display LIKE ? AND (recipient_id_reference != ? OR recipient_id_reference IS NULL)";
    $s2 = $pdo->prepare($q2);
    $s2->execute([$name, $id]);
    $count_unlinked = $s2->fetchColumn();

    if ($count_linked > 0 || $count_unlinked > 0) {
        echo "Vendor: $name (ID: $id)\n";
        echo "   - Linked Records (Good): $count_linked\n";
        echo "   - Unlinked/Orphaned Named Records (Bad): $count_unlinked\n";
        if ($count_unlinked > 0) {
            echo "     [!] Potential missing records found! These rows have the vendor's name but not their ID.\n";
        }
        echo "---------------------------------------\n";
    }
}
?>