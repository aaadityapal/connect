<?php
// Advanced test to simulate exact API behavior
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db_connect.php';

echo "<h1>Advanced Payment Proof Removal API Simulation</h1>";
echo "<hr>";

function testAPIBehavior($pdo, $payment_id, $scenario_name, $test_data) {
    echo "<h3>Testing: $scenario_name</h3>";
    
    try {
        // Display state before
        echo "<strong>BEFORE:</strong><br>";
        displayPaymentAndSplits($pdo, $payment_id);
        
        // Simulate the exact API logic
        $pdo->beginTransaction();
        
        // Get existing entry (like in the API)
        $check_stmt = $pdo->prepare("SELECT payment_id, payment_proof_image FROM hr_payment_entries WHERE payment_id = ?");
        $check_stmt->execute([$payment_id]);
        $existing_entry = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_entry) {
            throw new Exception('Payment entry not found');
        }
        
        // Extract test parameters
        $payment_mode = $test_data['payment_mode'];
        $remove_current_proof = $test_data['remove_current_proof'] ?? false;
        $has_file_upload = $test_data['has_file_upload'] ?? false;
        $split_amounts_provided = $test_data['split_amounts_provided'] ?? false;
        
        echo "Test Parameters:<br>";
        echo "- Payment Mode: $payment_mode<br>";
        echo "- Remove Current Proof: " . ($remove_current_proof ? 'Yes' : 'No') . "<br>";
        echo "- Has File Upload: " . ($has_file_upload ? 'Yes' : 'No') . "<br>";
        echo "- Split Amounts Provided: " . ($split_amounts_provided ? 'Yes' : 'No') . "<br><br>";
        
        // Handle file upload logic (simulated)
        $new_proof_image = null;
        
        if ($has_file_upload) {
            echo "Simulating file upload...<br>";
            $new_proof_image = 'uploads/payment_proofs/new_proof_' . time() . '.jpg';
            
            if (!empty($existing_entry['payment_proof_image'])) {
                echo "Would delete old file: " . $existing_entry['payment_proof_image'] . "<br>";
            }
        } elseif ($remove_current_proof) {
            echo "Removing current proof...<br>";
            if (!empty($existing_entry['payment_proof_image'])) {
                echo "Would delete file: " . $existing_entry['payment_proof_image'] . "<br>";
            }
            $new_proof_image = null;
        } else {
            echo "Keeping existing proof image<br>";
            $new_proof_image = $existing_entry['payment_proof_image'];
        }
        
        // Update main payment entry
        $update_query = "
            UPDATE hr_payment_entries 
            SET 
                payment_mode = ?,
                payment_proof_image = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE payment_id = ?
        ";
        
        $update_stmt = $pdo->prepare($update_query);
        $update_result = $update_stmt->execute([
            $payment_mode,
            $new_proof_image,
            $payment_id
        ]);
        
        echo "Main payment entry updated<br><br>";
        
        // Handle split payments (exact API logic)
        echo "<strong>Split Payment Processing:</strong><br>";
        
        if ($payment_mode === 'split_payment') {
            echo "Payment mode is 'split_payment'<br>";
            
            if ($split_amounts_provided) {
                echo "Split amounts provided - processing split payment updates...<br>";
                
                // Get existing split payments before deletion (like in API)
                $existing_splits_stmt = $pdo->prepare("SELECT proof_file FROM hr_main_payment_splits WHERE payment_id = ?");
                $existing_splits_stmt->execute([$payment_id]);
                $existing_splits = $existing_splits_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo "Found " . count($existing_splits) . " existing split payments<br>";
                
                // Delete existing split payments
                $delete_splits_stmt = $pdo->prepare("DELETE FROM hr_main_payment_splits WHERE payment_id = ?");
                $delete_splits_stmt->execute([$payment_id]);
                
                echo "Deleted existing split payments<br>";
                
                // Clean up old split proof files
                foreach ($existing_splits as $old_proof_file) {
                    if (!empty($old_proof_file)) {
                        echo "Would delete split proof file: $old_proof_file<br>";
                    }
                }
                
                // Simulate inserting new split payments
                echo "Would insert new split payments...<br>";
                
            } else {
                echo "No split amounts provided - keeping existing split payments<br>";
            }
        } else {
            echo "Payment mode is NOT 'split_payment' - preserving split payments<br>";
        }
        
        $pdo->commit();
        echo "<strong>Transaction committed</strong><br><br>";
        
        // Display state after
        echo "<strong>AFTER:</strong><br>";
        displayPaymentAndSplits($pdo, $payment_id);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
}

function displayPaymentAndSplits($pdo, $payment_id) {
    // Get main payment
    $stmt = $pdo->prepare("SELECT payment_mode, payment_proof_image FROM hr_payment_entries WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        echo "Main Payment Mode: " . $payment['payment_mode'] . "<br>";
        echo "Main Proof Image: " . ($payment['payment_proof_image'] ? $payment['payment_proof_image'] : 'None') . "<br>";
        
        // Get split payments
        $split_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM hr_main_payment_splits WHERE payment_id = ?");
        $split_stmt->execute([$payment_id]);
        $split_count = $split_stmt->fetch(PDO::FETCH_ASSOC);
        
        $split_detail_stmt = $pdo->prepare("SELECT amount, payment_mode, proof_file FROM hr_main_payment_splits WHERE payment_id = ?");
        $split_detail_stmt->execute([$payment_id]);
        $splits = $split_detail_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Split Payments Count: " . $split_count['count'] . "<br>";
        
        if (count($splits) > 0) {
            foreach ($splits as $i => $split) {
                echo "Split " . ($i + 1) . ": ₹" . $split['amount'] . " (" . $split['payment_mode'] . ") - Proof: " . ($split['proof_file'] ? $split['proof_file'] : 'None') . "<br>";
            }
        }
    }
    echo "<br>";
}

