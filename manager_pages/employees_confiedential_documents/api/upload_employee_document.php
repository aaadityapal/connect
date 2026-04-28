<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
$documentType = trim((string)($_POST['document_type'] ?? ''));
$customDocumentType = trim((string)($_POST['custom_document_type'] ?? ''));
$documentName = trim((string)($_POST['document_name'] ?? ''));
$documentDate = trim((string)($_POST['document_date'] ?? ''));
$expiryDate = trim((string)($_POST['expiry_date'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$visibilityMode = trim((string)($_POST['visibility_mode'] ?? 'all'));
$visibilityUserIdsRaw = trim((string)($_POST['visibility_user_ids'] ?? ''));

if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid employee id']);
    exit();
}

if ($documentType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document type is required']);
    exit();
}

if ($documentType === 'custom' && $customDocumentType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Custom document type is required']);
    exit();
}

if ($documentName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document name is required']);
    exit();
}

if ($documentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $documentDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid document date']);
    exit();
}

if ($expiryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid expiry date']);
    exit();
}

$visibilityMode = in_array($visibilityMode, ['all', 'specific_users'], true) ? $visibilityMode : 'all';
$visibilityUserIds = [];
if ($visibilityMode === 'specific_users') {
    if ($visibilityUserIdsRaw === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User IDs are required for specific visibility']);
        exit();
    }

    foreach (preg_split('/[,\s]+/', $visibilityUserIdsRaw) as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        if (!ctype_digit($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user id list']);
            exit();
        }
        $visibilityUserIds[] = (int)$token;
    }

    $visibilityUserIds = array_values(array_unique($visibilityUserIds));
    if (empty($visibilityUserIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'At least one valid user id is required']);
        exit();
    }
}

if (!isset($_FILES['document_file']) || !is_array($_FILES['document_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document file is required']);
    exit();
}

$file = $_FILES['document_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit();
}

$maxBytes = 10 * 1024 * 1024;
$fileSize = (int)($file['size'] ?? 0);
if ($fileSize <= 0 || $fileSize > $maxBytes) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size must be between 1 byte and 10MB']);
    exit();
}

$originalName = trim((string)($file['name'] ?? ''));
$tmpPath = (string)($file['tmp_name'] ?? '');
if ($originalName === '' || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid uploaded file']);
    exit();
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported file type']);
    exit();
}

$allowedMimesByExtension = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'png' => ['image/png', 'image/x-png'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
];

$detectedMime = '';
if (class_exists('finfo')) {
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower(trim((string)$finfo->file($tmpPath)));
    } catch (Throwable $mimeError) {
        $detectedMime = '';
    }
}

if ($detectedMime === '' && function_exists('mime_content_type')) {
    $detectedMime = strtolower(trim((string)mime_content_type($tmpPath)));
}

$allowedMimesForExtension = $allowedMimesByExtension[$extension] ?? [];
if (!empty($allowedMimesForExtension) && $detectedMime !== '' && !in_array($detectedMime, $allowedMimesForExtension, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid MIME type']);
    exit();
}

$mime = $detectedMime !== ''
    ? $detectedMime
    : (!empty($allowedMimesForExtension) ? (string)$allowedMimesForExtension[0] : 'application/octet-stream');

$documentTypeKey = $documentType;
$documentTypeLabel = $documentType;
if ($documentType === 'custom') {
    $documentTypeKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $customDocumentType));
    $documentTypeKey = trim((string)$documentTypeKey, '-');
    if ($documentTypeKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid custom document type']);
        exit();
    }
    $documentTypeLabel = $customDocumentType;
} else {
    $documentTypeLabel = ucwords(str_replace('-', ' ', $documentType));
}

$actorUserId = (int)($_SESSION['user_id'] ?? 0);
$actorUsername = trim((string)($_SESSION['username'] ?? 'System'));
$actorRole = strtolower(trim((string)($_SESSION['role'] ?? '')));

$storedAbsolutePath = '';
$storedRelativePath = '';

