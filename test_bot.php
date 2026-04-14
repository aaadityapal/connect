<?php
session_start();
$_SESSION['user_id'] = 21;
$_POST = [
    'expenses' => [
        [
            'date' => '2026-04-13',
            'purpose' => 'Test bot',
            'from' => 'A',
            'to' => 'B',
            'mode' => 'Car',
            'distance' => 10,
            'amount' => 100
        ]
    ]
];
require_once 'studio_users/api/save_travel_expenses.php';
