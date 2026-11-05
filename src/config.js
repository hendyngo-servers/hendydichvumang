import { normalizeWebhookSecret } from './security.js';

function required(name, value) {
  if (!value) throw new Error(`Thiếu biến môi trường bắt buộc: ${name}`);
  return value;
}

export function loadConfig(env = process.env) {
  const isProduction = env.NODE_ENV === 'production';
  const botToken = env.BOT_TOKEN || '';
  const rawWebhookSecret = env.TELEGRAM_WEBHOOK_SECRET || '';
  const adminPassword = env.ADMIN_PASSWORD || '';
  if (isProduction && !adminPassword) throw new Error('Thiếu biến môi trường bắt buộc: ADMIN_PASSWORD');
  if (isProduction && botToken && !rawWebhookSecret) throw new Error('Thiếu TELEGRAM_WEBHOOK_SECRET khi đã cấu hình BOT_TOKEN');
  const publicAppUrl = (env.PUBLIC_APP_URL || env.RENDER_EXTERNAL_URL || `http://localhost:${env.PORT || 10000}`).replace(/\/$/, '');
  if (isProduction && botToken && !publicAppUrl.startsWith('https://')) throw new Error('PUBLIC_APP_URL phải là HTTPS khi bật Telegram Bot');
  return {
    nodeEnv: env.NODE_ENV || 'development',
    port: Number(env.PORT || 10000),
    databaseUrl: required('DATABASE_URL', env.DATABASE_URL),
    botToken,
    webhookSecret: normalizeWebhookSecret(rawWebhookSecret),
    adminUsername: env.ADMIN_USERNAME || 'admin',
    adminPassword,
    publicAppUrl,
    allowedOrigins: (env.ALLOWED_ORIGINS || env.PUBLIC_APP_URL || env.RENDER_EXTERNAL_URL || '')
      .split(',').map((v) => v.trim()).filter(Boolean),
    initDataMaxAgeSeconds: Math.max(60, Number(env.TELEGRAM_INITDATA_MAX_AGE_SECONDS || 86400)),
    isProduction
  };
}
