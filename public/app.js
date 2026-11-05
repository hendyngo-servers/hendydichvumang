const state = { token: sessionStorage.getItem('hendy_session') || '', me: null };
const API_BASE_URL = String(window.HENDY_CONFIG?.apiBaseUrl || '').replace(/\/+$/, '');
const $ = (id) => document.getElementById(id);
const money = (value) => Number(value || 0).toLocaleString('vi-VN');

function toast(message) {
  const el = $('toast');
  el.textContent = message;
  el.classList.add('show');
  window.setTimeout(() => el.classList.remove('show'), 3000);
}

async function api(path, options = {}) {
  const response = await fetch(API_BASE_URL + path, {
    ...options,
    headers: { 'Content-Type': 'application/json', ...(state.token ? { Authorization: `Bearer ${state.token}` } : {}), ...(options.headers || {}) }
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) { const error = new Error(payload.error || `Lỗi HTTP ${response.status}`); error.status = response.status; throw error; }
  return payload;
}

function renderServices(services) {
  $('serviceCount').textContent = String(services.length);
  $('serviceList').innerHTML = services.length ? services.map((item) => `
    <article class="service-card">
      <span class="service-category">${escapeHtml(item.category)}</span>
      <h3>${escapeHtml(item.name)}</h3>
      <p>${escapeHtml(item.description)}</p>
      <span class="service-price">${Number(item.price).toLocaleString('vi-VN')} VNĐ</span>
    </article>`).join('') : '<div class="panel skeleton">Chưa có dịch vụ nào được công khai.</div>';
}

function renderTickets(tickets, totalCount = tickets.length) {
  $('ticketCount').textContent = String(totalCount);
  $('ticketList').innerHTML = tickets.length ? tickets.map((ticket) => `
    <article class="ticket-row"><div><strong>#${ticket.id} · ${escapeHtml(ticket.subject)}</strong><small>${new Date(ticket.created_at).toLocaleString('vi-VN')}</small>${ticket.admin_note ? `<small>Phản hồi: ${escapeHtml(ticket.admin_note)}</small>` : ''}</div>
    <span class="status ${ticket.status}">${statusText(ticket.status)}</span></article>`).join('') : '<p class="muted">Chưa có yêu cầu hỗ trợ.</p>';
}

function statusText(status) { return ({ open: 'Mới tiếp nhận', processing: 'Đang xử lý', closed: 'Đã đóng' })[status] || status; }
function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); }

async function bootstrap() {
  $('year').textContent = String(new Date().getFullYear());
  const tg = window.Telegram?.WebApp;
  if (tg) { tg.ready(); tg.expand(); }
  try {
    const services = await api('/api/services');
    renderServices(services.services);
  } catch (error) { $('serviceList').textContent = error.message; }

  const initData = tg?.initData || '';
  const signIn = async () => {
    if (!initData) return false;
    const session = await api('/api/telegram/auth', { method: 'POST', body: JSON.stringify({ initData }) });
    state.token = session.token;
    sessionStorage.setItem('hendy_session', state.token);
    return true;
  };

  if (!state.token && initData) await signIn();
  if (!state.token) {
    $('connectionStatus').innerHTML = '<i></i> Chưa đăng nhập';
    $('ticketForm').hidden = true;
    $('ticketFeedback').textContent = 'Vui lòng mở Mini App từ menu của Telegram để xác thực tài khoản.';
    $('ticketList').innerHTML = '<p class="muted">Hãy mở Mini App bên trong Telegram để xem thông tin cá nhân và gửi yêu cầu hỗ trợ.</p>';
    $('userName').textContent = 'bạn';
    return;
  }

  let privateData;
  try {
    privateData = await Promise.all([api('/api/me'), api('/api/dashboard'), api('/api/tickets')]);
  } catch (error) {
    if (error.status !== 401 || !initData) throw error;
    state.token = '';
    sessionStorage.removeItem('hendy_session');
    await signIn();
    privateData = await Promise.all([api('/api/me'), api('/api/dashboard'), api('/api/tickets')]);
  }

  const [me, dashboard, tickets] = privateData;
  state.me = me.user;
  $('userName').textContent = me.user.first_name || 'bạn';
  $('balanceValue').innerHTML = `${money(dashboard.balance)} <small>VNĐ</small>`;
  $('connectionStatus').innerHTML = '<i></i> Đã xác thực';
  renderTickets(tickets.tickets, Number(me.user.ticket_count));
}

$('ticketForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = event.currentTarget.querySelector('button[type="submit"]');
  button.disabled = true;
  $('ticketFeedback').textContent = 'Đang gửi yêu cầu…';
  try {
    await api('/api/tickets', { method: 'POST', body: JSON.stringify({ subject: $('ticketSubject').value, message: $('ticketMessage').value }) });
    $('ticketForm').reset();
    $('ticketFeedback').textContent = 'Yêu cầu đã được ghi nhận.';
    toast('Đã gửi yêu cầu hỗ trợ.');
    const [result, me] = await Promise.all([api('/api/tickets'), api('/api/me')]);
    renderTickets(result.tickets, Number(me.user.ticket_count));
  } catch (error) { $('ticketFeedback').textContent = error.message; toast(error.message); }
  finally { button.disabled = false; }
});

bootstrap().catch((error) => {
  $('connectionStatus').innerHTML = '<i></i> Lỗi kết nối';
  $('ticketFeedback').textContent = error.message;
  toast(error.message);
});
