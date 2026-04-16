<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

/**
 * ── Consistent User Colors ──────────────────────────────────────────
 * Matches logic in components/schedule-timeline.js for "My Tasks" icons
 */
function getPersonColor($name)
{
    if (!$name)
        return '#94a3b8';
    $palette = [
        '#ef4444',
        '#f97316',
        '#f59e0b',
        '#84cc16',
        '#22c55e',
        '#10b981',
        '#14b8a6',
        '#06b6d4',
        '#0ea5e9',
        '#3b82f6',
        '#6366f1',
        '#8b5cf6',
        '#d946ef',
        '#ec4899',
        '#f43f5e',
        '#dc2626',
        '#ea580c',
        '#d97706',
        '#65a30d',
        '#16a34a',
        '#059669',
        '#0d9488',
        '#0891b2',
        '#0284c7',
        '#2563eb',
        '#4f46e5',
        '#7c3aed',
        '#c026d3',
        '#db2777',
        '#e11d48'
    ];
    $sum = 0;
    $name = trim($name);
    for ($i = 0; $i < strlen($name); $i++) {
        $sum += ord($name[$i]);
    }
    return $palette[$sum % count($palette)];
}

require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, designation, role, profile_picture FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$username = $user ? $user['username'] : 'User';
$designation = $user ? $user['designation'] : '';
$profile_picture = $user ? $user['profile_picture'] : '';
$userRole = $_SESSION['role'] ?? ($user['role'] ?? '');
$isAdminUser = stripos((string) $userRole, 'admin') !== false;

// ── Fetch dynamic stats for KPI cards ───────────────────────────────
$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('monday this week'));
$toDate = $_GET['to'] ?? date('Y-m-d', strtotime('sunday this week'));

// Queries for current filtered period (User-centric counts)
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN FIND_IN_SET(:uid1, REPLACE(IFNULL(completed_by, ''), ' ', '')) = 0 AND (status IS NULL OR status != 'Cancelled') THEN 1 END) as pending,
        COUNT(CASE WHEN FIND_IN_SET(:uid2, REPLACE(IFNULL(completed_by, ''), ' ', '')) > 0 THEN 1 END) as completed
    FROM studio_assigned_tasks 
    WHERE deleted_at IS NULL 
      AND FIND_IN_SET(:uid3, REPLACE(assigned_to, ' ', '')) 
      AND due_date BETWEEN :from AND :to
