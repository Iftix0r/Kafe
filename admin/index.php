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
    <title>Admin — Buyurtmalar</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <span>🍽 Kafe Admin</span>
    <div>
        <a href="menu.php">Menyu</a>
        <a href="logout.php">Chiqish</a>
    </div>
</nav>

<div class="container">
    <div class="filter-tabs">
        <a href="index.php" class="tab <?= !$status ? 'active' : '' ?>">
            Barchasi (<?= array_sum($counts) ?>)
        </a>
        <?php foreach ($statusLabels as $key => [$label]): ?>
        <a href="?status=<?= $key ?>" class="tab <?= $status === $key ? 'active' : '' ?>">
            <?= $label ?> (<?= $counts[$key] ?? 0 ?>)
        </a>
        <?php endforeach; ?>
    </div>

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
