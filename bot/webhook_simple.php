<?php
require_once __DIR__ . '/config.php';

// Simple logging
function logMessage($message) {
    $logFile = __DIR__ . '/simple_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Webhook Called ===");

$input = file_get_contents('php://input');
logMessage("Raw input: " . $input);

$update = json_decode($input, true);
if (!$update) {
    logMessage("ERROR: No update data");
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    logMessage("ERROR: No message in update");
    exit;
}

$chatId = $message['chat']['id'];
$from = $message['from'];

logMessage("Chat ID: $chatId");
logMessage("From: " . json_encode($from));

// Handle WebApp data
if (isset($message['web_app_data'])) {
    logMessage("WebApp data received!");
    
    $webAppData = $message['web_app_data']['data'];
    logMessage("WebApp data content: " . $webAppData);
    
    $orderData = json_decode($webAppData, true);
    logMessage("Parsed order data: " . json_encode($orderData));
    
    if ($orderData && isset($orderData['items'])) {
        // Build order summary
        $summary = "📋 Buyurtma qabul qilindi!\n\n";
        $summary .= "🛒 Buyurtma tarkibi:\n";
        
        foreach ($orderData['items'] as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $summary .= "• {$item['name']} × {$item['quantity']} = " . number_format($itemTotal, 0, '.', ' ') . " so'm\n";
        }
        
        $summary .= "\n💰 Jami: " . number_format($orderData['total'], 0, '.', ' ') . " so'm\n\n";
        $summary .= "📱 Telefon raqamingizni yuboring:\nMasalan: +998901234567";
        
        // Send message
        $response = sendTelegramMessage($chatId, $summary);
        logMessage("Message sent, response: " . $response);
    } else {
        logMessage("ERROR: Invalid order data");
        sendTelegramMessage($chatId, "❌ Buyurtma ma'lumotlari noto'g'ri");
    }
    exit;
}

// Handle /start command
if (isset($message['text']) && $message['text'] === '/start') {
    logMessage("Start command received");
    
    $welcomeText = "🍽 Olmazor Go ga xush kelibsiz!\n\n";
    $welcomeText .= "📱 Telefon raqamingizni yuboring:";
    
    $keyboard = [
        'keyboard' => [[
            ['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]
        ]],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ];
    
    sendTelegramMessage($chatId, $welcomeText, $keyboard);
    exit;
}

// Handle contact
if (isset($message['contact'])) {
    logMessage("Contact received");
    
    $phone = $message['contact']['phone_number'];
    $firstName = $from['first_name'] ?? 'Foydalanuvchi';
    
    $text = "✅ Ro'yxatdan o'tdingiz!\n\n";
    $text .= "Menuni ochish uchun tugmani bosing:";
    
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '🍽 Menuni ochish', 'web_app' => ['url' => WEBAPP_URL]]
        ]],
        'remove_keyboard' => true
    ];
    
    sendTelegramMessage($chatId, $text, $keyboard);
    exit;
}

// Handle regular text (phone number, address, etc.)
if (isset($message['text'])) {
    $text = $message['text'];
    logMessage("Text message: $text");
    
    // Simple phone validation
    if (preg_match('/^[\+]?[0-9\s\-\(\)]{9,}$/', $text)) {
        logMessage("Phone number detected: $text");
        sendTelegramMessage($chatId, "✅ Telefon raqam saqlandi: $text\n\n📍 Endi manzilingizni yuboring:");
    } else {
        logMessage("Regular text message: $text");
        sendTelegramMessage($chatId, "✅ Ma'lumot saqlandi: $text\n\nRahmat! Tez orada siz bilan bog'lanamiz.");
    }
}

logMessage("=== Webhook End ===");

function sendTelegramMessage($chatId, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
?>