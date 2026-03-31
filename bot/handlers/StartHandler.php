<?php
require_once __DIR__ . '/../db/UserRepo.php';
require_once __DIR__ . '/../config.php';

class StartHandler {
    private UserRepo $userRepo;

    public function __construct() {
        $this->userRepo = new UserRepo();
    }

    public function handle(array $update): void {
        $message = $update['message'];
        $from = $message['from'];
        $chatId = $message['chat']['id'];

        // Contact received → save user
        if (isset($message['contact'])) {
            $this->userRepo->create([
                'telegram_id'  => $from['id'],
                'first_name'   => $from['first_name'] ?? '',
                'last_name'    => $from['last_name'] ?? '',
                'username'     => $from['username'] ?? '',
                'phone_number' => $message['contact']['phone_number'],
            ]);
            $this->sendWelcomeMessage($chatId, $from['first_name'] ?? 'Foydalanuvchi');
            return;
        }

        // /start command
        $user = $this->userRepo->findByTelegramId($from['id']);
        if ($user && $user['phone_number']) {
            $this->sendMenuButton($chatId, $from['first_name'] ?? 'Foydalanuvchi');
        } else {
            $this->requestContact($chatId, $from['first_name'] ?? 'Foydalanuvchi');
        }
    }

    private function requestContact(int $chatId, string $firstName): void {
        $this->sendRequest('sendMessage', [
            'chat_id' => $chatId,
            'text'    => "🍽 <b>Olmazor Go</b> ga xush kelibsiz, {$firstName}!\n\n" .
                        "🚀 Eng mazali taomlarni tez va oson buyurtma qiling!\n\n" .
                        "📱 Davom etish uchun telefon raqamingizni yuboring:",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'keyboard' => [[
                    ['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);
    }

    private function sendWelcomeMessage(int $chatId, string $firstName): void {
        $urlWithId = WEBAPP_URL . '&tg_id=' . $chatId;
        $this->sendRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => "🎉 <b>Tabriklaymiz, {$firstName}!</b>\n\n" .
                     "✅ Siz muvaffaqiyatli ro'yxatdan o'tdingiz!\n" .
                     "🍽 Endi mazali taomlarni buyurtma qilishingiz mumkin.\n\n" .
                     "👇 Menuni ochish uchun tugmani bosing:",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '🍽 Menuni ochish', 'web_app' => ['url' => $urlWithId]],
                ]],
                'remove_keyboard' => true
            ]),
        ]);
    }

    private function sendMenuButton(int $chatId, string $firstName): void {
        $urlWithId = WEBAPP_URL . '&tg_id=' . $chatId;
        $this->sendRequest('sendMessage', [
            'chat_id'      => $chatId,
            'text'         => "Assalomu alaykum, {$firstName}! 👋\n\n" .
                             "🍽 <b>Olmazor Go</b> dan buyurtma berish uchun menuni oching:\n\n" .
                             "🔥 Yangi taomlar qo'shildi!\n" .
                             "⚡ Tez yetkazib berish\n" .
                             "💰 Eng yaxshi narxlar",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '🍽 Menuni ochish', 'web_app' => ['url' => $urlWithId]],
                ]],
            ]),
        ]);
    }

    private function sendRequest(string $method, array $params): void {
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
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
            error_log("Telegram API request failed for method: $method");
        }
    }
}
