<?php
// whatsapp_sales_api/helper.php

// Helper function to get DB connection (if not already defined)
// Assuming DB config is in admin/config.php, similar to the main helper
require_once __DIR__ . '/../config/db_connect.php';

if (!function_exists('getDBConnection')) {
    function getDBConnection()
    {
        global $host, $username, $password, $dbname;
        $mysqli = new mysqli($host, $username, $password, $dbname);
        if ($mysqli->connect_error) {
            throw new Exception("Sales helper connection failed: " . $mysqli->connect_error);
        }
        $mysqli->query("SET time_zone = '+05:30'");
        return $mysqli;
    }
}

// Log Sales WhatsApp message to database
// Renamed to specifically indicate Sales logs
function logSalesWhatsAppMessage($waMessageId, $phone, $direction, $type, $body, $status = 'received')
{
    // Ensure phone number is clean (digits only)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        $phone = '91' . $phone;
    }

    // Connect to DB
    $conn = getDBConnection();

    // Check if message already exists
    if (!empty($waMessageId)) {
        $check = $conn->prepare("SELECT id FROM sales_whatsapp_messages WHERE wa_message_id = ?");
        if ($check) {
            $check->bind_param("s", $waMessageId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $conn->close();
                return; // Already logged
            }
        }
    }

    // We are logging to the new 'sales_whatsapp_messages' table.
    $statusVal = ($direction === 'inbound') ? 'read' : strtolower($status);
    // Sanitize status to match ENUM
    if (!in_array($statusVal, ['sent', 'delivered', 'read', 'failed'])) {
        $statusVal = 'sent';
    }

    $stmt = $conn->prepare("INSERT INTO sales_whatsapp_messages (wa_message_id, user_phone, direction, message_type, body, status) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssss", $waMessageId, $phone, $direction, $type, $body, $statusVal);
        $stmt->execute();
    } else {
        error_log("WA_SALES_DEBUG ERROR: Failed to prepare INSERT statement. Table might be missing! Error: " . $conn->error);
    }
    $conn->close();
}


/**
 * Wrapper to send text message using SalesWhatsAppClient
 */
function sendSalesWhatsAppText($toPhone, $messageText)
{
    require_once __DIR__ . '/WhatsAppClient.php';
    $client = new SalesWhatsAppClient();
    $result = $client->sendMessage($toPhone, $messageText);

    if (isset($result['messages'][0]['id'])) {
        return ['success' => true, 'response' => $result];
    } else {
        return ['success' => false, 'error' => $result];
    }
}

/**
 * Wrapper to upload media using SalesWhatsAppClient
 */
function uploadSalesMedia($filePath, $mimeType = 'application/pdf')
{
    require_once __DIR__ . '/WhatsAppClient.php';
    $client = new SalesWhatsAppClient();
    return $client->uploadMedia($filePath, $mimeType);
}

/**
 * Wrapper to send document message
 */
function sendSalesWhatsAppDocument($toPhone, $mediaId, $filename, $caption = '')
{
    require_once __DIR__ . '/WhatsAppClient.php';
    $client = new SalesWhatsAppClient();
    $result = $client->sendDocumentMessage($toPhone, $mediaId, $filename, $caption);

    if (isset($result['messages'][0]['id'])) {
        return ['success' => true, 'response' => $result];
    } else {
        return ['success' => false, 'error' => $result];
    }
}

/**
 * Wrapper to send template message using SalesWhatsAppClient
 */
function sendSalesWhatsAppMessage($toPhone, $templateName, $languageCode = 'en_US', $components = [])
{
    require_once __DIR__ . '/WhatsAppClient.php';
    $client = new SalesWhatsAppClient();
    $result = $client->sendTemplateMessage($toPhone, $templateName, $languageCode, $components);

    if (isset($result['messages'][0]['id'])) {
        return ['success' => true, 'response' => $result];
    } else {
        return ['success' => false, 'error' => $result];
    }
}

// Custom Logger for Sales WhatsApp Debugging
function logSalesDebug($message)
{
    $logFile = __DIR__ . '/../logs/whatsapp_sales_debug.log';
    $logDir = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // Ensure message is a string
    if (is_array($message) || is_object($message)) {
        $message = json_encode($message, JSON_PRETTY_PRINT);
    }

    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);

    // Also log to error_log for redundancy
    error_log("WA_SALES_DEBUG: " . $message);
}