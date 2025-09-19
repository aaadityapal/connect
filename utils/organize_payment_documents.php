<?php
/**
 * Utility script to organize existing payment documents into the new directory structure
 * Run this script once to migrate existing files to the new organized structure
 */

require_once '../config/db_connect.php';

// Set execution time limit for large migrations
set_time_limit(300); // 5 minutes

echo "<h3>Payment Documents Organization Script</h3>\n";
echo "<p>This script will organize existing payment documents into the new directory structure.</p>\n";
echo "<p><strong>Structure:</strong> uploads/payment_documents/payment_id/recipient_id/</p>\n";

try {
    // Get all payment documents
    $sql = "SELECT 
                pd.document_id,
                pd.recipient_id,
                pd.file_name,
                pd.file_path,
                pd.file_type,
                pr.payment_id
            FROM hr_payment_documents pd
            JOIN hr_payment_recipients pr ON pd.recipient_id = pr.recipient_id
            ORDER BY pr.payment_id, pd.recipient_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($documents) . " documents to process.</p>\n";
    
    $processed = 0;
    $errors = 0;
    $skipped = 0;
    
    foreach ($documents as $doc) {
        $paymentId = $doc['payment_id'];
        $recipientId = $doc['recipient_id'];
        $currentPath = $doc['file_path'];
        $fileName = $doc['file_name'];
        $documentId = $doc['document_id'];
        
        // Check if file is already in organized structure
        if (strpos($currentPath, "payment_{$paymentId}/recipient_{$recipientId}/") !== false) {
            echo "<span style='color: #6c757d;'>✓ Document {$documentId} already organized</span><br>\n";
            $skipped++;
            continue;
        }
        
        // Create new directory structure
        $newDir = "../uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/";
        if (!file_exists($newDir)) {
            mkdir($newDir, 0777, true);
        }
        
        // Determine current file location
        $oldFilePath = '';
        if (file_exists("../uploads/payment_documents/" . $currentPath)) {
            $oldFilePath = "../uploads/payment_documents/" . $currentPath;
        } elseif (file_exists("../" . $currentPath)) {
            $oldFilePath = "../" . $currentPath;
        } elseif (file_exists($currentPath)) {
            $oldFilePath = $currentPath;
        }
        
        if (!$oldFilePath || !file_exists($oldFilePath)) {
            echo "<span style='color: #dc3545;'>✗ Document {$documentId}: File not found at expected locations</span><br>\n";
            $errors++;
            continue;
        }
        
        // Generate new filename with better naming convention
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
        $newFileName = $cleanFileName . '_doc' . $documentId . '.' . $fileExtension;
        $newFilePath = $newDir . $newFileName;
        
        // Move file to new location
        if (copy($oldFilePath, $newFilePath)) {
            // Update database with new path
            $newRelativePath = "uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/{$newFileName}";
            
            $updateSql = "UPDATE hr_payment_documents SET file_path = :new_path WHERE document_id = :document_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':new_path' => $newRelativePath,
                ':document_id' => $documentId
            ]);
            
            // Delete old file
            unlink($oldFilePath);
            
            echo "<span style='color: #28a745;'>✓ Document {$documentId}: Moved to {$newRelativePath}</span><br>\n";
            $processed++;
        } else {
            echo "<span style='color: #dc3545;'>✗ Document {$documentId}: Failed to copy file</span><br>\n";
            $errors++;
        }
    }
    
    // Process split payment documents
    echo "<br><h4>Processing Split Payment Documents</h4>\n";
    
    $splitSql = "SELECT 
                    ps.split_id,
                    ps.recipient_id,
                    ps.proof_file,
                    pr.payment_id
                FROM hr_payment_splits ps
                JOIN hr_payment_recipients pr ON ps.recipient_id = pr.recipient_id
                WHERE ps.proof_file IS NOT NULL AND ps.proof_file != ''
                ORDER BY pr.payment_id, ps.recipient_id";
    
    $splitStmt = $pdo->prepare($splitSql);
    $splitStmt->execute();
    $splitDocs = $splitStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($splitDocs) . " split payment documents to process.</p>\n";
    
    foreach ($splitDocs as $split) {
        $paymentId = $split['payment_id'];
        $recipientId = $split['recipient_id'];
        $splitId = $split['split_id'];
        $currentPath = $split['proof_file'];
        
        // Check if file is already in organized structure
        if (strpos($currentPath, "payment_{$paymentId}/recipient_{$recipientId}/splits/") !== false) {
            echo "<span style='color: #6c757d;'>✓ Split {$splitId} already organized</span><br>\n";
            $skipped++;
            continue;
        }
        
        // Create new directory structure for splits
        $newDir = "../uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/splits/";
        if (!file_exists($newDir)) {
            mkdir($newDir, 0777, true);
        }
        
        // Determine current file location
        $oldFilePath = '';
        if (file_exists("../uploads/payment_documents/" . $currentPath)) {
            $oldFilePath = "../uploads/payment_documents/" . $currentPath;
        } elseif (file_exists("../" . $currentPath)) {
            $oldFilePath = "../" . $currentPath;
        } elseif (file_exists($currentPath)) {
            $oldFilePath = $currentPath;
        }
        
        if (!$oldFilePath || !file_exists($oldFilePath)) {
            echo "<span style='color: #dc3545;'>✗ Split {$splitId}: File not found at expected locations</span><br>\n";
            $errors++;
            continue;
        }
        
        // Generate new filename
        $fileExtension = pathinfo($currentPath, PATHINFO_EXTENSION);
        $newFileName = "split_{$splitId}_proof." . $fileExtension;
        $newFilePath = $newDir . $newFileName;
        
        // Move file to new location
        if (copy($oldFilePath, $newFilePath)) {
            // Update database with new path
            $newRelativePath = "uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/splits/{$newFileName}";
            
            $updateSql = "UPDATE hr_payment_splits SET proof_file = :new_path WHERE split_id = :split_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':new_path' => $newRelativePath,
                ':split_id' => $splitId
            ]);
            
            // Delete old file
            unlink($oldFilePath);
            
            echo "<span style='color: #28a745;'>✓ Split {$splitId}: Moved to {$newRelativePath}</span><br>\n";
            $processed++;
        } else {
            echo "<span style='color: #dc3545;'>✗ Split {$splitId}: Failed to copy file</span><br>\n";
            $errors++;
        }
    }
    
    echo "<br><h4>Migration Summary</h4>\n";
    echo "<p><span style='color: #28a745;'>✓ Processed: {$processed} files</span></p>\n";
    echo "<p><span style='color: #dc3545;'>✗ Errors: {$errors} files</span></p>\n";
    echo "<p><span style='color: #6c757d;'>→ Skipped (already organized): {$skipped} files</span></p>\n";
    echo "<p><strong>Migration completed!</strong></p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: #dc3545;'><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
}