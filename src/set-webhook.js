import 'dotenv/config';
import { Bot } from 'grammy';
import { normalizeWebhookSecret } from './security.js';

const token = String(process.env.BOT_TOKEN || '').trim();
const secret = normalizeWebhookSecret(process.env.TELEGRAM_WEBHOOK_SECRET || '');
const base = String(process.env.PUBLIC_API_URL || process.env.RENDER_EXTERNAL_URL || '').trim().replace(/\/+$/, '');

if (!token || !secret || !base.startsWith('https://')) {
  console.error('Cần BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET và URL API HTTPS.');
  process.exit(1);
}

const appUrl = String(process.env.PUBLIC_APP_URL || base).trim().replace(/\/+$/, '');
const bot = new Bot(token);
await bot.api.setWebhook(base + '/telegram/webhook', {
  secret_token: secret,
  allowed_updates: ['message', 'callback_query']
});
await bot.api.setMyCommands([
  { command: 'start', description: 'Mở menu HENDY' },
  { command: 'services', description: 'Xem danh sách dịch vụ' },
  { command: 'balance', description: 'Kiểm tra số dư' },
  { command: 'support', description: 'Trung tâm hỗ trợ' },
  { command: 'help', description: 'Hướng dẫn sử dụng' }
]);
console.log('Đã đăng ký Telegram webhook trên API; Mini App:', appUrl);
