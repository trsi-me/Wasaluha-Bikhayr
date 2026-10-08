CREATE DATABASE IF NOT EXISTS u741730784_wasaluha_bikha CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE u741730784_wasaluha_bikha;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('donor','needy') NOT NULL DEFAULT 'needy',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  type VARCHAR(100) DEFAULT NULL,
  status ENUM('open','claimed','fulfilled','closed') NOT NULL DEFAULT 'open',
  urgent TINYINT(1) DEFAULT 0,
  amount DECIMAL(10,2) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  from_user INT NOT NULL,
  to_user INT NOT NULL,
  message VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_flag TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, phone, password, role) VALUES
('أحمد محمد', 'ahmed@example.com', '0501234567', '', 'donor'),
('فاطمة علي', 'fatima@example.com', '0502345678', '', 'needy'),
('خالد سعيد', 'khalid@example.com', '0503456789', '', 'donor'),
('سارة أحمد', 'sara@example.com', '0504567890', '', 'needy'),
('محمد حسن', 'mohammed@example.com', '0505678901', '', 'donor');

INSERT INTO cases (user_id, item_name, quantity, type, status, urgent, amount, created_at) VALUES
(2, 'أرز', 50, 'طعام', 'open', 0, NULL, NOW() - INTERVAL 2 DAY),
(2, 'زيت طعام', 20, 'طعام', 'open', 1, NULL, NOW() - INTERVAL 1 DAY),
(4, 'ملابس أطفال', 30, 'ملابس', 'open', 0, NULL, NOW() - INTERVAL 3 DAY),
(4, 'أدوية', 15, 'أدوية', 'open', 1, NULL, NOW() - INTERVAL 5 HOUR),
(2, 'سكر', 25, 'طعام', 'claimed', 0, NULL, NOW() - INTERVAL 4 DAY),
(4, 'بطاطين', 10, 'ملابس', 'open', 0, NULL, NOW() - INTERVAL 6 HOUR),
(2, 'دقيق', 40, 'طعام', 'open', 0, NULL, NOW() - INTERVAL 1 DAY),
(4, 'حليب أطفال', 12, 'طعام', 'open', 1, NULL, NOW() - INTERVAL 2 HOUR),
(2, 'تبرع نقدي', 1, 'نقدي', 'claimed', 0, 500.00, NOW() - INTERVAL 3 DAY),
(4, 'تبرع نقدي', 1, 'نقدي', 'claimed', 0, 1000.00, NOW() - INTERVAL 2 DAY),
(2, 'تبرع نقدي', 1, 'نقدي', 'fulfilled', 0, 750.00, NOW() - INTERVAL 5 DAY),
(4, 'تبرع نقدي', 1, 'نقدي', 'open', 1, 300.00, NOW() - INTERVAL 1 HOUR);

INSERT INTO notifications (from_user, to_user, message, created_at, read_flag) VALUES
(1, 2, 'تم توفير حالتك: سكر. سيتم الاستلام من: جمعية يد الخير', NOW() - INTERVAL 1 DAY, 0),
(3, 4, 'تم توفير حالتك: ملابس أطفال. سيتم الاستلام من: جمعية يد الخير', NOW() - INTERVAL 2 DAY, 1);
