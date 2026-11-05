import test from 'node:test';
import assert from 'node:assert/strict';
import {
  BOT_MENU_KEYS,
  MENU_ACTIONS,
  defaultMenuConfig,
  normalizeMenuConfig,
  validateMenuConfigInput,
  menuButtonText,
  visibleMenuActions,
  buildMenuRows,
  resolveMenuAction
} from '../src/menu-config.js';

test('default menu configuration is separate for grammY and Aiogram', () => {
  assert.deepEqual(BOT_MENU_KEYS, ['grammy', 'aiogram']);
  assert.equal(defaultMenuConfig('grammy').buttonOrder.includes('admin'), false);
  assert.equal(defaultMenuConfig('aiogram').buttonOrder.includes('admin'), true);
});

test('menu normalization discards unknown actions and restores customer entry', () => {
  const config = normalizeMenuConfig({
    buttonOrder: ['dangerous-action', 'support', 'support'],
    menuColumns: 99,
    buttonLabels: { support: 'Liên hệ CSKH', unknown: 'Injected' }
  }, 'grammy');
  assert.deepEqual(config.buttonOrder, ['customer', 'support']);
  assert.equal(config.menuColumns, 2);
  assert.equal(config.buttonLabels.support, 'Liên hệ CSKH');
  assert.equal(config.buttonLabels.unknown, undefined);
});

test('non-admin users never see Aiogram admin or automation buttons', () => {
  const config = defaultMenuConfig('aiogram');
  assert.deepEqual(
    visibleMenuActions(config, false),
    ['customer', 'balance', 'services', 'support']
  );
  assert.deepEqual(
    visibleMenuActions(config, true),
    ['customer', 'balance', 'services', 'support', 'admin', 'automation']
  );
});

test('grid layout respects the configured number of columns', () => {
  const config = defaultMenuConfig('aiogram');
  config.menuColumns = 2;
  const rows = buildMenuRows(config, true);
  assert.deepEqual(rows.map((row) => row.length), [2, 2, 2]);
});

test('custom labels map back to fixed allowlisted actions', () => {
  const config = defaultMenuConfig('grammy');
  config.buttonLabels.customer = 'Khu vực thành viên';
  config.buttonIcons.customer = '🧭';
  assert.equal(menuButtonText(config, 'customer'), '🧭 Khu vực thành viên');
  assert.equal(resolveMenuAction('🧭 Khu vực thành viên', config), 'customer');
  assert.equal(resolveMenuAction('xóa toàn bộ dữ liệu', config), null);
});

test('admin cannot save a menu without the customer entry or with unknown actions', () => {
  const config = defaultMenuConfig('grammy');
  assert.throws(
    () => validateMenuConfigInput({ ...config, buttonOrder: ['support'] }, 'grammy'),
    /Trung tâm khách hàng/
  );
  assert.throws(
    () => validateMenuConfigInput({ ...config, buttonOrder: ['customer', 'run-shell'] }, 'grammy'),
    /Thứ tự nút/
  );
});

test('menu input validation rejects long labels, invalid columns and unsupported actions', () => {
  const config = defaultMenuConfig('aiogram');
  assert.throws(
    () => validateMenuConfigInput({ ...config, buttonLabels: { customer: 'x'.repeat(41) } }, 'aiogram'),
    /Nhãn hoặc biểu tượng/
  );
  assert.throws(
    () => validateMenuConfigInput({ ...config, menuColumns: 4 }, 'aiogram'),
    /Số cột/
  );
  assert.equal(MENU_ACTIONS.grammy.includes('automation'), false);
});
