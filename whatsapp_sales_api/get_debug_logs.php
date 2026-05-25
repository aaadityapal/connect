<?php
// whatsapp_sales_api/get_debug_logs.php
// Simple JSON endpoint to fetch and search the WhatsApp sales debug log.
header('Content-Type: application/json; charset=UTF-8');

// Allow local UI access (adjust for production as needed)
header('Access-Control-Allow-Origin: *');

// Path to the debug log (same as logSalesDebug in helper.php)
$logFile = __DIR__ . '/../logs/whatsapp_sales_debug.log';

// Simple tail implementation: read last N lines efficiently
function tailFileLines($filepath, $lines = 200)
{
    if (!is_readable($filepath)) return [];

    $f = fopen($filepath, 'rb');
    if (!$f) return [];

    $buffer = '';
    $chunkSize = 4096;
    $pos = -1;
    $lineCount = 0;

    fseek($f, 0, SEEK_END);
    $fileSize = ftell($f);

    while ($lineCount < $lines && ftell($f) > 0) {
        $seek = max(-$chunkSize, -ftell($f));
        fseek($f, $seek, SEEK_CUR);
        $chunk = fread($f, abs($seek));
        $buffer = $chunk . $buffer;
        $lineCount = substr_count($buffer, PHP_EOL);
        if (ftell($f) === 0) break;
        fseek($f, -strlen($chunk), SEEK_CUR);
    }

    fclose($f);

    $linesArr = preg_split('/\r?\n/', trim($buffer));
    if (count($linesArr) > $lines) {
        $linesArr = array_slice($linesArr, -$lines);
    }
    return $linesArr;
}

// Read input params
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 200;
if ($lines <= 0) $lines = 200;

if (!file_exists($logFile)) {
    echo json_encode(['success' => false, 'error' => 'Log file not found', 'path' => $logFile]);
    exit;
}

$allLines = tailFileLines($logFile, $lines);

if ($q !== '') {
    $qLower = mb_strtolower($q);
    $filtered = array_filter($allLines, function($l) use ($qLower) {
        return mb_stripos($l, $qLower) !== false;
    });
    $resultLines = array_values($filtered);
} else {
    $resultLines = $allLines;
}

echo json_encode([
    'success' => true,
    'path' => $logFile,
    'lines_requested' => $lines,
    'query' => $q,
    'returned' => count($resultLines),
    'lines' => $resultLines
]);

?>
