#!/usr/bin/env node

const token = process.env.BOT_TOKEN;
const secret = process.env.WEBHOOK_SECRET;
const workerApiUrl = String(process.env.WORKER_API_URL || "").replace(/\/$/, "");
const webhookUrl = process.env.BOT_WEBHOOK_URL || (workerApiUrl ? workerApiUrl + "/telegram/webhook" : "");

if (!token || !secret || !webhookUrl) {
  console.error("Thiếu BOT_TOKEN, WEBHOOK_SECRET hoặc WORKER_API_URL.");
  process.exit(1);
}
if (!/^https:\/\//i.test(webhookUrl)) {
  console.error("Webhook URL phải là HTTPS.");
  process.exit(1);
}
if (!/^[A-Za-z0-9_-]{1,256}$/.test(secret)) {
  console.error("WEBHOOK_SECRET không đúng định dạng Telegram.");
  process.exit(1);
}

const response = await fetch(`https://api.telegram.org/bot${token}/setWebhook`, {
  method: "POST",
  headers: { "content-type": "application/json" },
  body: JSON.stringify({
    url: webhookUrl,
    secret_token: secret,
    allowed_updates: ["message", "callback_query"],
    drop_pending_updates: false
  })
});
const result = await response.json().catch(() => null);
if (!response.ok || !result?.ok) {
  console.error("Telegram không chấp nhận cấu hình webhook:", result?.description || response.status);
  process.exit(1);
}
console.log("Đã đăng ký Telegram webhook thành công.");

const commandsResult = await fetch(`https://api.telegram.org/bot${token}/setMyCommands`, {
  method: "POST",
  headers: { "content-type": "application/json" },
  body: JSON.stringify({
    commands: [
      { command: "start", description: "Mở menu dịch vụ chính" },
      { command: "menu", description: "Hiển thị danh sách chức năng" },
      { command: "help", description: "Hướng dẫn sử dụng bot" },
      { command: "nap", description: "Xem thông tin nạp tiền" },
      { command: "otp", description: "Dịch vụ OTP" },
      { command: "proxy", description: "Dịch vụ Proxy" },
      { command: "vip", description: "Kho VIP" },
      { command: "code", description: "Mua Code" },
      { command: "tool", description: "Thuê Tool" },
      { command: "live", description: "Minigame Live" },
      { command: "support", description: "Trung tâm hỗ trợ" }
    ]
  })
});
const commandsData = await commandsResult.json().catch(() => null);
if (!commandsResult.ok || !commandsData?.ok) {
  console.error("Không thể cập nhật danh sách lệnh Bot:", commandsData?.description || commandsResult.status);
  process.exit(1);
}
console.log("Đã cập nhật danh sách lệnh Telegram Bot.");
