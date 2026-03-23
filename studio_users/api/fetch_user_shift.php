<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get the most recent effective shift for the user
    $query = "SELECT s.start_time, s.end_time, us.weekly_offs
              FROM user_shifts us 
              JOIN shifts s ON us.shift_id = s.id 
              WHERE us.user_id = ? 
              AND (us.effective_to IS NULL OR us.effective_to >= CURDATE())
              ORDER BY us.effective_from DESC 
              LIMIT 1";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shift) {
        // Fallback to a default if none assigned
        $shift = ['start_time' => '09:00:00', 'end_time' => '18:00:00', 'weekly_offs' => 'Saturday,Sunday'];
    }

    // Format times to H:i
    $start = date("H:i", strtotime($shift['start_time']));
    $end = date("H:i", strtotime($shift['end_time']));
    
    // Calculate Morning Short Leave: start to start + 1:30
    $morn_end = date("H:i", strtotime($shift['start_time'] . " +90 minutes"));
    
    // Calculate Evening Short Leave: end - 1:30 to end
    $eve_start = date("H:i", strtotime($shift['end_time'] . " -90 minutes"));

    echo json_encode([
        'success' => true,
        'data' => [
            'shift_start' => $start,
            'shift_end' => $end,
            'morning_range' => "$start - $morn_end",
            'evening_range' => "$eve_start - $end",
            'weekly_offs' => $shift['weekly_offs']
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
