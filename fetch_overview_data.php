<?php
require_once 'config.php'; // Include your database connection

// Get the selected date
$selectedDate = isset($_POST['overview_date']) ? $_POST['overview_date'] : date('Y-m-d');
$formattedDate = date('Y-m-d', strtotime($selectedDate));

// Fetch all required data
$data = [
    'presentCount' => 0,
    'lateCount' => 0,
    'onLeaveCount' => 0,
    'shortLeaveCount' => 0,
    'presentModalContent' => '',
    'lateModalContent' => '',
    'leaveModalContent' => '',
    'shortLeaveModalContent' => ''
];

// Present employees query
$presentQuery = "SELECT COUNT(DISTINCT user_id) as count 
               FROM attendance 
               WHERE date = '$formattedDate' 
               AND punch_in IS NOT NULL";
$data['presentCount'] = $conn->query($presentQuery)->fetch_assoc()['count'] ?? 0;

// Late employees query
$lateQuery = "SELECT COUNT(DISTINCT user_id) as count 
             FROM attendance 
             WHERE date = '$formattedDate' 
             AND TIME(punch_in) > '09:30:00'";
$data['lateCount'] = $conn->query($lateQuery)->fetch_assoc()['count'] ?? 0;

// On leave query
$leaveQuery = "SELECT COUNT(DISTINCT user_id) as count 
              FROM leaves 
              WHERE '$formattedDate' BETWEEN start_date AND end_date 
              AND status = 'Approved'";
$data['onLeaveCount'] = $conn->query($leaveQuery)->fetch_assoc()['count'] ?? 0;

// Short leave query
$shortLeaveQuery = "SELECT COUNT(DISTINCT user_id) as count 
                  FROM leaves 
                  WHERE DATE(start_date) = '$formattedDate' 
                  AND leave_type = 'Short Leave' 
                  AND status = 'Approved'";
$data['shortLeaveCount'] = $conn->query($shortLeaveQuery)->fetch_assoc()['count'] ?? 0;

// Generate modal contents
// Present Modal Content
ob_start();
include 'modal_contents/present_modal.php';
$data['presentModalContent'] = ob_get_clean();

// Late Modal Content
ob_start();
include 'modal_contents/late_modal.php';
$data['lateModalContent'] = ob_get_clean();

// Leave Modal Content
ob_start();
include 'modal_contents/leave_modal.php';
$data['leaveModalContent'] = ob_get_clean();

// Short Leave Modal Content
ob_start();
include 'modal_contents/short_leave_modal.php';
$data['shortLeaveModalContent'] = ob_get_clean();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($data);
?>