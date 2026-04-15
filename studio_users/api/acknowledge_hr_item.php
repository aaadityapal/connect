<?php
// ============================================
// acknowledge_hr_item.php — Persist HR policy/notice acknowledgements
//                         — Also logs to global_activity_logs
// ============================================

session_start();
header('Content-Type: application/json');
require_once '../../config/db_connect.php';

function ensure_hr_compliance_table_exists(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hr_user_compliance_records (\n"
        . "  record_id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  user_uid VARCHAR(191) NOT NULL,\n"
        . "  document_type VARCHAR(16) NOT NULL,\n"
        . "  document_id INT NOT NULL,\n"
        . "  document_version DATETIME NULL,\n"
        . "  acknowledged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uniq_user_doc (user_uid, document_type, document_id),\n"
        . "  KEY idx_doc (document_type, document_id),\n"
        . "  KEY idx_user (user_uid)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)\n"
        . "FROM information_schema.columns\n"
        . "WHERE table_schema = DATABASE() AND table_name = 'hr_user_compliance_records' AND column_name = :c"
    );
    $stmt->execute(['c' => 'document_version']);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE hr_user_compliance_records ADD COLUMN document_version DATETIME NULL AFTER document_id");
    }
    $stmt->execute(['c' => 'acknowledged_at']);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE hr_user_compliance_records ADD COLUMN acknowledged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    try {
        $pdo->exec("ALTER TABLE hr_user_compliance_records ADD UNIQUE KEY uniq_user_doc (user_uid, document_type, document_id)");
    } catch (Throwable $e) { /* ignore if already exists */ }
}

function ensure_hr_corner_tables_exist(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `hr_policies` (\n"
        . "  `id` INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  `heading` VARCHAR(255) NOT NULL,\n"
        . "  `short_desc` VARCHAR(500) NOT NULL,\n"
        . "  `long_desc` TEXT NOT NULL,\n"
        . "  `created_by` VARCHAR(100) DEFAULT 'HR Admin',\n"
        . "  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  `is_mandatory` TINYINT(1) DEFAULT 1,\n"
        . "  `is_active` TINYINT(1) DEFAULT 1\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `hr_notices` (\n"
        . "  `id` INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  `title` VARCHAR(255) NOT NULL,\n"
        . "  `short_desc` VARCHAR(500) NOT NULL,\n"
        . "  `long_desc` TEXT NOT NULL,\n"
        . "  `attachment` VARCHAR(500) DEFAULT NULL,\n"
        . "  `created_by` VARCHAR(100) DEFAULT 'HR Admin',\n"
        . "  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  `is_mandatory` TINYINT(1) DEFAULT 0,\n"
        . "  `is_active` TINYINT(1) DEFAULT 1\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns\n"
        . "WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c"
    );
    foreach ([
        ['t' => 'hr_policies', 'c' => 'is_mandatory', 'sql' => "ALTER TABLE hr_policies ADD COLUMN is_mandatory TINYINT(1) DEFAULT 1"],
        ['t' => 'hr_policies', 'c' => 'is_active',    'sql' => "ALTER TABLE hr_policies ADD COLUMN is_active TINYINT(1) DEFAULT 1"],
        ['t' => 'hr_notices',  'c' => 'is_mandatory', 'sql' => "ALTER TABLE hr_notices ADD COLUMN is_mandatory TINYINT(1) DEFAULT 0"],
        ['t' => 'hr_notices',  'c' => 'is_active',    'sql' => "ALTER TABLE hr_notices ADD COLUMN is_active TINYINT(1) DEFAULT 1"],
    ] as $m) {
        $stmt->execute(['t' => $m['t'], 'c' => $m['c']]);
        if ((int)$stmt->fetchColumn() === 0) $pdo->exec($m['sql']);
    }
}

function ensure_global_activity_logs(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `global_activity_logs` (\n"
        . "  `id` INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  `user_id` INT NOT NULL,\n"
        . "  `action_type` VARCHAR(100) NOT NULL,\n"
        . "  `entity_type` VARCHAR(100) NOT NULL,\n"
        . "  `entity_id` INT DEFAULT NULL,\n"
        . "  `description` TEXT NOT NULL,\n"
        . "  `metadata` JSON DEFAULT NULL,\n"
        . "  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  `is_read` TINYINT(1) NOT NULL DEFAULT 0,\n"
        . "  KEY `idx_user` (`user_id`),\n"
        . "  KEY `idx_action` (`action_type`),\n"
        . "  KEY `idx_entity` (`entity_type`, `entity_id`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

try {
    global $pdo;
    ensure_hr_compliance_table_exists($pdo);
    ensure_hr_corner_tables_exist($pdo);
    ensure_global_activity_logs($pdo);

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $itemId   = isset($data['item_id'])   ? (int)$data['item_id']            : 0;
    $itemType = isset($data['item_type']) ? trim((string)$data['item_type']) : '';

    if ($itemId <= 0 || ($itemType !== 'policy' && $itemType !== 'notice')) {
        echo json_encode(['success' => false, 'message' => 'Invalid payload']);
        exit;
    }

    $username = trim($_SESSION['username'] ?? '');
    $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $userUid  = $userId > 0 ? (string)$userId : $username;

    if ($userUid === '') {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // ── Fetch item version + label ──────────────────────────
    if ($itemType === 'policy') {
        $stmtV = $pdo->prepare('SELECT updated_at, heading AS label FROM hr_policies WHERE id = ? AND is_active = 1');
    } else {
        $stmtV = $pdo->prepare('SELECT created_at AS updated_at, title AS label FROM hr_notices WHERE id = ? AND is_active = 1');
    }
    $stmtV->execute([$itemId]);
    $row = $stmtV->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Item not found or inactive']);
        exit;
    }

    $version   = $row['updated_at'];
    $itemLabel = $row['label'];

    // ── 1. Upsert hr_user_compliance_records ───────────────
    $stmtAck = $pdo->prepare(
        'INSERT INTO hr_user_compliance_records (user_uid, document_type, document_id, document_version, acknowledged_at) '
        . 'VALUES (:u, :t, :id, :v, NOW()) '
        . 'ON DUPLICATE KEY UPDATE document_version = VALUES(document_version), acknowledged_at = NOW()'
    );
    $stmtAck->execute([
        'u'  => $userUid,
        't'  => $itemType,
        'id' => $itemId,
        'v'  => $version,
    ]);

    // ── 2. Insert into global_activity_logs ────────────────
    if ($userId > 0) {
        $typeLabel   = $itemType === 'policy' ? 'HR Policy' : 'HR Notice';
        $description = "Acknowledged {$typeLabel}: {$itemLabel}";
        $metadata    = json_encode([
            'item_type'  => $itemType,
            'item_id'    => $itemId,
            'item_label' => $itemLabel,
            'version'    => $version,
        ]);

        $stmtLog = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
             VALUES
                (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0)"
        );
        $stmtLog->execute([
            'user_id'     => $userId,
            'action_type' => 'hr_acknowledged',
            'entity_type' => $itemType === 'policy' ? 'hr_policy' : 'hr_notice',
            'entity_id'   => $itemId,
            'description' => $description,
            'metadata'    => $metadata,
        ]);
    }

    echo json_encode(['success' => true, 'label' => $itemLabel]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
