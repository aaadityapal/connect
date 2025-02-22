<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/hr/includes/db_connect.php';

if (!isset($_GET['file_id'])) {
    die('File ID is required');
}

$fileId = intval($_GET['file_id']);

try {
    $query = "SELECT sf.*, ps.stage_id 
              FROM substage_files sf 
              LEFT JOIN project_substages ps ON sf.substage_id = ps.id 
              WHERE sf.id = ? AND sf.deleted_at IS NULL";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        die('File not found');
    }

    // Read the file content
    $content = file_get_contents($file['file_path']);
    
    // Display as text
    header('Content-Type: text/plain');
    echo htmlspecialchars($content);

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?> 