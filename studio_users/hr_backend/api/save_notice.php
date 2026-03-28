<?php
header('Content-Type: application/json');
require_once 'db.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$shortDesc = trim($_POST['shortDesc'] ?? '');
$longDesc = trim($_POST['longDesc'] ?? '');

if (empty($title) || empty($longDesc)) {
    echo json_encode(['success' => false, 'message' => 'Title and Long Description are required']);
    exit;
}

try {
    global $pdo, $is_production;
    
    // Absolute root path for the project (Connect root)
    $rootPath = dirname(dirname(dirname(dirname(__FILE__))));
    $uploadDir = $rootPath . '/uploads/notices/';
    
    // Debugging: log the received file information
    if (isset($_FILES['attachment'])) {
        error_log("[HR Notice] File '" . $_FILES['attachment']['name'] . "' received. Size: " . $_FILES['attachment']['size'] . " Error: " . $_FILES['attachment']['error']);
    } else {
        error_log("[HR Notice] No file 'attachment' found in \$_FILES.");
    }

    $attachmentUrl = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $_FILES['attachment']['name']);
        $uploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
            // Determine site root locally for this script
            $siteRoot = ($is_production) ? '' : '/connect';
            $attachmentUrl = $siteRoot . '/uploads/notices/' . $filename;
            error_log("[HR Notice] File successfully moved to: " . $uploadPath);
            error_log("[HR Notice] Attachment URL stored in DB: " . $attachmentUrl);
        } else {
            error_log("[HR Notice] move_uploaded_file() FAILED for path: " . $uploadPath);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO hr_notices (title, short_desc, long_desc, attachment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $shortDesc, $longDesc, $attachmentUrl]);

    echo json_encode(['success' => true, 'message' => 'Notice successfully broadcasted!']);
} catch (PDOException $e) {
    error_log("[HR Notice] PDO Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
