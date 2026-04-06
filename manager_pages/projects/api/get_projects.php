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

function parseIdList($value): array {
    if ($value === null) return [];
    $text = trim((string)$value);
    if ($text === '') return [];

    $parts = preg_split('/\s*,\s*/', $text);
    if (!$parts) return [];

    $ids = [];
    foreach ($parts as $part) {
        $id = (int)$part;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function mapIdsToNames(array $ids, array $userMap): string {
    if (empty($ids)) return '';
    $names = [];
    foreach ($ids as $id) {
        if (isset($userMap[$id]) && trim((string)$userMap[$id]) !== '') {
            $names[] = trim((string)$userMap[$id]);
        } else {
            $names[] = 'User ' . $id;
        }
    }
    return implode(', ', $names);
}

$expectedColumns = [
    'id',
    'title',
    'description',
    'project_type',
    'category_id',
    'start_date',
    'end_date',
    'created_by',
    'assigned_to',
    'status',
    'created_at',
    'updated_at',
    'deleted_at',
    'updated_by',
    'assignment_status',
    'client_name',
    'client_address',
    'project_location',
    'plot_area',
    'contact_numbe'
];

$expectedStageColumns = [
    'id',
    'project_id',
    'stage_number',
    'assigned_to',
    'start_date',
    'end_date',
    'status',
    'created_at',
    'updated_at',
    'deleted_at',
    'updated_by',
    'assignment_status',
    'created_by',
    'deleted_by'
];

$expectedSubstageColumns = [
    'id',
    'stage_id',
    'substage_number',
    'title',
    'assigned_to',
    'start_date',
    'end_date',
    'status',
    'created_at',
    'updated_at',
    'deleted_at',
    'substage_identifier',
    'drawing_number',
    'updated_by',
    'assignment_status',
    'created_by',
    'deleted_by',
    'is_task_created'
];

$expectedSubstageFileColumns = [
    'id',
    'substage_id',
    'file_name',
    'file_path',
    'type',
    'uploaded_by',
    'uploaded_at',
    'status',
    'created_at',
    'updated_at',
    'deleted_at',
    'last_modified_at',
    'last_modified_by',
    'updated_by',
    'sent_to',
    'sent_by',
    'sent_at',
    'download_count',
    'last_downloaded_at',
    'last_downloaded_by'
];

try {
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM projects');
    $available = [];

    foreach ($columnsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (!empty($col['Field'])) {
            $available[$col['Field']] = true;
        }
    }

    $selectParts = [];
    foreach ($expectedColumns as $colName) {
        if (isset($available[$colName])) {
            $selectParts[] = "`{$colName}`";
        } else {
            $selectParts[] = "NULL AS `{$colName}`";
        }
    }

    $selectSql = implode(', ', $selectParts);

    $projectWhere = isset($available['deleted_at']) ? 'WHERE deleted_at IS NULL' : '';
    $projectOrder = [];
    if (isset($available['created_at'])) {
        $projectOrder[] = 'created_at DESC';
    }
    if (isset($available['id'])) {
        $projectOrder[] = 'id DESC';
    }
    $projectOrderSql = !empty($projectOrder) ? ('ORDER BY ' . implode(', ', $projectOrder)) : '';

    $query = "
        SELECT {$selectSql}
        FROM projects
        {$projectWhere}
        {$projectOrderSql}
    ";

    $stmt = $pdo->query($query);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $projectIds = array_values(array_filter(array_map(function ($row) {
        return isset($row['id']) ? (int)$row['id'] : 0;
    }, $projects), function ($id) {
        return $id > 0;
    }));

    $stagesByProjectId = [];
    $substagesByStageId = [];

    if (!empty($projectIds)) {
        $stages = [];
        $hasProjectStages = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote('project_stages'))->fetchColumn();

        if ($hasProjectStages) {
            $stageColumnsStmt = $pdo->query('SHOW COLUMNS FROM project_stages');
            $availableStageColumns = [];

            foreach ($stageColumnsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (!empty($col['Field'])) {
                    $availableStageColumns[$col['Field']] = true;
                }
            }

            $stageSelectParts = [];
            foreach ($expectedStageColumns as $colName) {
                if (isset($availableStageColumns[$colName])) {
                    $stageSelectParts[] = "`{$colName}`";
                } else {
                    $stageSelectParts[] = "NULL AS `{$colName}`";
                }
            }

            $stageSelectSql = implode(', ', $stageSelectParts);
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));

            $stageWhereParts = ["project_id IN ({$placeholders})"];
            if (isset($availableStageColumns['deleted_at'])) {
                $stageWhereParts[] = 'deleted_at IS NULL';
            }
            $stageWhereSql = 'WHERE ' . implode(' AND ', $stageWhereParts);

            $stageOrderParts = ['project_id ASC'];
            if (isset($availableStageColumns['stage_number'])) {
                $stageOrderParts[] = 'stage_number ASC';
            }
            if (isset($availableStageColumns['id'])) {
                $stageOrderParts[] = 'id ASC';
            }
            $stageOrderSql = 'ORDER BY ' . implode(', ', $stageOrderParts);

            $stageQuery = "
                SELECT {$stageSelectSql}
                FROM project_stages
                {$stageWhereSql}
                {$stageOrderSql}
            ";

            $stageStmt = $pdo->prepare($stageQuery);
            $stageStmt->execute($projectIds);
            $stages = $stageStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stageIds = array_values(array_filter(array_map(function ($row) {
            return isset($row['id']) ? (int)$row['id'] : 0;
        }, $stages), function ($id) {
            return $id > 0;
        }));

        if (!empty($stageIds)) {
            $substageTable = null;
            $hasTypoSubstageTable = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote('project_susbatges'))->fetchColumn();
            if ($hasTypoSubstageTable) {
                $substageTable = 'project_susbatges';
            } else {
                $hasSubstageTable = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote('project_substages'))->fetchColumn();
                if ($hasSubstageTable) {
                    $substageTable = 'project_substages';
                }
            }

            if ($substageTable !== null) {
                $substageColumnsStmt = $pdo->query("SHOW COLUMNS FROM {$substageTable}");
                $availableSubstageColumns = [];

                foreach ($substageColumnsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                    if (!empty($col['Field'])) {
                        $availableSubstageColumns[$col['Field']] = true;
                    }
                }

                $substageSelectParts = [];
                foreach ($expectedSubstageColumns as $colName) {
                    if (isset($availableSubstageColumns[$colName])) {
                        $substageSelectParts[] = "`{$colName}`";
                    } else {
                        $substageSelectParts[] = "NULL AS `{$colName}`";
                    }
                }

                $substageSelectSql = implode(', ', $substageSelectParts);
                $substagePlaceholders = implode(',', array_fill(0, count($stageIds), '?'));

                $substageWhereParts = ["stage_id IN ({$substagePlaceholders})"];
                if (isset($availableSubstageColumns['deleted_at'])) {
                    $substageWhereParts[] = 'deleted_at IS NULL';
                }
                $substageWhereSql = 'WHERE ' . implode(' AND ', $substageWhereParts);

                $substageOrderParts = ['stage_id ASC'];
                if (isset($availableSubstageColumns['substage_number'])) {
                    $substageOrderParts[] = 'substage_number ASC';
                }
                if (isset($availableSubstageColumns['id'])) {
                    $substageOrderParts[] = 'id ASC';
                }
                $substageOrderSql = 'ORDER BY ' . implode(', ', $substageOrderParts);

                $substageQuery = "
                    SELECT {$substageSelectSql}
                    FROM {$substageTable}
                    {$substageWhereSql}
                    {$substageOrderSql}
                ";

                $substageStmt = $pdo->prepare($substageQuery);
                $substageStmt->execute($stageIds);
                $substages = $substageStmt->fetchAll(PDO::FETCH_ASSOC);

                $filesBySubstageId = [];
                $substageIds = array_values(array_filter(array_map(function ($row) {
                    return isset($row['id']) ? (int)$row['id'] : 0;
                }, $substages), function ($id) {
                    return $id > 0;
                }));

                if (!empty($substageIds)) {
                    $hasSubstageFilesTable = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote('substage_files'))->fetchColumn();
                    if ($hasSubstageFilesTable) {
                        $substageFileColumnsStmt = $pdo->query('SHOW COLUMNS FROM substage_files');
                        $availableSubstageFileColumns = [];

                        foreach ($substageFileColumnsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                            if (!empty($col['Field'])) {
                                $availableSubstageFileColumns[$col['Field']] = true;
                            }
                        }

                        $substageFileSelectParts = [];
                        foreach ($expectedSubstageFileColumns as $colName) {
                            if (isset($availableSubstageFileColumns[$colName])) {
                                $substageFileSelectParts[] = "`{$colName}`";
                            } else {
                                $substageFileSelectParts[] = "NULL AS `{$colName}`";
                            }
                        }

                        $substageFileSelectSql = implode(', ', $substageFileSelectParts);
                        $substageFilePlaceholders = implode(',', array_fill(0, count($substageIds), '?'));

                        $substageFileWhereParts = ["substage_id IN ({$substageFilePlaceholders})"];
                        if (isset($availableSubstageFileColumns['deleted_at'])) {
                            $substageFileWhereParts[] = 'deleted_at IS NULL';
                        }
                        $substageFileWhereSql = 'WHERE ' . implode(' AND ', $substageFileWhereParts);

                        $substageFileOrderParts = [];
                        if (isset($availableSubstageFileColumns['uploaded_at'])) {
                            $substageFileOrderParts[] = 'uploaded_at DESC';
                        }
                        if (isset($availableSubstageFileColumns['id'])) {
                            $substageFileOrderParts[] = 'id DESC';
                        }
                        $substageFileOrderSql = !empty($substageFileOrderParts)
                            ? ('ORDER BY ' . implode(', ', $substageFileOrderParts))
                            : '';

                        $substageFileQuery = "
                            SELECT {$substageFileSelectSql}
                            FROM substage_files
                            {$substageFileWhereSql}
                            {$substageFileOrderSql}
                        ";

                        $substageFileStmt = $pdo->prepare($substageFileQuery);
                        $substageFileStmt->execute($substageIds);
                        $substageFiles = $substageFileStmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($substageFiles as $fileRow) {
                            $ssid = isset($fileRow['substage_id']) ? (int)$fileRow['substage_id'] : 0;
                            if ($ssid <= 0) continue;
                            if (!isset($filesBySubstageId[$ssid])) {
                                $filesBySubstageId[$ssid] = [];
                            }
                            $filesBySubstageId[$ssid][] = $fileRow;
                        }
                    }
                }

                foreach ($substages as &$substageRow) {
                    $ssid = isset($substageRow['id']) ? (int)$substageRow['id'] : 0;
                    $substageRow['files'] = $filesBySubstageId[$ssid] ?? [];
                }
                unset($substageRow);

                foreach ($substages as $substageRow) {
                    $sid = isset($substageRow['stage_id']) ? (int)$substageRow['stage_id'] : 0;
                    if ($sid <= 0) continue;
                    if (!isset($substagesByStageId[$sid])) {
                        $substagesByStageId[$sid] = [];
                    }
                    $substagesByStageId[$sid][] = $substageRow;
                }
            }
        }

        foreach ($stages as $stageRow) {
            $pid = isset($stageRow['project_id']) ? (int)$stageRow['project_id'] : 0;
            if ($pid <= 0) continue;
            $sid = isset($stageRow['id']) ? (int)$stageRow['id'] : 0;
            $stageRow['substages'] = $substagesByStageId[$sid] ?? [];
            if (!isset($stagesByProjectId[$pid])) {
                $stagesByProjectId[$pid] = [];
            }
            $stagesByProjectId[$pid][] = $stageRow;
        }
    }

    foreach ($projects as &$projectRow) {
        $pid = isset($projectRow['id']) ? (int)$projectRow['id'] : 0;
        $projectRow['stages'] = $stagesByProjectId[$pid] ?? [];
    }
    unset($projectRow);

    // ── Resolve user display names for assignment info ─────────────────────
    $allUserIds = [];

    foreach ($projects as $projectRow) {
        foreach (parseIdList($projectRow['assigned_to'] ?? '') as $uid) {
            $allUserIds[$uid] = true;
        }
        $createdBy = isset($projectRow['created_by']) ? (int)$projectRow['created_by'] : 0;
        if ($createdBy > 0) $allUserIds[$createdBy] = true;

        $stages = $projectRow['stages'] ?? [];
        foreach ($stages as $stageRow) {
            foreach (parseIdList($stageRow['assigned_to'] ?? '') as $uid) {
                $allUserIds[$uid] = true;
            }
            $stageCreatedBy = isset($stageRow['created_by']) ? (int)$stageRow['created_by'] : 0;
            if ($stageCreatedBy > 0) $allUserIds[$stageCreatedBy] = true;

            $substages = $stageRow['substages'] ?? [];
            foreach ($substages as $substageRow) {
                foreach (parseIdList($substageRow['assigned_to'] ?? '') as $uid) {
                    $allUserIds[$uid] = true;
                }
                $substageCreatedBy = isset($substageRow['created_by']) ? (int)$substageRow['created_by'] : 0;
                if ($substageCreatedBy > 0) $allUserIds[$substageCreatedBy] = true;
            }
        }
    }

    $userNameById = [];
    $userIds = array_keys($allUserIds);
    if (!empty($userIds)) {
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $userStmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN ({$userPlaceholders})");
        $userStmt->execute($userIds);
        foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $uid = isset($u['id']) ? (int)$u['id'] : 0;
            if ($uid > 0) {
                $userNameById[$uid] = $u['username'] ?? ('User ' . $uid);
            }
        }
    }

    foreach ($projects as &$projectRow) {
        $projectAssignedIds = parseIdList($projectRow['assigned_to'] ?? '');
        $projectCreatedBy = isset($projectRow['created_by']) ? (int)$projectRow['created_by'] : 0;

        $projectRow['assigned_to_names'] = mapIdsToNames($projectAssignedIds, $userNameById);
        $projectRow['assigned_by_name'] = $projectCreatedBy > 0
            ? ($userNameById[$projectCreatedBy] ?? ('User ' . $projectCreatedBy))
            : '';

        $stages = $projectRow['stages'] ?? [];
        foreach ($stages as &$stageRow) {
            $stageAssignedIds = parseIdList($stageRow['assigned_to'] ?? '');
            $stageCreatedBy = isset($stageRow['created_by']) ? (int)$stageRow['created_by'] : 0;

            $stageRow['assigned_to_names'] = mapIdsToNames($stageAssignedIds, $userNameById);
            $stageRow['assigned_by_name'] = $stageCreatedBy > 0
                ? ($userNameById[$stageCreatedBy] ?? ('User ' . $stageCreatedBy))
                : '';

            $substages = $stageRow['substages'] ?? [];
            foreach ($substages as &$substageRow) {
                $substageAssignedIds = parseIdList($substageRow['assigned_to'] ?? '');
                $substageCreatedBy = isset($substageRow['created_by']) ? (int)$substageRow['created_by'] : 0;

                $substageRow['assigned_to_names'] = mapIdsToNames($substageAssignedIds, $userNameById);
                $substageRow['assigned_by_name'] = $substageCreatedBy > 0
                    ? ($userNameById[$substageCreatedBy] ?? ('User ' . $substageCreatedBy))
                    : '';
            }
            unset($substageRow);

            $stageRow['substages'] = $substages;
        }
        unset($stageRow);

        $projectRow['stages'] = $stages;
    }
    unset($projectRow);

    echo json_encode([
        'success' => true,
        'count' => count($projects),
        'data' => $projects
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch projects',
        'error' => $e->getMessage()
    ]);
}
?>
