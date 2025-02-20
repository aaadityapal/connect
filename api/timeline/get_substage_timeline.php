<?php
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['substage_id']) || !isset($_GET['stage_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$substage_id = $_GET['substage_id'];
$stage_id = $_GET['stage_id'];

try {
    // Get substage details
    $substage_query = "SELECT * FROM task_substages WHERE id = ? AND stage_id = ?";
    $substage_stmt = $conn->prepare($substage_query);
    $substage_stmt->bind_param("ii", $substage_id, $stage_id);
    $substage_stmt->execute();
    $substage = $substage_stmt->get_result()->fetch_assoc();

    if (!$substage) {
        throw new Exception('Substage not found');
    }

    // Get substage files
    $files_query = "SELECT * FROM substage_files WHERE substage_id = ?";
    $files_stmt = $conn->prepare($files_query);
    $files_stmt->bind_param("i", $substage_id);
    $files_stmt->execute();
    $files = $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'timeline' => [
            'substage' => $substage,
            'files' => $files
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 