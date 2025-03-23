<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }
    
    echo '<p style="color: green;"><i class="fas fa-check-circle"></i> Database connection successful!</p>';
    
    // Check if official_documents table exists
    $result = $db->query("SHOW TABLES LIKE 'official_documents'");
    if ($result->num_rows > 0) {
        echo '<p><i class="fas fa-check-circle"></i> official_documents table exists.</p>';
        
        // Check structure of official_documents table
        $result = $db->query("DESCRIBE official_documents");
        echo '<p><strong>Table structure:</strong></p><ul style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px; margin:10px 0;">';
        while ($row = $result->fetch_assoc()) {
            echo '<li>' . $row['Field'] . ' - ' . $row['Type'] . '</li>';
        }
        echo '</ul>';
        
        // Check if there are records in the table
        $result = $db->query("SELECT COUNT(*) as count FROM official_documents");
        $row = $result->fetch_assoc();
        echo '<p><strong>Total official documents:</strong> ' . $row['count'] . '</p>';
        
        // See sample data (first 5 records) to help debug
        echo '<p><strong>Sample documents (first 5):</strong></p>';
        $result = $db->query("SELECT id, document_name, document_type, status, assigned_user_id FROM official_documents LIMIT 5");
        
        if ($result->num_rows > 0) {
            echo '<table style="border-collapse: collapse; width: 100%; margin: 10px 0;">';
            echo '<tr style="background-color: #f2f2f2;"><th style="border: 1px solid #ddd; padding: 8px;">ID</th><th style="border: 1px solid #ddd; padding: 8px;">Name</th><th style="border: 1px solid #ddd; padding: 8px;">Type</th><th style="border: 1px solid #ddd; padding: 8px;">Status</th><th style="border: 1px solid #ddd; padding: 8px;">Assigned To</th></tr>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['id'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['document_name'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['document_type'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['status'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['assigned_user_id'] . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        } else {
            echo '<p style="color: orange;"><i class="fas fa-exclamation-triangle"></i> No records found in the table.</p>';
        }
        
        // See if any are assigned to current user
        session_start();
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM official_documents WHERE assigned_user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            echo '<p><strong>Official documents assigned to current user (ID: ' . $user_id . '):</strong> ' . $row['count'] . '</p>';
            
            // Show the actual documents assigned to this user
            if ($row['count'] > 0) {
                echo '<p><strong>Your assigned documents:</strong></p>';
                $stmt = $db->prepare("SELECT id, document_name, document_type, status FROM official_documents WHERE assigned_user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                echo '<table style="border-collapse: collapse; width: 100%; margin: 10px 0;">';
                echo '<tr style="background-color: #f2f2f2;"><th style="border: 1px solid #ddd; padding: 8px;">ID</th><th style="border: 1px solid #ddd; padding: 8px;">Name</th><th style="border: 1px solid #ddd; padding: 8px;">Type</th><th style="border: 1px solid #ddd; padding: 8px;">Status</th></tr>';
                
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['id'] . '</td>';
                    echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['document_name'] . '</td>';
                    echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['document_type'] . '</td>';
                    echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $row['status'] . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            } else {
                echo '<p style="color: orange;"><i class="fas fa-exclamation-triangle"></i> No documents assigned to this user.</p>';
                
                // Let's create a test document for this user
                echo '<p><a href="create_test_document.php?user_id=' . $user_id . '" class="btn btn-primary btn-sm">Create Test Document</a></p>';
            }
        } else {
            echo '<p style="color: red;"><i class="fas fa-times-circle"></i> No active user session found.</p>';
        }
    } else {
        echo '<p style="color: red;"><i class="fas fa-times-circle"></i> official_documents table does not exist!</p>';
        
        // Provide SQL to create the table
        echo '<p><strong>SQL to create the table:</strong></p>';
        echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">
CREATE TABLE `official_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_name` varchar(255) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT \'pending\',
  `uploaded_by` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        </pre>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;"><i class="fas fa-times-circle"></i> Error: ' . $e->getMessage() . '</p>';
}
?> 