<?php
/**
 * Recent Time Widget Component - Compact Version
 * 
 * This component displays recent time and date information
 * in a compact, smaller format.
 */

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Get current date and time information
$current_time = date("h:i:s A"); // 12-hour format with seconds and AM/PM
$current_date = date("F j, Y"); // Month Day, Year format
$current_day = date("l"); // Full day name
$current_day_of_month = date("j"); // Day of the month without leading zeros
$current_month = date("F"); // Full month name
$current_year = date("Y"); // Full year
$current_time_24h = date("H:i"); // 24-hour format for time

// Get greeting based on IST hour
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
    // Choose one of the morning-appropriate icons
    $morning_icons = [
        "fas fa-mug-hot",      // Coffee mug
        "fas fa-sun",          // Rising sun
        "fas fa-coffee",       // Coffee cup
        "far fa-lightbulb",    // Light bulb (idea/awakening)
        "fas fa-cloud-sun",    // Sun with cloud
    ];
    // Select a random icon for variety
    $greeting_icon = $morning_icons[array_rand($morning_icons)];
    $greeting_class = "morning";
} elseif ($hour >= 12 && $hour < 16) {
    $greeting = "Good Afternoon";
    // Choose one of the afternoon-appropriate icons
    $afternoon_icons = [
        "fas fa-sun",          // Bright sun
        "fas fa-umbrella-beach", // Beach umbrella
        "fas fa-temperature-high", // High temperature
        "fas fa-business-time", // Business time
        "fas fa-briefcase",    // Briefcase (work time)
    ];
    // Select a random icon for variety
    $greeting_icon = $afternoon_icons[array_rand($afternoon_icons)];
    $greeting_class = "afternoon";
} elseif ($hour >= 16 && $hour < 20) {
    $greeting = "Good Evening";
    // Choose one of the evening-appropriate icons
    $evening_icons = [
        "fas fa-cloud-sun",    // Cloud with sun (sunset)
        "fas fa-home",         // Home (return home time)
        "fas fa-utensils",     // Utensils (dinner time)
        "fas fa-wine-glass-alt", // Wine glass
        "fas fa-bell",         // Bell (end of day)
    ];
    // Select a random icon for variety
    $greeting_icon = $evening_icons[array_rand($evening_icons)];
    $greeting_class = "evening";
} else {
    $greeting = "Good Night";
    // Choose one of the night-appropriate icons
    $night_icons = [
        "fas fa-moon",         // Moon
        "fas fa-bed",          // Bed
        "fas fa-star",         // Star
        "fas fa-cloud-moon",   // Moon with cloud
        "far fa-clock",        // Clock (late hour)
    ];
    // Select a random icon for variety
    $greeting_icon = $night_icons[array_rand($night_icons)];
    $greeting_class = "night";
}

// Get username and profile picture from session
$username = '';
$profile_picture = '';
$user_role = '';
if (isset($_SESSION['user_id'])) {
    // Get the database connection
    global $conn;
    
    // Get user details from database
    $user_id = $_SESSION['user_id'];
    $query = "SELECT username, profile_picture, role FROM users WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user_data = $result->fetch_assoc()) {
            $username = $user_data['username'];
            $profile_picture = $user_data['profile_picture'];
            $user_role = $user_data['role'];
        }
    }
}

// Check user's attendance status for today
$is_punched_in = false;
$attendance_completed = false;
$attendance_id = null;

if (isset($_SESSION['user_id'])) {
    // Get the database connection
    global $conn;
    
    // Get current date in IST
    $current_date = date('Y-m-d');
    $user_id = $_SESSION['user_id'];
    
    // Check if user has already completed attendance for today
    $completed_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NOT NULL";
    $stmt = $conn->prepare($completed_query);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $current_date);
        $stmt->execute();
        $completed_result = $stmt->get_result();
        
        if ($completed_result->num_rows > 0) {
            $attendance_completed = true;
        } else {
            // Check if user has punched in but not punched out today
            $query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NULL";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("is", $user_id, $current_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $is_punched_in = true;
                    $attendance_id = $row['id'];
                }
            }
        }
    }
}

// After the existing attendance check code, before setting $is_punched_in
// Around line 105-110, after checking punch in/punch out status:

// Get approval status if attendance exists
$attendance_status = null;
$approval_status = null;
$punch_in_time = null; // Add this line to store punch in time
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $current_date = date('Y-m-d');
    
    // Check attendance status and approval status
    $status_query = "SELECT 
                        id, 
                        punch_in, 
                        punch_out, 
                        approval_status 
                     FROM attendance 
                     WHERE user_id = ? AND date = ?";
    $stmt = $conn->prepare($status_query);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $current_date);
        $stmt->execute();
        $status_result = $stmt->get_result();
        
        if ($status_result->num_rows > 0) {
            $status_row = $status_result->fetch_assoc();
            $approval_status = $status_row['approval_status'];
            $punch_in_time = $status_row['punch_in']; // Store punch in time
            
            if (!empty($status_row['punch_in']) && empty($status_row['punch_out'])) {
                $attendance_status = 'punched_in';
            } elseif (!empty($status_row['punch_in']) && !empty($status_row['punch_out'])) {
                $attendance_status = 'completed';
            }
        }
    }
}

// Keep the existing $is_punched_in and $already_completed variables for compatibility

// Near the top of the file, after setting up time variables and before checking punch status

// Get user's shift information
$user_shift_info = null;
$shift_end_time = null;
$current_time_obj = new DateTime(date('H:i:s'));

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $current_date = date('Y-m-d');
    
    // Query to get the user's current shift
    $shift_query = "
        SELECT s.id, s.shift_name, s.start_time, s.end_time 
        FROM shifts s
        JOIN user_shifts us ON s.id = us.shift_id
        WHERE us.user_id = ?
        AND ? BETWEEN us.effective_from AND IFNULL(us.effective_to, '9999-12-31')
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($shift_query);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user_shift_info = $result->fetch_assoc();
            $shift_end_time = new DateTime($user_shift_info['end_time']);
        }
    }
}

// Calculate time remaining or overtime
$is_overtime = false;
$time_diff_seconds = 0;
$time_diff_formatted = '';

if ($shift_end_time) {
    // If end time is 00:00:00, treat it as midnight for calculation
    if ($shift_end_time->format('H:i:s') === '00:00:00') {
        $shift_end_time = new DateTime('23:59:59');
        $shift_end_time->modify('+1 second'); // Make it 00:00:00 of next day
    }
    
    // Calculate time difference
    $time_diff = $shift_end_time->getTimestamp() - $current_time_obj->getTimestamp();
    
    // If negative, it's overtime
    if ($time_diff < 0) {
        $is_overtime = true;
        $time_diff_seconds = abs($time_diff);
    } else {
        $time_diff_seconds = $time_diff;
    }
    
    // Format the time difference
    $hours = floor($time_diff_seconds / 3600);
    $minutes = floor(($time_diff_seconds % 3600) / 60);
    $seconds = $time_diff_seconds % 60;
    
    $time_diff_formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// Get user's geofence locations
$user_geofence_locations = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $current_date = date('Y-m-d');
    
    // Query to get the user's assigned geofence locations
    $geofence_query = "
        SELECT gl.id, gl.name, gl.latitude, gl.longitude, gl.radius, ugl.is_primary
        FROM geofence_locations gl
        JOIN user_geofence_locations ugl ON gl.id = ugl.geofence_location_id
        WHERE ugl.user_id = ?
        AND gl.is_active = 1
        AND ? BETWEEN ugl.effective_from AND IFNULL(ugl.effective_to, '9999-12-31')
        ORDER BY ugl.is_primary DESC
    ";
    
    $stmt = $conn->prepare($geofence_query);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $user_geofence_locations[] = $row;
        }
    }
    
    // If no specific locations assigned, get all active locations
    if (empty($user_geofence_locations)) {
        $default_query = "SELECT id, name, latitude, longitude, radius, 0 as is_primary FROM geofence_locations WHERE is_active = 1";
        $result = $conn->query($default_query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $user_geofence_locations[] = $row;
            }
        }
    }
}

// If still no locations, use default
if (empty($user_geofence_locations)) {
    $user_geofence_locations[] = [
        'id' => 0,
        'name' => 'Default Office',
        'latitude' => 28.636941,
        'longitude' => 77.302690,
        'radius' => 50,
        'is_primary' => 1
    ];
}
?>

<!-- Compact Recent Time Information Widget -->
<div class="compact-time-widget <?php echo $greeting_class; ?>">
    <div class="widget-content">
        <div class="time-greeting <?php echo $greeting_class; ?>">
            <div class="icon-container">
                <i class="greeting-icon <?php echo $greeting_icon; ?>"></i>
            </div>
            <span class="greeting-text"><?php echo $greeting; ?></span>
            <?php if (!empty($username)): ?>
                <span class="username-text">, <?php echo htmlspecialchars($username); ?></span>
            <?php endif; ?>
            <!-- Add this inside the time-greeting div, after the username-text span -->
            <?php if ($shift_end_time && !$attendance_completed): ?>
                <div class="shift-timer <?php echo $is_overtime ? 'overtime' : ''; ?>" id="shiftTimer" 
                     data-is-overtime="<?php echo $is_overtime ? 'true' : 'false'; ?>"
                     data-end-time="<?php echo $shift_end_time->format('H:i:s'); ?>"
                     data-current-time="<?php echo $current_time_obj->format('H:i:s'); ?>">
                    <div class="timer-icon">
                        <i class="fas fa-hourglass-half timer-hourglass"></i>
                    </div>
                    <div class="timer-content">
                        <div class="timer-label">
                            <?php echo $is_overtime ? 'Overtime:' : 'Shift ends in:'; ?>
                        </div>
                        <div class="timer-value" id="timerValue">
                            <?php echo $time_diff_formatted; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="datetime-row">
            <div class="date-info">
                <i class="far fa-calendar-alt"></i>
                <span class="date-text"><?php echo $current_day; ?>, <?php echo $current_date; ?></span>
            </div>
            <span class="time-divider">|</span>
            <div class="time-info">
                <i class="far fa-clock"></i>
                <span class="time-text" id="live-time"><?php echo $current_time; ?></span>
                <span class="time-zone">IST</span>
            </div>
        </div>
    </div>
    
    <!-- User Avatar and Punch-In Button Container -->
    <div class="user-controls-container">
        <!-- Punch-In Button -->
        <button id="punchInButton" class="punch-in-button <?php echo $greeting_class; ?> <?php echo $is_punched_in ? 'punched-in' : ''; ?> <?php echo $attendance_completed ? 'completed' : ''; ?>"
            data-status="<?php echo $is_punched_in ? 'punched-in' : 'not-punched-in'; ?>"
            data-approval="<?php echo $approval_status; ?>"
            <?php echo $attendance_completed ? 'disabled' : ''; ?>>
            <?php if ($approval_status == 'pending'): ?>
                <span class="approval-badge">Attendance Pending</span>
            <?php endif; ?>
            <i class="<?php 
                if ($is_punched_in) {
                    echo 'fas fa-sign-out-alt';
                } else if ($attendance_completed) {
                    echo 'fas fa-check-circle';
                } else {
                    echo 'fas fa-fingerprint';
                }
            ?>"></i>
            <span><?php 
                if ($is_punched_in) {
                    echo 'Punch Out';
                } else if ($attendance_completed) {
                    echo 'Completed';
                } else {
                    echo 'Punch In';
                }
            ?></span>
        </button>
        
        <!-- User Avatar with Dropdown Menu -->
        <div class="user-avatar-container">
            <div class="user-avatar" id="timeWidgetAvatar">
                <?php if (!empty($profile_picture)): ?>
                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            
            <!-- User Dropdown Menu -->
            <div class="user-dropdown-menu" id="timeWidgetUserMenu">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <span class="dropdown-username"><?php echo htmlspecialchars($username); ?></span>
                        <?php if (!empty($user_role)): ?>
                        <span class="dropdown-role"><?php echo htmlspecialchars($user_role); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    My Profile
                </a>
                <a href="logout.php" class="dropdown-item dropdown-item-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
    
    <button class="refresh-time-btn" onclick="refreshCompactTimeWidget()" title="Refresh Time">
        <i class="fas fa-sync-alt"></i>
    </button>
