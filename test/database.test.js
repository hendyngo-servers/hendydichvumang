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
    const tables = await db.query(
      "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"
    );
    const names = new Set(tables.rows.map((row) => row.table_name));
    for (const table of ['users', 'wallets', 'wallet_transactions', 'service_catalog', 'tickets', 'auth_sessions', 'audit_logs', 'schema_migrations']) {
      assert.equal(names.has(table), true, 'missing table ' + table);
    }

    const migrations = await db.query('SELECT version FROM schema_migrations ORDER BY version');
    assert.deepEqual(migrations.rows.map((row) => row.version), [
      '001_init.sql',
      '002_remove_placeholders.sql'
    ]);

    const services = await db.query('SELECT COUNT(*)::int AS count FROM service_catalog');
    assert.equal(services.rows[0].count, 0);

    await migrate(db);
    const secondRun = await db.query('SELECT COUNT(*)::int AS count FROM schema_migrations');
    assert.equal(secondRun.rows[0].count, 2);
  } finally {
    await closePool();
  }
});
