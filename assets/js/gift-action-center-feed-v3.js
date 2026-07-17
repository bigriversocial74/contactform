(() => {
  'use strict';

  if (window.__mgGiftActionCenterFeedV3Booted) return;
  window.__mgGiftActionCenterFeedV3Booted = true;

  const app = document.querySelector('[data-gift-center]');
  const list = app && app.querySelector('[data-gift-list]');
  if (!app || !list) return;

  const folders = ['inbox', 'sent', 'claimed'];
  const cache = { inbox: new Map(), sent: new Map(), claimed: new Map() };
  const loading = { inbox: null, sent: null, claimed: null };
  let activeFolder = folders.includes(app.dataset.initialFolder) ? app.dataset.initialFolder : 'inbox';
  let renderFrame = 0;

  const esc = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  function safeUrl(value) {
    const url = String(value == null ? '' : value).trim();
    if (!url || /[\u0000-\u001f\u007f]/.test(url)) return '';
    if (url.startsWith('/') && !url.startsWith('//')) return url;
    if (/^https?:\/\//i.test(url)) return url;
    return '';
  }

  function boolValue(value, fallback) {
    if (value === undefined || value === null || value === '') return fallback;
    if (value === true || value === 1 || value === '1' || value === 'true') return true;
    if (value === false || value === 0 || value === '0' || value === 'false') return false;
    return Boolean(value);
  }

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
    return date.toLocaleDateString(undefined, {
      month: 'short', day: 'numeric', year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
    });
  }

  function metadataObject(item) {
    if (!item) return {};
    const source = item.metadata_json || item.instance_metadata_json || item.metadata || {};
    if (source && typeof source === 'object') return source;
    try {
      const decoded = JSON.parse(String(source || ''));
      return decoded && typeof decoded === 'object' ? decoded : {};
    } catch (error) {
      return {};
    }
  }

  function firstText() {
    for (let index = 0; index < arguments.length; index += 1) {
      const value = String(arguments[index] == null ? '' : arguments[index]).trim();
      if (value) return value;
    }
    return '';
  }

  function businessNameFor(item) {
    const metadata = metadataObject(item);
    return firstText(
      item.business_name,
      item.storefront_name,
      metadata.business_name,
      metadata.businessName,
      metadata.storefront_name,
      metadata.storefrontName,
      metadata.merchant_display_name,
      metadata.merchantDisplayName,
      metadata.brand_name,
      metadata.brandName,
      item.merchant_name,
      'Microgifter'
    );
  }

  function senderNameFor(item) {
    return firstText(item.sender_name, item.sent_from_name, businessNameFor(item), 'Microgifter');
  }

  function normalizeItem(item, folder) {
    const adapter = window.MicrogifterActionCenterContract;
    const source = adapter && typeof adapter.view === 'function' ? adapter.view(item) : item;
    const normalized = Object.assign({}, source || {});
    normalized.folder = normalized.folder || folder;
    normalized.template_name = normalized.template_name || normalized.title || 'Microgift';
    normalized.business_name = businessNameFor(normalized);
    normalized.merchant_name = normalized.business_name;
    normalized.sender_name = senderNameFor(normalized);
    normalized.message = normalized.message || normalized.description || 'Gift ready to open';
    normalized.view_count = Math.max(0, Number(normalized.view_count || normalized.views || normalized.open_count || 0));
    normalized.avatar_url = safeUrl(normalized.product_image_url || normalized.avatar_url || normalized.merchant_avatar_url || normalized.reward_image_url || normalized.image_url || '');
    normalized.can_send = boolValue(normalized.can_send, false);
    normalized.can_claim = boolValue(normalized.can_claim, false);
    normalized.can_load = boolValue(normalized.can_load, true);
    normalized.can_follow_up = boolValue(normalized.can_follow_up, false);
    normalized.can_message = boolValue(normalized.can_message, false);
    normalized.can_tip = boolValue(normalized.can_tip, false);
    return normalized;
  }

  function fallbackItem(row) {
    const title = row.querySelector('h3');
    const message = row.querySelector('.mg-gift-card-message, .mg-gift-row-main p');
    const business = row.querySelector('.mg-gift-business-name');
    const image = row.querySelector('.mg-gift-thumb img');
    return normalizeItem({
      action_item_id: row.dataset.giftId || '',
      folder: activeFolder,
      template_name: title ? title.textContent.trim() : 'Microgift',
      message: message ? message.textContent.trim() : 'Gift ready to open',
      business_name: row.dataset.feedBusiness || (business ? business.textContent.trim() : ''),
      merchant_name: row.dataset.feedBusiness || (business ? business.textContent.trim() : ''),
      sender_name: row.dataset.feedSender || '',
      location_name: row.dataset.feedLocation || '',
      activity_label: row.dataset.feedActivity || '',
      view_count: Number(row.dataset.feedViews || 0),
      avatar_url: image ? image.getAttribute('src') || '' : '',
      source_system: row.dataset.giftSourceSystem || '',
      source_label: row.dataset.giftSourceLabel || '',
      source_detail: row.dataset.giftSourceDetail || '',
      source_reference: row.dataset.giftSourceReference || ''
    }, activeFolder);
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
        scheduleRebuild();
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
      sender: '<circle cx="12" cy="8" r="3"/><path d="M5.5 20c.8-4 3-6 6.5-6s5.7 2 6.5 6"/>',
      time: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      views: '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>'
    };
    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + paths[type] + '</svg>';
  }

  function actionButton(action, label, disabled, title) {
    return '<button class="mg-gift-row-action" type="button" data-gift-action="' + esc(action) + '"' +
      (disabled ? ' disabled' : '') + (title ? ' title="' + esc(title) + '"' : '') + '>' + esc(label) + '</button>';
  }

  function unavailableReason(item, capability, fallback) {
    const reasons = item && item.capability_reasons && typeof item.capability_reasons === 'object' ? item.capability_reasons : {};
    return firstText(reasons[capability], fallback);
  }

  function actionsMarkup(item, folder) {
    if (folder === 'inbox') {
      return actionButton('send', 'Regift', !item.can_send, unavailableReason(item, 'send', 'This gift cannot be transferred.')) +
        actionButton('claim', 'Claim', !item.can_claim, unavailableReason(item, 'claim', 'This gift cannot be claimed.')) +
        actionButton('load', 'Load', !item.can_load, unavailableReason(item, 'load', 'Gift content is unavailable.'));
    }
    if (folder === 'sent') {
      return actionButton('follow-up', 'Follow Up', !item.can_follow_up, unavailableReason(item, 'follow_up', 'Only the most recent sender can follow up.')) +
        actionButton('load', 'Load', !item.can_load, unavailableReason(item, 'load', 'Gift content is unavailable.'));
    }
    return actionButton('message', 'Message', !item.can_message, unavailableReason(item, 'message', 'Messaging is unavailable for this gift.')) +
      actionButton('tip', 'Tip', !item.can_tip, unavailableReason(item, 'tip', 'Tip is unavailable for this gift.')) +
      actionButton('load', 'Load', !item.can_load, unavailableReason(item, 'load', 'Gift content is unavailable.'));
  }

  function thumbMarkup(item, row) {
    const existingImage = row.querySelector('.mg-gift-thumb img');
    const imageUrl = safeUrl(item.product_image_url || (existingImage && existingImage.getAttribute('src')) || item.avatar_url || item.merchant_avatar_url || '');
    if (imageUrl) return '<img src="' + esc(imageUrl) + '" alt="" loading="lazy">';
    return '<span>' + esc(String(item.template_name || item.business_name || 'M').charAt(0).toUpperCase()) + '</span>';
  }

  function rebuildRow(row) {
    if (!row || !row.dataset.giftId || row.dataset.feedV3Rebuilding === 'true') return;
    row.dataset.feedV3Rebuilding = 'true';

    const folder = activeFolder;
    const id = String(row.dataset.giftId);
    const item = cache[folder].get(id) || fallbackItem(row);
    if (!cache[folder].has(id)) cache[folder].set(id, item);

    const active = row.classList.contains('is-active');
    const demo = row.classList.contains('is-demo') || Boolean(item.is_demo);
    const timestamp = timestampFor(item, folder);
    const activity = timestamp ? relativeTime(timestamp) : firstText(item.activity_label, 'Recently');
    const views = Math.max(0, Number(item.view_count || 0));
    const business = businessNameFor(item);
    const sender = senderNameFor(item);
    const thumb = thumbMarkup(item, row);

    row.className = 'mg-gift-row mg-gift-card-v3' + (active ? ' is-active' : '') + (demo ? ' is-demo' : '');
    row.dataset.feedV3 = 'true';
    row.dataset.feedFolder = folder;
    row.dataset.feedBusiness = business;
    row.dataset.feedSender = sender;
    row.dataset.feedLocation = item.location_name || '';
    row.dataset.feedViews = String(views);
    row.dataset.feedActivity = timestamp || item.activity_label || '';

    if (!row.dataset.giftSourceSystem && item.source_system) row.dataset.giftSourceSystem = String(item.source_system);
    if (!row.dataset.giftSourceLabel && item.source_label) row.dataset.giftSourceLabel = String(item.source_label);
    if (!row.dataset.giftSourceDetail && item.source_detail) row.dataset.giftSourceDetail = String(item.source_detail);
    if (!row.dataset.giftSourceReference && item.source_reference) row.dataset.giftSourceReference = String(item.source_reference);

    row.innerHTML =
      '<div class="mg-gift-thumb mg-gift-card-v3-thumb" aria-hidden="true">' + thumb + '</div>' +
      '<div class="mg-gift-row-main mg-gift-card-v3-copy">' +
        '<div class="mg-gift-card-v3-title"><h3>' + esc(item.template_name || 'Microgift') + '</h3></div>' +
        '<span class="mg-gift-business-name">' + esc(business) + '</span>' +
        '<p class="mg-gift-card-message">' + esc(item.message || 'Gift ready to open') + '</p>' +
        '<div class="mg-gift-row-meta mg-gift-card-v3-meta">' +
          '<span class="mg-feed-meta-item is-sender">' + icon('sender') + '<span>Sent from ' + esc(sender) + '</span></span>' +
          '<span class="mg-feed-meta-item is-time">' + icon('time') + '<span>' + esc(activity) + '</span></span>' +
          '<span class="mg-feed-meta-item is-views">' + icon('views') + '<span>' + esc(String(views)) + '</span></span>' +
        '</div>' +
      '</div>' +
      '<div class="mg-gift-row-actions mg-gift-card-v3-actions" aria-label="Gift actions">' + actionsMarkup(item, folder) + '</div>';

    delete row.dataset.feedV3Rebuilding;
  }

  function rebuildAll() {
    renderFrame = 0;
    activeFolder = folderFromPath();
    list.querySelectorAll(':scope > [data-gift-id]').forEach(rebuildRow);
    list.dataset.feedV3Ready = 'true';
  }

  function scheduleRebuild() {
    if (renderFrame) return;
    renderFrame = window.requestAnimationFrame(rebuildAll);
  }

  new MutationObserver(scheduleRebuild).observe(list, { childList: true });

  document.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-system-tab]');
    if (tab && folders.includes(tab.dataset.systemTab)) {
      activeFolder = tab.dataset.systemTab;
      requestFolder(activeFolder, false);
      window.setTimeout(scheduleRebuild, 0);
    }
    if (event.target.closest('[data-gift-refresh]')) {
      window.setTimeout(() => requestFolder(activeFolder, true), 80);
    }
  }, true);

  window.addEventListener('popstate', () => {
    activeFolder = folderFromPath();
    requestFolder(activeFolder, false);
    scheduleRebuild();
  });

  window.MicrogifterGiftFeedV3 = {
    getFolder: () => activeFolder,
    getItem: (id, folder) => cache[folder || activeFolder].get(String(id || '')) || null,
    loadFolder: (folder, force) => requestFolder(folder || activeFolder, Boolean(force)),
    relativeTime,
    rebuild: scheduleRebuild
  };

  requestFolder(activeFolder, false);
  scheduleRebuild();
})();
