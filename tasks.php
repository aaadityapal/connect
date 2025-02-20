<?php
session_start();

// Check if accessing from admin dashboard
$isAdminDashboard = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Modify the authentication logic
if ($isAdminDashboard) {
    // Admin can view all tasks
    $user_id = null; // No specific user ID for admin view
} else {
    // Normal access - check for Senior Manager Studio role
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Senior Manager (Studio)') {
        header('Location: login.php');
        exit();
    }
    $user_id = $_SESSION['user_id'];
}

// Database connection
require_once 'config/db_connect.php';

// Modify the queries based on access type
if ($isAdminDashboard) {
    // For admin view - get all categories and their creators
    $categories_query = "
        SELECT c.*, u.username as creator_name,
        (SELECT COUNT(*) FROM tasks WHERE category_id = c.id) as task_count,
        (SELECT COUNT(*) FROM tasks WHERE category_id = c.id AND status = 'completed') as completed_count
        FROM task_categories c 
        LEFT JOIN users u ON c.created_by = u.id 
        ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($categories_query);
    $stmt->execute();
} else {
    // For normal view - get user-specific categories
    $categories_query = "
        SELECT c.*, 
        (SELECT COUNT(*) FROM tasks WHERE category_id = c.id) as task_count,
        (SELECT COUNT(*) FROM tasks WHERE category_id = c.id AND status = 'completed') as completed_count
        FROM task_categories c 
        WHERE c.created_by = ? 
        ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($categories_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

$categories_result = $stmt->get_result();
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Initialize counter variables
$my_day_count = 0;
$important_count = 0;
$today_due_count = 0;
$completed_count = 0;
$due_count = 0;

// Calculate the counts
$sql = "SELECT 
    SUM(CASE WHEN assigned_to = ? THEN 1 ELSE 0 END) as my_day_count,
    SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) as important_count,
    SUM(CASE WHEN DATE(due_date) = CURDATE() THEN 1 ELSE 0 END) as today_due_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as due_count
FROM tasks";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $my_day_count = $row['my_day_count'] ?? 0;
    $important_count = $row['important_count'] ?? 0;
    $today_due_count = $row['today_due_count'] ?? 0;
    $completed_count = $row['completed_count'] ?? 0;
    $due_count = $row['due_count'] ?? 0;
}

// Add manager filter for admin view
$managers_query = "SELECT id, username FROM users WHERE role LIKE '%Manager%'";
$managers_result = $conn->query($managers_query);
$managers = $managers_result->fetch_all(MYSQLI_ASSOC);
?>

