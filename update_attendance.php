<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Set timezone to India
date_default_timezone_set('Asia/Kolkata');
$current_date = date('Y-m-d');
$current_time = date('Y-m-d H:i:s');

try {
    if ($action === 'punch_in') {
        // Check if user has already punched in today
        $stmt = $pdo->prepare("
            SELECT * FROM attendance_logs 
            WHERE user_id = ? AND DATE(punch_in) = ?
        ");
        $stmt->execute([$user_id, $current_date]);
        $existing_punch = $stmt->fetch();

        if ($existing_punch) {
            echo json_encode([
                'error' => true,
                'message' => 'You have already punched in today. Next punch-in available tomorrow.'
            ]);
            exit;
        }

        // Create new punch-in record
        $stmt = $pdo->prepare("
            INSERT INTO attendance_logs (user_id, punch_in, date) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $current_time, $current_date]);
        
        echo json_encode([
            'success' => true,
            'time' => date('H:i:s'),
            'message' => 'Punched in successfully!'
        ]);
    } 
    else if ($action === 'punch_out') {
        // Check if there's an open punch-in for today
        $stmt = $pdo->prepare("
            SELECT * FROM attendance_logs 
            WHERE user_id = ? 
            AND DATE(punch_in) = ? 
            AND punch_out IS NULL
        ");
        $stmt->execute([$user_id, $current_date]);
        $punch_record = $stmt->fetch();

        if (!$punch_record) {
            echo json_encode([
                'error' => true,
                'message' => 'No active punch-in found for today.'
            ]);
            exit;
        }

        // Update the punch-out time
        $stmt = $pdo->prepare("
            UPDATE attendance_logs 
            SET punch_out = ?,
                total_hours = TIMESTAMPDIFF(SECOND, punch_in, ?) / 3600
            WHERE id = ?
        ");
        $stmt->execute([$current_time, $current_time, $punch_record['id']]);
        
        echo json_encode([
            'success' => true,
            'time' => date('H:i:s'),
            'message' => 'Punched out successfully!'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'error' => true,
        'message' => 'Database error occurred.'
    ]);
}
