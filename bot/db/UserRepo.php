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
        $stmt = $this->db->prepare(
            'INSERT INTO users (telegram_id, first_name, last_name, username, phone_number)
             VALUES (:telegram_id, :first_name, :last_name, :username, :phone_number)
             ON DUPLICATE KEY UPDATE phone_number = :phone_number'
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }
}
