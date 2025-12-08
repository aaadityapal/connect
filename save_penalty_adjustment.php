<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!isset($data['user_id'], $data['action'], $data['reason'], $data['month'], $data['year'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$userId = intval($data['user_id']);
$action = $data['action']; // 'increase' or 'decrease'
$reason = trim($data['reason']);
$month = intval($data['month']);
$year = intval($data['year']);
$newPenalty = isset($data['new_penalty']) ? floatval($data['new_penalty']) : 0;

// Validate action
if (!in_array($action, ['increase', 'decrease'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

// Validate reason - minimum 10 words
$wordCount = count(array_filter(explode(' ', $reason), fn($word) => !empty($word)));
if ($wordCount < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Reason must contain at least 10 words']);
    exit;
}

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid month or year']);
    exit;
}

// Format penalty_month as MM-YYYY
$penaltyMonth = str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . $year;

try {
    // Check if user exists
    $userCheckStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
    $userCheckStmt->execute([$userId]);
    if (!$userCheckStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    // Ensure penalty value is not negative
    if ($newPenalty < 0) {
        $newPenalty = 0;
    }

    // Check if penalty record exists for this user and month
    $checkStmt = $pdo->prepare("
        SELECT id, penalty_days FROM salary_penalties
        WHERE user_id = ? AND penalty_month = ?
        LIMIT 1
    ");
    $checkStmt->execute([$userId, $penaltyMonth]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRecord) {
        // Update existing record
        $updateStmt = $pdo->prepare("
            UPDATE salary_penalties
            SET penalty_days = ?,
                reason = CONCAT(reason, ' | ', ?, ' (', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'), ')')
            WHERE user_id = ? AND penalty_month = ?
        ");
        $updateStmt->execute([
            $newPenalty,
            "[$action] " . $reason,
            $userId,
            $penaltyMonth
        ]);
    } else {
        // Create new record
        $insertStmt = $pdo->prepare("
            INSERT INTO salary_penalties (user_id, penalty_month, penalty_days, reason)
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $userId,
            $penaltyMonth,
            $newPenalty,
            "[$action] " . $reason
        ]);
    }

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Penalty adjustment saved successfully',
        'data' => [
            'user_id' => $userId,
            'penalty_month' => $penaltyMonth,
            'penalty_days' => $newPenalty,
            'action' => $action,
            'reason' => $reason
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in save_penalty_adjustment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Error in save_penalty_adjustment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    exit;
}
?>
