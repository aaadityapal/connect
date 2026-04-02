<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/db_connect.php';

$userId = $_GET['user_id'] ?? 0;
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

// Fetch user info based on passed ID
$stmt = $pdo->prepare("SELECT unique_id, username, email, role FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found.");
}

$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];
$monthName = $months[(int) $month] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Report - <?php echo htmlspecialchars($employee['username']); ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="../../studio_users/header.css">
    <link rel="stylesheet" href="../../studio_users/components/sidebar.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            margin: 0;
            color: #171717;
        }

        .dashboard-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .main-content-scroll {
            overflow-y: auto;
            flex: 1;
            padding: 2.5rem 3rem;
        }

        .dh-nav-header {
            background: #fff;
            padding: 1rem 2rem;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: #52525b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
            margin-bottom: 1.5rem;
        }

        .back-link:hover {
            color: #18181b;
        }

        .report-header {
            background: #fff;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .emp-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
            color: #111;
            letter-spacing: -0.01em;
        }

        .emp-roles {
            color: #52525b;
            font-size: 0.9rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            background: #f4f4f5;
            color: #52525b;
            border-radius: 4px;
            font-size: 0.75rem;
            border: 1px solid #e4e4e7;
        }

        .badge.primary {
            background: #ecfdf5;
            color: #059669;
            border-color: #d1fae5;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        .stat-title {
            font-size: 0.8rem;
            font-weight: 500;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #111;
            letter-spacing: -0.03em;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #111;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 0.75rem;
        }

        /* Placeholder table */
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eaeaea;
            margin-top: 1rem;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            font-size: 0.8rem;
            font-weight: 500;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #fafafa;
        }

        td {
            font-size: 0.9rem;
            color: #262626;
        }
    </style>
</head>

<body class="el-1">
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>
        <main class="main-content">
            <header class="dh-nav-header">
                <div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="color: #52525b; display: flex; align-items: center;">
                        <i data-lucide="file-text" style="width:16px;height:16px;"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 500; color: #18181b;">Internal Reports > Viewer</span>
                </div>
            </header>

            <div class="main-content-scroll">
                <a href="index.php" class="back-link">
                    <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                    Back to Staff List
                </a>

                <div class="report-header">
                    <div>
                        <h1 class="emp-name"><?php echo htmlspecialchars($employee['username']); ?></h1>
                        <div class="emp-roles">
                            <?php echo htmlspecialchars($employee['email'] ?? 'No Email'); ?> &middot;
                            Emp Code: <span
                                style="font-family: inherit; font-size: 0.85rem; background: #f4f4f5; border: 1px solid #e4e4e7; padding: 0.1rem 0.3rem; border-radius: 4px;"><?php echo htmlspecialchars($employee['unique_id'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div
                            style="font-size: 0.8rem; color: #737373; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">
                            Time Period</div>
                        <span class="badge primary"
                            style="font-size: 0.9rem; font-weight: 500; padding: 0.4rem 0.8rem;">
                            <?php echo htmlspecialchars($monthName . ' ' . $year); ?>
                        </span>
                    </div>
                </div>

                <div class="report-grid">
                    <div class="stat-card">
                        <div class="stat-title">Expected Working Days</div>
                        <div class="stat-value">22</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Total Present</div>
                        <div class="stat-value" style="color: #059669;">21</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Leaves & Absences</div>
                        <div class="stat-value" style="color: #dc2626;">1</div>
                    </div>
                </div>

                <div style="background: #fff; border: 1px solid #eaeaea; border-radius: 8px; padding: 2rem;">
                    <h2 class="section-title">Daily Summary Log (Mock Data for Now)</h2>
                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">Below is the calculated working
                        summary. We will connect the backend data (Attendance, Tasks) once we configure the query.</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo "01 " . htmlspecialchars($monthName) . " " . htmlspecialchars($year); ?>
                                </td>
                                <td>09:00 AM</td>
                                <td>06:05 PM</td>
                                <td><span class="badge"
                                        style="background:#ecfdf5;color:#059669;border-color:#d1fae5;">Present</span>
                                </td>
                            </tr>
                            <tr>
                                <td><?php echo "02 " . htmlspecialchars($monthName) . " " . htmlspecialchars($year); ?>
                                </td>
                                <td>09:15 AM</td>
                                <td>06:00 PM</td>
                                <td><span class="badge"
                                        style="background:#ecfdf5;color:#059669;border-color:#d1fae5;">Present</span>
                                </td>
                            </tr>
                            <tr>
                                <td><?php echo "03 " . htmlspecialchars($monthName) . " " . htmlspecialchars($year); ?>
                                </td>
                                <td colspan="2" style="text-align: center; color: #737373;">Out of Office</td>
                                <td><span class="badge"
                                        style="background: #fef2f2; color: #dc2626; border-color: #fee2e2;">Leave</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>