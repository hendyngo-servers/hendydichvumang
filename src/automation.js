const HANDLERS = new Set(['cleanup_sessions', 'notify_new_tickets', 'remind_open_tickets']);
export const AUTOMATION_CODES = Object.freeze([...HANDLERS]);

async function cleanupSessions(db) {
  const sessions = await db.query('DELETE FROM auth_sessions WHERE expires_at <= NOW()');
  const runs = await db.query("DELETE FROM automation_runs WHERE finished_at IS NOT NULL AND finished_at < NOW() - INTERVAL '180 days'");
  return { expiredSessionsRemoved: sessions.rowCount, oldRunsRemoved: runs.rowCount };
}

async function notifyNewTickets(db, context) {
  if (!context.bot || !context.adminTelegramId) {
    return { skipped: true, reason: 'Chưa cấu hình BOT_TOKEN hoặc ADMIN_TELEGRAM_ID.' };
  }
  const result = await db.query(
    'SELECT id, subject, message, created_at FROM tickets WHERE admin_notified_at IS NULL ORDER BY created_at ASC LIMIT 20'
  );
  let sent = 0;
  for (const ticket of result.rows) {
    const message = [
      '🎫 HENDY — TICKET MỚI #' + ticket.id,
      'Tiêu đề: ' + ticket.subject,
      'Nội dung: ' + ticket.message.slice(0, 1200),
      'Thời gian: ' + new Date(ticket.created_at).toLocaleString('vi-VN')
    ].join('\n');
    await context.bot.api.sendMessage(context.adminTelegramId, message);
    await db.query('UPDATE tickets SET admin_notified_at = NOW() WHERE id = $1 AND admin_notified_at IS NULL', [ticket.id]);
    sent += 1;
  }
  return { sent };
}

async function remindOpenTickets(db, context) {
  if (!context.bot || !context.adminTelegramId) {
    return { skipped: true, reason: 'Chưa cấu hình BOT_TOKEN hoặc ADMIN_TELEGRAM_ID.' };
  }
  const result = await db.query(
    "SELECT id, subject, status, created_at FROM tickets WHERE status IN ('open', 'processing') AND admin_notified_at IS NOT NULL AND created_at < NOW() - INTERVAL '60 minutes' AND (admin_reminded_at IS NULL OR admin_reminded_at < NOW() - INTERVAL '6 hours') ORDER BY created_at ASC LIMIT 10"
  );
  let sent = 0;
  for (const ticket of result.rows) {
    await context.bot.api.sendMessage(
      context.adminTelegramId,
      '⏰ HENDY — NHẮC TICKET #' + ticket.id +
      '\n' + ticket.subject +
      '\nTrạng thái: ' + ticket.status +
      '\nĐã chờ từ: ' + new Date(ticket.created_at).toLocaleString('vi-VN')
    );
    await db.query("UPDATE tickets SET admin_reminded_at = NOW() WHERE id = $1 AND status IN ('open', 'processing')", [ticket.id]);
    sent += 1;
  }
  return { remindersSent: sent };
}

async function executeHandler(code, db, context) {
  if (code === 'cleanup_sessions') return cleanupSessions(db);
  if (code === 'notify_new_tickets') return notifyNewTickets(db, context);
  if (code === 'remind_open_tickets') return remindOpenTickets(db, context);
  throw new Error('Loại tự động hóa không được hỗ trợ.');
}

export async function runAutomation(db, code, context = {}, options = {}) {
  if (!HANDLERS.has(code)) throw new Error('Mã tự động hóa không hợp lệ.');
  const client = await db.connect();
  let locked = false;
  let runId = null;
  try {
    const lock = await client.query('SELECT pg_try_advisory_lock(hashtext($1)) AS locked', ['hendy-automation:' + code]);
    locked = Boolean(lock.rows[0]?.locked);
    if (!locked) return { code, status: 'busy' };

    const selected = await client.query(
      'SELECT code, enabled, interval_minutes, next_run_at FROM automation_settings WHERE code = $1',
      [code]
    );
    const job = selected.rows[0];
    if (!job) return { code, status: 'not_found' };
    if (!options.force && (!job.enabled || new Date(job.next_run_at).getTime() > Date.now())) {
      return { code, status: 'not_due' };
    }

    const inserted = await client.query("INSERT INTO automation_runs (code, status) VALUES ($1, 'running') RETURNING id", [code]);
    runId = inserted.rows[0].id;
    const detail = await executeHandler(code, db, context);
    const status = detail?.skipped ? 'skipped' : 'success';

    await client.query(
      'UPDATE automation_runs SET status = $1, detail = $2::jsonb, finished_at = NOW() WHERE id = $3',
      [status, JSON.stringify(detail || {}), runId]
    );
    await client.query(
      'UPDATE automation_settings SET last_run_at = NOW(), last_status = $1, last_error = NULL, next_run_at = NOW() + make_interval(mins => interval_minutes), updated_at = NOW() WHERE code = $2',
      [status, code]
    );
    return { code, id: runId, status, detail };
  } catch (error) {
    if (runId !== null) {
      await client.query(
        "UPDATE automation_runs SET status = 'failed', detail = $1::jsonb, finished_at = NOW() WHERE id = $2",
        [JSON.stringify({ error: 'Tác vụ gặp lỗi; xem log dịch vụ để biết mã lỗi.' }), runId]
      ).catch(() => {});
      await client.query(
        "UPDATE automation_settings SET last_run_at = NOW(), last_status = 'failed', last_error = $1, next_run_at = NOW() + make_interval(mins => interval_minutes), updated_at = NOW() WHERE code = $2",
        [String(error?.code || error?.name || 'UNKNOWN').slice(0, 100), code]
      ).catch(() => {});
    }
    console.error('Automation failed:', code, error?.code || error?.name || 'UNKNOWN');
    throw error;
  } finally {
    if (locked) {
      await client.query('SELECT pg_advisory_unlock(hashtext($1))', ['hendy-automation:' + code]).catch(() => {});
    }
    client.release();
  }
}

export async function runDueAutomations(db, context = {}) {
  const result = await db.query(
    'SELECT code FROM automation_settings WHERE enabled = TRUE AND next_run_at <= NOW() ORDER BY next_run_at ASC LIMIT 10'
  );
  const outcomes = [];
  for (const row of result.rows) {
    try {
      outcomes.push(await runAutomation(db, row.code, context));
    } catch {
      outcomes.push({ code: row.code, status: 'failed' });
    }
  }
  return outcomes;
}
