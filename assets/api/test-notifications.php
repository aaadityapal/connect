<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'notifications' => [
        [
            'id' => 'test_1',
            'title' => 'Test Notification',
            'message' => 'This is a test notification',
            'icon' => 'fas fa-bell',
            'type' => 'info',
            'source_type' => 'test',
            'source_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'time_display' => 'Just now',
            'read_status' => 0
        ]
    ]
]); 