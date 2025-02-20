<?php
session_start();

// Check if accessing from admin dashboard
$isAdminDashboard = isset($_GET['admin_view']);

// Modify the authentication logic
if (!$isAdminDashboard) {
    // Normal access - check for Senior Manager Studio role
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Senior Manager (Studio)') {
        header('Location: login.php');
        exit();
    }
    $user_id = $_SESSION['user_id'];
} else {
    // Skip authentication for admin view
    $user_id = null;
}

// Get category ID
$category_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$category_id) {
    header('Location: ' . ($isAdminDashboard ? 'admin_dashboard.php' : 'tasks.php'));
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Modify queries based on access type
if ($isAdminDashboard) {
    // For admin view - get category details without user restriction
    $category_query = "
        SELECT c.*, u.username as creator_name 
        FROM task_categories c 
        LEFT JOIN users u ON c.created_by = u.id 
        WHERE c.id = ?";
    $stmt = $conn->prepare($category_query);
    $stmt->bind_param("i", $category_id);
} else {
    // For normal view - get user-specific category
    $category_query = "SELECT * FROM task_categories WHERE id = ? AND created_by = ?";
    $stmt = $conn->prepare($category_query);
    $stmt->bind_param("ii", $category_id, $user_id);
}

$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();

if (!$category) {
    header('Location: tasks.php');
    exit();
}

// Fetch all employees with specific roles (including multiple roles)
$employees_query = "SELECT id, username, email, role FROM users WHERE 
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0 OR
    FIND_IN_SET(?, role) > 0";

$stmt = $conn->prepare($employees_query);
$roles = [
    'Senior Manager (Studio)',
    'Senior Manager (Marketing)',
    'Senior Manager (Sales)',
    'Senior Manager (Site)',
    'Working Team',
    '3D designing Team',
    'Studio Trainee',
    'Business Developer',
    'Social Media Manager',
    'Site Supervisor',
    'Site Trainee',
    'Relationship Manager',
    'Sales Manager',
    'Sales Consultant',
    'Field Sales Representative'
];

$stmt->bind_param(str_repeat('s', count($roles)), ...$roles);
$stmt->execute();
$employees_result = $stmt->get_result();
$employees = $employees_result->fetch_all(MYSQLI_ASSOC);

// Fetch existing tasks for this category
$tasks_query = "SELECT t.*, u.username as assigned_to_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_to = u.id 
                WHERE t.category_id = ?
                ORDER BY t.created_at DESC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - Tasks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Add your existing CSS here -->
    <style>
        /* Add this to your existing styles */
        .subtask-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .subtask-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .priority-options {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .priority-option {
            padding: 5px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }

        .priority-option.selected {
            background-color: #4834d4;
            color: white;
            border-color: #4834d4;
        }

        .tasks-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .tasks-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .task-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
            transition: transform 0.2s;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .task-header h3 {
            margin: 0;
            font-size: 1.1em;
            color: #333;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .priority-badge.high {
            background: #ffe4e4;
            color: #d63031;
        }

        .priority-badge.medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-badge.low {
            background: #e2f3ea;
            color: #27ae60;
        }

        .task-details p {
            color: #666;
            font-size: 0.9em;
            margin: 10px 0;
        }

        .task-meta {
            display: grid;
            gap: 8px;
            font-size: 0.85em;
            color: #666;
            margin-top: 15px;
        }

        .task-meta > div {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.completed {
            background: #e2f3ea;
            color: #27ae60;
        }

        .no-tasks {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .tasks-list {
                grid-template-columns: 1fr;
            }
        }

        /* Add to your existing styles */
        .subtask-modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .file-upload-container {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .file-list {
            margin-top: 10px;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            background: #f8f9fa;
            margin-bottom: 5px;
            border-radius: 4px;
        }

        .remove-file {
            color: red;
            cursor: pointer;
        }

        .submit-btn {
            background-color: #4834d4;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background-color: #372aaa;
        }

        /* Add these styles */
        .filter-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }

        .status-filters, .priority-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            user-select: none;
        }

        .filter-checkbox:hover {
            background: #f0f0f0;
        }

        .filter-checkbox input[type="checkbox"] {
            margin: 0;
        }

        .filter-btn {
            padding: 8px 16px;
            background: #4834d4;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-btn:hover {
            background: #372aaa;
        }

        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-filters, .priority-filters {
                justify-content: flex-start;
            }
        }

        /* Add these styles */
        .header-container {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .header-content h2 {
            margin: 0;
            color: #2d3436;
            font-size: 1.5rem;
        }

        .add-task-btn {
            background-color: #4834d4;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(72, 52, 212, 0.2);
        }

        .add-task-btn:hover {
            background-color: #372aaa;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(72, 52, 212, 0.3);
        }

        .add-task-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(72, 52, 212, 0.2);
        }

        .add-task-btn i {
            font-size: 0.9rem;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .add-task-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Update tasks container margin */
        .tasks-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Update filter section margin */
        .filter-section {
            margin: 20px auto;
            max-width: 1200px;
        }

        /* Optional: Add smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Optional: Add transition to all buttons */
        button {
            transition: all 0.3s ease;
        }

        /* Optional: Add hover effect to filter checkboxes */
        .filter-checkbox:hover {
            background: #f0f0f0;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Optional: Improve filter button styling to match add task button */
        .filter-btn {
            background-color: #4834d4;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(72, 52, 212, 0.2);
        }

        .filter-btn:hover {
            background-color: #372aaa;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(72, 52, 212, 0.3);
        }

        /* Optional: Add transition to task cards */
        .task-card {
            transition: all 0.3s ease;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Add these styles */
        .task-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .action-btn {
            background: none;
            border: none;
            color: #4834d4;
            padding: 5px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn:hover {
            background-color: #f0f0f0;
            transform: translateY(-1px);
        }

        .action-btn i {
            font-size: 1.1rem;
        }

        .attachment-count {
            background: #4834d4;
            color: white;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 10px;
            position: absolute;
            top: -5px;
            right: -5px;
        }

        /* Timeline Modal Styles */
        .timeline-modal,
        .attachments-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .timeline-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-left: 2px solid #4834d4;
            margin-left: 20px;
            position: relative;
        }

        .timeline-item::before {
            content: '';
            width: 12px;
            height: 12px;
            background: #4834d4;
            border-radius: 50%;
            position: absolute;
            left: -7px;
        }

        .attachment-list {
            display: grid;
            gap: 10px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .attachment-item i {
            color: #4834d4;
        }

        /* Update the Timeline Modal */
        .timeline-modal-content {
            width: 95%;
            max-width: 1000px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .close-modal-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .timeline-container {
            flex-grow: 1;
            overflow: hidden;
        }

        .timeline-chat-wrapper {
            display: grid;
            grid-template-columns: 60% 40%;
            gap: 20px;
            height: calc(100vh - 180px);
            min-height: 400px;
        }

        .timeline-section,
        .chat-section {
            display: flex;
            flex-direction: column;
            height: 100%;
            max-height: calc(85vh - 100px);
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            overflow-y: auto;
        }

        .timeline-section h4,
        .chat-section h4 {
            margin: 0 0 15px 0;
            padding: 10px 0;
            border-bottom: 2px solid #4834d4;
            color: #2d3436;
        }

        #timelineContent,
        .chat-messages {
            flex-grow: 1;
            overflow-y: auto;
            padding: 10px;
            margin-bottom: 140px;
            height: calc(100% - 180px);
        }

        .chat-message {
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9rem;
            color: #666;
        }

        .message-content {
            margin-bottom: 10px;
        }

        .message-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 5px;
        }

        .attachment-preview {
            padding: 5px 10px;
            background: #f0f0f0;
            border-radius: 4px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .chat-input-container {
            position: absolute;
            bottom: 20px;
            left: 61%;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .chat-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: none;
            min-height: 60px;
            max-height: 120px;
        }

        .chat-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
        }

        .file-attach-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background: #f0f0f0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: #4834d4;
            transition: all 0.3s ease;
        }

        .file-attach-btn:hover {
            background: #e0e0e0;
        }

        .send-btn {
            padding: 8px 20px;
            background: #4834d4;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            background: #372aaa;
        }

        .selected-files {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin: 5px 0;
            max-height: 60px;
            overflow-y: auto;
        }

        .selected-file {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .remove-file {
            cursor: pointer;
            color: #ff4757;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .timeline-chat-wrapper {
                grid-template-columns: 1fr;
                height: calc(100vh - 160px);
            }

            .chat-input-container {
                left: 20px;
                bottom: 20px;
            }

            .chat-messages {
                height: calc(100% - 160px);
                margin-bottom: 160px;
            }

            .timeline-section,
            .chat-section {
                max-height: none;
            }
        }

        /* Optional: Add smooth scrolling */
        .chat-messages {
            scroll-behavior: smooth;
        }

        /* Optional: Style scrollbars */
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Enhanced Animations and Styling */

        /* Task Cards Animation */
        .task-card {
            animation: slideIn 0.3s ease-out;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid #4834d4;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .task-card:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 8px 12px rgba(72, 52, 212, 0.15);
        }

        /* Modal Animations */
        .modal {
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            animation: slideDown 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            background: linear-gradient(to bottom right, #ffffff, #f8f9fa);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: none;
        }

        /* Timeline Animations */
        .timeline-item {
            animation: slideInRight 0.5s ease-out;
            animation-fill-mode: both;
        }

        .timeline-item:nth-child(odd) {
            animation: slideInLeft 0.5s ease-out;
        }

        /* Message Animations */
        .chat-message {
            animation: popIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-left: 4px solid #4834d4;
        }

        /* Attachment Item Animations */
        .attachment-item {
            animation: fadeInUp 0.3s ease-out;
            animation-fill-mode: both;
        }

        .attachment-item:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 4px 8px rgba(72, 52, 212, 0.2);
            background: linear-gradient(145deg, #f8f9fa, #ffffff);
        }

        /* Button Animations */
        .add-task-btn, .submit-btn, .action-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(145deg, #4834d4, #3c2bb7);
            box-shadow: 0 4px 15px rgba(72, 52, 212, 0.3);
        }

        .add-task-btn:hover, .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(72, 52, 212, 0.4);
            background: linear-gradient(145deg, #3c2bb7, #4834d4);
        }

        /* Status Badge Animations */
        .status-badge {
            animation: pulse 2s infinite;
            background: linear-gradient(145deg, var(--status-color), var(--status-color-dark));
        }

        /* File Upload Area */
        .file-upload-area {
            border: 2px dashed #4834d4;
            padding: 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
        }

        .file-upload-area.drag-over {
            background: rgba(72, 52, 212, 0.1);
            transform: scale(1.02);
        }

        /* Loading Spinner */
        .loading-spinner {
            animation: rotate 1s linear infinite;
            background: conic-gradient(from 0deg, #4834d4, #ffffff);
        }

        /* Define Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes popIn {
            0% {
                opacity: 0;
                transform: scale(0.9);
            }
            50% {
                transform: scale(1.02);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(72, 52, 212, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(72, 52, 212, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(72, 52, 212, 0);
            }
        }

        /* Enhanced Component Styles */
        .timeline-section {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }

        .chat-section {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }

        .chat-input {
            background: linear-gradient(145deg, #f8f9fa, #ffffff);
            border: 1px solid rgba(72, 52, 212, 0.2);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .chat-input:focus {
            border-color: #4834d4;
            box-shadow: 0 0 0 3px rgba(72, 52, 212, 0.1);
        }

        /* Status Colors with Gradients */
        .status-pending {
            background: linear-gradient(145deg, #f59e0b, #d97706);
        }

        .status-in-progress {
            background: linear-gradient(145deg, #3b82f6, #2563eb);
        }

        .status-completed {
            background: linear-gradient(145deg, #10b981, #059669);
        }

        .status-on-hold {
            background: linear-gradient(145deg, #ef4444, #dc2626);
        }

        /* Attachment Preview */
        .attachment-preview {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .attachment-preview:hover::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(72, 52, 212, 0.1);
            backdrop-filter: blur(2px);
        }

        /* Responsive Design Enhancements */
        @media (max-width: 768px) {
            .task-card {
                margin: 10px 0;
            }

            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .timeline-chat-wrapper {
                flex-direction: column;
            }
        }

        /* Glass Morphism Effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        /* Floating Labels */
        .form-group {
            position: relative;
        }

        .floating-label {
            position: absolute;
            top: -10px;
            left: 10px;
            background: white;
            padding: 0 5px;
            color: #4834d4;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        /* Updated styles for action buttons */
        .task-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #4834d4;
            background: rgba(72, 52, 212, 0.1);
        }

        .action-btn i {
            font-size: 1rem;
        }

        .action-btn span {
            font-weight: 500;
        }

        .timeline-btn:hover {
            background: rgba(72, 52, 212, 0.2);
            transform: translateY(-2px);
        }

        .attachment-btn {
            position: relative;
        }

        .attachment-btn:hover {
            background: rgba(72, 52, 212, 0.2);
            transform: translateY(-2px);
        }

        .attachment-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #4834d4;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        /* Animation for buttons */
        .action-btn {
            animation: fadeIn 0.3s ease-out;
        }

        .action-btn:active {
            transform: scale(0.95);
        }

        /* Hover effect for task card */
        .task-card:hover .task-actions {
            opacity: 1;
            transform: translateY(0);
        }

        /* Add these keyframes if not already present */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .task-actions {
                flex-direction: row;
                justify-content: stretch;
            }

            .action-btn {
                flex: 1;
                justify-content: center;
            }
        }

        /* Optional: Add a subtle pulse animation to the attachment count */
        .attachment-count {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(72, 52, 212, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(72, 52, 212, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(72, 52, 212, 0);
            }
        }

        /* Update the timeline modal styles */
        .timeline-modal .modal-content {
            width: 95%;
            max-width: 1000px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        /* Update the timeline-chat-wrapper */
        .timeline-chat-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100% - 60px);
            overflow: hidden;
        }

        /* Update the chat section */
        .chat-section {
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }

        /* Update chat messages container */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            margin-bottom: 120px;
        }

        /* Update chat input container */
        .chat-input-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 15px;
            border-top: 1px solid #eee;
            z-index: 10;
        }

        .chat-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: none;
        }

        .chat-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 5px 0;
        }

        .file-attach-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background: #f0f0f0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: #4834d4;
            transition: all 0.3s ease;
        }

        .file-attach-btn:hover {
            background: #e0e0e0;
        }

        .send-btn {
            padding: 8px 20px;
            background: #4834d4;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            background: #372aaa;
        }

        .selected-files {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin: 5px 0;
        }

        .selected-file {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .remove-file {
            cursor: pointer;
            color: #ff4757;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .timeline-chat-wrapper {
                grid-template-columns: 1fr;
            }

            .chat-messages {
                margin-bottom: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Replace the existing add button with this -->
    <div class="header-container">
        <div class="header-content">
            <h2><?php echo htmlspecialchars($category['name']); ?> Tasks</h2>
            <button class="add-task-btn" onclick="openSubtaskModal()">
                <i class="fas fa-plus"></i>
                Add New Task
            </button>
        </div>
    </div>

    <!-- Subtask Modal -->
    <div id="subtaskModal" class="subtask-modal">
        <div class="subtask-modal-content">
            <div class="modal-header">
                <h3>Add New Task</h3>
                <span class="close" onclick="closeSubtaskModal()">&times;</span>
            </div>
            <form id="subtaskForm" enctype="multipart/form-data">
                <!-- Step 1: Task Title -->
                <div class="form-group">
                    <label for="taskTitle">Task Title*</label>
                    <input type="text" id="taskTitle" name="title" class="form-control" required>
                </div>

                <!-- Add this inside the subtaskForm, after the task title and before the priority section -->
                <div class="form-group">
                    <label for="projectType">Project Type*</label>
                    <select id="projectType" name="project_type" class="form-control" required>
                        <option value="">Select Project Type</option>
                        <option value="Architecture">Architecture</option>
                        <option value="Construction">Construction</option>
                        <option value="Interior">Interior</option>
                    </select>
                </div>

                <!-- Step 2: Priority -->
                <div class="form-group">
                    <label>Priority*</label>
                    <div class="priority-options">
                        <div class="priority-option" data-priority="low">Low</div>
                        <div class="priority-option" data-priority="medium">Medium</div>
                        <div class="priority-option" data-priority="high">High</div>
                    </div>
                    <input type="hidden" id="taskPriority" name="priority" value="medium">
                </div>

                <!-- Step 3: Start Date -->
                <div class="form-group">
                    <label for="startDate">Start Date*</label>
                    <input type="date" id="startDate" name="start_date" class="form-control" required>
                </div>

                <!-- Step 4: Due Date -->
                <div class="form-group">
                    <label for="dueDate">Due Date*</label>
                    <input type="date" id="dueDate" name="due_date" class="form-control" required>
                </div>

                <!-- Step 5: Due Time -->
                <div class="form-group">
                    <label for="dueTime">Due Time*</label>
                    <input type="time" id="dueTime" name="due_time" class="form-control" required>
                </div>

                <!-- Step 6: Assign To -->
                <div class="form-group">
                    <label for="assignedTo">Assign To*</label>
                    <select id="assignedTo" name="assigned_to" class="form-control" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['username']); ?> 
                                (<?php echo htmlspecialchars($employee['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Step 7: Pending Attendance Checkbox -->
                <div class="form-group">
                    <label class="checkbox-container">
                        <input type="checkbox" name="pending_attendance" id="pendingAttendance">
                        Pending attendance (if task is not completed)
                    </label>
                </div>

                <!-- Step 8: Repeat Task -->
                <div class="form-group">
                    <label for="repeatTask">Repeat Task</label>
                    <select id="repeatTask" name="repeat_task" class="form-control">
                        <option value="none">No Repeat</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>

                <!-- Step 9: Remarks -->
                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="3"></textarea>
                </div>

                <!-- Step 10: File Attachments -->
                <div class="form-group">
                    <label for="attachments">Attach Files</label>
                    <div class="file-upload-container">
                        <input type="file" id="attachments" name="attachments[]" multiple class="form-control">
                        <div id="fileList" class="file-list"></div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Add Task</button>
            </form>
        </div>
    </div>

    <!-- Add this after the modal -->
    <div class="tasks-container">
        <!-- Your existing filter section -->
        <div class="filter-section">
            <div class="filter-container">
                <div class="status-filters">
                    <label class="filter-checkbox">
                        <input type="checkbox" name="status" value="pending"> Pending
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="status" value="in_progress"> In Progress
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="status" value="completed"> Completed
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="status" value="on_hold"> On Hold
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="status" value="cancelled"> Cancelled
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="status" value="not_applicable"> Not Applicable
                    </label>
                </div>
                
                <div class="priority-filters">
                    <label>Priority:</label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="priority" value="high"> High
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="priority" value="medium"> Medium
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="priority" value="low"> Low
                    </label>
                </div>

                <button id="applyFilters" class="filter-btn">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </div>

        <div class="tasks-list">
            <?php if ($tasks && count($tasks) > 0): ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <h3><?php echo htmlspecialchars($task['title']); ?></h3>
                            <span class="priority-badge <?php echo $task['priority']; ?>">
                                <?php echo ucfirst($task['priority']); ?>
                            </span>
                        </div>
                        
                        <div class="task-details">
                            <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                            <div class="task-meta">
                                <div class="assigned-to">
                                    <i class="fas fa-user"></i>
                                    Assigned to: <?php echo htmlspecialchars($task['assigned_to_name']); ?>
                                </div>
                                <div class="due-date">
                                    <i class="fas fa-calendar"></i>
                                    Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                </div>
                                <div class="status <?php echo $task['status']; ?>">
                                    <?php echo ucfirst($task['status']); ?>
                                </div>
                            </div>
                            
                            <!-- Updated action buttons with better styling -->
                            <div class="task-actions">
                                <button class="action-btn timeline-btn" onclick="viewTimeline(<?php echo $task['id']; ?>)">
                                    <i class="fas fa-history"></i>
                                    <span>Timeline</span>
                                </button>
                                <button class="action-btn attachment-btn" onclick="viewAttachments(<?php echo $task['id']; ?>)">
                                    <i class="fas fa-paperclip"></i>
                                    <span>Attachments</span>
                                    <?php if (!empty($task['attachment_count'])): ?>
                                        <span class="attachment-count"><?php echo $task['attachment_count']; ?></span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-tasks">No tasks found in this category.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add these modals at the bottom of your file -->
    <div id="timelineModal" class="timeline-modal">
        <div class="modal-content timeline-modal-content">
            <div class="modal-header">
                <h3>Task Timeline & Discussion</h3>
                <button onclick="closeTimelineModal()" class="close-modal-btn">&times;</button>
            </div>
            
            <div class="timeline-container">
                <!-- Timeline and Chat Container -->
                <div class="timeline-chat-wrapper">
                    <!-- Timeline Section -->
                    <div class="timeline-section">
                        <h4>Activity Timeline</h4>
                        <div id="timelineContent"></div>
                    </div>

                    <!-- Chat Section -->
                    <div class="chat-section">
                        <h4>Discussion</h4>
                        <div class="chat-messages" id="chatMessages"></div>
                        
                        <!-- Chat Input Form -->
                        <div class="chat-input-container">
                            <form id="chatForm" class="chat-form">
                                <input type="hidden" id="currentTaskId" name="task_id" value="">
                                <textarea 
                                    class="chat-input" 
                                    placeholder="Type your message..."
                                    name="message"
                                    rows="3"
                                ></textarea>
                                <div class="chat-actions">
                                    <label class="file-attach-btn">
                                        <i class="fas fa-paperclip"></i>
                                        <input type="file" 
                                               name="attachment" 
                                               multiple 
                                               style="display: none;"
                                               onchange="handleFileSelect(this)">
                                    </label>
                                    <div id="selectedFiles" class="selected-files"></div>
                                    <button type="submit" class="send-btn">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="attachmentsModal" class="attachments-modal">
        <div class="modal-content">
            <h3>Task Attachments</h3>
            <div id="attachmentsContent"></div>
            <button onclick="closeAttachmentsModal()" class="close-btn">Close</button>
        </div>
    </div>

    <script>
        function openSubtaskModal() {
            document.getElementById('subtaskModal').style.display = 'block';
        }

        function closeSubtaskModal() {
            document.getElementById('subtaskModal').style.display = 'none';
        }

        // File handling
        document.getElementById('attachments').addEventListener('change', function(e) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            Array.from(this.files).forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <span>${file.name}</span>
                    <span class="remove-file">&times;</span>
                `;
                fileList.appendChild(fileItem);
            });
        });

        // Form submission with file upload
        document.getElementById('subtaskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('category_id', '<?php echo $category_id; ?>');

            // Add notification flag
            formData.append('send_notification', '1');

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding Task...';

            fetch('add_subtask.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeSubtaskModal();
                    // Show success message
                    alert('Task assigned successfully! The user will be notified.');
                    location.reload();
                } else {
                    alert('Error creating task: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating task. Please try again.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Add Task';
            });
        });

        // Priority selection
        document.querySelectorAll('.priority-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.priority-option').forEach(opt => 
                    opt.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('taskPriority').value = this.dataset.priority;
            });
        });

        // Add this JavaScript for filter functionality
        document.getElementById('applyFilters').addEventListener('click', function() {
            // Get selected status filters
            const selectedStatuses = Array.from(document.querySelectorAll('input[name="status"]:checked'))
                .map(checkbox => checkbox.value);

            // Get selected priority filters
            const selectedPriorities = Array.from(document.querySelectorAll('input[name="priority"]:checked'))
                .map(checkbox => checkbox.value);

            // Get all task cards
            const taskCards = document.querySelectorAll('.task-card');

            taskCards.forEach(card => {
                const cardStatus = card.querySelector('.status').textContent.toLowerCase().trim().replace('status: ', '');
                const cardPriority = card.querySelector('.priority-badge').textContent.toLowerCase().trim();
                
                // Show card if no filters are selected or if it matches selected filters
                const statusMatch = selectedStatuses.length === 0 || selectedStatuses.includes(cardStatus);
                const priorityMatch = selectedPriorities.length === 0 || selectedPriorities.includes(cardPriority);

                if (statusMatch && priorityMatch) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });

            // Update tasks count or show "no results" message
            const visibleTasks = document.querySelectorAll('.task-card[style=""]').length;
            const noTasksMessage = document.querySelector('.no-tasks');
            
            if (visibleTasks === 0) {
                if (!noTasksMessage) {
                    const message = document.createElement('p');
                    message.className = 'no-tasks';
                    message.textContent = 'No tasks match the selected filters.';
                    document.querySelector('.tasks-list').appendChild(message);
                }
            } else if (noTasksMessage) {
                noTasksMessage.remove();
            }
        });

        // Add "Clear Filters" functionality
        function clearFilters() {
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('applyFilters').click();
        }

        // Optional: Add clear filters button
        const filterContainer = document.querySelector('.filter-container');
        const clearButton = document.createElement('button');
        clearButton.className = 'filter-btn';
        clearButton.innerHTML = '<i class="fas fa-times"></i> Clear Filters';
        clearButton.onclick = clearFilters;
        filterContainer.appendChild(clearButton);

        // Optional: Add filter count badge
        function updateFilterCount() {
            const totalSelected = document.querySelectorAll('input[type="checkbox"]:checked').length;
            const badge = document.querySelector('.filter-count') || document.createElement('span');
            badge.className = 'filter-count';
            
            if (totalSelected > 0) {
                badge.textContent = totalSelected;
                if (!badge.parentElement) {
                    document.getElementById('applyFilters').appendChild(badge);
                }
            } else if (badge.parentElement) {
                badge.remove();
            }
        }

        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', updateFilterCount);
        });

        // Add this CSS for the filter count badge
        document.head.insertAdjacentHTML('beforeend', `
            <style>
                .filter-count {
                    background: white;
                    color: #4834d4;
                    border-radius: 50%;
                    padding: 2px 6px;
                    font-size: 12px;
                    margin-left: 5px;
                }
            </style>
        `);

        // Add these JavaScript functions
        function viewTimeline(taskId) {
            const modal = document.getElementById('timelineModal');
            const timelineContent = document.getElementById('timelineContent');
            const chatMessages = document.getElementById('chatMessages');
            document.getElementById('currentTaskId').value = taskId;
            
            modal.style.display = 'block';

            // Load timeline and chat messages
            loadTimelineData(taskId);
            loadChatMessages(taskId);
        }

        function loadTimelineData(taskId) {
            const timelineContent = document.getElementById('timelineContent');
            timelineContent.innerHTML = '<div class="loading">Loading timeline...</div>';

            fetch(`get_task_timeline.php?task_id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        timelineContent.innerHTML = data.timeline.map(item => `
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-date">${item.date}</div>
                                    <div class="timeline-text">${item.action}</div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        timelineContent.innerHTML = '<p>Error loading timeline</p>';
                    }
                })
                .catch(error => {
                    timelineContent.innerHTML = '<p>Error loading timeline</p>';
                    console.error('Error:', error);
                });
        }

        function loadChatMessages(taskId) {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = '<div class="loading">Loading messages...</div>';

            fetch(`get_task_messages.php?task_id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        chatMessages.innerHTML = data.messages.map(message => `
                            <div class="chat-message">
                                <div class="message-header">
                                    <span class="message-author">${message.username}</span>
                                    <span class="message-time">${message.created_at}</span>
                                </div>
                                <div class="message-content">${message.message}</div>
                                ${message.attachments ? `
                                    <div class="message-attachments">
                                        ${message.attachments.map(file => `
                                            <a href="${file.path}" class="attachment-preview" target="_blank">
                                                <i class="fas fa-file"></i>
                                                ${file.name}
                                            </a>
                                        `).join('')}
                                    </div>
                                ` : ''}
                            </div>
                        `).join('');
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    } else {
                        chatMessages.innerHTML = '<p>Error loading messages</p>';
                    }
                })
                .catch(error => {
                    chatMessages.innerHTML = '<p>Error loading messages</p>';
                    console.error('Error:', error);
                });
        }

        // Handle file selection
        function handleFileSelect(input) {
            const selectedFiles = document.getElementById('selectedFiles');
            const maxSize = 10 * 1024 * 1024; // 10MB limit
            let totalSize = 0;
            let validFiles = true;

            selectedFiles.innerHTML = '';

            Array.from(input.files).forEach(file => {
                totalSize += file.size;
                if (file.size > maxSize) {
                    alert(`File ${file.name} is too large. Maximum size is 10MB`);
                    validFiles = false;
                    input.value = '';
                    return;
                }
            });

            if (!validFiles) return;

            if (totalSize > maxSize * 3) { // 30MB total limit
                alert('Total file size too large. Please select fewer files.');
                input.value = '';
                return;
            }

            Array.from(input.files).forEach(file => {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileElement = document.createElement('div');
                fileElement.className = 'selected-file';
                fileElement.innerHTML = `
                    <i class="fas fa-file"></i>
                    <span title="${file.name}">${file.name.substring(0, 20)}${file.name.length > 20 ? '...' : ''}</span>
                    <small>(${fileSize}MB)</small>
                    <i class="fas fa-times remove-file" onclick="removeFile(this)"></i>
                `;
                selectedFiles.appendChild(fileElement);
            });
        }

        function removeFile(element) {
            const fileInput = document.querySelector('input[type="file"]');
            fileInput.value = ''; // Clear file input
            document.getElementById('selectedFiles').innerHTML = ''; // Clear selected files display
        }

        // Handle chat form submission
        document.getElementById('chatForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('.send-btn');
            const messageInput = this.querySelector('.chat-input');
            const fileInput = this.querySelector('input[type="file"]');
            
            // Validate message
            if (!messageInput.value.trim() && fileInput.files.length === 0) {
                alert('Please enter a message or attach a file');
                return;
            }

            // Disable submit button and show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                const formData = new FormData(this);
                const taskId = document.getElementById('currentTaskId').value;
                
                // Append each file separately
                const files = fileInput.files;
                if (files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        formData.append('attachments[]', files[i]);
                    }
                }

                const response = await fetch('add_task_message.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    // Clear form
                    this.reset();
                    document.getElementById('selectedFiles').innerHTML = '';
                    
                    // Reload messages and timeline
                    await Promise.all([
                        loadChatMessages(taskId),
                        loadTimelineData(taskId)
                    ]);
                } else {
                    throw new Error(data.message || 'Error sending message');
                }
            } catch (error) {
                console.error('Error:', error);
                alert(error.message || 'Error sending message. Please try again.');
            } finally {
                // Reset submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            }
        });

        function closeTimelineModal() {
            document.getElementById('timelineModal').style.display = 'none';
        }

        function closeAttachmentsModal() {
            document.getElementById('attachmentsModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const timelineModal = document.getElementById('timelineModal');
            const attachmentsModal = document.getElementById('attachmentsModal');
            
            if (event.target === timelineModal) {
                timelineModal.style.display = 'none';
            }
            if (event.target === attachmentsModal) {
                attachmentsModal.style.display = 'none';
            }
        }

        // Update the viewAttachments function
        function viewAttachments(taskId) {
            const modal = document.getElementById('attachmentsModal');
            const content = document.getElementById('attachmentsContent');
            
            content.innerHTML = '<div class="loading">Loading attachments...</div>';
            modal.style.display = 'block';

            fetch(`get_task_attachments.php?task_id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.attachments && data.attachments.length > 0) {
                            content.innerHTML = `
                                <div class="attachments-list">
                                    ${data.attachments.map(file => `
                                        <div class="attachment-item">
                                            <div class="attachment-icon">
                                                <i class="fas ${getFileIcon(file.file_name)}"></i>
                                            </div>
                                            <div class="attachment-details">
                                                <div class="attachment-name">${file.file_name}</div>
                                                <div class="attachment-meta">
                                                    <span class="attachment-date">${file.uploaded_at}</span>
                                                    <span class="attachment-user">by ${file.uploaded_by}</span>
                                                </div>
                                            </div>
                                            <a href="${file.file_path}" class="attachment-download" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    `).join('')}
                                </div>
                            `;
                        } else {
                            content.innerHTML = '<p class="no-attachments">No attachments found for this task.</p>';
                        }
                    } else {
                        throw new Error(data.message || 'Error loading attachments');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Error loading attachments. Please try again.
                        </div>
                    `;
                });
        }

        // Helper function to determine file icon
        function getFileIcon(fileName) {
            const extension = fileName.split('.').pop().toLowerCase();
            switch (extension) {
                case 'pdf':
                    return 'fa-file-pdf';
                case 'doc':
                case 'docx':
                    return 'fa-file-word';
                case 'xls':
                case 'xlsx':
                    return 'fa-file-excel';
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                    return 'fa-file-image';
                case 'zip':
                case 'rar':
                    return 'fa-file-archive';
                default:
                    return 'fa-file';
            }
        }

        // Add animation delay to timeline items
        document.querySelectorAll('.timeline-item').forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
        });

        // Add animation delay to attachment items
        document.querySelectorAll('.attachment-item').forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
        });

        // Add hover effect for buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('mouseover', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            btn.addEventListener('mouseout', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Add ripple effect to buttons
        function createRipple(event) {
            const button = event.currentTarget;
            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
            
            const diameter = Math.max(rect.width, rect.height);
            const radius = diameter / 2;
            
            ripple.style.width = ripple.style.height = `${diameter}px`;
            ripple.style.left = `${event.clientX - rect.left - radius}px`;
            ripple.style.top = `${event.clientY - rect.top - radius}px`;
            ripple.classList.add('ripple');
            
            const rippleContainer = document.createElement('span');
            rippleContainer.classList.add('ripple-container');
            
            button.appendChild(rippleContainer);
            rippleContainer.appendChild(ripple);
            
            setTimeout(() => {
                rippleContainer.remove();
            }, 1000);
        }

        document.querySelectorAll('.action-btn').forEach(button => {
            button.addEventListener('click', createRipple);
        });
    </script>
</body>
</html>
