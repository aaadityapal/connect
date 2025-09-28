<?php
session_start();
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

// Database connection
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Check if table already exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'imported_excel_data'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Table already exists'
        ]);
        exit();
    }
    
    // Create the table
    $sql = "
        CREATE TABLE imported_excel_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            column1 VARCHAR(255),
            column2 VARCHAR(255),
            column3 VARCHAR(255),
            column4 VARCHAR(255),
            column5 VARCHAR(255),
            column6 VARCHAR(255),
            column7 VARCHAR(255),
            column8 VARCHAR(255),
            column9 VARCHAR(255),
            column10 VARCHAR(255),
            import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            imported_by INT,
            FOREIGN KEY (imported_by) REFERENCES users(id)
        )
    ";
    
    $pdo->exec($sql);
    
    // Add indexes
    $pdo->exec("CREATE INDEX idx_import_date ON imported_excel_data(import_date)");
    $pdo->exec("CREATE INDEX idx_imported_by ON imported_excel_data(imported_by)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Table created successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error creating table: ' . $e->getMessage()
    ]);
}
?>