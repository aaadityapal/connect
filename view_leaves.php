<?php
// Add this to your existing leave listing query
$query = "
    SELECT l.*, 
           u.username as employee_name,
           u.email as employee_email,
           u.reporting_manager,
           DATEDIFF(l.end_date, l.start_date) + 1 as duration,
           m.username as manager_name,
           h.username as hr_name,
           ms.username as manager_approved_by_name,
           hs.username as hr_approved_by_name
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN users m ON u.reporting_manager = m.id
    LEFT JOIN users h ON l.hr_approved_by = h.id
    LEFT JOIN users ms ON l.manager_approved_by = ms.id
    LEFT JOIN users hs ON l.hr_approved_by = hs.id
    WHERE 1=1
";

// Add role-specific conditions
if ($_SESSION['role'] === 'Senior Manager (Studio)') {
    $query .= " AND u.reporting_manager = ?";
    $params[] = $_SESSION['user_id'];
}
?>

<!-- In your HTML table -->
<table class="table">
    <thead>
        <tr>
            <th>Employee</th>
            <th>Leave Type</th>
            <th>Duration</th>
            <th>Manager Status</th>
            <th>HR Status</th>
            <th>Final Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($leaves as $leave): ?>
            <tr>
                <td><?php echo htmlspecialchars($leave['employee_name']); ?></td>
                <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                <td><?php echo $leave['duration']; ?> days</td>
                <td>
                    <span class="badge bg-<?php echo getStatusColor($leave['manager_status']); ?>">
                        <?php echo htmlspecialchars($leave['manager_status']); ?>
                        <?php if ($leave['manager_approved_by_name']): ?>
                            <br><small>by <?php echo htmlspecialchars($leave['manager_approved_by_name']); ?></small>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <span class="badge bg-<?php echo getStatusColor($leave['hr_status']); ?>">
                        <?php echo htmlspecialchars($leave['hr_status']); ?>
                        <?php if ($leave['hr_approved_by_name']): ?>
                            <br><small>by <?php echo htmlspecialchars($leave['hr_approved_by_name']); ?></small>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <span class="badge bg-<?php echo getStatusColor($leave['status']); ?>">
                        <?php echo htmlspecialchars($leave['status']); ?>
                    </span>
                </td>
                <td>
                    <?php if (canApproveLeave($leave)): ?>
                        <button class="btn btn-sm btn-success" onclick="approveLeave(<?php echo $leave['id']; ?>)">
                            Approve
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="rejectLeave(<?php echo $leave['id']; ?>)">
                            Reject
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'Approved': return 'success';
        case 'Rejected': return 'danger';
        default: return 'warning';
    }
}

function canApproveLeave($leave) {
    $role = $_SESSION['role'];
    $userId = $_SESSION['user_id'];
    
    if ($role === 'Senior Manager (Studio)') {
        return $leave['reporting_manager'] == $userId && $leave['manager_status'] === 'Pending';
    }
    
    if ($role === 'HR') {
        return $leave['hr_status'] === 'Pending';
    }
    
    return false;
}
?>
