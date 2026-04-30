<?php
/**
 * manager_pages/employees_performance/index.php
 * Employees Performance Dashboard — UI Phase
 */
session_start();
require_once '../../config/db_connect.php';

// ── Auth guard ──
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// ── Role check (adjust as needed) ──
$allowed_roles = ['admin', 'hr', 'Senior Manager (Studio)', 'Senior Manager (Site)', 'manager'];
$user_role_raw = $_SESSION['role'] ?? '';
$user_role_lc  = strtolower($user_role_raw);

$is_allowed = false;
foreach ($allowed_roles as $r) {
    if (strtolower($r) === $user_role_lc) { $is_allowed = true; break; }
}
if (!$is_allowed) {
    header('Location: ../../unauthorized.php');
    exit;
}

// ── Current user info ──
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();
$username = $current_user['username'] ?? 'Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Performance | Connect</title>
    <meta name="description" content="Monitor and evaluate employee performance metrics including attendance, task completion, quality scores and overall ratings.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <!-- Sidebar base path (two levels up from manager_pages/employees_performance) -->
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
        window.USER_ROLE = '<?php echo htmlspecialchars(strtolower($user_role_raw)); ?>';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>

    <!-- Design System -->
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/desktop.css">
</head>
<body>

<div class="dashboard-container">

    <!-- Sidebar Mount -->
    <div id="sidebar-mount"></div>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Top Nav Header -->
        <header class="dh-nav-header">
            <div class="dh-nav-left">
                <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                    <i data-lucide="menu" style="width:18px;height:18px;"></i>
                </button>
                <div class="dh-user-info">
                    <div class="dh-icon-violet">
                        <i data-lucide="bar-chart-3" style="width:16px;height:16px;"></i>
                    </div>
                    <div class="dh-greeting">
                        <span class="dh-greeting-text">Management</span>
                        <span class="dh-greeting-name">Employee Performance</span>
                    </div>
                </div>
            </div>

            <div class="dh-nav-right">
                <!-- Month & Year Filters -->
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <select id="header-month-select" class="filter-select" style="min-width:110px; padding:0.3rem 0.6rem; font-size:0.8rem; height:auto; border-radius:99px;">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <select id="header-year-select" class="filter-select" style="min-width:85px; padding:0.3rem 0.6rem; font-size:0.8rem; height:auto; border-radius:99px;">
                        <?php 
                        $curYear = (int)date('Y');
                        for($y = $curYear - 2; $y <= $curYear + 1; $y++) {
                            echo "<option value='$y' " . ($y == $curYear ? 'selected' : '') . ">$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="dh-profile-box">
                    <div class="dh-profile-avatar" title="<?php echo htmlspecialchars($username); ?>">
                        <i data-lucide="user" style="width:16px;height:16px;"></i>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <div id="perf-root">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title-block">
                    <h1 class="page-title">Employee Performance</h1>
                    <p class="page-subtitle">Track attendance, task completion, quality & overall scores across your team.</p>
                </div>
                <div class="page-actions">
                    <button class="btn btn-outline" id="btn-refresh">
                        <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Refresh
                    </button>
                    <button class="btn btn-brand" id="btn-add-review">
                        <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Review
                    </button>
                </div>
            </div>

            <!-- Metrics Row -->
            <div id="metrics-mount"></div>

            <!-- Filter Bar -->
            <div id="filters-mount"></div>

            <!-- Content Grid: Table + Sidebar Panels -->
            <div class="content-grid">
                <div id="table-mount"></div>
                <div id="sidebar-panels"></div>
            </div>

        </div><!-- /#perf-root -->

    </main>
</div><!-- /.dashboard-container -->

<!-- Modal Backdrop -->
<div class="modal-backdrop hidden" id="modal-backdrop"></div>

<!-- Toast -->
<div id="perf-toast"></div>

<!-- Main App JS -->
<script src="js/helpers.js"></script>
<script src="js/app.js"></script>

<script>
    // Refresh btn
    document.getElementById('btn-refresh').addEventListener('click', () => {
        location.reload();
    });

    // Add Review btn (placeholder)
    document.getElementById('btn-add-review').addEventListener('click', () => {
        const t = document.getElementById('perf-toast');
        t.innerHTML = '<i data-lucide="info" style="width:16px;height:16px;"></i> Review form coming in the next phase.';
        t.style.background = '#1e293b';
        t.classList.add('show');
        if (window.lucide) lucide.createIcons();
        setTimeout(() => t.classList.remove('show'), 3200);
    });
</script>

</body>
</html>
