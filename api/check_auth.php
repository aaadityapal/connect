<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'success',
        'user_id' => $_SESSION['user_id']
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
}
?> 