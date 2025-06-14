<?php
// Include database connection
require_once 'config/db_connect.php';

// Get month and year from request
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Query to get income data grouped by date and project_type
$sql = "SELECT 
            DATE(project_date) as date,
            SUM(CASE WHEN project_type = 'architecture' THEN amount ELSE 0 END) as architecture,
            SUM(CASE WHEN project_type = 'interior' THEN amount ELSE 0 END) as interior,
            SUM(CASE WHEN project_type = 'construction' THEN amount ELSE 0 END) as construction
        FROM project_payouts
        WHERE MONTH(project_date) = ? AND YEAR(project_date) = ?
        GROUP BY DATE(project_date)
        ORDER BY date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$month, $year]);
$data = $stmt->fetchAll();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($data);
?> 