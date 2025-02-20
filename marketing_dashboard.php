<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is a marketing manager
if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'Marketing Manager') {
    header('Location: login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Manager Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .dashboard-container {
            padding: 20px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .campaign-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .campaign-card:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .status-active {
            background: #e3fcef;
            color: #00a854;
        }
        
        .status-pending {
            background: #fff7e6;
            color: #fa8c16;
        }
        
        .status-completed {
            background: #f0f5ff;
            color: #2f54eb;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .action-button {
            padding: 15px;
            border: none;
            border-radius: 8px;
            background: #4834d4;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-button:hover {
            background: #372aaa;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <h2>Welcome, <?php echo htmlspecialchars($username); ?></h2>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <button class="action-button" onclick="location.href='create_campaign.php'">
            <i class="fas fa-plus"></i> New Campaign
        </button>
        <button class="action-button" onclick="location.href='team_tasks.php'">
            <i class="fas fa-tasks"></i> Team Tasks
        </button>
        <button class="action-button" onclick="location.href='analytics.php'">
            <i class="fas fa-chart-line"></i> Analytics
        </button>
        <button class="action-button" onclick="location.href='budget_management.php'">
            <i class="fas fa-money-bill"></i> Budget
        </button>
    </div>
    
    <!-- Statistics Overview -->
    <div class="row">
        <div class="col-md-3">
            <div class="stats-card">
                <h4>Active Campaigns</h4>
                <h2>
                    <?php
                    $active_query = "SELECT COUNT(*) as count FROM campaigns WHERE status = 'active'";
                    $result = $conn->query($active_query);
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                    ?>
                </h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h4>Pending Approvals</h4>
                <h2>
                    <?php
                    $pending_query = "SELECT COUNT(*) as count FROM campaigns WHERE status = 'pending'";
                    $result = $conn->query($pending_query);
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                    ?>
                </h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h4>Team Members</h4>
                <h2>
                    <?php
                    $team_query = "SELECT COUNT(*) as count FROM users WHERE type = 'Marketing Team'";
                    $result = $conn->query($team_query);
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                    ?>
                </h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <h4>Monthly Budget</h4>
                <h2>$
                    <?php
                    $budget_query = "SELECT SUM(budget) as total FROM campaigns WHERE MONTH(start_date) = MONTH(CURRENT_DATE())";
                    $result = $conn->query($budget_query);
                    $row = $result->fetch_assoc();
                    echo number_format($row['total'] ?? 0);
                    ?>
                </h2>
            </div>
        </div>
    </div>
    
    <!-- Recent Campaigns -->
    <div class="row mt-4">
        <div class="col-md-8">
            <h3>Recent Campaigns</h3>
            <?php
            $campaigns_query = "SELECT * FROM campaigns ORDER BY start_date DESC LIMIT 5";
            $campaigns_result = $conn->query($campaigns_query);
            
            while ($campaign = $campaigns_result->fetch_assoc()):
            ?>
            <div class="campaign-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h5><?php echo htmlspecialchars($campaign['name']); ?></h5>
                    <span class="status-badge status-<?php echo $campaign['status']; ?>">
                        <?php echo ucfirst($campaign['status']); ?>
                    </span>
                </div>
                <p><?php echo htmlspecialchars($campaign['description']); ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <small>Budget: $<?php echo number_format($campaign['budget']); ?></small>
                    <small>Start Date: <?php echo date('M d, Y', strtotime($campaign['start_date'])); ?></small>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Team Performance -->
        <div class="col-md-4">
            <h3>Team Performance</h3>
            <div class="stats-card">
                <canvas id="teamPerformanceChart"></canvas>
            </div>
            
            <!-- Upcoming Tasks -->
            <h3>Upcoming Tasks</h3>
            <?php
            $tasks_query = "SELECT * FROM tasks WHERE assigned_to = $user_id AND status != 'completed' ORDER BY due_date LIMIT 5";
            $tasks_result = $conn->query($tasks_query);
            
            while ($task = $tasks_result->fetch_assoc()):
            ?>
            <div class="campaign-card">
                <h6><?php echo htmlspecialchars($task['title']); ?></h6>
                <small>Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></small>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Team Performance Chart
const ctx = document.getElementById('teamPerformanceChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
        datasets: [{
            label: 'Campaign Performance',
            data: [65, 59, 80, 81, 56],
            borderColor: '#4834d4',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html> 