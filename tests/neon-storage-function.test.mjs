import test from 'node:test';
import assert from 'node:assert/strict';
import storageFunction, { buildPresignedUrl } from '../neon-functions/hendystorage/index.mjs';
const env = {
  AWS_ENDPOINT_URL_S3: 'https://br-example.storage.c-3.ap-southeast-1.aws.neon.tech',
  AWS_ACCESS_KEY_ID: 'nak_test_access',
  AWS_SECRET_ACCESS_KEY: 'test-secret-for-unit-tests-only',
  AWS_REGION: 'ap-southeast-1',
};
const key = 'modules/proxy/0123456789abcdef0123456789abcdef.json';
const now = new Date('2026-11-12T08:00:00.000Z');
test('creates private Neon Storage PUT URL with signed content type', () => {
  const result = buildPresignedUrl({ method: 'PUT', key, contentType: 'application/json', env, now });
  const url = new URL(result.url);
  assert.equal(url.protocol, 'https:');
  assert.equal(url.hostname, 'br-example.storage.c-3.ap-southeast-1.aws.neon.tech');
  assert.equal(url.pathname, '/uploads/' + key);
  assert.equal(url.searchParams.get('X-Amz-SignedHeaders'), 'content-type;host');
  assert.equal(url.searchParams.get('X-Amz-Expires'), '300');
  assert.match(url.searchParams.get('X-Amz-Signature') || '', /^[a-f0-9]{64}$/);
  assert.deepEqual(result.headers, { 'Content-Type': 'application/json' });
});
test('creates private Neon Storage GET URL without write headers', () => {
  const result = buildPresignedUrl({ method: 'GET', key, env, now, expiresIn: 1200 });
  const url = new URL(result.url);
  assert.equal(url.searchParams.get('X-Amz-SignedHeaders'), 'host');
  assert.equal(url.searchParams.get('X-Amz-Expires'), '900');
  assert.deepEqual(result.headers, {});
});
test('rejects traversal keys, invalid MIME types, and non-Neon endpoints', () => {
  assert.throws(() => buildPresignedUrl({ method: 'GET', key: '../private.txt', env, now }), /Invalid object key/);
  assert.throws(() => buildPresignedUrl({ method: 'PUT', key, contentType: 'application/x-php', env, now }), /Unsupported content type/);
  assert.throws(() => buildPresignedUrl({ method: 'GET', key, env: { ...env, AWS_ENDPOINT_URL_S3: 'https://evil.example' }, now }), /Invalid Neon storage endpoint/);
});

test('health route verifies the Neon Storage environment without returning a signed URL', async () => {
  const keys = ['AWS_ENDPOINT_URL_S3', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_REGION'];
  const previous = Object.fromEntries(keys.map((key) => [key, process.env[key]]));
  Object.assign(process.env, env);
  try {
    const response = await storageFunction.fetch(new Request('https://function.test/healthz'));
    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), { status: 'ok', service: 'hendy-neon-storage', storage: 'ready' });
  } finally {
    for (const key of keys) {
      if (previous[key] === undefined) delete process.env[key];
      else process.env[key] = previous[key];
    }
  }
});
