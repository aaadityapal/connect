<?php
require_once 'config/db_connect.php';
require_once 'manage_leave_balance.php';
session_start();

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// First, let's fix the table if needed
$alter_query = "ALTER TABLE leave_request MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY";
try {
    $conn->query($alter_query);
} catch(Exception $e) {
    // Table might already be fixed
}

// Fetch all users and their leave data
$query = "SELECT 
    u.id as user_id,
    u.username,
    lr.id as leave_id,
    lr.start_date,
    lr.end_date,
    lr.leave_type,
    lr.status,
    lr.duration,
    lr.reason,
    lr.hr_approval,
    lr.manager_approval,
    lr.action_comments,
    lr.manager_action_reason,
    lr.hr_action_reason,
    lt.name as leave_type_name,
    lr.time_from,
    lr.time_to
FROM users u
INNER JOIN leave_request lr ON u.id = lr.user_id
    AND (
        (lr.start_date BETWEEN '$month_start' AND '$month_end')
        OR (lr.end_date BETWEEN '$month_start' AND '$month_end')
        OR (lr.start_date <= '$month_end' AND lr.end_date >= '$month_start')
    )
LEFT JOIN leave_types lt ON lr.leave_type = lt.id
WHERE u.deleted_at IS NULL 
AND u.status = 'active'
AND lr.status IS NOT NULL
ORDER BY lr.start_date DESC";

$result = $conn->query($query);
if (!$result) {
    echo "Query Error: " . $conn->error;
    exit;
}
$leaves = $result->fetch_all(MYSQLI_ASSOC);

// For existing records with id = 0, let's update them with unique IDs
$update_ids_query = "
    SET @row_number = 0;
    UPDATE leave_request 
    SET id = (@row_number:=@row_number + 1)
    WHERE id = 0;
";

try {
    $conn->multi_query($update_ids_query);
    while ($conn->next_result()) {;} // clear multi_query results
} catch(Exception $e) {
    // Handle error if needed
}

// Fetch all active users for the dropdown
$users_query = "SELECT 
    u.id,
    u.username 
FROM users u 
WHERE u.deleted_at IS NULL 
AND u.status = 'active' 
ORDER BY u.username";

