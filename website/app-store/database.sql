-- HENDY App Store module schema (MySQL 8.0+).
-- The module reuses the parent website's users table and login session.
-- Import into the existing website database only when its users table follows
-- the HENDY role-based schema: id,name,email,password,role.
-- Production on Render uses Neon PostgreSQL; use database.postgresql.sql there.

CREATE TABLE IF NOT EXISTS apps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  dev VARCHAR(150) NOT NULL,
  category ENUM('app','game') NOT NULL DEFAULT 'app',
  rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  price VARCHAR(50) NOT NULL DEFAULT 'Miễn phí',
  icon VARCHAR(2048) NOT NULL,
  version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  size VARCHAR(20) NOT NULL DEFAULT '',
  short_description VARCHAR(2000) NOT NULL,
  long_description MEDIUMTEXT NOT NULL,
  download_link VARCHAR(2048) NOT NULL DEFAULT '',
  status ENUM('published','draft') NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_apps_status_category_created(status,category,created_at),
  KEY idx_apps_name(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_screenshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  app_id BIGINT UNSIGNED NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_app_screenshots_app(app_id,sort_order,id),
  CONSTRAINT fk_app_screenshots_app FOREIGN KEY(app_id)
    REFERENCES apps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS network_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'Dịch vụ mạng',
  price VARCHAR(100) NOT NULL DEFAULT 'Liên hệ',
  duration VARCHAR(100) NOT NULL DEFAULT '',
  bandwidth VARCHAR(100) NOT NULL DEFAULT '',
  description MEDIUMTEXT NOT NULL,
  features MEDIUMTEXT NOT NULL,
  image_url VARCHAR(2048) NOT NULL DEFAULT '',
  order_url VARCHAR(2048) NOT NULL DEFAULT '',
  status ENUM('published','draft') NOT NULL DEFAULT 'published',
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_network_services_status_sort(status,sort_order,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Runtime user: GRANT SELECT,INSERT,UPDATE,DELETE on these tables.
-- Do not add a default Admin or hard-coded password here.
