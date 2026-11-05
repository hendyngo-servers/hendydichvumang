CREATE TABLE IF NOT EXISTS module_artifacts (
  id BIGSERIAL PRIMARY KEY,
  module_key VARCHAR(63) NOT NULL CHECK (module_key ~ '^[a-z][a-z0-9-]{1,62}$'),
  original_name VARCHAR(120) NOT NULL,
  mime_type VARCHAR(80) NOT NULL CHECK (mime_type IN ('application/json','text/markdown','text/plain','text/css')),
  size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576),
  sha256 CHAR(64) NOT NULL CHECK (sha256 ~ '^[a-f0-9]{64}$'),
  content BYTEA NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending_review'
    CHECK (status IN ('pending_review','approved','rejected')),
  uploaded_by VARCHAR(160) NOT NULL,
  reviewed_by VARCHAR(160),
  review_note VARCHAR(1000) NOT NULL DEFAULT '',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  reviewed_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS module_artifacts_module_created_idx
  ON module_artifacts(module_key, created_at DESC);
CREATE INDEX IF NOT EXISTS module_artifacts_status_created_idx
  ON module_artifacts(status, created_at DESC);
