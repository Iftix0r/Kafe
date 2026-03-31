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

        if (!$data || empty($data['items'])) return;

        $user = $this->userRepo->findByTelegramId($from['id']);
        if (!$user) return;

        $orderId = $this->orderRepo->create($user['id'], $data['total'], $data['comment'] ?? '');
        $this->orderRepo->addItems($orderId, $data['items']);

        $summary = $this->buildSummary($orderId, $data);
        $this->sendMessage($chatId, "✅ Buyurtmangiz qabul qilindi!\n\n" . $summary);
        $this->sendMessage(ADMIN_TELEGRAM_ID,
            "🆕 Yangi buyurtma #{$orderId}\n👤 {$from['first_name']}\n\n" . $summary
        );
    }

    private function buildSummary(int $orderId, array $data): string {
        $lines = ["📋 Buyurtma #{$orderId}:"];
        foreach ($data['items'] as $item) {
            $lines[] = "• {$item['name']} x{$item['quantity']} — " . number_format($item['price'] * $item['quantity'], 0, '.', ' ') . " so'm";
        }
        $lines[] = "\n💰 Jami: " . number_format($data['total'], 0, '.', ' ') . " so'm";
        if (!empty($data['comment'])) {
            $lines[] = "💬 Izoh: {$data['comment']}";
        }
        return implode("\n", $lines);
    }

    private function sendMessage(int $chatId, string $text): void {
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['chat_id' => $chatId, 'text' => $text],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
