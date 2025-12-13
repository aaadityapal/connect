<?php
// Start session and check authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user has required role
$requiredRoles = ['Senior Manager (Site)', 'Site Coordinator'];
$userRole = $_SESSION['role'] ?? '';

if (!in_array($userRole, $requiredRoles)) {
    // User doesn't have required role
    header('Location: unauthorized.php');
    exit;
}

// User is authenticated and has required role
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Construction Site Planner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="workforce.css">
</head>

<body class="light-theme">

    <!-- Include Manager Panel -->
    <?php include 'includes/manager_panel.php'; ?>

    <div class="app-container">
        <div class="main-content">
            <!-- Sidebar or Header -->
            <header class="app-header">
            <div class="logo-area">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon-site">
                    <path d="M3 21h18" />
                    <path d="M5 21V7l8-4 8 4v14" />
                    <path d="M17 21v-8.33A2 1.2 1.2 0 0 0 15 11h-4a2 1.2 1.2 0 0 0-2 1.67V21" />
                    <path d="M7 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6" />
                </svg>
                <h1>Site Planner</h1>
            </div>

            <!-- Site Filter -->
            <div class="site-selector-wrapper">
                <label for="siteSelect" class="sr-only">Select Site</label>
                <div class="select-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <select id="siteSelect" class="site-select">
                    <option value="">Loading projects...</option>
                </select>
            </div>

            <div class="header-controls">
                <button id="prevMonth" class="icon-btn" aria-label="Previous Month">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <div class="current-date-display">
                    <h2 id="currentMonthYear">October 2023</h2>
                </div>
                <button id="nextMonth" class="icon-btn" aria-label="Next Month">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
                <button id="todayBtn" class="btn btn-secondary">Today</button>
            </div>

            <div class="header-actions">
                <button id="addTaskBtn" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    New Task
                </button>
            </div>
        </header>

        <!-- Main Calendar Area -->
        <main class="calendar-container">
            <div class="calendar-header-scroller">
                <div class="calendar-days-header">
                    <div class="day-head">Mon</div>
                    <div class="day-head">Tue</div>
                    <div class="day-head">Wed</div>
                    <div class="day-head">Thu</div>
                    <div class="day-head">Fri</div>
                    <div class="day-head">Sat</div>
                    <div class="day-head">Sun</div>
                </div>
            </div>
            <div class="calendar-grid" id="calendarGrid">
                <!-- Javascript will populate this -->
            </div>
        </main>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="modal-overlay" id="taskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Task</h3>
                <button class="close-modal" id="closeModal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form id="taskForm">
                <input type="hidden" id="taskId" name="id">
                <div class="form-group">
                    <label for="taskTitle">Task Title</label>
                    <input type="text" id="taskTitle" name="title" placeholder="e.g., Foundation Pouring" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="taskStart">Start Date</label>
                        <input type="date" id="taskStart" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="taskEnd">End Date</label>
                        <input type="date" id="taskEnd" name="end_date" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="taskStatus">Status</label>
                        <div class="custom-select">
                            <select id="taskStatus" name="status">
                                <option value="planned">Planned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="on_hold">On Hold</option>
                                <option value="review">Review</option>
                                <option value="completed">Completed</option>
                                <option value="blocked">Blocked</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="taskAssignTo">Assign To</label>
                        <select id="taskAssignTo" name="assign_to" class="assignee-select">
                            <option value="">Select an assignee...</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="taskDesc">Description</label>
                    <textarea id="taskDesc" name="description" rows="3" placeholder="Additional details..."></textarea>
                </div>

                <div class="form-group">
                    <label for="taskImages">Attachments (Photos)</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="taskImages" name="images[]" multiple accept="image/*" class="file-input">
                        <div class="upload-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <span>Click to upload photos</span>
                        </div>
                    </div>
                    <div id="imagePreviewContainer" class="image-preview-grid"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" id="cancelModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script src="workforce.js"></script>
</body>

</html>