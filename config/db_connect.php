<?php
$host = 'localhost';
$dbname = 'crm';
$username = 'root';
$password = '';

// PDO Connection
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Set timezone
    $pdo->query("SET time_zone = '+05:30'");
    
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}

// MySQL Connection (if you still need it)
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+05:30'");

// Leave application function using PDO instead of mysqli
function applyLeave($pdo, $userId, $leaveTypeId, $startDate, $endDate, $reason, $halfDay) {
    $stmt = $pdo->prepare("
        INSERT INTO leaves (
            user_id, 
            leave_type_id, 
            start_date, 
            end_date, 
            reason, 
            half_day,
            status,
            created_at
        ) VALUES (
            :userId,
            :leaveTypeId,
            :startDate,
            :endDate,
            :reason,
            :halfDay,
            'Pending',
            NOW()
        )
    ");
    
    return $stmt->execute([
        ':userId' => $userId,
        ':leaveTypeId' => $leaveTypeId,
        ':startDate' => $startDate,
        ':endDate' => $endDate,
        ':reason' => $reason,
        ':halfDay' => $halfDay
    ]);
}

// Test query
$test_query = "SHOW TABLES";
$test_result = $conn->query($test_query);
if (!$test_result) {
    die("Query failed: " . $conn->error);
}

// Example usage:
// try {
//     applyLeave($pdo, $userId, $leaveTypeId, $startDate, $endDate, $reason, $halfDay);
// } catch (Exception $e) {
//     error_log($e->getMessage());
//     // Handle error appropriately
// }
?>