try {
    require_once '../../../config/db_connect.php';

    if ($actorRole !== 'admin') {
        $permStmt = $pdo->prepare('SELECT can_upload FROM confiedential_document_permissions WHERE user_id = :uid LIMIT 1');
        $permStmt->execute([':uid' => $actorUserId]);
        $perm = $permStmt->fetch(PDO::FETCH_ASSOC);
        $canUpload = $perm ? (int)($perm['can_upload'] ?? 0) : 0;
        if ($canUpload !== 1) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to upload documents']);
            exit();
        }
    }

    $empStmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = :id LIMIT 1");
    $empStmt->execute([':id' => $employeeId]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit();
    }

    $uploadDir = __DIR__ . '/../../../uploads/employee_confiedential_documents/' . $employeeId;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Failed to create upload directory');
    }

    $storedFileName = sprintf(
        'doc_%d_%s_%s.%s',
        $employeeId,
        date('Ymd_His'),
        bin2hex(random_bytes(4)),
        $extension
    );

    $storedAbsolutePath = $uploadDir . '/' . $storedFileName;
    $storedRelativePath = 'uploads/employee_confiedential_documents/' . $employeeId . '/' . $storedFileName;

    if (!move_uploaded_file($tmpPath, $storedAbsolutePath)) {
        throw new RuntimeException('Failed to save uploaded file');
    }

    $pdo->beginTransaction();

    $insertDoc = $pdo->prepare(
        "INSERT INTO employee_confiedential_documents
            (employee_id, uploaded_by, document_type_key, document_type_label, document_name, document_date, expiry_date, visibility_mode, visibility_user_ids, notes, file_original_name, file_stored_name, file_path, file_size, file_mime)
         VALUES
            (:employee_id, :uploaded_by, :document_type_key, :document_type_label, :document_name, :document_date, :expiry_date, :visibility_mode, :visibility_user_ids, :notes, :file_original_name, :file_stored_name, :file_path, :file_size, :file_mime)"
    );

    $insertDoc->execute([
        ':employee_id' => $employeeId,
        ':uploaded_by' => $actorUserId,
        ':document_type_key' => $documentTypeKey,
        ':document_type_label' => $documentTypeLabel,
        ':document_name' => $documentName,
        ':document_date' => $documentDate,
        ':expiry_date' => $expiryDate !== '' ? $expiryDate : null,
        ':visibility_mode' => $visibilityMode,
        ':visibility_user_ids' => $visibilityMode === 'specific_users' ? implode(',', $visibilityUserIds) : null,
        ':notes' => $notes !== '' ? $notes : null,
        ':file_original_name' => $originalName,
        ':file_stored_name' => $storedFileName,
        ':file_path' => $storedRelativePath,
        ':file_size' => $fileSize,
        ':file_mime' => $mime,
    ]);

    $documentId = (int)$pdo->lastInsertId();

    $metadata = [
        'module' => 'employees_confiedential_documents',
        'event' => 'document_uploaded',
        'document' => [
            'id' => $documentId,
            'employee_id' => $employeeId,
            'employee_name' => (string)($employee['username'] ?? ''),
            'document_type_key' => $documentTypeKey,
            'document_type_label' => $documentTypeLabel,
            'document_name' => $documentName,
            'document_date' => $documentDate,
            'expiry_date' => $expiryDate !== '' ? $expiryDate : null,
            'visibility_mode' => $visibilityMode,
            'visibility_user_ids' => $visibilityUserIds,
            'notes' => $notes,
            'file_original_name' => $originalName,
            'file_stored_name' => $storedFileName,
            'file_path' => $storedRelativePath,
            'file_size' => $fileSize,
            'file_mime' => $mime,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ],
        'actor' => [
            'user_id' => $actorUserId,
            'username' => $actorUsername,
        ],
    ];

    $description = sprintf(
        "Uploaded confidential document '%s' (%s) for %s (ID: %d)",
        $documentName,
        $documentTypeLabel,
        (string)($employee['username'] ?? 'Employee'),
        $employeeId
    );

    try {
        $insertLog = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
             VALUES
                (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0, 0)"
        );

        $insertLog->execute([
            ':user_id' => $actorUserId,
            ':action_type' => 'employee_confidential_document_uploaded',
            ':entity_type' => 'employee_confidential_document',
            ':entity_id' => $documentId,
            ':description' => $description,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $logError) {
        error_log('upload_employee_document.php log insert skipped: ' . $logError->getMessage());
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'document' => [
            'id' => $documentId,
            'employee_id' => $employeeId,
            'document_type_key' => $documentTypeKey,
            'document_type_label' => $documentTypeLabel,
            'document_name' => $documentName,
            'document_date' => $documentDate,
            'expiry_date' => $expiryDate !== '' ? $expiryDate : null,
            'visibility_mode' => $visibilityMode,
            'visibility_user_ids' => $visibilityUserIds,
            'notes' => $notes,
            'file_name' => $originalName,
            'file_path' => $storedRelativePath,
            'uploaded_at' => date('c'),
        ]
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($storedAbsolutePath !== '' && is_file($storedAbsolutePath)) {
        @unlink($storedAbsolutePath);
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload document'
    ]);
}
