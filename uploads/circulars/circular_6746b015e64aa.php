<?php
session_start();
require_once 'config/db_connect.php'; // Make sure this points to your database configuration file

header('Content-Type: application/json');

try {
    // Validate input
    if (empty($_POST['title']) || empty($_POST['description'])) {
        throw new Exception('Title and description are required');
    }

    // Sanitize input
    $title = htmlspecialchars($_POST['title']);
    $message = htmlspecialchars($_POST['description']); // Using description as message
    $display_until = !empty($_POST['display_until']) ? $_POST['display_until'] : null;
    $created_by = $_SESSION['user_id'] ?? 1; // Replace with actual user ID from session
    $priority = $_POST['priority'] ?? 'normal'; // Add priority field
    $status = 'active'; // Set default status
    $content = $message; // Using message as content as well

    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=login_system",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prepare and execute the insert statement
    $stmt = $pdo->prepare("
        INSERT INTO announcements (
            title, 
            message, 
            priority, 
            display_until, 
            created_by, 
            created_at,
            status,
            content
        )
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");

    $stmt->execute([
        $title,
        $message,
        $priority,
        $display_until,
        $created_by,
        $status,
        $content
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Announcement added successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
