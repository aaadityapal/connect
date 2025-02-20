<?php
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['stage_id']) || !isset($_GET['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$stage_id = $_GET['stage_id'];
$task_id = $_GET['task_id'];

try {
    // Get stage details
    $stage_query = "SELECT * FROM task_stages WHERE id = ? AND task_id = ?";
    $stage_stmt = $conn->prepare($stage_query);
    $stage_stmt->bind_param("ii", $stage_id, $task_id);
    $stage_stmt->execute();
    $stage = $stage_stmt->get_result()->fetch_assoc();

    if (!$stage) {
        throw new Exception('Stage not found');
    }

    // Get stage files
    $files_query = "SELECT * FROM stage_files WHERE stage_id = ?";
    $files_stmt = $conn->prepare($files_query);
    $files_stmt->bind_param("i", $stage_id);
    $files_stmt->execute();
    $files = $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get substages with their files
    $substages_query = "SELECT * FROM task_substages WHERE stage_id = ?";
    $substages_stmt = $conn->prepare($substages_query);
    $substages_stmt->bind_param("i", $stage_id);
    $substages_stmt->execute();
    $substages = $substages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get files for each substage
    foreach ($substages as &$substage) {
        $substage_files_query = "SELECT * FROM substage_files WHERE substage_id = ?";
        $substage_files_stmt = $conn->prepare($substage_files_query);
        $substage_files_stmt->bind_param("i", $substage['id']);
        $substage_files_stmt->execute();
        $substage['files'] = $substage_files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'timeline' => [
            'stage' => $stage,
            'files' => $files,
            'substages' => $substages
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 