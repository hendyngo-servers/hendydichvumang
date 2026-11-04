CREATE TABLE IF NOT EXISTS users (
  telegram_user_id INTEGER PRIMARY KEY,
  username TEXT,
  first_name TEXT,
  last_name TEXT,
  language_code TEXT,
  is_premium INTEGER NOT NULL DEFAULT 0 CHECK (is_premium IN (0, 1)),
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'blocked', 'pending')),
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS wallet_accounts (
  telegram_user_id INTEGER PRIMARY KEY REFERENCES users(telegram_user_id) ON DELETE CASCADE,
  balance_vnd INTEGER NOT NULL DEFAULT 0 CHECK (balance_vnd >= 0),
  updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS wallet_ledger (
  id TEXT PRIMARY KEY,
  telegram_user_id INTEGER NOT NULL REFERENCES users(telegram_user_id),
  amount_delta_vnd INTEGER NOT NULL CHECK (amount_delta_vnd <> 0),
  balance_after_vnd INTEGER NOT NULL CHECK (balance_after_vnd >= 0),
  entry_type TEXT NOT NULL,
  reference_type TEXT NOT NULL,
  reference_id TEXT NOT NULL,
  note TEXT,
  created_at INTEGER NOT NULL,
  UNIQUE(reference_type, reference_id)
);
CREATE INDEX IF NOT EXISTS idx_wallet_ledger_user_created
  ON wallet_ledger(telegram_user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS topup_orders (
  id TEXT PRIMARY KEY,
  telegram_user_id INTEGER NOT NULL REFERENCES users(telegram_user_id),
  amount_vnd INTEGER NOT NULL CHECK (amount_vnd BETWEEN 10000 AND 100000000),
  transfer_note TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'expired', 'cancelled', 'review')),
  provider TEXT,
  provider_transaction_id TEXT UNIQUE,
  expires_at INTEGER NOT NULL,
  paid_at INTEGER,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_topup_user_created
  ON topup_orders(telegram_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_topup_pending_expiry
  ON topup_orders(status, expires_at);

CREATE TABLE IF NOT EXISTS payment_events (
  id TEXT PRIMARY KEY,
  provider TEXT NOT NULL,
  provider_event_id TEXT NOT NULL,
  topup_order_id TEXT REFERENCES topup_orders(id),
  amount_vnd INTEGER NOT NULL,
  payload_hash TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'received',
  created_at INTEGER NOT NULL,
  processed_at INTEGER,
  UNIQUE(provider, provider_event_id)
);

CREATE TABLE IF NOT EXISTS orders (
  id TEXT PRIMARY KEY,
  telegram_user_id INTEGER NOT NULL REFERENCES users(telegram_user_id),
  order_type TEXT NOT NULL,
  product_code TEXT,
  quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
  amount_vnd INTEGER NOT NULL CHECK (amount_vnd >= 0),
  status TEXT NOT NULL DEFAULT 'pending',
  provider_reference TEXT,
  expires_at INTEGER,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_orders_user_created
  ON orders(telegram_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_orders_status_expiry
  ON orders(status, expires_at);

CREATE TABLE IF NOT EXISTS proxy_leases (
  id TEXT PRIMARY KEY,
  order_id TEXT NOT NULL UNIQUE REFERENCES orders(id),
  telegram_user_id INTEGER NOT NULL REFERENCES users(telegram_user_id),
  provider_lease_id TEXT,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'revoke_pending', 'expired', 'revoked', 'failed')),
  expires_at INTEGER NOT NULL,
  revoked_at INTEGER,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_proxy_lease_expiry
  ON proxy_leases(status, expires_at);

CREATE TABLE IF NOT EXISTS otp_orders (
  id TEXT PRIMARY KEY,
  telegram_user_id INTEGER NOT NULL REFERENCES users(telegram_user_id),
  provider TEXT,
  provider_order_id TEXT,
  service_code TEXT NOT NULL,
  phone_number TEXT,
  status TEXT NOT NULL DEFAULT 'waiting' CHECK (status IN ('waiting', 'received', 'cancelled', 'expired', 'failed')),
  expires_at INTEGER NOT NULL,
  received_at INTEGER,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_otp_status_expiry
  ON otp_orders(status, expires_at);
CREATE INDEX IF NOT EXISTS idx_otp_user_created
  ON otp_orders(telegram_user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS vip_memberships (
  telegram_user_id INTEGER PRIMARY KEY REFERENCES users(telegram_user_id) ON DELETE CASCADE,
  tier TEXT NOT NULL DEFAULT 'free',
  status TEXT NOT NULL DEFAULT 'active',
  starts_at INTEGER,
  expires_at INTEGER,
  updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS vip_checkins (
  id TEXT PRIMARY KEY,
  telegram_user_id INTEGER NOT NULL REFERENCES users(telegram_user_id),
  checkin_date TEXT NOT NULL,
  points_awarded INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL,
  UNIQUE(telegram_user_id, checkin_date)
);

CREATE TABLE IF NOT EXISTS tickets (
  id TEXT PRIMARY KEY,
  telegram_user_id INTEGER NOT NULL REFERENCES users(telegram_user_id),
  subject TEXT NOT NULL,
  message TEXT NOT NULL,
  category TEXT NOT NULL DEFAULT 'general',
  status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'resolved', 'closed')),
  assigned_admin_id INTEGER,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_tickets_user_created
  ON tickets(telegram_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_tickets_status_updated
  ON tickets(status, updated_at);

CREATE TABLE IF NOT EXISTS service_catalog (
  code TEXT PRIMARY KEY,
  title TEXT NOT NULL,
  description TEXT,
  enabled INTEGER NOT NULL DEFAULT 0 CHECK (enabled IN (0, 1)),
  sort_order INTEGER NOT NULL DEFAULT 0,
  updated_at INTEGER NOT NULL
);

INSERT OR IGNORE INTO service_catalog (code, title, description, enabled, sort_order, updated_at) VALUES
  ('live', 'Minigame Live', 'Dịch vụ Minigame Live', 0, 10, unixepoch()),
  ('rental', 'Thuê Tool', 'Dịch vụ thuê công cụ', 0, 20, unixepoch()),
  ('otp', 'OTP', 'Dịch vụ nhận mã OTP', 0, 30, unixepoch()),
  ('proxy', 'Proxy', 'Dịch vụ Proxy', 0, 40, unixepoch()),
  ('vip', 'Kho VIP', 'Gói thành viên VIP', 0, 50, unixepoch()),
  ('code', 'Mua Code', 'Kho mã Code', 0, 60, unixepoch());
