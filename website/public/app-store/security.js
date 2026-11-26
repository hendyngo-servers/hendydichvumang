(() => {
  'use strict';

  const EXTENSIONS = Object.freeze({
    android: ['apk'],
    windows: ['exe', 'msi', 'msix', 'zip'],
    macos: ['dmg', 'pkg', 'zip'],
    ios: ['ipa'],
    linux: ['appimage', 'deb', 'rpm', 'tar.gz'],
    web: ['zip']
  });
  const CHECKSUM = /^[a-f0-9]{64}$/i;
  const SAFE_FILENAME = /^[a-z0-9][a-z0-9._-]{0,159}$/i;

  function safeHttps(value) {
    const raw = String(value || '').trim();
    if (!raw || /[\u0000-\u0020\u007f\\]/.test(raw) || raw.startsWith('//')) return '';
    try {
      const url = new URL(raw);
      if (url.protocol !== 'https:' || !url.hostname || url.username || url.password) return '';
      return url.href;
    } catch (_) {
      return '';
    }
  }

  function fileExtension(filename) {
    const name = String(filename || '').toLowerCase();
    return name.endsWith('.tar.gz') ? 'tar.gz' : name.split('.').pop();
  }

  function validRelease(item) {
    if (!item || typeof item !== 'object' || Array.isArray(item)) return false;
    const platform = String(item.platform || '').toLowerCase();
    const filename = String(item.filename || '').trim();
    const extension = fileExtension(filename);
    return Object.prototype.hasOwnProperty.call(EXTENSIONS, platform) &&
      EXTENSIONS[platform].includes(extension) &&
      SAFE_FILENAME.test(filename) &&
      Boolean(safeHttps(item.downloadUrl)) &&
      CHECKSUM.test(String(item.sha256 || '')) &&
      String(item.version || '').trim().length > 0;
  }

  window.HENDY_RELEASE_POLICY = Object.freeze({
    safeHttps,
    fileExtension,
    validRelease
  });
})();
