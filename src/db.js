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
    connectionTimeoutMillis: 10000,
    allowExitOnIdle: false
  });
  pool.on('error', (error) => {
    // Do not print connection strings or credentials.
    console.error('Lỗi kết nối PostgreSQL nền:', error.code || 'UNKNOWN');
  });
  return pool;
}

export async function migrate(database) {
  const sql = await fs.readFile(path.resolve('migrations/001_init.sql'), 'utf8');
  await database.query(sql);
}

export async function closePool() {
  if (pool) {
    const current = pool;
    pool = undefined;
    await current.end();
  }
}
