<?php
/**
 * Test file to debug payment entry update issues
 * This file tests the exact queries that are failing
 */

session_start();

// Mock user session if not exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

header('Content-Type: application/json');

try {
    // Include database connection
    require_once __DIR__ . '/../config/db_connect.php';
    
    // Test with sample data
    $payment_entry_id = 35; // Use actual entry that exists
    $payment_amount = 1000;
    $totalAcceptanceMethods = 0;
    $totalLineItems = 300;
    $acceptanceCount = 0;
    $lineItemsCount = 2;
    
    $grandTotal = $payment_amount + $totalAcceptanceMethods + $totalLineItems;
    
    echo json_encode([
        'step' => 'Preparation',
        'payment_entry_id' => $payment_entry_id,
        'payment_amount' => $payment_amount,
        'totalAcceptanceMethods' => $totalAcceptanceMethods,
        'totalLineItems' => $totalLineItems,
        'grandTotal' => $grandTotal,
        'acceptanceCount' => $acceptanceCount,
        'lineItemsCount' => $lineItemsCount
    ], JSON_PRETTY_PRINT);
    echo "\n\n";
    
    // Test 1: Check if summary table entry exists
    echo "=== TEST 1: Check Summary Table Entry ===\n";
    $checkSummaryQuery = "
        SELECT * FROM tbl_payment_entry_summary_totals
        WHERE payment_entry_master_id_fk = :payment_entry_id
    ";
    
    $checkStmt = $pdo->prepare($checkSummaryQuery);
    $checkStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $checkStmt->execute();
    $summaryExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Summary exists: " . ($summaryExists ? "YES" : "NO") . "\n";
    echo json_encode($summaryExists, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 2: Try the INSERT ... ON DUPLICATE KEY UPDATE
    echo "=== TEST 2: INSERT ... ON DUPLICATE KEY UPDATE ===\n";
    
    $recalculateTotalsQuery = "
        INSERT INTO tbl_payment_entry_summary_totals (
            payment_entry_master_id_fk,
            total_amount_main_payment,
            total_amount_acceptance_methods,
            total_amount_line_items,
            total_amount_grand_aggregate,
            acceptance_methods_count,
            line_items_count,
            summary_calculated_timestamp
        ) VALUES (
            :payment_entry_id,
            :grand_total,
            :total_acceptance,
            :total_line_items,
            :grand_total_agg,
            :acceptance_count,
            :line_items_count,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            total_amount_main_payment = VALUES(total_amount_main_payment),
            total_amount_acceptance_methods = VALUES(total_amount_acceptance_methods),
            total_amount_line_items = VALUES(total_amount_line_items),
            total_amount_grand_aggregate = VALUES(total_amount_grand_aggregate),
            acceptance_methods_count = VALUES(acceptance_methods_count),
            line_items_count = VALUES(line_items_count),
            summary_calculated_timestamp = NOW()
    ";
    
    echo "Query prepared successfully\n";
    
    $recalcStmt = $pdo->prepare($recalculateTotalsQuery);
    echo "Statement prepared\n";
    
    // Bind all values
    $recalcStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    echo "Bound :payment_entry_id\n";
    
    $recalcStmt->bindValue(':grand_total', $grandTotal, PDO::PARAM_STR);
    echo "Bound :grand_total\n";
    
    $recalcStmt->bindValue(':total_acceptance', $totalAcceptanceMethods, PDO::PARAM_STR);
    echo "Bound :total_acceptance\n";
    
    $recalcStmt->bindValue(':total_line_items', $totalLineItems, PDO::PARAM_STR);
    echo "Bound :total_line_items\n";
    
    $recalcStmt->bindValue(':grand_total_agg', $grandTotal, PDO::PARAM_STR);
    echo "Bound :grand_total_agg\n";
    
    $recalcStmt->bindValue(':acceptance_count', $acceptanceCount, PDO::PARAM_INT);
    echo "Bound :acceptance_count\n";
    
    $recalcStmt->bindValue(':line_items_count', $lineItemsCount, PDO::PARAM_INT);
    echo "Bound :line_items_count\n";
    
    echo "All values bound successfully\n";
    
    if ($recalcStmt->execute()) {
        echo "Query executed successfully\n";
        echo "Rows affected: " . $recalcStmt->rowCount() . "\n";
    } else {
        echo "Query failed\n";
        echo "Error Info: " . json_encode($recalcStmt->errorInfo(), JSON_PRETTY_PRINT) . "\n";
    }
    
    echo "\n=== TEST 3: Verify Update ===\n";
    $verifyStmt = $pdo->prepare("
        SELECT * FROM tbl_payment_entry_summary_totals
        WHERE payment_entry_master_id_fk = :payment_entry_id
    ");
    $verifyStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $verifyStmt->execute();
    $updated = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($updated, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
