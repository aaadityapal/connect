<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/db_connect.php';
$user_id = $_SESSION['user_id'];

// Get session user details for the header display
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = :uid");
$stmt->execute([':uid' => $user_id]);
$current_username = $stmt->fetchColumn() ?: "Unknown User";

// Format Current Date (e.g., Mar 27, 2026)
$current_date = date('M d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Management System | Connect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Base Global Styles -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Component Specific Styles -->
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="filters.css">
    <link rel="stylesheet" href="stats.css">
    <link rel="stylesheet" href="table.css">

    <!-- Layout Overrides per Viewport -->
    <link rel="stylesheet" href="desktop.css">
    <link rel="stylesheet" href="mobile.css">

    <!-- Sidebar Dependencies -->
    <script>window.SIDEBAR_BASE_PATH = '../';</script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="../components/sidebar-loader.js" defer></script>
    <style>
        html, body { margin: 0; padding: 0; min-height: 100%; overflow-x: hidden; }
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100vw;
            overflow: hidden;
        }
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100vh;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Mount Point -->
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <!-- Hamburger for Mobile -->
            <div class="mobile-menu-wrapper" style="padding: 16px 20px 0; display: none;">
                <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar" style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: 8px; cursor: pointer; color: var(--text-primary); padding: 8px; box-shadow: var(--shadow-xs);">
                    <i data-lucide="menu" style="width:20px;height:20px;"></i>
                </button>
            </div>
            <style>
                @media (max-width: 768px) {
                    .mobile-menu-wrapper { display: flex !important; }
                    .app-container { padding-top: 16px !important; }
                }
            </style>

            <div class="app-container" id="app-root" style="opacity:1 !important; animation:none !important;">

<?php 
// Native server-side includes of the standalone HTML component snippets
include __DIR__ . '/header.php';
include __DIR__ . '/filters.html';
include __DIR__ . '/stats.html';
include __DIR__ . '/table.html'; 
?>

            </div><!-- /.app-container -->
        </main>
    </div>

    <script src="header.js"></script>
    <script src="filters.js"></script>
    <script src="stats.js"></script>
    <script src="table.js"></script>
    <script src="script.js"></script>

    <script>
        // Initialize components if they exist
        if (typeof initHeader === 'function') initHeader();
        if (typeof initFilters === 'function') initFilters();
        if (typeof initStats === 'function') initStats();
        if (typeof initTable === 'function') initTable();
    </script>
</body>
</html>
