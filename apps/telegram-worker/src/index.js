const TELEGRAM_API = "https://api.telegram.org";
const MAX_BODY_BYTES = 1024 * 1024;
const MAX_INIT_DATA_AGE_SECONDS = 24 * 60 * 60;

const MAIN_TEXT = (name) =>
  `👋 Xin chào <b>${escapeHtml(name || "Bạn")}</b>!\n` +
  `💠 <b>HENDY DỊCH VỤ MẠNG</b>\n` +
  `✨ Công nghệ kết nối giá trị thật\n\n` +
  `Vui lòng chọn dịch vụ bên dưới:`;

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (request.method === "GET" && (url.pathname === "/" || url.pathname === "/health")) {
      return json({ ok: true, service: "hendydichvumang-bot", status: "healthy" });
    }
    if (url.pathname === "/api/telegram/auth") return handleMiniAppAuth(request, env);
    if (url.pathname === "/telegram/webhook") {
      if (request.method !== "POST") return json({ ok: false, error: "method_not_allowed" }, 405);
      return handleWebhook(request, env);
    }
    return json({ ok: false, error: "not_found" }, 404);
  },
};

async function handleWebhook(request, env) {
  if (!env.BOT_TOKEN || !env.WEBHOOK_SECRET) {
    console.error("Webhook chưa được cấu hình đầy đủ secrets.");
    return json({ ok: false, error: "server_not_configured" }, 503);
  }
  const suppliedSecret = request.headers.get("X-Telegram-Bot-Api-Secret-Token") || "";
  if (!constantTimeEqual(suppliedSecret, env.WEBHOOK_SECRET)) return json({ ok: false, error: "unauthorized" }, 401);
  const contentLength = Number(request.headers.get("content-length") || 0);
  if (contentLength > MAX_BODY_BYTES) return json({ ok: false, error: "payload_too_large" }, 413);
  let update;
  try {
    const raw = await request.text();
    if (new TextEncoder().encode(raw).byteLength > MAX_BODY_BYTES) return json({ ok: false, error: "payload_too_large" }, 413);
    update = JSON.parse(raw);
  } catch {
    return json({ ok: false, error: "invalid_json" }, 400);
  }
  try {
    await handleTelegramUpdate(update, env);
    return json({ ok: true });
  } catch (error) {
    console.error("Không xử lý được Telegram update:", safeErrorMessage(error));
    return json({ ok: true, warning: "update_processing_error" });
  }
}

async function handleTelegramUpdate(update, env) {
  if (!update || typeof update !== "object") return;
  if (update.message) {
    const message = update.message;
    const chatId = message.chat?.id;
    const text = typeof message.text === "string" ? message.text.trim() : "";
    if (!chatId || !text) return;
    const command = text.split(/\s+/, 1)[0].split("@")[0].toLowerCase();
    const name = message.from?.first_name || "Bạn";
    if (command === "/start" || command === "/menu") {
      await telegramRequest(env, "sendMessage", { chat_id: chatId, text: MAIN_TEXT(name), parse_mode: "HTML", reply_markup: getMainMenuKeyboard(), disable_web_page_preview: true });
      return;
    }
    if (command === "/help") {
      await telegramRequest(env, "sendMessage", { chat_id: chatId, text: "ℹ️ <b>TRỢ GIÚP HENDY</b>\n/start hoặc /menu — mở menu chính\n/help — xem hướng dẫn", parse_mode: "HTML", reply_markup: getMainMenuKeyboard() });
      return;
    }
    await telegramRequest(env, "sendMessage", { chat_id: chatId, text: "Mình chưa hiểu nội dung này. Gõ /menu để mở danh sách dịch vụ nhé.", reply_markup: getMainMenuKeyboard() });
    return;
  }
  if (update.callback_query) {
    const query = update.callback_query;
    const callbackId = query.id;
    const action = typeof query.data === "string" ? query.data : "";
    const chatId = query.message?.chat?.id;
    const messageId = query.message?.message_id;
    if (callbackId) await telegramRequest(env, "answerCallbackQuery", { callback_query_id: callbackId });
    if (!chatId || !messageId) return;
    const screen = getScreen(action, query.from?.first_name || "Bạn", env);
    await telegramRequest(env, "editMessageText", { chat_id: chatId, message_id: messageId, text: screen.text, parse_mode: "HTML", reply_markup: screen.keyboard, disable_web_page_preview: true });
  }
}

