import test from "node:test";
import assert from "node:assert/strict";
import worker, { getMainMenuKeyboard, getScreen, escapeHtml, constantTimeEqual, validateWebAppInitData } from "../src/index.js";
import { webcrypto } from "node:crypto";

if (!globalThis.crypto) globalThis.crypto = webcrypto;

test("menu chính dùng đúng callback data và chỉ một menu bot", () => {
  const keyboard = getMainMenuKeyboard();
  const allButtons = keyboard.inline_keyboard.flat();
  assert.ok(allButtons.some((button) => button.callback_data === "menu:minigame"));
  assert.ok(allButtons.some((button) => button.callback_data === "menu:account"));
  assert.equal(allButtons.some((button) => button.callback_data === "menu:secondary_bot"), false);
});

test("màn hình chức năng chưa cấu hình có nút quay về", () => {
  const screen = getScreen("shop:static_proxy", "Bạn", {});
  assert.match(screen.text, /CHƯA KHẢ DỤNG/);
  assert.equal(screen.keyboard.inline_keyboard[0][0].callback_data, "menu:main");
});

test("tên người dùng được escape trước khi chèn vào HTML Telegram", () => {
  assert.equal(escapeHtml(`<img src=x onerror='x'> &`), "&lt;img src=x onerror=&#39;x&#39;&gt; &amp;");
});

test("so sánh secret phân biệt nội dung và độ dài", () => {
  assert.equal(constantTimeEqual("abc_123", "abc_123"), true);
  assert.equal(constantTimeEqual("abc_123", "abc_124"), false);
  assert.equal(constantTimeEqual("abc", "abcd"), false);
});

test("xác thực initData Mini App thành công và từ chối chữ ký sai", async () => {
  const token = "123456:TEST_TOKEN";
  const now = 1_800_000_000;
  const params = new URLSearchParams({ auth_date: String(now), query_id: "query-test", user: JSON.stringify({ id: 42, first_name: "Hendy" }) });
  const dataCheck = [...params.entries()].map(([key, value]) => `${key}=${value}`).sort().join("\n");
  const encoder = new TextEncoder();
  const hmacKey = await crypto.subtle.importKey("raw", encoder.encode("WebAppData"), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const derived = await crypto.subtle.sign("HMAC", hmacKey, encoder.encode(token));
  const signingKey = await crypto.subtle.importKey("raw", derived, { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const digest = await crypto.subtle.sign("HMAC", signingKey, encoder.encode(dataCheck));
  const hash = [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
  params.set("hash", hash);
  const valid = await validateWebAppInitData(params.toString(), token, now);
  assert.equal(valid.ok, true);
  assert.equal(valid.user.id, 42);
  params.set("hash", "0".repeat(64));
  const invalid = await validateWebAppInitData(params.toString(), token, now);
  assert.equal(invalid.ok, false);
  assert.equal(invalid.error, "invalid_signature");
});

test("health endpoint trả về trạng thái Worker", async () => {
  const response = await worker.fetch(new Request("https://example.workers.dev/health"), {});
  assert.equal(response.status, 200);
  const body = await response.json();
  assert.equal(body.service, "hendydichvumang-bot");
  assert.equal(body.status, "healthy");
});

test("webhook từ chối request không có secret hợp lệ", async () => {
  const request = new Request("https://example.workers.dev/telegram/webhook", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ update_id: 1, message: { text: "/start" } }),
  });
  const response = await worker.fetch(request, { BOT_TOKEN: "not-used", WEBHOOK_SECRET: "expected-secret" });
  assert.equal(response.status, 401);
  assert.deepEqual(await response.json(), { ok: false, error: "unauthorized" });
});
