const TELEGRAM_API = "https://api.telegram.org";
const MAX_BODY_BYTES = 1024 * 1024;
const MAX_INIT_DATA_AGE_SECONDS = 86400;

const SERVICES = {
  otp: { title: "🔐 DỊCH VỤ OTP", text: "Danh mục OTP đang chờ kết nối nhà cung cấp và hệ thống đơn hàng." },
  proxy: { title: "🌐 DỊCH VỤ PROXY", text: "Proxy tĩnh và Proxy xoay sẽ được mở khi cấu hình nhà cung cấp và bảng giá." },
  vip: { title: "👑 KHO VIP", text: "Kho VIP đang chờ kết nối hệ thống cấp quyền và quản lý gói dịch vụ." },
  code: { title: "🎟️ MUA CODE", text: "Danh mục mã Code đang chờ cấu hình sản phẩm và kho mã." },
  rental: { title: "🧰 THUÊ TOOL", text: "Dịch vụ thuê Tool Live đang được tích hợp. Mở Mini App để xem giao diện đăng nhập." },
  live: { title: "🎮 MINIGAME LIVE", text: "Khu vực Minigame Live đang chờ kết nối dịch vụ." },
  deposit: { title: "💳 NẠP TIỀN", text: "Cổng nạp tiền chưa được cấu hình. Vui lòng liên hệ CSKH để được hướng dẫn. Không chuyển tiền theo thông tin chưa xác nhận." },
  support: { title: "💬 TRUNG TÂM HỖ TRỢ", text: "Kênh CSKH trực tiếp sẽ được hiển thị khi quản trị viên cấu hình SUPPORT_URL. Ticket chưa được lưu cho tới khi kết nối D1." }
};

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (url.pathname.startsWith("/api/") && env.API_RATE_LIMITER) {
      const clientIp = request.headers.get("CF-Connecting-IP") || "unknown";
      const rate = await env.API_RATE_LIMITER.limit({ key: clientIp + ":" + url.pathname });
      if (!rate.success) return json({ ok: false, error: "rate_limited" }, 429, { "retry-after": "60" });
    }
    if (request.method === "GET" && (url.pathname === "/" || url.pathname === "/health")) {
      return json({ ok: true, service: "hendydichvumang-bot", status: "healthy", version: "2.0.0" });
    }
    if (url.pathname === "/telegram/webhook") {
      if (request.method !== "POST") return json({ ok: false, error: "method_not_allowed" }, 405);
      return handleWebhook(request, env);
    }
    if (url.pathname === "/api/telegram/auth") return handleAuth(request, env);
    if (url.pathname === "/api/payment/config") return handlePaymentConfig(request, env);
    if (url.pathname === "/api/dashboard") return handleDashboard(request, env);
    if (url.pathname === "/api/tickets") return handleTicket(request, env);
    return json({ ok: false, error: "not_found" }, 404);
  }
};

async function handleWebhook(request, env) {
  if (!env.BOT_TOKEN || !env.WEBHOOK_SECRET) return json({ ok: false, error: "server_not_configured" }, 503);
  const supplied = request.headers.get("X-Telegram-Bot-Api-Secret-Token") || "";
  if (!constantTimeEqual(supplied, env.WEBHOOK_SECRET)) return json({ ok: false, error: "unauthorized" }, 401);
  const contentLength = Number(request.headers.get("content-length") || 0);
  if (contentLength > MAX_BODY_BYTES) return json({ ok: false, error: "payload_too_large" }, 413);
  const raw = await request.text();
  if (new TextEncoder().encode(raw).byteLength > MAX_BODY_BYTES) return json({ ok: false, error: "payload_too_large" }, 413);
  let update;
  try { update = JSON.parse(raw); } catch { return json({ ok: false, error: "invalid_json" }, 400); }
  try {
    await dispatchUpdate(update, env);
    return json({ ok: true });
  } catch (error) {
    console.error("Telegram update error:", redact(error?.message || "unknown"));
    // Telegram should retry transient failures.
    return json({ ok: false, error: "update_failed" }, 500);
  }
}

