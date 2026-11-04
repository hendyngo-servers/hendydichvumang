import test from "node:test";
import assert from "node:assert/strict";
import { sanitizeLogFields } from "../src/core/safe-logger.js";

test("logger keeps allowlisted metadata and removes message payloads and credentials", () => {
  const token = "123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi";
  const safe = sanitizeLogFields({
    telegramUserId: 123,
    route: "service:proxy",
    message: "do not log this",
    callbackData: "private-callback",
    authorization: "Bearer private",
    errorCode: "bot" + token
  });
  assert.deepEqual(safe, {
    telegramUserId: 123,
    route: "service:proxy",
    errorCode: "[REDACTED]"
  });
});
