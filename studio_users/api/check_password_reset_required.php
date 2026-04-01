<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

const PASSWORD_MAX_AGE_DAYS = 90;

$userId = (int)$_SESSION['user_id'];

function ensureMustChangePasswordColumns(PDO $pdo): void {
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        }

        $col2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_changed_at'")->fetch(PDO::FETCH_ASSOC);
        if (!$col2) {
            $pdo->exec("ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL");
        }
    } catch (Throwable $e) {
        // If permissions prevent ALTER, we still try to query below.
    }
}

try {
    ensureMustChangePasswordColumns($pdo);

    // If core columns still don't exist, do not block the user.
    $hasMustChange = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch(PDO::FETCH_ASSOC);
    $hasChangedAt  = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'password_changed_at'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasMustChange) {
        echo json_encode(['success' => true, 'required' => false]);
        exit();
    }

    $select = "SELECT must_change_password";
    if ($hasChangedAt) {
        $select .= ", password_changed_at";
    }
    $select .= " FROM users WHERE id = ? LIMIT 1";

    $stmt = $pdo->prepare($select);
    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $mustChange = (int)($row['must_change_password'] ?? 0) === 1;

    // Rule 1: Explicit first-login/admin-forced reset
    if ($mustChange) {
        echo json_encode([
            'success' => true,
            'required' => true,
            'reason' => 'must_change_password',
            'max_age_days' => PASSWORD_MAX_AGE_DAYS
        ]);
        exit();
    }

    // Rule 2: Age-based reset every 90 days (if password_changed_at column exists)
    if ($hasChangedAt) {
        $changedAt = $row['password_changed_at'] ?? null;
        if (empty($changedAt)) {
            // Existing users with unknown password-age should be forced once.
            echo json_encode([
                'success' => true,
                'required' => true,
                'reason' => 'missing_password_changed_at',
                'max_age_days' => PASSWORD_MAX_AGE_DAYS
            ]);
            exit();
        }

        $lastChangedTs = strtotime((string)$changedAt);
        if ($lastChangedTs === false) {
            echo json_encode([
                'success' => true,
                'required' => true,
                'reason' => 'invalid_password_changed_at',
                'max_age_days' => PASSWORD_MAX_AGE_DAYS
            ]);
            exit();
        }

        $ageSeconds = time() - $lastChangedTs;
        $maxAgeSeconds = PASSWORD_MAX_AGE_DAYS * 86400;
        if ($ageSeconds >= $maxAgeSeconds) {
            echo json_encode([
                'success' => true,
                'required' => true,
                'reason' => 'age_policy',
                'max_age_days' => PASSWORD_MAX_AGE_DAYS,
                'days_since_change' => (int)floor($ageSeconds / 86400)
            ]);
            exit();
        }
    }

    echo json_encode([
        'success' => true,
        'required' => false,
        'max_age_days' => PASSWORD_MAX_AGE_DAYS
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
