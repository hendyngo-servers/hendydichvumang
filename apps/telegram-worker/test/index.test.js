import test from "node:test";
import assert from "node:assert/strict";
import worker, { mainKeyboard, screenFor, escapeHtml, constantTimeEqual, validateInitData } from "../src/index.js";
import { webcrypto } from "node:crypto";
if (!globalThis.crypto) globalThis.crypto = webcrypto;

test("menu chính hiển thị các nhóm OTP, Proxy, VIP, Code, Tool và Live", () => {
  const buttons = mainKeyboard({ MINI_APP_URL: "https://example.pages.dev/" }).inline_keyboard.flat();
  for (const key of ["otp", "proxy", "vip", "code", "rental", "live"]) {
    assert.ok(buttons.some((button) => button.callback_data === "service:" + key));
  }
  assert.ok(buttons.some((button) => button.web_app?.url === "https://example.pages.dev/"));
});
test("màn hình dịch vụ có nút quay lại menu", () => {
  const screen = screenFor("service:proxy", "Bạn", {});
  assert.match(screen.text, /DỊCH VỤ PROXY/);
  assert.equal(screen.keyboard.inline_keyboard.at(-1)[0].callback_data, "menu:main");
});
test("HTML escaping ngăn chèn markup", () => {
  assert.equal(escapeHtml("<img src=x> &"), "&lt;img src=x&gt; &amp;");
});
test("so sánh secret constant-time cho nội dung và độ dài", () => {
  assert.equal(constantTimeEqual("abc_123", "abc_123"), true);
  assert.equal(constantTimeEqual("abc_123", "abc_124"), false);
  assert.equal(constantTimeEqual("abc", "abcd"), false);
});
test("xác thực initData hợp lệ và từ chối hash sai", async () => {
  const token = "123456:TEST_TOKEN", now = 1800000000;
  const params = new URLSearchParams({ auth_date: String(now), query_id: "query-test", user: JSON.stringify({ id: 42, first_name: "Hendy" }) });
  const check = [...params.entries()].map(([k,v]) => k + "=" + v).sort().join("\n");
  const enc = new TextEncoder();
  const first = await crypto.subtle.importKey("raw", enc.encode("WebAppData"), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const secret = await crypto.subtle.sign("HMAC", first, enc.encode(token));
  const second = await crypto.subtle.importKey("raw", secret, { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const digest = await crypto.subtle.sign("HMAC", second, enc.encode(check));
  params.set("hash", [...new Uint8Array(digest)].map(v => v.toString(16).padStart(2,"0")).join(""));
  const valid = await validateInitData(params.toString(), token, now);
  assert.equal(valid.ok, true);
  assert.equal(valid.user.id, 42);
  params.set("hash", "0".repeat(64));
  assert.equal((await validateInitData(params.toString(), token, now)).ok, false);
});
test("health endpoint trả trạng thái Worker", async () => {
  const response = await worker.fetch(new Request("https://example.workers.dev/health"), {});
  assert.equal(response.status, 200);
  assert.equal((await response.json()).status, "healthy");
});
test("webhook từ chối secret sai", async () => {
  const request = new Request("https://example.workers.dev/telegram/webhook", { method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify({ update_id: 1 }) });
  const response = await worker.fetch(request, { BOT_TOKEN: "not-used", WEBHOOK_SECRET: "expected-secret" });
  assert.equal(response.status, 401);
});
