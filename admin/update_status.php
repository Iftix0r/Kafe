<?php
require_once __DIR__ . '/auth.php';
requireAuth();

$allowed = ['new', 'confirmed', 'preparing', 'delivered', 'cancelled'];
$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($id && in_array($status, $allowed)) {
    db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
}

$redirect = $_POST['redirect'] ?? 'index.php';
header('Location: ' . $redirect);
