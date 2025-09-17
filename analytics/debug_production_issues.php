<?php
// Production Issue Diagnostic Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h2>Production Issue Diagnostic Report</h2>";
echo "<hr>";

// 1. Check database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    require_once '../config/db_connect.php';
    echo "✅ Database connection successful<br>";
    
    // Test basic query
    $test_query = "SELECT COUNT(*) as user_count FROM users";
    $stmt = $pdo->prepare($test_query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Basic query successful - Users found: " . $result['user_count'] . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// 2. Check session status
echo "<h3>2. Session Status</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in - User ID: " . $_SESSION['user_id'] . "<br>";
    echo "✅ User Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
} else {
    echo "❌ User not logged in<br>";
}

// 3. Check required tables
echo "<h3>3. Required Tables Check</h3>";
$required_tables = [
    'users',
    'attendance', 
    'leave_request',
    'leave_types',
    'salary_increments',
    'user_shifts',
    'shifts',
    'office_holidays'
];

foreach ($required_tables as $table) {
    try {
        $check_query = "SHOW TABLES LIKE '$table'";
        $result = $pdo->query($check_query);
        if ($result->rowCount() > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    } catch (PDOException $e) {
        echo "❌ Error checking table '$table': " . $e->getMessage() . "<br>";
    }
}

// 4. Check optional tables
echo "<h3>4. Optional Tables Check</h3>";
$optional_tables = [
    'short_leave_preferences',
    'salary_payments',
    'incremented_salary_analytics'
];

foreach ($optional_tables as $table) {
    try {
        $check_query = "SHOW TABLES LIKE '$table'";
        $result = $pdo->query($check_query);
        if ($result->rowCount() > 0) {
            echo "✅ Optional table '$table' exists<br>";
        } else {
            echo "⚠️ Optional table '$table' missing (will be created if needed)<br>";
        }
    } catch (PDOException $e) {
        echo "❌ Error checking optional table '$table': " . $e->getMessage() . "<br>";
    }
}

// 5. Check file permissions and paths
echo "<h3>5. File System Check</h3>";
$files_to_check = [
    '../config/db_connect.php',
    'salary_analytics_dashboard.php',
    'save_incremented_salary.php',
    'update_short_leave_usage.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ File '$file' exists<br>";
        if (is_readable($file)) {
            echo "✅ File '$file' is readable<br>";
        } else {
            echo "❌ File '$file' is not readable<br>";
        }
    } else {
        echo "❌ File '$file' missing<br>";
    }
}

// 6. Test critical queries
echo "<h3>6. Critical Queries Test</h3>";
try {
    // Test users query
    $users_query = "SELECT id, username, base_salary FROM users WHERE status = 'active' AND deleted_at IS NULL LIMIT 1";
    $stmt = $pdo->prepare($users_query);
    $stmt->execute();
    $user_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_test) {
        echo "✅ Users query successful<br>";
        
        // Test attendance query
        $current_month = date('Y-m');
        $attendance_query = "SELECT COUNT(*) as att_count FROM attendance WHERE DATE_FORMAT(date, '%Y-%m') = ? LIMIT 1";
        $stmt = $pdo->prepare($attendance_query);
        $stmt->execute([$current_month]);
        $att_result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Attendance query successful - Records: " . $att_result['att_count'] . "<br>";
        
    } else {
        echo "❌ No active users found<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Critical query failed: " . $e->getMessage() . "<br>";
}

// 7. Check PHP configuration
echo "<h3>7. PHP Configuration</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "Error Reporting: " . error_reporting() . "<br>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";

// 8. Test specific month query
echo "<h3>8. Monthly Data Test</h3>";
try {
    $test_month = date('Y-m');
    echo "Testing month: $test_month<br>";
    
    $monthly_test_query = "SELECT 
        u.id, u.username, u.base_salary,
        COALESCE(att.present_days, 0) as present_days
        FROM users u 
        LEFT JOIN (
            SELECT user_id, COUNT(*) as present_days
            FROM attendance 
            WHERE DATE_FORMAT(date, '%Y-%m') = ?
            AND status = 'present'
            GROUP BY user_id
        ) att ON u.id = att.user_id
        WHERE u.status = 'active' AND u.deleted_at IS NULL 
        LIMIT 3";
    
    $stmt = $pdo->prepare($monthly_test_query);
    $stmt->execute([$test_month]);
    $monthly_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($monthly_results)) {
        echo "✅ Monthly data query successful - Sample results:<br>";
        foreach ($monthly_results as $row) {
            echo "- User: {$row['username']}, Base Salary: {$row['base_salary']}, Present Days: {$row['present_days']}<br>";
        }
    } else {
        echo "⚠️ No monthly data found<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Monthly data query failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>If you see any ❌ errors above, those need to be fixed for the page to work properly.</strong></p>";
echo "<p>Save this report and share it to help identify the specific production issue.</p>";
?>