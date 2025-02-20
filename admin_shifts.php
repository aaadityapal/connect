<?php
session_start();
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'bulk_assign':
                $employees = $_POST['employees'] ?? [];
                $shift_id = $_POST['shift_id'];
                
                if (!empty($employees)) {
                    $stmt = $pdo->prepare("UPDATE users SET shift_id = ? WHERE id IN (" . implode(',', array_fill(0, count($employees), '?')) . ")");
                    $stmt->execute(array_merge([$shift_id], $employees));
                }
                break;
        }
    }
    header('Location: admin_shifts.php');
    exit;
}

// Add this at the top of your PHP section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_shift') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO shifts (name, start_time, end_time, overtime_start, late_buffer)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $_POST['name'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['overtime_start'],
                $_POST['late_buffer']
            ]);

            if ($result) {
                // Redirect to prevent form resubmission
                header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error creating shift: " . $e->getMessage();
        }
    }
}

// Fetch all shifts
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments (safely handle if department column exists)
try {
    $departments = $pdo->query("
        SELECT DISTINCT department 
        FROM users 
        WHERE department IS NOT NULL 
        AND department != ''
        ORDER BY department
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $departments = []; // If department column doesn't exist
}

// Fetch all employees with their current shifts
$employees = $pdo->query("
    SELECT 
        u.id,
        u.username,
        u.unique_id,
        u.shift_id,
        " . (empty($departments) ? "'Not Set' as department," : "COALESCE(u.department, 'Not Set') as department,") . "
        s.name as shift_name,
        s.start_time,
        s.end_time
    FROM users u 
    LEFT JOIN shifts s ON u.shift_id = s.id 
    WHERE u.role = 'employee'
    ORDER BY u.username
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Management - Admin Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .shift-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .shift-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .employee-table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-table th,
        .employee-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .employee-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .filter-section {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.9rem;
            color: #666;
        }

        .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 150px;
        }

        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .bulk-actions.visible {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .employee-card {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }

        .shift-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        /* Update the add-shift-card styles */
        .add-shift-card {
            background: #4CAF50; /* Green background */
            color: white !important;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            gap: 12px;
            border: none;
            min-height: 150px; /* Match height with other shift cards */
            font-size: 1.1em;
            font-weight: 500;
            text-align: center;
            box-shadow: 0 2px 10px rgba(76, 175, 80, 0.2);
        }

        .add-shift-card:hover {
            background: #45a049; /* Darker green on hover */
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .add-shift-card i {
            font-size: 24px;
        }

        /* Remove any existing border styles */
        .add-shift-card {
            border: none !important;
        }

        /* If you want to make it even more prominent, add this pulsing animation */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
            100% {
                transform: scale(1);
            }
        }

        .add-shift-card {
            animation: pulse 2s infinite;
        }

        .add-shift-card:hover {
            animation: none;
        }

        /* Add these new styles */
        .no-shifts-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }

        .no-shifts-message {
            text-align: center;
            padding: 40px;
        }

        .no-shifts-message i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .no-shifts-message h3 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 24px;
        }

        .no-shifts-message p {
            color: #666;
            margin: 0 0 30px 0;
        }

        .add-shift-btn {
            background: #ff4444; /* Red background */
            color: #000; /* Black text */
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto; /* Center the button */
            transition: all 0.3s ease;
        }

        .add-shift-btn:hover {
            background: #ff3333; /* Slightly darker red on hover */
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.2);
        }

        .add-shift-btn i {
            font-size: 20px;
            color: #000; /* Black icon */
        }

        /* Update shifts grid styles */
        .shifts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        /* Remove the old add-shift-card styles */
        .add-shift-card {
            display: none;
        }

        /* Add these modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .save-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="container">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="admin_dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="admin_settings.php">Settings</a>
                <i class="fas fa-chevron-right"></i>
                <span>Shift Management</span>
            </div>

            <div class="settings-header">
                <h2><i class="fas fa-clock"></i> Shift Management</h2>
                <button class="btn btn-primary" onclick="showAddShiftModal()">
                    <i class="fas fa-plus"></i> Add New Shift
                </button>
            </div>

            <div class="tabs">
                <div class="tab active" onclick="switchTab('shifts')">
                    <i class="fas fa-clock"></i> Shifts
                </div>
                <div class="tab" onclick="switchTab('assignments')">
                    <i class="fas fa-users"></i> Assignments
                </div>
            </div>

            <!-- Shifts Tab -->
            <div id="shiftsTab" class="tab-content active">
                <div class="shifts-section">
                    <?php if (empty($shifts)): ?>
                        <div class="no-shifts-container">
                            <div class="no-shifts-message">
                                <i class="fas fa-clock"></i>
                                <h3>No Shifts Created Yet</h3>
                                <p>Start by creating your first shift schedule</p>
                                <button type="button" class="add-shift-btn" onclick="openShiftModal()">
                                    <i class="fas fa-plus-circle"></i>
                                    Create New Shift
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="shifts-grid">
                            <?php foreach ($shifts as $shift): ?>
                                <div class="shift-card">
                                    <h3><?php echo htmlspecialchars($shift['name']); ?></h3>
                                    <div class="shift-times">
                                        <p><i class="fas fa-clock"></i> Start: <?php echo date('h:i A', strtotime($shift['start_time'])); ?></p>
                                        <p><i class="fas fa-clock"></i> End: <?php echo date('h:i A', strtotime($shift['end_time'])); ?></p>
                                        <p><i class="fas fa-stopwatch"></i> Overtime: <?php echo date('h:i A', strtotime($shift['overtime_start'])); ?></p>
                                    </div>
                                    <button class="btn btn-secondary" onclick="editShift(<?php echo htmlspecialchars(json_encode($shift)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                            <?php endforeach; ?>
                            
                            <button class="add-shift-btn" onclick="showAddShiftModal()">
                                <i class="fas fa-plus-circle"></i>
                                Add New Shift
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assignments Tab -->
            <div id="assignmentsTab" class="tab-content">
                <div class="filter-section">
                    <?php if (!empty($departments)): ?>
                    <div class="filter-group">
                        <label>Department</label>
                        <select id="departmentFilter" onchange="filterEmployees()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>">
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label>Shift</label>
                        <select id="shiftFilter" onchange="filterEmployees()">
                            <option value="">All Shifts</option>
                            <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo $shift['id']; ?>">
                                <?php echo htmlspecialchars($shift['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bulk-actions" id="bulkActions">
                    <select id="bulkShiftSelect" class="shift-select">
                        <option value="">Select Shift</option>
                        <?php foreach ($shifts as $shift): ?>
                        <option value="<?php echo $shift['id']; ?>">
                            <?php echo htmlspecialchars($shift['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" onclick="applyBulkShift()">
                        Apply to Selected
                    </button>
                </div>

                <form id="bulkAssignForm" method="POST">
                    <input type="hidden" name="action" value="bulk_assign">
                    <input type="hidden" name="shift_id" id="bulkShiftId">
                    
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                </th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Current Shift</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                data-shift="<?php echo $employee['shift_id']; ?>">
                                <td>
                                    <input type="checkbox" name="employees[]" value="<?php echo $employee['id']; ?>"
                                           class="employee-checkbox" onclick="updateBulkActions()">
                                </td>
                                <td>
                                    <div class="employee-card">
                                        <div class="employee-avatar">
                                            <?php echo strtoupper(substr($employee['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div><?php echo htmlspecialchars($employee['username']); ?></div>
                                            <small><?php echo htmlspecialchars($employee['unique_id']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td>
                                    <?php if ($employee['shift_name']): ?>
                                    <span class="shift-badge">
                                        <?php echo htmlspecialchars($employee['shift_name']); ?>
                                        (<?php echo date('h:i A', strtotime($employee['start_time'])); ?> - 
                                         <?php echo date('h:i A', strtotime($employee['end_time'])); ?>)
                                    </span>
                                    <?php else: ?>
                                    <span class="shift-badge" style="background: rgba(158, 158, 158, 0.1); color: #9e9e9e;">
                                        No Shift Assigned
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select class="shift-select" onchange="assignShift(this, <?php echo $employee['id']; ?>)">
                                        <option value="">Select Shift</option>
                                        <?php foreach ($shifts as $shift): ?>
                                        <option value="<?php echo $shift['id']; ?>" 
                                                <?php echo ($employee['shift_id'] == $shift['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($shift['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            event.currentTarget.classList.add('active');
            document.getElementById(tabName + 'Tab').classList.add('active');
        }

        function filterEmployees() {
            const departmentFilter = document.getElementById('departmentFilter');
            const shiftFilter = document.getElementById('shiftFilter');
            
            const department = departmentFilter ? departmentFilter.value : '';
            const shift = shiftFilter.value;
            
            document.querySelectorAll('.employee-table tbody tr').forEach(row => {
                const showDepartment = !department || row.dataset.department === department;
                const showShift = !shift || row.dataset.shift === shift;
                row.style.display = showDepartment && showShift ? '' : 'none';
            });
        }

        function toggleSelectAll() {
            const checked = document.getElementById('selectAll').checked;
            document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
                checkbox.checked = checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.employee-checkbox:checked');
            document.getElementById('bulkActions').classList.toggle('visible', checkedBoxes.length > 0);
        }

        function applyBulkShift() {
            const shiftId = document.getElementById('bulkShiftSelect').value;
            if (!shiftId) {
                alert('Please select a shift');
                return;
            }
            document.getElementById('bulkShiftId').value = shiftId;
            document.getElementById('bulkAssignForm').submit();
        }

        function assignShift(select, employeeId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="bulk_assign">
                <input type="hidden" name="shift_id" value="${select.value}">
                <input type="hidden" name="employees[]" value="${employeeId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function showAddShiftModal() {
            // Reset form
            document.getElementById('modalTitle').textContent = 'Create New Shift';
            document.getElementById('formAction').value = 'create_shift';
            document.getElementById('shiftId').value = '';
            document.getElementById('shiftName').value = '';
            document.getElementById('startTime').value = '';
            document.getElementById('endTime').value = '';
            document.getElementById('overtimeStart').value = '';
            document.getElementById('lateBuffer').value = '10';
            
            // Show modal
            document.getElementById('shiftModal').style.display = 'flex';
        }

        function hideModal() {
            document.getElementById('shiftModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('shiftModal');
            if (event.target === modal) {
                hideModal();
            }
        }

        function openShiftModal() {
            console.log('Opening modal...'); // Debug line
            const modal = document.getElementById('shiftModal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset form fields
                document.getElementById('shiftName').value = '';
                document.getElementById('startTime').value = '';
                document.getElementById('endTime').value = '';
                document.getElementById('overtimeStart').value = '';
                document.getElementById('lateBuffer').value = '10';
            } else {
                console.error('Modal element not found!'); // Debug line
            }
        }

        function closeShiftModal() {
            const modal = document.getElementById('shiftModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('shiftModal');
            if (event.target === modal) {
                closeShiftModal();
            }
        }
    </script>

    <!-- Add this modal structure -->
    <div id="shiftModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Shift</h3>
                <span class="close" onclick="closeShiftModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_shift">
                
                <div class="form-group">
                    <label for="shiftName">Shift Name:</label>
                    <input type="text" id="shiftName" name="name" required>
                </div>

                <div class="form-group">
                    <label for="startTime">Start Time:</label>
                    <input type="time" id="startTime" name="start_time" required>
                </div>

                <div class="form-group">
                    <label for="endTime">End Time:</label>
                    <input type="time" id="endTime" name="end_time" required>
                </div>

                <div class="form-group">
                    <label for="overtimeStart">Overtime Start:</label>
                    <input type="time" id="overtimeStart" name="overtime_start" required>
                </div>

                <div class="form-group">
                    <label for="lateBuffer">Late Buffer (minutes):</label>
                    <input type="number" id="lateBuffer" name="late_buffer" min="0" max="60" value="10" required>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="save-btn">Save Shift</button>
                    <button type="button" class="cancel-btn" onclick="closeShiftModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
