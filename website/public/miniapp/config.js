(() => {
  'use strict';
  const staticSite = location.hostname === 'hendydichvumang-szad.onrender.com';
  window.HENDY_API_BASE = staticSite ? 'https://hendydichvumang-telegram-bot.onrender.com' : '';
})();
