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
require_once '../../includes/profile_completion_helper.php';

// Fetch all employees with fields needed for profile completion percentage sync
$stmt = $pdo->prepare("SELECT id, username, email, role, joining_date, status, status_changed_date, profile_completion_percent, profile_picture, phone_number, phone, dob, gender, bio, address, city, state, country, postal_code, designation, department, nationality, blood_group, marital_status, languages, skills, interests, social_media, emergency_contact, education_background, work_experiences, bank_details, documents FROM users ORDER BY username ASC");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Keep stored percentage synced with profile module logic
if (!empty($employees)) {
    $syncPctStmt = $pdo->prepare("UPDATE users SET profile_completion_percent = :pct WHERE id = :id LIMIT 1");
    foreach ($employees as $idx => $emp) {
        $computed = compute_profile_completion_percent($emp);
        $stored = isset($emp['profile_completion_percent']) ? (int)$emp['profile_completion_percent'] : -1;
        $employees[$idx]['profile_completion_percent'] = $computed;
        if ($stored !== $computed) {
            $syncPctStmt->execute([
                ':pct' => $computed,
                ':id' => (int)$emp['id'],
            ]);
        }
    }
}

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

function extractSelectOptionsFromSignup($signupContent, $selectId) {
    $options = [];
    if (!is_string($signupContent) || $signupContent === '') {
        return $options;
    }

    $pattern = '/<select[^>]*id=["\']' . preg_quote($selectId, '/') . '["\'][^>]*>(.*?)<\/select>/is';
    if (!preg_match($pattern, $signupContent, $m)) {
        return $options;
    }

    if (preg_match_all('/<option[^>]*value=["\']([^"\']*)["\'][^>]*>/i', $m[1], $matches)) {
        foreach ($matches[1] as $value) {
            $value = trim($value);
            if ($value !== '') {
                $options[] = $value;
            }
        }
    }

    return array_values(array_unique($options));
}

$signupPath = realpath(__DIR__ . '/../../signup.php');
$signupContent = ($signupPath && is_readable($signupPath)) ? file_get_contents($signupPath) : '';

$signupRoleOptions = extractSelectOptionsFromSignup($signupContent, 'role');
$signupReportingManagers = extractSelectOptionsFromSignup($signupContent, 'reporting_manager');

$addEmployeeSuccess = $_SESSION['add_employee_success'] ?? null;
if ($addEmployeeSuccess) {
    unset($_SESSION['add_employee_success']);
}

if (empty($signupRoleOptions)) {
    $signupRoleOptions = [
        'admin', 'HR', 'Senior Manager (Studio)', 'Senior Manager (Site)',
        'Senior Manager (Marketing)', 'Senior Manager (Sales)', 'Senior Manager (Purchase)',
        'Design Team', 'Working Team', 'Interior Designer', 'Senior Interior Designer',
        'Junior Interior Designer', 'Lead Interior Designer', 'Associate Interior Designer',
        'Interior Design Coordinator', 'Interior Design Assistant', 'FF&E Designer',
        'Interior Stylist', 'Interior Design Intern', 'Interior Architect', 'Fit-out Manager',
        'Architect', 'Senior Architect', 'Junior Architect', 'Project Architect',
        'Architectural Designer', 'Landscape Architect', 'Urban Planner',
        'BIM Coordinator', 'BIM Modeler', 'CAD Technician', 'Draughtsman',
        '3D Visualizer', 'Render Artist', '3D Designing Team', 'Studio Trainees',
        'Graphic Designer', 'Business Developer', 'Social Media Manager',
        'Social Media Marketing', 'Site Manager', 'Site Coordinator', 'Site Supervisor',
        'Site Engineer', 'Civil Engineer', 'Structural Engineer', 'MEP Engineer',
        'Quantity Surveyor', 'Planning Engineer', 'Estimation Engineer',
        'Construction Manager', 'Project Manager', 'QA/QC Engineer',
        'Safety Officer', 'Document Controller', 'Storekeeper', 'Site Trainee',
        'IT Manager', 'IT Administrator', 'System Administrator', 'Network Administrator',
        'IT Support Engineer', 'Helpdesk Technician', 'Software Engineer',
        'Accounts Manager', 'Accountant', 'Accounts Executive', 'Finance Manager',
        'Accounts Payable', 'Accounts Receivable', 'Payroll Executive',
        'Relationship Manager', 'Sales Manager', 'Sales Consultant',
        'Field Sales Representative', 'Purchase Manager', 'Purchase Executive', 'Sales',
        'Purchase', 'Maid Back Office'
    ];
}

