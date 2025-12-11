<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

try {
    date_default_timezone_set('Asia/Kolkata');
    $date = date('Y-m-d');
    $formattedDate = date('d/m/Y'); // For en-IN format in JS if needed

    // Check for today's record
    $query = "
        SELECT 
            id as attendance_id, 
            punch_in, 
            punch_out, 
            DATE_FORMAT(date, '%d/%m/%Y') as date_formatted
        FROM attendance 
        WHERE user_id = :user_id 
        AND DATE(date) = :date 
        LIMIT 1
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $userId, ':date' => $date]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        $punchInTime = $record['punch_in'] ? date('h:i:s A', strtotime($record['punch_in'])) : null;
        $punchOutTime = $record['punch_out'] ? date('h:i:s A', strtotime($record['punch_out'])) : null;

        echo json_encode([
            'success' => true,
            'has_record' => true,
            'attendance_id' => $record['attendance_id'],
            'punch_in_time' => $punchInTime,
            'punch_out_time' => $punchOutTime,
            'date' => $record['date_formatted'], // Matches Javascript's new Date().toLocaleDateString('en-IN') usually dd/mm/yyyy or d/m/y
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_record' => false
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check status',
        'details' => $e->getMessage()
    ]);
}
?>