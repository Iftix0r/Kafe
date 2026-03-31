-- Yangi foydalanuvchilar uchun jadval
CREATE TABLE IF NOT EXISTS user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('new_user', 'start_command', 'contact_shared', 'first_order') NOT NULL,
    activity_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
);

-- Yangi foydalanuvchilar statistikasi uchun view
CREATE OR REPLACE VIEW new_users_today AS
SELECT 
    COUNT(*) as total_new_users,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_new_users,
    COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_new_users,
    COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_new_users
FROM users;