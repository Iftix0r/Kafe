<?php
require_once __DIR__ . '/auth.php';
requireAuth();

// Get users with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$totalUsers = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

$users = db()->prepare(
    "SELECT u.*, DATE_FORMAT(u.created_at, '%d.%m.%Y %H:%i') as reg_time,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as orders_count,
            (SELECT SUM(total_price) FROM orders WHERE user_id = u.id AND status IN ('confirmed', 'preparing', 'delivered')) as total_spent
     FROM users u 
     ORDER BY u.created_at DESC 
     LIMIT ? OFFSET ?"
);
$users->execute([$limit, $offset]);
$users = $users->fetchAll();

// Statistics
$stats = [
    'total' => $totalUsers,
    'today' => db()->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'week' => db()->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'month' => db()->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'with_phone' => db()->query("SELECT COUNT(*) FROM users WHERE phone_number IS NOT NULL")->fetchColumn(),
    'with_orders' => db()->query("SELECT COUNT(DISTINCT user_id) FROM orders")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Foydalanuvchilar - Olmazor Go Admin</title>
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
            <i class="fas fa-arrow-left"></i> Buyurtmalar
        </a>
    </div>
    <div class="nav-links">
        <span><i class="fas fa-users"></i> Foydalanuvchilar</span>
    </div>
</nav>

<div class="container">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Jami foydalanuvchilar</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-user-plus"></i></div>
            <div class="number"><?= $stats['today'] ?></div>
            <div class="label">Bugun qo'shilgan</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-calendar-week"></i></div>
            <div class="number"><?= $stats['week'] ?></div>
            <div class="label">Bu hafta qo'shilgan</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-phone"></i></div>
            <div class="number"><?= $stats['with_phone'] ?></div>
            <div class="label">Telefon raqami bor</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="number"><?= $stats['with_orders'] ?></div>
            <div class="label">Buyurtma bergan</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="number"><?= $stats['month'] ?></div>
            <div class="label">Bu oy qo'shilgan</div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <table class="orders-table">
            <thead>
                <tr>
                    <th><i class="fas fa-hashtag"></i> ID</th>
                    <th><i class="fas fa-user"></i> Ism</th>
                    <th><i class="fas fa-at"></i> Username</th>
                    <th><i class="fas fa-phone"></i> Telefon</th>
                    <th><i class="fas fa-shopping-cart"></i> Buyurtmalar</th>
                    <th><i class="fas fa-money-bill"></i> Sarflagan</th>
                    <th><i class="fas fa-calendar"></i> Ro'yxatdan o'tgan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= $user['telegram_id'] ?></strong></td>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                        </div>
                    </td>
                    <td>
                        <?php if ($user['username']): ?>
                            <a href="https://t.me/<?= htmlspecialchars($user['username']) ?>" target="_blank" style="color: var(--primary);">
                                @<?= htmlspecialchars($user['username']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">Username yo'q</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['phone_number']): ?>
                            <a href="tel:<?= htmlspecialchars($user['phone_number']) ?>" style="color: var(--success);">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone_number']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">Telefon yo'q</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['orders_count'] > 0): ?>
                            <span class="status-badge status-delivered">
                                <i class="fas fa-shopping-cart"></i> <?= $user['orders_count'] ?> ta
                            </span>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">Buyurtma yo'q</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['total_spent'] > 0): ?>
                            <strong style="color: var(--primary);">
                                <?= number_format($user['total_spent'], 0, '.', ' ') ?> so'm
                            </strong>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">0 so'm</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><i class="fas fa-calendar"></i> <?= $user['reg_time'] ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-chevron-left"></i> Oldingi
            </a>
        <?php endif; ?>
        
        <span class="pagination-info">
            Sahifa <?= $page ?> / <?= $totalPages ?> (Jami: <?= $totalUsers ?> foydalanuvchi)
        </span>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-primary btn-sm">
                Keyingi <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto refresh every 60 seconds
setTimeout(() => {
    location.reload();
}, 60000);
</script>
</body>
</html>