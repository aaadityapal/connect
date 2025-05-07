<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has the 'Site Supervisor' role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Site Supervisor') {
    header("Location: unauthorized.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Supervisor Dashboard</title>
    
    <!-- Include CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    
    <!-- Include custom styles -->
    <style>
        /* Base styles for quick display - main styles in CSS file */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .main-content.collapsed {
            margin-left: 70px;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
                padding-top: 60px;
            }
            
            .hamburger-menu {
                display: flex !important;
            }
        }
        
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Hamburger menu style */
        .hamburger-menu {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: #2c3e50;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .hamburger-menu i {
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Hamburger Menu for Mobile -->
    <div class="hamburger-menu" id="hamburgerMenu" onclick="toggleMobilePanel()">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Include Left Panel -->
    <?php include 'includes/supervisor_panel.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
        
            
            <!-- Greetings Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card greetings-card">
                        <div class="greetings-header">
                            <div class="greeting-time">
                                <?php
                                // Set timezone to Indian Standard Time
                                date_default_timezone_set('Asia/Kolkata');
                                
                                $hour = date('H');
                                $greeting = '';
                                if ($hour >= 5 && $hour < 12) {
                                    $greeting = 'Good Morning';
                                    $icon = 'fa-sun';
                                    $greet_class = 'morning';
                                } elseif ($hour >= 12 && $hour < 18) {
                                    $greeting = 'Good Afternoon';
                                    $icon = 'fa-cloud-sun';
                                    $greet_class = 'afternoon';
                                } else {
                                    $greeting = 'Good Evening';
                                    $icon = 'fa-moon';
                                    $greet_class = 'evening';
                                }
                                ?>
                                <h4 class="greeting <?php echo $greet_class; ?>"><i class="fas <?php echo $icon; ?>"></i> <?php echo $greeting; ?>, <?php echo isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Supervisor'; ?>!</h4>
                                <div class="date-time" id="live-datetime">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?> <small>(IST)</small></span>
                                    <span><i class="fas fa-clock"></i> <span id="live-time"><?php echo date('h:i:s A'); ?></span></span>
                                </div>
                            </div>
                            <div class="greeting-actions">
                                <div class="notification-icon">
                                    <a href="#" class="notification-bell">
                                        <i class="fas fa-bell"></i>
                                        <span class="notification-badge">3</span>
                                    </a>
                                </div>
                                <div class="punch-button">
                                    <?php
                                    // Assume this is the simple check to see if user is punched in
                                    $isPunchedIn = false;
                                    if (isset($_SESSION['punched_in']) && $_SESSION['punched_in'] === true) {
                                        $isPunchedIn = true;
                                    }
                                    ?>
                                    <button id="punchButton" class="btn <?php echo $isPunchedIn ? 'btn-danger' : 'btn-success'; ?> btn-sm">
                                        <i class="fas <?php echo $isPunchedIn ? 'fa-sign-out-alt' : 'fa-sign-in-alt'; ?>"></i>
                                        <?php echo $isPunchedIn ? 'Punch Out' : 'Punch In'; ?>
                                        <span class="punch-button-status <?php echo $isPunchedIn ? 'status-in' : 'status-out'; ?>"></span>
                                    </button>
                                    <?php if($isPunchedIn && isset($_SESSION['punch_in_time'])): ?>
                                    <div class="punch-time">Since: <?php echo date('h:i A', strtotime($_SESSION['punch_in_time'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Overview Row -->
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3>42</h3>
                            <p>Active Workers</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-details">
                            <h3>8</h3>
                            <p>Active Projects</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-details">
                            <h3>5</h3>
                            <p>Pending Issues</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3>12</h3>
                            <p>Completed Tasks</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity and Tasks Row -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Recent Site Activities</h4>
                        <div class="activity-timeline">
                            <div class="activity-item">
                                <div class="activity-icon bg-primary">
                                    <i class="fas fa-hammer"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Foundation work completed for Building B</p>
                                    <p class="activity-time">Today, 10:30 AM</p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">New materials delivery received</p>
                                    <p class="activity-time">Yesterday, 2:15 PM</p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-hard-hat"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Safety inspection completed</p>
                                    <p class="activity-time">Yesterday, 11:00 AM</p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon bg-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Minor issue reported in electrical wiring</p>
                                    <p class="activity-time">May 22, 9:45 AM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Upcoming Tasks</h4>
                        <div class="task-list">
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task1">
                                    <label class="form-check-label" for="task1">
                                        Complete daily inspection report
                                    </label>
                                </div>
                                <span class="badge badge-warning">Today</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task2">
                                    <label class="form-check-label" for="task2">
                                        Review worker attendance
                                    </label>
                                </div>
                                <span class="badge badge-info">Today</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task3">
                                    <label class="form-check-label" for="task3">
                                        Coordinate with material suppliers
                                    </label>
                                </div>
                                <span class="badge badge-primary">Tomorrow</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task4">
                                    <label class="form-check-label" for="task4">
                                        Prepare weekly progress report
                                    </label>
                                </div>
                                <span class="badge badge-success">May 25</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task5">
                                    <label class="form-check-label" for="task5">
                                        Attend site management meeting
                                    </label>
                                </div>
                                <span class="badge badge-secondary">May 26</span>
                            </div>
                        </div>
                        
                        <a href="#" class="btn btn-outline-primary btn-sm mt-3">View All Tasks</a>
                    </div>
                </div>
            </div>
            
            <!-- Project Progress Row -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Project Progress</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Status</th>
                                        <th>Deadline</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Building A Construction</td>
                                        <td><span class="badge badge-success">On Track</span></td>
                                        <td>June 30, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">75%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Foundation Work Building B</td>
                                        <td><span class="badge badge-warning">Slight Delay</span></td>
                                        <td>July 15, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: 45%" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100">45%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Interior Finishing Phase 1</td>
                                        <td><span class="badge badge-danger">Delayed</span></td>
                                        <td>June 10, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: 30%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100">30%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Electrical Installation</td>
                                        <td><span class="badge badge-success">On Track</span></td>
                                        <td>August 5, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100">60%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/supervisor/dashboard.js"></script>
    
    <!-- Live Time Script -->
    <script>
        // Function to update time
        function updateTime() {
            const now = new Date();
            
            // Convert to IST (UTC+5:30)
            const istTime = new Date(now.getTime() + (5.5 * 60 * 60 * 1000));
            
            let hours = istTime.getUTCHours();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            
            const minutes = istTime.getUTCMinutes().toString().padStart(2, '0');
            const seconds = istTime.getUTCSeconds().toString().padStart(2, '0');
            
            document.getElementById('live-time').textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        
        // Initial call to display time immediately
        updateTime();
        
        // Punch in/out functionality
        document.getElementById('punchButton').addEventListener('click', function() {
            const isPunchedIn = this.classList.contains('btn-danger');
            const button = this;
            
            // Action based on current state
            const action = isPunchedIn ? 'out' : 'in';
            
            // Open camera modal for capturing photo
            openCameraModal(action, function(photoData, locationData) {
                // Show loading state after photo is captured
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                const currentTime = document.getElementById('live-time').textContent;
                const punchTimeElem = document.createElement('div');
                punchTimeElem.className = 'punch-time';
                
                // Prepare form data with punch details
                const formData = new FormData();
                formData.append('action', action);
                formData.append('photo', photoData);
                formData.append('latitude', locationData.latitude || '');
                formData.append('longitude', locationData.longitude || '');
                formData.append('accuracy', locationData.accuracy || '');
                formData.append('address', locationData.address || 'Not available');
                formData.append('device_info', navigator.userAgent);
                
                // Send data to server
                fetch('punch_action.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
                    }
                    return response.text(); // First get as text to debug
                })
                .then(text => {
                    // Try to parse as JSON, but log the raw text if it fails
                    try {
                        // Check if the response begins with HTML or error tags
                        if (text.trim().startsWith('<')) {
                            console.error('Received HTML instead of JSON:', text);
                            throw new Error('Server returned HTML instead of JSON');
                        }
                        
                        const data = JSON.parse(text);
                        if (data.status === 'success') {
                            // Update button state
                            if (isPunchedIn) {
                                // Switched to punched out
                                button.classList.remove('btn-danger');
                                button.classList.add('btn-success');
                                button.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In <span class="punch-button-status status-out"></span>';
                                
                                // Remove any existing punch time indicator
                                const existingPunchTime = button.parentElement.querySelector('.punch-time');
                                if (existingPunchTime) {
                                    existingPunchTime.remove();
                                }
                                
                                // Show toast notification
                                showToast('Punched out successfully', 'success', 'You worked for ' + (data.hours_worked || 'some time'));
                            } else {
                                // Switched to punched in
                                button.classList.remove('btn-success');
                                button.classList.add('btn-danger');
                                button.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out <span class="punch-button-status status-in"></span>';
                                
                                // Add punch time indicator
                                punchTimeElem.innerHTML = 'Since: ' + currentTime;
                                button.parentElement.appendChild(punchTimeElem);
                                
                                // Show toast notification
                                showToast('Punched in successfully', 'success', 'Punch time recorded: ' + currentTime);
                            }
                        } else {
                            // Error handling
                            button.innerHTML = originalText;
                            
                            // Show the specific error message
                            showToast('Action failed', 'danger', data.message || 'Please try again');
                            
                            // If there's a photo error, show it
                            if (data.photo_error) {
                                console.error('Photo error:', data.photo_error);
                                showToast('Photo error', 'warning', data.photo_error);
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        console.log('Raw response:', text);
                        
                        // More detailed error message
                        let errorMsg = 'Could not process server response';
                        if (text.includes('PHP')) {
                            errorMsg = 'PHP error detected. Please check server logs.';
                        } else if (text.trim().startsWith('<')) {
                            errorMsg = 'Server returned HTML instead of JSON.';
                        }
                        
                        showToast('Response Error', 'danger', errorMsg);
                        button.innerHTML = originalText;
                    }
                    button.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showToast('Connection error', 'danger', error.message || 'Please check your connection and try again');
                });
            });
        });
        
        // Function to open camera modal
        function openCameraModal(action, callback) {
            // Create modal if it doesn't exist
            let cameraModal = document.getElementById('camera-modal');
            if (!cameraModal) {
                // Create modal container
                cameraModal = document.createElement('div');
                cameraModal.id = 'camera-modal';
                cameraModal.className = 'camera-modal';
                
                // Create modal content HTML
                cameraModal.innerHTML = `
                    <div class="camera-modal-content">
                        <div class="camera-header">
                            <h4 id="camera-title">Take Photo for Punch In</h4>
                            <button class="camera-close">&times;</button>
                        </div>
                        <div class="camera-body">
                            <div class="video-container">
                                <video id="camera-video" playsinline autoplay></video>
                                <canvas id="camera-canvas" style="display:none;"></canvas>
                                <div class="camera-overlay">
                                    <div class="camera-frame"></div>
                                </div>
                                <div id="camera-error" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); color:white; text-align:center; padding:20px; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <p><i class="fas fa-exclamation-triangle" style="font-size:2rem; color:#f39c12; margin-bottom:10px; display:block;"></i>Camera could not be accessed</p>
                                    <p id="camera-error-message">Please try using a different device or check camera permissions</p>
                                    <button id="retry-camera-btn" class="btn btn-warning mt-3"><i class="fas fa-redo"></i> Retry Camera</button>
                                </div>
                                <button id="rotate-camera-btn" class="btn btn-info camera-rotate-btn"><i class="fas fa-sync"></i></button>
                            </div>
                            <div id="photo-preview" style="display:none;">
                                <img id="captured-photo" src="" alt="Captured photo">
                            </div>
                            <div class="location-info">
                                <p><i class="fas fa-map-marker-alt"></i> <span id="location-status">Getting location...</span></p>
                                <p id="location-address" class="location-address"><i class="fas fa-map"></i> <span>Fetching address...</span></p>
                            </div>
                        </div>
                        <div class="camera-footer">
                            <button id="capture-btn" class="btn btn-primary"><i class="fas fa-camera"></i> Capture</button>
                            <button id="retake-btn" class="btn btn-secondary" style="display:none;"><i class="fas fa-redo"></i> Retake</button>
                            <button id="confirm-btn" class="btn btn-success" style="display:none;"><i class="fas fa-check"></i> Confirm</button>
                            <button id="skip-photo-btn" class="btn btn-outline-secondary"><i class="fas fa-forward"></i> Skip Photo</button>
                        </div>
                    </div>
                `;
                
                // Add to body
                document.body.appendChild(cameraModal);
                
                // Add modal styles
                if (!document.getElementById('camera-modal-styles')) {
                    const style = document.createElement('style');
                    style.id = 'camera-modal-styles';
                    style.innerHTML = `
                        .camera-modal {
                            position: fixed;
                            z-index: 9999;
                            left: 0;
                            top: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.9);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            opacity: 0;
                            transition: opacity 0.3s ease;
                            pointer-events: none;
                        }
                        .camera-modal.active {
                            opacity: 1;
                            pointer-events: all;
                        }
                        .camera-modal-content {
                            background-color: white;
                            border-radius: 10px;
                            width: 90%;
                            max-width: 500px;
                            overflow: hidden;
                            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                        }
                        .camera-header {
                            padding: 15px;
                            background-color: var(--primary-color);
                            color: white;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        .camera-header h4 {
                            margin: 0;
                            font-size: 1.2rem;
                        }
                        .camera-close {
                            background: none;
                            border: none;
                            font-size: 1.5rem;
                            color: white;
                            cursor: pointer;
                        }
                        .camera-body {
                            padding: 15px;
                        }
                        .video-container {
                            position: relative;
                            width: 100%;
                            height: 0;
                            padding-bottom: 75%;
                            background: #f0f0f0;
                            overflow: hidden;
                            border-radius: 5px;
                            margin-bottom: 15px;
                        }
                        #camera-video, #captured-photo {
                            position: absolute;
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            background: #000;
                        }
                        .camera-overlay {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            pointer-events: none;
                        }
                        .camera-frame {
                            width: 80%;
                            height: 80%;
                            border: 2px dashed rgba(255,255,255,0.7);
                            border-radius: 10px;
                        }
                        .camera-rotate-btn {
                            position: absolute;
                            top: 10px;
                            right: 10px;
                            z-index: 10;
                            border-radius: 50%;
                            width: 40px;
                            height: 40px;
                            padding: 0;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .location-info {
                            background: #f5f5f5;
                            padding: 10px;
                            border-radius: 5px;
                            font-size: 0.9rem;
                            margin-top: 10px;
                        }
                        .location-info p {
                            margin-bottom: 5px;
                        }
                        .location-info i {
                            color: var(--primary-color);
                            width: 20px;
                            text-align: center;
                            margin-right: 5px;
                        }
                        .location-address {
                            font-style: normal;
                            word-break: break-word;
                        }
                        .location-success {
                            color: #2ecc71;
                        }
                        .location-error {
                            color: #e74c3c;
                        }
                        .camera-footer {
                            padding: 15px;
                            background: #f9f9f9;
                            display: flex;
                            justify-content: center;
                            gap: 10px;
                        }
                        #photo-preview {
                            position: relative;
                            width: 100%;
                            height: 0;
                            padding-bottom: 75%;
                            background: #f0f0f0;
                            border-radius: 5px;
                            margin-bottom: 15px;
                            overflow: hidden;
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
            
            // Update modal title based on action
            document.getElementById('camera-title').textContent = `Take Photo for Punch ${action === 'in' ? 'In' : 'Out'}`;
            
            // Show modal
            cameraModal.classList.add('active');
            
            // Elements
            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('camera-canvas');
            const captureBtn = document.getElementById('capture-btn');
            const retakeBtn = document.getElementById('retake-btn');
            const confirmBtn = document.getElementById('confirm-btn');
            const closeBtn = document.querySelector('.camera-close');
            const photoPreview = document.getElementById('photo-preview');
            const videoContainer = document.querySelector('.video-container');
            const locationStatus = document.getElementById('location-status');
            const cameraError = document.getElementById('camera-error');
            const skipPhotoBtn = document.getElementById('skip-photo-btn');
            
            // Location data
            let locationData = {};
            const locationAddress = document.getElementById('location-address');
            
            // Start location tracking
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        locationData = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        };
                        locationStatus.innerHTML = `Location found (Accuracy: ${Math.round(position.coords.accuracy)}m)`;
                        locationStatus.className = 'location-success';
                        
                        // Call reverse geocoding to get address
                        getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
                    },
                    function(error) {
                        locationStatus.innerHTML = 'Unable to get location: ' + error.message;
                        locationStatus.className = 'location-error';
                        locationAddress.style.display = 'none';
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                locationStatus.innerHTML = 'Geolocation is not supported by this browser';
                locationStatus.className = 'location-error';
                locationAddress.style.display = 'none';
            }
            
            // Function to get address from coordinates using reverse geocoding
            function getAddressFromCoordinates(latitude, longitude) {
                // Show loading state
                locationAddress.querySelector('span').textContent = 'Fetching address...';
                
                // Use Nominatim API for reverse geocoding (free and no API key required)
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`;
                
                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'User-Agent': 'HR Attendance System' // Nominatim requires a user agent
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Geocoding service failed');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.display_name) {
                        // Store the address in locationData
                        locationData.address = data.display_name;
                        
                        // Display a shorter version of the address
                        let displayAddress = data.display_name;
                        if (displayAddress.length > 60) {
                            displayAddress = displayAddress.substring(0, 57) + '...';
                        }
                        
                        locationAddress.querySelector('span').textContent = displayAddress;
                        locationAddress.title = data.display_name; // Show full address on hover
                    } else {
                        throw new Error('No address found');
                    }
                })
                .catch(error => {
                    console.error('Error getting address:', error);
                    locationAddress.querySelector('span').textContent = 'Address could not be determined';
                });
            }
            
            // Variables for camera facing mode
            let currentFacingMode = 'user';
            let stream = null;
            
            // Start camera stream with specified facing mode
            function startCamera(facingMode) {
                // Hide error message initially
                cameraError.style.display = 'none';
                captureBtn.disabled = false;
                
                // Check if the browser supports the permissions API
                if (navigator.permissions && navigator.permissions.query) {
                    // Check camera permissions
                    navigator.permissions.query({name: 'camera'})
                    .then(function(permissionStatus) {
                        console.log('Camera permission status:', permissionStatus.state);
                        
                        if (permissionStatus.state === 'denied') {
                            // Permission explicitly denied
                            showCameraError("Camera permission denied. Please check your browser settings.");
                            return;
                        }
                        
                        // Continue with camera initialization
                        initializeCamera(facingMode);
                        
                        // Listen for permission changes
                        permissionStatus.onchange = function() {
                            console.log('Permission state changed to:', this.state);
                            if (this.state === 'granted') {
                                initializeCamera(currentFacingMode);
                            } else if (this.state === 'denied') {
                                showCameraError("Camera permission was denied");
                            }
                        };
                    })
                    .catch(function(error) {
                        console.error("Error checking permissions:", error);
                        // Fall back to direct camera access
                        initializeCamera(facingMode);
                    });
                } else {
                    // Browser doesn't support permission API, try direct camera access
                    console.log('Permissions API not supported, trying direct camera access');
                    initializeCamera(facingMode);
                }
            }
            
            // Function to initialize the camera
            function initializeCamera(facingMode) {
                // Stop any existing stream
                if (stream) {
                    stream.getTracks().forEach(track => {
                        track.stop();
                    });
                }
                
                // Hardware constraints
                const constraints = {
                    video: {
                        facingMode: facingMode,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };
                
                // Start new stream with specified facing mode
                navigator.mediaDevices.getUserMedia(constraints)
                .then(function(mediaStream) {
                    stream = mediaStream;
                    video.srcObject = mediaStream;
                    
                    // Promise to check if video is actually playing
                    const playPromise = video.play();
                    
                    if (playPromise !== undefined) {
                        playPromise
                        .then(() => {
                            // Video is playing successfully
                            currentFacingMode = facingMode;
                            console.log('Camera started successfully with facing mode:', facingMode);
                        })
                        .catch(error => {
                            console.error('Error playing video:', error);
                            showCameraError("Error starting video playback. Please reload the page.");
                        });
                    }
                    
                    // Verify we're actually getting frames from the camera after a short delay
                    setTimeout(function() {
                        if (video.readyState < 2) { // HAVE_CURRENT_DATA or less
                            showCameraError("Camera connected but not providing video. Try reloading the page.");
                        }
                    }, 3000);
                })
                .catch(function(err) {
                    console.error("Error accessing camera: ", err);
                    
                    // Different error message based on error type
                    if (err.name === 'NotAllowedError') {
                        showCameraError("Camera access denied. Please allow camera access in your browser settings.");
                    } else if (err.name === 'NotFoundError') {
                        showCameraError("No camera found on this device. Try using a different device.");
                    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                        showCameraError("Camera is in use by another application or not available.");
                    } else if (err.name === 'OverconstrainedError') {
                        // Try again with relaxed constraints
                        navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                        .then(function(mediaStream) {
                            stream = mediaStream;
                            video.srcObject = mediaStream;
                            video.play();
                        })
                        .catch(function(fallbackErr) {
                            showCameraError("Camera not available: " + fallbackErr.message);
                        });
                    } else {
                        showCameraError("Camera error: " + err.message);
                    }
                });
            }
            
            // Helper function to show camera error
            function showCameraError(message) {
                document.getElementById('camera-error-message').textContent = message;
                cameraError.style.display = 'flex';
                captureBtn.disabled = true;
                locationStatus.className = 'location-error';
            }
            
            // Add event listener to check when video actually starts playing
            video.addEventListener('playing', function() {
                // Hide error if video is actually playing
                cameraError.style.display = 'none';
                captureBtn.disabled = false;
            });
            
            // Start camera with front-facing camera by default
            startCamera('user');
            
            // Rotate camera button
            const rotateCameraBtn = document.getElementById('rotate-camera-btn');
            rotateCameraBtn.addEventListener('click', function() {
                // Toggle between front and rear cameras
                const newFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
                startCamera(newFacingMode);
            });
            
            // Photo data
            let photoData = null;
            
            // Capture photo
            captureBtn.addEventListener('click', function() {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                photoData = canvas.toDataURL('image/jpeg', 0.8);
                document.getElementById('captured-photo').src = photoData;
                
                // Show preview and confirm buttons
                videoContainer.style.display = 'none';
                photoPreview.style.display = 'block';
                captureBtn.style.display = 'none';
                retakeBtn.style.display = 'inline-block';
                confirmBtn.style.display = 'inline-block';
            });
            
            // Retake photo
            retakeBtn.addEventListener('click', function() {
                photoPreview.style.display = 'none';
                videoContainer.style.display = 'block';
                captureBtn.style.display = 'inline-block';
                retakeBtn.style.display = 'none';
                confirmBtn.style.display = 'none';
                photoData = null;
            });
            
            // Confirm photo and location
            confirmBtn.addEventListener('click', function() {
                if (photoData) {
                    // Close modal and stop camera
                    closeCamera();
                    
                    // Call the callback with captured data
                    callback(photoData, locationData);
                } else {
                    showToast('Error', 'danger', 'Please capture a photo first');
                }
            });
            
            // Close modal and cleanup
            closeBtn.addEventListener('click', closeCamera);
            
            // Skip photo button
            skipPhotoBtn.addEventListener('click', function() {
                // Close camera and proceed without photo
                closeCamera();
                callback(null, locationData);
            });
            
            // File upload is disabled - we're using camera rotation instead
            function closeCamera() {
                cameraModal.classList.remove('active');
                
                // Stop camera stream
                if (stream) {
                    stream.getTracks().forEach(track => {
                        track.stop();
                    });
                }
                
                // Reset UI state
                videoContainer.style.display = 'block';
                photoPreview.style.display = 'none';
                captureBtn.style.display = 'inline-block';
                retakeBtn.style.display = 'none';
                confirmBtn.style.display = 'none';
                skipPhotoBtn.style.display = 'inline-block';
                cameraError.style.display = 'none';
                captureBtn.disabled = false;
            }
            
            // Retry camera button
            document.getElementById('retry-camera-btn').addEventListener('click', function() {
                // Try to reinitialize camera
                startCamera(currentFacingMode);
            });
        }
        
        // Function to show toast notifications
        function showToast(title, type, message) {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'toast-container';
                document.body.appendChild(toastContainer);
                
                // Add toast container styles if they don't exist
                if (!document.getElementById('toast-styles')) {
                    const style = document.createElement('style');
                    style.id = 'toast-styles';
                    style.innerHTML = `
                        .toast-container {
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            z-index: 9999;
                        }
                        .toast {
                            background: white;
                            border-radius: 8px;
                            padding: 15px 20px;
                            margin-bottom: 10px;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            display: flex;
                            flex-direction: column;
                            min-width: 250px;
                            max-width: 350px;
                            transform: translateX(100%);
                            opacity: 0;
                            transition: all 0.3s ease;
                        }
                        .toast.show {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        .toast-header {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 8px;
                            font-weight: bold;
                        }
                        .toast-body {
                            font-size: 0.9rem;
                            color: #666;
                        }
                        .toast-success {
                            border-left: 4px solid #2ecc71;
                        }
                        .toast-danger {
                            border-left: 4px solid #e74c3c;
                        }
                        .toast-close {
                            background: none;
                            border: none;
                            font-size: 1rem;
                            cursor: pointer;
                            color: #999;
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-header">
                    <span>${title}</span>
                    <button class="toast-close">&times;</button>
                </div>
                <div class="toast-body">${message}</div>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Show toast (delayed to allow animation)
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Set up close button
            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            });
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
        }
        
        // Notification bell click
        document.querySelector('.notification-bell').addEventListener('click', function(e) {
            e.preventDefault();
            alert('You have 3 unread notifications');
            // In a real app, this would open a notification panel
        });
    </script>
</body>
</html> 