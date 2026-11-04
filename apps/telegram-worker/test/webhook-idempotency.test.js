import test from "node:test";
import assert from "node:assert/strict";
import { claimTelegramUpdate, finishTelegramUpdate } from "../src/webhook-idempotency.js";

function memoryDb() {
  const rows = new Map();
  return {
    rows,
    prepare(sql) {
      return {
        bind(...values) {
          return {
            async first() {
              if (sql.includes("INSERT INTO telegram_webhook_updates")) {
                const [updateId, claimToken, lockExpiresAt, createdAt, updatedAt, now] = values;
                const existing = rows.get(updateId);
                if (!existing) {
                  rows.set(updateId, {
                    update_id: updateId, status: "processing", claim_token: claimToken, attempts: 1,
                    lock_expires_at: lockExpiresAt, created_at: createdAt,
                    updated_at: updatedAt, processed_at: null, error_code: null
                  });
                  return { update_id: updateId, claim_token: claimToken };
                }
                const canRetry = existing.status === "failed" ||
                  (existing.status === "processing" && existing.lock_expires_at <= now);
                if (!canRetry) return null;
                Object.assign(existing, {
                  status: "processing", claim_token: claimToken,
                  attempts: existing.attempts + 1,
                  lock_expires_at: lockExpiresAt,
                  updated_at: updatedAt,
                  error_code: null
                });
                return { update_id: updateId, claim_token: claimToken };
              }
              if (sql.includes("SELECT status, lock_expires_at")) {
                const existing = rows.get(values[0]);
                return existing ? { status: existing.status, lock_expires_at: existing.lock_expires_at } : null;
              }
              throw new Error("Unexpected query in test: " + sql);
            },
            async run() {
              if (sql.includes("UPDATE telegram_webhook_updates")) {
                const [status, errorCode, processedAt, updatedAt, updateId, claimToken] = values;
                const existing = rows.get(updateId);
                const ownsClaim = existing && existing.claim_token === claimToken && existing.status === "processing";
                if (ownsClaim) Object.assign(existing, {
                  status, error_code: errorCode,
                  processed_at: processedAt,
                  lock_expires_at: 0,
                  updated_at: updatedAt
                });
                return { meta: { changes: ownsClaim ? 1 : 0 } };
              }
              throw new Error("Unexpected query in test: " + sql);
            }
          };
        }
      };
    }
  };
}

test("claimTelegramUpdate claims a new update with a unique lease token", async () => {
  const db = memoryDb();
  const result = await claimTelegramUpdate(db, 1001, 100);
  assert.equal(result.claimed, true);
  assert.ok(result.claimToken);
  assert.equal(db.rows.get(1001).status, "processing");
  assert.equal(db.rows.get(1001).lock_expires_at, 160);
});

test("processed update is acknowledged as a duplicate without reprocessing", async () => {
  const db = memoryDb();
  const claim = await claimTelegramUpdate(db, 1002, 100);
  await finishTelegramUpdate(db, 1002, { claimToken: claim.claimToken, success: true, now: 101 });
  const result = await claimTelegramUpdate(db, 1002, 102);
  assert.deepEqual(result, { claimed: false, duplicate: true });
  assert.equal(db.rows.get(1002).attempts, 1);
});

test("live processing lease blocks concurrent duplicate delivery", async () => {
  const db = memoryDb();
  await claimTelegramUpdate(db, 1003, 100);
  const result = await claimTelegramUpdate(db, 1003, 120);
  assert.equal(result.claimed, false);
  assert.equal(result.busy, true);
  assert.equal(db.rows.get(1003).attempts, 1);
});

test("expired processing lease can be reclaimed after a crashed attempt", async () => {
  const db = memoryDb();
  await claimTelegramUpdate(db, 1004, 100);
  const result = await claimTelegramUpdate(db, 1004, 160);
  assert.equal(result.claimed, true);
  assert.equal(db.rows.get(1004).attempts, 2);
});

test("failed update is retryable and error code is restricted", async () => {
  const db = memoryDb();
  const claim = await claimTelegramUpdate(db, 1005, 100);
  await finishTelegramUpdate(db, 1005, {
    claimToken: claim.claimToken,
    success: false,
    errorCode: "unexpected token: private data",
    now: 110
  });
  assert.equal(db.rows.get(1005).status, "failed");
  assert.equal(db.rows.get(1005).error_code, "UPDATE_PROCESSING_FAILED");
  assert.equal((await claimTelegramUpdate(db, 1005, 111)).claimed, true);
});

test("an expired worker cannot finalize a newer retry claim", async () => {
  const db = memoryDb();
  const oldClaim = await claimTelegramUpdate(db, 1006, 100);
  const newClaim = await claimTelegramUpdate(db, 1006, 160);
  assert.notEqual(oldClaim.claimToken, newClaim.claimToken);
  await finishTelegramUpdate(db, 1006, { claimToken: oldClaim.claimToken, success: true, now: 161 });
  assert.equal(db.rows.get(1006).status, "processing");
  assert.equal(db.rows.get(1006).claim_token, newClaim.claimToken);
});

test("invalid update IDs are rejected", async () => {
  const db = memoryDb();
  assert.deepEqual(await claimTelegramUpdate(db, -1, 100), { claimed: false, invalid: true });
  assert.deepEqual(await claimTelegramUpdate(db, 1.5, 100), { claimed: false, invalid: true });
});
