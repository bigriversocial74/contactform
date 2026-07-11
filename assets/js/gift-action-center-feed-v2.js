(() => {
  'use strict';
  if (window.__mgGiftActionCenterFeedV2Booted) return;
  window.__mgGiftActionCenterFeedV2Booted = true;

  const app = document.querySelector('[data-gift-center]');
  if (!app) return;
  const list = app.querySelector('[data-gift-list]');
  if (!list) return;

  const folders = ['inbox', 'sent', 'claimed'];
  const cache = { inbox: new Map(), sent: new Map(), claimed: new Map() };
  const loading = { inbox: null, sent: null, claimed: null };
  let activeFolder = folders.includes(app.dataset.initialFolder) ? app.dataset.initialFolder : 'inbox';
  let renderFrame = 0;

  const esc = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  function folderFromPath() {
    const name = String(window.location.pathname || '').split('/').pop().replace(/\.php$/i, '');
    return folders.includes(name) ? name : activeFolder;
  }

  function timestampFor(item, folder) {
    if (!item) return '';
    if (folder === 'inbox') return item.received_at || item.first_received_at || item.sent_at || item.updated_at || '';
    if (folder === 'sent') return item.sent_at || item.last_delivery_event_at || item.updated_at || '';
    return item.redeemed_at || item.merchant_redeemed_at || item.claimed_at || item.updated_at || '';
  }

  function relativeTime(value) {
    if (!value) return 'Recently';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes + 'm ago';
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours + 'h ago';
    const days = Math.floor(hours / 24);
    if (days < 7) return days + 'd ago';
    const weeks = Math.floor(days / 7);
    if (weeks < 5) return weeks + 'w ago';
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined });
  }

  function fallbackItem(row) {
    const original = {};
    row.querySelectorAll('.mg-gift-row-meta span').forEach((span) => {
      const text = span.textContent.trim();
      const index = text.indexOf(':');
      if (index < 1) return;
      original[text.slice(0, index).trim().toLowerCase()] = text.slice(index + 1).trim();
    });
    const title = row.querySelector('.mg-gift-row-main h3');
    const message = row.querySelector('.mg-gift-row-main p');
    const status = row.querySelector('.mg-gift-status');
    const image = row.querySelector('.mg-gift-thumb img');
    return {
      action_item_id: row.dataset.giftId || '',
      folder: activeFolder,
      template_name: title ? title.textContent.trim() : 'Microgift',
      message: message ? message.textContent.trim() : '',
      merchant_name: image && image.alt ? image.alt.replace(/\s+profile$/i, '') : (original.merchant || original.from || 'Microgifter'),
      sender_name: original.from || '',
      recipient_name: original.to || '',
      location_name: original.location || 'Participating location',
      product_type: original.type || 'Microgift',
      state: original.status || (status ? status.textContent.trim() : activeFolder),
      face_value_label: original.value || '',
      expires_at: original.expires || '',
      activity_label: original.received || original.sent || original.claimed || 'Recently',
      view_count: 0,
      instance_id: original['gift id'] || '',
      avatar_url: image ? image.getAttribute('src') || '' : ''
    };
  }

  function normalizeItem(item, folder) {
    const normalized = Object.assign({}, item || {});
    normalized.folder = normalized.folder || folder;
    normalized.template_name = normalized.template_name || normalized.title || 'Microgift';
    normalized.merchant_name = normalized.merchant_name || normalized.sender_name || 'Microgifter';
    normalized.location_name = normalized.location_name || 'Participating location';
    normalized.product_type = normalized.product_type || normalized.source_detail || normalized.source_label || 'Microgift';
    normalized.view_count = Math.max(0, Number(normalized.view_count || normalized.views || normalized.open_count || 0));
    normalized.avatar_url = normalized.merchant_avatar_url || normalized.avatar_url || '';
    return normalized;
  }

  async function requestFolder(folder, force) {
    if (!folders.includes(folder)) folder = activeFolder;
    if (!force && cache[folder].size) return Array.from(cache[folder].values());
    if (loading[folder]) return loading[folder];
    loading[folder] = (async () => {
      try {
        let payload;
        if (window.Microgifter && typeof window.Microgifter.get === 'function') {
          const response = await window.Microgifter.get('/api/account/action-center.php?folder=' + encodeURIComponent(folder) + '&limit=100');
          payload = response && response.data ? response.data : response;
        } else {
          const response = await fetch('/api/account/action-center.php?folder=' + encodeURIComponent(folder) + '&limit=100', {
            credentials: 'same-origin', headers: { Accept: 'application/json' }
          });
          if (!response.ok) throw new Error('Unable to load Action Center feed.');
          const json = await response.json();
          payload = json && json.data ? json.data : json;
        }
        const items = payload && Array.isArray(payload.items) ? payload.items : [];
        if (items.length || app.dataset.demoEnabled !== 'true') cache[folder].clear();
        items.forEach((item) => {
          const normalized = normalizeItem(item, folder);
          if (normalized.action_item_id) cache[folder].set(String(normalized.action_item_id), normalized);
        });
        scheduleApply();
        return Array.from(cache[folder].values());
      } catch (error) {
        console.error(error);
        return [];
      } finally {
        loading[folder] = null;
      }
    })();
    return loading[folder];
  }

  function icon(type) {
    const paths = {
      location: '<path d="M12 21s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/>',
      time: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      views: '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>'
    };
    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + paths[type] + '</svg>';
  }

  function badgeLabel(item, folder) {
    const type = String(item.product_type || '').trim();
    if (type && !/^pppm gift$/i.test(type)) return type;
    const state = String(item.state || folder || 'Microgift').replace(/[_-]+/g, ' ').trim();
    return state || 'Microgift';
  }

  function applyRow(row) {
    if (!row || !row.dataset.giftId) return;
    const folder = activeFolder;
    let item = cache[folder].get(String(row.dataset.giftId));
    if (!item) {
      item = fallbackItem(row);
      cache[folder].set(String(row.dataset.giftId), item);
    }
    const meta = row.querySelector('.mg-gift-row-meta');
    const badge = row.querySelector('.mg-gift-status');
    const actions = row.querySelector('.mg-gift-row-actions');
    if (!meta || !actions) return;

    const timestamp = timestampFor(item, folder);
    const activity = timestamp ? relativeTime(timestamp) : (item.activity_label || 'Recently');
    const views = Math.max(0, Number(item.view_count || 0));
    meta.innerHTML =
      '<span class="mg-feed-meta-item is-location">' + icon('location') + '<span>' + esc(item.location_name || 'Participating location') + '</span></span>' +
      '<span class="mg-feed-meta-item is-time">' + icon('time') + '<span>' + esc(activity) + '</span></span>' +
      '<span class="mg-feed-meta-item is-views">' + icon('views') + '<span>' + esc(String(views)) + '</span></span>';

    if (badge) {
      badge.textContent = badgeLabel(item, folder);
      badge.classList.toggle('is-claimed', folder === 'claimed');
    }

    actions.querySelectorAll('.mg-gift-row-action').forEach((button) => button.classList.remove('is-primary'));
    if (folder === 'claimed' && !actions.querySelector('[data-gift-action="load"]')) {
      actions.insertAdjacentHTML('beforeend', '<button class="mg-gift-row-action" type="button" data-gift-action="load">Load</button>');
    }

    row.classList.add('mg-gift-row-v2');
    row.dataset.feedV2 = 'true';
    row.dataset.feedFolder = folder;
    row.dataset.feedLocation = item.location_name || '';
    row.dataset.feedViews = String(views);
    row.dataset.feedActivity = timestamp || item.activity_label || '';
  }

  function applyAll() {
    renderFrame = 0;
    activeFolder = folderFromPath();
    list.querySelectorAll('[data-gift-id]').forEach(applyRow);
  }

  function scheduleApply() {
    if (renderFrame) return;
    renderFrame = window.requestAnimationFrame(applyAll);
  }

  new MutationObserver(scheduleApply).observe(list, { childList: true });

  document.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-system-tab]');
    if (tab && folders.includes(tab.dataset.systemTab)) {
      activeFolder = tab.dataset.systemTab;
      requestFolder(activeFolder, false);
      window.setTimeout(scheduleApply, 0);
    }
    if (event.target.closest('[data-gift-refresh]')) {
      window.setTimeout(() => requestFolder(activeFolder, true), 80);
    }
  }, true);

  window.addEventListener('popstate', () => {
    activeFolder = folderFromPath();
    requestFolder(activeFolder, false);
    scheduleApply();
  });

  window.MicrogifterGiftFeedV2 = {
    getFolder: () => activeFolder,
    getItem: (id, folder) => cache[folder || activeFolder].get(String(id || '')) || null,
    loadFolder: (folder, force) => requestFolder(folder || activeFolder, !!force),
    relativeTime
  };

  requestFolder(activeFolder, false);
  scheduleApply();
})();
