import test from 'node:test';
import assert from 'node:assert/strict';
import { buildCustomerCenterKeyboard } from '../src/bot.js';

test('customer center keyboard offers Telegram Mini App and legacy web fallback', () => {
  const keyboard = buildCustomerCenterKeyboard({
    appUrl: 'https://hendy-site.onrender.com',
    legacyCustomerUrl: 'https://legacy.example.com/customer',
    isPrivate: true
  });
  assert.equal(keyboard.inline_keyboard.length, 3);
  assert.equal(keyboard.inline_keyboard[0][0].web_app.url, 'https://hendy-site.onrender.com');
  assert.equal(keyboard.inline_keyboard[1][0].url, 'https://legacy.example.com/customer');
  assert.equal(keyboard.inline_keyboard[2][0].callback_data, 'customer:home');
});

test('customer center keyboard omits Mini App inline button outside private chat', () => {
  const keyboard = buildCustomerCenterKeyboard({
    appUrl: 'https://hendy-site.onrender.com',
    legacyCustomerUrl: 'https://legacy.example.com/customer',
    isPrivate: false
  });
  assert.equal(keyboard.inline_keyboard.length, 1);
  assert.equal(keyboard.inline_keyboard[0][0].web_app, undefined);
  assert.equal(keyboard.inline_keyboard[0][0].url, 'https://legacy.example.com/customer');
});
