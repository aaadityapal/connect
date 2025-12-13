<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Database connection
require_once '../config/db_connect.php';

// Get user data from database
$user_id = $_SESSION['user_id'];
$username = 'User';
$user_role = 'Employee';
$profile_image = '';

try {
    $stmt = $pdo->prepare("SELECT username, role, profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        $username = isset($user['username']) ? $user['username'] : 'User';
        $user_role = isset($user['role']) ? $user['role'] : 'Employee';
        $profile_image = isset($user['profile_image']) ? $user['profile_image'] : '';
    }
} catch (Exception $e) {
    // Fallback to session data if query fails
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
    error_log("Error fetching user data: " . $e->getMessage());
}

// Fetch Attendance Records
$attendance_records = [];
try {
    // Get month/year filters if set, default to current month
    $month = isset($_GET['month']) ? $_GET['month'] : date('m');
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');

    $query = "
        SELECT 
            id,
            date,
            punch_in,
            punch_out,
            punch_in_photo,
            punch_out_photo,
            address as punch_in_address,
            punch_out_address,
            work_report,
            status,
            approval_status,
            shift_time
        FROM attendance 
        WHERE user_id = :user_id 
        AND MONTH(date) = :month 
        AND YEAR(date) = :year
        ORDER BY date DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id, ':month' => $month, ':year' => $year]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching attendance: " . $e->getMessage());
}

// Fetch Holidays
$holidays = [];
try {
    $hQuery = "SELECT holiday_date, holiday_name FROM office_holidays WHERE MONTH(holiday_date) = :month AND YEAR(holiday_date) = :year";
    $hStmt = $pdo->prepare($hQuery);
    $hStmt->execute([':month' => $month, ':year' => $year]);
    while ($row = $hStmt->fetch(PDO::FETCH_ASSOC)) {
        $holidays[$row['holiday_date']] = $row['holiday_name'];
    }
} catch (Exception $e) {
    error_log("Error fetching holidays: " . $e->getMessage());
}

// Index attendance by date
$indexed_attendance = [];
foreach ($attendance_records as $rec) {
    $indexed_attendance[$rec['date']] = $rec;
}

