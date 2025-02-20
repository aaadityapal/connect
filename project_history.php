<?php
require_once 'config/db_connect.php';

function getProjectHistory($project_id, $conn) {
    $query = "
        SELECT 
            ph.*,
            u.username as changed_by_user,
            p.title as project_title
        FROM project_history ph
        JOIN users u ON ph.changed_by = u.id
        JOIN projects p ON ph.project_id = p.id
        WHERE ph.project_id = ?
        ORDER BY ph.changed_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getStatusHistory($project_id, $conn) {
    $query = "
        SELECT 
            psh.*,
            u.username as changed_by_user,
            p.title as project_title,
            ps.stage_number,
            pss.substage_number
        FROM project_status_history psh
        JOIN users u ON psh.changed_by = u.id
        JOIN projects p ON psh.project_id = p.id
        LEFT JOIN project_stages ps ON psh.stage_id = ps.id
        LEFT JOIN project_substages pss ON psh.substage_id = pss.id
        WHERE psh.project_id = ?
        ORDER BY psh.changed_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Usage example:
$project_id = $_GET['project_id'] ?? null;
if ($project_id) {
    $history = getProjectHistory($project_id, $conn);
    $status_history = getStatusHistory($project_id, $conn);
    // Display the history...
} 