CREATE DATABASE IF NOT EXISTS app_store_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE app_store_db;

-- 1. Bảng tài khoản người dùng
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng ứng dụng / trò chơi
CREATE TABLE IF NOT EXISTS apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    dev VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL, -- 'app' hoặc 'game'
    rating DECIMAL(2,1) DEFAULT 5.0,
    price VARCHAR(50) DEFAULT 'Miễn phí',
    icon TEXT NOT NULL,
    version VARCHAR(20) DEFAULT '1.0.0',
    size VARCHAR(20) DEFAULT '50 MB',
    `desc` TEXT NOT NULL,
    long_desc TEXT NOT NULL,
    download_link TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng ảnh chụp màn hình ứng dụng
CREATE TABLE IF NOT EXISTS app_screenshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    image_url TEXT NOT NULL,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE
);

-- 4. Bảng gói dịch vụ mạng
CREATE TABLE IF NOT EXISTS network_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    package_type VARCHAR(50) NOT NULL, -- 'vps', 'proxy', 'server'
    speed VARCHAR(50) NOT NULL,
    bandwidth VARCHAR(50) NOT NULL,
    price VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tài khoản Admin mẫu (Username: admin | Password: 123456)
INSERT INTO users (id, username, email, password, is_admin) VALUES 
(1, 'admin', 'admin@appstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Dữ liệu mẫu ứng dụng
INSERT INTO apps (id, name, dev, category, rating, price, icon, version, size, `desc`, long_desc, download_link) VALUES
(1, 'Visual Studio Code', 'Microsoft Corporation', 'app', 4.9, 'Miễn phí', 'https://upload.wikimedia.org/wikipedia/commons/9/9a/Visual_Studio_Code_1.35_icon.svg', '1.85.0', '88.4 MB', 'Trình soạn thảo mã nguồn nhẹ, mạnh mẽ hỗ trợ đa ngôn ngữ.', 'Visual Studio Code là trình soạn thảo mã nguồn mở mạnh mẽ phát triển bởi Microsoft.', '#'),
(2, 'Genshin Impact', 'COGNOSPHERE', 'game', 4.7, 'Miễn phí', 'https://upload.wikimedia.org/wikipedia/en/5/5d/Genshin_Impact_logo.svg', '4.3.0', '3.2 GB', 'Tựa game nhập vai phiêu lưu thế giới mở kỳ ảo tuyệt đẹp.', 'Bước chân vào Teyvat - thế giới ảo rộng lớn tràn ngập các nguyên tố luân chuyển.', '#');

-- Dữ liệu mẫu dịch vụ mạng
INSERT INTO network_services (name, package_type, speed, bandwidth, price, description) VALUES
('VPS Cloud Pro #1', 'vps', '1 Gbps', 'Unmetered', '150.000đ/tháng', 'VPS hiệu năng cao tối ưu cho bot automation và script tự động hóa.'),
('Proxy SOCKS5 Private', 'proxy', '100 Mbps', '1000 GB', '50.000đ/tháng', 'Proxy tốc độ cao, IP sạch hỗ trợ đa luồng kết nối ổn định.');
