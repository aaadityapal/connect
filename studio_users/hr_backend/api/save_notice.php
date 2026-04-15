<?php
// ============================================
// save_notice.php — Broadcast a new HR notice with optional attachment
// ============================================
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$title     = trim($_POST['title']     ?? '');
$shortDesc = trim($_POST['shortDesc'] ?? '');
$longDesc  = trim($_POST['longDesc']  ?? '');

if (empty($title) || empty($longDesc)) {
    echo json_encode(['success' => false, 'message' => 'Title and Long Description are required']);
    exit;
}

try {
    global $pdo;

    // ── Build upload directory using DOCUMENT_ROOT ──────────────────────────
    // DOCUMENT_ROOT is always the correct web-root regardless of environment.
    // On production: /home/user/public_html
    // On XAMPP local: /Applications/XAMPP/xamppfiles/htdocs  (but connect/ is sub-root)
    //
    // We store files at:  {DOCUMENT_ROOT}/uploads/notices/
    // And serve them at:  /uploads/notices/filename  (production)
    //                     /connect/uploads/notices/  (local XAMPP with /connect sub-dir)

    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $uploadDir = $docRoot . '/uploads/notices/';

    // Detect sub-directory prefix (e.g. "/connect" on local XAMPP).
    // SCRIPT_NAME for this file is like /connect/studio_users/hr_backend/api/save_notice.php
    // We strip everything after the first segment that is not a sub-directory root.
    // Simplest reliable method: compare DOCUMENT_ROOT with the real path of this file.
    $thisFile      = realpath(__FILE__);                    // absolute fs path
    $docRootReal   = realpath($docRoot);                    // absolute fs path
    $relPath       = str_replace('\\', '/', substr($thisFile, strlen($docRootReal)));
    // $relPath == /connect/studio_users/hr_backend/api/save_notice.php
    // Strip everything after the first path segment to get the sub-dir prefix
    $segments      = explode('/', trim($relPath, '/'));
    // If the first segment is the project folder (not studio_users/etc), use it.
    // Heuristic: if there are 5+ segments the first is likely the project sub-dir.
    $urlPrefix     = (count($segments) >= 5) ? ('/' . $segments[0]) : '';

    // On production the project lives directly under public_html → no prefix needed.
    // Force empty prefix when DOCUMENT_ROOT itself already contains "public_html".
    if (stripos($docRoot, 'public_html') !== false) {
        $urlPrefix = '';
        // Also use DOCUMENT_ROOT directly (it IS public_html)
        $uploadDir = $docRoot . '/uploads/notices/';
    }

    // ── Create upload directory if missing ──────────────────────────────────
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("[HR Notice] mkdir FAILED for: $uploadDir");
        }
    }

    // ── Handle file upload ──────────────────────────────────────────────────
    $attachmentUrl = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $origName   = $_FILES['attachment']['name'];
        $filename   = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-_]/", '_', $origName);
        $uploadPath = $uploadDir . $filename;

        error_log("[HR Notice] Attempting upload → $uploadPath");
        error_log("[HR Notice] uploadDir exists: " . (is_dir($uploadDir) ? 'yes' : 'no'));
        error_log("[HR Notice] uploadDir writable: " . (is_writable($uploadDir) ? 'yes' : 'no'));

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
            // Store a root-relative URL so it works regardless of domain
            $attachmentUrl = $urlPrefix . '/uploads/notices/' . $filename;
            error_log("[HR Notice] SUCCESS → DB url: $attachmentUrl");
        } else {
            error_log("[HR Notice] move_uploaded_file FAILED → $uploadPath");
        }
    } elseif (isset($_FILES['attachment'])) {
        error_log("[HR Notice] Upload error code: " . $_FILES['attachment']['error']);
    }

    // ── Insert record ───────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO hr_notices (title, short_desc, long_desc, attachment) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$title, $shortDesc, $longDesc, $attachmentUrl]);

    echo json_encode(['success' => true, 'message' => 'Notice successfully broadcasted!']);

} catch (PDOException $e) {
    error_log("[HR Notice] PDO Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
