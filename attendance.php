<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to get user's current shift
function getCurrentShift($pdo, $user_id, $date) {
    $stmt = $pdo->prepare("
        SELECT s.*, us.weekly_offs 
        FROM user_shifts us
        JOIN shifts s ON us.shift_id = s.id
        WHERE us.user_id = ? 
        AND us.effective_from <= ?
        AND (us.effective_to IS NULL OR us.effective_to >= ?)
        LIMIT 1
    ");
    $stmt->execute([$user_id, $date, $date]);
    return $stmt->fetch();
}

// Function to calculate working hours
function calculateWorkingHours($punch_in, $punch_out) {
    if (!$punch_in || !$punch_out) return 0;
    
    $start = new DateTime($punch_in);
    $end = new DateTime($punch_out);
    $interval = $start->diff($end);
    
    return round($interval->h + ($interval->i / 60), 2);
}

// Function to get device info
function getDeviceInfo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $browser = get_browser(null, true);
    return json_encode([
        'user_agent' => $user_agent,
        'browser' => $browser['browser'],
        'platform' => $browser['platform']
    ]);
}

// Add function to check if today is a weekly off
function isWeeklyOff($weekly_offs, $date) {
    if (empty($weekly_offs)) return false;
    
    $weekly_offs_array = explode(',', $weekly_offs);
    $day_of_week = date('l', strtotime($date));
    return in_array($day_of_week, $weekly_offs_array);
}

// Handle attendance recording
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = $_SESSION['user_id'];
        $date = date('Y-m-d');
        $current_time = date('Y-m-d H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $device_info = getDeviceInfo();
        
        // Get user's current shift
        $current_shift = getCurrentShift($pdo, $user_id, $date);
        
        if (!$current_shift) {
            throw new Exception("No shift assigned for today");
        }

        // Check if today is a weekly off
        $is_weekly_off = isWeeklyOff($current_shift['weekly_offs'], $date);

        if (isset($_POST['punch_in'])) {
            // Check if already punched in
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL");
            $stmt->execute([$user_id, $date]);
            if ($stmt->fetch()) {
                throw new Exception("Already punched in for today");
            }

            $status = $is_weekly_off ? 'Weekly Off' : 'Present';

            $stmt = $pdo->prepare("
                INSERT INTO attendance 
                (user_id, date, punch_in, location, ip_address, device_info, 
                 shift_time, weekly_offs, status, remarks, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $date,
                $current_time,
                $_POST['location'],
                $ip_address,
                $device_info,
                $current_shift['shift_name'],
                $current_shift['weekly_offs'],
                $status,
                $is_weekly_off ? 'Worked on Weekly Off' : null
            ]);
            
            $_SESSION['success_message'] = "Punch in recorded successfully!" . 
                ($is_weekly_off ? " (Note: Today is your weekly off)" : "");
        }

        if (isset($_POST['punch_out'])) {
            // Get existing attendance record
            $stmt = $pdo->prepare("
                SELECT id, punch_in 
                FROM attendance 
                WHERE user_id = ? AND date = ? AND punch_out IS NULL
            ");
            $stmt->execute([$user_id, $date]);
            $attendance = $stmt->fetch();

            if (!$attendance) {
                throw new Exception("No punch-in record found for today");
            }

            // Calculate working hours and overtime
            $working_hours = calculateWorkingHours($attendance['punch_in'], $current_time);
            $shift_hours = calculateWorkingHours($current_shift['start_time'], $current_shift['end_time']);
            $overtime = max(0, $working_hours - $shift_hours);

            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET punch_out = ?,
                    working_hours = ?,
                    overtime = ?,
                    modified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $current_time,
                $working_hours,
                $overtime,
                $attendance['id']
            ]);
            
            $_SESSION['success_message'] = "Punch out recorded successfully!";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: attendance.php');
    exit();
}

// Get today's attendance for the current user
$today_attendance = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, s.shift_name, s.start_time, s.end_time
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN user_shifts us ON u.id = us.user_id
        LEFT JOIN shifts s ON us.shift_id = s.id
        WHERE a.user_id = ? AND a.date = ?
        AND us.effective_from <= a.date
        AND (us.effective_to IS NULL OR us.effective_to >= a.date)
    ");
    $stmt->execute([$_SESSION['user_id'], date('Y-m-d')]);
    $today_attendance = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        /* Reuse your existing CSS styles */
        .attendance-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .time-display {
            font-size: 2em;
            text-align: center;
            margin: 20px 0;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }
        .status-present { background: #d4edda; color: #155724; }
        .status-late { background: #fff3cd; color: #856404; }
        .weekly-off-alert {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .status-badge.present {
            background-color: #d4edda;
            color: #155724;
        }
        .status-badge.weekly-off {
            background-color: #e9ecef;
            color: #495057;
        }
        .status-badge.absent {
            background-color: #f8d7da;
            color: #721c24;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }

        .info-item label {
            display: block;
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }

        .info-item span {
            display: block;
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Attendance System</h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="attendance-card">
            <div class="time-display" id="current-time"></div>
            
            <?php if ($today_attendance): ?>
                <div class="attendance-info">
                    <h3>Today's Attendance</h3>
                    <?php 
                    $weekly_offs_array = explode(',', $today_attendance['weekly_offs']);
                    $weekly_offs_display = !empty($weekly_offs_array) ? implode(', ', $weekly_offs_array) : 'None';
                    ?>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Shift:</label>
                            <span><?php echo htmlspecialchars($today_attendance['shift_time']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Weekly Offs:</label>
                            <span><?php echo htmlspecialchars($weekly_offs_display); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span class="status-badge <?php echo strtolower($today_attendance['status']); ?>">
                                <?php echo htmlspecialchars($today_attendance['status']); ?>
                            </span>
                        </div>
                        <?php if ($today_attendance['remarks']): ?>
                            <div class="info-item">
                                <label>Remarks:</label>
                                <span><?php echo htmlspecialchars($today_attendance['remarks']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" id="attendance-form">
                <input type="hidden" name="location" id="location">
                
                <?php if (!$today_attendance || !$today_attendance['punch_in']): ?>
                    <button type="submit" name="punch_in" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Punch In
                    </button>
                <?php elseif (!$today_attendance['punch_out']): ?>
                    <button type="submit" name="punch_out" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Punch Out
                    </button>
                <?php else: ?>
                    <p class="text-success">Attendance completed for today!</p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = 
                now.toLocaleTimeString('en-US', { hour12: true });
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Get location
        document.getElementById('attendance-form').addEventListener('submit', function(e) {
            if (navigator.geolocation) {
                e.preventDefault();
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('location').value = 
                        position.coords.latitude + ',' + position.coords.longitude;
                    e.target.submit();
                }, function(error) {
                    console.error("Error getting location:", error);
                    document.getElementById('location').value = 'Location not available';
                    e.target.submit();
                });
            }
        });
    </script>
</body>
</html> 