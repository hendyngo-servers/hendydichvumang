import { Bot, Keyboard, InlineKeyboard } from 'grammy';

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);
}

async function upsertTelegramUser(db, from) {
  const result = await db.query(
    `INSERT INTO users (telegram_id, username, first_name, last_name, language_code)
     VALUES ($1, $2, $3, $4, $5)
     ON CONFLICT (telegram_id) DO UPDATE SET
       username = EXCLUDED.username,
       first_name = EXCLUDED.first_name,
       last_name = EXCLUDED.last_name,
       language_code = EXCLUDED.language_code,
       updated_at = NOW()
     RETURNING id`,
    [String(from.id), from.username || null, from.first_name || '', from.last_name || '', from.language_code || 'vi']
  );
  const userId = result.rows[0].id;
  await db.query('INSERT INTO wallets (user_id) VALUES ($1) ON CONFLICT (user_id) DO NOTHING', [userId]);
  return userId;
}

function mainMenu(appUrl, isPrivate = true) {
  const keyboard = new Keyboard()
    .text('👤 Tài khoản').text('💳 Số dư').row()
    .text('🛍 Dịch vụ').text('🎫 Hỗ trợ').row();
  if (isPrivate && appUrl.startsWith('https://')) keyboard.webApp('🌐 Mở Mini App', appUrl);
  return keyboard.resized().persistent();
}

export function createBot({ token, db, appUrl }) {
  if (!token) return null;
  const bot = new Bot(token);

  bot.command('start', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    const name = escapeHtml(ctx.from.first_name || 'bạn');
    await ctx.reply(
      '💠 <b>HENDY DỊCH VỤ MẠNG</b>\n\nChào ' + name + '! Chào mừng bạn đến với hệ thống dịch vụ HENDY.\n\nChọn chức năng bên dưới để tiếp tục.',
      { parse_mode: 'HTML', reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private') }
    );
  });

  bot.command('help', async (ctx) => {
    await ctx.reply(
      'Các lệnh hiện có:\n/start – Mở menu\n/services – Danh sách dịch vụ\n/balance – Kiểm tra số dư\n/support – Trung tâm hỗ trợ',
      { reply_markup: mainMenu(appUrl, ctx.chat?.type === 'private') }
    );
  });

  bot.command('services', async (ctx) => sendServices(ctx, db));
  bot.command('balance', async (ctx) => sendBalance(ctx, db));
  bot.command('support', async (ctx) => {
    await upsertTelegramUser(db, ctx.from);
    const keyboard = new InlineKeyboard();
    if (appUrl.startsWith('https://')) keyboard.webApp('Mở Trung tâm khách hàng', appUrl);
    await ctx.reply('🎫 Vui lòng mở Trung tâm khách hàng để tạo yêu cầu và theo dõi phản hồi từ CSKH.', {
      reply_markup: keyboard.inline_keyboard.length ? keyboard : mainMenu(appUrl, ctx.chat?.type === 'private')
    });
  });

  bot.on('message:text', async (ctx) => {
    const text = ctx.message.text.trim();
    const menu = mainMenu(appUrl, ctx.chat?.type === 'private');

    if (text === '👤 Tài khoản') {
      const userId = await upsertTelegramUser(db, ctx.from);
      const { rows } = await db.query(
        'SELECT telegram_id, username, first_name, created_at FROM users WHERE id = $1',
        [userId]
      );
      const user = rows[0];
      await ctx.reply(
        '👤 <b>TÀI KHOẢN</b>\nID: <code>' + escapeHtml(user.telegram_id) + '</code>\nTên: ' + escapeHtml(user.first_name) +
        '\nUsername: ' + (user.username ? '@' + escapeHtml(user.username) : 'Chưa đặt') +
        '\nTham gia: ' + new Date(user.created_at).toLocaleDateString('vi-VN'),
        { parse_mode: 'HTML', reply_markup: menu }
      );
      return;
    }
    if (text === '💳 Số dư') return sendBalance(ctx, db);
    if (text === '🛍 Dịch vụ') return sendServices(ctx, db);
    if (text === '🎫 Hỗ trợ') {
      const keyboard = new InlineKeyboard();
      if (appUrl.startsWith('https://')) keyboard.webApp('Mở Trung tâm khách hàng', appUrl);
      await ctx.reply('🎫 Mở Trung tâm khách hàng để tạo ticket và theo dõi phản hồi.', {
        reply_markup: keyboard.inline_keyboard.length ? keyboard : menu
      });
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
  const { rows } = await db.query('SELECT balance FROM wallets WHERE user_id = $1', [userId]);
  const balance = Number(rows[0]?.balance || 0).toLocaleString('vi-VN');
  await ctx.reply(
    '💳 Số dư hiện tại: <b>' + balance + ' đ</b>\n\nSố dư chỉ thay đổi sau khi giao dịch được hệ thống đối soát hợp lệ.',
    { parse_mode: 'HTML' }
  );
}

async function sendServices(ctx, db) {
  const { rows } = await db.query(
    'SELECT name, category, description, price FROM service_catalog WHERE is_active = TRUE ORDER BY sort_order, id LIMIT 15'
  );
  if (!rows.length) return ctx.reply('Hiện chưa có dịch vụ nào được quản trị viên công khai.');
  const lines = rows.map((service) =>
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
