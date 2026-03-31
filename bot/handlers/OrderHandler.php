<?php
require_once __DIR__ . '/../db/UserRepo.php';
require_once __DIR__ . '/../db/OrderRepo.php';
require_once __DIR__ . '/../config.php';

class OrderHandler {
    private UserRepo $userRepo;
    private OrderRepo $orderRepo;

    public function __construct() {
        $this->userRepo = new UserRepo();
        $this->orderRepo = new OrderRepo();
    }

    public function handle(array $update): void {
        $from   = $update['message']['from'];
        $chatId = $update['message']['chat']['id'];
        $data   = json_decode($update['message']['web_app_data']['data'], true);

        if (!$data || empty($data['items'])) {
            $this->sendMessage($chatId, "❌ Buyurtma ma'lumotlari noto'g'ri. Iltimos, qayta urinib ko'ring.");
            return;
        }

        $user = $this->userRepo->findByTelegramId($from['id']);
        if (!$user) {
            $this->sendMessage($chatId, "❌ Foydalanuvchi topilmadi. Iltimos, /start buyrug'ini yuboring.");
            return;
        }

        try {
            // Buyurtma ma'lumotlarini sessiyaga saqlash
            $this->saveOrderToSession($chatId, $data, $user);
            
            // Buyurtma xulosasini ko'rsatish
            $summary = $this->buildOrderPreview($data);
            
            // Telefon raqam so'rash
            $this->sendMessage($chatId, 
                "📋 <b>Buyurtma qabul qilindi!</b>\n\n" . 
                $summary . 
                "\n📱 <b>Telefon raqamingizni yuboring:</b>\n" .
                "Masalan: +998901234567"
            );
            
        } catch (Exception $e) {
            error_log("Order processing failed: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Buyurtma qayta ishlashda xatolik yuz berdi. Iltimos, qayta urinib ko'ring.");
        }
    }

