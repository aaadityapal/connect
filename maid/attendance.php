<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get Filter Params
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// 1. Fetch User Shift for Weekly Offs
try {
    $shiftQuery = "
        SELECT us.weekly_offs
        FROM user_shifts us
        WHERE us.user_id = :user_id
        AND us.effective_from <= CURDATE()
        AND (us.effective_to IS NULL OR us.effective_to >= CURDATE())
        LIMIT 1
    ";
    $shiftStmt = $pdo->prepare($shiftQuery);
    $shiftStmt->execute([':user_id' => $user_id]);
    $shiftData = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    $weeklyOffs = $shiftData ? $shiftData['weekly_offs'] : ''; // e.g. "Saturday,Sunday"
} catch (PDOException $e) {
    $weeklyOffs = '';
}

// 2. Fetch attendance records for the selected month
try {
    $query = "SELECT * FROM attendance 
              WHERE user_id = :user_id 
              AND MONTH(date) = :month 
              AND YEAR(date) = :year 
              ORDER BY date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id, ':month' => $selectedMonth, ':year' => $selectedYear]);
    $raw_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Index by date for easy lookup
    $attendance_by_date = [];
    foreach ($raw_records as $rec) {
        $attendance_by_date[$rec['date']] = $rec;
    }
} catch (PDOException $e) {
    $attendance_by_date = [];
}

// 3. Fetch Holidays for the selected month
try {
    $holidayQuery = "SELECT holiday_date, holiday_name FROM office_holidays 
                     WHERE MONTH(holiday_date) = :month 
                     AND YEAR(holiday_date) = :year";
    $holidayStmt = $pdo->prepare($holidayQuery);
    $holidayStmt->execute([':month' => $selectedMonth, ':year' => $selectedYear]);
    $holidays_raw = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);

    $holidays_by_date = [];
    foreach ($holidays_raw as $h) {
        $holidays_by_date[$h['holiday_date']] = $h['holiday_name'];
    }
} catch (PDOException $e) {
    $holidays_by_date = [];
}

// 4. Fetch Approved Leaves for the selected month
try {
    // Calculate start and end of the month for query optimization
    $monthStart = sprintf("%04d-%02d-01", $selectedYear, $selectedMonth);
    $monthEnd = date("Y-m-t", strtotime($monthStart));

    $leaveQuery = "SELECT lr.start_date, lr.end_date, lt.name as leave_name 
                   FROM leave_request lr
                   LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                   WHERE lr.user_id = :user_id 
                   AND lr.status = 'approved'
                   AND lr.start_date <= :month_end 
                   AND lr.end_date >= :month_start";

    $leaveStmt = $pdo->prepare($leaveQuery);
    $leaveStmt->execute([
        ':user_id' => $user_id,
        ':month_end' => $monthEnd,
        ':month_start' => $monthStart
    ]);
    $leaves_raw = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

    $leaves_by_date = [];
    foreach ($leaves_raw as $leave) {
        // Expand date range
        $current = strtotime($leave['start_date']);
        $end = strtotime($leave['end_date']);

        while ($current <= $end) {
            $d = date('Y-m-d', $current);
            // Only add if within the selected month (though loop handles display, consistent map is good)
            $leaves_by_date[$d] = $leave['leave_name'];
            $current = strtotime('+1 day', $current);
        }
    }
} catch (PDOException $e) {
    $leaves_by_date = [];
}

// 5. Generate Loop for All Days in Selected Month
$display_records = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);

