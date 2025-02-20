<?php
session_start();

// Add authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db_connect.php';

// Get all active users for dropdown
$users_query = "SELECT id, unique_id, username FROM users WHERE deleted_at IS NULL ORDER BY username";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get selected user and month
$unique_id = isset($_GET['id']) ? $_GET['id'] : ($users[0]['unique_id'] ?? '');
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Get the current date
$current_date_obj = new DateTime();
$current_date_str = $current_date_obj->format('Y-m-d');

// Set end date as either month end or current date, whichever is earlier
$month_end_obj = new DateTime($month_end);
$end_date = ($month_end_obj > $current_date_obj) ? $current_date_obj : $month_end_obj;

// Add this new function for rounding overtime
function roundOvertime($overtime) {
    if (empty($overtime) || $overtime === '00:00:00') {
        return '00:00:00';
    }

    // Convert overtime string to minutes
    list($hours, $minutes) = explode(':', $overtime);
    $total_minutes = ($hours * 60) + $minutes;

    // Get the hour part
    $base_hours = floor($total_minutes / 60);
    $remaining_minutes = $total_minutes % 60;

    // Round to nearest 30 minutes
    if ($remaining_minutes < 30) {
        $rounded_minutes = 0;
    } else {
        $rounded_minutes = 30;
    }

    // Format back to HH:MM:00
    return sprintf('%02d:%02d:00', $base_hours, $rounded_minutes);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        die("Please login to continue.");
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get the user's ID from unique_id
        $user_stmt = $conn->prepare("SELECT id FROM users WHERE unique_id = ? AND deleted_at IS NULL");
        $user_stmt->bind_param('s', $unique_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $user_id = $user['id'];

        // First check if record exists
        $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        
        foreach ($_POST['attendance'] as $date => $data) {
            if (!isset($data['modified']) || $data['modified'] !== 'true') {
                continue;
            }

            $status = $data['status'];
            $punch_in = !empty($data['punch_in']) ? date('Y-m-d H:i:s', strtotime("$date {$data['punch_in']}")) : null;
            $punch_out = !empty($data['punch_out']) ? date('Y-m-d H:i:s', strtotime("$date {$data['punch_out']}")) : null;
            $shift_time = !empty($data['shift_time']) ? $data['shift_time'] : null;
            
            // Calculate overtime based on punch out time
            $overtime = '00:00:00';
            if ($status === 'present' && !empty($punch_out)) {
                $punch_out_parts = explode(':', date('H:i', strtotime($punch_out)));
                $punch_out_minutes = ($punch_out_parts[0] * 60) + $punch_out_parts[1];
                $shift_end_minutes = 18 * 60; // 18:00 = 1080 minutes
                
                if ($punch_out_minutes > $shift_end_minutes) {
                    $overtime_minutes = $punch_out_minutes - $shift_end_minutes;
                    $overtime_hours = floor($overtime_minutes / 60);
                    $overtime_mins = $overtime_minutes % 60;
                    $overtime = sprintf('%02d:%02d:00', $overtime_hours, $overtime_mins);
                    
                    // Round the overtime
                    $overtime = roundOvertime($overtime);
                }
            }

            // Add debug logging
            error_log("Date: $date, Punch Out: $punch_out, Raw Overtime: " . sprintf('%02d:%02d:00', $overtime_hours, $overtime_mins) . ", Rounded Overtime: $overtime");
            
            $is_weekly_off = isset($data['is_weekly_off']) ? 1 : 0;
            
            // Calculate working hours if both punch in and punch out exist
            $working_hours = null;
            if ($punch_in && $punch_out && $status === 'present') {
                $punch_in_obj = new DateTime($punch_in);
                $punch_out_obj = new DateTime($punch_out);
                $interval = $punch_in_obj->diff($punch_out_obj);
                $working_hours = sprintf('%02d:%02d:00', 
                    $interval->h + ($interval->days * 24), 
                    $interval->i
                );
            }

            // Add weekly off check - get the day of week
            $date_obj = new DateTime($date);
            $day_of_week = $date_obj->format('l'); // Gets day name (Monday, Tuesday, etc.)
            
            // Check if record exists
            $check_stmt->bind_param('is', $user_id, $date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                // Update existing record
                $update_stmt = $conn->prepare("UPDATE attendance 
                                             SET status = ?,
                                                 punch_in = ?,
                                                 punch_out = ?,
                                                 shift_time = ?,
                                                 overtime_hours = ?,
                                                 working_hours = ?,
                                                 is_weekly_off = ?
                                             WHERE user_id = ? AND date = ?");
                
                $update_stmt->bind_param('ssssssiis', 
                    $status,
                    $punch_in,
                    $punch_out,
                    $shift_time,
                    $overtime,
                    $working_hours,
                    $is_weekly_off,
                    $user_id,
                    $date
                );
                $update_stmt->execute();
                
                if ($update_stmt->error) {
                    throw new Exception("Error updating record for date $date: " . $update_stmt->error);
                }
            } else {
                // Insert new record
                $insert_stmt = $conn->prepare("INSERT INTO attendance 
                                             (user_id, date, status, punch_in, punch_out, 
                                              shift_time, overtime_hours, working_hours, is_weekly_off) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $insert_stmt->bind_param('isssssssi',
                    $user_id,
                    $date,
                    $status,
                    $punch_in,
                    $punch_out,
                    $shift_time,
                    $overtime,
                    $working_hours,
                    $is_weekly_off
                );
                $insert_stmt->execute();
                
                if ($insert_stmt->error) {
                    throw new Exception("Error inserting record for date $date: " . $insert_stmt->error);
                }
            }
        }

        // Commit transaction
        $conn->commit();
        
        // Redirect with success message
        header("Location: salary_overview.php?month=" . $selected_month . "&success=1");
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Error updating attendance: " . $e->getMessage();
    }
}

// Fetch employee details
$stmt = $conn->prepare("
    SELECT 
        u.*,
        s.shift_name,
        s.start_time as shift_start_time,
        s.end_time as shift_end_time,
        us.weekly_offs,
        us.effective_from,
        us.effective_to
    FROM users u
    LEFT JOIN user_shifts us ON (
        u.id = us.user_id 
        AND CURRENT_DATE >= us.effective_from 
        AND (us.effective_to IS NULL OR CURRENT_DATE <= us.effective_to)
    )
    LEFT JOIN shifts s ON COALESCE(us.shift_id, u.shift_id) = s.id
    WHERE u.unique_id = ? AND u.deleted_at IS NULL
");
$stmt->bind_param('s', $unique_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Add this error handling after fetching employee
if (!$employee) {
    die("Employee not found. Please check the employee ID and try again.");
}

// Fetch attendance records for the month
$query = "SELECT a.*, a.overtime_hours FROM attendance a 
          WHERE a.user_id = ? AND a.date BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('iss', $employee['id'], $month_start, $month_end);
$stmt->execute();
$result = $stmt->get_result();
$attendance_records = [];
while ($row = $result->fetch_assoc()) {
    $attendance_records[date('Y-m-d', strtotime($row['date']))] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .attendance-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            margin: 20px auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            border: 1px solid var(--border-color);
        }

        .attendance-table th {
            background-color: #f8fafc;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .status-select {
            background-color: white;
        }

        .weekend {
            background-color: #fff5f5;
        }

        .today {
            background-color: #f0f9ff;
        }

        .filters-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: 600;
            color: #333;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            min-width: 200px;
        }

        .apply-filters {
            height: 38px;
            padding: 0 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .apply-filters:hover {
            background-color: #0056b3;
        }

        /* Add styles for the overtime input */
        .overtime {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="salary_overview.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Overview
        </a>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="attendance-form">
            <!-- Add filters at the top -->
            <form id="filters-form" method="GET" class="filters-container">
                <div class="filter-group">
                    <label for="employee">Select Employee:</label>
                    <select name="id" id="employee" required>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['unique_id']); ?>"
                                <?php echo $user['unique_id'] === $unique_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="month">Select Month:</label>
                    <input type="month" 
                           id="month" 
                           name="month" 
                           value="<?php echo htmlspecialchars($selected_month); ?>" 
                           required>
                </div>

                <button type="submit" class="apply-filters">Apply Filters</button>
            </form>

            <h2 class="section-title">
                Edit Attendance - <?php echo htmlspecialchars($employee['username'] ?? 'Unknown Employee'); ?> 
                (<?php echo date('F Y', strtotime($month_start)); ?>)
            </h2>

            <form method="POST">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Shift Time</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Overtime (hrs)</th>
                            <th>Weekly Off</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_date = new DateTime($month_start);
                        
                        while ($current_date <= $end_date) {
                            $date_str = $current_date->format('Y-m-d');
                            $is_weekend = in_array($current_date->format('N'), [6, 7]); // Saturday or Sunday
                            $is_today = $date_str === date('Y-m-d');
                            $record = $attendance_records[$date_str] ?? null;
                            
                            $row_class = $is_weekend ? 'weekend' : '';
                            $row_class .= $is_today ? ' today' : '';
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <?php echo $current_date->format('d M (D)'); ?>
                                    <input type="checkbox" 
                                           name="attendance[<?php echo $date_str; ?>][is_weekly_off]" 
                                           value="1" 
                                           <?php echo (isset($record['is_weekly_off']) && $record['is_weekly_off'] == 1) ? 'checked' : ''; ?>
                                           onchange="markAsModified(this)">
                                </td>
                                <td>
                                    <input type="hidden" name="attendance[<?php echo $date_str; ?>][modified]" class="modified-flag" value="false">
                                    <select name="attendance[<?php echo $date_str; ?>][status]" class="form-control status-select" onchange="markAsModified(this)">
                                        <option value="present" <?php echo ($record && $record['status'] === 'present') ? 'selected' : ''; ?>>Present</option>
                                        <option value="absent" <?php echo ($record && $record['status'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                                        <option value="leave" <?php echo ($record && $record['status'] === 'leave') ? 'selected' : ''; ?>>Leave</option>
                                        <option value="holiday" <?php echo ($record && $record['status'] === 'holiday') ? 'selected' : ''; ?>>Holiday</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="time" class="form-control" 
                                           name="attendance[<?php echo $date_str; ?>][shift_time]"
                                           value="<?php echo $record ? $record['shift_time'] : ($employee['shift_start_time'] ?? ''); ?>"
                                           data-shift-start="<?php echo $employee['shift_start_time'] ?? ''; ?>"
                                           data-shift-end="<?php echo $employee['shift_end_time'] ?? ''; ?>"
                                           onchange="markAsModified(this)">
                                </td>
                                <td>
                                    <input type="time" class="form-control punch-in" 
                                           name="attendance[<?php echo $date_str; ?>][punch_in]"
                                           value="<?php 
                                                if ($record && $record['punch_in']) {
                                                    $punch_in_time = new DateTime($record['punch_in']);
                                                    echo $punch_in_time->format('H:i');
                                                }
                                           ?>"
                                           onchange="markAsModified(this)">
                                </td>
                                <td>
                                    <input type="time" class="form-control punch-out" 
                                           name="attendance[<?php echo $date_str; ?>][punch_out]"
                                           value="<?php 
                                                if ($record && $record['punch_out']) {
                                                    // Convert the datetime format to time only
                                                    $punch_out_time = new DateTime($record['punch_out']);
                                                    echo $punch_out_time->format('H:i');
                                                }
                                           ?>"
                                           <?php echo ($record && $record['status'] !== 'present') ? 'disabled' : ''; ?>
                                           onchange="markAsModified(this)">
                                </td>
                                <td>
                                    <input type="time" class="form-control overtime" 
                                           name="attendance[<?php echo $date_str; ?>][overtime]"
                                           value="<?php 
                                                if (isset($attendance_records[$date_str]['overtime_hours']) && 
                                                    $attendance_records[$date_str]['overtime_hours'] !== '00:00:00' && 
                                                    $attendance_records[$date_str]['overtime_hours'] !== null) {
                                                    echo $attendance_records[$date_str]['overtime_hours'];
                                                } else {
                                                    echo '00:00:00';
                                                }
                                           ?>"
                                           step="1"
                                           <?php echo ($record && $record['status'] !== 'present') ? 'disabled' : ''; ?>
                                           onchange="markAsModified(this)">
                                </td>
                            </tr>
                        <?php
                            $current_date->modify('+1 day');
                        }
                        ?>
                    </tbody>
                </table>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to mark rows as modified
        function markAsModified(element) {
            const row = element.closest('tr');
            const modifiedFlag = row.querySelector('.modified-flag');
            if (modifiedFlag) {
                modifiedFlag.value = 'true';
            }
        }

        // Handle status change
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const row = this.closest('tr');
                const inputs = row.querySelectorAll('input[type="time"]');
                const overtimeInput = row.querySelector('.overtime');
                
                if (this.value === 'absent' || this.value === 'holiday' || this.value === 'leave') {
                    inputs.forEach(input => {
                        input.value = '';
                        input.disabled = true;
                        input.required = false;
                    });
                    if (overtimeInput) {
                        overtimeInput.value = '00:00:00';
                    }
                } else if (this.value === 'present') {
                    inputs.forEach(input => {
                        input.disabled = false;
                        input.required = false;
                    });
                    
                    const shiftTimeInput = row.querySelector('input[name$="[shift_time]"]');
                    if (shiftTimeInput && !shiftTimeInput.value && shiftTimeInput.dataset.shiftStart) {
                        shiftTimeInput.value = shiftTimeInput.dataset.shiftStart;
                    }
                    
                    calculateOvertime(row);
                }
                markAsModified(this);
            });
        });

        function roundOvertime(hours, minutes) {
            // Convert to total minutes
            const totalMinutes = (hours * 60) + minutes;
            
            // Get base hours and remaining minutes
            const baseHours = Math.floor(totalMinutes / 60);
            const remainingMinutes = totalMinutes % 60;
            
            // Round to nearest 30 minutes
            const roundedMinutes = remainingMinutes < 30 ? 0 : 30;
            
            return {
                hours: baseHours,
                minutes: roundedMinutes
            };
        }

        function calculateOvertime(row) {
            const punchOut = row.querySelector('.punch-out').value;
            const shiftTime = row.querySelector('input[name$="[shift_time]"]');
            const overtimeInput = row.querySelector('.overtime');
            const status = row.querySelector('.status-select').value;

            if (status === 'present' && punchOut && shiftTime && shiftTime.dataset.shiftEnd) {
                // Convert punch out time to minutes since midnight
                const [punchOutHours, punchOutMinutes] = punchOut.split(':').map(Number);
                const punchOutInMinutes = (punchOutHours * 60) + punchOutMinutes;
                
                // Standard shift end (18:00) in minutes
                const shiftEndInMinutes = 18 * 60; // 18:00 = 1080 minutes
                
                if (punchOutInMinutes > shiftEndInMinutes) {
                    // Calculate overtime in minutes
                    const overtimeMinutes = punchOutInMinutes - shiftEndInMinutes;
                    
                    // Convert to hours and minutes
                    const rawHours = Math.floor(overtimeMinutes / 60);
                    const rawMinutes = overtimeMinutes % 60;
                    
                    // Round the overtime
                    const rounded = roundOvertime(rawHours, rawMinutes);
                    
                    // Format as HH:MM:00
                    overtimeInput.value = 
                        String(rounded.hours).padStart(2, '0') + ':' +
                        String(rounded.minutes).padStart(2, '0') + ':00';
                    
                    console.log('Raw overtime:', `${rawHours}:${rawMinutes}:00`);
                    console.log('Rounded overtime:', overtimeInput.value);
                } else {
                    overtimeInput.value = '00:00:00';
                }
            }
        }

        // Add event listeners for overtime changes
        document.querySelectorAll('.overtime').forEach(input => {
            input.addEventListener('change', function() {
                markAsModified(this);
            });
        });

        // Add event listeners for punch out changes
        document.querySelectorAll('.punch-out').forEach(input => {
            input.addEventListener('change', function() {
                calculateOvertime(this.closest('tr'));
                markAsModified(this);
            });
        });

        // Add event listeners for punch in changes
        document.querySelectorAll('.punch-in').forEach(input => {
            input.addEventListener('change', function() {
                markAsModified(this);
            });
        });

        // Add event listeners for shift time changes
        document.querySelectorAll('input[name$="[shift_time]"]').forEach(input => {
            input.addEventListener('change', function() {
                markAsModified(this);
            });
        });

        // Initialize all rows
        document.querySelectorAll('.status-select').forEach(select => {
            select.dispatchEvent(new Event('change'));
        });
    });
    </script>
</body>
</html> 