function getMainMenuKeyboard() {
  return { inline_keyboard: [
    [{ text: "🎮 MINIGAME LIVE", callback_data: "menu:minigame" }, { text: "🧰 THUÊ TOOL", callback_data: "menu:rental" }],
    [{ text: "🛒 MUA HÀNG", callback_data: "menu:shop" }, { text: "💳 NẠP TIỀN", callback_data: "menu:deposit" }],
    [{ text: "💎 KHO CÔNG CỤ ĐẶC QUYỀN", callback_data: "menu:tools" }],
    [{ text: "👑 TRUNG TÂM QUẢN LÝ TÀI KHOẢN", callback_data: "menu:account" }],
    [{ text: "ℹ️ GIỚI THIỆU & HỖ TRỢ", callback_data: "menu:support" }],
  ] };
}
function backKeyboard() { return { inline_keyboard: [[{ text: "« MENU CHÍNH", callback_data: "menu:main" }]] }; }

function getScreen(action, name, env) {
  const screens = {
    "menu:main": { text: MAIN_TEXT(name), keyboard: getMainMenuKeyboard() },
    "menu:minigame": { text: "🎮 <b>MINIGAME LIVE</b>\n\nChọn dịch vụ bạn muốn tìm hiểu:", keyboard: { inline_keyboard: [
      [{ text: "🎲 RR88 MINI", callback_data: "game:rr88" }],
      [{ text: "📦 KHO TÀI KHOẢN GAME", callback_data: "game:accounts" }],
      [{ text: "« MENU CHÍNH", callback_data: "menu:main" }],
    ] } },
    "menu:shop": { text: "🛒 <b>MUA HÀNG & DỊCH VỤ</b>\n\nVui lòng chọn nhóm sản phẩm:", keyboard: { inline_keyboard: [
      [{ text: "📡 PROXY TĨNH", callback_data: "shop:static_proxy" }, { text: "🔄 PROXY XOAY", callback_data: "shop:rotating_proxy" }],
      [{ text: "« MENU CHÍNH", callback_data: "menu:main" }],
    ] } },
    "menu:tools": { text: "💎 <b>KHO CÔNG CỤ ĐẶC QUYỀN</b>\n\nChọn công cụ cần tìm hiểu. Các công cụ chưa kết nối dịch vụ sẽ hiển thị trạng thái chưa khả dụng.", keyboard: { inline_keyboard: [
      [{ text: "🔎 KIỂM TRA ĐIỀU KIỆN TÀI KHOẢN", callback_data: "tool:account_check" }],
      [{ text: "🎁 KIỂM TRA ƯU ĐÃI HỢP LỆ", callback_data: "tool:promo_check" }],
      [{ text: "🔗 LẤY LIÊN KẾT VIDEO", callback_data: "tool:video_link" }],
      [{ text: "🎮 KIỂM TRA TÀI KHOẢN GAME", callback_data: "tool:game_check" }],
      [{ text: "⌨️ NHẬP CODE", callback_data: "tool:redeem_code" }],
      [{ text: "« MENU CHÍNH", callback_data: "menu:main" }],
    ] } },
    "menu:account": { text: "👑 <b>TRUNG TÂM QUẢN LÝ TÀI KHOẢN</b>\n\nChọn cách truy cập:", keyboard: accountKeyboard(env) },
    "menu:deposit": { text: "💳 <b>NẠP TIỀN</b>\n\nCổng thanh toán chưa được cấu hình. Vui lòng liên hệ CSKH để được hướng dẫn. Không chuyển tiền theo thông tin chưa được xác nhận trong bot.", keyboard: backKeyboard() },
    "menu:rental": { text: "🧰 <b>THUÊ TOOL</b>\n\nDanh mục thuê công cụ đang chờ kết nối hệ thống sản phẩm. Vui lòng liên hệ CSKH để kiểm tra dịch vụ hiện có.", keyboard: backKeyboard() },
    "menu:support": { text: "💠 <b>HENDY DỊCH VỤ MẠNG</b>\n\n✨ Công nghệ kết nối giá trị thật\n\nĐể được hỗ trợ đơn hàng hoặc tài khoản, vui lòng liên hệ quản trị viên của bot.", keyboard: backKeyboard() },
  };
  if (screens[action]) return screens[action];
  const unavailable = {
    "game:rr88": "Dịch vụ Minigame Live chưa được kết nối hệ thống vận hành.",
    "game:accounts": "Kho tài khoản game chưa được kết nối cơ sở dữ liệu sản phẩm.",
    "shop:static_proxy": "Sản phẩm Proxy tĩnh chưa có bảng giá và API đơn hàng.",
    "shop:rotating_proxy": "Sản phẩm Proxy xoay chưa có bảng giá và API đơn hàng.",
    "tool:account_check": "Công cụ kiểm tra tài khoản chưa được kết nối API chính thức.",
    "tool:promo_check": "Chức năng kiểm tra ưu đãi chưa được kết nối nguồn dữ liệu hợp lệ.",
    "tool:video_link": "Chức năng xử lý liên kết video chưa được cấu hình.",
    "tool:game_check": "Công cụ kiểm tra tài khoản game chưa được tích hợp.",
    "tool:redeem_code": "Hệ thống nhập và xác thực mã code chưa được tích hợp.",
    "account:web_legacy": "Cổng đăng nhập web cũ chưa được cấu hình. Vui lòng liên hệ CSKH.",
    "account:miniapp_missing": "Mini App chưa được cấu hình URL HTTPS. Quản trị viên cần cập nhật MINI_APP_URL.",
  };
  return { text: `⚙️ <b>CHƯA KHẢ DỤNG</b>\n\n${escapeHtml(unavailable[action] || "Chức năng này chưa được cấu hình.")}`, keyboard: backKeyboard() };
}

