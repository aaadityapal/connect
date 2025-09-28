<?php
// Demo file to test the resubmission functionality
// This file shows how to query resubmission information

include_once('includes/db_connect.php');

// Function to display resubmission information for an expense
function displayResubmissionInfo($conn, $expense_id) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            purpose,
            status,
            created_at,
            resubmission_count,
            is_resubmitted,
            original_expense_id,
            resubmitted_from,
            resubmission_date,
            max_resubmissions,
            CASE 
                WHEN resubmission_count >= max_resubmissions THEN 1 
                ELSE 0 
            END as max_reached
        FROM travel_expenses 
        WHERE id = ?
    ");
    
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "<h3>Expense ID: {$row['id']}</h3>";
        echo "<p><strong>Purpose:</strong> {$row['purpose']}</p>";
        echo "<p><strong>Status:</strong> {$row['status']}</p>";
        echo "<p><strong>Created:</strong> {$row['created_at']}</p>";
        echo "<p><strong>Resubmission Count:</strong> {$row['resubmission_count']}</p>";
        echo "<p><strong>Is Resubmitted:</strong> " . ($row['is_resubmitted'] ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Max Resubmissions:</strong> {$row['max_resubmissions']}</p>";
        echo "<p><strong>Max Reached:</strong> " . ($row['max_reached'] ? 'Yes' : 'No') . "</p>";
        
        if ($row['original_expense_id']) {
            echo "<p><strong>Original Expense ID:</strong> {$row['original_expense_id']}</p>";
        }
        
        if ($row['resubmitted_from']) {
            echo "<p><strong>Resubmitted From:</strong> {$row['resubmitted_from']}</p>";
            echo "<p><strong>Resubmission Date:</strong> {$row['resubmission_date']}</p>";
        }
        
        // Show resubmission history
        echo "<h4>Resubmission History:</h4>";
        $root_id = $row['original_expense_id'] ?: $row['id'];
        
        $history_stmt = $conn->prepare("
            SELECT id, purpose, status, created_at, resubmission_count
            FROM travel_expenses 
            WHERE original_expense_id = ? OR id = ?
            ORDER BY created_at ASC
        ");
        
        $history_stmt->bind_param("ii", $root_id, $root_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        
        echo "<ul>";
        while ($history_row = $history_result->fetch_assoc()) {
            $type = ($history_row['resubmission_count'] == 0) ? 'Original' : "Resubmission #{$history_row['resubmission_count']}";
            echo "<li>{$type} - ID: {$history_row['id']}, Status: {$history_row['status']}, Created: {$history_row['created_at']}</li>";
        }
        echo "</ul>";
        
        $history_stmt->close();
    } else {
        echo "<p>Expense not found.</p>";
    }
    
    $stmt->close();
}

// Get all expenses for demonstration
$stmt = $conn->prepare("SELECT id, purpose, status, resubmission_count FROM travel_expenses ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Recent Travel Expenses (Last 10)</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Purpose</th><th>Status</th><th>Resubmission Count</th><th>Actions</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['purpose']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td>{$row['resubmission_count']}</td>";
    echo "<td><a href='?expense_id={$row['id']}'>View Details</a></td>";
    echo "</tr>";
}

echo "</table>";

// Show details for selected expense
if (isset($_GET['expense_id'])) {
    echo "<hr>";
    displayResubmissionInfo($conn, $_GET['expense_id']);
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Resubmission Demo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { margin: 20px 0; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Travel Expense Resubmission Demo</h1>
    <p>This page demonstrates the resubmission tracking functionality.</p>
    
    <h3>Features Implemented:</h3>
    <ul>
        <li>✅ Track resubmission count (max 3 by default)</li>
        <li>✅ Display resubmission badges in expense listings</li>
        <li>✅ Disable resubmit button when limit reached</li>
        <li>✅ Show remaining resubmissions</li>
        <li>✅ Link resubmitted expenses to original</li>
        <li>✅ Enhanced success messages</li>
    </ul>
    
    <p><a href="view_travel_expenses.php">← Back to Travel Expenses</a></p>
</body>
</html>