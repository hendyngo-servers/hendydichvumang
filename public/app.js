const state = {
  token: sessionStorage.getItem('hendy_session') || '',
  initData: '',
  user: null,
  currentSection: 'overviewSection'
};
const API_BASE_URL = String(window.HENDY_CONFIG?.apiBaseUrl || '').replace(/\/+$/, '');
const $ = (id) => document.getElementById(id);
const tg = window.Telegram?.WebApp;
const money = (value) => Number(value || 0).toLocaleString('vi-VN');
const dateText = (value) => value ? new Date(value).toLocaleDateString('vi-VN') : '—';

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[c]);
}

function toast(message) {
  const el = $('toast');
  if (!el) return;
  el.textContent = message;
  el.classList.add('show');
  window.setTimeout(() => el.classList.remove('show'), 2800);
}

function setStatus(message, connected = false) {
  $('connectionStatus').innerHTML = '<i class="' + (connected ? 'connected' : '') + '"></i> ' + escapeHtml(message);
}

function telegramTheme() {
  if (!tg) return;
  const params = tg.themeParams || {};
  const root = document.documentElement;
  if (params.bg_color) root.style.setProperty('--telegram-bg', params.bg_color);
  if (params.text_color) root.style.setProperty('--telegram-text', params.text_color);
  if (params.hint_color) root.style.setProperty('--telegram-hint', params.hint_color);
  try {
    tg.setHeaderColor('#08090c');
    tg.setBackgroundColor('#030405');
  } catch { /* Older Telegram clients may not expose color controls. */ }
}

function initTelegram() {
  if (!tg) return false;
  try {
    tg.ready();
    tg.expand();
    telegramTheme();
    tg.onEvent?.('themeChanged', telegramTheme);
    if (tg.BackButton?.onClick) tg.BackButton.onClick(() => showSection('overviewSection'));
  } catch (error) {
    console.warn('Telegram Mini App initialization warning:', error?.message || 'UNKNOWN');
  }
  return true;
}

function haptic(type = 'light') {
  try { tg?.HapticFeedback?.impactOccurred(type); } catch { /* Optional. */ }
}

async function api(path, options = {}) {
  const response = await fetch(API_BASE_URL + path, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(state.token ? { Authorization: 'Bearer ' + state.token } : {}),
      ...(options.headers || {})
    }
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(payload.error || 'Lỗi HTTP ' + response.status);
    error.status = response.status;
    throw error;
  }
  return payload;
}

async function signInWithTelegram() {
  if (!state.initData) throw new Error('Telegram chưa cung cấp dữ liệu xác thực. Hãy đóng Mini App và mở lại từ bot HENDY.');
  const session = await api('/api/telegram/auth', {
    method: 'POST',
    body: JSON.stringify({ initData: state.initData })
  });
  if (!session.token) throw new Error('Máy chủ không cấp được phiên đăng nhập.');
  state.token = session.token;
  sessionStorage.setItem('hendy_session', state.token);
}

async function loadPrivateData() {
  const results = await Promise.all([
    api('/api/me'),
    api('/api/dashboard'),
    api('/api/services'),
    api('/api/tickets')
  ]);
  return { me: results[0].user, dashboard: results[1], services: results[2].services, tickets: results[3].tickets };
}

function renderAccount(user) {
  state.user = user;
  const displayName = [user.first_name, user.last_name].filter(Boolean).join(' ').trim() || 'bạn';
  $('userName').textContent = displayName;
  $('userAvatar').textContent = displayName.slice(0, 1).toLocaleUpperCase('vi');
  $('telegramId').textContent = String(user.telegram_id);
  $('accountTelegramId').textContent = String(user.telegram_id);
  $('accountName').textContent = displayName;
  $('accountUsername').textContent = user.username ? '@' + user.username : 'Chưa đặt';
  $('accountJoined').textContent = dateText(user.created_at);
}

function renderServices(services) {
  $('serviceCount').textContent = String(services.length);
  $('serviceList').innerHTML = services.length ? services.map((item) =>
    '<article class="service-card"><span class="service-category">' + escapeHtml(item.category) +
    '</span><h3>' + escapeHtml(item.name) + '</h3><p>' + escapeHtml(item.description) +
    '</p><span class="service-price">' + Number(item.price).toLocaleString('vi-VN') + ' VNĐ</span></article>'
  ).join('') : '<div class="panel skeleton">Chưa có dịch vụ nào được công khai.</div>';
}

function statusText(status) {
  return ({ open: 'Mới tiếp nhận', processing: 'Đang xử lý', closed: 'Đã đóng' })[status] || status;
}

function renderTickets(tickets, totalCount = tickets.length) {
  $('ticketCount').textContent = String(totalCount);
  $('ticketList').innerHTML = tickets.length ? tickets.map((ticket) =>
    '<article class="ticket-row"><div><strong>#' + escapeHtml(ticket.id) + ' · ' + escapeHtml(ticket.subject) +
    '</strong><small>' + new Date(ticket.created_at).toLocaleString('vi-VN') + '</small>' +
    (ticket.admin_note ? '<small>Phản hồi: ' + escapeHtml(ticket.admin_note) + '</small>' : '') +
    '</div><span class="status ' + escapeHtml(ticket.status) + '">' + statusText(ticket.status) + '</span></article>'
  ).join('') : '<p class="muted">Chưa có yêu cầu hỗ trợ.</p>';
}

