<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db/UserRepo.php';
require_once __DIR__ . '/db/OrderRepo.php';

// Debug logging
function logDebug($message) {
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Session file helper
function getSessionFile($chatId) {
    $sessionsDir = __DIR__ . "/sessions";
    if (!is_dir($sessionsDir)) {
        mkdir($sessionsDir, 0755, true);
    }
    return $sessionsDir . "/order_session_{$chatId}.json";
}

logDebug("=== Webhook Called ===");

$input = file_get_contents('php://input');
logDebug("Raw input: " . $input);

$update = json_decode($input, true);
if (!$update) {
    logDebug("ERROR: No update data");
    exit;
}

// Handle Callback Query (Admin buttons)
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $data = $cb['data'];
    $chatId = $cb['message']['chat']['id'];
    $msgId = $cb['message']['message_id'];
    
    logDebug("Callback received: $data");
    
    $oRepo = new OrderRepo();
    
    if (strpos($data, 'st_') === 0) {
        // Status update: st_{orderId}_{status}
        list($prefix, $orderId, $status) = explode('_', $data);
        $oRepo->updateStatus((int)$orderId, $status);
        
        $order = $oRepo->findById((int)$orderId);
        $statusText = [
            'confirmed' => 'Tasdiqlangan ✅',
            'preparing' => 'Tayyorlanmoqda 👨‍🍳',
            'delivered' => 'Yetkazilgan 🚀',
            'cancelled' => 'Bekor qilingan ❌'
        ];
        
        $newText = $cb['message']['text'] . "\n\n➖➖➖➖➖➖\nHolat: " . ($statusText[$status] ?? $status);
        
        // Update admin message
        answerCallbackQuery($cb['id'], "Holat o'zgardi: " . $status);
        editMessageText($chatId, $msgId, $newText, [
            'inline_keyboard' => $cb['message']['reply_markup']['inline_keyboard']
        ]);
        
        // Notify customer
        if ($order) {
            $userRepo = new UserRepo();
            $user = $userRepo->db->prepare("SELECT telegram_id FROM users WHERE id = ?");
            $user->execute([$order['user_id']]);
            $userData = $user->fetch();
            if ($userData) {
                $statusMsg = "🔔 Buyurtmangiz #{$orderId} holati o'zgardi: <b>" . ($statusText[$status] ?? $status) . "</b>";
                sendTelegramMessage($userData['telegram_id'], $statusMsg);
            }
        }
    } elseif (strpos($data, 'tr_') === 0) {
        // Tracking: tr_{orderId}
        $orderId = substr($data, 3);
        $adminStateFile = __DIR__ . "/sessions/admin_state.json";
        file_put_contents($adminStateFile, json_encode(['expecting_tracking' => $orderId]));
        
        answerCallbackQuery($cb['id'], "Tracking havolasini yuboring");
        sendTelegramMessage($chatId, "📍 Buyurtma #{$orderId} uchun tracking havolasini (URL) yuboring:");
    }
    exit;
}

$message = $update['message'] ?? null;
if (!$message) exit;

$chatId = $message['chat']['id'];
$from = $message['from'];
$text = $message['text'] ?? '';

