<?php
date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
    $safeColumn = $pdo->quote($column);
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE {$safeColumn}");
    return $stmt ? (bool)$stmt->fetchColumn() : false;
}

function normalizeDateTimeForClient($value): ?string {
    $raw = trim((string)($value ?? ''));
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return null;
    }

    // Preserve SQL DATETIME exactly as stored (already app-local time)
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $raw)) {
        return strlen($raw) === 16 ? $raw . ':00' : $raw;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw . ' 00:00:00';
    }

    // ISO / timezone-aware fallback -> IST
    try {
        $dt = new DateTime($raw);
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $raw;
    }
}

try {
    $pdo->exec("SET time_zone = '+05:30'");

    $contactColumnSql = 'NULL AS contact_number';
    if (hasColumn($pdo, 'projects', 'contact_number')) {
        $contactColumnSql = 'p.contact_number';
    } elseif (hasColumn($pdo, 'projects', 'contact_numbe')) {
        $contactColumnSql = 'p.contact_numbe AS contact_number';
    }

    $substageTable = null;
    if (tableExists($pdo, 'project_substages')) {
        $substageTable = 'project_substages';
    } elseif (tableExists($pdo, 'project_susbatges')) {
        $substageTable = 'project_susbatges';
    }

    $hasStageFiles = tableExists($pdo, 'stage_files');
    $hasSubstageFiles = tableExists($pdo, 'substage_files');

    // First get projects with basic info
    $query = "SELECT 
        p.id,
        p.title,
        p.description,
        p.project_type,
        p.category_id,
        pc.name as category_name,
        pc.parent_id as category_parent_id,
        p.start_date,
        p.end_date,
        p.assigned_to,
        p.client_name,
        p.client_address,
        p.project_location,
        p.plot_area,
        {$contactColumnSql},
        CASE WHEN p.assigned_to IS NULL THEN 'Unassigned' ELSE u.username END as assigned_to_name
    FROM projects p
    LEFT JOIN project_categories pc ON p.category_id = pc.id
    LEFT JOIN users u ON p.assigned_to = u.id
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC
    LIMIT 500";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log('Projects fetched: ' . json_encode($projects));
    
    // For each project, get its stages and substages
    foreach ($projects as &$project) {
        $project['start_date'] = normalizeDateTimeForClient($project['start_date'] ?? null);
        $project['end_date'] = normalizeDateTimeForClient($project['end_date'] ?? null);

        // Ensure assigned_to is properly formatted
        if ($project['assigned_to'] === null) {
            $project['assigned_to'] = '0';
            $project['assigned_to_name'] = 'Unassigned';
        }
        
        // Get stages
        $stageQuery = "SELECT 
            id,
            stage_number,
            assigned_to,
            CASE WHEN assigned_to IS NULL THEN 'Unassigned' ELSE (SELECT username FROM users WHERE id = assigned_to) END as assigned_to_name,
            start_date,
            end_date,
            status
        FROM project_stages 
        WHERE project_id = :project_id 
        AND deleted_at IS NULL 
        ORDER BY stage_number";
        
        $stageStmt = $pdo->prepare($stageQuery);
        $stageStmt->execute([':project_id' => $project['id']]);
        $stages = $stageStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each stage, get its substages and files
        foreach ($stages as &$stage) {
            $stage['start_date'] = normalizeDateTimeForClient($stage['start_date'] ?? null);
            $stage['end_date'] = normalizeDateTimeForClient($stage['end_date'] ?? null);

            // Ensure assigned_to is properly formatted
            if ($stage['assigned_to'] === null) {
                $stage['assigned_to'] = '0';
                $stage['assigned_to_name'] = 'Unassigned';
            }
            
            // Get substages
            $substages = [];
            if ($substageTable !== null) {
                $substageQuery = "SELECT 
                    id,
                    substage_number,
                    title,
                    assigned_to,
                    CASE WHEN assigned_to IS NULL THEN 'Unassigned' ELSE (SELECT username FROM users WHERE id = assigned_to) END as assigned_to_name,
                    start_date,
                    end_date,
                    status,
                    substage_identifier,
                    drawing_number
                FROM {$substageTable}
                WHERE stage_id = :stage_id 
                AND deleted_at IS NULL 
                ORDER BY substage_number";
                
                $substageStmt = $pdo->prepare($substageQuery);
                $substageStmt->execute([':stage_id' => $stage['id']]);
                $substages = $substageStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Ensure substage assigned_to is properly formatted
            foreach ($substages as &$substage) {
                $substage['start_date'] = normalizeDateTimeForClient($substage['start_date'] ?? null);
                $substage['end_date'] = normalizeDateTimeForClient($substage['end_date'] ?? null);

                if ($substage['assigned_to'] === null) {
                    $substage['assigned_to'] = '0';
                    $substage['assigned_to_name'] = 'Unassigned';
                }
            }
            unset($substage); // Clear reference
            
            $stage['substages'] = $substages;
            
            // Get stage files
            if ($hasStageFiles) {
                $stageFileQuery = "SELECT 
                    id,
                    file_name,
                    file_path,
                    original_name,
                    file_type,
                    file_size
                FROM stage_files 
                WHERE stage_id = :stage_id";
                
                $stageFileStmt = $pdo->prepare($stageFileQuery);
                $stageFileStmt->execute([':stage_id' => $stage['id']]);
                $stage['files'] = $stageFileStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stage['files'] = [];
            }
            
            // Get substage files
            foreach ($stage['substages'] as &$substage) {
                if ($hasSubstageFiles) {
                    $substageFileQuery = "SELECT 
                        id,
                        file_name,
                        file_path,
                        type
                    FROM substage_files 
                    WHERE substage_id = :substage_id 
                    AND deleted_at IS NULL";
                    
                    $substageFileStmt = $pdo->prepare($substageFileQuery);
                    $substageFileStmt->execute([':substage_id' => $substage['id']]);
                    $substage['files'] = $substageFileStmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $substage['files'] = [];
                }
            }
            unset($substage); // Clear reference
        }
        unset($stage); // Clear reference
        
        $project['stages'] = $stages;
    }
    unset($project); // Clear reference
    
    echo json_encode([
        'status' => 'success',
        'data' => $projects
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching projects: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch project suggestions: ' . $e->getMessage()
    ]);
}
exit; 