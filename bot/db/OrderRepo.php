<?php
require_once __DIR__ . '/Database.php';

class OrderRepo {
    private $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function create($userId, $total, $comment, $phone = '', $address = '') {
        try {
            // First try inserting with all fields
            $stmt = $this->db->prepare(
                'INSERT INTO orders (user_id, total_price, comment, phone, address) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $total, $comment, $phone, $address]);
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            // If it fails (maybe phone/address columns missing), try basic insert
            $stmt = $this->db->prepare(
                'INSERT INTO orders (user_id, total_price, comment) VALUES (?, ?, ?)'
            );
            $stmt->execute([$userId, $total, $comment]);
            $orderId = (int)$this->db->lastInsertId();
            
            // Try updating phone/address separately (so it doesn't crash the whole process)
            try { $this->db->prepare("UPDATE orders SET phone = ? WHERE id = ?")->execute([$phone, $orderId]); } catch(Exception $ex) {}
            try { $this->db->prepare("UPDATE orders SET address = ? WHERE id = ?")->execute([$address, $orderId]); } catch(Exception $ex) {}
            
            return $orderId;
        }
    }

    public function addItems($orderId, array $items) {
        $stmt = $this->db->prepare(
            'INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['menu_item_id'] ?? $item['id'], $item['quantity'], $item['price']]);
        }
    }

    public function findById($id) {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public function updateTracking($id, $link) {
        $stmt = $this->db->prepare('UPDATE orders SET tracking_link = ? WHERE id = ?');
        $stmt->execute([$link, $id]);
    }

    public function getActiveOrders() {
        $stmt = $this->db->query("SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.status NOT IN ('delivered', 'cancelled') ORDER BY o.id DESC");
        return $stmt->fetchAll();
    }
}