async function dispatchUpdate(update, env) {
  if (update?.message) {
    const message = update.message;
    const chatId = message.chat?.id;
    if (!chatId) return;
    const text = typeof message.text === "string" ? message.text.trim() : "";
    const command = text.split(/\s+/, 1)[0].split("@")[0].toLowerCase();
    if (command === "/start" || command === "/menu") {
      await sendMessage(env, chatId, mainText(message.from?.first_name), mainKeyboard(env));
      return;
    }
    if (command === "/help") {
      await sendMessage(env, chatId, "ℹ️ <b>TRỢ GIÚP HENDY</b>\n/start hoặc /menu — mở menu dịch vụ\n/nap — thông tin nạp tiền\n/otp, /proxy, /vip, /code — nhóm dịch vụ\n/tool, /live — dịch vụ công cụ và Live\n/support — kênh hỗ trợ\n/help — xem hướng dẫn", mainKeyboard(env));
      return;
    }
    const commandRoutes = {
      "/nap": "deposit", "/deposit": "deposit", "/topup": "deposit",
      "/otp": "otp", "/proxy": "proxy", "/vip": "vip",
      "/code": "code", "/tool": "rental", "/live": "live",
      "/support": "support"
    };
    if (commandRoutes[command]) {
      const view = screenFor("service:" + commandRoutes[command], message.from?.first_name, env, message.from?.id);
      await sendMessage(env, chatId, view.text, view.keyboard);
      return;
    }
    if (text) await sendMessage(env, chatId, "Mình chưa nhận diện được yêu cầu. Bạn hãy chọn chức năng trong menu nhé.", mainKeyboard(env));
    return;
  }
  if (update?.callback_query) {
    const q = update.callback_query;
    await telegramCall(env, "answerCallbackQuery", { callback_query_id: q.id });
    const chatId = q.message?.chat?.id;
    const messageId = q.message?.message_id;
    if (!chatId || !messageId) return;
    const view = screenFor(q.data || "", q.from?.first_name, env, q.from?.id);
    await telegramCall(env, "editMessageText", {
      chat_id: chatId, message_id: messageId, text: view.text, parse_mode: "HTML",
      reply_markup: view.keyboard, disable_web_page_preview: true
    });
  }
}

function mainText(firstName) {
  return "👋 Xin chào <b>" + escapeHtml(firstName || "bạn") + "</b>!\n" +
    "💠 <b>HENDY DỊCH VỤ MẠNG</b>\n✨ Công nghệ kết nối giá trị thật\n\n" +
    "Chọn nhóm dịch vụ bạn muốn sử dụng:";
}
function mainKeyboard(env) {
  const rows = [
    [{ text: "🎮 MINIGAME LIVE", callback_data: "service:live" }, { text: "🧰 THUÊ TOOL", callback_data: "service:rental" }],
    [{ text: "🔐 OTP", callback_data: "service:otp" }, { text: "🌐 PROXY", callback_data: "service:proxy" }],
    [{ text: "👑 KHO VIP", callback_data: "service:vip" }, { text: "🎟️ MUA CODE", callback_data: "service:code" }],
    [{ text: "💳 NẠP TIỀN", callback_data: "service:deposit" }, { text: "💬 HỖ TRỢ", callback_data: "service:support" }]
  ];
  if (isHttps(env.MINI_APP_URL)) rows.push([{ text: "🚀 MỞ TRUNG TÂM KHÁCH HÀNG", web_app: { url: env.MINI_APP_URL } }]);
  return { inline_keyboard: rows };
}
function backKeyboard(env) {
  const rows = [];
  if (isHttps(env.MINI_APP_URL)) rows.push([{ text: "🚀 MỞ MINI APP", web_app: { url: env.MINI_APP_URL } }]);
  rows.push([{ text: "« MENU CHÍNH", callback_data: "menu:main" }]);
  return { inline_keyboard: rows };
}
function screenFor(action, name, env, telegramUserId) {
  if (action === "menu:main") return { text: mainText(name), keyboard: mainKeyboard(env) };
  const key = action.startsWith("service:") ? action.slice(8) : "";
  if (key === "deposit") return paymentScreen(env, telegramUserId);
  if (key === "support") return supportScreen(env);
  const service = SERVICES[key];
  if (service) return { text: "<b>" + service.title + "</b>\n\n" + escapeHtml(service.text), keyboard: backKeyboard(env) };
  if (action === "account:open") return { text: "👤 <b>TRUNG TÂM KHÁCH HÀNG</b>\nMở Mini App để xem hồ sơ, thống kê và gửi yêu cầu hỗ trợ.", keyboard: backKeyboard(env) };
  return { text: "Chức năng chưa được cấu hình. Vui lòng quay lại menu chính.", keyboard: backKeyboard(env) };
}

