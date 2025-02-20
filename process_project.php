<?php
require_once 'config/db_connect.php';
session_start();

try {
    // Begin transaction
    $conn->begin_transaction();

    // Insert into tbl_projects
    $project_query = "INSERT INTO tbl_projects (
        client_name, contact_number, email, project_type, 
        project_cost, marketing_user_id, description, 
        start_date, end_date, created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($project_query);
    $stmt->bind_param(
        "ssssidssis",
        $_POST['client_name'],
        $_POST['contact_number'],
        $_POST['email'],
        $_POST['project_type'],
        $_POST['project_cost'],
        $_POST['marketing_user_id'],
        $_POST['description'],
        $_POST['start_date'],
        $_POST['end_date'],
        $_SESSION['user_id']
    );
    $stmt->execute();

    // Update tbl_sales
    $sales_query = "INSERT INTO tbl_sales (
        project_id, amount, type, date
    ) VALUES (?, ?, ?, NOW())";

    $project_id = $conn->insert_id;
    $stmt = $conn->prepare($sales_query);
    $stmt->bind_param(
        "ids",
        $project_id,
        $_POST['project_cost'],
        $_POST['project_type']
    );
    $stmt->execute();

    // Get updated sales figures
    $sales_totals = $conn->query("
        SELECT 
            SUM(CASE WHEN type = 'architecture' THEN amount ELSE 0 END) as architecture,
            SUM(CASE WHEN type = 'construction' THEN amount ELSE 0 END) as construction,
            SUM(CASE WHEN type = 'interior' THEN amount ELSE 0 END) as interior
        FROM tbl_sales
        WHERE MONTH(date) = MONTH(CURRENT_DATE())
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ")->fetch_assoc();

    $sales_totals['total'] = $sales_totals['architecture'] + 
                            $sales_totals['construction'] + 
                            $sales_totals['interior'];

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Project added successfully',
        'sales' => $sales_totals
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add project: ' . $e->getMessage()
    ]);
}
