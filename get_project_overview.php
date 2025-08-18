<?php
// Returns filtered Project Overview metrics as JSON
header('Content-Type: application/json');

try {
    session_start();

    require_once __DIR__ . '/config/db_connect.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    // Read JSON body
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $selectedYear = isset($body['year']) ? (int)$body['year'] : (int)date('Y');
    $selectedMonth = isset($body['month']) ? $body['month'] : (string)date('n'); // 'all' or numeric string

    // Compute date range for filters
    $startDate = sprintf('%04d-01-01', $selectedYear);
    $endDate = sprintf('%04d-12-31', $selectedYear);
    if ($selectedMonth !== 'all' && ctype_digit((string)$selectedMonth)) {
        $monthInt = (int)$selectedMonth; // 1-12
        $startDate = date('Y-m-d', strtotime(sprintf('%04d-%02d-01', $selectedYear, $monthInt)));
        $endDate = date('Y-m-t', strtotime($startDate));
    }

    // Helper to run scalar queries safely
    $fetch_count = function(mysqli $conn, string $sql, array $params = []) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return 0; }
        if (!empty($params)) {
            // Build types string
            $types = '';
            $bind = [];
            foreach ($params as $p) {
                if (is_int($p)) $types .= 'i';
                elseif (is_float($p)) $types .= 'd';
                else $types .= 's';
                $bind[] = $p;
            }
            $stmt->bind_param($types, ...$bind);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_row())) {
            return (int)$row[0];
        }
        return 0;
    };

    // Projects Assigned (filter by created_at within range if provided)
    $projectsSql = "SELECT COUNT(*)
                    FROM projects p
                    WHERE p.assigned_to = ?
                      AND p.deleted_at IS NULL
                      AND p.created_at BETWEEN ? AND ?";
    $projectsCount = $fetch_count($conn, $projectsSql, [$userId, $startDate, $endDate]);

    // Stages Assigned
    $stagesSql = "SELECT COUNT(*)
                  FROM project_stages ps
                  JOIN projects p ON p.id = ps.project_id AND p.deleted_at IS NULL
                  WHERE ps.assigned_to = ?
                    AND ps.deleted_at IS NULL
                    AND ps.assignment_status = 'assigned'
                    AND ps.created_at BETWEEN ? AND ?";
    $stagesCount = $fetch_count($conn, $stagesSql, [$userId, $startDate, $endDate]);

    // Substages Assigned
    $substagesSql = "SELECT COUNT(*)
                     FROM project_substages pss
                     JOIN project_stages ps ON ps.id = pss.stage_id AND ps.deleted_at IS NULL
                     JOIN projects p ON p.id = ps.project_id AND p.deleted_at IS NULL
                     WHERE pss.assigned_to = ?
                       AND pss.deleted_at IS NULL
                       AND pss.assignment_status = 'assigned'
                       AND pss.created_at BETWEEN ? AND ?";
    $substagesCount = $fetch_count($conn, $substagesSql, [$userId, $startDate, $endDate]);

    // Stages Due in range (by end_date)
    $stagesDueSql = "SELECT COUNT(*)
                     FROM project_stages ps
                     JOIN projects p ON p.id = ps.project_id AND p.deleted_at IS NULL
                     WHERE ps.assigned_to = ?
                       AND ps.deleted_at IS NULL
                       AND ps.assignment_status = 'assigned'
                       AND ps.status != 'completed'
                       AND ps.end_date BETWEEN ? AND ?";
    $stagesDue = $fetch_count($conn, $stagesDueSql, [$userId, $startDate, $endDate]);

    // Substages Due in range (by end_date)
    $substagesDueSql = "SELECT COUNT(*)
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id AND ps.deleted_at IS NULL
                        JOIN projects p ON p.id = ps.project_id AND p.deleted_at IS NULL
                        WHERE pss.assigned_to = ?
                          AND pss.deleted_at IS NULL
                          AND pss.assignment_status = 'assigned'
                          AND pss.status != 'completed'
                          AND pss.end_date BETWEEN ? AND ?";
    $substagesDue = $fetch_count($conn, $substagesDueSql, [$userId, $startDate, $endDate]);

    // Build overview response. Trend placeholders; UI may ignore.
    $overview = [
        'projectsCard' => [ 'value' => $projectsCount, 'trend' => '', 'trend_direction' => 'neutral' ],
        'stagesCard' => [ 'value' => $stagesCount, 'trend' => '', 'trend_direction' => 'neutral' ],
        'substagesCard' => [ 'value' => $substagesCount, 'trend' => '', 'trend_direction' => 'neutral' ],
        'stagesDueCard' => [ 'value' => $stagesDue, 'trend' => '', 'trend_direction' => 'neutral' ],
        'substagesDueCard' => [ 'value' => $substagesDue, 'trend' => '', 'trend_direction' => 'neutral' ],
    ];

    echo json_encode(['success' => true, 'overview' => $overview]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
// Note: omit closing PHP tag to avoid accidental output that can break JSON responses