<?php
// ============================================
// acknowledge_hr_item.php — Persist HR policy/notice acknowledgements
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

    // Lightweight migration for older installs
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
    } catch (Throwable $e) {
        // ignore if already exists
    }
}

function ensure_hr_corner_tables_exist(PDO $pdo): void {
    // Keep in sync with get_hr_dashboard_content.php
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

    // Lightweight migrations
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)\n"
        . "FROM information_schema.columns\n"
        . "WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c"
    );

    foreach ([
        ['t' => 'hr_policies', 'c' => 'is_mandatory', 'sql' => "ALTER TABLE hr_policies ADD COLUMN is_mandatory TINYINT(1) DEFAULT 1"],
        ['t' => 'hr_policies', 'c' => 'is_active', 'sql' => "ALTER TABLE hr_policies ADD COLUMN is_active TINYINT(1) DEFAULT 1"],
        ['t' => 'hr_notices', 'c' => 'is_mandatory', 'sql' => "ALTER TABLE hr_notices ADD COLUMN is_mandatory TINYINT(1) DEFAULT 0"],
        ['t' => 'hr_notices', 'c' => 'is_active', 'sql' => "ALTER TABLE hr_notices ADD COLUMN is_active TINYINT(1) DEFAULT 1"],
    ] as $m) {
        $stmt->execute(['t' => $m['t'], 'c' => $m['c']]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec($m['sql']);
        }
    }
}

try {
    global $pdo;
    ensure_hr_compliance_table_exists($pdo);
    ensure_hr_corner_tables_exist($pdo);

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;
    $itemType = isset($data['item_type']) ? trim((string)$data['item_type']) : '';

    if ($itemId <= 0 || ($itemType !== 'policy' && $itemType !== 'notice')) {
        echo json_encode(['success' => false, 'message' => 'Invalid payload']);
        exit;
    }

    $username = trim($_SESSION['username'] ?? '');
    $userId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : '';
    $userUid = $userId !== '' ? $userId : $username;

    if ($userUid === '') {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // Version marker: policies use updated_at; notices use created_at.
    if ($itemType === 'policy') {
        $stmtV = $pdo->prepare('SELECT updated_at FROM hr_policies WHERE id = ? AND is_active = 1');
        $stmtV->execute([$itemId]);
    } else {
        $stmtV = $pdo->prepare('SELECT created_at AS updated_at FROM hr_notices WHERE id = ? AND is_active = 1');
        $stmtV->execute([$itemId]);
    }

    $version = $stmtV->fetchColumn();
    if (!$version) {
        echo json_encode(['success' => false, 'message' => 'Item not found or inactive']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO hr_user_compliance_records (user_uid, document_type, document_id, document_version, acknowledged_at) '
        . 'VALUES (:u, :t, :id, :v, NOW()) '
        . 'ON DUPLICATE KEY UPDATE document_version = VALUES(document_version), acknowledged_at = NOW()'
    );

    $stmt->execute([
        'u' => $userUid,
        't' => $itemType,
        'id' => $itemId,
        'v' => $version,
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
