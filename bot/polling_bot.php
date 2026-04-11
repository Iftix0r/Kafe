<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db/UserRepo.php';
require_once __DIR__ . '/db/OrderRepo.php';

// Debug logging
function logDebug($message) {
    $logFile = __DIR__ . '/polling_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

// Session file helper
function getSessionFile($chatId) {
    $sessionsDir = __DIR__ . "/sessions";
    if (!is_dir($sessionsDir)) {
        mkdir($sessionsDir, 0755, true);
    }
    return $sessionsDir . "/order_session_{$chatId}.json";
}

function sendTelegramMessage($chatId, $text, $keyboard = null) {
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
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

function getUpdates($offset = 0) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getUpdates';
    $params = ['offset' => $offset, 'timeout' => 30, 'limit' => 100];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 35]);
    return json_decode(curl_exec($ch), true);
}

function processUpdate($update) {
    // Handle Callback Query (Admin buttons)
    if (isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $data = $cb['data'];
        $chatId = $cb['message']['chat']['id'];
        $msgId = $cb['message']['message_id'];
        $oRepo = new OrderRepo();
        
        if (strpos($data, 'st_') === 0) {
            list($prefix, $orderId, $status) = explode('_', $data);
            $oRepo->updateStatus((int)$orderId, $status);
            $order = $oRepo->findById((int)$orderId);
            $statusText = ['confirmed' => 'Tasdiqlangan ✅', 'preparing' => 'Tayyorlanmoqda 👨‍🍳', 'delivered' => 'Yetkazilgan 🚀', 'cancelled' => 'Bekor qilingan ❌'];
            $newText = $cb['message']['text'] . "\n\n➖➖➖➖➖➖\nHolat: " . ($statusText[$status] ?? $status);
            answerCallbackQuery($cb['id'], "Holat o'zgardi");
            editMessageText($chatId, $msgId, $newText, ['inline_keyboard' => $cb['message']['reply_markup']['inline_keyboard']]);
            if ($order) {
                $uRepo = new UserRepo();
                $uData = $uRepo->db->prepare("SELECT telegram_id FROM users WHERE id = ?");
                $uData->execute([$order['user_id']]);
                if ($user = $uData->fetch()) sendTelegramMessage($user['telegram_id'], "🔔 Buyurtmangiz #{$orderId} holati: <b>" . ($statusText[$status] ?? $status) . "</b>");
            }
        } elseif (strpos($data, 'tr_') === 0) {
            $orderId = substr($data, 3);
            file_put_contents(__DIR__ . "/sessions/admin_state.json", json_encode(['expecting_tracking' => $orderId]));
            answerCallbackQuery($cb['id'], "Tracking kutilmoqda");
            sendTelegramMessage($chatId, "📍 Buyurtma #{$orderId} uchun tracking linkni yuboring:");
        }
        return;
    }

    $message = $update['message'] ?? null;
    if (!$message) return;

    $chatId = $message['chat']['id'];
    $from = $message['from'];
    $text = $message['text'] ?? '';

    // Handle WebApp data
    if (isset($message['web_app_data'])) {
        $orderData = json_decode($message['web_app_data']['data'], true);
        if ($orderData && isset($orderData['items'])) {
            $sessionFile = getSessionFile($chatId);
            file_put_contents($sessionFile, json_encode(['data' => $orderData, 'user' => $from, 'step' => 'phone', 'timestamp' => time()]));
            
            $summary = "📋 Buyurtma tarkibi:\n";
            foreach ($orderData['items'] as $item) $summary .= "• {$item['name']} × {$item['quantity']} = " . number_format($item['price'] * $item['quantity'], 0, '.', ' ') . " so'm\n";
            $summary .= "\n💰 Jami: " . number_format($orderData['total'], 0, '.', ' ') . " so'm\n\n📱 Telefon raqamingizni yuboring:";
            
            sendTelegramMessage($chatId, $summary, ['keyboard' => [[['text' => '📱 Telefon yuborish', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true]);
        }
        return;
    }

    // Admin /start
    logDebug("Checking admin: chatId=$chatId, adminId=" . ADMIN_TELEGRAM_ID);
    if ($text === '/start' && (string)$chatId === (string)ADMIN_TELEGRAM_ID) {
        logDebug("Admin panel triggered");
        $oRepo = new OrderRepo();
        $orders = $oRepo->getActiveOrders();
        $msg = "👨‍💻 Admin Panel\n\nFaol buyurtmalar: " . count($orders) . "\n\n";
        foreach (array_slice($orders, 0, 10) as $ord) {
            $msg .= "#{$ord['id']} - {$ord['first_name']} | " . number_format($ord['total_price'], 0, '.', ' ') . " so'm | {$ord['status']}\n";
        }
        sendTelegramMessage($chatId, $msg, [
            'inline_keyboard' => [[['text' => '🔄 Yangilash', 'callback_data' => 'admin_refresh']]]
        ]);
        return;
    }

    // Start
    if ($text === '/start') {
        logDebug("Normal start triggered");
        sendTelegramMessage($chatId, "🍽 Olmazor Go ga xush kelibsiz!", [
            'inline_keyboard' => [[['text' => '🍽 Menu', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]]],
            'remove_keyboard' => true
        ]);
        return;
    }

    // Contact
    if (isset($message['contact'])) {
        logDebug("Contact received from $chatId");
        $phone = $message['contact']['phone_number'];
        $sessionFile = getSessionFile($chatId);
        if (file_exists($sessionFile)) {
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            $sessionData['phone'] = $phone;
            $sessionData['step'] = 'address';
            file_put_contents($sessionFile, json_encode($sessionData));
            sendTelegramMessage($chatId, "✅ Raqam: $phone\n\n📍 Manzilni yozing yoki joylashuvingizni yuboring:", [
                'keyboard' => [[['text' => '📍 Joylashuv', 'request_location' => true]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ]);
        } else {
            // Just welcome message if no session
            sendTelegramMessage($chatId, "✅ Rahmat! Ro'yxatdan o'tdingiz. Menuni ochish uchun /start ni bosing.");
        }
        return;
    }

    // Address/Comment
    $sessionFile = getSessionFile($chatId);
    if (file_exists($sessionFile)) {
        logDebug("Session exists for $chatId, step: " . (json_decode(file_get_contents($sessionFile), true)['step'] ?? 'unknown'));
        $sessionData = json_decode(file_get_contents($sessionFile), true);
        if ($sessionData['step'] === 'address') {
            $address = isset($message['location']) ? "📍 GPS: {$message['location']['latitude']}, {$message['location']['longitude']}" : $text;
            $sessionData['address'] = $address;
            $sessionData['step'] = 'comment';
            file_put_contents($sessionFile, json_encode($sessionData));
            sendTelegramMessage($chatId, "✅ Manzil: $address\n\n💬 Izoh (ixtiyoriy, yo'q bo'lsa \"Yo'q\"):");
        } elseif ($sessionData['step'] === 'comment') {
            $comment = (strtolower($text) === 'yo\'q' || strtolower($text) === 'yoq') ? '' : $text;
            $uRepo = new UserRepo(); $oRepo = new OrderRepo();
            $dbUid = $uRepo->create(['telegram_id' => $chatId, 'first_name' => $from['first_name'], 'last_name' => $from['last_name'] ?? '', 'username' => $from['username'] ?? '', 'phone_number' => $sessionData['phone']]);
            $orderId = $oRepo->create($dbUid, (float)$sessionData['data']['total'], $comment, $sessionData['phone'], $sessionData['address']);
            $oRepo->addItems($orderId, $sessionData['data']['items']);
            $summary = "✅ #{$orderId} qabul qilindi! " . number_format($sessionData['data']['total'], 0, '.', ' ') . " so'm";
            sendTelegramMessage($chatId, "🎉 Rahmat! " . $summary);
            $adminKb = ['inline_keyboard' => [[['text' => '✅ Tasdiqlash', 'callback_data' => "st_{$orderId}_confirmed"], ['text' => '👨‍🍳 Tayyorlash', 'callback_data' => "st_{$orderId}_preparing"]], [['text' => '🚀 Yetkazish', 'callback_data' => "st_{$orderId}_delivered"], ['text' => '📍 Tracking', 'callback_data' => "tr_{$orderId}"]]]];
            sendTelegramMessage(ADMIN_TELEGRAM_ID, "🆕 BUYURTMA #{$orderId}\n" . $summary, $adminKb);
            unlink($sessionFile);
        }
    } elseif ($chatId == ADMIN_TELEGRAM_ID) {
        $adminStateFile = __DIR__ . "/sessions/admin_state.json";
        if (file_exists($adminStateFile)) {
            $state = json_decode(file_get_contents($adminStateFile), true);
            if (isset($state['expecting_tracking'])) {
                $oRepo = new OrderRepo();
                $oRepo->updateTracking((int)$state['expecting_tracking'], $text);
                unlink($adminStateFile);
                sendTelegramMessage($chatId, "✅ Tracking saqlandi!");
            }
        }
    }
}

logDebug("=== Polling Started ===");
$offset = 0;
while (true) {
    try {
        $resp = getUpdates($offset);
        if ($resp && $resp['ok']) {
            foreach ($resp['result'] as $upd) {
                $offset = $upd['update_id'] + 1;
                processUpdate($upd);
            }
        }
        if (empty($resp['result'])) sleep(1);
    } catch (Exception $e) { logDebug("ERR: " . $e->getMessage()); sleep(5); }
}