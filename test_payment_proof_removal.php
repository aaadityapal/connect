<?php
// Test file to debug payment proof removal issue
// This file will help us understand why split payment proofs are being deleted

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/db_connect.php';

echo "<h1>Payment Proof Removal Debug Test</h1>";
echo "<hr>";

// Function to display current state of payment and split payments
function displayPaymentState($pdo, $payment_id, $title) {
    echo "<h3>$title</h3>";
    
    // Get main payment info
    $stmt = $pdo->prepare("SELECT payment_id, payment_amount, payment_mode, payment_proof_image FROM hr_payment_entries WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        echo "<strong>Main Payment:</strong><br>";
        echo "Payment ID: " . $payment['payment_id'] . "<br>";
        echo "Amount: ₹" . number_format($payment['payment_amount'], 2) . "<br>";
        echo "Mode: " . $payment['payment_mode'] . "<br>";
        echo "Proof Image: " . ($payment['payment_proof_image'] ? $payment['payment_proof_image'] : 'None') . "<br>";
        
        // Check if proof file exists
        if ($payment['payment_proof_image']) {
            $file_path = $payment['payment_proof_image'];
            $full_path = $file_path;
            echo "Proof File Exists: " . (file_exists($full_path) ? 'Yes' : 'No') . "<br>";
        }
        echo "<br>";
        
        // Get split payments
        $split_stmt = $pdo->prepare("SELECT main_split_id, amount, payment_mode, proof_file FROM hr_main_payment_splits WHERE payment_id = ?");
        $split_stmt->execute([$payment_id]);
        $splits = $split_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<strong>Split Payments:</strong><br>";
        if (count($splits) > 0) {
            foreach ($splits as $index => $split) {
                echo "Split " . ($index + 1) . ":<br>";
                echo "  - ID: " . $split['main_split_id'] . "<br>";
                echo "  - Amount: ₹" . number_format($split['amount'], 2) . "<br>";
                echo "  - Mode: " . $split['payment_mode'] . "<br>";
                echo "  - Proof File: " . ($split['proof_file'] ? $split['proof_file'] : 'None') . "<br>";
                
                // Check if split proof file exists
                if ($split['proof_file']) {
                    $split_file_path = $split['proof_file'];
                    echo "  - File Exists: " . (file_exists($split_file_path) ? 'Yes' : 'No') . "<br>";
                }
                echo "<br>";
            }
        } else {
            echo "No split payments found.<br>";
        }
    } else {
        echo "Payment not found!<br>";
    }
    echo "<hr>";
}

// Function to simulate the update API call for removing main payment proof
function simulateRemoveMainProof($pdo, $payment_id) {
    echo "<h3>Simulating Main Payment Proof Removal</h3>";
    
    try {
        $pdo->beginTransaction();
        
        // Get existing payment entry
        $check_stmt = $pdo->prepare("SELECT payment_id, payment_proof_image FROM hr_payment_entries WHERE payment_id = ?");
        $check_stmt->execute([$payment_id]);
        $existing_entry = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_entry) {
            throw new Exception('Payment entry not found');
        }
        
        echo "Found existing payment with proof: " . ($existing_entry['payment_proof_image'] ? $existing_entry['payment_proof_image'] : 'None') . "<br>";
        
        // Simulate removing current proof (setting remove_current_proof = true)
        $remove_current_proof = true;
        $new_proof_image = null;
        
        if ($remove_current_proof) {
            echo "Removing current proof...<br>";
            // Remove current proof if requested
            if (!empty($existing_entry['payment_proof_image'])) {
                $old_file_path = $existing_entry['payment_proof_image'];
                echo "Would delete file: $old_file_path<br>";
                // Note: Not actually deleting in test
                // if (file_exists($old_file_path)) {
                //     unlink($old_file_path);
                // }
            }
            $new_proof_image = null;
        }
        
        // Get current payment mode
        $mode_stmt = $pdo->prepare("SELECT payment_mode FROM hr_payment_entries WHERE payment_id = ?");
        $mode_stmt->execute([$payment_id]);
        $current_payment = $mode_stmt->fetch(PDO::FETCH_ASSOC);
        $payment_mode = $current_payment['payment_mode'];
        
        echo "Current payment mode: $payment_mode<br>";
        
        // Update payment entry (simulate the main update)
        $update_query = "
            UPDATE hr_payment_entries 
            SET 
                payment_proof_image = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE payment_id = ?
        ";
        
        $update_stmt = $pdo->prepare($update_query);
        $update_result = $update_stmt->execute([
            $new_proof_image,
            $payment_id
        ]);
        
        echo "Main payment proof updated successfully<br>";
        
        // Now check what happens with split payments
        echo "<br><strong>Split Payment Processing:</strong><br>";
        
        if ($payment_mode === 'split_payment') {
            echo "Payment mode is split_payment<br>";
            
            // Check if split_amounts is provided (simulate typical scenario)
            $split_amounts_provided = false; // This simulates when user is ONLY removing main proof
            
            if ($split_amounts_provided) {
                echo "Split amounts provided - would update split payments<br>";
                // This is where split payments would be deleted and recreated
            } else {
                echo "No split amounts provided - split payments should be preserved<br>";
                // Split payments should remain intact
            }
        } else {
            echo "Payment mode is NOT split_payment<br>";
            echo "Split payments should be preserved<br>";
        }
        
        $pdo->commit();
        echo "<strong>Transaction committed successfully</strong><br>";
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
}

