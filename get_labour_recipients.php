<?php
// Get Labour Recipients
// Fetches labour records from labour_records table based on labour_type

header('Content-Type: application/json');

try {
    // Include database connection
    require_once(__DIR__ . '/config/db_connect.php');

    $labour_type = isset($_GET['labour_type']) ? $_GET['labour_type'] : '';

    if (empty($labour_type)) {
        echo json_encode(['success' => false, 'message' => 'labour_type parameter is required']);
        exit;
    }

    // Fetch active labour records filtered by labour_type
    $query = "SELECT id, full_name FROM labour_records 
              WHERE status = 'active' AND labour_type = ? 
              ORDER BY full_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$labour_type]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'recipients' => $recipients,
        'count' => count($recipients)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
