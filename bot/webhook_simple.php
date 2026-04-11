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
        
        logMessage("Callback received in webhook_simple: $data");
        
        try {
            require_once __DIR__ . '/db/OrderRepo.php';
            $oRepo = new OrderRepo();
            
            if (strpos($data, 'st_') === 0) {
                list($prefix, $orderId, $status) = explode('_', $data);
                $oRepo->updateStatus((int)$orderId, $status);
                
                $statusText = [
                    'confirmed' => 'Tasdiqlangan ✅',
                    'preparing' => 'Tayyorlanmoqda 👨‍🍳',
                    'delivered' => 'Yetkazilgan 🚀',
                    'cancelled' => 'Bekor qilingan ❌'
                ];
                
                $newText = $cb['message']['text'] . "\n\n➖➖➖➖➖➖\nHolat: " . ($statusText[$status] ?? $status);
                
                // Update message
                $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/editMessageText';
                $params = [
                    'chat_id' => $chatId,
                    'message_id' => $msgId,
                    'text' => $newText,
                    'reply_markup' => json_encode($cb['message']['reply_markup'])
                ];
                file_get_contents($url . '?' . http_build_query($params));
            }
        } catch (Exception $e) {
            logMessage("Callback error: " . $e->getMessage());
        }
    }
    exit;
}

$chatId = $message['chat']['id'] ?? null;
$from = $message['from'] ?? null;

if (!$chatId || !$from) {
    logMessage("ERROR: Missing chat_id or from data");
    exit;
}

logMessage("Chat ID: $chatId");
logMessage("From: " . json_encode($from));

