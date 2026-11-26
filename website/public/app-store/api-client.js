(() => {
  'use strict';

  const RELEASES_PATH = '/api/v1/store/releases';
  const ALLOWED_REMOTE_HOSTS = new Set(['hendydichvumang-telegram-bot.onrender.com']);
  const CATEGORIES = new Set(['tools', 'codes', 'vip', 'minigame', 'otp', 'proxy']);

  function resolveApiBase(config) {
    const raw = String(config?.apiBaseUrl || '').trim();
    if (!raw) return '';
    let url;
    try {
      url = new URL(raw);
    } catch (_) {
      throw new Error('Cấu hình địa chỉ API không hợp lệ.');
    }
    if (url.protocol !== 'https:' || !url.hostname || url.username || url.password ||
        (url.pathname !== '' && url.pathname !== '/') || url.search || url.hash ||
        !ALLOWED_REMOTE_HOSTS.has(url.hostname.toLowerCase())) {
      throw new Error('Địa chỉ API không nằm trong danh sách máy chủ HENDY được phép.');
    }
    return url.origin;
  }

  function create(config = {}) {
    const apiBase = resolveApiBase(config);

    async function listReleases(options = {}) {
      const category = String(options.category || '').trim();
      if (category && !CATEGORIES.has(category)) {
        throw new Error('Danh mục App Store không hợp lệ.');
      }

      const origin = apiBase || window.location.origin;
      const url = new URL(RELEASES_PATH, origin);
      if (category) url.searchParams.set('category', category);

      const controller = new AbortController();
      const timeout = window.setTimeout(() => controller.abort(), 15000);
      try {
        const response = await window.fetch(url.href, {
          method: 'GET',
          mode: 'cors',
          credentials: 'omit',
          cache: 'no-store',
          redirect: 'error',
          headers: { Accept: 'application/json' },
          signal: controller.signal
        });
        if (!response.ok) {
          throw new Error(response.status === 503
            ? 'Kho ứng dụng hiện chưa sẵn sàng.'
            : 'Không thể tải danh mục ứng dụng.');
        }

        const contentType = response.headers?.get?.('content-type') || '';
        if (contentType && !/application\/(?:[a-z.+-]+\+)?json\b/i.test(contentType)) {
          throw new Error('API trả về định dạng dữ liệu không hợp lệ.');
        }

        const payload = await response.json();
        if (!payload || payload.ok !== true || !Array.isArray(payload.releases) ||
            payload.releases.length > 250) {
          throw new Error('Dữ liệu danh mục App Store không hợp lệ.');
        }
        return payload;
      } finally {
        window.clearTimeout(timeout);
      }
    }

    return Object.freeze({ listReleases });
  }

  window.HENDY_STORE_API = Object.freeze({ create });
})();
