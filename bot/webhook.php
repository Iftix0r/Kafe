<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/handlers/StartHandler.php';
require_once __DIR__ . '/handlers/OrderHandler.php';

// Debug logging
function logDebug($message) {
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logDebug("Webhook called");

$input = file_get_contents('php://input');
logDebug("Raw input: " . $input);

$update = json_decode($input, true);
if (!$update) {
    logDebug("No update data");
    exit;
}

logDebug("Update received: " . json_encode($update));

$message = $update['message'] ?? null;
if (!$message) {
    logDebug("No message in update");
    exit;
}

$chatId = $message['chat']['id'];
$text = $message['text'] ?? '';

logDebug("Chat ID: $chatId, Text: $text");

// WebApp data - buyurtma boshlash
if (isset($message['web_app_data'])) {
    logDebug("WebApp data received");
    try {
        (new OrderHandler())->handle($update);
        logDebug("OrderHandler completed successfully");
    } catch (Exception $e) {
        logDebug("OrderHandler error: " . $e->getMessage());
    }
    exit;
}

// Contact yoki /start
if (isset($message['contact']) || $text === '/start') {
    logDebug("Start or contact message");
    try {
        (new StartHandler())->handle($update);
        logDebug("StartHandler completed successfully");
    } catch (Exception $e) {
        logDebug("StartHandler error: " . $e->getMessage());
    }
    exit;
}

// Buyurtma jarayonini boshqarish
$orderHandler = new OrderHandler();
$sessionFile = sys_get_temp_dir() . "/order_session_{$chatId}.json";

logDebug("Checking session file: $sessionFile");

if (file_exists($sessionFile)) {
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    $step = $sessionData['step'] ?? '';
    
    logDebug("Session found, step: $step");
    
    try {
        switch ($step) {
            case 'phone':
                $orderHandler->handlePhoneNumber($update);
                break;
            case 'address':
                $orderHandler->handleAddress($update);
                break;
            case 'comment':
                $orderHandler->handleComment($update);
                break;
            default:
                logDebug("Unknown step: $step");
        }
        logDebug("Session handler completed");
    } catch (Exception $e) {
        logDebug("Session handler error: " . $e->getMessage());
    }
} else {
    logDebug("No session file found");
}

logDebug("Webhook completed");
?>
