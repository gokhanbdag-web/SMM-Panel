CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,
 role ENUM('user','admin') NOT NULL DEFAULT 'user',balance DECIMAL(12,2) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,category VARCHAR(100) NOT NULL,name VARCHAR(160) NOT NULL,
 description TEXT,price_per_1000 DECIMAL(12,4) NOT NULL,min_qty INT NOT NULL DEFAULT 100,max_qty INT NOT NULL DEFAULT 100000,
 active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE topups (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,amount DECIMAL(12,2) NOT NULL,
 method ENUM('bank','online') NOT NULL,status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 note VARCHAR(500),created_at DATETIME NOT NULL,INDEX(user_id,status),
 CONSTRAINT fk_topups_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,service_id BIGINT UNSIGNED NOT NULL,
 link VARCHAR(2048) NOT NULL,quantity INT NOT NULL,charge DECIMAL(12,2) NOT NULL,
 status ENUM('pending','processing','completed','partial','canceled') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL,INDEX(user_id,status),
 CONSTRAINT fk_orders_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_orders_service FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO services(category,name,description,price_per_1000,min_qty,max_qty) VALUES
('Instagram','Instagram Takipçi','Demo hizmet; gerçek teslimat için servis sağlayıcı API bağlantısı gerekir.',35,100,50000),
('Instagram','Instagram Beğeni','Demo hizmet; gerçek teslimat için servis sağlayıcı API bağlantısı gerekir.',12,50,100000),
('TikTok','TikTok Beğeni','Demo hizmet; gerçek teslimat için servis sağlayıcı API bağlantısı gerekir.',18,50,100000),
('YouTube','YouTube İzlenme','Demo hizmet; gerçek teslimat için servis sağlayıcı API bağlantısı gerekir.',25,100,100000);
