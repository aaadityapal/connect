<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Fetch active leave types
$leave_types_query = "SELECT DISTINCT lt.id, lt.name, lt.description, lt.max_days, lt.carry_forward, lt.paid, lt.color_code 
                     FROM leave_types lt
                     WHERE lt.status = 1 
                     GROUP BY lt.id, lt.name, lt.description, lt.max_days, lt.carry_forward, lt.paid, lt.color_code
                     ORDER BY lt.name ASC";

// Alternative query if the above doesn't work
// $leave_types_query = "SELECT * FROM leave_types 
//                      WHERE status = 1 
//                      ORDER BY name ASC";

$leave_types_result = $conn->query($leave_types_query);
$leave_types = [];
while ($row = $leave_types_result->fetch_assoc()) {
    // Use ID as key to ensure uniqueness
    $leave_types[$row['id']] = $row;
}

// Fetch user's leave balance for current year
$balance_query = "SELECT 
    lt.id,
    lt.name,
    lt.description,
    lt.color_code,
    CASE 
        WHEN lt.name = 'Compensate Leave' THEN (
            SELECT COUNT(*) 
            FROM attendance a
            JOIN user_shifts us ON a.user_id = us.user_id
                AND a.date >= us.effective_from
                AND (us.effective_to IS NULL OR a.date <= us.effective_to)
            WHERE a.user_id = ? 
            AND a.status = 'present'
            AND DAYNAME(a.date) = us.weekly_offs
            AND YEAR(a.date) = YEAR(CURRENT_DATE())
        )
        WHEN lt.name = 'Half Day Leave' THEN 30  -- Set default max days for Half Day Leave
        ELSE lt.max_days
    END as total_leaves,
    COALESCE(
        CASE 
            WHEN lt.name = 'Compensate Leave' THEN (
                SELECT COALESCE(SUM(duration), 0)
                FROM leave_request lr
                WHERE lr.user_id = ? 
                AND lr.leave_type = lt.id
                AND lr.status = 'approved'
                AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())
            )
            WHEN lt.name = 'Half Day Leave' THEN (
                SELECT COUNT(*)  -- Count number of half day leaves
                FROM leave_request lr
                WHERE lr.user_id = ? 
                AND lr.leave_type = lt.id
                AND lr.status = 'approved'
                AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())
            )
            ELSE (
                SELECT COALESCE(SUM(duration), 0)
                FROM leave_request lr
                WHERE lr.user_id = ? 
                AND lr.leave_type = lt.id
                AND lr.status = 'approved'
                AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())
            )
        END, 0
    ) as used_leaves
FROM leave_types lt
WHERE lt.status = 'active'";

$stmt = $conn->prepare($balance_query);
$stmt->bind_param('iiii', $user_id, $user_id, $user_id, $user_id);  // Added one more parameter
$stmt->execute();
$balance_result = $stmt->get_result();
$leave_balance = $balance_result->fetch_all(MYSQLI_ASSOC);

// Fetch user's leave history
$history_query = "SELECT 
    lr.*,
    lt.name as leave_type_name,
    lt.color_code,
    COALESCE(u.username, 'Not Available') as action_by_name
    FROM leave_request lr 
    JOIN leave_types lt ON lr.leave_type = lt.id 
    LEFT JOIN users u ON lr.action_by = u.id
    WHERE lr.user_id = ? 
    ORDER BY lr.created_at DESC";

// Add filter handling
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Modify query based on filters
if ($filter_year != 'all') {
    $history_query = str_replace("WHERE lr.user_id = ?", 
                               "WHERE lr.user_id = ? AND YEAR(lr.created_at) = '$filter_year'", 
                               $history_query);
}
if ($filter_status != 'all') {
    $history_query .= " AND lr.status = '$filter_status'";
}
if ($filter_type != 'all') {
    $history_query .= " AND lr.leave_type = '$filter_type'";
}

$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history_result = $stmt->get_result();
$leave_history = [];
while ($row = $history_result->fetch_assoc()) {
    $leave_history[] = $row;
}

