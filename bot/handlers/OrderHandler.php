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
            $orderId = $this->orderRepo->create($user['id'], $data['total'], $data['comment'] ?? '');
            $this->orderRepo->addItems($orderId, $data['items']);

            $summary = $this->buildOrderSummary($orderId, $data, $user);
            
            // Send confirmation to user
            $this->sendMessage($chatId, 
                "🎉 <b>Buyurtmangiz qabul qilindi!</b>\n\n" . 
                $summary . 
                "\n⏰ Tayyorlanish vaqti: 15-20 daqiqa\n" .
                "📞 Aloqa: +998 90 123 45 67"
            );
            
            // Send notification to admin
            $this->sendMessage(ADMIN_TELEGRAM_ID,
                "🆕 <b>YANGI BUYURTMA #{$orderId}</b>\n\n" . 
                "👤 <b>Mijoz:</b> {$from['first_name']} {$from['last_name']}\n" .
                "📱 <b>Telefon:</b> {$user['phone_number']}\n" .
                ($from['username'] ? "👤 <b>Username:</b> @{$from['username']}\n" : "") .
                "\n" . $summary
            );
            
        } catch (Exception $e) {
            error_log("Order creation failed: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Buyurtma yaratishda xatolik yuz berdi. Iltimos, qayta urinib ko'ring.");
        }
    }

    private function buildOrderSummary(int $orderId, array $data, array $user): string {
        $lines = ["📋 <b>Buyurtma #{$orderId}</b>"];
        $lines[] = "";
        
        foreach ($data['items'] as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $lines[] = "• {$item['name']} × {$item['quantity']} = " . 
                      number_format($itemTotal, 0, '.', ' ') . " so'm";
        }
        
        $lines[] = "";
        $lines[] = "💰 <b>Jami: " . number_format($data['total'], 0, '.', ' ') . " so'm</b>";
        
        if (!empty($data['comment'])) {
            $lines[] = "";
            $lines[] = "💬 <b>Izoh:</b> {$data['comment']}";
        }
        
        return implode("\n", $lines);
    }

    private function sendMessage(int $chatId, string $text): void {
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'chat_id' => $chatId, 
                'text' => $text,
                'parse_mode' => 'HTML'
            ],
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
