<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Debug: Log all POST data
        error_log("POST Data: " . print_r($_POST, true));

        // Set shift times based on shift type
        $shift_start = '';
        $shift_end = '';
        
        switch($_POST['shift_type']) {
            case 'morning':
                $shift_start = '09:00:00';
                $shift_end = '18:00:00';
                break;
            case 'evening':
                $shift_start = '14:00:00';
                $shift_end = '23:00:00';
                break;
            case 'night':
                $shift_start = '21:00:00';
                $shift_end = '06:00:00';
                break;
            case 'custom':
                $shift_start = $_POST['shift_start'] . ':00';
                $shift_end = $_POST['shift_end'] . ':00';
                break;
        }

        // Handle weekly off days
        $weekly_off = '';
        if (isset($_POST['weekly_off']) && is_array($_POST['weekly_off'])) {
            $weekly_off = implode(', ', $_POST['weekly_off']);
        }

        error_log("Processed Data:");
        error_log("Shift Start: " . $shift_start);
        error_log("Shift End: " . $shift_end);
        error_log("Weekly Off: " . $weekly_off);

        // Update the database query
        $sql = "INSERT INTO users (
            username, 
            email, 
            password, 
            department, 
            designation, 
            role, 
            shift_start, 
            shift_end, 
            weekly_off
        ) VALUES (
            :username,
            :email,
            :password,
            :department,
            :designation,
            :role,
            :shift_start,
            :shift_end,
            :weekly_off
        )";

        $stmt = $pdo->prepare($sql);
        
        $params = [
            ':username' => $_POST['username'],
            ':email' => $_POST['email'],
            ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            ':department' => $_POST['department'],
            ':designation' => $_POST['designation'],
            ':role' => $_POST['role'],
            ':shift_start' => $shift_start,
            ':shift_end' => $shift_end,
            ':weekly_off' => $weekly_off
        ];

        // Execute and check for errors
        if (!$stmt->execute($params)) {
            error_log("Database Error: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Database error occurred");
        }

        // Verify the inserted data
        $new_id = $pdo->lastInsertId();
        $verify = $pdo->query("SELECT shift_start, shift_end, weekly_off FROM users WHERE id = $new_id")->fetch();
        error_log("Verified Data: " . print_r($verify, true));

        $_SESSION['success_message'] = "User added successfully!";
        header('Location: hr_dashboard.php');
        exit();

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: add_user.php');
        exit();
    }
}
?>