    public function handlePhoneNumber(array $update): void {
        $from = $update['message']['from'];
        $chatId = $update['message']['chat']['id'];
        $phone = $update['message']['text'];

        // Telefon raqam formatini tekshirish
        if (!$this->isValidPhone($phone)) {
            $this->sendMessage($chatId, 
                "❌ Telefon raqam noto'g'ri formatda.\n\n" .
                "📱 Iltimos, to'g'ri formatda yuboring:\n" .
                "Masalan: +998901234567 yoki 998901234567"
            );
            return;
        }

        // Telefon raqamni sessiyaga saqlash
        $this->updateOrderSession($chatId, 'phone', $phone);

        // Manzil so'rash
        $this->sendMessage($chatId, 
            "✅ Telefon raqam saqlandi: {$phone}\n\n" .
            "📍 <b>Endi manzilingizni yuboring:</b>\n" .
            "Masalan: Toshkent sh., Yunusobod t., 5-mavze, 12-uy\n\n" .
            "Yoki joylashuvingizni yuboring 👇",
            [
                'reply_markup' => json_encode([
                    'keyboard' => [[
                        ['text' => '📍 Joylashuvni yuborish', 'request_location' => true]
                    ]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ])
            ]
        );
    }

    public function handleAddress(array $update): void {
        $from = $update['message']['from'];
        $chatId = $update['message']['chat']['id'];
        
        $address = '';
        
        // Joylashuv yoki matn manzil
        if (isset($update['message']['location'])) {
            $lat = $update['message']['location']['latitude'];
            $lon = $update['message']['location']['longitude'];
            $address = "📍 GPS: {$lat}, {$lon}";
        } else {
            $address = $update['message']['text'];
        }

        if (empty($address)) {
            $this->sendMessage($chatId, "❌ Manzil bo'sh. Iltimos, manzilingizni yuboring.");
            return;
        }

        // Manzilni sessiyaga saqlash
        $this->updateOrderSession($chatId, 'address', $address);

        // Izoh so'rash
        $this->sendMessage($chatId, 
            "✅ Manzil saqlandi: {$address}\n\n" .
            "💬 <b>Qo'shimcha izoh bor mi?</b>\n" .
            "Masalan: 3-qavat, 12-xonadon. Kamroq tuz qo'shing.\n\n" .
            "Izoh yo'q bo'lsa \"Yo'q\" deb yozing.",
            [
                'reply_markup' => json_encode([
                    'keyboard' => [[
                        ['text' => 'Yo\'q']
                    ]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ])
            ]
        );
    }

    public function handleComment(array $update): void {
        $from = $update['message']['from'];
        $chatId = $update['message']['chat']['id'];
        $comment = $update['message']['text'];

        if (strtolower($comment) === 'yo\'q' || strtolower($comment) === 'yoq') {
            $comment = '';
        }

        // Buyurtmani yakunlash
        $this->finalizeOrder($chatId, $comment);
    }

    private function finalizeOrder(int $chatId, string $comment): void {
        $orderData = $this->getOrderFromSession($chatId);
        if (!$orderData) {
            $this->sendMessage($chatId, "❌ Buyurtma ma'lumotlari topilmadi. Iltimos, qaytadan buyurtma bering.");
            return;
        }

        try {
            $orderId = $this->orderRepo->create(
                $orderData['user']['id'], 
                $orderData['data']['total'], 
                $comment,
                $orderData['phone'],
                $orderData['address']
            );
            $this->orderRepo->addItems($orderId, $orderData['data']['items']);

            // Buyurtma xulosasi
            $finalSummary = $this->buildFinalSummary($orderId, $orderData, $comment);
            
            // Mijozga tasdiqlash
            $this->sendMessage($chatId, 
                "🎉 <b>Buyurtmangiz muvaffaqiyatli qabul qilindi!</b>\n\n" . 
                $finalSummary . 
                "\n⏰ Tayyorlanish vaqti: 15-20 daqiqa\n" .
                "📞 Aloqa: +998 90 123 45 67",
                [
                    'reply_markup' => json_encode([
                        'remove_keyboard' => true
                    ])
                ]
            );
            
            // Adminga xabar
            $this->sendMessage(ADMIN_TELEGRAM_ID,
                "🆕 <b>YANGI BUYURTMA #{$orderId}</b>\n\n" . 
                $finalSummary
            );

            // Sessiyani tozalash
            $this->clearOrderSession($chatId);
            
        } catch (Exception $e) {
            error_log("Order finalization failed: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Buyurtma yaratishda xatolik yuz berdi. Iltimos, qayta urinib ko'ring.");
        }
    }

    private function buildOrderPreview(array $data): string {
        $lines = ["🛒 <b>Buyurtma tarkibi:</b>"];
        
        foreach ($data['items'] as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $lines[] = "• {$item['name']} × {$item['quantity']} = " . 
                      number_format($itemTotal, 0, '.', ' ') . " so'm";
        }
        
        $lines[] = "";
        $lines[] = "💰 <b>Jami: " . number_format($data['total'], 0, '.', ' ') . " so'm</b>";
        
        return implode("\n", $lines);
    }

    private function buildFinalSummary(int $orderId, array $orderData, string $comment): string {
        $lines = ["📋 <b>Buyurtma #{$orderId}</b>"];
        $lines[] = "";
        
        // Mijoz ma'lumotlari
        $lines[] = "👤 <b>Mijoz:</b> {$orderData['user']['first_name']} {$orderData['user']['last_name']}";
        $lines[] = "📱 <b>Telefon:</b> {$orderData['phone']}";
        $lines[] = "📍 <b>Manzil:</b> {$orderData['address']}";
        
        // Buyurtma tarkibi
        $lines[] = "";
        $lines[] = "🛒 <b>Buyurtma tarkibi:</b>";
        foreach ($orderData['data']['items'] as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $lines[] = "• {$item['name']} × {$item['quantity']} = " . 
                      number_format($itemTotal, 0, '.', ' ') . " so'm";
        }
        
        $lines[] = "";
        $lines[] = "💰 <b>Jami: " . number_format($orderData['data']['total'], 0, '.', ' ') . " so'm</b>";
        
        if (!empty($comment)) {
            $lines[] = "";
            $lines[] = "💬 <b>Izoh:</b> {$comment}";
        }
        
        $lines[] = "";
        $lines[] = "⏰ <b>Vaqt:</b> " . date('d.m.Y H:i');
        
        return implode("\n", $lines);
    }

    private function isValidPhone(string $phone): bool {
        // Remove spaces and special characters
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        // Check if it's a valid Uzbek phone number
        return preg_match('/^(\+?998|998|8)?[0-9]{9}$/', $cleanPhone);
    }

    // Session management methods (simplified - in production use Redis or database)
    private function saveOrderToSession(int $chatId, array $data, array $user): void {
        $sessionFile = sys_get_temp_dir() . "/order_session_{$chatId}.json";
        $sessionData = [
            'data' => $data,
            'user' => $user,
            'step' => 'phone',
            'timestamp' => time()
        ];
        file_put_contents($sessionFile, json_encode($sessionData));
    }

    private function updateOrderSession(int $chatId, string $key, string $value): void {
        $sessionFile = sys_get_temp_dir() . "/order_session_{$chatId}.json";
        if (file_exists($sessionFile)) {
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            $sessionData[$key] = $value;
            
            // Update step
            if ($key === 'phone') $sessionData['step'] = 'address';
            if ($key === 'address') $sessionData['step'] = 'comment';
            
            file_put_contents($sessionFile, json_encode($sessionData));
        }
    }

    private function getOrderFromSession(int $chatId): ?array {
        $sessionFile = sys_get_temp_dir() . "/order_session_{$chatId}.json";
        if (file_exists($sessionFile)) {
            return json_decode(file_get_contents($sessionFile), true);
        }
        return null;
    }

    private function clearOrderSession(int $chatId): void {
        $sessionFile = sys_get_temp_dir() . "/order_session_{$chatId}.json";
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
    }

    private function sendMessage(int $chatId, string $text, array $extra = []): void {
        $params = array_merge([
            'chat_id' => $chatId, 
            'text' => $text,
            'parse_mode' => 'HTML'
        ], $extra);

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
        
        if ($result === false) {
            error_log("Failed to send message to chat: $chatId");
        }
    }
}