// Handle leave application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $user_id = $_SESSION['user_id'];
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : $_POST['start_date'];
    $half_day_type = isset($_POST['half_day_type']) ? $_POST['half_day_type'] : null;
    $reason = $_POST['reason'];
    
    // Get leave type details
    $leave_type_query = "SELECT name FROM leave_types WHERE id = ?";
    $stmt = $conn->prepare($leave_type_query);
    $stmt->bind_param("i", $leave_type);
    $stmt->execute();
    $leave_type_result = $stmt->get_result();
    $leave_type_data = $leave_type_result->fetch_assoc();
    
    // Calculate duration
    if ($leave_type_data['name'] === 'Half Day Leave') {
        $duration = 0.5;  // Set duration to 0.5 for half day leave
        $end_date = $start_date; // For half day, end date is same as start date
    } else {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $duration = $interval->days + 1;
    }

    try {
        // Insert into leave_request table
        $insert_query = "INSERT INTO leave_request (
            user_id,
            leave_type,
            start_date,
            end_date,
            half_day_type,
            duration,
            reason,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iisssds", 
            $user_id,
            $leave_type,
            $start_date,
            $end_date,
            $half_day_type,
            $duration,  // This will now be 0.5 for half day leaves
            $reason
        );

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Leave application submitted successfully!";
            header("Location: leave.php");
            exit();
        } else {
            throw new Exception("Error submitting leave application: " . $conn->error);
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        error_log("Leave Application Error: " . $e->getMessage());
    }
}

