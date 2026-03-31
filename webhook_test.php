<?php
// Webhook o'rnatish va test qilish

$botToken = '8695815144:AAGN5duKthrGjEake5hk88QArLsQZrgHAzg';

echo "<h2>🤖 Telegram Bot Webhook Test</h2>";

// 1. Webhook o'rnatish
$webhookUrl = 'https://olmazorgo.bigsaver.ru/bot/webhook_simple.php';

echo "<h3>1️⃣ Webhook o'rnatish...</h3>";
$setWebhookUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";

$data = [
    'url' => $webhookUrl,
    'allowed_updates' => ['message'],
    'drop_pending_updates' => true
];

$ch = curl_init($setWebhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_RETURNTRANSFER => true,
]);

$result = curl_exec($ch);
curl_close($ch);

$response = json_decode($result, true);
if ($response['ok']) {
    echo "✅ Webhook muvaffaqiyatli o'rnatildi<br>";
    echo "📍 URL: $webhookUrl<br><br>";
} else {
    echo "❌ Webhook o'rnatishda xatolik: " . $response['description'] . "<br><br>";
}

// 2. Webhook holatini tekshirish
echo "<h3>2️⃣ Webhook holati...</h3>";
$infoUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$ch = curl_init($infoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$info = curl_exec($ch);
curl_close($ch);

$webhookInfo = json_decode($info, true);
if ($webhookInfo['ok']) {
    $info = $webhookInfo['result'];
    echo "📍 URL: " . ($info['url'] ?: 'O\'rnatilmagan') . "<br>";
    echo "🔄 Pending updates: " . $info['pending_update_count'] . "<br>";
    echo "📅 Oxirgi xato: " . ($info['last_error_date'] ? date('Y-m-d H:i:s', $info['last_error_date']) : 'Yo\'q') . "<br>";
    if ($info['last_error_message']) {
        echo "❌ Oxirgi xato: " . $info['last_error_message'] . "<br>";
    }
    echo "<br>";
}

// 3. Test xabar yuborish
echo "<h3>3️⃣ Test xabar yuborish...</h3>";
$testChatId = '2114098498'; // Admin chat ID
$testMessage = "🧪 Test xabar - " . date('H:i:s');

$sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
$sendData = [
    'chat_id' => $testChatId,
    'text' => $testMessage
];

$ch = curl_init($sendUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $sendData,
    CURLOPT_RETURNTRANSFER => true,
]);

$sendResult = curl_exec($ch);
curl_close($ch);

$sendResponse = json_decode($sendResult, true);
if ($sendResponse['ok']) {
    echo "✅ Test xabar yuborildi<br><br>";
} else {
    echo "❌ Test xabar yuborishda xatolik: " . $sendResponse['description'] . "<br><br>";
}

// 4. Log fayllarini tekshirish
echo "<h3>4️⃣ Log fayllar...</h3>";

$logFiles = [
    'bot/simple_debug.log' => 'Oddiy webhook log',
    'bot/debug.log' => 'Batafsil webhook log'
];

foreach ($logFiles as $file => $description) {
    if (file_exists($file)) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "📄 $description: <a href='$file' target='_blank'>$file</a> ({$size} bytes, $modified)<br>";
    } else {
        echo "📄 $description: Fayl mavjud emas<br>";
    }
}

echo "<br>";

// 5. Ko'rsatmalar
echo "<h3>5️⃣ Test qilish ko'rsatmalari:</h3>";
echo "<ol>";
echo "<li>Botga <code>/start</code> yuboring</li>";
echo "<li>Telefon raqam yuboring</li>";
echo "<li>WebApp ochib mahsulot qo'shing</li>";
echo "<li><strong>Buyurtma berish</strong> tugmasini bosing</li>";
echo "<li>WebApp yopilgandan keyin bot javob berishi kerak</li>";
echo "</ol>";

echo "<h3>6️⃣ Foydali linklar:</h3>";
echo "<ul>";
echo "<li><a href='webapp/index_working.html' target='_blank'>WebApp</a></li>";
echo "<li><a href='webapp/test_new.html' target='_blank'>WebApp Test</a></li>";
echo "<li><a href='bot/simple_debug.log' target='_blank'>Webhook Log</a></li>";
echo "</ul>";

echo "<p><strong>Agar ishlamasa:</strong> Log fayllarni tekshiring va xatolarni ko'rsating.</p>";
?>