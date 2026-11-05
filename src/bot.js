import { Bot, Keyboard, InlineKeyboard } from 'grammy';

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

function mainMenu(appUrl, isPrivate = true) {
  const keyboard = new Keyboard()
    .text('👤 Trung tâm khách hàng').row()
    .text('💳 Số dư').text('🛍 Dịch vụ').row()
    .text('🎫 Hỗ trợ');
  if (isPrivate && appUrl.startsWith('https://')) {
    keyboard.row().webApp('🌐 Mở Mini App', appUrl);
  }
  return keyboard.resized().persistent();
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

async function sendCustomerCenter(ctx, appUrl, legacyCustomerUrl) {
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
    reply_markup: keyboard.inline_keyboard.length ? keyboard : mainMenu(appUrl, true)
  });
}

export function createBot({ token, db, appUrl, legacyCustomerUrl = '' }) {
  if (!token) return null;
  const bot = new Bot(token);

  bot.command('start', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    const name = escapeHtml(ctx.from.first_name || 'bạn');
    await ctx.reply(
      '💠 <b>HENDY DỊCH VỤ MẠNG</b>\n\nChào ' + name + '! Chào mừng bạn đến với hệ thống dịch vụ HENDY.\n\nMở Trung tâm khách hàng để quản lý tài khoản, số dư, dịch vụ và yêu cầu hỗ trợ.',
      { parse_mode: 'HTML', reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private') }
    );
  });

  bot.command('customer', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl);
  });
  bot.command('account', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl);
  });

  bot.command('help', async (ctx) => {
    await ctx.reply(
      'Các lệnh hiện có:\n/start – Mở menu\n/customer – Trung tâm khách hàng\n/services – Danh sách dịch vụ\n/balance – Kiểm tra số dư\n/support – Trung tâm hỗ trợ',
      { reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private') }
    );
  });

  bot.command('services', async (ctx) => sendServices(ctx, db));
  bot.command('balance', async (ctx) => sendBalance(ctx, db));
  bot.command('support', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl);
  });

  bot.callbackQuery('customer:home', async (ctx) => {
    await ctx.answerCallbackQuery({ text: 'Đã mở menu chính.' });
    await ctx.reply('💠 Menu chính HENDY. Chọn chức năng bạn cần:', {
      reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private')
    });
  });

  bot.on('message:text', async (ctx) => {
    const text = ctx.message.text.trim();
    const menu = mainMenu(appUrl, ctx.chat?.type === 'private');
    if (text === '👤 Trung tâm khách hàng' || text === '👤 Tài khoản') {
      await upsertTelegramUser(db, ctx.from);
      await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl);
      return;
    }
    if (text === '💳 Số dư') return sendBalance(ctx, db);
    if (text === '🛍 Dịch vụ') return sendServices(ctx, db);
    if (text === '🎫 Hỗ trợ') {
      await upsertTelegramUser(db, ctx.from);
      await sendCustomerCenter(ctx, appUrl, legacyCustomerUrl);
      return;
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