// Make sure your leaves table has the correct structure
$create_table_query = "
CREATE TABLE IF NOT EXISTS leaves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reporting_manager INT,
    manager_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    manager_comment TEXT,
    manager_action_date DATETIME,
    hr_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    hr_comment TEXT,
    hr_action_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    FOREIGN KEY (reporting_manager) REFERENCES users(id)
)";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Application</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add your CSS styles here */
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #6366f1;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
            color: var(--gray-700);
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .leave-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .leave-card {
            background: white;
            padding: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .leave-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--card-color);
        }

        .leave-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-color: var(--card-color);
        }

        .leave-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .leave-type {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--card-color);
        }

        .leave-balance {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--card-color);
        }

        .days-label {
            font-size: 0.9rem;
            color: #666;
        }

        .leave-description {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        .apply-leave-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .section-title {
            color: #2c3e50;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8fafc;
        }

        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
            background-color: #fff;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .submit-btn {
            background-color: #6366f1;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background-color: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .submit-btn i {
            font-size: 1.1rem;
        }

        .leave-history {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 15px;
        }

        .history-table th {
            background-color: #f8fafc;
            color: #4a5568;
            font-weight: 600;
            padding: 12px 20px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .history-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #e2e8f0;
            color: #4b5563;
        }

        .history-table tr:hover {
            background-color: #f8fafc;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .progress {
            background-color: #f5f5f5;
            height: 6px;
            border-radius: 3px;
            margin: 10px 0;
        }

        .progress-bar {
            height: 100%;
            border-radius: 3px;
            background-color: var(--card-color);
        }

        .restriction-btn {
            background: none;
            border: 1px solid var(--card-color);
            color: var(--card-color);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            margin-top: 10px;
        }

        .restriction-btn:hover {
            background: var(--card-color);
            color: white;
        }

        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: flex-start;
            justify-content: center;
        }

        .modal.show {
            display: flex; /* Will only show when this class is added */
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin-top: 5%;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 95%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .rules-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .rules-table th {
            background-color: #f5f5f5;
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: 600;
        }

        .rules-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
        }

        .rules-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .close-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #f0f0f0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .close-btn:hover {
            background-color: #e0e0e0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .history-table {
                display: block;
                overflow-x: auto;
            }
        }

        .filters-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #f8fafc;
        }

        .status-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .status-badge {
            padding: 4px 8px;
            font-size: 0.75rem;
        }

        .status-comment {
            font-size: 0.75rem;
            color: #666;
            margin-top: 2px;
        }

        .status-date {
            font-size: 0.7rem;
            color: #888;
        }

        .days-display {
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #f3f4f6;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            color: #4b5563;
        }

        .days-count {
            font-weight: 600;
            color: #6366f1;
        }

        .days-display.warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .days-display.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Leave Application</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user_data['username']); ?></span>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="leave-grid">
            <?php foreach ($leave_balance as $balance): ?>
                <div class="leave-card" style="--card-color: <?php echo $balance['color_code']; ?>">
                    <div class="leave-card-header">
                        <span class="leave-type">
                            <?php echo htmlspecialchars($balance['name']); ?>
                        </span>
                        <span class="leave-balance">
                            <?php 
                            if ($balance['name'] === 'Half Day Leave') {
                                $remaining = $balance['total_leaves'] - $balance['used_leaves'];
                                echo $remaining . '/' . $balance['total_leaves'];
                            } else {
                                $remaining = $balance['total_leaves'] - $balance['used_leaves'];
                                echo $remaining . '/' . $balance['total_leaves'];
                            }
                            ?>
                        </span>
                    </div>
                    <div class="leave-card-body">
                        <div class="progress">
                            <div class="progress-bar" 
                                 style="width: <?php 
                                 if ($balance['name'] === 'Half Day Leave') {
                                     echo ($balance['used_leaves'] / ($balance['total_leaves'] ?: 1)) * 100;
                                 } else {
                                     echo ($balance['used_leaves'] / ($balance['total_leaves'] ?: 1)) * 100;
                                 }
                                 ?>%">
                            </div>
                        </div>
                        <span class="days-label">days remaining</span>
                        <p class="leave-description">
                            <?php echo htmlspecialchars($balance['description']); ?>
                        </p>
                        <?php if ($balance['name'] === 'Casual Leave'): ?>
                            <button class="restriction-btn" onclick="showRestrictions()">
                                Leave Restriction Rules
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="apply-leave-section">
            <h2 class="section-title">Apply for Leave</h2>
            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="leave_type">Leave Type</label>
                        <select class="form-control" name="leave_type" id="leave_type" required>
                            <option value="">Select Leave Type</option>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" 
                                        data-max-days="<?php echo $type['max_days']; ?>"
                                        data-is-half-day="<?php echo $type['name'] === 'Half Day Leave' ? '1' : '0'; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?> 
                                    (Max: <?php echo $type['max_days']; ?> days)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="start_date">Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" required>
                    </div>

                    <div class="form-group" id="halfDaySection" style="display: none;">
                        <label class="form-label" for="half_day_type">Time</label>
                        <select class="form-control" name="half_day_type" id="half_day_type">
                            <option value="first_half">First Half (Morning)</option>
                            <option value="second_half">Second Half (Afternoon)</option>
                        </select>
                    </div>

                    <div class="form-group" id="endDateSection">
                        <label class="form-label" for="end_date">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" required>
                    </div>
                </div>

                <div class="days-display" id="daysDisplay" style="display: none;">
                    You are requesting leave for <span class="days-count">0</span> day(s)
                </div>

                <div class="form-group">
                    <label class="form-label" for="reason">Reason</label>
                    <textarea class="form-control" name="reason" id="reason" rows="4" required></textarea>
                </div>

                <button type="submit" name="submit_leave" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    Submit Application
                </button>
            </form>
        </div>

        <div class="leave-history">
            <h2 class="section-title">Leave History</h2>
            
            <div class="filters-section">
                <div class="filter-group">
                    <label>Year:</label>
                    <select class="filter-select" onchange="applyFilters()">
                        <option value="all" <?php echo $filter_year == 'all' ? 'selected' : ''; ?>>All Years</option>
                        <?php 
                        $current_year = date('Y');
                        for($y = $current_year; $y >= $current_year - 5; $y--) {
                            echo "<option value='$y' " . ($filter_year == $y ? 'selected' : '') . ">$y</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status:</label>
                    <select class="filter-select" onchange="applyFilters()">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Leave Type:</label>
                    <select class="filter-select" onchange="applyFilters()">
                        <option value="all">All Types</option>
                        <?php foreach ($leave_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <table class="history-table">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Applied On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_history as $leave): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                            <td>
                                <?php 
                                // Format the duration to show 1 day for half day leaves
                                if ($leave['leave_type_name'] === 'Half Day Leave') {
                                    echo '1';
                                } else {
                                    echo number_format($leave['duration'], 1);
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                            <td>
                                <div class="status-cell">
                                    <span class="status-badge status-<?php echo $leave['status']; ?>">
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span>
                                    <?php if ($leave['action_comments']): ?>
                                        <span class="status-comment">
                                            "<?php echo htmlspecialchars($leave['action_comments']); ?>"
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($leave['action_at']): ?>
                                        <span class="status-date">
                                            <?php echo date('M d, Y', strtotime($leave['action_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($leave['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="restrictionModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Leave Restriction Rules</h3>
            <table class="rules-table">
                <thead>
                    <tr>
                        <th>Total Leave Days</th>
                        <th>Apply Before</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Applying leave for 1 day</td><td>0 Day</td></tr>
                    <tr><td>Applying leave for 2 days</td><td>2 Days</td></tr>
                    <tr><td>Applying leave for 3 days</td><td>5 Days</td></tr>
                    <tr><td>Applying leave for 4 days</td><td>5 Days</td></tr>
                    <tr><td>Applying leave for 5 days</td><td>7 Days</td></tr>
                    <tr><td>Applying leave for 6 days</td><td>7 Days</td></tr>
                    <tr><td>Applying leave for 7 days</td><td>7 Days</td></tr>
                    <tr><td>Applying leave for 8 days</td><td>7 Days</td></tr>
                    <tr><td>Applying leave for 10 days</td><td>7 Days</td></tr>
                    <tr><td>Applying leave for 11 days</td><td>7 Days</td></tr>
                    <tr><td>Applying leave for 12 days</td><td>7 Days</td></tr>
                </tbody>
            </table>
            <button class="close-btn" onclick="hideRestrictions()">CLOSE</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const daysDisplay = document.getElementById('daysDisplay');
            const daysCount = daysDisplay.querySelector('.days-count');
            const leaveType = document.getElementById('leave_type');
            const halfDaySection = document.getElementById('halfDaySection');
            const endDateSection = document.getElementById('endDateSection');
            const halfDayType = document.getElementById('half_day_type');

            function toggleHalfDayControls() {
                const selectedOption = leaveType.options[leaveType.selectedIndex];
                const isHalfDay = selectedOption.getAttribute('data-is-half-day') === '1';
                
                halfDaySection.style.display = isHalfDay ? 'block' : 'none';
                endDateSection.style.display = isHalfDay ? 'none' : 'block';
                endDate.required = !isHalfDay;
                
                if (isHalfDay) {
                    endDate.value = startDate.value;
                }
                calculateDays();
            }

            function calculateDays() {
                if (startDate.value && leaveType.value) {
                    const selectedOption = leaveType.options[leaveType.selectedIndex];
                    const isHalfDay = selectedOption.getAttribute('data-is-half-day') === '1';
                    
                    let diffDays;
                    if (isHalfDay) {
                        diffDays = 0.5;
                    } else {
                        const start = new Date(startDate.value);
                        const end = new Date(endDate.value);
                        const diffTime = Math.abs(end - start);
                        diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    }
                    
                    const maxDays = parseInt(selectedOption.getAttribute('data-max-days'));
                    
                    daysDisplay.style.display = 'inline-flex';

                    if (maxDays && diffDays > maxDays) {
                        daysDisplay.className = 'days-display error';
                        daysDisplay.innerHTML = `<i class="fas fa-exclamation-circle"></i> 
                            You are requesting ${diffDays} days, which exceeds the maximum allowed (${maxDays} days)`;
                    } else {
                        daysDisplay.className = 'days-display';
                        daysDisplay.innerHTML = `You are requesting <span class="days-count">${diffDays}</span> day(s)`;
                    }
                } else {
                    daysDisplay.style.display = 'none';
                }
            }

            // Add event listeners
            startDate.addEventListener('change', calculateDays);
            endDate.addEventListener('change', calculateDays);
            leaveType.addEventListener('change', function() {
                toggleHalfDayControls();
                calculateDays();
            });
            halfDayType.addEventListener('change', calculateDays);

            // Initial setup
            toggleHalfDayControls();
        });

        document.getElementById('leave_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const description = selectedOption.dataset.description;
            const descriptionElement = document.querySelector('.leave-description');
            
            if (description) {
                descriptionElement.textContent = description;
                descriptionElement.style.display = 'block';
            } else {
                descriptionElement.style.display = 'none';
            }
        });

        const modal = document.getElementById('restrictionModal');

        function showRestrictions() {
            modal.classList.add('show');
        }

        function hideRestrictions() {
            modal.classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === modal) {
                hideRestrictions();
            }
        }

        // Close modal when ESC key is pressed
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideRestrictions();
            }
        });

        function applyFilters() {
            const year = document.querySelector('select[name="year"]').value;
            const status = document.querySelector('select[name="status"]').value;
            const type = document.querySelector('select[name="type"]').value;
            
            window.location.href = `leave.php?year=${year}&status=${status}&type=${type}`;
        }
    </script>
</body>
</html> 