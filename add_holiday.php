<?php
session_start();
require_once 'config/db_connect.php'; // Adjust path as needed

try {
    // Validate input
    if (empty($_POST['title']) || empty($_POST['holiday_date'])) {
        throw new Exception("Required fields are missing");
    }

    // Sanitize inputs
    $title = htmlspecialchars($_POST['title']);
    $date = $_POST['holiday_date'];
    $type = htmlspecialchars($_POST['holiday_type']);
    $description = htmlspecialchars($_POST['description'] ?? '');

    // Validate date
    $holiday_date = new DateTime($date);
    $current_date = new DateTime();
    if ($holiday_date < $current_date) {
        throw new Exception("Holiday date cannot be in the past");
    }

    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=crm",
        "root",  // replace with your database username
        ""       // replace with your database password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Insert holiday
    $stmt = $pdo->prepare("
        INSERT INTO holidays (
            title, 
            holiday_date, 
            holiday_type, 
            description, 
            created_by, 
            created_at,
            status
        ) VALUES (
            :title,
            :holiday_date,
            :holiday_type,
            :description,
            :created_by,
            NOW(),
            'active'
        )
    ");

    $stmt->execute([
        ':title' => $title,
        ':holiday_date' => $date,
        ':holiday_type' => $type,
        ':description' => $description,
        ':created_by' => $_SESSION['user_id'] ?? 1
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Holiday added successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 