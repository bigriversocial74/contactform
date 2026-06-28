(() => {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
  }

  function activeVoucherId(form) {
    const direct = form && form.querySelector('[name="action_item_id"],[data-action-item-id]');
    const directValue = direct && (direct.value || direct.getAttribute('data-action-item-id'));
    if (directValue) return String(directValue).trim();
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
          <p>Preparing a merchant-ready signed voucher QR and manual claim fallback.</p>
        </div>
        <div class="mg-action-form-note">Generating scanner payload…</div>
        <div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button></div>
      </section>`;
  }

  function manualClaimForm(voucherId, token, disabled) {
    return `
      <form class="mg-claim-voucher-manual" data-voucher-claim-form data-action-item-id="${esc(voucherId)}" data-voucher-token="${esc(token || '')}">
        <div class="mg-claim-voucher-manual-copy">
          <span>Merchant-only fallback</span>
          <strong>Manual claim code</strong>
          <p>If the merchant scanner cannot read the QR, the merchant can enter their active location claim code here. The value is masked, never displayed back, cleared immediately after submit, logged, and rate-limited.</p>
        </div>
        <label class="mg-claim-voucher-code-label">
          <span>Merchant claim code</span>
          <input type="password" name="merchant_claim_code" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Merchant-only claim code" ${disabled ? 'disabled' : ''} required>
        </label>
        <div class="mg-claim-voucher-manual-actions">
          <button class="mg-btn mg-btn-primary" type="submit" data-voucher-claim-submit ${disabled ? 'disabled' : ''}>Verify & claim</button>
        </div>
        <div class="mg-claim-voucher-status" data-voucher-claim-status role="status" aria-live="polite"></div>
      </form>`;
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
        ${manualClaimForm(voucherId, '', true)}
        <div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button></div>
      </section>`;
  }

  function voucherMarkup(title, voucherId, data) {
    const payload = data.scan_payload || '';
    const qrImage = data.qr_image_url || '';
    const token = data.token || '';
    const expires = data.expires_at ? ` Signed QR expires ${esc(new Date(data.expires_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }))}.` : '';
    const copy = data.is_wallet_reward
      ? 'Show this QR code to the merchant. The QR contains a signed, short-lived wallet reward token.'
      : 'Show this QR code to the merchant. The QR contains a signed, short-lived voucher token.';
    return `
      <section class="mg-claim-voucher-qr" aria-label="Merchant scan voucher QR">
        <div class="mg-claim-voucher-copy">
          <span>Customer voucher QR</span>
          <strong>${esc((data.voucher && data.voucher.title) || title)}</strong>
          <p>${copy} The merchant scanner verifies the merchant location before the claim is recorded.${expires}</p>
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
        ${manualClaimForm(voucherId, token, false)}
        <div class="mg-action-form-note">Merchant flow: scan customer QR or use the merchant-only fallback → verify authorized merchant/location → record a single redemption. Already-claimed gifts cannot be claimed again unless a refund has reversed the prior redemption.</div>
        <div class="mg-action-form-footer">
          <button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button>
        </div>
      </section>`;
  }

  function successMarkup(data, message) {
    const gift = (data && data.gift) || {};
    return `
      <div class="mg-action-success mg-claim-voucher-success">
        <strong>${esc(message || 'Gift claimed successfully.')}</strong>
        <p>${esc(gift.title || 'The voucher')} was verified and claimed${data && data.location_name ? ' at ' + esc(data.location_name) : ''}. The gift has moved into the claimed state and cannot be claimed again unless a refund reverses this redemption.</p>
        <button class="mg-btn mg-btn-primary" type="button" data-action-modal-close>Done</button>
      </div>`;
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
    const voucherId = activeVoucherId(form);
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

  function setStatus(form, message, state) {
    const status = form.querySelector('[data-voucher-claim-status]');
    if (!status) return;
    status.textContent = message || '';
    status.dataset.state = state || '';
  }

  async function submitManualClaim(event, form) {
    event.preventDefault();
    const voucherId = form.getAttribute('data-action-item-id') || '';
    const token = form.getAttribute('data-voucher-token') || '';
    const input = form.querySelector('[name="merchant_claim_code"]');
    const button = form.querySelector('[data-voucher-claim-submit]');
    const merchantClaimCode = input ? input.value.trim() : '';
    if (input) input.value = '';
    if (!voucherId || !merchantClaimCode) {
      setStatus(form, 'Enter the merchant claim code to verify and claim this gift.', 'error');
      return;
    }
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function') {
      setStatus(form, 'Microgifter API client is unavailable.', 'error');
      return;
    }
    if (button) {
      button.disabled = true;
      button.textContent = 'Claiming…';
    }
    if (input) input.disabled = true;
    setStatus(form, 'Verifying merchant claim code…', 'loading');
    try {
      const response = await window.Microgifter.post('/api/account/action-center-voucher-claim.php', {
        action_item_id: voucherId,
        merchant_claim_code: merchantClaimCode,
        voucher_token: token
      });
      const payload = response && response.data ? response.data : response;
      const message = response && response.message ? response.message : 'Gift claimed successfully.';
      const container = form.closest('[data-action-form="claim"]') || form.closest('.mg-claim-voucher-qr') || form;
      container.innerHTML = successMarkup(payload, message);
      document.dispatchEvent(new CustomEvent('mg:action-center:voucher-claimed', { detail: payload }));
      const refresh = document.querySelector('[data-gift-refresh]');
      if (refresh) window.setTimeout(() => refresh.click(), 650);
    } catch (error) {
      if (button) {
        button.disabled = false;
        button.textContent = 'Verify & claim';
      }
      if (input) {
        input.disabled = false;
        input.focus();
      }
      setStatus(form, error.message || 'Unable to claim this gift right now.', 'error');
    }
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

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-voucher-claim-form]');
    if (!form) return;
    submitManualClaim(event, form);
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scanForClaimForms, { once: true });
  } else {
    scanForClaimForms();
  }
  new MutationObserver(scanForClaimForms).observe(document.documentElement, { childList: true, subtree: true });
})();