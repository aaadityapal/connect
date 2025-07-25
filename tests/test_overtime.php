<?php
/**
 * Test page for overtime functionality
 * This page allows testing of the overtime detection and request submission
 */

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

// Get user information
$user_query = "SELECT username FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$username = '';

if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $username = $user_data['username'];
}

// Get user's shift information
$shift_info = null;
$shift_query = "
    SELECT s.id, s.shift_name, s.start_time, s.end_time 
    FROM shifts s
    JOIN user_shifts us ON s.id = us.shift_id
    WHERE us.user_id = ?
    AND CURRENT_DATE BETWEEN us.effective_from AND IFNULL(us.effective_to, '9999-12-31')
    LIMIT 1
";

$shift_stmt = $conn->prepare($shift_query);
$shift_stmt->bind_param("i", $user_id);
$shift_stmt->execute();
$shift_result = $shift_stmt->get_result();

if ($shift_result->num_rows > 0) {
    $shift_info = $shift_result->fetch_assoc();
}

// Handle form submission for test attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_test') {
        // Get form data
        $test_date = isset($_POST['test_date']) ? $_POST['test_date'] : $current_date;
        $punch_in = isset($_POST['punch_in']) ? $_POST['punch_in'] : '';
        $punch_out = isset($_POST['punch_out']) ? $_POST['punch_out'] : '';
        
        // Validate inputs
        if (empty($test_date) || empty($punch_in) || empty($punch_out)) {
            $error = "Please fill in all fields.";
        } else {
            // Check if an attendance record already exists for this date
            $check_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("is", $user_id, $test_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing record
                $row = $check_result->fetch_assoc();
                $attendance_id = $row['id'];
                
                $update_query = "UPDATE attendance 
                                SET punch_in = ?, punch_out = ?, 
                                approval_status = 'approved', 
                                overtime_status = 'pending',
                                status = 'present',
                                modified_at = NOW() 
                                WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssi", $punch_in, $punch_out, $attendance_id);
                
                if ($update_stmt->execute()) {
                    $message = "Test attendance record updated successfully.";
                } else {
                    $error = "Error updating attendance record: " . $conn->error;
                }
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
                $insert_stmt->bind_param("isss", $user_id, $test_date, $punch_in, $punch_out);
                
                if ($insert_stmt->execute()) {
                    $message = "Test attendance record created successfully.";
                } else {
                    $error = "Error creating attendance record: " . $conn->error;
                }
            }
        }
    } elseif ($_POST['action'] === 'test_overtime') {
        // Call the get_working_hours.php endpoint directly
        $test_date = isset($_POST['test_date']) ? $_POST['test_date'] : $current_date;
        
        // Create cURL request to get_working_hours.php
        $ch = curl_init();
        $post_data = http_build_query([
            'user_id' => $user_id,
            'date' => $test_date
        ]);
        
        // Use absolute URL path to avoid path issues
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
        $script_path = dirname($_SERVER['SCRIPT_NAME']);
        // Use the simplified endpoint for testing
        $endpoint_url = $base_url . rtrim($script_path, '/tests') . '/ajax_handlers/get_working_hours_simple.php';
        
        // Pass session cookie to maintain login state
        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = $name . '=' . $value;
        }
        
        curl_setopt($ch, CURLOPT_URL, $endpoint_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookies));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                $message = "Working hours calculation successful. Working hours: " . $result['working_hours'];
                
                if ($result['has_overtime']) {
                    $message .= "<br>Overtime detected: " . $result['overtime_hours'] . " beyond shift end time (" . $result['shift_end_time'] . ").";
                } else {
                    $message .= "<br>No overtime detected.";
                }
            } else {
                $error = "Error calculating working hours: " . ($result['message'] ?? 'Unknown error');
                // Add response details for debugging
                $error .= "<br>Response: " . htmlspecialchars($response);
            }
        } else {
            $curl_error = curl_error($ch);
            $error = "Error connecting to working hours endpoint. URL: " . $endpoint_url;
            if ($curl_error) {
                $error .= "<br>cURL Error: " . $curl_error;
            }
        }
    } elseif ($_POST['action'] === 'test_request') {
        // Call the submit_overtime_request.php endpoint
        $test_date = isset($_POST['test_date']) ? $_POST['test_date'] : $current_date;
        $overtime_hours = isset($_POST['overtime_hours']) ? $_POST['overtime_hours'] : '01:30:00';
        $shift_end_time = isset($_POST['shift_end_time']) ? $_POST['shift_end_time'] : '18:00:00';
        $overtime_reason = isset($_POST['overtime_reason']) ? $_POST['overtime_reason'] : 'Worked late to complete urgent task';
        
        // Create cURL request to submit_overtime_request.php
        $ch = curl_init();
        $post_data = http_build_query([
            'user_id' => $user_id,
            'date' => $test_date,
            'overtime_hours' => $overtime_hours,
            'shift_end_time' => $shift_end_time,
            'overtime_reason' => $overtime_reason
        ]);
        
        // Use absolute URL path to avoid path issues
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
        $script_path = dirname($_SERVER['SCRIPT_NAME']);
        // Use the simplified endpoint for testing
        $endpoint_url = $base_url . rtrim($script_path, '/tests') . '/ajax_handlers/submit_overtime_request_simple.php';
        
        // Pass session cookie to maintain login state
        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = $name . '=' . $value;
        }
        
        curl_setopt($ch, CURLOPT_URL, $endpoint_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookies));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                $message = "Overtime request submitted successfully. Notification ID: " . $result['notification_id'];
            } else {
                $error = "Error submitting overtime request: " . ($result['message'] ?? 'Unknown error');
                // Add response details for debugging
                $error .= "<br>Response: " . htmlspecialchars($response);
            }
        } else {
            $curl_error = curl_error($ch);
            $error = "Error connecting to overtime request endpoint. URL: " . $endpoint_url;
            if ($curl_error) {
                $error .= "<br>cURL Error: " . $curl_error;
            }
        }
    }
}

