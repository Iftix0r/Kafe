<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/UserRepo.php';
require_once __DIR__ . '/../config.php';

$telegramId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$telegramId) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

try {
    $userRepo = new UserRepo();
    $user = $userRepo->findByTelegramId($telegramId);
    
    $firstName = '';
    $lastName = '';
    if ($user) {
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
    }
    
    $photoUrl = null;
    
    if (defined('BOT_TOKEN')) {
        function fetchTg($method, $params = []) {
            $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
            if ($params) $url .= '?' . http_build_query($params);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds absolute timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            $r = curl_exec($ch);
            curl_close($ch);
            return $r ? @json_decode($r, true) : null;
        }

        // 1. Fetch live username/firstname if empty locally
        if (empty($firstName) || $firstName === 'Foydalanuvchi') {
            $chatData = fetchTg('getChat', ['chat_id' => $telegramId]);
            if ($chatData && !empty($chatData['ok'])) {
                $firstName = $chatData['result']['first_name'] ?? '';
                $lastName = $chatData['result']['last_name'] ?? '';
            }
        }
        
        // 2. Fetch Profile Picture from Telegram
        $pData = fetchTg('getUserProfilePhotos', ['user_id' => $telegramId, 'limit' => 1]);
        if ($pData && !empty($pData['ok']) && !empty($pData['result']['photos'][0][0]['file_id'])) {
            $fileId = $pData['result']['photos'][0][0]['file_id'];
            
            // Resolve file_id to file_path
            $fData = fetchTg('getFile', ['file_id' => $fileId]);
            if ($fData && !empty($fData['ok']) && !empty($fData['result']['file_path'])) {
                $photoUrl = 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $fData['result']['file_path'];
            }
        }
    }

    if ($user) {
        echo json_encode([
            'ok' => true,
            'user' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $user['username'] ?? '',
                'phone_number' => $user['phone_number'] ?? '',
                'photo_url' => $photoUrl
            ]
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
?>
