import 'dotenv/config';
import { loadConfig } from '../src/config.js';
import { createPool, migrate, closePool } from '../src/db.js';
import { createBot } from '../src/bot.js';
import { runDueAutomations } from '../src/automation.js';

const config = loadConfig();
const db = createPool(config.databaseUrl, {
  sslMode: config.databaseSslMode,
  sslCa: config.databaseSslCa,
  max: config.databasePoolMax
});
const bot = createBot({ token: config.botToken, db, appUrl: config.publicAppUrl });

try {
  await migrate(db);
  const results = await runDueAutomations(db, {
    bot,
    adminTelegramId: config.adminTelegramId
  });
  console.log('Automation run:', JSON.stringify(results));
} catch (error) {
  console.error('Automation command failed:', error?.code || error?.name || 'UNKNOWN');
  process.exitCode = 1;
} finally {
  await closePool();
}