// Get recent attendance records for this user
$attendance_query = "SELECT id, date, punch_in, punch_out, approval_status 
                    FROM attendance 
                    WHERE user_id = ? 
                    ORDER BY date DESC 
                    LIMIT 10";
$attendance_stmt = $conn->prepare($attendance_query);
$attendance_stmt->bind_param("i", $user_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();

// Get recent overtime requests for this user
$overtime_query = "SELECT n.id, n.message, n.status, n.manager_response, n.created_at, n.read_at, n.actioned_at,
                         a.overtime_status, a.overtime_approved_by, a.overtime_actioned_at, a.date
                  FROM overtime_notifications n
                  LEFT JOIN attendance a ON n.employee_id = a.user_id AND DATE(n.created_at) = a.date
                  WHERE n.employee_id = ? 
                  ORDER BY n.created_at DESC 
                  LIMIT 10";
$overtime_stmt = $conn->prepare($overtime_query);
$overtime_stmt->bind_param("i", $user_id);
$overtime_stmt->execute();
$overtime_result = $overtime_stmt->get_result();

// Format time function
function formatTime($time) {
    if (!$time) return '';
    
    // If it's a full datetime, extract just the time part
    if (strlen($time) > 8) {
        $time = substr($time, 11, 8);
    }
    
    $parts = explode(':', $time);
    if (count($parts) !== 3) return $time;
    
    $hour = intval($parts[0]);
    $ampm = $hour >= 12 ? 'PM' : 'AM';
    $hour12 = $hour % 12;
    if ($hour12 === 0) $hour12 = 12;
    
    return sprintf('%d:%s:%s %s', $hour12, $parts[1], $parts[2], $ampm);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Functionality Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f7fa;
        }
        
        h1, h2, h3 {
            color: #2c3e50;
        }
        
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
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
            font-weight: 600;
        }
        
        input[type="date"],
        input[type="time"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-style: italic;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: #2ecc71;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-warning {
            background-color: #f39c12;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending {
            background-color: #f39c12;
            color: white;
        }
        
        .badge-approved {
            background-color: #2ecc71;
            color: white;
        }
        
        .badge-rejected {
            background-color: #e74c3c;
            color: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .user-info i {
            font-size: 24px;
            color: #3498db;
        }
        
        .user-info .details {
            display: flex;
            flex-direction: column;
        }
        
        .user-info .username {
            font-weight: 600;
            font-size: 18px;
        }
        
        .user-info .shift {
            font-size: 14px;
            color: #666;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            border-bottom: 2px solid #3498db;
            font-weight: 600;
            color: #3498db;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <h1><i class="fas fa-clock"></i> Overtime Functionality Test</h1>
    
    <div class="user-info">
        <i class="fas fa-user-circle"></i>
        <div class="details">
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
            <?php if ($shift_info): ?>
                <span class="shift">Shift: <?php echo htmlspecialchars($shift_info['shift_name']); ?> (<?php echo formatTime($shift_info['start_time']); ?> - <?php echo formatTime($shift_info['end_time']); ?>)</span>
            <?php else: ?>
                <span class="shift">No shift assigned</span>
            <?php endif; ?>
        </div>
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
    
    <div class="tabs">
        <div class="tab active" data-tab="create-test">Create Test Attendance</div>
        <div class="tab" data-tab="test-overtime">Test Overtime Calculation</div>
        <div class="tab" data-tab="test-request">Test Overtime Request</div>
        <div class="tab" data-tab="view-records">View Records</div>
    </div>
    
    <div class="tab-content active" id="create-test">
        <div class="card">
            <h2>Create Test Attendance Record</h2>
            <p>Use this form to create a test attendance record with specific punch-in and punch-out times.</p>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="create_test">
                
                <div class="form-group">
                    <label for="test_date">Date:</label>
                    <input type="date" id="test_date" name="test_date" value="<?php echo $current_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="punch_in">Punch In Time:</label>
                    <input type="time" id="punch_in" name="punch_in" step="1" required>
                </div>
                
                <div class="form-group">
                    <label for="punch_out">Punch Out Time:</label>
                    <input type="time" id="punch_out" name="punch_out" step="1" required>
                </div>
                
                <button type="submit" class="btn-success">
                    <i class="fas fa-save"></i> Create Test Record
                </button>
            </form>
        </div>
    </div>
    
    <div class="tab-content" id="test-overtime">
        <div class="card">
            <h2>Test Overtime Calculation</h2>
            <p>Test the overtime calculation for a specific date.</p>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="test_overtime">
                
                <div class="form-group">
                    <label for="test_date_ot">Date:</label>
                    <input type="date" id="test_date_ot" name="test_date" value="<?php echo $current_date; ?>" required>
                </div>
                
                <button type="submit">
                    <i class="fas fa-calculator"></i> Calculate Working Hours & Overtime
                </button>
            </form>
        </div>
    </div>
    
    <div class="tab-content" id="test-request">
        <div class="card">
            <h2>Test Overtime Request</h2>
            <p>Test submitting an overtime request manually.</p>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="test_request">
                
                <div class="form-group">
                    <label for="test_date_req">Date:</label>
                    <input type="date" id="test_date_req" name="test_date" value="<?php echo $current_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="overtime_hours">Overtime Hours (HH:MM:SS):</label>
                    <input type="text" id="overtime_hours" name="overtime_hours" value="01:30:00" required pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}">
                </div>
                
                <div class="form-group">
                    <label for="overtime_reason">Overtime Reason:</label>
                    <textarea id="overtime_reason" name="overtime_reason" rows="3" placeholder="Enter reason for overtime" required>Worked late to complete urgent task</textarea>
                    <small>This reason will be sent to your manager with the overtime request.</small>
                </div>
                
                <div class="form-group">
                    <label for="shift_end_time">Shift End Time (HH:MM:SS):</label>
                    <input type="text" id="shift_end_time" name="shift_end_time" value="<?php echo $shift_info ? $shift_info['end_time'] : '18:00:00'; ?>" required pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}">
                </div>
                
                <button type="submit" class="btn-warning">
                    <i class="fas fa-paper-plane"></i> Submit Overtime Request
                </button>
            </form>
        </div>
    </div>
    
    <div class="tab-content" id="view-records">
        <div class="container">
            <div class="card">
                <h2>Recent Attendance Records</h2>
                
                <?php if ($attendance_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $attendance_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['date']); ?></td>
                                    <td><?php echo formatTime($row['punch_in']); ?></td>
                                    <td><?php echo formatTime($row['punch_out']); ?></td>
                                    <td>
                                        <?php if ($row['approval_status'] === 'pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php elseif ($row['approval_status'] === 'approved'): ?>
                                            <span class="badge badge-approved">Approved</span>
                                        <?php elseif ($row['approval_status'] === 'rejected'): ?>
                                            <span class="badge badge-rejected">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No attendance records found.</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Recent Overtime Notifications</h2>
                
                <?php if ($overtime_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Manager Response</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $overtime_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['date'] ?? date('Y-m-d', strtotime($row['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($row['message']); ?></td>
                                    <td>
                                        <?php 
                                        $status = $row['overtime_status'] ?? $row['status'];
                                        if ($status === 'pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php elseif ($status === 'submitted'): ?>
                                            <span class="badge badge-pending">Submitted</span>
                                        <?php elseif ($status === 'approved'): ?>
                                            <span class="badge badge-approved">Approved</span>
                                        <?php elseif ($status === 'rejected'): ?>
                                            <span class="badge badge-rejected">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge"><?php echo htmlspecialchars($status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($row['manager_response']) ? htmlspecialchars($row['manager_response']) : '-'; ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No overtime requests found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default times for the form
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            
            // Set default punch in time (9:00 AM)
            document.getElementById('punch_in').value = '09:00:00';
            
            // Set default punch out time (current time or 6:00 PM + 1:30 hours for overtime testing)
            const endTime = '19:30:00'; // 6:00 PM + 1:30 hours
            document.getElementById('punch_out').value = endTime;
            
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and tab contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html> 