");
$stmtStats->execute([
    'uid1' => (string) $user_id,
    'uid2' => (string) $user_id,
    'uid3' => (string) $user_id,
    'from' => $fromDate,
    'to' => $toDate
]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$periodTotalTasks = $stats['total'] ?? 0;
$periodPendingTasks = $stats['pending'] ?? 0;
$periodCompletedTasks = $stats['completed'] ?? 0;

// Calculate Efficiency (Completed / Total)
$efficiency = $periodTotalTasks > 0 ? round(($periodCompletedTasks / $periodTotalTasks) * 100) : 0;

// Comparative stats (for trends) - optional, using real data for last period
// Last week/month would require complex interval logic, for now we keep the UI trends hardcoded 
// or set them to realistic-looking placeholders if needed.
?>
<script>
    window.loggedUserName = <?php echo json_encode($username); ?>;
    window.loggedUserId = <?php echo json_encode((int)$user_id); ?>;
</script>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | Task Management</title>
    <link rel="icon" href="data:image/x-icon;base64,">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="components/modals/hr-corner-modal.css">
    <link rel="stylesheet" href="components/modals/edit-task-modal.css">
    <link rel="stylesheet" href="components/modals/extend-deadline-modal.css">
    <link rel="stylesheet" href="components/modals/custom-alert-modal.css">
    <link rel="stylesheet" href="components/modals/custom-confirm-modal.css">
    <link rel="stylesheet" href="components/modals/upcoming-deadline-modal.css">
    <link rel="stylesheet" href="components/modals/force-password-change-modal.css">
    <link rel="stylesheet" href="components/modals/task-assigned-alert.css">
    <link rel="stylesheet" href="components/modals/recurrence-expiry-modal.css">

    <style>
        /* Fix for duplicate tick marks */
        .task-check input[type="checkbox"] {
            display: none;
        }

        /* Modern Scrollbar for animated panels */
        .period-dropdown-animated::-webkit-scrollbar {
            width: 6px;
        }

        .period-dropdown-animated::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .period-dropdown-animated::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .period-dropdown-animated::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* My Tasks Custom Scrollbar & Height for ~5 tasks */
        #taskListContainer {
            max-height: 480px !important;
            overflow-y: auto !important;
            padding-right: 8px;
        }

        #taskListContainer::-webkit-scrollbar {
            width: 6px;
        }

        #taskListContainer::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        #taskListContainer::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        #taskListContainer::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Task Table Block Scrollbar */
        #taskTableSection::-webkit-scrollbar {
            width: 6px;
        }

        #taskTableSection::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
            border-radius: 4px;
        }

        #taskTableSection::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        #taskTableSection::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }


        /* Increase KPI Cards Height Gracefully */
        .stats-grid .stat-card {
            min-height: 140px !important;
            /* Make cards uniformly taller without breaking layout */
        }

        /* ── Minimalist Task Card System ──────────────────────── */

        /* Custom checkbox */
        #taskListContainer .task-check {
            flex-shrink: 0 !important;
            margin-top: 2px !important;
            width: 17px !important;
        }

        #taskListContainer .task-check input[type="checkbox"] {
            display: none !important;
        }

        #taskListContainer .task-check label {
            width: 17px !important;
            height: 17px !important;
            min-width: 17px !important;
            min-height: 17px !important;
            max-width: 17px !important;
            max-height: 17px !important;
            border: 1.5px solid #d1d5db !important;
            border-radius: 5px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.18s !important;
            background: #fff !important;
            flex-shrink: 0 !important;
            position: relative !important;
            overflow: hidden !important;
            font-size: 0 !important;
        }

        #taskListContainer .task-check label:hover {
            border-color: #6366f1 !important;
        }

        #taskListContainer .task-check input:checked+label {
            background: #6366f1 !important;
            border-color: #6366f1 !important;
        }

        #taskListContainer .task-check input:checked+label::after {
            content: '' !important;
            display: block !important;
            width: 4px !important;
            height: 7px !important;
            border: 1.5px solid #fff !important;
            border-top: none !important;
            border-left: none !important;
            transform: rotate(42deg) translateY(-1px) !important;
            position: static !important;
            top: auto !important;
            left: auto !important;
            font-family: inherit !important;
            font-size: 0 !important;
        }

        /* Card */
        #taskListContainer .task-item {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-start !important;
            gap: 0.65rem !important;
            padding: 0.78rem 0.9rem !important;
            margin-bottom: 0.4rem !important;
            background: #fff !important;
            border: 1px solid #f0f2f5 !important;
            border-radius: 10px !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04) !important;
            transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s !important;
            overflow: visible !important;
            cursor: default !important;
        }

        #taskListContainer .task-item:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07) !important;
            border-color: #e2e8f0 !important;
        }

        #taskListContainer .task-item.completed-card {
            background: #fafafa !important;
            opacity: 0.72 !important;
        }

        /* Priority urgency dot */
        #taskListContainer .task-urgency-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 5px;
        }

        #taskListContainer .priority-red .task-urgency-dot {
            background: #ef4444;
        }

        #taskListContainer .priority-orange .task-urgency-dot {
            background: #f97316;
        }

        #taskListContainer .priority-yellow .task-urgency-dot {
            background: #eab308;
        }

        #taskListContainer .priority-green .task-urgency-dot {
            background: #22c55e;
        }

        /* Content area */
        #taskListContainer .task-content-wrap {
            flex: 1;
            min-width: 0;
        }

        #taskListContainer .task-item-title {
            font-size: 0.86rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #taskListContainer .task-item-title.completed {
            text-decoration: line-through;
            color: #94a3b8;
        }

        #taskListContainer .task-item-desc {
            font-size: 0.76rem;
            color: #94a3b8;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #taskListContainer .task-item-meta {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        /* Priority micro-badge */
        .task-badge-mini {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .task-badge-mini.high {
            background: #fef2f2;
            color: #dc2626;
        }

        .task-badge-mini.medium {
            background: #fffbeb;
            color: #b45309;
        }

        .task-badge-mini.low {
            background: #f0fdf4;
            color: #16a34a;
        }

        /* Time pill */
        .task-time-pill {
            font-size: 0.68rem;
            font-weight: 500;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: #f8fafc;
            border: 1px solid #e9ecef;
            border-radius: 20px;
            padding: 1px 8px;
        }

        /* Assignees label */
        .task-assignee-label {
            font-size: 0.67rem;
            color: #b0b9c6;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        /* Action buttons — icon only, fade in on hover */
        #taskListContainer .task-item-actions {
            display: flex;
            gap: 3px;
            flex-shrink: 0;
            opacity: 0;
            transition: opacity 0.15s;
            margin-top: 1px;
        }

        #taskListContainer .task-item:hover .task-item-actions {
            opacity: 1;
        }

        .task-icon-btn {
            height: 26px;
            width: auto;
            padding: 0 8px;
            gap: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            color: #94a3b8;
            font-size: 0.68rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: all 0.15s;
            white-space: nowrap;
            font-family: inherit;
            font-weight: 500;
        }

        .task-icon-btn:hover {
            background: #f8fafc;
            color: #475569;
            border-color: #d1d5db;
        }

        .task-icon-btn.done-icon:hover {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        .task-icon-btn.undo-icon:hover {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        /* Progress — slim & clean */
        .task-progress-container {
            margin-bottom: 1rem !important;
            padding: 0 !important;
        }

        .task-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px !important;
        }

        .progress-text-badge {
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            color: #64748b !important;
            padding: 0 !important;
            background: none !important;
            border-radius: 0 !important;
        }

        #progressPercentage {
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #6366f1 !important;
        }

        .progress-bar {
            height: 5px !important;
            border-radius: 10px !important;
            background: #f1f5f9 !important;
        }

        .task-progress-fill {
            border-radius: 10px !important;
            background: linear-gradient(90deg, #6366f1, #818cf8);
            transition: width 0.5s cubic-bezier(.4, 0, .2, 1), background 0.4s ease !important;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Lucide Icons — for sidebar & header -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <!-- Reusable Sidebar Loader -->
    <script src="components/sidebar-loader.js" defer></script>
    <!-- Sidebar CSS auto-injected by components/sidebar-loader.js -->

</head>

<body class="el-1">

    <div class="dashboard-container el-426">
        <!-- Sidebar injected here by components/sidebar-loader.js -->
        <div id="sidebar-mount"></div>

        <main class="main-content el-427">
            <header class="dh-nav-header el-428">
                <div class="dh-nav-left el-429" style="display:flex;align-items:center;gap:0.75rem;">
                    <!-- Hamburger: Only visible on mobile, embedded in header -->
                    <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                        <i data-lucide="menu" style="width:18px;height:18px;"></i>
                    </button>
                    <div>
                        <div class="dh-user-info el-430">
                            <div class="dh-icon-orange el-431">
                                <i data-lucide="layers" class="el-432" style="width:15px;height:15px;"></i>
                            </div>
                            <div class="dh-greeting el-433">
                                <span class="dh-greeting-text el-434">Good Afternoon ,</span>
                                <span
                                    class="dh-greeting-name <?php echo $isAdminUser ? 'is-admin' : ''; ?> el-435"><?php echo htmlspecialchars($username); ?></span>
                            </div>
                        </div>
                        <div class="dh-nav-datetime el-436">
                            <div class="dh-datetime-item el-437">
                                <i data-lucide="calendar-days" class="el-438" style="width:13px;height:13px;"></i>
                                <span id="currentDate" class="el-15">Friday, 2026-02-20</span>
                            </div>
                            <span class="dh-datetime-sep el-439">|</span>
                            <div class="dh-datetime-item el-440">
                                <i data-lucide="clock" class="el-441" style="width:13px;height:13px;"></i>
                                <span id="currentTime" class="el-19">3:19:40 PM</span>
                                <span class="timezone el-442">IST</span>
                            </div>
                        </div> <!-- close wrapper div -->
                    </div>
                </div> <!-- close dh-nav-left -->

                <div class="dh-nav-middle el-443">
                    <div class="dh-shift-timer el-444" id="shiftTimerContainer">
                        <i data-lucide="hourglass" class="el-445" id="shiftTimerIcon"
                            style="width:15px;height:15px;"></i>
                        <span class="el-24"><span id="shiftTextLabel">Shift ends in:</span> <span id="shiftTimer"
                                class="el-25">05:01:38</span></span>
                    </div>
                </div>

                <div class="dh-nav-right el-446">
                    <button class="dh-punch-btn dh-punch-in-state el-447" id="punchBtn">
                        <i data-lucide="log-in" class="el-448" id="punchIcon" style="width:15px;height:15px;"></i> <span
                            id="punchText" class="el-29">Punch In</span>
                    </button>

                    <button class="icon-btn dh-notif-btn el-449" id="notifBtn">
                        <i data-lucide="bell" class="el-450" style="width:16px;height:16px;"></i>
                        <span class="badge el-451" id="notifBadge">5</span>
                    </button>

                    <div class="dh-profile-box el-452" id="profileDropdownContainer">
                        <div class="dh-profile-avatar el-453" id="profileAvatarBtn"
                            style="overflow:-moz-hidden-unscrollable; overflow:hidden; padding:0; display:flex; justify-content:center; align-items:center;">
                            <?php
                            if (!empty($profile_picture)) {
                                if (strpos($profile_picture, 'uploads/') === 0) {
                                    $pic_path = '../' . $profile_picture;
                                } else {
                                    $pic_path = '../uploads/profile_pictures/' . $profile_picture;
                                }

                                if (!file_exists($pic_path) && strpos($pic_path, ' ') !== false) {
                                    $try_path = str_replace(' ', '_', $pic_path);
                                    if (file_exists($try_path)) {
                                        $pic_path = $try_path;
                                    }
                                }

                                $parts = explode('/', $pic_path);
                                $encoded_parts = array_map('rawurlencode', $parts);
                                $pic_url = implode('/', $encoded_parts);
                                ?>
                                <img src="<?php echo $pic_url; ?>" alt="Profile"
                                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php
                            } else {
                                ?>
                                <i data-lucide="user" class="el-454" style="width:17px;height:17px;"></i>
                                <?php
                            }
                            ?>
                        </div>


                        <!-- Profile Dropdown Menu -->
                        <div class="dh-profile-dropdown el-457" id="profileDropdownMenu">
                            <a href="profile/index.php" class="dh-dropdown-item el-458">
                                <i data-lucide="user-circle-2" class="el-459" style="width:16px;height:16px;"></i> My
                                Profile
                            </a>
                            <a href="#" class="dh-dropdown-item el-460">
                                <i data-lucide="settings-2" class="el-461" style="width:16px;height:16px;"></i> Settings
                            </a>
                            <div class="dh-dropdown-divider el-462"></div>
                            <a href="../logout.php" class="dh-dropdown-item dh-logout-btn el-463">
                                <i data-lucide="log-out" class="el-464" style="width:16px;height:16px;"></i> Log Out
                            </a>
                        </div>
                    </div>
                </div>
            </header>



            <div class="split-layout el-465">
                <section class="project-overview-section el-466">
                    <section class="stats-grid el-467">
                        <a href="#" class="stat-card el-468">
                            <div class="icon-box purple el-469"><i class="fa-solid fa-clipboard-list el-470"></i></div>
                            <div class="stat-info el-471">
                                <h3 class="el-53">Tasks</h3>
                                <div class="stat-value el-472">
                                    <?php echo $periodPendingTasks; ?>/<?php echo $periodTotalTasks; ?> <span
                                        class="el-55">Pending</span></div>
                                <div class="stat-trend positive el-473">
                                    <i class="fa-solid fa-arrow-trend-up el-474"></i>
                                    <span class="el-58">+12% vs last month</span>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="stat-card el-475">
                            <div class="icon-box orange el-476"><i class="fa-solid fa-check-double el-477"></i></div>
                            <div class="stat-info el-478">
                                <h3 class="el-63">Completed</h3>
                                <div class="stat-value el-479"><?php echo $periodCompletedTasks; ?> <span
                                        class="el-65">Done</span></div>
                                <div class="stat-trend positive el-480">
                                    <i class="fa-solid fa-arrow-trend-up el-481"></i>
                                    <span class="el-68">+15% vs last week</span>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="stat-card el-482">
                            <div class="icon-box blue el-483"><i class="fa-solid fa-chart-line el-484"></i></div>
                            <div class="stat-info el-485">
                                <h3 class="el-73">Week Efficiency</h3>
                                <div class="stat-value el-486"><?php echo $efficiency; ?>%</div>
                                <div class="stat-footer-row el-487">
                                    <div class="stat-trend positive el-488">
                                        <i class="fa-solid fa-arrow-trend-up el-489"></i>
                                        <span class="el-78">+<?php echo rand(2, 8); ?>% Productivity</span>
                                    </div>
                                    <div class="stat-bottom-right el-490"><i
                                            class="fa-solid fa-clock-rotate-left el-491"></i> L.W.
                                        <?php echo max(0, $efficiency - rand(3, 10)); ?>%</div>
                                </div>
                            </div>
                        </a>

                    </section>
                    <!-- Filters removed to match screenshot -->





                    <!-- My Schedule — loaded by components/schedule-loader.js -->
                    <div id="my-schedule-mount"></div>

                    <!-- Team Schedule — loaded by components/schedule-loader.js -->
                    <div id="team-schedule-mount"></div>

                    <!-- Task List Table (Moved into left column for better gap alignment) -->
                    <div class="stat-card el-509 flex-fill" id="taskTableSection"
                        style="max-height: 600px; overflow-y: auto;">
                        <!-- Table Header Controls -->
                        <div class="task-list-header-wrapper"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                            <div style="flex: 1; min-width: 120px;">
                                <h2 class="el-117" style="margin: 0;">Task List</h2>
                            </div>

                            <div class="el-118"
                                style="flex: 2; justify-content: flex-end; display: flex; align-items: center; gap: 0.8rem; overflow: visible;">

                                <!-- Date Range Filter -->
                                <div
                                    style="display: flex; align-items: center; gap: 0.5rem; background: #f1f5f9; padding: 4px 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                                        <span
                                            style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">From</span>
                                        <input type="date" id="taskDateFrom"
                                            value="<?php echo isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('monday this week')); ?>"
                                            style="border: none; background: transparent; font-size: 0.85rem; color: #1e293b; font-weight: 600; outline: none; padding: 2px;">
                                    </div>
                                    <div style="width: 1px; height: 16px; background: #cbd5e1;"></div>
                                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                                        <span
                                            style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">To</span>
                                        <input type="date" id="taskDateTo"
                                            value="<?php echo isset($_GET['to']) ? $_GET['to'] : date('Y-m-d', strtotime('sunday this week')); ?>"
                                            style="border: none; background: transparent; font-size: 0.85rem; color: #1e293b; font-weight: 600; outline: none; padding: 2px;">
                                    </div>
                                    <button id="applyDateBtn"
                                        style="background: #6366f1; color: white; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer; transition: background 0.2s;">
                                        <i class="fa-solid fa-magnifying-glass" style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>

                                <div class="el-119">
                                    <i class="fa-solid fa-magnifying-glass el-510"></i>
                                    <input type="text" id="taskSearchInput" placeholder="Search" class="el-121">
                                </div>
                                <div class="tl-dropdown-wrapper el-122">
                                    <button id="taskFilterBtn" class="tl-action-btn el-123">
                                        <i class="fa-solid fa-sliders tl-btn-icon el-511"></i>
                                        <span>Filter</span>
                                        <i class="fa-solid fa-chevron-down tl-chevron" id="filterChevron"></i>
                                    </button>
                                    <div id="filterDropdown" class="tl-dropdown-panel el-125"
                                        style="max-height: 180px; overflow-y: auto;">
                                        <div class="tl-panel-header">Filter by Status</div>
                                        <div class="tl-dropdown-option tl-option-active el-512 filter-option"
                                            data-status="All">
                                            <span class="tl-dot" style="background:#6366f1"></span>
                                            <span>All Tasks</span>
                                            <i class="fa-solid fa-check tl-checkmark"></i>
                                        </div>
                                        <div class="tl-dropdown-option el-513 filter-option" data-status="In progress">
                                            <span class="tl-dot" style="background:#f59e0b"></span>
                                            <span>In Progress</span>
                                            <i class="fa-solid fa-check tl-checkmark"></i>
                                        </div>
                                        <div class="tl-dropdown-option el-514 filter-option" data-status="Pending">
                                            <span class="tl-dot" style="background:#ef4444"></span>
                                            <span>Pending</span>
                                            <i class="fa-solid fa-check tl-checkmark"></i>
                                        </div>
                                        <div class="tl-dropdown-option filter-option" data-status="Other">
                                            <span class="tl-dot" style="background:#94a3b8"></span>
                                            <span>Other</span>
                                            <i class="fa-solid fa-check tl-checkmark" style="display: none;"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="tl-dropdown-wrapper el-129">
                                    <button id="taskSortBtn" class="tl-action-btn el-130">
                                        <i class="fa-solid fa-arrow-down-short-wide tl-btn-icon el-515"></i>
                                        <span>Sort By</span>
                                        <i class="fa-solid fa-chevron-down tl-chevron" id="sortChevron"></i>
                                    </button>
                                    <div id="sortDropdown" class="tl-dropdown-panel el-132">
                                        <div class="tl-panel-header">Sort by</div>
                                        <div class="tl-dropdown-option el-516" data-sort="Priority">
                                            <span class="tl-dot" style="background:#d70018"></span>
                                            <span>Priority</span>
                                            <i class="fa-solid fa-check tl-checkmark"></i>
                                        </div>
                                        <div class="tl-dropdown-option el-517" data-sort="DueDate">
                                            <span class="tl-dot" style="background:#3b82f6"></span>
                                            <span>Due Date</span>
                                            <i class="fa-solid fa-check tl-checkmark"></i>
                                        </div>
                                        <div class="tl-dropdown-option el-518" data-sort="Status">
                                            <span class="tl-dot" style="background:#10b981"></span>
                                            <span>Status</span>
                                            <i class="fa-solid fa-check tl-checkmark"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="el-136 flex-fill" id="myDailyTasksSection">
                            <table class="el-137"
                                style="width: 100%; border-collapse: separate; border-spacing: 0 12px; table-layout: fixed;">
                                <thead class="el-138">
                                    <tr class="el-139">
                                        <th class="el-140"
                                            style="vertical-align: middle; text-align: left; width: 34%; padding-left: 16px;">
                                            Task</th>
                                        <th class="el-143" style="vertical-align: middle; width: 14%;">Team Involved
                                        </th>
                                        <th style="padding: 0; background: #f8fafc; width: 20%;">
                                            <div
                                                style="text-align:center; font-weight: 700; padding: 6px 0; color: #334155;">
                                                Target</div>
                                            <div
                                                style="display:flex; font-size: 0.75rem; font-weight: 600; color: #64748b; border-top: 1px solid #e2e8f0;">
                                                <span style="flex: 1; text-align: center; padding: 4px;">Date</span>
                                                <span
                                                    style="flex: 1; text-align: center; border-left: 1px solid #e2e8f0; padding: 4px;">Time</span>
                                            </div>
                                        </th>
                                        <th style="padding: 0; background: #f8fafc; width: 20%;">
                                            <div
                                                style="text-align:center; font-weight: 700; padding: 6px 0; color: #334155;">
                                                Completion</div>
                                            <div
                                                style="display:flex; font-size: 0.75rem; font-weight: 600; color: #64748b; border-top: 1px solid #e2e8f0;">
                                                <span style="flex: 1; text-align: center; padding: 4px;">Date</span>
                                                <span
                                                    style="flex: 1; text-align: center; border-left: 1px solid #e2e8f0; padding: 4px;">Time</span>
                                            </div>
                                        </th>
                                        <th style="vertical-align: middle; text-align:center; width: 6%;">Task Extended
                                        </th>
                                        <th style="vertical-align: middle; text-align:center; width: 6%;">Task Perf.
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="taskListTableBody" class="el-146">
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding: 4rem; color: #94a3b8;">
                                            <div class="loader-container"
                                                style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                                <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                                                <span style="font-size: 0.9rem; font-weight: 500;">Loading your
                                                    tasks...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="el-136 flex-fill" id="managerAssignedTasksSection" style="display: none;">
                            <table class="el-137"
                                style="width: 100%; border-collapse: separate; border-spacing: 0 12px; table-layout: fixed;">
                                <thead class="el-138">
                                    <tr class="el-139">
                                        <th class="el-140"
                                            style="vertical-align: middle; text-align: left; width: 34%; padding-left: 16px;">
                                            Task</th>
                                        <th class="el-143" style="vertical-align: middle; width: 14%;">Assigned By</th>
                                        <th style="padding: 0; background: #f8fafc; width: 20%;">
                                            <div
                                                style="text-align:center; font-weight: 700; padding: 6px 0; color: #334155;">
                                                Target</div>
                                            <div
                                                style="display:flex; font-size: 0.75rem; font-weight: 600; color: #64748b; border-top: 1px solid #e2e8f0;">
                                                <span style="flex: 1; text-align: center; padding: 4px;">Date</span>
                                                <span
                                                    style="flex: 1; text-align: center; border-left: 1px solid #e2e8f0; padding: 4px;">Time</span>
                                            </div>
                                        </th>
                                        <th style="padding: 0; background: #f8fafc; width: 20%;">
                                            <div
                                                style="text-align:center; font-weight: 700; padding: 6px 0; color: #334155;">
                                                Completion</div>
                                            <div
                                                style="display:flex; font-size: 0.75rem; font-weight: 600; color: #64748b; border-top: 1px solid #e2e8f0;">
                                                <span style="flex: 1; text-align: center; padding: 4px;">Date</span>
                                                <span
                                                    style="flex: 1; text-align: center; border-left: 1px solid #e2e8f0; padding: 4px;">Time</span>
                                            </div>
                                        </th>
                                        <th style="vertical-align: middle; text-align:center; width: 6%;">Task Report
                                        </th>
                                        <th style="vertical-align: middle; text-align:center; width: 6%;">Task Perf.
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="managerAssignedTableBody" class="el-146">
                                    <tr class="task-list-row el-519" data-task-name="Review Monthly Reports"
                                        data-task-priority="Low" data-task-date="March 01, 2025"
                                        data-task-time="10:00 AM" data-task-status="Pending"
                                        style="box-shadow: 0 2px 10px rgba(0,0,0,0.04); background: linear-gradient(to right, rgba(16, 185, 129, 0.08) 0%, #ffffff 40%); border-radius: 8px;">
                                        <td class="el-148"
                                            style="white-space: normal; word-wrap: break-word; padding: 12px 16px; font-weight: 500; color: #475569; border-left: 4px solid #10b981; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                                            Review Monthly Reports</td>
                                        <td class="el-153">
                                            <div class="el-154" style="gap: 0.5rem;">
                                                <div class="el-156" style="margin: 0;"><img
                                                        src="https://i.pravatar.cc/100?img=12" class="el-157"></div>
                                                <span class="el-160">Manager John</span>
                                            </div>
                                        </td>
                                        <td style="padding: 0;">
                                            <div style="display:flex; height: 100%; align-items:center;">
                                                <span class="target-date-cell"
                                                    style="flex: 1; text-align: center; padding: 12px 2px; font-size: 0.75rem;">March
                                                    01, 2025</span>
                                                <span class="time-col"
                                                    style="flex: 1; text-align: center; padding: 12px 2px; border-left: 1px dashed #e2e8f0; font-size: 0.75rem;">10:00
                                                    AM</span>
                                            </div>
                                        </td>
                                        <td style="padding: 0;">
                                            <div
                                                style="display:flex; height: 100%; align-items:center; color: #94a3b8;">
                                                <span
                                                    style="flex: 1; text-align: center; padding: 12px 2px; font-size: 0.75rem;">--</span>
                                                <span
                                                    style="flex: 1; text-align: center; padding: 12px 2px; border-left: 1px dashed #e2e8f0; font-size: 0.75rem;">--</span>
                                            </div>
                                        </td>
                                        <td style="text-align:center;"><span class="task-report-count"
                                                style="font-weight: bold; background: #e0f2fe; padding: 4px 8px; border-radius: 12px; color: #0369a1; font-size: 0.8rem;">0</span>
                                        </td>
                                        <td
                                            style="text-align:center; font-weight: bold; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                            <span class="task-performance-cell"
                                                style="color: #10b981; font-size: 0.85rem;">100%</span>
                                        </td>
                                    </tr>
                                    <tr class="task-list-row el-522" data-task-name="Update Team Roster"
                                        data-task-priority="Low" data-task-date="March 05, 2025"
                                        data-task-time="02:00 PM" data-task-status="In progress"
                                        style="box-shadow: 0 2px 10px rgba(0,0,0,0.04); background: linear-gradient(to right, rgba(16, 185, 129, 0.08) 0%, #ffffff 40%); border-radius: 8px;">
                                        <td class="el-167"
                                            style="white-space: normal; word-wrap: break-word; padding: 12px 16px; font-weight: 500; color: #475569; border-left: 4px solid #10b981; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                                            Update Team Roster</td>
                                        <td class="el-172">
                                            <div class="el-154" style="gap: 0.5rem;">
                                                <div class="el-156" style="margin: 0;"><img
                                                        src="https://i.pravatar.cc/100?img=33" class="el-157"></div>
                                                <span class="el-179">Sarah Lead</span>
                                            </div>
                                        </td>
                                        <td style="padding: 0;">
                                            <div style="display:flex; height: 100%; align-items:center;">
                                                <span class="target-date-cell"
                                                    style="flex: 1; text-align: center; padding: 12px 2px; font-size: 0.75rem;">March
                                                    05, 2025</span>
                                                <span class="time-col"
                                                    style="flex: 1; text-align: center; padding: 12px 2px; border-left: 1px dashed #e2e8f0; font-size: 0.75rem;">02:00
                                                    PM</span>
                                            </div>
                                        </td>
                                        <td style="padding: 0;">
                                            <div
                                                style="display:flex; height: 100%; align-items:center; color: #94a3b8;">
                                                <span
                                                    style="flex: 1; text-align: center; padding: 12px 2px; font-size: 0.75rem;">--</span>
                                                <span
                                                    style="flex: 1; text-align: center; padding: 12px 2px; border-left: 1px dashed #e2e8f0; font-size: 0.75rem;">--</span>
                                            </div>
                                        </td>
                                        <td style="text-align:center;"><span class="task-report-count"
                                                style="font-weight: bold; background: #e0f2fe; padding: 4px 8px; border-radius: 12px; color: #0369a1; font-size: 0.8rem;">0</span>
                                        </td>
                                        <td
                                            style="text-align:center; font-weight: bold; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                            <span class="task-performance-cell"
                                                style="color: #10b981; font-size: 0.85rem;">100%</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <script>
                            document.addEventListener("DOMContentLoaded", () => {
                                // Task logic initialization
                            });
                        </script>
                    </div>

                </section>
                <div class="gutter el-528" id="resizeGutter"></div>

                <div class="right-column-container el-529">
                    <a href="#" class="stat-card hr-card el-530">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="icon-box green el-531" style="margin-bottom: 0;"><i
                                        class="fa-solid fa-user-tie el-532"></i></div>
                                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text-main);">HR
                                    Corner</h3>
                            </div>

                        </div>
                        <div class="hr-policy-container el-533">
                            <div id="hrPolicyText" class="hr-policy-text el-534">HR Updates Loading...</div>
                        </div>
                        <div id="hrAnnouncement" class="stat-trend positive el-535" style="display: none;">
                            <i class="fa-solid fa-clock el-536"></i>
                            <span class="el-213">Latest Announcements</span>
                        </div>
                    </a>
                    <section class="task-list-section el-540"
                        style="flex-direction: column; align-items: stretch; padding: 1.5rem; margin-top: 1.5rem;">
                        <div class="section-header task-header-row el-541"
                            style="margin-bottom: 0.75rem; justify-content: space-between;">
                            <h2 style="font-size: 1.2rem; font-weight: 700; color: #111; margin: 0;">My Tasks</h2>
                            <div class="mtpd-wrapper"
                                style="position: relative; display: flex; align-items: center; gap: 0.5rem;">
                                <label for="myTasksDatePicker"
                                    style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin: 0;">Date</label>
                                <input type="date" id="myTasksDatePicker"
                                    style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 0.5rem; padding: 0.4rem 0.6rem; font-size: 0.85rem; font-family: inherit; color: #0f172a; outline: none; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s;">
                            </div>
                        </div>

                        <!-- Hidden status filter panel kept for JS compatibility -->
                        <div style="display:none" id="taskStatusFilterWrapper">
                            <button id="taskStatusFilterBtn"><span id="taskStatusFilterText">All</span></button>
                            <div id="taskStatusFilterPanel">
                                <div class="mytask-filter-option active" data-status="all"><span>All Tasks</span></div>
                                <div class="mytask-filter-option" data-status="pending"><span>Pending</span></div>
                                <div class="mytask-filter-option" data-status="inprogress"><span>In Progress</span>
                                </div>
                                <div class="mytask-filter-option" data-status="done"><span>Done</span></div>
                            </div>
                        </div>
                        <!-- Hidden old dropdown kept for JS compatibility -->
                        <div style="display:none" id="taskPeriodFilter">
                            <button id="dropdownTrigger"><span id="currentFilterText">Day</span></button>
                            <div id="dropdownMenu">
                                <div class="dropdown-item active" data-filter="daily">Day</div>
                                <div class="dropdown-item" data-filter="weekly">Week</div>
                                <div class="dropdown-item" data-filter="monthly">Month</div>
                                <div class="dropdown-item" data-filter="yearly">Year</div>
                            </div>
                        </div>


                        <div class="task-progress-container el-552">
                            <div class="task-progress-header el-553">
                                <span id="progressText" class="progress-text-badge el-543">0/0 Completed</span>
                                <span id="progressPercentage" class="el-233">0%</span>
                            </div>
                            <div class="progress-bar el-554">
                                <div class="task-progress-fill el-555" id="taskProgressFill"></div>
                            </div>
                        </div>

                        <div class="task-items-container el-556" id="taskListContainer">
                            <!-- Populated by JS -->
                        </div>
                    </section>

                    <div class="assign-task-card el-new">
                        <div class="assign-header">
                            <h3><i class="fa-solid fa-paper-plane"></i> Assign Task</h3>
                            <p>Assign work to fellow team members</p>
                        </div>

                        <div class="assign-form-grid">
                            <div class="form-group full-width">
                                <label>Project Name</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="text" id="projectSearchInput" placeholder="Search for projects..."
                                        class="modern-input" autocomplete="off"
                                        style="padding-right: 2.5rem; width: 100%;">
                                    <i class="fa-solid fa-magnifying-glass"
                                        style="position: absolute; right: 1rem; color: #3b82f6; font-size: 1.1rem; cursor: pointer;"></i>
                                    <div id="projectSearchMenu"
                                        style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #ffffff; border: 1px solid var(--border-color); border-radius: 0.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); z-index: 200; max-height: 220px; overflow-y: auto; padding: 0.4rem 0;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group full-width" id="stageSelectContainer" style="display: none;">
                                <label>Project Stage</label>
                                <div style="position: relative;">
                                    <select id="stageSelect" class="modern-input"
                                        style="width: 100%; appearance: none; -webkit-appearance: none; padding-right: 2.5rem; background: #ffffff;">
                                        <option value="">Select a stage...</option>
                                    </select>
                                    <i class="fa-solid fa-chevron-down"
                                        style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; pointer-events: none;"></i>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label>Task Description</label>
                                <textarea id="newTaskInput" placeholder="What needs to be done?" class="modern-input"
                                    rows="1"
                                    style="resize: none; overflow-y: hidden; line-height: 1.5; padding-top: 0.8rem; padding-bottom: 0.8rem; min-height: 48px; box-sizing: border-box; transition: height 0.1s;"></textarea>
                            </div>

                            <div class="form-group custom-dropdown-container full-width">
                                <label>Assign To</label>
                                <div style="position: relative;">
                                    <div id="mentionWrapper"
                                        style="border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 0.4rem 0.65rem; min-height: 48px; display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; background: #ffffff; cursor: text; transition: border-color 0.2s, box-shadow 0.2s;">
                                        <i class="fa-solid fa-user"
                                            style="color: var(--text-muted); font-size: 0.85rem; flex-shrink: 0; padding-right: 2px;"></i>
                                        <input type="text" id="multiSelectInput" placeholder="Type @name to mention..."
                                            autocomplete="off"
                                            style="border: none; outline: none; background: transparent; font-family: inherit; font-size: 0.9rem; color: var(--text-main); flex: 1; min-width: 120px; padding: 0.2rem 0;" />
                                    </div>
                                    <div id="multiSelectMenu"
                                        style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #ffffff; border: 1px solid var(--border-color); border-radius: 0.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); z-index: 200; max-height: 220px; overflow-y: auto; padding: 0.4rem 0;">
                                    </div>
                                </div>
                            </div>


                            <div class="form-group">
                                <label>Due Date</label>
                                <div class="select-wrapper">
                                    <input type="date" id="newTaskDate" class="modern-input with-icon">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Time</label>
                                <div class="select-wrapper">
                                    <input type="time" id="newTaskTime" class="modern-input with-icon">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Repeat</label>
                                <div class="select-wrapper custom-single-select" id="repeatSelect"
                                    style="position: relative;">
                                    <i class="fa-solid fa-repeat select-icon" style="z-index: 2;"></i>
                                    <div class="modern-select has-icon single-select-trigger" id="repeatSelectTrigger"
                                        style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; background-image: none;">
                                        <span id="repeatSelectText"
                                            style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 20px);">No</span>
                                        <i class="fa-solid fa-chevron-down"
                                            style="font-size: 0.8em; color: var(--text-muted); transition: transform 0.2s;"></i>
                                    </div>
                                    <div class="single-select-menu" id="repeatSelectMenu"
                                        style="perspective: 1000px; display: none; position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: white; border: 1px solid var(--border-color); border-radius: 0.5rem; box-shadow: var(--shadow-md); z-index: 100; max-height: 200px; overflow-y: auto; padding: 0.5rem 0; opacity: 0; transform-origin: top; transform: scaleY(0); transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                                        <div class="repeat-option" data-value="No"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">No</div>
                                        <div class="repeat-option" data-value="Yes"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">Yes</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" id="frequencyGroup" style="display: none;">
                                <label>How often?</label>
                                <div class="select-wrapper custom-single-select" id="frequencySelect"
                                    style="position: relative;">
                                    <i class="fa-solid fa-clock-rotate-left select-icon" style="z-index: 2;"></i>
                                    <div class="modern-select has-icon single-select-trigger"
                                        id="frequencySelectTrigger"
                                        style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; background-image: none;">
                                        <span id="frequencySelectText"
                                            style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 20px);">Weekly</span>
                                        <i class="fa-solid fa-chevron-down"
                                            style="font-size: 0.8em; color: var(--text-muted); transition: transform 0.2s;"></i>
                                    </div>
                                    <div class="single-select-menu" id="frequencySelectMenu"
                                        style="perspective: 1000px; display: none; position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: white; border: 1px solid var(--border-color); border-radius: 0.5rem; box-shadow: var(--shadow-md); z-index: 100; max-height: 200px; overflow-y: auto; padding: 0.5rem 0; opacity: 0; transform-origin: top; transform: scaleY(0); transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                                        <div class="frequency-option" data-value="Hourly"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">Hourly</div>
                                        <div class="frequency-option" data-value="Daily"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">Daily</div>
                                        <div class="frequency-option" data-value="Weekly"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">Weekly</div>
                                        <div class="frequency-option" data-value="Monthly"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">Monthly</div>
                                        <div class="frequency-option" data-value="Yearly"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">Yearly</div>
                                        <div class="frequency-option" data-value="Custom"
                                            style="padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500; border-top: 1px solid var(--border-color); color: var(--primary-color); font-weight: 600;"
                                            onmouseover="this.style.background='#f3f4f6'"
                                            onmouseout="this.style.background='transparent'">
                                            <i class="fa-solid fa-sliders" style="margin-right: 6px;"></i>Custom...
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Frequency Panel (shown when Custom is selected) -->
                            <div class="form-group full-width" id="customFreqGroup"
                                style="display: none; animation: chipIn 0.2s ease both;">
                                <label>Custom Repeat</label>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span
                                        style="font-size: 0.88rem; color: var(--text-muted); white-space: nowrap;">Every</span>
                                    <input type="number" id="customFreqNum" min="1" max="365" value="1"
                                        style="width: 64px; border: 1px solid var(--border-color); border-radius: 0.4rem; padding: 0.45rem 0.6rem; font-family: inherit; font-size: 0.9rem; color: var(--text-main); outline: none; transition: border-color 0.2s; text-align: center;"
                                        onfocus="this.style.borderColor='var(--primary-color)'"
                                        onblur="this.style.borderColor='var(--border-color)'" />
                                    <div id="customFreqUnit" style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                                        <button type="button" class="cfu-btn" data-unit="minute">Minute</button>
                                        <button type="button" class="cfu-btn" data-unit="hour">Hour</button>
                                        <button type="button" class="cfu-btn cfu-active" data-unit="day">Day</button>
                                        <button type="button" class="cfu-btn" data-unit="week">Week</button>
                                        <button type="button" class="cfu-btn" data-unit="month">Month</button>
                                        <button type="button" class="cfu-btn" data-unit="year">Year</button>
                                    </div>
                                    <span id="customFreqPreview"
                                        style="font-size: 0.78rem; color: var(--primary-color); font-weight: 600; background: #eef2ff; border-radius: 12px; padding: 2px 10px; white-space: nowrap;">Every
                                        1 day</span>
                                </div>
                            </div>

                            <div class="form-group submit-group full-width">
                                <button id="addTaskBtn" class="assign-submit-btn">
                                    Assign Task <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Recently Assigned Tasks List -->
                        <div class="assigned-tasks-section">
                            <div class="assigned-tasks-header">
                                <span class="assigned-tasks-title"><i class="fa-solid fa-list-check"></i> Recently
                                    Assigned</span>
                                <div style="display:flex;align-items:center;gap:0.6rem;">
                                    <div style="position:relative;display:flex;align-items:center;">
                                        <i class="fa-regular fa-calendar"
                                            style="position:absolute;left:0.6rem;color:#f97316;font-size:0.8rem;pointer-events:none;"></i>
                                        <input type="date" id="assignedTasksDateFilter"
                                            style="padding:0.3rem 0.6rem 0.3rem 2rem;border:1.5px solid #e2e8f0;border-radius:0.5rem;font-size:0.78rem;font-family:'Outfit',sans-serif;color:#374151;background:#f8fafc;outline:none;cursor:pointer;transition:border-color 0.2s,box-shadow 0.2s;"
                                            title="Filter by assignment date">
                                    </div>
                                    <span class="assigned-tasks-count" id="assignedTasksCount">0 tasks</span>
                                </div>
                            </div>
                            <div class="assigned-tasks-list" id="assignedTasksList">
                                <!-- Real tasks will be loaded here by script.js -->
                                <div id="assignedTasksLoader"
                                    style="padding: 1.5rem; text-align: center; color: #94a3b8; font-size: 0.9rem;">
                                    <i class="fa-solid fa-spinner fa-spin" style="margin-right: 0.5rem;"></i> Loading
                                    tasks...
                                </div>
                            </div>
                        </div>
                    </div>


                </div>

            </div>
        </main>
    </div>
    <?php include 'components/modals/task-assigned-alert.html'; ?>
    <?php include 'components/modals/recurrence-expiry-modal.html'; ?>
    <?php include __DIR__ . '/components/modals/hr-corner-modal.html'; ?>
    <div id="drawerOverlay" class="drawer-overlay el-563"></div>
    <div id="notifDrawer" class="notification-drawer el-564">
        <div class="drawer-header el-565" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <h3 class="el-283">Notifications</h3>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <button id="markAllReadBtn"
                    style="background:transparent; border:none; color:#3b82f6; cursor:pointer; font-size:0.75rem; font-weight:600;"><i
                        class="fa-solid fa-check-double"></i> Mark All Read</button>
                <button id="clearNotifBtn"
                    style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:0.75rem; font-weight:600;"><i
                        class="fa-regular fa-trash-can"></i> Clear</button>
                <button id="closeNotif" class="close-drawer el-566" style="position:static;"><i
                        class="fa-solid fa-xmark el-567"></i></button>
            </div>
        </div>
        <div class="drawer-content el-568" id="notifContent" style="padding: 0;">
            <!-- Dummy content completely cleared. Content will be dynamically rendered via global_activity_logs payload -->
        </div>
    </div>
    </div>

    <?php include __DIR__ . '/components/modals/extend-deadline-modal.html'; ?>
    <?php include __DIR__ . '/components/modals/edit-task-modal.html'; ?>
    <?php include __DIR__ . '/components/modals/custom-alert-modal.html'; ?>
    <?php include __DIR__ . '/components/modals/custom-confirm-modal.html'; ?>
    <?php include __DIR__ . '/components/modals/upcoming-deadline-modal.html'; ?>
    <?php include __DIR__ . '/components/modals/force-password-change-modal.html'; ?>

    <div id="teamModal" class="modal-overlay el-597">
        <div class="modal-content team-modal-content el-598">
            <div class="modal-header el-599">
                <div class="modal-icon team-icon el-600"><i class="fa-solid fa-users el-601"></i></div>
                <h3 class="el-331">Team Members</h3>
                <button class="close-modal el-602" id="closeTeamModal"><i class="fa-solid fa-xmark el-603"></i></button>
            </div>
            <div class="modal-body el-604">
                <div class="team-list el-605" id="teamListContainer">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="modal-overlay">
        <div class="modal-content premium-task-modal"
            style="padding: 0; overflow: hidden; border: none; background: #ffffff;">
            <div class="modal-header"
                style="background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); padding: 1.5rem 2rem; border-bottom: 1px solid rgba(225, 29, 72, 0.1);">
                <div class="modal-icon"
                    style="background: linear-gradient(135deg, #e11d48, #be123c); color: white; box-shadow: 0 4px 15px rgba(225, 29, 72, 0.3); border: none;">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h3 id="scheduleModalTitle" style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0;">
                    Task Details</h3>
                <button class="close-modal" id="closeScheduleModal"
                    style="position: absolute; top: 1.25rem; right: 1.25rem; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s;"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" style="padding: 2rem; text-align: left;">
                <div style="margin-bottom: 1.5rem;">
                    <span
                        style="font-size: 0.85rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">Task
                        Name</span>
                    <div id="scheduleModalTaskName" style="font-weight: 700; font-size: 1.5rem; color: #0f172a;">...
                    </div>
                </div>

                <div
                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                        <div>
                            <span
                                style="font-size: 0.8rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;"><i
                                    class="fa-solid fa-user" style="color:#94a3b8;"></i> Assigned To</span>
                            <div id="scheduleModalAssignee" style="font-weight: 600; font-size: 1rem; color: #1e293b;">
                            </div>
                        </div>
                        <div>
                            <span
                                style="font-size: 0.8rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;"><i
                                    class="fa-solid fa-bolt" style="color:#eab308;"></i> Priority</span>
                            <div id="scheduleModalPriority" style="font-weight: 600; font-size: 1rem; color: #1e293b;">
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                        <div>
                            <span
                                style="font-size: 0.8rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;"><i
                                    class="fa-regular fa-clock" style="color:#3b82f6;"></i> Time</span>
                            <div id="scheduleModalTime" style="font-weight: 600; font-size: 1em; color: #1e293b;">
                            </div>
                        </div>
                        <div>
                            <span
                                style="font-size: 0.8rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;"><i
                                    class="fa-solid fa-hourglass-half" style="color:#8b5cf6;"></i> Duration</span>
                            <div id="scheduleModalDuration" style="font-weight: 600; font-size: 1rem; color: #1e293b;">
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div>
                            <span
                                style="font-size: 0.8rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;"><i
                                    class="fa-regular fa-calendar" style="color:#ef4444;"></i> Deadline</span>
                            <div id="scheduleModalDeadline" style="font-weight: 600; font-size: 1rem; color: #1e293b;">
                            </div>
                        </div>
                        <div>
                            <span
                                style="font-size: 0.8rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;"><i
                                    class="fa-solid fa-spinner" style="color:#10b981;"></i> Status</span>
                            <div style="font-weight: 600; font-size: 1rem; color: #1e293b;">
                                <span
                                    style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-block;">Scheduled</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="assignedTaskModal" class="modal-overlay">
        <div class="modal-content premium-task-modal"
            style="padding: 0; overflow: hidden; border: none; background: #ffffff;">
            <div class="modal-header"
                style="background: linear-gradient(135deg, #f8faff 0%, #edf2ff 100%); padding: 1.5rem 2rem; border-bottom: 1px solid rgba(99, 102, 241, 0.1);">
                <div class="modal-icon"
                    style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); border: none;">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <h3 id="assignedTaskModalTitle"
                    style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0;">Task Assignment Details
                </h3>
                <button class="close-modal" id="closeAssignedTaskModal"
                    style="position: absolute; top: 1.25rem; right: 1.25rem; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s;"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body" style="padding: 2rem; background: #ffffff;">
                <div style="margin-bottom: 2rem;">
                    <h2 id="atmTaskName"
                        style="font-weight: 800; font-size: 1.5rem; color: #0f172a; margin: 0 0 0.75rem 0; letter-spacing: -0.01em;">
                        ...</h2>
                    <div id="atmTaskDetails"
                        style="font-size: 0.95rem; color: #475569; line-height: 1.6; background: #f8fafc; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; position: relative; overflow: hidden;">
                        <div
                            style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(to bottom, #6366f1, #8b5cf6);">
                        </div>
                        ...
                    </div>
                </div>

                <div
                    style="background: #f1f5f9; border-radius: 12px; padding: 1.25rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem; border: 1px solid #e2e8f0;">
                    <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                        <label
                            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;"><i
                                class="fa-solid fa-bolt" style="color:#eab308; font-size: 0.9rem;"></i> Priority</label>
                        <div id="atmPriority" style="font-weight: 600; color: #0f172a; font-size: 1.05rem;">...</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                        <label
                            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;"><i
                                class="fa-solid fa-spinner" style="color:#3b82f6; font-size: 0.9rem;"></i>
                            Status</label>
                        <div id="atmStatus" style="font-weight: 600; color: #0f172a; font-size: 1.05rem;">...</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                        <label
                            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;"><i
                                class="fa-regular fa-calendar" style="color:#ec4899; font-size: 0.9rem;"></i> Due
                            Date</label>
                        <div id="atmDate" style="font-weight: 600; color: #0f172a; font-size: 1.05rem;">...</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                        <label
                            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;"><i
                                class="fa-regular fa-clock" style="color:#8b5cf6; font-size: 0.9rem;"></i> Time</label>
                        <div id="atmTime" style="font-weight: 600; color: #0f172a; font-size: 1.05rem;">...</div>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <label
                        style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;"><i
                            class="fa-solid fa-users" style="color: #6366f1;"></i> Assigned Team Members</label>
                    <div id="atmAssignees"
                        style="display: flex; align-items: center; background: #f8fafc; padding: 0.5rem 1rem 0.5rem 0.5rem; border-radius: 50px; border: 1px solid #e2e8f0; width: max-content; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                        <!-- JS populated members -->
                    </div>
                </div>
            </div>

            <div
                style="padding: 1.25rem 2rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 1rem;">
                <button class="premium-btn unlock-btn"
                    style="padding: 0.6rem 1.5rem; font-size: 0.9rem; border-radius: 8px; background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); transition: transform 0.2s, box-shadow 0.2s;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(79, 70, 229, 0.4)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(79, 70, 229, 0.3)';"><i
                        class="fa-solid fa-paper-plane"></i> Send Update</button>
            </div>
        </div>
    </div>

    <!-- Policy Acknowledgement Modal system -->
    <div id="policyModal" class="modal-overlay el-606">
        <div class="modal-content policy-modal-content el-607">
            <div class="policy-container el-608">
                <!-- Sidebar Progress -->
                <div class="policy-sidebar el-609">
                    <div class="policy-header-brand el-610">
                        <i class="fa-solid fa-shield-halved el-611"></i>
                        <span class="el-342">Compliance Hub</span>
                    </div>
                    <div class="policy-progress-wrapper el-612">
                        <div class="progress-info el-613">
                            <span id="policyStepText" class="el-345">1 of 3</span>
                            <span id="policyProgressPercent" class="el-346">0%</span>
                        </div>
                        <div class="policy-progress-bar el-614">
                            <div class="policy-progress-fill el-615" id="policyProgressFill"></div>
                        </div>
                    </div>
                    <div class="policy-steps-list el-616" id="policyStepsList">
                        <!-- Populated by JS -->
                    </div>


                </div>

                <!-- Main Content -->
                <div class="policy-main el-622">
                    <div class="policy-header el-623">
                        <h2 id="policyTitleDisplay" class="el-357">Policy Title</h2>
                        <div class="version-badge el-624" id="policyVersionDisplay">v1.0</div>
                        <button class="close-modal el-625" id="closePolicyModal"><i
                                class="fa-solid fa-xmark el-626"></i></button>
                    </div>

                    <div class="policy-scroll-area el-627" id="policyContentArea">
                        <div class="policy-text-content el-628" id="policyTextContent">
                            <!-- Content goes here -->
                        </div>
                        <div id="scrollSentinel" class="el-363"></div>
                    </div>

                    <div class="policy-footer el-629">
                        <div class="scroll-warning el-630" id="scrollWarning">
                            <i class="fa-solid fa-arrows-up-down el-631"></i> Please scroll to the bottom to read the
                            full policy.
                        </div>
                        <div class="acceptance-wrapper disabled el-632" id="acceptanceWrapper">
                            <div class="checkbox-group el-633">
                                <input type="checkbox" id="policyAckCheckbox" disabled class="el-369">
                                <label for="policyAckCheckbox" class="el-370">I have read and understood the <strong
                                        class="el-371"><span id="policyNameLabel" class="el-372">Policy</span></strong>.
                                    I agree to abide by these terms.</label>
                            </div>
                            <div class="action-buttons el-634">
                                <button class="policy-btn primary el-636" id="nextPolicyBtn" disabled>Accept &amp;
                                    Continue</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadPolicyModal" class="modal-overlay el-637">
        <div class="modal-content el-638">
            <div class="modal-header el-639">
                <h3 class="el-379">Upload New Policy</h3>
                <button class="close-modal el-640" id="closeUploadModal"><i
                        class="fa-solid fa-xmark el-641"></i></button>
            </div>
            <div class="modal-body el-642">
                <div class="input-group el-643">
                    <label class="el-384">Policy Title</label>
                    <input type="text" id="upPolicyTitle" placeholder="e.g. Data Security Policy" class="el-385">
                </div>
                <div class="input-group el-644">
                    <label class="el-387">Version</label>
                    <input type="text" id="upPolicyVersion" placeholder="v1.0" class="el-388">
                </div>
                <div class="input-group el-645">
                    <label class="el-390">Policy Content</label>
                    <textarea id="upPolicyContent" rows="6" placeholder="Enter full policy text here..."
                        class="el-391"></textarea>
                </div>
                <button id="submitNewPolicy" class="add-btn el-646">Upload Policy</button>
            </div>
        </div>
    </div>
    <div id="sendNotifModal" class="modal-overlay el-647">
        <div class="modal-content el-648">
            <div class="modal-header el-649">
                <h3 class="el-396">Send Employee Notification</h3>
                <button class="close-modal el-650" id="closeNotifModal"><i
                        class="fa-solid fa-xmark el-651"></i></button>
            </div>
            <div class="modal-body el-652">
                <div class="input-group el-653">
                    <label class="el-401">Title</label>
                    <input type="text" id="notifTitle" placeholder="e.g. System Maintenance" class="el-402">
                </div>
                <div class="input-group el-654">
                    <label class="el-404">Type</label>
                    <select id="notifType" class="el-405">
                        <option value="info" class="el-406">General Information</option>
                        <option value="warning" class="el-407">Warning / Alert</option>
                        <option value="success" class="el-408">Success / Good News</option>
                    </select>
                </div>
                <div class="input-group el-655">
                    <label class="el-410">Message</label>
                    <textarea id="notifMessage" rows="4" placeholder="Enter notification details..."
                        class="el-411"></textarea>
                </div>
                <button id="submitNewNotif" class="add-btn el-656">Send Notification</button>
            </div>
        </div>
    </div>

    <!-- Punch Modals are loaded dynamically -->
    <script src="punch-modals/punch-modal-loader.js"></script>

    <!-- Daily Tasks Reminder Modal -->
    <div id="dailyTasksModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px; padding: 0;">
            <div
                style="background: linear-gradient(135deg, #1e293b, #0f172a); padding: 20px; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h3 style="color: #f8fafc; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-clipboard-list" style="color: #60a5fa;"></i> Daily Agenda
                </h3>
                <button id="closeDailyTasksModal"
                    style="background: transparent; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer; transition: color 0.3s;"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div style="padding: 24px; max-height: 60vh; overflow-y: auto; background-color: #f8fafc;">
                <p style="color: #475569; font-size: 0.95rem; margin-top: 0; margin-bottom: 20px; line-height: 1.5;">
                    Welcome back! Here are your recurring daily tasks that need your attention today:</p>
                <div id="dailyTasksListModal" style="display: flex; flex-direction: column; gap: 12px;">
                    <!-- Tasks will be injected here -->
                </div>
            </div>
            <div
                style="padding: 16px 24px; background-color: #ffffff; border-top: 1px solid #e2e8f0; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; display: flex; justify-content: flex-end;">
                <button id="acknowledgeTasksBtn"
                    style="background-color: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2); transition: background-color 0.2s;">Let's
                    Get Started</button>
            </div>
        </div>
    </div>

    <script>
        window.loggedUserName = <?php echo json_encode($username); ?>;
        window.loggedUserDesignation = <?php echo json_encode($designation); ?>;
        window.loggedUserId = <?php echo json_encode((int)$user_id); ?>;
    </script>
    <script src="header.js"></script>
    <script src="components/schedule-loader.js"></script>
    <script src="components/modals/task-modal-loader.js"></script>
    <script src="script.js?v=<?= time() ?>"></script>
    <script src="components/my-tasks.js"></script>
    <script src="components/modals/extend-deadline-modal.js"></script>
    <script src="components/modals/custom-alert-modal.js"></script>
    <script src="components/modals/custom-confirm-modal.js"></script>
    <script src="components/modals/upcoming-deadline-modal.js"></script>
    <script src="components/modals/edit-task-modal.js"></script>
    <script src="components/modals/task-assigned-alert.js"></script>
    <script src="components/modals/recurrence-expiry-modal.js"></script>
    <script src="components/modals/force-password-change-modal.js"></script>
    <script src="components/modals/hr-corner-modal.js"></script>
    <script>
        /**
         * Dynamic Task List Loader (Performance Optimization)
         */
        function refreshDashboardTaskList() {
            const from = document.getElementById('taskDateFrom').value;
            const to = document.getElementById('taskDateTo').value;
            const body = document.getElementById('taskListTableBody');

            if (!body) return;

            // Show loader
            body.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; padding: 4rem; color: #94a3b8;">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                            <span style="font-size: 0.9rem;">Updating task list...</span>
                        </div>
                    </td>
                </tr>`;

            fetch(`api/fetch_dashboard_tasks.php?from=${from}&to=${to}`)
                .then(res => res.text())
                .then(html => {
                    body.innerHTML = html;
                    if (window.lucide) lucide.createIcons();
                })
                .catch(err => {
                    console.error('Task list fetch error:', err);
                    body.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: #ef4444;">Failed to load tasks.</td></tr>';
                });
        }

        document.getElementById('applyDateBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            refreshDashboardTaskList();

            // Optional: Update URL without reload to persist filter on refresh
            const from = document.getElementById('taskDateFrom').value;
            const to = document.getElementById('taskDateTo').value;
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('from', from);
            newUrl.searchParams.set('to', to);
            window.history.pushState({}, '', newUrl);
        });

        // Initial load
        document.addEventListener('DOMContentLoaded', refreshDashboardTaskList);
    </script>

</body>

</html>