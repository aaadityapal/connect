<?php
/**
 * fetch_my_hierarchy.php
 * Returns the logged-in user's profile + their FULL recursive descendant tree
 * from the user_reporting table. Supports unlimited depth.
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$logged_in_id = (int) $_SESSION['user_id'];

// Helper: generate a distinct tailwind color sequentially to guarantee zero collisions
function stringToColor($str) {
    if (empty($str)) return '#94a3b8';
    
    $palette = [
        '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#10b981', '#14b8a6', '#06b6d4', 
        '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#ec4899', '#f43f5e',
        '#dc2626', '#ea580c', '#d97706', '#65a30d', '#16a34a', '#059669', '#0d9488', '#0891b2',
        '#0284c7', '#2563eb', '#4f46e5', '#7c3aed', '#c026d3', '#db2777', '#e11d48'
    ];
    
    static $assigned = [];
    static $index = 0;
    
    if (!isset($assigned[$str])) {
        $assigned[$str] = $palette[$index % count($palette)];
        $index++;
    }
    
    return $assigned[$str];
}

// Helper: build initials from username
function getInitials($username) {
    $parts = explode(' ', trim($username));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $i .= strtoupper(substr($parts[1], 0, 1));
    return $i;
}

/**
 * Recursively build the subtree for a given manager.
 * $visited prevents infinite loops in case of circular references.
 */
function buildSubtree($pdo, $managerId, $visited = []) {
    if (in_array($managerId, $visited)) return []; // break cycles
    $visited[] = $managerId;

    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.position, u.role
         FROM user_reporting ur
         INNER JOIN users u ON u.id = ur.subordinate_id
         WHERE ur.manager_id = ?
           AND u.deleted_at IS NULL
           AND u.status = 'Active'
         ORDER BY u.username ASC"
    );
    $stmt->execute([$managerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $children = [];
    foreach ($rows as $row) {
        $children[] = [
            'id'       => (int) $row['id'],
            'name'     => $row['username'],
            'position' => $row['position'] ?? '',
            'role'     => $row['role'] ?? '',
            'initials' => getInitials($row['username']),
            'color'    => stringToColor($row['username']),
            'children' => buildSubtree($pdo, (int) $row['id'], $visited),
        ];
    }
    return $children;
}

try {
    // 1. Fetch the logged-in user's own details
    $stmt = $pdo->prepare(
        "SELECT id, username, position, role
         FROM users
         WHERE id = ? AND deleted_at IS NULL"
    );
    $stmt->execute([$logged_in_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    // 2. Build the recursive tree starting from the logged-in user
    $tree = [
        'id'       => (int) $me['id'],
        'name'     => $me['username'],
        'position' => $me['position'] ?? '',
        'role'     => $me['role'] ?? '',
        'initials' => getInitials($me['username']),
        'color'    => stringToColor($me['username']),
        'children' => buildSubtree($pdo, $logged_in_id, []),
    ];

    echo json_encode([
        'success' => true,
        'tree'    => $tree,
        // Keep backward-compat fields
        'manager'      => $tree,
        'subordinates' => $tree['children'],
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage(),
    ]);
}
?>
