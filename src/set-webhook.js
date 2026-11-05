import 'dotenv/config';
import { Bot } from 'grammy';

const token = process.env.BOT_TOKEN;
const secret = process.env.TELEGRAM_WEBHOOK_SECRET;
const base = (process.env.PUBLIC_APP_URL || process.env.RENDER_EXTERNAL_URL || '').replace(/\/$/, '');
if (!token || !secret || !base.startsWith('https://')) {
  console.error('Cần BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET và PUBLIC_APP_URL (HTTPS).');
  process.exit(1);
}
const bot = new Bot(token);
await bot.api.setWebhook(`${base}/telegram/webhook`, {
  secret_token: secret,
  allowed_updates: ['message', 'callback_query']
});
console.log(`Đã đăng ký Telegram webhook: ${base}/telegram/webhook`);
