(() => {
  'use strict';

  const RELEASES_PATH = '/api/v1/store/releases';
  const CHECK_PATH = '/api/v1/store/releases/check';
  const ALLOWED_REMOTE_HOSTS = new Set(['hendydichvumang-telegram-bot.onrender.com']);
  const CATEGORIES = new Set(['tools', 'codes', 'vip', 'minigame', 'otp', 'proxy']);
  const PLATFORMS = new Set(['android', 'windows', 'macos', 'ios', 'linux', 'web']);

  function resolveApiBase(config) {
    const raw = String(config?.apiBaseUrl || '').trim();
    if (!raw) return '';
    let url;
    try { url = new URL(raw); }
    catch (_) { throw new Error('Cấu hình địa chỉ API không hợp lệ.'); }
    if (url.protocol !== 'https:' || !url.hostname || url.username || url.password ||
        (url.pathname !== '' && url.pathname !== '/') || url.search || url.hash ||
        !ALLOWED_REMOTE_HOSTS.has(url.hostname.toLowerCase())) {
      throw new Error('Địa chỉ API không nằm trong danh sách máy chủ HENDY được phép.');
    }
    return url.origin;
  }

  function create(config = {}) {
    const apiBase = resolveApiBase(config);

    async function request(path, parameters = {}) {
      const origin = apiBase || window.location.origin;
      const url = new URL(path, origin);
      Object.entries(parameters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && String(value) !== '') {
          url.searchParams.set(key, String(value));
        }
      });
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
        const contentType = response.headers?.get?.('content-type') || '';
        if (contentType && !/application\/(?:[a-z.+-]+\+)?json\b/i.test(contentType)) {
          throw new Error('API trả về định dạng dữ liệu không hợp lệ.');
        }
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload?.ok !== true) {
          throw new Error(payload?.error || (response.status === 503
            ? 'Kho ứng dụng hiện chưa sẵn sàng.'
            : 'Không thể xử lý yêu cầu App Store.'));
        }
        return payload;
      } finally {
        window.clearTimeout(timeout);
      }
    }

    async function listReleases(options = {}) {
      const category = String(options.category || '').trim();
      if (category && !CATEGORIES.has(category)) {
        throw new Error('Danh mục App Store không hợp lệ.');
      }
      const payload = await request(RELEASES_PATH, { category });
      if (!Array.isArray(payload.releases) || payload.releases.length > 500) {
        throw new Error('Dữ liệu danh mục App Store không hợp lệ.');
      }
      return payload;
    }

    async function checkUpdate(appId, currentVersion, platform) {
      const safeAppId = String(appId || '').trim();
      const safePlatform = String(platform || '').toLowerCase();
      if (!safeAppId || safeAppId.length > 100 || !PLATFORMS.has(safePlatform)) {
        throw new Error('Thiếu mã ứng dụng hoặc nền tảng hợp lệ.');
      }
      return request(CHECK_PATH, {
        appId: safeAppId,
        currentVersion: String(currentVersion || '').trim(),
        platform: safePlatform
      });
    }

    return Object.freeze({ listReleases, checkUpdate });
  }

  window.HENDY_STORE_API = Object.freeze({ create });
})();
