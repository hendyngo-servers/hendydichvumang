const SAFE_FIELDS = new Set([
  "updateId", "telegramUserId", "durationMs", "action",
  "status", "errorCode", "route", "count"
]);

export function sanitizeLogFields(fields = {}) {
  const safe = {};
  if (!fields || typeof fields !== "object" || Array.isArray(fields)) return safe;

  for (const [key, value] of Object.entries(fields)) {
    if (!SAFE_FIELDS.has(key)) continue;
    if (typeof value === "number" && Number.isFinite(value)) {
      safe[key] = value;
    } else if (typeof value === "boolean") {
      safe[key] = value;
    } else if (typeof value === "string") {
      safe[key] = value
        .replace(/[\u0000-\u001f\u007f]/g, " ")
        .replace(/bot\d+:[A-Za-z0-9_-]{20,}/gi, "[REDACTED]")
        .replace(/(bearer\s+)[^\s,;]+/gi, "$1[REDACTED]")
        .slice(0, 100);
    }
  }
  return safe;
}

export function safeLog(level, event, fields = {}) {
  const method = ["debug", "info", "warn", "error"].includes(level) ? level : "info";
  const eventName = /^[a-z0-9_.-]{1,80}$/i.test(String(event)) ? String(event) : "app.event";
  const entry = {
    timestamp: new Date().toISOString(),
    event: eventName,
    ...sanitizeLogFields(fields)
  };
  console[method](JSON.stringify(entry));
  return entry;
}
