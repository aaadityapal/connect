<?php
$_POST = [
    'expenses' => [
        [
            'date' => '2026-04-13',
            'purpose' => 'Test bot 2',
            'from' => 'A',
            'to' => 'B',
            'mode' => 'Car',
            'distance' => 10,
            'amount' => 100
        ]
    ]
];
$_COOKIE['PHPSESSID'] = 'dummy'; // To simulate a web request without failing? 
