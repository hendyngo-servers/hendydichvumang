import crypto from 'node:crypto';

const toHex = (buffer) => Buffer.from(buffer).toString('hex');

export function safeEqual(left, right) {
  const a = Buffer.from(String(left ?? ''));
  const b = Buffer.from(String(right ?? ''));
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

export function verifyTelegramInitData(initData, botToken, maxAgeSeconds = 86400, nowSeconds = Math.floor(Date.now() / 1000)) {
  if (!initData || !botToken) return { ok: false, reason: 'missing' };
  let params;
  try { params = new URLSearchParams(initData); } catch { return { ok: false, reason: 'invalid' }; }
  const receivedHash = params.get('hash');
  const authDate = Number(params.get('auth_date'));
  if (!receivedHash || !/^[a-f0-9]{64}$/i.test(receivedHash) || !Number.isFinite(authDate)) {
    return { ok: false, reason: 'invalid' };
  }
  if (authDate > nowSeconds + 30 || nowSeconds - authDate > maxAgeSeconds) {
    return { ok: false, reason: 'expired' };
  }
  const dataCheckString = [...params.entries()]
    .filter(([key]) => key !== 'hash')
    .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0))
    .map(([key, value]) => `${key}=${value}`)
    .join('\n');
  const secretKey = crypto.createHmac('sha256', 'WebAppData').update(botToken).digest();
  const calculatedHash = crypto.createHmac('sha256', secretKey).update(dataCheckString).digest('hex');
  if (!safeEqual(calculatedHash.toLowerCase(), receivedHash.toLowerCase())) return { ok: false, reason: 'signature' };
  let user;
  try { user = JSON.parse(params.get('user') || 'null'); } catch { return { ok: false, reason: 'user' }; }
  if (!user || !Number.isSafeInteger(Number(user.id))) return { ok: false, reason: 'user' };
  return { ok: true, user, authDate };
}

export function createSessionToken() {
  return crypto.randomBytes(32).toString('base64url');
}

export function hashSessionToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex');
}

export function normalizeWebhookSecret(secret) {
  if (!secret) return '';
  // Telegram Bot API accepts only A-Z, a-z, 0-9, underscore and hyphen.
  // Render-generated values are base64 and may contain +, / or =; derive a stable URL-safe secret.
  return crypto.createHash('sha256').update(String(secret)).digest('base64url');
}

export function verifyWebhookSecret(expected, received) {
  return Boolean(expected && received && safeEqual(expected, received));
}
