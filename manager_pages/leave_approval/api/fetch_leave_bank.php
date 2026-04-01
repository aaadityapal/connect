<?php
header('Content-Type: application/json');
require_once '../../../config/db_connect.php';

try {
    $userFilter = $_GET['user'] ?? 'All';
    $yearFilter = $_GET['year'] ?? date('Y');
    $monthFilter = $_GET['month'] ?? 'All';

    $query = "
        SELECT 
            lb.id,
            lb.total_balance,
            lb.remaining_balance,
            lb.year,
            u.username,
            u.unique_id,
            lt.name as leave_type_name
        FROM leave_bank lb
        JOIN users u ON lb.user_id = u.id
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE 1=1
    ";

    $params = [];

    if ($userFilter !== 'All') {
        $query .= " AND lb.user_id = :user_id";
        $params[':user_id'] = $userFilter;
    }

    if ($yearFilter !== 'All') {
        $query .= " AND lb.year = :year";
        $params[':year'] = $yearFilter;
    }

    // Note: leave_bank table doesn't have a month column, so we just return the annual balance.
    // If the user wants to filter by who had activity in a month, that would require joining leave_request.
    // But per request, we use table leave_bank.

    $query .= " ORDER BY u.username ASC, lt.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also fetch users for the filter
    $stmtUsers = $pdo->query("SELECT id, username FROM users WHERE status = 'active' ORDER BY username ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $results,
        'users' => $users
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
