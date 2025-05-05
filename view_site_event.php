<?php
// Include necessary files
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get event ID from URL parameter
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if event ID is valid
if ($event_id <= 0) {
    header("Location: site_supervision.php");
    exit();
}

// Fetch event details
try {
    $query = "SELECT 
                se.*,
                u.username as created_by_name
              FROM 
                site_events se
              LEFT JOIN
                users u ON se.created_by = u.id
              WHERE 
                se.id = ?";
                
    $stmt = $pdo->prepare($query);
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If event not found, redirect back to calendar
    if (!$event) {
        header("Location: site_supervision.php");
        exit();
    }
    
} catch (PDOException $e) {
    // Log error and redirect to calendar
    error_log('Error fetching event details: ' . $e->getMessage());
    header("Location: site_supervision.php");
    exit();
}

// Page title
$pageTitle = "Event Details: " . htmlspecialchars($event['site_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | ArchitectsHive</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/dashboard/dashboard_styles.css">
    <style>
        .event-detail-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .event-header {
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 10px 10px 0 0;
            padding: 1.5rem;
            border-bottom: 2px solid #e74c3c;
        }
        
        .event-title {
            font-weight: 600;
            color: #34495e;
            margin-bottom: 0.5rem;
        }
        
        .event-date {
            color: #e74c3c;
            font-weight: 500;
        }
        
        .event-info {
            padding: 1.5rem;
        }
        
        .info-label {
            font-weight: 500;
            color: #7f8c8d;
        }
        
        .info-value {
            font-weight: 400;
            color: #34495e;
        }
        
        .event-actions {
            padding: 1rem;
            background-color: #f9f9f9;
            border-radius: 0 0 10px 10px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-dark">Site Event Details</h1>
                    <a href="site_supervision.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Calendar
                    </a>
                </div>
                
                <div class="card event-detail-card">
                    <div class="event-header">
                        <h2 class="event-title"><?= htmlspecialchars($event['site_name']) ?></h2>
                        <div class="event-date">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <?= date('l, F j, Y', strtotime($event['event_date'])) ?>
                        </div>
                    </div>
                    
                    <div class="event-info">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="info-label">Created By</div>
                                <div class="info-value">
                                    <i class="fas fa-user me-2"></i>
                                    <?= htmlspecialchars($event['created_by_name'] ?? 'Unknown') ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Created At</div>
                                <div class="info-value">
                                    <i class="fas fa-clock me-2"></i>
                                    <?= date('M j, Y g:i A', strtotime($event['created_at'])) ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Last Updated</div>
                                <div class="info-value">
                                    <i class="fas fa-sync-alt me-2"></i>
                                    <?= $event['updated_at'] ? date('M j, Y g:i A', strtotime($event['updated_at'])) : 'Never' ?>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Additional event details would go here -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This is a simple site event. You can add more details to this view based on your specific requirements.
                        </div>
                    </div>
                    
                    <div class="event-actions d-flex justify-content-end">
                        <a href="edit_site_event.php?id=<?= $event_id ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit"></i> Edit Event
                        </a>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEventModal">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteEventModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this event? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete_site_event.php" method="post">
                        <input type="hidden" name="event_id" value="<?= $event_id ?>">
                        <button type="submit" class="btn btn-danger">Delete Event</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 