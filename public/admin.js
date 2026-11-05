const $ = (id) => document.getElementById(id);
const state = { token: sessionStorage.getItem('hendy_admin_session') || '' };

async function api(path, options = {}) {
  const response = await fetch(path, {
    ...options,
    headers: { 'Content-Type': 'application/json', ...(state.token ? { Authorization: `Bearer ${state.token}` } : {}), ...(options.headers || {}) }
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || `Lỗi HTTP ${response.status}`);
  return payload;
}
function esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); }
function toast(message) { const el = $('toast'); el.textContent = message; el.classList.add('show'); setTimeout(() => el.classList.remove('show'), 3000); }
function statusText(status) { return ({ open: 'Mới tiếp nhận', processing: 'Đang xử lý', closed: 'Đã đóng' })[status] || status; }

async function loadAdmin() {
  const [stats, services, tickets] = await Promise.all([api('/api/admin/stats'), api('/api/admin/services'), api('/api/tickets')]);
  $('loginPanel').hidden = true;
  $('adminApp').hidden = false;
  const items = [
    ['Người dùng', stats.stats.users], ['Ticket cần xử lý', stats.stats.open_tickets],
    ['Dịch vụ', stats.stats.services], ['Tổng số dư ví', `${Number(stats.stats.wallet_total).toLocaleString('vi-VN')} đ`]
  ];
  $('statsGrid').innerHTML = items.map(([label, value]) => `<article class="panel"><strong>${esc(value)}</strong><span class="muted">${esc(label)}</span></article>`).join('');
  renderServices(services.services);
  renderTickets(tickets.tickets);
}

function renderServices(services) {
  $('adminServices').innerHTML = `
    <table class="admin-table"><thead><tr><th>Dịch vụ</th><th>Giá (VNĐ)</th><th>Hiển thị</th><th></th></tr></thead><tbody>
    ${services.map((s) => `<tr data-service="${s.id}"><td><strong>${esc(s.name)}</strong><br><small class="muted">${esc(s.code)} · ${esc(s.category)}</small></td>
    <td><input class="price-input" type="number" min="0" step="1000" value="${Number(s.price)}" aria-label="Giá dịch vụ"></td>
    <td><input class="active-input" type="checkbox" ${s.is_active ? 'checked' : ''} aria-label="Hiển thị dịch vụ"></td>
    <td><button class="button ghost save-service" type="button">Lưu</button></td></tr>`).join('')}
    </tbody></table>`;
  document.querySelectorAll('.save-service').forEach((button) => button.addEventListener('click', async () => {
    const row = button.closest('tr');
    button.disabled = true;
    try {
      await api(`/api/admin/services/${row.dataset.service}`, { method: 'PATCH', body: JSON.stringify({ price: row.querySelector('.price-input').value, is_active: row.querySelector('.active-input').checked }) });
      toast('Đã cập nhật dịch vụ.');
    } catch (error) { toast(error.message); }
    finally { button.disabled = false; }
  }));
}

function renderTickets(tickets) {
  $('adminTickets').innerHTML = tickets.length ? tickets.map((t) => `
    <article class="panel admin-ticket" data-ticket="${t.id}">
      <div class="ticket-row"><div><h3>#${t.id} · ${esc(t.subject)}</h3><small class="muted">Telegram: ${esc(t.telegram_id)} ${t.username ? `· @${esc(t.username)}` : ''} · ${new Date(t.created_at).toLocaleString('vi-VN')}</small></div><span class="status ${t.status}">${statusText(t.status)}</span></div>
      <p>${esc(t.message)}</p>
      <div class="ticket-actions"><select class="ticket-status" aria-label="Trạng thái"><option value="open" ${t.status === 'open' ? 'selected' : ''}>Mới tiếp nhận</option><option value="processing" ${t.status === 'processing' ? 'selected' : ''}>Đang xử lý</option><option value="closed" ${t.status === 'closed' ? 'selected' : ''}>Đã đóng</option></select>
      <input class="ticket-note" maxlength="2000" placeholder="Ghi chú phản hồi" value="${esc(t.admin_note)}"><button class="button ghost save-ticket" type="button">Cập nhật</button></div>
    </article>`).join('') : '<div class="panel"><p class="muted">Chưa có yêu cầu hỗ trợ.</p></div>';
  document.querySelectorAll('.save-ticket').forEach((button) => button.addEventListener('click', async () => {
    const card = button.closest('[data-ticket]');
    button.disabled = true;
    try {
      await api(`/api/admin/tickets/${card.dataset.ticket}`, { method: 'PATCH', body: JSON.stringify({ status: card.querySelector('.ticket-status').value, admin_note: card.querySelector('.ticket-note').value }) });
      toast('Đã cập nhật yêu cầu hỗ trợ.');
      await loadAdmin();
    } catch (error) { toast(error.message); }
    finally { button.disabled = false; }
  }));
}

$('loginForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = event.currentTarget.querySelector('button');
  button.disabled = true;
  $('loginFeedback').textContent = 'Đang xác thực…';
  try {
    const result = await api('/api/admin/login', { method: 'POST', body: JSON.stringify({ username: $('adminUsername').value, password: $('adminPassword').value }) });
    state.token = result.token;
    sessionStorage.setItem('hendy_admin_session', state.token);
    $('adminPassword').value = '';
    await loadAdmin();
  } catch (error) { $('loginFeedback').textContent = error.message; }
  finally { button.disabled = false; }
});

$('logoutButton').addEventListener('click', async () => {
  try { await api('/api/logout', { method: 'POST' }); } catch { /* Expired session is already invalid. */ }
  state.token = '';
  sessionStorage.removeItem('hendy_admin_session');
  $('adminApp').hidden = true;
  $('loginPanel').hidden = false;
  toast('Đã đăng xuất.');
});

$('serviceForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = event.currentTarget.querySelector('button');
  button.disabled = true;
  const form = new FormData(event.currentTarget);
  const body = Object.fromEntries(form.entries());
  try {
    await api('/api/admin/services', { method: 'POST', body: JSON.stringify(body) });
    event.currentTarget.reset();
    $('serviceFeedback').textContent = 'Đã thêm dịch vụ.';
    toast('Đã thêm dịch vụ.');
    await loadAdmin();
  } catch (error) { $('serviceFeedback').textContent = error.message; }
  finally { button.disabled = false; }
});

if (state.token) loadAdmin().catch(() => {
  state.token = '';
  sessionStorage.removeItem('hendy_admin_session');
  $('loginPanel').hidden = false;
  $('adminApp').hidden = true;
});
