-- HENDY commerce domain foundation.
-- All modules start disabled until their Node.js implementation and reconciliation
-- procedures have been reviewed and verified in production.
CREATE TABLE IF NOT EXISTS commerce_feature_flags (
  code VARCHAR(40) PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  enabled BOOLEAN NOT NULL DEFAULT FALSE,
  backend_ready BOOLEAN NOT NULL DEFAULT FALSE,
  reconciliation_verified BOOLEAN NOT NULL DEFAULT FALSE,
  updated_by VARCHAR(160),
  notes VARCHAR(1000) NOT NULL DEFAULT '',
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT commerce_feature_requires_readiness
    CHECK (NOT enabled OR (backend_ready AND reconciliation_verified))
);

INSERT INTO commerce_feature_flags (code, label, enabled, backend_ready, reconciliation_verified, notes)
VALUES
  ('payments', 'Thanh toán', FALSE, FALSE, FALSE, 'Chưa cấu hình cổng thanh toán và đối soát.'),
  ('orders', 'Đơn hàng', FALSE, FALSE, FALSE, 'Chờ hoàn thiện vòng đời đơn hàng và ledger.'),
  ('vip', 'Thành viên VIP', FALSE, FALSE, FALSE, 'Chờ hoàn thiện cấp bậc, thời hạn và gia hạn.'),
  ('vouchers', 'Voucher', FALSE, FALSE, FALSE, 'Chờ hoàn thiện điều kiện áp dụng và chống dùng lặp.'),
  ('giftcodes', 'Giftcode', FALSE, FALSE, FALSE, 'Chờ hoàn thiện phát hành, redeem và đối soát.'),
  ('referrals', 'Giới thiệu bạn bè', FALSE, FALSE, FALSE, 'Chờ hoàn thiện điều kiện hợp lệ và thưởng.'),
  ('video_jobs', 'Video Jobs', FALSE, FALSE, FALSE, 'Chờ worker, định giá, trạng thái và đối soát.')
ON CONFLICT (code) DO NOTHING;

CREATE TABLE IF NOT EXISTS commerce_orders (
  id BIGSERIAL PRIMARY KEY,
  order_code VARCHAR(48) NOT NULL UNIQUE,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  status VARCHAR(24) NOT NULL DEFAULT 'pending_payment'
    CHECK (status IN ('draft','pending_payment','paid','processing','fulfilled','cancelled','refunded','failed')),
  currency CHAR(3) NOT NULL DEFAULT 'VND',
  subtotal NUMERIC(18,2) NOT NULL CHECK (subtotal >= 0),
  discount_total NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (discount_total >= 0 AND discount_total <= subtotal),
  total NUMERIC(18,2) NOT NULL CHECK (total >= 0),
  idempotency_key VARCHAR(120) NOT NULL,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (user_id, idempotency_key),
  CHECK (total = subtotal - discount_total)
);

