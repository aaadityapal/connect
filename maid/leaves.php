<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$current_year = date('Y');

// 1. Fetch all active leave types
try {
    $typesQuery = "SELECT id, name, max_days, color_code FROM leave_types WHERE status = 'active' AND name != 'Casual Leave'";
    $stmt = $pdo->query($typesQuery);
    $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $leaveTypes = [];
}

// 2. Fetch User Shift (for Short Leave calculations)
$shiftStart = null;
$shiftEnd = null;
try {
    // Corrected Query: Join user_shifts (assignment) with shifts (details)
    $shiftQuery = "SELECT s.start_time, s.end_time 
                   FROM shifts s 
                   JOIN user_shifts us ON us.shift_id = s.id 
                   WHERE us.user_id = ? 
                   ORDER BY us.id DESC LIMIT 1";
    $stmt = $pdo->prepare($shiftQuery);
    $stmt->execute([$user_id]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($shift) {
        $shiftStart = $shift['start_time'];
        $shiftEnd = $shift['end_time'];
    }
} catch (PDOException $e) {
    // Ignore
}

// 3. Fetch used leaves (continue existing logic...)
$usedLeaves = [];
try {
    $usedQuery = "SELECT leave_type, SUM(duration) as total_used 
                  FROM leave_request 
                  WHERE user_id = ? 
                  AND status = 'approved' 
                  AND YEAR(start_date) = ? 
                  GROUP BY leave_type";
    $stmt = $pdo->prepare($usedQuery);
    $stmt->execute([$user_id, $current_year]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $usedLeaves[$row['leave_type']] = $row['total_used'];
    }
} catch (PDOException $e) {
    // Ignore error, assume 0 used
}
// 3. Get Filter Params for History
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// 4. Fetch Recent Requests (Filtered)
$recentRequests = [];
try {
    $reqQuery = "SELECT lr.*, lt.name as leave_name 
                 FROM leave_request lr
                 LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                 WHERE lr.user_id = :user_id 
                 AND MONTH(lr.start_date) = :month 
                 AND YEAR(lr.start_date) = :year
                 ORDER BY lr.start_date DESC";
    $stmt = $pdo->prepare($reqQuery);
    $stmt->execute([
        ':user_id' => $user_id,
        ':month' => $selectedMonth,
        ':year' => $selectedYear
    ]);
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore error
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leaves</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        .filter-select {
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid transparent;
            font-size: 0.85rem;
            background-color: transparent;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            outline: none;
        }

        .filter-select:hover {
            background-color: #f3f4f6;
            border-color: #e5e7eb;
        }

        .filter-select:focus {
            background-color: white;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
    </style>
</head>

<body>

    <div class="app-container">
        <header class="page-header" style="position: relative;">
            <h2>Leaves</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Manage your time off</p>
            <a href="../logout.php" style="
                position: absolute;
                top: 0;
                right: 0;
                text-decoration: none;
                color: var(--text-muted);
                opacity: 0.7;
                transition: opacity 0.2s;
            " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </header>

        <div class="scroll-content">
            <div class="balance-grid">
                <?php if (count($leaveTypes) > 0): ?>
                    <?php foreach ($leaveTypes as $type): ?>
                        <?php
                        $used = isset($usedLeaves[$type['id']]) ? $usedLeaves[$type['id']] : 0;

                        if ($type['name'] === 'Back Office Leave') {
                            // Helper logic: 3 leaves per month accrual
                            // Available = (Current Month * 3) - Total Used
                            $accrued = 3 * (int) date('n'); // e.g. March = 3 * 3 = 9
                            $available = $accrued - $used;
                        } else {
                            $total = $type['max_days'];
                            $available = $total - $used;
                        }

                        // Use color_code if available, else default style
                        $cardClass = 'balance-card';

                        // Format numbers to remove decimals if whole
                        $availableDisplay = (float) $available == (int) $available ? (int) $available : number_format($available, 1);
                        ?>
                        <div class="<?php echo $cardClass; ?>">
                            <h4><?php echo htmlspecialchars($type['name']); ?></h4>
                            <div class="count"><?php echo $availableDisplay; ?></div>
                            <small>Available</small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="balance-card">
                        <h4>No Types</h4>
                        <div class="count">-</div>
                        <small>Contact Admin</small>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h3 style="font-size: 1rem; color: var(--text-main); margin: 0;">Recent Requests</h3>
                <form method="GET" style="display: flex; gap: 4px;">
                    <select name="month" class="filter-select" onchange="this.form.submit()">
                        <?php
                        for ($m = 1; $m <= 12; $m++) {
                            $monthName = date('M', mktime(0, 0, 0, $m, 1)); // Short month name
                            $selected = ($m == $selectedMonth) ? 'selected' : '';
                            echo "<option value='$m' $selected>$monthName</option>";
                        }
                        ?>
                    </select>
                    <select name="year" class="filter-select" onchange="this.form.submit()">
                        <?php
                        $curYear = date('Y');
                        for ($y = $curYear; $y >= $curYear - 1; $y--) {
                            $selected = ($y == $selectedYear) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </form>
            </div>

            <div class="list-container">
                <?php if (count($recentRequests) > 0): ?>
                    <?php foreach ($recentRequests as $req): ?>
                        <div class="request-item">
                            <div class="req-info">
                                <h4>
                                    <?php echo htmlspecialchars($req['leave_name']); ?>
                                </h4>
                                <small>
                                    <?php
                                    echo date('d M', strtotime($req['start_date']));
                                    if ($req['start_date'] != $req['end_date']) {
                                        echo ' - ' . date('d M', strtotime($req['end_date']));
                                    }
                                    ?>
                                    (<?php echo (float) $req['duration']; ?> days)
                                </small>
                                <?php if (!empty($req['time_from']) && !empty($req['time_to'])): ?>
                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 2px;">
                                        🕒
                                        <?php echo date('H:i', strtotime($req['time_from'])) . ' - ' . date('H:i', strtotime($req['time_to'])); ?>
                                    </div>
                                <?php endif; ?>
                                <p class="leave-req-unique-reason">
                                    "<?php echo htmlspecialchars($req['reason']); ?>"
                                </p>
                            </div>
                            <div class="req-status">
                                <span class="status-badge status-<?php echo strtolower($req['status']); ?>">
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <div class="action-buttons"
                                        style="margin-top: 5px; display: flex; gap: 8px; justify-content: flex-end;">
                                        <button onclick="editLeave(<?php echo $req['id']; ?>)"
                                            style="background: none; border: none; cursor: pointer; color: #3b82f6;" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <button onclick="deleteLeave(<?php echo $req['id']; ?>)"
                                            style="background: none; border: none; cursor: pointer; color: #ef4444;" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path
                                                    d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                </path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="request-item">
                        <div class="req-info">No requests found for this filter.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Floating Action Button -->
        <button class="fab" title="New Leave Request" onclick="openLeaveModal()">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>

        <!-- Leave Request Modal -->
        <div class="leave-modal-overlay" id="leaveModalOverlay">
            <div class="leave-modal">
                <div class="leave-modal-header">
                    <h3>Request Leave</h3>
                    <button class="close-leave-modal" onclick="closeLeaveModal()">&times;</button>
                </div>

                <form id="leaveRequestForm" onsubmit="submitLeaveRequest(event)">
                    <div class="form-group">
                        <label>Select Manager</label>
                        <select name="manager_id" class="form-control" required>
                            <option value="">Choose your manager...</option>
                            <?php
                            // Fetch Managers
                            try {
                                $mgrQuery = "SELECT id, username, role FROM users WHERE role LIKE '%Manager%' AND status='active'";
                                $mgrStmt = $pdo->query($mgrQuery);
                                while ($mgr = $mgrStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = (stripos($mgr['role'], 'Senior Manager (Site)') !== false) ? 'selected' : '';
                                    echo "<option value='{$mgr['id']}' $selected>{$mgr['username']} ({$mgr['role']})</option>";
                                }
                            } catch (PDOException $e) {
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Leave Type</label>
                        <select name="leave_type_id" id="leaveTypeSelect" class="form-control"
                            onchange="checkLeaveType()" required>
                            <option value="">Select type...</option>
                            <?php foreach ($leaveTypes as $type): ?>
                                <?php $selected = ($type['name'] === 'Back Office Leave') ? 'selected' : ''; ?>
                                <option value="<?php echo $type['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($type['name']); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Time Slot Options (Hidden by default) -->
                    <div id="timeSlotOptions" class="form-group" style="display:none;">
                        <label id="timeSlotLabel">Select Duration</label>
                        <div
                            style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px;">
                            <label
                                style="display: flex; align-items: center; margin-bottom: 8px; cursor: pointer; font-weight: normal;">
                                <input type="radio" name="slot_option" value="morning" style="margin-right: 8px;"
                                    onchange="updateTimes()">
                                <span>First Half / Morning (<span id="morningSlotTime">--:--</span>)</span>
                            </label>
                            <label
                                style="display: flex; align-items: center; cursor: pointer; font-weight: normal; margin-bottom: 0;">
                                <input type="radio" name="slot_option" value="evening" style="margin-right: 8px;"
                                    onchange="updateTimes()">
                                <span>Second Half / Evening (<span id="eveningSlotTime">--:--</span>)</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Select Dates</label>
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <button type="button" class="month-nav-btn" onclick="prevMonth()">&lt;</button>
                                <h4 id="calMonthYear">December 2025</h4>
                                <button type="button" class="month-nav-btn" onclick="nextMonth()">&gt;</button>
                            </div>
                            <div class="calendar-grid" id="calendarGrid">
                                <!-- JS will populate this -->
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Brief reason..."
                            required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Submit Request</button>
                </form>
            </div>
        </div>

        <!-- Same Bottom Nav... -->
        <nav class="bottom-nav">
            <!-- Navigation Lines (Unchanged) -->
            <a href="index.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Punch</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Attendance</span>
            </a>
            <a href="leaves.php" class="nav-item active">
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

    <script>
        const modalOverlay = document.getElementById('leaveModalOverlay');
        let currentDate = new Date();
        let selectedDates = new Set(); // Store "YYYY-MM-DD" strings

        // Inject PHP vars
        const userShiftStart = "<?php echo $shiftStart; ?>"; // e.g. "09:00:00"
        const userShiftEnd = "<?php echo $shiftEnd; ?>";     // e.g. "18:00:00"

        // Time Calc Helpers
        function addMinutes(timeStr, minsToAdd) {
            if (!timeStr) return "--:--";
            let [h, m] = timeStr.split(':').map(Number);
            let date = new Date();
            date.setHours(h, m, 0, 0);
            date.setMinutes(date.getMinutes() + minsToAdd);
            return date.toTimeString().slice(0, 5);
        }

        function minutesDiff(startStr, endStr) {
            if (!startStr || !endStr) return 0;
            let [h1, m1] = startStr.split(':').map(Number);
            let [h2, m2] = endStr.split(':').map(Number);
            return (h2 * 60 + m2) - (h1 * 60 + m1);
        }

        function updateTimes() {
            // Just a wrapper to be called on radio change if needed in future
        }

        // Check Leave Type selection
        function checkLeaveType() {
            const select = document.getElementById('leaveTypeSelect');
            const selectedOption = select.options[select.selectedIndex];
            const typeNameRaw = selectedOption.getAttribute('data-name');
            const typeName = typeNameRaw ? typeNameRaw.trim().toLowerCase() : '';
            const slotOptions = document.getElementById('timeSlotOptions');
            const label = document.getElementById('timeSlotLabel');

            if (typeName.includes('short leave')) {
                slotOptions.style.display = 'block';
                label.innerText = "Select Short Leave Slot";
                calculateTimes('short');
            } else if (typeName.includes('half day')) {
                slotOptions.style.display = 'block';
                label.innerText = "Select Half Day Slot";
                calculateTimes('half');
            } else {
                slotOptions.style.display = 'none';
                // Reset radios
                const radios = document.querySelectorAll('input[name="slot_option"]');
                radios.forEach(r => r.checked = false);
            }
        }

        function calculateTimes(type) {
            let mStart = userShiftStart ? userShiftStart.substring(0, 5) : "09:00";
            let mEnd, eStart;
            let eEnd = userShiftEnd ? userShiftEnd.substring(0, 5) : "18:00";

            if (type === 'short') {
                // Short: Start+90m / End-90m
                mEnd = addMinutes(mStart, 90);
                eStart = addMinutes(eEnd, -90);
            } else {
                // Half Day: Total Duration / 2
                const totalMins = minutesDiff(mStart, eEnd);
                const halfMins = Math.floor(totalMins / 2);

                // Morning Slot: Start -> Start + Half
                mEnd = addMinutes(mStart, halfMins);
                // Evening Slot: Start + Half -> End
                eStart = mEnd;
            }

            document.getElementById('morningSlotTime').innerText = `${mStart} - ${mEnd}`;
            document.getElementById('eveningSlotTime').innerText = `${eStart} - ${eEnd}`;
        }

        // Modal Controls
        function openLeaveModal() {
            modalOverlay.classList.add('active');
            renderCalendar();
            checkLeaveType();
        }

        function closeLeaveModal() {
            modalOverlay.classList.remove('active');
        }

        // Close on outside click
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) closeLeaveModal();
        });

        // Calendar & Date Logic
        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();

            // Update Header
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            document.getElementById('calMonthYear').innerText = `${monthNames[month]} ${year}`;

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            const grid = document.getElementById('calendarGrid');
            grid.innerHTML = '';

            // Day Names
            const days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
            days.forEach(d => {
                const el = document.createElement('div');
                el.className = 'cal-day-name';
                el.innerText = d;
                grid.appendChild(el);
            });

            // Empty slots
            for (let i = 0; i < firstDay; i++) {
                const el = document.createElement('div');
                el.className = 'cal-date empty';
                grid.appendChild(el);
            }

            // Days
            for (let d = 1; d <= daysInMonth; d++) {
                const el = document.createElement('div');
                el.className = 'cal-date';
                el.innerText = d;

                // Format YYYY-MM-DD
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                el.dataset.date = dateStr;

                if (selectedDates.has(dateStr)) {
                    el.classList.add('selected');
                }

                el.onclick = () => toggleDate(dateStr, el);
                grid.appendChild(el);
            }
        }

        function toggleDate(dateStr, el) {
            if (selectedDates.has(dateStr)) {
                selectedDates.delete(dateStr);
                el.classList.remove('selected');
            } else {
                selectedDates.add(dateStr);
                el.classList.add('selected');
            }
        }

        function prevMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        }

        // Submit Logic
        async function submitLeaveRequest(e) {
            e.preventDefault();
            const dates = Array.from(selectedDates).sort();
            if (dates.length === 0) {
                customAlert("Please select at least one date.", "warning");
                return;
            }

            const managerId = document.querySelector('[name="manager_id"]').value;
            const select = document.getElementById('leaveTypeSelect');
            const leaveTypeId = select.value;
            const typeNameRaw = select.options[select.selectedIndex].getAttribute('data-name');
            const typeName = typeNameRaw ? typeNameRaw.trim().toLowerCase() : '';
            const reason = document.querySelector('[name="reason"]').value;
            const submitBtn = document.querySelector('.btn-submit');

            if (!managerId || !leaveTypeId || !reason) {
                customAlert("Please fill all fields", "warning");
                return;
            }

            // Handle Slot Logic
            let timeFrom = null;
            let timeTo = null;
            if (typeName.includes('short leave') || typeName.includes('half day')) {
                const slot = document.querySelector('input[name="slot_option"]:checked');
                if (!slot) {
                    customAlert("Please select a time slot.", "warning");
                    return;
                }

                if (slot.value === 'morning') {
                    const text = document.getElementById('morningSlotTime').innerText;
                    if (text && text.includes(' - ')) [timeFrom, timeTo] = text.split(' - ');
                } else {
                    const text = document.getElementById('eveningSlotTime').innerText;
                    if (text && text.includes(' - ')) [timeFrom, timeTo] = text.split(' - ');
                }

                if (!timeFrom || !timeTo) {
                    customAlert("Error determining time slot. Please refresh and try again.", "error");
                    return;
                }
            }

            const payload = {
                manager_id: managerId,
                leave_type_id: leaveTypeId,
                reason: reason,
                dates: dates,
                time_from: timeFrom,
                time_to: timeTo
            };

            // Check for edit ID
            const hiddenId = document.getElementById('edit_leave_id');
            if (hiddenId && hiddenId.value) {
                payload.leave_id = hiddenId.value;
            }

            try {
                submitBtn.disabled = true;
                submitBtn.innerText = "Submitting...";

                const response = await fetch('api_submit_leave.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.success) {
                    customAlert("Leave request submitted successfully!", "success");
                    setTimeout(() => {
                        closeLeaveModal();
                        window.location.reload();
                    }, 1500);
                } else {
                    customAlert("Error: " + result.message, "error");
                }
            } catch (error) {
                console.error('Error:', error);
                customAlert("An error occurred while submitting.", "error");
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = "Submit Request";
            }
        }

        // Delete Leave
        async function deleteLeave(id) {
            customConfirm("Are you sure you want to delete this pending request?", (confirmed) => {
                if (!confirmed) return;
                proceedDeleteLeave(id);
            });
        }

        async function proceedDeleteLeave(id) {

            try {
                const response = await fetch('api_delete_leave.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });

                const result = await response.json();
                if (result.success) {
                    customAlert('Request deleted successfully.', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    customAlert('Error: ' + result.message, 'error');
                }
            } catch (err) {
                console.error(err);
                customAlert('An error occurred.', 'error');
            }
        }

        // Edit Leave
        async function editLeave(id) {
            try {
                const response = await fetch(`api_get_leave.php?id=${id}`);
                const result = await response.json();

                if (!result.success) {
                    customAlert(result.message, 'error');
                    return;
                }

                const data = result.data;

                // Populate Form
                const form = document.getElementById('leaveRequestForm'); // Fixed ID
                document.querySelector('[name="manager_id"]').value = data.action_by; // Manager ID
                document.querySelector('[name="reason"]').value = data.reason;

                // Hidden ID field for update
                let hiddenId = document.getElementById('edit_leave_id');
                if (!hiddenId) {
                    hiddenId = document.createElement('input');
                    hiddenId.type = 'hidden';
                    hiddenId.id = 'edit_leave_id';
                    hiddenId.name = 'leave_id';
                    form.appendChild(hiddenId);
                }
                hiddenId.value = data.id;

                // Set Leave Type
                const typeSelect = document.getElementById('leaveTypeSelect');
                typeSelect.value = data.leave_type;

                // Updates dates
                selectedDates.clear();
                data.dates_list.forEach(d => selectedDates.add(d));

                // Open Modal first to ensure elements are visible for manipulation
                modalOverlay.classList.add('active');
                renderCalendar();

                // Trigger checkLeaveType based on selected value
                // We need to wait a tick or force the UI update
                checkLeaveType();

                // Handle Short/Half Day Slots
                const typeName = data.leave_type_name.toLowerCase();
                if (typeName.includes('short leave') || typeName.includes('half day')) {
                    // Determine slot based on time
                    // Morning usually starts at Shift Start (e.g., 09:00, 10:00 etc)
                    // Evening usually ends at Shift End.
                    // Simple heuristic: if time_from matches shift start (approx), it's morning.
                    // Or we can just check if time_from < 12:00 or similar if shifts are standard.
                    // Better: Check if time_from matches the calculated Morning start.

                    // We need to re-calc times to know what the slots are
                    // The 'calculateTimes' function updates the UI text.
                    // Let's assume the user hasn't changed their shift since applying.

                    const morningRadio = document.querySelector('input[name="slot_option"][value="morning"]');
                    const eveningRadio = document.querySelector('input[name="slot_option"][value="evening"]');

                    // Let's assume standard logic: 
                    // If time_from is early, it's morning.
                    // We can compare data.time_from with the Morning Start shown in UI?
                    // But UI is dynamic.
                    // Let's just try to match.

                    const timeFromShort = data.time_from ? data.time_from.substring(0, 5) : '';

                    // We can just set it based on a simple check for now
                    // If time_from is closer to start_time -> Morning
                    // If time_to is closer to end_time -> Evening

                    // Helper to get minutes
                    const getMins = (t) => {
                        if (!t) return 0;
                        const [h, m] = t.split(':').map(Number);
                        return h * 60 + m;
                    }

                    const tStart = getMins(userShiftStart);
                    const tReq = getMins(data.time_from);

                    if (Math.abs(tReq - tStart) < 60) {
                        if (morningRadio) {
                            morningRadio.checked = true;
                            morningRadio.dispatchEvent(new Event('change'));
                        }
                    } else {
                        if (eveningRadio) {
                            eveningRadio.checked = true;
                            eveningRadio.dispatchEvent(new Event('change'));
                        }
                    }
                }

                // Update Button Text
                document.querySelector('.btn-submit').innerText = "Update Request";

            } catch (error) {
                console.error(error);
                customAlert("Failed to load leave details.", "error");
            }
        }

        // Update closeLeaveModal to reset state
        function closeLeaveModal() {
            modalOverlay.classList.remove('active');
            // Reset form
            document.getElementById('leaveRequestForm').reset(); // Fixed ID
            selectedDates.clear();
            document.querySelector('.btn-submit').innerText = "Submit Request";
            const hiddenId = document.getElementById('edit_leave_id');
            if (hiddenId) hiddenId.value = '';

            // Re-render calendar to clear selections
            // (Will serve next open)
        }
    </script>
    <script src="modal-notification.js"></script>
</body>

</html>