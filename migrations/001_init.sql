CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  telegram_id BIGINT UNIQUE,
  username TEXT,
  first_name TEXT NOT NULL DEFAULT '',
  last_name TEXT NOT NULL DEFAULT '',
  language_code TEXT NOT NULL DEFAULT 'vi',
  role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS wallets (
  user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  balance NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (balance >= 0),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS wallet_transactions (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  amount NUMERIC(18,2) NOT NULL,
  balance_after NUMERIC(18,2) NOT NULL,
  type TEXT NOT NULL CHECK (type IN ('topup','purchase','adjustment','refund')),
  description TEXT NOT NULL DEFAULT '',
  reference TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS service_catalog (
  id BIGSERIAL PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  category TEXT NOT NULL DEFAULT 'Khác',
  description TEXT NOT NULL DEFAULT '',
  price NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (price >= 0),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order INTEGER NOT NULL DEFAULT 100,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS tickets (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  subject TEXT NOT NULL,
  message TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','processing','closed')),
  admin_note TEXT NOT NULL DEFAULT '',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS auth_sessions (
  id BIGSERIAL PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  role TEXT NOT NULL CHECK (role IN ('user','admin')),
  expires_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS auth_sessions_expires_idx ON auth_sessions(expires_at);
CREATE INDEX IF NOT EXISTS tickets_status_created_idx ON tickets(status, created_at DESC);
CREATE INDEX IF NOT EXISTS wallet_tx_user_created_idx ON wallet_transactions(user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGSERIAL PRIMARY KEY,
  actor TEXT NOT NULL,
  action TEXT NOT NULL,
  entity TEXT NOT NULL,
  entity_id TEXT,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO service_catalog (code, name, category, description, price, is_active, sort_order)
VALUES
  ('tool-live', 'Thuê Tool Live', 'Tool & Code', 'Liên hệ quản trị viên để được tư vấn gói phù hợp.', 0, TRUE, 10),
  ('proxy', 'Proxy', 'OTP & Proxy', 'Dịch vụ Proxy sẽ được kích hoạt sau khi kết nối nhà cung cấp.', 0, TRUE, 20),
  ('vip', 'Kho VIP', 'VIP', 'Liên hệ quản trị viên để kiểm tra quyền lợi và thời hạn.', 0, TRUE, 30),
  ('source-code', 'Mua Code', 'Tool & Code', 'Yêu cầu tư vấn mã nguồn và phạm vi bàn giao.', 0, TRUE, 40)
ON CONFLICT (code) DO NOTHING;
