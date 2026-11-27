(() => {
  'use strict';

  const storeApi = window.HENDY_STORE_API.create(window.HENDY_STORE_CONFIG || {});
  const policy = window.HENDY_RELEASE_POLICY;
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const platformNames = { windows:'Windows', android:'Android', ios:'iOS / iPadOS', macos:'macOS', linux:'Linux', web:'Web' };
  const categoryNames = { tools:'Công cụ', codes:'Code', vip:'VIP', minigame:'Mini Game', otp:'OTP', proxy:'Proxy' };
  const platformSymbols = { windows:'⊞', android:'◈', ios:'●', macos:'●', linux:'⌘', web:'◎' };
  const state = { releases:[], platform:'all', category:'all', previousFocus:null, activeRelease:null };

  function byId(id) { return document.getElementById(id); }
  function formatBytes(value) {
    const bytes = Number(value);
    if (!Number.isFinite(bytes) || bytes < 0) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 ** 2) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 ** 3) return (bytes / 1024 ** 2).toFixed(2) + ' MB';
    return (bytes / 1024 ** 3).toFixed(2) + ' GB';
  }
  function escapeText(value) { return String(value ?? '').slice(0, 2000); }
  function detectDevice() {
    const ua = String(navigator.userAgent || '').toLowerCase();
    const platform = String(navigator.userAgentData?.platform || navigator.platform || '').toLowerCase();
    if (/android/.test(ua)) return 'android';
    if (/iphone|ipad|ipod/.test(ua) || (/mac/.test(platform) && navigator.maxTouchPoints > 1)) return 'ios';
    if (/windows/.test(platform) || /windows/.test(ua)) return 'windows';
    if (/mac/.test(platform) || /macintosh/.test(ua)) return 'macos';
    if (/linux/.test(platform) || /linux/.test(ua)) return 'linux';
    return 'web';
  }
  function toast(message) {
    const node = byId('toast');
    if (!node) return;
    node.textContent = String(message || '');
    node.hidden = false;
    window.clearTimeout(toast.timer);
    toast.timer = window.setTimeout(() => { node.hidden = true; }, 3200);
  }
  function setApiStatus(message, mode) {
    const node = byId('api-status');
    if (!node) return;
    node.className = 'api-status ' + mode;
    node.replaceChildren();
    const dot = document.createElement('i');
    const text = document.createElement('span');
    text.textContent = message;
    node.append(dot, text);
  }
  function createEmpty(title, description, icon) {
    const node = document.createElement('div');
    node.className = 'empty-state';
    const glyph = document.createElement('strong');
    glyph.className = 'empty-glyph';
    glyph.textContent = icon || '✦';
    const heading = document.createElement('b');
    heading.textContent = title;
    const copy = document.createElement('span');
    copy.textContent = description;
    node.append(glyph, heading, copy);
    return node;
  }
  function iconFor(item, sizeClass) {
    const wrap = document.createElement('div');
    wrap.className = 'app-icon' + (sizeClass ? ' ' + sizeClass : '');
    const imageUrl = policy.safeHttps(item.imageUrl);
    if (imageUrl) {
      const image = document.createElement('img');
      image.src = imageUrl;
      image.alt = '';
      image.loading = 'lazy';
      image.referrerPolicy = 'no-referrer';
      image.addEventListener('error', () => {
        wrap.replaceChildren();
        wrap.textContent = platformSymbols[item.platform] || '✦';
      }, { once:true });
      wrap.append(image);
    } else {
      wrap.textContent = platformSymbols[item.platform] || '✦';
    }
    return wrap;
  }
  function categoryLabel(value) { return categoryNames[value] || 'Ứng dụng'; }
  function filterItems() {
    const term = String(byId('search-input')?.value || '').trim().toLocaleLowerCase('vi');
    const items = state.releases.filter(item => {
      const platformMatch = state.platform === 'all' || item.platform === state.platform;
      const categoryMatch = state.category === 'all' || item.category === state.category;
      const haystack = [item.name,item.description,item.version,item.filename,item.platform,item.category,item.publisherName]
        .map(value => String(value || '').toLocaleLowerCase('vi')).join(' ');
      return platformMatch && categoryMatch && (!term || haystack.includes(term));
    });
    const sort = String(byId('sort-select')?.value || 'default');
    const collator = new Intl.Collator('vi', {numeric:true,sensitivity:'base'});
    if (sort === 'name') items.sort((a,b) => collator.compare(String(a.name || ''),String(b.name || '')));
    if (sort === 'version') items.sort((a,b) => collator.compare(String(b.version || ''),String(a.version || '')));
    if (sort === 'platform') items.sort((a,b) => collator.compare(platformNames[a.platform] || a.platform,platformNames[b.platform] || b.platform));
    return items;
  }
  function buildCard(item) {
    const card = document.createElement('article');
    card.className = 'app-card';
    const head = document.createElement('div');
    head.className = 'card-head';
    head.append(iconFor(item));
    const title = document.createElement('div');
    title.className = 'app-heading';
    const name = document.createElement('h3');
    name.textContent = escapeText(item.name || 'Ứng dụng HENDY');
    const version = document.createElement('p');
    version.textContent = 'v' + String(item.version || '—') + ' · ' + (platformNames[item.platform] || item.platform);
    title.append(name,version);
    head.append(title);
    const releaseTag = document.createElement('span');
    releaseTag.className = 'release-tag';
    releaseTag.textContent = String(item.channel || 'stable').toUpperCase();
    head.append(releaseTag);
    card.append(head);

    const meta = document.createElement('div');
    meta.className = 'card-meta';
    [categoryLabel(item.category),String(item.filename || ''),formatBytes(item.sizeBytes)].filter(Boolean).forEach(value => {
      const tag = document.createElement('span');
      tag.textContent = value;
      meta.append(tag);
    });
    card.append(meta);
    const description = document.createElement('p');
    description.className = 'card-description';
    description.textContent = escapeText(item.description || item.releaseNotes || 'Ứng dụng và công cụ được HENDY quản lý.');
    card.append(description);
    const bottom = document.createElement('div');
    bottom.className = 'card-bottom';
    const versionNote = document.createElement('span');
    versionNote.className = 'card-version';
    versionNote.textContent = (item.publisherName || 'HENDY Verified listing') + ' · ' + (item.architecture || 'Universal');
    const open = document.createElement('button');
    open.className = 'card-open';
    open.type = 'button';
    open.textContent = 'Xem chi tiết →';
    open.addEventListener('click', () => showDetails(item));
    bottom.append(versionNote,open);
    card.append(bottom);
    return card;
  }
  function renderCatalog() {
    const grid = byId('app-grid');
    if (!grid) return;
    const items = filterItems();
    if (items.length) grid.replaceChildren(...items.map(buildCard));
    else grid.replaceChildren(createEmpty(
      state.releases.length ? 'Không tìm thấy ứng dụng phù hợp' : 'Kho ứng dụng chưa có bản phát hành',
      state.releases.length ? 'Hãy đổi bộ lọc hoặc từ khóa tìm kiếm.' : 'Danh mục chỉ hiển thị khi Admin tải bộ cài, xác minh hồ sơ phân phối và công khai bản phát hành.',
      '⌕'
    ));
    const count = byId('catalog-count');
    if (count) count.textContent = 'Đang hiển thị ' + items.length + ' / ' + state.releases.length + ' gói phát hành';
    const heroCount = byId('hero-count');
    if (heroCount) heroCount.textContent = String(state.releases.length);
  }
  function setFilter(group, value) {
    state[group] = value;
    const selector = group === 'platform' ? '[data-platform]' : '[data-category]';
    $$(selector).forEach(button => {
      button.classList.toggle('active', String(button.dataset[group] || '') === String(value));
    });
    renderCatalog();
  }
  function makeLink(label, href, className) {
    const link = document.createElement('a');
    link.className = className;
    link.href = href;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = label;
    return link;
  }
  function appendSteps(parent, title, lines, warning) {
    const panel = document.createElement('section');
    panel.className = 'install-card';
    const heading = document.createElement('h3');
    heading.textContent = title;
    panel.append(heading);
    const list = document.createElement('ol');
    lines.forEach(line => {
      const item = document.createElement('li');
      item.textContent = line;
      list.append(item);
    });
    panel.append(list);
    if (warning) {
      const notice = document.createElement('div');
      notice.className = 'warning-card';
      notice.textContent = warning;
      panel.append(notice);
    }
    parent.append(panel);
  }
  function appendDistributionActions(parent, item) {
    const actions = document.createElement('div');
    actions.className = 'detail-actions';
    const downloadUrl = policy.safeHttps(item.downloadUrl);
    const storeUrl = policy.safeStoreListing(item.storeListingUrl);
    const platform = String(item.platform || '').toLowerCase();

    if (platform === 'ios') {
      if (storeUrl) actions.append(makeLink('Mở App Store / TestFlight ↗',storeUrl,'store-action'));
      if (item.otaAvailable === true && policy.safeHttps(item.manifestUrl)) {
        const ota = document.createElement('a');
        ota.className = 'primary-action';
        ota.href = 'itms-services://?action=download-manifest&url=' + encodeURIComponent(policy.safeHttps(item.manifestUrl));
        ota.textContent = 'Cài đặt iOS qua OTA';
        actions.append(ota);
      }
      if (downloadUrl) actions.append(makeLink('Tải tệp IPA (nếu thiết bị được cấp quyền)',downloadUrl,'secondary-action'));
      if (!storeUrl && !(item.otaAvailable === true && policy.safeHttps(item.manifestUrl)) && !downloadUrl) {
        const unavailable = document.createElement('span');
        unavailable.className = 'card-version';
        unavailable.textContent = 'Chưa có kênh phân phối iOS hợp lệ.';
        actions.append(unavailable);
      }
    } else if (downloadUrl && policy.validRelease(item)) {
      const extension = policy.fileExtension(item.filename);
      const label = platform === 'android' ? 'Tải APK cho Android' :
        platform === 'windows' && extension === 'zip' ? 'Tải gói portable Windows (ZIP)' :
        platform === 'windows' ? 'Tải bộ cài Windows' :
        'Tải gói ' + (platformNames[platform] || platform);
      actions.append(makeLink(label,downloadUrl,'primary-action'));
    } else {
      const unavailable = document.createElement('span');
      unavailable.className = 'card-version';
      unavailable.textContent = 'Bản tải xuống chưa đạt điều kiện xác minh.';
      actions.append(unavailable);
    }
    parent.append(actions);

    if (platform === 'android') {
      appendSteps(parent,'Cách cài đặt Android',[
        'Tải tệp APK về thiết bị.',
        'Mở tệp từ mục Downloads hoặc trình quản lý tệp.',
        'Khi Android hỏi, chỉ cho phép ứng dụng nguồn này cài đặt nếu bạn tin cậy nhà phát hành.',
        'Xem quyền ứng dụng rồi chọn Cài đặt.'
      ],'Không tắt Play Protect trên toàn thiết bị. Không cài APK nếu chữ ký hoặc nguồn phát hành không rõ ràng.');
    } else if (platform === 'windows') {
      const isZip = policy.fileExtension(item.filename) === 'zip';
      appendSteps(parent,isZip ? 'Cách sử dụng gói portable Windows' : 'Cách cài đặt Windows',
        isZip ? [
          'Tải ZIP và quét tệp bằng công cụ bảo mật bạn tin cậy.',
          'Giải nén vào thư mục do bạn quản lý; không chạy tệp lạ trong archive.',
          'Mở ứng dụng theo hướng dẫn đi kèm và kiểm tra nhà phát hành.'
        ] : [
          'Tải EXE, MSI hoặc MSIX phù hợp với phiên bản Windows.',
          'Mở tệp trong thư mục Downloads và đọc tên nhà phát hành trong hộp thoại xác nhận.',
          'Làm theo trình cài đặt; chỉ xác nhận UAC nếu bạn tin cậy nguồn phát hành.'
        ],'MSIX cần chữ ký được Windows tin cậy. Kiểm tra Properties → Digital Signatures khi có chữ ký; tên hiển thị trong metadata không tự chứng minh tệp đã được ký.');
    } else if (platform === 'ios') {
      appendSteps(parent,'Cách cài đặt iPhone / iPad',[
        'Ưu tiên nút mở App Store hoặc TestFlight nếu nhà phát hành cung cấp.',
        'Với OTA, thiết bị phải phù hợp với provisioning profile và chính sách phân phối của ứng dụng.',
        'Nếu chỉ có IPA, hãy dùng đúng kênh phân phối do nhà phát hành cấp; không thể cài IPA tùy ý trên mọi iPhone.'
      ],'IPA không phải tệp cài trực tiếp phổ thông. Ad Hoc cần đăng ký thiết bị; Enterprise cần cấu hình phân phối hợp lệ. Không tin cậy hồ sơ doanh nghiệp không rõ nguồn.');
    } else if (platform === 'macos') {
      appendSteps(parent,'Cách cài đặt macOS',[
        'Tải DMG hoặc PKG, mở tệp và làm theo hướng dẫn.',
        'Chỉ tiếp tục khi nhà phát hành và chữ ký được macOS xác minh.',
        'Nếu macOS cảnh báo ứng dụng không rõ nhà phát triển, hãy dừng lại và xác minh với nhà phát hành.'
      ],'Không bỏ qua Gatekeeper cho gói cài đặt không rõ nguồn.');
    }
  }
  function addChecksum(parent,item) {
    const row = document.createElement('div');
    row.className = 'detail-checksum';
    const label = document.createElement('small');
    label.textContent = 'SHA-256';
    const value = document.createElement('code');
    value.textContent = item.sha256 || '—';
    const copy = document.createElement('button');
    copy.type = 'button';
    copy.textContent = 'Sao chép';
    copy.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(String(item.sha256 || ''));
        copy.textContent = 'Đã sao chép';
      } catch (_) { toast('Trình duyệt không cho phép sao chép tự động.'); }
    });
    row.append(label,value,copy);
    parent.append(row);
  }
  function showDetails(item) {
    const modal = byId('release-modal');
    const content = byId('modal-content');
    if (!modal || !content) return;
    state.previousFocus = document.activeElement;
    state.activeRelease = item;
    content.replaceChildren();

    const heading = document.createElement('div');
    heading.className = 'detail-heading';
    heading.append(iconFor(item,'detail-icon'));
    const title = document.createElement('div');
    title.className = 'detail-title';
    const h2 = document.createElement('h2');
    h2.id = 'modal-title';
    h2.textContent = escapeText(item.name || 'Ứng dụng HENDY');
    const sub = document.createElement('p');
    sub.textContent = 'v' + String(item.version || '—') + ' · ' + (platformNames[item.platform] || item.platform) + ' · ' + String(item.channel || 'stable').toUpperCase();
    title.append(h2,sub);
    heading.append(title);
    content.append(heading);

    const stats = document.createElement('div');
    stats.className = 'detail-stats';
    [
      categoryLabel(item.category),String(item.filename || ''),formatBytes(item.sizeBytes),
      item.architecture || 'Universal',item.minimumOs ? 'OS tối thiểu: ' + item.minimumOs : ''
    ].filter(Boolean).forEach(value => {
      const chip = document.createElement('span');
      chip.textContent = value;
      stats.append(chip);
    });
    content.append(stats);

    const description = document.createElement('p');
    description.className = 'detail-copy';
    description.textContent = escapeText(item.description || 'Thông tin ứng dụng do HENDY quản lý.');
    content.append(description);

    if (Array.isArray(item.screenshots) && item.screenshots.length) {
      const strip = document.createElement('div');
      strip.className = 'screenshot-strip';
      item.screenshots.slice(0,8).forEach((raw,index) => {
        const href = policy.safeHttps(typeof raw === 'string' ? raw : raw?.url);
        if (!href) return;
        const image = document.createElement('img');
        image.src = href;
        image.alt = (item.name || 'Ứng dụng') + ' · ảnh ' + (index + 1);
        image.loading = 'lazy';
        image.referrerPolicy = 'no-referrer';
        strip.append(image);
      });
      if (strip.childElementCount) content.append(strip);
    }

    if (item.publisherName) {
      const publisher = document.createElement('p');
      publisher.className = 'detail-copy';
      publisher.textContent = 'Nhà phát hành khai báo: ' + escapeText(item.publisherName);
      content.append(publisher);
    }
    if (item.packageId) {
      const packageLine = document.createElement('p');
      packageLine.className = 'detail-copy';
      packageLine.textContent = (item.platform === 'ios' ? 'Bundle ID: ' : 'Package ID: ') + escapeText(item.packageId);
      content.append(packageLine);
    }
    if (item.releaseNotes) {
      const notesHeading = document.createElement('h3');
      notesHeading.className = 'detail-section-title';
      notesHeading.textContent = 'Ghi chú phát hành';
      const notes = document.createElement('p');
      notes.className = 'detail-copy';
      notes.textContent = escapeText(item.releaseNotes);
      content.append(notesHeading,notes);
    }
    if (item.installNotes) {
      const installNotes = document.createElement('p');
      installNotes.className = 'detail-copy';
      installNotes.textContent = 'Ghi chú từ Admin: ' + escapeText(item.installNotes);
      content.append(installNotes);
    }

    appendDistributionActions(content,item);
    addChecksum(content,item);
    const update = document.createElement('section');
    update.className = 'update-check';
    const updateTitle = document.createElement('h3');
    updateTitle.textContent = 'Kiểm tra phiên bản mới';
    const row = document.createElement('div');
    row.className = 'update-row';
    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 64;
    input.placeholder = 'Phiên bản đang cài, ví dụ 1.0.0';
    input.setAttribute('aria-label','Phiên bản đang cài');
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Kiểm tra';
    const result = document.createElement('p');
    result.className = 'update-result';
    button.addEventListener('click',async () => {
      button.disabled = true;
      result.textContent = 'Đang kiểm tra...';
      try {
        const response = await storeApi.checkUpdate(item.slug || item.id,input.value,item.platform);
        result.textContent = response.hasUpdate
          ? 'Có phiên bản mới: ' + response.latestVersion + '. Hãy tải gói mới ở phần tải xuống phía trên.'
          : 'Bạn đang sử dụng phiên bản mới nhất (' + response.latestVersion + ').';
      } catch (error) {
        result.textContent = error.message || 'Không kiểm tra được phiên bản.';
      } finally { button.disabled = false; }
    });
    row.append(input,button);
    update.append(updateTitle,row,result);
    content.append(update);

    modal.hidden = false;
    document.body.classList.add('modal-open');
    $('.modal-close',modal)?.focus();
  }
  function closeModal() {
    const modal = byId('release-modal');
    if (modal) modal.hidden = true;
    document.body.classList.remove('modal-open');
    if (state.previousFocus && typeof state.previousFocus.focus === 'function') state.previousFocus.focus();
  }

  async function loadReleases() {
    const refresh = byId('refresh-catalog');
    if (refresh) refresh.disabled = true;
    setApiStatus('Đang kết nối kho', 'pending');
    try {
      const payload = await storeApi.listReleases();
      state.releases = payload.releases.filter(policy.validRelease);
      setApiStatus('API đã kết nối', 'online');
      renderCatalog();
    } catch (error) {
      state.releases = [];
      setApiStatus('Không kết nối được API', 'offline');
      const grid = byId('app-grid');
      if (grid) grid.replaceChildren(createEmpty(
        'Kho ứng dụng tạm thời chưa kết nối',
        error.message || 'Kiểm tra Backend Render, CORS và PostgreSQL rồi thử lại.',
        '!'
      ));
      const count = byId('catalog-count');
      if (count) count.textContent = 'Không tải được danh mục';
    } finally { if (refresh) refresh.disabled = false; }
  }

  const detected = detectDevice();
  const deviceHint = byId('device-hint');
  if (deviceHint) deviceHint.textContent = 'Thiết bị hiện tại: ' + (platformNames[detected] || 'Trình duyệt') + '. Chọn nền tảng khác để xem gói tương thích.';
  byId('year').textContent = String(new Date().getFullYear());
  byId('search-input')?.addEventListener('input',renderCatalog);
  byId('sort-select')?.addEventListener('change',renderCatalog);
  byId('refresh-catalog')?.addEventListener('click',loadReleases);
  $$('[data-platform]').forEach(button => button.addEventListener('click',() => setFilter('platform',button.dataset.platform || 'all')));
  $$('[data-category]').forEach(button => button.addEventListener('click',() => setFilter('category',button.dataset.category || 'all')));
  $$('[data-close-modal]').forEach(button => button.addEventListener('click',closeModal));
  document.addEventListener('keydown',event => {
    if (event.key === 'Escape' && !byId('release-modal')?.hidden) closeModal();
    if (event.key === '/' && !['INPUT','TEXTAREA'].includes(document.activeElement?.tagName)) {
      event.preventDefault();
      byId('search-input')?.focus();
    }
  });
  byId('mobile-menu-toggle')?.addEventListener('click',event => {
    const nav = $('.main-nav');
    const open = nav.classList.toggle('open');
    event.currentTarget.setAttribute('aria-expanded',String(open));
  });
  loadReleases();
})();
