<?php
// ============================================
// get_hr_dashboard_content.php — Fetch latest HR content for dashboard
// ============================================
session_start();
header('Content-Type: application/json');
require_once '../../config/db_connect.php';

function ensure_hr_compliance_table_exists(PDO $pdo): void {
    // Stores per-user acknowledgement with a version marker so a policy can require re-ack on update.
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

    // Lightweight migration for older installs (CREATE TABLE IF NOT EXISTS won't add columns)
    if (!column_exists($pdo, 'hr_user_compliance_records', 'document_version')) {
        $pdo->exec("ALTER TABLE hr_user_compliance_records ADD COLUMN document_version DATETIME NULL AFTER document_id");
    }
    if (!column_exists($pdo, 'hr_user_compliance_records', 'acknowledged_at')) {
        $pdo->exec("ALTER TABLE hr_user_compliance_records ADD COLUMN acknowledged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    // Ensure unique index exists
    try {
        $pdo->exec("ALTER TABLE hr_user_compliance_records ADD UNIQUE KEY uniq_user_doc (user_uid, document_type, document_id)");
    } catch (Throwable $e) {
        // ignore if already exists
    }
}

function ensure_hr_corner_tables_exist(PDO $pdo): void {
    // Note: Avoid `SHOW TABLES LIKE ?` placeholders; some MariaDB/PDO combos choke on it.
    // Creating with IF NOT EXISTS is safe and fast.
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

    // Lightweight migrations for older installs (CREATE TABLE IF NOT EXISTS won't add columns)
    if (!column_exists($pdo, 'hr_policies', 'is_mandatory')) {
        $pdo->exec("ALTER TABLE hr_policies ADD COLUMN is_mandatory TINYINT(1) DEFAULT 1");
    }
    if (!column_exists($pdo, 'hr_policies', 'is_active')) {
        $pdo->exec("ALTER TABLE hr_policies ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    }

    if (!column_exists($pdo, 'hr_notices', 'is_mandatory')) {
        $pdo->exec("ALTER TABLE hr_notices ADD COLUMN is_mandatory TINYINT(1) DEFAULT 0");
    }
    if (!column_exists($pdo, 'hr_notices', 'is_active')) {
        $pdo->exec("ALTER TABLE hr_notices ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    }

    // Seed a default policy so HR Corner is never empty
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM hr_policies")->fetchColumn();
        if ($count === 0) {
            if (column_exists($pdo, 'hr_policies', 'is_mandatory') && column_exists($pdo, 'hr_policies', 'is_active')) {
                $stmt = $pdo->prepare(
                    "INSERT INTO hr_policies (heading, short_desc, long_desc, is_mandatory, is_active) VALUES (?, ?, ?, 1, 1)"
                );
                $stmt->execute([
                    'Welcome to HR Corner',
                    'This section contains all company policies and guidelines.',
                    'Please check back regularly for the latest updates from the HR team. New policies will appear here as soon as they are published.'
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO hr_policies (heading, short_desc, long_desc) VALUES (?, ?, ?)"
                );
                $stmt->execute([
                    'Welcome to HR Corner',
                    'This section contains all company policies and guidelines.',
                    'Please check back regularly for the latest updates from the HR team. New policies will appear here as soon as they are published.'
                ]);
            }
        }
    } catch (Throwable $e) {
        // If the table exists but has a different schema, don't hard-fail here.
    }
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)\n"
        . "FROM information_schema.columns\n"
        . "WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c"
    );
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    global $pdo;

    ensure_hr_compliance_table_exists($pdo);
    ensure_hr_corner_tables_exist($pdo);

    // Get current logged-in user and sanitize
    $username = trim($_SESSION['username'] ?? '');
    $userId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : '';
    $userUid = $userId !== '' ? $userId : $username;

    if ($userUid === '') {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $policyHasMandatory = column_exists($pdo, 'hr_policies', 'is_mandatory');
    $noticeHasMandatory = column_exists($pdo, 'hr_notices', 'is_mandatory');

    // Fetch latest 10 active policies, joining with the fresh unique table (using case-insensitive comparison)
    $policyMandatorySelect = $policyHasMandatory ? 'p.is_mandatory' : '1 as is_mandatory';
    $stmtP = $pdo->prepare("
        SELECT p.id, p.heading, p.short_desc, p.long_desc, {$policyMandatorySelect}, p.updated_at,
               (CASE
                    WHEN a.record_id IS NOT NULL AND a.document_version = p.updated_at THEN 1
                    ELSE 0
                END) as is_acknowledged
        FROM hr_policies p
        LEFT JOIN hr_user_compliance_records a
            ON a.document_id = p.id
           AND a.document_type = 'policy'
           AND (
                a.user_uid = :uid
                OR (:uname1 <> '' AND LOWER(a.user_uid) = LOWER(:uname2))
           )
        WHERE p.is_active = 1
        ORDER BY p.updated_at DESC
        LIMIT 10
    ");
    $stmtP->execute(['uid' => $userUid, 'uname1' => $username, 'uname2' => $username]);
    $policies = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Fetch latest 10 active notices, joining similarly
    $noticeMandatorySelect = $noticeHasMandatory ? 'n.is_mandatory' : '1 as is_mandatory';
    $stmtN = $pdo->prepare("
        SELECT n.id, n.title, n.short_desc, n.long_desc, n.attachment, {$noticeMandatorySelect}, n.created_at,
               (CASE
                    WHEN a.record_id IS NOT NULL AND a.document_version = n.created_at THEN 1
                    ELSE 0
                END) as is_acknowledged
        FROM hr_notices n
        LEFT JOIN hr_user_compliance_records a
            ON a.document_id = n.id
           AND a.document_type = 'notice'
           AND (
                a.user_uid = :uid
                OR (:uname1 <> '' AND LOWER(a.user_uid) = LOWER(:uname2))
           )
        WHERE n.is_active = 1
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmtN->execute(['uid' => $userUid, 'uname1' => $username, 'uname2' => $username]);
    $notices = $stmtN->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'policies' => $policies,
        'notices' => $notices
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
