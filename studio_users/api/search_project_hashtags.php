<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    $sql = "SELECT id, title
            FROM projects
            WHERE deleted_at IS NULL
              AND status NOT IN ('completed', 'cancelled')";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (title LIKE :search_start OR title LIKE :search_any)
                  ORDER BY
                    CASE WHEN title LIKE :search_start_order THEN 1 ELSE 2 END,
                    title ASC
                  LIMIT 25";

        $params = [
            'search_start' => "$search%",
            'search_any' => "% $search%",
            'search_start_order' => "$search%"
        ];
    } else {
        $sql .= " ORDER BY title ASC LIMIT 25";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
