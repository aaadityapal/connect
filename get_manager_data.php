<?php
// Include database connection
require_once 'config/db_connect.php';

// Get month and year from request
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : 'all';

// Query to get manager data with commissions
$sql = "SELECT 
            m.id,
            m.name,
            m.initials,
            m.color,
            m.department,
            m.status,
            SUM(CASE WHEN p.project_type = 'architecture' THEN p.amount ELSE 0 END) as architecture_commission,
            SUM(CASE WHEN p.project_type = 'interior' THEN p.amount ELSE 0 END) as interior_commission,
            SUM(CASE WHEN p.project_type = 'construction' THEN p.amount ELSE 0 END) as construction_commission,
            m.fixed_remuneration as fixed_remuneration,
            (SELECT SUM(amount) FROM project_payouts WHERE manager_id = m.id AND MONTH(project_date) = ? AND YEAR(project_date) = ?) as total_paid
        FROM 
            managers m
        LEFT JOIN 
            project_payouts p ON m.id = p.manager_id AND MONTH(p.project_date) = ? AND YEAR(p.project_date) = ?
        WHERE 
            (? = 'all' OR m.department = ?)
        GROUP BY 
            m.id
        ORDER BY 
            m.name";

$stmt = $pdo->prepare($sql);
$stmt->execute([$month, $year, $month, $year, $department, $department]);
$result = $stmt->fetchAll();

$managers = array();
foreach ($result as $row) {
    // Calculate totals
    $total_commission = $row['architecture_commission'] + $row['interior_commission'] + $row['construction_commission'] + $row['fixed_remuneration'];
    $total_paid = $row['total_paid'] ?: 0;
    
    // Get payment history
    $payments = array();
    $paymentsSql = "SELECT project_date as date, amount FROM project_payouts 
                    WHERE manager_id = ? AND MONTH(project_date) = ? AND YEAR(project_date) = ?
                    ORDER BY project_date";
    $paymentsStmt = $pdo->prepare($paymentsSql);
    $paymentsStmt->execute([$row['id'], $month, $year]);
    $payments = $paymentsStmt->fetchAll();
    
    // Add manager data to array
    $managers[] = array(
        'id' => $row['id'],
        'name' => $row['name'],
        'initials' => $row['initials'],
        'color' => $row['color'],
        'department' => $row['department'],
        'status' => ($total_paid >= $total_commission) ? 'paid' : 'pending',
        'commissions' => array(
            $year => array(
                $month => array(
                    'architecture' => (float)$row['architecture_commission'],
                    'interior' => (float)$row['interior_commission'],
                    'construction' => (float)$row['construction_commission'],
                    'fixed' => (float)$row['fixed_remuneration'],
                    'total' => $total_commission,
                    'paid' => (float)$total_paid,
                    'payments' => $payments
                )
            )
        )
    );
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($managers);
?> 