<?php
/**
 * Check for pending missing punch requests
 */
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check for pending missing punch in requests
$punch_in_query = "SELECT * FROM missing_punch_in WHERE status = 'pending' LIMIT 5";
$punch_in_result = $conn->query($punch_in_query);

// Check for pending missing punch out requests
$punch_out_query = "SELECT * FROM missing_punch_out WHERE status = 'pending' LIMIT 5";
$punch_out_result = $conn->query($punch_out_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Pending Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Check Pending Missing Punch Requests</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Pending Missing Punch In Requests</h5>
            </div>
            <div class="card-body">
                <?php if ($punch_in_result && $punch_in_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User ID</th>
                                    <th>Date</th>
                                    <th>Punch In Time</th>
                                    <th>Reason</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $punch_in_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><?= htmlspecialchars($row['user_id']) ?></td>
                                        <td><?= htmlspecialchars($row['date']) ?></td>
                                        <td><?= htmlspecialchars($row['punch_in_time']) ?></td>
                                        <td><?= htmlspecialchars(substr($row['reason'], 0, 50)) ?><?= strlen($row['reason']) > 50 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No pending missing punch in requests found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Pending Missing Punch Out Requests</h5>
            </div>
            <div class="card-body">
                <?php if ($punch_out_result && $punch_out_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User ID</th>
                                    <th>Date</th>
                                    <th>Punch Out Time</th>
                                    <th>Reason</th>
                                    <th>Work Report</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $punch_out_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><?= htmlspecialchars($row['user_id']) ?></td>
                                        <td><?= htmlspecialchars($row['date']) ?></td>
                                        <td><?= htmlspecialchars($row['punch_out_time']) ?></td>
                                        <td><?= htmlspecialchars(substr($row['reason'], 0, 50)) ?><?= strlen($row['reason']) > 50 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars(substr($row['work_report'], 0, 50)) ?><?= strlen($row['work_report']) > 50 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No pending missing punch out requests found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>