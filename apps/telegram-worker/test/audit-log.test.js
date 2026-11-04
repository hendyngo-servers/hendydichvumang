import test from "node:test";
import assert from "node:assert/strict";
import { writeAuditLog } from "../src/core/audit-log.js";

test("audit log stores bounded metadata and never requires message content", async () => {
  let captured;
  const db = {
    prepare(sql) {
      assert.match(sql, /INSERT INTO audit_logs/);
      return { bind(...values) { captured = values; return { run: async () => ({ meta: { changes: 1 } }) }; } };
    }
  };
  const id = await writeAuditLog(db, {
    actorTelegramId: 123,
    action: "ticket.created",
    target: "ticket-1",
    details: { category: "general" },
    now: 1000
  });
  assert.ok(id);
  assert.equal(captured[1], 123);
  assert.equal(captured[2], "ticket.created");
  assert.deepEqual(JSON.parse(captured[4]), { category: "general" });
  assert.equal(captured[5], 1000);
});

test("audit log rejects unbounded or malformed records", async () => {
  const db = { prepare: () => ({ bind: () => ({ run: async () => ({}) }) }) };
  await assert.rejects(writeAuditLog(db, { action: "DELETE * FROM users" }), /action/i);
  await assert.rejects(writeAuditLog(db, { action: "ticket.created", details: { content: "x".repeat(3000) } }), /too large/i);
});
