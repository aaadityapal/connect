<?php
/**
 * Check Substage Statuses Utility
 * 
 * This script checks all substages that aren't completed and updates their status
 * to 'completed' if all their associated files are approved.
 * 
 * Can be run manually or via cron job.
 */

// Disable execution time limit for potentially long-running script
set_time_limit(0);

// Load database connection
require_once '../config/db_connect.php';

// Keep track of stats
$stats = [
    'total_checked' => 0,
    'total_updated' => 0,
    'errors' => []
];

// Get all active substages that are not already completed
$substages_query = "SELECT 
                        ps.id as substage_id,
                        ps.title as substage_title,
                        ps.status as substage_status,
                        pstg.title as stage_title,
                        p.title as project_title
                    FROM 
                        project_substages ps
                        JOIN project_stages pstg ON ps.stage_id = pstg.id
                        JOIN projects p ON pstg.project_id = p.id
                    WHERE 
                        ps.status != 'completed' 
                        AND ps.deleted_at IS NULL
                    ORDER BY 
                        p.title, pstg.title, ps.title";

try {
    $stmt = $pdo->prepare($substages_query);
    $stmt->execute();
    
    $substages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Loop through each substage
    foreach ($substages as $substage) {
        $stats['total_checked']++;
        $substage_id = $substage['substage_id'];
        
        try {
            // Check if all files are approved
            $files_query = "SELECT 
                                COUNT(*) as total_files,
                                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_files
                            FROM 
                                substage_files
                            WHERE 
                                substage_id = :substage_id
                                AND deleted_at IS NULL";
            
            $files_stmt = $pdo->prepare($files_query);
            $files_stmt->execute(['substage_id' => $substage_id]);
            $files_result = $files_stmt->fetch(PDO::FETCH_ASSOC);
            
            // If there are files and all are approved
            if ($files_result['total_files'] > 0 && $files_result['total_files'] == $files_result['approved_files']) {
                // Update the substage status
                $update_query = "UPDATE project_substages 
                                SET status = 'completed', 
                                    updated_at = NOW(),
                                    updated_by = NULL
                                WHERE id = :substage_id";
                
                $update_stmt = $pdo->prepare($update_query);
                $update_result = $update_stmt->execute(['substage_id' => $substage_id]);
                
                if ($update_result) {
                    // Record successful update
                    $stats['total_updated']++;
                    
                    // Log the activity
                    $log_query = "INSERT INTO project_activity_log 
                                (project_id, stage_id, substage_id, activity_type, description, performed_at) 
                                SELECT 
                                    ps.project_id,
                                    pss.stage_id,
                                    pss.id,
                                    'substage_status_update',
                                    'Substage automatically marked as completed due to all files being approved (batch update)',
                                    NOW()
                                FROM 
                                    project_substages pss
                                    JOIN project_stages ps ON pss.stage_id = ps.id
                                WHERE 
                                    pss.id = :substage_id";
                    
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute(['substage_id' => $substage_id]);
                    
                    echo "Updated substage: {$substage['project_title']} > {$substage['stage_title']} > {$substage['substage_title']}\n";
                }
            }
        } catch (Exception $e) {
            $stats['errors'][] = "Error processing substage {$substage_id}: " . $e->getMessage();
            echo "Error with substage {$substage_id}: " . $e->getMessage() . "\n";
        }
    }
    
    // Output results
    echo "\n\nProcess completed.\n";
    echo "Total substages checked: {$stats['total_checked']}\n";
    echo "Total substages updated: {$stats['total_updated']}\n";
    
    if (count($stats['errors']) > 0) {
        echo "Errors encountered: " . count($stats['errors']) . "\n";
        foreach ($stats['errors'] as $index => $error) {
            echo ($index + 1) . ". {$error}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
} 