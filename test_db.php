<?php
require 'config/db_connect.php';
$stmt = $conn->query("SELECT lr.id, lr.duration_type, lr.day_type, lr.time_from, lr.time_to, lt.name as lt_name FROM leave_request lr LEFT JOIN leave_types lt ON lr.leave_type = lt.id ORDER BY lr.created_at DESC LIMIT 5");
while ($row = $stmt->fetch_assoc()) {
    print_r($row);
}
