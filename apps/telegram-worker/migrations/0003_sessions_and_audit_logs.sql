-- Additive integration: short-lived conversation state and minimal audit metadata.
-- Existing users, wallets, webhook idempotency and payment tables remain unchanged.

CREATE TABLE IF NOT EXISTS user_sessions (
  telegram_user_id INTEGER PRIMARY KEY
    REFERENCES users(telegram_user_id) ON DELETE CASCADE,
  state_json TEXT NOT NULL CHECK (length(state_json) <= 4096),
  expires_at INTEGER NOT NULL,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_user_sessions_expiry
  ON user_sessions(expires_at);

CREATE TABLE IF NOT EXISTS audit_logs (
  id TEXT PRIMARY KEY,
  actor_telegram_id INTEGER,
  action TEXT NOT NULL,
  target TEXT,
  details_json TEXT,
  created_at INTEGER NOT NULL,
  FOREIGN KEY (actor_telegram_id) REFERENCES users(telegram_user_id)
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_created
  ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_actor_created
  ON audit_logs(actor_telegram_id, created_at DESC);