if (empty($signupReportingManagers)) {
    $signupReportingManagers = [
        'Sr. Manager (Studio)',
        'Sr. Manager (Business Developer)',
        'Sr. Manager (Relationship Manager)',
        'Sr. Manager (Operations)',
        'Sr. Manager (HR)',
        'Sr. Manager (Sales)',
        'Sr. Manager (Marketing)',
        'Sr. Manager (Social Media)',
        'Sr. Manager (Purchase)',
        'Sr. Manager (Finance)',
        'Sr. Manager (Accounts)',
        'Sr. Manager (IT)',
        'Sr. Manager (Site)'
    ];
}

function avatarColor($name) {
    $colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#f97316','#14b8a6'];
    return $colors[ord($name[0]) % count($colors)];
}

function profilePictureUrl($path) {
    $raw = trim((string)$path);
    if ($raw === '') return '';
    if (preg_match('/^https?:\/\//i', $raw) || strpos($raw, '/') === 0) {
        return $raw;
    }
    $clean = preg_replace('/^(\.\/)+/', '', $raw);
    $clean = preg_replace('/^(\.\.\/)+/', '', $clean);
    $clean = ltrim($clean, '/');
    return '../../' . $clean;
}

function formatLastActiveAt($status, $statusChangedDate) {
    $raw = trim((string)$statusChangedDate);
    if ($raw === '') {
        return 'N/A';
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return htmlspecialchars($raw);
    }

    return date('d M Y, h:i A', $ts);
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
    <link rel="stylesheet" href="../add_employee_modal/modal.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>

    <!-- Sidebar Loader -->
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
        window.EMPLOYEE_ROLE_OPTIONS = <?php echo json_encode($signupRoleOptions, JSON_UNESCAPED_UNICODE); ?>;
        window.REPORTING_MANAGER_OPTIONS = <?php echo json_encode($signupReportingManagers, JSON_UNESCAPED_UNICODE); ?>;
        window.CURRENT_USER_ROLE = <?php echo json_encode($user_role, JSON_UNESCAPED_UNICODE); ?>;
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
                            $avatarInitial = strtoupper(substr($employee['username'], 0, 1));
                            $profilePicUrl = profilePictureUrl($employee['profile_picture'] ?? '');
                            $profileCompletion = isset($employee['profile_completion_percent']) ? (int)$employee['profile_completion_percent'] : 0;
                        ?>
                            <div class="employee-card <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>"
                                   data-employee-id="<?php echo (int)$employee['id']; ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($employee['username'])); ?>" 
                                 data-email="<?php echo htmlspecialchars(strtolower($employee['email'])); ?>"
                                 data-role="<?php echo htmlspecialchars(strtolower($employee['role'])); ?>"
                                   data-status="<?php echo htmlspecialchars(strtolower($employee['status'])); ?>"
                                                                     data-status-changed-at="<?php echo htmlspecialchars((string)($employee['status_changed_date'] ?? '')); ?>"
                                   data-completion="<?php echo (int)$profileCompletion; ?>">
                                <div class="card-options">
                                    <button class="btn-more"><i data-lucide="more-vertical"></i></button>
                                </div>
                                <div class="employee-header">
                                    <div class="avatar-large" style="background: <?php echo avatarColor($employee['username']); ?>">
                                        <?php if ($profilePicUrl): ?>
                                            <img src="<?php echo htmlspecialchars($profilePicUrl); ?>" alt="<?php echo htmlspecialchars($employee['username']); ?>" class="employee-avatar-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <span class="employee-avatar-fallback" style="display:none;"><?php echo $avatarInitial; ?></span>
                                        <?php else: ?>
                                            <span class="employee-avatar-fallback"><?php echo $avatarInitial; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="employee-info">
                                        <h3 class="employee-name"><?php echo htmlspecialchars($employee['username']); ?></h3>
                                        <div class="employee-meta">
                                            <span class="employee-role"><?php echo htmlspecialchars($employee['role']); ?></span>
                                            <span class="status-badge js-status-badge <?php echo $isActive ? 'active' : 'inactive'; ?>">
                                                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        <div class="status-time js-status-time">
                                            Last Active At: <?php echo formatLastActiveAt($employee['status'] ?? '', $employee['status_changed_date'] ?? ''); ?>
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
                                    <div class="profile-completion-row">
                                        <div class="profile-completion-top">
                                            <span>Profile Completion</span>
                                            <strong><?php echo $profileCompletion; ?>%</strong>
                                        </div>
                                        <div class="profile-completion-track">
                                            <div class="profile-completion-fill" style="width: <?php echo $profileCompletion; ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button class="btn-view-profile" onclick="viewProfile(<?php echo $employee['id']; ?>)">
                                        View Profile
                                    </button>
                                    <button class="btn-status-toggle <?php echo $isActive ? 'is-active' : 'is-inactive'; ?>" title="<?php echo $isActive ? 'Set Inactive' : 'Set Active'; ?>" onclick="toggleEmployeeStatus(<?php echo (int)$employee['id']; ?>, '<?php echo $isActive ? 'active' : 'inactive'; ?>', this, 'card')">
                                        <i data-lucide="power"></i>
                                        <span><?php echo $isActive ? 'Set Inactive' : 'Set Active'; ?></span>
                                    </button>
                                    <?php if ($profileCompletion < 90 && $isActive): ?>
                                        <button class="btn-icon-action btn-reminder" title="Send Profile Reminder" onclick="sendProfileReminder(<?php echo $employee['id']; ?>, null, this, 'card')">
                                            <i data-lucide="bell-ring"></i>
                                        </button>
                                    <?php endif; ?>
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

    <?php include __DIR__ . '/../add_employee_modal/modal.php'; ?>

    <?php if ($addEmployeeSuccess): ?>
        <div id="addEmployeeSuccessModal" class="ae-success-modal" aria-hidden="true">
            <div class="ae-success-modal__backdrop"></div>
            <div class="ae-success-modal__panel" role="dialog" aria-modal="true" aria-labelledby="aeSuccessTitle">
                <button type="button" class="ae-success-modal__close" data-ae-success-close="true" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
                <div class="ae-success-modal__content">
                    <h3 id="aeSuccessTitle">Employee Created</h3>
                    <p class="ae-success-modal__subtitle">Share these details with the employee.</p>
                    <div class="ae-success-modal__grid">
                        <div>
                            <span>Employee ID</span>
                            <strong><?php echo htmlspecialchars((string)$addEmployeeSuccess['unique_id']); ?></strong>
                        </div>
                        <div>
                            <span>Username</span>
                            <strong><?php echo htmlspecialchars((string)$addEmployeeSuccess['username']); ?></strong>
                        </div>
                        <div>
                            <span>Email</span>
                            <strong><?php echo htmlspecialchars((string)$addEmployeeSuccess['email']); ?></strong>
                        </div>
                        <div>
                            <span>Role</span>
                            <strong><?php echo htmlspecialchars((string)$addEmployeeSuccess['role']); ?></strong>
                        </div>
                        <div>
                            <span>Reporting Manager</span>
                            <strong><?php echo htmlspecialchars((string)($addEmployeeSuccess['reporting_manager'] ?: 'N/A')); ?></strong>
                        </div>
                        <div>
                            <span>Joining Date</span>
                            <strong><?php echo htmlspecialchars((string)($addEmployeeSuccess['joining_date'] ?: 'N/A')); ?></strong>
                        </div>
                        <div>
                            <span>Department</span>
                            <strong><?php echo htmlspecialchars((string)($addEmployeeSuccess['department'] ?: 'General')); ?></strong>
                        </div>
                        <div>
                            <span>Phone</span>
                            <strong><?php echo htmlspecialchars((string)($addEmployeeSuccess['phone'] ?: 'N/A')); ?></strong>
                        </div>
                        <div class="ae-success-modal__span">
                            <span>Temporary Password</span>
                            <strong><?php echo htmlspecialchars((string)$addEmployeeSuccess['temp_password']); ?></strong>
                        </div>
                    </div>
                    <div class="ae-success-modal__actions">
                        <button type="button" class="ae-btn ae-btn--ghost" data-ae-success-close="true">Close</button>
                        <button type="button" class="ae-btn ae-btn--primary" id="copyEmployeeDetails">Copy Details</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Notice Modal -->
    <div id="actionNoticeModal" class="notice-overlay" aria-hidden="true">
        <div class="notice-card" role="dialog" aria-modal="true" aria-labelledby="noticeTitle" aria-describedby="noticeMessage">
            <h3 id="noticeTitle">Notification</h3>
            <p id="noticeMessage">Action completed.</p>
            <div class="notice-actions">
                <button type="button" class="notice-ok-btn" id="noticeOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="script.js" defer></script>
    <script src="../add_employee_modal/modal.js" defer></script>
    <?php if ($addEmployeeSuccess): ?>
        <script>
            window.ADD_EMPLOYEE_SUCCESS = <?php echo json_encode($addEmployeeSuccess, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('addEmployeeSuccessModal');
                const copyBtn = document.getElementById('copyEmployeeDetails');

                if (!modal || !window.ADD_EMPLOYEE_SUCCESS) {
                    return;
                }

                modal.setAttribute('aria-hidden', 'false');
                modal.classList.add('is-open');

                function closeSuccessModal() {
                    modal.setAttribute('aria-hidden', 'true');
                    modal.classList.remove('is-open');
                }

                modal.addEventListener('click', function (event) {
                    const target = event.target;
                    if (target && target.closest && target.closest('[data-ae-success-close="true"]')) {
                        closeSuccessModal();
                    }
                });

                if (copyBtn) {
                    copyBtn.addEventListener('click', function () {
                        const data = window.ADD_EMPLOYEE_SUCCESS;
                        const lines = [
                            'Employee Details',
                            'Employee ID: ' + (data.unique_id || ''),
                            'Username: ' + (data.username || ''),
                            'Email: ' + (data.email || ''),
                            'Role: ' + (data.role || ''),
                            'Reporting Manager: ' + (data.reporting_manager || 'N/A'),
                            'Joining Date: ' + (data.joining_date || 'N/A'),
                            'Department: ' + (data.department || 'General'),
                            'Phone: ' + (data.phone || 'N/A'),
                            'Temporary Password: ' + (data.temp_password || '')
                        ];
                        const text = lines.join('\n');

                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(text).then(function () {
                                copyBtn.textContent = 'Copied';
                                setTimeout(function () {
                                    copyBtn.textContent = 'Copy Details';
                                }, 1600);
                            });
                        } else {
                            const textarea = document.createElement('textarea');
                            textarea.value = text;
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                            copyBtn.textContent = 'Copied';
                            setTimeout(function () {
                                copyBtn.textContent = 'Copy Details';
                            }, 1600);
                        }
                    });
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
