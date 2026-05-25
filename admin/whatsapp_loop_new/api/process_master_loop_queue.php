<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../whatsapp_sales_api/WhatsAppClient.php';

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/../master_loop_cron.log';

function logMasterLoopEvent($logFile, $event, $payload = [])
{
    $entry = [
        'time' => date('Y-m-d H:i:s'),
        'event' => $event,
        'payload' => $payload
    ];
    @file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
}

$conn = $pdo;

function normalizePhone($phone)
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    return $digits;
}

function addDelay(DateTime $dt, $value, $unit)
{
    $val = (int) $value;
    $unit = strtolower($unit);
    if ($val <= 0)
        return $dt;

    if ($unit === 'minutes')
        $dt->add(new DateInterval('PT' . $val . 'M'));
    else if ($unit === 'hours')
        $dt->add(new DateInterval('PT' . $val . 'H'));
    else if ($unit === 'weeks')
        $dt->add(new DateInterval('P' . ($val * 7) . 'D'));
    else if ($unit === 'months')
        $dt->add(new DateInterval('P' . $val . 'M'));
    else
        $dt->add(new DateInterval('P' . $val . 'D'));

    return $dt;
}

function getMimeType($filePath)
{
    if (!file_exists($filePath))
        return null;
        
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath);
    }
    
    if (function_exists('mime_content_type')) {
        return mime_content_type($filePath);
    }
    
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'mp4' => 'video/mp4'
    ];
    return $mimes[$ext] ?? 'application/octet-stream';
}

