<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (empty($data['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: name']);
    exit;
}

$media_upload_dir = __DIR__ . '/../uploads/master_loop_media/';
$media_web_path   = 'uploads/master_loop_media/';
if (!is_dir($media_upload_dir)) {
    @mkdir($media_upload_dir, 0755, true);
}

$client_id = isset($data['id']) ? (int)$data['id'] : 0;
$is_new    = ($client_id === 0 || $client_id > 1000000000);
$name      = trim($data['name']);
$status    = trim($data['status'] ?? 'draft');
$steps     = isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [];

try {
    $conn->beginTransaction();

    if ($is_new) {
        $stmt = $conn->prepare('
            INSERT INTO master_loops (name, status, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ');
        $stmt->execute([$name, $status]);
        $loop_id = (int)$conn->lastInsertId();
    } else {
        $loop_id = $client_id;
        $stmt = $conn->prepare('
            UPDATE master_loops
               SET name = ?, status = ?, updated_at = NOW()
             WHERE id = ?
        ');
        $stmt->execute([$name, $status, $loop_id]);
    }

    $conn->prepare('DELETE FROM master_loop_steps WHERE master_loop_id = ?')->execute([$loop_id]);

    if (!empty($steps)) {
        $ins = $conn->prepare('
            INSERT INTO master_loop_steps
                (master_loop_id, step_order, template_key, header_type,
                 delay_value, delay_unit, media_path, media_filename)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($steps as $idx => $step) {
            $template_key  = trim($step['template_key'] ?? '');
            $header_type   = trim($step['header_type'] ?? 'NONE');
            $delay_value   = (int)($step['delay_value'] ?? 0);
            $delay_unit    = trim($step['delay_unit'] ?? 'days');
            $media_data    = $step['media_data'] ?? '';
            $media_filename= trim($step['media_filename'] ?? '');
            $media_path    = trim($step['media_path'] ?? '');

            if ($media_data !== '' && strpos($media_data, 'data:') === 0) {
                $base64_pos = strpos($media_data, ';base64,');
                if ($base64_pos !== false) {
                    $mime_full  = substr($media_data, 5, $base64_pos - 5);
                    $file_bytes = base64_decode(substr($media_data, $base64_pos + 8));
                    $mime_parts = explode(';', $mime_full);
                    $mime       = $mime_parts[0];
                    $ext        = strtolower(substr(strrchr($mime, '/'), 1));
                    if ($ext === 'jpeg') $ext = 'jpg';
                    if (strpos($ext, 'wordprocessingml') !== false) $ext = 'docx';

                    $safe_name   = preg_replace('/[^a-z0-9_\-\.]/i', '_', $media_filename ?: 'file');
                    $unique_name = 'master_loop_' . $loop_id . '_step_' . ($idx + 1) . '_' . time() . '_' . $safe_name;
                    $save_path   = $media_upload_dir . $unique_name;

                    if (file_put_contents($save_path, $file_bytes) !== false) {
                        $media_path = $media_web_path . $unique_name;
                    }
                }
            }

            $ins->execute([
                $loop_id,
                $idx + 1,
                $template_key,
                $header_type,
                $delay_value,
                $delay_unit,
                $media_path ?: null,
                $media_filename ?: null,
            ]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'id' => $loop_id]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
