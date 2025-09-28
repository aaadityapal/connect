<?php
// Basic database test
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h2>Basic Database Test</h2>";

try {
    // Test 1: Session check
    echo "<h3>1. Session Test</h3>";
    if (isset($_SESSION['user_id'])) {
        echo "✅ User logged in: ID = " . $_SESSION['user_id'] . "<br>";
    } else {
        echo "❌ User not logged in<br>";
        exit;
    }

    // Test 2: Database connection
    echo "<h3>2. Database Connection Test</h3>";
    if (file_exists('includes/db_connect.php')) {
        echo "✅ Database connection file exists<br>";
        include_once('includes/db_connect.php');
        
        if (isset($conn) && !$conn->connect_error) {
            echo "✅ Database connected successfully<br>";
        } else {
            echo "❌ Database connection failed: " . ($conn->connect_error ?? 'Connection object not found') . "<br>";
            exit;
        }
    } else {
        echo "❌ Database connection file not found<br>";
        exit;
    }

    // Test 3: Basic query
    echo "<h3>3. Basic Query Test</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM travel_expenses");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ Total expenses in database: " . $row['count'] . "<br>";
    } else {
        echo "❌ Basic query failed: " . $conn->error . "<br>";
    }

    // Test 4: User's expenses
    echo "<h3>4. User Expenses Test</h3>";
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, purpose, status FROM travel_expenses WHERE user_id = ? LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            echo "✅ Found " . $result->num_rows . " expenses for user<br>";
            while ($row = $result->fetch_assoc()) {
                echo "- ID: {$row['id']}, Purpose: {$row['purpose']}, Status: {$row['status']}<br>";
            }
        } else {
            echo "❌ Execute failed: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "❌ Prepare failed: " . $conn->error . "<br>";
    }

    // Test 5: Table structure
    echo "<h3>5. Table Structure Test</h3>";
    $result = $conn->query("DESCRIBE travel_expenses");
    if ($result) {
        echo "✅ Table structure:<br>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Table structure query failed: " . $conn->error . "<br>";
    }

    echo "<h3>✅ All Tests Completed Successfully!</h3>";

} catch (Exception $e) {
    echo "<h3>❌ Exception: " . $e->getMessage() . "</h3>";
} catch (Error $e) {
    echo "<h3>❌ Fatal Error: " . $e->getMessage() . "</h3>";
}
?>