import pg from 'pg';
import fs from 'node:fs/promises';
import path from 'node:path';

const { Pool } = pg;
let pool;

export function createPool(databaseUrl) {
  if (pool) return pool;
  pool = new Pool({
    connectionString: databaseUrl,
    max: 5,
    idleTimeoutMillis: 30000,
    connectionTimeoutMillis: 8000,
    keepAlive: true,
    application_name: 'hendydichvumang-web'
  });
  pool.on('error', (error) => {
    console.error('PostgreSQL pool error:', error.code || error.name || 'UNKNOWN');
  });
  return pool;
}

export async function migrate(database) {
  const sql = await fs.readFile(path.resolve('migrations/001_init.sql'), 'utf8');
  const client = await database.connect();
  try {
    await client.query('SELECT pg_advisory_lock($1)', [7120261105]);
    await client.query('BEGIN');
    await client.query(sql);
    await client.query('DELETE FROM auth_sessions WHERE expires_at <= NOW()');
    await client.query('COMMIT');
  } catch (error) {
    await client.query('ROLLBACK').catch(() => {});
    throw error;
  } finally {
    await client.query('SELECT pg_advisory_unlock($1)', [7120261105]).catch(() => {});
    client.release();
  }
}

export async function closePool() {
  if (!pool) return;
  const current = pool;
  pool = undefined;
  await current.end();
}
