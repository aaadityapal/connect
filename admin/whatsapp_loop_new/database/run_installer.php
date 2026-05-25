<?php
// Usage:
// php run_installer.php --host=127.0.0.1 --user=root --pass= --db=archweb_db

$opts = getopt('', ['host::','user::','pass::','db::','master::']);
$host = $opts['host'] ?? '127.0.0.1';
$user = $opts['user'] ?? 'root';
$pass = $opts['pass'] ?? '';
$db   = $opts['db']   ?? 'archweb_db';
$masterFile = $opts['master'] ?? __DIR__ . DIRECTORY_SEPARATOR . 'whatsapp_loop_master.sql';

if (!file_exists($masterFile)) {
    fwrite(STDERR, "Master file not found: $masterFile\n");
    exit(2);
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n");
    exit(3);
}
$mysqli->set_charset('utf8mb4');

echo "Running master installer: $masterFile\n";
$master = file($masterFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$sources = [];
foreach ($master as $line) {
    $line = trim($line);
    if (stripos($line, 'SOURCE ') === 0) {
        $src = trim(substr($line, 6));
        // allow quoted or unquoted
        $src = trim($src, " \t'\";");
        // resolve relative paths: treat as absolute if starts with '/' (Unix) or drive letter 'C:' (Windows)
        $isAbsolute = (substr($src, 0, 1) === '/') || (strlen($src) > 1 && ctype_alpha($src[0]) && $src[1] === ':');
        if (!$isAbsolute) {
            $src = dirname($masterFile) . DIRECTORY_SEPARATOR . $src;
        }
        $sources[] = $src;
    }
}

if (empty($sources)) {
    fwrite(STDERR, "No SOURCE entries found in master file.\n");
    exit(4);
}

// disable foreign keys
if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=0')) {
    fwrite(STDERR, "Failed to disable foreign key checks: {$mysqli->error}\n");
}

foreach ($sources as $src) {
    echo "\n-- Importing: $src\n";
    if (!file_exists($src)) {
        fwrite(STDERR, "File not found: $src\n");
        continue;
    }
    $sql = file_get_contents($src);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read $src\n");
        continue;
    }
    // skip empty files
    $sql = trim($sql);
    if ($sql === '') {
        echo "Skipping empty $src\n";
        continue;
    }

    // Execute as multi query
    if (!$mysqli->multi_query($sql)) {
        fwrite(STDERR, "Error executing $src: {$mysqli->error}\n");
        // try to continue
        while ($mysqli->more_results() && $mysqli->next_result()) { /* flush */ }
        continue;
    }
    // flush results
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    echo "Imported $src\n";
}

// re-enable foreign keys
if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=1')) {
    fwrite(STDERR, "Failed to enable foreign key checks: {$mysqli->error}\n");
}

echo "\nDone.\n";
$mysqli->close();
return 0;