<!-- Add this section after your existing filters -->
<?php if ($isAdminDashboard): ?>
<div class="filter-section mb-4">
    <select id="managerFilter" class="form-select" onchange="filterByManager(this.value)">
        <option value="">All Managers</option>
        <?php foreach ($managers as $manager): ?>
            <option value="<?php echo $manager['id']; ?>">
                <?php echo htmlspecialchars($manager['username']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<script>
function filterByManager(managerId) {
    const currentUrl = new URL(window.location.href);
    if (managerId) {
        currentUrl.searchParams.set('manager_id', managerId);
    } else {
        currentUrl.searchParams.delete('manager_id');
    }
    window.location.href = currentUrl.toString();
}
</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: #334155;
        }

        .header {
            padding: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .back-button {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #334155;
            font-weight: 500;
            gap: 8px;
        }

        .search-container {
            margin: 20px;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: white;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .task-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            padding: 20px;
        }

        .task-box {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .task-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
        }

        .task-box i {
            font-size: 1rem;
            opacity: 0.8;
        }

        .my-day {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        .important {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .today-due {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .completed {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .due-tasks {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .task-info {
            flex-grow: 1;
        }

        .task-title {
            font-size: 0.75rem;
            margin-bottom: 3px;
            opacity: 0.9;
        }

        .task-count {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .progress-section {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .progress-title {
            font-size: 1rem;
            color: #333;
        }

        .edit-btn {
            padding: 5px 15px;
            border-radius: 8px;
            border: none;
            background: #f0f0f5;
            color: #6366f1;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .progress-bar {
            height: 10px;
            background-color: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(90deg, #6366f1, #4f46e5);
            transition: width 0.5s ease;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .stat-item {
            font-size: 0.8rem;
            color: #666;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            text-align: center;
        }

        /* Status-specific colors */
        .stat-item.completed {
            color: #10b981; /* green */
        }

        .stat-item.pending {
            color: #f59e0b; /* yellow */
        }

        .stat-item.in-progress {
            color: #3b82f6; /* blue */
        }

        .stat-item.on-hold {
            color: #ef4444; /* red */
        }

        .stat-item.na {
            color: #666; /* gray */
        }

        .add-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            cursor: pointer;
            transition: all 0.2s;
        }

        .add-button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }

        .task-box.completed,
        .task-box.due-tasks {
            grid-column: span 1.5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 20px;
            width: 95%;
            max-width: 450px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            color: #333;
            margin: 0;
        }

        .close-modal {
            font-size: 1.5rem;
            color: #666;
            cursor: pointer;
            padding: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: #4834d4;
            outline: none;
        }

        .color-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .color-preview {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .selected-color {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background-color: #4834d4;
        }

        .color-picker {
            width: 40px;
            height: 40px;
            padding: 0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .color-picker::-webkit-color-swatch-wrapper {
            padding: 0;
        }

        .color-picker::-webkit-color-swatch {
            border: none;
            border-radius: 8px;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .submit-btn:hover {
            background-color: #3c2bb7;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .categories-section {
            padding: 20px;
        }

        .categories-section h3 {
            font-size: 1rem;
            margin-bottom: 10px;
            color: #333;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .category-box {
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s;
        }

        .category-box:hover {
            transform: translateY(-5px);
        }

        .category-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }

        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .category-info h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .category-stats {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            color: #666;
        }

        .category-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Add or update these styles */
        .chat-input-container {
            position: relative;
            min-height: 50px; /* Minimum height to ensure visibility */
            padding: 10px;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-input {
            flex-grow: 1;
            min-height: 40px; /* Minimum height for input */
            max-height: 120px; /* Maximum height before scrolling */
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            resize: none;
            overflow-y: auto;
        }

        .chat-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 10px;
        }

        .attachment-btn,
        .send-btn {
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .attachment-btn:hover,
        .send-btn:hover {
            background-color: #f3f4f6;
        }

        .attachment-btn i,
        .send-btn i {
            font-size: 1.2rem;
            color: #6366f1;
        }

        /* Ensure the chat container doesn't overlap with input */
        .chat-container {
            height: calc(100% - 70px); /* Adjust based on your input container height */
            overflow-y: auto;
            padding-bottom: 60px; /* Add padding to prevent overlap */
        }

        /* Make sure the input container stays at the bottom */
        .chat-input-wrapper {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            z-index: 10;
        }

        .category-tasks {
            margin-top: 10px;
            font-size: 0.8em;
        }

        .task-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-top: 1px solid #eee;
        }

        .task-item:first-child {
            border-top: none;
        }

        .assigned-to, .due-date {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
        }

        .assigned-to i, .due-date i {
            font-size: 0.9em;
            opacity: 0.7;
        }

        .category-card {
            min-height: 160px;
        }

        .see-more-btn {
            width: 100%;
            padding: 8px;
            margin-top: 10px;
            background: none;
            border: none;
            color: #6366f1;
            font-size: 0.8em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .see-more-btn:hover {
            background: #f3f4f6;
            border-radius: 6px;
        }

        .see-more-btn i {
            transition: transform 0.2s;
        }

        .see-more-btn.active i {
            transform: rotate(180deg);
        }

        .hidden-tasks {
            margin-top: 5px;
        }

        .creator-info {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="<?php echo $isAdminDashboard ? 'admin_dashboard.php' : 'studio_manager_dashboard.php'; ?>" class="back-button">
            <i class="fas fa-arrow-left"></i>
            <span><?php echo $isAdminDashboard ? 'Admin Dashboard' : 'Tasks'; ?></span>
        </a>
    </div>

    <div class="search-container">
        <input type="text" class="search-input" placeholder="Search">
    </div>

    <?php if (!$isAdminDashboard): ?>
        <div class="task-grid">
            <div class="task-box my-day">
                <i class="far fa-calendar"></i>
                <div class="task-info">
                    <div class="task-title">My Day</div>
                    <div class="task-count"><?php echo $my_day_count; ?> Task</div>
                </div>
            </div>

            <div class="task-box important">
                <i class="far fa-star"></i>
                <div class="task-info">
                    <div class="task-title">Important</div>
                    <div class="task-count"><?php echo $important_count; ?> Task</div>
                </div>
            </div>

            <div class="task-box today-due">
                <i class="far fa-clock"></i>
                <div class="task-info">
                    <div class="task-title">Today's Due</div>
                    <div class="task-count"><?php echo $today_due_count; ?> Task</div>
                </div>
            </div>

            <div class="task-box completed">
                <i class="far fa-check-circle"></i>
                <div class="task-info">
                    <div class="task-title">Completed</div>
                    <div class="task-count"><?php echo $completed_count; ?> Task</div>
                </div>
            </div>

            <div class="task-box due-tasks">
                <i class="far fa-calendar-times"></i>
                <div class="task-info">
                    <div class="task-title">Due Tasks</div>
                    <div class="task-count"><?php echo $due_count; ?> Task</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="categories-section">
        <h3>Categories</h3>
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
                <a href="category_view.php?id=<?php echo $category['id']; ?><?php echo $isAdminDashboard ? '&admin_view=1' : ''; ?>" class="category-box">
                    <div class="category-card" style="border-left: 4px solid <?php echo $category['color']; ?>">
                        <div class="category-info">
                            <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                            <?php if ($isAdminDashboard && isset($category['creator_name'])): ?>
                                <div class="creator-info">
                                    <small>Created by: <?php echo htmlspecialchars($category['creator_name']); ?></small>
                                </div>
                            <?php endif; ?>
                            <?php
                            // Get all tasks for this category
                            $task_details_query = "
                                SELECT t.*, u.username as assigned_to
                                FROM tasks t
                                LEFT JOIN users u ON t.assigned_to = u.id
                                WHERE t.category_id = ?
                                ORDER BY t.due_date ASC";
                            $stmt = $conn->prepare($task_details_query);
                            $stmt->bind_param("i", $category['id']);
                            $stmt->execute();
                            $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                            // Get counts
                            $counts_query = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                                FROM tasks 
                                WHERE category_id = ?";
                            $stmt = $conn->prepare($counts_query);
                            $stmt->bind_param("i", $category['id']);
                            $stmt->execute();
                            $counts = $stmt->get_result()->fetch_assoc();
                            ?>
                            <div class="category-stats">
                                <span><i class="fas fa-tasks"></i> <?php echo $counts['total'] ?? 0; ?> Total</span>
                                <span><i class="fas fa-check"></i> <?php echo $counts['completed'] ?? 0; ?> Done</span>
                            </div>
                            <div class="category-tasks" data-category-id="<?php echo $category['id']; ?>">
                                <?php 
                                $initial_tasks = array_slice($tasks, 0, 2); // Show first 2 tasks initially
                                foreach ($initial_tasks as $task): 
                                ?>
                                    <div class="task-item">
                                        <span class="assigned-to">
                                            <i class="fas fa-user"></i> 
                                            <?php echo htmlspecialchars($task['assigned_to'] ?? 'Unassigned'); ?>
                                        </span>
                                        <span class="due-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo $task['due_date'] ? date('M d', strtotime($task['due_date'])) : 'No due date'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>

                                <div class="hidden-tasks" style="display: none;">
                                    <?php 
                                    $remaining_tasks = array_slice($tasks, 2); // Get remaining tasks
                                    foreach ($remaining_tasks as $task): 
                                    ?>
                                        <div class="task-item">
                                            <span class="assigned-to">
                                                <i class="fas fa-user"></i> 
                                                <?php echo htmlspecialchars($task['assigned_to'] ?? 'Unassigned'); ?>
                                            </span>
                                            <span class="due-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo $task['due_date'] ? date('M d', strtotime($task['due_date'])) : 'No due date'; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($tasks) > 2): ?>
                                    <button class="see-more-btn" onclick="toggleTasks(event, <?php echo $category['id']; ?>)">
                                        See More <i class="fas fa-chevron-down"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

   

    <button class="add-button">
        <i class="fas fa-plus"></i>
    </button>

    <div class="modal" id="addTaskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Task Category</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="taskCategoryForm">
                    <div class="form-group">
                        <input type="text" 
                               id="categoryName" 
                               placeholder="Enter Task Category" 
                               class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="color-label">
                            <i class="fas fa-palette"></i>
                            Choose a color
                        </label>
                        <div class="color-preview">
                            <div class="selected-color" id="colorPreview"></div>
                            <input type="color" 
                                   id="categoryColor" 
                                   class="color-picker">
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Add Category</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addTaskModal');
            const addButton = document.querySelector('.add-button');
            const closeModal = document.querySelector('.close-modal');
            const colorPicker = document.getElementById('categoryColor');
            const colorPreview = document.getElementById('colorPreview');
            const form = document.getElementById('taskCategoryForm');

            // Open modal when clicking add button
            addButton.addEventListener('click', function() {
                modal.style.display = 'block';
            });

            // Close modal when clicking X
            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });

            // Update color preview when color is picked
            colorPicker.addEventListener('input', function(e) {
                colorPreview.style.backgroundColor = e.target.value;
            });

            // Handle form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const categoryName = document.getElementById('categoryName').value;
                const categoryColor = colorPicker.value;

                // Create FormData object
                const formData = new FormData();
                formData.append('name', categoryName);
                formData.append('color', categoryColor);

                // Send AJAX request
                fetch('add_category.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create new category element
                        const categoriesGrid = document.querySelector('.categories-grid');
                        const newCategory = document.createElement('div');
                        newCategory.className = 'task-box';
                        newCategory.style.backgroundColor = `${data.category.color}20`;
                        newCategory.style.color = data.category.color;
                        
                        newCategory.innerHTML = `
                            <i class="fas fa-folder"></i>
                            <div class="task-info">
                                <div class="task-title">${data.category.name}</div>
                                <div class="task-count">0 Task</div>
                            </div>
                        `;
                        
                        // Add to grid
                        categoriesGrid.insertBefore(newCategory, categoriesGrid.firstChild);
                        
                        // Show success message (optional)
                        alert('Category added successfully!');
                        
                        // Close modal and reset form
                        modal.style.display = 'none';
                        form.reset();
                        colorPreview.style.backgroundColor = '#4834d4';
                    } else {
                        alert('Error adding category. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding category. Please try again.');
                });
            });
        });

        function toggleTasks(event, categoryId) {
            event.preventDefault(); // Prevent the anchor tag from redirecting
            
            const categoryDiv = document.querySelector(`.category-tasks[data-category-id="${categoryId}"]`);
            const hiddenTasks = categoryDiv.querySelector('.hidden-tasks');
            const button = categoryDiv.querySelector('.see-more-btn');
            
            if (hiddenTasks.style.display === 'none') {
                hiddenTasks.style.display = 'block';
                button.innerHTML = 'See Less <i class="fas fa-chevron-up"></i>';
                button.classList.add('active');
            } else {
                hiddenTasks.style.display = 'none';
                button.innerHTML = 'See More <i class="fas fa-chevron-down"></i>';
                button.classList.remove('active');
            }
        }

        // Stop propagation of click events on the see more button
        document.querySelectorAll('.see-more-btn').forEach(btn => {
            btn.addEventListener('click', e => e.stopPropagation());
        });

        document.getElementById('subtaskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Create FormData object
            const formData = new FormData(this);
            
            // Add pending_attendance value
            formData.set('pending_attendance', document.getElementById('pendingAttendance').checked ? 1 : 0);
            
            // Debug log
            console.log('Form Data:', Object.fromEntries(formData));

            // Send AJAX request
            fetch('add_subtask.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data); // Debug log
                if (data.success) {
                    alert('Task created successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Error creating task. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating task. Please try again.');
            });
        });
    </script>
</body>
</html> 