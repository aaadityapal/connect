<?php
/**
 * Migration script to transfer data from project_payouts table to the new tables:
 * - hrm_project_stage_payment_transactions
 * - hrm_project_payment_entries
 */

// Determine if script is running from CLI or web
$isCLI = (php_sapi_name() === 'cli');

// If running from web, output as HTML
if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre>";
}

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set script execution time to 5 minutes to handle large datasets
set_time_limit(300);

echo "Starting migration of project payouts data...\n";

// Start a transaction
$conn->begin_transaction();

try {
    // Check if the source table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'project_payouts'");
    if ($tableCheckResult->num_rows == 0) {
        throw new Exception("Source table 'project_payouts' does not exist");
    }
    
    // Check if destination tables exist
    $destTable1Check = $conn->query("SHOW TABLES LIKE 'hrm_project_stage_payment_transactions'");
    $destTable2Check = $conn->query("SHOW TABLES LIKE 'hrm_project_payment_entries'");
    
    if ($destTable1Check->num_rows == 0 || $destTable2Check->num_rows == 0) {
        // Tables don't exist, create them
        echo "Creating destination tables...\n";
        
        // Read SQL file content
        $sqlFilePath = __DIR__ . '/project_stage_payment_transactions.sql';
        if (!file_exists($sqlFilePath)) {
            throw new Exception("SQL file not found: $sqlFilePath");
        }
        
        $sqlContent = file_get_contents($sqlFilePath);
        
        // Execute SQL statements
        if ($conn->multi_query($sqlContent)) {
            // Process all result sets
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }
        
        if ($conn->error) {
            throw new Exception("Error creating tables: " . $conn->error);
        }
        
        echo "Destination tables created successfully.\n";
    } else {
        echo "Destination tables already exist.\n";
    }
    
    // Get all records from project_payouts
    $query = "SELECT * FROM project_payouts";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error fetching data from project_payouts: " . $conn->error);
    }
    
    $totalRecords = $result->num_rows;
    echo "Found $totalRecords records to migrate.\n";
    
    $migratedCount = 0;
    $errorCount = 0;
    
    // Prepare insert statements
    $transactionInsertStmt = $conn->prepare("
        INSERT INTO hrm_project_stage_payment_transactions 
        (project_id, project_name, project_type, client_name, stage_number, stage_date, 
         stage_notes, remaining_amount, total_project_amount, created_at, updated_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $paymentInsertStmt = $conn->prepare("
        INSERT INTO hrm_project_payment_entries
        (transaction_id, payment_date, payment_amount, payment_mode, created_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if (!$transactionInsertStmt || !$paymentInsertStmt) {
        throw new Exception("Error preparing statements: " . $conn->error);
    }
    
    // Process each record
    while ($row = $result->fetch_assoc()) {
        try {
            // For project_id, use the original id or 0 if not available
            $projectId = $row['id'] ?? 0;
            
            // Set default values for required fields
            $projectName = $row['project_name'] ?? 'Unknown Project';
            $projectType = in_array($row['project_type'], ['architecture', 'interior', 'construction']) 
                         ? $row['project_type'] 
                         : 'architecture'; // Default to architecture if invalid
            $clientName = $row['client_name'] ?? 'Unknown Client';
            $stageNumber = $row['project_stage'] ?? 1;
            $stageDate = $row['project_date'] ?? date('Y-m-d');
            $stageNotes = ''; // Default empty stage notes
            $remainingAmount = $row['remaining_amount'] ?? null;
            $totalProjectAmount = null; // No direct mapping in source table
            $createdAt = $row['created_at'] ?? date('Y-m-d H:i:s');
            $updatedAt = $row['updated_at'] ?? date('Y-m-d H:i:s');
            $createdBy = $row['manager_id'] ?? 1; // Default to user ID 1 if not available
            
            // Insert into transactions table
            $transactionInsertStmt->bind_param(
                "isssississsi",
                $projectId,
                $projectName,
                $projectType,
                $clientName,
                $stageNumber,
                $stageDate,
                $stageNotes,
                $remainingAmount,
                $totalProjectAmount,
                $createdAt,
                $updatedAt,
                $createdBy
            );
            
            if (!$transactionInsertStmt->execute()) {
                throw new Exception("Error inserting transaction: " . $transactionInsertStmt->error);
            }
            
            $transactionId = $conn->insert_id;
            
            // Handle payment entries
            if (isset($row['has_multiple_payments']) && $row['has_multiple_payments'] && !empty($row['payment_modes_json'])) {
                // Parse JSON payment data
                $paymentData = json_decode($row['payment_modes_json'], true);
                
                if (is_array($paymentData)) {
                    foreach ($paymentData as $payment) {
                        $paymentDate = $payment['date'] ?? $stageDate;
                        $paymentAmount = $payment['amount'] ?? 0;
                        $paymentMode = $payment['mode'] ?? 'cash';
                        
                        // Validate payment mode
                        if (!in_array($paymentMode, ['cash', 'upi', 'net_banking', 'cheque', 'credit_card'])) {
                            $paymentMode = 'cash'; // Default to cash if invalid
                        }
                        
                        $paymentInsertStmt->bind_param(
                            "isdss",
                            $transactionId,
                            $paymentDate,
                            $paymentAmount,
                            $paymentMode,
                            $createdAt
                        );
                        
                        if (!$paymentInsertStmt->execute()) {
                            throw new Exception("Error inserting payment entry: " . $paymentInsertStmt->error);
                        }
                    }
                }
            } else {
                // Single payment
                $paymentAmount = $row['amount'] ?? 0;
                $paymentMode = $row['payment_mode'] ?? 'cash';
                
                // Validate payment mode
                if (!in_array($paymentMode, ['cash', 'upi', 'net_banking', 'cheque', 'credit_card'])) {
                    $paymentMode = 'cash'; // Default to cash if invalid
                }
                
                $paymentInsertStmt->bind_param(
                    "isdss",
                    $transactionId,
                    $stageDate,
                    $paymentAmount,
                    $paymentMode,
                    $createdAt
                );
                
                if (!$paymentInsertStmt->execute()) {
                    throw new Exception("Error inserting payment entry: " . $paymentInsertStmt->error);
                }
            }
            
            $migratedCount++;
            
            // Print progress
            if ($migratedCount % 10 == 0) {
                echo "Migrated $migratedCount of $totalRecords records...\n";
            }
            
        } catch (Exception $e) {
            echo "Error migrating record ID {$row['id']}: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    // Close statements
    $transactionInsertStmt->close();
    $paymentInsertStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo "\nMigration completed!\n";
    echo "Successfully migrated: $migratedCount records\n";
    echo "Failed to migrate: $errorCount records\n";
    
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    echo "Migration failed: " . $e->getMessage() . "\n";
} finally {
    // Close the connection
    $conn->close();
}

// Close HTML output if running from web
if (!$isCLI) {
    echo "</pre>";
} 