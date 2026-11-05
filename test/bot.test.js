import test from 'node:test';
import assert from 'node:assert/strict';
import { buildCustomerCenterKeyboard, buildMainMenu } from '../apps/bot_chinh/bot.js';

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

test('grammY main menu has nine styled buttons with two-column and full-width rows', () => {
  const menu = buildMainMenu('https://hendy-site.onrender.com/app');
  assert.deepEqual(menu.keyboard.map((row) => row.length), [2, 2, 1, 1, 2, 1, 1]);
  assert.equal(menu.keyboard[0][0].style, 'danger');
  assert.equal(menu.keyboard[1][0].style, 'danger');
  assert.equal(menu.keyboard[2][0].style, 'success');
  assert.equal(menu.keyboard[3][0].text, '💠 TRUNG TÂM KHÁCH HÀNG');
  assert.equal(menu.keyboard.at(-1)[0].web_app.url, 'https://hendy-site.onrender.com/app');
});
