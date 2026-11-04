CREATE TABLE IF NOT EXISTS telegram_webhook_updates (
  update_id INTEGER PRIMARY KEY,
  status TEXT NOT NULL CHECK (status IN ('processing', 'processed', 'failed')),
  claim_token TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 1 CHECK (attempts > 0),
  lock_expires_at INTEGER NOT NULL DEFAULT 0,
  error_code TEXT,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  processed_at INTEGER
);

CREATE INDEX IF NOT EXISTS idx_telegram_webhook_updates_status_updated
  ON telegram_webhook_updates(status, updated_at);
