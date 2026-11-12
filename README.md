# HENDY DIGITAL ECOSYSTEM

Production monorepo for one Telegram Bot backend and one public Static Website.

## Production architecture

- **Static Site:** Render service name `hendydichvumang`, source `website/public/`.
- **Current working Static Site URL:** `https://hendydichvumang-szad.onrender.com`.
- **Telegram Bot:** Render service `hendydichvumang-telegram-bot`, built from `telegram-bot/Dockerfile`.
- **Admin:** Browser-based Admin Dashboard is served from the authenticated Bot backend at `https://hendydichvumang-telegram-bot.onrender.com/admin`. Admin and customer UI files are stored in `website/telegram/`, outside the Static Site publish directory.
- **PostgreSQL:** Neon database `hendydichvumang` is used by the Bot. The legacy `hendydichvumang_website` database is not required by the public Static Site.

The clean hostname `https://hendydichvumang.onrender.com` currently resolves to the Bot API, not the Static Site. The Static Site therefore uses its verified Render subdomain with the `-szad` suffix until Render releases the old hostname. Do not point Website links or canonical metadata at the Bot API hostname.

## Website

Everything under `website/public/` is public static content: HTML, CSS, JavaScript, SVG, robots, sitemap and legal pages. It does not connect to PostgreSQL, process payments, or contain Bot credentials.

Public tabs: Trang chủ, App Store, Dịch vụ mạng, AI Studio, Code Telegram, Giới thiệu. The public navigation intentionally does not expose "Trung tâm khách hàng".

## Telegram Bot

- Exactly one production Bot is supported; only `BOT1_TOKEN` is read.
- The webhook bootstrap calls Telegram `getMe()`, registers and verifies the webhook at the Bot service's own Render URL, then stores the public Bot username in `app_settings.primary_bot_url`.
- `BOT1_PUBLIC_URL` is an optional override; referral links otherwise use the auto-detected Bot URL.
- One-time login tickets are short-lived, stored hashed, consumed atomically, and exchanged for a signed HttpOnly website-session cookie.
- Admin access requires the user's Telegram numeric ID to be in `ADMIN_ID` or `ADMIN_IDS`.

## Database reset and recovery

On 2026-11-13, records were cleared from the `public` schema of both `hendydichvumang` and `hendydichvumang_website`; tables and constraints were retained. Neon Auth database `neondb` was not changed.

Recovery branch retained in Neon:
`backup-before-full-reset-2026-11-13`.

The Bot schema remains in `telegram-bot/database/schema.sql`. Runtime uses `RUN_MIGRATIONS_ON_START=false`; reviewed schema changes and seed configuration are applied with the database owner role, while the Bot runtime role performs DML only.

## Render environment

Set secrets only in Render Environment. Never commit live credentials.

Required Bot values:
- `BOT1_TOKEN`
- `ADMIN_ID` or `ADMIN_IDS`
- `DATABASE_URL` pointing to Neon database `hendydichvumang`
- `DATABASE_SSL=true`

Set `WEBSITE_PUBLIC_URL=https://hendydichvumang-szad.onrender.com`. Render injects `RENDER_EXTERNAL_URL` for the Bot; webhook registration uses that service-owned URL. Optional bank, AI, OCR and WebSocket integrations stay disabled until provider credentials and migrations are verified.

## Local development

1. Copy `telegram-bot/.env.example` to `telegram-bot/.env`.
2. Populate credentials locally; never commit `.env`.
3. Run `composer install` in `telegram-bot/`.
4. Run `composer validate-config`, `composer lint` and `composer test`.
