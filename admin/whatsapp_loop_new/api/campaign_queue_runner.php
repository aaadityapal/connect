<?php
require_once __DIR__ . '/../../../whatsapp_sales_api/WhatsAppClient.php';

function campaignLogExists(PDO $conn): bool
{
    $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaign_message_logs'");
    return (int)$stmt->fetchColumn() > 0;
}

function campaignWriteLog(PDO $conn, array $row): void
{
    if (!campaignLogExists($conn)) {
        return;
    }

    $stmt = $conn->prepare('
        INSERT INTO campaign_message_logs
            (campaign_id, campaign_delivery_id, client_id, client_name, client_phone, template_name, message_id, status, details, created_at)
        VALUES
            (:campaign_id, :campaign_delivery_id, :client_id, :client_name, :client_phone, :template_name, :message_id, :status, :details, NOW())
    ');

    $stmt->execute([
        ':campaign_id' => $row['campaign_id'] ?? null,
        ':campaign_delivery_id' => $row['campaign_delivery_id'] ?? null,
        ':client_id' => $row['client_id'] ?? null,
        ':client_name' => $row['client_name'] ?? null,
        ':client_phone' => $row['client_phone'] ?? null,
        ':template_name' => $row['template_name'] ?? null,
        ':message_id' => $row['message_id'] ?? null,
        ':status' => $row['status'] ?? 'Unknown',
        ':details' => isset($row['details']) ? json_encode($row['details']) : null,
    ]);
}

function campaignQueueNormalizePhone($phone)
{
    $digits = preg_replace('/[^0-9]/', '', (string) $phone);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    return $digits;
}

function campaignQueueBuildComponents(PDO $conn, SalesWhatsAppClient $waClient, array &$campaign, $clientName)
{
    static $templatesMap = null;
    if ($templatesMap === null) {
        $templatesMap = [];
        $templatesResponse = $waClient->getTemplates();
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
    }

    $components = [];
    $headerType = strtoupper((string)($campaign['media_header_type'] ?? 'NONE'));

    if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
        $relativePath = (string)($campaign['media_path'] ?? '');
        $absolutePath = $relativePath ? (__DIR__ . '/../' . ltrim($relativePath, '/')) : '';

        if (!$absolutePath || !file_exists($absolutePath)) {
            return ['error' => 'Media file missing for media template'];
        }

        $mediaId = '';
        $cacheAgeDays = 25;
        if (!empty($campaign['media_wa_id']) && !empty($campaign['media_wa_id_updated_at'])) {
            $updatedAt = strtotime((string)$campaign['media_wa_id_updated_at']);
            if ($updatedAt && (time() - $updatedAt) < ($cacheAgeDays * 86400)) {
                $mediaId = (string)$campaign['media_wa_id'];
            }
        }

        if ($mediaId === '') {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($absolutePath) ?: 'application/octet-stream';
            $mediaId = $waClient->uploadMedia($absolutePath, $mime);
            if (!$mediaId) {
                return ['error' => 'Failed to upload media to WhatsApp'];
            }

            $upd = $conn->prepare('UPDATE campaigns SET media_wa_id = ?, media_wa_id_updated_at = NOW() WHERE id = ?');
            $upd->execute([$mediaId, (int)$campaign['id']]);

            // Update in-memory array so subsequent deliveries in this run use the cached media
            $campaign['media_wa_id'] = $mediaId;
            $campaign['media_wa_id_updated_at'] = date('Y-m-d H:i:s');
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
        } else {
            $components[] = [
                'type' => 'header',
                'parameters' => [['type' => 'document', 'document' => ['id' => $mediaId]]]
            ];
        }
    }

    $name = trim((string)$clientName);
    $templateKey = trim((string)($campaign['template_key'] ?? ''));
    $expectedParams = isset($templatesMap[$templateKey]) ? $templatesMap[$templateKey] : 1; // Default to 1 for safety if not found

    if ($expectedParams > 0) {
        $params = [];
        for ($i = 0; $i < $expectedParams; $i++) {
            $params[] = ['type' => 'text', 'text' => ($i === 0 && $name !== '') ? $name : ' '];
        }
        $components[] = [
            'type' => 'body',
            'parameters' => $params
        ];
    }

    return ['components' => $components];
}

