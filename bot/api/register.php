<?php
// bot/api/register.php — Sync user from WebApp
require_once __DIR__ . '/../db/UserRepo.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
$first = $_GET['first'] ?? '';
$last = $_GET['last'] ?? '';
$user = $_GET['user'] ?? '';

if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'No ID']);
    exit;
}

$repo = new UserRepo();
$userId = $repo->create([
    'telegram_id' => $id,
    'first_name'  => $first,
    'last_name'   => $last,
    'username'    => $user
]);

echo json_encode(['ok' => true, 'user_id' => $userId]);
exit;
