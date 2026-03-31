<?php
require_once __DIR__ . '/auth.php';
requireAuth();

// Add item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $stmt = db()->prepare(
        'INSERT INTO menu_items (category_id, name, description, price, image_url, is_available)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $_POST['category_id'], $_POST['name'], $_POST['description'],
        $_POST['price'], $_POST['image_url'], isset($_POST['is_available']) ? 1 : 0
    ]);
    header('Location: menu.php'); exit;
}

// Toggle availability
if (isset($_GET['toggle'])) {
    db()->prepare('UPDATE menu_items SET is_available = 1 - is_available WHERE id = ?')
       ->execute([(int)$_GET['toggle']]);
    header('Location: menu.php'); exit;
}

// Delete item
if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM menu_items WHERE id = ?')->execute([(int)$_GET['delete']]);
    header('Location: menu.php'); exit;
}

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
$items = db()->query(
    'SELECT m.*, c.name as cat_name FROM menu_items m
     LEFT JOIN categories c ON c.id = m.category_id ORDER BY m.category_id, m.id'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menyu boshqaruvi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <span><a href="index.php">← Buyurtmalar</a></span>
    <span>🍽 Menyu</span>
</nav>
<div class="container">
    <div class="detail-card">
        <h3>➕ Yangi taom qo'shish</h3>
        <form method="POST" class="add-form">
            <select name="category_id" required>
                <option value="">Kategoriya</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="name" placeholder="Nomi" required>
            <input type="text" name="description" placeholder="Tavsif">
            <input type="number" name="price" placeholder="Narx (so'm)" required step="500">
            <input type="text" name="image_url" placeholder="Rasm URL (ixtiyoriy)">
            <label><input type="checkbox" name="is_available" checked> Mavjud</label>
            <button type="submit" name="add_item" class="btn">Qo'shish</button>
        </form>
    </div>

    <table class="orders-table">
        <thead>
            <tr><th>#</th><th>Kategoriya</th><th>Nomi</th><th>Narx</th><th>Holat</th><th>Amal</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= $item['id'] ?></td>
                <td><?= htmlspecialchars($item['cat_name']) ?></td>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= number_format($item['price'], 0, '.', ' ') ?> so'm</td>
                <td>
                    <a href="?toggle=<?= $item['id'] ?>" class="badge <?= $item['is_available'] ? 'delivered' : 'cancelled' ?>">
                        <?= $item['is_available'] ? '✅ Mavjud' : '❌ Yopiq' ?>
                    </a>
                </td>
                <td>
                    <a href="?delete=<?= $item['id'] ?>" class="btn btn-danger"
                       onclick="return confirm('O\'chirishni tasdiqlaysizmi?')">🗑</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
