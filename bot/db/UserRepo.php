<?php
require_once __DIR__ . '/Database.php';

class UserRepo {
    private $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function findByTelegramId($telegramId) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data) {
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
                
                // Log contact sharing activity
                $this->logActivity($existing['id'], 'contact_shared', [
                    'phone_number' => $data['phone_number'],
                    'updated_profile' => true
                ]);
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
            
            $userId = (int)$this->db->lastInsertId();
            
            // Log new user activity
            $this->logActivity($userId, 'new_user', [
                'telegram_id' => $data['telegram_id'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'username' => $data['username'] ?? '',
                'phone_number' => $data['phone_number'] ?? null,
                'registration_source' => 'telegram_bot'
            ]);
            
            return $userId;
        }
    }

    public function logStartCommand($telegramId) {
        $user = $this->findByTelegramId($telegramId);
        if ($user) {
            $this->logActivity($user['id'], 'start_command', [
                'telegram_id' => $telegramId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    private function logActivity($userId, $activityType, $data) {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO user_activities (user_id, activity_type, activity_data) VALUES (?, ?, ?)'
            );
            $stmt->execute([$userId, $activityType, json_encode($data)]);
        } catch (Exception $e) {
            error_log("Failed to log user activity: " . $e->getMessage());
        }
    }

    public function getNewUsersStats() {
        try {
            $stmt = $this->db->query('SELECT * FROM new_users_today');
            return $stmt->fetch() ?: [
                'total_new_users' => 0,
                'today_new_users' => 0,
                'week_new_users' => 0,
                'month_new_users' => 0
            ];
        } catch (Exception $e) {
            error_log("Failed to get new users stats: " . $e->getMessage());
            return [
                'total_new_users' => 0,
                'today_new_users' => 0,
                'week_new_users' => 0,
                'month_new_users' => 0
            ];
        }
    }

    public function getRecentNewUsers($limit = 10) {
        try {
            $stmt = $this->db->prepare(
                'SELECT u.*, ua.created_at as registration_time, ua.activity_data
                 FROM users u
                 JOIN user_activities ua ON u.id = ua.user_id
                 WHERE ua.activity_type = "new_user"
                 ORDER BY ua.created_at DESC
                 LIMIT ?'
            );
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get recent new users: " . $e->getMessage());
            return [];
        }
    }

    public function countAll() {
        try {
            return (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}
