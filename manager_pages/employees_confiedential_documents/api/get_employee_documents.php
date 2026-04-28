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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee id'
    ]);
    exit();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isAdmin = ($currentRole === 'admin') ? 1 : 0;
$isPrivilegedRole = in_array($currentRole, ['admin', 'hr', 'manager', 'superadmin'], true) ? 1 : 0;
$canManageDocs = 0;

try {
    require_once '../../../config/db_connect.php';

    if ($isPrivilegedRole !== 1 && $currentUserId > 0) {
        try {
            $permStmt = $pdo->prepare('SELECT can_upload, can_delete FROM confiedential_document_permissions WHERE user_id = :uid LIMIT 1');
            $permStmt->execute([':uid' => $currentUserId]);
            $perm = $permStmt->fetch(PDO::FETCH_ASSOC);
            if ($perm) {
                $canManageDocs = ((int)($perm['can_upload'] ?? 0) === 1 || (int)($perm['can_delete'] ?? 0) === 1) ? 1 : 0;
            }
        } catch (Throwable $permissionError) {
            $canManageDocs = 0;
        }
    }

    $canBypassVisibility = ($isPrivilegedRole === 1 || $canManageDocs === 1) ? 1 : 0;

    $existingColumns = [];
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM employee_confiedential_documents');
    $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($columns as $column) {
        $name = (string)($column['Field'] ?? '');
        if ($name !== '') {
            $existingColumns[$name] = true;
        }
    }

    $hasIsDeleted = isset($existingColumns['is_deleted']);
    $hasCreatedAt = isset($existingColumns['created_at']);

    $deletedFilter = $hasIsDeleted ? 'AND COALESCE(d.is_deleted, 0) = 0' : '';
    $createdAtSelect = $hasCreatedAt ? 'd.created_at' : 'NULL AS created_at';
    $orderBy = $hasCreatedAt ? 'ORDER BY d.created_at DESC, d.id DESC' : 'ORDER BY d.id DESC';

    $sql = "SELECT
                d.id,
                d.employee_id,
                d.uploaded_by,
                d.document_type_key,
                d.document_type_label,
                d.document_name,
                d.document_date,
                d.expiry_date,
                d.visibility_mode,
                d.visibility_user_ids,
                d.notes,
                d.file_original_name,
                                {$createdAtSelect}
            FROM employee_confiedential_documents d
            WHERE d.employee_id = :employee_id
                            {$deletedFilter}
              AND (
                                :is_admin_check = 1
                  OR :can_bypass_visibility = 1
                                        OR d.employee_id = :current_user_id_employee
                                        OR d.uploaded_by = :current_user_id_uploaded_by
                    OR d.visibility_mode = 'all'
                    OR (
                        d.visibility_mode = 'specific_users'
                        AND FIND_IN_SET(:current_user_id_csv, REPLACE(COALESCE(d.visibility_user_ids, ''), ' ', '')) > 0
                    )
                  )
                        {$orderBy}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':employee_id' => $employeeId,
                ':is_admin_check' => $isAdmin,
            ':can_bypass_visibility' => $canBypassVisibility,
                ':current_user_id_employee' => $currentUserId,
                ':current_user_id_uploaded_by' => $currentUserId,
        ':current_user_id_csv' => (string)$currentUserId,
    ]);

    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
} catch (Throwable $e) {
    error_log('get_employee_documents.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch documents'
    ]);
}
