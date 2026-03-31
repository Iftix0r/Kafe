<?php
require_once __DIR__ . '/auth.php';
requireAuth();

$status = $_GET['status'] ?? '';
$where = $status ? 'WHERE o.status = :status' : '';

$sql = "SELECT o.id, o.total_price, o.status, o.comment, o.created_at,
               u.first_name, u.last_name, u.phone_number, u.username
        FROM orders o
        JOIN users u ON u.id = o.user_id
        $where
        ORDER BY o.created_at DESC";

$stmt = db()->prepare($sql);
if ($status) $stmt->bindValue(':status', $status);
$stmt->execute();
$orders = $stmt->fetchAll();

$counts = db()->query(
    "SELECT status, COUNT(*) as cnt FROM orders GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$totalOrders = array_sum($counts);
$todayOrders = db()->query(
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();

$totalRevenue = db()->query(
    "SELECT SUM(total_price) FROM orders WHERE status IN ('confirmed', 'preparing', 'delivered')"
)->fetchColumn() ?: 0;

$statusLabels = [
    'new'       => ['🆕 Yangi',       'new'],
    'confirmed' => ['✅ Tasdiqlangan', 'confirmed'],
    'preparing' => ['👨‍🍳 Tayyorlanmoqda','preparing'],
    'delivered' => ['🚀 Yetkazildi',   'delivered'],
    'cancelled' => ['❌ Bekor',        'cancelled'],
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>🍽 Olmazor Go - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <div class="brand">
        <span class="emoji">🍽</span>
        <span>Olmazor Go Admin</span>
    </div>
    <div class="nav-links">
        <a href="menu.php">📋 Menyu</a>
        <a href="logout.php">🚪 Chiqish</a>
    </div>
</nav>

<div class="container">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon">📊</div>
            <div class="number"><?= $totalOrders ?></div>
            <div class="label">Jami buyurtmalar</div>
        </div>
        <div class="stat-card">
            <div class="icon">📅</div>
            <div class="number"><?= $todayOrders ?></div>
            <div class="label">Bugungi buyurtmalar</div>
        </div>
        <div class="stat-card">
            <div class="icon">💰</div>
            <div class="number"><?= number_format($totalRevenue, 0, '.', ' ') ?></div>
            <div class="label">Jami daromad (so'm)</div>
        </div>
        <div class="stat-card">
            <div class="icon">⏳</div>
            <div class="number"><?= $counts['new'] ?? 0 ?></div>
            <div class="label">Yangi buyurtmalar</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="index.php" class="tab <?= !$status ? 'active' : '' ?>">
            📋 Barchasi (<?= $totalOrders ?>)
        </a>
        <?php foreach ($statusLabels as $key => [$label]): ?>
        <a href="?status=<?= $key ?>" class="tab <?= $status === $key ? 'active' : '' ?>">
            <?= $label ?> (<?= $counts[$key] ?? 0 ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Orders Table -->
    <div class="table-container">
        <?php if (empty($orders)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <h3>Buyurtmalar topilmadi</h3>
                <p>Hozircha <?= $status ? $statusLabels[$status][0] : 'hech qanday' ?> buyurtmalar yo'q.</p>
            </div>
        <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mijoz</th>
                        <th>Telefon</th>
                        <th>Summa</th>
                        <th>Holat</th>
                        <th>Vaqt</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                                <?php if ($order['username']): ?>
                                    <br><small style="color: var(--text-secondary);">@<?= htmlspecialchars($order['username']) ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="tel:<?= htmlspecialchars($order['phone_number']) ?>" 
                               style="color: var(--primary); text-decoration: none;">
                                <?= htmlspecialchars($order['phone_number']) ?>
                            </a>
                        </td>
                        <td><strong><?= number_format($order['total_price'], 0, '.', ' ') ?> so'm</strong></td>
                        <td>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?= $statusLabels[$order['status']][0] ?? $order['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div><?= date('d.m.Y', strtotime($order['created_at'])) ?></div>
                            <small style="color: var(--text-secondary);"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                        </td>
                        <td>
                            <a href="order.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-sm">
                                👁️ Ko'rish
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto refresh every 30 seconds
setTimeout(() => {
    location.reload();
}, 30000);

// Add smooth animations
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach((card, index) => {
        card.style.animation = `slideUp 0.5s ease ${index * 0.1}s both`;
    });
    
    const rows = document.querySelectorAll('.orders-table tbody tr');
    rows.forEach((row, index) => {
        row.style.animation = `fadeIn 0.5s ease ${index * 0.05}s both`;
    });
});
</script>
</body>
</html>

    <table class="orders-table">
        <thead>
            <tr>
                <th>#</th><th>Mijoz</th><th>Telefon</th><th>Summa</th><th>Status</th><th>Vaqt</th><th>Amal</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><a href="order.php?id=<?= $o['id'] ?>">#<?= $o['id'] ?></a></td>
                <td><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></td>
                <td><?= htmlspecialchars($o['phone_number']) ?></td>
                <td><?= number_format($o['total_price'], 0, '.', ' ') ?> so'm</td>
                <td><span class="badge <?= $o['status'] ?>"><?= $statusLabels[$o['status']][0] ?></span></td>
                <td><?= date('d.m H:i', strtotime($o['created_at'])) ?></td>
                <td>
                    <form method="POST" action="update_status.php" style="display:inline">
                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach ($statusLabels as $key => [$label]): ?>
                                <option value="<?= $key ?>" <?= $o['status'] === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
            <tr><td colspan="7" style="text-align:center;padding:20px">Buyurtma yo'q</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
