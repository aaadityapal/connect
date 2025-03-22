<?php
session_start();
require_once 'config.php';

// Simulate a logged-in user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 15; // Replace with an actual user ID from your database
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }

    // First, let's check if the table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'personal_documents'");
    if ($tableCheck->num_rows === 0) {
        throw new Exception('Table personal_documents does not exist');
    }

    // Let's check the table structure
    $columns = $db->query("SHOW COLUMNS FROM personal_documents");
    echo "<h3>Table Structure:</h3>";
    echo "<pre>";
    while ($col = $columns->fetch_assoc()) {
        print_r($col);
        echo "\n";
    }
    echo "</pre>";

    // Now let's check for actual records
    $stmt = $db->prepare("
        SELECT 
            id,
            document_name,
            original_filename,
            document_type,
            file_type,
            file_size,
            upload_date,
            last_modified,
            document_number,
            issue_date,
            expiry_date,
            issuing_authority,
            uploaded_by
        FROM personal_documents
        WHERE uploaded_by = ?
    ");
    
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<h3>Query Results:</h3>";
    echo "<pre>";
    echo "User ID being checked: " . $_SESSION['user_id'] . "\n";
    echo "Number of records found: " . $result->num_rows . "\n\n";
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            print_r($row);
            echo "\n";
        }
    } else {
        echo "No records found for this user.\n";
        
        // Let's check if there are any records at all in the table
        $totalRecords = $db->query("SELECT COUNT(*) as total FROM personal_documents");
        $total = $totalRecords->fetch_assoc();
        echo "\nTotal records in table: " . $total['total'] . "\n";
        
        if ($total['total'] > 0) {
            // Show some sample records to verify data structure
            echo "\nSample records from table (up to 3):\n";
            $samples = $db->query("SELECT * FROM personal_documents LIMIT 3");
            while ($sample = $samples->fetch_assoc()) {
                print_r($sample);
                echo "\n";
            }
        }
    }
    
    echo "</pre>";

} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<pre>";
    echo "Error message: " . $e->getMessage() . "\n";
    echo "</pre>";
}

// Also display session information
echo "<h3>Session Information:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?> 