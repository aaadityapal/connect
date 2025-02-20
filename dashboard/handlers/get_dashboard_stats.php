<?php

// Add these queries to get pending stages and substages
$pending_stages_query = "
    SELECT COUNT(*) as count 
    FROM project_stages 
    WHERE status = 'pending'";
$pending_substages_query = "
    SELECT COUNT(*) as count 
    FROM project_substages 
    WHERE status = 'pending'";

$pending_stages_result = $conn->query($pending_stages_query);
$pending_substages_result = $conn->query($pending_substages_query);

// Add to your response array
$response = [
    // ... existing stats ...
    'pending_stages' => $pending_stages_result->fetch_assoc()['count'],
    'pending_substages' => $pending_substages_result->fetch_assoc()['count']
]; 