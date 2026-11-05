CREATE TABLE IF NOT EXISTS bot_fsm_states (
  key_hash CHAR(64) PRIMARY KEY CHECK (key_hash ~ '^[a-f0-9]{64}$'),
  bot_id BIGINT NOT NULL,
  chat_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  state VARCHAR(160),
  data JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(data) = 'object'),
  expires_at TIMESTAMPTZ NOT NULL DEFAULT (NOW() + INTERVAL '24 hours'),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS bot_fsm_states_expires_idx ON bot_fsm_states(expires_at);
CREATE INDEX IF NOT EXISTS bot_fsm_states_user_idx ON bot_fsm_states(bot_id, user_id, updated_at DESC);