try {
    $stmt = $conn->prepare('
        SELECT * FROM master_loop_assignments
         WHERE status = "Assigned"
           AND (next_send_at IS NULL OR next_send_at <= NOW())
         ORDER BY assigned_at ASC
         LIMIT 25
    ');
    $stmt->execute();
    $assignments = $stmt->fetchAll();

    $client = new SalesWhatsAppClient();
    $sentCount = 0;
    $failedCount = 0;
    logMasterLoopEvent($logFile, 'tick_start', ['due' => count($assignments)]);

    // Fetch all templates to build a parameters count cache
    $templatesMap = [];
    $templatesResponse = $client->getTemplates();
    if (isset($templatesResponse['data']) && is_array($templatesResponse['data'])) {
        foreach ($templatesResponse['data'] as $tmpl) {
            if (isset($tmpl['name'])) {
                $bodyParamCount = 0;
                if (isset($tmpl['components']) && is_array($tmpl['components'])) {
                    foreach ($tmpl['components'] as $comp) {
                        if ($comp['type'] === 'BODY' && isset($comp['text'])) {
                            preg_match_all('/\{\{(\d+)\}\}/', $comp['text'], $matches);
                            if (!empty($matches[1])) {
                                $bodyParamCount = count(array_unique($matches[1]));
                            }
                        }
                    }
                }
                $templatesMap[$tmpl['name']] = $bodyParamCount;
            }
        }
    }

    foreach ($assignments as $assign) {
        $currentOrder = (int) ($assign['current_step_order'] ?? 1);
        $loopId = (int) $assign['master_loop_id'];

        $stepStmt = $conn->prepare('
                        SELECT step_order, template_key, header_type, delay_value, delay_unit, media_path, media_filename,
                                     media_wa_id, media_wa_id_updated_at
              FROM master_loop_steps
             WHERE master_loop_id = ? AND step_order = ?
             LIMIT 1
        ');
        $stepStmt->execute([$loopId, $currentOrder]);
        $step = $stepStmt->fetch();

        if (!$step) {
            $upd = $conn->prepare('UPDATE master_loop_assignments SET status = "Completed", next_send_at = NULL WHERE id = ?');
            $upd->execute([$assign['id']]);
            logMasterLoopEvent($logFile, 'loop_completed', [
                'assignment_id' => $assign['id'],
                'client' => $assign['client_name'],
                'phone' => $assign['client_phone'],
                'loop' => $assign['master_loop_name']
            ]);
            continue;
        }

        $to = normalizePhone($assign['client_phone']);
        $templateName = $step['template_key'];
        $headerType = strtoupper($step['header_type'] ?? 'NONE');

        $components = [];
        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $mediaPath = $step['media_path'] ? (__DIR__ . '/../' . $step['media_path']) : '';
            if (!$mediaPath || !file_exists($mediaPath)) {
                $failedCount++;
                logMasterLoopEvent($logFile, 'media_missing', [
                    'assignment_id' => $assign['id'],
                    'client' => $assign['client_name'],
                    'phone' => $assign['client_phone'],
                    'loop' => $assign['master_loop_name'],
                    'template' => $templateName,
                    'media_path' => $step['media_path'],
                    'header_type' => $headerType
                ]);
                // Retry in 10 min — store as UTC to match MySQL's NOW()
                $next = new DateTime('now', new DateTimeZone('UTC'));
                $next->add(new DateInterval('PT10M'));
                $conn->prepare('UPDATE master_loop_assignments SET next_send_at = ? WHERE id = ?')
                    ->execute([$next->format('Y-m-d H:i:s'), $assign['id']]);
                continue;
            }

            $mediaId = '';
            $cacheAgeDays = 25;
            if (!empty($step['media_wa_id']) && !empty($step['media_wa_id_updated_at'])) {
                $updatedAt = strtotime($step['media_wa_id_updated_at']);
                if ($updatedAt && (time() - $updatedAt) < ($cacheAgeDays * 86400)) {
                    $mediaId = $step['media_wa_id'];
                }
            }

            if ($mediaId === '') {
                $mime = getMimeType($mediaPath) ?: 'application/octet-stream';
                $mediaId = $client->uploadMedia($mediaPath, $mime);
                if ($mediaId) {
                    $cacheStmt = $conn->prepare('
                        UPDATE master_loop_steps
                           SET media_wa_id = ?, media_wa_id_updated_at = NOW()
                         WHERE master_loop_id = ? AND step_order = ?
                    ');
                    $cacheStmt->execute([$mediaId, $loopId, $currentOrder]);
                }
            }
            if (!$mediaId) {
                $failedCount++;
                logMasterLoopEvent($logFile, 'media_upload_failed', [
                    'assignment_id' => $assign['id'],
                    'client' => $assign['client_name'],
                    'phone' => $assign['client_phone'],
                    'loop' => $assign['master_loop_name'],
                    'template' => $templateName,
                    'media_path' => $mediaPath,
                    'header_type' => $headerType
                ]);
                $next = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                $next->add(new DateInterval('PT10M'));
                $conn->prepare('UPDATE master_loop_assignments SET next_send_at = ? WHERE id = ?')
                    ->execute([$next->format('Y-m-d H:i:s'), $assign['id']]);
                continue;
            }

            if ($headerType === 'IMAGE') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [['type' => 'image', 'image' => ['id' => $mediaId]]]
                ];
            } elseif ($headerType === 'VIDEO') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [['type' => 'video', 'video' => ['id' => $mediaId]]]
                ];
            } elseif ($headerType === 'DOCUMENT') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [['type' => 'document', 'document' => ['id' => $mediaId]]]
                ];
            }
        }

        $clientName = trim($assign['client_name'] ?? '');
        $expectedParams = isset($templatesMap[$templateName]) ? $templatesMap[$templateName] : 1; // Default to 1 for safety if not found
        if ($expectedParams > 0) {
            $params = [];
            for ($i = 0; $i < $expectedParams; $i++) {
                $params[] = ['type' => 'text', 'text' => ($i === 0 && $clientName !== '') ? $clientName : ' '];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $params
            ];
        }

        $resp = $client->sendTemplateMessage($to, $templateName, 'en_US', $components);
        if (!isset($resp['messages'][0]['id'])) {
            $failedCount++;
            logMasterLoopEvent($logFile, 'send_failed', [
                'assignment_id' => $assign['id'],
                'client' => $assign['client_name'],
                'phone' => $assign['client_phone'],
                'loop' => $assign['master_loop_name'],
                'template' => $templateName,
                'components' => $components,
                'response' => $resp
            ]);
            // Retry in 10 min — store as UTC to match MySQL's NOW()
            $next = new DateTime('now', new DateTimeZone('UTC'));
            $next->add(new DateInterval('PT10M'));
            $conn->prepare('UPDATE master_loop_assignments SET next_send_at = ? WHERE id = ?')
                ->execute([$next->format('Y-m-d H:i:s'), $assign['id']]);
            continue;
        }

        logMasterLoopEvent($logFile, 'send_success', [
            'assignment_id' => $assign['id'],
            'client' => $assign['client_name'],
            'phone' => $assign['client_phone'],
            'loop' => $assign['master_loop_name'],
            'template' => $templateName,
            'components' => $components,
            'response' => $resp
        ]);

        $nextOrder = $currentOrder + 1;
        $nextStmt = $conn->prepare('
            SELECT delay_value, delay_unit
              FROM master_loop_steps
             WHERE master_loop_id = ? AND step_order = ?
             LIMIT 1
        ');
        $nextStmt->execute([$loopId, $nextOrder]);
        $nextStep = $nextStmt->fetch();

        if ($nextStep) {
            // Store next_send_at in UTC to match MySQL's NOW() in the cron query
            $nextTime = new DateTime('now', new DateTimeZone('UTC'));
            $nextTime = addDelay($nextTime, $nextStep['delay_value'], $nextStep['delay_unit']);
            $upd = $conn->prepare('
                UPDATE master_loop_assignments
                   SET current_step_order = ?, next_send_at = ?, last_sent_at = NOW()
                 WHERE id = ?
            ');
            $upd->execute([$nextOrder, $nextTime->format('Y-m-d H:i:s'), $assign['id']]);
        } else {
            $upd = $conn->prepare('
                UPDATE master_loop_assignments
                   SET current_step_order = ?, status = "Completed", next_send_at = NULL, last_sent_at = NOW()
                 WHERE id = ?
            ');
            $upd->execute([$nextOrder, $assign['id']]);
        }

        $sentCount++;
    }

    logMasterLoopEvent($logFile, 'tick_end', [
        'due' => count($assignments),
        'sent' => $sentCount,
        'failed' => $failedCount
    ]);
    echo json_encode(['success' => true, 'processed' => count($assignments), 'sent' => $sentCount]);
} catch (Throwable $e) {
    http_response_code(500);
    logMasterLoopEvent($logFile, 'cron_error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