</div>

<!-- Punch In Modal -->
<div class="punch-modal" id="punchModal">
    <div class="punch-modal-content">
        <div class="punch-modal-header">
            <h3>Punch In Verification</h3>
            <span class="punch-close">&times;</span>
        </div>
        <div class="punch-modal-body">
            <div class="camera-container">
                <video id="cameraFeed" autoplay playsinline></video>
                <canvas id="captureCanvas" style="display:none;"></canvas>
                <div class="camera-controls">
                    <button id="rotateCamera" class="rotate-camera-btn">
                        <i class="fas fa-sync-alt"></i> Rotate Camera
                    </button>
                    <button id="capturePhoto" class="capture-photo-btn">
                        <i class="fas fa-camera"></i> Take Photo
                    </button>
                </div>
            </div>
            
            <div class="preview-container" style="display:none;">
                <img id="photoPreview" src="" alt="Captured photo">
                <div class="preview-controls">
                    <button id="retakePhoto" class="retake-btn">
                        <i class="fas fa-redo"></i> Retake
                    </button>
                </div>
            </div>
            
            <div class="location-info">
                <div class="location-loading">
                    <i class="fas fa-spinner fa-spin"></i> Detecting your location...
                </div>
                <div class="location-details" style="display:none;">
                    <div class="location-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="locationCoords">Coordinates: --</span>
                    </div>
                    <div class="location-item">
                        <i class="fas fa-map"></i>
                        <span id="locationAddress">Address: --</span>
                    </div>
                </div>
                <div class="location-error" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Could not detect location. Please enable location services.</span>
                </div>
                
                <!-- Outside location reason field - initially hidden -->
                <div id="outsideReasonContainer" class="outside-reason-container" style="display:none;">
                    <div class="reason-header">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>You are outside the allowed area. Please provide a reason for punching in remotely (max 15 words):</span>
                    </div>
                    <textarea id="outsideReason" class="outside-reason-input" maxlength="100" placeholder="Enter reason for outside location (required)"></textarea>
                    <div class="word-counter"><span id="wordCount">0</span>/15 words</div>
                    <div class="reason-note"><i class="fas fa-info-circle"></i> Note: Special characters alone won't be counted as words.</div>
                </div>
            </div>

            <!-- Add this after the outside reason container in the modal body -->
            <div id="workReportContainer" class="work-report-container" style="display:none;">
                <div class="report-header">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Please provide a summary of your work today (minimum 20 words):</span>
                </div>
                <textarea id="workReport" class="work-report-input" maxlength="500" placeholder="Describe tasks completed, progress made, and challenges faced today..."></textarea>
                <div class="word-counter"><span id="reportWordCount">0</span>/20 words minimum</div>
                <div class="reason-note"><i class="fas fa-info-circle"></i> Note: Special characters alone won't be counted as words.</div>
            </div>
        </div>
        <div class="punch-modal-footer">
            <button id="submitPunch" class="submit-punch-btn" disabled>
                Submit Punch In
            </button>
        </div>
    </div>
</div>

