(() => {
  'use strict';
  if (window.__mgGiftEnvelopePresentationBooted) return;
  window.__mgGiftEnvelopePresentationBooted = true;

  const esc = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  function setCardOpen(card, open) {
    if (!card) return;
    card.classList.toggle('is-open', open);
    card.setAttribute('data-envelope-state', open ? 'open' : 'closed');
  }

  function metaValue(row, label) {
    const key = String(label || '').toLowerCase() + ':';
    for (const span of row.querySelectorAll('.mg-gift-row-meta span')) {
      const text = span.textContent.trim();
      if (text.toLowerCase().indexOf(key) === 0) return text.slice(text.indexOf(':') + 1).trim();
    }
    return '';
  }

  function rowText(row, selector, fallback) {
    const node = row.querySelector(selector);
    return node ? node.textContent.trim() : fallback;
  }

  function envelopeMarkup(row) {
    const title = rowText(row, '.mg-gift-row-main h3', 'Microgift');
    const message = rowText(row, '.mg-gift-row-main p', 'A gift is waiting for you.');
    const value = metaValue(row, 'Value') || '$0.00';
    const status = metaValue(row, 'Status') || 'Received';
    const giftId = metaValue(row, 'Gift ID') || row.dataset.giftId || '';
    const type = metaValue(row, 'Type') || 'Gift envelope';
    return '<div class="mg-envelope-card" data-envelope-card data-envelope-state="closed">' +
      '<section class="mg-envelope-stage"><div class="mg-envelope-book">' +
        '<div class="mg-envelope-page mg-envelope-page-left"><div class="mg-envelope-media"></div><div class="mg-envelope-content mg-envelope-cover-content"><div class="mg-envelope-icon">✉</div><h2>' + esc(title) + '</h2><p>' + esc(type) + '</p><button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Gift</button></div></div>' +
        '<div class="mg-envelope-page mg-envelope-page-right"><div class="mg-envelope-inside-media"></div><div class="mg-envelope-content mg-envelope-inside"><span class="mg-eyebrow">Gift message</span><h3>' + esc(title) + '</h3><p>' + esc(message) + '</p><div class="mg-envelope-value">' + esc(value) + '</div></div></div>' +
      '</div></section>' +
      '<div class="mg-envelope-controls"><button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Card</button><button class="mg-envelope-close-button" type="button" data-envelope-action="hide">Close Card</button></div>' +
      '<div class="mg-envelope-voucher-strip"><div><span>Protected claim controls</span><strong>' + esc(status) + '</strong><small>Gift ID ' + esc(giftId || 'available after issue') + '. Product details stay in the main feed card.</small></div><div class="mg-envelope-claim-pill">' + esc(value) + '</div></div>' +
    '</div>';
  }

  window.addEventListener('click', (event) => {
    const loadButton = event.target.closest('[data-gift-action="load"]');
    if (!loadButton) return;
    const app = document.querySelector('[data-gift-center]');
    const row = loadButton.closest('[data-gift-id]');
    if (!app || !row || !app.contains(row)) return;
    const drawer = app.querySelector('[data-gift-drawer]');
    const drawerContent = app.querySelector('[data-gift-drawer-content]');
    const drawerTitle = app.querySelector('[data-gift-drawer-title]');
    const backdrop = app.querySelector('[data-gift-drawer-backdrop]');
    if (!drawer || !drawerContent || !backdrop) return;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    if (drawerTitle) drawerTitle.textContent = 'Loaded gift envelope';
    drawerContent.innerHTML = envelopeMarkup(row);
    drawer.classList.add('is-open', 'mg-load-envelope-drawer');
    drawer.setAttribute('aria-hidden', 'false');
    backdrop.hidden = false;
    document.body.classList.add('mg-modal-lock');
    drawerContent.scrollTop = 0;
  }, true);

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-envelope-action]');
    if (!button) return;
    const card = button.closest('[data-envelope-card]');
    if (!card) return;
    event.preventDefault();
    setCardOpen(card, button.dataset.envelopeAction === 'show');
  });

  document.addEventListener('click', (event) => {
    if (event.target.closest('button,a,input,textarea,select,audio,video')) return;
    const stage = event.target.closest('.mg-envelope-stage');
    if (!stage) return;
    const card = stage.closest('[data-envelope-card]');
    if (!card) return;
    const rect = stage.getBoundingClientRect();
    const isLeftSide = (event.clientX - rect.left) < rect.width / 2;
    const open = card.classList.contains('is-open');
    if (!open) setCardOpen(card, true);
    else setCardOpen(card, !isLeftSide);
  });
})();
