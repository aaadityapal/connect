<?php
// Include database connection
include 'config/db_connect.php';

// Set header for HTML output
header('Content-Type: text/html');
echo "<html><head><title>Insert Test Payment</title></head><body>";

try {
    // Get a manager ID
    $managerResult = $conn->query("SELECT id FROM users WHERE role = 'Senior Manager (Studio)' OR role = 'Senior Manager (Site)' LIMIT 1");
    if (!$managerResult || $managerResult->num_rows == 0) {
        throw new Exception("No managers found");
    }
    
    $managerRow = $managerResult->fetch_assoc();
    $managerId = $managerRow['id'];
    
    // Get a project ID
    $projectResult = $conn->query("SELECT id FROM project_payouts LIMIT 1");
    if (!$projectResult || $projectResult->num_rows == 0) {
        throw new Exception("No projects found");
    }
    
    $projectRow = $projectResult->fetch_assoc();
    $projectId = $projectRow['id'];
    
    // Insert test payment
    $amount = 10000; // 10,000
    $paymentDate = date('Y-m-d');
    $paymentMode = 'bank_transfer';
    $notes = 'Test payment for debugging';
    
    $stmt = $conn->prepare("INSERT INTO manager_payments (manager_id, project_id, payment_date, amount, payment_mode, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisdss", $managerId, $projectId, $paymentDate, $amount, $paymentMode, $notes);
    
    if ($stmt->execute()) {
        $paymentId = $conn->insert_id;
        echo "<h2>Test Payment Inserted Successfully</h2>";
        echo "<p>Payment ID: $paymentId</p>";
        echo "<p>Manager ID: $managerId</p>";
        echo "<p>Project ID: $projectId</p>";
        echo "<p>Amount: $amount</p>";
        echo "<p>Date: $paymentDate</p>";
    } else {
        throw new Exception("Failed to insert payment: " . $stmt->error);
    }
    
    // Check if the payment is reflected in the query
    $checkResult = $conn->query("SELECT (SELECT COALESCE(SUM(amount), 0) FROM manager_payments WHERE manager_id = $managerId) as amount_paid");
    $checkRow = $checkResult->fetch_assoc();
    
    echo "<h2>Amount Paid Check</h2>";
    echo "<p>Total Amount Paid for Manager ID $managerId: " . $checkRow['amount_paid'] . "</p>";
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

echo "<p><a href='company_stats.php'>Go to Company Stats</a></p>";
echo "</body></html>";
?> 