const MAX_STATE_BYTES = 4096;

function validateUserId(telegramUserId) {
  if (!Number.isSafeInteger(Number(telegramUserId)) || Number(telegramUserId) <= 0) {
    throw new TypeError("Invalid Telegram user ID");
  }
  return Number(telegramUserId);
}

export async function getUserSession(db, telegramUserId, now = Math.floor(Date.now() / 1000)) {
  if (!db) throw new Error("D1 database is not configured");
  const userId = validateUserId(telegramUserId);
  const row = await db.prepare(
    "SELECT state_json, expires_at FROM user_sessions WHERE telegram_user_id = ?"
  ).bind(userId).first();
  if (!row) return null;

  if (!Number.isFinite(Number(row.expires_at)) || Number(row.expires_at) <= now) {
    await clearUserSession(db, userId);
    return null;
  }

  try {
    const state = JSON.parse(row.state_json);
    if (!state || typeof state !== "object" || Array.isArray(state)) {
      await clearUserSession(db, userId);
      return null;
    }
    return state;
  } catch {
    await clearUserSession(db, userId);
    return null;
  }
}

export async function setUserSession(
  db,
  telegramUserId,
  state,
  ttlSeconds = 900,
  now = Math.floor(Date.now() / 1000)
) {
  if (!db) throw new Error("D1 database is not configured");
  const userId = validateUserId(telegramUserId);
  if (!state || typeof state !== "object" || Array.isArray(state)) {
    throw new TypeError("Session state must be an object");
  }
  if (!Number.isInteger(ttlSeconds) || ttlSeconds < 60 || ttlSeconds > 86400) {
    throw new RangeError("Session TTL must be between 60 and 86400 seconds");
  }
  const serialized = JSON.stringify(state);
  if (new TextEncoder().encode(serialized).byteLength > MAX_STATE_BYTES) {
    throw new RangeError("Session state is too large");
  }
  const expiresAt = now + ttlSeconds;
  await db.prepare(
    `INSERT INTO user_sessions
      (telegram_user_id, state_json, expires_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?)
     ON CONFLICT(telegram_user_id) DO UPDATE SET
       state_json = excluded.state_json,
       expires_at = excluded.expires_at,
       updated_at = excluded.updated_at`
  ).bind(userId, serialized, expiresAt, now, now).run();
  return { expiresAt };
}

export async function clearUserSession(db, telegramUserId) {
  if (!db) throw new Error("D1 database is not configured");
  const userId = validateUserId(telegramUserId);
  await db.prepare("DELETE FROM user_sessions WHERE telegram_user_id = ?")
    .bind(userId).run();
}
