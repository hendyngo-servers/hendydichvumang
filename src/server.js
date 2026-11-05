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
import { AUTOMATION_CODES, runAutomation, runDueAutomations } from './automation.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const config = loadConfig();
const db = createPool(config.databaseUrl, { sslMode: config.databaseSslMode, sslCa: config.databaseSslCa, max: config.databasePoolMax });
const app = express();
const bot = createBot({ token: config.botToken, db, appUrl: config.publicAppUrl, legacyCustomerUrl: config.legacyCustomerUrl });

app.disable('x-powered-by');
app.set('trust proxy', 1);
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", 'https://telegram.org'],
      styleSrc: ["'self'"],
      imgSrc: ["'self'", 'data:', 'https:'],
      connectSrc: ["'self'", ...config.allowedOrigins],
      objectSrc: ["'none'"],
      ...(config.isProduction ? { upgradeInsecureRequests: [] } : {})
    }
  }
}));
app.use(cors({
  origin(origin, callback) {
    if (!origin || config.allowedOrigins.includes(origin)) return callback(null, true);
    return callback(null, false);
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
  res.json({ brand: 'HENDY DỊCH VỤ MẠNG', appUrl: config.publicAppUrl, apiBaseUrl: config.publicApiUrl, language: 'vi', telegramBotConfigured: Boolean(bot) });
});

app.post('/telegram/webhook', rateLimit({ windowMs: 60_000, limit: 60, standardHeaders: 'draft-8', legacyHeaders: false }), async (req, res) => {
  if (!bot) return res.status(503).json({ error: 'Telegram Bot chưa được cấu hình.' });
  if (!req.body || typeof req.body !== 'object' || Array.isArray(req.body)) return res.status(400).json({ error: 'Dữ liệu webhook không hợp lệ.' });
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
    await client.query(
      "INSERT INTO auth_sessions (token_hash, user_id, role, expires_at) VALUES ($1, $2, 'user', NOW() + ($3 * INTERVAL '1 second'))",
      [hashSessionToken(token), userId, config.sessionTtlSeconds]
    );
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

function requireBotService(req, res, next) {
  if (!config.botServiceKey || config.botServiceKey.length < 32) {
    return res.status(503).json({ error: 'Chưa cấu hình BOT_SERVICE_KEY trên API.' });
  }
  const supplied = req.get('x-hendy-service-key') || '';
  if (!safeEqual(config.botServiceKey, supplied)) {
    return res.status(401).json({ error: 'Service key không hợp lệ.' });
  }
  return next();
}

app.use('/internal/bot', rateLimit({
  windowMs: 60_000,
  limit: 120,
  standardHeaders: 'draft-8',
  legacyHeaders: false
}));

app.get('/internal/bot/primary-identity', requireBotService, async (_req, res) => {
  if (!bot) return res.status(503).json({ error: 'Bot grammY chính chưa được cấu hình.' });
  const identity = await bot.api.getMe();
  res.json({ telegram_id: String(identity.id), username: identity.username || null });
});

app.post('/internal/bot/users/upsert', requireBotService, async (req, res) => {
  const telegramId = String(req.body?.telegram_id || '');
  const username = req.body?.username == null ? null : String(req.body.username);
  const firstName = String(req.body?.first_name || '');
  const lastName = String(req.body?.last_name || '');
  const languageCode = String(req.body?.language_code || 'vi');
  if (!/^\d{5,20}$/.test(telegramId) ||
      (username !== null && (!/^[A-Za-z0-9_]{1,64}$/.test(username))) ||
      firstName.length > 128 || lastName.length > 128 || languageCode.length > 16) {
    return res.status(400).json({ error: 'Thông tin Telegram không hợp lệ.' });
  }

  const client = await db.connect();
  try {
    await client.query('BEGIN');
    const user = await client.query(
      \`INSERT INTO users (telegram_id, username, first_name, last_name, language_code)
       VALUES ($1, $2, $3, $4, $5)
       ON CONFLICT (telegram_id) DO UPDATE SET
         username = EXCLUDED.username,
         first_name = EXCLUDED.first_name,
         last_name = EXCLUDED.last_name,
         language_code = EXCLUDED.language_code,
         updated_at = NOW()
       RETURNING id, telegram_id, username, first_name, last_name, language_code, created_at\`,
      [telegramId, username, firstName, lastName, languageCode]
    );
    const userId = user.rows[0].id;
    await client.query(
      'INSERT INTO wallets (user_id) VALUES ($1) ON CONFLICT (user_id) DO NOTHING',
      [userId]
    );
    const wallet = await client.query(
      'SELECT COALESCE(balance, 0)::text AS balance FROM wallets WHERE user_id = $1',
      [userId]
    );
    await client.query('COMMIT');
    res.json({ user: user.rows[0], balance: wallet.rows[0]?.balance || '0.00' });
  } catch (error) {
    await client.query('ROLLBACK');
    throw error;
  } finally {
    client.release();
  }
});

app.get('/internal/bot/users/:telegramId/balance', requireBotService, async (req, res) => {
  const telegramId = String(req.params.telegramId || '');
  if (!/^\d{5,20}$/.test(telegramId)) return res.status(400).json({ error: 'Telegram ID không hợp lệ.' });
  const { rows } = await db.query(
    \`SELECT u.telegram_id, COALESCE(w.balance, 0)::text AS balance
     FROM users u LEFT JOIN wallets w ON w.user_id = u.id
     WHERE u.telegram_id = $1 LIMIT 1\`,
    [telegramId]
  );
  if (!rows[0]) return res.status(404).json({ error: 'Chưa tìm thấy tài khoản HENDY. Hãy gửi /start trước.' });
  res.json(rows[0]);
});

app.post('/internal/bot/tickets', requireBotService, async (req, res) => {
  const telegramId = String(req.body?.telegram_id || '');
  const subject = String(req.body?.subject || '').trim();
  const message = String(req.body?.message || '').trim();
  if (!/^\d{5,20}$/.test(telegramId) ||
      subject.length < 3 || subject.length > 160 ||
      message.length < 5 || message.length > 3000) {
    return res.status(400).json({ error: 'Tiêu đề hoặc nội dung ticket không hợp lệ.' });
  }

  const client = await db.connect();
  try {
    await client.query('BEGIN');
    await client.query('SELECT pg_advisory_xact_lock(hashtext($1))', ['hendy-aiogram-ticket:' + telegramId]);
    const user = await client.query(
      'SELECT id FROM users WHERE telegram_id = $1 LIMIT 1',
      [telegramId]
    );
    if (!user.rows[0]) {
      await client.query('ROLLBACK');
      return res.status(404).json({ error: 'Chưa tìm thấy hồ sơ khách hàng. Hãy gửi /start trước.' });
    }

    const recent = await client.query(
      \`SELECT COUNT(*)::int AS count FROM tickets
       WHERE user_id = $1 AND created_at > NOW() - INTERVAL '1 hour'\`,
      [user.rows[0].id]
    );
    if (recent.rows[0].count >= 5) {
      await client.query('ROLLBACK');
      return res.status(429).json({ error: 'Bạn đã tạo quá nhiều ticket trong một giờ. Vui lòng thử lại sau.' });
    }

    const ticket = await client.query(
      \`INSERT INTO tickets (user_id, subject, message)
       VALUES ($1, $2, $3)
       RETURNING id, subject, status, created_at\`,
      [user.rows[0].id, subject, message]
    );
    await client.query(
      \`INSERT INTO audit_logs (actor, action, entity, entity_id, metadata)
       VALUES ($1, $2, $3, $4, $5)\`,
      [telegramId, 'ticket.create', 'ticket', String(ticket.rows[0].id), JSON.stringify({ source: 'aiogram' })]
    );
    await client.query('COMMIT');
    res.status(201).json({ ticket: ticket.rows[0] });
  } catch (error) {
    await client.query('ROLLBACK');
    throw error;
  } finally {
    client.release();
  }
});

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
  if (typeof is_active !== 'boolean' || !Number.isSafeInteger(Number(sort_order)) || !/^[a-z0-9][a-z0-9_-]{1,49}$/.test(String(code || '')) || !String(name || '').trim() || String(name).length > 120) {
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
  if (req.body?.is_active !== undefined && typeof req.body.is_active !== 'boolean') return res.status(400).json({ error: 'Trạng thái hiển thị phải là true hoặc false.' });
  const isActive = req.body?.is_active === undefined ? old.is_active : req.body.is_active;
  const sortOrder = req.body?.sort_order === undefined ? old.sort_order : Number(req.body.sort_order);
  if (!Number.isSafeInteger(sortOrder)) return res.status(400).json({ error: 'Thứ tự sắp xếp không hợp lệ.' });
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
  const note = String(req.body?.admin_note || '').trim();
  if (note.length > 2000) return res.status(400).json({ error: 'Ghi chú không được vượt quá 2.000 ký tự.' });
  if (!Number.isSafeInteger(id) || id < 1 || !['open', 'processing', 'closed'].includes(status)) return res.status(400).json({ error: 'Trạng thái hoặc ID không hợp lệ.' });
  const { rows } = await db.query('UPDATE tickets SET status=$1, admin_note=$2, updated_at=NOW() WHERE id=$3 RETURNING id, status, admin_note, updated_at', [status, note, id]);
  if (!rows[0]) return res.status(404).json({ error: 'Không tìm thấy yêu cầu hỗ trợ.' });
  await db.query('INSERT INTO audit_logs (actor, action, entity, entity_id) VALUES ($1,$2,$3,$4)', ['admin', 'ticket.update', 'ticket', String(id)]);
  res.json({ ticket: rows[0] });
});


app.get('/api/admin/automations', requireAuth, requireAdmin, async (_req, res) => {
  const { rows } = await db.query(
    `SELECT a.code, a.name, a.description, a.enabled, a.interval_minutes,
      a.next_run_at, a.last_run_at, a.last_status, a.last_error,
      r.status AS recent_run_status, r.started_at AS recent_run_at
     FROM automation_settings a
     LEFT JOIN LATERAL (
       SELECT status, started_at FROM automation_runs
       WHERE code = a.code ORDER BY started_at DESC LIMIT 1
     ) r ON TRUE
     ORDER BY a.code`
  );
  res.json({ automations: rows });
});

app.patch('/api/admin/automations/:code', requireAuth, requireAdmin, async (req, res) => {
  const code = String(req.params.code || '');
  const enabled = req.body?.enabled;
  const interval = Number(req.body?.interval_minutes);
  if (!AUTOMATION_CODES.includes(code)) return res.status(404).json({ error: 'Không tìm thấy tác vụ tự động hóa.' });
  if (typeof enabled !== 'boolean' || !Number.isSafeInteger(interval) || interval < 1 || interval > 10080) {
    return res.status(400).json({ error: 'Trạng thái hoặc chu kỳ chạy không hợp lệ.' });
  }
  const { rows } = await db.query(
    `UPDATE automation_settings
     SET enabled = $1, interval_minutes = $2,
         next_run_at = CASE WHEN $1 THEN NOW() ELSE next_run_at END,
         updated_at = NOW()
     WHERE code = $3
     RETURNING code, enabled, interval_minutes, next_run_at, last_status`,
    [enabled, interval, code]
  );
  if (!rows[0]) return res.status(404).json({ error: 'Không tìm thấy tác vụ tự động hóa.' });
  await db.query(
    'INSERT INTO audit_logs (actor, action, entity, entity_id, metadata) VALUES ($1,$2,$3,$4,$5)',
    ['admin', 'automation.update', 'automation', code, JSON.stringify({ enabled, interval_minutes: interval })]
  );
  res.json({ automation: rows[0] });
});

app.post('/api/admin/automations/:code/run', requireAuth, requireAdmin, async (req, res) => {
  const code = String(req.params.code || '');
  if (!AUTOMATION_CODES.includes(code)) return res.status(404).json({ error: 'Không tìm thấy tác vụ tự động hóa.' });
  const result = await runAutomation(
    db,
    code,
    { bot, adminTelegramId: config.adminTelegramId },
    { force: true }
  );
  if (result.status === 'busy') return res.status(409).json({ error: 'Tác vụ này đang chạy ở một tiến trình khác.' });
  await db.query(
    'INSERT INTO audit_logs (actor, action, entity, entity_id, metadata) VALUES ($1,$2,$3,$4,$5)',
    ['admin', 'automation.run', 'automation', code, JSON.stringify({ status: result.status })]
  );
  res.json({ result });
});

app.get('/admin', (_req, res) => res.sendFile(path.resolve(__dirname, '../public/admin.html')));
app.get('/health', (_req, res) => res.redirect('/healthz'));
app.get('*path', (req, res, next) => {
  if (req.path.startsWith('/api/') || req.path.startsWith('/telegram/')) return res.status(404).json({ error: 'Không tìm thấy API.' });
  return res.sendFile(path.resolve(__dirname, '../public/index.html'), (error) => error && next(error));
});

app.use((error, _req, res, _next) => {
  if (res.headersSent) return;
  if (error?.type === 'entity.parse.failed') return res.status(400).json({ error: 'JSON gửi lên không hợp lệ.' });
  if (error?.type === 'entity.too.large') return res.status(413).json({ error: 'Dữ liệu gửi lên vượt quá giới hạn 32 KB.' });
  if (error?.code === '23505') return res.status(409).json({ error: 'Mã dữ liệu đã tồn tại.' });
  console.error('Request failed:', error?.code || error?.name || 'UNKNOWN');
  res.status(500).json({ error: 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.' });
});

async function migrateWithRetry() {
  const attempts = 8;
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      await migrate(db);
      return;
    } catch (error) {
      console.error('PostgreSQL chưa sẵn sàng, lần thử ' + attempt + '/' + attempts + ':', error?.code || error?.name || 'UNKNOWN');
      if (attempt === attempts) throw error;
      await new Promise((resolve) => setTimeout(resolve, Math.min(attempt * 2000, 12000)));
    }
  }
}

const server = await (async () => {
  await migrateWithRetry();
  const instance = app.listen(config.port, '0.0.0.0', () => {
    console.log(`HENDY đang chạy trên cổng ${config.port}; PostgreSQL đã sẵn sàng; Telegram Bot: ${bot ? 'đã cấu hình' : 'chưa cấu hình'}.`);
  });
  instance.keepAliveTimeout = 65000;
  instance.headersTimeout = 66000;
  instance.requestTimeout = 30000;
  return instance;
})();

// Configure the Telegram webhook automatically after Render assigns the public HTTPS URL.
const automationContext = { bot, adminTelegramId: config.adminTelegramId };
const automationTimer = setInterval(() => {
  runDueAutomations(db, automationContext).catch((error) => {
    console.error('Automation scheduler error:', error?.code || error?.name || 'UNKNOWN');
  });
}, 30_000);
automationTimer.unref();
runDueAutomations(db, automationContext).catch((error) => {
  console.error('Automation startup error:', error?.code || error?.name || 'UNKNOWN');
});

if (bot && config.webhookSecret && config.publicApiUrl.startsWith('https://')) {
  Promise.all([
    bot.api.setWebhook(`${config.publicApiUrl}/telegram/webhook`, {
      secret_token: config.webhookSecret,
      allowed_updates: ['message', 'callback_query']
    }),
    bot.api.setMyCommands([
      { command: 'start', description: 'Mở menu HENDY' },
      { command: 'services', description: 'Xem danh sách dịch vụ' },
      { command: 'balance', description: 'Kiểm tra số dư' },
      { command: 'support', description: 'Trung tâm hỗ trợ' },
      { command: 'customer', description: 'Trung tâm khách hàng' },
      { command: 'help', description: 'Hướng dẫn sử dụng' }
    ]),
    bot.api.setChatMenuButton({
      menu_button: {
        type: 'web_app',
        text: 'Trung tâm KH',
        web_app: { url: config.publicAppUrl }
      }
    })
  ]).then(() => console.log('Telegram webhook và menu lệnh đã được đăng ký.'))
    .catch((error) => console.error('Không thể cấu hình Telegram Bot:', error?.error_code || error?.name || 'UNKNOWN'));
}

async function shutdown(signal) {
  console.log(`Nhận ${signal}, đang đóng kết nối...`);
  clearInterval(automationTimer);
  const forceExit = setTimeout(() => process.exit(1), 25000);
  forceExit.unref();
  server.close(async () => {
    clearTimeout(forceExit);
    try { await closePool(); } finally { process.exit(0); }
  });
  server.closeIdleConnections?.();
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));
