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
    <title>Buyurtma #<?= $id ?> - Olmazor Go Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <div class="brand">
        <a href="index.php" style="color: white; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Orqaga
        </a>
    </div>
    <div class="nav-links">
        <span>Buyurtma #<?= $id ?></span>
    </div>
</nav>

<div class="container">
    <div class="detail-card">
        <h3><i class="fas fa-user"></i> Mijoz ma'lumotlari</h3>
        <p><span class="highlight">Ism:</span> <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></p>
        <p><span class="highlight">Telefon:</span> 
            <a href="tel:<?= htmlspecialchars($order['phone_number']) ?>" style="color: var(--primary);">
                <i class="fas fa-phone"></i> <?= htmlspecialchars($order['phone_number']) ?>
            </a>
        </p>
        <?php if ($order['username']): ?>
        <p><span class="highlight">Telegram:</span> @<?= htmlspecialchars($order['username']) ?></p>
        <?php endif; ?>
        <p><span class="highlight">Telegram ID:</span> <?= htmlspecialchars($order['telegram_id']) ?></p>
        <p><span class="highlight">Buyurtma vaqti:</span> 
            <i class="fas fa-calendar"></i> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
        </p>
    </div>

    <div class="detail-card">
        <h3><i class="fas fa-shopping-cart"></i> Buyurtma tarkibi</h3>
        <div class="table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-utensils"></i> Taom</th>
                        <th><i class="fas fa-sort-numeric-up"></i> Soni</th>
                        <th><i class="fas fa-money-bill"></i> Narx</th>
                        <th><i class="fas fa-calculator"></i> Jami</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                        <td><?= $item['quantity'] ?> ta</td>
                        <td><?= number_format($item['price'], 0, '.', ' ') ?> so'm</td>
                        <td><strong><?= number_format($item['price'] * $item['quantity'], 0, '.', ' ') ?> so'm</strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="text-align: right; margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 2px solid var(--primary);">
            <h3 style="color: var(--primary); font-size: 1.5rem;">
                <i class="fas fa-money-bill-wave"></i> 
                Jami: <?= number_format($order['total_price'], 0, '.', ' ') ?> so'm
            </h3>
        </div>
        
        <?php if ($order['comment']): ?>
        <div style="margin-top: var(--spacing-lg); padding: var(--spacing-lg); background: var(--bg-secondary); border-radius: var(--radius); border-left: 4px solid var(--info);">
            <h4><i class="fas fa-comment"></i> Mijoz izohi:</h4>
            <p style="margin-top: var(--spacing-sm); font-style: italic;">"<?= htmlspecialchars($order['comment']) ?>"</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="detail-card">
        <h3><i class="fas fa-info-circle"></i> Buyurtma holati</h3>
        <form method="POST" action="update_status.php" style="display: flex; gap: var(--spacing-md); align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="redirect" value="order.php?id=<?= $id ?>">
            
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Holat:</label>
                <select name="status" class="form-select">
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Saqlash
            </button>
        </form>
        
        <div style="margin-top: var(--spacing-lg);">
            <span class="status-badge status-<?= $order['status'] ?>">
                <?php
                $statusIcons = [
                    'new' => 'fas fa-plus-circle',
                    'confirmed' => 'fas fa-check-circle',
                    'preparing' => 'fas fa-fire',
                    'delivered' => 'fas fa-shipping-fast',
                    'cancelled' => 'fas fa-times-circle'
                ];
                ?>
                <i class="<?= $statusIcons[$order['status']] ?? 'fas fa-question-circle' ?>"></i>
                Joriy holat: <?= $statusLabels[$order['status']] ?? $order['status'] ?>
            </span>
        </div>
    </div>
</div>

<script>
// Add confirmation for status changes
document.querySelector('form').addEventListener('submit', function(e) {
    const select = this.querySelector('select[name="status"]');
    const selectedText = select.options[select.selectedIndex].text;
    
    if (!confirm(`Buyurtma holatini "${selectedText}" ga o'zgartirishni tasdiqlaysizmi?`)) {
        e.preventDefault();
    }
});

// Auto-refresh every 60 seconds
setTimeout(() => {
    location.reload();
}, 60000);
</script>
</body>
</html>
