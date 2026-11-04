const MAX_DETAILS_BYTES = 2048;

export async function writeAuditLog(
  db,
  { actorTelegramId = null, action, target = null, details = {}, now = Math.floor(Date.now() / 1000) }
) {
  if (!db) throw new Error("D1 database is not configured");
  if (typeof action !== "string" || !/^[a-z][a-z0-9_.-]{1,79}$/.test(action)) {
    throw new TypeError("Invalid audit action");
  }
  if (actorTelegramId !== null &&
      (!Number.isSafeInteger(Number(actorTelegramId)) || Number(actorTelegramId) <= 0)) {
    throw new TypeError("Invalid audit actor");
  }
  if (target !== null && (typeof target !== "string" || target.length > 160)) {
    throw new TypeError("Invalid audit target");
  }
  if (!details || typeof details !== "object" || Array.isArray(details)) {
    throw new TypeError("Audit details must be an object");
  }
  const serialized = JSON.stringify(details);
  if (new TextEncoder().encode(serialized).byteLength > MAX_DETAILS_BYTES) {
    throw new RangeError("Audit details are too large");
  }

  const id = crypto.randomUUID();
  await db.prepare(
    `INSERT INTO audit_logs
      (id, actor_telegram_id, action, target, details_json, created_at)
     VALUES (?, ?, ?, ?, ?, ?)`
  ).bind(id, actorTelegramId, action, target, serialized, now).run();
  return id;
}
