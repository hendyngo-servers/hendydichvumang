# HENDY Cybertech — Website + Telegram Bot

A single GitHub repository deploying two separate Render services with isolated Neon PostgreSQL databases, managed together by an auto-sync Render Blueprint.

| App | Render service | Runtime | Neon database |
|---|---|---|---|
| Website/catalog | hendydichvumang-website | PHP 8.3 + Apache | hendydichvumang_website |
| Single Telegram bot | hendydichvumang-telegram-bot | PHP 8.3 + Apache | hendydichvumang |

## Website deployment

- Root Dockerfile, PHP 8.3, PDO PostgreSQL/MySQL, cURL, GD and fileinfo.
- Health endpoint: `/healthz.php`.
- Production uses Neon PostgreSQL with role `hendy_website_app`.
- Existing MySQL configuration remains supported when DATABASE_URL is unset.
- Screenshot uploads use the private Neon Object Storage bucket `uploads`; short-lived signed URLs are generated through the private Neon Function.
- Configure `DATABASE_URL`, `DATABASE_SSL=true`, `NEON_STORAGE_FUNCTION_URL`, `NEON_STORAGE_FUNCTION_KEY`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` and optional `TELEGRAM_BOT_URL` directly in Render.
- The first admin is created only when ADMIN_EMAIL and ADMIN_PASSWORD are both present and no admin exists. No default credentials are shipped.

## Telegram Bot deployment

See `telegram-bot/README.md` and `docs/TELEGRAM-BOT-INTEGRATION.md`.
- Only `BOT1_TOKEN` is used for the production Bot. BOT2_TOKEN remains unset.
- `ADMIN_ID` or `ADMIN_IDS` must contain the numeric Telegram administrator ID.
- Bot database uses Neon `hendydichvumang` with runtime role `hendy_bot_app`.
- `RUN_MIGRATIONS_ON_START=false`; schema changes are managed separately.
- `WEBHOOK_AUTO_REGISTER=true`; HTTPS service URL is read from Render.
- Never commit Bot tokens, DATABASE_URL, passwords, S3 credentials or internal Function keys.

## Neon resources

Project `calm-forest-79251169`, branch `production`, region `aws-ap-southeast-1`.
- Website database: `hendydichvumang_website`, role `hendy_website_app`.
- Bot database: `hendydichvumang`, role `hendy_bot_app`.
- Private bucket: `uploads`.
- Function: `hendystorage` (separate internal bearer keys for Website and Bot).
- Neon Realtime is enabled; Bot currently uses its PostgreSQL LISTEN/NOTIFY WebSocket transport.

## Render Blueprint

`render.yaml` declares both services. Root service uses `./Dockerfile`, Bot uses `dockerfilePath: ./telegram-bot/Dockerfile` and `dockerContext: ./telegram-bot`. Existing Render Environment secrets are preserved by Blueprint sync; no production secrets belong in the repository.

## CI

GitHub Actions runs root Website PHP lint, Bot Composer validate/lint/tests and secret scan, plus Node build/tests for the Neon Function. No production credentials are needed by CI.

## Data boundaries

Website and Bot use separate databases and roles. There is no automatic transfer of balances, orders, products or user records between them. Account linking requires an explicit one-time verification process.


## App Store and network services

The `app_store/` module adds a public app/game catalog, product details, screenshot management, network-service plans, and Admin CRUD. It reuses the main website's PDO connection, users table, role checks, CSRF protection and login session; it does not create a default admin account or a second set of user credentials.

- Public: `/app_store/index.php`, `/app_store/detail.php?id=...`, `/app_store/dichvumang.php`
- Admin: `/app_store/admin.php` and `/app_store/admin_services.php`
- MySQL schema reference: `app_store/database.sql`
- Current production Neon/PostgreSQL migration: `app_store/database.postgresql.sql`

Apply the PostgreSQL migration as the Neon database owner to `hendydichvumang_website`. The migration is additive and grants the existing `hendy_website_app` runtime role access to the new tables. Do not run the MySQL script against Neon. Screenshots use the existing private Neon Storage adapter when configured; otherwise they fall back to the App Store uploads folder.

## One Telegram bot

The Telegram backend accepts only the primary bot token `BOT1_TOKEN`. Any legacy `BOT2_TOKEN` value is ignored by the runtime and the secondary webhook is not available. Existing Render secrets and database URLs stay in the service's Environment settings; they are not committed to this repository.
