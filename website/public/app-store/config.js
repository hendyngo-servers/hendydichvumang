(() => {
  'use strict';
  const staticSite = location.hostname === 'hendydichvumang-szad.onrender.com';
  window.HENDY_STORE_CONFIG = Object.freeze({
    apiBaseUrl: staticSite ? 'https://hendydichvumang-telegram-bot.onrender.com' : ''
  });
})();
