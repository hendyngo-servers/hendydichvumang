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

## Admin Control Center

The Admin Dashboard is a browser-first control plane protected by the Telegram Admin allowlist. It includes:
- Member accounts and linked accounts, manual wallet adjustments with audit entries, orders, deposit approvals, customer tickets and vouchers.
- Product/catalog editing, Giftcode, Bot menu labels/descriptions/styles, shared configuration and community links.
- Bot operations: normal mode, maintenance, upgrade window, scheduled end time and operator message. Admin accounts remain able to enter during maintenance.
- HomeProxy enable/disable, encrypted member key management, video/Code Telegram job operations, module review, AI Studio controls and AI Cover licensed-preset management.
- Audit log for administrative changes.

Do not use a manual wallet adjustment as a substitute for payment reconciliation. Deposit requests must be matched to bank evidence before approval.

## AI Features

The Telegram main menu groups creative tools under **🤖 TÍNH NĂNG AI**:
- **AI Cover:** licensed RVC voice presets, owner consent and license reference, private Neon Object Storage, verified worker and HMAC callback. At least one enabled preset must pass a recent worker check before member access can open.
- **Tạo âm nhạc AI:** prompt → PostgreSQL job queue → ElevenLabs Music worker → send audio back to the same Telegram user.
- **Chỉnh sửa ảnh AI:** prompt + private Telegram photo → PostgreSQL job queue → OpenAI image edit → send edited image to the user.

AI provider keys are secrets and must only be configured in Render Environment. The Admin Dashboard never displays these values. AI_MEDIA_QUEUE_ENABLED=false, AI_COVER_ENABLED=false and AI_COVER_WORKER_ENABLED=false are fail-closed defaults. After provider credentials and worker are configured, an Admin must enable AI Studio globally and then enable each tool separately. AI Cover additionally requires a working RVC worker, callback HMAC secret, Neon Object Storage and a verified licensed preset.

AI tools currently have a per-user daily job limit and one active job per user. Automatic AI wallet billing is not enabled; users are not charged for these tasks. Do not advertise paid AI packages until an explicit price/ledger flow has been implemented and tested.

The /ai command opens the AI submenu. The /cancel command clears the current prompt/photo input. Image and audio workflows are restricted to private Bot chats.

## Database reset and recovery

On 2026-11-13, records were cleared from the `public` schema of both `hendydichvumang` and `hendydichvumang_website`; tables and constraints were retained. Neon Auth database `neondb` was not changed.

Recovery branch retained in Neon:
`backup-before-full-reset-2026-11-13`.

The Bot schema remains in `telegram-bot/database/schema.sql`. Runtime uses `RUN_MIGRATIONS_ON_START=false`; reviewed schema changes and seed configuration are applied with the database owner role, while the Bot runtime role performs DML only.

## Product and provider activation

The Admin product form records supplier identity, supplier product reference, source/rights evidence, verification states and the Environment variable NAME only. Public product lists and both checkout paths fail closed until the source permission is authorized, supplier access is verified, evidence URLs are HTTPS, stock is available and manual fulfillment is selected. API fulfillment stays locked until a dedicated tested adapter exists.

Before deploying this readiness change to an existing Neon database, follow [the safe migration and provider onboarding checklist](docs/product-provider-onboarding.md) using the database owner/migration role. Production intentionally keeps `RUN_MIGRATIONS_ON_START=false`; CI success alone does not apply a production migration.

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
