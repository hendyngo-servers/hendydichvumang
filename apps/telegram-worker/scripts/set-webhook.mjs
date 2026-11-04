#!/usr/bin/env node

const botToken = process.env.BOT_TOKEN || "";
const webhookSecret = process.env.WEBHOOK_SECRET || "";
const workerApiUrl = String(process.env.WORKER_API_URL || "").replace(/\/$/, "");

if (!/^\d{6,12}:[A-Za-z0-9_-]{30,}$/.test(botToken)) {
  console.error("BOT_TOKEN thiếu hoặc không đúng định dạng. Không in giá trị token ra log.");
  process.exit(1);
}
if (!/^[A-Za-z0-9_-]{1,256}$/.test(webhookSecret)) {
  console.error("WEBHOOK_SECRET thiếu hoặc không đúng định dạng.");
  process.exit(1);
}

let webhookUrl;
try {
  const base = new URL(workerApiUrl);
  if (base.protocol !== "https:" || base.username || base.password || base.search || base.hash) {
    throw new Error("invalid_worker_url");
  }
  webhookUrl = new URL("/telegram/webhook", base).toString();
} catch {
  console.error("WORKER_API_URL phải là URL HTTPS hợp lệ.");
  process.exit(1);
}

async function telegramRequest(method, payload) {
  // The Telegram Bot API requires the bot token in the URL path.
  // Do not log this URL or print raw fetch exceptions / API response bodies.
  let response;
  try {
    response = await fetch("https://api.telegram.org/bot" + botToken + "/" + method, {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify(payload),
      redirect: "error",
      signal: AbortSignal.timeout(10000)
    });
  } catch {
    throw new Error("Không thể kết nối Telegram API cho thao tác " + method + ".");
  }

  const result = await response.json().catch(() => null);
  if (!response.ok || !result?.ok) {
    const code = Number.isInteger(result?.error_code) ? result.error_code : response.status;
    throw new Error("Telegram API từ chối thao tác " + method + " (mã " + code + ").");
  }
  return result.result;
}

const commands = [
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
];

try {
  await telegramRequest("setWebhook", {
    url: webhookUrl,
    secret_token: webhookSecret,
    allowed_updates: ["message", "callback_query"],
    drop_pending_updates: false
  });
  console.log("Đã đăng ký Telegram webhook thành công.");

  await telegramRequest("setMyCommands", { commands });
  console.log("Đã cập nhật danh sách lệnh Telegram Bot.");
} catch (error) {
  console.error(error instanceof Error ? error.message : "Telegram API request failed.");
  process.exit(1);
}
