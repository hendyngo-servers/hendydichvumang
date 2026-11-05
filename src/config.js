function required(name, value) {
  if (!value) throw new Error(`Thiếu biến môi trường bắt buộc: ${name}`);
  return value;
}

export function loadConfig(env = process.env) {
  const isProduction = env.NODE_ENV === 'production';
  return {
    nodeEnv: env.NODE_ENV || 'development',
    port: Number(env.PORT || 10000),
    databaseUrl: required('DATABASE_URL', env.DATABASE_URL),
    botToken: env.BOT_TOKEN || '',
    webhookSecret: env.TELEGRAM_WEBHOOK_SECRET || '',
    adminUsername: env.ADMIN_USERNAME || 'admin',
    adminPassword: env.ADMIN_PASSWORD || '',
    publicAppUrl: (env.PUBLIC_APP_URL || env.RENDER_EXTERNAL_URL || `http://localhost:${env.PORT || 10000}`).replace(/\/$/, ''),
    allowedOrigins: (env.ALLOWED_ORIGINS || env.PUBLIC_APP_URL || env.RENDER_EXTERNAL_URL || '')
      .split(',').map((v) => v.trim()).filter(Boolean),
    initDataMaxAgeSeconds: Math.max(60, Number(env.TELEGRAM_INITDATA_MAX_AGE_SECONDS || 86400)),
    isProduction
  };
}
