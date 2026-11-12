# HENDY DIGITAL ECOSYSTEM

Production monorepo: one Telegram Bot backend and one public Static Website.

## Website
- All public Website source: `website/public/` — static HTML, CSS/JS, SVG, JSON and SEO files only.
- Render Static Site: `hendydichvumang-static-site`, publishes `website/public` through the CDN.
- Tabs: Trang chủ, App Store, Dịch vụ mạng, AI Studio, Code Telegram, Giới thiệu.
- Public navigation intentionally does not show Trung tâm khách hàng.
- The Website never connects directly to PostgreSQL and does not process payments or store credentials.
- Catalog prices, stock, orders and payment flows remain in the Telegram Bot.
- Admin entry: `https://hendydichvumang-telegram-bot.onrender.com/admin`, protected by Telegram Admin authentication.

## Telegram Bot
- One primary Bot; backend, webhook, API, workers and PostgreSQL remain in `telegram-bot/`.
- Admin/Customer HTML is stored in `website/telegram/` and copied by the Bot Docker build to a location outside the Bot public document root. It is not inside `website/public`, so the Static Site cannot publish those files.
- Bot account data, wallets, ledger, transactions, orders, Code Telegram and AI Cover remain in the separate Bot database.

## Render
- `hendydichvumang-static-site`: public Static Site.
- `hendydichvumang-website`: temporary Nginx static fallback during CDN verification.
- `hendydichvumang-telegram-bot`: PHP API/webhook and workers.

## Website PostgreSQL cleanup
The old Website database is Neon project `calm-forest-79251169`, database `hendydichvumang_website`. A recovery branch `pre-static-website-20261113` was created before cleanup. Current row counts: `users=1`; `products=0`; `screenshots=0`; `apps=0`; `app_screenshots=0`; `network_services=0`. The single user row is retained pending confirmation because deleting it also removes the Website Admin account. The Telegram Bot database is separate and is not part of this cleanup.
