import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
  COMMERCE_MODULES,
  summarizeCommerceFeature,
  loadCommerceStatus,
  createCommerceFeatureGate
} from '../src/commerce.js';

test('all seven business modules are registered and start locked', async () => {
  assert.deepEqual(COMMERCE_MODULES.map((item) => item.code), [
    'payments', 'orders', 'vip', 'vouchers', 'giftcodes', 'referrals', 'video_jobs'
  ]);
  const status = await loadCommerceStatus({ query: async () => ({ rows: [] }) });
  assert.equal(status.length, 7);
  assert.ok(status.every((item) => item.enabled === false && item.readiness === 'LOCKED'));
});

test('database flags alone cannot activate unported business features', () => {
  for (const feature of COMMERCE_MODULES) {
    const status = summarizeCommerceFeature(feature, {
      enabled: true, backend_ready: true, reconciliation_verified: true
    });
    assert.equal(status.enabled, false, feature.code);
    assert.equal(status.readiness, 'LOCKED', feature.code);
  }
});

test('backend and reconciliation readiness are both required', () => {
  const payments = COMMERCE_MODULES.find((item) => item.code === 'payments');
  assert.equal(summarizeCommerceFeature(payments, {
    enabled: true, backend_ready: false, reconciliation_verified: true
  }).enabled, false);
  assert.equal(summarizeCommerceFeature(payments, {
    enabled: true, backend_ready: true, reconciliation_verified: false
  }).enabled, false);
});

test('commerce API guard returns HTTP 503 before calling downstream handler', async () => {
  let statusCode = 0;
  let payload = null;
  let nextCalled = false;
  const middleware = createCommerceFeatureGate({
    query: async () => ({ rows: [{ enabled: true, backend_ready: true, reconciliation_verified: true }] })
  }, 'payments');
  await middleware({}, {
    status(code) { statusCode = code; return this; },
    json(value) { payload = value; return this; }
  }, () => { nextCalled = true; });
  assert.equal(statusCode, 503);
  assert.equal(nextCalled, false);
  assert.equal(payload.feature, 'payments');
  assert.equal(payload.readiness, 'LOCKED');
});

test('commerce migration contains separate tables for every domain and reconciliation', () => {
  const sql = readFileSync(new URL('../migrations/007_commerce_readiness.sql', import.meta.url), 'utf8');
  for (const table of [
    'commerce_feature_flags', 'commerce_orders', 'commerce_order_items', 'payment_intents',
    'commerce_order_events', 'vip_tiers', 'vip_memberships', 'vouchers', 'voucher_redemptions',
    'giftcodes', 'giftcode_redemptions', 'referral_codes', 'referral_attributions',
    'referral_rewards', 'video_jobs', 'commerce_ledger_entries',
    'payment_reconciliation_batches', 'payment_reconciliation_items'
  ]) assert.match(sql, new RegExp('CREATE TABLE IF NOT EXISTS ' + table));
  assert.match(sql, /enabled BOOLEAN NOT NULL DEFAULT FALSE/);
  assert.match(sql, /reconciliation_verified BOOLEAN NOT NULL DEFAULT FALSE/);
});
