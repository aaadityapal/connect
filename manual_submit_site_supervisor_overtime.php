<?php
/**
 * Manual Submit OT Hours for Site Supervisor and Purchase Manager
 * 
 * This script allows manual submission of overtime hours for Site Supervisor and Purchase Manager users
 * directly to the overtime_requests table.
 */

session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and has appropriate permissions (admin)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $attendance_id = $_POST['attendance_id'] ?? null;
    $overtime_hours = $_POST['overtime_hours'] ?? null;
    $work_report = $_POST['work_report'] ?? '';
    $overtime_description = $_POST['overtime_description'] ?? 'Manual submission for Site Supervisor/Purchase Manager';
    $status = $_POST['status'] ?? 'pending';
    
    // Validate inputs
    if (!$user_id || !$attendance_id || !$overtime_hours) {
        $error = "All fields are required.";
    } else if (!is_numeric($overtime_hours) || $overtime_hours <= 0) {
        $error = "Overtime hours must be a positive number.";
    } else {
        try {
            // Get attendance record details
            $attendance_query = "SELECT date, punch_out FROM attendance WHERE id = ? AND user_id = ?";
            $attendance_stmt = $pdo->prepare($attendance_query);
            $attendance_stmt->execute([$attendance_id, $user_id]);
            $attendance_record = $attendance_stmt->fetch();
            
            if (!$attendance_record) {
                $error = "Attendance record not found.";
            } else {
                // Get shift end time for the user on that date
                $shift_query = "SELECT s.end_time FROM user_shifts us 
                               JOIN shifts s ON us.shift_id = s.id 
                               WHERE us.user_id = ? 
                               AND ? BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')";
                $shift_stmt = $pdo->prepare($shift_query);
                $shift_stmt->execute([$user_id, $attendance_record['date']]);
                $shift_record = $shift_stmt->fetch();
                
                $shift_end_time = $shift_record['end_time'] ?? '18:00:00'; // Default shift end time
                
                // Check if a record already exists for this attendance
                $check_query = "SELECT id FROM overtime_requests WHERE attendance_id = ?";
                $check_stmt = $pdo->prepare($check_query);
                $check_stmt->execute([$attendance_id]);
                $existing_record = $check_stmt->fetch();
                
                if ($existing_record) {
                    // Update existing record
                    $update_query = "UPDATE overtime_requests SET 
                                        user_id = ?,
                                        date = ?,
                                        shift_end_time = ?,
                                        punch_out_time = ?,
                                        overtime_hours = ?,
                                        work_report = ?,
                                        overtime_description = ?,
                                        status = ?,
                                        updated_at = NOW()
                                     WHERE attendance_id = ?";
                    
                    $update_stmt = $pdo->prepare($update_query);
                    $result = $update_stmt->execute([
                        $user_id,
                        $attendance_record['date'],
                        $shift_end_time,
                        $attendance_record['punch_out'],
                        $overtime_hours,
                        $work_report,
                        $overtime_description,
                        $status,
                        $attendance_id
                    ]);
                    
                    if ($result) {
                        $success = "Overtime request updated successfully.";
                    } else {
                        $error = "Error updating overtime request.";
                    }
                } else {
                    // Insert new record
                    $insert_query = "INSERT INTO overtime_requests (
                                        user_id,
                                        attendance_id,
                                        date,
                                        shift_end_time,
                                        punch_out_time,
                                        overtime_hours,
                                        work_report,
                                        overtime_description,
                                        status,
                                        submitted_at
                                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $insert_stmt = $pdo->prepare($insert_query);
                    $result = $insert_stmt->execute([
                        $user_id,
                        $attendance_id,
                        $attendance_record['date'],
                        $shift_end_time,
                        $attendance_record['punch_out'],
                        $overtime_hours,
                        $work_report,
                        $overtime_description,
                        $status
                    ]);
                    
                    if ($result) {
                        $success = "Overtime request submitted successfully.";
                    } else {
                        $error = "Error submitting overtime request.";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get Site Supervisor and Purchase Manager users
$users_query = "SELECT id, username FROM users WHERE role IN ('Site Supervisor', 'Purchase Manager') AND status = 'active' ORDER BY username";
$users_stmt = $pdo->prepare($users_query);
$users_stmt->execute();
$site_supervisors = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance records for Site Supervisors and Purchase Managers in November 2025 that don't have overtime_requests
$attendance_query = "SELECT a.id as attendance_id, a.user_id, u.username, a.date, a.punch_out, a.overtime_hours 
                     FROM attendance a 
                     JOIN users u ON a.user_id = u.id 
                     LEFT JOIN overtime_requests oreq ON a.id = oreq.attendance_id 
                     WHERE u.role IN ('Site Supervisor', 'Purchase Manager') 
                     AND MONTH(a.date) = 11 
                     AND YEAR(a.date) = 2025 
                     AND a.overtime_hours IS NOT NULL 
                     AND a.overtime_hours > '00:00:00' 
                     AND oreq.id IS NULL
                     ORDER BY u.username, a.date";
$attendance_stmt = $pdo->prepare($attendance_query);
$attendance_stmt->execute();
$attendance_records = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Submit OT Hours - Site Supervisor & Purchase Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">
                <i class="fas fa-business-time text-blue-600 mr-2"></i>
                Manual Submit OT Hours for Site Supervisors & Purchase Managers
            </h1>
            <p class="text-gray-600 mb-6">
                Submit overtime hours manually for Site Supervisor and Purchase Manager users who may not have their data properly recorded.
            </p>
            
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-user mr-1"></i>
                            User
                        </label>
                        <select name="user_id" id="user_id" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select a Site Supervisor or Purchase Manager</option>
                            <?php foreach ($site_supervisors as $supervisor): ?>
                                <option value="<?php echo $supervisor['id']; ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $supervisor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supervisor['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-calendar mr-1"></i>
                            Attendance Record
                        </label>
                        <select name="attendance_id" id="attendance_id" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select an attendance record</option>
                            <?php foreach ($attendance_records as $record): ?>
                                <option value="<?php echo $record['attendance_id']; ?>" 
                                        data-user="<?php echo $record['user_id']; ?>"
                                        data-hours="<?php echo preg_replace('/^(\d+):(\d+):.*$/', '$1.$2', $record['overtime_hours']); ?>"
                                        <?php echo (isset($_POST['attendance_id']) && $_POST['attendance_id'] == $record['attendance_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($record['username'] . ' - ' . $record['date'] . ' (' . $record['overtime_hours'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-clock mr-1"></i>
                            Overtime Hours
                        </label>
                        <input type="number" step="0.5" min="0.5" name="overtime_hours" id="overtime_hours" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Enter overtime hours" 
                               value="<?php echo isset($_POST['overtime_hours']) ? htmlspecialchars($_POST['overtime_hours']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tasks mr-1"></i>
                            Status
                        </label>
                        <select name="status" id="status" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="submitted" <?php echo (isset($_POST['status']) && $_POST['status'] == 'submitted') ? 'selected' : ''; ?>>Submitted</option>
                            <option value="approved" <?php echo (isset($_POST['status']) && $_POST['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo (isset($_POST['status']) && $_POST['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-file-alt mr-1"></i>
                        Work Report
                    </label>
                    <textarea name="work_report" id="work_report" rows="3" 
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                              placeholder="Enter work report (optional)"><?php echo isset($_POST['work_report']) ? htmlspecialchars($_POST['work_report']) : ''; ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-comment mr-1"></i>
                        Overtime Description
                    </label>
                    <textarea name="overtime_description" id="overtime_description" rows="2" 
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                              placeholder="Enter overtime description"><?php echo isset($_POST['overtime_description']) ? htmlspecialchars($_POST['overtime_description']) : 'Manual submission for Site Supervisor/Purchase Manager'; ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Overtime Request
                    </button>
                </div>
            </form>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-list mr-2"></i>
                Site Supervisor & Purchase Manager Attendance Records (November 2025)
            </h2>
            
            <?php if (empty($attendance_records)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-info-circle text-4xl mb-4"></i>
                    <p>No attendance records found for Site Supervisors and Purchase Managers in November 2025 that need manual submission.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Punch Out</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime Hours</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($attendance_records as $record): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($record['username']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($record['date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($record['punch_out']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($record['overtime_hours']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending Manual Submission
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-fill overtime hours when an attendance record is selected
        document.getElementById('attendance_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const hours = selectedOption.getAttribute('data-hours');
            
            if (hours) {
                // Convert format from HH.MM to decimal (e.g., 1.30 to 1.5)
                const parts = hours.split('.');
                if (parts.length === 2) {
                    const decimalHours = parseInt(parts[0]) + (parseInt(parts[1]) / 60);
                    document.getElementById('overtime_hours').value = decimalHours.toFixed(1);
                } else {
                    document.getElementById('overtime_hours').value = hours;
                }
            }
        });
    </script>
</body>
</html>