<!-- Add styles for the compact widget -->
<style>
.compact-time-widget {
    background: linear-gradient(145deg, #ffffff, #f0f4f8);
    border-radius: 12px;
    padding: 15px 20px;
    margin: 15px 0;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    align-items: center;
}

/* Time-specific gradients - darker, richer versions */
.compact-time-widget.morning {
    background: linear-gradient(145deg, #fff8e1, #ffd54f);
    border-left: 4px solid #ff9800;
    box-shadow: 0 3px 15px rgba(255, 152, 0, 0.15);
}

.compact-time-widget.afternoon {
    background: linear-gradient(145deg, #fff3e0, #ffb74d);
    border-left: 4px solid #f57c00;
    box-shadow: 0 3px 15px rgba(245, 124, 0, 0.15);
}

.compact-time-widget.evening {
    background: linear-gradient(145deg, #fbe9e7, #ff8a65);
    border-left: 4px solid #d84315;
    box-shadow: 0 3px 15px rgba(216, 67, 21, 0.15);
}

.compact-time-widget.night {
    background: linear-gradient(145deg, #e8eaf6, #7986cb);
    border-left: 4px solid #3949ab;
    box-shadow: 0 3px 15px rgba(57, 73, 171, 0.2);
}

.compact-time-widget:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
}

.widget-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
}

/* User controls container for avatar and punch-in button */
.user-controls-container {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-left: 20px;
}

/* Punch-In Button Styles */
.punch-in-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 50px;
    border: none;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    background: linear-gradient(145deg, #4CAF50, #2E7D32);
    color: white;
    position: relative;
    overflow: hidden;
}

.punch-in-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transform: translateX(-100%);
}

.punch-in-button:hover::before {
    transform: translateX(100%);
    transition: transform 0.8s ease;
}

.punch-in-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
}

.punch-in-button:active {
    transform: translateY(1px);
    box-shadow: 0 2px 5px rgba(46, 125, 50, 0.3);
}

.punch-in-button i {
    font-size: 1.1rem;
}

/* Time-specific punch-in button styles */
.punch-in-button.morning {
    background: linear-gradient(145deg, #FF9800, #F57C00);
    box-shadow: 0 3px 10px rgba(255, 152, 0, 0.2);
}

.punch-in-button.morning:hover {
    box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
}

.punch-in-button.afternoon {
    background: linear-gradient(145deg, #FF7043, #E64A19);
    box-shadow: 0 3px 10px rgba(230, 74, 25, 0.2);
}

.punch-in-button.afternoon:hover {
    box-shadow: 0 5px 15px rgba(230, 74, 25, 0.3);
}

.punch-in-button.evening {
    background: linear-gradient(145deg, #F06292, #C2185B);
    box-shadow: 0 3px 10px rgba(194, 24, 91, 0.2);
}

.punch-in-button.evening:hover {
    box-shadow: 0 5px 15px rgba(194, 24, 91, 0.3);
}

.punch-in-button.night {
    background: linear-gradient(145deg, #5C6BC0, #3949AB);
    box-shadow: 0 3px 10px rgba(57, 73, 171, 0.2);
}

.punch-in-button.night:hover {
    box-shadow: 0 5px 15px rgba(57, 73, 171, 0.3);
}

/* User avatar styles */
.user-avatar-container {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.7);
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f1f5f9;
    cursor: pointer;
}

.time-greeting.morning .user-avatar {
    border-color: rgba(255, 152, 0, 0.5);
    box-shadow: 0 3px 10px rgba(255, 152, 0, 0.2);
}

.time-greeting.afternoon .user-avatar {
    border-color: rgba(245, 124, 0, 0.5);
    box-shadow: 0 3px 10px rgba(245, 124, 0, 0.2);
}

.time-greeting.evening .user-avatar {
    border-color: rgba(216, 67, 21, 0.5);
    box-shadow: 0 3px 10px rgba(216, 67, 21, 0.2);
}

.time-greeting.night .user-avatar {
    border-color: rgba(57, 73, 171, 0.5);
    box-shadow: 0 3px 10px rgba(57, 73, 171, 0.3);
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar i {
    font-size: 24px;
    color: #94a3b8;
}

.user-avatar:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
}

.time-greeting {
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Time-specific text gradients - darker and richer */
.time-greeting.morning .greeting-text {
    background: linear-gradient(120deg, #ff9800, #e65100);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 1px 3px rgba(230, 81, 0, 0.1);
}

.time-greeting.afternoon .greeting-text {
    background: linear-gradient(120deg, #f57f17, #e65100);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 1px 3px rgba(230, 81, 0, 0.1);
}

.time-greeting.evening .greeting-text {
    background: linear-gradient(120deg, #bf360c, #b71c1c);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 1px 3px rgba(183, 28, 28, 0.1);
}

.time-greeting.night .greeting-text {
    background: linear-gradient(120deg, #283593, #1a237e);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 1px 3px rgba(26, 35, 126, 0.1);
}

/* Icon container with darker gradients */
.icon-container {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(255, 255, 255, 0.8);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.time-greeting.morning .icon-container {
    background: linear-gradient(145deg, #ffecb3, #ffb300);
    border: 2px solid rgba(255, 152, 0, 0.5);
    box-shadow: 0 2px 10px rgba(255, 152, 0, 0.2);
}

.time-greeting.afternoon .icon-container {
    background: linear-gradient(145deg, #ffe0b2, #fb8c00);
    border: 2px solid rgba(245, 124, 0, 0.5);
    box-shadow: 0 2px 10px rgba(245, 124, 0, 0.2);
}

.time-greeting.evening .icon-container {
    background: linear-gradient(145deg, #ffccbc, #e64a19);
    border: 2px solid rgba(216, 67, 21, 0.5);
    box-shadow: 0 2px 10px rgba(216, 67, 21, 0.2);
}

.time-greeting.night .icon-container {
    background: linear-gradient(145deg, #c5cae9, #3949ab);
    border: 2px solid rgba(57, 73, 171, 0.5);
    box-shadow: 0 2px 10px rgba(57, 73, 171, 0.3);
}

.icon-container:hover {
    transform: scale(1.1) rotate(10deg);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Time-specific icons */
.greeting-icon {
    font-size: 1.1rem;
    position: relative;
    z-index: 2;
}

.time-greeting.morning .greeting-icon {
    color: #ff9800;
    animation: pulse-rotate 3s infinite;
}

.time-greeting.afternoon .greeting-icon {
    color: #ff6f00;
    animation: spin-smooth 12s linear infinite;
}

.time-greeting.evening .greeting-icon {
    color: #e64a19;
    animation: bounce-rotate 4s infinite alternate;
}

.time-greeting.night .greeting-icon {
    color: #5c6bc0;
    animation: float-rotate 4s infinite alternate;
}

/* Updated animations for better rotation with rounded icons */
@keyframes pulse-rotate {
    0% { transform: scale(1); }
    50% { transform: scale(1.2) rotate(10deg); }
    100% { transform: scale(1) rotate(0deg); }
}

@keyframes spin-smooth {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes bounce-rotate {
    0% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-3px) rotate(10deg); }
    100% { transform: translateY(0) rotate(0deg); }
}

@keyframes float-rotate {
    0% { transform: translateY(0) rotate(-5deg); }
    50% { transform: translateY(-3px) rotate(5deg); }
    100% { transform: translateY(0) rotate(-5deg); }
}

/* Update username styling for better contrast with darker backgrounds */
.username-text {
    color: #3498db;
    background: linear-gradient(120deg, #2196f3, #0d47a1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
    text-shadow: 0 1px 3px rgba(13, 71, 161, 0.1);
}

.datetime-row {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
    color: #475569;
}

.date-info, .time-info {
    display: flex;
    align-items: center;
    gap: 6px;
}

.date-info i, .time-info i {
    color: #3498db;
    font-size: 0.9rem;
}

.time-divider {
    color: #cbd5e1;
    margin: 0 4px;
}

.date-text, .time-text {
    font-weight: 500;
}

.time-text {
    letter-spacing: 0.5px;
}

.time-zone {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-left: 3px;
}

.refresh-time-btn {
    background: transparent;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #94a3b8;
    position: absolute;
    top: 12px;
    right: 12px;
}

.refresh-time-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
    transform: rotate(30deg);
}

/* Animations for icons */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes fade {
    from { opacity: 0.7; }
    to { opacity: 1; }
}

@keyframes twinkle {
    from { opacity: 0.8; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1.1); }
}

/* Fingerprint animation for punch-in button */
@keyframes fingerprint-scan {
    0% { opacity: 0.7; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.1); }
    100% { opacity: 0.7; transform: scale(1); }
}

.punch-in-button i {
    animation: fingerprint-scan 2s infinite;
}

/* Responsive design */
@media (max-width: 768px) {
    .user-controls-container {
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }
    
    .punch-in-button {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .datetime-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .time-divider {
        display: none;
    }
    
    .compact-time-widget {
        flex-direction: column;
        align-items: flex-start;
        min-height: 180px;
        padding: 15px 20px 25px;
        position: relative;
        overflow: visible;
    }
    
    .widget-content {
        width: 100%;
        position: relative;
        z-index: 1;
    }
    
    .time-greeting {
        font-size: 1.2rem;
        margin-bottom: 10px;
        flex-wrap: wrap;
        padding-right: 60px; /* Make room for avatar */
    }
    
    .user-controls-container {
        position: absolute;
        top: 0;
        right: 0;
        margin-left: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        width: auto;
        z-index: 2;
    }
    
    .user-avatar {
        width: 50px;
        height: 50px;
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    /* Position the punch button to the right side */
    .punch-in-button {
        position: absolute;
        top: 90px; /* Changed from 60px to 90px to move it down */
        right: 0;
        font-size: 0.9rem;
        padding: 8px 20px;
        border-radius: 25px;
        min-width: 110px;
        background: linear-gradient(145deg, #f44336, #d32f2f);
        box-shadow: 0 3px 10px rgba(244, 67, 54, 0.3);
        z-index: 2;
    }
    
    .punch-in-button.morning,
    .punch-in-button.afternoon,
    .punch-in-button.evening,
    .punch-in-button.night {
        background: linear-gradient(145deg, #f44336, #d32f2f);
    }
    
    .punch-in-button span {
        display: inline-block;
    }
    
    .refresh-time-btn {
        top: 15px;
        right: 65px;
    }

    /* Shift timer positioning at bottom */
    .shift-timer {
        margin-left: 0;
        margin-top: 15px;
        position: absolute;
        bottom: 15px;
        left: 20px;
        display: inline-flex;
        padding: 8px 15px;
        border-radius: 25px;
        background: linear-gradient(135deg, #2196F3, #1976D2);
        box-shadow: 0 3px 10px rgba(33, 150, 243, 0.3);
        z-index: 2;
    }
    
    .shift-timer .timer-content {
        flex-direction: row;
        align-items: center;
    }
    
    .shift-timer .timer-label {
        margin-right: 5px;
        margin-bottom: 0;
        font-size: 13px;
    }
    
    .shift-timer .timer-value {
        font-size: 14px;
    }
    
    /* User dropdown menu */
    .user-dropdown-menu {
        width: 180px;
        right: 0;
        top: 60px;
    }
    
    /* Word counter styles */
    .word-counter {
        font-size: 14px;
        padding: 6px 10px;
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        position: absolute;
        right: 15px;
        bottom: -12px;
        margin: 0;
        font-weight: 500;
    }
    
    .word-counter.insufficient {
        background-color: rgba(244, 67, 54, 0.15);
        color: #d32f2f;
        font-weight: 600;
    }
    
    /* Responsive styles for work report on small screens */
    .work-report-container, .outside-reason-container {
        padding: 8px;
    }
    
    .report-header span, .reason-header span {
        font-size: 13px;
    }
    
    .work-report-input, .outside-reason-input {
        min-height: 60px;
        font-size: 14px;
    }
    
    .word-counter {
        font-size: 11px;
    }
}

@media (max-width: 480px) {
    .word-counter {
        font-size: 14px;
        padding: 6px 10px;
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        position: absolute;
        right: 15px;
        bottom: -12px;
        margin: 0;
        font-weight: 500;
    }
    
    .word-counter.insufficient {
        background-color: rgba(244, 67, 54, 0.15);
        color: #d32f2f;
        font-weight: 600;
    }
    
    /* Responsive styles for work report on small screens */
    .work-report-container, .outside-reason-container {
        padding: 8px;
        padding-bottom: 20px; /* Add extra padding at bottom for the note */
    }
    
    .report-header span, .reason-header span {
        font-size: 13px;
    }
    
    .work-report-input, .outside-reason-input {
        min-height: 60px;
        font-size: 14px;
    }
    
    .word-counter {
        font-size: 11px;
    }
    
    .reason-note {
        font-size: 11px;
        margin-top: 16px;
        text-align: center;
        justify-content: center;
    }
    
    /* Specific styling for work report container's reason note */
    .work-report-container .reason-note {
        margin-top: 20px;
    }
}

/* iPhone SE specific styles (smallest iPhone) */
@media (max-width: 375px) {
    .compact-time-widget {
        min-height: 170px;
        padding: 12px 15px 20px;
    }
    
    .datetime-row {
        margin-top: 5px;
    }
    
    .user-avatar {
        width: 45px;
        height: 45px;
    }
    
    .punch-in-button {
        top: 85px; /* Adjusted from 55px to 85px to match the change above */
        padding: 6px 15px;
        font-size: 0.8rem;
        min-width: 100px;
    }
    
    .shift-timer {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .shift-timer .timer-label {
        font-size: 11px;
    }
    
    .shift-timer .timer-value {
        font-size: 12px;
    }
}

@media (max-width: 375px) {
    .compact-time-widget {
        min-height: 180px; /* Even more height for very small screens */
        padding: 12px 15px 20px; /* Extra padding at bottom */
    }
    
    .datetime-row {
        margin-top: 5px;
    }
    
    .user-controls-container {
        right: 10px;
        gap: 10px;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
    }
    
    .punch-in-button {
        font-size: 0.8rem;
        padding: 7px 15px;
        min-width: 100px;
    }
    
    .shift-timer {
        margin-top: 10px;
        padding: 6px 12px;
    }
    
    .shift-timer .timer-content {
        flex-direction: row;
    }
    
    .shift-timer .timer-label {
        font-size: 12px;
    }
    
    .shift-timer .timer-value {
        font-size: 13px;
    }
}
    
    /* Responsive styles for work report on small screens */
    .work-report-container, .outside-reason-container {
        padding: 8px;
    }
    
    .report-header span, .reason-header span {
        font-size: 13px;
    }
    
    .work-report-input, .outside-reason-input {
        min-height: 60px;
        font-size: 14px;
    }
    
    .word-counter {
        font-size: 11px;
    }
}

/* iPhone SE, XR and other small mobile devices */
@media (max-width: 414px) {
    .work-report-container, .outside-reason-container {
        margin-top: 30px;
        padding: 18px 15px 22px;
        border-radius: 12px;
        background: linear-gradient(145deg, #e6f2ff, #d4e9fc);
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .report-header i, .reason-header i {
        top: -18px;
        left: 50%;
        transform: translateX(-50%);
        padding: 10px;
        font-size: 20px;
        background: linear-gradient(145deg, #1976D2, #1565C0);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    .report-header, .reason-header {
        margin-top: 5px;
        margin-bottom: 18px;
        justify-content: center;
        text-align: center;
    }
    
    .report-header span, .reason-header span {
        text-align: center;
        font-size: 15px;
        font-weight: 600;
        color: #0d47a1;
        display: block;
        width: 100%;
    }
    
    .work-report-input, .outside-reason-input {
        border-radius: 10px;
        border: 2px solid rgba(25, 118, 210, 0.3);
        background-color: rgba(255, 255, 255, 0.9);
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        padding: 14px;
        font-size: 16px;
        min-height: 120px;
    }
    
    .work-report-input:focus, .outside-reason-input:focus {
        border-color: #1976D2;
        box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.25), inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .word-counter {
        bottom: -15px;
        right: 50%;
        transform: translateX(50%);
        padding: 6px 14px;
        font-size: 14px;
        font-weight: 600;
        background: #ffffff;
        color: #1976D2;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    
    .word-counter.insufficient {
        background-color: #ffffff;
        color: #f44336;
        box-shadow: 0 2px 8px rgba(244, 67, 54, 0.25);
    }
    
    .submit-punch-btn {
        margin-top: 10px;
        width: 100%;
        padding: 16px;
        font-size: 18px;
        border-radius: 30px;
        background: linear-gradient(145deg, #2196F3, #1976D2);
        box-shadow: 0 6px 12px rgba(33, 150, 243, 0.4);
    }
}

/* iPhone SE specific styles (smallest iPhone) */
@media (max-width: 375px) {
    .work-report-container, .outside-reason-container {
        padding: 16px 12px 20px;
    }
    
    .report-header span, .reason-header span {
        font-size: 14px;
    }
    
    .work-report-input, .outside-reason-input {
        padding: 12px;
        font-size: 15px;
        min-height: 100px;
    }
    
    .word-counter {
        font-size: 12px;
        padding: 5px 12px;
    }
    
    .submit-punch-btn {
        padding: 14px;
        font-size: 16px;
    }
}

/* Extra small devices - fullscreen modal */
@media (max-width: 480px) and (max-height: 800px) {
    .punch-modal-content {
        width: 100%;
        margin: 0;
        height: 100%;
        border-radius: 0;
        display: flex;
        flex-direction: column;
    }
    
    .punch-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
    }
    
    #cameraFeed {
        min-height: 200px; /* Smaller camera view on very small screens */
    }
    
    /* Success/error messages on small screens */
    .success-message-container, .error-message-container {
        padding: 20px 15px;
    }
    
    .success-title {
        font-size: 20px;
    }
    
    .success-time {
        font-size: 28px;
    }
    
    .success-close-btn, .error-close-btn {
        width: 100%;
        padding: 12px;
    }
    
    /* Fix for location details */
    .location-item {
        font-size: 13px;
        word-break: break-word;
    }
    
    .geofence-status {
        padding: 6px 8px;
    }
}

/* Landscape mode on mobile */
@media (max-height: 500px) and (orientation: landscape) {
    .punch-modal-content {
        height: auto;
        max-height: 95vh;
    }
    
    .punch-modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
    
    #cameraFeed {
        min-height: 150px;
    }
    
    /* Two-column layout for landscape */
    .punch-modal-body {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .camera-container, .preview-container {
        flex: 1 1 48%;
        min-width: 250px;
    }
    
    .location-info {
        flex: 1 1 100%;
    }
    
    .work-report-container, .outside-reason-container {
        flex: 1 1 100%;
    }
}

/* User Dropdown Menu Styles */
.user-dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 220px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1000;
}

.user-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.dropdown-user-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.dropdown-username {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

.dropdown-role {
    color: #64748b;
    font-size: 0.8rem;
    font-style: italic;
}

.dropdown-divider {
    height: 1px;
    background: #eee;
    margin: 5px 0;
}

.dropdown-item {
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #475569;
    text-decoration: none;
    transition: all 0.3s ease;
}

.dropdown-item:hover {
    background: #f8fafc;
    color: #2c3e50;
}

.dropdown-item i {
    font-size: 0.9rem;
    width: 16px;
}

.dropdown-item-danger {
    color: #ef4444;
}

.dropdown-item-danger:hover {
    background: #fef2f2;
}

.punch-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
    overflow: auto;
}

.punch-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from {opacity: 0; transform: translateY(-20px);}
    to {opacity: 1; transform: translateY(0);}
}

.punch-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.punch-modal-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.punch-close {
    font-size: 24px;
    cursor: pointer;
    color: #888;
    transition: color 0.2s;
}

.punch-close:hover {
    color: #333;
}

.punch-modal-body {
    padding: 20px;
}

.camera-container {
    position: relative;
    width: 100%;
    margin-bottom: 20px;
}

#cameraFeed {
    width: 100%;
    height: auto;
    border-radius: 8px;
    background-color: #f0f0f0;
    min-height: 300px;
}

.camera-controls {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

.rotate-camera-btn, .capture-photo-btn, .retake-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.rotate-camera-btn {
    background-color: #f0f0f0;
    color: #333;
}

.rotate-camera-btn:hover {
    background-color: #e0e0e0;
}

.capture-photo-btn {
    background-color: #4CAF50;
    color: white;
}

.capture-photo-btn:hover {
    background-color: #45a049;
}

.retake-btn {
    background-color: #f44336;
    color: white;
}

.retake-btn:hover {
    background-color: #d32f2f;
}

.preview-container {
    margin-bottom: 20px;
}

#photoPreview {
    width: 100%;
    height: auto;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.preview-controls {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
}

.location-info {
    background-color: #f9f9f9;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.location-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 14px;
}

.location-item i {
    color: #2196F3;
}

.location-error {
    color: #f44336;
    display: flex;
    align-items: center;
    gap: 10px;
}

.punch-modal-footer {
    padding: 15px 20px;
    text-align: center;
    border-top: 1px solid #e0e0e0;
    background-color: #f8f9fa;
    border-radius: 0 0 10px 10px;
}

.submit-punch-btn {
    padding: 12px 24px;
    background: linear-gradient(145deg, #2196F3, #1976D2);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
    text-transform: uppercase;
}

.submit-punch-btn:hover:not([disabled]) {
    background: linear-gradient(145deg, #1E88E5, #1565C0);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(33, 150, 243, 0.4);
}

.submit-punch-btn:active:not([disabled]) {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(33, 150, 243, 0.3);
}

.submit-punch-btn[disabled] {
    background: linear-gradient(145deg, #B0BEC5, #90A4AE);
    cursor: not-allowed;
    opacity: 0.8;
    box-shadow: none;
}

@media (max-width: 768px) {
    .punch-modal-content {
        width: 95%;
        margin: 5% auto;
    }
    
    .camera-controls {
        flex-direction: column;
        gap: 10px;
    }
    
    .rotate-camera-btn, .capture-photo-btn, .retake-btn {
        width: 100%;
        justify-content: center;
        padding: 12px 15px; /* Larger touch targets */
        font-size: 16px;
    }
    
    .work-report-container, .outside-reason-container {
        padding: 15px;
        margin-top: 25px;
        position: relative;
    }
    
    .report-header, .reason-header {
        flex-direction: row;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .report-header i, .reason-header i {
        position: absolute;
        top: -15px;
        left: 15px;
        background-color: #1565c0;
        color: white;
        padding: 8px;
        border-radius: 50%;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    
    .report-header span, .reason-header span {
        margin-top: 10px;
        font-size: 16px;
    }
    
    .work-report-input, .outside-reason-input {
        min-height: 100px;
        font-size: 16px; /* Larger font for better mobile input */
        padding: 12px;
        border-width: 2px;
    }
    
    .submit-punch-btn {
        width: 100%;
        padding: 12px 20px;
        font-size: 16px;
    }
    
    .punch-modal-footer {
        padding: 15px;
    }
    
    /* Improve modal header on mobile */
    .punch-modal-header {
        padding: 12px 15px;
    }
    
    .punch-modal-header h3 {
        font-size: 18px;
    }
    
    .punch-close {
        font-size: 28px; /* Larger close button */
    }
}

.geofence-status {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 5px;
    background-color: #f5f5f5;
    font-weight: 500;
}

.submit-punch-btn.outside-geofence {
    background-color: #f44336;
}

.submit-punch-btn.outside-geofence:hover {
    background-color: #d32f2f;
}

/* Add CSS for the outside reason field */
.outside-reason-container {
    margin-top: 15px;
    padding: 12px;
    background-color: #fff3e0;
    border: 1px solid #ffcc80;
    border-radius: 5px;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.reason-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    color: #e65100;
    font-weight: 500;
}

.outside-reason-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
    font-size: 14px;
}

.outside-reason-input:focus {
    border-color: #2196F3;
    outline: none;
    box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
}

.word-counter {
    margin-top: 5px;
    text-align: right;
    font-size: 12px;
    color: #666;
}

.word-counter.exceeded {
    color: #f44336;
    font-weight: bold;
}

.submit-punch-btn.outside-with-reason {
    background-color: #ff9800;
}

.submit-punch-btn.outside-with-reason:hover:not([disabled]) {
    background-color: #f57c00;
}

/* Success message styles */
.success-message-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    text-align: center;
}

.success-animation {
    margin-bottom: 20px;
}

.success-title {
    color: #4CAF50;
    font-size: 24px;
    margin: 10px 0;
}

.success-time {
    font-size: 36px;
    font-weight: bold;
    margin: 5px 0;
}

.success-date {
    font-size: 16px;
    color: #666;
    margin-bottom: 20px;
}

.success-close-btn {
    padding: 10px 30px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.success-close-btn:hover {
    background-color: #45a049;
}

/* Checkmark animation */
.checkmark {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #4CAF50;
    stroke-miterlimit: 10;
    box-shadow: inset 0px 0px 0px #4CAF50;
    animation: fill 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
}

.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #4CAF50;
    fill: none;
    animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}

@keyframes stroke {
    100% {
        stroke-dashoffset: 0;
    }
}

@keyframes scale {
    0%, 100% {
        transform: none;
    }
    50% {
        transform: scale3d(1.1, 1.1, 1);
    }
}

@keyframes fill {
    100% {
        box-shadow: inset 0px 0px 0px 30px #4CAF50;
    }
}

/* Error message styles */
.error-message-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    text-align: center;
}

.error-icon {
    font-size: 60px;
    color: #f44336;
    margin-bottom: 20px;
}

.error-text {
    font-size: 18px;
    color: #333;
    margin-bottom: 20px;
}

.error-close-btn {
    padding: 10px 30px;
    background-color: #f44336;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.error-close-btn:hover {
    background-color: #d32f2f;
}

/* Add this to your existing CSS */
.punch-in-button.punched-in {
    background: linear-gradient(145deg, #f44336, #d32f2f);
    box-shadow: 0 3px 10px rgba(211, 47, 47, 0.2);
}

.punch-in-button.punched-in:hover {
    box-shadow: 0 5px 15px rgba(211, 47, 47, 0.3);
}

/* Time-specific punch-out button styles */
.punch-in-button.punched-in.morning {
    background: linear-gradient(145deg, #f44336, #d32f2f);
}

.punch-in-button.punched-in.afternoon {
    background: linear-gradient(145deg, #e53935, #c62828);
}

.punch-in-button.punched-in.evening {
    background: linear-gradient(145deg, #d32f2f, #b71c1c);
}

.punch-in-button.punched-in.night {
    background: linear-gradient(145deg, #c62828, #b71c1c);
}

/* Add this to your existing CSS */
.punch-in-button.completed {
    background: linear-gradient(145deg, #4CAF50, #388E3C);
    box-shadow: 0 3px 10px rgba(56, 142, 60, 0.2);
    cursor: not-allowed;
    opacity: 0.8;
}

.punch-in-button.completed:hover {
    transform: none;
    box-shadow: 0 3px 10px rgba(56, 142, 60, 0.2);
}

.punch-in-button.completed::before {
    display: none;
}

/* Work report styles */
.work-report-container {
    margin-top: 20px;
    padding: 15px;
    background-color: #e3f2fd;
    border: 1px solid #90caf9;
    border-radius: 8px;
    animation: fadeIn 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    position: relative; /* Added for proper positioning of elements */
}

.report-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    color: #1565c0;
    font-weight: 600;
}

.report-header i {
    font-size: 18px;
    background-color: rgba(33, 150, 243, 0.15);
    padding: 8px;
    border-radius: 50%;
    color: #1565c0;
}

.report-header span {
    line-height: 1.4;
}

.work-report-input {
    width: 100%;
    padding: 12px;
    border: 1px solid #bbd9f7;
    border-radius: 6px;
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
    font-size: 15px;
    background-color: #ffffff;
    transition: all 0.2s ease;
}

.work-report-input:focus {
    border-color: #2196F3;
    outline: none;
    box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.2);
}

.word-counter {
    margin-top: 8px;
    text-align: right;
    font-size: 13px;
    color: #666;
    background-color: rgba(255, 255, 255, 0.7);
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
    float: right;
}

.word-counter.insufficient {
    color: #f44336;
    font-weight: bold;
    background-color: rgba(244, 67, 54, 0.1);
}

/* Add this to your existing CSS */
.shift-timer {
    display: flex;
    align-items: center;
    margin-left: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.shift-timer .timer-icon {
    margin-right: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.shift-timer .timer-content {
    display: flex;
    align-items: center; /* Change to horizontal layout */
}

.shift-timer .timer-label {
    font-weight: 600;
    font-size: 14px;
    margin-right: 6px; /* Add margin to separate label from value */
    margin-bottom: 0; /* Remove bottom margin */
}

.shift-timer .timer-value {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    letter-spacing: 0.5px;
    font-size: 14px;
}

/* Timer hourglass animation */
@keyframes flip {
    0% {
        transform: rotate(0deg);
    }
    50% {
        transform: rotate(180deg);
    }
    100% {
        transform: rotate(180deg);
    }
}

.timer-hourglass {
    animation: flip 2s infinite;
    transform-origin: center center;
    display: inline-block;
}

/* Regular shift timer colors */
.shift-timer {
    background: linear-gradient(135deg, rgba(72, 149, 239, 0.9), rgba(51, 105, 168, 0.9));
    color: #ffffff;
    border: none;
}

/* Overtime timer colors and animation - match the dark red in the image */
.shift-timer.overtime {
    background: linear-gradient(135deg, rgba(139, 0, 0, 0.9), rgba(80, 0, 0, 0.9));
    color: #ffffff;
    border: none;
    box-shadow: 0 2px 8px rgba(139, 0, 0, 0.4);
}

.shift-timer.overtime .timer-hourglass {
    animation: flip 1s infinite; /* Faster flip for overtime */
}

/* Change icon for overtime */
.shift-timer.overtime .timer-icon i:before {
    content: "\f252"; /* fa-hourglass-end */
}

/* When shift is about to end (less than 30 minutes) */
.shift-timer.ending-soon {
    background: linear-gradient(135deg, rgba(255, 153, 0, 0.9), rgba(230, 81, 0, 0.9));
    color: #ffffff;
    border: none;
}

/* Change icon when ending soon */
.shift-timer.ending-soon .timer-icon i:before {
    content: "\f251"; /* fa-hourglass-start */
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .shift-timer {
        margin-left: 0;
        margin-top: 10px;
        padding: 5px 10px;
    }
    
    .shift-timer .timer-icon {
        font-size: 14px;
    }
    
    .shift-timer .timer-value {
        font-size: 13px;
    }
}

/* Add this to your existing CSS */
.overtime-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 8px 15px;
    background-color: rgba(255, 87, 34, 0.1);
    border-radius: 5px;
    color: #D84315;
    font-weight: 500;
}

.overtime-info i {
    font-size: 18px;
}

@media (max-width: 480px) {
    .word-counter {
        font-size: 14px;
        padding: 6px 10px;
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        position: absolute;
        right: 15px;
        bottom: -12px;
        margin: 0;
        font-weight: 500;
    }
    
    .word-counter.insufficient {
        background-color: rgba(244, 67, 54, 0.15);
        color: #d32f2f;
        font-weight: 600;
    }
    
    /* Responsive styles for work report on small screens */
    .work-report-container, .outside-reason-container {
        padding: 8px;
    }
    
    .report-header span, .reason-header span {
        font-size: 13px;
    }
    
    .work-report-input, .outside-reason-input {
        min-height: 60px;
        font-size: 14px;
    }
    
    .word-counter {
        font-size: 11px;
    }
}

@media (max-width: 480px) {
    .word-counter {
        font-size: 14px;
        padding: 6px 10px;
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        position: absolute;
        right: 15px;
        bottom: -12px;
        margin: 0;
        font-weight: 500;
    }
    
    .word-counter.insufficient {
        background-color: rgba(244, 67, 54, 0.15);
        color: #d32f2f;
        font-weight: 600;
    }
    
    /* Responsive styles for work report on small screens */
    .work-report-container, .outside-reason-container {
        padding: 8px;
    }
    
    .report-header span, .reason-header span {
        font-size: 13px;
    }
    
    .work-report-input, .outside-reason-input {
        min-height: 60px;
        font-size: 14px;
    }
    
    .word-counter {
        font-size: 11px;
    }
    
    .reason-note {
        font-size: 11px;
        margin-top: 16px;
        text-align: center;
        justify-content: center;
    }
}

.word-counter.exceeded {
    color: #f44336;
    font-weight: bold;
}

.reason-note {
    font-size: 12px;
    color: #777;
    margin-top: 8px;
    padding-left: 2px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.reason-note i {
    color: #ff9800;
    font-size: 14px;
}

.submit-punch-btn.outside-with-reason {
    background-color: #ff9800;
}

.submit-punch-btn.outside-with-reason:hover:not([disabled]) {
    background-color: #f57c00;
}

@media (max-width: 768px) {
    .punch-modal-content {
        width: 95%;
        margin: 5% auto;
    }
}

/* Add after .punch-in-button.completed styles in the CSS section */
.punch-in-button.pending-approval {
    background: linear-gradient(145deg, #FFA726, #FF8F00);
    box-shadow: 0 3px 10px rgba(255, 152, 0, 0.2);
    cursor: not-allowed;
    animation: pulse-pending 2s infinite ease-in-out;
}

.punch-in-button.pending-approval:hover {
    transform: none;
    box-shadow: 0 3px 10px rgba(255, 152, 0, 0.2);
}

.punch-in-button.pending-approval i {
    animation: spin-pending 2s infinite linear;
}

@keyframes pulse-pending {
    0% { opacity: 0.8; }
    50% { opacity: 1; }
    100% { opacity: 0.8; }
}

@keyframes spin-pending {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Add after .punch-in-button.pending-approval styles in the CSS section */
.approval-badge {
    position: absolute;
    top: -12px;
    right: -3px;
    background: linear-gradient(145deg, #2c3e50, #1a1a1a);
    color: white;
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 10px;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    animation: pulse-badge 2s infinite ease-in-out;
    z-index: 2;
    white-space: nowrap;
    line-height: 1;
    border: 1px solid rgba(255,255,255,0.7);
    letter-spacing: 0.3px;
}

@keyframes pulse-badge {
    0% { transform: scale(1); opacity: 0.9; }
    50% { transform: scale(1.05); opacity: 1; }
    100% { transform: scale(1); opacity: 0.9; }
}

/* Make the punch button position relative to properly position the badge */
.punch-in-button {
    position: relative;
    overflow: visible !important;
}

@media (max-width: 480px) {
    .approval-badge {
        top: -10px;
        right: -2px;
        font-size: 8px;
        padding: 2px 6px;
    }
}
</style>

<!-- Add JavaScript for live time update, avatar dropdown, and punch-in button -->
<script>
    // Pass PHP geofence locations to JavaScript
    const geofenceLocations = <?php echo json_encode($user_geofence_locations); ?>;
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal elements
        const punchButton = document.getElementById('punchInButton');
        const punchModal = document.getElementById('punchModal');
        const closeButton = document.querySelector('.punch-close');
        const submitButton = document.getElementById('submitPunch');
        
        // Camera elements
        const cameraFeed = document.getElementById('cameraFeed');
        const captureCanvas = document.getElementById('captureCanvas');
        const captureButton = document.getElementById('capturePhoto');
        const rotateButton = document.getElementById('rotateCamera');
        const photoPreview = document.getElementById('photoPreview');
        const retakeButton = document.getElementById('retakePhoto');
        const cameraContainer = document.querySelector('.camera-container');
        const previewContainer = document.querySelector('.preview-container');
        
        // Location elements
        const locationLoading = document.querySelector('.location-loading');
        const locationDetails = document.querySelector('.location-details');
        const locationError = document.querySelector('.location-error');
        const locationCoords = document.getElementById('locationCoords');
        const locationAddress = document.getElementById('locationAddress');
        
        let stream = null;
        let facingMode = 'user'; // Default to front camera
        let photoTaken = false;
        let locationDetected = false;
        let withinGeofence = false;
        let capturedImage = null;
        let userLatitude = null;
        let userLongitude = null;
        let userDistance = 0; // Store the distance from office
        let nearestLocation = null; // Store the nearest geofence location
        
        // Define closeModal in the global scope
        window.closeModal = function() {
            punchModal.style.display = 'none';
            stopCamera();
            resetModal();
            
            // Remove success message container if it exists
            const successContainer = document.querySelector('.success-message-container');
            if (successContainer) {
                successContainer.remove();
            }
            
            // Remove error message container if it exists
            const errorContainer = document.querySelector('.error-message-container');
            if (errorContainer) {
                errorContainer.remove();
            }
            
            // Show original modal content
            document.querySelector('.punch-modal-body').style.display = 'block';
            document.querySelector('.punch-modal-footer').style.display = 'block';
        };
        
        // Open modal when punch button is clicked
        punchButton.addEventListener('click', function() {
            // Determine if this is punch in or punch out
            const isPunchOut = this.getAttribute('data-status') === 'punched-in';
            
            // Show the modal
            punchModal.style.display = 'block';
            
            // Update modal title
            document.querySelector('.punch-modal-header h3').textContent = 
                isPunchOut ? 'Punch Out Verification' : 'Punch In Verification';
            
            // Update submit button text
            submitButton.textContent = isPunchOut ? 'Submit Punch Out' : 'Submit Punch In';
            
            // Show/hide work report section based on punch type
            const workReportContainer = document.getElementById('workReportContainer');
            if (workReportContainer) {
                workReportContainer.style.display = isPunchOut ? 'block' : 'none';
            }
            
            // Start camera and get location
            startCamera();
            getLocation();
        });
        
        // Close modal
        closeButton.addEventListener('click', closeModal);
        
        // Close modal if clicked outside
        window.addEventListener('click', function(event) {
            if (event.target === punchModal) {
                closeModal();
            }
        });
        
        function resetModal() {
            cameraContainer.style.display = 'block';
            previewContainer.style.display = 'none';
            photoTaken = false;
            locationDetected = false;
            withinGeofence = false;
            submitButton.disabled = true;
        }
        
        // Start camera
        function startCamera() {
            const constraints = {
                video: { facingMode: facingMode },
                audio: false
            };
            
            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(mediaStream) {
                    stream = mediaStream;
                    cameraFeed.srcObject = mediaStream;
                    cameraFeed.play();
                })
                .catch(function(error) {
                    console.error("Error accessing camera: ", error);
                    alert("Could not access camera. Please ensure you have granted camera permissions.");
                });
        }
        
        // Stop camera
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => {
                    track.stop();
                });
            }
        }
        
        // Rotate camera (switch between front and back)
        rotateButton.addEventListener('click', function() {
            facingMode = facingMode === 'user' ? 'environment' : 'user';
            stopCamera();
            startCamera();
        });
        
        // Capture photo
        captureButton.addEventListener('click', function() {
            captureCanvas.width = cameraFeed.videoWidth;
            captureCanvas.height = cameraFeed.videoHeight;
            
            const context = captureCanvas.getContext('2d');
            context.drawImage(cameraFeed, 0, 0, captureCanvas.width, captureCanvas.height);
            
            capturedImage = captureCanvas.toDataURL('image/jpeg');
            photoPreview.src = capturedImage;
            
            cameraContainer.style.display = 'none';
            previewContainer.style.display = 'block';
            
            photoTaken = true;
            checkSubmitEnabled();
        });
        
        // Retake photo
        retakeButton.addEventListener('click', function() {
            cameraContainer.style.display = 'block';
            previewContainer.style.display = 'none';
            photoTaken = false;
            checkSubmitEnabled();
        });
        
        // Get location
        function getLocation() {
            if (navigator.geolocation) {
                locationLoading.style.display = 'block';
                locationDetails.style.display = 'none';
                locationError.style.display = 'none';
                
                navigator.geolocation.getCurrentPosition(
                    showPosition,
                    showError,
                    { enableHighAccuracy: true }
                );
            } else {
                locationLoading.style.display = 'none';
                locationError.style.display = 'block';
                locationError.querySelector('span').textContent = "Geolocation is not supported by this browser.";
            }
        }
        
        // Calculate distance between two points using Haversine formula
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371e3; // Earth's radius in meters
            const φ1 = lat1 * Math.PI / 180;
            const φ2 = lat2 * Math.PI / 180;
            const Δφ = (lat2 - lat1) * Math.PI / 180;
            const Δλ = (lon2 - lon1) * Math.PI / 180;
            
            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                    Math.cos(φ1) * Math.cos(φ2) *
                    Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            
            return R * c; // Distance in meters
        }
        
        // Check if user is within any geofence
        function checkGeofences(latitude, longitude) {
            let minDistance = Infinity;
            let closestLocation = null;
            let isWithinAnyGeofence = false;
            
            // Check each geofence location
            geofenceLocations.forEach(location => {
                const distance = calculateDistance(
                    latitude, 
                    longitude, 
                    parseFloat(location.latitude), 
                    parseFloat(location.longitude)
                );
                
                // Update nearest location if this one is closer
                if (distance < minDistance) {
                    minDistance = distance;
                    closestLocation = location;
                }
                
                // Check if within this geofence
                if (distance <= parseFloat(location.radius)) {
                    isWithinAnyGeofence = true;
                }
            });
            
            return {
                withinGeofence: isWithinAnyGeofence,
                distance: minDistance,
                nearestLocation: closestLocation
            };
        }
        
        // Handle geolocation errors
        function showError(error) {
            locationLoading.style.display = 'none';
            locationError.style.display = 'block';
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    locationError.querySelector('span').textContent = "User denied the request for geolocation.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    locationError.querySelector('span').textContent = "Location information is unavailable.";
                    break;
                case error.TIMEOUT:
                    locationError.querySelector('span').textContent = "The request to get user location timed out.";
                    break;
                case error.UNKNOWN_ERROR:
                    locationError.querySelector('span').textContent = "An unknown error occurred.";
                    break;
            }
        }
        
        // Replace the direct OpenStreetMap API call with a server-side proxy
        function showPosition(position) {
            userLatitude = position.coords.latitude;
            userLongitude = position.coords.longitude;
            
            // Check all geofences
            const geofenceResult = checkGeofences(userLatitude, userLongitude);
            withinGeofence = geofenceResult.withinGeofence;
            userDistance = geofenceResult.distance;
            nearestLocation = geofenceResult.nearestLocation;
            
            // Display coordinates
            locationCoords.textContent = `Coordinates: ${userLatitude.toFixed(6)}, ${userLongitude.toFixed(6)}`;
            
            // Add geofence status indicator to the UI
            const geofenceStatusElement = document.createElement('div');
            geofenceStatusElement.className = 'location-item geofence-status';
            
            if (withinGeofence) {
                geofenceStatusElement.innerHTML = `
                    <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                    <span>You are within the allowed area (${userDistance.toFixed(1)} meters from ${nearestLocation.name})</span>
                `;
                
                // Hide reason field if within geofence
                document.getElementById('outsideReasonContainer').style.display = 'none';
            } else {
                geofenceStatusElement.innerHTML = `
                    <i class="fas fa-times-circle" style="color: #f44336;"></i>
                    <span>You are outside the allowed area (${userDistance.toFixed(1)} meters from ${nearestLocation.name}, max ${nearestLocation.radius}m allowed)</span>
                `;
                
                // Show reason field if outside geofence
                document.getElementById('outsideReasonContainer').style.display = 'block';
            }
            
            // Use our own server-side proxy to avoid CORS issues
            fetch(`ajax_handlers/get_address.php?lat=${userLatitude}&lng=${userLongitude}`)
                .then(response => response.json())
                .then(data => {
                    locationAddress.textContent = `Address: ${data.address}`;
                    locationLoading.style.display = 'none';
                    locationDetails.style.display = 'block';
                    
                    // Add the geofence status after the address
                    const existingGeofenceStatus = document.querySelector('.geofence-status');
                    if (existingGeofenceStatus) {
                        existingGeofenceStatus.remove();
                    }
                    locationDetails.appendChild(geofenceStatusElement);
                    
                    locationDetected = true;
                    checkSubmitEnabled();
                })
                .catch(error => {
                    console.error("Error getting address: ", error);
                    locationAddress.textContent = "Address: Could not retrieve address";
                    locationLoading.style.display = 'none';
                    locationDetails.style.display = 'block';
                    
                    // Add the geofence status even if address lookup failed
                    const existingGeofenceStatus = document.querySelector('.geofence-status');
                    if (existingGeofenceStatus) {
                        existingGeofenceStatus.remove();
                    }
                    locationDetails.appendChild(geofenceStatusElement);
                    
                    locationDetected = true;
                    checkSubmitEnabled();
                });
        }
        
        // Submit punch
        submitButton.addEventListener('click', function() {
            let message = "Punch submitted successfully!";
            let outsideReason = null;
            let workReport = null;
            
            // Determine if this is a punch-in or punch-out
            const isPunchIn = document.getElementById('punchInButton').getAttribute('data-status') !== 'punched-in';
            
            // Validate photo
            if (!photoTaken) {
                alert("Please take a photo first.");
                return;
            }
            
            // Validate outside reason if needed
            const outsideReasonContainer = document.getElementById('outsideReasonContainer');
            if (outsideReasonContainer && outsideReasonContainer.style.display !== 'none') {
                outsideReason = document.getElementById('outsideReason').value.trim();
                const wordCount = countWords(outsideReason);
                
                if (wordCount === 0) {
                    alert("Please provide a reason for being outside the allowed area.");
                    return;
                }
                
                if (wordCount > 15) {
                    alert("Please limit your reason to 15 words.");
                    return;
                }
            }
            
            // For punch out, validate work report
                            if (!isPunchIn) {
                const workReportContainer = document.getElementById('workReportContainer');
                if (workReportContainer && workReportContainer.style.display !== 'none') {
                    workReport = document.getElementById('workReport').value.trim();
                    const reportWordCount = countWords(workReport);
                    
                    if (reportWordCount < 20) {
                        alert("Please provide a work report with at least 20 words. Note that special characters alone won't be counted as words.");
                        return;
                    }
                }
                
                message = "Punch out submitted successfully.";
            } else {
                message = "Punch in recorded successfully.";
            }
            
            // Show loading state in the button
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Prepare data for submission
            const formData = new FormData();
            formData.append('photo', capturedImage);
            formData.append('user_id', <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>);
            formData.append('latitude', userLatitude);
            formData.append('longitude', userLongitude);
            formData.append('within_geofence', withinGeofence ? 1 : 0);
            formData.append('distance_from_geofence', userDistance);
            formData.append('address', document.getElementById('locationAddress').textContent.replace('Address: ', ''));
            formData.append('ip_address', '<?php echo getUserIP(); ?>');
            formData.append('device_info', '<?php echo addslashes(getDeviceInfo()); ?>');
            
            // Add geofence location ID
            if (nearestLocation && nearestLocation.id) {
                formData.append('geofence_id', nearestLocation.id);
            }
            
            // Add current time in IST to ensure server gets correct time
            const now = new Date();
            // Convert to IST (UTC+5:30)
            const istTime = new Date(now.getTime() + (5.5 * 60 * 60 * 1000));
            formData.append('client_time', istTime.toISOString());
            
            // Add outside location reason if applicable
            if (!withinGeofence && outsideReason) {
                formData.append('outside_location_reason', outsideReason);
            }
            
            // Add work report for punch out
            if (!isPunchIn && workReport) {
                formData.append('work_report', workReport);
            }
            
            // Send data to server
            fetch('ajax_handlers/submit_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Clone the response so we can both read as text and still parse as JSON
                return response.clone().text().then(text => {
                    try {
                        // Try to parse the response as JSON
                        return JSON.parse(text);
                    } catch (parseError) {
                        // If parsing fails, throw an error
                        throw new Error(`Failed to parse response as JSON`);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Show success message in the modal
                    showSuccessMessage(data.message);
                } else {
                    // Show error message in the modal
                    showErrorMessage(data.message);
                }
            })
            .catch(error => {
                showErrorMessage("Error submitting punch. Please try again.");
            });
        });
        
        // Check if submit should be enabled
        function checkSubmitEnabled() {
            // Get punch button status
            const punchButton = document.getElementById('punchInButton');
            const isPunchedIn = punchButton.getAttribute('data-status') === 'punched-in';
            
            // For punch in: need photo and location (and outside reason if applicable)
            // For punch out: need photo, location, work report (and outside reason if applicable)
            const outsideReasonContainer = document.getElementById('outsideReasonContainer');
            const outsideReasonNeeded = outsideReasonContainer && outsideReasonContainer.style.display !== 'none';
            const outsideReasonText = outsideReasonNeeded ? document.getElementById('outsideReason').value.trim() : '';
            const outsideReasonValid = !outsideReasonNeeded || (outsideReasonText.length > 0 && countWords(outsideReasonText) <= 15);
            
            // Work report validation (only for punch out)
            let workReportValid = true;
            let workReportNeeded = false;
            
            if (isPunchedIn) {
                const workReportContainer = document.getElementById('workReportContainer');
                if (workReportContainer && workReportContainer.style.display !== 'none') {
                    workReportNeeded = true;
                    const workReport = document.getElementById('workReport');
                    workReportValid = workReport && countWords(workReport.value) >= 20;
                }
            }
            
            // Determine if all conditions are met for enabling the submit button
            const canSubmit = photoTaken && locationDetected && outsideReasonValid && 
                             (!workReportNeeded || workReportValid);
            
            submitButton.disabled = !canSubmit;
            
            // Update button text based on what's missing
            if (locationDetected) {
                if (!photoTaken) {
                    // Photo is missing
                    submitButton.textContent = "Please Take a Photo";
                    submitButton.classList.add("outside-geofence");
                    submitButton.classList.remove("outside-with-reason");
                } else if (!outsideReasonValid && outsideReasonNeeded) {
                    // Outside reason is needed but not valid
                    submitButton.textContent = "Provide Reason to Continue";
                    submitButton.classList.add("outside-geofence");
                    submitButton.classList.remove("outside-with-reason");
                } else if (workReportNeeded && !workReportValid) {
                    // Work report is needed but not valid
                    submitButton.textContent = "Please Complete Work Report";
                    submitButton.classList.add("outside-geofence");
                    submitButton.classList.remove("outside-with-reason");
                } else {
                    // All conditions met
                    if (isPunchedIn) {
                        submitButton.textContent = withinGeofence ? "Submit Punch Out" : "Submit Punch Out (Outside Location)";
                    } else {
                        submitButton.textContent = withinGeofence ? "Submit Punch In" : "Submit Punch In (Outside Location)";
                    }
                    
                    if (!withinGeofence && outsideReasonValid) {
                        submitButton.classList.remove("outside-geofence");
                        submitButton.classList.add("outside-with-reason");
                    } else {
                        submitButton.classList.remove("outside-geofence", "outside-with-reason");
                    }
                }
            }
        }
        
        // Add event listeners for word counting
        const outsideReason = document.getElementById('outsideReason');
        if (outsideReason) {
            outsideReason.addEventListener('input', function() {
                const wordCount = countWords(this.value);
                document.getElementById('wordCount').textContent = wordCount;
                
                const wordCountElement = document.getElementById('wordCount').parentElement;
                if (wordCount > 15) {
                    wordCountElement.classList.add('exceeded');
                } else {
                    wordCountElement.classList.remove('exceeded');
                }
                
                checkSubmitEnabled();
            });
        }
        
        const workReport = document.getElementById('workReport');
        if (workReport) {
            workReport.addEventListener('input', function() {
                const wordCount = countWords(this.value);
                document.getElementById('reportWordCount').textContent = wordCount;
                
                const wordCountElement = document.getElementById('reportWordCount').parentElement;
                if (wordCount < 20) {
                    wordCountElement.classList.add('insufficient');
                } else {
                    wordCountElement.classList.remove('insufficient');
                }
                
                checkSubmitEnabled();
            });
        }
        
        // Improved word counting function
        function countWords(text) {
            if (!text || typeof text !== 'string') {
                return 0;
            }
            
            // Remove extra spaces and split by whitespace
            const words = text.trim().split(/\s+/);
            
            // Filter out empty entries and entries that contain only special characters
            return words.filter(word => {
                // Keep only if the word contains at least one alphanumeric character
                return word.length > 0 && /[a-zA-Z0-9]/.test(word);
            }).length;
        }
        
        // Function to show success message
        function showSuccessMessage(message) {
            // Hide all modal content
            document.querySelector('.punch-modal-body').style.display = 'none';
            document.querySelector('.punch-modal-footer').style.display = 'none';
            
            // Create success message container
            const successContainer = document.createElement('div');
            successContainer.className = 'success-message-container';
            
            // Determine if it's punch in or punch out
            const punchButton = document.getElementById('punchInButton');
            const isPunchIn = punchButton.getAttribute('data-status') !== 'punched-in';
            
            // Check if we're in overtime
            const timerElement = document.getElementById('shiftTimer');
            const isOvertime = timerElement && timerElement.classList.contains('overtime');
            
            // Get overtime value if available
            let overtimeInfo = '';
            if (!isPunchIn && isOvertime && timerElement) {
                const timerValue = document.getElementById('timerValue').textContent;
                
                // Only show overtime message if it's at least 1:30:00
                const [hours, minutes] = timerValue.split(':').map(Number);
                if (hours > 0 || (hours === 0 && minutes >= 30)) {
                    overtimeInfo = `
                        <div class="overtime-info">
                            <i class="fas fa-business-time"></i>
                            <span>Overtime recorded: ${timerValue}</span>
                        </div>
                    `;
                }
            }
            
            // Check if the attendance requires approval (outside geofence)
            const requiresApproval = !withinGeofence;
            
            // Add manager approval notification if outside geofence
            let approvalMessage = '';
            if (requiresApproval) {
                approvalMessage = `
                    <div class="approval-message">
                        <i class="fas fa-info-circle"></i>
                        <p>Your attendance has been sent to your manager for approval because you punched ${isPunchIn ? 'in' : 'out'} outside the geolocation. If approved, it will be counted as present.</p>
                    </div>
                `;
            }
            
                // Create success container HTML first
            successContainer.innerHTML = `
                <div class="success-animation">
                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                    </svg>
                </div>
                <h2 class="success-title">${isPunchIn ? 'Punch In Successful!' : 'Punch Out Successful!'}</h2>
                ${approvalMessage}
                <p class="success-time">${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</p>
                <p class="success-date">${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                ${overtimeInfo}
        <div id="working-hours-container"></div>
                <button class="success-close-btn">Close</button>
            `;
            
            // For punch out, fetch and show working hours
        if (!isPunchIn) {
            // We'll fetch this from the server
            fetchWorkingHours().then(data => {
                if (data) {
                    // Create working hours info
                    let workingHoursInfo = `
                        <div class="working-hours-info">
                            <i class="fas fa-clock"></i>
                            <span>Total working hours: ${data.working_hours}</span>
                            <span class="hours-format">(HH:MM:SS)</span>
                        </div>
                    `;
                
                // Add overtime section if available
                if (data.has_overtime) {
                    workingHoursInfo += `
                        <div class="overtime-info-box">
                            <div class="overtime-header">
                                <i class="fas fa-business-time"></i>
                                <span>Overtime Detected</span>
                            </div>
                            <div class="overtime-details">
                                <p>You worked <strong>${data.overtime_hours}</strong> beyond your shift end time (${data.shift_name}: ends at ${formatTime(data.shift_end_time)}).</p>
                            </div>
                            <button id="sendOvertimeRequest" class="send-overtime-btn">
                                <i class="fas fa-paper-plane"></i> Send Overtime Request
                            </button>
                        </div>
                    `;
                    
                    // Add event listener for the send button after a short delay to ensure DOM is ready
                    setTimeout(() => {
                        const sendButton = document.getElementById('sendOvertimeRequest');
                        if (sendButton) {
                            sendButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                sendOvertimeRequest(data.overtime_hours, data.shift_end_time);
                            });
                        }
                    }, 100);
                }
                
                // Insert into the placeholder
                const container = document.getElementById('working-hours-container');
                if (container) {
                    container.innerHTML = workingHoursInfo;
                }
            }
        });
    }
            
                // Add CSS for approval message and working hours
            const style = document.createElement('style');
            style.textContent = `
                .approval-message {
                    background-color: #fff3e0;
                    border-left: 4px solid #ff9800;
                    border-radius: 4px;
                    padding: 12px 15px;
                    margin: 15px 0;
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                }
                
                .approval-message i {
                    color: #ff9800;
                    font-size: 20px;
                    margin-top: 2px;
                }
                
                .approval-message p {
                    margin: 0;
                    font-size: 14px;
                    line-height: 1.5;
                    color: #333;
                    text-align: left;
                }
        
        .working-hours-info {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            border-radius: 4px;
            padding: 12px 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .working-hours-info i {
            color: #4caf50;
            font-size: 20px;
        }
        
        .working-hours-info span {
            font-size: 16px;
            font-weight: 600;
            color: #2e7d32;
        }
        
        .working-hours-info .hours-format {
            font-size: 12px;
            font-weight: normal;
            color: #689f38;
            margin-left: 5px;
            opacity: 0.8;
        }
        
        /* Overtime styles */
        .overtime-info-box {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .overtime-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .overtime-header i {
            color: #ff9800;
            font-size: 20px;
        }
        
        .overtime-header span {
            font-size: 16px;
            font-weight: 600;
            color: #e65100;
        }
        
        .overtime-details {
            margin-bottom: 15px;
        }
        
        .overtime-details p {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }
        
        .overtime-details strong {
            color: #e65100;
            font-weight: 600;
        }
        
        .send-overtime-btn {
            background: linear-gradient(145deg, #ff9800, #f57c00);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .send-overtime-btn:hover {
            background: linear-gradient(145deg, #f57c00, #ef6c00);
            box-shadow: 0 3px 8px rgba(0,0,0,0.3);
            transform: translateY(-1px);
        }
        
        .send-overtime-btn:active {
            transform: translateY(1px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .send-overtime-btn.request-sent {
            background: linear-gradient(145deg, #4caf50, #388e3c);
            cursor: default;
        }
        
        .send-overtime-btn.request-failed {
            background: linear-gradient(145deg, #f44336, #d32f2f);
        }
                
                @media (max-width: 480px) {
            .overtime-header i {
                font-size: 18px;
            }
            
            .overtime-header span {
                font-size: 15px;
            }
            
            .overtime-details p {
                font-size: 13px;
            }
            
            .send-overtime-btn {
                width: 100%;
                justify-content: center;
                        padding: 10px;
                    }
        }
        
        @media (max-width: 480px) {
            .approval-message, .working-hours-info {
                padding: 10px;
            }
                    
            .approval-message p, .working-hours-info span {
                        font-size: 13px;
                    }
            
            .working-hours-info i {
                font-size: 18px;
            }
                }
            `;
            document.head.appendChild(style);
            
            // Insert after the header
            const modalHeader = document.querySelector('.punch-modal-header');
            modalHeader.insertAdjacentElement('afterend', successContainer);
            
            // If this is a punch out, remove the timer
            if (!isPunchIn && timerElement) {
                timerElement.style.display = 'none';
                
                // If we have an interval for the timer, clear it
                if (window.timerInterval) {
                    clearInterval(window.timerInterval);
                    window.timerInterval = null;
                }
            }
            
            // Update the punch button immediately without waiting for page refresh
            if (isPunchIn) {
                // Update the button to show "Punch Out" after punch in
                let buttonContent = '';
                
                // Add approval badge if outside geofence
                if (requiresApproval) {
                    buttonContent += '<span class="approval-badge">Attendance Pending</span>';
                    punchButton.setAttribute('data-approval', 'pending');
                }
                
                buttonContent += '<i class="fas fa-sign-out-alt"></i><span>Punch Out</span>';
                punchButton.innerHTML = buttonContent;
                punchButton.classList.add('punched-in');
                punchButton.setAttribute('data-status', 'punched-in');
            } else {
                // Update the button to show "Completed" after punch out
                let buttonContent = '';
                
                // Add approval badge if outside geofence
                if (requiresApproval) {
                    buttonContent += '<span class="approval-badge">Attendance Pending</span>';
                    punchButton.setAttribute('data-approval', 'pending');
                }
                
                buttonContent += '<i class="fas fa-check-circle"></i><span>Completed</span>';
                punchButton.innerHTML = buttonContent;
                punchButton.classList.remove('punched-in');
                punchButton.classList.add('completed');
                punchButton.setAttribute('data-status', 'completed');
                punchButton.disabled = true;
            }
            
            // Add event listener to close button
            document.querySelector('.success-close-btn').addEventListener('click', function() {
                window.closeModal();
            });
            
            // Auto close after 7 seconds
            setTimeout(window.closeModal, 7000);
        }
        
        // Function to show error message
        function showErrorMessage(message) {
            // Create error message container
            const errorContainer = document.createElement('div');
            errorContainer.className = 'error-message-container';
            
            errorContainer.innerHTML = `
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <p class="error-text">${message}</p>
                <button class="error-close-btn">Try Again</button>
            `;
            
            // Replace modal body with error
            const modalBody = document.querySelector('.punch-modal-body');
            modalBody.innerHTML = '';
            modalBody.appendChild(errorContainer);
            
            // Hide footer
            document.querySelector('.punch-modal-footer').style.display = 'none';
            
            // Add event listener to try again button
            document.querySelector('.error-close-btn').addEventListener('click', function() {
                window.closeModal();
            });
        }

        // Update live time every second
        function updateLiveTime() {
            // Create a new date object for current time
            const now = new Date();
            
            // Use server timezone (IST) directly from PHP
            // No need to add 5.5 hours if server is already in IST
            const serverHour = <?php echo (int)date('H'); ?>;
            const serverMinute = <?php echo (int)date('i'); ?>;
            const serverSecond = <?php echo (int)date('s'); ?>;
            
            // For client-side display, calculate current time based on server time
            // and elapsed seconds since page load
            const clientDate = new Date();
            const elapsedSeconds = Math.floor((clientDate - window.pageLoadTime) / 1000);
            
            // Add elapsed seconds to server time
            let totalSeconds = serverSecond + elapsedSeconds;
            let totalMinutes = serverMinute + Math.floor(totalSeconds / 60);
            let totalHours = serverHour + Math.floor(totalMinutes / 60);
            
            // Normalize values
            const seconds = totalSeconds % 60;
            const minutes = totalMinutes % 60;
            const hours = totalHours % 24;
            
            // Format for display (12-hour format)
            const displayHours = hours % 12 || 12;
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            // Update the time display
            const timeElement = document.getElementById('live-time');
            if (timeElement) {
                timeElement.textContent = `${displayHours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} ${ampm}`;
            }
        }

        // Update shift timer countdown or overtime
        function updateShiftTimer() {
            const timerElement = document.getElementById('shiftTimer');
            if (!timerElement) return;
            
            const isOvertime = timerElement.getAttribute('data-is-overtime') === 'true';
            const timerValueElement = document.getElementById('timerValue');
            if (!timerValueElement) return;
            
            // Get current timer value
            const timeValues = timerValueElement.textContent.split(':').map(Number);
            let hours = timeValues[0];
            let minutes = timeValues[1];
            let seconds = timeValues[2];
            
            if (isOvertime) {
                // For overtime, increment the timer
                seconds++;
                if (seconds >= 60) {
                    seconds = 0;
                    minutes++;
                    if (minutes >= 60) {
                        minutes = 0;
                        hours++;
                    }
                }
            } else {
                // For countdown, decrement the timer
                seconds--;
                if (seconds < 0) {
                    seconds = 59;
                    minutes--;
                    if (minutes < 0) {
                        minutes = 59;
                        hours--;
                        if (hours < 0) {
                            // Time's up, switch to overtime mode
                            timerElement.setAttribute('data-is-overtime', 'true');
                            timerElement.classList.add('overtime');
                            
                            // Change label
                            const timerLabelElement = timerElement.querySelector('.timer-label');
                            if (timerLabelElement) {
                                timerLabelElement.textContent = 'Overtime:';
                            }
                            
                            hours = 0;
                            minutes = 0;
                            seconds = 0;
                        }
                    }
                }
            }
            
            // Update timer display
            timerValueElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // Store page load time for accurate time calculation
        window.pageLoadTime = new Date();

        // Initialize time update
        updateLiveTime();

        // Set intervals to update time and timer
        setInterval(updateLiveTime, 1000);

        // Only set up timer interval if the shift timer exists
        if (document.getElementById('shiftTimer')) {
            window.timerInterval = setInterval(updateShiftTimer, 1000);
        }

        // Function to refresh the widget (for the refresh button)
        function refreshCompactTimeWidget() {
            updateLiveTime();
            if (document.getElementById('shiftTimer')) {
                updateShiftTimer();
            }
        }
    });

    // Format time from 24h to 12h format
    function formatTime(timeString) {
        if (!timeString) return '';
        
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours, 10);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        
        return `${hour12}:${minutes} ${ampm}`;
    }
    
    // Function to send overtime request
    async function sendOvertimeRequest(overtimeHours, shiftEndTime) {
        try {
            const sendButton = document.getElementById('sendOvertimeRequest');
            if (sendButton) {
                // Show loading state
                sendButton.disabled = true;
                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }
            
            const response = await fetch('ajax_handlers/submit_overtime_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?> + 
                      '&date=' + new Date().toISOString().split('T')[0] +
                      '&overtime_hours=' + overtimeHours +
                      '&shift_end_time=' + shiftEndTime
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                if (sendButton) {
                    sendButton.innerHTML = '<i class="fas fa-check"></i> Request Sent';
                    sendButton.classList.add('request-sent');
                }
            } else {
                // Show error
                if (sendButton) {
                    sendButton.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed';
                    sendButton.classList.add('request-failed');
                    
                    // Reset after 3 seconds
                    setTimeout(() => {
                        sendButton.disabled = false;
                        sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> Try Again';
                        sendButton.classList.remove('request-failed');
                    }, 3000);
                }
                console.error('Error sending overtime request:', data.message);
            }
        } catch (error) {
            console.error('Error sending overtime request:', error);
            
            // Reset button
            const sendButton = document.getElementById('sendOvertimeRequest');
            if (sendButton) {
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> Try Again';
                sendButton.classList.add('request-failed');
            }
        }
    }
    
    // Add this function to fetch working hours from the server
    async function fetchWorkingHours() {
        try {
            const response = await fetch('ajax_handlers/get_working_hours.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?> + 
                      '&date=' + new Date().toISOString().split('T')[0]
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data; // Return the entire data object instead of just working_hours
            } else {
                console.error('Error fetching working hours:', data.message);
                return null;
            }
        } catch (error) {
            console.error('Error fetching working hours:', error);
            return null;
        }
    }
</script> 