-- Set the primary grammY bot's default persistent menu to the nine-button layout.
-- Preserve custom icons; migrate the old default customer icon to the new diamond mark.
UPDATE bot_menu_configs
SET menu_columns = 2,
    button_order = '["minigame","otp_proxy","topup","vip","catalog","customer","group","channel","upcoming"]'::jsonb,
    button_icons =
      '{"minigame":"🚀","otp_proxy":"🎟","topup":"💳","vip":"🏆","catalog":"🎁","customer":"💠","group":"👥","channel":"📢","upcoming":"⏳"}'::jsonb
      || (button_icons - 'customer')
      || CASE
           WHEN button_icons ? 'customer' AND button_icons->>'customer' <> '👤'
             THEN jsonb_build_object('customer', button_icons->>'customer')
           ELSE '{}'::jsonb
         END,
    updated_at = NOW()
WHERE bot_key = 'grammy';