CREATE TABLE IF NOT EXISTS commerce_order_items (
  id BIGSERIAL PRIMARY KEY,
  order_id BIGINT NOT NULL REFERENCES commerce_orders(id) ON DELETE RESTRICT,
  service_id BIGINT REFERENCES service_catalog(id) ON DELETE SET NULL,
  item_code VARCHAR(80) NOT NULL,
  item_name VARCHAR(160) NOT NULL,
  quantity INTEGER NOT NULL CHECK (quantity > 0),
  unit_price NUMERIC(18,2) NOT NULL CHECK (unit_price >= 0),
  line_total NUMERIC(18,2) NOT NULL CHECK (line_total = quantity * unit_price),
  snapshot JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS payment_intents (
  id BIGSERIAL PRIMARY KEY,
  order_id BIGINT REFERENCES commerce_orders(id) ON DELETE RESTRICT,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  provider VARCHAR(48) NOT NULL,
  payment_method VARCHAR(48) NOT NULL,
  amount NUMERIC(18,2) NOT NULL CHECK (amount > 0),
  currency CHAR(3) NOT NULL DEFAULT 'VND',
  status VARCHAR(24) NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending','confirmed','rejected','expired','refunded','reconciliation_hold')),
  provider_reference VARCHAR(160),
  idempotency_key VARCHAR(120) NOT NULL UNIQUE,
  provider_payload_hash CHAR(64),
  confirmed_at TIMESTAMPTZ,
  expires_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS payment_intents_provider_reference_idx
  ON payment_intents(provider, provider_reference)
  WHERE provider_reference IS NOT NULL;

CREATE TABLE IF NOT EXISTS commerce_order_events (
  id BIGSERIAL PRIMARY KEY,
  order_id BIGINT NOT NULL REFERENCES commerce_orders(id) ON DELETE RESTRICT,
  actor VARCHAR(160) NOT NULL,
  event_code VARCHAR(48) NOT NULL,
  from_status VARCHAR(24),
  to_status VARCHAR(24),
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS vip_tiers (
  id BIGSERIAL PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  minimum_spend NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (minimum_spend >= 0),
  duration_days INTEGER NOT NULL CHECK (duration_days > 0),
  benefits JSONB NOT NULL DEFAULT '{}'::jsonb,
  is_active BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS vip_memberships (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  tier_id BIGINT NOT NULL REFERENCES vip_tiers(id) ON DELETE RESTRICT,
  status VARCHAR(16) NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending','active','expired','suspended','cancelled')),
  starts_at TIMESTAMPTZ,
  ends_at TIMESTAMPTZ,
  source_order_id BIGINT REFERENCES commerce_orders(id) ON DELETE RESTRICT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
);
CREATE INDEX IF NOT EXISTS vip_memberships_user_status_idx
  ON vip_memberships(user_id, status, ends_at DESC);

CREATE TABLE IF NOT EXISTS vouchers (
  id BIGSERIAL PRIMARY KEY,
  code_hash CHAR(64) NOT NULL UNIQUE,
  discount_type VARCHAR(16) NOT NULL CHECK (discount_type IN ('fixed','percent')),
  discount_value NUMERIC(18,2) NOT NULL CHECK (discount_value > 0),
  minimum_order NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (minimum_order >= 0),
  max_redemptions INTEGER CHECK (max_redemptions IS NULL OR max_redemptions > 0),
  redeemed_count INTEGER NOT NULL DEFAULT 0 CHECK (redeemed_count >= 0),
  starts_at TIMESTAMPTZ,
  expires_at TIMESTAMPTZ,
  is_active BOOLEAN NOT NULL DEFAULT FALSE,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS voucher_redemptions (
  id BIGSERIAL PRIMARY KEY,
  voucher_id BIGINT NOT NULL REFERENCES vouchers(id) ON DELETE RESTRICT,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  order_id BIGINT NOT NULL REFERENCES commerce_orders(id) ON DELETE RESTRICT,
  discount_amount NUMERIC(18,2) NOT NULL CHECK (discount_amount >= 0),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (voucher_id, user_id, order_id)
);

CREATE TABLE IF NOT EXISTS giftcodes (
  id BIGSERIAL PRIMARY KEY,
  code_hash CHAR(64) NOT NULL UNIQUE,
  benefit_type VARCHAR(24) NOT NULL CHECK (benefit_type IN ('wallet_credit','discount','vip_days','service')),
  benefit_value NUMERIC(18,2) NOT NULL CHECK (benefit_value > 0),
  max_redemptions INTEGER NOT NULL DEFAULT 1 CHECK (max_redemptions > 0),
  redeemed_count INTEGER NOT NULL DEFAULT 0 CHECK (redeemed_count >= 0),
  starts_at TIMESTAMPTZ,
  expires_at TIMESTAMPTZ,
  is_active BOOLEAN NOT NULL DEFAULT FALSE,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS giftcode_redemptions (
  id BIGSERIAL PRIMARY KEY,
  giftcode_id BIGINT NOT NULL REFERENCES giftcodes(id) ON DELETE RESTRICT,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  ledger_reference VARCHAR(160),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (giftcode_id, user_id)
);

CREATE TABLE IF NOT EXISTS referral_codes (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE RESTRICT,
  code VARCHAR(32) NOT NULL UNIQUE,
  is_active BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS referral_attributions (
  id BIGSERIAL PRIMARY KEY,
  referral_code_id BIGINT NOT NULL REFERENCES referral_codes(id) ON DELETE RESTRICT,
  referred_user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE RESTRICT,
  status VARCHAR(20) NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending','qualified','rejected','rewarded')),
  qualified_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS referral_rewards (
  id BIGSERIAL PRIMARY KEY,
  attribution_id BIGINT NOT NULL UNIQUE REFERENCES referral_attributions(id) ON DELETE RESTRICT,
  referrer_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  referred_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  amount NUMERIC(18,2) NOT NULL CHECK (amount > 0),
  status VARCHAR(16) NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending','approved','paid','reversed')),
  ledger_reference VARCHAR(160) UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS video_jobs (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  job_code VARCHAR(48) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'queued'
    CHECK (status IN ('queued','validated','processing','completed','failed','cancelled','refunded')),
  provider VARCHAR(48),
  provider_job_id VARCHAR(160),
  input_data JSONB NOT NULL DEFAULT '{}'::jsonb,
  result_data JSONB NOT NULL DEFAULT '{}'::jsonb,
  quoted_amount NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (quoted_amount >= 0),
  charged_amount NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (charged_amount >= 0),
  error_code VARCHAR(80),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  started_at TIMESTAMPTZ,
  completed_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS commerce_ledger_entries (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  wallet_transaction_id BIGINT REFERENCES wallet_transactions(id) ON DELETE RESTRICT,
  order_id BIGINT REFERENCES commerce_orders(id) ON DELETE RESTRICT,
  payment_intent_id BIGINT REFERENCES payment_intents(id) ON DELETE RESTRICT,
  entry_type VARCHAR(24) NOT NULL
    CHECK (entry_type IN ('deposit','purchase','refund','voucher','giftcode','referral','video_job','adjustment')),
  amount NUMERIC(18,2) NOT NULL CHECK (amount <> 0),
  currency CHAR(3) NOT NULL DEFAULT 'VND',
  reference VARCHAR(160) NOT NULL UNIQUE,
  idempotency_key VARCHAR(120) NOT NULL UNIQUE,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS payment_reconciliation_batches (
  id BIGSERIAL PRIMARY KEY,
  provider VARCHAR(48) NOT NULL,
  statement_reference VARCHAR(160) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'VND',
  statement_total NUMERIC(18,2) NOT NULL CHECK (statement_total >= 0),
  matched_total NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (matched_total >= 0),
  status VARCHAR(20) NOT NULL DEFAULT 'draft'
    CHECK (status IN ('draft','processing','reconciled','mismatched','failed')),
  report_hash CHAR(64),
  started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  completed_at TIMESTAMPTZ,
  UNIQUE (provider, statement_reference)
);

CREATE TABLE IF NOT EXISTS payment_reconciliation_items (
  id BIGSERIAL PRIMARY KEY,
  batch_id BIGINT NOT NULL REFERENCES payment_reconciliation_batches(id) ON DELETE RESTRICT,
  payment_intent_id BIGINT REFERENCES payment_intents(id) ON DELETE RESTRICT,
  wallet_transaction_id BIGINT REFERENCES wallet_transactions(id) ON DELETE RESTRICT,
  provider_reference VARCHAR(160) NOT NULL,
  statement_amount NUMERIC(18,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'VND',
  status VARCHAR(24) NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending','matched','missing_internal','missing_external','amount_mismatch','duplicate')),
  payload_hash CHAR(64),
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (batch_id, provider_reference)
);

CREATE INDEX IF NOT EXISTS commerce_orders_user_created_idx
  ON commerce_orders(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS commerce_orders_status_created_idx
  ON commerce_orders(status, created_at DESC);
CREATE INDEX IF NOT EXISTS payment_intents_user_created_idx
  ON payment_intents(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS video_jobs_user_created_idx
  ON video_jobs(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS reconciliation_batches_status_idx
  ON payment_reconciliation_batches(status, started_at DESC);
