(() => {
  'use strict';

  function text(node) {
    return node && node.textContent ? node.textContent.trim() : '';
  }

  function metaValue(row, labels) {
    const pieces = Array.from(row.querySelectorAll('.mg-gift-row-meta span')).map((span) => text(span));
    for (const label of labels) {
      const found = pieces.find((piece) => piece.toLowerCase().startsWith(label.toLowerCase() + ':'));
      if (found) return found.replace(new RegExp('^' + label + ':\\s*', 'i'), '').trim();
    }
    return '';
  }

  function itemFromRow(row) {
    const id = row && row.dataset ? String(row.dataset.giftId || '').trim() : '';
    const title = text(row.querySelector('h3')) || 'Microgift voucher';
    const merchant = metaValue(row, ['Merchant', 'From']) || 'Participating merchant';
    const value = metaValue(row, ['Value']);
    const item = {
      action_item_id: id,
      template_name: title,
      title,
      merchant_name: merchant,
      business_name: merchant,
      sender_name: merchant,
      claim_code: 'DEMO',
      is_demo: row.classList.contains('is-demo') || /^demo-/i.test(id)
    };
    if (value) item.claim_value_label = value;
    return item;
  }

  function boot() {
    const app = document.querySelector('[data-gift-center]');
    if (!app) return;

    app.addEventListener('click', (event) => {
      const action = event.target.closest('[data-gift-action="claim"]');
      if (!action || !app.contains(action)) return;
      const row = action.closest('[data-gift-id]');
      if (!row) return;

      event.preventDefault();
      event.stopPropagation();
      if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();

      app.dispatchEvent(new CustomEvent('mg:gift-claim:open', {
        bubbles: true,
        cancelable: true,
        detail: { item: itemFromRow(row), row }
      }));
    }, true);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
