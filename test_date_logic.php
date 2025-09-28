<?php
// Test the date logic for resubmission
function canResubmit($expense) {
    if ($expense['status'] !== 'rejected') {
        return false;
    }
    
    $current_count = intval($expense['resubmission_count']);
    $max_allowed = intval($expense['max_resubmissions']);
    
    // Check if the expense is within 15 days from present date
    $travel_date = new DateTime($expense['travel_date']);
    $current_date = new DateTime();
    $date_diff = $current_date->diff($travel_date)->days;
    
    // If the expense is older than 15 days, don't allow resubmission
    if ($date_diff > 15) {
        return false;
    }
    
    return $current_count < $max_allowed;
}

function isExpenseTooOld($expense) {
    $travel_date = new DateTime($expense['travel_date']);
    $current_date = new DateTime();
    $date_diff = $current_date->diff($travel_date)->days;
    
    return $date_diff > 15;
}

function getDaysSinceTravelDate($expense) {
    $travel_date = new DateTime($expense['travel_date']);
    $current_date = new DateTime();
    return $current_date->diff($travel_date)->days;
}

// Test scenarios
$test_expenses = [
    [
        'id' => 1,
        'travel_date' => date('Y-m-d', strtotime('-5 days')), // 5 days ago
        'status' => 'rejected',
        'resubmission_count' => 0,
        'max_resubmissions' => 3
    ],
    [
        'id' => 2,
        'travel_date' => date('Y-m-d', strtotime('-10 days')), // 10 days ago
        'status' => 'rejected',
        'resubmission_count' => 1,
        'max_resubmissions' => 3
    ],
    [
        'id' => 3,
        'travel_date' => date('Y-m-d', strtotime('-20 days')), // 20 days ago (too old)
        'status' => 'rejected',
        'resubmission_count' => 0,
        'max_resubmissions' => 3
    ],
    [
        'id' => 4,
        'travel_date' => date('Y-m-d', strtotime('-8 days')), // 8 days ago
        'status' => 'rejected',
        'resubmission_count' => 3,
        'max_resubmissions' => 3
    ]
];

echo "<h1>Travel Expense Resubmission Test</h1>";
echo "<p>Current Date: " . date('Y-m-d') . "</p>";

foreach ($test_expenses as $expense) {
    echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
    echo "<h3>Expense ID: " . $expense['id'] . "</h3>";
    echo "<p>Travel Date: " . $expense['travel_date'] . "</p>";
    echo "<p>Days Since Travel: " . getDaysSinceTravelDate($expense) . "</p>";
    echo "<p>Status: " . $expense['status'] . "</p>";
    echo "<p>Resubmission Count: " . $expense['resubmission_count'] . " / " . $expense['max_resubmissions'] . "</p>";
    
    echo "<p><strong>Results:</strong></p>";
    echo "<ul>";
    echo "<li>Can Resubmit: " . (canResubmit($expense) ? 'YES' : 'NO') . "</li>";
    echo "<li>Is Too Old: " . (isExpenseTooOld($expense) ? 'YES' : 'NO') . "</li>";
    
    if (canResubmit($expense)) {
        echo "<li style='color: green;'>✓ Resubmit button should be ENABLED</li>";
    } elseif (isExpenseTooOld($expense)) {
        echo "<li style='color: orange;'>⏰ Should show 'Too Old to Resubmit'</li>";
    } else {
        echo "<li style='color: red;'>❌ Should show 'Max Resubmissions Reached'</li>";
    }
    
    echo "</ul>";
    echo "</div>";
}
?>