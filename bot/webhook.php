<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/handlers/StartHandler.php';
require_once __DIR__ . '/handlers/OrderHandler.php';

// Telegram IP tekshiruvi (production da yoqing)
// function isFromTelegram(): bool {
//     $ip = $_SERVER['REMOTE_ADDR'] ?? '';
//     $ranges = ['149.154.160.0/20', '91.108.4.0/22'];
//     foreach ($ranges as $range) {
//         [$subnet, $bits] = explode('/', $range);
//         $mask = ~((1 << (32 - $bits)) - 1);
//         if ((ip2long($ip) & $mask) === (ip2long($subnet) & $mask)) return true;
//     }
//     return false;
// }
// if (!isFromTelegram()) { http_response_code(403); exit; }

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$message = $update['message'] ?? null;
if (!$message) exit;

if (isset($message['web_app_data'])) {
    (new OrderHandler())->handle($update);
} elseif (isset($message['contact']) || (isset($message['text']) && $message['text'] === '/start')) {
    (new StartHandler())->handle($update);
}
