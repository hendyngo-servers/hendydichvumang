# Provider & Service Registry

Provider & Service Registry is the shared Admin control plane inside the existing
browser-based dashboard at `/admin`. It does not create a second dashboard or store
provider passwords/API keys.

## What it manages

The registry includes the HENDY service inventory: Telegram Bot, Website/App Store,
product catalog and orders, Mini Game account links, Tool rental, OTP, Proxy/HomeProxy,
Code Telegram, OCR, wallet, deposits, VIP, referrals, customer support, vouchers,
giftcodes, matchmaking, AI Music, AI Image, AI Cover/RVC, Vietsub/Video and modules.

For each service, an Admin can set:
- Registry state (enabled or API-locked).
- Maintenance mode and the member-facing maintenance message.
- Provider records (provider name/key, adapter family, HTTPS endpoint, credential
  Environment variable NAME, operational notes and enabled state).
- Local configuration-check history.

The registry-level maintenance/lock flags are enforced for the API paths explicitly
listed in `ProviderRegistryService::SERVICES`. Bot callback flows and worker runtime
switches continue to use their existing module-specific settings (AI Studio, AI Cover,
HomeProxy, Video Worker, Code Telegram and the global Bot maintenance controls).
This is intentional: a registry entry must not silently override a specialised
worker's own safety gate.

## Provider data and secrets

The database stores provider metadata only. The `credential_env_key` column contains
a variable name such as `OTP_PROVIDER_API_KEY`, never the value. Secret values must
be set in Render Environment and must not be pasted into provider notes, endpoint URLs,
chat messages, issues or committed files.

Provider endpoints must use HTTPS, cannot contain user/password information, query
strings or fragments, and cannot be an IP/localhost URL. Query strings are rejected
because they frequently contain credentials.

The “Kiểm tra cấu hình” action is deliberately a local configuration check. It checks
whether the configured ENV name is present and whether an adapter family exists in
the code allow-list. It does **not** make an external API request, validate the API key
against a provider, prove supplier ownership, test fulfillment, or mark an integration
as production-verified. A custom API provider remains marked as not supported for
automatic execution until a provider-specific adapter, auth/signature validation,
timeouts, idempotency, error mapping, webhook verification and tests are implemented.

## Apply the database migration

Production continues to use `RUN_MIGRATIONS_ON_START=false`. Apply the additive
migration before deploying the new Bot code:

1. Verify a Neon restore point / backup.
2. Connect using the Neon database owner or dedicated migration role, not the
   restricted `hendy_bot_app` runtime role.
3. Apply `telegram-bot/database/migrations/20261113_provider_service_registry.sql`
   to the `hendydichvumang` database.
4. Verify that `service_registry` and `service_providers` exist, the 22 default
   service rows were inserted, and `hendy_bot_app` has SELECT/INSERT/UPDATE/DELETE
   on both tables plus sequence usage.
5. Deploy the Bot service, then open `/admin` → **Provider & Service Registry**.

The migration only creates new tables/indexes, seeds missing registry keys and grants
runtime DML permissions. It does not delete or rewrite users, products, orders,
transactions, wallet ledgers, deposits or support tickets. It is safe to re-run.

## Admin API

All routes below are behind the existing Telegram Admin allowlist:

- `GET /api/admin/provider-registry`
- `POST /api/admin/provider-registry/providers`
- `PATCH /api/admin/provider-registry/providers/{id}`
- `DELETE /api/admin/provider-registry/providers/{id}`
- `POST /api/admin/provider-registry/providers/{id}/check`
- `PATCH /api/admin/provider-registry/services/{serviceKey}`

Every create/update/delete/check and service-state change is written to
`admin_audit`. The snapshot returns Environment variable names and boolean presence
only; it never returns secret values.

## Activation checklist

Before opening any new service to members, confirm supplier identity and resale rights,
record HTTPS evidence, agree stock/SLA/refunds, configure secrets in Render, implement
or select a reviewed adapter, run sandbox tests, and then enable the relevant existing
module switch. For manual products, both source permission and supplier access must
also pass the product readiness gate. A green CI run or a local configuration check is
not a substitute for provider-side verification.