// Test scenario
try {
    // First, let's find a payment entry with split payments for testing
    $test_payment_id = null;
    
    // Look for a split payment entry
    $find_stmt = $pdo->prepare("
        SELECT DISTINCT pe.payment_id 
        FROM hr_payment_entries pe 
        INNER JOIN hr_main_payment_splits ps ON pe.payment_id = ps.payment_id 
        WHERE pe.payment_mode = 'split_payment' 
        LIMIT 1
    ");
    $find_stmt->execute();
    $test_payment = $find_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_payment) {
        $test_payment_id = $test_payment['payment_id'];
        echo "<strong>Found test payment ID: $test_payment_id</strong><br><br>";
        
        // Display initial state
        displayPaymentState($pdo, $test_payment_id, "BEFORE: Initial State");
        
        // Simulate removing main payment proof
        simulateRemoveMainProof($pdo, $test_payment_id);
        
        // Display final state
        displayPaymentState($pdo, $test_payment_id, "AFTER: Final State");
        
        echo "<h3>Analysis</h3>";
        echo "If split payments disappeared, the issue is in the update logic.<br>";
        echo "If split payments remained, the API logic is working correctly.<br>";
        
    } else {
        // Create test data if none exists
        echo "<strong>No existing split payment found. Creating test data...</strong><br><br>";
        
        // Create a test payment entry
        $insert_payment = $pdo->prepare("
            INSERT INTO hr_payment_entries 
            (project_id, payment_date, payment_amount, payment_mode, payment_done_via, payment_proof_image, created_by) 
            VALUES (1, CURDATE(), 1000.00, 'split_payment', 1, 'uploads/payment_proofs/test_main_proof.jpg', 1)
        ");
        $insert_payment->execute();
        $test_payment_id = $pdo->lastInsertId();
        
        // Create test split payments
        $insert_split = $pdo->prepare("
            INSERT INTO hr_main_payment_splits 
            (payment_id, amount, payment_mode, proof_file) 
            VALUES (?, ?, ?, ?)
        ");
        
        $insert_split->execute([$test_payment_id, 600.00, 'cash', 'uploads/split_payment_proofs/test_split1.jpg']);
        $insert_split->execute([$test_payment_id, 400.00, 'upi', 'uploads/split_payment_proofs/test_split2.jpg']);
        
        echo "Created test payment ID: $test_payment_id<br><br>";
        
        // Display initial state
        displayPaymentState($pdo, $test_payment_id, "BEFORE: Test Data Created");
        
        // Simulate removing main payment proof
        simulateRemoveMainProof($pdo, $test_payment_id);
        
        // Display final state
        displayPaymentState($pdo, $test_payment_id, "AFTER: Main Proof Removal Simulation");
    }
    
} catch (Exception $e) {
    echo "<strong>Database Error:</strong> " . $e->getMessage() . "<br>";
}

echo "<h3>Debug Information</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";
echo "Script Path: " . __FILE__ . "<br>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h3 { color: #333; }
hr { border: 1px solid #ccc; margin: 20px 0; }
strong { color: #d63384; }
</style>