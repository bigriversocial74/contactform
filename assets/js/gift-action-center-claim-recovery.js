(() => {
  'use strict';

  const RECOVERABLE_TOKEN_ERROR = /(qr|voucher token|token payload|network|fetch|temporar|unavailable|server)/i;
  const NON_RECOVERABLE_ITEM_ERROR = /(voucher id is required|not attached to an active action center voucher|voucher not found|action center voucher not found)/i;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    })[character]);
  }

  function recoveryMarkup(flow, message) {
    const stepper = flow.querySelector('.mg-claim-stepper');
    const product = flow.querySelector('.mg-claim-product-card');
    const safeMessage = message || 'The QR voucher could not be prepared.';

    return `<form class="mg-claim-flow" data-claim-step="claim" data-manual-claim-recovery>
      ${stepper ? stepper.outerHTML : ''}
      ${product ? product.outerHTML : ''}
      <div class="mg-claim-qr-frame">
        <div class="mg-claim-qr-placeholder">QR unavailable · manual claim remains available</div>
      </div>
      <div class="mg-claim-note">
        <strong>Use the merchant claim code.</strong>
        The code is still checked by the authorized merchant, location, ownership, expiration, lockout, and duplicate-redemption rules.
      </div>
      <label class="mg-claim-code-label">
        <span>Manual merchant claim code</span>
        <input type="password" name="merchant_claim_code" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Enter claim code" required>
      </label>
      <div class="mg-claim-actions is-split">
        <button class="mg-btn mg-btn-soft" type="button" data-claim-retry>Retry QR</button>
        <button class="mg-btn mg-btn-primary" type="submit">Review claim</button>
      </div>
      <div class="mg-claim-status" data-claim-status data-state="error" role="status" aria-live="polite">${escapeHtml(safeMessage)} Enter the merchant claim code or retry the QR.</div>
    </form>`;
  }

  function recoverClaimModal(modal) {
    if (!modal || modal.getAttribute('aria-hidden') === 'true') return;

    const flow = modal.querySelector('.mg-claim-flow[data-claim-step="error"]');
    if (!flow || flow.dataset.manualRecoveryProcessed === 'true') return;

    const messageNode = flow.querySelector('.mg-claim-error-card p');
    const message = String(messageNode ? messageNode.textContent : '').trim();
    if (NON_RECOVERABLE_ITEM_ERROR.test(message) || !RECOVERABLE_TOKEN_ERROR.test(message)) return;

    flow.dataset.manualRecoveryProcessed = 'true';
    flow.outerHTML = recoveryMarkup(flow, message);

    const input = modal.querySelector('[data-manual-claim-recovery] input[name="merchant_claim_code"]');
    if (input) window.setTimeout(() => input.focus({ preventScroll: true }), 0);
  }

  function scan() {
    document.querySelectorAll('[data-claim-modal]').forEach(recoverClaimModal);
  }

  function boot() {
    scan();
    const observer = new MutationObserver(scan);
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