async function sendMessage(env, chatId, text, replyMarkup) {
  return telegramCall(env, "sendMessage", { chat_id: chatId, text, parse_mode: "HTML", reply_markup: replyMarkup, disable_web_page_preview: true });
}
async function telegramCall(env, method, payload) {
  if (!env.BOT_TOKEN) throw new Error("BOT_TOKEN missing");
  const response = await fetch(TELEGRAM_API + "/bot" + env.BOT_TOKEN + "/" + method, {
    method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify(payload),
    signal: AbortSignal.timeout(10000)
  });
  const data = await response.json().catch(() => null);
  if (!response.ok || !data?.ok) {
    const description = data?.description || "HTTP " + response.status;
    if (method === "editMessageText" && description.includes("message is not modified")) return data;
    throw new Error("Telegram " + method + ": " + description);
  }
  return data;
}

function corsHeaders(request, env) {
  const origin = request.headers.get("Origin") || "";
  const allowed = env.MINI_APP_ORIGIN || "";
  if (origin && allowed && origin === allowed) return {
    "Access-Control-Allow-Origin": allowed, "Access-Control-Allow-Methods": "POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type, Authorization", "Vary": "Origin"
  };
  return {};
}
async function handleAuth(request, env) {
  const cors = corsHeaders(request, env);
  if (request.method === "OPTIONS") return Object.keys(cors).length ? new Response(null, { status: 204, headers: cors }) : json({ ok: false, error: "origin_not_allowed" }, 403);
  if (request.method !== "POST") return json({ ok: false, error: "method_not_allowed" }, 405, cors);
  if (!env.BOT_TOKEN) return json({ ok: false, error: "server_not_configured" }, 503, cors);
  if (request.headers.get("Origin") && !Object.keys(cors).length) return json({ ok: false, error: "origin_not_allowed" }, 403);
  let body;
  try { body = await request.json(); } catch { return json({ ok: false, error: "invalid_json" }, 400, cors); }
  if (typeof body?.initData !== "string" || body.initData.length > 16384) return json({ ok: false, error: "invalid_init_data" }, 400, cors);
  const result = await validateInitData(body.initData, env.BOT_TOKEN);
  if (!result.ok) return json({ ok: false, error: result.error }, 401, cors);
  return json({ ok: true, user: result.user, authDate: result.authDate }, 200, cors);
}
function supportScreen(env) {
  const supportUrl = String(env.SUPPORT_URL || "").trim();
  const rows = [];
  if (isHttps(supportUrl)) rows.push([{ text: "💬 MỞ KÊNH CSKH", url: supportUrl }]);
  if (isHttps(env.MINI_APP_URL)) rows.push([{ text: "🚀 MỞ TRUNG TÂM KHÁCH HÀNG", web_app: { url: env.MINI_APP_URL } }]);
  rows.push([{ text: "« MENU CHÍNH", callback_data: "menu:main" }]);
  const text = isHttps(supportUrl)
    ? "💬 <b>TRUNG TÂM HỖ TRỢ</b>\n\nBấm nút bên dưới để liên hệ CSKH. Ticket trong Mini App chỉ được bật sau khi kết nối kho dữ liệu."
    : "💬 <b>TRUNG TÂM HỖ TRỢ</b>\n\nQuản trị viên chưa cấu hình SUPPORT_URL. Ticket sẽ khả dụng sau khi kết nối D1.";
  return { text, keyboard: { inline_keyboard: rows } };
}

function paymentDetails(env, telegramUserId) {
  const bankCode = String(env.PAYMENT_BANK_CODE || "").trim();
  const bankName = String(env.PAYMENT_BANK_NAME || bankCode).trim();
  const accountNumber = String(env.PAYMENT_ACCOUNT_NUMBER || "").trim();
  const accountName = String(env.PAYMENT_ACCOUNT_NAME || "").trim();
  const prefix = String(env.PAYMENT_TRANSFER_NOTE_PREFIX || "HENDY")
    .trim().replace(/[^A-Za-z0-9_-]/g, "").slice(0, 12) || "HENDY";
  const transferNote = prefix + (telegramUserId ? String(telegramUserId) : "");
  let qrUrl = "";
  if (isHttps(env.PAYMENT_QR_URL)) {
    qrUrl = env.PAYMENT_QR_URL;
  } else if (bankCode && accountNumber) {
    const params = new URLSearchParams({
      acc: accountNumber,
      bank: bankCode,
      des: transferNote
    });
    qrUrl = "https://vietqr.app/img?" + params.toString();
  }
  return {
    configured: Boolean(bankName && accountNumber && accountName),
    bankCode,
    bankName,
    accountNumber,
    accountName,
    transferNote,
    qrUrl
  };
}

