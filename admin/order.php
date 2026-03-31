<?php
require_once __DIR__ . '/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$order = db()->prepare(
    "SELECT o.*, u.first_name, u.last_name, u.phone_number, u.username, u.telegram_id
     FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?"
);
$order->execute([$id]);
$order = $order->fetch();
if (!$order) { header('Location: index.php'); exit; }

$items = db()->prepare(
    "SELECT oi.quantity, oi.price, m.name FROM order_items oi
     JOIN menu_items m ON m.id = oi.menu_item_id WHERE oi.order_id = ?"
);
$items->execute([$id]);
$items = $items->fetchAll();

$statusLabels = [
    'new'=>'🆕 Yangi','confirmed'=>'✅ Tasdiqlangan',
    'preparing'=>'👨🍳 Tayyorlanmoqda','delivered'=>'🚀 Yetkazildi','cancelled'=>'❌ Bekor'
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buyurtma #<?= $id ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <span><a href="index.php">← Orqaga</a></span>
    <span>Buyurtma #<?= $id ?></span>
</nav>
<div class="container">
    <div class="detail-card">
        <h3>👤 Mijoz</h3>
        <p><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></p>
        <p>📱 <?= htmlspecialchars($order['phone_number']) ?></p>
        <?php if ($order['username']): ?>
        <p>@<?= htmlspecialchars($order['username']) ?></p>
        <?php endif; ?>
    </div>

    <div class="detail-card">
        <h3>🛒 Buyurtma tarkibi</h3>
        <table class="orders-table">
            <thead><tr><th>Taom</th><th>Soni</th><th>Narx</th><th>Jami</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= number_format($item['price'], 0, '.', ' ') ?></td>
                    <td><?= number_format($item['price'] * $item['quantity'], 0, '.', ' ') ?> so'm</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="text-align:right;font-weight:700;margin-top:10px">
            💰 Jami: <?= number_format($order['total_price'], 0, '.', ' ') ?> so'm
        </p>
        <?php if ($order['comment']): ?>
        <p>💬 Izoh: <?= htmlspecialchars($order['comment']) ?></p>
        <?php endif; ?>
    </div>

    <div class="detail-card">
        <h3>📋 Status</h3>
        <form method="POST" action="update_status.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="redirect" value="order.php?id=<?= $id ?>">
            <select name="status">
                <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Saqlash</button>
        </form>
    </div>
</div>
</body>
</html>
