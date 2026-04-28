<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit();
}

$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = strtolower(trim((string)($_GET['mode'] ?? 'view')));
$mode = in_array($mode, ['view', 'download'], true) ? $mode : 'view';

if ($documentId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid document id';
    exit();
}

try {
    require_once '../../../config/db_connect.php';

    $stmt = $pdo->prepare(
        "SELECT id, employee_id, uploaded_by, visibility_mode, visibility_user_ids, file_original_name, file_path, file_mime, is_deleted
         FROM employee_confiedential_documents
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $documentId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Document not found';
        exit();
    }

    if ((int)($doc['is_deleted'] ?? 0) === 1) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Document not found';
        exit();
    }

    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
    $isAdmin = ($currentRole === 'admin');
    $isPrivilegedRole = in_array($currentRole, ['admin', 'hr', 'manager', 'superadmin'], true);
    $canManageDocs = false;

    if (!$isPrivilegedRole && $currentUserId > 0) {
        try {
            $permStmt = $pdo->prepare('SELECT can_upload, can_delete FROM confiedential_document_permissions WHERE user_id = :uid LIMIT 1');
            $permStmt->execute([':uid' => $currentUserId]);
            $perm = $permStmt->fetch(PDO::FETCH_ASSOC);
            if ($perm) {
                $canManageDocs = ((int)($perm['can_upload'] ?? 0) === 1 || (int)($perm['can_delete'] ?? 0) === 1);
            }
        } catch (Throwable $permissionError) {
            $canManageDocs = false;
        }
    }

    $canBypassVisibility = $isPrivilegedRole || $canManageDocs;

    $canAccess = $canBypassVisibility
        || $currentUserId === (int)$doc['employee_id']
        || $currentUserId === (int)$doc['uploaded_by'];

    if (!$canAccess) {
        $visibilityMode = strtolower(trim((string)($doc['visibility_mode'] ?? 'all')));
        if ($visibilityMode === 'all') {
            $canAccess = true;
        } elseif ($visibilityMode === 'specific_users') {
            $allowedUserIds = [];
            $rawIds = trim((string)($doc['visibility_user_ids'] ?? ''));
            if ($rawIds !== '') {
                foreach (preg_split('/[,\s]+/', $rawIds) as $token) {
                    $token = trim($token);
                    if ($token !== '' && ctype_digit($token)) {
                        $allowedUserIds[] = (int)$token;
                    }
                }
            }
            $canAccess = in_array($currentUserId, $allowedUserIds, true);
        }
    }

    if (!$canAccess) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit();
    }

    $relativePath = ltrim((string)$doc['file_path'], '/');
    $absolutePath = realpath(__DIR__ . '/../../../' . $relativePath);
    $uploadsRoot = realpath(__DIR__ . '/../../../uploads');

    if (!$absolutePath || !is_file($absolutePath) || !$uploadsRoot || strpos($absolutePath, $uploadsRoot) !== 0) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'File not found';
        exit();
    }

    $mime = trim((string)$doc['file_mime']);
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    $downloadName = trim((string)$doc['file_original_name']);
    if ($downloadName === '') {
        $downloadName = basename($absolutePath);
    }
    $safeName = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $downloadName);

    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($absolutePath));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($mode === 'download' ? 'attachment' : 'inline') . '; filename="' . $safeName . '"');

    readfile($absolutePath);
    exit();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to serve document';
    exit();
}
