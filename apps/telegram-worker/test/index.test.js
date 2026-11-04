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
  assert.equal((await validateInitData(params.toString(), "wrong-token", now)).ok, false);
  params.set("hash", "0".repeat(64));
  assert.equal((await validateInitData(params.toString(), token, now)).ok, false);
});

test("đối chiếu vector mẫu xác thực do tài liệu Telegram công bố", async () => {
  const token = "5768337691:AAH5YkoiEuPk8-FZa32hStHTqXiLPtAEhx8";
  const raw = "query_id=AAHdF6IQAAAAAN0XohDhrOrc&user=%7B%22id%22%3A279058397%2C%22first_name%22%3A%22Vladislav%22%2C%22last_name%22%3A%22Kibenko%22%2C%22username%22%3A%22vdkfrost%22%2C%22language_code%22%3A%22ru%22%2C%22is_premium%22%3Atrue%7D&auth_date=1662771648&hash=c501b71e775f74ce10e377dea85a7ea24ecd640b223ea86dfe453e0eaed2e2b2";
  const result = await validateInitData(raw, token, 1662771648);
  assert.equal(result.ok, true);
  assert.equal(result.user.id, 279058397);
});

test("từ chối initData quá hạn", async () => {
  const result = await validateInitData("auth_date=100&hash=" + "a".repeat(64), "token", 100 + 86401);
  assert.equal(result.ok, false);
  assert.equal(result.error, "expired_or_invalid_auth_date");
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
