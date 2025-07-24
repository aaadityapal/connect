<?php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug mode - can be enabled for troubleshooting
$debug_mode = false;

// Check if debug parameter is passed in URL
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    $debug_mode = true;
}

// Check if user is logged in and has appropriate role
$user_id = $_SESSION['user_id'] ?? 0;
$allowed_roles = ['HR', 'Admin', 'Senior Manager (Studio)', 'Senior Manager (Site)', 
                'Senior Manager (Marketing)', 'Senior Manager (Sales)'];

// Debug information
if ($debug_mode) {
    echo "<div style='padding: 10px; background: #f8f9fa; border: 1px solid #ddd; margin-bottom: 20px;'>";
    echo "<h4>Debug Information</h4>";
    echo "<p>User ID: " . $user_id . "</p>";
    echo "<p>Session Data: <pre>" . print_r($_SESSION, true) . "</pre></p>";
    echo "</div>";
}

// Check if the user is authorized
$user_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows == 0) {
    header('Location: index.php');
    exit();
}

$user_data = $user_result->fetch_assoc();
if (!in_array($user_data['role'], $allowed_roles)) {
    header('Location: unauthorized.php');
    exit();
}

// Handle approval/rejection actions
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (isset($_POST['attendance_id'])) {
        $attendance_id = intval($_POST['attendance_id']);
        $action = $_POST['action'];
        $comments = isset($_POST['comments']) ? $_POST['comments'] : '';
        
        if ($action === 'approve') {
            $update_query = "UPDATE attendance 
                            SET approval_status = 'approved', 
                                approval_timestamp = NOW(), 
                                manager_comments = ? 
                            WHERE id = ? AND manager_id = ?";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sii", $comments, $attendance_id, $user_id);
            
            if ($stmt->execute()) {
                $action_message = '<div class="alert alert-success">Attendance has been approved successfully.</div>';
            } else {
                $action_message = '<div class="alert alert-danger">Error approving attendance: ' . $stmt->error . '</div>';
            }
        } elseif ($action === 'reject') {
            if (empty($comments)) {
                $action_message = '<div class="alert alert-danger">Comments are required when rejecting attendance.</div>';
            } else {
                $update_query = "UPDATE attendance 
                                SET approval_status = 'rejected', 
                                    approval_timestamp = NOW(), 
                                    manager_comments = ? 
                                WHERE id = ? AND manager_id = ?";
                
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("sii", $comments, $attendance_id, $user_id);
                
                if ($stmt->execute()) {
                    $action_message = '<div class="alert alert-warning">Attendance has been rejected.</div>';
                } else {
                    $action_message = '<div class="alert alert-danger">Error rejecting attendance: ' . $stmt->error . '</div>';
                }
            }
        }
    }
}

// Fetch attendance records requiring approval
$query = "SELECT a.*, 
          u.username, 
          u.designation,
          u.profile_picture,
          u.employee_id,
          u.department,
          gl.name as location_name
          FROM attendance a
          LEFT JOIN users u ON a.user_id = u.id
          LEFT JOIN geofence_locations gl ON a.geofence_id = gl.id
          WHERE a.approval_status = 'pending'
          ORDER BY a.date DESC, a.punch_in DESC";

// For non-admin roles, only show records assigned to this manager
if (!in_array($user_data['role'], ['HR', 'Admin'])) {
    // We need to rebuild the query with the proper WHERE condition
    $query = "SELECT a.*, 
            u.username, 
            u.designation,
            u.profile_picture,
            u.employee_id,
            u.department,
            gl.name as location_name
            FROM attendance a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN geofence_locations gl ON a.geofence_id = gl.id
            WHERE a.approval_status = 'pending' 
            AND a.manager_id = ?
            ORDER BY a.date DESC, a.punch_in DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    
    // Debug information
    if ($debug_mode) {
        echo "<div style='padding: 10px; background: #f8f9fa; border: 1px solid #ddd; margin-bottom: 20px;'>";
        echo "<h4>Query Debug</h4>";
        echo "<p>Role: " . htmlspecialchars($user_data['role']) . "</p>";
        echo "<p>Query with manager filter: " . htmlspecialchars($query) . "</p>";
        echo "<p>Manager ID parameter: " . $user_id . "</p>";
        echo "</div>";
    }
} else {
    // For HR and Admin, show all pending records
    $stmt = $conn->prepare($query);
    
    // Debug information
    if ($debug_mode) {
        echo "<div style='padding: 10px; background: #f8f9fa; border: 1px solid #ddd; margin-bottom: 20px;'>";
        echo "<h4>Query Debug</h4>";
        echo "<p>Role: " . htmlspecialchars($user_data['role']) . "</p>";
        echo "<p>Query without manager filter: " . htmlspecialchars($query) . "</p>";
        echo "</div>";
    }
}

