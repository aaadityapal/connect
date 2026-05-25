<?php
/**
 * DIAGNOSTIC FILE — _diag_sequence.php
 * Deploy to production, open in browser, DELETE AFTER USE.
 * URL: https://architectshive.com/admin/whatsapp_loop_new/api/_diag_sequence.php
 */

// ── Security: simple token check so random people can't run this ──
// Change "archweb2026" to whatever you like, pass as ?token=archweb2026
$secret = 'archweb2026';
if (($_GET['token'] ?? '') !== $secret) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:red">403 — Missing or wrong ?token=</h2>');
}

header('Content-Type: text/html; charset=utf-8');

function ok($msg)  { echo "<tr><td>$msg</td><td style='color:green'>✅ OK</td></tr>"; }
function err($msg) { echo "<tr><td>$msg</td><td style='color:red'>❌ FAIL</td></tr>"; }
function info($label, $val) { echo "<tr><td>$label</td><td><code>" . htmlspecialchars($val) . "</code></td></tr>"; }

echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<title>Sequence Diagnostic</title>
<style>
  body { font-family: monospace; background:#0d1117; color:#c9d1d9; padding:20px; }
  h2   { color:#58a6ff; }
  table{ border-collapse:collapse; width:100%; margin-bottom:30px; }
  th   { background:#161b22; color:#8b949e; text-align:left; padding:8px 12px; }
  td   { border-bottom:1px solid #21262d; padding:8px 12px; }
  pre  { background:#161b22; padding:12px; border-radius:6px; overflow-x:auto; color:#a5d6ff; }
  .section { background:#161b22; border-radius:8px; padding:16px; margin-bottom:20px; }
</style>
</head>
<body>
<h2>🔍 WhatsApp Loop — Sequence Save Diagnostic</h2>
HTML;

// ── 1. PHP ENVIRONMENT ──────────────────────────────────────────────────────
echo '<div class="section"><h3>1. PHP Environment</h3><table><tr><th>Check</th><th>Result</th></tr>';
info('PHP Version', PHP_VERSION);
info('Server Software', $_SERVER['SERVER_SOFTWARE'] ?? 'N/A');
info('Script path', __FILE__);
info('Document Root', $_SERVER['DOCUMENT_ROOT'] ?? 'N/A');
extension_loaded('pdo')        ? ok('PDO extension') : err('PDO extension MISSING');
extension_loaded('pdo_mysql')  ? ok('PDO MySQL driver') : err('PDO MySQL driver MISSING');
extension_loaded('json')       ? ok('JSON extension') : err('JSON extension MISSING');
echo '</table></div>';

// ── 2. CONFIG FILE ──────────────────────────────────────────────────────────
echo '<div class="section"><h3>2. Config File</h3><table><tr><th>Check</th><th>Result</th></tr>';
$config_path = __DIR__ . '/../../../config/db_connect.php';
if (file_exists($config_path)) {
    ok('config.php found at ' . realpath($config_path));
    require_once $config_path;
    defined('DB_HOST') ? ok('DB_HOST defined: ' . DB_HOST) : err('DB_HOST not defined');
    defined('DB_NAME') ? ok('DB_NAME defined: ' . DB_NAME) : err('DB_NAME not defined');
    defined('DB_USER') ? ok('DB_USER defined: ' . DB_USER) : err('DB_USER not defined');
    defined('DB_PASS') ? ok('DB_PASS defined (length: ' . strlen(DB_PASS) . ')') : err('DB_PASS not defined');
} else {
    err('config.php NOT FOUND at: ' . $config_path);
}
echo '</table></div>';

// ── 3. DATABASE CONNECTION ──────────────────────────────────────────────────
echo '<div class="section"><h3>3. Database Connection</h3><table><tr><th>Check</th><th>Result</th></tr>';
$conn = null;
if (defined('DB_HOST')) {
    $conn = $pdo;
} else {
    err('Skipped — constants not defined');
}
echo '</table></div>';

// ── 4. TABLE STRUCTURE ──────────────────────────────────────────────────────
if ($conn) {
    $tables = ['sequences', 'sequence_steps', 'templates'];
    foreach ($tables as $tbl) {
        echo "<div class='section'><h3>4. Table: <code>$tbl</code></h3>";
        try {
            $rows = $conn->query("DESCRIBE `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
            echo '<table><tr>';
            foreach (array_keys($rows[0]) as $col) echo "<th>$col</th>";
            echo '</tr>';
            foreach ($rows as $r) {
                echo '<tr>';
                foreach ($r as $v) echo '<td>' . htmlspecialchars((string)$v) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } catch (PDOException $e) {
            echo "<p style='color:red'>❌ Table missing or error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        echo '</div>';
    }

    // ── 5. SPECIFIC COLUMN CHECKS ──────────────────────────────────────────
    echo '<div class="section"><h3>5. Required Column Checks</h3><table><tr><th>Column</th><th>Status</th></tr>';
    $required = [
        ['sequences',      'stop_on_reply'],
        ['sequence_steps', 'template_language'],
        ['sequence_steps', 'header_type'],
        ['sequence_steps', 'media_path'],
        ['sequence_steps', 'media_filename'],
        ['templates',      'language'],
    ];
    foreach ($required as [$tbl, $col]) {
        try {
            $conn->query("SELECT `$col` FROM `$tbl` LIMIT 1");
            ok("`$tbl`.`$col`");
        } catch (PDOException $e) {
            err("`$tbl`.`$col` — MISSING: " . htmlspecialchars($e->getMessage()));
        }
    }
    echo '</table>';

    // ── 6. GENERATE MISSING ALTER STATEMENTS ──────────────────────────────
    echo '<h3>6. ALTER Statements for Missing Columns</h3><pre>';
    $alters = [];
    $checks = [
        ['sequences',      'stop_on_reply',     "ALTER TABLE `sequences` ADD COLUMN `stop_on_reply` BOOLEAN DEFAULT TRUE AFTER `is_persistent`;"],
        ['sequence_steps', 'template_language', "ALTER TABLE `sequence_steps` ADD COLUMN `template_language` VARCHAR(20) DEFAULT 'en_US' AFTER `template_name`;"],
        ['sequence_steps', 'header_type',       "ALTER TABLE `sequence_steps` ADD COLUMN `header_type` VARCHAR(50) DEFAULT 'NONE' AFTER `delay_unit`;"],
        ['sequence_steps', 'media_path',        "ALTER TABLE `sequence_steps` ADD COLUMN `media_path` VARCHAR(255) DEFAULT NULL AFTER `header_type`;"],
        ['sequence_steps', 'media_filename',    "ALTER TABLE `sequence_steps` ADD COLUMN `media_filename` VARCHAR(255) DEFAULT NULL AFTER `media_path`;"],
        ['templates',      'language',          "ALTER TABLE `templates` ADD COLUMN `language` VARCHAR(20) DEFAULT 'en_US' AFTER `category`;"],
    ];
    $any_missing = false;
    foreach ($checks as [$tbl, $col, $sql]) {
        try {
            $conn->query("SELECT `$col` FROM `$tbl` LIMIT 1");
        } catch (PDOException $e) {
            echo htmlspecialchars($sql) . "\n";
            $any_missing = true;
        }
    }
    if (!$any_missing) echo "-- ✅ All required columns exist. Nothing to alter.";
    echo '</pre></div>';
}

// ── 7. UPLOAD DIRECTORY ────────────────────────────────────────────────────
echo '<div class="section"><h3>7. Upload Directory</h3><table><tr><th>Check</th><th>Result</th></tr>';
$upload_dir = __DIR__ . '/../uploads/sequence_media/';
if (is_dir($upload_dir)) {
    ok('Directory exists: ' . realpath($upload_dir));
    is_writable($upload_dir) ? ok('Directory is writable') : err('Directory NOT writable — fix permissions (chmod 755)');
} else {
    err('Directory MISSING: ' . $upload_dir);
    echo "<tr><td colspan=2>Run: <code>mkdir -p " . htmlspecialchars($upload_dir) . " && chmod 755 " . htmlspecialchars($upload_dir) . "</code></td></tr>";
}
echo '</table></div>';

// ── 8. TEST INSERT SIMULATION ──────────────────────────────────────────────
if ($conn && isset($_GET['test_insert'])) {
    echo '<div class="section"><h3>8. Test INSERT into sequences</h3>';
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO sequences (name, description, is_persistent, stop_on_reply) VALUES (?, ?, ?, ?)");
        $stmt->execute(['__DIAG_TEST__', 'Diagnostic test row', 1, 1]);
        $test_id = $conn->lastInsertId();
        $conn->rollBack();
        echo "<p style='color:green'>✅ INSERT into sequences succeeded (id=$test_id, rolled back)</p>";
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo "<p style='color:red'>❌ INSERT FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo '</div>';
} else {
    echo '<div class="section"><h3>8. Test INSERT</h3>';
    echo '<p>Add <code>&amp;test_insert=1</code> to the URL to run a live INSERT test (auto rolled back).</p>';
    echo '</div>';
}

// ── 9. PHP ERROR LOG ───────────────────────────────────────────────────────
echo '<div class="section"><h3>9. PHP Error Log (last 30 lines)</h3>';
$log_paths = [
    ini_get('error_log'),
    __DIR__ . '/../../../../logs/php_error.log',
    __DIR__ . '/../../../logs/error_log',
    '/home/' . get_current_user() . '/logs/php_error.log',
    '/tmp/php_errors.log',
];
$log_found = false;
foreach ($log_paths as $lp) {
    if ($lp && file_exists($lp) && is_readable($lp)) {
        echo "<p>Reading: <code>" . htmlspecialchars($lp) . "</code></p>";
        $lines = file($lp);
        $tail  = array_slice($lines, -30);
        // filter to only show lines containing 'save_sequence' or 'PHP Fatal' or 'PHP Parse'
        $relevant = array_filter($tail, fn($l) =>
            stripos($l, 'save_sequence') !== false ||
            stripos($l, 'Fatal') !== false ||
            stripos($l, 'Parse error') !== false ||
            stripos($l, 'Uncaught') !== false ||
            stripos($l, 'sequence') !== false
        );
        if (empty($relevant)) {
            echo "<pre>" . htmlspecialchars(implode('', $tail)) . "</pre>";
        } else {
            echo "<pre style='color:#ff7b72'>" . htmlspecialchars(implode('', $relevant)) . "</pre>";
        }
        $log_found = true;
        break;
    }
}
if (!$log_found) {
    echo "<p style='color:orange'>⚠️ Could not find PHP error log. Check cPanel → Error Logs, or add <code>ini_set('error_log', '/path/to/error.log');</code> to config.php</p>";
    echo "<p>PHP error_log ini value: <code>" . htmlspecialchars(ini_get('error_log') ?: '(empty)') . "</code></p>";
}
echo '</div>';

// ── 10. LIVE SAVE SIMULATION (POST) ────────────────────────────────────────
if ($conn && isset($_GET['test_save'])) {
    echo '<div class="section"><h3>10. Live Save Simulation</h3>';
    // Simulate exactly what save_sequence.php does with minimal data
    $errors = [];
    try {
        $conn->beginTransaction();

        // Step 1: INSERT into sequences
        $stmt = $conn->prepare("INSERT INTO sequences (name, description, is_persistent, stop_on_reply) VALUES (?, ?, ?, ?)");
        $stmt->execute(['__SIM_TEST__', 'Sim test', 1, 0]);
        $sim_id = $conn->lastInsertId();
        echo "<p style='color:green'>✅ Step 1: sequences INSERT ok (id=$sim_id)</p>";

        // Step 2: INSERT into sequence_steps (all columns the real save uses)
        $stmt2 = $conn->prepare("
            INSERT INTO sequence_steps
                (sequence_id, template_id, template_name, step_order, delay_value, delay_unit, header_type, media_path, media_filename, template_language)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt2->execute([$sim_id, NULL, '__test_template__', 1, 1, 'days', 'NONE', NULL, NULL, 'en_US']);
        echo "<p style='color:green'>✅ Step 2: sequence_steps INSERT ok</p>";

        $conn->rollBack();
        echo "<p style='color:green'>✅ All steps passed — rolled back cleanly. DB is NOT the issue.</p>";
        echo "<p style='color:orange'>⚠️ The 500 error must be coming from PHP code logic (not DB). Check section 9 error log above.</p>";

    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo "<p style='color:red'>❌ FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo '</div>';
} else {
    echo '<div class="section"><h3>10. Live Save Simulation (Full)</h3>';
    echo '<p>Add <code>&amp;test_save=1</code> to the URL to simulate the exact INSERT sequence that <code>save_sequence.php</code> performs. Rolled back automatically.</p>';
    echo '</div>';
}

echo '<p style="color:#8b949e;font-size:12px">⚠️ Delete this file from production after use.</p>';
echo '</body></html>';

