import test from 'node:test';
import assert from 'node:assert/strict';
import { loadConfig } from '../src/config.js';

test('production config requires an admin password', () => {
  assert.throws(
    () => loadConfig({ NODE_ENV: 'production', DATABASE_URL: 'postgres://db' }),
    /ADMIN_PASSWORD/
  );
});

test('production config requires webhook secret when a bot token is configured', () => {
  assert.throws(
    () => loadConfig({
      NODE_ENV: 'production',
      DATABASE_URL: 'postgres://db',
      ADMIN_PASSWORD: 'long-strong-password',
      BOT_TOKEN: '123:token',
      RENDER_EXTERNAL_URL: 'https://hendy.onrender.com'
    }),
    /TELEGRAM_WEBHOOK_SECRET/
  );
});

test('production config normalizes generated webhook secret and trusts Render URL', () => {
  const config = loadConfig({
    NODE_ENV: 'production',
    DATABASE_URL: 'postgres://db',
    ADMIN_USERNAME: 'ops',
    ADMIN_PASSWORD: 'long-strong-password',
    BOT_TOKEN: '123:token',
    TELEGRAM_WEBHOOK_SECRET: 'render-generated+/=secret',
    RENDER_EXTERNAL_URL: 'https://hendy.onrender.com'
  });
  assert.match(config.webhookSecret, /^[A-Za-z0-9_-]{43}$/);
  assert.equal(config.publicAppUrl, 'https://hendy.onrender.com');
  assert.deepEqual(config.allowedOrigins, ['https://hendy.onrender.com']);
});

test('development config defaults to local URL and does not require admin secret', () => {
  const config = loadConfig({ DATABASE_URL: 'postgres://localhost/db' });
  assert.equal(config.publicAppUrl, 'http://localhost:10000');
  assert.equal(config.isProduction, false);
});
