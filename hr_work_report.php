<?php
// hr_work_report.php
session_start();

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: login.php');
    exit();
}

// Database connection using config
require_once 'config.php';
$conn = getDBConnection();

// Fetch all work reports from attendance table
$sql = "SELECT 
            a.id,
            a.user_id,
            a.date,
            a.work_report,
            u.username,
            u.position,
            u.designation,
            u.email,
            u.phone_number
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.work_report IS NOT NULL AND a.work_report != ''
        ORDER BY a.date DESC, a.punch_in DESC";

try {
    $result = $conn->query($sql);
    if ($result === false) {
        throw new Exception("Query failed");
    }
} catch (Exception $e) {
    die("Error fetching work reports: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Work Report Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
        }
        .back-link {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .back-link:hover {
            background-color: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        th, td {
            border: 1px solid #e1e8ed;
            padding: 14px 16px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #eef2f7;
        }
        .report-content {
            background-color: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin: 12px 0;
            border-radius: 0 4px 4px 0;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.5;
        }
        .date {
            font-weight: 600;
            color: #3498db;
        }
        .user-info {
            color: #2c3e50;
            margin-bottom: 3px;
            font-weight: 500;
        }
        .user-email {
            color: #7f8c8d;
            font-size: 13px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        .empty-state p {
            font-size: 18px;
            margin: 0;
        }
        .report-count {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Work Reports Dashboard</h1>
                <div class="report-count">
                    <?php echo $result->rowCount(); ?> reports found
                </div>
            </div>
            <a href="hr_dashboard.php" class="back-link">← Back to HR Dashboard</a>
        </div>
        
        <?php if ($result->rowCount() > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Position</th>
                        <th>Designation</th>
                        <th>Contact</th>
                        <th>Work Report</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td class="date"><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                            <td>
                                <div class="user-info"><?php echo htmlspecialchars($row['username']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['designation'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="user-info"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($row['phone_number'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div class="report-content">
                                    <?php echo nl2br(htmlspecialchars($row['work_report'])); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No work reports found.</p>
                <p>Work reports will appear here when employees submit their daily work reports.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>