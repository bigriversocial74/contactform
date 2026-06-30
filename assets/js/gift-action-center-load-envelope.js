(() => {
  'use strict';
  if (window.__mgGiftLoadEnvelopeBooted) return;
  window.__mgGiftLoadEnvelopeBooted = true;

  document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-gift-center]');
    if (!app) return;

    const drawer = app.querySelector('[data-gift-drawer]');
    const drawerContent = app.querySelector('[data-gift-drawer-content]');
    const drawerTitle = app.querySelector('[data-gift-drawer-title]');
    const backdrop = app.querySelector('[data-gift-drawer-backdrop]');
    if (!drawer || !drawerContent || !backdrop) return;

    function esc(value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      })[char]);
    }

    function metaValue(row, label) {
      const wanted = String(label || '').toLowerCase();
      const spans = Array.from(row.querySelectorAll('.mg-gift-row-meta span'));
      for (const span of spans) {
        const text = span.textContent.trim();
        const lower = text.toLowerCase();
        if (lower.indexOf(wanted + ':') === 0) return text.slice(text.indexOf(':') + 1).trim();
      }
      return '';
    }

    function titleFromRow(row) {
      const node = row.querySelector('.mg-gift-row-main h3');
      return node ? node.textContent.trim() : 'Microgift';
    }

    function messageFromRow(row) {
      const node = row.querySelector('.mg-gift-row-main p');
      return node ? node.textContent.trim() : 'A gift is waiting for you.';
    }

    function merchantFromRow(row) {
      return metaValue(row, 'From') || metaValue(row, 'Merchant') || 'Microgifter';
    }

    function imageFromRow(row) {
      const img = row.querySelector('.mg-gift-thumb img');
      const src = img ? img.getAttribute('src') : '';
      if (!src) return '';
      if (src.charAt(0) === '/' || /^https?:\/\//i.test(src)) return src;
      return '';
    }

    function statusLabel(row) {
      const status = metaValue(row, 'Status') || 'Received';
      return status.charAt(0).toUpperCase() + status.slice(1);
    }

    function cardMarkup(row) {
      const title = titleFromRow(row);
      const message = messageFromRow(row) || 'A gift is waiting for you.';
      const merchant = merchantFromRow(row);
      const value = metaValue(row, 'Value') || '$0.00';
      const location = metaValue(row, 'Location') || 'Participating locations';
      const expires = metaValue(row, 'Expires') || 'No expiration';
      const giftId = metaValue(row, 'Gift ID') || row.getAttribute('data-gift-id') || '';
      const status = statusLabel(row);
      const image = imageFromRow(row);
      const media = image
        ? '<div class="mg-gift-card-envelope-media"><img src="' + esc(image) + '" alt="' + esc(title) + ' media"></div>'
        : '';

      return '<div class="mg-gift-drawer-card mg-load-envelope-card" data-load-envelope-card>' +
        '<span class="mg-eyebrow">Protected voucher</span>' +
        '<section class="mg-gift-card-preview">' +
          '<div class="mg-gift-card-hero"><span class="mg-eyebrow">' + esc(merchant) + '</span>' +
            '<h2>' + esc(title) + '</h2><p>' + esc(message) + '</p></div>' +
          '<div class="mg-gift-card-body">' + media +
            '<div class="mg-gift-value">' + esc(value) + '</div>' +
            '<div class="mg-gift-claim-code"><span>Merchant claim</span><strong>Ready</strong>' +
              '<small>Present the gift at the merchant. The authorized location claim code is entered into this voucher and recorded with a timestamp.</small></div>' +
            '<div class="mg-gift-meta"><div><span>Status</span><strong>' + esc(status) + '</strong></div>' +
              '<div><span>Location</span><strong>' + esc(location) + '</strong></div>' +
              '<div><span>Gift ID</span><strong>' + esc(giftId) + '</strong></div>' +
              '<div><span>Expires</span><strong>' + esc(expires) + '</strong></div></div>' +
          '</div>' +
        '</section>' +
      '</div>';
    }

    function openEnvelope(row) {
      const title = titleFromRow(row);
      if (drawerTitle) drawerTitle.textContent = title;
      drawerContent.innerHTML = cardMarkup(row);
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden', 'false');
      backdrop.hidden = false;
      document.body.classList.add('mg-modal-lock');
      drawerContent.scrollTop = 0;
    }

    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-gift-action="load"]');
      if (!button) return;
      const row = button.closest('[data-gift-id]');
      if (!row || !app.contains(row)) return;
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      openEnvelope(row);
    }, true);
  });
})();
