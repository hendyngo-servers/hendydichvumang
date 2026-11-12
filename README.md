# HENDY Digital Ecosystem

HENDY uses one canonical Website application source directory: **`website/`**.

## Website architecture

- `website/app-store/`: Website home, App Store, application/game details, network services, Website Admin, login/register, shared PHP includes, CSS and uploads.
- `website/telegram/admin-dashboard/`: Telegram Bot Admin interface source. It is served only by authenticated Bot route `/admin`.
- `website/telegram/customer-center/`: Telegram Customer/Mini App interface source. It is served by Bot routes `/customer` and `/miniapp`; it is deliberately not listed in public Website navigation.
- `website/healthz.php`: Website Render health endpoint.
- `website/.htaccess`: canonical Website routing and private source protection.

Legacy public routes remain available: `/app_store/`, `/admin.php`, `/detail.php`, `/dichvumang.php`, `/login.php`, `/register.php`, `/admin-products.php`, `/admin-users.php`, and `/admin-system.php`. They are rewritten into `website/app-store/`.

The Website DocumentRoot is `/var/www/html/website`. The top-level `Dockerfile` is deployment infrastructure; Website application code is under `website/`.

## Services

| Component | Render service | Runtime | Database |
|---|---|---|---|
| Website, App Store, Network Services, Website Admin | hendydichvumang-website | PHP 8.3 / Apache | Neon `hendydichvumang_website` |
| One Telegram Bot, webhook, API, Telegram Admin/Customer UI | hendydichvumang-telegram-bot | PHP 8.3 / Apache | Neon `hendydichvumang` |

The Bot Docker context is the repository root so it can copy `website/telegram/*` to `/var/www/website/`, outside the Bot's public DocumentRoot. The Bot root/marketing/legal routes redirect to the canonical Website; authenticated Bot Admin and Customer routes remain served by the Bot backend.

Website and Bot PostgreSQL databases remain separate. Website passwords, Telegram IDs, balances, wallets and histories are not inferred or silently merged. Cross-account linking requires a verified identity flow.

## AI Cover integration

The primary Telegram Bot has a native PHP service and additive PostgreSQL schema for AI Cover. The AI inference worker is isolated from the webhook process and uses the private Neon Object Storage signer. The service stays disabled until the additive migration, worker service, callback secret and at least one authorized voice model are configured. Voice models must have documented license and owner consent; no pretrained or third-party model is bundled.

## Data preservation

- Website schema: `database/website-postgresql.sql`
- App Store and Network Services migration: `website/app-store/database.postgresql.sql`
- Bot schema: `telegram-bot/database/schema.sql`
- Additive feature migrations: `telegram-bot/database/migrations/`

No application tables or member balances are merged or dropped as part of the source-folder consolidation.
