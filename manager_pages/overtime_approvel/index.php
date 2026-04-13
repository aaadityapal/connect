<?php
session_start();
// Include database connection (adjusting path since we are in a subdirectory)
require_once '../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if user has the correct role
$allowed_roles = ['admin', 'hr', 'Senior Manager (Studio)', 'Senior Manager (Site)'];
$user_role = strtolower($_SESSION['role'] ?? '');

$is_allowed = false;
foreach ($allowed_roles as $role) {
    if (strtolower($role) === $user_role) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    // Redirect to unauthorized page
    header('Location: ../../unauthorized.php');
    exit;
}

// Get current user ID from session
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Dashboard | Enterprise</title>
    <meta name="description"
        content="Enterprise overtime approval and monitoring dashboard for real-time employee activity tracking.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
        window.USER_ROLE = '<?php echo strtolower($_SESSION['role'] ?? ""); ?>';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>

    <!-- Global Design System -->
    <link rel="stylesheet" href="assets/css/global.css">

    <!-- Component CSS (pre-loaded to prevent FOUC) -->
    <link rel="stylesheet" href="components/header/header.css">
    <link rel="stylesheet" href="components/filters/filters.css">
    <link rel="stylesheet" href="components/metrics/metrics.css">
    <link rel="stylesheet" href="components/table/table.css">
    <link rel="stylesheet" href="components/modal/modal.css">

    <!-- Responsive Layouts -->
    <link rel="stylesheet" href="assets/css/mobile.css" media="screen and (max-width: 768px)">
    <link rel="stylesheet" href="assets/css/desktop.css" media="screen and (min-width: 769px)">

    <style>
        /* Modern Layout Integration */
        body {
            display: flex !important;
            flex-direction: row !important;
            height: 100vh !important;
            margin: 0 !important;
            overflow: hidden !important;
        }

        .dashboard-container {
            display: flex !important;
            flex-direction: row !important;
            width: 100% !important;
            height: 100vh !important;
            padding: 0 !important;
            gap: 0 !important;
            max-width: 100% !important;
            background: #f1f5f9;
        }

        .main-content {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding: 0 !important;
            background: #f1f5f9;
            min-width: 0;
        }

        #app-root {
            padding: 1.5rem 2rem 3rem !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 1.5rem !important;
            width: 100% !important;
            margin-top: 0 !important;
        }

        /* Nav Header Polish */
        .dh-nav-header {
            padding: 1.25rem 2.5rem;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            flex-shrink: 0;
        }

        .dh-icon-orange {
            background: #fff7ed;
            color: #f97316;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ffedd5;
        }

        .dh-greeting-text {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .dh-greeting-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            display: block;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar Mount Point -->
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <!-- Dashboard Components -->
            <div id="app-root">
                <div id="header-mount"></div>
                <div id="filters-mount"></div>
                <div id="metrics-mount"></div>
                <div id="table-mount"></div>
            </div>
        </main>
    </div>

    <!-- Main JS Orchestrator -->
    <script src="app.js" type="module"></script>
</body>

</html>