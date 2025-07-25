<?php
// Start session and check for authentication
session_start();


// Include database connection
include_once('includes/db_connect.php');

// Fetch username from users table for the greeting
$username = "Supervisor"; // Default value
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $username = $row['username'];
            } else {
                error_log("User not found for ID: " . $_SESSION['user_id']);
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for fetching username");
        }
    } catch (Exception $e) {
        error_log("Error fetching username: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Supervisor Dashboard</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    <link rel="stylesheet" href="css/supervisor/calendar-modal.css">
    <link rel="stylesheet" href="css/supervisor/calendar-stats.css">
    <link rel="stylesheet" href="css/supervisor/calendar-events-modal.css">
    <link rel="stylesheet" href="css/supervisor/calendar-events-modal-enhanced.css">
    <link rel="stylesheet" href="css/supervisor/event-view-modal.css">
    <link rel="stylesheet" href="css/supervisor/enhanced-event-view.css">
    <link rel="stylesheet" href="css/supervisor/greeting-section.css">
    <link rel="stylesheet" href="css/supervisor/travel-expense-modal.css">
    <link rel="stylesheet" href="css/supervisor/supervisor-camera-modal.css">

    
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
        
        .col-lg-1-5 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
        }
        
        @media (min-width: 992px) {
            .col-lg-1-5 {
                -ms-flex: 0 0 20%;
                flex: 0 0 20%;
                max-width: 20%;
            }
        }
        
        .stats-section {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
        }
        
        .stats-section .stat-card {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            cursor: pointer;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .stats-section .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        
        .stats-filters select {
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            font-size: 0.85rem;
            min-width: 100px;
        }
        
        .mr-2 {
            margin-right: 0.5rem;
        }
        
        /* Enhanced styles for stat cards */
        .stat-trend {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .trend-up {
            color: #27ae60;
        }
        
        .trend-down {
            color: #e74c3c;
        }
        
        .stat-footer {
            margin-top: 8px;
            border-top: 1px dashed rgba(0,0,0,0.1);
            padding-top: 8px;
            font-size: 0.8rem;
        }
        
        .stat-secondary {
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-chart {
            height: 20px;
            width: 100%;
            margin-top: 5px;
        }
        
        .stat-sparkline {
            overflow: visible;
        }
        
        /* New styles for additional features */
        .stat-progress {
            margin-top: 5px;
            margin-bottom: 2px;
            background-color: rgba(0,0,0,0.05);
        }
        
        .stat-goal-text {
            font-size: 0.7rem;
            color: #777;
            margin-bottom: 5px;
        }
        
        .stat-actions .btn {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        /* Animated pulse for important stats that need attention */
        @keyframes pulse-animation {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }
        
        .stat-card.needs-attention {
            animation: pulse-animation 2s infinite;
            border: 1px solid #ffc107;
        }
        
        .card-warning-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #ffc107;
            color: #212529;
            font-size: 0.75rem;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 10;
            white-space: nowrap;
        }
        
        .card-warning-badge i {
            margin-right: 4px;
        }
        
        /* Pending expense indicator styles */
        .pending-expense-indicator {
            position: absolute;
            top: 0px;
            right: 0px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            padding: 4px 8px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #f8d7da;
        }
        
        .pending-amount {
            font-weight: bold;
            color: #dc3545;
            font-size: 0.9rem;
        }
        
        .pending-label {
            font-size: 0.7rem;
            color: #dc3545;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Position the stat card as relative for absolute positioning of the indicator */
        .stat-card {
            position: relative;
        }
        
        /* Calendar styles */
        .calendar-nav {
            display: flex;
            align-items: center;
        }
        
        .calendar-nav-btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .current-month-display {
            margin: 0 15px;
            font-weight: 500;
            font-size: 1.1rem;
            min-width: 120px;
            text-align: center;
        }
        
        .site-calendar-container {
            position: relative;
            overflow: hidden;
        }
        
        .site-calendar {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px;
        }
        
        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background-color: #f8f9fa;
            border-radius: 6px 6px 0 0;
            overflow: hidden;
        }
        
        .calendar-header-cell {
            padding: 8px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .calendar-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-gap: 4px;
            padding: 4px;
        }
        
        .calendar-day {
            height: 120px; /* Significantly increased from 90px to 120px */
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 8px; /* Increased padding for more internal space */
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
            overflow: hidden; /* Prevent content overflow */
            display: flex;
            flex-direction: column;
        }
        
        .calendar-day:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .calendar-day.today {
            background-color: #e8f4ff;
            border: 1px solid #4299e1;
        }
        
        .calendar-date-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px; /* Increased from 2px to 4px for more space */
            position: relative;
        }
        
        .calendar-date {
            font-weight: 600;
            font-size: 0.9rem;
            padding: 2px;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-event-btn {
            width: 20px;
            height: 20px;
            border-radius: 18px;
            background-color: #3498db; /* Changed to a lighter blue to match image */
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px; /* Specific font size in px instead of rem */
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0.9;
            border: none;
            padding: 0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1); /* Lighter shadow to match image */
            line-height: 1;
            position: absolute; /* Changed to absolute for precise positioning */
            top: 0; /* Positioned at the top of the container */
            right: 10px; /* Slight offset from right edge */
            border-radius: 18px;
            padding: 10px 14px;
        }
        
        .add-event-btn:hover {
            background-color: #2980b9;
            color: white;
            opacity: 1;
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .calendar-day.other-month .add-event-btn {
            opacity: 0.4;
            background-color: #cbd5e0;
        }
        
        .calendar-day:hover .add-event-btn {
            opacity: 1;
        }
        
        .calendar-day.has-events {
            background-color: #fff8e6;
            border: 1px solid #f6e05e;
        }
        
        .calendar-day.other-month {
            opacity: 0.4;
        }
        
        .calendar-events {
            font-size: 0.7rem;
            overflow: hidden;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .calendar-event {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 3px 6px;
            border-radius: 3px;
            margin-bottom: 3px;
            color: white;
            font-weight: 500;
            font-size: 0.7rem;
            line-height: 1.2;
        }
        
        .event-inspection {
            background-color: #38a169;
        }
        
        .event-delivery {
            background-color: #e67e22; /* More orange color to match image */
        }
        
        .event-meeting {
            background-color: #805ad5;
        }
        
        .event-report {
            background-color: #ffb347; /* More yellowish/orange color */
        }
        
        .event-issue {
            background-color: #e53e3e;
        }
        
        .event-more {
            text-align: center;
            color: #718096;
            font-style: italic;
            font-size: 0.65rem;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .calendar-day {
                height: 110px; /* Significantly increased from 80px to 110px */
            }
            
            .calendar-date {
                width: 24px;
                height: 24px;
                font-size: 0.85rem;
            }
            
            .add-event-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
                top: 0;
                right: 10px;
                border-radius: 18px;
                padding: 10px 14px;
            }
        }
        
        @media (max-width: 768px) {
            .calendar-nav {
                flex-wrap: wrap;
                justify-content: center;
                margin-top: 10px;
            }
            
            .current-month-display {
                order: -1;
                width: 100%;
                margin-bottom: 10px;
            }
            
            .calendar-day {
                height: 100px; /* Significantly increased from 70px to 100px */
            }
            
            .calendar-body {
                grid-gap: 3px; /* Reduced gap to provide more space for cells */
                padding: 3px;
            }
            
            .add-event-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
                top: 0;
                right: 10px;
                border-radius: 18px;
                padding: 10px 14px;
            }
        }
        
        @media (max-width: 576px) {
            .calendar-header-cell {
                font-size: 0.8rem;
                padding: 5px 2px;
            }
            
            .calendar-day {
                height: 80px; /* Significantly increased from 60px to 80px */
                padding: 5px;
            }
            
            .calendar-date {
                font-size: 0.75rem;
                width: 20px;
                height: 20px;
            }
            
            .calendar-event {
                padding: 2px 4px;
                margin-bottom: 2px;
                font-size: 0.65rem;
            }
            
            .add-event-btn {
                width: 16px;
                height: 16px;
                font-size: 10px;
                top: 0;
                right: 10px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.1);
                border-radius: 18px;
                padding: 10px 14px;
            }
            
            /* Allow up to two events on mobile now that we have more space */
            .calendar-events .calendar-event:nth-child(n+3),
            .event-more {
                display: none;
            }
            
            .calendar-events {
                margin-top: 3px;
            }
            
            /* Wider cells on mobile */
            .calendar-body {
                grid-gap: 2px; /* Further reduced gap */
                padding: 2px;
            }
        }
        
        /* Alternative display for smallest screens */
        @media (max-width: 450px) {
            .site-calendar-container {
                overflow-x: auto; /* Allow horizontal scrolling if needed */
                padding-bottom: 10px; /* Space for potential scrollbar */
            }
            
            .site-calendar {
                min-width: 400px; /* Set minimum width to ensure cells are wide enough */
            }
            
            .calendar-day {
                width: auto;
                min-width: 55px; /* Ensure minimum width */
            }
            
            .add-event-btn {
                width: 14px;
                height: 14px;
                font-size: 9px;
                top: 0;
                right: 10px;
                border-radius: 18px;
                padding: 10px 14px;
            }
        }
        
        .today .calendar-date {
            background-color: #3182ce;
            color: white;
        }
        
        /* Calendar Stats Section Styles */
        .calendar-stats-section {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
        }
        
        .supervisor-calendar-wrapper {
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .supervisor-calendar-container {
            width: 100%;
            min-height: 350px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 15px;
        }
        
        .calendar-stats-summary {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 15px;
            height: 100%;
        }
        
        .stats-summary-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .stats-summary-item {
            margin-bottom: 15px;
        }
        
        .stats-summary-item .badge {
            font-size: 0.85rem;
            padding: 5px 8px;
        }
        
        .upcoming-events {
            margin-top: 20px;
        }
        
        .upcoming-event-item {
            display: flex;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .upcoming-event-item:last-child {
            border-bottom: none;
        }
        
        .event-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
            margin-top: 5px;
        }
        
        .event-inspection {
            background-color: #38a169;
        }
        
        .event-delivery {
            background-color: #e67e22;
        }
        
        .event-meeting {
            background-color: #805ad5;
        }
        
        .event-report {
            background-color: #ffb347;
        }
        
        .event-issue {
            background-color: #e53e3e;
        }
        
        .event-details {
            flex: 1;
        }
        
        .event-title {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .event-time {
            color: #666;
            font-size: 0.8rem;
        }
        
        /* Supervisor Calendar Styles */
        .supervisor-calendar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px;
        }
        
        .supervisor-calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background-color: #f8f9fa;
            border-radius: 6px 6px 0 0;
            overflow: hidden;
        }
        
        .supervisor-calendar-header-cell {
            padding: 8px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .supervisor-calendar-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-gap: 4px;
            padding: 4px;
        }
        
        .supervisor-calendar-day {
            height: 80px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 8px;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .supervisor-calendar-day:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .supervisor-calendar-day.today {
            background-color: #e8f4ff;
            border: 1px solid #4299e1;
        }
        
        .supervisor-calendar-date {
            font-weight: 600;
            font-size: 0.9rem;
            padding: 2px;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Add supervisor calendar date container to hold date and add button */
        .supervisor-calendar-date-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
            position: relative;
            width: 100%;
        }
        
        @media (max-width: 576px) {
            .supervisor-calendar-date-container {
                justify-content: flex-start;
                margin-bottom: 2px;
            }
        }
        
        /* Add button styles for supervisor calendar */
        .supervisor-add-event-btn {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0.9;
            border: none;
            padding: 0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            line-height: 1;
            position: relative;
            z-index: 5;
        }
        
        /* Add plus sign using ::before pseudo-element */
        .supervisor-add-event-btn::before {
            content: "+";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
            line-height: 1;
        }
        
        .supervisor-add-event-btn:hover {
            background-color: #2980b9;
            opacity: 1;
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .supervisor-calendar-day.other-month .supervisor-add-event-btn {
            opacity: 0.4;
            background-color: #cbd5e0;
        }
        
        .supervisor-calendar-day:hover .supervisor-add-event-btn {
            opacity: 1;
        }
        
        .supervisor-calendar-events {
            font-size: 0.7rem;
            overflow: hidden;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            margin-top: 5px;
        }
        
        .supervisor-calendar-event {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 2px 4px;
            border-radius: 3px;
            margin-bottom: 2px;
            color: white;
            font-weight: 500;
            font-size: 0.65rem;
            line-height: 1.2;
        }
        
        .supervisor-calendar-day.other-month {
            opacity: 0.4;
        }
        
        .supervisor-event-more {
            text-align: center;
            color: #718096;
            font-style: italic;
            font-size: 0.65rem;
        }
        
        /* Responsive styles for calendar stats */
        @media (max-width: 992px) {
            .supervisor-calendar-container {
                min-height: 300px;
            }
            
            .supervisor-calendar-day {
                height: 70px;
            }
            
            .supervisor-calendar-date {
                width: 22px;
                height: 22px;
                font-size: 0.85rem;
            }
            
            .supervisor-add-event-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
            }
            
            .calendar-stats-summary {
                margin-top: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .supervisor-calendar-container {
                min-height: 250px;
            }
            
            .supervisor-calendar-day {
                height: 60px;
            }
            
            .supervisor-calendar-events .supervisor-calendar-event:nth-child(n+2),
            .supervisor-event-more {
                display: none;
            }
            
            .supervisor-add-event-btn {
                width: 16px;
                height: 16px;
                font-size: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .supervisor-calendar-header-cell {
                font-size: 0.8rem;
                padding: 5px 2px;
            }
            
            .supervisor-calendar-day {
                height: 60px; /* Increased from 50px */
                padding: 5px; /* Increased from 4px */
            }
            
            .supervisor-calendar-date {
                font-size: 0.8rem; /* Increased from 0.75rem */
                width: 28px; /* Increased from 26px */
                height: 28px; /* Increased from 26px */
                font-weight: bold;
            }
            
            .supervisor-calendar-date-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 4px; /* Increased from 2px */
                position: relative;
                padding-right: 20px; /* Make space for the button */
            }
            
            .supervisor-calendar-event {
                padding: 1px 3px;
                font-size: 0.65rem; /* Increased from 0.6rem */
                margin-bottom: 2px;
            }
            
            .supervisor-add-event-btn {
                width: 18px;
                height: 18px;
                position: absolute;
                top: 50%;
                right: 0;
                transform: translateY(-50%);
                opacity: 1;
                background-color: #2196F3;
                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            }
            
            .supervisor-add-event-btn::before {
                font-size: 12px;
                font-weight: bold;
            }
            
            .supervisor-calendar-day.other-month .supervisor-add-event-btn {
                opacity: 0.6;
            }
            
            .supervisor-calendar-body {
                grid-gap: 3px;
                padding: 3px;
            }
            
            .supervisor-calendar-day.today .supervisor-calendar-date {
                background-color: #3182ce;
                color: white;
            }
            
            .stats-summary-title {
                font-size: 1rem;
            }
            
            .event-title {
                font-size: 0.85rem;
            }
            
            .event-time {
                font-size: 0.75rem;
            }
        }
        
        /* Even smaller screens */
        @media (max-width: 450px) {
            .supervisor-calendar-container {
                padding: 10px 5px;
            }
            
            .supervisor-calendar-day {
                height: 55px;
                padding: 3px;
            }
            
            .supervisor-calendar-date {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
            }
            
            .supervisor-calendar-date-container {
                margin-bottom: 2px;
            }
            
            .supervisor-calendar-body {
                grid-gap: 2px;
                padding: 2px;
            }
        }
        
        /* Punch Camera Modal Styles */
        .punch-camera-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        .punch-camera-header {
            padding: 15px;
            background-color: #3498db;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .punch-camera-body {
            padding: 15px;
            overflow-y: auto;
            flex-grow: 1;
        }
        
        .punch-camera-footer {
            padding: 15px;
            background: #f9f9f9;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
        }
        
        .punch-work-report {
            margin-top: 10px;
            margin-bottom: 10px;
            max-height: 150px;
            overflow-y: auto;
        }
        
        @media (max-height: 600px) {
            .punch-work-report {
                max-height: 100px;
            }
            
            .punch-work-report textarea {
                max-height: 60px;
            }
            
            .punch-video-wrapper, .punch-captured-image-wrapper {
                max-height: 180px;
            }
            
            .punch-location-info {
                margin-top: 5px;
                margin-bottom: 5px;
                font-size: 0.85rem;
            }
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
                    <div class="dashboard-card greeting-section">
                        <div class="row align-items-center">
                            <div class="col-12">
                                <h2 class="greeting-text">
                                    <i id="greeting-icon" class="fas fa-sun"></i> <span id="greeting-time">Good morning</span>, <span class="greeting-name"><?php echo htmlspecialchars($username); ?></span>!
                                </h2>
                                <div class="greeting-small-time">
                                    <i class="fas fa-clock"></i> <span id="small-current-time">Loading time...</span>
                                    <span class="greeting-small-separator">•</span>
                                    <i class="fas fa-calendar-alt"></i> <span id="small-current-date">Loading date...</span>
                                </div>
                                <div class="greeting-actions">
                                    <div class="shift-time-remaining">
                                        <div class="time-display">
                                            <i class="fas fa-hourglass-half"></i> Shift ends in: <span id="shift-remaining-time">Loading...</span>
                                        </div>
                                        <!-- Shift info will be inserted here by JavaScript -->
                                    </div>
                                    <div class="punch-button-container">
                                        <!-- Hide the original button with CSS but keep it in the DOM -->
                                        <button id="punchButton" class="btn btn-success punch-button" style="display: none;">
                                            <i class="fas fa-sign-in-alt"></i> Punch In
                                        </button>
                                        <!-- Keep the Punch In 2 button with improved styling -->
                                        <button id="supervisorCameraBtn" class="btn btn-primary supervisor-camera-button" style="border-radius: 50px; padding: 8px 20px; font-weight: 500; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.2s ease;">
                                            <i class="fas fa-camera"></i> Punch In
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Camera Container for Punch In/Out -->
            <div id="cameraContainer" class="punch-camera-container">
                <div class="punch-camera-overlay"></div>
                <div class="punch-camera-content">
                    <div class="punch-camera-header">
                        <h4 id="camera-title">Take Selfie for Punch In</h4>
                        <button id="closeCameraBtn" class="punch-close-camera-btn"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="punch-camera-body">
                        <div class="punch-video-wrapper">
                            <video id="cameraVideo" autoplay playsinline></video>
                            <canvas id="cameraCanvas" style="display: none;"></canvas>
                            <div id="cameraCaptureBtn" class="punch-camera-capture-btn">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <div class="punch-captured-image-wrapper" style="display: none;">
                            <img id="capturedImage" src="" alt="Captured selfie">
                        </div>
                        <div class="punch-location-info">
                            <div class="punch-location-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span id="locationStatus">Getting your location...</span>
                            </div>
                            <div class="punch-location-item">
                                <i class="fas fa-globe"></i>
                                <span id="locationCoords">Latitude: -- | Longitude: --</span>
                            </div>
                            <div class="punch-location-item">
                                <i class="fas fa-map"></i>
                                <span id="locationAddress">Address: --</span>
                            </div>
                        </div>
                        
                        <!-- Work Report Section (shown only when punching out) -->
                        <div id="workReportSection" class="punch-work-report" style="display: none;">
                            <div class="work-report-header">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Daily Work Report</span>
                            </div>
                            <div class="form-group">
                                <textarea id="workReportText" class="form-control" rows="4" placeholder="Enter details about your work today..."></textarea>
                                <small class="form-text text-muted">Please provide a brief description of tasks completed today.</small>
                            </div>
                        </div>
                    </div>
                    <div class="punch-camera-footer">
                        <button id="retakePhotoBtn" class="btn btn-secondary punch-retake-btn" style="display: none;">
                            <i class="fas fa-redo"></i> Retake
                        </button>
                        <button id="confirmPunchBtn" class="btn btn-primary punch-confirm-btn" style="display: none;">
                            <i class="fas fa-check"></i> Confirm Punch
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Stats Overview Row -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card stats-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Stats Overview</h4>
                            <div class="stats-filters">
                                <div class="d-flex">
                                    <select class="form-control form-control-sm mr-2" id="statsMonthFilter">
                                        <option value="1" <?php echo date('n') == 1 ? 'selected' : ''; ?>>January</option>
                                        <option value="2" <?php echo date('n') == 2 ? 'selected' : ''; ?>>February</option>
                                        <option value="3" <?php echo date('n') == 3 ? 'selected' : ''; ?>>March</option>
                                        <option value="4" <?php echo date('n') == 4 ? 'selected' : ''; ?>>April</option>
                                        <option value="5" <?php echo date('n') == 5 ? 'selected' : ''; ?>>May</option>
                                        <option value="6" <?php echo date('n') == 6 ? 'selected' : ''; ?>>June</option>
                                        <option value="7" <?php echo date('n') == 7 ? 'selected' : ''; ?>>July</option>
                                        <option value="8" <?php echo date('n') == 8 ? 'selected' : ''; ?>>August</option>
                                        <option value="9" <?php echo date('n') == 9 ? 'selected' : ''; ?>>September</option>
                                        <option value="10" <?php echo date('n') == 10 ? 'selected' : ''; ?>>October</option>
                                        <option value="11" <?php echo date('n') == 11 ? 'selected' : ''; ?>>November</option>
                                        <option value="12" <?php echo date('n') == 12 ? 'selected' : ''; ?>>December</option>
                                    </select>
                                    <select class="form-control form-control-sm" id="statsYearFilter">
                                        <?php 
                                        $currentYear = date('Y');
                                        for($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
                                            echo '<option value="'.$year.'" '.($year == $currentYear ? 'selected' : '').'>'.$year.'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card">
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h4>42</h4>
                                        <p>Active Workers</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calendar Stats Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card calendar-stats-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Calendar Stats</h4>
                            <div class="calendar-nav">
                                <button id="prevMonthCalStats" class="btn btn-sm btn-outline-secondary calendar-nav-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span id="currentMonthCalStats" class="current-month-display">May 2023</span>
                                <button id="nextMonthCalStats" class="btn btn-sm btn-outline-secondary calendar-nav-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="calendar-stats-container">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="supervisor-calendar-wrapper">
                                        <div id="supervisorCalendar" class="supervisor-calendar-container">
                                            <!-- Calendar will be rendered here by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="calendar-stats-summary">
                                        <h5 class="stats-summary-title">Recent Month Targets</h5>
                                        <div class="monthly-targets-section">
                                            <!-- Monthly Targets -->
                                            <div class="monthly-targets-group">
                                                <div class="monthly-targets-header">
                                                    <div class="monthly-targets-title">
                                                        <i class="fas fa-bullseye"></i> Monthly Objectives
                                                    </div>
                                                    <div class="monthly-targets-period-selector">
                                                        <select class="monthly-targets-period-dropdown" id="monthlyTargetPeriod">
                                                            <option value="previous">Previous Month</option>
                                                            <option value="present" selected>Current Month</option>
                                                            <option value="next">Next Month</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <!-- Current Month Targets -->
                                                <ul class="monthly-targets-list" id="currentMonthTargets">
                                                    <li class="target-item">Complete foundation work for entire building</li>
                                                    <li class="target-item">Finish structural framework for floors 1-3</li>
                                                    <li class="target-item">Install main electrical panels and distribution</li>
                                                    <li class="target-item">Complete plumbing rough-in for ground floor</li>
                                                    <li class="target-item">Finish exterior wall construction</li>
                                                    <li class="target-item">Begin window installation on completed floors</li>
                                                    <li class="target-item">Set up permanent site security measures</li>
                                                    <li class="target-item">Complete drainage system installation</li>
                                                    <li class="target-item">Begin HVAC ductwork installation</li>
                                                    <li class="target-item">Complete monthly safety inspection and reporting</li>
                                                </ul>
                                                
                                                <!-- Previous Month Targets -->
                                                <ul class="monthly-targets-list" id="previousMonthTargets" style="display: none;">
                                                    <li class="target-item">Site preparation and excavation completed</li>
                                                    <li class="target-item">Temporary facilities and utilities set up</li>
                                                    <li class="target-item">Initial foundation layout and marking</li>
                                                    <li class="target-item">Procurement of primary building materials</li>
                                                    <li class="target-item">Approval of final architectural drawings</li>
                                                    <li class="target-item">Soil testing and foundation preparation</li>
                                                    <li class="target-item">Security fencing and site access control</li>
                                                    <li class="target-item">Environmental compliance measures implemented</li>
                                                    <li class="target-item">Subcontractor agreements finalized</li>
                                                    <li class="target-item">Initial site drainage systems installed</li>
                                                </ul>
                                                
                                                <!-- Next Month Targets -->
                                                <ul class="monthly-targets-list" id="nextMonthTargets" style="display: none;">
                                                    <li class="target-item">Begin interior wall framing on all floors</li>
                                                    <li class="target-item">Complete roof structure and waterproofing</li>
                                                    <li class="target-item">Install electrical wiring in completed sections</li>
                                                    <li class="target-item">Begin plumbing fixture installation</li>
                                                    <li class="target-item">Start interior drywall installation</li>
                                                    <li class="target-item">Complete all window and exterior door installation</li>
                                                    <li class="target-item">Begin exterior finishing and cladding</li>
                                                    <li class="target-item">Install fire suppression systems</li>
                                                    <li class="target-item">Begin elevator shaft construction</li>
                                                    <li class="target-item">Prepare for initial building inspection</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <!-- Upcoming Events section removed -->
                                    </div>
                                </div>
                            </div>
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
    <script src="js/supervisor/calendar-modal.js"></script>
    <script src="js/supervisor/calendar-stats.js"></script>
    <script src="js/supervisor/calendar-events-save.js"></script>
    <script src="js/supervisor/calendar-events-modal.js"></script>
    <script src="js/supervisor/date-events-modal.js"></script>
    <script src="js/supervisor/enhanced-event-view-modal.js"></script>
    <script src="js/supervisor/greeting-section.js"></script>
    <script src="js/supervisor/travel-expense-modal.js"></script>
    <script src="js/supervisor/supervisor-camera-module.js"></script>

    <!-- Add Chart.js for the attendance chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    
    <!-- Active Workers Modal Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize attendance chart
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            
            // Get last 7 days for labels
            const labels = [];
            for (let i = 6; i >= 0; i--) {
                const d = new Date();
                d.setDate(d.getDate() - i);
                labels.push(d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
            }
            
            // Sample data - in production, this would come from the database
            const attendanceData = {
                morning: [<?php
                    // Generate data for morning attendance over the last 7 days
                    $morning_data_points = [];
                    for ($i = 6; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days"));
                        
                        $day_query = "SELECT COUNT(*) as day_count 
                                     FROM sv_company_labours 
                                     WHERE morning_attendance = 1
                                     AND attendance_date = ? 
                                     AND is_deleted = 0";
                        
                        $day_stmt = $conn->prepare($day_query);
                        $day_stmt->bind_param("s", $date);
                        $day_stmt->execute();
                        $day_result = $day_stmt->get_result();
                        $day_data = $day_result->fetch_assoc();
                        
                        $morning_data_points[] = $day_data['day_count'] ? $day_data['day_count'] : 0;
                    }
                    echo implode(', ', $morning_data_points);
                ?>],
                evening: [<?php
                    // Generate data for evening attendance over the last 7 days
                    $evening_data_points = [];
                    for ($i = 6; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days"));
                        
                        $day_query = "SELECT COUNT(*) as day_count 
                                     FROM sv_company_labours 
                                     WHERE evening_attendance = 1
                                     AND attendance_date = ? 
                                     AND is_deleted = 0";
                        
                        $day_stmt = $conn->prepare($day_query);
                        $day_stmt->bind_param("s", $date);
                        $day_stmt->execute();
                        $day_result = $day_stmt->get_result();
                        $day_data = $day_result->fetch_assoc();
                        
                        $evening_data_points[] = $day_data['day_count'] ? $day_data['day_count'] : 0;
                    }
                    echo implode(', ', $evening_data_points);
                ?>]
            };
            
            const attendanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Morning Attendance',
                            data: attendanceData.morning,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            borderWidth: 2,
                            pointBackgroundColor: '#3498db',
                            pointRadius: 4,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Evening Attendance',
                            data: attendanceData.evening,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            borderWidth: 2,
                            pointBackgroundColor: '#2ecc71',
                            pointRadius: 4,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                precision: 0
                            },
                            gridLines: {
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }],
                        xAxes: [{
                            gridLines: {
                                display: false
                            }
                        }]
                    },
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    },
                    tooltips: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleFontColor: '#fff',
                        bodyFontColor: '#fff',
                        bodySpacing: 4,
                        xPadding: 12,
                        mode: 'index',
                        intersect: false
                    }
                }
            });
            
            // Filter functionality for the workers table
            const workerFilterSelect = document.getElementById('workerFilterSelect');
            const workerRows = document.querySelectorAll('.worker-row');
            
            workerFilterSelect.addEventListener('change', function() {
                const filterValue = this.value;
                
                workerRows.forEach(row => {
                    const morningAttendance = row.getAttribute('data-morning') === '1';
                    const eveningAttendance = row.getAttribute('data-evening') === '1';
                    
                    switch(filterValue) {
                        case 'morning':
                            row.style.display = morningAttendance ? '' : 'none';
                            break;
                        case 'evening':
                            row.style.display = eveningAttendance ? '' : 'none';
                            break;
                        case 'both':
                            row.style.display = (morningAttendance && eveningAttendance) ? '' : 'none';
                            break;
                        default: // 'all'
                            row.style.display = '';
                    }
                });
            });
            
            // Refresh button functionality
            document.getElementById('refreshWorkersBtn').addEventListener('click', function() {
                // In a real implementation, this would reload the data via AJAX
                this.classList.add('spin');
                setTimeout(() => {
                    this.classList.remove('spin');
                }, 1000);
            });
        });
    </script>
    
    <!-- Override native alerts for calendar messages -->
    <script>
        // Override the native alert for calendar-related messages
        const originalAlert = window.alert;
        window.alert = function(message) {
            // Check if the message is a calendar event notification
            if (message && message.includes && (
                message.includes('No events on') || 
                message.includes('Events on') || 
                message.includes('Add new event on')
            )) {
                console.log('Suppressed alert:', message);
                return; // Don't show the alert
            }
            
            // For other alerts, use the original function
            originalAlert(message);
        };
    </script>
    
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
                            max-height: 90vh;
                            display: flex;
                            flex-direction: column;
                        }
                        .camera-header {
                            padding: 15px;
                            background-color: #3498db;
                            color: white;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            flex-shrink: 0;
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
                            flex-shrink: 0;
                            position: sticky;
                            bottom: 0;
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
    
    <!-- Update JavaScript for enhanced filter functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Get filter elements
        const monthFilter = document.getElementById('statsMonthFilter');
        const yearFilter = document.getElementById('statsYearFilter');
        
        // Add event listeners
        monthFilter.addEventListener('change', updateStatsData);
        yearFilter.addEventListener('change', updateStatsData);
        
        // Add click handler for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Only navigate if the click wasn't on a button
                if (!e.target.closest('.stat-actions')) {
                    const detailLink = this.querySelector('.stat-actions a');
                    if (detailLink) {
                        window.location.href = detailLink.getAttribute('href');
                    }
                }
            });
        });
        
        function updateStatsData() {
            // Get selected values
            const selectedMonth = monthFilter.value;
            const selectedYear = yearFilter.value;
            
            // Show loading state
            const statValues = document.querySelectorAll('.stat-details h3');
            statValues.forEach(el => {
                el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            });
            
            const trendIndicators = document.querySelectorAll('.stat-trend');
            trendIndicators.forEach(el => {
                el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            });
            
            const secondaryMetrics = document.querySelectorAll('.stat-secondary small');
            secondaryMetrics.forEach(el => {
                el.innerHTML = 'Loading...';
            });
            
            const progressBars = document.querySelectorAll('.stat-progress .progress-bar');
            progressBars.forEach(el => {
                el.style.width = '0%';
            });
            
            const goalTexts = document.querySelectorAll('.stat-goal-text small:last-child');
            goalTexts.forEach(el => {
                el.innerHTML = 'Loading...';
            });
            
            // In a real implementation, you would fetch data from the server
            // For now, we'll simulate a delay and then show random data
            setTimeout(() => {
                // Simulate data - in real implementation, this would come from AJAX
                const mockData = {
                    activeWorkers: {
                        value: Math.floor(Math.random() * 50) + 30,
                        trend: Math.floor(Math.random() * 15) - 5,
                        secondary: 'Attendance Rate: ' + (Math.floor(Math.random() * 10) + 90) + '%',
                        sparkline: generateSparkline(),
                        goal: 50,
                        progress: Math.floor(Math.random() * 30) + 70,
                        needsAttention: false
                    },
                    activeProjects: {
                        value: Math.floor(Math.random() * 10) + 5,
                        trend: Math.floor(Math.random() * 6) - 2,
                        secondary: 'Completion Rate: ' + (Math.floor(Math.random() * 20) + 70) + '%',
                        sparkline: generateSparkline(),
                        goal: 10,
                        progress: Math.floor(Math.random() * 20) + 60,
                        needsAttention: false
                    },
                    taskEfficiency: {
                        value: Math.floor(Math.random() * 30) + 65 + '%',
                        trend: Math.floor(Math.random() * 10) - 3,
                        secondary: 'Previous: ' + (Math.floor(Math.random() * 30) + 65) + '%',
                        sparkline: generateSparkline(),
                        goal: '85%',
                        progress: Math.floor(Math.random() * 15) + 75,
                        toGoal: Math.floor(Math.random() * 10) + 85,
                        needsAttention: false
                    },
                    completedTasks: {
                        value: Math.floor(Math.random() * 20) + 10,
                        trend: Math.floor(Math.random() * 10) - 5,
                        secondary: 'This Week: ' + (Math.floor(Math.random() * 8) + 1),
                        sparkline: generateSparkline(),
                        goal: 20,
                        progress: Math.floor(Math.random() * 40) + 40,
                        needsAttention: Math.random() > 0.7
                    },
                    travelExpenses: {
                        value: '₹' + (Math.floor(Math.random() * 8000) + 2000),
                        trend: Math.floor(Math.random() * 20) - 15,
                        secondary: 'Avg/Worker: ₹' + (Math.floor(Math.random() * 150) + 50),
                        sparkline: generateSparkline(),
                        budget: '₹10,000',
                        progress: Math.floor(Math.random() * 60) + 20,
                        needsAttention: Math.random() > 0.8
                    }
                };
                
                // Update the stats
                const statCards = document.querySelectorAll('.stat-card');
                
                // Card 1: Active Workers
                updateCard(statCards[0], mockData.activeWorkers, 'Complete');
                
                // Card 2: Active Projects
                updateCard(statCards[1], mockData.activeProjects, 'Complete');
                
                // Card 3: Task Efficiency
                updateCard(statCards[2], mockData.taskEfficiency, 'to Goal');
                
                // Card 4: Completed Tasks
                updateCard(statCards[3], mockData.completedTasks, 'Complete');
                
                // Card 5: Travel Expenses
                updateCard(statCards[4], mockData.travelExpenses, 'Used');
            }, 800);
        }
        
        // Helper function to update a card with data
        function updateCard(card, data, progressLabel) {
            // Update main value
            card.querySelector('h3').textContent = data.value;
            
            // Update trend indicator
            const trendElement = card.querySelector('.stat-trend');
            const isTrendUp = data.trend > 0;
            trendElement.className = 'stat-trend ' + (isTrendUp ? 'trend-up' : 'trend-down');
            trendElement.innerHTML = '<i class="fas fa-arrow-' + (isTrendUp ? 'up' : 'down') + '"></i> ' + 
                                   (Math.abs(data.trend) + (isNaN(parseInt(data.trend)) ? '%' : ''));
            
            // Update secondary metric
            card.querySelector('.stat-secondary small').textContent = data.secondary;
            
            // Update sparkline if available
            if(data.sparkline && card.querySelector('.stat-sparkline polyline')) {
                card.querySelector('.stat-sparkline polyline').setAttribute('points', data.sparkline);
            }
            
            // Update progress bar
            if (data.progress !== undefined) {
                const progressBar = card.querySelector('.progress-bar');
                progressBar.style.width = data.progress + '%';
                progressBar.setAttribute('aria-valuenow', data.progress);
            }
            
            // Update goal text
            if (data.goal !== undefined) {
                card.querySelectorAll('.stat-goal-text small')[0].textContent = 
                    data.hasOwnProperty('budget') ? 'Budget: ' + data.budget : 'Goal: ' + data.goal;
                
                card.querySelectorAll('.stat-goal-text small')[1].textContent = 
                    (data.toGoal || data.progress) + '% ' + progressLabel;
            }
            
            // Add attention animation if needed
            if (data.needsAttention) {
                card.classList.add('needs-attention');
            } else {
                card.classList.remove('needs-attention');
            }
        }
        
        // Generate random sparkline data points
        function generateSparkline() {
            let points = '';
            for (let i = 0; i < 10; i++) {
                const x = i * 10;
                const y = Math.floor(Math.random() * 15) + 1;
                points += x + ',' + y + ' ';
            }
            return points.trim();
        }
        });
    </script>
    
    <!-- Add JavaScript for the calendar functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Calendar Stats functionality
        const calendarContainer = document.getElementById('supervisorCalendar');
        const currentMonthDisplay = document.getElementById('currentMonthCalStats');
        const prevMonthBtn = document.getElementById('prevMonthCalStats');
        const nextMonthBtn = document.getElementById('nextMonthCalStats');
        
        // Set initial date to current month/year
        let currentDate = new Date();
        
        // Event listeners for navigation buttons
        prevMonthBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderSupervisorCalendar();
        });
        
        nextMonthBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderSupervisorCalendar();
        });
        
        // Function to render the calendar
        function renderSupervisorCalendar() {
            // Get current month and year
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Update the month display
            const monthNames = ["January", "February", "March", "April", "May", "June",
                               "July", "August", "September", "October", "November", "December"];
            currentMonthDisplay.textContent = `${monthNames[month]} ${year}`;
            
            // Get the first day of the month
            const firstDay = new Date(year, month, 1);
            const startingDay = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
            
            // Get the number of days in the month
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            // Get the number of days in the previous month
            const prevMonth = month === 0 ? 11 : month - 1;
            const prevYear = month === 0 ? year - 1 : year;
            const daysInPrevMonth = new Date(prevYear, prevMonth + 1, 0).getDate();
            
            // Create calendar HTML
            let calendarHTML = `
                <div class="supervisor-calendar-header">
                    <div class="supervisor-calendar-header-cell">Sun</div>
                    <div class="supervisor-calendar-header-cell">Mon</div>
                    <div class="supervisor-calendar-header-cell">Tue</div>
                    <div class="supervisor-calendar-header-cell">Wed</div>
                    <div class="supervisor-calendar-header-cell">Thu</div>
                    <div class="supervisor-calendar-header-cell">Fri</div>
                    <div class="supervisor-calendar-header-cell">Sat</div>
                </div>
                <div class="supervisor-calendar-body">
            `;
            
            // Get today's date for highlighting
            const today = new Date();
            const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
            
            // Generate days from previous month (if needed)
            let dayCount = 1;
            for (let i = 0; i < startingDay; i++) {
                const prevMonthDay = daysInPrevMonth - startingDay + i + 1;
                calendarHTML += createSupervisorDayCell(prevMonthDay, true, false, []);
            }
            
            // Generate days for current month
            const sampleEvents = generateSampleCalendarEvents(year, month, daysInMonth);
            
            for (let day = 1; day <= daysInMonth; day++) {
                const isToday = isCurrentMonth && today.getDate() === day;
                const dayEvents = sampleEvents[day] || [];
                calendarHTML += createSupervisorDayCell(day, false, isToday, dayEvents);
                dayCount++;
            }
            
            // Generate days for next month (if needed)
            const totalCells = Math.ceil((startingDay + daysInMonth) / 7) * 7;
            const nextMonthDays = totalCells - (startingDay + daysInMonth);
            
            for (let day = 1; day <= nextMonthDays; day++) {
                calendarHTML += createSupervisorDayCell(day, true, false, []);
            }
            
            calendarHTML += `</div>`;
            
            // Add calendar footer with legend
            calendarHTML += `
                <!-- Calendar Footer with Legend -->
                <div class="calendar-footer">
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <span class="legend-color legend-inspection"></span>
                            <span>Inspection</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color legend-meeting"></span>
                            <span>Meeting</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color legend-delivery"></span>
                            <span>Delivery</span>
                        </div>
                    </div>
                    <div class="calendar-actions">
                        <button class="btn btn-sm btn-outline-primary">View All Events</button>
                    </div>
                </div>
            `;
            
            // Update the calendar
            calendarContainer.innerHTML = calendarHTML;
            
            // Add click events for day cells
            setupSupervisorCalendarInteractions(sampleEvents, month, year);
        }
        
        // Function to set up calendar interactions
        function setupSupervisorCalendarInteractions(events, month, year) {
            // Add click event for day cells
            document.querySelectorAll('.supervisor-calendar-day').forEach(cell => {
                cell.addEventListener('click', function() {
                    const dayNumber = this.getAttribute('data-day');
                    const monthNumber = parseInt(this.getAttribute('data-month'));
                    const yearNumber = parseInt(this.getAttribute('data-year'));
                    const isOtherMonth = this.classList.contains('other-month');
                    
                    if (isOtherMonth) {
                        // Navigate to the clicked month
                        currentDate = new Date(yearNumber, monthNumber - 1, 1);
                        renderSupervisorCalendar();
                        return;
                    }
                    
                    // Show events for this day (you can implement a modal or other UI)
                    const dayEvents = events[dayNumber] || [];
                    if (dayEvents.length > 0) {
                        // For now, just show an alert with the events
                        let eventsList = `Events on ${monthNumber}/${dayNumber}/${yearNumber}:\n`;
                        dayEvents.forEach(event => {
                            eventsList += `- ${event.time}: ${event.title} (${event.type})\n`;
                        });
                        alert(eventsList);
                    } else {
                        alert(`No events on ${monthNumber}/${dayNumber}/${yearNumber}`);
                    }
                });
            });
        }
        
        // Function to create a day cell
        function createSupervisorDayCell(day, isOtherMonth, isToday, events) {
            const hasEvents = events.length > 0;
            let cellClass = 'supervisor-calendar-day';
            
            if (isOtherMonth) cellClass += ' other-month';
            if (isToday) cellClass += ' today';
            if (hasEvents) cellClass += ' has-events';
            
            // Calculate the month value for this cell (for data attributes)
            let cellMonth, cellYear;
            
            if (isOtherMonth) {
                if (day > 20) {
                    // Previous month
                    cellMonth = currentDate.getMonth(); // 0-indexed
                    if (cellMonth === 0) {
                        cellMonth = 12; // December
                        cellYear = currentDate.getFullYear() - 1;
                    } else {
                        cellYear = currentDate.getFullYear();
                    }
                } else {
                    // Next month
                    cellMonth = currentDate.getMonth() + 2; // +2 because we're already 0-indexed
                    if (cellMonth === 13) {
                        cellMonth = 1; // January
                        cellYear = currentDate.getFullYear() + 1;
                    } else {
                        cellYear = currentDate.getFullYear();
                    }
                }
            } else {
                // Current month
                cellMonth = currentDate.getMonth() + 1; // +1 to convert from 0-indexed to 1-indexed
                cellYear = currentDate.getFullYear();
            }
            
            let cellHTML = `<div class="${cellClass}" data-day="${day}" data-month="${cellMonth}" data-year="${cellYear}">
                <div class="supervisor-calendar-date-container">
                    <div class="supervisor-calendar-date">${day}</div>
                    <button class="supervisor-add-event-btn" data-day="${day}" data-month="${cellMonth}" data-year="${cellYear}"></button>
                </div>`;
            
            if (hasEvents) {
                cellHTML += `<div class="supervisor-calendar-events">`;
                
                // Show max 1 event on mobile, 2 on larger screens
                const displayCount = Math.min(2, events.length);
                for (let i = 0; i < displayCount; i++) {
                    const event = events[i];
                    cellHTML += `<div class="supervisor-calendar-event event-${event.type}" title="${event.time}: ${event.title}">
                        ${event.title}
                    </div>`;
                }
                
                if (events.length > 2) {
                    cellHTML += `<div class="supervisor-event-more">+${events.length - 2} more</div>`;
                }
                
                cellHTML += `</div>`;
            }
            
            cellHTML += `</div>`;
            
            return cellHTML;
        }
        
        // Function to generate sample events (this would be replaced with real data)
        function generateSampleCalendarEvents(year, month, daysInMonth) {
            const events = {};
            const eventTypes = ['inspection', 'delivery', 'meeting', 'report', 'issue'];
            const eventTitles = {
                'inspection': ['Safety Inspection', 'Quality Check', 'Equipment Inspection'],
                'delivery': ['Material Delivery', 'Equipment Arrival', 'Supplies Delivery'],
                'meeting': ['Team Meeting', 'Client Review', 'Planning Session'],
                'report': ['Progress Report', 'Financial Report', 'Weekly Report'],
                'issue': ['Plumbing Issue', 'Electrical Problem', 'Structural Concern']
            };
            
            // Add 15-20 random events throughout the month
            const numEvents = 15 + Math.floor(Math.random() * 6);
            
            for (let i = 0; i < numEvents; i++) {
                const day = Math.floor(Math.random() * daysInMonth) + 1;
                const eventType = eventTypes[Math.floor(Math.random() * eventTypes.length)];
                const eventTitle = eventTitles[eventType][Math.floor(Math.random() * eventTitles[eventType].length)];
                
                // Random time between 8 AM and 5 PM
                const hour = 8 + Math.floor(Math.random() * 10);
                const minute = Math.floor(Math.random() * 4) * 15; // 0, 15, 30, 45
                const time = `${hour}:${minute === 0 ? '00' : minute} ${hour >= 12 ? 'PM' : 'AM'}`;
                
                if (!events[day]) events[day] = [];
                
                events[day].push({
                    type: eventType,
                    title: eventTitle,
                    time: time
                });
            }
            
            // Sort events by time
            for (const day in events) {
                events[day].sort((a, b) => {
                    return a.time.localeCompare(b.time);
                });
            }
            
            return events;
        }
        
        // Update stats based on calendar data
        function updateCalendarStats() {
            // In a real implementation, this would fetch data from the server
            // For now, we'll just show some static data
            
            // Update event counts
            document.querySelectorAll('.stats-summary-item .badge').forEach((badge, index) => {
                const counts = [12, 8, 15, 6, 3]; // Sample counts for each event type
                badge.textContent = counts[index];
                
                // Update progress bars
                const progressPercentages = [75, 60, 85, 45, 25]; // Sample percentages
                const progressBar = badge.closest('.stats-summary-item').querySelector('.progress-bar');
                progressBar.style.width = progressPercentages[index] + '%';
                progressBar.setAttribute('aria-valuenow', progressPercentages[index]);
            });
        }
        
        // Initial render
        renderSupervisorCalendar();
        updateCalendarStats();
    });
    </script>
    
    <!-- Travel Expense Modal -->
    <div class="modal fade" id="travelExpenseModal" tabindex="-1" role="dialog" aria-labelledby="travelExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="travelExpenseModalLabel">Add Travel Expense</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="travel-expenses-container">
                        <div class="travel-expenses-list">
                            <!-- Travel expense entries will be added here dynamically -->
                        </div>
                        
                        <form id="travelExpenseForm">
                            <div class="row form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purposeOfVisit">Purpose of Visit</label>
                                        <input type="text" class="form-control" id="purposeOfVisit" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modeOfTransport">Mode of Transport</label>
                                        <select class="form-control" id="modeOfTransport" required>
                                            <option value="">Select mode</option>
                                            <option value="Car">Car</option>
                                            <option value="Bike">Bike</option>
                                            <option value="Public Transport">Public Transport</option>
                                            <option value="Taxi">Taxi</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fromLocation">From</label>
                                        <input type="text" class="form-control" id="fromLocation" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="toLocation">To</label>
                                        <input type="text" class="form-control" id="toLocation" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="travelDate">Date</label>
                                        <input type="date" class="form-control" id="travelDate" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="approxDistance">Approx Distance (km)</label>
                                        <input type="number" min="0" step="0.1" class="form-control" id="approxDistance" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="totalExpense">Total Expenses (₹)</label>
                                        <input type="number" min="0" step="0.01" class="form-control" id="totalExpense" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="expenseNotes">Notes (Optional)</label>
                                <textarea class="form-control" id="expenseNotes" rows="2"></textarea>
                            </div>
                            
                            <div class="text-right">
                                <button type="button" class="btn btn-outline-secondary" id="resetExpenseForm">Reset</button>
                                <button type="button" class="btn btn-primary" id="addExpenseEntry">Add Entry</button>
                            </div>
                        </form>
                        
                        <div class="travel-expenses-summary mt-4" style="display: none;">
                            <h5>Summary</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p>Total Entries: <span id="totalEntries">0</span></p>
                                </div>
                                <div class="col-md-6 text-right">
                                    <p>Total Amount: ₹<span id="totalAmount">0.00</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="saveAllExpenses">Save All Expenses</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Supervisor Camera Modal -->
    <div id="supervisorCameraModal" class="supervisor-camera-modal-overlay">
        <div class="supervisor-camera-modal-content">
            <div class="supervisor-camera-modal-header">
                <h4>Supervisor Camera</h4>
                <button id="closeSupervisorCameraBtn" class="supervisor-camera-close-btn"><i class="fas fa-times"></i></button>
            </div>
            <div class="supervisor-camera-modal-body">
                <div class="supervisor-camera-container">
                    <video id="supervisorCameraVideo" autoplay playsinline></video>
                    <canvas id="supervisorCameraCanvas" style="display: none;"></canvas>
                    <div id="supervisorCameraCaptureBtn" class="supervisor-camera-capture-btn">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <div class="supervisor-captured-image-container" style="display: none;">
                    <img id="supervisorCapturedImage" src="" alt="Captured image">
                </div>
            </div>
            <div class="supervisor-camera-modal-footer">
                <button id="supervisorRetakeBtn" class="btn btn-secondary" style="display: none;">Retake</button>
                <button id="supervisorSaveImageBtn" class="btn btn-success" style="display: none;">Save Image</button>
            </div>
        </div>
    </div>
    
    <!-- Active Workers Modal -->
    <div class="modal fade" id="activeWorkersModal" tabindex="-1" role="dialog" aria-labelledby="activeWorkersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title" id="activeWorkersModalLabel">
                        <i class="fas fa-users mr-2"></i>Active Workers Today
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body py-4">
                    <div class="active-workers-container">
                        <!-- Summary Cards -->
                        <div class="workers-summary mb-4">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="usage-stat-card bg-gradient-info text-white rounded shadow-sm p-3 h-100">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon-bg mr-3">
                                                <i class="fas fa-user-check fa-2x"></i>
                                            </div>
                                            <div>
                                                <?php
                                                                                                 // Get count of morning attendance with worker details
                                                $morning_query = "SELECT COUNT(*) as morning_count 
                                                                 FROM sv_company_labours 
                                                                 WHERE morning_attendance = 1 
                                                                 AND attendance_date = ? 
                                                                 AND is_deleted = 0";
                                                
                                                $morning_stmt = $conn->prepare($morning_query);
                                                $morning_stmt->bind_param("s", $today);
                                                $morning_stmt->execute();
                                                $morning_result = $morning_stmt->get_result();
                                                $morning_data = $morning_result->fetch_assoc();
                                                
                                                $morning_count = $morning_data['morning_count'] ? $morning_data['morning_count'] : 0;
                                                ?>
                                                <h2 class="mb-0"><?php echo $morning_count; ?></h2>
                                                <p class="mb-0 stat-label">Morning Attendance</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="usage-stat-card bg-gradient-success text-white rounded shadow-sm p-3 h-100">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon-bg mr-3">
                                                <i class="fas fa-user-clock fa-2x"></i>
                                            </div>
                                            <div>
                                                <?php
                                                // Get count of evening attendance
                                                $evening_query = "SELECT COUNT(*) as evening_count 
                                                                 FROM sv_company_labours 
                                                                 WHERE evening_attendance = 1 
                                                                 AND attendance_date = ? 
                                                                 AND is_deleted = 0";
                                                
                                                $evening_stmt = $conn->prepare($evening_query);
                                                $evening_stmt->bind_param("s", $today);
                                                $evening_stmt->execute();
                                                $evening_result = $evening_stmt->get_result();
                                                $evening_data = $evening_result->fetch_assoc();
                                                
                                                $evening_count = $evening_data['evening_count'] ? $evening_data['evening_count'] : 0;
                                                ?>
                                                <h2 class="mb-0"><?php echo $evening_count; ?></h2>
                                                <p class="mb-0 stat-label">Evening Attendance</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="usage-stat-card bg-gradient-warning text-white rounded shadow-sm p-3 h-100">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon-bg mr-3">
                                                <i class="fas fa-percentage fa-2x"></i>
                                            </div>
                                            <div>
                                                <?php
                                                // Calculate attendance rate
                                                $attendance_rate = 0;
                                                if ($total_workers > 0) {
                                                    $attendance_rate = round(($active_workers / $total_workers) * 100);
                                                }
                                                ?>
                                                <h2 class="mb-0"><?php echo $attendance_rate; ?>%</h2>
                                                <p class="mb-0 stat-label">Attendance Rate</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Workers Table -->
                        <div class="workers-table-section bg-white rounded shadow-sm p-3 mt-4">
                            <div class="section-header d-flex justify-content-between align-items-center mb-3">
                                <h6 class="font-weight-bold mb-0">
                                    <i class="fas fa-list text-primary mr-2"></i>Workers Present Today
                                    <span class="badge badge-primary ml-2"><?php echo $workers_result->num_rows; ?> Workers</span>
                                </h6>
                                <div class="d-flex">
                                    <div class="form-group mb-0 mr-2">
                                        <select class="form-control form-control-sm" id="workerFilterSelect">
                                            <option value="all">All Workers</option>
                                            <option value="morning">Morning Only</option>
                                            <option value="evening">Evening Only</option>
                                            <option value="both">Both Shifts</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshWorkersBtn">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="workersTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><i class="fas fa-id-badge mr-1"></i> Worker ID</th>
                                            <th><i class="fas fa-user mr-1"></i> Name</th>
                                            <th><i class="fas fa-briefcase mr-1"></i> Role</th>
                                            <th><i class="fas fa-sun mr-1"></i> Morning</th>
                                            <th><i class="fas fa-moon mr-1"></i> Evening</th>
                                            <th><i class="fas fa-clock mr-1"></i> Check-in Time</th>
                                            <th><i class="fas fa-rupee-sign mr-1"></i> Daily Wage</th>
                                            <th><i class="fas fa-money-bill-wave mr-1"></i> Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Get workers present today with wage information
                                        $workers_query = "SELECT 
                                                         l.company_labour_id,
                                                         l.event_id,
                                                         l.labour_name,
                                                         l.contact_number,
                                                         l.sequence_number,
                                                         l.morning_attendance,
                                                         l.evening_attendance,
                                                         l.attendance_date,
                                                         l.created_at,
                                                         e.title as event_title,
                                                         w.daily_wage,
                                                         w.grand_total
                                                      FROM sv_company_labours l
                                                      LEFT JOIN sv_calendar_events e ON l.event_id = e.event_id
                                                      LEFT JOIN sv_company_wages w ON l.company_labour_id = w.company_labour_id
                                                      WHERE (l.morning_attendance = 1 OR l.evening_attendance = 1) 
                                                      AND l.attendance_date = ? 
                                                      AND l.is_deleted = 0
                                                      ORDER BY l.labour_name ASC";
                                        
                                        $workers_stmt = $conn->prepare($workers_query);
                                        $workers_stmt->bind_param("s", $today);
                                        $workers_stmt->execute();
                                        $workers_result = $workers_stmt->get_result();
                                        
                                        if ($workers_result->num_rows > 0) {
                                            while ($worker = $workers_result->fetch_assoc()) {
                                                $role = !empty($worker['event_title']) ? $worker['event_title'] : 'General Worker';
                                                
                                                echo "<tr class='worker-row' 
                                                        data-morning='" . $worker['morning_attendance'] . "' 
                                                        data-evening='" . $worker['evening_attendance'] . "'>";
                                                echo "<td>" . htmlspecialchars($worker['company_labour_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($worker['labour_name']) . 
                                                     "<br><small class='text-muted'>" . htmlspecialchars($worker['contact_number']) . "</small></td>";
                                                echo "<td>" . htmlspecialchars($role) . "</td>";
                                                echo "<td>" . ($worker['morning_attendance'] ? '<span class="badge badge-success"><i class="fas fa-check"></i> Present</span>' : '<span class="badge badge-secondary">Absent</span>') . "</td>";
                                                echo "<td>" . ($worker['evening_attendance'] ? '<span class="badge badge-success"><i class="fas fa-check"></i> Present</span>' : '<span class="badge badge-secondary">Absent</span>') . "</td>";
                                                // Use created_at as check-in time if available, otherwise use attendance_date
                                                $check_in_time = isset($worker['created_at']) ? $worker['created_at'] : $worker['attendance_date'];
                                                echo "<td>" . date('h:i A', strtotime($check_in_time)) . "</td>";
                                                // Add daily wage and grand total columns
                                                $daily_wage = isset($worker['daily_wage']) ? $worker['daily_wage'] : 0;
                                                $grand_total = isset($worker['grand_total']) ? $worker['grand_total'] : 0;
                                                echo "<td>₹" . number_format($daily_wage, 2) . "</td>";
                                                echo "<td>₹" . number_format($grand_total, 2) . "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='8' class='text-center py-3 text-muted'><i class='fas fa-exclamation-circle mr-2'></i>No workers present today</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Attendance Trend Section -->
                        <div class="attendance-trend-section bg-light rounded shadow-sm p-3 mt-4">
                            <div class="section-header mb-3">
                                <h6 class="font-weight-bold mb-0">
                                    <i class="fas fa-chart-line text-primary mr-2"></i>Weekly Attendance Trend
                                </h6>
                            </div>
                            
                            <div class="attendance-chart-container">
                                <canvas id="attendanceChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                    <a href="worker_attendance.php" class="btn btn-primary">
                        <i class="fas fa-clipboard-list mr-1"></i>Full Attendance Report
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Calendar Updates Modal -->
    <div class="modal fade" id="calendarUsageModal" tabindex="-1" role="dialog" aria-labelledby="calendarUsageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title" id="calendarUsageModalLabel">
                        <i class="fas fa-calendar-alt mr-2"></i>Calendar Updates - <?php echo date('F Y'); ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body py-4">
                    <div class="calendar-usage-container">
                        <!-- Summary Cards -->
                        <div class="calendar-usage-summary mb-4">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="usage-stat-card bg-gradient-info text-white rounded shadow-sm p-3 h-100">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon-bg mr-3">
                                                <i class="fas fa-calendar-check fa-2x"></i>
                                            </div>
                                            <div>
                                                <h2 class="mb-0"><?php echo $days_with_events; ?>/<?php echo $days_in_month; ?></h2>
                                                <p class="mb-0 stat-label">Days with Updates</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="usage-stat-card bg-gradient-success text-white rounded shadow-sm p-3 h-100">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon-bg mr-3">
                                                <i class="fas fa-clipboard-list fa-2x"></i>
                                            </div>
                                            <div>
                                                <h2 class="mb-0"><?php echo $total_events; ?></h2>
                                                <p class="mb-0 stat-label">Total Updates</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="usage-stat-card bg-gradient-warning text-white rounded shadow-sm p-3 h-100">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon-bg mr-3">
                                                <i class="fas fa-hourglass-half fa-2x"></i>
                                            </div>
                                            <div>
                                                <h2 class="mb-0"><?php echo $days_remaining; ?></h2>
                                                <p class="mb-0 stat-label">Days Remaining</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                                                                    <!-- Progress Section -->
                            <div class="progress-section bg-light rounded p-3 mt-3 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 font-weight-bold">Monthly Planning Progress</h6>
                                    <div class="d-flex align-items-center">
                                        <?php if (!$has_today_updates): ?>
                                        <span class="badge badge-pill badge-warning mr-2" title="No updates for today">
                                            <i class="fas fa-exclamation-triangle"></i> Today
                                        </span>
                                        <?php endif; ?>
                                        <span class="badge badge-pill <?php echo $calendar_usage_percentage < 50 ? 'badge-warning' : 'badge-success'; ?>">
                                            <?php echo $calendar_usage_percentage; ?>% Complete
                                        </span>
                                    </div>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" 
                                         style="width: <?php echo $calendar_usage_percentage; ?>%" 
                                         aria-valuenow="<?php echo $calendar_usage_percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                
                                <div class="alert <?php echo $calendar_usage_percentage < 50 ? 'alert-warning' : 'alert-success'; ?> mt-3 mb-0 d-flex align-items-center">
                                    <div class="alert-icon mr-3">
                                        <?php if ($calendar_usage_percentage < 50): ?>
                                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if (!$has_today_updates): ?>
                                            <strong>Today's Update Missing:</strong> You haven't added any updates for today yet. 
                                            <?php if ($calendar_usage_percentage < 50): ?>
                                                You have only planned <?php echo $calendar_usage_percentage; ?>% of this month. Consider adding more updates to improve your planning.
                                            <?php else: ?>
                                                Your monthly planning is on track (<?php echo $calendar_usage_percentage; ?>%), but don't forget to add today's updates.
                                            <?php endif; ?>
                                        <?php elseif ($calendar_usage_percentage < 50): ?>
                                            <strong>Planning Alert:</strong> You have only planned <?php echo $calendar_usage_percentage; ?>% of this month. Consider adding more updates to improve your planning.
                                        <?php else: ?>
                                            <strong>Well Done!</strong> You have planned <?php echo $calendar_usage_percentage; ?>% of this month, which shows excellent preparation.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Updates Table -->
                        <div class="recent-updates-section bg-white rounded shadow-sm p-3 mt-4">
                                                            <div class="section-header d-flex justify-content-between align-items-center mb-3">
                                <h6 class="font-weight-bold mb-0">
                                    <i class="fas fa-history text-primary mr-2"></i>Recent Updates
                                </h6>
                                <div>
                                    <?php if (!$has_today_updates): ?>
                                    <a href="supervisor_calendar.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-warning mr-2">
                                        <i class="fas fa-plus-circle"></i> Add Today's Update
                                    </a>
                                    <?php endif; ?>
                                    <span class="badge badge-primary"><?php echo $recent_events_result->num_rows; ?> Records</span>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><i class="far fa-calendar-alt mr-1"></i> Date</th>
                                            <th><i class="fas fa-heading mr-1"></i> Title</th>
                                            <th><i class="far fa-clock mr-1"></i> Added On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Get recent updates
                                        $recent_events_query = "SELECT 
                                                                event_id, 
                                                                title, 
                                                                event_date, 
                                                                created_at
                                                             FROM sv_calendar_events 
                                                             WHERE created_by = ? 
                                                             ORDER BY created_at DESC
                                                             LIMIT 10";
                                        
                                        $recent_events_stmt = $conn->prepare($recent_events_query);
                                        $recent_events_stmt->bind_param("i", $user_id);
                                        $recent_events_stmt->execute();
                                        $recent_events_result = $recent_events_stmt->get_result();
                                        
                                        if ($recent_events_result->num_rows > 0) {
                                            while ($event = $recent_events_result->fetch_assoc()) {
                                                echo "<tr>
                                                        <td>" . date('M j, Y', strtotime($event['event_date'])) . "</td>
                                                        <td>" . htmlspecialchars($event['title']) . "</td>
                                                        <td>" . date('M j, Y g:i A', strtotime($event['created_at'])) . "</td>
                                                      </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='3' class='text-center py-3 text-muted'><i class='far fa-calendar-times mr-2'></i>No updates found</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Planning Tips Section -->
                        <div class="planning-tips-section bg-light rounded shadow-sm p-3 mt-4">
                            <div class="section-header mb-3">
                                <h6 class="font-weight-bold mb-0">
                                    <i class="fas fa-lightbulb text-warning mr-2"></i>Planning Tips
                                </h6>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="tip-card bg-white rounded p-3 mb-3 shadow-sm">
                                        <div class="d-flex">
                                            <div class="tip-icon mr-3 text-info">
                                                <i class="fas fa-hard-hat fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 font-weight-bold">Site Visits</h6>
                                                <p class="mb-0 text-muted small">Add important site visits and inspections updates to track your schedule</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="tip-card bg-white rounded p-3 mb-3 shadow-sm">
                                        <div class="d-flex">
                                            <div class="tip-icon mr-3 text-success">
                                                <i class="fas fa-truck fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 font-weight-bold">Deliveries</h6>
                                                <p class="mb-0 text-muted small">Create updates for material deliveries in advance for proper coordination</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="tip-card bg-white rounded p-3 mb-3 shadow-sm">
                                        <div class="d-flex">
                                            <div class="tip-icon mr-3 text-primary">
                                                <i class="fas fa-users fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 font-weight-bold">Meetings</h6>
                                                <p class="mb-0 text-muted small">Add regular team meeting updates to improve communication</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="tip-card bg-white rounded p-3 mb-3 shadow-sm">
                                        <div class="d-flex">
                                            <div class="tip-icon mr-3 text-danger">
                                                <i class="fas fa-file-alt fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 font-weight-bold">Deadlines</h6>
                                                <p class="mb-0 text-muted small">Set reminder updates for report submissions and deadlines</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="window.location.href='supervisor_calendar.php'">
                        <i class="fas fa-calendar-alt mr-1"></i>Go to Calendar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add custom styles for the calendar modal -->
    <style>
        /* Today's Update Warning */
        .today-update-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-radius: 4px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            animation: pulse-warning 2s infinite;
        }
        
        .today-update-warning i {
            margin-right: 8px;
            font-size: 1.1rem;
            color: #e0a800;
        }
        
        @keyframes pulse-warning {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(255, 193, 7, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
            }
        }
        
        /* Calendar Updates Modal Styles */
        #calendarUsageModal .modal-content {
            border: none;
            border-radius: 8px;
            overflow: hidden;
        }
        
        #calendarUsageModal .modal-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-bottom: none;
            padding: 15px 20px;
        }
        
        #calendarUsageModal .modal-title {
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        #calendarUsageModal .modal-body {
            background-color: #f8f9fa;
        }
        
        #calendarUsageModal .usage-stat-card {
            transition: all 0.3s ease;
            border-radius: 8px;
            height: 100%;
        }
        
        #calendarUsageModal .usage-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        #calendarUsageModal .bg-gradient-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        #calendarUsageModal .bg-gradient-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        #calendarUsageModal .bg-gradient-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        #calendarUsageModal .bg-gradient-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        #calendarUsageModal h2 {
            font-size: 2rem;
            font-weight: 700;
        }
        
        #calendarUsageModal .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        #calendarUsageModal .stat-icon-bg {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #calendarUsageModal .section-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        #calendarUsageModal .alert-icon {
            opacity: 0.8;
        }
        
        #calendarUsageModal .table th {
            font-weight: 600;
            font-size: 0.9rem;
            border-top: none;
        }
        
        #calendarUsageModal .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        #calendarUsageModal .tip-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #calendarUsageModal .tip-card {
            transition: all 0.3s ease;
        }
        
        #calendarUsageModal .tip-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        #calendarUsageModal .modal-footer {
            border-top: none;
        }
        
        #calendarUsageModal .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 8px 16px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            #calendarUsageModal h2 {
                font-size: 1.5rem;
            }
            
            #calendarUsageModal .stat-icon-bg {
                width: 40px;
                height: 40px;
            }
            
            #calendarUsageModal .stat-icon-bg i {
                font-size: 1.2rem;
            }
        }
        
        /* Active Workers Modal Styles */
        #activeWorkersModal .modal-content {
            border: none;
            border-radius: 8px;
            overflow: hidden;
        }
        
        #activeWorkersModal .modal-header {
            background: linear-gradient(135deg, #4e73df, #224abe);
            border-bottom: none;
            padding: 15px 20px;
        }
        
        #activeWorkersModal .modal-title {
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        #activeWorkersModal .modal-body {
            background-color: #f8f9fa;
        }
        
        #activeWorkersModal .usage-stat-card {
            transition: all 0.3s ease;
            border-radius: 8px;
            height: 100%;
        }
        
        #activeWorkersModal .usage-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        #activeWorkersModal .bg-gradient-info {
            background: linear-gradient(135deg, #36b9cc, #1a8a98);
        }
        
        #activeWorkersModal .bg-gradient-success {
            background: linear-gradient(135deg, #1cc88a, #169a6f);
        }
        
        #activeWorkersModal .bg-gradient-warning {
            background: linear-gradient(135deg, #f6c23e, #dda20a);
        }
        
        #activeWorkersModal .bg-gradient-primary {
            background: linear-gradient(135deg, #4e73df, #224abe);
        }
        
        #activeWorkersModal h2 {
            font-size: 2rem;
            font-weight: 700;
        }
        
        #activeWorkersModal .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        #activeWorkersModal .stat-icon-bg {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #activeWorkersModal .section-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        #activeWorkersModal .table th {
            font-weight: 600;
            font-size: 0.9rem;
            border-top: none;
        }
        
        #activeWorkersModal .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        #activeWorkersModal .badge {
            padding: 0.4em 0.6em;
            font-weight: 500;
        }
        
        #activeWorkersModal .modal-footer {
            border-top: none;
        }
        
        #activeWorkersModal .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 8px 16px;
        }
        
        #refreshWorkersBtn.spin i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .attendance-chart-container {
            position: relative;
            height: 250px;
        }
        
        @media (max-width: 767px) {
            #activeWorkersModal h2 {
                font-size: 1.5rem;
            }
            
            #activeWorkersModal .stat-icon-bg {
                width: 40px;
                height: 40px;
            }
            
            #activeWorkersModal .stat-icon-bg i {
                font-size: 1.2rem;
            }
            
            .attendance-chart-container {
                height: 200px;
            }
        }
    </style>
</body>
</html> 