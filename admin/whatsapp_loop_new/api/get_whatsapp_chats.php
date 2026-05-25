<?php
/**
 * api/get_whatsapp_chats.php
 * Fetches real chats and contacts from the MySQL database directly from `whatsapp_messages` table,
 * resolving correct statuses, date headers, and unread counts.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
require_once __DIR__ . '/../../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$conn = $pdo;

// Phone cleaning helper function
function cleanPhoneNumber($phone) {
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strlen($clean) === 10) {
        $clean = '91' . $clean;
    }
    return $clean;
}

// Date grouping helper function (assumes timestamps are stored in IST)
function getMessageGroupDate($timestamp) {
    if (!$timestamp) return 'TODAY';

    $ist = new DateTimeZone('Asia/Kolkata');
    $dt = new DateTime($timestamp, $ist);

    $today = new DateTime('now', $ist);
    $today->setTime(0, 0, 0);
    $yesterday = clone $today;
    $yesterday->modify('-1 day');

    if ($dt >= $today) {
        return 'TODAY';
    } elseif ($dt >= $yesterday) {
        return 'YESTERDAY';
    } else {
        return $dt->format('d/m/Y');
    }
}

try {
    // 1. Fetch all clients
    $clientStmt = $conn->prepare("SELECT id, name, phone, email FROM clients ORDER BY name ASC");
    $clientStmt->execute();
    $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch all messages from sales_whatsapp_messages sorted by time
    $msgStmt = $conn->prepare("SELECT id, wa_message_id, user_phone, direction, message_type, body, status, created_at FROM sales_whatsapp_messages ORDER BY created_at ASC, id ASC");
    $msgStmt->execute();
    $allMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group messages by clean phone number
    $messagesByPhone = [];
    foreach ($allMessages as $msg) {
        $cleanPhone = cleanPhoneNumber($msg['user_phone']);
        if (!isset($messagesByPhone[$cleanPhone])) {
            $messagesByPhone[$cleanPhone] = [];
        }
        $messagesByPhone[$cleanPhone][] = $msg;
    }

    $chatsList = [];
    $contactsList = [];

    foreach ($clients as $client) {
        $clientId = (int)$client['id'];
        $clientName = $client['name'];
        $clientPhone = $client['phone'];
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($clientName) . "&background=random";

        $clientPhoneClean = cleanPhoneNumber($clientPhone);
        $clientMessages = isset($messagesByPhone[$clientPhoneClean]) ? $messagesByPhone[$clientPhoneClean] : [];

        $messages = [];
        $unreadCount = 0;
        $lastMsgText = '';
        $lastMsgTime = '';
        $lastInboundTs = null;

        foreach ($clientMessages as $msg) {
            $direction = $msg['direction'];
            $type = ($direction === 'inbound') ? 'in' : 'out';
            $status = $msg['status']; // 'received', 'sent', 'delivered', 'read'
            $timestamp = $msg['created_at'];

            // Time and date formats
            $ist = new DateTimeZone('Asia/Kolkata');
            $dt = new DateTime($timestamp, $ist);

            $formattedTime = $dt->format('h:i A');
            $formattedDate = getMessageGroupDate($timestamp);

            $bodyText = $msg['body'];

            // If this is a logged template message (the system logs template sends as
            // "[SALES] Template: <template_key>\nParameters:\n<param1>\n<param2>...",
            // reconstruct a readable preview using the `templates` table when possible.
            $isTemplatePreview = false;
            if (preg_match('/Template:\s*([A-Za-z0-9_\-]+)/i', $bodyText, $m)) {
                $templateKey = $m[1];
                $params = [];

                // Try to extract parameters block after 'Parameters:'
                if (preg_match('/Parameters:\s*(.*)$/is', $bodyText, $pm)) {
                    $paramsText = trim($pm[1]);
                    // split lines and remove empty
                    $pLines = preg_split('/\r?\n/', $paramsText);
                    foreach ($pLines as $pl) {
                        $pl = trim($pl);
                        if ($pl !== '') $params[] = $pl;
                    }
                }

                // Fetch template body from DB (if available) to build a nicer preview
                try {
                    $tplStmt = $conn->prepare("SELECT body FROM templates WHERE template_key = ? LIMIT 1");
                    $tplStmt->execute([$templateKey]);
                    $tplRow = $tplStmt->fetch(PDO::FETCH_ASSOC);
                    if ($tplRow && !empty($tplRow['body'])) {
                        $tplBody = $tplRow['body'];

                        // Attempt parameter substitution for common placeholder styles
                        foreach ($params as $idx => $val) {
                            $n = $idx + 1;
                            // patterns: {{1}} or {{1}} with spaces, %1$s, {1}
                            $tplBody = str_replace(["{{{$n}}}", "{{ $n }}", "%{$n}\$s", "{{$n}}"], $val, $tplBody);
                        }

                        // Use the substituted template body as preview
                        $bodyText = $tplBody;
                        $isTemplatePreview = true;
                    }
                } catch (Exception $e) {
                    // ignore and fall back to raw bodyText
                }
            }

            // Clean up any campaign log prefixes if they exist (non-template paths)
            if (!$isTemplatePreview && strpos($bodyText, '[SALES] ') === 0) {
                $bodyText = substr($bodyText, 8);
            }

            $ts = strtotime($timestamp);

            $messages[] = [
                'id' => $msg['id'],
                'text' => $bodyText,
                'time' => $formattedTime,
                'date' => $formattedDate,
                'type' => $type,
                'status' => $status,
                'ts' => $ts
            ];

            $lastMsgText = $bodyText;
            $lastMsgTime = $formattedTime;

            if ($type === 'in') {
                if ($status === 'received') {
                    $unreadCount++;
                }
                if ($ts && (!$lastInboundTs || $ts > $lastInboundTs)) {
                    $lastInboundTs = $ts;
                }
            }
        }

        // Add to Contacts list
        $contactsList[] = [
            'id' => $clientId,
            'name' => $clientName,
            'phone' => $clientPhone ?: 'No Phone',
            'avatar' => $avatarUrl
        ];

        // Add to Active Chats list only if they have message history
        if (!empty($messages)) {
            $nowTs = time();
            $windowEndTs = $lastInboundTs ? ($lastInboundTs + 24 * 3600) : null;
            $windowOpen = $windowEndTs ? ($nowTs <= $windowEndTs) : false;

            $chatsList[] = [
                'id' => $clientId,
                'name' => $clientName,
                'phone' => $clientPhone,
                'avatar' => $avatarUrl,
                'lastMsg' => $lastMsgText,
                'time' => $lastMsgTime,
                'unread' => $unreadCount,
                'messages' => $messages,
                'window' => [
                    'lastInboundTs' => $lastInboundTs,
                    'endTs' => $windowEndTs,
                    'nowTs' => $nowTs,
                    'isOpen' => $windowOpen
                ]
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "chats" => $chatsList,
        "contacts" => $contactsList
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
