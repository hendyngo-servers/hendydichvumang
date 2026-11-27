import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const securitySource = readFileSync(new URL('../website/public/app-store/security.js', import.meta.url), 'utf8');
const apiSource = readFileSync(new URL('../website/public/app-store/api-client.js', import.meta.url), 'utf8');

function loadSecurity() {
  const window = {};
  runInNewContext(securitySource, { window, URL });
  return window.HENDY_RELEASE_POLICY;
}

function loadApi({ apiBaseUrl = '', fetchImpl } = {}) {
  const window = {
    location: { origin: 'https://store.example.test' },
    setTimeout,
    clearTimeout,
    fetch: fetchImpl || (async () => ({
      ok: true,
      status: 200,
      headers: { get: () => 'application/json; charset=utf-8' },
      json: async () => ({ ok: true, releases: [] })
    }))
  };
  runInNewContext(apiSource, { window, URL, AbortController });
  return window.HENDY_STORE_API.create({ apiBaseUrl });
}

test('release security accepts only known platform files with HTTPS and SHA-256', () => {
  const policy = loadSecurity();
  const good = {
    platform: 'android',
    filename: 'Hendy-App-1.2.3.apk',
    downloadUrl: 'https://storage.example.test/release.apk?signature=token',
    sha256: 'a'.repeat(64),
    version: '1.2.3'
  };
  assert.equal(policy.validRelease(good), true);
  assert.equal(policy.safeHttps('http://storage.example.test/file.apk'), '');
  assert.equal(policy.validRelease({ ...good, downloadUrl: 'javascript:alert(1)' }), false);
  assert.equal(policy.validRelease({ ...good, filename: '../secret.apk' }), false);
  assert.equal(policy.validRelease({ ...good, sha256: 'not-a-checksum' }), false);
  assert.equal(policy.validRelease({ ...good, platform: 'unknown' }), false);
});

test('App Store API client calls only the versioned public route without cookies or cache', async () => {
  let requestedUrl = '';
  let requestOptions;
  const api = loadApi({
    apiBaseUrl: 'https://hendydichvumang-telegram-bot.onrender.com',
    fetchImpl: async (url, options) => {
      requestedUrl = url;
      requestOptions = options;
      return {
        ok: true,
        status: 200,
        headers: { get: () => 'application/json' },
        json: async () => ({ ok: true, releases: [] })
      };
    }
  });
  const result = await api.listReleases({ category: 'otp' });
  assert.equal(result.ok, true);
  assert.equal(new URL(requestedUrl).pathname, '/api/v1/store/releases');
  assert.equal(new URL(requestedUrl).searchParams.get('category'), 'otp');
  assert.equal(requestOptions.credentials, 'omit');
  assert.equal(requestOptions.cache, 'no-store');
  assert.equal(requestOptions.mode, 'cors');
  assert.equal(requestOptions.method, 'GET');
});

test('App Store API client rejects arbitrary backend origins', () => {
  assert.throws(
    () => loadApi({ apiBaseUrl: 'https://attacker.example.test' }),
    /không nằm trong danh sách máy chủ HENDY/
  );
  assert.throws(
    () => loadApi({ apiBaseUrl: 'http://hendydichvumang-telegram-bot.onrender.com' }),
    /không nằm trong danh sách máy chủ HENDY/
  );
});

test('App Store API client rejects malformed or oversized catalog payloads', async () => {
  const malformedApi = loadApi({
    fetchImpl: async () => ({
      ok: true,
      status: 200,
      headers: { get: () => 'text/html' },
      json: async () => ({ ok: true, releases: [] })
    })
  });
  await assert.rejects(() => malformedApi.listReleases(), /định dạng dữ liệu không hợp lệ/);

  const oversizedApi = loadApi({
    fetchImpl: async () => ({
      ok: true,
      status: 200,
      headers: { get: () => 'application/json' },
      json: async () => ({ ok: true, releases: Array.from({ length: 501 }, () => ({})) })
    })
  });
  await assert.rejects(() => oversizedApi.listReleases(), /Dữ liệu danh mục App Store không hợp lệ/);
});


test('store listing links are restricted to official platform stores', () => {
  const policy = loadSecurity();
  assert.equal(policy.safeStoreListing('https://apps.apple.com/app/example/id123'), 'https://apps.apple.com/app/example/id123');
  assert.equal(policy.safeStoreListing('https://play.google.com/store/apps/details?id=com.example.app'), 'https://play.google.com/store/apps/details?id=com.example.app');
  assert.equal(policy.safeStoreListing('https://attacker.example.test/'), '');
  assert.equal(policy.safeStoreListing('javascript:alert(1)'), '');
});

test('App Store API client checks updates through the matching platform route', async () => {
  let requestedUrl = '';
  const api = loadApi({
    apiBaseUrl: 'https://hendydichvumang-telegram-bot.onrender.com',
    fetchImpl: async (url) => {
      requestedUrl = url;
      return {
        ok: true,
        status: 200,
        headers: { get: () => 'application/json' },
        json: async () => ({ ok: true, hasUpdate: true, latestVersion: '2.0.0' })
      };
    }
  });
  const result = await api.checkUpdate('hendydesk', '1.0.0', 'windows');
  const parsed = new URL(requestedUrl);
  assert.equal(parsed.pathname, '/api/v1/store/releases/check');
  assert.equal(parsed.searchParams.get('appId'), 'hendydesk');
  assert.equal(parsed.searchParams.get('currentVersion'), '1.0.0');
  assert.equal(parsed.searchParams.get('platform'), 'windows');
  assert.equal(result.hasUpdate, true);
});
