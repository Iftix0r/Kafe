<?php
require_once __DIR__ . '/Database.php';

class OrderRepo {
    private PDO $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function create(int $userId, float $total, string $comment): int {
        $stmt = $this->db->prepare(
            'INSERT INTO orders (user_id, total_price, comment) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $total, $comment]);
        return (int)$this->db->lastInsertId();
    }

    public function addItems(int $orderId, array $items): void {
        $stmt = $this->db->prepare(
            'INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['menu_item_id'], $item['quantity'], $item['price']]);
        }
    }
}
