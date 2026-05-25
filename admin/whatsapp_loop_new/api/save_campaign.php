<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/campaign_queue_runner.php';

function campaignColumnExists(PDO $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('
        SELECT COUNT(*)
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureCampaignSchema(PDO $conn): void
{
    $campaignColumns = [
        'template_key' => "ALTER TABLE campaigns ADD COLUMN template_key VARCHAR(100) DEFAULT NULL AFTER template_id",
        'template_language' => "ALTER TABLE campaigns ADD COLUMN template_language VARCHAR(20) DEFAULT 'en_US' AFTER template_key",
        'template_body' => "ALTER TABLE campaigns ADD COLUMN template_body TEXT DEFAULT NULL AFTER template_language",
        'media_header_type' => "ALTER TABLE campaigns ADD COLUMN media_header_type VARCHAR(20) DEFAULT 'NONE' AFTER template_body",
        'media_path' => "ALTER TABLE campaigns ADD COLUMN media_path VARCHAR(255) DEFAULT NULL AFTER media_header_type",
        'media_filename' => "ALTER TABLE campaigns ADD COLUMN media_filename VARCHAR(255) DEFAULT NULL AFTER media_path",
        'media_wa_id' => "ALTER TABLE campaigns ADD COLUMN media_wa_id VARCHAR(255) DEFAULT NULL AFTER media_filename",
        'media_wa_id_updated_at' => "ALTER TABLE campaigns ADD COLUMN media_wa_id_updated_at DATETIME DEFAULT NULL AFTER media_wa_id",
        'last_error' => "ALTER TABLE campaigns ADD COLUMN last_error TEXT DEFAULT NULL AFTER updated_at",
    ];

    foreach ($campaignColumns as $column => $sql) {
        if (!campaignColumnExists($conn, 'campaigns', $column)) {
            $conn->exec($sql);
        }
    }

    $deliveryColumns = [
        'client_name' => "ALTER TABLE campaign_deliveries ADD COLUMN client_name VARCHAR(100) DEFAULT NULL AFTER client_id",
        'client_phone' => "ALTER TABLE campaign_deliveries ADD COLUMN client_phone VARCHAR(20) DEFAULT NULL AFTER client_name",
        'template_name' => "ALTER TABLE campaign_deliveries ADD COLUMN template_name VARCHAR(100) DEFAULT NULL AFTER client_phone",
        'whatsapp_message_id' => "ALTER TABLE campaign_deliveries ADD COLUMN whatsapp_message_id VARCHAR(255) DEFAULT NULL AFTER template_name",
    ];

    foreach ($deliveryColumns as $column => $sql) {
        if (!campaignColumnExists($conn, 'campaign_deliveries', $column)) {
            $conn->exec($sql);
        }
    }
}

function ensureTemplateExists(PDO $conn, string $templateKey, string $templateName, string $templateCategory, string $templateLanguage, string $templateBody): int
{
    $stmt = $conn->prepare('SELECT id FROM templates WHERE template_key = ? LIMIT 1');
    $stmt->execute([$templateKey]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($template) {
        return (int)$template['id'];
    }

    $insert = $conn->prepare('
        INSERT INTO templates (template_key, label, category, language, body, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $insert->execute([
        $templateKey,
        $templateName ?: $templateKey,
        $templateCategory ?: 'General',
        $templateLanguage ?: 'en_US',
        $templateBody ?: '',
        'Active'
    ]);

    return (int)$conn->lastInsertId();
}

function campaignMessageLogTableExists(PDO $conn): bool
{
    $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaign_message_logs'");
    return (int)$stmt->fetchColumn() > 0;
}

function writeCampaignStatusLog(PDO $conn, array $row): void
{
    if (!campaignMessageLogTableExists($conn)) {
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
        ':status' => $row['status'] ?? 'Queued',
        ':details' => isset($row['details']) ? json_encode($row['details']) : null,
    ]);
}

$conn = $pdo;

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->name) && !empty($data->template_key)){
    try {
        ensureCampaignSchema($conn);
        $conn->beginTransaction();

        // 1. Get Template ID from Key
        $templateName = $data->template_name ?? $data->template_key;
        $templateCategory = $data->template_category ?? 'General';
        $templateLanguage = $data->template_language ?? 'en_US';
        $templateBody = $data->template_body ?? '';
        $template_id = ensureTemplateExists($conn, (string)$data->template_key, (string)$templateName, (string)$templateCategory, (string)$templateLanguage, (string)$templateBody);

        $scheduleType = strtolower((string)($data->schedule_type ?? 'now')) === 'later' ? 'Later' : 'Now';
        $statusValue = $data->status ?? 'Draft';
        $targetAudience = $data->target_audience ?? 'Selected';
        $scheduledAt = null;
        if (!empty($data->scheduled_at)) {
            $scheduledAt = $data->scheduled_at;
        } elseif (!empty($data->schedule_date) && !empty($data->schedule_time)) {
            $scheduledAt = $data->schedule_date . ' ' . $data->schedule_time . ':00';
        }

        $isRepeat = !empty($data->is_repeat) ? 1 : 0;
        $repeatInterval = $data->repeat_interval ?? null;
        $stopOnReply = array_key_exists('stop_on_reply', (array) $data) ? (!empty($data->stop_on_reply) ? 1 : 0) : 1;
        $templateName = $data->template_name ?? $templateName ?? $data->template_key;
        $templateLanguage = $data->template_language ?? $templateLanguage ?? 'en_US';
        $templateBody = $data->template_body ?? $templateBody ?? null;
        $mediaHeaderType = $data->media_header_type ?? 'NONE';
        $mediaPath = $data->media_path ?? null;
        $mediaFilename = $data->media_filename ?? null;
        $mediaWaId = $data->media_wa_id ?? null;

        if ($scheduleType === 'Later' && empty($scheduledAt)) {
            throw new Exception('Scheduled campaigns require scheduled date and time.');
        }

        // 2. Insert Campaign
        $query = "INSERT INTO campaigns (name, template_id, template_name, template_key, template_language, template_body, target_audience, schedule_type, scheduled_at, is_repeat, repeat_interval, stop_on_reply, media_header_type, media_path, media_filename, media_wa_id, status) 
                  VALUES (:name, :tid, :tname, :tkey, :tlang, :tbody, :audience, :stype, :scheduled_at, :is_repeat, :repeat_interval, :stop_on_reply, :media_header_type, :media_path, :media_filename, :media_wa_id, :status)";
        
        $stmt = $conn->prepare($query);

        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":tid", $template_id);
        $stmt->bindParam(":tname", $templateName);
        $stmt->bindParam(":tkey", $data->template_key);
        $stmt->bindParam(":tlang", $templateLanguage);
        $stmt->bindParam(":tbody", $templateBody);
        $stmt->bindParam(":audience", $data->target_audience);
        $stmt->bindParam(":stype", $scheduleType);
        $stmt->bindValue(":scheduled_at", $scheduledAt);
        $stmt->bindValue(":is_repeat", $isRepeat, PDO::PARAM_INT);
        $stmt->bindValue(":repeat_interval", $repeatInterval);
        $stmt->bindValue(":stop_on_reply", $stopOnReply, PDO::PARAM_INT);
        $stmt->bindValue(":media_header_type", $mediaHeaderType);
        $stmt->bindValue(":media_path", $mediaPath);
        $stmt->bindValue(":media_filename", $mediaFilename);
        $stmt->bindValue(":media_wa_id", $mediaWaId);
        $stmt->bindParam(":status", $statusValue);

        $stmt->execute();
        $campaign_id = $conn->lastInsertId();

        // 3. Insert Deliveries
        if(!empty($data->client_ids)){
            $delivery_query = "INSERT INTO campaign_deliveries (campaign_id, client_id, client_name, client_phone, template_name, status) VALUES (:cid, :clid, :cname, :cphone, :tname, 'Pending')";
            $delivery_stmt = $conn->prepare($delivery_query);
            
            foreach($data->client_ids as $client_id){
                $clientStmt = $conn->prepare("SELECT name, phone FROM clients WHERE id = ? LIMIT 1");
                $clientStmt->execute([(int)$client_id]);
                $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                if (!$clientRow) {
                    continue;
                }

                $delivery_stmt->bindValue(":cid", $campaign_id, PDO::PARAM_INT);
                $delivery_stmt->bindValue(":clid", (int)$client_id, PDO::PARAM_INT);
                $delivery_stmt->bindValue(":cname", $clientRow['name']);
                $delivery_stmt->bindValue(":cphone", $clientRow['phone']);
                $delivery_stmt->bindValue(":tname", $templateName);
                $delivery_stmt->execute();

                $deliveryId = (int)$conn->lastInsertId();
                writeCampaignStatusLog($conn, [
                    'campaign_id' => $campaign_id,
                    'campaign_delivery_id' => $deliveryId,
                    'client_id' => (int)$client_id,
                    'client_name' => $clientRow['name'],
                    'client_phone' => $clientRow['phone'],
                    'template_name' => $templateName,
                    'status' => 'Queued',
                    'details' => [
                        'schedule_type' => $scheduleType,
                        'scheduled_at' => $scheduledAt,
                        'is_repeat' => $isRepeat,
                        'repeat_interval' => $repeatInterval,
                    ]
                ]);
            }
        }

        $conn->commit();

        $response = ["success" => true, "message" => "Campaign saved.", "id" => $campaign_id];

        if ($scheduleType === 'Now' && $statusValue === 'Running') {
            $queueSummary = runCampaignQueue($conn, $campaign_id, 1, 1000);
            $response['queue'] = $queueSummary;
        }

        echo json_encode($response);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Error: " . $e->getMessage()));
    }
} else {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Incomplete data."));
}
?>
