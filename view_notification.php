<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if notification ID and type are provided
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    header('Location: similar_dashboard.php');
    exit;
}

$notification_id = $_GET['id'];
$notification_type = $_GET['type'];
$user_id = $_SESSION['user_id'];
$error = '';
$notification = null;

// Mark as read in database
function markAsRead($conn, $user_id, $notification_type, $notification_id) {
    $check_sql = "SELECT id FROM notification_read_status 
                 WHERE user_id = ? AND notification_type = ? AND source_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('isi', $user_id, $notification_type, $notification_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $current_time = date('Y-m-d H:i:s');
        $sql = "INSERT INTO notification_read_status 
               (user_id, notification_type, source_id, read_at) 
               VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isis', $user_id, $notification_type, $notification_id, $current_time);
        $stmt->execute();
    }
}

// Fetch notification details based on type
try {
    switch ($notification_type) {
        case 'announcement':
            $sql = "SELECT a.*, 'announcement' as type FROM announcements a WHERE a.id = ?";
            break;
        case 'circular':
            $sql = "SELECT c.*, 'circular' as type FROM circulars c WHERE c.id = ?";
            break;
        case 'event':
            $sql = "SELECT e.*, 'event' as type FROM events e WHERE e.id = ?";
            break;
        case 'holiday':
            $sql = "SELECT h.*, 'holiday' as type FROM holidays h WHERE h.id = ?";
            break;
        default:
            throw new Exception("Invalid notification type");
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $notification_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Notification not found");
    }
    
    $notification = $result->fetch_assoc();
    
    // Mark notification as read
    markAsRead($conn, $user_id, $notification_type, $notification_id);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Helper function to get formatted date
function formatDate($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('F j, Y');
}

// Get icon and color based on notification type
function getNotificationTypeInfo($type) {
    switch ($type) {
        case 'announcement':
            return ['icon' => 'fas fa-bullhorn', 'color' => '#007bff', 'label' => 'Announcement'];
        case 'circular':
            return ['icon' => 'fas fa-file', 'color' => '#28a745', 'label' => 'Circular'];
        case 'event':
            return ['icon' => 'fas fa-calendar', 'color' => '#6f42c1', 'label' => 'Event'];
        case 'holiday':
            return ['icon' => 'fas fa-calendar-alt', 'color' => '#fd7e14', 'label' => 'Holiday'];
        default:
            return ['icon' => 'fas fa-bell', 'color' => '#6c757d', 'label' => 'Notification'];
    }
}

// Get type info
$typeInfo = $notification ? getNotificationTypeInfo($notification_type) : getNotificationTypeInfo('');

// Page title
$pageTitle = $notification ? $typeInfo['label'] . ': ' . $notification['title'] : 'Notification Not Found';

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <?php if ($error): ?>
                    <div class="card-header bg-danger text-white">
                        <h4>Error</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                        <a href="similar_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                    </div>
                <?php elseif ($notification): ?>
                    <div class="card-header" style="background-color: <?php echo $typeInfo['color']; ?>; color: white;">
                        <div class="d-flex align-items-center">
                            <i class="<?php echo $typeInfo['icon']; ?> mr-2"></i>
                            <h4 class="mb-0"><?php echo $typeInfo['label']; ?></h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($notification['title']); ?></h3>
                        
                        <div class="notification-meta text-muted mb-4">
                            <div>
                                <i class="far fa-calendar"></i> 
                                Published: <?php echo formatDate($notification['created_at']); ?>
                            </div>
                            
                            <?php if ($notification_type === 'announcement' && !empty($notification['display_until'])): ?>
                                <div>
                                    <i class="far fa-clock"></i>
                                    Valid until: <?php echo formatDate($notification['display_until']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($notification_type === 'circular' && !empty($notification['valid_until'])): ?>
                                <div>
                                    <i class="far fa-clock"></i>
                                    Valid until: <?php echo formatDate($notification['valid_until']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($notification_type === 'event'): ?>
                                <?php if (!empty($notification['start_date'])): ?>
                                <div>
                                    <i class="fas fa-play"></i>
                                    Starts: <?php echo formatDate($notification['start_date']); ?>
                                    <?php if (!empty($notification['start_time'])): ?>
                                        at <?php echo $notification['start_time']; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($notification['end_date'])): ?>
                                <div>
                                    <i class="fas fa-stop"></i>
                                    Ends: <?php echo formatDate($notification['end_date']); ?>
                                    <?php if (!empty($notification['end_time'])): ?>
                                        at <?php echo $notification['end_time']; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($notification['location'])): ?>
                                <div>
                                    <i class="fas fa-map-marker-alt"></i>
                                    Location: <?php echo htmlspecialchars($notification['location']); ?>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($notification_type === 'holiday' && !empty($notification['holiday_date'])): ?>
                                <div>
                                    <i class="far fa-calendar-check"></i>
                                    Date: <?php echo formatDate($notification['holiday_date']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-content mb-4">
                            <?php 
                            // Display message or description
                            $content = isset($notification['message']) 
                                     ? $notification['message'] 
                                     : (isset($notification['description']) ? $notification['description'] : '');
                            
                            // Check if content contains HTML
                            if ($content == strip_tags($content)) {
                                // Plain text - add paragraphs
                                echo nl2br(htmlspecialchars($content));
                            } else {
                                // Already has HTML formatting
                                echo $content;
                            }
                            ?>
                        </div>
                        
                        <?php if (!empty($notification['attachments'])): ?>
                        <div class="attachments-section mb-4">
                            <h5><i class="fas fa-paperclip"></i> Attachments</h5>
                            <div class="list-group">
                                <?php foreach(json_decode($notification['attachments']) as $attachment): ?>
                                <a href="<?php echo $attachment->path; ?>" class="list-group-item list-group-item-action" target="_blank">
                                    <i class="fas fa-file"></i> <?php echo $attachment->name; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <a href="similar_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 