<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/UserRepo.php';

$telegramId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$telegramId) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

try {
    $userRepo = new UserRepo();
    $user = $userRepo->findByTelegramId($telegramId);

    if ($user) {
        echo json_encode([
            'ok' => true,
            'user' => [
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'username' => $user['username'],
                'phone_number' => $user['phone_number'],
            ]
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
