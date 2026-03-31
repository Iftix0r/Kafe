<?php
require_once __DIR__ . '/Database.php';

class UserRepo {
    private PDO $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function findByTelegramId(int $telegramId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        // First try to find existing user
        $existing = $this->findByTelegramId($data['telegram_id']);
        
        if ($existing) {
            // Update existing user
            $sql = 'UPDATE users SET first_name = ?, last_name = ?, username = ?';
            $params = [
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['username'] ?? '',
            ];
            
            // Only update phone if provided
            if (!empty($data['phone_number'])) {
                $sql .= ', phone_number = ?';
                $params[] = $data['phone_number'];
            }
            
            $sql .= ' WHERE telegram_id = ?';
            $params[] = $data['telegram_id'];
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return (int)$existing['id'];
        } else {
            // Insert new user
            $stmt = $this->db->prepare(
                'INSERT INTO users (telegram_id, first_name, last_name, username, phone_number)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['telegram_id'],
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['username'] ?? '',
                $data['phone_number'] ?? null
            ]);
            return (int)$this->db->lastInsertId();
        }
    }
}