// Handle WebApp data
if (isset($message['web_app_data'])) {
    $orderData = json_decode($message['web_app_data']['data'], true);
    if ($orderData && isset($orderData['items'])) {
        $sessionFile = getSessionFile($chatId);
        file_put_contents($sessionFile, json_encode([
            'data' => $orderData,
            'user' => $from,
            'step' => 'phone',
            'timestamp' => time()
        ]));
        
        $summary = "📋 Buyurtma tarkibi:\n";
        foreach ($orderData['items'] as $item) {
            $summary .= "• {$item['name']} × {$item['quantity']} = " . number_format($item['price'] * $item['quantity'], 0, '.', ' ') . " so'm\n";
        }
        $summary .= "\n💰 Jami: " . number_format($orderData['total'], 0, '.', ' ') . " so'm\n\n";
        $summary .= "📱 Telefon raqamingizni yuboring:";
        
        sendTelegramMessage($chatId, $summary, [
            'keyboard' => [[['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]]],
            'resize_keyboard' => true, 'one_time_keyboard' => true
        ]);
    }
    exit;
}

// Admin /start or /admin
if (($text === '/start' || $text === '/admin') && $chatId == ADMIN_TELEGRAM_ID) {
    $oRepo = new OrderRepo();
    $activeOrders = $oRepo->getActiveOrders();
    
    $msg = "👨‍💻 Admin Panel\n\nFaol buyurtmalar: " . count($activeOrders) . "\n\n";
    foreach (array_slice($activeOrders, 0, 10) as $ord) {
        $msg .= "#{$ord['id']} - {$ord['first_name']} | " . number_format($ord['total_price'], 0, '.', ' ') . " so'm | {$ord['status']}\n";
    }
    
    sendTelegramMessage($chatId, $msg, [
        'inline_keyboard' => [[['text' => '🔄 Yangilash', 'callback_data' => 'admin_refresh']]]
    ]);
    exit;
}

// User /start
if ($text === '/start') {
    $welcomeText = "🍽 Olmazor Go ga xush kelibsiz!\n\nMenuni ochish uchun tugmani bosing:";
    sendTelegramMessage($chatId, $welcomeText, [
        'inline_keyboard' => [[['text' => '🍽 Menuni ochish', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]]],
        'remove_keyboard' => true
    ]);
    exit;
}

// Contact handling
if (isset($message['contact'])) {
    $phone = $message['contact']['phone_number'];
    $sessionFile = getSessionFile($chatId);
    if (file_exists($sessionFile)) {
        $sessionData = json_decode(file_get_contents($sessionFile), true);
        $sessionData['phone'] = $phone;
        $sessionData['step'] = 'address';
        file_put_contents($sessionFile, json_encode($sessionData));
        
        sendTelegramMessage($chatId, "✅ Raqam: $phone\n\n📍 Endi manzilingizni yuboring yoki joylashuvingizni ulashing:", [
            'keyboard' => [[['text' => '📍 Joylashuvni yuborish', 'request_location' => true]]],
            'resize_keyboard' => true, 'one_time_keyboard' => true
        ]);
    } else {
        // Just register user
        $uRepo = new UserRepo();
        $uRepo->create(['telegram_id' => $chatId, 'first_name' => $from['first_name'], 'last_name' => $from['last_name'] ?? '', 'username' => $from['username'] ?? '', 'phone_number' => $phone]);
        sendTelegramMessage($chatId, "✅ Ro'yxatdan o'tdingiz! Menuni ochish uchun /start ni bosing.");
    }
    exit;
}

// Location or Address Text handling
$sessionFile = getSessionFile($chatId);
if (file_exists($sessionFile)) {
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    
    if ($sessionData['step'] === 'address') {
        $address = isset($message['location']) ? "📍 GPS: {$message['location']['latitude']}, {$message['location']['longitude']}" : $text;
        $sessionData['address'] = $address;
        $sessionData['step'] = 'comment';
        file_put_contents($sessionFile, json_encode($sessionData));
        
        sendTelegramMessage($chatId, "✅ Manzil: $address\n\n💬 Izoh bormi? (Yo'q bo'lsa \"Yo'q\" deb yozing)");
    } elseif ($sessionData['step'] === 'comment') {
        $comment = (strtolower($text) === 'yo\'q' || strtolower($text) === 'yoq') ? '' : $text;
        
        // SAVE TO DB
        $uRepo = new UserRepo();
        $oRepo = new OrderRepo();
        
        $dbUserId = $uRepo->create([
            'telegram_id' => $chatId,
            'first_name' => $sessionData['user']['first_name'],
            'last_name' => $sessionData['user']['last_name'] ?? '',
            'username' => $sessionData['user']['username'] ?? '',
            'phone_number' => $sessionData['phone']
        ]);
        
        $orderId = $oRepo->create($dbUserId, (float)$sessionData['data']['total'], $comment, $sessionData['phone'], $sessionData['address']);
        $oRepo->addItems($orderId, $sessionData['data']['items']);
        
        $summary = "✅ Buyurtma #{$orderId} qabul qilindi!\n\n💰 Jami: " . number_format($sessionData['data']['total'], 0, '.', ' ') . " so'm\n📍 Manzil: {$sessionData['address']}";
        sendTelegramMessage($chatId, "🎉 Rahmat! Buyurtmangiz qabul qilindi.\n\n" . $summary);
        
        // Notify Admin
        $adminKeyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Tasdiqlash', 'callback_data' => "st_{$orderId}_confirmed"], ['text' => '👨‍🍳 Tayyorlash', 'callback_data' => "st_{$orderId}_preparing"]],
                [['text' => '🚀 Yetkazish', 'callback_data' => "st_{$orderId}_delivered"], ['text' => '📍 Tracking', 'callback_data' => "tr_{$orderId}"]]
            ]
        ];
        sendTelegramMessage(ADMIN_TELEGRAM_ID, "🆕 YANGI BUYURTMA #{$orderId}\n" . $summary . "\n👤 Mijoz: " . $sessionData['user']['first_name'], $adminKeyboard);
        
        unlink($sessionFile);
    }
} elseif ($chatId == ADMIN_TELEGRAM_ID) {
    // Admin state check
    $adminStateFile = __DIR__ . "/sessions/admin_state.json";
    if (file_exists($adminStateFile)) {
        $state = json_decode(file_get_contents($adminStateFile), true);
        if (isset($state['expecting_tracking'])) {
            $oRepo = new OrderRepo();
            $oRepo->updateTracking((int)$state['expecting_tracking'], $text);
            unlink($adminStateFile);
            sendTelegramMessage($chatId, "✅ #{$state['expecting_tracking']} uchun tracking link saqlandi!");
        }
    }
}

// Helpers
function sendTelegramMessage($chatId, $text, $keyboard = null) {
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true]);
    return curl_exec($ch);
}

function answerCallbackQuery($id, $text) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/answerCallbackQuery?callback_query_id=' . $id . '&text=' . urlencode($text);
    return file_get_contents($url);
}

function editMessageText($chatId, $msgId, $text, $keyboard = null) {
    $params = ['chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/editMessageText';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true]);
    return curl_exec($ch);
}