// Loop from last day to first day (descending)
for ($d = $daysInMonth; $d >= 1; $d--) {
    $dateStr = sprintf("%04d-%02d-%02d", $selectedYear, $selectedMonth, $d);

    // Skip future dates if filtering current month
    if (strtotime($dateStr) > time()) {
        continue;
    }

    $dayName = date('l', strtotime($dateStr)); // e.g. Monday

    // Check if record exists
    if (isset($attendance_by_date[$dateStr])) {
        // Use existing record
        $display_records[] = $attendance_by_date[$dateStr];
    } else {
        // Generate placeholder record
        $status = 'absent';
        $holidayName = null;
        $leaveName = null;

        // Priority 1: Check for Holiday
        if (isset($holidays_by_date[$dateStr])) {
            $status = 'Holiday';
            $holidayName = $holidays_by_date[$dateStr];
        }
        // Priority 2: Check for Approved Leave
        elseif (isset($leaves_by_date[$dateStr])) {
            $status = 'Leave';
            $leaveName = $leaves_by_date[$dateStr];
        }
        // Priority 3: Check for Weekly Off
        elseif ($weeklyOffs && stripos($weeklyOffs, $dayName) !== false) {
            $status = 'Weekly Off';
        }

        // Priority 4: Check if it's today and not punched
        if ($dateStr === date('Y-m-d') && $status === 'absent') {
            $status = 'Not Marked';
        }

        $display_records[] = [
            'date' => $dateStr,
            'status' => $status,
            'holiday_name' => $holidayName,
            'leave_name' => $leaveName, // Pass leave name
            'punch_in' => null,
            'punch_out' => null,
            'punch_in_photo' => null,
            'punch_out_photo' => null
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        /* UNIQUE TIMELINE UI CLASSES */
        .timeline-only-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 0;
            position: relative;
            border-bottom: 1px solid #f9fafb;
            /* Very subtle divider */
        }

        .timeline-only-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .timeline-only-date-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .timeline-only-year {
            font-size: 0.9rem;
            color: #9ca3af;
            font-weight: 400;
        }

        /* Minimalistic Filter Styles */
        .att-minimal-filter-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .att-minimal-select {
            appearance: none;
            -webkit-appearance: none;
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            padding: 8px 32px 8px 12px;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23374151'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 18px;
            outline: none;
            transition: all 0.2s ease;
        }

        .att-minimal-select:hover {
            border-color: #d1d5db;
            background-color: #f9fafb;
        }

        .att-minimal-select:focus {
            border-color: #9ca3af;
            box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.1);
        }

        .timeline-only-absent {
            font-size: 0.9rem;
            color: #9ca3af;
            font-style: italic;
            margin-top: 2px;
        }

        .timeline-only-time {
            font-size: 0.9rem;
            color: #4b5563;
            margin-top: 4px;
            font-weight: 500;
        }

        .timeline-only-photos {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 12px;
        }

        .timeline-photo-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .timeline-photo-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .timeline-only-photo {
            width: 100%;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #e5e7eb;
        }

        .timeline-only-photo:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        .timeline-only-photo:active {
            transform: scale(0.98);
        }

        /* Photo Preview Modal */
        .photo-preview-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease;
        }

        .photo-preview-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-preview-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .photo-preview-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            background: rgba(0, 0, 0, 0.5);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .photo-preview-close:hover {
            background: rgba(0, 0, 0, 0.7);
        }

        .timeline-only-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            position: relative;
            padding-right: 14px;
            min-height: 60px;
        }

        .timeline-only-status {
            font-size: 0.9rem;
            font-weight: 500;
            /* Color inline */
        }

        /* Timeline Vertical Bar Segment */
        .timeline-only-bar {
            position: absolute;
            right: 0;
            top: 6px;
            bottom: -20px;
            /* Extend to next item */
            width: 4px;
            background-color: #e5e7eb;
            border-radius: 4px;
        }

        /* Hide bar extension for last item */
        .timeline-only-item:last-child .timeline-only-bar {
            bottom: 6px;
            height: auto;
        }


        border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #d1d5db;
        }

        /* Firefox */
        * {
            scrollbar-width: thin;
            scrollbar-color: #e5e7eb transparent;
        }

        /* Holiday Status Badge */
        .status-holiday {
            background-color: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #d8b4fe;
        }

        /* Leave Status Badge */
        .status-leave {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fcd34d;
        }
    </style>
</head>

