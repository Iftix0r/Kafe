<?php
require_once __DIR__ . '/config.php';

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

function getUpdates($offset = 0) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getUpdates';
    $params = [
        'offset' => $offset,
        'timeout' => 30,
        'limit' => 100
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

function processUpdate($update) {
    logDebug("Processing update: " . json_encode($update));
    
    $message = $update['message'] ?? null;
    if (!$message) {
        logDebug("No message in update");
        return;
    }

    $chatId = $message['chat']['id'];
    $from = $message['from'];

    logDebug("Chat ID: $chatId, From: " . json_encode($from));

    // Handle WebApp data
    if (isset($message['web_app_data'])) {
        logDebug("WebApp data received!");
        
        $webAppData = $message['web_app_data']['data'];
        logDebug("WebApp data content: " . $webAppData);
        
        $orderData = json_decode($webAppData, true);
        logDebug("Parsed order data: " . json_encode($orderData));
        
        if ($orderData && isset($orderData['items'])) {
            // Save order to session
            $sessionFile = getSessionFile($chatId);
            $sessionData = [
                'data' => $orderData,
                'user' => $from,
                'step' => 'phone',
                'timestamp' => time()
            ];
            file_put_contents($sessionFile, json_encode($sessionData));
            logDebug("Order saved to session: " . $sessionFile);
            
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
            logDebug("Message sent, response: " . $response);
        } else {
            logDebug("ERROR: Invalid order data");
            sendTelegramMessage($chatId, "❌ Buyurtma ma'lumotlari noto'g'ri");
        }
        return;
    }

    // Handle /start command
    if (isset($message['text']) && $message['text'] === '/start') {
        logDebug("Start command received");
        
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
        return;
    }

    // Handle contact
    if (isset($message['contact'])) {
        logDebug("Contact received");
        
        $phone = $message['contact']['phone_number'];
        logDebug("Phone number: $phone");
        
        $text = "✅ Ro'yxatdan o'tdingiz!\n\n";
        $text .= "Menuni ochish uchun tugmani bosing:";
        
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '🍽 Menuni ochish', 'web_app' => ['url' => WEBAPP_URL]]
            ]],
            'remove_keyboard' => true
        ];
        
        sendTelegramMessage($chatId, $text, $keyboard);
        return;
    }

    // Handle regular text (phone number, address, etc.)
    if (isset($message['text'])) {
        $text = $message['text'];
        logDebug("Text message: $text");
        
        // Check if there's an active order session
        $sessionFile = getSessionFile($chatId);
        if (file_exists($sessionFile)) {
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            logDebug("Found order session, step: " . $sessionData['step']);
            
            if ($sessionData['step'] === 'phone') {
                // Validate phone number
                $cleanPhone = preg_replace('/[^\d+]/', '', $text);
                if (preg_match('/^(\+?998|998|8)?[0-9]{9}$/', $cleanPhone)) {
                    logDebug("Valid phone number: $text");
                    
                    // Save phone to session
                    $sessionData['phone'] = $text;
                    $sessionData['step'] = 'address';
                    file_put_contents($sessionFile, json_encode($sessionData));
                    
                    // Ask for address
                    $keyboard = [
                        'keyboard' => [[
                            ['text' => '📍 Joylashuvni yuborish', 'request_location' => true]
                        ]],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true,
                    ];
                    
                    sendTelegramMessage($chatId, 
                        "✅ Telefon raqam saqlandi: $text\n\n📍 Endi manzilingizni yuboring:\nMasalan: Toshkent sh., Yunusobod t., 5-mavze, 12-uy\n\nYoki joylashuvingizni yuboring 👇", 
                        $keyboard
                    );
                } else {
                    logDebug("Invalid phone number: $text");
                    sendTelegramMessage($chatId, "❌ Telefon raqam noto'g'ri formatda.\n\n📱 Iltimos, to'g'ri formatda yuboring:\nMasalan: +998901234567 yoki 998901234567");
                }
            } elseif ($sessionData['step'] === 'address') {
                logDebug("Address received: $text");
                
                // Save address to session
                $sessionData['address'] = $text;
                $sessionData['step'] = 'comment';
                file_put_contents($sessionFile, json_encode($sessionData));
                
                // Ask for comment
                $keyboard = [
                    'keyboard' => [[
                        ['text' => 'Yo\'q']
                    ]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ];
                
                sendTelegramMessage($chatId, 
                    "✅ Manzil saqlandi: $text\n\n💬 Qo'shimcha izoh bor mi?\nMasalan: 3-qavat, 12-xonadon. Kamroq tuz qo'shing.\n\nIzoh yo'q bo'lsa \"Yo'q\" deb yozing.",
                    $keyboard
                );
            } elseif ($sessionData['step'] === 'comment') {
                logDebug("Comment received: $text");
                
                $comment = (strtolower($text) === 'yo\'q' || strtolower($text) === 'yoq') ? '' : $text;
                
                // Build final summary
                $orderId = rand(1000, 9999);
                
                $finalSummary = "📋 Buyurtma #{$orderId}\n\n";
                $finalSummary .= "👤 Mijoz: {$sessionData['user']['first_name']} {$sessionData['user']['last_name']}\n";
                $finalSummary .= "📱 Telefon: {$sessionData['phone']}\n";
                $finalSummary .= "📍 Manzil: {$sessionData['address']}\n\n";
                $finalSummary .= "🛒 Buyurtma tarkibi:\n";
                
                foreach ($sessionData['data']['items'] as $item) {
                    $itemTotal = $item['price'] * $item['quantity'];
                    $finalSummary .= "• {$item['name']} × {$item['quantity']} = " . number_format($itemTotal, 0, '.', ' ') . " so'm\n";
                }
                
                $finalSummary .= "\n💰 Jami: " . number_format($sessionData['data']['total'], 0, '.', ' ') . " so'm";
                
                if (!empty($comment)) {
                    $finalSummary .= "\n💬 Izoh: $comment";
                }
                
                $finalSummary .= "\n⏰ Vaqt: " . date('d.m.Y H:i');
                
                // Send to customer
                sendTelegramMessage($chatId, 
                    "🎉 Buyurtmangiz muvaffaqiyatli qabul qilindi!\n\n" . 
                    $finalSummary . 
                    "\n\n⏰ Tayyorlanish vaqti: 15-20 daqiqa\n📞 Aloqa: +998 90 123 45 67"
                );
                
                // Send to admin
                sendTelegramMessage(ADMIN_TELEGRAM_ID, "🆕 YANGI BUYURTMA #{$orderId}\n\n" . $finalSummary);
                
                // Clear session
                unlink($sessionFile);
                logDebug("Order completed and session cleared");
            }
        } else {
            // No active session - treat as regular message
            logDebug("No active session, treating as regular message");
            sendTelegramMessage($chatId, "Buyurtma berish uchun /start buyrug'ini yuboring va menyuni oching.");
        }
    }

    // Handle location
    if (isset($message['location'])) {
        logDebug("Location received");
        
        $lat = $message['location']['latitude'];
        $lon = $message['location']['longitude'];
        $address = "📍 GPS: $lat, $lon";
        
        // Check if there's an active order session
        $sessionFile = getSessionFile($chatId);
        if (file_exists($sessionFile)) {
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            
            if ($sessionData['step'] === 'address') {
                logDebug("Location saved as address: $address");
                
                // Save address to session
                $sessionData['address'] = $address;
                $sessionData['step'] = 'comment';
                file_put_contents($sessionFile, json_encode($sessionData));
                
                // Ask for comment
                $keyboard = [
                    'keyboard' => [[
                        ['text' => 'Yo\'q']
                    ]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ];
                
                sendTelegramMessage($chatId, 
                    "✅ Manzil saqlandi: $address\n\n💬 Qo'shimcha izoh bor mi?\nMasalan: 3-qavat, 12-xonadon. Kamroq tuz qo'shing.\n\nIzoh yo'q bo'lsa \"Yo'q\" deb yozing.",
                    $keyboard
                );
            }
        }
    }
}

// Main polling loop
logDebug("=== Polling Bot Started ===");

$offset = 0;

while (true) {
    try {
        logDebug("Getting updates with offset: $offset");
        
        $response = getUpdates($offset);
        
        if (!$response || !$response['ok']) {
            logDebug("ERROR: Failed to get updates: " . json_encode($response));
            sleep(5);
            continue;
        }
        
        $updates = $response['result'];
        logDebug("Received " . count($updates) . " updates");
        
        foreach ($updates as $update) {
            $offset = $update['update_id'] + 1;
            processUpdate($update);
        }
        
        if (empty($updates)) {
            // No new updates, wait a bit
            sleep(1);
        }
        
    } catch (Exception $e) {
        logDebug("ERROR: " . $e->getMessage());
        sleep(5);
    }
}
?>