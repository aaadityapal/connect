<?php
function recordProjectHistory($project_id, $action_type, $old_value, $new_value, $conn) {
    $query = "INSERT INTO project_history (
        project_id, 
        action_type, 
        old_value, 
        new_value, 
        changed_by, 
        changed_at
    ) VALUES (?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "isssi",
        $project_id,
        $action_type,
        $old_value,
        $new_value,
        $_SESSION['user_id']
    );
    return $stmt->execute();
}

function recordStatusChange($project_id, $stage_id, $substage_id, $old_status, $new_status, $remarks, $conn) {
    $query = "INSERT INTO project_status_history (
        project_id,
        stage_id,
        substage_id,
        old_status,
        new_status,
        remarks,
        changed_by,
        changed_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "iiisssi",
        $project_id,
        $stage_id,
        $substage_id,
        $old_status,
        $new_status,
        $remarks,
        $_SESSION['user_id']
    );
    return $stmt->execute();
}

function logProjectActivity($project_id, $stage_id, $substage_id, $activity_type, $description, $conn) {
    $query = "INSERT INTO project_activity_log (
        project_id,
        stage_id,
        substage_id,
        activity_type,
        description,
        performed_by,
        performed_at
    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "iiissi",
        $project_id,
        $stage_id,
        $substage_id,
        $activity_type,
        $description,
        $_SESSION['user_id']
    );
    return $stmt->execute();
} 