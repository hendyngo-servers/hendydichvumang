import { Bot, InlineKeyboard } from 'grammy';
import { buildMenuRows, defaultMenuConfig, normalizeMenuConfig, resolveMenuAction } from '../../src/menu-config.js';

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);
}

async function upsertTelegramUser(db, from) {
  const result = await db.query(
    'INSERT INTO users (telegram_id, username, first_name, last_name, language_code) ' +
    'VALUES ($1, $2, $3, $4, $5) ON CONFLICT (telegram_id) DO UPDATE SET ' +
    'username = EXCLUDED.username, first_name = EXCLUDED.first_name, ' +
    'last_name = EXCLUDED.last_name, language_code = EXCLUDED.language_code, ' +
    'updated_at = NOW() RETURNING id',
    [String(from.id), from.username || null, from.first_name || '', from.last_name || '', from.language_code || 'vi']
  );
  const userId = result.rows[0].id;
  await db.query('INSERT INTO wallets (user_id) VALUES ($1) ON CONFLICT (user_id) DO NOTHING', [userId]);
  return userId;
}

async function loadMenuConfig(db, botKey = 'grammy') {
  try {
    const { rows } = await db.query('SELECT * FROM bot_menu_configs WHERE bot_key = $1 LIMIT 1', [botKey]);
    return normalizeMenuConfig(rows[0] || defaultMenuConfig(botKey), botKey);
  } catch (error) {
    console.error('Không tải được cấu hình menu:', error?.code || error?.name || 'UNKNOWN');
    return defaultMenuConfig(botKey);
  }
}

const REPLY_BUTTON_STYLES = Object.freeze({
  minigame: 'danger',
  otp_proxy: 'primary',
  topup: 'danger',
  vip: 'primary',
  catalog: 'success',
  customer: 'primary',
  group: 'primary',
  channel: 'primary',
  upcoming: 'primary'
});

export function buildMainMenu(appUrl, isPrivate = true, isAdmin = false, menuConfig = defaultMenuConfig('grammy')) {
  const config = normalizeMenuConfig(menuConfig, 'grammy');
  const keyboard = buildMenuRows(config, isAdmin).map((row) => row.map((button) => ({
    text: button.text,
    ...(REPLY_BUTTON_STYLES[button.action] ? { style: REPLY_BUTTON_STYLES[button.action] } : {})
  })));
  if (isPrivate && typeof appUrl === 'string' && appUrl.startsWith('https://')) {
    keyboard.push([{ text: '🌐 MỞ MINI APP', web_app: { url: appUrl } }]);
  }
  return {
    keyboard,
    resize_keyboard: config.resizeKeyboard,
    is_persistent: config.persistentKeyboard,
    input_field_placeholder: 'Chọn chức năng HENDY…'
  };
}

function mainMenu(...args) {
  return buildMainMenu(...args);
}

export function buildCustomerCenterKeyboard({ appUrl = '', legacyCustomerUrl = '', isPrivate = true } = {}) {
  const keyboard = new InlineKeyboard();
  let hasButton = false;
  if (isPrivate && appUrl.startsWith('https://')) {
    keyboard.webApp('🚀 MINI APP BẢN MỚI | CHỈ MOBILE', appUrl);
    hasButton = true;
  }
  if (legacyCustomerUrl.startsWith('https://')) {
    if (hasButton) keyboard.row();
    keyboard.url('🔑 PHIÊN BẢN WEB CŨ', legacyCustomerUrl);
    hasButton = true;
  }
  if (isPrivate && hasButton) keyboard.row().text('↩️ TRỞ VỀ MENU CHÍNH', 'customer:home');
  return keyboard;
}

async function sendCustomerCenter(ctx, appUrl, legacyCustomerUrl, menuConfig) {
  if (ctx.chat?.type !== 'private') {
    await ctx.reply('🔐 Hãy mở chat riêng với bot để truy cập Trung tâm khách hàng.');
    return;
  }
  const lines = [
    '🚀 <b>TRUNG TÂM QUẢN LÝ TÀI KHOẢN</b>',
    '',
    'Chọn một trong hai cách truy cập bên dưới:',
    '• <b>Mini App bản mới:</b> giao diện tối ưu điện thoại, đăng nhập bằng tài khoản Telegram.',
    legacyCustomerUrl
      ? '• <b>Phiên bản Web cũ:</b> tiếp tục sử dụng trang web truyền thống.'
      : '• <b>Phiên bản Web cũ:</b> hiện chưa được cấu hình.',
    '',
    'Khuyến nghị sử dụng Mini App để có trải nghiệm liền mạch hơn.'
  ];
  const keyboard = buildCustomerCenterKeyboard({ appUrl, legacyCustomerUrl, isPrivate: true });
  await ctx.reply(lines.join('\n'), {
    parse_mode: 'HTML',
    reply_markup: keyboard.inline_keyboard.length ? keyboard : mainMenu(appUrl, true, false, menuConfig)
  });
}

