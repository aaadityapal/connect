<?php
/**
 * Simple test page for attendance records
 * With detailed error reporting
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once '../config/db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please log in first.";
    exit;
}

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $test_date = isset($_POST['test_date']) ? $_POST['test_date'] : $current_date;
        $punch_in = isset($_POST['punch_in']) ? $_POST['punch_in'] : '';
        $punch_out = isset($_POST['punch_out']) ? $_POST['punch_out'] : '';
        
        // Validate inputs
        if (empty($test_date) || empty($punch_in) || empty($punch_out)) {
            throw new Exception("Please fill in all fields.");
        }
        
        // Check if an attendance record already exists for this date
        $check_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ?";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $check_stmt->bind_param("is", $user_id, $test_date);
        if (!$check_stmt->execute()) {
            throw new Exception("Execute failed: " . $check_stmt->error);
        }
        
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $row = $check_result->fetch_assoc();
            $attendance_id = $row['id'];
            
            $update_query = "UPDATE attendance 
                            SET punch_in = ?, 
                                punch_out = ?, 
                                approval_status = 'approved', 
                                overtime_status = 'pending',
                                status = 'present',
                                modified_at = NOW() 
                            WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            if (!$update_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $update_stmt->bind_param("ssi", $punch_in, $punch_out, $attendance_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Execute failed: " . $update_stmt->error);
            }
            
            $message = "Test attendance record updated successfully.";
        } else {
            // Create new record with all required fields
            $insert_query = "INSERT INTO attendance 
                            (user_id, date, punch_in, punch_out, 
                            approval_status, overtime_status, status,
                            created_at, modified_at) 
                            VALUES (?, ?, ?, ?, 
                            'approved', 'pending', 'present',
                            NOW(), NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            if (!$insert_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $insert_stmt->bind_param("isss", $user_id, $test_date, $punch_in, $punch_out);
            if (!$insert_stmt->execute()) {
                throw new Exception("Execute failed: " . $insert_stmt->error);
            }
            
            $message = "Test attendance record created successfully.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get username
$username = '';
$user_query = "SELECT username FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $username = $user_data['username'];
}

// Get recent attendance records for this user
$attendance_query = "SELECT id, date, punch_in, punch_out, approval_status, overtime_status 
                    FROM attendance 
                    WHERE user_id = ? 
                    ORDER BY date DESC 
                    LIMIT 5";
$attendance_stmt = $conn->prepare($attendance_query);
$attendance_stmt->bind_param("i", $user_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();

// Format time function
function formatTime($time) {
    if (!$time) return '';
    
    // If it's a full datetime, extract just the time part
    if (strlen($time) > 8) {
        $time = substr($time, 11, 8);
    }
    
    return $time;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Attendance Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }
        
        h1, h2 {
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        form {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="date"],
        input[type="time"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Attendance Test</h1>
        
        <div class="card">
            <h2>User: <?php echo htmlspecialchars($username); ?></h2>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Create Test Attendance Record</h2>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="test_date">Date:</label>
                    <input type="date" id="test_date" name="test_date" value="<?php echo $current_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="punch_in">Punch In Time:</label>
                    <input type="time" id="punch_in" name="punch_in" value="09:00:00" step="1" required>
                </div>
                
                <div class="form-group">
                    <label for="punch_out">Punch Out Time:</label>
                    <input type="time" id="punch_out" name="punch_out" value="18:00:00" step="1" required>
                </div>
                
                <button type="submit">Create Test Record</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Recent Attendance Records</h2>
            
            <?php if ($attendance_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Approval Status</th>
                            <th>Overtime Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $attendance_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo formatTime($row['punch_in']); ?></td>
                                <td><?php echo formatTime($row['punch_out']); ?></td>
                                <td><?php echo htmlspecialchars($row['approval_status']); ?></td>
                                <td><?php echo htmlspecialchars($row['overtime_status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No attendance records found.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Database Table Structure</h2>
            <p>Showing structure of the attendance table:</p>
            
            <?php
            $table_structure = $conn->query("DESCRIBE attendance");
            if ($table_structure): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Null</th>
                            <th>Key</th>
                            <th>Default</th>
                            <th>Extra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($column = $table_structure->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
                                <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Could not retrieve table structure.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 