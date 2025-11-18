<?php
header('Content-Type: application/json');

try {
    // Include database configuration
    include 'config/db_connect.php';
    
    // Get the type from query parameter
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    
    if (empty($type)) {
        echo json_encode(['success' => false, 'error' => 'Type parameter is required']);
        exit;
    }
    
    $recipients = [];
    
    // Query based on type
    if (strtolower($type) === 'vendor') {
        // Fetch vendors from vendors table
        $stmt = $pdo->prepare("SELECT id, vendor_name as name FROM vendors WHERE status = 'Active' ORDER BY vendor_name ASC");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    elseif (strtolower($type) === 'labour') {
        // Fetch labour from labour table
        $stmt = $pdo->prepare("SELECT id, labour_name as name FROM labour WHERE status = 'Active' ORDER BY labour_name ASC");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif (strtolower($type) === 'supplier') {
        // Fetch suppliers from suppliers table
        $stmt = $pdo->prepare("SELECT id, supplier_name as name FROM suppliers WHERE status = 'Active' ORDER BY supplier_name ASC");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif (strtolower($type) === 'contractor') {
        // Fetch contractors from contractors table
        $stmt = $pdo->prepare("SELECT id, contractor_name as name FROM contractors WHERE status = 'Active' ORDER BY contractor_name ASC");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'recipients' => $recipients
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