export function createBot({ token, db, appUrl, legacyCustomerUrl = '', groupUrl = '', channelUrl = '' }) {
  if (!token) return null;
  const bot = new Bot(token);

  bot.command('start', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    const menuConfig = await loadMenuConfig(db, 'grammy');
    const name = escapeHtml(ctx.from.first_name || 'bạn');
    const intro = escapeHtml(menuConfig.introText);
    await ctx.reply(
      '💠 <b>HENDY DỊCH VỤ MẠNG</b>\n\nChào ' + name + '! Chào mừng bạn đến với hệ thống dịch vụ HENDY.\n\n' + intro,
      { parse_mode: 'HTML', reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private', false, menuConfig) }
    );
  });

  bot.command('customer', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl, await loadMenuConfig(db, 'grammy'));
  });
  bot.command('account', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl, await loadMenuConfig(db, 'grammy'));
  });

  bot.command('help', async (ctx) => {
    const menuConfig = await loadMenuConfig(db, 'grammy');
    await ctx.reply(
      'Các lệnh hiện có:\n/start – Mở menu\n/customer – Trung tâm khách hàng\n/services – Danh sách dịch vụ\n/balance – Kiểm tra số dư\n/support – Trung tâm hỗ trợ',
      { reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private', false, menuConfig) }
    );
  });

  bot.command('services', async (ctx) => sendServices(ctx, db));
  bot.command('balance', async (ctx) => sendBalance(ctx, db));
  bot.command('support', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl, await loadMenuConfig(db, 'grammy'));
  });

  bot.callbackQuery('customer:home', async (ctx) => {
    await ctx.answerCallbackQuery({ text: 'Đã mở menu chính.' });
    const menuConfig = await loadMenuConfig(db, 'grammy');
    await ctx.reply('💠 Menu chính HENDY. Chọn chức năng bạn cần:', {
      reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private', false, menuConfig)
    });
  });

  bot.on('message:text', async (ctx) => {
    const text = ctx.message.text.trim();
    const menuConfig = await loadMenuConfig(db, 'grammy');
    const menu = mainMenu(appUrl, ctx.chat?.type === 'private', false, menuConfig);
    const action = resolveMenuAction(text, menuConfig, false);
    if (action === 'customer' || action === 'support') {
      await upsertTelegramUser(db, ctx.from);
      await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl, menuConfig);
      return;
    }
    if (action === 'balance') return sendBalance(ctx, db);
    if (action === 'services' || action === 'catalog') return sendServices(ctx, db);
    if (action === 'group') return sendTelegramLink(ctx, 'Nhóm chat HENDY', groupUrl);
    if (action === 'channel') return sendTelegramLink(ctx, 'Kênh thông báo HENDY', channelUrl);
    if (['minigame', 'otp_proxy', 'topup', 'vip', 'upcoming'].includes(action)) {
      return sendFeatureNotice(ctx, action, menu);
    }
    await ctx.reply('Mình chưa nhận diện chức năng này. Hãy chọn nút trong menu hoặc gửi /help.', { reply_markup: menu });
  });

  bot.catch((error) => {
    console.error('Telegram update failed:', error.error?.error_code || error.error?.name || 'UNKNOWN');
  });
  return bot;
}

async function sendBalance(ctx, db) {
  const userId = await upsertTelegramUser(db, ctx.from);
  const result = await db.query('SELECT balance FROM wallets WHERE user_id = $1', [userId]);
  const balance = Number(result.rows[0]?.balance || 0).toLocaleString('vi-VN');
  await ctx.reply(
    '💳 Số dư hiện tại: <b>' + balance + ' đ</b>\n\nSố dư chỉ thay đổi sau khi giao dịch được hệ thống đối soát hợp lệ.',
    { parse_mode: 'HTML' }
  );
}

async function sendTelegramLink(ctx, title, url) {
  if (!url) {
    return ctx.reply('🔧 ' + title + ' chưa được cấu hình liên kết. Vui lòng liên hệ quản trị viên HENDY.');
  }
  const keyboard = new InlineKeyboard().url('MỞ ' + title.toLocaleUpperCase('vi'), url);
  return ctx.reply('🔗 ' + title + ':', { reply_markup: keyboard });
}

const FEATURE_NOTICES = Object.freeze({
  minigame: '🎮 MINIGAME LIVE đang được hoàn thiện. Chức năng sẽ chỉ mở sau khi hệ thống game, điểm thưởng và chống gian lận được kiểm thử.',
  otp_proxy: '🔐 OTP & PROXY đang được chuẩn bị. Chưa có dịch vụ nào được bán hoặc kích hoạt từ nút này.',
  topup: '💳 NẠP TIỀN chưa mở. Vui lòng không chuyển khoản vào tài khoản không được xác nhận; số dư chỉ tăng sau khi cổng thanh toán và đối soát hoàn tất.',
  vip: '🏆 TRUNG TÂM VIP đang được hoàn thiện. Quyền lợi và thời hạn VIP sẽ chỉ hiển thị khi backend đã sẵn sàng.',
  upcoming: '⏳ Các dịch vụ đang phát triển sẽ được công bố tại đây sau khi hoàn tất kiểm thử.'
});

async function sendFeatureNotice(ctx, action, menu) {
  const message = FEATURE_NOTICES[action] || FEATURE_NOTICES.upcoming;
  return ctx.reply(message, { reply_markup: menu });
}

async function sendServices(ctx, db) {
  const result = await db.query(
    'SELECT name, category, description, price FROM service_catalog WHERE is_active = TRUE ORDER BY sort_order, id LIMIT 15'
  );
  if (!result.rows.length) return ctx.reply('Hiện chưa có dịch vụ nào được quản trị viên công khai.');
  const lines = result.rows.map((service) =>
    '• <b>' + escapeHtml(service.name) + '</b> (' + escapeHtml(service.category) + ')\n  ' +
    escapeHtml(service.description) + '\n  Giá tham khảo: ' +
    Number(service.price).toLocaleString('vi-VN') + ' đ'
  );
  await ctx.reply(
    '🛍 <b>DANH SÁCH DỊCH VỤ</b>\n\n' + lines.join('\n\n') +
    '\n\nLiên hệ CSKH để xác nhận khả năng cung cấp.',
    { parse_mode: 'HTML' }
  );
}