// Add error handling
if (!$stmt) {
    echo '<div class="alert alert-danger">Error preparing statement: ' . $conn->error . '</div>';
    exit;
}

$stmt->execute();
if ($stmt->error) {
    echo '<div class="alert alert-danger">Error executing query: ' . $stmt->error . '</div>';
    exit;
}

$result = $stmt->get_result();

// Get count for header
$pending_count = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Approval</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .approval-header {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        .approval-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
            position: relative;
            transition: all 0.3s;
        }
        .approval-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        .employee-info {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        .attendance-details {
            padding: 20px;
        }
        .map-container {
            height: 250px;
            margin-bottom: 20px;
        }
        .action-buttons {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .detail-row {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .detail-row .icon {
            width: 20px;
            margin-right: 10px;
            color: #6c757d;
        }
        .outside-reason {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .photo-evidence {
            margin: 20px 0;
        }
        .photo-evidence img {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 500;
        }
        .badge-outside {
            background-color: #ffe0b2;
            color: #e65100;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }
        .empty-state i {
            font-size: 60px;
            color: #d1d1d1;
            margin-bottom: 20px;
        }
        .comments-field {
            display: none;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="approval-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="fas fa-clipboard-check me-2"></i> Attendance Approval</h2>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <?php if (!empty($action_message)) echo $action_message; ?>
            <p class="text-muted">
                <i class="fas fa-info-circle me-1"></i> 
                You have <?php echo $pending_count; ?> attendance records requiring your approval.
            </p>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="approval-card" id="attendance-<?php echo $row['id']; ?>">
                    <div class="employee-info">
                        <div class="row">
                            <div class="col-md-1">
                                <img src="<?php echo !empty($row['profile_picture']) ? $row['profile_picture'] : 'assets/default-avatar.png'; ?>" 
                                     class="employee-avatar" alt="Employee">
                            </div>
                            <div class="col-md-7">
                                <h4><?php echo $row['username']; ?><?php echo !empty($row['designation']) ? ' (' . $row['designation'] . ')' : ''; ?></h4>
                                <p class="text-muted">
                                    <span class="me-3">
                                        <i class="fas fa-id-card"></i> <?php echo $row['employee_id']; ?>
                                    </span>
                                    <span class="me-3">
                                        <i class="fas fa-building"></i> <?php echo $row['department']; ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <h5>
                                    <?php if (empty($row['punch_out'])): ?>
                                        <span class="badge bg-info">Punch In Only</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Complete Attendance</span>
                                    <?php endif; ?>
                                    
                                    <span class="badge badge-outside">Outside Office</span>
                                </h5>
                                <p class="text-muted">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?php echo date('d M Y', strtotime($row['date'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="attendance-details">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Attendance Details</h5>
                                
                                <div class="detail-row">
                                    <div class="icon"><i class="fas fa-sign-in-alt"></i></div>
                                    <div><strong>Punch In:</strong> <?php echo date('h:i A', strtotime($row['punch_in'])); ?></div>
                                </div>
                                
                                <?php if (!empty($row['punch_out'])): ?>
                                    <div class="detail-row">
                                        <div class="icon"><i class="fas fa-sign-out-alt"></i></div>
                                        <div><strong>Punch Out:</strong> <?php echo date('h:i A', strtotime($row['punch_out'])); ?></div>
                                    </div>
                                    
                                    <div class="detail-row">
                                        <div class="icon"><i class="fas fa-clock"></i></div>
                                        <div><strong>Working Hours:</strong> <?php echo $row['working_hours']; ?></div>
                                    </div>
                                    
                                    <?php if (!empty($row['overtime_hours']) && $row['overtime_hours'] != '00:00:00'): ?>
                                        <div class="detail-row">
                                            <div class="icon"><i class="fas fa-business-time"></i></div>
                                            <div><strong>Overtime:</strong> <?php echo $row['overtime_hours']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="detail-row">
                                    <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                                    <div><strong>Location:</strong> <?php echo $row['location_name'] ?? 'Default Location'; ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="icon"><i class="fas fa-ruler-combined"></i></div>
                                    <div><strong>Distance:</strong> <?php echo round($row['distance_from_geofence']); ?> meters from allowed area</div>
                                </div>
                                
                                <?php if (!empty($row['punch_in_outside_reason'])): ?>
                                    <div class="outside-reason">
                                        <h6><i class="fas fa-comment-alt"></i> Punch In Reason:</h6>
                                        <p><?php echo htmlspecialchars($row['punch_in_outside_reason']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($row['punch_out_outside_reason'])): ?>
                                    <div class="outside-reason">
                                        <h6><i class="fas fa-comment-alt"></i> Punch Out Reason:</h6>
                                        <p><?php echo htmlspecialchars($row['punch_out_outside_reason']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($row['work_report'])): ?>
                                    <div class="card mt-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-file-alt"></i> Work Report</h6>
                                        </div>
                                        <div class="card-body">
                                            <p><?php echo nl2br(htmlspecialchars($row['work_report'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>Location & Evidence</h5>
                                
                                <div id="map-<?php echo $row['id']; ?>" class="map-container"></div>
                                
                                <div class="row">
                                    <?php if (!empty($row['punch_in_photo'])): ?>
                                        <div class="col-md-6">
                                            <div class="photo-evidence">
                                                <h6>Punch In Photo</h6>
                                                <img src="<?php echo $row['punch_in_photo']; ?>" class="img-fluid" alt="Punch In Photo">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($row['punch_out_photo'])): ?>
                                        <div class="col-md-6">
                                            <div class="photo-evidence">
                                                <h6>Punch Out Photo</h6>
                                                <img src="<?php echo $row['punch_out_photo']; ?>" class="img-fluid" alt="Punch Out Photo">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn btn-outline-secondary" 
                                onclick="toggleComments('<?php echo $row['id']; ?>')">
                            <i class="fas fa-comment"></i> Add Comments
                        </button>
                        
                        <button type="button" class="btn btn-success" 
                                onclick="approveAttendance('<?php echo $row['id']; ?>')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        
                        <button type="button" class="btn btn-danger" 
                                onclick="rejectAttendance('<?php echo $row['id']; ?>')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                    
                    <div class="comments-field p-3 bg-light" id="comments-<?php echo $row['id']; ?>">
                        <form method="post" class="approval-form" id="form-<?php echo $row['id']; ?>">
                            <input type="hidden" name="attendance_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="action" id="action-<?php echo $row['id']; ?>" value="">
                            
                            <div class="form-group">
                                <label for="comments-text-<?php echo $row['id']; ?>">Comments:</label>
                                <textarea class="form-control" id="comments-text-<?php echo $row['id']; ?>" 
                                          name="comments" rows="3" placeholder="Enter comments (required for rejection)"></textarea>
                            </div>
                            
                            <div class="mt-3 text-end">
                                <button type="submit" class="btn btn-primary">Submit</button>
                                <button type="button" class="btn btn-secondary" 
                                        onclick="toggleComments('<?php echo $row['id']; ?>')">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                    // Initialize map for this attendance record
                    document.addEventListener('DOMContentLoaded', function() {
                        var map = L.map('map-<?php echo $row['id']; ?>').setView([<?php echo $row['punch_in_latitude']; ?>, <?php echo $row['punch_in_longitude']; ?>], 15);
                        
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(map);
                        
                        // Add marker for punch in location
                        L.marker([<?php echo $row['punch_in_latitude']; ?>, <?php echo $row['punch_in_longitude']; ?>])
                            .addTo(map)
                            .bindPopup('Punch In Location')
                            .openPopup();
                        
                        <?php if (!empty($row['punch_out'])): ?>
                            // Add marker for punch out location if exists
                            L.marker([<?php echo $row['punch_out_latitude']; ?>, <?php echo $row['punch_out_longitude']; ?>])
                                .addTo(map)
                                .bindPopup('Punch Out Location');
                        <?php endif; ?>
                        
                        // Add circle for geofence
                        <?php if (!empty($row['geofence_id'])): ?>
                            // Fetch geofence details
                            <?php
                            $geo_query = "SELECT latitude, longitude, radius FROM geofence_locations WHERE id = ?";
                            $geo_stmt = $conn->prepare($geo_query);
                            $geo_stmt->bind_param("i", $row['geofence_id']);
                            $geo_stmt->execute();
                            $geo_result = $geo_stmt->get_result();
                            if ($geo_result->num_rows > 0) {
                                $geo = $geo_result->fetch_assoc();
                                echo "L.circle([{$geo['latitude']}, {$geo['longitude']}], {
                                    color: 'blue',
                                    fillColor: '#30f',
                                    fillOpacity: 0.2,
                                    radius: {$geo['radius']}
                                }).addTo(map);";
                            }
                            ?>
                        <?php endif; ?>
                    });
                </script>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-check-circle"></i>
                <h3>All Caught Up!</h3>
                <p>There are no pending attendance records requiring your approval.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleComments(id) {
            document.getElementById('comments-' + id).style.display = 
                document.getElementById('comments-' + id).style.display === 'block' ? 'none' : 'block';
        }
        
        function approveAttendance(id) {
            document.getElementById('action-' + id).value = 'approve';
            document.getElementById('form-' + id).submit();
        }
        
        function rejectAttendance(id) {
            toggleComments(id);
            document.getElementById('action-' + id).value = 'reject';
            // Form will be submitted when the user clicks the submit button in the comments section
            
            // Make comments required for rejection
            document.getElementById('comments-text-' + id).setAttribute('required', 'required');
        }
    </script>
</body>
</html> 