// Helper: get or create user
function getOrCreateUser($from) {
    try {
        require_once __DIR__ . '/db/UserRepo.php';
        $userRepo = new UserRepo();
        
        $user = $userRepo->findByTelegramId($from['id']);
        if (!$user) {
            logMessage("User not found, creating new user for telegram_id: " . $from['id']);
            $userData = [
                'telegram_id' => $from['id'],
                'first_name' => $from['first_name'] ?? '',
                'last_name' => $from['last_name'] ?? '',
                'username' => $from['username'] ?? '',
                'phone_number' => null
            ];
            $userId = $userRepo->create($userData);
            logMessage("New user created with ID: $userId");
            $user = $userRepo->findByTelegramId($from['id']);
        }
        return $user;
    } catch (Exception $e) {
        logMessage("ERROR in getOrCreateUser: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// Handle WebApp data
// ============================================================
if (isset($message['web_app_data'])) {
    logMessage("=== WebApp data received! ===");
    
    $webAppData = $message['web_app_data']['data'] ?? null;
    logMessage("WebApp data content: " . $webAppData);
    
    if (!$webAppData) {
        logMessage("ERROR: web_app_data->data is empty");
        sendTelegramMessage($chatId, "❌ Buyurtma ma'lumotlari bo'sh keldi. Qayta urinib ko'ring.");
        exit;
    }
    
    $orderData = json_decode($webAppData, true);
    logMessage("Parsed order data: " . json_encode($orderData));
    
    if (!$orderData || !isset($orderData['items']) || empty($orderData['items'])) {
        logMessage("ERROR: Invalid or empty order data");
        sendTelegramMessage($chatId, "❌ Buyurtma ma'lumotlari noto'g'ri. Qayta urinib ko'ring.");
        exit;
    }
    
    // Get or create user (don't block order if DB fails)
    $user = getOrCreateUser($from);
    
    // Save order to session
    $sessionFile = getSessionFile($chatId);
    $sessionData = [
        'data' => $orderData,
        'user' => $user ?? [
            'id' => null,
            'telegram_id' => $from['id'],
            'first_name' => $from['first_name'] ?? '',
            'last_name' => $from['last_name'] ?? '',
        ],
        'from' => $from,
        'step' => 'phone',
        'timestamp' => time()
    ];
    
    $saved = file_put_contents($sessionFile, json_encode($sessionData));
    logMessage("Order saved to session: " . ($saved ? "OK ($sessionFile)" : "FAILED"));
    
    // Build order summary
    $summary = "📋 Buyurtma qabul qilindi!\n\n";
    $summary .= "🛒 Buyurtma tarkibi:\n";
    
    foreach ($orderData['items'] as $item) {
        $itemName = $item['name'] ?? 'Nomalum';
        $itemQty = $item['quantity'] ?? 1;
        $itemPrice = $item['price'] ?? 0;
        $itemTotal = $itemPrice * $itemQty;
        $summary .= "  " . $itemName . " x " . $itemQty . " = " . number_format($itemTotal, 0, '.', ' ') . " so'm\n";
    }
    
    $total = $orderData['total'] ?? 0;
    $summary .= "\n💰 Jami: " . number_format($total, 0, '.', ' ') . " so'm\n\n";
    $summary .= "📱 Telefon raqamingizni yuboring:\nMasalan: +998901234567";
    
    // Send message (without HTML parse_mode to avoid issues)
    $response = sendTelegramMessage($chatId, $summary);
    logMessage("Order summary sent, response: " . $response);
    exit;
}

// ============================================================
// Handle Admin Commands
// ============================================================
$text = $message['text'] ?? '';
if (($text === '/start' || $text === '/admin') && (string)$chatId === (string)ADMIN_TELEGRAM_ID) {
    logMessage("Admin panel triggered in webhook_simple.php");
    try {
        require_once __DIR__ . '/db/OrderRepo.php';
        $oRepo = new OrderRepo();
        $orders = $oRepo->getActiveOrders();
        
        $msg = "👨‍💻 Admin Panel\n\nFaol buyurtmalar: " . count($orders) . "\n\n";
        foreach (array_slice($orders, 0, 10) as $ord) {
            $msg .= "#{$ord['id']} - {$ord['first_name']} | " . number_format($ord['total_price'], 0, '.', ' ') . " so'm | {$ord['status']}\n";
        }
        
        // Use inline keyboard for admin panel updates
        $keyboard = [
            'inline_keyboard' => [[['text' => '🔄 Yangilash', 'callback_data' => 'admin_refresh']]]
        ];
        
        sendTelegramMessage($chatId, $msg, $keyboard);
        exit;
    } catch (Exception $e) {
        logMessage("ERROR in admin panel: " . $e->getMessage());
    }
} elseif ($text === '/admin') {
    sendTelegramMessage($chatId, "❌ Kechirasiz, siz admin emassiz.");
    exit;
}

// ============================================================
// Handle /start command
// ============================================================
if ($text === '/start') {
    logMessage("Start command received");
    
    // Create or update user
    $user = getOrCreateUser($from);
    
    // Check if user already registered (has phone number)
    if ($user && !empty($user['phone_number'])) {
        logMessage("Returning user with phone: " . $user['phone_number']);
        
        $welcomeText = "🍽 Xush kelibsiz, " . ($from['first_name'] ?? '') . "!\n\n";
        $welcomeText .= "👇 Pastdagi tugmalardan birini tanlang:";
        
        $keyboard = [
            'keyboard' => [
                [['text' => '🍽 Menu', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]],
                [['text' => '🛒 Savat'], ['text' => '👤 Profil']]
            ],
            'resize_keyboard' => true,
        ];
        
        sendTelegramMessage($chatId, $welcomeText, $keyboard);
    } else {
        logMessage("New user, requesting phone number");
        
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
    }
    exit;
}

// ============================================================
// Handle contact
// ============================================================
if (isset($message['contact'])) {
    logMessage("Contact received");
    
    $phone = $message['contact']['phone_number'];
    logMessage("Phone number: $phone");
    
    // Update user with phone number
    try {
        require_once __DIR__ . '/db/UserRepo.php';
        $userRepo = new UserRepo();
        
        $userData = [
            'telegram_id' => $from['id'],
            'first_name' => $from['first_name'] ?? '',
            'last_name' => $from['last_name'] ?? '',
            'username' => $from['username'] ?? '',
            'phone_number' => $phone
        ];
        $userRepo->create($userData);
        logMessage("User phone number updated: $phone");
    } catch (Exception $e) {
        logMessage("ERROR updating user phone: " . $e->getMessage());
    }
    
    $text = "✅ Ro'yxatdan o'tdingiz!\n\n";
    $text .= "👇 Menuni ochish uchun pastdagi tugmani bosing:";
    
    // IMPORTANT: web_app must be in regular keyboard (not inline_keyboard)
    // because tg.sendData() only works with regular keyboard web apps
    $keyboard = [
        'keyboard' => [[
            ['text' => '🍽 Menuni ochish', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]
        ]],
        'resize_keyboard' => true,
    ];
    
    sendTelegramMessage($chatId, $text, $keyboard);
    exit;
}

// ============================================================
// Handle location
// ============================================================
if (isset($message['location'])) {
    logMessage("Location received");
    
    $lat = $message['location']['latitude'];
    $lon = $message['location']['longitude'];
    $address = "📍 GPS: $lat, $lon";
    
    $sessionFile = getSessionFile($chatId);
    if (file_exists($sessionFile)) {
        $sessionData = json_decode(file_get_contents($sessionFile), true);
        
        if ($sessionData && $sessionData['step'] === 'address') {
            logMessage("Location saved as address: $address");
            
            $sessionData['address'] = $address;
            $sessionData['step'] = 'confirm';
            file_put_contents($sessionFile, json_encode($sessionData));
            
            // Show order summary and ask for confirmation
            $confirmText = buildFinalSummary($sessionData);
            
            $keyboard = [
                'keyboard' => [
                    [['text' => '✅ Tasdiqlash']],
                    [['text' => '❌ Bekor qilish']]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ];
            
            sendTelegramMessage($chatId, $confirmText, $keyboard);
        } else {
            logMessage("Location received but step is: " . ($sessionData['step'] ?? 'unknown'));
        }
    } else {
        logMessage("Location received but no active session");
    }
    exit;
}

// ============================================================
// Handle regular text
// ============================================================
if (isset($message['text'])) {
    $text = trim($message['text']);
    logMessage("Text message: $text");
    
    // Skip commands
    if (strpos($text, '/') === 0) {
        logMessage("Unknown command: $text");
        sendTelegramMessage($chatId, "Noma'lum buyruq. /start buyrug'ini yuboring.");
        exit;
    }
    
    // ===== Handle Savat button =====
    if ($text === '🛒 Savat' || mb_strtolower($text) === 'savat') {
        logMessage("Cart button pressed");
        
        $sessionFile = getSessionFile($chatId);
        if (file_exists($sessionFile)) {
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            if ($sessionData && isset($sessionData['data']['items'])) {
                $cartText = "🛒 Sizning savatingiz:\n\n";
                foreach ($sessionData['data']['items'] as $item) {
                    $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                    $cartText .= "  " . ($item['name'] ?? '') . " x " . ($item['quantity'] ?? 1) . " = " . number_format($itemTotal, 0, '.', ' ') . " so'm\n";
                }
                $total = $sessionData['data']['total'] ?? 0;
                $cartText .= "\n💰 Jami: " . number_format($total, 0, '.', ' ') . " so'm";
                $cartText .= "\n\nBuyurtmani davom ettirish uchun kerakli ma'lumotlarni yuboring.";
                sendTelegramMessage($chatId, $cartText);
            } else {
                sendTelegramMessage($chatId, "🛒 Savatingiz bo'sh.\n\n🍽 Menuni ochib mahsulot tanlang!");
            }
        } else {
            sendTelegramMessage($chatId, "🛒 Savatingiz bo'sh.\n\n🍽 Menuni ochib mahsulot tanlang!");
        }
        exit;
    }
    
    // ===== Handle Profil button =====
    if ($text === '👤 Profil' || mb_strtolower($text) === 'profil') {
        logMessage("Profile button pressed");
        
        $user = getOrCreateUser($from);
        
        $profileText = "👤 Sizning profilingiz:\n\n";
        $profileText .= "📛 Ism: " . ($from['first_name'] ?? '') . " " . ($from['last_name'] ?? '') . "\n";
        if (!empty($from['username'])) {
            $profileText .= "📎 Username: @" . $from['username'] . "\n";
        }
        if ($user && !empty($user['phone_number'])) {
            $profileText .= "📱 Telefon: " . $user['phone_number'] . "\n";
        }
        if ($user && !empty($user['created_at'])) {
            $profileText .= "📅 Ro'yxatdan o'tgan: " . $user['created_at'] . "\n";
        }
        
        sendTelegramMessage($chatId, $profileText);
        exit;
    }
    
    // Check if there's an active order session
    $sessionFile = getSessionFile($chatId);
    if (file_exists($sessionFile)) {
        $sessionData = json_decode(file_get_contents($sessionFile), true);
        
        if (!$sessionData) {
            logMessage("ERROR: Could not parse session file");
            unlink($sessionFile);
            sendTelegramMessage($chatId, "❌ Xatolik yuz berdi. Iltimos, qayta buyurtma bering.");
            exit;
        }
        
        logMessage("Found order session, step: " . $sessionData['step']);
        
        // ===== STEP: Phone =====
        if ($sessionData['step'] === 'phone') {
            $cleanPhone = preg_replace('/[^\d+]/', '', $text);
            if (preg_match('/^(\+?998|998|8)?[0-9]{9}$/', $cleanPhone)) {
                logMessage("Valid phone number: $text");
                
                $sessionData['phone'] = $text;
                $sessionData['step'] = 'address';
                file_put_contents($sessionFile, json_encode($sessionData));
                
                $keyboard = [
                    'keyboard' => [[
                        ['text' => '📍 Joylashuvni yuborish', 'request_location' => true]
                    ]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ];
                
                sendTelegramMessage($chatId, 
                    "✅ Telefon raqam saqlandi: $text\n\n📍 Endi manzilingizni yuboring:\nMasalan: Toshkent sh., Olmazor t., 5-mavze, 12-uy\n\nYoki joylashuvingizni yuboring 👇", 
                    $keyboard
                );
            } else {
                logMessage("Invalid phone number: $text");
                sendTelegramMessage($chatId, "❌ Telefon raqam noto'g'ri formatda.\n\n📱 Iltimos, to'g'ri formatda yuboring:\nMasalan: +998901234567 yoki 998901234567");
            }
        }
        // ===== STEP: Address =====
        elseif ($sessionData['step'] === 'address') {
            logMessage("Address received: $text");
            
            $sessionData['address'] = $text;
            $sessionData['step'] = 'confirm';
            file_put_contents($sessionFile, json_encode($sessionData));
            
            // Show order summary and ask for confirmation
            $confirmText = buildFinalSummary($sessionData);
            
            $keyboard = [
                'keyboard' => [
                    [['text' => '✅ Tasdiqlash']],
                    [['text' => '❌ Bekor qilish']]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ];
            
            sendTelegramMessage($chatId, $confirmText, $keyboard);
        }
        // ===== STEP: Confirm =====
        elseif ($sessionData['step'] === 'confirm') {
            if (mb_strpos($text, 'Tasdiqlash') !== false || mb_strpos($text, '✅') !== false) {
                logMessage("Order confirmed!");
                
                // Try to save to database
                $orderId = null;
                try {
                    require_once __DIR__ . '/db/OrderRepo.php';
                    $orderRepo = new OrderRepo();
                    
                    $userId = $sessionData['user']['id'] ?? 0;
                    $total = $sessionData['data']['total'] ?? 0;
                    $comment = $sessionData['data']['comment'] ?? '';
                    $phone = $sessionData['phone'] ?? '';
                    $address = $sessionData['address'] ?? '';
                    
                    if ($userId > 0) {
                        $orderId = $orderRepo->create($userId, $total, $comment, $phone, $address);
                        $orderRepo->addItems($orderId, $sessionData['data']['items']);
                        logMessage("Order saved to DB with ID: $orderId");
                    } else {
                        $orderId = rand(1000, 9999);
                        logMessage("User has no DB ID, using random order ID: $orderId");
                    }
                } catch (Exception $e) {
                    logMessage("ERROR saving order to DB: " . $e->getMessage());
                    $orderId = rand(1000, 9999);
                }
                
                // Build final summary
                $firstName = $sessionData['from']['first_name'] ?? $sessionData['user']['first_name'] ?? '';
                $lastName = $sessionData['from']['last_name'] ?? $sessionData['user']['last_name'] ?? '';
                
                $finalSummary = "📋 Buyurtma #$orderId\n\n";
                $finalSummary .= "👤 Mijoz: $firstName $lastName\n";
                $finalSummary .= "📱 Telefon: " . ($sessionData['phone'] ?? 'N/A') . "\n";
                $finalSummary .= "📍 Manzil: " . ($sessionData['address'] ?? 'N/A') . "\n\n";
                $finalSummary .= "🛒 Buyurtma tarkibi:\n";
                
                foreach ($sessionData['data']['items'] as $item) {
                    $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                    $itemName = $item['name'] ?? 'Nomalum';
                    $finalSummary .= "  $itemName x " . ($item['quantity'] ?? 1) . " = " . number_format($itemTotal, 0, '.', ' ') . " so'm\n";
                }
                
                $total = $sessionData['data']['total'] ?? 0;
                $finalSummary .= "\n💰 Jami: " . number_format($total, 0, '.', ' ') . " so'm";
                
                $orderComment = $sessionData['data']['comment'] ?? '';
                if (!empty($orderComment)) {
                    $finalSummary .= "\n💬 Izoh: $orderComment";
                }
                
                $finalSummary .= "\n⏰ Vaqt: " . date('d.m.Y H:i');
                
                // Send to customer - use regular keyboard for web_app
                $keyboard = [
                    'keyboard' => [
                        [['text' => '🍽 Menu', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]],
                        [['text' => '🛒 Savat'], ['text' => '👤 Profil']]
                    ],
                    'resize_keyboard' => true,
                ];
                
                $customerResponse = sendTelegramMessage($chatId, 
                    "🎉 Buyurtmangiz muvaffaqiyatli qabul qilindi!\n\n" . 
                    $finalSummary . 
                    "\n\n⏰ Tayyorlanish vaqti: 15-20 daqiqa",
                    $keyboard
                );
                logMessage("Customer response: " . $customerResponse);
                
                // Send to admin
                $adminResponse = sendTelegramMessage(ADMIN_TELEGRAM_ID, "🆕 YANGI BUYURTMA #$orderId\n\n" . $finalSummary);
                logMessage("Admin notification: " . $adminResponse);
                
                // Clear session
                unlink($sessionFile);
                logMessage("Order completed and session cleared");
                
            } elseif (mb_strpos($text, 'Bekor') !== false || mb_strpos($text, '❌') !== false) {
                logMessage("Order cancelled by user");
                unlink($sessionFile);
                
                $keyboard = [
                    'keyboard' => [
                        [['text' => '🍽 Menu', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]],
                        [['text' => '🛒 Savat'], ['text' => '👤 Profil']]
                    ],
                    'resize_keyboard' => true,
                ];
                
                sendTelegramMessage($chatId, "❌ Buyurtma bekor qilindi.\n\nYangi buyurtma berish uchun menyuni oching 👇", $keyboard);
            } else {
                $keyboard = [
                    'keyboard' => [
                        [['text' => '✅ Tasdiqlash']],
                        [['text' => '❌ Bekor qilish']]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ];
                sendTelegramMessage($chatId, "Iltimos, buyurtmani tasdiqlang yoki bekor qiling 👇", $keyboard);
            }
        }
    } else {
        // No active session
        logMessage("No active session, treating as regular message");
        
        $keyboard = [
            'keyboard' => [
                [['text' => '🍽 Menu', 'web_app' => ['url' => WEBAPP_URL . '&tg_id=' . $chatId]]],
                [['text' => '🛒 Savat'], ['text' => '👤 Profil']]
            ],
            'resize_keyboard' => true,
        ];
        
        sendTelegramMessage($chatId, "Buyurtma berish uchun menyuni oching 👇", $keyboard);
    }
}

logMessage("=== Webhook End ===");

// ============================================================
// Helper functions
// ============================================================

function buildFinalSummary($sessionData) {
    $text = "📋 Buyurtma ma'lumotlari:\n\n";
    $text .= "📱 Telefon: " . ($sessionData['phone'] ?? 'N/A') . "\n";
    $text .= "📍 Manzil: " . ($sessionData['address'] ?? 'N/A') . "\n\n";
    $text .= "🛒 Tarkibi:\n";
    
    foreach ($sessionData['data']['items'] as $item) {
        $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        $itemName = $item['name'] ?? 'Nomalum';
        $text .= "  $itemName x " . ($item['quantity'] ?? 1) . " = " . number_format($itemTotal, 0, '.', ' ') . " so'm\n";
    }
    
    $total = $sessionData['data']['total'] ?? 0;
    $text .= "\n💰 Jami: " . number_format($total, 0, '.', ' ') . " so'm\n\n";
    $text .= "Buyurtmani tasdiqlaysizmi? 👇";
    
    return $text;
}

function sendTelegramMessage($chatId, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chatId,
        'text' => $text
        // No parse_mode to avoid HTML/Markdown parsing errors
    ];
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    
    logMessage("Sending message to $chatId, text length: " . strlen($text));
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        logMessage("CURL ERROR: $curlError");
    }
    
    logMessage("Telegram API response (HTTP $httpCode): $result");
    
    // Check if message was sent successfully
    $response = json_decode($result, true);
    if (!$response || !($response['ok'] ?? false)) {
        logMessage("WARNING: Message may not have been sent. Response: $result");
    }
    
    return $result;
}
?>