<?php
header('Content-Type: application/json');

$response = [
    'success' => true,
    'items' => [
        [
            'title' => 'Team sync for weekly priorities',
            'time' => '09:30 - 10:00',
            'tag' => 'Meeting'
        ],
        [
            'title' => 'Finalize task handoff notes',
            'time' => '10:30 - 11:00',
            'tag' => 'Admin'
        ],
        [
            'title' => 'Site walkthrough with safety checklist',
            'time' => '11:15 - 12:00',
            'tag' => 'Field'
        ]
    ]
];

echo json_encode($response);
