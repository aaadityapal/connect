<?php
// Debug script to check payment entry categories
require_once 'config/db_connect.php';

echo "<h1>Payment Entry Category Debug</h1>";

try {
    // Get the latest payment entries with recipients
    $sql = "SELECT 
                pe.payment_id,
                pe.project_type,
                pe.payment_date,
                pe.payment_amount,
                pe.recipient_count,
                pr.recipient_id,
                pr.category,
                pr.type,
                pr.custom_type,
                pr.name,
                pr.payment_for,
                pr.amount as recipient_amount
            FROM hr_payment_entries pe
            LEFT JOIN hr_payment_recipients pr ON pe.payment_id = pr.payment_id
            ORDER BY pe.payment_id DESC, pr.recipient_id ASC
            LIMIT 20";
    
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Recent Payment Entries and Recipients:</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Payment ID</th>";
    echo "<th>Recipient ID</th>";
    echo "<th>Category (Raw)</th>";
    echo "<th>Type (Raw)</th>";
    echo "<th>Custom Type</th>";
    echo "<th>Name</th>";
    echo "<th>Payment For</th>";
    echo "<th>Amount</th>";
    echo "</tr>";
    
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['payment_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['recipient_id'] ?? 'N/A') . "</td>";
        echo "<td style='background-color: " . ($row['category'] == 'vendor' ? '#ffcccc' : ($row['category'] == 'labour' ? '#ccffcc' : '#ccccff')) . ";'>" . htmlspecialchars($row['category'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['type'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['custom_type'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['name'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['payment_for'] ?? 'NULL') . "</td>";
        echo "<td>₹" . number_format($row['recipient_amount'] ?? 0, 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check the categories distribution
    echo "<h2>Category Distribution:</h2>";
    $categorySql = "SELECT category, COUNT(*) as count FROM hr_payment_recipients GROUP BY category";
    $categoryStmt = $pdo->query($categorySql);
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Category</th>";
    echo "<th>Count</th>";
    echo "</tr>";
    
    foreach ($categories as $cat) {
        echo "<tr>";
        echo "<td style='background-color: " . ($cat['category'] == 'vendor' ? '#ffcccc' : ($cat['category'] == 'labour' ? '#ccffcc' : '#ccccff')) . ";'>" . htmlspecialchars($cat['category']) . "</td>";
        echo "<td>" . $cat['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check specific payment entry that's showing the issue
    echo "<h2>Check Latest Payment Entry Recipients:</h2>";
    $latestSql = "SELECT payment_id FROM hr_payment_entries ORDER BY payment_id DESC LIMIT 1";
    $latestStmt = $pdo->query($latestSql);
    $latestPayment = $latestStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latestPayment) {
        $paymentId = $latestPayment['payment_id'];
        echo "<p><strong>Latest Payment ID: $paymentId</strong></p>";
        
        $recipientsSql = "SELECT * FROM hr_payment_recipients WHERE payment_id = :payment_id ORDER BY recipient_id";
        $recipientsStmt = $pdo->prepare($recipientsSql);
        $recipientsStmt->execute([':payment_id' => $paymentId]);
        $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Field</th>";
        foreach ($recipients as $index => $recipient) {
            echo "<th>Recipient " . ($index + 1) . "</th>";
        }
        echo "</tr>";
        
        $fields = ['recipient_id', 'category', 'type', 'custom_type', 'entity_id', 'name', 'payment_for', 'amount', 'payment_mode'];
        
        foreach ($fields as $field) {
            echo "<tr>";
            echo "<td style='background-color: #f9f9f9; font-weight: bold;'>" . ucfirst(str_replace('_', ' ', $field)) . "</td>";
            foreach ($recipients as $recipient) {
                $value = $recipient[$field] ?? 'NULL';
                $bgColor = '';
                if ($field == 'category') {
                    $bgColor = ($value == 'vendor' ? '#ffcccc' : ($value == 'labour' ? '#ccffcc' : '#ccccff'));
                }
                echo "<td style='background-color: $bgColor;'>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test the display logic
    echo "<h2>Display Logic Test:</h2>";
    if ($latestPayment && !empty($recipients)) {
        foreach ($recipients as $index => $recipient) {
            echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
            echo "<h3>Recipient " . ($index + 1) . ":</h3>";
            echo "<p><strong>Raw Category:</strong> " . htmlspecialchars($recipient['category']) . "</p>";
            echo "<p><strong>Raw Type:</strong> " . htmlspecialchars($recipient['type']) . "</p>";
            
            // Apply the same logic as in get_payment_entry_details.php
            if ($recipient['category'] == 'vendor') {
                $display_category = 'Vendor';
            } elseif ($recipient['category'] == 'labour') {
                $display_category = 'Labour';
            } else {
                $display_category = ucwords(str_replace('_', ' ', $recipient['category']));
            }
            
            $display_type = ucwords(str_replace('_', ' ', $recipient['type']));
            
            echo "<p><strong>Display Category:</strong> <span style='background-color: yellow;'>" . htmlspecialchars($display_category) . "</span></p>";
            echo "<p><strong>Display Type:</strong> <span style='background-color: yellow;'>" . htmlspecialchars($display_type) . "</span></p>";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>Recommendations:</h2>";
echo "<ul>";
echo "<li>Check if both recipients have category='vendor' in the database</li>";
echo "<li>Verify the payment entry form is correctly setting different categories</li>";
echo "<li>Check the save_payment_entry.php logic for category assignment</li>";
echo "<li>Ensure the frontend properly distinguishes between vendor and labour selections</li>";
echo "</ul>";
?>