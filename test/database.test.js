import test from 'node:test';
import assert from 'node:assert/strict';

const databaseUrl = process.env.TEST_DATABASE_URL || '';

test('PostgreSQL migrations apply once and create the application schema', {
  skip: !databaseUrl
}, async () => {
  const { createPool, migrate, closePool } = await import('../src/db.js');
  const db = createPool(databaseUrl);
  try {
    await migrate(db);
    const tables = await db.query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    const names = new Set(tables.rows.map((row) => row.table_name));
    for (const table of [
      'users', 'wallets', 'wallet_transactions', 'service_catalog', 'tickets',
      'auth_sessions', 'audit_logs', 'schema_migrations', 'automation_settings', 'automation_runs', 'bot_fsm_states', 'bot_menu_configs', 'module_artifacts'
    ]) {
      assert.equal(names.has(table), true, 'missing table ' + table);
    }

    const ticketColumns = await db.query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tickets'");
    const columnNames = new Set(ticketColumns.rows.map((row) => row.column_name));
    assert.equal(columnNames.has('admin_notified_at'), true);
    assert.equal(columnNames.has('admin_reminded_at'), true);

    const migrations = await db.query('SELECT version FROM schema_migrations ORDER BY version');
    assert.deepEqual(migrations.rows.map((row) => row.version), [
      '001_init.sql', '002_remove_placeholders.sql', '003_automations.sql', '004_aiogram_fsm.sql', '005_bot_menu_configs.sql', '006_module_artifacts.sql'
    ]);

    const automation = await db.query('SELECT code FROM automation_settings ORDER BY code');
    assert.deepEqual(automation.rows.map((row) => row.code), [
      'cleanup_sessions', 'notify_new_tickets', 'remind_open_tickets'
    ]);

    await migrate(db);
    const secondRun = await db.query('SELECT COUNT(*)::int AS count FROM schema_migrations');
    assert.equal(secondRun.rows[0].count, 6);
  } finally {
    await closePool();
  }
});