function showSection(sectionId) {
  const allowed = ['overviewSection', 'servicesSection', 'supportSection'];
  if (!allowed.includes(sectionId)) return;
  state.currentSection = sectionId;
  allowed.forEach((id) => { $(id).hidden = id !== sectionId; });
  document.querySelectorAll('.mini-nav-button').forEach((button) => {
    const active = button.dataset.section === sectionId;
    button.classList.toggle('active', active);
    button.setAttribute('aria-current', active ? 'page' : 'false');
  });
  if (tg?.BackButton) {
    if (sectionId === 'overviewSection') tg.BackButton.hide();
    else tg.BackButton.show();
  }
  haptic('light');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showGate(title, message) {
  $('refreshButton').hidden = true;
  $('customerApp').hidden = true;
  $('telegramGate').hidden = false;
  $('gateTitle').textContent = title;
  $('gateMessage').textContent = message;
  setStatus('Chưa xác thực');
}

function showCustomerApp(data) {
  $('refreshButton').hidden = false;
  $('telegramGate').hidden = true;
  $('customerApp').hidden = false;
  renderAccount(data.me);
  $('balanceValue').textContent = money(data.dashboard.balance);
  renderServices(data.services);
  renderTickets(data.tickets, Number(data.me.ticket_count));
  setStatus('Đã xác thực', true);
  showSection('overviewSection');
  const mobilePlatform = ['android', 'ios'].includes(String(tg?.platform || '').toLowerCase());
  $('deviceNote').hidden = mobilePlatform;
}

async function refreshData() {
  try {
    return await loadPrivateData();
  } catch (error) {
    if (error.status !== 401 || !state.initData) throw error;
    state.token = '';
    sessionStorage.removeItem('hendy_session');
    await signInWithTelegram();
    return loadPrivateData();
  }
}

async function bootstrap() {
  $('year').textContent = String(new Date().getFullYear());
  if (!initTelegram()) {
    showGate('Mở trung tâm trong Telegram', 'Mini App chỉ xác thực tài khoản khi được mở từ chat bot HENDY trong Telegram.');
    $('closeMiniAppButton').hidden = true;
    return;
  }

  const platform = String(tg.platform || '').toLowerCase();
  const mobilePlatforms = ['android', 'ios', 'android_x', 'ios_x'];
  if (platform && !mobilePlatforms.includes(platform)) {
    showGate('Mini App dành cho điện thoại', 'Phiên bản Mini App mới hiện được giới hạn cho Telegram trên Android và iPhone. Hãy đóng cửa sổ này, gửi /customer cho bot và chọn Phiên bản Web cũ.');
    return;
  }

  state.initData = tg.initData || '';
  if (!state.initData) {
    showGate('Không nhận được dữ liệu Telegram', 'Hãy đóng cửa sổ này, quay lại chat bot HENDY và nhấn nút Mini App để đăng nhập an toàn.');
    return;
  }

  setStatus('Đang xác thực');
  try {
    if (!state.token) await signInWithTelegram();
    const data = await refreshData();
    showCustomerApp(data);
  } catch (error) {
    state.token = '';
    sessionStorage.removeItem('hendy_session');
    showGate('Không thể mở tài khoản', error.message || 'Vui lòng thử lại từ bot HENDY.');
    toast(error.message || 'Không thể tải dữ liệu.');
  }
}

document.querySelectorAll('[data-section]').forEach((button) => {
  button.addEventListener('click', () => showSection(button.dataset.section));
});

$('refreshButton').addEventListener('click', async () => {
  const button = $('refreshButton');
  button.disabled = true;
  try {
    const data = await refreshData();
    showCustomerApp(data);
    toast('Đã cập nhật dữ liệu mới nhất.');
    tg?.HapticFeedback?.notificationOccurred?.('success');
  } catch (error) {
    toast(error.message || 'Không thể cập nhật dữ liệu.');
  } finally {
    button.disabled = false;
  }
});

$('closeMiniAppButton').addEventListener('click', () => {
  if (tg?.close) tg.close();
  else window.close();
});

$('ticketForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = event.currentTarget.querySelector('button[type="submit"]');
  button.disabled = true;
  $('ticketFeedback').textContent = 'Đang gửi yêu cầu…';
  try {
    await api('/api/tickets', {
      method: 'POST',
      body: JSON.stringify({
        subject: $('ticketSubject').value.trim(),
        message: $('ticketMessage').value.trim()
      })
    });
    event.currentTarget.reset();
    $('ticketFeedback').textContent = 'Yêu cầu đã được ghi nhận.';
    const data = await refreshData();
    showCustomerApp(data);
    showSection('supportSection');
    toast('Đã gửi yêu cầu hỗ trợ.');
    tg?.HapticFeedback?.notificationOccurred?.('success');
  } catch (error) {
    $('ticketFeedback').textContent = error.message;
    toast(error.message);
    tg?.HapticFeedback?.notificationOccurred?.('error');
  } finally {
    button.disabled = false;
  }
});

$('logoutButton').addEventListener('click', async () => {
  const button = $('logoutButton');
  button.disabled = true;
  try { await api('/api/logout', { method: 'POST' }); } catch { /* Session may have expired. */ }
  state.token = '';
  sessionStorage.removeItem('hendy_session');
  showGate('Bạn đã đăng xuất', 'Phiên đăng nhập đã được thu hồi. Đóng Mini App rồi mở lại từ bot HENDY để đăng nhập lần nữa.');
  toast('Đã đăng xuất an toàn.');
  button.disabled = false;
});

bootstrap().catch((error) => {
  showGate('Có lỗi khi tải dữ liệu', error.message || 'Vui lòng thử lại sau.');
  toast(error.message || 'Lỗi kết nối.');
});
