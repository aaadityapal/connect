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

$userId = $_SESSION['user_id'];
$errors = [];
$success = false;
$event = [
    'id' => 0,
    'site_name' => '',
    'event_date' => date('Y-m-d')
];

// Check if editing an existing event or creating a new one
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEditing = ($event_id > 0);

// If editing, fetch event details
if ($isEditing) {
    try {
        $query = "SELECT * FROM site_events WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$event_id]);
        $fetchedEvent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if event exists and user has permission
        if (!$fetchedEvent) {
            header("Location: site_supervision.php");
            exit();
        }
        
        // For simplicity, we're allowing any logged-in user to edit any event
        // In a real application, you might want to check permissions
        
        $event = $fetchedEvent;
        
    } catch (PDOException $e) {
        error_log('Error fetching event: ' . $e->getMessage());
        $errors[] = "Failed to load event details.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $site_name = trim($_POST['site_name'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    
    if (empty($site_name)) {
        $errors[] = "Site name is required.";
    }
    
    if (empty($event_date)) {
        $errors[] = "Event date is required.";
    } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        $errors[] = "Invalid date format. Please use YYYY-MM-DD.";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            if ($isEditing) {
                // Update existing event
                $query = "UPDATE site_events SET 
                            site_name = ?, 
                            event_date = ?, 
                            updated_at = CURRENT_TIMESTAMP 
                          WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$site_name, $event_date, $event_id]);
                
                $success = true;
                $successMessage = "Event updated successfully!";
                
                // Update local event data to reflect changes
                $event['site_name'] = $site_name;
                $event['event_date'] = $event_date;
                
            } else {
                // Create new event
                $query = "INSERT INTO site_events (
                            site_name, 
                            event_date, 
                            created_by, 
                            created_at
                          ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$site_name, $event_date, $userId]);
                
                $newEventId = $pdo->lastInsertId();
                
                // Set success message in session
                $_SESSION['success'] = "Event created successfully!";
                
                // Redirect to view the new event
                header("Location: view_site_event.php?id=" . $newEventId);
                exit();
            }
            
        } catch (PDOException $e) {
            error_log('Error saving event: ' . $e->getMessage());
            $errors[] = "Failed to save event.";
        }
    }
}

// Page title
$pageTitle = $isEditing ? "Edit Site Event" : "Add New Site Event";
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
        .event-form-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .event-form-header {
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 10px 10px 0 0;
            padding: 1.5rem;
            border-bottom: 2px solid #e74c3c;
        }
        
        .form-title {
            font-weight: 600;
            color: #34495e;
            margin-bottom: 0;
        }
        
        .event-form {
            padding: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #7f8c8d;
        }
        
        .form-actions {
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
                    <h1 class="h3 text-dark"><?= $pageTitle ?></h1>
                    <a href="<?= $isEditing ? 'view_site_event.php?id=' . $event_id : 'site_supervision.php' ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 
                        <?= $isEditing ? 'Back to Event' : 'Back to Calendar' ?>
                    </a>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($successMessage) ?>
                    </div>
                <?php endif; ?>
                
                <div class="card event-form-card">
                    <div class="event-form-header">
                        <h2 class="form-title">
                            <i class="fas <?= $isEditing ? 'fa-edit' : 'fa-calendar-plus' ?>"></i>
                            <?= $pageTitle ?>
                        </h2>
                    </div>
                    
                    <form method="post" class="event-form">
                        <div class="mb-3">
                            <label for="site_name" class="form-label">Site Name <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="site_name" 
                                name="site_name" 
                                value="<?= htmlspecialchars($event['site_name']) ?>" 
                                required
                                placeholder="Enter site name or event title"
                            >
                            <div class="form-text">
                                Enter a descriptive name for this site event
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                            <input 
                                type="date" 
                                class="form-control" 
                                id="event_date" 
                                name="event_date" 
                                value="<?= htmlspecialchars($event['event_date']) ?>" 
                                required
                            >
                            <div class="form-text">
                                Select the date when this event occurs
                            </div>
                        </div>
                        
                        <div class="form-actions d-flex justify-content-end">
                            <a href="<?= $isEditing ? 'view_site_event.php?id=' . $event_id : 'site_supervision.php' ?>" class="btn btn-secondary me-2">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <?= $isEditing ? 'Update Event' : 'Create Event' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 