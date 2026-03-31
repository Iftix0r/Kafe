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
    <title>Menyu boshqaruvi - Olmazor Go Admin</title>
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
        <span><i class="fas fa-utensils"></i> Menyu boshqaruvi</span>
    </div>
</nav>

<div class="container">
    <div class="detail-card">
        <h3><i class="fas fa-plus-circle"></i> Yangi taom qo'shish</h3>
        <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md); align-items: end;">
            <div class="form-group mb-0">
                <label class="form-label">Kategoriya</label>
                <select name="category_id" class="form-select" required>
                    <option value="">Kategoriyani tanlang</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Taom nomi</label>
                <input type="text" name="name" class="form-input" placeholder="Masalan: Osh" required>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Tavsif</label>
                <input type="text" name="description" class="form-input" placeholder="Qisqacha tavsif">
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Narx (so'm)</label>
                <input type="number" name="price" class="form-input" placeholder="25000" required step="500" min="0">
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Rasm URL</label>
                <input type="url" name="image_url" class="form-input" placeholder="https://example.com/image.jpg">
            </div>
            
            <div class="form-group mb-0" style="display: flex; align-items: center; gap: var(--spacing-sm);">
                <input type="checkbox" name="is_available" id="is_available" checked style="width: auto;">
                <label for="is_available" class="form-label mb-0">Mavjud</label>
            </div>
            
            <button type="submit" name="add_item" class="btn btn-success">
                <i class="fas fa-plus"></i> Qo'shish
            </button>
        </form>
    </div>

    <div class="table-container">
        <table class="orders-table">
            <thead>
                <tr>
                    <th><i class="fas fa-hashtag"></i> ID</th>
                    <th><i class="fas fa-image"></i> Rasm</th>
                    <th><i class="fas fa-tag"></i> Kategoriya</th>
                    <th><i class="fas fa-utensils"></i> Nomi</th>
                    <th><i class="fas fa-align-left"></i> Tavsif</th>
                    <th><i class="fas fa-money-bill"></i> Narx</th>
                    <th><i class="fas fa-toggle-on"></i> Holat</th>
                    <th><i class="fas fa-cogs"></i> Amallar</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong>#<?= $item['id'] ?></strong></td>
                    <td>
                        <?php if ($item['image_url']): ?>
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>"
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: var(--bg-secondary); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--text-secondary);">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['cat_name']) ?></td>
                    <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars($item['description']) ?>
                    </td>
                    <td><strong><?= number_format($item['price'], 0, '.', ' ') ?> so'm</strong></td>
                    <td>
                        <a href="?toggle=<?= $item['id'] ?>" class="status-badge <?= $item['is_available'] ? 'status-delivered' : 'status-cancelled' ?>">
                            <i class="fas <?= $item['is_available'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                            <?= $item['is_available'] ? 'Mavjud' : 'Yopiq' ?>
                        </a>
                    </td>
                    <td>
                        <div style="display: flex; gap: var(--spacing-sm);">
                            <button onclick="editItem(<?= $item['id'] ?>)" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $item['id'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('<?= htmlspecialchars($item['name']) ?> ni o\'chirishni tasdiqlaysizmi?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editItem(id) {
    // Simple edit functionality - you can expand this
    const newPrice = prompt('Yangi narxni kiriting:');
    if (newPrice && !isNaN(newPrice)) {
        // You would need to create an edit endpoint for this
        alert('Tahrirlash funksiyasi keyingi versiyada qo\'shiladi');
    }
}

// Add image preview functionality
document.querySelector('input[name="image_url"]').addEventListener('input', function() {
    const url = this.value;
    if (url) {
        // Create preview element if it doesn't exist
        let preview = document.getElementById('image-preview');
        if (!preview) {
            preview = document.createElement('img');
            preview.id = 'image-preview';
            preview.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: var(--radius); margin-top: var(--spacing-sm); display: none;';
            this.parentNode.appendChild(preview);
        }
        
        preview.src = url;
        preview.style.display = 'block';
        preview.onerror = function() {
            this.style.display = 'none';
        };
    }
});
</script>
</body>
</html>
