(() => {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
  }

  function activeVoucherId() {
    const app = document.querySelector('[data-gift-center]');
    const row = app && app.querySelector('.mg-gift-row.is-active[data-gift-id]');
    return row && row.dataset ? String(row.dataset.giftId || '').trim() : '';
  }

  function voucherPayload(voucherId) {
    return `${window.location.origin}/claim-voucher.php?gift=${encodeURIComponent(voucherId)}`;
  }

  function qrImageUrl(payload) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=12&format=svg&data=${encodeURIComponent(payload)}`;
  }

  function enhanceClaimVoucher(form) {
    if (!form || form.dataset.claimVoucherQr === 'true') return;
    const voucherId = activeVoucherId();
    if (!voucherId) return;
    const modal = form.closest('[data-action-modal]') || document;
    const title = modal.querySelector('[data-action-modal-eyebrow]')?.textContent?.trim() || 'Microgift voucher';
    const payload = voucherPayload(voucherId);
    form.dataset.claimVoucherQr = 'true';
    form.innerHTML = `
      <section class="mg-claim-voucher-qr" aria-label="Merchant scan voucher QR">
        <div class="mg-claim-voucher-copy">
          <span>Customer voucher QR</span>
          <strong>${esc(title)}</strong>
          <p>Show this QR code to the merchant. The QR contains this gift's unique voucher ID. The merchant scanner applies the selected location claim code automatically.</p>
        </div>
        <div class="mg-claim-voucher-frame">
          <img src="${esc(qrImageUrl(payload))}" alt="QR code for ${esc(title)}" loading="eager" referrerpolicy="no-referrer">
        </div>
        <div class="mg-claim-voucher-id">
          <span>Voucher ID</span>
          <code>${esc(voucherId)}</code>
          <button class="mg-btn mg-btn-soft" type="button" data-copy-voucher-id="${esc(voucherId)}">Copy ID</button>
        </div>
        <div class="mg-action-form-note">Merchant scanner flow: scan customer QR → verify selected merchant location → apply that location's active claim code → record redemption and notifications.</div>
        <div class="mg-action-form-footer">
          <button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button>
        </div>
      </section>`;
  }

  function scanForClaimForms() {
    document.querySelectorAll('[data-action-form="claim"]').forEach(enhanceClaimVoucher);
  }

  document.addEventListener('click', async (event) => {
    const copyButton = event.target.closest('[data-copy-voucher-id]');
    if (!copyButton) return;
    event.preventDefault();
    const value = copyButton.getAttribute('data-copy-voucher-id') || '';
    try {
      await navigator.clipboard.writeText(value);
      copyButton.textContent = 'Copied';
      window.setTimeout(() => { copyButton.textContent = 'Copy ID'; }, 1600);
    } catch (error) {
      copyButton.textContent = 'Copy failed';
      window.setTimeout(() => { copyButton.textContent = 'Copy ID'; }, 1600);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scanForClaimForms, { once: true });
  } else {
    scanForClaimForms();
  }
  new MutationObserver(scanForClaimForms).observe(document.documentElement, { childList: true, subtree: true });
})();
