<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/handlers/StartHandler.php';
require_once __DIR__ . '/handlers/OrderHandler.php';

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$message = $update['message'] ?? null;
if (!$message) exit;

$chatId = $message['chat']['id'];
$text = $message['text'] ?? '';

// WebApp data - buyurtma boshlash
if (isset($message['web_app_data'])) {
    (new OrderHandler())->handle($update);
    exit;
}

// Contact yoki /start
if (isset($message['contact']) || $text === '/start') {
    (new StartHandler())->handle($update);
    exit;
}

// Buyurtma jarayonini boshqarish
$orderHandler = new OrderHandler();
$sessionFile = sys_get_temp_dir() . "/order_session_{$chatId}.json";

if (file_exists($sessionFile)) {
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    $step = $sessionData['step'] ?? '';
    
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
    }
}
