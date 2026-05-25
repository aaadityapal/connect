<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

if (!empty($_SESSION['user_id'])) {
    echo json_encode([
        "loggedIn" => true,
        "user" => [
            "id"    => $_SESSION['user_id'],
            "name"  => $_SESSION['user_name'],
            "email" => $_SESSION['user_email'],
            "role"  => $_SESSION['user_role']
        ]
    ]);
} else {
    echo json_encode(["loggedIn" => false]);
}
?>
