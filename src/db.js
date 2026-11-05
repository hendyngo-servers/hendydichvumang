import pg from 'pg';
import fs from 'node:fs/promises';
import path from 'node:path';

const { Pool } = pg;
let pool;

export function createPool(databaseUrl, options = {}) {
  if (pool) return pool;
  // Keep TLS policy in PGSSLMODE/PGSSL_CA_CERT. node-postgres otherwise lets
  // sslmode/sslrootcert URL parameters replace the explicit ssl config object.
  const parsedUrl = new URL(databaseUrl);
  for (const key of [...parsedUrl.searchParams.keys()]) {
    if (['sslmode', 'sslrootcert', 'sslcert', 'sslkey'].includes(key.toLowerCase())) {
      parsedUrl.searchParams.delete(key);
    }
  }
  const connectionString = parsedUrl.toString();
  const sslMode = options.sslMode || 'disable';
  const ssl = sslMode === 'disable'
    ? false
    : {
        rejectUnauthorized: sslMode === 'verify-full',
        ...(options.sslCa ? { ca: options.sslCa } : {})
      };

  pool = new Pool({
    connectionString,
    ssl,
    max: options.max || 5,
    idleTimeoutMillis: 30000,
    connectionTimeoutMillis: 10000,
    keepAlive: true,
    application_name: 'hendydichvumang-api'
  });
  pool.on('error', (error) => {
    console.error('PostgreSQL pool error:', error.code || error.name || 'UNKNOWN');
  });
  return pool;
}

export async function migrate(database) {
  const directory = path.resolve('migrations');
  const files = (await fs.readdir(directory))
    .filter((name) => /^\d+_[a-z0-9_-]+\.sql$/i.test(name))
    .sort();

  const client = await database.connect();
  try {
    await client.query('SELECT pg_advisory_lock($1)', [7120261105]);
    await client.query('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(160) PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');

    for (const file of files) {
      const exists = await client.query('SELECT 1 FROM schema_migrations WHERE version = $1', [file]);
      if (exists.rowCount) continue;

      const sql = await fs.readFile(path.join(directory, file), 'utf8');
      await client.query('BEGIN');
      try {
        await client.query(sql);
        await client.query('INSERT INTO schema_migrations (version) VALUES ($1)', [file]);
        await client.query('COMMIT');
        console.log('Đã áp dụng migration:', file);
      } catch (error) {
        await client.query('ROLLBACK');
        throw error;
      }
    }

    await client.query('DELETE FROM auth_sessions WHERE expires_at <= NOW()');
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
