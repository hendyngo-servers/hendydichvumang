ALTER TABLE tickets
  ADD COLUMN IF NOT EXISTS admin_notified_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS admin_reminded_at TIMESTAMPTZ;

CREATE TABLE IF NOT EXISTS automation_settings (
  code VARCHAR(50) PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(400) NOT NULL DEFAULT '',
  enabled BOOLEAN NOT NULL DEFAULT TRUE,
  interval_minutes INTEGER NOT NULL DEFAULT 60 CHECK (interval_minutes BETWEEN 1 AND 10080),
  next_run_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  last_run_at TIMESTAMPTZ,
  last_status VARCHAR(20) NOT NULL DEFAULT 'never'
    CHECK (last_status IN ('never', 'running', 'success', 'failed', 'skipped')),
  last_error VARCHAR(200),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS automation_runs (
  id BIGSERIAL PRIMARY KEY,
  code VARCHAR(50) NOT NULL REFERENCES automation_settings(code) ON DELETE CASCADE,
  status VARCHAR(20) NOT NULL CHECK (status IN ('running', 'success', 'failed', 'skipped')),
  detail JSONB NOT NULL DEFAULT '{}'::jsonb,
  started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  finished_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS automation_due_idx ON automation_settings(enabled, next_run_at);
CREATE INDEX IF NOT EXISTS automation_runs_code_started_idx ON automation_runs(code, started_at DESC);

INSERT INTO automation_settings (code, name, description, enabled, interval_minutes)
VALUES
  ('cleanup_sessions', 'Dọn phiên đăng nhập hết hạn', 'Xóa session hết hạn và lịch sử chạy tự động hóa đã quá 180 ngày.', TRUE, 60),
  ('notify_new_tickets', 'Thông báo ticket mới qua Telegram', 'Gửi thông báo ticket mới đến ADMIN_TELEGRAM_ID bằng Bot Telegram.', TRUE, 1),
  ('remind_open_tickets', 'Nhắc ticket đang chờ xử lý', 'Nhắc quản trị viên ticket mở hoặc đang xử lý quá 60 phút; nhắc lại sau mỗi 6 giờ.', TRUE, 60)
ON CONFLICT (code) DO NOTHING;
