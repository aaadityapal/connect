<?php
require_once 'config/db_connect.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

try {
    // Validate required fields
    $required_fields = ['project_name', 'client_name', 'project_type', 'location', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Start building the SQL query
    $fields = [
        'project_name', 'client_name', 'project_type', 'location', 'status',
        'assigned_to', 'total_cost', 'created_at'
    ];
    
    // Add stage and substage fields
    for ($i = 1; $i <= 5; $i++) {
        $fields[] = "stage$i";
        $fields[] = "stage{$i}_status";
        $fields[] = "stage{$i}_assigned_to";
        for ($j = 1; $j <= 6; $j++) {
            $fields[] = "stage{$i}_sub{$j}";
            $fields[] = "stage{$i}_sub{$j}_status";
            $fields[] = "stage{$i}_sub{$j}_assigned_to";
        }
    }

    // Create the placeholders for the SQL query
    $placeholders = array_map(function($field) {
        return ":$field";
    }, $fields);

    // Prepare the SQL statement
    $sql = "INSERT INTO projects (" . implode(", ", $fields) . ") 
            VALUES (" . implode(", ", $placeholders) . ")";
    
    $stmt = $pdo->prepare($sql);

    // Build the parameters array
    $params = [
        'project_name' => $_POST['project_name'],
        'client_name' => $_POST['client_name'],
        'project_type' => $_POST['project_type'],
        'location' => $_POST['location'],
        'status' => $_POST['status'],
        'assigned_to' => !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
        'total_cost' => !empty($_POST['total_cost']) ? $_POST['total_cost'] : null,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Add stage and substage parameters
    for ($i = 1; $i <= 5; $i++) {
        $params["stage$i"] = $_POST["stage$i"] ?? '';
        $params["stage{$i}_status"] = $_POST["stage{$i}_status"] ?? 'Pending';
        $params["stage{$i}_assigned_to"] = $_POST["stage{$i}_assigned_to"] ?? null;
        for ($j = 1; $j <= 6; $j++) {
            $params["stage{$i}_sub{$j}"] = $_POST["stage{$i}_sub{$j}"] ?? '';
            $params["stage{$i}_sub{$j}_status"] = $_POST["stage{$i}_sub{$j}_status"] ?? 'Pending';
            $params["stage{$i}_sub{$j}_assigned_to"] = $_POST["stage{$i}_sub{$j}_assigned_to"] ?? null;
        }
    }

    // Execute the statement
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Task created successfully']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 