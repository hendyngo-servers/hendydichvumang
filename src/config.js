import { normalizeWebhookSecret } from './security.js';

function readPositiveInteger(value, fallback, name, max = 2_147_483_647) {
  if (value === undefined || value === '') return fallback;
  const parsed = Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < 1 || parsed > max) {
    throw new Error(name + ' không hợp lệ.');
  }
  return parsed;
}

function normalizeBaseUrl(value, fallback, name) {
  const raw = String(value || fallback).trim().replace(/\/+$/, '');
  let url;
  try { url = new URL(raw); } catch { throw new Error(name + ' không phải URL hợp lệ.'); }
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error(name + ' phải dùng HTTP hoặc HTTPS.');
  return url.origin;
}

export function loadConfig(env = process.env) {
  const isProduction = env.NODE_ENV === 'production';
  const databaseUrl = String(env.DATABASE_URL || env.EXTERNAL_DATABASE_URL || '').trim();
  if (!databaseUrl) throw new Error('Thiếu DATABASE_URL (có thể dùng PostgreSQL bên ngoài).');
  let dbUrl;
  try { dbUrl = new URL(databaseUrl); } catch { throw new Error('DATABASE_URL không hợp lệ.'); }
  if (!['postgres:', 'postgresql:'].includes(dbUrl.protocol) || !dbUrl.hostname) {
    throw new Error('DATABASE_URL phải là PostgreSQL URL hợp lệ.');
  }

  const port = readPositiveInteger(env.PORT, 10000, 'PORT', 65535);
  const botToken = String(env.BOT_TOKEN || '').trim();
  const rawWebhookSecret = String(env.TELEGRAM_WEBHOOK_SECRET || '').trim();
  const adminUsername = String(env.ADMIN_USERNAME || 'admin').trim();
  const adminPassword = String(env.ADMIN_PASSWORD || '');
  const sslMode = String(env.PGSSLMODE || (isProduction ? 'require' : 'disable')).trim().toLowerCase();

  if (!['disable', 'require', 'verify-full'].includes(sslMode)) {
    throw new Error('PGSSLMODE chỉ nhận disable, require hoặc verify-full.');
  }
  if (isProduction && adminPassword.length < 16) {
    throw new Error('ADMIN_PASSWORD phải có ít nhất 16 ký tự trong môi trường production.');
  }
  if (isProduction && !adminUsername) throw new Error('ADMIN_USERNAME không được để trống.');
  if (botToken && !rawWebhookSecret) throw new Error('Đã cấu hình BOT_TOKEN nhưng thiếu TELEGRAM_WEBHOOK_SECRET.');

  const publicApiUrl = normalizeBaseUrl(
    env.PUBLIC_API_URL || env.RENDER_EXTERNAL_URL,
    'http://localhost:' + port,
    'PUBLIC_API_URL'
  );
  const publicAppUrl = normalizeBaseUrl(env.PUBLIC_APP_URL, publicApiUrl, 'PUBLIC_APP_URL');
  if (isProduction && botToken && !publicApiUrl.startsWith('https://')) {
    throw new Error('PUBLIC_API_URL phải là HTTPS khi bật Telegram Bot.');
  }
  if (isProduction && botToken && !publicAppUrl.startsWith('https://')) {
    throw new Error('PUBLIC_APP_URL phải là HTTPS khi bật Telegram Mini App.');
  }

  const origins = String(env.ALLOWED_ORIGINS || '')
    .split(',').map((item) => item.trim().replace(/\/+$/, '')).filter(Boolean);
  const adminTelegramId = String(env.ADMIN_TELEGRAM_ID || '').trim();
  if (adminTelegramId && !/^-?\d{5,20}$/.test(adminTelegramId)) {
    throw new Error('ADMIN_TELEGRAM_ID không hợp lệ.');
  }

  return {
    nodeEnv: env.NODE_ENV || 'development',
    isProduction,
    port,
    databaseUrl,
    databaseSslMode: sslMode,
    databaseSslCa: String(env.PGSSL_CA_CERT || ''),
    databasePoolMax: readPositiveInteger(env.PG_POOL_MAX, 5, 'PG_POOL_MAX', 20),
    botToken,
    webhookSecret: normalizeWebhookSecret(rawWebhookSecret),
    adminUsername,
    adminPassword,
    adminTelegramId,
    publicApiUrl,
    publicAppUrl,
    allowedOrigins: [...new Set([publicApiUrl, publicAppUrl, ...origins])],
    initDataMaxAgeSeconds: readPositiveInteger(env.TELEGRAM_INITDATA_MAX_AGE_SECONDS, 86400, 'TELEGRAM_INITDATA_MAX_AGE_SECONDS', 604800),
    sessionTtlSeconds: readPositiveInteger(env.SESSION_TTL_SECONDS, 604800, 'SESSION_TTL_SECONDS', 2_592_000)
  };
}
