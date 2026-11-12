-- HENDY App Store module migration for the current Neon PostgreSQL website DB.
-- Run as the database owner against hendydichvumang_website.
-- The runtime role only needs DML rights. This migration is additive/idempotent.
CREATE TABLE IF NOT EXISTS apps (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  dev VARCHAR(150) NOT NULL,
  category VARCHAR(8) NOT NULL DEFAULT 'app' CHECK (category IN ('app','game')),
  rating NUMERIC(2,1) NOT NULL DEFAULT 5.0 CHECK (rating >= 0 AND rating <= 5),
  price VARCHAR(50) NOT NULL DEFAULT 'Miễn phí',
  icon VARCHAR(2048) NOT NULL,
  version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  size VARCHAR(20) NOT NULL DEFAULT '',
  short_description VARCHAR(2000) NOT NULL,
  long_description TEXT NOT NULL DEFAULT '',
  download_link VARCHAR(2048) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'published' CHECK (status IN ('published','draft')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_apps_status_category_created ON apps(status,category,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_apps_name ON apps(name);

CREATE TABLE IF NOT EXISTS app_screenshots (
  id BIGSERIAL PRIMARY KEY,
  app_id BIGINT NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
  image_url VARCHAR(255) NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_app_screenshots_app ON app_screenshots(app_id,sort_order,id);

CREATE TABLE IF NOT EXISTS network_services (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'Dịch vụ mạng',
  price VARCHAR(100) NOT NULL DEFAULT 'Liên hệ',
  duration VARCHAR(100) NOT NULL DEFAULT '',
  bandwidth VARCHAR(100) NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '',
  features TEXT NOT NULL DEFAULT '',
  image_url VARCHAR(2048) NOT NULL DEFAULT '',
  order_url VARCHAR(2048) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'published' CHECK (status IN ('published','draft')),
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_network_services_status_sort ON network_services(status,sort_order,created_at DESC);

GRANT SELECT,INSERT,UPDATE,DELETE ON apps,app_screenshots,network_services TO hendy_website_app;
GRANT USAGE,SELECT ON SEQUENCE apps_id_seq,app_screenshots_id_seq,network_services_id_seq TO hendy_website_app;
