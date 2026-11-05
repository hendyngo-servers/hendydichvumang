import 'dotenv/config';
import express from 'express';
import helmet from 'helmet';
import cors from 'cors';
import rateLimit from 'express-rate-limit';
import crypto from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadConfig } from './config.js';
import { createPool, migrate, closePool } from './db.js';
import { createBot } from './bot.js';
import { createSessionToken, hashSessionToken, safeEqual, verifyTelegramInitData, verifyWebhookSecret } from './security.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const config = loadConfig();
const db = createPool(config.databaseUrl);
const app = express();
const bot = createBot({ token: config.botToken, db, appUrl: config.publicAppUrl });

app.disable('x-powered-by');
app.set('trust proxy', 1);
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", 'https://telegram.org'],
      styleSrc: ["'self'"],
      imgSrc: ["'self'", 'data:', 'https:'],
      connectSrc: ["'self'"],
      objectSrc: ["'none'"],
      ...(config.isProduction ? { upgradeInsecureRequests: [] } : {})
    }
  }
}));
app.use(cors({
  origin(origin, callback) {
    if (!origin || !config.allowedOrigins.length || config.allowedOrigins.includes(origin)) return callback(null, true);
    return callback(new Error('Origin không được phép'));
  },
  methods: ['GET', 'POST', 'PATCH', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization'],
  maxAge: 600
}));
app.use(express.json({ limit: '32kb' }));
app.use('/api', rateLimit({ windowMs: 60_000, limit: 90, standardHeaders: 'draft-8', legacyHeaders: false }));
app.use(express.static(path.resolve(__dirname, '../public'), { extensions: ['html'], maxAge: config.isProduction ? '1h' : 0 }));

app.get('/healthz', async (_req, res) => {
  try {
    await db.query('SELECT 1');
    res.status(200).json({ status: 'ok', database: 'ready', bot: bot ? 'configured' : 'not_configured', timestamp: new Date().toISOString() });
  } catch {
    res.status(503).json({ status: 'error', database: 'unavailable' });
  }
});

app.get('/api/config', (_req, res) => {
  res.json({ brand: 'HENDY DỊCH VỤ MẠNG', appUrl: config.publicAppUrl, language: 'vi', telegramBotConfigured: Boolean(bot) });
});

app.post('/telegram/webhook', async (req, res) => {
  if (!bot) return res.status(503).json({ error: 'Telegram Bot chưa được cấu hình.' });
  const received = req.get('x-telegram-bot-api-secret-token');
  if (!verifyWebhookSecret(config.webhookSecret, received)) return res.status(401).json({ error: 'Webhook không hợp lệ.' });
  try {
    await bot.handleUpdate(req.body);
    res.sendStatus(200);
  } catch (error) {
    console.error('Webhook xử lý thất bại:', error?.error_code || error?.name || 'UNKNOWN');
    res.sendStatus(500);
  }
});

app.post('/api/telegram/auth', async (req, res) => {
  if (!config.botToken) return res.status(503).json({ error: 'Telegram Bot chưa được cấu hình.' });
  const verification = verifyTelegramInitData(req.body?.initData, config.botToken, config.initDataMaxAgeSeconds);
  if (!verification.ok) return res.status(401).json({ error: verification.reason === 'expired' ? 'Phiên Telegram đã hết hạn. Vui lòng mở lại Mini App.' : 'Không thể xác thực tài khoản Telegram.' });
  const client = await db.connect();
  try {
    await client.query('BEGIN');
    const { user } = verification;
    const userResult = await client.query(
      `INSERT INTO users (telegram_id, username, first_name, last_name, language_code)
       VALUES ($1, $2, $3, $4, $5)
       ON CONFLICT (telegram_id) DO UPDATE SET username = EXCLUDED.username,
         first_name = EXCLUDED.first_name, last_name = EXCLUDED.last_name,
         language_code = EXCLUDED.language_code, updated_at = NOW()
       RETURNING id`,
      [String(user.id), user.username || null, user.first_name || '', user.last_name || '', user.language_code || 'vi']
    );
    const userId = userResult.rows[0].id;
    await client.query('INSERT INTO wallets (user_id) VALUES ($1) ON CONFLICT (user_id) DO NOTHING', [userId]);
    const token = createSessionToken();
    await client.query('INSERT INTO auth_sessions (token_hash, user_id, role, expires_at) VALUES ($1, $2, $3, NOW() + INTERVAL \'7 days\')', [hashSessionToken(token), userId, 'user']);
    await client.query('COMMIT');
    res.json({ token, expiresIn: 604800 });
  } catch (error) {
    await client.query('ROLLBACK');
    throw error;
  } finally {
    client.release();
  }
});

async function requireAuth(req, res, next) {
  const match = /^Bearer\s+([A-Za-z0-9_-]{32,})$/i.exec(req.get('authorization') || '');
  if (!match) return res.status(401).json({ error: 'Vui lòng đăng nhập.' });
  try {
    const { rows } = await db.query(
      `SELECT s.user_id, s.role, u.telegram_id, u.username, u.first_name, u.last_name
       FROM auth_sessions s LEFT JOIN users u ON u.id = s.user_id
       WHERE s.token_hash = $1 AND s.expires_at > NOW() LIMIT 1`,
      [hashSessionToken(match[1])]
    );
    if (!rows[0]) return res.status(401).json({ error: 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.' });
    req.auth = rows[0];
    req.sessionToken = match[1];
    next();
  } catch (error) { next(error); }
}

function requireAdmin(req, res, next) {
  if (req.auth?.role !== 'admin') return res.status(403).json({ error: 'Bạn không có quyền thực hiện thao tác này.' });
  next();
}

app.post('/api/admin/login', rateLimit({ windowMs: 15 * 60_000, limit: 8, standardHeaders: 'draft-8', legacyHeaders: false }), async (req, res) => {
  if (!config.adminPassword) return res.status(503).json({ error: 'Chưa cấu hình ADMIN_PASSWORD trên Render.' });
  const username = String(req.body?.username || '');
  const password = String(req.body?.password || '');
  if (!safeEqual(username, config.adminUsername) || !safeEqual(password, config.adminPassword)) {
    return res.status(401).json({ error: 'Tên đăng nhập hoặc mật khẩu không chính xác.' });
  }
  const token = createSessionToken();
  await db.query('INSERT INTO auth_sessions (token_hash, role, expires_at) VALUES ($1, \'admin\', NOW() + INTERVAL \'8 hours\')', [hashSessionToken(token)]);
  await db.query('INSERT INTO audit_logs (actor, action, entity, metadata) VALUES ($1, $2, $3, $4)', [username, 'admin.login', 'session', JSON.stringify({ ip: req.ip })]);
  res.json({ token, expiresIn: 28800 });
});

app.post('/api/logout', requireAuth, async (req, res) => {
  await db.query('DELETE FROM auth_sessions WHERE token_hash = $1', [hashSessionToken(req.sessionToken)]);
  res.json({ ok: true });
});

app.get('/api/me', requireAuth, async (req, res) => {
  if (req.auth.role !== 'user') return res.status(403).json({ error: 'Tài khoản khách hàng không hợp lệ.' });
  const { rows } = await db.query(
    `SELECT u.telegram_id, u.username, u.first_name, u.last_name, u.created_at,
      COALESCE(w.balance, 0)::text AS balance,
      (SELECT COUNT(*) FROM tickets t WHERE t.user_id = u.id)::int AS ticket_count
     FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = $1`, [req.auth.user_id]
  );
  res.json({ user: rows[0] });
});

app.get('/api/dashboard', requireAuth, async (req, res) => {
  if (req.auth.role !== 'user') return res.status(403).json({ error: 'Không có quyền truy cập.' });
  const [wallet, tickets, services] = await Promise.all([
    db.query('SELECT COALESCE(balance, 0)::text AS balance FROM wallets WHERE user_id = $1', [req.auth.user_id]),
    db.query('SELECT id, subject, status, created_at FROM tickets WHERE user_id = $1 ORDER BY created_at DESC LIMIT 5', [req.auth.user_id]),
    db.query('SELECT COUNT(*)::int AS total FROM service_catalog WHERE is_active = TRUE')
  ]);
  res.json({ balance: wallet.rows[0]?.balance || '0.00', recentTickets: tickets.rows, activeServices: services.rows[0].total });
});

app.get('/api/services', async (_req, res) => {
  const { rows } = await db.query('SELECT id, code, name, category, description, price::text AS price FROM service_catalog WHERE is_active = TRUE ORDER BY sort_order, id');
  res.json({ services: rows });
});

app.get('/api/tickets', requireAuth, async (req, res) => {
  if (req.auth.role === 'admin') {
    const { rows } = await db.query(
      `SELECT t.id, t.subject, t.message, t.status, t.admin_note, t.created_at, t.updated_at,
       u.telegram_id, u.username, u.first_name FROM tickets t JOIN users u ON u.id = t.user_id
       ORDER BY t.created_at DESC LIMIT 100`
    );
    return res.json({ tickets: rows });
  }
  const { rows } = await db.query('SELECT id, subject, message, status, admin_note, created_at, updated_at FROM tickets WHERE user_id = $1 ORDER BY created_at DESC LIMIT 50', [req.auth.user_id]);
  res.json({ tickets: rows });
});

app.post('/api/tickets', requireAuth, async (req, res) => {
  if (req.auth.role !== 'user') return res.status(403).json({ error: 'Không có quyền tạo yêu cầu.' });
  const subject = String(req.body?.subject || '').trim();
  const message = String(req.body?.message || '').trim();
  if (subject.length < 3 || subject.length > 160 || message.length < 5 || message.length > 3000) {
    return res.status(400).json({ error: 'Tiêu đề cần 3–160 ký tự và nội dung cần 5–3.000 ký tự.' });
  }
  const { rows } = await db.query('INSERT INTO tickets (user_id, subject, message) VALUES ($1, $2, $3) RETURNING id, subject, status, created_at', [req.auth.user_id, subject, message]);
  await db.query('INSERT INTO audit_logs (actor, action, entity, entity_id) VALUES ($1, $2, $3, $4)', [String(req.auth.telegram_id), 'ticket.create', 'ticket', String(rows[0].id)]);
  res.status(201).json({ ticket: rows[0] });
});

app.get('/api/admin/stats', requireAuth, requireAdmin, async (_req, res) => {
  const { rows } = await db.query(
    `SELECT (SELECT COUNT(*) FROM users)::int AS users,
      (SELECT COUNT(*) FROM tickets WHERE status <> 'closed')::int AS open_tickets,
      (SELECT COUNT(*) FROM service_catalog)::int AS services,
      (SELECT COALESCE(SUM(balance), 0)::text FROM wallets) AS wallet_total`
  );
  res.json({ stats: rows[0] });
});

app.get('/api/admin/services', requireAuth, requireAdmin, async (_req, res) => {
  const { rows } = await db.query('SELECT id, code, name, category, description, price::text AS price, is_active, sort_order FROM service_catalog ORDER BY sort_order, id');
  res.json({ services: rows });
});

app.post('/api/admin/services', requireAuth, requireAdmin, async (req, res) => {
  const { code, name, category = 'Khác', description = '', price = 0, is_active = true, sort_order = 100 } = req.body || {};
  if (!/^[a-z0-9][a-z0-9_-]{1,49}$/.test(String(code || '')) || !String(name || '').trim() || String(name).length > 120) {
    return res.status(400).json({ error: 'Mã dịch vụ hoặc tên dịch vụ không hợp lệ.' });
  }
  const value = Number(price);
  if (!Number.isFinite(value) || value < 0 || value > 1_000_000_000) return res.status(400).json({ error: 'Giá dịch vụ không hợp lệ.' });
  const { rows } = await db.query(
    `INSERT INTO service_catalog (code, name, category, description, price, is_active, sort_order)
     VALUES ($1,$2,$3,$4,$5,$6,$7) RETURNING *`,
    [code, String(name).trim(), String(category).slice(0, 80), String(description).slice(0, 1000), value, Boolean(is_active), Number(sort_order) || 100]
  );
  await db.query('INSERT INTO audit_logs (actor, action, entity, entity_id) VALUES ($1,$2,$3,$4)', ['admin', 'service.create', 'service', String(rows[0].id)]);
  res.status(201).json({ service: rows[0] });
});

app.patch('/api/admin/services/:id', requireAuth, requireAdmin, async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isSafeInteger(id) || id < 1) return res.status(400).json({ error: 'ID không hợp lệ.' });
  const current = await db.query('SELECT * FROM service_catalog WHERE id = $1', [id]);
  if (!current.rows[0]) return res.status(404).json({ error: 'Không tìm thấy dịch vụ.' });
  const old = current.rows[0];
  const name = req.body?.name === undefined ? old.name : String(req.body.name).trim();
  const category = req.body?.category === undefined ? old.category : String(req.body.category).slice(0, 80);
  const description = req.body?.description === undefined ? old.description : String(req.body.description).slice(0, 1000);
  const price = req.body?.price === undefined ? Number(old.price) : Number(req.body.price);
  const isActive = req.body?.is_active === undefined ? old.is_active : Boolean(req.body.is_active);
  const sortOrder = req.body?.sort_order === undefined ? old.sort_order : Number(req.body.sort_order) || 100;
  if (!name || name.length > 120 || !Number.isFinite(price) || price < 0 || price > 1_000_000_000) return res.status(400).json({ error: 'Dữ liệu dịch vụ không hợp lệ.' });
  const { rows } = await db.query(
    `UPDATE service_catalog SET name=$1, category=$2, description=$3, price=$4, is_active=$5, sort_order=$6, updated_at=NOW()
     WHERE id=$7 RETURNING *`, [name, category, description, price, isActive, sortOrder, id]
  );
  await db.query('INSERT INTO audit_logs (actor, action, entity, entity_id) VALUES ($1,$2,$3,$4)', ['admin', 'service.update', 'service', String(id)]);
  res.json({ service: rows[0] });
});

app.patch('/api/admin/tickets/:id', requireAuth, requireAdmin, async (req, res) => {
  const id = Number(req.params.id);
  const status = String(req.body?.status || '');
  const note = String(req.body?.admin_note || '').slice(0, 2000);
  if (!Number.isSafeInteger(id) || id < 1 || !['open', 'processing', 'closed'].includes(status)) return res.status(400).json({ error: 'Trạng thái hoặc ID không hợp lệ.' });
  const { rows } = await db.query('UPDATE tickets SET status=$1, admin_note=$2, updated_at=NOW() WHERE id=$3 RETURNING id, status, admin_note, updated_at', [status, note, id]);
  if (!rows[0]) return res.status(404).json({ error: 'Không tìm thấy yêu cầu hỗ trợ.' });
  await db.query('INSERT INTO audit_logs (actor, action, entity, entity_id) VALUES ($1,$2,$3,$4)', ['admin', 'ticket.update', 'ticket', String(id)]);
  res.json({ ticket: rows[0] });
});

app.get('/admin', (_req, res) => res.sendFile(path.resolve(__dirname, '../public/admin.html')));
app.get('/health', (_req, res) => res.redirect('/healthz'));
app.get('*path', (req, res, next) => {
  if (req.path.startsWith('/api/') || req.path.startsWith('/telegram/')) return res.status(404).json({ error: 'Không tìm thấy API.' });
  return res.sendFile(path.resolve(__dirname, '../public/index.html'), (error) => error && next(error));
});

app.use((error, _req, res, _next) => {
  console.error('Request failed:', error?.code || error?.name || 'UNKNOWN');
  if (res.headersSent) return;
  res.status(500).json({ error: 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.' });
});

const server = await (async () => {
  await migrate(db);
  return app.listen(config.port, '0.0.0.0', () => {
    console.log(`HENDY đang chạy trên cổng ${config.port}; PostgreSQL đã sẵn sàng; Telegram Bot: ${bot ? 'đã cấu hình' : 'chưa cấu hình'}.`);
  });
})();

async function shutdown(signal) {
  console.log(`Nhận ${signal}, đang đóng kết nối...`);
  server.close(async () => {
    try { await closePool(); } finally { process.exit(0); }
  });
  setTimeout(() => process.exit(1), 10000).unref();
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));