function accountKeyboard(env) {
  const miniAppUrl = typeof env.MINI_APP_URL === "string" ? env.MINI_APP_URL : "";
  if (isHttpsUrl(miniAppUrl)) return { inline_keyboard: [
    [{ text: "🚀 MỞ TELEGRAM MINI APP", web_app: { url: miniAppUrl } }],
    [{ text: "🔑 PHIÊN BẢN WEB CŨ", callback_data: "account:web_legacy" }],
    [{ text: "« MENU CHÍNH", callback_data: "menu:main" }],
  ] };
  return { inline_keyboard: [
    [{ text: "🚀 MINI APP CHƯA CẤU HÌNH", callback_data: "account:miniapp_missing" }],
    [{ text: "🔑 PHIÊN BẢN WEB CŨ", callback_data: "account:web_legacy" }],
    [{ text: "« MENU CHÍNH", callback_data: "menu:main" }],
  ] };
}

async function telegramRequest(env, method, payload) {
  if (!env.BOT_TOKEN) throw new Error("BOT_TOKEN chưa được cấu hình.");
  const response = await fetch(`${TELEGRAM_API}/bot${env.BOT_TOKEN}/${method}`, { method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify(payload), signal: AbortSignal.timeout(10000) });
  let result;
  try { result = await response.json(); } catch { throw new Error(`Telegram ${method}: phản hồi không hợp lệ (HTTP ${response.status}).`); }
  if (!response.ok || !result.ok) {
    const description = typeof result.description === "string" ? result.description : `HTTP ${response.status}`;
    if (method === "editMessageText" && description.includes("message is not modified")) return result;
    throw new Error(`Telegram ${method}: ${description}`);
  }
  return result;
}

