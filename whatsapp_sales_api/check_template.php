<?php
// whatsapp_sales_api/check_template.php
require_once __DIR__ . '/WhatsAppClient.php';
$client = new SalesWhatsAppClient();
$templates = $client->getTemplates();

if (isset($templates['data'])) {
    foreach ($templates['data'] as $template) {
        if ($template['name'] === 'mahashivratri_2026_wishing') {
            echo "Template Found: " . $template['name'] . "\n";
            echo "Status: " . $template['status'] . "\n";
            echo "Components: \n";
            print_r($template['components']);
            exit;
        }
    }
    echo "Template 'shivratri_wish_2026' not found.\n";
} else {
    echo "Error fetching templates: " . print_r($templates, true) . "\n";
}
?>