<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['employee_id', 'user_id', 'base_salary', 'month', 'year'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
        exit;
    }
}

$employee_id = trim($input['employee_id']);
$user_id = intval($input['user_id']);
$base_salary = floatval($input['base_salary']);
$month = intval($input['month']);
$year = intval($input['year']);
$remarks = isset($input['remarks']) ? trim($input['remarks']) : null;

// Validate values
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

if ($base_salary < 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Base salary cannot be negative']);
    exit;
}

if ($month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid month']);
    exit;
}

if ($year < 2000 || $year > date('Y') + 5) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid year']);
    exit;
}

try {
    // Check if user exists
    $userCheckStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
    $userCheckStmt->execute([$user_id]);
    $userExists = $userCheckStmt->fetch();

    if (!$userExists) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    // Check if salary record already exists for this period
    $checkStmt = $pdo->prepare("
        SELECT id, base_salary FROM employee_salary_records 
        WHERE user_id = ? AND month = ? AND year = ? AND deleted_at IS NULL
    ");
    $checkStmt->execute([$user_id, $month, $year]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRecord) {
        // Update existing record
        $updateStmt = $pdo->prepare("
            UPDATE employee_salary_records 
            SET base_salary = ?, remarks = ?, updated_by = ?, updated_at = NOW()
            WHERE user_id = ? AND month = ? AND year = ? AND deleted_at IS NULL
        ");
        
        $updateStmt->execute([
            $base_salary,
            $remarks,
            $_SESSION['user_id'],
            $user_id,
            $month,
            $year
        ]);

        $action = 'updated';
        $recordId = $existingRecord['id'];
    } else {
        // Insert new record
        $insertStmt = $pdo->prepare("
            INSERT INTO employee_salary_records 
            (employee_id, user_id, base_salary, month, year, remarks, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insertStmt->execute([
            $employee_id,
            $user_id,
            $base_salary,
            $month,
            $year,
            $remarks,
            $_SESSION['user_id']
        ]);

        $action = 'created';
        $recordId = $pdo->lastInsertId();
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Salary record ' . $action . ' successfully',
        'record_id' => $recordId,
        'action' => $action
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in save_salary_record.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in save_salary_record.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
