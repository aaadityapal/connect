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

// Fetch all employees
$stmt = $pdo->prepare("SELECT id, username, email, role, joining_date, status FROM users ORDER BY username ASC");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activeCount = 0;
$inactiveCount = 0;
$rolesMap = [];
foreach ($employees as $emp) {
    if (strtolower(trim($emp['status'])) === 'active') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if (!empty(trim($emp['role']))) {
        $r = trim($emp['role']);
        $rolesMap[strtolower($r)] = $r;
    }
}
$uniqueRoles = array_values($rolesMap);
sort($uniqueRoles);

function avatarColor($name) {
    $colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#f97316','#14b8a6'];
    return $colors[ord($name[0]) % count($colors)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Profile | Connect</title>
    <meta name="description" content="View and manage employee profiles and details.">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

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
                    <h1>Employees Profile</h1>
                    <p>Manage and view detailed information for all team members.</p>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" id="addEmployeeBtn">
                        <i data-lucide="user-plus" style="width:18px;height:18px;"></i>
                        <span>Add Employee</span>
                    </button>
                </div>
            </header>

            <!-- Stats Overview -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="users" style="width:18px;height:18px;"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Employees</h3>
                        <div class="value"><?php echo count($employees); ?></div>
                    </div>
                    <div class="stat-trend positive">
                        <i data-lucide="trending-up" style="width:14px;height:14px;"></i>
                        <span>4% inc</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="user-check" style="width:18px;height:18px;"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Active Now</h3>
                        <div class="value"><?php echo $activeCount; ?></div>
                    </div>
                    <div class="stat-trend neutral">
                        <span>On Track</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="user-minus" style="width:18px;height:18px;"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Inactive Users</h3>
                        <div class="value"><?php echo $inactiveCount; ?></div>
                    </div>
                    <div class="stat-trend negative" style="color: var(--danger);">
                        <span>Off Track</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="award" style="width:18px;height:18px;"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Roles</h3>
                        <div class="value"><?php echo count($uniqueRoles); ?></div>
                    </div>
                    <div class="stat-trend positive">
                        <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
                        <span>Diverse</span>
                    </div>
                </div>
            </section>

            <!-- Main Content Area -->
            <div class="content-section">
                <!-- Filters Bar -->
                <div class="table-controls glass-card">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" id="employeeSearch" placeholder="Search team members...">
                    </div>
                    <div class="filter-actions">
                        <select class="filter-select" id="roleFilter">
                            <option value="All">All Roles</option>
                            <?php foreach ($uniqueRoles as $roleOption): ?>
                                <option value="<?php echo htmlspecialchars($roleOption); ?>"><?php echo htmlspecialchars($roleOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn-secondary" id="exportBtn">
                            <i data-lucide="download" style="width:16px;height:16px;"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>

                <!-- Employees Grid -->
                <div class="employees-grid" id="employeesGrid">
                    <?php if (empty($employees)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i data-lucide="users"></i>
                            </div>
                            <h3>No Employees Found</h3>
                            <p>We couldn't find any employees matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): 
                            $isActive = strtolower(trim($employee['status'])) === 'active';
                        ?>
                            <div class="employee-card <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($employee['username'])); ?>" 
                                 data-email="<?php echo htmlspecialchars(strtolower($employee['email'])); ?>"
                                 data-role="<?php echo htmlspecialchars(strtolower($employee['role'])); ?>"
                                 data-status="<?php echo htmlspecialchars(strtolower($employee['status'])); ?>">
                                <div class="card-options">
                                    <button class="btn-more"><i data-lucide="more-vertical"></i></button>
                                </div>
                                <div class="employee-header">
                                    <div class="avatar-large" style="background: <?php echo avatarColor($employee['username']); ?>">
                                        <?php echo strtoupper(substr($employee['username'], 0, 1)); ?>
                                    </div>
                                    <div class="employee-info">
                                        <h3 class="employee-name"><?php echo htmlspecialchars($employee['username']); ?></h3>
                                        <div class="employee-meta">
                                            <span class="employee-role"><?php echo htmlspecialchars($employee['role']); ?></span>
                                            <span class="status-badge <?php echo $isActive ? 'active' : 'inactive'; ?>">
                                                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="employee-details">
                                    <div class="detail-item">
                                        <i data-lucide="mail"></i>
                                        <span><?php echo htmlspecialchars($employee['email']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i data-lucide="calendar"></i>
                                        <span>Joined: <?php echo $employee['joining_date'] ? date('M Y', strtotime($employee['joining_date'])) : 'Unknown'; ?></span>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button class="btn-view-profile" onclick="viewProfile(<?php echo $employee['id']; ?>)">
                                        View Profile
                                    </button>
                                    <button class="btn-icon-action" title="Send Message">
                                        <i data-lucide="message-square"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Profile Detail Modal -->
    <div id="profileModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalEmployeeName">Employee Profile</h2>
                <button class="btn-close" id="closeModal"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" id="profileBody">
                <!-- Profile details will be loaded here -->
                <div class="loader-container">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="script.js" defer></script>
</body>
</html>
