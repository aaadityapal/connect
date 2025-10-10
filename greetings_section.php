<?php
// Start session to access session variables
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in - redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get username from session (this would typically come from your authentication system)
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest User';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// If we still have Guest User but user is logged in, fetch from database
if ($username === 'Guest User' && $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $username = $user['username'];
            // Update session
            $_SESSION['username'] = $username;
        }
    } catch (Exception $e) {
        error_log('Error fetching username: ' . $e->getMessage());
    }
}

// Get user initials for avatar
$user_initials = '';
if ($username !== 'Guest User') {
    $names = explode(' ', $username);
    $user_initials = strtoupper(substr($names[0], 0, 1));
    if (count($names) > 1) {
        $user_initials .= strtoupper(substr($names[count($names)-1], 0, 1));
    }
} else {
    $user_initials = 'GU';
}

// Initialize shift information
$shift_info = null;
$remaining_time = null;
$shift_end_time = null;
$is_weekly_off = false;
$shift_end_timestamp = null;

// Fetch user shift information if user is logged in
if ($user_id) {
    try {
        $currentDate = date('Y-m-d');
        $currentDay = date('l');
        
        $query = "SELECT s.shift_name, s.start_time, s.end_time, us.weekly_offs
                  FROM user_shifts us 
                  JOIN shifts s ON us.shift_id = s.id 
                  WHERE us.user_id = ?
                  AND us.effective_from <= ?
                  AND (us.effective_to IS NULL OR us.effective_to >= ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $currentDate, $currentDate]);
        
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shift) {
            $shift_info = $shift;
            
            // Check if today is a weekly off
            // Handle potential data issues - weekly_offs might be empty, a day name, or corrupted data
            $weekly_offs = $shift['weekly_offs'];
            
            // Check if today is a weekly off (handle different data formats)
            if (!empty($weekly_offs)) {
                // If weekly_offs contains the current day name
                if (strpos($weekly_offs, $currentDay) !== false) {
                    $is_weekly_off = true;
                }
                // If weekly_offs is just a number (corrupted data), we'll treat it as not a weekly off
            }
            
            // If not a weekly off, calculate remaining time
            if (!$is_weekly_off) {
                // Calculate remaining time
                $endTime = strtotime($currentDate . ' ' . $shift['end_time']);
                $currentTimestamp = strtotime('now');
                $remaining_time = $endTime - $currentTimestamp;
                $shift_end_timestamp = $endTime; // Store the end timestamp for JavaScript
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching shift data: ' . $e->getMessage());
    }
}

// Check if user has an active punch-in (punched in but not punched out)
$is_currently_punched_in = false;
if ($user_id) {
    try {
        $currentDate = date('Y-m-d');
        $query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NULL";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $currentDate]);
        $active_punch = $stmt->fetch();
        
        if ($active_punch) {
            $is_currently_punched_in = true;
        }
        // For debugging - you can remove this later
        error_log("User ID: " . $user_id . " | Date: " . $currentDate . " | Punch Status: " . ($is_currently_punched_in ? "Punched In" : "Punched Out"));
    } catch (Exception $e) {
        error_log('Error checking punch status: ' . $e->getMessage());
    }
}

// Check if user has completed their attendance cycle for today (punched in and out)
$has_completed_attendance_cycle = false;
if ($user_id) {
    try {
        $currentDate = date('Y-m-d');
        $query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NOT NULL";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $currentDate]);
        $completed_cycle = $stmt->fetch();
        
        if ($completed_cycle) {
            $has_completed_attendance_cycle = true;
        }
        // For debugging - you can remove this later
        error_log("User ID: " . $user_id . " | Date: " . $currentDate . " | Attendance Cycle Completed: " . ($has_completed_attendance_cycle ? "Yes" : "No"));
    } catch (Exception $e) {
        error_log('Error checking attendance cycle status: ' . $e->getMessage());
    }
}

// Check if user has an active site-in (site in but not site out)
$is_currently_site_in = false;
if ($user_id) {
    try {
        $currentDate = date('Y-m-d');
        $query = "SELECT action FROM site_in_out_logs WHERE user_id = ? AND DATE(timestamp) = ? ORDER BY timestamp DESC LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $currentDate]);
        $last_action = $stmt->fetch();
        
        if ($last_action && $last_action['action'] === 'site_in') {
            $is_currently_site_in = true;
        }
    } catch (Exception $e) {
        error_log('Error checking site status: ' . $e->getMessage());
    }
}

