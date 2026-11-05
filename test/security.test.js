import test from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { safeEqual, verifyTelegramInitData, createSessionToken, hashSessionToken, verifyWebhookSecret, normalizeWebhookSecret } from '../src/security.js';

function buildInitData(botToken, user, authDate) {
  const values = new URLSearchParams({ auth_date: String(authDate), query_id: 'AAEAAAE', user: JSON.stringify(user) });
  const dataCheck = [...values.entries()].sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0)).map(([k, v]) => `${k}=${v}`).join('\n');
  const secret = crypto.createHmac('sha256', 'WebAppData').update(botToken).digest();
  const hash = crypto.createHmac('sha256', secret).update(dataCheck).digest('hex');
  values.set('hash', hash);
  return values.toString();
}

test('safeEqual compares equal values and rejects mismatches', () => {
  assert.equal(safeEqual('abc', 'abc'), true);
  assert.equal(safeEqual('abc', 'abd'), false);
  assert.equal(safeEqual('abc', 'abcd'), false);
});

test('Telegram initData validates correct HMAC and returns user', () => {
  const token = '123456:TEST_TOKEN';
  const user = { id: 123456, first_name: 'Hendy', username: 'hendy_user' };
  const payload = buildInitData(token, user, 1_800_000_000);
  const result = verifyTelegramInitData(payload, token, 3600, 1_800_000_100);
  assert.equal(result.ok, true);
  assert.equal(result.user.id, user.id);
});

test('Telegram initData rejects an altered user payload', () => {
  const token = '123456:TEST_TOKEN';
  const payload = buildInitData(token, { id: 321, first_name: 'User' }, 1_800_000_000);
  const alteredParams = new URLSearchParams(payload);
  alteredParams.set('user', JSON.stringify({ id: 321, first_name: 'Admin' }));
  assert.equal(verifyTelegramInitData(alteredParams.toString(), token, 3600, 1_800_000_100).ok, false);
});

test('Telegram initData rejects expired and future auth_date values', () => {
  const token = '123456:TEST_TOKEN';
  const old = buildInitData(token, { id: 321 }, 1_700_000_000);
  const future = buildInitData(token, { id: 321 }, 1_800_000_500);
  assert.equal(verifyTelegramInitData(old, token, 60, 1_800_000_000).reason, 'expired');
  assert.equal(verifyTelegramInitData(future, token, 3600, 1_800_000_000).reason, 'expired');
});

test('session tokens are random and hashed deterministically', () => {
  const a = createSessionToken();
  const b = createSessionToken();
  assert.notEqual(a, b);
  assert.match(hashSessionToken(a), /^[a-f0-9]{64}$/);
  assert.equal(hashSessionToken(a), hashSessionToken(a));
});

test('Render webhook secret is normalized to Telegram-safe URL-safe characters', () => {
  const normalized = normalizeWebhookSecret('base64+/=render-generated-secret');
  assert.match(normalized, /^[A-Za-z0-9_-]{43}$/);
  assert.equal(normalizeWebhookSecret('base64+/=render-generated-secret'), normalized);
  assert.equal(normalizeWebhookSecret(''), '');
});

test('webhook secret must match and cannot be empty', () => {
  assert.equal(verifyWebhookSecret('secret', 'secret'), true);
  assert.equal(verifyWebhookSecret('secret', 'wrong'), false);
  assert.equal(verifyWebhookSecret('', 'secret'), false);
});
