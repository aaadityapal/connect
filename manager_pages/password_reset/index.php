<?php
session_start();
// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$username  = $_SESSION['username'] ?? 'Manager';
$user_role = $_SESSION['role'] ?? 'user';

require_once '../../config/db_connect.php';

// Fetch active users only (excluding self)
$currentUserId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id != ? AND status = 'active' ORDER BY username ASC");
$stmt->execute([$currentUserId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset | Connect</title>
    <meta name="description" content="Admin password reset tool to manage and update user account passwords securely.">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>

    <!-- Sidebar Loader -->
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
</head>
<body>

    <div class="dashboard-container">
        <!-- Sidebar mount -->
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <div class="header-title">
                    <h1>Password Reset</h1>
                    <p>Securely reset the password for any user account in the system.</p>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" id="refreshBtn" title="Refresh list">
                        <i data-lucide="refresh-cw" style="width:18px;height:18px;"></i>
                    </button>
                </div>
            </header>

            <!-- Stats -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i data-lucide="users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <div class="value" id="stat-total"><?php echo count($users); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon reset">
                        <i data-lucide="key-round"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Resets Today</h3>
                        <div class="value" id="stat-resets">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secure">
                        <i data-lucide="shield-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Protected</h3>
                        <div class="value">Active</div>
                    </div>
                </div>
            </section>

            <!-- User List + Reset Panel -->
            <div class="section-card">
                <div class="card-header">
                    <div class="card-title">User Accounts</div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <div class="filter-group search-group">
                        <div class="search-box">
                            <i data-lucide="search"></i>
                            <input type="text" id="userSearchInput" placeholder="Search by username or email...">
                        </div>
                    </div>
                    <div class="filter-group select-group">
                        <select class="filter-select" id="roleFilter">
                            <option value="All">All Roles</option>
                            <?php
                            $roles = array_unique(array_column($users, 'role'));
                            sort($roles);
                            foreach ($roles as $r) {
                                echo "<option value=\"" . htmlspecialchars($r) . "\">" . htmlspecialchars($r) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i data-lucide="users" style="width:40px;height:40px;opacity:0.3;"></i>
                                    <p>No users found.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users as $idx => $u): ?>
                            <tr class="user-row"
                                data-username="<?php echo strtolower(htmlspecialchars($u['username'])); ?>"
                                data-email="<?php echo strtolower(htmlspecialchars($u['email'])); ?>"
                                data-role="<?php echo htmlspecialchars($u['role']); ?>">
                                <td class="td-num"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar" style="background: <?php echo avatarColor($u['username']); ?>">
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="user-name"><?php echo htmlspecialchars($u['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="td-email"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><span class="role-badge role-<?php echo strtolower(preg_replace('/\s+/', '-', $u['role'])); ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                                <td>
                                    <?php if (strtolower($u['role']) === 'admin'): ?>
                                        <span class="badge-protected">
                                            <i data-lucide="shield-check" style="width:13px;height:13px;"></i>
                                            Protected
                                        </span>
                                    <?php else: ?>
                                    <button class="btn-reset-action"
                                        data-user-id="<?php echo $u['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                        data-role="<?php echo htmlspecialchars($u['role']); ?>">
                                        <i data-lucide="key-round" style="width:14px;height:14px;"></i>
                                        Reset Password
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Password Reset Modal -->
    <div id="resetModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-icon">
                    <i data-lucide="key-round" style="width:22px;height:22px;"></i>
                </div>
                <div>
                    <h2 class="modal-title" id="modalTitle">Reset Password</h2>
                    <p class="modal-subtitle" id="modalSubtitle">Set a new password for this user.</p>
                </div>
                <button class="modal-close" id="modalCloseBtn" aria-label="Close">
                    <i data-lucide="x" style="width:18px;height:18px;"></i>
                </button>
            </div>

            <form id="resetPasswordForm" autocomplete="off">
                <input type="hidden" id="targetUserId" name="user_id">

                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <div class="password-input-wrap">
                        <i data-lucide="lock" class="field-icon" style="width:16px;height:16px;"></i>
                        <input type="password" id="newPassword" name="new_password"
                            placeholder="Enter new password" autocomplete="new-password" required>
                        <button type="button" class="toggle-pw" data-target="newPassword" aria-label="Toggle visibility">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                    <div class="suggest-row">
                        <button type="button" id="suggestPwBtn" class="btn-suggest">
                            <i data-lucide="wand-2" style="width:14px;height:14px;"></i>
                            Suggest Password
                        </button>
                        <button type="button" id="copyPwBtn" class="btn-copy-pw" title="Copy suggested password" style="display:none;">
                            <i data-lucide="copy" style="width:14px;height:14px;"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="strengthBar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="strength-label" id="strengthLabel"></span>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <div class="password-input-wrap">
                        <i data-lucide="lock-keyhole" class="field-icon" style="width:16px;height:16px;"></i>
                        <input type="password" id="confirmPassword" name="confirm_password"
                            placeholder="Re-enter new password" autocomplete="new-password" required>
                        <button type="button" class="toggle-pw" data-target="confirmPassword" aria-label="Toggle visibility">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                    <span class="match-label" id="matchLabel"></span>
                </div>

                <div class="pw-requirements">
                    <p>Password must include:</p>
                    <ul>
                        <li id="req-length"><i data-lucide="circle" style="width:12px;height:12px;"></i> At least 8 characters</li>
                        <li id="req-upper"><i data-lucide="circle" style="width:12px;height:12px;"></i> One uppercase letter</li>
                        <li id="req-lower"><i data-lucide="circle" style="width:12px;height:12px;"></i> One lowercase letter</li>
                        <li id="req-num"><i data-lucide="circle" style="width:12px;height:12px;"></i> One number</li>
                    </ul>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn-confirm" id="confirmResetBtn">
                        <i data-lucide="check-circle" style="width:16px;height:16px;"></i>
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast" role="alert" aria-live="polite"></div>

    <script src="js/script.js" defer></script>

</body>
</html>
<?php
function avatarColor($name) {
    $colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#f97316','#14b8a6'];
    return $colors[ord($name[0]) % count($colors)];
}
?>
