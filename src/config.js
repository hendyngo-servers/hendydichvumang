import { normalizeWebhookSecret } from './security.js';

function readPositiveInteger(value, fallback, name, max = 2_147_483_647) {
  if (value === undefined || value === '') return fallback;
  const parsed = Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < 1 || parsed > max) {
    throw new Error(name + ' không hợp lệ.');
  }
  return parsed;
}

function normalizeBaseUrl(value, fallback) {
  const raw = String(value || fallback).trim().replace(/\/+$/, '');
  let url;
  try { url = new URL(raw); } catch { throw new Error('PUBLIC_APP_URL không phải URL hợp lệ.'); }
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error('PUBLIC_APP_URL phải dùng HTTP hoặc HTTPS.');
  return url.origin;
}

export function loadConfig(env = process.env) {
  const isProduction = env.NODE_ENV === 'production';
  const databaseUrl = String(env.DATABASE_URL || '').trim();
  if (!databaseUrl) throw new Error('Thiếu biến môi trường bắt buộc: DATABASE_URL');
  let dbUrl;
  try { dbUrl = new URL(databaseUrl); } catch { throw new Error('DATABASE_URL không hợp lệ.'); }
  if (!['postgres:', 'postgresql:'].includes(dbUrl.protocol)) throw new Error('DATABASE_URL phải là PostgreSQL URL.');

  const port = readPositiveInteger(env.PORT, 10000, 'PORT', 65535);
  const botToken = String(env.BOT_TOKEN || '').trim();
  const rawWebhookSecret = String(env.TELEGRAM_WEBHOOK_SECRET || '').trim();
  const adminUsername = String(env.ADMIN_USERNAME || 'admin').trim();
  const adminPassword = String(env.ADMIN_PASSWORD || '');

  if (isProduction && adminPassword.length < 16) {
    throw new Error('ADMIN_PASSWORD phải có ít nhất 16 ký tự trong môi trường production.');
  }
  if (isProduction && !adminUsername) throw new Error('ADMIN_USERNAME không được để trống.');
  if (botToken && !rawWebhookSecret) throw new Error('Đã cấu hình BOT_TOKEN nhưng thiếu TELEGRAM_WEBHOOK_SECRET.');

  const publicAppUrl = normalizeBaseUrl(
    env.PUBLIC_APP_URL || env.RENDER_EXTERNAL_URL,
    'http://localhost:' + port
  );
  if (isProduction && botToken && !publicAppUrl.startsWith('https://')) {
    throw new Error('PUBLIC_APP_URL phải là HTTPS khi bật Telegram Bot.');
  }

  const origins = String(env.ALLOWED_ORIGINS || publicAppUrl)
    .split(',').map((item) => item.trim().replace(/\/+$/, '')).filter(Boolean);

  return {
    nodeEnv: env.NODE_ENV || 'development',
    isProduction,
    port,
    databaseUrl,
    botToken,
    webhookSecret: normalizeWebhookSecret(rawWebhookSecret),
    adminUsername,
    adminPassword,
    publicAppUrl,
    allowedOrigins: [...new Set(origins)],
    initDataMaxAgeSeconds: readPositiveInteger(env.TELEGRAM_INITDATA_MAX_AGE_SECONDS, 86400, 'TELEGRAM_INITDATA_MAX_AGE_SECONDS', 604800),
    sessionTtlSeconds: readPositiveInteger(env.SESSION_TTL_SECONDS, 604800, 'SESSION_TTL_SECONDS', 2_592_000)
  };
}