<body>

    <div class="app-container">
        <header class="page-header" style="margin-bottom: 1rem;">
            <h2 style="margin: 0; font-size: 1.5rem; font-weight: 700;">Attendance</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 4px;">Your activity history</p>
        </header>

        <div class="filter-section" style="margin-bottom: 24px;">
            <form method="GET" class="att-minimal-filter-box" style="margin: 0;">
                <select name="month" class="att-minimal-select" onchange="this.form.submit()">
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $monthName = date('F', mktime(0, 0, 0, $m, 1));
                        $selected = ($m == $selectedMonth) ? 'selected' : '';
                        echo "<option value='$m' $selected>$monthName</option>";
                    }
                    ?>
                </select>
                <select name="year" class="att-minimal-select" onchange="this.form.submit()">
                    <?php
                    $currentYear = date('Y');
                    for ($y = $currentYear; $y >= $currentYear - 1; $y--) {
                        $selected = ($y == $selectedYear) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </form>
        </div>

        <div class="scroll-content">
            <div class="list-container">
                <?php foreach ($display_records as $record): ?>
                    <?php
                    $dateObj = new DateTime($record['date']);
                    $today = new DateTime();
                    $yesterday = (clone $today)->modify('-1 day');

                    $dateSub = $dateObj->format('Y'); // Default year
                    $isToday = false;

                    if ($dateObj->format('Y-m-d') == $today->format('Y-m-d')) {
                        $dateTitle = "Today";
                        $isToday = true;
                        $inTime = $record['punch_in'] ? date('h:i A', strtotime($record['punch_in'])) : '--:--';
                        $outTime = '';
                        if ($record['punch_out']) {
                            $outTime = ' • Out: ' . date('h:i A', strtotime($record['punch_out']));
                        }
                        $timeText = "In: " . $inTime . $outTime;
                    } elseif ($dateObj->format('Y-m-d') == $yesterday->format('Y-m-d')) {
                        $dateTitle = "Yesterday";
                    } else {
                        $dateTitle = $dateObj->format('D, d M');
                    }

                    // Colors & Status Display
                    $statusLabel = ucfirst($record['status']);
                    $statusColor = '#6b7280'; // Default Gray
                
                    $lowerStatus = strtolower($record['status']);
                    if ($lowerStatus == 'present') {
                        $statusColor = '#16a34a'; // Green
                    } elseif ($lowerStatus == 'holiday') {
                        $statusColor = '#9333ea'; // Purple
                    } elseif ($lowerStatus == 'leave') {
                        $statusColor = '#d97706'; // Orange
                    } elseif ($lowerStatus == 'absent') {
                        $statusColor = '#ef4444'; // Red
                    } elseif ($lowerStatus == 'weekly off') {
                        $statusColor = '#9ca3af'; // Muted Gray
                    }

                    $photoIn = $record['punch_in_photo'] ? '../' . $record['punch_in_photo'] : null;
                    $photoOut = $record['punch_out_photo'] ? '../' . $record['punch_out_photo'] : null;
                    ?>

                    <div class="timeline-only-item">
                        <!-- Left Side -->
                        <div class="timeline-only-left">
                            <h3 class="timeline-only-date-title"><?php echo $dateTitle; ?></h3>

                            <?php if (!$isToday): ?>
                                <!-- Past: Show Year -->
                                <span class="timeline-only-year"><?php echo $dateSub; ?></span>
                            <?php endif; ?>

                            <!-- Show Holiday/Leave Name if applicable -->
                            <?php if ($lowerStatus == 'holiday'): ?>
                                <span class="timeline-only-time"
                                    style="color: #9333ea; font-size: 0.85rem; display:block; margin-top:2px;"><?php echo htmlspecialchars($record['holiday_name']); ?></span>
                            <?php elseif ($lowerStatus == 'leave'): ?>
                                <span class="timeline-only-time"
                                    style="color: #d97706; font-size: 0.85rem; display:block; margin-top:2px;"><?php echo htmlspecialchars($record['leave_name']); ?></span>
                            <?php endif; ?>

                            <!-- Show Punch In/Out Photos for Present status -->
                            <?php if ($lowerStatus == 'present' && ($photoIn || $photoOut)): ?>
                                <div class="timeline-only-photos">
                                    <?php if ($photoIn): ?>
                                        <div class="timeline-photo-item">
                                            <?php if ($record['punch_in']): ?>
                                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                                        fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 16 14"></polyline>
                                                    </svg>
                                                    <span style="color: #16a34a; font-weight: 500; font-size: 0.875rem;">In:
                                                        <?php echo date('h:i A', strtotime($record['punch_in'])); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <span class="timeline-photo-label">PUNCH IN</span>
                                            <img src="<?php echo htmlspecialchars($photoIn); ?>" class="timeline-only-photo"
                                                alt="Punch In Photo"
                                                onclick="openPhotoPreview('<?php echo htmlspecialchars($photoIn); ?>')">
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($photoOut): ?>
                                        <div class="timeline-photo-item">
                                            <?php if ($record['punch_out']): ?>
                                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                                        fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 16 14"></polyline>
                                                    </svg>
                                                    <span style="color: #ef4444; font-weight: 500; font-size: 0.875rem;">Out:
                                                        <?php echo date('h:i A', strtotime($record['punch_out'])); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <span class="timeline-photo-label">PUNCH OUT</span>
                                            <img src="<?php echo htmlspecialchars($photoOut); ?>" class="timeline-only-photo"
                                                alt="Punch Out Photo"
                                                onclick="openPhotoPreview('<?php echo htmlspecialchars($photoOut); ?>')">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Side -->
                        <div class="timeline-only-right">
                            <span class="timeline-only-status" style="color: <?php echo $statusColor; ?>">
                                <?php echo $statusLabel; ?>
                            </span>
                            <!-- Vertical Bar -->
                            <div class="timeline-only-bar"
                                style="background-color: <?php echo $statusColor; ?>; opacity: 0.3;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <nav class="bottom-nav">
            <a href="index.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Punch</span>
            </a>
            <a href="attendance.php" class="nav-item active">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Attendance</span>
            </a>
            <a href="leaves.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Leaves</span>
            </a>
            <a href="profile.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <!-- Photo Preview Modal -->
    <div id="photoPreviewModal" class="photo-preview-modal" onclick="closePhotoPreview()">
        <span class="photo-preview-close">&times;</span>
        <img class="photo-preview-content" id="previewImage" src="">
    </div>

    <script>
        function openPhotoPreview(photoSrc) {
            const modal = document.getElementById('photoPreviewModal');
            const img = document.getElementById('previewImage');
            modal.classList.add('show');
            img.src = photoSrc;
        }

        function closePhotoPreview() {
            const modal = document.getElementById('photoPreviewModal');
            modal.classList.remove('show');
        }

        // Close on escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closePhotoPreview();
            }
        });
    </script>
</body>

</html>