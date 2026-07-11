(() => {
  'use strict';
  if (window.__mgGiftEnvelopePresentationBooted) return;
  window.__mgGiftEnvelopePresentationBooted = true;

  const esc = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  function money(item) {
    if (item.face_value_label) return item.face_value_label;
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: item.currency || 'USD' })
        .format(Number(item.face_value_cents || 0) / 100);
    } catch (error) {
      return String(item.currency || 'USD') + ' ' + (Number(item.face_value_cents || 0) / 100).toFixed(2);
    }
  }

  function dateLabel(value) {
    if (!value) return 'Recently';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString(undefined, {
      month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
    });
  }

  function timestampFor(item, folder) {
    if (item.activity_label) return item.activity_label;
    if (folder === 'inbox') return dateLabel(item.received_at || item.first_received_at || item.sent_at || item.updated_at);
    if (folder === 'sent') return dateLabel(item.sent_at || item.last_delivery_event_at || item.updated_at);
    return dateLabel(item.redeemed_at || item.merchant_redeemed_at || item.claimed_at || item.updated_at);
  }

  function rowFallback(row, folder) {
    const title = row.querySelector('.mg-gift-row-main h3');
    const message = row.querySelector('.mg-gift-row-main p');
    const image = row.querySelector('.mg-gift-thumb img');
    const status = row.querySelector('.mg-gift-status');
    const business = row.querySelector('.mg-gift-business-name');
    return {
      action_item_id: row.dataset.giftId || '',
      folder,
      template_name: title ? title.textContent.trim() : 'Microgift',
      message: message ? message.textContent.trim() : '',
      merchant_name: row.dataset.feedBusiness || (business ? business.textContent.trim() : '') || (image && image.alt ? image.alt.replace(/\s+profile$/i, '') : 'Microgifter'),
      sender_name: row.dataset.feedSender || '',
      location_name: row.dataset.feedLocation || 'Participating location',
      activity_label: row.dataset.feedActivity || 'Recently',
      view_count: Number(row.dataset.feedViews || 0),
      state: status ? status.textContent.trim() : folder,
      avatar_url: image ? image.getAttribute('src') || '' : '',
      source_system: row.dataset.giftSourceSystem || '',
      source_label: row.dataset.giftSourceLabel || '',
      source_detail: row.dataset.giftSourceDetail || '',
      source_reference: row.dataset.giftSourceReference || ''
    };
  }

  function mergeRowMetadata(item, row) {
    const merged = Object.assign({}, item || {});
    if (!merged.merchant_name && row.dataset.feedBusiness) merged.merchant_name = row.dataset.feedBusiness;
    if (!merged.sender_name && row.dataset.feedSender) merged.sender_name = row.dataset.feedSender;
    if (!merged.location_name && row.dataset.feedLocation) merged.location_name = row.dataset.feedLocation;
    if (!merged.activity_label && row.dataset.feedActivity) merged.activity_label = row.dataset.feedActivity;
    if (!merged.view_count && row.dataset.feedViews) merged.view_count = Number(row.dataset.feedViews || 0);
    if (row.dataset.giftSourceSystem) merged.source_system = row.dataset.giftSourceSystem;
    if (row.dataset.giftSourceLabel) merged.source_label = row.dataset.giftSourceLabel;
    if (row.dataset.giftSourceDetail) merged.source_detail = row.dataset.giftSourceDetail;
    if (row.dataset.giftSourceReference) merged.source_reference = row.dataset.giftSourceReference;
    return merged;
  }

  function detail(label, value) {
    if (value === undefined || value === null || String(value).trim() === '') return '';
    return '<div class="mg-load-detail-item"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function avatarMarkup(item, row) {
    const image = item.avatar_url || item.merchant_avatar_url || (row.querySelector('.mg-gift-thumb img') || {}).src || '';
    if (image) return '<img src="' + esc(image) + '" alt="">';
    return esc(String(item.merchant_name || item.template_name || 'M').charAt(0).toUpperCase());
  }

  function detailMarkup(item, row, folder) {
    const value = money(item);
    const views = Math.max(0, Number(item.view_count || item.views || item.open_count || 0));
    const business = item.merchant_name || item.business_name || 'Microgifter';
    const sender = item.sender_name || business;
    const source = item.source_label || item.source_system || item.source_type || 'Microgifter';
    const sourceDetail = item.source_detail || '';
    const sourceReference = item.source_reference || '';
    const status = item.state || item.instance_status || folder;
    const type = item.product_type || item.source_type || item.source_detail || 'Microgift';
    const activity = timestampFor(item, folder);
    const details = [
      detail('Business', business),
      detail('Sent From', sender),
      detail('Sent To', item.recipient_name || ''),
      detail('Location', item.location_name || 'Participating location'),
      detail('Activity', activity),
      detail('Views', String(views)),
      detail('Type', type),
      detail('Status', status),
      detail('Expires', item.expires_at || 'No expiration'),
      detail('Gift ID', item.instance_id || item.action_item_id || ''),
      detail('Source', source),
      detail('Source Detail', sourceDetail),
      detail('Source Reference', sourceReference),
      detail('Follow Ups', Number(item.follow_up_count || 0) > 0 ? String(item.follow_up_count) : ''),
      detail('Last Follow Up', item.last_follow_up_at ? dateLabel(item.last_follow_up_at) : '')
    ].filter(Boolean).join('');

    return '<div class="mg-load-detail-shell">' +
      '<section class="mg-load-summary-card">' +
        '<div class="mg-load-summary-avatar">' + avatarMarkup(item, row) + '</div>' +
        '<div class="mg-load-summary-copy"><span class="mg-eyebrow">' + esc(business) + '</span>' +
          '<h2>' + esc(item.template_name || 'Microgift') + '</h2>' +
          '<p>' + esc(item.message || 'Gift details and protected PPPM metadata.') + '</p></div>' +
      '</section>' +
      '<div class="mg-load-value-status"><strong>' + esc(value) + '</strong><span>' + esc(status) + '</span></div>' +
      '<section class="mg-load-detail-section"><span>Gift metadata</span><div class="mg-load-detail-grid">' + details + '</div></section>' +
      (item.message ? '<section class="mg-load-message"><span>Message</span><p>' + esc(item.message) + '</p></section>' : '') +
    '</div>';
  }

  function overlayParts() {
    return {
      drawer: document.querySelector('[data-gift-drawer]'),
      content: document.querySelector('[data-gift-drawer-content]'),
      title: document.querySelector('[data-gift-drawer-title]'),
      backdrop: document.querySelector('[data-gift-drawer-backdrop]')
    };
  }

  function openDrawer(parts, title, markup) {
    if (!parts.drawer || !parts.content || !parts.backdrop) return false;
    if (parts.title) parts.title.textContent = title || 'Loaded gift details';
    parts.content.innerHTML = markup;
    parts.drawer.classList.add('is-open', 'mg-load-envelope-drawer');
    parts.drawer.setAttribute('aria-hidden', 'false');
    parts.backdrop.hidden = false;
    document.body.classList.add('mg-modal-lock');
    parts.content.scrollTop = 0;
    return true;
  }

  window.addEventListener('click', async (event) => {
    const loadButton = event.target.closest('[data-gift-action="load"]');
    if (!loadButton) return;
    const app = document.querySelector('[data-gift-center]');
    const row = loadButton.closest('[data-gift-id]');
    if (!app || !row || !app.contains(row)) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const parts = overlayParts();
    if (!openDrawer(parts, 'Loaded gift details', '<div class="mg-load-loading">Loading gift details…</div>')) return;

    const controller = window.MicrogifterGiftFeedV2;
    const folder = controller && controller.getFolder ? controller.getFolder() : (row.dataset.feedFolder || app.dataset.initialFolder || 'inbox');
    let item = controller && controller.getItem ? controller.getItem(row.dataset.giftId, folder) : null;
    if (!item && controller && controller.loadFolder) {
      await controller.loadFolder(folder, false);
      item = controller.getItem(row.dataset.giftId, folder);
    }
    item = mergeRowMetadata(item || rowFallback(row, folder), row);
    if (!parts.content || !parts.content.isConnected) return;
    if (parts.title) parts.title.textContent = item.template_name || 'Loaded gift details';
    parts.content.innerHTML = detailMarkup(item, row, folder);
    parts.content.scrollTop = 0;
  }, true);
})();
