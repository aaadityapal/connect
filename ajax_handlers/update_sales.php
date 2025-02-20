<?php
require_once '../config/db_connect.php'; // Adjust path as needed
session_start();

header('Content-Type: application/json');

if (!isset($_POST['from_date']) || !isset($_POST['end_date'])) {
    echo json_encode(['success' => false, 'error' => 'Missing date parameters']);
    exit;
}

try {
    $from_date = $_POST['from_date'];
    $end_date = $_POST['end_date'];

    // Validate dates
    if (!strtotime($from_date) || !strtotime($end_date)) {
        throw new Exception('Invalid date format');
    }

    $query = "
        SELECT 
            project_type,
            COUNT(*) as project_count,
            SUM(total_cost) as total_value
        FROM projects 
        WHERE created_at BETWEEN ? AND ?
            AND status != 'cancelled'
            AND (archived_date IS NULL OR archived_date > ?)
        GROUP BY project_type";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $from_date, $end_date, $from_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sales_data = [
        'success' => true,
        'total_sales' => 0,
        'architecture' => 0,
        'interior' => 0,
        'construction' => 0,
        'project_counts' => [
            'total' => 0,
            'architecture' => 0,
            'interior' => 0,
            'construction' => 0
        ]
    ];
    
    while ($row = $result->fetch_assoc()) {
        $value = $row['total_value'] / 100000; // Convert to Lakhs
        $sales_data['total_sales'] += $value;
        $sales_data['project_counts']['total'] += $row['project_count'];
        
        switch ($row['project_type']) {
            case 'architecture':
                $sales_data['architecture'] = $value;
                $sales_data['project_counts']['architecture'] = $row['project_count'];
                break;
            case 'interior':
                $sales_data['interior'] = $value;
                $sales_data['project_counts']['interior'] = $row['project_count'];
                break;
            case 'construction':
                $sales_data['construction'] = $value;
                $sales_data['project_counts']['construction'] = $row['project_count'];
                break;
        }
    }

    echo json_encode($sales_data);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}