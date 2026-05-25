<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../whatsapp_sales_api/config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Sanitize: Meta only allows lowercase letters, digits, underscores
$name     = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($input['name'] ?? '')));
$name     = trim($name, '_');
$category = strtoupper(trim($input['category'] ?? 'MARKETING'));
$langCode = trim($input['language'] ?? 'en_US');
$body     = trim($input['body'] ?? '');
$header   = trim($input['header'] ?? '');
$footer   = trim($input['footer'] ?? '');
$buttons  = $input['buttons'] ?? [];

// Map short codes to Meta-valid full locale codes
$localeMap = [
    'en'  => 'en_US',
    'hi'  => 'hi',
    'mr'  => 'mr',
    'gu'  => 'gu',
    'ta'  => 'ta',
    'te'  => 'te',
    'kn'  => 'kn',
    'bn'  => 'bn',
    'pa'  => 'pa',
    'ar'  => 'ar',
];
$language = $localeMap[$langCode] ?? $langCode;

// Validate allowed categories
$allowedCategories = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];
if (!in_array($category, $allowedCategories)) {
    $category = 'MARKETING';
}

if (!$name || !$body) {
    echo json_encode(['success' => false, 'message' => 'Template name and body are required.']);
    exit;
}

if (strlen($name) < 1 || strlen($name) > 512) {
    echo json_encode(['success' => false, 'message' => 'Template name must be between 1 and 512 characters.']);
    exit;
}

// Build the components array
$components = [];

// Header component (optional)
if (!empty($header)) {
    $components[] = [
        'type'   => 'HEADER',
        'format' => 'TEXT',
        'text'   => $header
    ];
}

// Body component (required)
$components[] = [
    'type' => 'BODY',
    'text' => $body
];

// Footer component (optional)
if (!empty($footer)) {
    $components[] = [
        'type' => 'FOOTER',
        'text' => $footer
    ];
}

// Buttons component (optional)
if (!empty($buttons)) {
    $buttonItems = [];
    foreach ($buttons as $btn) {
        $type = strtoupper($btn['type'] ?? 'QUICK_REPLY');
        if ($type === 'QUICK_REPLY') {
            $buttonItems[] = ['type' => 'QUICK_REPLY', 'text' => $btn['text']];
        } elseif ($type === 'PHONE_NUMBER') {
            $buttonItems[] = ['type' => 'PHONE_NUMBER', 'text' => $btn['text'], 'phone_number' => $btn['value']];
        } elseif ($type === 'URL') {
            $buttonItems[] = ['type' => 'URL', 'text' => $btn['text'], 'url' => $btn['value']];
        }
    }
    if (!empty($buttonItems)) {
        $components[] = ['type' => 'BUTTONS', 'buttons' => $buttonItems];
    }
}

// Build payload
$payload = [
    'name'       => $name,
    'category'   => $category,
    'language'   => $language,
    'components' => $components
];

$waba_id      = SALES_WHATSAPP_BUSINESS_ACCOUNT_ID;
$access_token = SALES_WHATSAPP_ACCESS_TOKEN;
$version      = GRAPH_API_VERSION;

$url = "https://graph.facebook.com/{$version}/{$waba_id}/message_templates";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'message' => 'cURL error: ' . $err]);
    exit;
}

$result = json_decode($response, true);

if (isset($result['id'])) {
    echo json_encode(['success' => true, 'message' => 'Template submitted to Meta for review! Status: PENDING.', 'id' => $result['id']]);
} else {
    // Collect as much error detail as possible
    $errMsg  = $result['error']['message'] ?? 'Unknown error from Meta API';
    $errUser = $result['error']['error_user_msg'] ?? '';
    $errCode = $result['error']['code'] ?? '';
    $errSub  = $result['error']['error_subcode'] ?? '';
    $detail  = $errMsg;
    if ($errUser) $detail .= ' | ' . $errUser;
    if ($errCode) $detail .= ' (Code: ' . $errCode . ($errSub ? '/' . $errSub : '') . ')';
    echo json_encode(['success' => false, 'message' => $detail, 'payload_sent' => $payload, 'raw' => $result]);
}
?>
