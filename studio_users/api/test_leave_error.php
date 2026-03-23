<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Testing save_leave_request dependencies...<br>";

// 1. Check DB Connection
try {
    require_once '../../config/db_connect.php';
    echo "DB Connection: OK<br>";
} catch (Throwable $e) {
    echo "DB Connection Error: " . $e->getMessage() . "<br>";
}

// 2. Check WhatsApp Service
try {
    $wa_path = __DIR__ . '/../../whatsapp/WhatsAppService.php';
    if (file_exists($wa_path)) {
        echo "WhatsAppService file found.<br>";
        require_once $wa_path;
        if (class_exists('WhatsAppService')) {
            echo "WhatsAppService class loaded OK.<br>";
        } else {
            echo "WhatsAppService class NOT found in the file.<br>";
        }
    } else {
        echo "WhatsAppService file NOT FOUND at: " . $wa_path . "<br>";
    }
} catch (Throwable $e) {
    echo "WhatsAppService Error: " . $e->getMessage() . "<br>";
}

echo "Test complete.";
?>