function runCampaignQueue(PDO $conn, $forcedCampaignId = null, $maxCampaigns = 10, $maxDeliveriesPerCampaign = 500)
{
    date_default_timezone_set('Asia/Kolkata');
    $conn->exec("SET time_zone = '+05:30'");

    $summary = [
        'campaigns' => 0,
        'deliveries_sent' => 0,
        'deliveries_failed' => 0,
    ];

    $lockFile = sys_get_temp_dir() . '/whatsapp_campaign_queue.lock';
    $fp = fopen($lockFile, 'c+');
    if (!$fp) {
        return $summary;
    }

    // Try to acquire an exclusive lock, non-blocking
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return $summary; // Another instance is currently processing the queue
    }

    try {
        if ($forcedCampaignId) {
            $campaignStmt = $conn->prepare('
                SELECT c.*
                  FROM campaigns c
                 WHERE c.id = ? AND c.status = "Running"
                 LIMIT 1
            ');
            $campaignStmt->execute([(int)$forcedCampaignId]);
        } else {
            $campaignStmt = $conn->prepare('
                SELECT c.*
                  FROM campaigns c
                 WHERE c.status = "Running"
                   AND (
                        c.schedule_type = "Now"
                        OR (c.schedule_type = "Later" AND c.scheduled_at IS NOT NULL AND c.scheduled_at <= NOW())
                   )
                 ORDER BY c.created_at ASC
                 LIMIT ?
            ');
            $campaignStmt->bindValue(1, (int)$maxCampaigns, PDO::PARAM_INT);
            $campaignStmt->execute();
        }

        $campaigns = $campaignStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$campaigns) {
            return $summary;
        }

        $waClient = new SalesWhatsAppClient();

        foreach ($campaigns as &$campaign) {
            $summary['campaigns']++;
            $campaignId = (int)$campaign['id'];
            $templateKey = trim((string)($campaign['template_key'] ?? ''));
            $language = trim((string)($campaign['template_language'] ?? 'en_US')) ?: 'en_US';

            if ($templateKey === '') {
                $conn->prepare('UPDATE campaigns SET status = "Completed", last_error = ? WHERE id = ?')
                    ->execute(['Missing template key', $campaignId]);
                continue;
            }

            $deliveryStmt = $conn->prepare('
                SELECT id, client_id, client_name, client_phone
                  FROM campaign_deliveries
                 WHERE campaign_id = ? AND status = "Pending"
                 ORDER BY id ASC
                 LIMIT ?
            ');
            $deliveryStmt->bindValue(1, $campaignId, PDO::PARAM_INT);
            $deliveryStmt->bindValue(2, (int)$maxDeliveriesPerCampaign, PDO::PARAM_INT);
            $deliveryStmt->execute();
            $deliveries = $deliveryStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$deliveries) {
                $conn->prepare('UPDATE campaigns SET status = "Completed", last_error = NULL WHERE id = ?')
                    ->execute([$campaignId]);
                continue;
            }

            foreach ($deliveries as $delivery) {
                campaignWriteLog($conn, [
                    'campaign_id' => $campaignId,
                    'campaign_delivery_id' => $delivery['id'],
                    'client_id' => $delivery['client_id'],
                    'client_name' => $delivery['client_name'],
                    'client_phone' => $delivery['client_phone'],
                    'template_name' => $campaign['template_name'] ?? $templateKey,
                    'status' => 'Queued',
                    'details' => ['event' => 'queue_pick']
                ]);

                $build = campaignQueueBuildComponents($conn, $waClient, $campaign, $delivery['client_name']);
                if (!empty($build['error'])) {
                    $summary['deliveries_failed']++;
                    $conn->prepare('UPDATE campaign_deliveries SET status = "Failed", error_message = ? WHERE id = ?')
                        ->execute([$build['error'], (int)$delivery['id']]);
                    campaignWriteLog($conn, [
                        'campaign_id' => $campaignId,
                        'campaign_delivery_id' => $delivery['id'],
                        'client_id' => $delivery['client_id'],
                        'client_name' => $delivery['client_name'],
                        'client_phone' => $delivery['client_phone'],
                        'template_name' => $campaign['template_name'] ?? $templateKey,
                        'status' => 'Failed',
                        'details' => ['error' => $build['error']]
                    ]);
                    continue;
                }

                $phone = campaignQueueNormalizePhone($delivery['client_phone']);
                $resp = $waClient->sendTemplateMessage($phone, $templateKey, $language, $build['components']);

                if (isset($resp['messages'][0]['id'])) {
                    $waMessageId = $resp['messages'][0]['id'];
                    $summary['deliveries_sent']++;
                    $conn->prepare('UPDATE campaign_deliveries SET status = "Sent", sent_at = NOW(), whatsapp_message_id = ?, error_message = NULL WHERE id = ?')
                        ->execute([$waMessageId, (int)$delivery['id']]);
                    campaignWriteLog($conn, [
                        'campaign_id' => $campaignId,
                        'campaign_delivery_id' => $delivery['id'],
                        'client_id' => $delivery['client_id'],
                        'client_name' => $delivery['client_name'],
                        'client_phone' => $delivery['client_phone'],
                        'template_name' => $campaign['template_name'] ?? $templateKey,
                        'message_id' => $waMessageId,
                        'status' => 'Sent',
                        'details' => $resp
                    ]);
                } else {
                    $summary['deliveries_failed']++;
                    $err = isset($resp['error']) ? json_encode($resp['error']) : json_encode($resp);
                    $conn->prepare('UPDATE campaign_deliveries SET status = "Failed", error_message = ? WHERE id = ?')
                        ->execute([$err, (int)$delivery['id']]);
                    campaignWriteLog($conn, [
                        'campaign_id' => $campaignId,
                        'campaign_delivery_id' => $delivery['id'],
                        'client_id' => $delivery['client_id'],
                        'client_name' => $delivery['client_name'],
                        'client_phone' => $delivery['client_phone'],
                        'template_name' => $campaign['template_name'] ?? $templateKey,
                        'status' => 'Failed',
                        'details' => $resp
                    ]);
                }
            }

            $pendingStmt = $conn->prepare('SELECT COUNT(*) FROM campaign_deliveries WHERE campaign_id = ? AND status = "Pending"');
            $pendingStmt->execute([$campaignId]);
            $pending = (int)$pendingStmt->fetchColumn();

            if ($pending === 0) {
                $conn->prepare('UPDATE campaigns SET status = "Completed", last_error = NULL WHERE id = ?')
                    ->execute([$campaignId]);
            }
        }
        unset($campaign);

        return $summary;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
