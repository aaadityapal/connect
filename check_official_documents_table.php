<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }
    
    echo "<h3>Checking official_documents table</h3>";
    
    // Check if table exists
    $result = $db->query("SHOW TABLES LIKE 'official_documents'");
    
    if ($result->num_rows == 0) {
        echo "<p>Table 'official_documents' doesn't exist. Creating it now...</p>";
        
        // Create the table
        $sql = "CREATE TABLE `official_documents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `document_name` varchar(255) NOT NULL,
            `document_type` varchar(50) NOT NULL,
            `file_path` varchar(255) NOT NULL,
            `original_filename` varchar(255) NOT NULL,
            `file_size` int(11) DEFAULT NULL,
            `status` varchar(20) DEFAULT 'pending',
            `uploaded_by` int(11) DEFAULT NULL,
            `assigned_user_id` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if ($db->query($sql)) {
            echo "<p style='color: green;'>Table created successfully!</p>";
            
            // Add sample data
            session_start();
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to user 1 if not logged in
            
            $sql = "INSERT INTO `official_documents` 
                    (`document_name`, `document_type`, `file_path`, `original_filename`, `file_size`, `status`, `uploaded_by`, `assigned_user_id`) 
                    VALUES 
                    ('Employment Contract', 'contract', 'documents/official/contract_1.pdf', 'employment_contract.pdf', 245000, 'pending', 1, $user_id),
                    ('Tax Declaration Form', 'tax', 'documents/official/tax_form_1.pdf', 'tax_form.pdf', 180000, 'pending', 1, $user_id),
                    ('Employee Handbook', 'handbook', 'documents/official/handbook_1.pdf', 'handbook.pdf', 1250000, 'pending', 1, $user_id)";
                    
            if ($db->query($sql)) {
                echo "<p style='color: green;'>Sample documents added successfully!</p>";
            } else {
                echo "<p style='color: red;'>Error adding sample documents: " . $db->error . "</p>";
            }
        } else {
            echo "<p style='color: red;'>Error creating table: " . $db->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>Table 'official_documents' exists!</p>";
        
        // Check count of records
        $result = $db->query("SELECT COUNT(*) as count FROM official_documents");
        $row = $result->fetch_assoc();
        echo "<p>Total records: " . $row['count'] . "</p>";
        
        // Check records for current user
        session_start();
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $result = $db->query("SELECT COUNT(*) as count FROM official_documents WHERE assigned_user_id = $user_id");
            $row = $result->fetch_assoc();
            echo "<p>Documents assigned to current user (ID: $user_id): " . $row['count'] . "</p>";
            
            // If no records for current user, add some
            if ($row['count'] == 0) {
                echo "<p>No documents assigned to current user. Adding sample documents...</p>";
                
                $sql = "INSERT INTO `official_documents` 
                        (`document_name`, `document_type`, `file_path`, `original_filename`, `file_size`, `status`, `uploaded_by`, `assigned_user_id`) 
                        VALUES 
                        ('Employment Contract', 'contract', 'documents/official/contract_$user_id.pdf', 'employment_contract.pdf', 245000, 'pending', 1, $user_id),
                        ('Tax Declaration Form', 'tax', 'documents/official/tax_form_$user_id.pdf', 'tax_form.pdf', 180000, 'pending', 1, $user_id),
                        ('Employee Handbook', 'handbook', 'documents/official/handbook_$user_id.pdf', 'handbook.pdf', 1250000, 'pending', 1, $user_id)";
                        
                if ($db->query($sql)) {
                    echo "<p style='color: green;'>Sample documents added for user $user_id!</p>";
                } else {
                    echo "<p style='color: red;'>Error adding sample documents: " . $db->error . "</p>";
                }
            }
        }
    }
    
    // Show button to go back to profile
    echo "<p><a href='profile.php' style='display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Back to Profile</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 