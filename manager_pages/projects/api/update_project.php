<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

require_once '../../../config/db_connect.php';

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function getColumnsMap(PDO $pdo, string $table): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!empty($row['Field'])) {
            $cols[$row['Field']] = true;
        }
    }
    return $cols;
}

function nullableValue($value) {
    if ($value === null) return null;
    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function dateOrNull($value) {
    $text = trim((string)($value ?? ''));
    if ($text === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
        return $text;
    }
    $timestamp = strtotime($text);
    if ($timestamp === false) return null;
    return date('Y-m-d', $timestamp);
}

function updateRow(PDO $pdo, string $table, array $columnsMap, array $data, int $id): void {
    $set = [];
    $params = [];

    foreach ($data as $col => $value) {
        if (!isset($columnsMap[$col])) continue;
        $set[] = "`{$col}` = ?";
        $params[] = $value;
    }

    if (empty($set)) return;

    $params[] = $id;
    $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function insertRow(PDO $pdo, string $table, array $columnsMap, array $data): int {
    $cols = [];
    $vals = [];
    $params = [];

    foreach ($data as $col => $value) {
        if (!isset($columnsMap[$col])) continue;
        $cols[] = "`{$col}`";
        $vals[] = '?';
        $params[] = $value;
    }

    if (empty($cols)) {
        throw new Exception("No valid columns found for insert into {$table}");
    }

    $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

function removeMissingSubstagesForStage(PDO $pdo, string $table, array $columnsMap, int $stageId, array $keepIds, int $actorId): void {
    if ($stageId <= 0 || !isset($columnsMap['stage_id']) || !isset($columnsMap['id'])) {
        return;
    }

    $safeKeepIds = array_values(array_filter(array_map('intval', $keepIds), function ($id) {
        return $id > 0;
    }));

    $where = ['stage_id = ?'];
    $params = [$stageId];

    if (!empty($safeKeepIds)) {
        $placeholders = implode(',', array_fill(0, count($safeKeepIds), '?'));
        $where[] = "id NOT IN ({$placeholders})";
        $params = array_merge($params, $safeKeepIds);
    }

    if (isset($columnsMap['deleted_at'])) {
        $set = ['deleted_at = ?'];
        $setParams = [date('Y-m-d H:i:s')];

        if (isset($columnsMap['deleted_by'])) {
            $set[] = 'deleted_by = ?';
            $setParams[] = $actorId;
        }
        if (isset($columnsMap['updated_by'])) {
            $set[] = 'updated_by = ?';
            $setParams[] = $actorId;
        }
        if (isset($columnsMap['updated_at'])) {
            $set[] = 'updated_at = ?';
            $setParams[] = date('Y-m-d H:i:s');
        }

        $where[] = 'deleted_at IS NULL';
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($setParams, $params));
        return;
    }

    $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new Exception('Invalid JSON payload');
    }

    $project = $payload['project'] ?? null;
    $stages = $payload['stages'] ?? [];

    if (!is_array($project)) {
        throw new Exception('Project payload is required');
    }

    $projectId = isset($project['id']) ? (int)$project['id'] : 0;
    if ($projectId <= 0) {
        throw new Exception('Invalid project id');
    }

    if (!tableExists($pdo, 'projects')) {
        throw new Exception('projects table not found');
    }

    $projectCols = getColumnsMap($pdo, 'projects');

    $pdo->beginTransaction();

    $projectUpdateData = [
        'title' => nullableValue($project['title'] ?? null),
        'description' => nullableValue($project['description'] ?? null),
        'project_type' => nullableValue($project['project_type'] ?? null),
        'status' => nullableValue($project['status'] ?? null),
        'client_name' => nullableValue($project['client_name'] ?? null),
        'assigned_to' => nullableValue($project['assigned_to'] ?? null),
        'assignment_status' => nullableValue($project['assignment_status'] ?? null),
        'created_by' => nullableValue($project['created_by'] ?? null),
        'start_date' => dateOrNull($project['start_date'] ?? null),
        'end_date' => dateOrNull($project['end_date'] ?? null)
    ];

    if (isset($projectCols['updated_by'])) {
        $projectUpdateData['updated_by'] = (int)$_SESSION['user_id'];
    }
    if (isset($projectCols['updated_at'])) {
        $projectUpdateData['updated_at'] = date('Y-m-d H:i:s');
    }

    updateRow($pdo, 'projects', $projectCols, $projectUpdateData, $projectId);

    $hasStagesTable = tableExists($pdo, 'project_stages');
    $substageTable = null;
    if (tableExists($pdo, 'project_susbatges')) {
        $substageTable = 'project_susbatges';
    } elseif (tableExists($pdo, 'project_substages')) {
        $substageTable = 'project_substages';
    }

    $stageCols = $hasStagesTable ? getColumnsMap($pdo, 'project_stages') : [];
    $substageCols = $substageTable ? getColumnsMap($pdo, $substageTable) : [];

    if ($hasStagesTable && is_array($stages)) {
        foreach ($stages as $stageIndex => $stage) {
            if (!is_array($stage)) continue;

            $stageData = [
                'project_id' => $projectId,
                'stage_number' => (int)($stage['stage_number'] ?? ($stageIndex + 1)),
                'status' => nullableValue($stage['status'] ?? null),
                'assigned_to' => nullableValue($stage['assigned_to'] ?? null),
                'assignment_status' => nullableValue($stage['assignment_status'] ?? null),
                'created_by' => nullableValue($stage['created_by'] ?? null),
                'start_date' => dateOrNull($stage['start_date'] ?? null),
                'end_date' => dateOrNull($stage['end_date'] ?? null)
            ];

            if (isset($stageCols['updated_by'])) {
                $stageData['updated_by'] = (int)$_SESSION['user_id'];
            }
            if (isset($stageCols['updated_at'])) {
                $stageData['updated_at'] = date('Y-m-d H:i:s');
            }

            $rawStageId = $stage['id'] ?? null;
            $stageId = 0;
            if (is_numeric($rawStageId) && (int)$rawStageId > 0) {
                $stageId = (int)$rawStageId;
                updateRow($pdo, 'project_stages', $stageCols, $stageData, $stageId);
            } else {
                if (!isset($stageCols['created_by']) || nullableValue($stageData['created_by']) === null) {
                    $stageData['created_by'] = (int)$_SESSION['user_id'];
                }
                if (isset($stageCols['created_at'])) {
                    $stageData['created_at'] = date('Y-m-d H:i:s');
                }
                $stageId = insertRow($pdo, 'project_stages', $stageCols, $stageData);
            }

            if ($substageTable && is_array($stage['substages'] ?? null)) {
                $submittedSubstageIds = [];
                foreach ($stage['substages'] as $subIndex => $substage) {
                    if (!is_array($substage)) continue;

                    $substageData = [
                        'stage_id' => $stageId,
                        'substage_number' => (int)($substage['substage_number'] ?? ($subIndex + 1)),
                        'title' => nullableValue($substage['title'] ?? null),
                        'status' => nullableValue($substage['status'] ?? null),
                        'assigned_to' => nullableValue($substage['assigned_to'] ?? null),
                        'assignment_status' => nullableValue($substage['assignment_status'] ?? null),
                        'created_by' => nullableValue($substage['created_by'] ?? null),
                        'start_date' => dateOrNull($substage['start_date'] ?? null),
                        'end_date' => dateOrNull($substage['end_date'] ?? null),
                        'substage_identifier' => nullableValue($substage['substage_identifier'] ?? null),
                        'drawing_number' => nullableValue($substage['drawing_number'] ?? null)
                    ];

                    if (isset($substageCols['updated_by'])) {
                        $substageData['updated_by'] = (int)$_SESSION['user_id'];
                    }
                    if (isset($substageCols['updated_at'])) {
                        $substageData['updated_at'] = date('Y-m-d H:i:s');
                    }

                    $rawSubstageId = $substage['id'] ?? null;
                    if (is_numeric($rawSubstageId) && (int)$rawSubstageId > 0) {
                        $existingSubstageId = (int)$rawSubstageId;
                        updateRow($pdo, $substageTable, $substageCols, $substageData, $existingSubstageId);
                        $submittedSubstageIds[] = $existingSubstageId;
                    } else {
                        if (!isset($substageCols['created_by']) || nullableValue($substageData['created_by']) === null) {
                            $substageData['created_by'] = (int)$_SESSION['user_id'];
                        }
                        if (isset($substageCols['created_at'])) {
                            $substageData['created_at'] = date('Y-m-d H:i:s');
                        }
                        $newSubstageId = insertRow($pdo, $substageTable, $substageCols, $substageData);
                        $submittedSubstageIds[] = $newSubstageId;
                    }
                }

                removeMissingSubstagesForStage(
                    $pdo,
                    $substageTable,
                    $substageCols,
                    $stageId,
                    $submittedSubstageIds,
                    (int)$_SESSION['user_id']
                );
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Project updated successfully'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update project',
        'error' => $e->getMessage()
    ]);
}
