import test from "node:test";
import assert from "node:assert/strict";
import { clearUserSession, getUserSession, setUserSession } from "../src/core/session-store.js";

function memoryDb() {
  let row = null;
  return {
    get row() { return row; },
    prepare(sql) {
      return {
        bind(...values) {
          return {
            async first() {
              if (!sql.includes("SELECT state_json")) throw new Error("Unexpected first query");
              return row ? { ...row } : null;
            },
            async run() {
              if (sql.includes("INSERT INTO user_sessions")) {
                const [telegramUserId, stateJson, expiresAt, createdAt, updatedAt] = values;
                row = { telegram_user_id: telegramUserId, state_json: stateJson, expires_at: expiresAt, created_at: createdAt, updated_at: updatedAt };
                return { meta: { changes: 1 } };
              }
              if (sql.includes("DELETE FROM user_sessions")) {
                row = null;
                return { meta: { changes: 1 } };
              }
              throw new Error("Unexpected run query");
            }
          };
        }
      };
    }
  };
}

test("session store persists a short-lived JSON state", async () => {
  const db = memoryDb();
  const result = await setUserSession(db, 123, { action: "support:subject" }, 900, 1000);
  assert.equal(result.expiresAt, 1900);
  assert.deepEqual(await getUserSession(db, 123, 1001), { action: "support:subject" });
});

test("expired and malformed sessions are removed", async () => {
  const db = memoryDb();
  await setUserSession(db, 123, { action: "support:subject" }, 60, 1000);
  assert.equal(await getUserSession(db, 123, 1060), null);
  assert.equal(db.row, null);
  await setUserSession(db, 123, { action: "support:subject" }, 60, 1000);
  db.row.state_json = "{broken";
  assert.equal(await getUserSession(db, 123, 1001), null);
  assert.equal(db.row, null);
});

test("session store rejects invalid IDs, oversized state and invalid TTL", async () => {
  const db = memoryDb();
  await assert.rejects(setUserSession(db, 0, { action: "x" }), /user ID/i);
  await assert.rejects(setUserSession(db, 1, { text: "x".repeat(5000) }), /too large/i);
  await assert.rejects(setUserSession(db, 1, { action: "x" }, 10), /TTL/i);
});

test("clearUserSession removes only the requested user's session", async () => {
  const db = memoryDb();
  await setUserSession(db, 123, { action: "support:subject" }, 900, 1000);
  await clearUserSession(db, 123);
  assert.equal(db.row, null);
});