// Generate full month list
$final_records = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Calculate limit date (don't show future absents)
$limitDay = $daysInMonth;
is_numeric($month) && is_numeric($year) ? true : $month = date('m'); // safety
if ($year == date('Y') && $month == date('m')) {
    $limitDay = date('d');
} elseif ($year > date('Y') || ($year == date('Y') && $month > date('m'))) {
    $limitDay = 0; // Future
}

for ($d = $limitDay; $d >= 1; $d--) {
    $dateStr = sprintf("%d-%02d-%02d", $year, $month, $d);

    if (isset($indexed_attendance[$dateStr])) {
        $final_records[] = $indexed_attendance[$dateStr];
    } elseif (isset($holidays[$dateStr])) {
        $final_records[] = [
            'date' => $dateStr,
            'status' => 'Holiday',
            'holiday_name' => $holidays[$dateStr],
            'is_holiday' => true,
            'punch_in' => null,
            'punch_out' => null
        ];
    } else {
        $final_records[] = [
            'date' => $dateStr,
            'status' => 'Absent',
            'is_absent' => true,
            'punch_in' => null,
            'punch_out' => null
        ];
    }
}

// Use final_records for display
$attendance_records = $final_records;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | ArchitectsHive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <!-- Icons (Feather Icons) -->
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        /* specific styles for attendance report */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: var(--font-display);
        }

        .filter-container {
            display: flex;
            gap: 1rem;
            align-items: center;
            background: var(--bg-card);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .filter-select {
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-family: var(--font-body);
            font-size: 0.9rem;
            outline: none;
            cursor: pointer;
        }

        .report-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .attendance-table th {
            background-color: var(--bg-hover);
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .attendance-table tr:hover {
            background-color: var(--bg-hover);
        }

        .attendance-table tr:last-child td {
            border-bottom: none;
        }

        .photo-thumb {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid var(--border-color);
            transition: transform 0.2s;
        }

        .photo-thumb:hover {
            transform: scale(1.1);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-present {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-absent {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-holiday {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .report-preview {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .report-preview:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        /* Modal for Photo/Report */
        .info-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .info-modal.active {
            display: flex;
        }

        .info-modal-content {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }

        .info-modal-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .close-info-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            background: none;
            border: none;
        }
    </style>
</head>

<body>

    <!-- Sidebar Container -->
    <aside class="sidebar" id="sidebarContainer">
        <!-- Sidebar content will be loaded here -->
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header (Simplified for sub-pages) -->
        <header class="top-header">
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i data-feather="menu"></i>
            </button>
            <div class="page-header-content">
                <h1 class="page-title">Attendance & Work Report</h1>
            </div>

            <div class="header-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                    <i data-feather="sun"></i>
                </button>
                <div class="user-profile-header">
                    <!-- Could add small profile dropdown here -->
                </div>
            </div>
        </header>

        <div class="dashboard-view" style="padding-top: 1rem;">

            <div class="page-header">
                <div class="filter-container">
                    <i data-feather="filter" width="16"></i>
                    <form id="filterForm" method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                        <select name="month" class="filter-select" onchange="this.form.submit()">
                            <?php
                            $months = [
                                1 => 'January',
                                2 => 'February',
                                3 => 'March',
                                4 => 'April',
                                5 => 'May',
                                6 => 'June',
                                7 => 'July',
                                8 => 'August',
                                9 => 'September',
                                10 => 'October',
                                11 => 'November',
                                12 => 'December'
                            ];
                            foreach ($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo $num == $month ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="year" class="filter-select" onchange="this.form.submit()">
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>

                <div class="stats-summary"
                    style="display: flex; gap: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
                    <div>
                        <span
                            style="font-weight: 600; color: var(--text-primary);"><?php echo count($attendance_records); ?></span>
                        Records
                    </div>
                </div>
            </div>

            <div class="report-card">
                <div class="table-responsive">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Shift Start Time</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Duration</th>
                                <th>Work Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendance_records)): ?>
                                <tr>
                                    <td colspan="7"
                                        style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                        <i data-feather="calendar"
                                            style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                                        <p>No records found for this period.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance_records as $record):
                                    $punchIn = isset($record['punch_in']) && $record['punch_in'] ? date('h:i A', strtotime($record['punch_in'])) : '-';
                                    $punchOut = isset($record['punch_out']) && $record['punch_out'] ? date('h:i A', strtotime($record['punch_out'])) : '-';

                                    // Calculate duration if both exist
                                    $duration = '-';
                                    if (isset($record['punch_in']) && isset($record['punch_out']) && $record['punch_in'] && $record['punch_out']) {
                                        $t1 = strtotime($record['punch_in']);
                                        $t2 = strtotime($record['punch_out']);
                                        $diff = $t2 - $t1;
                                        $hours = floor($diff / 3600);
                                        $minutes = floor(($diff / 60) % 60);
                                        $duration = "{$hours}h {$minutes}m";
                                    }

                                    // Photos
                                    $inPhoto = isset($record['punch_in_photo']) && $record['punch_in_photo'] ? '../' . $record['punch_in_photo'] : null;
                                    $outPhoto = isset($record['punch_out_photo']) && $record['punch_out_photo'] ? '../' . $record['punch_out_photo'] : null;

                                    // Status Badge Logic
                                    $statusClass = 'status-present';
                                    $statusText = 'Present';

                                    if (isset($record['is_holiday'])) {
                                        $statusClass = 'status-holiday'; // We'll add this CSS
                                        $statusText = $record['holiday_name'] ? $record['holiday_name'] : 'Holiday';
                                    } elseif (isset($record['is_absent'])) {
                                        $statusClass = 'status-absent';
                                        $statusText = 'Absent';
                                    } else {
                                        $statusText = ucfirst($record['status'] ?? 'Present');
                                        if (strtolower($statusText) === 'absent')
                                            $statusClass = 'status-absent';
                                    }
                                    ?>
                                    <tr>
                                        <td style="font-weight: 500;">
                                            <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                            <div style="font-size: 0.8em; color: var(--text-secondary);">
                                                <?php echo date('l', strtotime($record['date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                            <?php if (isset($record['approval_status']) && $record['approval_status'] == 'pending'): ?>
                                                <span
                                                    style="display: block; font-size: 0.7rem; color: #f59e0b; margin-top: 4px;">Pending
                                                    Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--text-secondary);">
                                            <?php echo isset($record['shift_time']) && $record['shift_time'] ? date('h:i A', strtotime($record['shift_time'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($punchIn !== '-'): ?>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <?php if ($inPhoto): ?>
                                                        <img src="<?php echo $inPhoto; ?>" class="photo-thumb"
                                                            onclick="showImage('<?php echo $inPhoto; ?>')" alt="In">
                                                    <?php else: ?>
                                                        <div class="photo-thumb"
                                                            style="background: var(--bg-hover); display: flex; align-items: center; justify-content: center;">
                                                            <i data-feather="user" width="16"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div style="font-weight: 500;"><?php echo $punchIn; ?></div>
                                                        <?php if (!empty($record['punch_in_address'])): ?>
                                                            <div style="font-size: 0.75rem; color: var(--text-secondary); max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                                title="<?php echo htmlspecialchars($record['punch_in_address']); ?>">
                                                                <i data-feather="map-pin" width="10"></i>
                                                                <?php echo htmlspecialchars($record['punch_in_address']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($punchOut !== '-'): ?>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <?php if ($outPhoto): ?>
                                                        <img src="<?php echo $outPhoto; ?>" class="photo-thumb"
                                                            onclick="showImage('<?php echo $outPhoto; ?>')" alt="Out">
                                                    <?php else: ?>
                                                        <div class="photo-thumb"
                                                            style="background: var(--bg-hover); display: flex; align-items: center; justify-content: center;">
                                                            <i data-feather="user" width="16"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div style="font-weight: 500;"><?php echo $punchOut; ?></div>
                                                        <?php if (!empty($record['punch_out_address'])): ?>
                                                            <div style="font-size: 0.75rem; color: var(--text-secondary); max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                                title="<?php echo htmlspecialchars($record['punch_out_address']); ?>">
                                                                <i data-feather="map-pin" width="10"></i>
                                                                <?php echo htmlspecialchars($record['punch_out_address']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight: 500;"><?php echo $duration; ?></td>
                                        <td>
                                            <?php if (isset($record['work_report']) && !empty($record['work_report'])): ?>
                                                <div class="report-preview"
                                                    onclick="showReport('<?php echo htmlspecialchars(addslashes($record['work_report'])); ?>')">
                                                    <?php echo htmlspecialchars($record['work_report']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">No report</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Content Modal -->
    <div class="info-modal" id="infoModal">
        <div class="info-modal-content">
            <button class="close-info-modal" id="closeInfoModal">&times;</button>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        // Use existing sidebar loader
        fetch('sidebar.html')
            .then(response => response.text())
            .then(html => {
                document.getElementById('sidebarContainer').innerHTML = html;
                feather.replace();

                // Highlight active link manually since this page isn't in the static active logic
                const links = document.querySelectorAll('.nav-link');
                links.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === 'attendance_report.php') {
                        link.classList.add('active');
                    }
                });

                // Setup sidebar toggle logic (copied from index.php/sidebar.html logic if needed, 
                // but usually the sidebar.html script handles some or we need to init it)
                const sidebar = document.getElementById('sidebarContainer');
                const overlay = document.getElementById('sidebarOverlay');
                const mobileMenuBtn = document.getElementById('mobileMenuBtn');

                // If sidebar.html has its own script, it might not run via fetch injection
                // We need to re-bind toggle logic here as a safety measure
                const toggleBtn = document.getElementById('sidebarToggle');

                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        sidebar.classList.toggle('collapsed');
                    });
                }

                if (mobileMenuBtn) {
                    mobileMenuBtn.addEventListener('click', () => {
                        sidebar.classList.toggle('mobile-open');
                        overlay.classList.toggle('active');
                    });
                }

                if (overlay) {
                    overlay.addEventListener('click', () => {
                        sidebar.classList.remove('mobile-open');
                        overlay.classList.remove('active');
                    });
                }
            });

        // Initialize Feather Icons
        feather.replace();

        // Theme Toggle
        const themeToggleBtn = document.getElementById('themeToggle');
        themeToggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');
            if (document.body.classList.contains('light-mode')) {
                themeToggleBtn.innerHTML = '<i data-feather="moon"></i>';
            } else {
                themeToggleBtn.innerHTML = '<i data-feather="sun"></i>';
            }
            feather.replace();
        });

        // Modal Logic
        const modal = document.getElementById('infoModal');
        const modalBody = document.getElementById('modalBody');
        const closeInfoModal = document.getElementById('closeInfoModal');

        function showImage(src) {
            modalBody.innerHTML = `<img src="${src}" class="info-modal-image" alt="Evidence">`;
            modal.classList.add('active');
        }

        function showReport(text) {
            modalBody.innerHTML = `
                <h3 style="margin-bottom: 1rem; color: var(--text-primary);">Work Report</h3>
                <p style="white-space: pre-wrap; line-height: 1.6; color: var(--text-secondary);">${text}</p>
            `;
            modal.classList.add('active');
        }

        closeInfoModal.addEventListener('click', () => {
            modal.classList.remove('active');
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });

        // Current user setup
        window.currentUsername = "<?php echo htmlspecialchars($username); ?>";
        // Update user profile in sidebar once loaded
        const updateSidebarProfile = setInterval(() => {
            const sidebarName = document.getElementById('sidebarUsername');
            const sidebarRole = document.getElementById('sidebarUserRole');
            const sidebarInitials = document.getElementById('userInitials');

            if (sidebarName) {
                sidebarName.textContent = window.currentUsername;
                sidebarRole.textContent = "<?php echo htmlspecialchars($user_role); ?>";
                // Initials
                const nameParts = window.currentUsername.split(' ');
                let initials = nameParts[0][0];
                if (nameParts.length > 1) {
                    initials += nameParts[nameParts.length - 1][0];
                }
                if (sidebarInitials) sidebarInitials.textContent = initials.toUpperCase();

                clearInterval(updateSidebarProfile);
            }
        }, 100);

    </script>
</body>

</html>