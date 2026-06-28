(() => {
  'use strict';

  const KEY = 'mg:action-center:claimed-v1';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
  }

  function stepper() {
    const labels = ['Claim', 'Confirm', 'Claimed', 'Actions'];
    return '<ol class="mg-voucher-stepper" aria-label="Claim progress">' + labels.map((label, index) => {
      const state = index < 2 ? 'complete' : (index === 2 ? 'active' : 'pending');
      return '<li class="is-' + state + '"><span>' + (index + 1) + '</span><strong>' + esc(label) + '</strong></li>';
    }).join('') + '</ol>';
  }

  function saveClaim(detail) {
    try {
      const data = detail || {};
      const gift = data.gift || {};
      const location = data.location || {};
      sessionStorage.setItem(KEY, JSON.stringify({
        ts: Date.now(),
        action_item_id: data.action_item_id || '',
        redemption_id: data.redemption_id || '',
        attempt_id: data.attempt_id || '',
        title: gift.title || data.title || 'Gift claimed',
        value_cents: gift.value_cents || 0,
        currency: gift.currency || 'USD',
        location_id: (location.id || data.location_id || ''),
        location_name: (location.name || data.location_name || 'Merchant location'),
        merchant_notification_id: data.merchant_notification_id || '',
        merchant_alert_id: data.merchant_alert_id || '',
        is_wallet_reward: !!data.is_wallet_reward
      }));
    } catch (error) {}
  }

  function claimedUrl(data) {
    const params = new URLSearchParams();
    if (data && data.redemption_id) params.set('claimed', data.redemption_id);
    if (data && data.action_item_id) params.set('item', data.action_item_id);
    return '/claimed.php' + (params.toString() ? '?' + params.toString() : '');
  }

  function restorePayload() {
    try {
      const raw = sessionStorage.getItem(KEY);
      if (!raw) return null;
      const data = JSON.parse(raw);
      if (!data || Date.now() - Number(data.ts || 0) > 5 * 60 * 1000) {
        sessionStorage.removeItem(KEY);
        return null;
      }
      sessionStorage.removeItem(KEY);
      return data;
    } catch (error) {
      sessionStorage.removeItem(KEY);
      return null;
    }
  }

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(cents || 0) / 100);
    } catch (error) {
      return String(currency || 'USD') + ' ' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function openConfirmation(data) {
    const app = document.querySelector('[data-gift-center]');
    const modal = app && app.querySelector('[data-action-modal]');
    const backdrop = app && app.querySelector('[data-action-modal-backdrop]');
    const title = modal && modal.querySelector('[data-action-modal-title]');
    const eyebrow = modal && modal.querySelector('[data-action-modal-eyebrow]');
    const body = modal && modal.querySelector('[data-action-modal-body]');
    if (!modal || !body) return false;
    if (eyebrow) eyebrow.textContent = data.is_wallet_reward ? 'Wallet reward claimed' : 'Microgift claimed';
    if (title) title.textContent = 'Claim confirmed';
    body.innerHTML = '<section class="mg-claim-voucher-qr" data-restored-claim-confirmation>' +
      stepper() +
      '<section class="mg-claim-voucher-success-card">' +
      '<div class="mg-claim-voucher-success-icon" aria-hidden="true">✓</div>' +
      '<strong>Gift claimed successfully</strong>' +
      '<p>' + esc(data.title) + ' was claimed at ' + esc(data.location_name) + '.</p>' +
      '</section>' +
      '<div class="mg-claim-voucher-review-grid">' +
      '<div><span>Product</span><strong>' + esc(data.title) + '</strong></div>' +
      '<div><span>Value</span><strong>' + esc(money(data.value_cents, data.currency)) + '</strong></div>' +
      '<div><span>Location</span><strong>' + esc(data.location_name) + '</strong></div>' +
      '<div><span>Redemption</span><strong>' + esc(data.redemption_id || 'Recorded') + '</strong></div>' +
      '<div><span>Merchant dashboard</span><strong>' + esc(data.merchant_notification_id || data.merchant_alert_id ? 'Notification queued' : 'Recorded') + '</strong></div>' +
      '</div>' +
      '<div class="mg-action-form-footer mg-claim-voucher-step-actions">' +
      '<button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Done</button>' +
      '<a class="mg-btn mg-btn-primary" href="/merchant-claims.php?redemption=' + encodeURIComponent(data.redemption_id || '') + '">Merchant claim record</a>' +
      '</div>' +
      '</section>';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    if (backdrop) backdrop.hidden = false;
    document.body.classList.add('mg-modal-lock');
    return true;
  }

  document.addEventListener('mg:action-center:voucher-claimed', (event) => {
    const detail = event.detail || {};
    if (detail.demo_preview) return;
    saveClaim(detail);
    window.setTimeout(() => {
      window.location.href = claimedUrl(detail);
    }, 850);
  });

  function boot() {
    const data = restorePayload();
    if (!data) return;
    window.setTimeout(() => openConfirmation(data), 350);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
