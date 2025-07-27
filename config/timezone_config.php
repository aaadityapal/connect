<?php
/**
 * Centralized timezone configuration
 * This file should be included at the start of any file that deals with dates/times
 */

// Set PHP timezone
date_default_timezone_set('Asia/Kolkata');

// Function to ensure MySQL timezone is set correctly
function ensureCorrectTimezone($conn) {
    // First try to set timezone using named timezone
    $timezone_set = false;
    try {
        // Try to set Asia/Kolkata first
        if ($conn->query("SET time_zone = 'Asia/Kolkata'")) {
            $timezone_set = true;
        }
    } catch (Exception $e) {
        // If named timezone fails, we'll use offset
    }

    // If named timezone failed, use offset
    if (!$timezone_set) {
        $conn->query("SET time_zone = '+05:30'");
    }

    // Verify the timezone setting
    $result = $conn->query("SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP) as time_diff");
    $row = $result->fetch_assoc();
    
    // If timezone is still not correct, log an error
    if ($row['time_diff'] !== '05:30:00') {
        error_log("Warning: MySQL timezone offset is not IST. Current offset: " . $row['time_diff']);
    }
}

// Function to convert any timestamp to IST
function convertToIST($timestamp) {
    if (empty($timestamp)) return null;
    
    try {
        $dt = new DateTime($timestamp);
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Error converting timestamp to IST: " . $e->getMessage());
        return $timestamp;
    }
}

// Function to get current IST timestamp
function getCurrentIST() {
    return date('Y-m-d H:i:s');
}

// Function to format timestamp for display
function formatTimestamp($timestamp, $format = 'Y-m-d h:i A') {
    try {
        $dt = new DateTime($timestamp);
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("Error formatting timestamp: " . $e->getMessage());
        return $timestamp;
    }
}
?> 