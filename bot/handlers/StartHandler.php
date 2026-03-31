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
            $this->sendMenuButton($chatId);
            return;
        }

        // /start command
        $user = $this->userRepo->findByTelegramId($from['id']);
        if ($user && $user['phone_number']) {
            $this->sendMenuButton($chatId);
        } else {
            $this->requestContact($chatId);
        }
    }

    private function requestContact(int $chatId): void {
        $this->sendRequest('sendMessage', [
            'chat_id' => $chatId,
            'text'    => "Assalomu alaykum! 👋\nIltimos, telefon raqamingizni yuboring:",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);
    }

    private function sendMenuButton(int $chatId): void {
        $this->sendRequest('sendMessage', [
            'chat_id'      => $chatId,
            'text'         => '✅ Ro\'yxatdan o\'tdingiz! Menuni ochish uchun tugmani bosing:',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '🍽 Menuni ochish', 'web_app' => ['url' => WEBAPP_URL]],
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
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