// Find or create test data
try {
    $test_payment_id = null;
    
    // Look for existing split payment
    $find_stmt = $pdo->prepare("
        SELECT pe.payment_id 
        FROM hr_payment_entries pe 
        INNER JOIN hr_main_payment_splits ps ON pe.payment_id = ps.payment_id 
        WHERE pe.payment_mode = 'split_payment' 
        LIMIT 1
    ");
    $find_stmt->execute();
    $existing_payment = $find_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_payment) {
        $test_payment_id = $existing_payment['payment_id'];
        echo "<strong>Using existing payment ID: $test_payment_id</strong><br><br>";
    } else {
        // Create test data
        echo "<strong>Creating test data...</strong><br>";
        
        $pdo->beginTransaction();
        
        // Create main payment
        $insert_payment = $pdo->prepare("
            INSERT INTO hr_payment_entries 
            (project_id, payment_date, payment_amount, payment_mode, payment_done_via, payment_proof_image, created_by) 
            VALUES (1, CURDATE(), 1000.00, 'split_payment', 1, 'uploads/payment_proofs/test_main.jpg', 1)
        ");
        $insert_payment->execute();
        $test_payment_id = $pdo->lastInsertId();
        
        // Create split payments
        $insert_split = $pdo->prepare("
            INSERT INTO hr_main_payment_splits 
            (payment_id, amount, payment_mode, proof_file) 
            VALUES (?, ?, ?, ?)
        ");
        
        $insert_split->execute([$test_payment_id, 600.00, 'cash', 'uploads/split_payment_proofs/test_split1.jpg']);
        $insert_split->execute([$test_payment_id, 400.00, 'upi', 'uploads/split_payment_proofs/test_split2.jpg']);
        
        $pdo->commit();
        
        echo "Created test payment ID: $test_payment_id<br><br>";
    }
    
    // Test different scenarios
    
    // Scenario 1: Remove main proof only (most common user case)
    testAPIBehavior($pdo, $test_payment_id, "Remove Main Proof Only", [
        'payment_mode' => 'split_payment',
        'remove_current_proof' => true,
        'has_file_upload' => false,
        'split_amounts_provided' => false  // This is key - user is NOT updating splits
    ]);
    
    // Restore test data for next test
    $pdo->prepare("UPDATE hr_payment_entries SET payment_proof_image = 'uploads/payment_proofs/test_main.jpg' WHERE payment_id = ?")->execute([$test_payment_id]);
    
    // Add debug test to see what parameters are actually being sent
    echo "<h3>DEBUG: Testing Parameter Detection</h3>";
    
    // Test what happens when form data is submitted like the real frontend
    $_POST = [
        'payment_id' => $test_payment_id,
        'project_id' => 1,
        'payment_date' => date('Y-m-d'),
        'payment_amount' => 1000.00,
        'payment_mode' => 'split_payment',
        'payment_done_via' => 1,
        'remove_current_proof' => 'true'
        // Note: split_amounts is NOT provided when just removing main proof
    ];
    
    // Simulate the exact logic from the API
    $input_data = $_POST;
    $has_file_upload = isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] !== UPLOAD_ERR_NO_FILE;
    $payment_mode = $input_data['payment_mode'];
    $remove_current_proof = isset($input_data['remove_current_proof']) && $input_data['remove_current_proof'] === 'true';
    
    echo "Input Data Analysis:<br>";
    echo "- payment_mode: $payment_mode<br>";
    echo "- remove_current_proof: " . ($remove_current_proof ? 'true' : 'false') . "<br>";
    echo "- has_file_upload: " . ($has_file_upload ? 'true' : 'false') . "<br>";
    echo "- split_amounts isset: " . (isset($input_data['split_amounts']) ? 'true' : 'false') . "<br>";
    echo "- split_amounts is_array: " . (isset($input_data['split_amounts']) && is_array($input_data['split_amounts']) ? 'true' : 'false') . "<br>";
    
    // Test the exact condition from our API
    if ($payment_mode === 'split_payment') {
        echo "<strong>Payment mode is split_payment</strong><br>";
        if (isset($input_data['split_amounts']) && is_array($input_data['split_amounts'])) {
            echo "<span style='color: red;'>❌ WOULD DELETE split payments (split_amounts provided)</span><br>";
        } else {
            echo "<span style='color: green;'>✅ Would PRESERVE split payments (no split_amounts provided)</span><br>";
        }
    } else {
        echo "Payment mode is NOT split_payment - would preserve split payments<br>";
    }
    
    echo "<hr>";
    
    // Clean up test POST data
    $_POST = [];
    
    // Scenario 2: Update split payments
    testAPIBehavior($pdo, $test_payment_id, "Update Split Payments", [
        'payment_mode' => 'split_payment',
        'remove_current_proof' => false,
        'has_file_upload' => false,
        'split_amounts_provided' => true  // User IS updating splits
    ]);
    
    // Scenario 3: Upload new main proof
    testAPIBehavior($pdo, $test_payment_id, "Upload New Main Proof", [
        'payment_mode' => 'split_payment',
        'remove_current_proof' => false,
        'has_file_upload' => true,
        'split_amounts_provided' => false  // User is NOT updating splits
    ]);
    
    echo "<h3>Summary</h3>";
    echo "The test shows exactly what happens in each scenario.<br>";
    echo "If split payments are being deleted in Scenario 1, that's the bug.<br>";
    echo "Split payments should only be deleted in Scenario 2.<br>";
    
} catch (Exception $e) {
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h3 { color: #333; }
hr { border: 1px solid #ccc; margin: 20px 0; }
strong { color: #d63384; }
</style>