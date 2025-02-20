<?php
require_once '../config/db_connect.php';

$project_id = $_GET['project_id'] ?? 0;

// Fetch project details
$project_query = "SELECT 
    p.*,
    u1.username as assigned_by,
    ps.name as current_stage,
    ps.status
FROM projects p
LEFT JOIN users u1 ON p.got_project_from = u1.id
LEFT JOIN project_stages ps ON ps.project_id = p.id
WHERE p.id = ? AND (ps.id IS NULL OR ps.id = (
    SELECT ps2.id 
    FROM project_stages ps2 
    WHERE ps2.project_id = p.id 
    ORDER BY ps2.created_at DESC 
    LIMIT 1
))";

$stmt = $conn->prepare($project_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

// Fetch all stages and substages
$stages_query = "SELECT 
    ps.*,
    u.username as assigned_to,
    GROUP_CONCAT(
        JSON_OBJECT(
            'id', pss.id,
            'name', pss.name,
            'status', pss.status,
            'assigned_to', u2.username,
            'due_date', pss.due_date
        )
    ) as sub_stages
FROM project_stages ps
LEFT JOIN users u ON ps.assigned_to = u.id
LEFT JOIN project_sub_stages pss ON pss.stage_id = ps.id
LEFT JOIN users u2 ON pss.assigned_to = u2.id
WHERE ps.project_id = ?
GROUP BY ps.id
ORDER BY ps.created_at";

$stmt = $conn->prepare($stages_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$stages_result = $stmt->get_result();

$stages = [];
while ($stage = $stages_result->fetch_assoc()) {
    $stage['sub_stages'] = $stage['sub_stages'] ? json_decode('[' . $stage['sub_stages'] . ']', true) : [];
    $stages[] = $stage;
}

echo json_encode([
    'success' => true,
    'project' => $project,
    'stages' => $stages
]); 