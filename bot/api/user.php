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
    
    $firstName = $user ? $user['first_name'] : '';
    $lastName = $user ? $user['last_name'] : '';
    $photoUrl = null;
    
    if (defined('BOT_TOKEN')) {
        // Fetch missing names
        if (empty($firstName)) {
            $chatUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getChat?chat_id=' . $telegramId;
            $chatRes = @file_get_contents($chatUrl);
            if ($chatRes) {
                $chatData = json_decode($chatRes, true);
                if (!empty($chatData['ok'])) {
                    $firstName = $chatData['result']['first_name'] ?? '';
                    $lastName = $chatData['result']['last_name'] ?? '';
                }
            }
        }
        
        // Fetch Profile Picture
        $photosUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getUserProfilePhotos?user_id=' . $telegramId . '&limit=1';
        $pRes = @file_get_contents($photosUrl);
        if ($pRes) {
            $pData = json_decode($pRes, true);
            if (!empty($pData['ok']) && !empty($pData['result']['photos'][0][0]['file_id'])) {
                $fileId = $pData['result']['photos'][0][0]['file_id'];
                
                // Resolve file_id to file_path
                $fUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getFile?file_id=' . $fileId;
                $fRes = @file_get_contents($fUrl);
                if ($fRes) {
                    $fData = json_decode($fRes, true);
                    if (!empty($fData['ok']) && !empty($fData['result']['file_path'])) {
                        $photoUrl = 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $fData['result']['file_path'];
                    }
                }
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
