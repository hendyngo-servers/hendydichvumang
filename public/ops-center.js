(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const apiBase = String(window.HENDY_CONFIG?.apiBaseUrl || '').replace(/\/+$/, '');
  const number = (value) => Number(value || 0).toLocaleString('vi-VN');
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  async function api(path) {
    const token = sessionStorage.getItem('hendy_admin_session') || '';
    const response = await fetch(apiBase + path, {
      headers: {
        Accept: 'application/json',
        ...(token ? { Authorization: 'Bearer ' + token } : {})
      },
      cache: 'no-store'
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || 'Không thể tải dữ liệu điều hành.');
    return payload;
  }

  function statusLabel(status) {
    const labels = {
      ready: 'Sẵn sàng', ok: 'Hoạt động', live: 'Đang chạy',
      build_in_progress: 'Đang build', update_in_progress: 'Đang cập nhật',
      queued: 'Đang xếp hàng', created: 'Đã tạo', suspended: 'Đã tạm dừng',
      not_found: 'Chưa tìm thấy', build_failed: 'Build thất bại',
      update_failed: 'Deploy thất bại', failed: 'Thất bại',
      success: 'Thành công', skipped: 'Đã bỏ qua', running: 'Đang chạy',
      unknown: 'Chưa xác định', not_configured: 'Chưa cấu hình'
    };
    return labels[status] || status || 'Chưa có dữ liệu';
  }

  function statusClass(status) {
    if (['ready', 'ok', 'live', 'success'].includes(status)) return 'good';
    if (['build_failed', 'update_failed', 'failed', 'not_configured', 'not_found'].includes(status)) return 'bad';
    if (['build_in_progress', 'update_in_progress', 'queued', 'created', 'running', 'suspended'].includes(status)) return 'warn';
    return '';
  }

  function renderMetrics(data, renderData) {
    const aiogram = renderData?.services?.find((item) => item.key === 'aiogram');
    const cards = [
      { label: 'Người dùng', value: number(data.business?.users), note: 'Tài khoản Telegram' },
      { label: 'Ticket đang mở', value: number(data.business?.open_tickets), note: 'Cần CSKH xử lý' },
      { label: 'Module chờ duyệt', value: number(data.modules?.pending), note: 'Không tự thực thi' },
      { label: 'Automation bật', value: number(data.automations?.enabled), note: number(data.automations?.total) + ' tác vụ đã đăng ký' }
    ];
    $('opsMetrics').innerHTML = cards.map((card) =>
      '<article class="ops-metric"><span>' + escapeHtml(card.label) + '</span><strong>' + escapeHtml(card.value) +
      '</strong><small>' + escapeHtml(card.note) + '</small></article>'
    ).join('');

    const botRows = [
      {
        name: 'grammY · Bot chính',
        status: data.bots?.grammy?.configured ? 'ready' : 'not_configured',
        detail: data.bots?.grammy?.configured ? 'Token được cấu hình tại API service' : 'Thiếu BOT_TOKEN'
      },
      {
        name: 'Aiogram · Bot song song',
        status: !renderData?.configured ? 'not_configured' :
          !aiogram?.found ? 'not_found' :
            aiogram.status === 'live' && aiogram.missingEnv?.length === 0 ? 'live' : (aiogram.status || 'unknown'),
        detail: !renderData?.configured ? 'Cần kết nối Render API để kiểm tra' :
          !aiogram?.found ? 'Chưa tìm thấy service Aiogram trên Render' :
            aiogram.missingEnv?.length ? 'Thiếu ' + aiogram.missingEnv.length + ' biến môi trường bắt buộc' : 'Service độc lập, dùng API và PostgreSQL chung'
      }
    ];
    $('opsBotStatus').innerHTML = botRows.map((item) =>
      '<div class="ops-status-row"><div><strong>' + escapeHtml(item.name) + '</strong><small>' +
      escapeHtml(item.detail) + '</small></div><span class="ops-pill ' + statusClass(item.status) + '">' +
      escapeHtml(statusLabel(item.status)) + '</span></div>'
    ).join('');

    $('opsDbStatus').textContent = statusLabel(data.database?.status);
    $('opsDbStatus').className = 'ops-pill ' + statusClass(data.database?.status);
    $('opsRuntime').textContent = 'Node ' + escapeHtml(data.runtime?.node_version || '') +
      ' · ' + number(data.runtime?.uptime_seconds) + ' giây uptime · ' +
      number(data.runtime?.db_latency_ms) + ' ms kiểm tra DB';
    $('opsMigration').textContent = data.database?.latest_migration
      ? data.database.latest_migration
      : 'Chưa có thông tin migration';
    $('opsGeneratedAt').textContent = 'Cập nhật: ' + new Date(data.generated_at || Date.now()).toLocaleString('vi-VN');
  }

  function renderConfig(payload) {
    const groups = Array.isArray(payload.groups) ? payload.groups : [];
    $('opsConfigList').innerHTML = groups.map((group) =>
      '<section class="ops-config-group"><h3>' + escapeHtml(group.name) + '</h3><div class="ops-config-items">' +
      (group.items || []).map((item) =>
        '<div class="ops-config-item"><div><strong>' + escapeHtml(item.key) + '</strong><small>' +
        (item.secret ? 'Thông tin bí mật · không hiển thị giá trị' : 'Biến cấu hình runtime') +
        '</small></div><span class="ops-pill ' + (item.configured ? 'good' : 'warn') + '">' +
        (item.configured ? 'Đã cấu hình' : 'Chưa cấu hình') + '</span></div>'
      ).join('') + '</div></section>'
    ).join('') || '<p class="muted">Chưa có dữ liệu cấu hình.</p>';
    $('opsConfigSecurity').textContent = payload.security?.instruction ||
      'Giá trị bí mật không được gửi đến trình duyệt.';
  }

  function renderRender(payload) {
    if (!payload.configured) {
      $('opsRenderServices').innerHTML =
        '<div class="ops-empty"><strong>Chưa kết nối Render API</strong><p>Thêm RENDER_API_KEY vào Environment của service hendydichvumang-api. Có thể khai báo thêm RENDER_WORKSPACE_ID để giới hạn workspace.</p><a href="https://dashboard.render.com/" target="_blank" rel="noopener noreferrer">Mở Render Dashboard ↗</a></div>';
      return;
    }
    if (!payload.available) {
      $('opsRenderServices').innerHTML =
        '<div class="ops-empty"><strong>Chưa đọc được trạng thái Render</strong><p>' + escapeHtml(payload.message || 'Kiểm tra RENDER_API_KEY và quyền truy cập.') + '</p><a href="https://dashboard.render.com/" target="_blank" rel="noopener noreferrer">Mở Render Dashboard ↗</a></div>';
      return;
    }

    $('opsRenderServices').innerHTML = (payload.services || []).map((service) => {
      const deploy = service.latestDeploy;
      const missing = service.missingEnv || [];
      const link = typeof service.dashboardUrl === 'string' && /^https:\/\//i.test(service.dashboardUrl)
        ? '<a href="' + escapeHtml(service.dashboardUrl) + '" target="_blank" rel="noopener noreferrer">Mở service ↗</a>'
        : '';
      const missingHtml = service.envInventoryAvailable
        ? (missing.length
          ? '<p class="ops-missing">Thiếu biến: ' + missing.map(escapeHtml).join(', ') + '</p>'
          : '<p class="ops-ok-note">Các biến môi trường bắt buộc đã có tên khai báo.</p>')
        : '<p class="muted">Chưa đọc được danh sách biến môi trường.</p>';
      return '<article class="ops-service-card"><div class="ops-service-top"><div><span class="ops-service-key">' +
        escapeHtml(service.label) + '</span><h3>' + escapeHtml(service.name) + '</h3></div><span class="ops-pill ' +
        statusClass(service.status) + '">' + escapeHtml(statusLabel(service.status)) + '</span></div>' +
        '<div class="ops-service-meta"><span>Nhánh: ' + escapeHtml(service.branch || '—') + '</span>' +
        '<span>Auto deploy: ' + escapeHtml(service.autoDeploy || '—') + '</span></div>' +
        (deploy ? '<p class="ops-deploy-line">Deploy gần nhất: <strong>' + escapeHtml(statusLabel(deploy.status)) +
          '</strong>' + (deploy.createdAt ? ' · ' + escapeHtml(new Date(deploy.createdAt).toLocaleString('vi-VN')) : '') +
          '</p>' + (deploy.commitMessage ? '<p class="muted tiny">' + escapeHtml(deploy.commitMessage) + '</p>' : '') :
          '<p class="muted tiny">Chưa có dữ liệu deploy gần nhất.</p>') +
        missingHtml + link + '</article>';
    }).join('') || '<p class="muted">Render API không trả về service nào.</p>';
  }

  function renderLogs(payload) {
    const audit = (payload.audit || []).map((item) => ({
      time: item.created_at,
      type: 'Audit',
      actor: item.actor,
      action: item.action,
      entity: item.entity + (item.entity_id ? ' #' + item.entity_id : ''),
      status: ''
    }));
    const runs = (payload.automation_runs || []).map((item) => ({
      time: item.started_at,
      type: 'Automation',
      actor: 'Hệ thống',
      action: item.code,
      entity: 'Tác vụ tự động hóa',
      status: item.status
    }));
    const rows = [...audit, ...runs]
      .filter((item) => item.time)
      .sort((left, right) => new Date(right.time) - new Date(left.time))
      .slice(0, 35);

    $('opsLogsList').innerHTML = rows.length ? rows.map((item) =>
      '<div class="ops-log-row"><time>' + escapeHtml(new Date(item.time).toLocaleString('vi-VN')) +
      '</time><span class="ops-log-type">' + escapeHtml(item.type) + '</span><div><strong>' +
      escapeHtml(item.action) + '</strong><small>' + escapeHtml(item.actor) + ' · ' + escapeHtml(item.entity) +
      '</small></div>' + (item.status ? '<span class="ops-pill ' + statusClass(item.status) + '">' +
      escapeHtml(statusLabel(item.status)) + '</span>' : '') + '</div>'
    ).join('') : '<p class="muted">Chưa có nhật ký hoạt động.</p>';
  }

  async function refresh() {
    const button = $('opsRefreshButton');
    if (!button) return;
    button.disabled = true;
    button.textContent = 'Đang đồng bộ…';
    try {
      const results = await Promise.allSettled([
        api('/api/admin/operations/overview'),
        api('/api/admin/operations/config'),
        api('/api/admin/operations/render'),
        api('/api/admin/operations/logs?limit=30')
      ]);
      if (results[0].status === 'fulfilled') {
        renderMetrics(results[0].value, results[2].status === 'fulfilled' ? results[2].value : null);
      } else {
        $('opsMetrics').innerHTML = '<p class="muted">Không thể tải tổng quan hệ thống: ' +
          escapeHtml(results[0].reason?.message || 'Lỗi không xác định') + '</p>';
      }
      if (results[1].status === 'fulfilled') renderConfig(results[1].value);
      else $('opsConfigList').textContent = 'Không thể tải danh mục cấu hình.';
      if (results[2].status === 'fulfilled') renderRender(results[2].value);
      else $('opsRenderServices').textContent = 'Không thể tải trạng thái Render.';
      if (results[3].status === 'fulfilled') renderLogs(results[3].value);
      else $('opsLogsList').textContent = 'Không thể tải nhật ký.';
    } finally {
      button.disabled = false;
      button.textContent = '↻ Đồng bộ trạng thái';
    }
  }

  document.addEventListener('click', (event) => {
    if (event.target?.id === 'opsRefreshButton') refresh().catch(() => {});
  });

  window.HENDY_OPS = { refresh };
})();
