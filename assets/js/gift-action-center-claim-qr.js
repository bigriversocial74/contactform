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

  function loadingMarkup(title) {
    return `
      <section class="mg-claim-voucher-qr" aria-label="Merchant scan voucher QR">
        <div class="mg-claim-voucher-copy">
          <span>Customer voucher QR</span>
          <strong>${esc(title)}</strong>
          <p>Preparing a signed voucher QR for the merchant scanner.</p>
        </div>
        <div class="mg-action-form-note">Generating scanner payload…</div>
        <div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button></div>
      </section>`;
  }

  function errorMarkup(title, message, voucherId) {
    return `
      <section class="mg-claim-voucher-qr" aria-label="Merchant scan voucher QR">
        <div class="mg-claim-voucher-copy">
          <span>Customer voucher QR</span>
          <strong>${esc(title)}</strong>
          <p>${esc(message || 'Unable to prepare the scanner QR right now.')}</p>
        </div>
        <div class="mg-claim-voucher-id">
          <span>Manual voucher ID</span>
          <code>${esc(voucherId)}</code>
          <button class="mg-btn mg-btn-soft" type="button" data-copy-voucher-id="${esc(voucherId)}">Copy ID</button>
        </div>
        <div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button></div>
      </section>`;
  }

  function voucherMarkup(title, voucherId, data) {
    const payload = data.scan_payload || '';
    const qrImage = data.qr_image_url || '';
    const expires = data.expires_at ? ` Signed QR expires ${esc(new Date(data.expires_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }))}.` : '';
    return `
      <section class="mg-claim-voucher-qr" aria-label="Merchant scan voucher QR">
        <div class="mg-claim-voucher-copy">
          <span>Customer voucher QR</span>
          <strong>${esc((data.voucher && data.voucher.title) || title)}</strong>
          <p>Show this QR code to the merchant. The QR contains a signed, short-lived voucher token. The merchant scanner applies the selected location claim code automatically.${expires}</p>
        </div>
        <div class="mg-claim-voucher-frame">
          <img src="${esc(qrImage)}" alt="QR code for ${esc(title)}" loading="eager" referrerpolicy="no-referrer">
        </div>
        <div class="mg-claim-voucher-id">
          <span>Voucher ID</span>
          <code>${esc(voucherId)}</code>
          <button class="mg-btn mg-btn-soft" type="button" data-copy-voucher-id="${esc(voucherId)}">Copy ID</button>
        </div>
        <input type="hidden" data-voucher-scan-payload value="${esc(payload)}">
        <div class="mg-action-form-note">Merchant scanner flow: scan customer QR → verify selected merchant location → apply that location's active claim code → record redemption and notifications.</div>
        <div class="mg-action-form-footer">
          <button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button>
        </div>
      </section>`;
  }

  async function prepareVoucher(form, voucherId, title) {
    try {
      if (!window.Microgifter || typeof window.Microgifter.get !== 'function') {
        throw new Error('Microgifter API client is unavailable.');
      }
      const response = await window.Microgifter.get('/api/account/action-center-voucher-token.php?action_item_id=' + encodeURIComponent(voucherId));
      const data = response && response.data ? response.data : response;
      if (!data || !data.scan_payload || !data.qr_image_url) {
        throw new Error('Voucher QR payload was not returned.');
      }
      form.innerHTML = voucherMarkup(title, voucherId, data);
    } catch (error) {
      form.innerHTML = errorMarkup(title, error.message, voucherId);
    }
  }

  function enhanceClaimVoucher(form) {
    if (!form || form.dataset.claimVoucherQr === 'true') return;
    const voucherId = activeVoucherId();
    if (!voucherId) return;
    const modal = form.closest('[data-action-modal]') || document;
    const title = modal.querySelector('[data-action-modal-eyebrow]')?.textContent?.trim() || 'Microgift voucher';
    form.dataset.claimVoucherQr = 'true';
    form.innerHTML = loadingMarkup(title);
    prepareVoucher(form, voucherId, title);
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
