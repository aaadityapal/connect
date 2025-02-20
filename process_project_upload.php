<?php
require_once 'config/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['project_file'])) {
    require 'vendor/autoload.php';

    try {
        $inputFileName = $_FILES['project_file']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Remove header row
        array_shift($rows);
        
        // Begin transaction
        $conn->begin_transaction();
        
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row[0])) continue;
            
            // Get marketing user ID
            $marketing_query = "SELECT user_id FROM tbl_users WHERE username = ? AND role = 'marketing'";
            $stmt = $conn->prepare($marketing_query);
            $stmt->bind_param("s", $row[5]); // Marketing Executive column
            $stmt->execute();
            $marketing_result = $stmt->get_result()->fetch_assoc();
            $marketing_user_id = $marketing_result['user_id'];
            
            // Insert project
            $project_query = "INSERT INTO tbl_projects (
                client_name, contact_number, email, project_type, 
                project_cost, marketing_user_id, description, 
                start_date, end_date, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($project_query);
            $stmt->bind_param(
                "ssssidssis",
                $row[0], // Client Name
                $row[1], // Contact Number
                $row[2], // Email
                $row[3], // Project Type
                $row[4], // Project Cost
                $marketing_user_id,
                $row[6], // Description
                $row[7], // Start Date
                $row[8], // End Date
                $_SESSION['user_id']
            );
            $stmt->execute();
            
            // Insert into sales
            $project_id = $conn->insert_id;
            $sales_query = "INSERT INTO tbl_sales (project_id, amount, type, date) 
                           VALUES (?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sales_query);
            $stmt->bind_param(
                "ids",
                $project_id,
                $row[4], // Project Cost
                $row[3]  // Project Type
            );
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Projects imported successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error importing projects: ' . $e->getMessage()
        ]);
    }
}