<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Join with leave_types to get the name for UI logic if needed
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as leave_type_name 
        FROM leave_request r 
        JOIN leave_types t ON r.leave_type = t.id 
        WHERE r.id = ? AND r.user_id = ?
    ");
    $stmt->execute([$id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        // If it's a date range, we might want to expand it for the frontend, 
        // but the frontend expects a list of dates.
        // For now, let's just send the raw row. 
        // However, the current system seems to create 1 row per request (which might span multiple days).
        // The frontend expects `selectedDates` as a Set of YYYY-MM-DD.
        // We need to generate that list between start_date and end_date.

        $dates = [];
        $start = new DateTime($request['start_date']);
        $end = new DateTime($request['end_date']);

        while ($start <= $end) {
            $dates[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }

        $request['dates_list'] = $dates;

        echo json_encode(['success' => true, 'data' => $request]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>