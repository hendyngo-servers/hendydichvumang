# Product, source and supplier readiness

A product record is not enough to activate a service. Customer-facing catalogs and
checkout paths require all of the following:

- is_active = true and stock is available.
- source_permission_status = authorized.
- supplier_access_status = verified.
- Supplier name, supplier HTTPS URL and source / permission HTTPS URL are present.
- fulfillment_mode = manual. The generic API mode is deliberately blocked
  until a provider-specific adapter, authentication, timeout, idempotency and
  webhook verification have been implemented and tested.

## Admin product form

The product form records supplier name, supplier page, supplier SKU/reference,
source/contract/ownership evidence URL, permission status, access status,
fulfillment mode and an optional Environment variable name. Never paste API
keys, passwords, session cookies, OTP seeds or other secret values into this form
or the database. Configure secret values only in Render Environment, with the
name matching the stored credentialEnvKey after a provider adapter exists.

New and existing products default to pending verification after migration.
Existing orders and wallet history are not changed. An Admin must review evidence
and explicitly set both permission and supplier access statuses before a product
can be listed or purchased.

## Provider onboarding checklist

1. Confirm the supplier identity and the right to resell / distribute the item.
2. Record the supplier product reference and HTTPS source evidence.
3. Verify that the HENDY account has access to the exact supplier service.
4. Confirm actual stock, delivery SLA, refund/incident handling and pricing.
5. For automation, implement a dedicated allow-listed adapter in code, keep
   credentials in Render Environment, validate signatures, set timeouts and
   idempotency, and pass tests before enabling API fulfillment.
6. Test with a non-cash/non-production sandbox or an explicitly designated test
   item before activating customer sales.

OTP, AI, video, Proxy and Code Telegram providers may have their own distinct
access controls. A supplier URL alone does not prove ownership, authorization or
working API access. Keep each feature locked until its own readiness checks pass.


## Apply the database change safely

The additive columns and constraints are included in `telegram-bot/database/schema.sql`
and tracked in `telegram-bot/database/migrations/20261113_product_supplier_readiness.sql`.
Production keeps `RUN_MIGRATIONS_ON_START=false`: do not give the runtime Bot role
DDL privileges just to make this feature work.

Before deploying the code to an existing database, take/verify a Neon restore point,
then apply the reviewed migration using the Neon database owner / migration role.
Do not run it using the restricted `hendy_bot_app` runtime role. The migration adds
columns and an index, defaults all existing products to pending verification, and
does not delete orders, wallet entries, user records or product rows. Verify the
new columns exist and that the app role can SELECT/UPDATE them before enabling the
new Bot/API version.

After the schema is present, use the Admin product form to record the supplier and
evidence, then review both permission statuses. A green CI run does not prove that
a production database migration or external provider account has been applied.
