CREATE DATABASE IF NOT EXISTS olmazorgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE olmazorgo;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  telegram_id BIGINT UNIQUE NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  username VARCHAR(100),
  phone_number VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort_order INT DEFAULT 0
);

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  image_url VARCHAR(255),
  is_available TINYINT(1) DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  total_price DECIMAL(10,2),
  status ENUM('new','confirmed','preparing','delivered','cancelled') DEFAULT 'new',
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  menu_item_id INT NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

-- Sample data
INSERT INTO categories (name, sort_order) VALUES ('Salatlar', 1), ('Asosiy taomlar', 2), ('Ichimliklar', 3);
INSERT INTO menu_items (category_id, name, description, price) VALUES
  (1, 'Toshkent salati', 'Yangi sabzavotlar', 15000),
  (2, 'Osh', 'An\'anaviy o\'zbek oshi', 35000),
  (2, 'Shashlik', 'Qo\'y go\'shtidan', 45000),
  (3, 'Choy', 'Ko\'k choy', 5000),
  (3, 'Coca-Cola', '0.5L', 8000);
