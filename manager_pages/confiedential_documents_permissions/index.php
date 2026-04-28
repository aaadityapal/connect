<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/db_connect.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT username, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || strtolower(trim((string)($currentUser['role'] ?? ''))) !== 'admin') {
    header('Location: ../../studio_users/index.php');
    exit();
}

$username = (string)($currentUser['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confiedential Docs Permission | Connect Admin</title>
    <link rel="stylesheet" href="../../studio_users/style.css">
    <link rel="stylesheet" href="../../studio_users/header.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
</head>
<body class="el-1">
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <header class="dh-nav-header">
                <div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
                    <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                        <i data-lucide="menu" style="width:18px;height:18px;"></i>
                    </button>
                    <div class="dh-user-info">
                        <div class="dh-icon-orange"><i data-lucide="shield-check" style="width:15px;height:15px;"></i></div>
                        <div class="dh-greeting">
                            <span class="dh-greeting-text">Confiedential Docs Permission</span>
                            <span class="dh-greeting-name"><?php echo htmlspecialchars($username); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="admin-page-container">
                <div class="admin-card">
                    <div class="card-head">
                        <div>
                            <h2 class="card-title">Document Access Permissions</h2>
                            <p class="card-sub">Manage active users who can upload and delete confidential documents.</p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="project-access-toolbar">
                            <label for="userSearch" style="font-weight:600;color:#475569;">Search User:</label>
                            <input type="text" id="userSearch" class="project-access-search" placeholder="Search by username, email, or role...">
                        </div>

                        <div id="projectPermissionsContainer" class="project-perm-grid">
                            <div class="empty-box">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.3rem; margin-bottom: 0.6rem;"></i>
                                <p>Loading active users permissions...</p>
                            </div>
                        </div>

                        <div class="save-wrap">
                            <button id="savePermissionsBtn" class="save-btn" type="button">
                                <i data-lucide="save" style="width:18px;height:18px;"></i>
                                Save Permissions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="toast">Changes saved successfully!</div>
    <div class="loader-overlay" id="loaderOverlay">
        <i class="fa-solid fa-spinner fa-spin" style="font-size: 3rem; color: #6366f1;"></i>
    </div>

    <script src="script.js" defer></script>
</body>
</html>
