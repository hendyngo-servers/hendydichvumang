-- PostgreSQL schema for the independent HENDY website database.
CREATE TABLE IF NOT EXISTS users (
 id BIGSERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL, role VARCHAR(16) NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS products (
 id BIGSERIAL PRIMARY KEY, title VARCHAR(180) NOT NULL, category VARCHAR(100) NOT NULL DEFAULT '',
 price VARCHAR(100) NOT NULL DEFAULT '', description TEXT NOT NULL, external_url VARCHAR(2048),
 status VARCHAR(16) NOT NULL DEFAULT 'published' CHECK (status IN ('published','draft')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS screenshots (
 id BIGSERIAL PRIMARY KEY, product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
 image_path VARCHAR(255) NOT NULL, caption VARCHAR(255) NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 0,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_website_products_status_created ON products(status,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_website_products_category ON products(category);
CREATE INDEX IF NOT EXISTS idx_website_screenshots_product ON screenshots(product_id,sort_order,id);
GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES IN SCHEMA public TO hendy_website_app;
GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA public TO hendy_website_app;
ALTER DEFAULT PRIVILEGES FOR ROLE neondb_owner IN SCHEMA public GRANT SELECT,INSERT,UPDATE,DELETE ON TABLES TO hendy_website_app;
ALTER DEFAULT PRIVILEGES FOR ROLE neondb_owner IN SCHEMA public GRANT USAGE,SELECT ON SEQUENCES TO hendy_website_app;
