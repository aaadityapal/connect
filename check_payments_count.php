<?php
// Include database connection
include 'config/db_connect.php';

// Check if there are any records in the manager_payments table
$result = $conn->query("SELECT COUNT(*) as count FROM manager_payments");
$row = $result->fetch_assoc();
echo "Number of records in manager_payments table: " . $row['count'] . "\n";

// Check if there are any records with amount_paid in get_managers.php result
$result = $conn->query("SELECT u.id, u.first_name, u.last_name, 
                      (SELECT COALESCE(SUM(amount), 0) FROM manager_payments WHERE manager_id = u.id) as amount_paid
                      FROM users u
                      WHERE u.role = 'Senior Manager (Studio)' OR u.role = 'Senior Manager (Site)'
                      LIMIT 1");
$row = $result->fetch_assoc();
echo "Manager ID: " . $row['id'] . "\n";
echo "Manager Name: " . $row['first_name'] . " " . $row['last_name'] . "\n";
echo "Amount Paid: " . $row['amount_paid'] . "\n";

// Insert a test payment if none exist
if ($row['amount_paid'] == 0) {
    echo "\nNo payments found. Inserting a test payment...\n";
    
    // Get a valid project_id from project_payouts
    $projectResult = $conn->query("SELECT id FROM project_payouts LIMIT 1");
    if ($projectResult && $projectResult->num_rows > 0) {
        $projectRow = $projectResult->fetch_assoc();
        $project_id = $projectRow['id'];
        
        // Insert test payment
        $stmt = $conn->prepare("INSERT INTO manager_payments (manager_id, project_id, payment_date, amount, payment_mode, notes) 
                              VALUES (?, ?, CURDATE(), 5000, 'bank_transfer', 'Test payment')");
        $stmt->bind_param("ii", $row['id'], $project_id);
        
        if ($stmt->execute()) {
            echo "Test payment inserted successfully!\n";
        } else {
            echo "Error inserting test payment: " . $stmt->error . "\n";
        }
    } else {
        echo "No projects found to create test payment.\n";
    }
}
?> 