$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Fetch leave types for dropdown
$leave_types_query = "SELECT id, name FROM leave_types WHERE status = 'active'";
$leave_types_result = $conn->query($leave_types_query);
$leave_types = $leave_types_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Leave | HR Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #059669;
            --danger-color: #dc2626;
            --background-color: #f1f5f9;
            --border-color: #e2e8f0;
            --text-color: #1e293b;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .leave-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .leave-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .leave-table th,
        .leave-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .leave-table th {
            background: #f8fafc;
            font-weight: 600;
            text-align: left;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .leave-table tbody tr:hover {
            background: #f8fafc;
        }

        .leave-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 0.4rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .status-pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-approved {
            background: #f0fdf4;
            color: #166534;
        }

        .status-rejected {
            background: #fef2f2;
            color: #dc2626;
        }

        .header-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .header-controls .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .header-controls .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .header-controls .btn-primary i {
            font-size: 1rem;
        }

        .month-picker {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--text-color);
            background-color: white;
        }

        .reason-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            padding: 0.5rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background: #f0f9ff;
            color: #0369a1;
        }

        .btn-delete {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-approve {
            background: #f0fdf4;
            color: #166534;
        }

        .btn-icon:hover {
            transform: translateY(-1px);
        }

        /* Add subtle animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .leave-form, .leave-table {
            animation: fadeIn 0.3s ease-out;
        }

        /* Leave Balance Panel Styles */
        .leave-bank-panel {
            position: fixed;
            top: 80px;
            right: 20px;
            width: 360px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow: hidden;
        }

        .leave-bank-header {
            background: #2563eb;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .leave-bank-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            line-height: 1;
            border-radius: 6px;
            transition: all 0.2s;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .leave-balance-grid {
            padding: 1rem;
            max-height: 700px;
            overflow-y: auto;
        }

        /* Different colors for different leave types */
        .leave-balance-card {
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .leave-balance-card.sick-leave {
            background: linear-gradient(145deg, #fee2e2, #fecaca);
            border: 1px solid #fca5a5;
        }

        .leave-balance-card.casual-leave {
            background: linear-gradient(145deg, #dbeafe, #bfdbfe);
            border: 1px solid #93c5fd;
        }

        .leave-balance-card.emergency-leave {
            background: linear-gradient(145deg, #fef3c7, #fde68a);
            border: 1px solid #fcd34d;
        }

        .leave-balance-card.maternity-leave {
            background: linear-gradient(145deg, #d1fae5, #a7f3d0);
            border: 1px solid #6ee7b7;
        }

        .leave-balance-card.paternity-leave {
            background: linear-gradient(145deg, #e0e7ff, #c7d2fe);
            border: 1px solid #a5b4fc;
        }

        .leave-balance-card.short-leave {
            background: linear-gradient(145deg, #ede9fe, #ddd6fe);
            border: 1px solid #c4b5fd;
        }

        .leave-balance-card.compensate-leave {
            background: linear-gradient(145deg, #fae8ff, #f5d0fe);
            border: 1px solid #e9d5ff;
        }

        .leave-balance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .leave-type-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.75rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .leave-type-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .leave-balance-details {
            font-size: 0.9rem;
            color: #334155;
        }

        .balance-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            align-items: center;
        }

        .balance-row.total {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px dashed rgba(0, 0, 0, 0.1);
            font-weight: 600;
            color: #1e293b;
        }

        .leave-progress {
            margin-top: 1rem;
            background: rgba(255, 255, 255, 0.5);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .leave-progress-bar {
            height: 100%;
            background: rgba(0, 0, 0, 0.2);
            transition: width 0.3s ease;
        }

        /* Time field styles */
        .time-fields {
            animation: fadeIn 0.3s ease-out;
        }

        .time-fields input[type="time"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: #fff;
        }

        .time-fields input[type="time"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Time field validation styles */
        .time-fields input[type="time"]:invalid {
            border-color: var(--danger-color);
        }

        .time-fields input[type="time"]:invalid:focus {
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        /* Shift and Time fields styles */
        .shift-fields,
        .time-fields {
            animation: fadeIn 0.3s ease-out;
        }

        .shift-fields select,
        .time-fields input[type="time"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: #fff;
        }

        .shift-fields select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Style for readonly time inputs */
        .time-fields input[type="time"][readonly] {
            background-color: #f8fafc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-calendar-alt"></i>
                Manage Leave Requests
            </h1>
            <div class="header-controls">
                <button class="btn btn-primary" onclick="window.location.href='manage_leave_balance_view.php'">
                    <i class="fas fa-cogs"></i>
                    Manage Leave Balance
                </button>
                <div class="form-group">
                    <label for="leaveBalanceUser">
                        <i class="fas fa-calculator"></i>
                        Select Employee for Leave Balance
                    </label>
                    <select name="leaveBalanceUser" id="leaveBalanceUser" class="form-control">
                        <option value="">Select Employee for Leave Balance</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="month" class="month-picker" value="<?php echo $selected_month; ?>">
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div id="leaveBalancePanel" class="leave-bank-panel" style="display: none;">
            <div class="leave-bank-header">
                <h3>
                    <i class="fas fa-calendar-check"></i>
                    Leave Balance
                </h3>
                <button class="btn-close" onclick="toggleLeaveBalance()">×</button>
            </div>
            <div id="leaveBalanceContent" class="leave-balance-grid">
                <!-- Leave balance cards will be dynamically added here -->
            </div>
        </div>

        <div class="leave-form">
            <form id="addLeaveForm" method="POST" action="handle_leave_operations.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="employee">
                            <i class="fas fa-user"></i>
                            Employee
                        </label>
                        <select name="employee" id="employee" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="leave_type">
                            <i class="fas fa-tag"></i>
                            Leave Type
                        </label>
                        <select name="leave_type" class="form-control" required>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_date">
                            <i class="fas fa-calendar"></i>
                            Start Date
                        </label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">
                            <i class="fas fa-calendar"></i>
                            End Date
                        </label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="duration">
                            <i class="fas fa-clock"></i>
                            Duration (Days)
                        </label>
                        <input type="number" name="duration" class="form-control" step="0.5" readonly>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="reason">
                            <i class="fas fa-comment"></i>
                            Reason
                        </label>
                        <textarea name="reason" class="form-control reason-textarea" required></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Leave Request
                </button>
            </form>
        </div>

        <table class="leave-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Duration</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Manager Approval</th>
                    <th>HR Approval</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $leave): ?>
                    <?php if ($leave['leave_id']): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($leave['username']); ?></td>
                            <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                            <td>
                                <?php 
                                echo number_format($leave['duration'], 1) . ' days';
                                if ($leave['time_from'] && $leave['time_to']) {
                                    echo '<br><small class="text-muted">(' . 
                                         date('h:i A', strtotime($leave['time_from'])) . ' - ' . 
                                         date('h:i A', strtotime($leave['time_to'])) . 
                                         ')</small>';
                                }
                                ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($leave['start_date'])); ?></td>
                            <td><?php echo date('d M Y', strtotime($leave['end_date'])); ?></td>
                            <td>
                                <span class="reason-text" title="<?php echo htmlspecialchars($leave['reason'] ?? ''); ?>">
                                    <?php echo $leave['reason'] ? substr(htmlspecialchars($leave['reason']), 0, 30) . (strlen($leave['reason']) > 30 ? '...' : '') : ''; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($leave['status'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($leave['status'] ?? 'pending'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($leave['manager_approval'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($leave['manager_approval'] ?? 'pending'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($leave['hr_approval'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($leave['hr_approval'] ?? 'pending'); ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button class="btn-edit" onclick="editLeave(<?php echo $leave['leave_id']; ?>)" 
                                        title="Edit Leave">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-delete" onclick="deleteLeave(<?php echo $leave['leave_id']; ?>)"
                                        title="Delete Leave">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php if ($leave['status'] !== 'approved'): ?>
                                    <button class="btn-approve" onclick="approveLeave(<?php echo $leave['leave_id']; ?>)"
                                            title="Approve Leave">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const leaveTypeSelect = document.querySelector('select[name="leave_type"]');
            const formGrid = document.querySelector('.form-grid');
            
            // Remove any existing time fields first to avoid duplicates
            document.querySelectorAll('.time-fields, .shift-fields').forEach(field => field.remove());
            
            // Create and insert shift and time fields
            const timeFieldsHTML = `
                <div class="form-group shift-fields" id="shiftGroup" style="display: none;">
                    <label for="shift_time">
                        <i class="fas fa-sun"></i>
                        Shift
                    </label>
                    <select name="shift_time" id="shiftTime" class="form-control">
                        <option value="">Select Shift</option>
                        <option value="morning">Morning (9:00 - 10:30)</option>
                        <option value="evening">Evening (16:30 - 18:00)</option>
                    </select>
                </div>
                <div class="form-group time-fields" id="timeFromGroup" style="display: none;">
                    <label for="time_from">
                        <i class="fas fa-clock"></i>
                        From Time
                    </label>
                    <input type="time" name="time_from" id="timeFrom" class="form-control" step="1800" readonly>
                </div>
                <div class="form-group time-fields" id="timeToGroup" style="display: none;">
                    <label for="time_to">
                        <i class="fas fa-clock"></i>
                        To Time
                    </label>
                    <input type="time" name="time_to" id="timeTo" class="form-control" step="1800" readonly>
                </div>
            `;
            
            // Insert after duration field
            const durationField = document.querySelector('input[name="duration"]').closest('.form-group');
            durationField.insertAdjacentHTML('afterend', timeFieldsHTML);
            
            // Function to toggle time fields
            function toggleTimeFields() {
                const selectedText = leaveTypeSelect.options[leaveTypeSelect.selectedIndex].text;
                const isShortLeave = selectedText.toLowerCase().trim() === 'short leave';
                
                document.querySelectorAll('.time-fields, .shift-fields').forEach(field => {
                    field.style.display = isShortLeave ? 'block' : 'none';
                    const input = field.querySelector('input, select');
                    if (input) {
                        input.required = isShortLeave;
                    }
                });
            }
            
            // Function to set time based on shift selection
            function handleShiftChange() {
                const shiftSelect = document.getElementById('shiftTime');
                const timeFrom = document.getElementById('timeFrom');
                const timeTo = document.getElementById('timeTo');
                const durationInput = document.querySelector('input[name="duration"]');
                
                switch(shiftSelect.value) {
                    case 'morning':
                        timeFrom.value = '09:00';
                        timeTo.value = '10:30';
                        durationInput.value = '0.19'; // 1.5 hours / 8 hours
                        break;
                    case 'evening':
                        timeFrom.value = '16:30';
                        timeTo.value = '18:00';
                        durationInput.value = '0.19'; // 1.5 hours / 8 hours
                        break;
                    default:
                        timeFrom.value = '';
                        timeTo.value = '';
                        durationInput.value = '';
                }
            }
            
            // Add event listeners
            leaveTypeSelect.addEventListener('change', toggleTimeFields);
            document.getElementById('shiftTime').addEventListener('change', handleShiftChange);
            
            // Call once on page load
            toggleTimeFields();
        });

        function editLeave(leaveId) {
            // Implement edit functionality
            window.location.href = `edit_leave_detail.php?id=${leaveId}`;
        }

        function deleteLeave(leaveId) {
            if (confirm('Are you sure you want to delete this leave record?')) {
                // Implement delete functionality
                window.location.href = `process_leave.php?action=delete&id=${leaveId}`;
            }
        }

        function toggleLeaveBalance() {
            const panel = document.getElementById('leaveBalancePanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function updateLeaveBalance(data) {
            // Add debug logging
            console.log('Received leave balance data:', data);
            
            const content = document.getElementById('leaveBalanceContent');
            content.innerHTML = data.map(balance => {
                // Add debug logging for each balance
                console.log('Processing balance:', balance.name, {
                    used_days: balance.used_days,
                    max_days: balance.max_days
                });
                
                const usedDays = parseFloat(balance.used_days) || 0;
                const maxDays = parseFloat(balance.max_days) || 0;
                const remainingDays = Math.max(0, maxDays - usedDays);
                const usagePercentage = maxDays > 0 ? Math.min(100, (usedDays / maxDays) * 100) : 0;
                
                // Get icon based on leave type
                const icon = getLeaveTypeIcon(balance.name);
                const cardClass = getLeaveTypeClass(balance.name);
                
                // Special handling for Compensate Leave
                const isCompensateLeave = balance.name === 'Compensate Leave';
                
                return `
                    <div class="leave-balance-card ${cardClass}">
                        <div class="leave-type-name">
                            <div class="leave-type-icon">
                                <i class="${icon}"></i>
                            </div>
                            ${balance.name}
                        </div>
                        <div class="leave-balance-details">
                            <div class="balance-row">
                                <span><i class="fas fa-check-circle"></i> Total Available:</span>
                                <span>${maxDays} ${isCompensateLeave ? 'days earned' : 'days'}</span>
                            </div>
                            <div class="balance-row">
                                <span><i class="fas fa-clock"></i> Used:</span>
                                <span>${usedDays} ${balance.name === 'Short Leave' ? 'leave(s)' : 'days'}</span>
                            </div>
                            <div class="balance-row total">
                                <span><i class="fas fa-calendar-check"></i> Remaining:</span>
                                <span>${remainingDays} ${balance.name === 'Short Leave' ? 'leave(s)' : 'days'}</span>
                            </div>
                            <div class="leave-progress">
                                <div class="leave-progress-bar" style="width: ${usagePercentage}%"></div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function getLeaveTypeIcon(leaveType) {
            const icons = {
                'Sick Leave': 'fas fa-hospital',
                'Casual Leave': 'fas fa-umbrella-beach',
                'Emergency Leave': 'fas fa-exclamation-circle',
                'Maternity Leave': 'fas fa-baby',
                'Paternity Leave': 'fas fa-baby-carriage',
                'Short Leave': 'fas fa-hourglass-half',
                'Compensate Leave': 'fas fa-sync-alt'
            };
            return icons[leaveType] || 'fas fa-calendar';
        }

        function getLeaveTypeClass(leaveType) {
            return leaveType.toLowerCase().replace(' ', '-');
        }

        // Update the event listener with error handling
        document.getElementById('leaveBalanceUser').addEventListener('change', function() {
            const userId = this.value;
            if (userId) {
                fetch(`get_leave_balance.php?user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log('API Response:', data); // Debug log
                        updateLeaveBalance(data);
                        document.getElementById('leaveBalancePanel').style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error fetching leave balance:', error);
                    });
            } else {
                document.getElementById('leaveBalancePanel').style.display = 'none';
            }
        });

        function calculateDuration() {
            const startDate = new Date(document.querySelector('input[name="start_date"]').value);
            const endDate = new Date(document.querySelector('input[name="end_date"]').value);
            const leaveTypeSelect = document.querySelector('select[name="leave_type"]');
            const selectedText = leaveTypeSelect.options[leaveTypeSelect.selectedIndex].text;
            const isShortLeave = selectedText.toLowerCase().trim() === 'short leave';
            
            if (startDate && endDate && !isNaN(startDate) && !isNaN(endDate)) {
                if (isShortLeave) {
                    // For short leave, always count as 1
                    document.querySelector('input[name="duration"]').value = 1;
                } else {
                    // Calculate the difference in days for other leave types
                    const diffTime = Math.abs(endDate - startDate);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    document.querySelector('input[name="duration"]').value = diffDays;
                }
            }
        }

        // Add event listeners to start and end date inputs
        document.querySelector('input[name="start_date"]').addEventListener('change', calculateDuration);
        document.querySelector('input[name="end_date"]').addEventListener('change', calculateDuration);

        // Add this to your existing JavaScript
        document.querySelector('form').addEventListener('submit', function(e) {
            // For debugging purposes
            console.log('Form submitted');
            console.log('Time From:', document.getElementById('timeFrom').value);
            console.log('Time To:', document.getElementById('timeTo').value);
            console.log('Shift:', document.getElementById('shiftTime').value);
        });
    </script>
</body>
</html> 