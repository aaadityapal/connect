<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $pendingLeaves = getPendingLeaveDetails($pdo);
    
    echo json_encode([
        'success' => true,
        'count' => count($pendingLeaves),
        'leaves' => $pendingLeaves
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch updates'
    ]);
} 