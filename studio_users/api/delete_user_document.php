<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';
require_once 'activity_helper.php';
require_once '../../includes/profile_completion_helper.php';

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['doc_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing document ID']);
    exit();
}

$docId = $data['doc_id'];

try {
    // Fetch current documents
    $stmt = $pdo->prepare("SELECT documents FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (empty($userData['documents'])) {
        echo json_encode(['status' => 'error', 'message' => 'No documents found']);
        exit();
    }

    $documents = json_decode($userData['documents'], true);
    if (!is_array($documents)) {
        $documents = [];
    } else {
        // Normalize legacy formats
        foreach ($documents as &$doc) {
            if (!isset($doc['path']) && isset($doc['file_path'])) { $doc['path'] = $doc['file_path']; }
            if (!isset($doc['name']) && isset($doc['filename'])) { $doc['name'] = $doc['filename']; }
            if (!isset($doc['id'])) { $doc['id'] = md5($doc['path'] ?? uniqid()); }
        }
    }

    $deletedDocName = '';
    // Find and remove the document
    $newDocs = [];
    foreach ($documents as $doc) {
        if ($doc['id'] != $docId) {
            $newDocs[] = $doc;
        } else {
            $deletedDocName = $doc['name'] ?? 'Untitled';
            // Optional: delete physical file if needed
            $filePath = "../../" . $doc['path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    // Update DB
    $updateStmt = $pdo->prepare("UPDATE users SET documents = ? WHERE id = ?");
    $updateStmt->execute([json_encode($newDocs), $userId]);

    $fetchPctStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $fetchPctStmt->execute([$userId]);
    $userRow = $fetchPctStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $profilePercent = compute_profile_completion_percent($userRow);
    $pctStmt = $pdo->prepare("UPDATE users SET profile_completion_percent = ? WHERE id = ?");
    $pctStmt->execute([$profilePercent, $userId]);

    logUserActivity($pdo, $userId, 'document_delete', 'user', "Deleted document: " . $deletedDocName);

    echo json_encode(['status' => 'success', 'message' => 'Document deleted successfully', 'documents' => $newDocs]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
