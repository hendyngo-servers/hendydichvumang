CREATE TABLE IF NOT EXISTS bot_menu_configs (
  bot_key VARCHAR(16) PRIMARY KEY CHECK (bot_key IN ('grammy', 'aiogram')),
  intro_text VARCHAR(500) NOT NULL DEFAULT '',
  menu_columns SMALLINT NOT NULL DEFAULT 2 CHECK (menu_columns BETWEEN 1 AND 3),
  persistent_keyboard BOOLEAN NOT NULL DEFAULT TRUE,
  resize_keyboard BOOLEAN NOT NULL DEFAULT TRUE,
  button_order JSONB NOT NULL DEFAULT '["customer","balance","services","support"]'::jsonb
    CHECK (jsonb_typeof(button_order) = 'array'),
  button_labels JSONB NOT NULL DEFAULT '{}'::jsonb
    CHECK (jsonb_typeof(button_labels) = 'object'),
  button_icons JSONB NOT NULL DEFAULT '{}'::jsonb
    CHECK (jsonb_typeof(button_icons) = 'object'),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO bot_menu_configs (bot_key, intro_text, menu_columns, button_order, button_labels, button_icons)
VALUES
  (
    'grammy',
    'Chọn chức năng bạn cần bên dưới.',
    2,
    '["customer","balance","services","support"]'::jsonb,
    '{}'::jsonb,
    '{"customer":"👤","balance":"💳","services":"🛍","support":"🎫"}'::jsonb
  ),
  (
    'aiogram',
    'Chọn chức năng HENDY. Quản trị và tự động hóa chỉ hiển thị với tài khoản được cấp quyền.',
    2,
    '["customer","balance","services","support","admin","automation"]'::jsonb,
    '{}'::jsonb,
    '{"customer":"👤","balance":"💳","services":"🛍","support":"🎫","admin":"🛠","automation":"⚙️"}'::jsonb
  )
ON CONFLICT (bot_key) DO NOTHING;

CREATE INDEX IF NOT EXISTS bot_menu_configs_updated_idx
  ON bot_menu_configs (updated_at DESC);
