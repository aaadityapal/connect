<?php
session_start();
require_once 'config/db_connect.php';

// Function to insert test announcements
function insertTestAnnouncements($conn) {
    // First, let's clear any existing test announcements
    $clear_query = "DELETE FROM announcements WHERE title LIKE 'Test Announcement%'";
    $conn->query($clear_query);

    // Test announcements data
    $test_announcements = [
        [
            'title' => 'Test Announcement - High Priority',
            'message' => 'This is a high priority test announcement. Please check if the styling is correct.',
            'priority' => 'high',
            'display_until' => date('Y-m-d', strtotime('+7 days')),
            'content' => '<strong>Additional Details:</strong><br>- Point 1<br>- Point 2<br>- Point 3',
            'created_by' => $_SESSION['user_id'] ?? 1,
            'status' => 'active'
        ],
        [
            'title' => 'Test Announcement - Normal Priority',
            'message' => 'This is a normal priority test announcement with some regular information.',
            'priority' => 'normal',
            'display_until' => date('Y-m-d', strtotime('+5 days')),
            'content' => null,
            'created_by' => $_SESSION['user_id'] ?? 1,
            'status' => 'active'
        ],
        [
            'title' => 'Test Announcement - Low Priority',
            'message' => 'This is a low priority test announcement for general information.',
            'priority' => 'low',
            'display_until' => date('Y-m-d', strtotime('+3 days')),
            'content' => null,
            'created_by' => $_SESSION['user_id'] ?? 1,
            'status' => 'active'
        ],
        [
            'title' => 'Test Announcement - Expired',
            'message' => 'This announcement should not appear as it has expired.',
            'priority' => 'normal',
            'display_until' => date('Y-m-d', strtotime('-1 day')),
            'content' => null,
            'created_by' => $_SESSION['user_id'] ?? 1,
            'status' => 'active'
        ]
    ];

    $insert_query = "INSERT INTO announcements (title, message, priority, display_until, content, created_by, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);

    $inserted_count = 0;
    foreach ($test_announcements as $announcement) {
        $stmt->bind_param("sssssss", 
            $announcement['title'],
            $announcement['message'],
            $announcement['priority'],
            $announcement['display_until'],
            $announcement['content'],
            $announcement['created_by'],
            $announcement['status']
        );
        if ($stmt->execute()) {
            $inserted_count++;
        }
    }

    return $inserted_count;
}

// Handle form submission to insert test announcements
$message = '';
if (isset($_POST['insert_test_data'])) {
    $inserted = insertTestAnnouncements($conn);
    $message = "Successfully inserted $inserted test announcements.";
}

// Get current announcements count
$count_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_count,
    SUM(CASE WHEN priority = 'normal' THEN 1 ELSE 0 END) as normal_count,
    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_count,
    SUM(CASE WHEN display_until < CURDATE() THEN 1 ELSE 0 END) as expired_count
    FROM announcements 
    WHERE status = 'active'";
$counts = $conn->query($count_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Announcements</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/announcements_popup.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .test-controls {
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background: #2980b9;
        }

        .test-button {
            margin-top: 10px;
            background: #27ae60;
        }

        .test-button:hover {
            background: #219a52;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .clear-button {
            background: #e74c3c;
        }

        .clear-button:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bullhorn"></i> Announcements Test Page</h1>
        
        <?php if ($message): ?>
            <div class="message success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="test-controls">
            <h2>Test Controls</h2>
            <div class="button-group">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="insert_test_data">
                        <i class="fas fa-plus"></i> Insert Test Announcements
                    </button>
                </form>
                <button class="test-button" onclick="showAnnouncements()">
                    <i class="fas fa-eye"></i> Show Announcements Popup
                </button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_test_data" class="clear-button">
                        <i class="fas fa-trash"></i> Clear Test Data
                    </button>
                </form>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3><i class="fas fa-chart-bar"></i> Total Active</h3>
                <div class="stat-value"><?php echo $counts['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-exclamation-circle"></i> High Priority</h3>
                <div class="stat-value"><?php echo $counts['high_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-bell"></i> Normal Priority</h3>
                <div class="stat-value"><?php echo $counts['normal_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-info-circle"></i> Low Priority</h3>
                <div class="stat-value"><?php echo $counts['low_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-calendar-times"></i> Expired</h3>
                <div class="stat-value"><?php echo $counts['expired_count']; ?></div>
            </div>
        </div>

        <!-- Include the announcements popup -->
        <?php include 'announcements_popup.php'; ?>
    </div>

    <!-- Include JavaScript at the end of the body -->
    <script src="assets/js/announcements_popup.js"></script>
</body>
</html> 