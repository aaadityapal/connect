<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Check user role for access control
$allowed_roles = ['HR', 'admin', 'Senior Manager (Studio)', 'Senior Manager (Site)', 'Site Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Database connection
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON data from request body
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['rows']) || !is_array($data['rows'])) {
            throw new Exception('Invalid data format');
        }
        
        // Prepare statement for inserting data
        $columns = count($data['rows'][0] ?? []);
        if ($columns === 0) {
            throw new Exception('No data to save');
        }
        
        // Create placeholders for prepared statement
        $placeholders = rtrim(str_repeat('?,', $columns), ',');
        $columnNames = '';
        for ($i = 1; $i <= $columns; $i++) {
            $columnNames .= "column$i,";
        }
        $columnNames = rtrim($columnNames, ',');
        
        $sql = "INSERT INTO imported_excel_data ($columnNames, imported_by) VALUES ($placeholders, ?)";
        $stmt = $pdo->prepare($sql);
        
        // Insert each row
        $imported_by = $_SESSION['user_id'];
        foreach ($data['rows'] as $row) {
            // Pad row with empty values if needed
            $values = array_slice($row, 0, $columns);
            while (count($values) < $columns) {
                $values[] = '';
            }
            $values[] = $imported_by;
            
            $stmt->execute($values);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Data saved successfully',
            'rows_imported' => count($data['rows'])
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error saving data: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>