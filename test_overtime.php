<?php
require_once 'includes/db_connect.php';  // adjust path as needed

// Basic styling for readability
echo "<style>
    body { font-family: Arial; padding: 20px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .debug { background: #f9f9f9; padding: 10px; margin: 10px 0; }
</style>";

// Test Query
$query = "SELECT 
    a.id,
    a.date,
    a.overtime_hours,
    u.username
FROM attendance a
JOIN users u ON a.user_id = u.id
ORDER BY a.date DESC
LIMIT 12";  // just get last 10 records

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Show the query
    echo "<div class='debug'>";
    echo "<strong>Query used:</strong><br>";
    echo htmlspecialchars($query);
    echo "</div>";

    // Debug: Show raw data
    echo "<div class='debug'>";
    echo "<strong>Raw Data:</strong><br>";
    echo "<pre>";
    print_r($records);
    echo "</pre>";
    echo "</div>";

    // Display in table format
    echo "<h2>Last 10 Attendance Records</h2>";
    echo "<table>";
    echo "<tr>
            <th>ID</th>
            <th>Date</th>
            <th>Username</th>
            <th>Overtime Hours</th>
            <th>Raw Value</th>
          </tr>";

    foreach ($records as $record) {
        echo "<tr>";
        echo "<td>" . $record['id'] . "</td>";
        echo "<td>" . $record['date'] . "</td>";
        echo "<td>" . $record['username'] . "</td>";
        echo "<td>" . ($record['overtime_hours'] ?? '00:00:00') . "</td>";
        echo "<td><pre>" . print_r($record['overtime_hours'], true) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 