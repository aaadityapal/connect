<?php
session_start();
// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Corner | Management Panel</title>
    <meta name="description" content="HR Administrator panel to manage and publish company policies.">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/tabs.css">
    <link rel="stylesheet" href="css/form.css">
    <link rel="stylesheet" href="css/notice.css">
    <link rel="stylesheet" href="css/custom.css?v=2">
    <link rel="stylesheet" href="css/toast.css">
    <link rel="stylesheet" href="css/mobile.css">

    <!-- Sidebar Configuration -->
    <script>
        // Tell sidebar-loader.js where to find components/ relative to this file
        window.SIDEBAR_BASE_PATH = '../';
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script src="../components/sidebar-loader.js" defer></script>
</head>
<body class="hr-body">

    <div class="hr-dashboard-container">
        <!-- Sidebar mount point -->
        <div id="sidebar-mount"></div>

        <!-- Main Content Wrapper -->
        <main class="hr-main-content">
            <!-- Desktop Header / Mobile Hamburger Toggle -->
            <div id="hr-header"></div>
            
            <div id="hr-tabs"></div>
            <div id="hr-form"></div>
            <div id="hr-notice"></div>
            <div id="hr-custom"></div>
            <div id="hr-toast"></div>
        </main>
    </div>

    <!-- JS -->
    <script src="js/app.js?v=2"></script>

</body>
</html>