async function handleMiniAppAuth(request, env) {
  const origin = request.headers.get("Origin") || "";
  const allowedOrigin = env.MINI_APP_ORIGIN || "";
  const corsHeaders = origin && allowedOrigin && origin === allowedOrigin ? {
    "Access-Control-Allow-Origin": allowedOrigin, "Access-Control-Allow-Methods": "POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type", "Vary": "Origin",
  } : {};
  if (request.method === "OPTIONS") {
    if (!Object.keys(corsHeaders).length) return json({ ok: false, error: "origin_not_allowed" }, 403);
    return new Response(null, { status: 204, headers: corsHeaders });
  }
  if (request.method !== "POST") return json({ ok: false, error: "method_not_allowed" }, 405, corsHeaders);
  if (origin && origin !== allowedOrigin) return json({ ok: false, error: "origin_not_allowed" }, 403);
  if (!env.BOT_TOKEN) return json({ ok: false, error: "server_not_configured" }, 503, corsHeaders);
  let body;
  try { body = await request.json(); } catch { return json({ ok: false, error: "invalid_json" }, 400, corsHeaders); }
  if (typeof body?.initData !== "string" || body.initData.length < 10 || body.initData.length > 16384) return json({ ok: false, error: "invalid_init_data" }, 400, corsHeaders);
  const validation = await validateWebAppInitData(body.initData, env.BOT_TOKEN);
  if (!validation.ok) return json({ ok: false, error: validation.error }, 401, corsHeaders);
  return json({ ok: true, user: validation.user, authDate: validation.authDate }, 200, corsHeaders);
}

export async function validateWebAppInitData(rawInitData, botToken, nowSeconds = Math.floor(Date.now() / 1000)) {
  try {
    const params = new URLSearchParams(rawInitData);
    const receivedHash = params.get("hash");
    const authDate = Number(params.get("auth_date"));
    if (!receivedHash || !/^[a-f0-9]{64}$/i.test(receivedHash)) return { ok: false, error: "missing_or_invalid_hash" };
    if (!Number.isInteger(authDate) || authDate <= 0 || authDate > nowSeconds + 60) return { ok: false, error: "invalid_auth_date" };
    if (nowSeconds - authDate > MAX_INIT_DATA_AGE_SECONDS) return { ok: false, error: "expired_init_data" };
    const dataCheckString = [...params.entries()].filter(([key]) => key !== "hash").map(([key, value]) => `${key}=${value}`).sort((a, b) => a.localeCompare(b)).join("\n");
    const encoder = new TextEncoder();
    const webAppKey = await crypto.subtle.importKey("raw", encoder.encode("WebAppData"), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
    const secretBytes = await crypto.subtle.sign("HMAC", webAppKey, encoder.encode(botToken));
    const derivedKey = await crypto.subtle.importKey("raw", secretBytes, { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
    const digest = await crypto.subtle.sign("HMAC", derivedKey, encoder.encode(dataCheckString));
    const computedHash = [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
    if (!constantTimeEqual(computedHash.toLowerCase(), receivedHash.toLowerCase())) return { ok: false, error: "invalid_signature" };
    let user = null;
    const userValue = params.get("user");
    if (userValue) {
      try { user = JSON.parse(userValue); } catch { return { ok: false, error: "invalid_user_data" }; }
    }
    return { ok: true, authDate, user };
  } catch { return { ok: false, error: "invalid_init_data" }; }
}

function constantTimeEqual(a, b) {
  if (typeof a !== "string" || typeof b !== "string") return false;
  const encoder = new TextEncoder();
  const left = encoder.encode(a);
  const right = encoder.encode(b);
  let difference = left.length ^ right.length;
  const length = Math.max(left.length, right.length);
  for (let i = 0; i < length; i += 1) difference |= (left[i] || 0) ^ (right[i] || 0);
  return difference === 0;
}
function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
}
function isHttpsUrl(value) { try { return new URL(value).protocol === "https:"; } catch { return false; } }
function safeErrorMessage(error) {
  const message = error instanceof Error ? error.message : "Lỗi không xác định";
  return message.replace(/bot\d+:[A-Za-z0-9_-]+/g, "bot[REDACTED]").slice(0, 300);
}
function json(data, status = 200, headers = {}) {
  return new Response(JSON.stringify(data), { status, headers: { "content-type": "application/json; charset=utf-8", "cache-control": "no-store", ...headers } });
}
export { getMainMenuKeyboard, getScreen, escapeHtml, constantTimeEqual };
