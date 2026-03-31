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
            $userId = $this->userRepo->create([
                'telegram_id'  => $from['id'],
                'first_name'   => $from['first_name'] ?? '',
                'last_name'    => $from['last_name'] ?? '',
                'username'     => $from['username'] ?? '',
                'phone_number' => $message['contact']['phone_number'],
            ]);
            
            // Send notification to admin about new user registration
            $this->notifyAdminNewUser($from, $message['contact']['phone_number'], 'contact_shared');
            
            $this->sendWelcomeMessage($chatId, $from['first_name'] ?? 'Foydalanuvchi');
            return;
        }

        // /start command - log the activity
        $this->userRepo->logStartCommand($from['id']);
        
        $user = $this->userRepo->findByTelegramId($from['id']);
        if ($user && $user['phone_number']) {
            // Existing user with phone
            $this->sendMenuButton($chatId, $from['first_name'] ?? 'Foydalanuvchi');
            
            // Notify admin about returning user
            $this->notifyAdminUserActivity($from, 'returning_user');
        } else {
            // New user or user without phone
            $isNewUser = !$user;
            $this->requestContact($chatId, $from['first_name'] ?? 'Foydalanuvchi');
            
            // Notify admin about new user start
            if ($isNewUser) {
                $this->notifyAdminNewUser($from, null, 'new_start');
            }
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

    private function notifyAdminNewUser(array $from, ?string $phoneNumber, string $action): void {
        $firstName = $from['first_name'] ?? 'Noma\'lum';
        $lastName = $from['last_name'] ?? '';
        $username = $from['username'] ?? '';
        $telegramId = $from['id'];
        
        $fullName = trim($firstName . ' ' . $lastName);
        $usernameText = $username ? "@{$username}" : "Username yo'q";
        $phoneText = $phoneNumber ? "📱 {$phoneNumber}" : "📱 Telefon raqam yo'q";
        
        $actionText = [
            'new_start' => '🆕 Yangi foydalanuvchi botni boshladi',
            'contact_shared' => '✅ Foydalanuvchi telefon raqamini ulashdi',
            'returning_user' => '🔄 Mavjud foydalanuvchi qaytdi'
        ];
        
        $message = "<b>{$actionText[$action]}</b>\n\n";
        $message .= "👤 <b>Ism:</b> {$fullName}\n";
        $message .= "🆔 <b>Telegram ID:</b> <code>{$telegramId}</code>\n";
        $message .= "👨‍💻 <b>Username:</b> {$usernameText}\n";
        $message .= "{$phoneText}\n";
        $message .= "🕐 <b>Vaqt:</b> " . date('d.m.Y H:i:s') . "\n\n";
        
        if ($action === 'new_start') {
            $message .= "🎯 Foydalanuvchi hali telefon raqamini bermagan";
        } elseif ($action === 'contact_shared') {
            $message .= "🎉 Ro'yxatdan o'tish yakunlandi!";
        }
        
        $this->sendRequest('sendMessage', [
            'chat_id' => ADMIN_TELEGRAM_ID,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }

    private function notifyAdminUserActivity(array $from, string $activity): void {
        $firstName = $from['first_name'] ?? 'Noma\'lum';
        $lastName = $from['last_name'] ?? '';
        $username = $from['username'] ?? '';
        $telegramId = $from['id'];
        
        $fullName = trim($firstName . ' ' . $lastName);
        $usernameText = $username ? "@{$username}" : "Username yo'q";
        
        $message = "🔄 <b>Foydalanuvchi faolligi</b>\n\n";
        $message .= "👤 <b>Ism:</b> {$fullName}\n";
        $message .= "🆔 <b>Telegram ID:</b> <code>{$telegramId}</code>\n";
        $message .= "👨‍💻 <b>Username:</b> {$usernameText}\n";
        $message .= "🕐 <b>Vaqt:</b> " . date('d.m.Y H:i:s') . "\n";
        $message .= "📋 <b>Harakat:</b> Menyuni ochdi";
        
        $this->sendRequest('sendMessage', [
            'chat_id' => ADMIN_TELEGRAM_ID,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
}
