# HENDY Digital Ecosystem

Two Render Web Services: Website/App Store and one Telegram Bot production service.

| Component | Render service | Runtime | Database |
|---|---|---|---|
| Website + App Store + network services | hendydichvumang-website | PHP 8.3 / Apache | Neon hendydichvumang_website |
| Telegram Bot | hendydichvumang-telegram-bot | PHP 8.3 / Apache | Neon hendydichvumang |

## Website source of truth

Website PHP/CSS/includes are consolidated under app_store/. Main routes:
- / — Website home (app_store/home.php).
- /app_store/ — App Store.
- /app_store/detail.php?id=... — App/game detail.
- /app_store/dichvumang.php — Network services.
- /admin.php — Central Website admin hub.
- /app_store/admin.php — App Store admin.
- /app_store/admin_services.php — Network service admin.
- /login.php, /register.php, /logout.php — Single Website account/session.

Root .htaccess routes legacy URLs into app_store/. Duplicate root PHP/CSS/includes files are removed. healthz.php, Dockerfile, render.yaml, database schemas and uploads remain at root because they are infrastructure/data assets, not duplicated Website application sources.

## Admin hub

/admin.php is the single entry point for Website, App Store and network-service administration, with a shortcut to the Telegram Bot Dashboard. Bot Dashboard still uses its Telegram Admin allowlist (ADMIN_ID / ADMIN_IDS) and its separate Neon database. Accounts and balances are not implicitly merged.

## Data preservation

Website schema: database/website-postgresql.sql.
App Store migration: app_store/database.postgresql.sql.
Bot schema: telegram-bot/database/schema.sql.

Legacy products/screenshots and App Store apps/app_screenshots/network_services tables are preserved. No destructive migration or cross-database balance transfer is performed. The Website Admin seed still requires ADMIN_EMAIL and ADMIN_PASSWORD in Render Environment when no Admin already exists.

## Telegram Bot

The primary production bot uses BOT1_TOKEN and one webhook /webhook/telegram. The main Inline Keyboard follows the supplied reference image. The welcome caption uses live Telegram/profile/wallet data. Home artwork is read from BOT_HOME_PHOTO; default URL: https://i.postimg.cc/rcD583NW/anh-quan-cao.png?dl=1.

Keep BOT1_TOKEN, DATABASE_URL, passwords and signing keys in Render Environment/GitHub Secrets. CI runs Website PHP lint, Bot Composer validate/lint/tests, secret scan and Neon Function build/tests.
