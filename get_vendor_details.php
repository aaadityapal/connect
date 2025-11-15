<?php
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vendor ID is required']);
    exit;
}

$vendorId = intval($_GET['id']);

try {
    // Fetch vendor details
    $query = "SELECT * FROM pm_vendor_registry_master WHERE vendor_id = :vendor_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':vendor_id', $vendorId, PDO::PARAM_INT);
    $stmt->execute();
    $vendor = $stmt->fetch();

    if ($vendor) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $vendor
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Vendor not found'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