function paymentScreen(env, telegramUserId) {
  const payment = paymentDetails(env, telegramUserId);
  if (!payment.configured) {
    return {
      text: "💳 <b>NẠP TIỀN</b>\n\nThông tin ngân hàng chưa được cấu hình. Vui lòng liên hệ CSKH. Không chuyển khoản theo thông tin không được bot xác nhận.",
      keyboard: backKeyboard(env)
    };
  }
  const lines = [
    "💳 <b>THÔNG TIN CHUYỂN KHOẢN</b>",
    "",
    "Ngân hàng: <b>" + escapeHtml(payment.bankName) + "</b>",
    "Số tài khoản: <code>" + escapeHtml(payment.accountNumber) + "</code>",
    "Chủ tài khoản: <b>" + escapeHtml(payment.accountName) + "</b>",
    "Nội dung chuyển khoản: <code>" + escapeHtml(payment.transferNote) + "</code>",
    "",
    "Vui lòng nhập đúng nội dung chuyển khoản. Hiện tại hệ thống mới hiển thị thông tin nhận tiền; đối soát và cộng số dư tự động chưa được bật."
  ];
  const rows = [];
  if (payment.qrUrl) rows.push([{ text: "📷 MỞ MÃ QR CHUYỂN KHOẢN", url: payment.qrUrl }]);
  if (isHttps(env.MINI_APP_URL)) rows.push([{ text: "🚀 MỞ TRUNG TÂM KHÁCH HÀNG", web_app: { url: env.MINI_APP_URL } }]);
  rows.push([{ text: "« MENU CHÍNH", callback_data: "menu:main" }]);
  return { text: lines.join("\n"), keyboard: { inline_keyboard: rows } };
}

async function handlePaymentConfig(request, env) {
  const cors = corsHeaders(request, env);
  if (request.method === "OPTIONS") return Object.keys(cors).length ? new Response(null, { status: 204, headers: cors }) : json({ ok: false, error: "origin_not_allowed" }, 403);
  if (request.method !== "POST") return json({ ok: false, error: "method_not_allowed" }, 405, cors);
  if (request.headers.get("Origin") && !Object.keys(cors).length) return json({ ok: false, error: "origin_not_allowed" }, 403);
  let body;
  try { body = await request.json(); } catch { return json({ ok: false, error: "invalid_json" }, 400, cors); }
  const auth = await validateInitData(body?.initData || "", env.BOT_TOKEN || "");
  if (!auth.ok) return json({ ok: false, error: auth.error }, 401, cors);
  const payment = paymentDetails(env, auth.user?.id);
  return json({ ok: true, ...payment }, 200, cors);
}

async function handleDashboard(request, env) {
  const cors = corsHeaders(request, env);
  if (request.method === "OPTIONS") return Object.keys(cors).length ? new Response(null, { status: 204, headers: cors }) : json({ ok: false, error: "origin_not_allowed" }, 403);
  if (request.method !== "POST") return json({ ok: false, error: "method_not_allowed" }, 405, cors);
  if (request.headers.get("Origin") && !Object.keys(cors).length) return json({ ok: false, error: "origin_not_allowed" }, 403);
  let body;
  try { body = await request.json(); } catch { return json({ ok: false, error: "invalid_json" }, 400, cors); }
  const auth = await validateInitData(body?.initData || "", env.BOT_TOKEN || "");
  if (!auth.ok) return json({ ok: false, error: auth.error }, 401, cors);
  return json({
    ok: true, profile: { id: auth.user?.id || null, name: [auth.user?.first_name, auth.user?.last_name].filter(Boolean).join(" ") || "Người dùng", username: auth.user?.username || null },
    stats: { orders: null, balance: null, tickets: null },
    notice: "Số liệu sẽ hiển thị sau khi kết nối cơ sở dữ liệu nghiệp vụ."
  }, 200, cors);
}
async function handleTicket(request, env) {
  const cors = corsHeaders(request, env);
  if (request.method === "OPTIONS") return Object.keys(cors).length ? new Response(null, { status: 204, headers: cors }) : json({ ok: false, error: "origin_not_allowed" }, 403);
  if (request.method !== "POST") return json({ ok: false, error: "method_not_allowed" }, 405, cors);
  if (request.headers.get("Origin") && !Object.keys(cors).length) return json({ ok: false, error: "origin_not_allowed" }, 403);
  let body;
  try { body = await request.json(); } catch { return json({ ok: false, error: "invalid_json" }, 400, cors); }
  const auth = await validateInitData(body?.initData || "", env.BOT_TOKEN || "");
  if (!auth.ok) return json({ ok: false, error: auth.error }, 401, cors);
  const subject = String(body?.subject || "").trim().slice(0, 100);
  const message = String(body?.message || "").trim().slice(0, 2000);
  if (subject.length < 3 || message.length < 5) return json({ ok: false, error: "invalid_ticket" }, 400, cors);
  // No database is configured in the requested architecture. Do not claim a ticket was stored.
  return json({ ok: false, error: "ticket_storage_not_configured", message: "Hệ thống chưa kết nối kho lưu ticket. Vui lòng liên hệ CSKH qua bot." }, 503, cors);
}

