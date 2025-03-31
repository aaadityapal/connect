<?php
session_start();
require_once 'config.php';

// Check authentication and role permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR', 'Senior Manager (Studio)'])) {
    header('Location: unauthorized.php');
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

// Base query
$query = "
    SELECT 
        a.date,
        a.work_report,
        u.username,
        u.designation,
        u.unique_id
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.work_report IS NOT NULL 
    AND a.date BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

// Add user filter to query
if ($user_id) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_id;
}

$query .= " ORDER BY a.date DESC, u.username ASC";

// Fetch work reports
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$work_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users for filter dropdown
$users_query = "SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username ASC";
$users_stmt = $pdo->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4F46E5;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --bg-light: #F3F4F6;
            --bg-white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1 {
            font-size: 28px;
            color: var(--text-dark);
        }

        .back-button {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filters {
            background: var(--bg-white);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .apply-filters {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .apply-filters:hover {
            background: #4338CA;
            transform: translateY(-1px);
        }

        .reports-grid {
            display: grid;
            gap: 20px;
        }

        .report-card {
            background: var(--bg-white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .user-info h3 {
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .user-info p {
            font-size: 14px;
            color: var(--text-light);
        }

        .report-date {
            font-size: 14px;
            color: var(--text-light);
            background: var(--bg-light);
            padding: 6px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .report-content {
            font-size: 15px;
            color: var(--text-dark);
            line-height: 1.6;
            white-space: pre-line;
        }

        .no-reports {
            text-align: center;
            padding: 40px;
            background: var(--bg-white);
            border-radius: 10px;
            color: var(--text-light);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Work Reports</h1>
            <a href="real.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <form class="filters" method="GET">
            <div class="filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" 
                       value="<?php echo $start_date; ?>">
            </div>

            <div class="filter-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" 
                       value="<?php echo $end_date; ?>">
            </div>

            <div class="filter-group">
                <label for="user_id">Employee</label>
                <select id="user_id" name="user_id">
                    <option value="">All Employees</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?> 
                            (<?php echo htmlspecialchars($user['unique_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <button type="submit" class="apply-filters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>

        <div class="reports-grid">
            <?php if (empty($work_reports)): ?>
                <div class="no-reports">
                    <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 20px; color: var(--text-light);"></i>
                    <p>No work reports found for the selected criteria.</p>
                </div>
            <?php else: ?>
                <?php foreach ($work_reports as $report): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div class="user-info">
                                <h3><?php echo htmlspecialchars($report['username']); ?></h3>
                                <p><?php echo htmlspecialchars($report['designation']); ?></p>
                            </div>
                            <span class="report-date">
                                <i class="far fa-calendar"></i>
                                <?php echo date('d M Y', strtotime($report['date'])); ?>
                            </span>
                        </div>
                        <div class="report-content">
                            <?php echo nl2br(htmlspecialchars($report['work_report'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add date validation
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');

            startDate.addEventListener('change', function() {
                endDate.min = this.value;
            });

            endDate.addEventListener('change', function() {
                startDate.max = this.value;
            });
        });
    </script>
</body>
</html> 