<?php
require_once __DIR__ . '/Database.php';

class OrderRepo {
    private PDO $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function create(int $userId, float $total, string $comment, string $phone = '', string $address = ''): int {
        $stmt = $this->db->prepare(
            'INSERT INTO orders (user_id, total_price, comment, phone, address) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $total, $comment, $phone, $address]);
        return (int)$this->db->lastInsertId();
    }

    public function addItems(int $orderId, array $items): void {
        $stmt = $this->db->prepare(
            'INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['menu_item_id'] ?? $item['id'], $item['quantity'], $item['price']]);
        }
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): void {
        $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public function updateTracking(int $id, string $link): void {
        $stmt = $this->db->prepare('UPDATE orders SET tracking_link = ? WHERE id = ?');
        $stmt->execute([$link, $id]);
    }

    public function getActiveOrders(): array {
        $stmt = $this->db->query("SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.status NOT IN ('delivered', 'cancelled') ORDER BY o.id DESC");
        return $stmt->fetchAll();
    }
}