export async function validateInitData(raw, botToken, now = Math.floor(Date.now() / 1000)) {
  try {
    const params = new URLSearchParams(raw);
    const hashes = params.getAll("hash");
    if (hashes.length !== 1 || !/^[a-f0-9]{64}$/i.test(hashes[0])) return { ok: false, error: "invalid_hash" };
    const entries = [...params.entries()];
    const keys = entries.map(([key]) => key);
    if (new Set(keys).size !== keys.length) return { ok: false, error: "duplicate_fields" };
    const hash = hashes[0];
    const authDate = Number(params.get("auth_date"));
    if (!Number.isInteger(authDate) || authDate <= 0) return { ok: false, error: "expired_or_invalid_auth_date" };
    if (!Number.isInteger(authDate) || authDate <= 0 || authDate > now + 60 || now - authDate > MAX_INIT_DATA_AGE_SECONDS) return { ok: false, error: "expired_or_invalid_auth_date" };
    const check = entries.filter(([key]) => key !== "hash").map(([key, value]) => key + "=" + value).sort().join("\n");
    const encoder = new TextEncoder();
    // Telegram derivation: HMAC-SHA256(data=BOT_TOKEN, key="WebAppData").
    // Web Crypto takes the HMAC key first, then the data to sign.
    const webAppKey = await crypto.subtle.importKey("raw", encoder.encode("WebAppData"), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
    const secret = await crypto.subtle.sign("HMAC", webAppKey, encoder.encode(botToken));
    const key = await crypto.subtle.importKey("raw", secret, { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
    const digest = await crypto.subtle.sign("HMAC", key, encoder.encode(check));
    const actual = [...new Uint8Array(digest)].map((v) => v.toString(16).padStart(2, "0")).join("");
    if (!constantTimeEqual(actual, hash.toLowerCase())) return { ok: false, error: "invalid_signature" };
    const userText = params.get("user");
    let user = null;
    if (userText) user = JSON.parse(userText);
    if (!user || !Number.isSafeInteger(user.id) || user.id <= 0) return { ok: false, error: "invalid_user" };
    return { ok: true, user, authDate };
  } catch { return { ok: false, error: "invalid_init_data" }; }
}
function constantTimeEqual(a, b) {
  if (typeof a !== "string" || typeof b !== "string") return false;
  const x = new TextEncoder().encode(a), y = new TextEncoder().encode(b);
  let diff = x.length ^ y.length;
  for (let i = 0; i < Math.max(x.length, y.length); i++) diff |= (x[i] || 0) ^ (y[i] || 0);
  return diff === 0;
}
function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
}
function isHttps(value) { try { return new URL(value).protocol === "https:"; } catch { return false; } }
function redact(value) { return String(value).replace(/bot\d+:[A-Za-z0-9_-]+/g, "bot[REDACTED]").slice(0, 300); }
function json(data, status = 200, headers = {}) {
  return new Response(JSON.stringify(data), { status, headers: { "content-type": "application/json; charset=utf-8", "cache-control": "no-store", ...headers } });
}
export { mainKeyboard, screenFor, paymentDetails, paymentScreen, escapeHtml, constantTimeEqual };
