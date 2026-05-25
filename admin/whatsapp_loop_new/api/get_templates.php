<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include WhatsApp configuration
require_once __DIR__ . '/../../../whatsapp_sales_api/config.php';

// Prepare cURL to fetch templates from WhatsApp Business Account
$waba_id = SALES_WHATSAPP_BUSINESS_ACCOUNT_ID;
$access_token = SALES_WHATSAPP_ACCESS_TOKEN;
$version = GRAPH_API_VERSION;

$url = "https://graph.facebook.com/{$version}/{$waba_id}/message_templates?limit=100";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
));

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(array("success" => false, "message" => "cURL Error #:" . $err));
    exit;
}

$data = json_decode($response, true);

if (isset($data['error'])) {
    echo json_encode(array("success" => false, "message" => $data['error']['message']));
    exit;
}

$templates = array();

if (isset($data['data'])) {
    foreach ($data['data'] as $template) {
        // Extract basic data
        $key = $template['name'];
        $label = ucwords(str_replace('_', ' ', $template['name']));
        $category = $template['category'];
        $status = $template['status']; // e.g., APPROVED, PENDING, REJECTED
        
        // Find the body and header components
        $body = "";
        $header_type = "NONE"; // Default
        if (isset($template['components'])) {
            foreach ($template['components'] as $component) {
                if ($component['type'] === 'BODY') {
                    $body = $component['text'];
                } else if ($component['type'] === 'HEADER') {
                    if (isset($component['format'])) {
                        $header_type = $component['format']; // TEXT, IMAGE, VIDEO, DOCUMENT
                    }
                }
            }
        }
        
        $templates[] = array(
            'key' => $key,
            'label' => $label,
            'category' => $category,
            'status' => $status,
            'body' => $body,
            'header_type' => $header_type,
            'language' => $template['language']
        );
    }
}

echo json_encode(array("success" => true, "data" => $templates));
?>
