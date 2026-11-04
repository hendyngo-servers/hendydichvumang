const CLAIM_LEASE_SECONDS = 60;

export async function claimTelegramUpdate(db, updateId, now = Math.floor(Date.now() / 1000)) {
  if (!db?.prepare) throw new Error("database_not_configured");
  if (!Number.isSafeInteger(updateId) || updateId < 0) {
    return { claimed: false, invalid: true };
  }

  const claimToken = crypto.randomUUID();
  // The unique claim token prevents an expired worker from finalizing a newer retry.
  const claimed = await db.prepare(
    `INSERT INTO telegram_webhook_updates
      (update_id, status, claim_token, attempts, lock_expires_at, created_at, updated_at)
    VALUES (?, 'processing', ?, 1, ?, ?, ?)
    ON CONFLICT(update_id) DO UPDATE SET
      status = 'processing',
      claim_token = excluded.claim_token,
      attempts = telegram_webhook_updates.attempts + 1,
      lock_expires_at = excluded.lock_expires_at,
      error_code = NULL,
      updated_at = excluded.updated_at
    WHERE telegram_webhook_updates.status = 'failed'
       OR (
         telegram_webhook_updates.status = 'processing'
         AND telegram_webhook_updates.lock_expires_at <= ?
       )
    RETURNING update_id`
  ).bind(updateId, claimToken, now + CLAIM_LEASE_SECONDS, now, now, now).first();

  if (claimed) return { claimed: true, claimToken };

  const existing = await db.prepare(
    `SELECT status, lock_expires_at
     FROM telegram_webhook_updates
     WHERE update_id = ?`
  ).bind(updateId).first();

  if (existing?.status === "processed") {
    return { claimed: false, duplicate: true };
  }
  return {
    claimed: false,
    busy: existing?.status === "processing",
    status: existing?.status || "unknown"
  };
}

export async function finishTelegramUpdate(db, updateId, {
  claimToken,
  success,
  errorCode = "UPDATE_PROCESSING_FAILED",
  now = Math.floor(Date.now() / 1000)
} = {}) {
  if (!db?.prepare) throw new Error("database_not_configured");
  if (!Number.isSafeInteger(updateId) || updateId < 0 || !claimToken) {
    throw new Error("invalid_update_claim");
  }

  const status = success ? "processed" : "failed";
  const safeErrorCode = /^[A-Z0-9_]{1,64}$/.test(String(errorCode))
    ? String(errorCode)
    : "UPDATE_PROCESSING_FAILED";

  await db.prepare(
    `UPDATE telegram_webhook_updates
     SET status = ?,
         lock_expires_at = 0,
         error_code = ?,
         processed_at = ?,
         updated_at = ?
     WHERE update_id = ? AND claim_token = ? AND status = 'processing'`
  ).bind(
    status,
    success ? null : safeErrorCode,
    success ? now : null,
    now,
    updateId,
    claimToken
  ).run();
}
