<?php
/**
 * fetch_user_registry.php
 * -------------------------------------------------------
 * Fetches all active users (name + phone number) from the
 * database and returns the result as JSON.
 * Also writes a snapshot to users.json in the same folder.
 *
 * Usage: GET /user_registry/fetch_user_registry.php
 * -------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');

// ── Database connection ────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/db_connect.php';

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            username   AS name,
            phone,
            email,
            role,
            designation,
            employee_id,
            status
        FROM users
        WHERE LOWER(status) = 'active'
          AND deleted_at IS NULL
        ORDER BY username ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sanitise & normalise output
    $registry = array_map(function ($u) {
        return [
            'id' => (int) $u['id'],
            'name' => $u['name'] ?? '',
            'phone' => $u['phone'] ?? '',
            'email' => $u['email'] ?? '',
            'role' => $u['role'] ?? '',
            'designation' => $u['designation'] ?? '',
            'employee_id' => $u['employee_id'] ?? '',
            'status' => $u['status'] ?? 'Active',
        ];
    }, $users);

    $response = [
        'success' => true,
        'count' => count($registry),
        'generated' => date('Y-m-d H:i:s'),
        'users' => $registry,
    ];

    // ── Save snapshot to users.json ────────────────────────────────────────────
    $jsonPath = __DIR__ . '/users.json';
    file_put_contents(
        $jsonPath,
        json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
?>