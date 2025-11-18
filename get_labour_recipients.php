<?php
// Get Labour Recipients
// Fetches labour records from labour_records table

header('Content-Type: application/json');

try {
    // Include database connection
    require_once(__DIR__ . '/config/db_connect.php');

    $type = isset($_GET['type']) ? $_GET['type'] : '';

    if (empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Type parameter is required']);
        exit;
    }

    // Fetch active labour records using PDO
    $query = "SELECT id, full_name AS name FROM labour_records WHERE status = 'active' ORDER BY full_name ASC";
    
    $result = $pdo->query($query);

    if (!$result) {
        throw new Exception('Query failed');
    }

    $recipients = $result->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'recipients' => $recipients
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
