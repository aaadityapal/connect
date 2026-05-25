<?php
/**
 * save_sequence.php — Clean production-ready version
 * Saves a sequence (header) + its steps to the DB.
 */
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');

// ── 1. CONFIG ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../../config/db_connect.php';

// ── 2. DB CONNECTION ──────────────────────────────────────────────────────────
$conn = $pdo;

// ── 3. PARSE REQUEST BODY ─────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (empty($data['title'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: title']);
    exit;
}

// ── 4. MEDIA UPLOAD DIRECTORY ─────────────────────────────────────────────────
$media_upload_dir = __DIR__ . '/../uploads/sequence_media/';
$media_web_path   = 'uploads/sequence_media/';
if (!is_dir($media_upload_dir)) {
    @mkdir($media_upload_dir, 0755, true);
}

// ── 5. EXTRACT FIELDS ─────────────────────────────────────────────────────────
// If the client sends a JS Date.now() as the ID (> 1 trillion), treat it as new.
$client_id   = isset($data['id']) ? (int)$data['id'] : 0;
$is_new      = ($client_id === 0 || $client_id > 1000000000);
$title       = trim($data['title']);
$desc        = trim($data['desc']      ?? '');
$persist     = !empty($data['persistOnReply']) ? 1 : 0;
$stop_reply  = !empty($data['stopOnReply'])    ? 1 : 0;
$steps       = isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [];

// ── 6. SAVE ───────────────────────────────────────────────────────────────────
try {
    $conn->beginTransaction();

    // 6a. Upsert the sequence header
    if ($is_new) {
        $stmt = $conn->prepare('
            INSERT INTO sequences
                (name, description, is_persistent, stop_on_reply, status, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $stmt->execute([$title, $desc, $persist, $stop_reply, 'Active']);
        $seq_id = (int)$conn->lastInsertId();
    } else {
        $seq_id = $client_id;
        $stmt = $conn->prepare('
            UPDATE sequences
               SET name = ?, description = ?, is_persistent = ?, stop_on_reply = ?, updated_at = NOW()
             WHERE id = ?
        ');
        $stmt->execute([$title, $desc, $persist, $stop_reply, $seq_id]);
    }

    // 6b. Replace all steps (delete + re-insert)
    $conn->prepare('DELETE FROM sequence_steps WHERE sequence_id = ?')->execute([$seq_id]);

    if (!empty($steps)) {
        $ins = $conn->prepare('
            INSERT INTO sequence_steps
                (sequence_id, template_id, template_name, template_language,
                 step_order, delay_value, delay_unit,
                 header_type, media_path, media_filename)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($steps as $idx => $step) {
            $template_key  = trim($step['templateKey']   ?? '');
            $delay_value   = (int)($step['delay_value']  ?? 0);
            $delay_unit    = $step['delay_unit']          ?? 'days';
            $header_type   = $step['header_type']         ?? 'NONE';
            $media_data    = $step['media_data']          ?? '';
            $media_filename= trim($step['media_filename'] ?? '');
            $media_path    = '';

            // ── Resolve template from local DB (best-effort) ──────────────
            $template_id   = null;
            $template_name = $template_key;
            $template_lang = 'en_US';

            if ($template_key !== '') {
                $t = $conn->prepare('SELECT id, label, language FROM templates WHERE template_key = ? LIMIT 1');
                $t->execute([$template_key]);
                $row = $t->fetch();
                if ($row) {
                    $template_id   = (int)$row['id'];
                    $template_name = $row['label'];
                    $template_lang = $row['language'] ?: 'en_US';
                }
            }

            // ── Save uploaded media (base64) ──────────────────────────────
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
                    $unique_name = "seq_{$seq_id}_step_" . ($idx + 1) . '_' . time() . '_' . $safe_name;
                    $save_path   = $media_upload_dir . $unique_name;

                    if (file_put_contents($save_path, $file_bytes) !== false) {
                        $media_path = $media_web_path . $unique_name;

                        // Auto-transcode MP4 → WhatsApp-compatible H.264 Baseline
                        if ($ext === 'mp4') {
                            $tmp  = $save_path . '_tmp.mp4';
                            $ffmp = file_exists('/usr/local/bin/ffmpeg') ? '/usr/local/bin/ffmpeg' : 'ffmpeg';
                            $cmd  = escapeshellcmd($ffmp)
                                  . ' -y -i '        . escapeshellarg($save_path)
                                  . ' -c:v libx264 -profile:v baseline -level:v 3.0'
                                  . ' -vf scale=640:360'
                                  . ' -b:v 600k -maxrate 700k -bufsize 1200k'
                                  . ' -c:a aac -b:a 96k -ar 44100'
                                  . ' -movflags +faststart -pix_fmt yuv420p'
                                  . ' '              . escapeshellarg($tmp)
                                  . ' 2>/dev/null';
                            exec($cmd, $out, $rc);
                            if ($rc === 0 && file_exists($tmp)) {
                                rename($tmp, $save_path);
                            } elseif (file_exists($tmp)) {
                                unlink($tmp);
                            }
                        }
                    }
                }
            } elseif (!empty($step['media_path'])) {
                // Edit case — reuse existing saved path
                $media_path = $step['media_path'];
            }

            $ins->execute([
                $seq_id,
                $template_id,
                $template_name,
                $template_lang,
                $idx + 1,
                $delay_value,
                $delay_unit,
                $header_type,
                $media_path ?: null,
                $media_filename ?: null,
            ]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'id' => $seq_id]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
