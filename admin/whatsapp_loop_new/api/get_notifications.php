<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$query = "SELECT id, activity_type, page_name, activity_data, created_at,
          DATE_FORMAT(created_at, '%b %d, %h:%i %p') as time_formatted,
          TIMESTAMPDIFF(MINUTE, created_at, NOW()) as mins_ago
          FROM user_activities
          ORDER BY created_at DESC
          LIMIT 20";

$stmt = $conn->prepare($query);
$stmt->execute();

$notifications = array();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data = json_decode($row['activity_data'], true);
    $type = $row['activity_type'];
    $page = $row['page_name'];
    $mins = (int)$row['mins_ago'];

    // Build human-readable message & icon color
    $title   = '';
    $message = '';
    $color   = 'blue';

    if ($type === 'Client Added') {
        $name    = $data['name'] ?? 'A client';
        $tags    = isset($data['tags']) ? implode(', ', $data['tags']) : '';
        $title   = 'New Client Added';
        $message = "$name was added to your contact list" . ($tags ? " ($tags)" : "") . ".";
        $color   = 'green';
    } elseif ($type === 'Client Removed') {
        $name    = $data['name'] ?? 'A client';
        $title   = 'Client Removed';
        $message = "$name was permanently removed from the list.";
        $color   = 'red';
    } elseif ($type === 'Campaign Started') {
        $name     = $data['name'] ?? 'A campaign';
        $audience = $data['audience'] ?? 0;
        $title    = 'Campaign Launched';
        $message  = "\"$name\" was sent to $audience contact(s).";
        $color    = 'green';
    } elseif ($type === 'Page View') {
        $title   = 'Page Visited';
        $message = ucfirst($page) . " section was opened.";
        $color   = 'blue';
    } elseif ($type === 'User Click') {
        $text    = $data['text'] ?? 'a button';
        $title   = 'User Interaction';
        $message = "Clicked \"$text\" on the " . ucfirst($page) . " page.";
        $color   = 'purple';
    } else {
        $title   = $type;
        $message = ucfirst($page) . " activity recorded.";
        $color   = 'blue';
    }

    // Human-readable time
    if ($mins < 1) {
        $time_label = 'Just now';
    } elseif ($mins < 60) {
        $time_label = $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($mins < 1440) {
        $hrs = floor($mins / 60);
        $time_label = $hrs . ' hour' . ($hrs > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($mins / 1440);
        $time_label = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }

    $notifications[] = array(
        'id'         => $row['id'],
        'title'      => $title,
        'message'    => $message,
        'color'      => $color,
        'time_label' => $time_label,
        'type'       => $type
    );
}

echo json_encode($notifications);
?>
