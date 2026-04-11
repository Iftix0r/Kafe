<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to Telegram
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';

// Simple logging
function logMessage($message) {
    $logFile = __DIR__ . '/simple_debug.log';
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

logMessage("=== Webhook Called ===");

// Always respond 200 OK to Telegram immediately
http_response_code(200);

$input = file_get_contents('php://input');
logMessage("Raw input: " . $input);

$update = json_decode($input, true);
if (!$update) {
    logMessage("ERROR: No update data or invalid JSON");
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    if (isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $data = $cb['data'];
        $chatId = $cb['message']['chat']['id'];
        $msgId = $cb['message']['message_id'];
        
        logMessage("Callback received: $data");
        
        try {
            require_once __DIR__ . '/db/OrderRepo.php';
            $oRepo = new OrderRepo();
            
            if (strpos($data, 'st_') === 0) {
                list($prefix, $orderId, $status) = explode('_', $data);
                $oRepo->updateStatus((int)$orderId, $status);
                
                $statusText = [
                    'confirmed' => 'Tasdiqlangan ✅',
                    'preparing' => 'Tayyorlanmoqda 👨‍🍳',
                    'on_way' => 'Yo\'lda 🚀',
                    'delivered' => 'Yetkazildi ✅',
                    'cancelled' => 'Bekor qilingan ❌'
                ];
                
                $newText = $cb['message']['text'] . "\n\n➖➖➖➖➖➖\nOxirgi holat: " . ($statusText[$status] ?? $status);
                
                $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/editMessageText';
                $params = [
                    'chat_id' => $chatId,
                    'message_id' => $msgId,
                    'text' => $newText,
                    'reply_markup' => json_encode($cb['message']['reply_markup'])
                ];
                file_get_contents($url . '?' . http_build_query($params));

                // Notify Customer
                $order = $oRepo->findById((int)$orderId);
                if ($order && $order['user_id']) {
                    require_once __DIR__ . '/db/UserRepo.php';
                    $db = Database::get();
                    $uStmt = $db->prepare("SELECT telegram_id FROM users WHERE id = ?");
                    $uStmt->execute([$order['user_id']]);
                    if ($userData = $uStmt->fetch()) {
                        $customerMsg = "🔔 Buyurtmangiz #{$orderId} holati o'zgardi: <b>" . ($statusText[$status] ?? $status) . "</b>";
                        sendTelegramMessage($userData['telegram_id'], $customerMsg);
                    }
                }
            } elseif (strpos($data, 'tr_') === 0) {
                $orderId = substr($data, 3);
                file_put_contents(__DIR__ . "/sessions/admin_state.json", json_encode(['expecting_tracking' => $orderId, 'admin_chat_id' => $chatId]));
                $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/answerCallbackQuery';
                file_get_contents($url . '?' . http_build_query(['callback_query_id' => $cb['id'], 'text' => "Tracking linkini yuboring"]));
                sendTelegramMessage($chatId, "📍 Buyurtma #{$orderId} uchun tracking havolasini yuboring:");
            }
        } catch (Exception $e) { logMessage("CB Error: " . $e->getMessage()); }
        
        // Always answer callback query to stop loading spinner
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/answerCallbackQuery';
        file_get_contents($url . '?' . http_build_query(['callback_query_id' => $cb['id']]));
    }
    exit;
}

$chatId = $message['chat']['id'] ?? null;
$from = $message['from'] ?? null;
if (!$chatId || !$from) exit;

// Helper: get or create user
function getOrCreateUser($from) {
    try {
        require_once __DIR__ . '/db/UserRepo.php';
        $userRepo = new UserRepo();
        $user = $userRepo->findByTelegramId($from['id']);
        if (!$user) {
            logMessage("User not found, creating new user for telegram_id: " . $from['id']);
            $userRepo->create(['telegram_id' => $from['id'], 'first_name' => $from['first_name'] ?? '', 'last_name' => $from['last_name'] ?? '', 'username' => $from['username'] ?? '']);
            $user = $userRepo->findByTelegramId($from['id']);
        }
        return $user;
    } catch (Exception $e) { 
        logMessage("getOrCreateUser Error: " . $e->getMessage());
        return null; 
    }
}

// Admin Logic
$text = $message['text'] ?? '';
if (($text === '/start' || $text === '/admin') && (string)$chatId === (string)ADMIN_TELEGRAM_ID) {
    require_once __DIR__ . '/db/OrderRepo.php';
    $oRepo = new OrderRepo();
    $orders = $oRepo->getActiveOrders();
    $msg = "👨‍💻 Admin Panel\n\nActive: " . count($orders);
    sendTelegramMessage($chatId, $msg, ['inline_keyboard' => [[['text' => '🔄 Refresh', 'callback_data' => 'admin_refresh']]]]);
    exit;
}

// WebApp data Handling
if (isset($message['web_app_data'])) {
    $rawData = $message['web_app_data']['data'] ?? '';
    logMessage("WebApp data received: " . $rawData);
    $orderData = json_decode($rawData, true);
    
    if ($orderData) {
        logMessage("Parsed items: " . json_encode($orderData['items'] ?? []));
        $sessionFile = getSessionFile($chatId);
        file_put_contents($sessionFile, json_encode(['data' => $orderData, 'user' => $from, 'step' => 'phone', 'timestamp' => time()]));
        $summary = "🛒 Buyurtma tarkibi:\n";
        foreach (($orderData['items'] ?? []) as $item) {
            $name = $item['name'] ?? 'Noma\'lum';
            $qty = $item['quantity'] ?? 0;
            $price = $item['price'] ?? 0;
            $summary .= "• " . htmlspecialchars($name) . " x " . $qty . " = " . number_format($price * $qty, 0, '.', ' ') . " so'm\n";
        }
        $summary .= "\n💰 Jami: " . number_format($orderData['total'] ?? 0, 0, '.', ' ') . " so'm\n\n📱 Telefon raqamingizni yuboring:";
        sendTelegramMessage($chatId, $summary, ['keyboard' => [[['text' => '📱 Telefon yuborish', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true]);
    }
    exit;
}

// Register Flow
if ($text === '/start') {
    logMessage("Start command received from $chatId");
    $user = getOrCreateUser($from);
    
    // Always Notify Group about who pressed /start
    try {
        require_once __DIR__ . '/db/UserRepo.php';
        $userRepo = new UserRepo();
        $userCount = $userRepo->countAll();
        $fullName = ($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '');
        $username = !empty($from['username']) ? "@" . $from['username'] : "mavjud emas";
        
        $msg = "📢 <b>Foydalanuvchi botni boshladi!</b>\n\n";
        $msg .= "👤 Ism: " . htmlspecialchars($fullName) . "\n";
        $msg .= "🔗 Username: " . htmlspecialchars($username) . "\n";
        $msg .= "📊 Jami foydalanuvchilar: <b>" . $userCount . "</b>";
        
        $kb = [
            'inline_keyboard' => [[
                ['text' => '👤 Profilga o\'tish', 'url' => 'tg://user?id=' . $from['id']]
            ]]
        ];
        
        sendTelegramMessage(ORDER_GROUP_ID, $msg, $kb);
    } catch (Exception $e) { logMessage("Notify Error: " . $e->getMessage()); }

    if ($user && !empty($user['phone_number'])) {
        sendTelegramMessage($chatId, "🍽 Xush kelibsiz!", ['keyboard' => [[['text' => '🍽 Menu', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]], [['text' => '🛒 Savat'], ['text' => '👤 Profil']]], 'resize_keyboard' => true]);
    } else {
        sendTelegramMessage($chatId, "📱 Telefon raqamingizni yuboring:", ['keyboard' => [[['text' => '📱 Telefon yuborish', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true]);
    }
    exit;
}

// Session steps
$sessionFile = getSessionFile($chatId);
if (file_exists($sessionFile)) {
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    
    if ($sessionData['step'] === 'phone') {
        $phone = isset($message['contact']) ? $message['contact']['phone_number'] : $text;
        logMessage("Saving phone for $chatId: $phone");
        
        // Also update user profile with phone
        try {
            require_once __DIR__ . '/db/UserRepo.php';
            (new UserRepo())->create(['telegram_id' => $from['id'], 'first_name' => $from['first_name'], 'phone_number' => $phone]);
        } catch (Exception $e) {}

        $sessionData['phone'] = $phone; $sessionData['step'] = 'address';
        file_put_contents($sessionFile, json_encode($sessionData));
        sendTelegramMessage($chatId, "✅ Raqam saqlandi: $phone\n\n📍 Endi manzilingizni yuboring:", ['keyboard' => [[['text' => '📍 Joylashuv', 'request_location' => true]]], 'resize_keyboard' => true]);
        exit;
    } elseif ($sessionData['step'] === 'address') {
        logMessage("Saving address for $chatId: $text");
        $address = isset($message['location']) ? "📍 GPS: {$message['location']['latitude']}, {$message['location']['longitude']}" : $text;
        $sessionData['address'] = $address; $sessionData['step'] = 'confirm';
        file_put_contents($sessionFile, json_encode($sessionData));
        $summary = "📋 Buyurtma ma'lumotlari:\n📍 Manzil: $address\n💰 Jami: " . number_format($sessionData['data']['total'], 0, '.', ' ') . " so'm\n\nTasdiqlaysizmi?";
        sendTelegramMessage($chatId, $summary, ['keyboard' => [[['text' => '✅ Tasdiqlash']], [['text' => '❌ Bekor qilish']]], 'resize_keyboard' => true]);
    } elseif ($sessionData['step'] === 'confirm') {
        if ($text === '✅ Tasdiqlash') {
            logMessage("Order confirmation received for $chatId");
            try {
                require_once __DIR__ . '/db/OrderRepo.php';
                require_once __DIR__ . '/db/UserRepo.php';
                $oRepo = new OrderRepo();
                $uRepo = new UserRepo();
                
                $user = $uRepo->findByTelegramId($chatId);
                if (!$user) {
                    logMessage("User not found in DB during confirm, creating...");
                    $uId = $uRepo->create(['telegram_id' => $chatId, 'first_name' => $from['first_name'] ?? 'Mijoz']);
                    $user = ['id' => $uId];
                }
                
                $orderId = $oRepo->create($user['id'], $sessionData['data']['total'], '', $sessionData['phone'], $sessionData['address']);
                $oRepo->addItems($orderId, $sessionData['data']['items']);
                logMessage("Order #$orderId created in DB");
                
                sendTelegramMessage($chatId, "🎉 Buyurtma #$orderId qabul qilindi!");
                
                // Build Item List for Group
                $itemsList = "";
                foreach ($sessionData['data']['items'] as $item) {
                    $itemsList .= "• " . ($item['name'] ?? 'Noma\'lum') . " x " . ($item['quantity'] ?? 1) . "\n";
                }

                $groupKb = ['inline_keyboard' => [
                    [['text' => '✅ Tasdiqlash', 'callback_data' => "st_{$orderId}_confirmed"], ['text' => '👨‍🍳 Tayyorlash', 'callback_data' => "st_{$orderId}_preparing"]],
                    [['text' => '🚀 Yo\'lda', 'callback_data' => "st_{$orderId}_on_way"], ['text' => '✅ Yetkazildi', 'callback_data' => "st_{$orderId}_delivered"]],
                    [['text' => '❌ Bekor qilish', 'callback_data' => "st_{$orderId}_cancelled"], ['text' => '📍 Tracking', 'callback_data' => "tr_{$orderId}"]]
                ]];
                
                $groupMsg = "🆕 <b>YANGI BUYURTMA #$orderId</b>\n\n";
                $groupMsg .= "👤 Mijoz: <b>" . ($from['first_name'] ?? 'Noma\'lum') . "</b>\n";
                $groupMsg .= "📱 Tel: <code>{$sessionData['phone']}</code>\n";
                $groupMsg .= "📍 Manzil: <i>{$sessionData['address']}</i>\n\n";
                $groupMsg .= "🛒 <b>Tarkibi:</b>\n$itemsList\n";
                $groupMsg .= "💰 Jami: <b>" . number_format($sessionData['data']['total'], 0, '.', ' ') . " so'm</b>";
                
                $res = sendTelegramMessage(ORDER_GROUP_ID, $groupMsg, $groupKb);
                logMessage("Group send result to " . ORDER_GROUP_ID . ": " . $res);
                
                unlink($sessionFile);
            } catch (Exception $e) {
                logMessage("Order Creation ERROR: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Buyurtma saqlashda xatolik yuz berdi. Iltimos qayta urinib ko'ring.");
            }
        } else {
            logMessage("Order cancelled for $chatId");
            unlink($sessionFile);
            sendTelegramMessage($chatId, "❌ Bekor qilindi.");
        }
    }
    exit;
}

function sendTelegramMessage($chatId, $text, $keyboard = null) {
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    return curl_exec($ch);
}