<?php
require_once 'bot/config.php';

$webhookUrl = 'https://olmazorgo.bigsaver.ru/bot/webhook_simple.php';

$url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/setWebhook';

$data = [
    'url' => $webhookUrl,
    'allowed_updates' => ['message']
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_RETURNTRANSFER => true,
]);

$result = curl_exec($ch);
curl_close($ch);

echo "Webhook o'rnatish natijasi:\n";
echo $result . "\n\n";

// Webhook holatini tekshirish
$infoUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getWebhookInfo';
$ch = curl_init($infoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$info = curl_exec($ch);
curl_close($ch);

echo "Webhook holati:\n";
echo $info;
?>