// Fetch geofence locations
$geofence_locations = [];
try {
    $query = "SELECT id, name, address, latitude, longitude, radius FROM geofence_locations WHERE is_active = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $geofence_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching geofence locations: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Greetings Section</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .greetings-section {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            background: #f9fafb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-align: left;
            position: relative;
        }
        
        /* Morning theme */
        .greetings-section.morning {
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        
        .greetings-section.morning .greeting-text {
            color: #1e40af;
        }
        
        .greetings-section.morning .username {
            color: #3b82f6;
        }
        
        .greetings-section.morning .greeting-icon {
            color: #3b82f6;
        }
        
        /* Afternoon theme */
        .greetings-section.afternoon {
            background: #fffbeb;
            border-color: #fde68a;
        }
        
        .greetings-section.afternoon .greeting-text {
            color: #b45309;
        }
        
        .greetings-section.afternoon .username {
            color: #d97706;
        }
        
        .greetings-section.afternoon .greeting-icon {
            color: #d97706;
        }
        
        /* Evening theme */
        .greetings-section.evening {
            background: #f3e8ff;
            border-color: #d8b4fe;
        }
        
        .greetings-section.evening .greeting-text {
            color: #7e22ce;
        }
        
        .greetings-section.evening .username {
            color: #a855f7;
        }
        
        .greetings-section.evening .greeting-icon {
            color: #a855f7;
        }
        
        .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .greeting-container {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .greeting-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .greeting-text {
            font-size: 1.2rem;
            font-weight: 500;
        }
        
        .username {
            font-size: 1.2rem;
            font-weight: 600;
            margin-left: 6px;
        }
        
        .right-icons {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-icon, .profile-icon {
            color: #4b5563;
            font-size: 1.2rem;
            position: relative;
            cursor: pointer;
        }
        
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 0.6rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .profile-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4b5563;
            font-weight: bold;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .punch-button {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .punch-button:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .site-button {
            background: #8b5cf6;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .site-button:hover {
            background: #7c3aed;
            transform: translateY(-1px);
        }
        
        .shift-info {
            font-size: 0.9rem;
            font-weight: 500;
            color: #4b5563;
            background-color: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
            display: inline-block;
        }
        
        .shift-info.warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .shift-info.danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .date-time-container {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #4b5563;
        }
        
        .date-icon, .time-icon {
            color: #6b7280;
            margin-right: 6px;
            font-size: 0.8rem;
        }
        
        .date-display {
            margin-right: 15px;
            font-weight: 500;
        }
        
        .time-display {
            font-family: 'Courier New', monospace;
            font-weight: 500;
            background-color: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        /* Notification dropdown styles */
        .notifications-dropdown {
            position: absolute;
            top: 65px;
            right: 15px;
            width: 280px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
            max-height: 300px; /* Limit the height */
            overflow-y: auto; /* Enable scrolling */
        }
        
        .notifications-header {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #1f2937;
            font-size: 1rem;
            position: sticky; /* Keep header visible when scrolling */
            top: 0;
            background: white;
            z-index: 10;
        }
        
        .notification-item {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background-color: #f9fafb;
        }
        
        .notification-title {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        
        .notification-message {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .notification-time {
            font-size: 0.7rem;
            color: #9ca3af;
        }
        
        .notification-item.unread {
            background-color: #f0f9ff;
        }
        
        .mark-all-read {
            padding: 8px 12px;
            text-align: center;
            color: #3b82f6;
            font-size: 0.8rem;
            cursor: pointer;
            position: sticky; /* Keep mark all read button visible when scrolling */
            bottom: 0;
            background: white;
            z-index: 10;
            border-top: 1px solid #e5e7eb;
        }
        
        .mark-all-read:hover {
            background-color: #f9fafb;
        }
        
        /* Profile dropdown styles */
        .profile-dropdown {
            position: absolute;
            top: 65px;
            right: 65px;
            width: 180px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
        }
        
        .profile-dropdown-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #1f2937;
        }
        
        .profile-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .profile-dropdown-item:hover {
            background-color: #f9fafb;
        }
        
        .profile-dropdown-icon {
            width: 20px;
            text-align: center;
            color: #6b7280;
        }
        
        /* Punch Attendance Modal Styles */
        .punch-attendance-modal-unique-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .punch-attendance-modal-unique-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .punch-attendance-modal-unique-content {
            background-color: #fff;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .punch-attendance-modal-unique-header {
            padding: 15px 20px;
            background-color: #3498db;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .punch-attendance-modal-unique-header h4 {
            margin: 0;
            font-size: 1.2rem;
        }

        .punch-attendance-close-modal-unique-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .punch-attendance-modal-unique-body {
            padding: 20px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .punch-attendance-camera-container-unique {
            position: relative;
            width: 100%;
            height: 300px;
            background-color: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .punch-attendance-camera-container-unique video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .punch-attendance-camera-capture-btn-unique {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .punch-attendance-camera-capture-btn-unique:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .punch-attendance-camera-capture-btn-unique i {
            color: white;
            font-size: 24px;
        }

        .punch-attendance-switch-camera-btn-unique {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .punch-attendance-switch-camera-btn-unique:hover {
            background-color: rgba(0, 0, 0, 0.7);
        }

        .punch-attendance-captured-image-container-unique {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .punch-attendance-captured-image-container-unique img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .punch-attendance-location-container-unique {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .punch-attendance-location-container-unique h5 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }

        .punch-attendance-location-status-unique {
            text-align: center;
            padding: 10px;
            color: #666;
        }

        .punch-attendance-location-details-unique p {
            margin: 8px 0;
            font-size: 0.9rem;
        }

        .punch-attendance-location-details-unique strong {
            color: #333;
        }

        /* Geofence Status Tag Styles */
        .geofence-status-tag-unique {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .geofence-status-tag-unique.inside {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .geofence-status-tag-unique.outside {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .punch-attendance-modal-unique-footer {
            padding: 15px 20px;
            background: #f9f9f9;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
        }

        .punch-attendance-retake-btn-unique,
        .punch-attendance-submit-btn-unique {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .punch-attendance-retake-btn-unique {
            background-color: #6c757d;
            color: white;
        }

        .punch-attendance-retake-btn-unique:hover {
            background-color: #5a6268;
        }

        .punch-attendance-submit-btn-unique {
            background-color: #28a745;
            color: white;
        }

        .punch-attendance-submit-btn-unique:hover {
            background-color: #218838;
        }

        /* Geofence Reason Textarea Styles */
        .geofence-reason-textarea-unique {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
            margin-top: 5px;
        }

        .geofence-reason-textarea-unique:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .geofence-reason-error-unique {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }
        
        /* Word Counter Styles */
        .word-counter {
            text-align: right;
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 576px) {
            .punch-attendance-modal-unique-content {
                width: 95%;
                max-width: 95%;
            }
            
            .punch-attendance-modal-unique-header h4 {
                font-size: 1.1rem;
            }
            
            .punch-attendance-camera-container-unique {
                height: 250px;
            }
            
            .punch-attendance-captured-image-container-unique {
                height: 250px;
            }
            
            .punch-attendance-modal-unique-body {
                padding: 15px;
            }
            
            .geofence-status-tag-unique {
                font-size: 0.7rem;
                padding: 2px 6px;
            }
        }
        
        /* Responsive styles for small screens */
        @media (max-width: 768px) {
            .greetings-section {
                padding: 12px;
                margin: 8px 0;
            }
            
            .top-row {
                margin-bottom: 10px;
            }
            
            .greeting-icon {
                font-size: 1.3rem;
                margin-right: 8px;
            }
            
            .greeting-text {
                font-size: 1.1rem;
            }
            
            .username {
                font-size: 1.1rem;
                margin-left: 5px;
            }
            
            .right-icons {
                gap: 12px;
            }
            
            .notification-icon, .profile-icon {
                font-size: 1.1rem;
            }
            
            .profile-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .action-buttons {
                gap: 8px;
            }
            
            .punch-button, .site-button {
                padding: 5px 12px;
                font-size: 0.8rem;
            }
            
            .shift-info {
                font-size: 0.8rem;
                padding: 3px 6px;
            }
            
            .date-time-container {
                font-size: 0.8rem;
            }
            
            .date-icon, .time-icon {
                font-size: 0.7rem;
            }
            
            .date-display {
                margin-right: 10px;
            }
            
            .time-display {
                font-size: 0.75rem;
                padding: 1px 5px;
            }
            
            .notifications-dropdown {
                top: 60px;
                right: 12px;
                width: 250px;
            }
            
            .profile-dropdown {
                top: 60px;
                right: 55px;
                width: 160px;
            }
        }
        
        /* Extra small screens (iPhone SE, etc.) */
        @media (max-width: 480px) {
            .greetings-section {
                padding: 10px;
                margin: 6px 0;
            }
            
            .top-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 8px;
            }
            
            .greeting-container {
                width: 100%;
            }
            
            .right-icons {
                width: 100%;
                justify-content: flex-end;
                padding-top: 5px;
                border-top: 1px solid #e5e7eb;
            }
            
            .greeting-icon {
                font-size: 1.2rem;
                margin-right: 6px;
            }
            
            .greeting-text {
                font-size: 1rem;
            }
            
            .username {
                font-size: 1rem;
                margin-left: 4px;
            }
            
            .action-buttons {
                gap: 6px;
            }
            
            .punch-button, .site-button {
                padding: 4px 10px;
                font-size: 0.75rem;
            }
            
            .shift-info {
                font-size: 0.75rem;
                padding: 2px 5px;
                margin-top: 6px;
            }
            
            .date-time-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 3px;
            }
            
            .date-display {
                margin-right: 0;
            }
            
            .notifications-dropdown {
                top: 55px;
                right: 10px;
                width: 220px;
            }
            
            .profile-dropdown {
                top: 55px;
                right: 50px;
                width: 150px;
            }
            
            .notification-title {
                font-size: 0.85rem;
            }
            
            .notification-message {
                font-size: 0.75rem;
            }
        }
        
        /* Extra extra small screens */
        @media (max-width: 320px) {
            .greetings-section {
                padding: 8px;
                margin: 5px 0;
            }
            
            .greeting-icon {
                font-size: 1.1rem;
                margin-right: 5px;
            }
            
            .greeting-text {
                font-size: 0.9rem;
            }
            
            .username {
                font-size: 0.9rem;
                margin-left: 3px;
            }
            
            .notification-icon, .profile-icon {
                font-size: 1rem;
            }
            
            .profile-avatar {
                width: 28px;
                height: 28px;
                font-size: 0.7rem;
            }
            
            .action-buttons {
                gap: 5px;
            }
            
            .punch-button, .site-button {
                padding: 3px 8px;
                font-size: 0.7rem;
            }
            
            .shift-info {
                font-size: 0.7rem;
                padding: 2px 4px;
                margin-top: 5px;
            }
            
            .date-time-container {
                font-size: 0.75rem;
            }
            
            .date-icon, .time-icon {
                font-size: 0.65rem;
            }
            
            .time-display {
                font-size: 0.7rem;
                padding: 1px 4px;
            }
            
            .notifications-dropdown {
                top: 50px;
                right: 8px;
                width: 200px;
            }
            
            .profile-dropdown {
                top: 50px;
                right: 45px;
                width: 140px;
            }
            
            .notifications-header {
                padding: 10px;
                font-size: 0.9rem;
            }
            
            .notification-item {
                padding: 8px 10px;
            }
            
            .profile-dropdown-item {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <section class="greetings-section" id="greetingsSection">
        <div class="top-row">
            <div class="greeting-container">
                <i class="greeting-icon fas fa-hand-wave"></i>
                <span class="greeting-text" id="greetingMessage">Good Morning</span>
                <span class="username" id="usernameDisplay"><?php echo htmlspecialchars($username); ?></span>
            </div>
            <div class="right-icons">
                <div class="notification-icon" id="notificationIcon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="profile-avatar" id="profileAvatar"><?php echo htmlspecialchars($user_initials); ?></div>
                <div class="action-buttons">
                    <button class="site-button" id="siteButton">Site In</button>
                    <button class="punch-button" id="punchButton">Punch In</button>
                </div>
            </div>
        </div>
        <div class="date-time-container">
            <i class="date-icon fas fa-calendar-alt"></i>
            <span class="date-display" id="currentDate">Monday, January 1, 2023</span>
            <i class="time-icon fas fa-clock"></i>
            <span class="time-display" id="currentTime">00:00:00</span>
        </div>
        <?php if ($shift_info): ?>
            <?php if ($is_weekly_off): ?>
                <div class="shift-info" id="shiftInfo">
                    <?php echo htmlspecialchars($shift_info['shift_name']); ?> shift (Weekly Off Today)
                </div>
            <?php elseif ($remaining_time !== null): ?>
                <div class="shift-info <?php echo ($remaining_time < 3600) ? 'danger' : (($remaining_time < 7200) ? 'warning' : ''); ?>" id="shiftInfo">
                    <?php echo htmlspecialchars($shift_info['shift_name']); ?> shift ends in: <span id="shiftTimeDisplay"><?php echo gmdate('H:i:s', max(0, $remaining_time)); ?></span>
                </div>
            <?php else: ?>
                <div class="shift-info" id="shiftInfo">
                    <?php echo htmlspecialchars($shift_info['shift_name']); ?> shift (Active)
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="shift-info" id="shiftInfo">
                No shift assigned
            </div>
        <?php endif; ?>
        
        <!-- Notifications Dropdown -->
        <div class="notifications-dropdown" id="notificationsDropdown">
            <div class="notifications-header">
                Notifications
            </div>
            <!-- Notifications will be populated dynamically -->
            <div class="mark-all-read" id="markAllRead">
                Mark all as read
            </div>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="profile-dropdown" id="profileDropdown">
            <div class="profile-dropdown-item" id="profileOption">
                <div class="profile-dropdown-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div>Profile</div>
            </div>
            <div class="profile-dropdown-item" id="logoutOption">
                <div class="profile-dropdown-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div>Logout</div>
            </div>
        </div>
    </section>
    
    <!-- Punch Attendance Modal -->
    <div id="punchAttendanceModalUnique" class="punch-attendance-modal-unique-overlay">
        <div class="punch-attendance-modal-unique-content">
            <div class="punch-attendance-modal-unique-header">
                <h4 id="punchAttendanceModalTitleUnique">Punch Attendance</h4>
                <button id="closePunchAttendanceModalUnique" class="punch-attendance-close-modal-unique-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="punch-attendance-modal-unique-body">
                <div class="punch-attendance-camera-container-unique">
                    <video id="punchAttendanceCameraVideoUnique" autoplay playsinline></video>
                    <canvas id="punchAttendanceCameraCanvasUnique" style="display: none;"></canvas>
                    <div id="punchAttendanceCameraCaptureBtnUnique" class="punch-attendance-camera-capture-btn-unique">
                        <i class="fas fa-camera"></i>
                    </div>
                    <button id="punchAttendanceSwitchCameraBtnUnique" class="punch-attendance-switch-camera-btn-unique">
                        <i class="fas fa-sync"></i> Switch Camera
                    </button>
                </div>
                <div class="punch-attendance-captured-image-container-unique" style="display: none;">
                    <img id="punchAttendanceCapturedImageUnique" src="" alt="Captured image">
                </div>
                
                <div class="punch-attendance-location-container-unique">
                    <h5><i class="fas fa-map-marker-alt"></i> Location Information</h5>
                    <div id="punchAttendanceLocationStatusUnique" class="punch-attendance-location-status-unique">
                        Getting your location...
                    </div>
                    <div id="punchAttendanceLocationDetailsUnique" class="punch-attendance-location-details-unique" style="display: none;">
                        <p><strong>Latitude:</strong> <span id="punchAttendanceLatitudeUnique"></span></p>
                        <p><strong>Longitude:</strong> <span id="punchAttendanceLongitudeUnique"></span></p>
                        <p><strong>Accuracy:</strong> <span id="punchAttendanceAccuracyUnique"></span> meters</p>
                        <p><strong>Address:</strong> <span id="punchAttendanceAddressUnique"></span></p>
                        
                        <!-- Geofence status tag -->
                        <div id="geofenceStatusContainerUnique" style="margin-top: 10px; display: none;">
                            <span id="geofenceStatusTagUnique" class="geofence-status-tag-unique"></span>
                        </div>
                        
                        <!-- Geofence reason textbox (hidden by default) -->
                        <div id="geofenceReasonContainerUnique" style="display: none; margin-top: 15px;">
                            <label for="geofenceReasonTextUnique"><strong>Reason for being outside work location:</strong></label>
                            <textarea id="geofenceReasonTextUnique" class="geofence-reason-textarea-unique" 
                                      placeholder="Please explain why you are outside the designated work area (minimum 10 words)..."></textarea>
                            <div class="word-counter" id="geofenceReasonWordCountUnique" style="text-align: right; font-size: 0.8rem; color: #666; margin-top: 5px;">
                                <span id="geofenceReasonCurrentCountUnique">0</span> words
                            </div>
                            <div id="geofenceReasonErrorUnique" class="geofence-reason-error-unique" style="display: none;">
                                Please enter at least 10 words.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="punch-attendance-modal-unique-footer">
                <button id="punchAttendanceRetakeBtnUnique" class="punch-attendance-retake-btn-unique" style="display: none;">
                    Retake Photo
                </button>
                <button id="punchAttendanceSubmitBtnUnique" class="punch-attendance-submit-btn-unique" style="display: none;">
                    Submit Punch
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Notification Modal -->
    <?php include 'modals/alert_notification_modal.php'; ?>
    
    <!-- Loader Modal -->
    <?php include 'modals/loader_modal.php'; ?>
    
    <!-- Missing Punch In Modal -->
    <?php include 'modals/missing_punch_modal.php'; ?>
    
    <!-- Missing Punch Out Modal -->
    <?php include 'modals/missing_punch_out_modal.php'; ?>
    <script>
        // Set username - in a real application, this would come from the session or database
        const username = "<?php echo addslashes($username); ?>";
        const userInitials = "<?php echo addslashes($user_initials); ?>";
        // Pass shift end timestamp to JavaScript
        const shiftEndTimestamp = <?php echo $shift_end_timestamp ? $shift_end_timestamp . '000' : 'null'; ?>; // Convert to milliseconds
        // Pass geofence locations to JavaScript
        const geofenceLocations = <?php echo json_encode($geofence_locations); ?>;
        // Pass punch status to JavaScript
        const isCurrentlyPunchedIn = <?php echo $is_currently_punched_in ? 'true' : 'false'; ?>;
        // Pass site status to JavaScript
        const isCurrentlySiteIn = <?php echo $is_currently_site_in ? 'true' : 'false'; ?>;
        // Pass attendance cycle completion status to JavaScript
        const hasCompletedAttendanceCycle = <?php echo $has_completed_attendance_cycle ? 'true' : 'false'; ?>;
        
        // Update profile avatar with user initials
        document.getElementById("profileAvatar").textContent = userInitials;
        
        // Punch button functionality
        const punchButton = document.getElementById("punchButton");
        let isPunchedIn = isCurrentlyPunchedIn; // Initialize with actual punch status from database
        let currentStream = null;
        let currentFacingMode = 'user'; // Front camera by default
        
        // Debug logging
        console.log("Initial punch status from PHP:", isCurrentlyPunchedIn);
        console.log("JavaScript isPunchedIn variable:", isPunchedIn);
        console.log("Attendance cycle completed:", hasCompletedAttendanceCycle);
        
        // Set initial button state based on punch status
        if (hasCompletedAttendanceCycle) {
            // If user has completed their attendance cycle, disable the punch button
            punchButton.textContent = "Attendance Completed";
            punchButton.style.background = "#6c757d";
            punchButton.disabled = true;
            punchButton.title = "You have already completed your attendance for today";
            console.log("Setting button to Attendance Completed (disabled)");
        } else if (isPunchedIn) {
            punchButton.textContent = "Punch Out";
            punchButton.style.background = "#ef4444";
            punchButton.disabled = false;
            console.log("Setting button to Punch Out");
        } else {
            punchButton.textContent = "Punch In";
            punchButton.style.background = "#10b981";
            punchButton.disabled = false;
            console.log("Setting button to Punch In");
        }
        
        // Function to open the punch attendance modal
        function openPunchAttendanceModal() {
            const modal = document.getElementById('punchAttendanceModalUnique');
            if (modal) {
                // Show the modal
                modal.classList.add('active');
                
                // Initialize modal functionality
                initializePunchAttendanceModal();
            } else {
                alert('Error opening attendance modal. Please try again.');
            }
        }
        
        // Function to open the punch out modal with work report
        function openPunchOutModal() {
            // For now, we'll use the same modal but with work report functionality
            // In a real implementation, this would open a separate modal
            const modal = document.getElementById('punchAttendanceModalUnique');
            if (modal) {
                // Show the modal
                modal.classList.add('active');
                
                // Initialize modal functionality
                initializePunchAttendanceModal();
                
                // Add work report section for punch out
                addWorkReportSection();
            } else {
                alert('Error opening attendance modal. Please try again.');
            }
        }
        
        // Function to add work report section for punch out
        function addWorkReportSection() {
            // Check if work report section already exists
            if (document.getElementById('workReportContainerUnique')) {
                return;
            }
            
            // Create work report container
            const workReportContainer = document.createElement('div');
            workReportContainer.id = 'workReportContainerUnique';
            workReportContainer.style.marginTop = '15px';
            
            // Create label
            const label = document.createElement('label');
            label.innerHTML = '<strong>Work Report (minimum 20 words):</strong>';
            label.setAttribute('for', 'workReportTextUnique');
            
            // Create textarea
            const textarea = document.createElement('textarea');
            textarea.id = 'workReportTextUnique';
            textarea.className = 'geofence-reason-textarea-unique';
            textarea.placeholder = 'Please provide details about your work today (minimum 20 words)...';
            textarea.style.minHeight = '100px';
            textarea.style.marginTop = '5px';
            
            // Create word counter
            const wordCounter = document.createElement('div');
            wordCounter.className = 'word-counter';
            wordCounter.id = 'workReportWordCountUnique';
            wordCounter.style.textAlign = 'right';
            wordCounter.style.fontSize = '0.8rem';
            wordCounter.style.color = '#666';
            wordCounter.style.marginTop = '5px';
            wordCounter.innerHTML = '<span id="workReportCurrentCountUnique">0</span> words';
            
            // Create error message
            const errorDiv = document.createElement('div');
            errorDiv.id = 'workReportErrorUnique';
            errorDiv.className = 'geofence-reason-error-unique';
            errorDiv.style.display = 'none';
            errorDiv.textContent = 'Please enter at least 20 words for the work report.';
            
            // Append elements
            workReportContainer.appendChild(label);
            workReportContainer.appendChild(textarea);
            workReportContainer.appendChild(wordCounter);
            workReportContainer.appendChild(errorDiv);
            
            // Add to modal body
            const modalBody = document.querySelector('.punch-attendance-modal-unique-body');
            if (modalBody) {
                modalBody.appendChild(workReportContainer);
            }
            
            // Add event listener for word counting
            textarea.addEventListener('input', function() {
                updateWordCount(this.value, 'workReportCurrentCountUnique');
                validateWorkReport();
            });
        }
        
        // Function to validate geofence reason (to be called before submitting)
        function validateGeofenceReason() {
            const reasonContainer = document.getElementById('geofenceReasonContainerUnique');
            if (reasonContainer && reasonContainer.style.display !== 'none') {
                const reasonText = document.getElementById('geofenceReasonTextUnique').value.trim();
                const words = reasonText.split(/\s+/).filter(word => word.length > 0);
                const wordCount = words.length;
                const errorElement = document.getElementById('geofenceReasonErrorUnique');
                
                // Update word count display
                updateWordCount(reasonText, 'geofenceReasonCurrentCountUnique');
                
                if (wordCount < 10) {
                    if (errorElement) {
                        errorElement.style.display = 'block';
                    }
                    return false;
                } else {
                    if (errorElement) {
                        errorElement.style.display = 'none';
                    }
                    return true;
                }
            }
            return true; // No reason required if within geofence
        }
        
        // Function to validate work report
        function validateWorkReport() {
            const workReportContainer = document.getElementById('workReportContainerUnique');
            if (workReportContainer) {
                const workReportText = document.getElementById('workReportTextUnique').value.trim();
                const words = workReportText.split(/\s+/).filter(word => word.length > 0);
                const wordCount = words.length;
                const errorElement = document.getElementById('workReportErrorUnique');
                
                // Update word count display
                updateWordCount(workReportText, 'workReportCurrentCountUnique');
                
                if (wordCount < 20) {
                    if (errorElement) {
                        errorElement.style.display = 'block';
                    }
                    return false;
                } else {
                    if (errorElement) {
                        errorElement.style.display = 'none';
                    }
                    return true;
                }
            }
            return true; // No work report required for punch in
        }
        
        // Function to initialize the punch attendance modal
        function initializePunchAttendanceModal() {
            const modal = document.getElementById('punchAttendanceModalUnique');
            const closeBtn = document.getElementById('closePunchAttendanceModalUnique');
            const captureBtn = document.getElementById('punchAttendanceCameraCaptureBtnUnique');
            const switchCameraBtn = document.getElementById('punchAttendanceSwitchCameraBtnUnique');
            const retakeBtn = document.getElementById('punchAttendanceRetakeBtnUnique');
            const submitBtn = document.getElementById('punchAttendanceSubmitBtnUnique');
            const video = document.getElementById('punchAttendanceCameraVideoUnique');
            const canvas = document.getElementById('punchAttendanceCameraCanvasUnique');
            const capturedImage = document.getElementById('punchAttendanceCapturedImageUnique');
            const cameraContainer = document.querySelector('.punch-attendance-camera-container-unique');
            const imageContainer = document.querySelector('.punch-attendance-captured-image-container-unique');
            const locationStatus = document.getElementById('punchAttendanceLocationStatusUnique');
            const locationDetails = document.getElementById('punchAttendanceLocationDetailsUnique');
            
            // Update modal title based on action
            const modalTitle = document.getElementById('punchAttendanceModalTitleUnique');
            if (modalTitle) {
                modalTitle.textContent = isPunchedIn ? 'Punch Out' : 'Punch In';
            }
            
            // Close modal event
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    closePunchAttendanceModal();
                });
            }
            
            // Close modal when clicking outside
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closePunchAttendanceModal();
                    }
                });
            }
            
            // Start camera
            startCamera(currentFacingMode);
            
            // Capture photo event
            if (captureBtn) {
                captureBtn.addEventListener('click', function() {
                    capturePhoto();
                });
            }
            
            // Switch camera event
            if (switchCameraBtn) {
                switchCameraBtn.addEventListener('click', function() {
                    switchCamera();
                });
            }
            
            // Retake photo event
            if (retakeBtn) {
                retakeBtn.addEventListener('click', function() {
                    retakePhoto();
                });
            }
            
            // Submit punch event
            if (submitBtn) {
                submitBtn.addEventListener('click', function() {
                    submitPunch();
                });
            }
            
            // Add event listener for geofence reason word counting
            const geofenceReasonTextarea = document.getElementById('geofenceReasonTextUnique');
            if (geofenceReasonTextarea) {
                // Initialize word count
                updateWordCount(geofenceReasonTextarea.value, 'geofenceReasonCurrentCountUnique');
                
                geofenceReasonTextarea.addEventListener('input', function() {
                    updateWordCount(this.value, 'geofenceReasonCurrentCountUnique');
                    validateGeofenceReason();
                });
            }
            
            // Add event listener for work report word counting if it exists
            const workReportTextarea = document.getElementById('workReportTextUnique');
            if (workReportTextarea) {
                // Initialize word count
                updateWordCount(workReportTextarea.value, 'workReportCurrentCountUnique');
                
                workReportTextarea.addEventListener('input', function() {
                    updateWordCount(this.value, 'workReportCurrentCountUnique');
                    validateWorkReport();
                });
            }
            
            // Get user location
            getLocation();
        }
        
        // Function to update word count
        function updateWordCount(text, elementId) {
            const words = text.trim().split(/\s+/).filter(word => word.length > 0);
            const wordCount = words.length;
            const countElement = document.getElementById(elementId);
            if (countElement) {
                countElement.textContent = wordCount;
            }
        }
        
        // Function to start camera
        function startCamera(facingMode) {
            const video = document.getElementById('punchAttendanceCameraVideoUnique');
            
            // Stop any existing stream
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
            }
            
            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };
            
            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    currentStream = stream;
                    video.srcObject = stream;
                })
                .catch(function(err) {
                    console.error("Error accessing camera: ", err);
                    alert("Could not access the camera. Please check permissions.");
                });
        }
        
        // Function to capture photo
        function capturePhoto() {
            const video = document.getElementById('punchAttendanceCameraVideoUnique');
            const canvas = document.getElementById('punchAttendanceCameraCanvasUnique');
            const capturedImage = document.getElementById('punchAttendanceCapturedImageUnique');
            const cameraContainer = document.querySelector('.punch-attendance-camera-container-unique');
            const imageContainer = document.querySelector('.punch-attendance-captured-image-container-unique');
            const retakeBtn = document.getElementById('punchAttendanceRetakeBtnUnique');
            const submitBtn = document.getElementById('punchAttendanceSubmitBtnUnique');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const dataUrl = canvas.toDataURL('image/jpeg');
            capturedImage.src = dataUrl;
            
            // Show captured image and hide camera
            cameraContainer.style.display = 'none';
            imageContainer.style.display = 'block';
            
            // Show retake and submit buttons
            if (retakeBtn) retakeBtn.style.display = 'inline-block';
            if (submitBtn) submitBtn.style.display = 'inline-block';
        }
        
        // Function to switch camera
        function switchCamera() {
            currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
            startCamera(currentFacingMode);
        }
        
        // Function to retake photo
        function retakePhoto() {
            const cameraContainer = document.querySelector('.punch-attendance-camera-container-unique');
            const imageContainer = document.querySelector('.punch-attendance-captured-image-container-unique');
            const retakeBtn = document.getElementById('punchAttendanceRetakeBtnUnique');
            const submitBtn = document.getElementById('punchAttendanceSubmitBtnUnique');
            
            // Show camera and hide captured image
            cameraContainer.style.display = 'block';
            imageContainer.style.display = 'none';
            
            // Hide retake and submit buttons
            if (retakeBtn) retakeBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'none';
        }
        
        // Global variables to store geofence information
        let geofenceInfo = {
            isWithinGeofence: null,
            nearestGeofenceId: null,
            distanceFromGeofence: null
        };
        
        // Function to get user location
        function getLocation() {
            const locationStatus = document.getElementById('punchAttendanceLocationStatusUnique');
            const locationDetails = document.getElementById('punchAttendanceLocationDetailsUnique');
            const latitudeEl = document.getElementById('punchAttendanceLatitudeUnique');
            const longitudeEl = document.getElementById('punchAttendanceLongitudeUnique');
            const accuracyEl = document.getElementById('punchAttendanceAccuracyUnique');
            const addressEl = document.getElementById('punchAttendanceAddressUnique');
            
            if (navigator.geolocation) {
                locationStatus.textContent = "Getting your location...";
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        const accuracy = position.coords.accuracy;
                        
                        // Update location display
                        locationStatus.style.display = 'none';
                        locationDetails.style.display = 'block';
                        
                        if (latitudeEl) latitudeEl.textContent = latitude.toFixed(6);
                        if (longitudeEl) longitudeEl.textContent = longitude.toFixed(6);
                        if (accuracyEl) accuracyEl.textContent = accuracy.toFixed(2);
                        
                        // Get address using reverse geocoding
                        if (addressEl) {
                            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data && data.display_name) {
                                        // Truncate address if too long
                                        let address = data.display_name;
                                        if (address.length > 100) {
                                            address = address.substring(0, 97) + '...';
                                        }
                                        addressEl.textContent = address;
                                    } else {
                                        addressEl.textContent = "Address not found";
                                    }
                                })
                                .catch(error => {
                                    console.error('Error fetching address:', error);
                                    addressEl.textContent = "Address lookup failed";
                                });
                        }
                        
                        // Check if user is within geofence and get geofence information
                        geofenceInfo = checkGeofence(latitude, longitude);
                    },
                    function(error) {
                        locationStatus.textContent = "Unable to get location: " + error.message;
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                locationStatus.textContent = "Geolocation is not supported by this browser.";
            }
        }
        
        // Function to check if user is within geofence and find nearest geofence
        function checkGeofence(userLat, userLng) {
            // If no geofence locations, skip check
            if (!geofenceLocations || geofenceLocations.length === 0) {
                return {
                    isWithinGeofence: false,
                    nearestGeofenceId: null,
                    distanceFromGeofence: null
                };
            }
            
            let isWithinGeofence = false;
            let geofenceName = '';
            let nearestGeofenceId = null;
            let minDistance = Infinity;
            
            // Check each geofence location to find the nearest one
            for (let i = 0; i < geofenceLocations.length; i++) {
                const geofence = geofenceLocations[i];
                const distance = calculateDistance(
                    userLat, 
                    userLng, 
                    parseFloat(geofence.latitude), 
                    parseFloat(geofence.longitude)
                );
                
                // Update nearest geofence if this one is closer
                if (distance < minDistance) {
                    minDistance = distance;
                    nearestGeofenceId = geofence.id;
                }
                
                // If user is within the radius, they are in the geofence
                if (distance <= parseFloat(geofence.radius)) {
                    isWithinGeofence = true;
                    geofenceName = geofence.name;
                }
            }
            
            // Show geofence status tag
            const statusContainer = document.getElementById('geofenceStatusContainerUnique');
            const statusTag = document.getElementById('geofenceStatusTagUnique');
            if (statusContainer && statusTag) {
                statusContainer.style.display = 'block';
                if (isWithinGeofence) {
                    statusTag.textContent = 'Within geofence: ' + geofenceName;
                    statusTag.className = 'geofence-status-tag-unique inside';
                } else {
                    // Show distance to nearest geofence
                    const distanceInMeters = Math.round(minDistance);
                    const distanceInKm = (minDistance / 1000).toFixed(2);
                    statusTag.textContent = `Outside geofence (${distanceInMeters}m / ${distanceInKm}km from nearest)`;
                    statusTag.className = 'geofence-status-tag-unique outside';
                }
            }
            
            // If user is outside all geofences, show reason textbox
            const reasonContainer = document.getElementById('geofenceReasonContainerUnique');
            if (!isWithinGeofence && reasonContainer) {
                reasonContainer.style.display = 'block';
            }
            
            // Return geofence information for submission
            return {
                isWithinGeofence: isWithinGeofence,
                nearestGeofenceId: nearestGeofenceId,
                distanceFromGeofence: minDistance
            };
        }
        
        // Function to calculate distance between two points (Haversine formula)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371e3; // Earth radius in meters
            const φ1 = lat1 * Math.PI/180;
            const φ2 = lat2 * Math.PI/180;
            const Δφ = (lat2-lat1) * Math.PI/180;
            const Δλ = (lon2-lon1) * Math.PI/180;
            
            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                    Math.cos(φ1) * Math.cos(φ2) *
                    Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            
            return R * c; // Distance in meters
        }
        
        // Function to submit punch
        function submitPunch() {
            // Validate geofence reason if needed
            if (!validateGeofenceReason()) {
                return; // Don't submit if validation fails
            }
            
            // Validate work report if needed (for punch out)
            if (isPunchedIn && !validateWorkReport()) {
                return; // Don't submit if validation fails
            }
            
            // Get geofence reason if provided
            const reasonContainer = document.getElementById('geofenceReasonContainerUnique');
            let geofenceReason = '';
            if (reasonContainer && reasonContainer.style.display !== 'none') {
                geofenceReason = document.getElementById('geofenceReasonTextUnique').value.trim();
            }
            
            // Get work report if provided (for punch out)
            let workReport = '';
            const workReportContainer = document.getElementById('workReportContainerUnique');
            if (workReportContainer) {
                workReport = document.getElementById('workReportTextUnique').value.trim();
            }
            
            // Get captured image data
            const capturedImage = document.getElementById('punchAttendanceCapturedImageUnique');
            const imageData = capturedImage.src;
            
            // Get location data
            const latitudeEl = document.getElementById('punchAttendanceLatitudeUnique');
            const longitudeEl = document.getElementById('punchAttendanceLongitudeUnique');
            const accuracyEl = document.getElementById('punchAttendanceAccuracyUnique');
            const addressEl = document.getElementById('punchAttendanceAddressUnique');
            
            const locationData = {
                latitude: latitudeEl ? latitudeEl.textContent : null,
                longitude: longitudeEl ? longitudeEl.textContent : null,
                accuracy: accuracyEl ? accuracyEl.textContent : null,
                address: addressEl ? addressEl.textContent : null
            };
            
            // Get geofence status
            const statusTag = document.getElementById('geofenceStatusTagUnique');
            const withinGeofence = statusTag && statusTag.classList.contains('inside');
            
            // Determine action (punch in or punch out) - this should be based on the current state BEFORE toggling
            const action = isPunchedIn ? 'punch_out' : 'punch_in';
            
            // Show loader
            showLoader(`Processing ${action === 'punch_in' ? 'Punch In' : 'Punch Out'}...`);
            
            // Prepare data to send to backend including geofence information
            const requestData = {
                action: action,
                photo: imageData,
                geofence_reason: geofenceReason,
                work_report: workReport, // Include work report for punch out
                within_geofence: geofenceInfo.isWithinGeofence,
                geofence_id: geofenceInfo.nearestGeofenceId,
                distance_from_geofence: geofenceInfo.distanceFromGeofence,
                ...locationData
            };
            
            // Send data to backend
            fetch('save_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                // Hide loader
                hideLoader();
                
                if (data.success) {
                    closePunchAttendanceModal();
                    
                    // Show success notification
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    const dateString = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                    
                    if (action === 'punch_in') {
                        punchButton.textContent = "Punch Out";
                        punchButton.style.background = "#ef4444";
                        isPunchedIn = true; // Set punch state to true after punch in
                        
                        // Enable site button when user punches in
                        updateSiteButtonState(true);
                        
                        // Show success notification for punch in
                        showAlertNotification(
                            'success',
                            'Punch In Successful',
                            'You have successfully punched in!',
                            `Time: ${timeString} | Date: ${dateString}`,
                            'Have a great and productive day!'
                        );
                    } else {
                        punchButton.textContent = "Attendance Completed";
                        punchButton.style.background = "#6c757d";
                        punchButton.disabled = true;
                        punchButton.title = "You have already completed your attendance for today";
                        isPunchedIn = false; // Set punch state to false after punch out
                        
                        // Disable site button when user punches out
                        updateSiteButtonState(false);
                        
                        // Remove work report section after punch out
                        const workReportContainer = document.getElementById('workReportContainerUnique');
                        if (workReportContainer) {
                            workReportContainer.remove();
                        }
                        
                        // Show success notification for punch out
                        showAlertNotification(
                            'success',
                            'Punch Out Successful',
                            'You have successfully completed your attendance for today!',
                            `Time: ${timeString} | Date: ${dateString}`,
                            'Great work today! See you tomorrow!'
                        );
                    }
                } else {
                    showAlertNotification(
                        'warning',
                        'Punch Error',
                        'Error saving attendance',
                        data.message,
                        'Please try again'
                    );
                }
            })
            .catch(error => {
                // Hide loader
                hideLoader();
                
                console.error('Error:', error);
                showAlertNotification(
                    'warning',
                    'Connection Error',
                    'An error occurred while submitting punch',
                    'Please check your connection and try again',
                    'We apologize for the inconvenience'
                );
            });
        }
        
        // Function to close the punch attendance modal
        function closePunchAttendanceModal() {
            const modal = document.getElementById('punchAttendanceModalUnique');
            
            if (modal) {
                modal.classList.remove('active');
                
                // Stop camera stream
                if (currentStream) {
                    currentStream.getTracks().forEach(track => track.stop());
                    currentStream = null;
                }
            }
        }
        
        // Update punch button event listener to also update site button
        punchButton.addEventListener("click", function() {
            // Check if user has already completed their attendance cycle
            if (hasCompletedAttendanceCycle) {
                // Show notification that attendance cycle is already completed
                showAlertNotification(
                    'warning',
                    'Attendance Cycle Completed',
                    'You have already completed your attendance for today',
                    'You cannot punch in again until tomorrow',
                    'Great job on completing your work day!'
                );
                return;
            }
            
            if (isPunchedIn) {
                // If currently punched in, clicking should punch out
                openPunchOutModal(); // Open punch out modal with work report
            } else {
                // If currently punched out, clicking should punch in
                openPunchAttendanceModal(); // Open punch in modal
            }
        });
        
        // Function to update site button state
        function updateSiteButtonState(isPunchedIn) {
            const siteButton = document.getElementById("siteButton");
            if (isPunchedIn) {
                siteButton.disabled = false;
                siteButton.style.opacity = "1";
                siteButton.title = "";
            } else {
                siteButton.disabled = true;
                siteButton.style.opacity = "0.5";
                siteButton.title = "Please punch in first";
                
                // Reset site button to "Site In" when user punches out
                siteButton.textContent = "Site In";
                siteButton.style.background = "#8b5cf6";
                isSiteIn = false;
            }
        }
        
        // Site button functionality
        const siteButton = document.getElementById("siteButton");
        let isSiteIn = isCurrentlySiteIn;
        
        // Debug logging
        console.log("Initial site status from PHP:", isCurrentlySiteIn);
        console.log("JavaScript isSiteIn variable:", isSiteIn);
        
        // Set initial site button state based on punch and site status
        if (!isPunchedIn) {
            siteButton.disabled = true;
            siteButton.style.opacity = "0.5";
            siteButton.title = "Please punch in first";
            console.log("Site button disabled - user not punched in");
        } else {
            // User is punched in, set site button state
            if (isSiteIn) {
                siteButton.textContent = "Site Out";
                siteButton.style.background = "#ef4444";
                console.log("Setting site button to Site Out");
            } else {
                siteButton.textContent = "Site In";
                siteButton.style.background = "#8b5cf6";
                console.log("Setting site button to Site In");
            }
        }
        
        siteButton.addEventListener("click", function() {
            // Check if user is punched in before allowing site in/out
            if (!isPunchedIn) {
                // Show warning notification
                showAlertNotification(
                    'warning',
                    'Action Not Allowed',
                    'You have not punched in',
                    'Please punch in first before using site in/out functionality',
                    'Safety first - always punch in before starting work'
                );
                return;
            }
            
            // Get user location first
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        const accuracy = position.coords.accuracy;
                        
                        // Get address using reverse geocoding
                        let address = null;
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`)
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.display_name) {
                                    address = data.display_name;
                                    if (address.length > 100) {
                                        address = address.substring(0, 97) + '...';
                                    }
                                }
                                // Call site in/out handler
                                callSiteInOutHandler(latitude, longitude, address);
                            })
                            .catch(error => {
                                console.error('Error fetching address:', error);
                                // Call site in/out handler even without address
                                callSiteInOutHandler(latitude, longitude, null);
                            });
                    },
                    function(error) {
                        showAlertNotification(
                            'warning',
                            'Location Error',
                            'Unable to get location',
                            error.message,
                            'Please enable location services and try again'
                        );
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                showAlertNotification(
                    'warning',
                    'Browser Error',
                    'Geolocation not supported',
                    'Your browser does not support geolocation services',
                    'Please use a modern browser'
                );
            }
        });
        
        // Function to call site in/out handler
        function callSiteInOutHandler(latitude, longitude, address) {
            // Determine action based on current state
            const action = isSiteIn ? 'site_out' : 'site_in';
            
            // Show loader
            showLoader(`Processing Site ${action === 'site_in' ? 'In' : 'Out'}...`);
            
            // Get device info
            const deviceInfo = navigator.userAgent;
            
            // Prepare data to send
            const requestData = {
                action: action,
                latitude: latitude,
                longitude: longitude,
                address: address,
                device_info: deviceInfo
            };
            
            // Send data to backend
            fetch('ajax_handlers/site_in_out.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(requestData)
            })
            .then(response => response.json())
            .then(data => {
                // Hide loader
                hideLoader();
                
                if (data.status === 'success') {
                    // Toggle site state
                    isSiteIn = !isSiteIn;
                    
                    // Update button text and style
                    if (isSiteIn) {
                        siteButton.textContent = "Site Out";
                        siteButton.style.background = "#ef4444";
                    } else {
                        siteButton.textContent = "Site In";
                        siteButton.style.background = "#8b5cf6";
                    }
                    
                    // Show success notification
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    const dateString = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                    
                    showAlertNotification(
                        'success',
                        `Site ${action === 'site_in' ? 'In' : 'Out'} Successful`,
                        `You have successfully ${action === 'site_in' ? 'entered' : 'exited'} the site!`,
                        `Time: ${timeString} | Date: ${dateString}`,
                        action === 'site_in' ? 'Stay safe on the site!' : 'Have a great day off-site!'
                    );
                } else {
                    showAlertNotification(
                        'warning',
                        'Site Action Error',
                        'Error recording site action',
                        data.message,
                        'Please try again'
                    );
                }
            })
            .catch(error => {
                // Hide loader
                hideLoader();
                
                console.error('Error:', error);
                showAlertNotification(
                    'warning',
                    'Connection Error',
                    'An error occurred while processing your request',
                    'Please check your connection and try again',
                    'We apologize for the inconvenience'
                );
            });
        }
        
        // Notification dropdown functionality
        const notificationIcon = document.getElementById("notificationIcon");
        const notificationsDropdown = document.getElementById("notificationsDropdown");
        const markAllRead = document.getElementById("markAllRead");
        const notificationBadge = document.querySelector(".notification-badge");
        
        // Function to fetch missing punches and populate notifications
        function fetchMissingPunches() {
            // Clear existing notifications
            const notificationItems = notificationsDropdown.querySelectorAll(".notification-item");
            notificationItems.forEach(item => {
                if (!item.classList.contains("mark-all-read")) {
                    item.remove();
                }
            });
            
            // Show loading message
            const loadingItem = document.createElement("div");
            loadingItem.className = "notification-item";
            loadingItem.innerHTML = '<div class="notification-message">Loading notifications...</div>';
            notificationsDropdown.insertBefore(loadingItem, markAllRead);
            
            // Fetch missing punches
            fetch('ajax_handlers/get_missing_punches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                // Remove loading message
                loadingItem.remove();
                
                if (data.success) {
                    const missingPunches = data.data;
                    const dates = missingPunches.map(punch => punch.date);
                    
                    // Update notification badge
                    notificationBadge.textContent = missingPunches.length;
                    if (missingPunches.length === 0) {
                        notificationBadge.style.display = "none";
                    } else {
                        notificationBadge.style.display = "block";
                    }
                    
                    if (missingPunches.length === 0) {
                        // No missing punches
                        const noItem = document.createElement("div");
                        noItem.className = "notification-item";
                        noItem.innerHTML = '<div class="notification-message">No missing attendance records</div>';
                        notificationsDropdown.insertBefore(noItem, markAllRead);
                    } else {
                        // Check read status for all dates
                        fetch('ajax_handlers/check_notification_read_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'dates[]=' + dates.join('&dates[]=')
                        })
                        .then(response => response.json())
                        .then(statusData => {
                            if (statusData.success) {
                                // Create notification items for ALL notifications (with scrolling)
                                missingPunches.forEach(punch => {
                                    const dateObj = new Date(punch.date);
                                    const formattedDate = dateObj.toLocaleDateString('en-US', { 
                                        weekday: 'short', 
                                        month: 'short', 
                                        day: 'numeric' 
                                    });
                                    
                                    const isRead = statusData.read_dates.includes(punch.date);
                                    
                                    // Check submitted status based on notification type
                                    let isSubmitted = false;
                                    if (punch.type === 'punch_in') {
                                        isSubmitted = statusData.submitted_punch_in_dates.includes(punch.date);
                                    } else if (punch.type === 'punch_out') {
                                        isSubmitted = statusData.submitted_punch_out_dates.includes(punch.date);
                                    }
                                    
                                    // Create notification based on type or missing fields
                                    let notificationType, title, message;
                                    
                                    // Check if this is a new format with type property
                                    if (punch.type) {
                                        notificationType = punch.type;
                                        if (punch.type === 'punch_in') {
                                            title = "Missing Punch In";
                                            message = "Punch in not recorded";
                                        } else if (punch.type === 'punch_out') {
                                            title = "Missing Punch Out";
                                            message = "Punch out not recorded";
                                        }
                                    } else {
                                        // Handle old format for backward compatibility
                                        if (punch.punch_in === null && punch.punch_out === null) {
                                            // For backward compatibility, we'll show punch in by default
                                            notificationType = 'punch_in';
                                            title = "Missing Attendance";
                                            message = "No punch in or out recorded";
                                        } else if (punch.punch_in === null) {
                                            notificationType = 'punch_in';
                                            title = "Missing Punch In";
                                            message = "Punch in not recorded";
                                        } else if (punch.punch_out === null) {
                                            notificationType = 'punch_out';
                                            title = "Missing Punch Out";
                                            message = "Punch out not recorded";
                                        }
                                    }
                                    
                                    const notificationItem = document.createElement("div");
                                    notificationItem.className = "notification-item" + (isRead && !isSubmitted ? "" : " unread");
                                    notificationItem.setAttribute("data-date", punch.date);
                                    notificationItem.setAttribute("data-type", notificationType);
                                    
                                    // Add status indicator
                                    let statusText = "";
                                    if (isSubmitted) {
                                        statusText = " (Submitted)";
                                        notificationItem.classList.remove("unread");
                                    } else if (!isRead) {
                                        statusText = " (New)";
                                    } else {
                                        statusText = " (Read)";
                                    }
                                    
                                    notificationItem.innerHTML = `
                                        <div class="notification-title">${title}${statusText}</div>
                                        <div class="notification-message">${message} on ${formattedDate}</div>
                                        <div class="notification-time">${punch.date}</div>
                                    `;
                                    
                                    // Add click event to open appropriate modal
                                    notificationItem.addEventListener("click", function() {
                                        console.log("Notification clicked:", punch.date, "Type:", notificationType);
                                        console.log("Punch data:", punch);
                                        console.log("Is submitted:", isSubmitted);
                                        
                                        // Mark as read when clicked
                                        if (!isSubmitted) {
                                            markNotificationAsRead(punch.date);
                                            this.classList.remove("unread");
                                        }
                                        
                                        // Open appropriate modal based on notification type
                                        if (notificationType === 'punch_in') {
                                            console.log("Opening punch in modal");
                                            openMissingPunchModal(punch.date);
                                        } else if (notificationType === 'punch_out') {
                                            console.log("Opening punch out modal");
                                            openMissingPunchOutModal(punch.date);
                                        }
                                    });
                                    
                                    notificationsDropdown.insertBefore(notificationItem, markAllRead);
                                });
                            } else {
                                // Error checking status
                                const errorItem = document.createElement("div");
                                errorItem.className = "notification-item";
                                errorItem.innerHTML = '<div class="notification-message">Error loading notification status</div>';
                                notificationsDropdown.insertBefore(errorItem, markAllRead);
                            }
                        })
                        .catch(error => {
                            // Remove loading message
                            loadingItem.remove();
                            
                            // Error checking status
                            const errorItem = document.createElement("div");
                            errorItem.className = "notification-item";
                            errorItem.innerHTML = '<div class="notification-message">Error checking notification status</div>';
                            notificationsDropdown.insertBefore(errorItem, markAllRead);
                        });
                    }
                } else {
                    // Remove loading message
                    loadingItem.remove();
                    
                    // Error fetching data
                    const errorItem = document.createElement("div");
                    errorItem.className = "notification-item";
                    errorItem.innerHTML = '<div class="notification-message">Error loading notifications: ' + data.message + '</div>';
                    notificationsDropdown.insertBefore(errorItem, markAllRead);
                }
            })
            .catch(error => {
                // Remove loading message
                loadingItem.remove();
                
                // Error fetching data
                const errorItem = document.createElement("div");
                errorItem.className = "notification-item";
                errorItem.innerHTML = '<div class="notification-message">Error loading notifications</div>';
                notificationsDropdown.insertBefore(errorItem, markAllRead);
            });
        }
        
        // Function to mark a notification as read
        function markNotificationAsRead(date) {
            // Send request to mark the notification as read
            fetch('ajax_handlers/mark_notification_as_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'date=' + encodeURIComponent(date)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("Notification for " + date + " marked as read");
                } else {
                    console.error("Error marking notification as read: " + data.message);
                }
            })
            .catch(error => {
                console.error("Error marking notification as read: " + error);
            });
        }
        
        // Function to open missing punch modal
        function openMissingPunchModal(date) {
            console.log("Attempting to open missing punch modal for date:", date);
            
            // Check if the missing punch modal exists in the DOM
            const missingPunchModal = document.getElementById('missingPunchModal');
            if (missingPunchModal) {
                // Call the modal's open function directly
                if (typeof window.openMissingPunchModalInternal === 'function') {
                    window.openMissingPunchModalInternal(date);
                } else {
                    // Fallback to direct manipulation if function not available
                    const dateInput = document.getElementById('missingPunchDate');
                    if (dateInput) {
                        dateInput.value = date;
                    }
                    
                    // Clear previous values
                    const timeInput = document.getElementById('missingPunchTime');
                    const reasonInput = document.getElementById('missingPunchReason');
                    const wordCount = document.getElementById('missingPunchWordCount');
                    const confirmCheckbox = document.getElementById('missingPunchConfirm');
                    const submitButton = document.getElementById('submitMissingPunch');
                    
                    if (timeInput) timeInput.value = '';
                    if (reasonInput) reasonInput.value = '';
                    if (wordCount) wordCount.textContent = '0';
                    if (confirmCheckbox) confirmCheckbox.checked = false;
                    if (submitButton) submitButton.disabled = true;
                    
                    // Show the modal
                    missingPunchModal.style.display = 'block';
                }
            } else {
                console.error("Missing punch modal not found in DOM");
                alert("Error: Missing punch in modal not available. Please refresh the page and try again.");
            }
        }
        
        // Function to open missing punch out modal
        function openMissingPunchOutModal(date) {
            console.log("Attempting to open missing punch out modal for date:", date);
            
            // Check if the missing punch out modal exists in the DOM
            const missingPunchOutModal = document.getElementById('missingPunchOutModal');
            if (missingPunchOutModal) {
                // Call the modal's open function directly
                if (typeof window.openMissingPunchOutModalInternal === 'function') {
                    window.openMissingPunchOutModalInternal(date);
                } else {
                    // Fallback to direct manipulation if function not available
                    const dateInput = document.getElementById('missingPunchOutDate');
                    if (dateInput) {
                        dateInput.value = date;
                    }
                    
                    // Clear previous values
                    const timeInput = document.getElementById('missingPunchOutTime');
                    const reasonInput = document.getElementById('missingPunchOutReason');
                    const workReportInput = document.getElementById('missingPunchOutWorkReport');
                    const reasonWordCount = document.getElementById('missingPunchOutWordCount');
                    const workReportWordCount = document.getElementById('workReportWordCount');
                    const confirmCheckbox = document.getElementById('missingPunchOutConfirm');
                    const submitButton = document.getElementById('submitMissingPunchOut');
                    
                    if (timeInput) timeInput.value = '';
                    if (reasonInput) reasonInput.value = '';
                    if (workReportInput) workReportInput.value = '';
                    if (reasonWordCount) reasonWordCount.textContent = '0';
                    if (workReportWordCount) workReportWordCount.textContent = '0';
                    if (confirmCheckbox) confirmCheckbox.checked = false;
                    if (submitButton) submitButton.disabled = true;
                    
                    // Show the modal
                    missingPunchOutModal.style.display = 'block';
                }
            } else {
                console.error("Missing punch out modal not found in DOM");
                alert("Error: Missing punch out modal not available. Please refresh the page and try again.");
            }
        }
        
        notificationIcon.addEventListener("click", function(e) {
            e.stopPropagation();
            // Close profile dropdown if open
            profileDropdown.style.display = "none";
            // Toggle notifications dropdown
            if (notificationsDropdown.style.display === "block") {
                notificationsDropdown.style.display = "none";
            } else {
                notificationsDropdown.style.display = "block";
                // Fetch missing punches when opening dropdown
                fetchMissingPunches();
            }
        });
        
        // Profile dropdown functionality
        const profileAvatar = document.getElementById("profileAvatar");
        const profileDropdown = document.getElementById("profileDropdown");
        const profileOption = document.getElementById("profileOption");
        const logoutOption = document.getElementById("logoutOption");
        
        profileAvatar.addEventListener("click", function(e) {
            e.stopPropagation();
            // Close notifications dropdown if open
            notificationsDropdown.style.display = "none";
            // Toggle profile dropdown
            if (profileDropdown.style.display === "block") {
                profileDropdown.style.display = "none";
            } else {
                profileDropdown.style.display = "block";
            }
        });
        
        // Profile option functionality
        profileOption.addEventListener("click", function() {
            window.location.href = 'site_supervisor_profile.php';
            profileDropdown.style.display = "none";
        });
        
        // Logout option functionality
        logoutOption.addEventListener("click", function() {
            // Redirect to logout.php to handle the logout process
            window.location.href = 'logout.php';
            profileDropdown.style.display = "none";
        });
        
        // Close dropdowns when clicking elsewhere
        document.addEventListener("click", function(e) {
            if (!notificationIcon.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                notificationsDropdown.style.display = "none";
            }
            if (!profileAvatar.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.style.display = "none";
            }
        });
        
        // Mark all as read functionality
        markAllRead.addEventListener("click", function() {
            // In a real implementation, you would send a request to mark all notifications as read
            const unreadItems = document.querySelectorAll(".notification-item.unread");
            unreadItems.forEach(item => {
                item.classList.remove("unread");
            });
            notificationBadge.style.display = "none";
        });
        
        function updateGreetingAndTime() {
            const now = new Date();
            const greetingsSection = document.getElementById("greetingsSection");
            
            // Update greeting based on time of day
            const hour = now.getHours();
            let greeting = "";
            let greetingIcon = "";
            let themeClass = "";
            
            if (hour < 12) {
                greeting = "Good Morning";
                greetingIcon = "fas fa-sun";
                themeClass = "morning";
            } else if (hour < 17) {
                greeting = "Good Afternoon";
                greetingIcon = "fas fa-cloud-sun";
                themeClass = "afternoon";
            } else {
                greeting = "Good Evening";
                greetingIcon = "fas fa-moon";
                themeClass = "evening";
            }
            
            // Apply theme class to the section
            greetingsSection.className = "greetings-section " + themeClass;
            
            document.getElementById("greetingMessage").textContent = greeting;
            document.querySelector(".greeting-icon").className = "greeting-icon " + greetingIcon;
            
            // Update date
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById("currentDate").textContent = now.toLocaleDateString('en-US', options);
            
            // Update time with seconds
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            document.getElementById("currentTime").textContent = now.toLocaleTimeString('en-US', timeOptions);
            
            // Update shift timer if it exists
            const shiftTimer = document.getElementById("shiftTimeDisplay");
            if (shiftTimer && shiftEndTimestamp) {
                const currentTime = Date.now();
                const remainingTime = Math.max(0, shiftEndTimestamp - currentTime);
                
                // Convert milliseconds to seconds
                const seconds = Math.floor(remainingTime / 1000);
                
                // Format as HH:MM:SS
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                
                shiftTimer.textContent = 
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(secs).padStart(2, '0');
            }
        }
        
        // Update immediately and then every second
        updateGreetingAndTime();
        setInterval(updateGreetingAndTime, 1000);
    </script>
</body>
</html>