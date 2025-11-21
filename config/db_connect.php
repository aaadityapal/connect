<?php
// Database connection configuration
// Determine environment - check if we're on production or local
$is_production = false;

// Check if we're on a production server
if (isset($_SERVER['SERVER_NAME'])) {
    $hostname = $_SERVER['SERVER_NAME'];
    if (strpos($hostname, 'localhost') === false && 
        strpos($hostname, '127.0.0.1') === false &&
        strpos($hostname, '.test') === false && 
        strpos($hostname, '.local') === false) {
        $is_production = true;
    }
}

// Set appropriate database credentials based on environment
if ($is_production) {
    // Production database credentials
    $host = 'localhost';  // Update with your production database host
    $dbname = 'crm';      // Update with your production database name
    $username = 'root';   // Update with your production username
    $password = '';       // Update with your production password
} else {
    // Local development database credentials
    $host = 'localhost';
    $dbname = 'crm';
    $username = 'root';
    $password = '';
}

// Error handling function
function handleDatabaseError($message, $error = null) {
    // Log the error
    $logFile = 'logs/database_errors.log';
    if (!file_exists(dirname($logFile))) {
        @mkdir(dirname($logFile), 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = "[$timestamp] $message";
    
    if ($error !== null) {
        $errorMessage .= " - " . $error->getMessage();
        if ($error instanceof Exception) {
            $errorMessage .= "\nStack trace: " . $error->getTraceAsString();
        }
    }
    
    @error_log($errorMessage . "\n", 3, $logFile);
    
    // Don't show detailed errors in production
    if ($GLOBALS['is_production']) {
        return "Database connection error. Please try again later or contact support.";
    } else {
        return $errorMessage;
    }
}

// PDO Connection
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5, // 5 second timeout
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    
    // Set timezone to match your application's timezone
    $pdo->query("SET time_zone = '+05:30'"); // IST - adjust as needed
    
} catch (PDOException $e) {
    $errorMessage = handleDatabaseError("PDO Database Connection Error", $e);
    error_log("PDO Connection failed: " . $e->getMessage());
    
    // Don't show error details in production
    if ($is_production) {
        die("Database connection failed. Please try again later.");
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// MySQL Connection (if you still need it for backward compatibility)
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
    
    // Set character set
    $conn->set_charset("utf8mb4");
    
    // Set timezone
    $conn->query("SET time_zone = '+05:30'"); // IST - adjust as needed
    
} catch (Exception $e) {
    $errorMessage = handleDatabaseError("MySQLi Database Connection Error", $e);
    
    // If PDO is already connected, we can continue without mysqli
    if (!isset($pdo)) {
        if ($is_production) {
            die("Database connection failed. Please try again later.");
        } else {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    // Create a dummy mysqli object to prevent errors in code that expects it
    $conn = new stdClass();
    $conn->connect_error = true;
}

// Optional: Test connection health with a simple query
try {
    $test_query = "SELECT 1";
    $test_result = $pdo->query($test_query);
    if (!$test_result) {
        handleDatabaseError("Test query failed");
    }
} catch (Exception $e) {
    handleDatabaseError("Test query exception", $e);
}

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

// Test query (commented out to prevent issues)
// $test_query = "SHOW TABLES";
// $test_result = $conn->query($test_query);
// if (!$test_result) {
//     die("Query failed: " . $conn->error);
// }

// Example usage:
// try {
//     applyLeave($pdo, $userId, $leaveTypeId, $startDate, $endDate, $reason, $halfDay);
// } catch (Exception $e) {
//     error_log($e->getMessage());
//     // Handle error appropriately
// }
?>
