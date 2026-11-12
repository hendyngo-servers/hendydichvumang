# HENDY DIGITAL ECOSYSTEM

Production monorepo for one Telegram Bot backend and one public Static Website.

## Architecture

- **Static Site:** Render service `hendydichvumang`, deployed from `website/public/` and served through Render's CDN.
- **Telegram Bot:** Render service `hendydichvumang-telegram-bot`, built from `telegram-bot/Dockerfile`.
- **Database:** Neon PostgreSQL. The Bot uses `hendydichvumang`; the legacy Website catalog database is `hendydichvumang_website`.
- **Admin:** Browser-based Admin Dashboard is served by the authenticated Bot backend at `https://hendydichvumang-telegram-bot.onrender.com/admin`. It is not published in the public Static Site.

## Website

All files published to the Internet are under `website/public/`: HTML, static CSS/JS, SVG icons, and SEO assets. The Website does not connect to PostgreSQL, expose secrets, process payments, or host Admin/Customer sessions.

Public tabs: Trang chủ, App Store, Dịch vụ mạng, AI Studio, Code Telegram, Giới thiệu. The public navigation intentionally does not expose a "Trung tâm khách hàng" tab.

Canonical production URL: `https://hendydichvumang.onrender.com`.

## Telegram Bot

- Exactly one production Bot is supported. `BOT1_TOKEN` is the only Bot token consumed by the backend.
- Webhook registration calls Telegram `getMe()`, verifies `getWebhookInfo()`, and stores the detected public Bot URL in `app_settings.primary_bot_url`.
- `BOT1_PUBLIC_URL` is optional; when omitted, referral URLs use the automatically detected Bot URL.
- Webhook and website-session signing secrets are generated and stored server-side in PostgreSQL. They are never included in public Website files.
- Admin access requires a Telegram numeric ID in `ADMIN_ID` or `ADMIN_IDS`.

## Database reset

On 2026-11-13, all records were cleared from the `public` schema of both `hendydichvumang` and `hendydichvumang_website`. All tables, indexes, constraints, and schema objects were retained; identity sequences were reset. Neon Auth database `neondb` was not changed.

A pre-reset recovery branch is retained in Neon:
`backup-before-full-reset-2026-11-13`.

The Bot schema is not dropped. The Render Bot service keeps `RUN_MIGRATIONS_ON_START=false` because its runtime database role is intentionally DML-only; schema changes must be applied separately using a reviewed migration. After the reset, default public Website and support links are re-seeded in `app_settings`.

## Render environment

Set sensitive values only in the Render Environment page. Never commit live credentials.

Required Bot values:
- `BOT1_TOKEN`
- `ADMIN_ID` or `ADMIN_IDS`
- `DATABASE_URL` (Neon Bot database URL: `hendydichvumang`)
- `DATABASE_SSL=true`

The Bot service URL is used for the Telegram webhook; Render provides `RENDER_EXTERNAL_URL` automatically. `WEBSITE_PUBLIC_URL` must be `https://hendydichvumang.onrender.com`.

Optional integrations (bank callback, AI providers, Code Telegram OCR worker, WebSocket) must remain disabled until their provider credentials, migrations, and health checks have been verified.

## Local development

1. Copy `telegram-bot/.env.example` to `telegram-bot/.env`.
2. Populate credentials locally; do not commit the resulting `.env`.
3. Run `composer install` in `telegram-bot/`.
4. Validate configuration with `composer validate-config`.
5. Run the PHP tests with `composer test`.
