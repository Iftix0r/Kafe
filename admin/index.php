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

// Yangi foydalanuvchilar statistikasi
$newUsersToday = db()->query(
    "SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();

$totalUsers = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Oxirgi yangi foydalanuvchilar
$recentUsers = db()->query(
    "SELECT u.*, DATE_FORMAT(u.created_at, '%d.%m.%Y %H:%i') as reg_time 
     FROM users u 
     ORDER BY u.created_at DESC 
     LIMIT 5"
)->fetchAll();

$statusLabels = [
    'new'       => ['🆕 Yangi',       'new'],
    'confirmed' => ['✅ Tasdiqlangan', 'confirmed'],
    'preparing' => ['👨‍🍳 Tayyorlanmoqda','preparing'],
    'on_way'    => ['🚚 Yo\'lda',        'on_way'],
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <div class="brand">
        <span class="emoji"><i class="fas fa-utensils"></i></span>
        <span>Olmazor Go Admin</span>
    </div>
    <div class="nav-links">
        <a href="users.php"><i class="fas fa-users"></i> Foydalanuvchilar</a>
        <a href="menu.php"><i class="fas fa-list"></i> Menyu</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
    </div>
</nav>

<div class="container">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon"><i class="fas fa-chart-bar"></i></div>
            <div class="number"><?= $totalOrders ?></div>
            <div class="label">Jami buyurtmalar</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
            <div class="number"><?= $todayOrders ?></div>
            <div class="label">Bugungi buyurtmalar</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="number"><?= $totalUsers ?></div>
            <div class="label">Jami foydalanuvchilar</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-user-plus"></i></div>
            <div class="number"><?= $newUsersToday ?></div>
            <div class="label">Bugungi yangi foydalanuvchilar</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="number"><?= number_format($totalRevenue, 0, '.', ' ') ?></div>
            <div class="label">Jami daromad (so'm)</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-clock"></i></div>
            <div class="number"><?= $counts['new'] ?? 0 ?></div>
            <div class="label">Yangi buyurtmalar</div>
        </div>
    </div>

    <!-- Recent New Users -->
    <?php if (!empty($recentUsers)): ?>
    <div class="detail-card">
        <h3><i class="fas fa-user-plus"></i> Oxirgi yangi foydalanuvchilar</h3>
        <div class="recent-users-list">
            <?php foreach ($recentUsers as $user): ?>
            <div class="user-item">
                <div class="user-info">
                    <div class="user-name">
                        <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                        <?php if ($user['username']): ?>
                            <span class="username">@<?= htmlspecialchars($user['username']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <span class="telegram-id">ID: <?= $user['telegram_id'] ?></span>
                        <?php if ($user['phone_number']): ?>
                            <span class="phone">📱 <?= htmlspecialchars($user['phone_number']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="user-time">
                    <i class="fas fa-clock"></i> <?= $user['reg_time'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="index.php" class="tab <?= !$status ? 'active' : '' ?>">
            <i class="fas fa-list"></i> Barchasi (<?= $totalOrders ?>)
        </a>
        <a href="?status=new" class="tab <?= $status === 'new' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i> Yangi (<?= $counts['new'] ?? 0 ?>)
        </a>
        <a href="?status=confirmed" class="tab <?= $status === 'confirmed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Tasdiqlangan (<?= $counts['confirmed'] ?? 0 ?>)
        </a>
        <a href="?status=preparing" class="tab <?= $status === 'preparing' ? 'active' : '' ?>">
            <i class="fas fa-fire"></i> Tayyorlanmoqda (<?= $counts['preparing'] ?? 0 ?>)
        </a>
        <a href="?status=on_way" class="tab <?= $status === 'on_way' ? 'active' : '' ?>">
            <i class="fas fa-truck"></i> Yo'lda (<?= $counts['on_way'] ?? 0 ?>)
        </a>
        <a href="?status=delivered" class="tab <?= $status === 'delivered' ? 'active' : '' ?>">
            <i class="fas fa-shipping-fast"></i> Yetkazildi (<?= $counts['delivered'] ?? 0 ?>)
        </a>
        <a href="?status=cancelled" class="tab <?= $status === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Bekor (<?= $counts['cancelled'] ?? 0 ?>)
        </a>
    </div>

    <!-- Orders Table -->
    <div class="table-container">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-inbox"></i></div>
                <h3>Buyurtmalar topilmadi</h3>
                <p>Hozircha <?= $status ? $statusLabels[$status][0] : 'hech qanday' ?> buyurtmalar yo'q.</p>
            </div>
        <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-user"></i> Mijoz</th>
                        <th><i class="fas fa-phone"></i> Telefon</th>
                        <th><i class="fas fa-money-bill"></i> Summa</th>
                        <th><i class="fas fa-info-circle"></i> Holat</th>
                        <th><i class="fas fa-clock"></i> Vaqt</th>
                        <th><i class="fas fa-cogs"></i> Amallar</th>
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
                                <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($order['phone_number']) ?>
                            </a>
                        </td>
                        <td><strong><?= number_format($order['total_price'], 0, '.', ' ') ?> so'm</strong></td>
                        <td>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?php
                                $statusIcons = [
                                    'new' => 'fas fa-plus-circle',
                                    'confirmed' => 'fas fa-check-circle',
                                    'preparing' => 'fas fa-fire',
                                    'on_way' => 'fas fa-truck',
                                    'delivered' => 'fas fa-shipping-fast',
                                    'cancelled' => 'fas fa-times-circle'
                                ];
                                ?>
                                <i class="<?= $statusIcons[$order['status']] ?? 'fas fa-question-circle' ?>"></i>
                                <?= $statusLabels[$order['status']][0] ?? $order['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div><i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($order['created_at'])) ?></div>
                            <small style="color: var(--text-secondary);"><i class="fas fa-clock"></i> <?= date('H:i', strtotime($order['created_at'])) ?></small>
                        </td>
                        <td>
                            <a href="order.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> Ko'rish
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

// Add loading states for buttons
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', function() {
        this.style.opacity = '0.7';
        this.style.pointerEvents = 'none';
        setTimeout(() => {
            this.style.opacity = '1';
            this.style.pointerEvents = 'auto';
        }, 1000);
    });
});
</script>
</body>
</html>