<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
    // Redirect to login page if not authorized
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Panel</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-styles.css">
    <link rel="stylesheet" href="assets/css/notification-system.css">
    <link rel="stylesheet" href="css/fingerprint_button.css">
    <link rel="stylesheet" href="css/fingerprint_notification.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .sidebar {
            min-height: 100vh;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .dashboard {
            display: flex;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include 'components/sidebar.php'; ?>
    </div>

    <script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>