(() => {
  'use strict';

  const flowState = new WeakMap();

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

  function activeRowContext() {
    const row = document.querySelector('[data-gift-center] .mg-gift-row.is-active[data-gift-id]');
    if (!row) return {};
    const title = row.querySelector('h3')?.textContent?.trim() || '';
    const meta = Array.from(row.querySelectorAll('.mg-gift-row-meta span')).map((span) => span.textContent.trim());
    const merchantMeta = meta.find((piece) => /^Merchant:/i.test(piece)) || meta.find((piece) => /^From:/i.test(piece));
    const merchant = merchantMeta ? merchantMeta.replace(/^(Merchant|From):\s*/i, '').trim() : '';
    return { title, merchant };
  }

  function activeContext(form, data, fallbackTitle) {
    const modal = form.closest('[data-action-modal]') || document;
    const row = activeRowContext();
    const title = (data && data.voucher && data.voucher.title) || form.dataset.productTitle || row.title || modal.querySelector('[data-action-modal-eyebrow]')?.textContent?.trim() || fallbackTitle || 'Microgift';
    const merchant = (data && data.voucher && (data.voucher.merchant_name || data.voucher.location_name)) || form.dataset.merchantName || row.merchant || 'Participating merchant';
    const value = form.dataset.giftValue || '';
    return { title, merchant, value };
  }

  function stepper(current) {
    const steps = [
      ['claim', 'Claim'],
      ['confirm', 'Confirm'],
      ['claimed', 'Claimed'],
      ['actions', 'Actions']
    ];
    const currentIndex = Math.max(0, steps.findIndex((step) => step[0] === current));
    return `<ol class="mg-voucher-stepper" aria-label="Claim progress">${steps.map((step, index) => {
      const state = index < currentIndex ? 'complete' : (index === currentIndex ? 'active' : 'pending');
      return `<li class="is-${state}"><span>${index + 1}</span><strong>${esc(step[1])}</strong></li>`;
    }).join('')}</ol>`;
  }

  function loadingMarkup(title) {
    return `
      <section class="mg-claim-voucher-qr" aria-label="Merchant scan voucher QR">
        ${stepper('claim')}
        <div class="mg-claim-voucher-product">
          <strong>${esc(title)}</strong>
          <span>Preparing QR…</span>
        </div>
        <div class="mg-action-form-note">Generating scanner payload…</div>
      </section>`;
  }

  function rootState(root) {
    return flowState.get(root) || {};
  }

  function setRootState(root, next) {
    flowState.set(root, Object.assign({}, rootState(root), next));
  }

  function statusMarkup(message, state) {
    return `<div class="mg-claim-voucher-status" data-voucher-claim-status data-state="${esc(state || '')}" role="status" aria-live="polite">${esc(message || '')}</div>`;
  }

  function friendlyClaimError(error) {
    const raw = (error && error.message ? error.message : '').trim();
    if (/invalid merchant claim code|invalid claim code|wrong|claim code/i.test(raw)) {
      return 'Wrong merchant claim code. Check the code and try again.';
    }
    if (/too many|temporarily locked|locked/i.test(raw)) {
      return raw || 'Too many invalid claim-code attempts. Try again later.';
    }
    return raw || 'Unable to claim this gift right now.';
  }

  function claimStepMarkup(state, statusMessage, statusState) {
    const disabled = !state.qrImage;
    return `
      ${stepper('claim')}
      <div class="mg-claim-voucher-product">
        <strong>${esc(state.title)}</strong>
        <span>${esc(state.merchant)}</span>
      </div>
      <div class="mg-claim-voucher-frame">
        ${state.qrImage ? `<img src="${esc(state.qrImage)}" alt="QR code for ${esc(state.title)}" loading="eager" referrerpolicy="no-referrer">` : '<div class="mg-claim-voucher-qr-placeholder">QR unavailable</div>'}
      </div>
      <div class="mg-claim-voucher-manual" data-voucher-claim-panel>
        <label class="mg-claim-voucher-code-label">
          <span>Manual claim code</span>
          <input type="password" name="merchant_claim_code" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Enter claim code" ${disabled ? 'disabled' : ''} required>
        </label>
        <div class="mg-claim-voucher-manual-actions">
          <button class="mg-btn mg-btn-primary" type="button" data-voucher-review-claim ${disabled ? 'disabled' : ''}>Review claim</button>
        </div>
        ${statusMarkup(statusMessage || '', statusState || '')}
      </div>`;
  }

  function confirmStepMarkup(state) {
    return `
      ${stepper('confirm')}
      <div class="mg-claim-voucher-product">
        <strong>${esc(state.title)}</strong>
        <span>${esc(state.merchant)}</span>
      </div>
      <section class="mg-claim-voucher-confirm-card">
        <div class="mg-claim-voucher-confirm-icon" aria-hidden="true">✓</div>
        <div>
          <strong>Confirm claim</strong>
          <p>Manual claim code entered. Confirm to submit this gift claim to the merchant redemption system.</p>
        </div>
      </section>
      <div class="mg-claim-voucher-review-grid">
        <div><span>Product</span><strong>${esc(state.title)}</strong></div>
        <div><span>Merchant</span><strong>${esc(state.merchant)}</strong></div>
        <div><span>Claim code</span><strong>••••••</strong></div>
      </div>
      <div class="mg-action-form-footer mg-claim-voucher-step-actions">
        <button class="mg-btn mg-btn-soft" type="button" data-voucher-back>Back</button>
        <button class="mg-btn mg-btn-primary" type="button" data-voucher-confirm-claim>Confirm claim</button>
      </div>
      ${statusMarkup('', '')}`;
  }

  function claimedStepMarkup(state, payload, message) {
    const location = payload && payload.location_name ? payload.location_name : state.merchant;
    return `
      ${stepper('claimed')}
      <section class="mg-claim-voucher-success-card">
        <div class="mg-claim-voucher-success-icon" aria-hidden="true">✓</div>
        <strong>${esc(message || 'Gift claimed successfully.')}</strong>
        <p>${esc(state.title)} was claimed${location ? ' at ' + esc(location) : ''}.</p>
      </section>
      <div class="mg-claim-voucher-review-grid">
        <div><span>Product</span><strong>${esc(state.title)}</strong></div>
        <div><span>Merchant</span><strong>${esc(location || state.merchant)}</strong></div>
        <div><span>Status</span><strong>Claimed</strong></div>
      </div>
      <div class="mg-action-form-footer mg-claim-voucher-step-actions">
        <button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Done</button>
        <button class="mg-btn mg-btn-primary" type="button" data-voucher-actions>Continue</button>
      </div>`;
  }

  function actionsStepMarkup() {
    return `
      ${stepper('actions')}
      <section class="mg-claim-voucher-success-card is-actions">
        <div class="mg-claim-voucher-success-icon" aria-hidden="true">✓</div>
        <strong>Claim complete</strong>
        <p>Continue with a message or send a tip using the existing Microgifter flows.</p>
      </section>
      <div class="mg-claim-voucher-next-actions">
        <button type="button" class="mg-claim-voucher-action-card" data-voucher-next-action="message">
          <span aria-hidden="true">💬</span>
          <strong>Message</strong>
          <small>Open the message thread</small>
        </button>
        <button type="button" class="mg-claim-voucher-action-card" data-voucher-next-action="tip">
          <span aria-hidden="true">💸</span>
          <strong>Send tip</strong>
          <small>Open the tipping flow</small>
        </button>
      </div>
      <div class="mg-action-form-footer mg-claim-voucher-step-actions">
        <button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Done</button>
      </div>`;
  }

  function renderStep(root, step, extra) {
    const state = rootState(root);
    root.dataset.voucherStep = step;
    if (step === 'claim') root.innerHTML = claimStepMarkup(state, extra && extra.message, extra && extra.state);
    if (step === 'confirm') root.innerHTML = confirmStepMarkup(state);
    if (step === 'claimed') root.innerHTML = claimedStepMarkup(state, extra && extra.payload, extra && extra.message);
    if (step === 'actions') root.innerHTML = actionsStepMarkup(state);
  }

  function errorMarkup(title, message) {
    return `
      <section class="mg-claim-voucher-qr" aria-label="Merchant scan voucher QR">
        ${stepper('claim')}
        <div class="mg-claim-voucher-product">
          <strong>${esc(title)}</strong>
          <span>QR unavailable</span>
        </div>
        ${statusMarkup(message || 'Unable to prepare the scanner QR right now.', 'error')}
        <div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Close</button></div>
      </section>`;
  }

  function voucherMarkup(title, voucherId, data, form) {
    const context = activeContext(form, data, title);
    const state = {
      actionItemId: voucherId,
      title: context.title,
      merchant: context.merchant,
      value: context.value,
      token: data.token || '',
      qrImage: data.qr_image_url || '',
      payload: data.scan_payload || '',
      claimResponse: null,
      pendingClaimCode: ''
    };
    const markup = `<section class="mg-claim-voucher-qr" data-voucher-flow data-action-item-id="${esc(voucherId)}" data-voucher-token="${esc(state.token)}" aria-label="Claim gift QR and manual claim code"></section>`;
    window.setTimeout(() => {
      const selectorId = window.CSS && CSS.escape ? CSS.escape(voucherId) : String(voucherId).replace(/"/g, '\\"');
      const root = document.querySelector('[data-voucher-flow][data-action-item-id="' + selectorId + '"]');
      if (!root) return;
      setRootState(root, state);
      renderStep(root, 'claim');
    }, 0);
    return markup;
  }

  function actionFormMarkup(action, state) {
    if (action === 'tip') {
      return '<form class="mg-action-form" data-action-form="tip">' +
        '<label>Tip amount<input type="number" name="amount" placeholder="5.00" required></label>' +
        '<label>Message<textarea name="message" placeholder="Add a thank-you note"></textarea></label>' +
        '<div class="mg-action-form-note">Tip for ' + esc(state.merchant) + ' after claiming ' + esc(state.title) + '.</div>' +
        '<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button>' +
        '<button class="mg-btn mg-btn-primary" type="submit">Continue to tip</button></div></form>';
    }
    return '<form class="mg-action-form" data-action-form="message">' +
      '<label>To<input type="text" name="recipient" value="' + esc(state.merchant) + '" required></label>' +
      '<label>Message<textarea name="message" placeholder="Write a message" required></textarea></label>' +
      '<div class="mg-action-form-note">Message thread for ' + esc(state.title) + '.</div>' +
      '<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button>' +
      '<button class="mg-btn mg-btn-primary" type="submit">Send message</button></div></form>';
  }

  function openNextAction(root, action) {
    const state = rootState(root);
    const modal = root.closest('[data-action-modal]') || document;
    const title = modal.querySelector('[data-action-modal-title]');
    const eyebrow = modal.querySelector('[data-action-modal-eyebrow]');
    const body = modal.querySelector('[data-action-modal-body]');
    if (eyebrow) eyebrow.textContent = state.title || 'Claimed gift';
    if (title) title.textContent = action === 'tip' ? 'Send a tip' : 'Message participant';
    if (body) body.innerHTML = actionFormMarkup(action, state);
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
      form.innerHTML = voucherMarkup(title, voucherId, data, form);
    } catch (error) {
      form.innerHTML = errorMarkup(title, error.message);
    }
  }

  function enhanceClaimVoucher(form) {
    if (!form || form.dataset.claimVoucherQr === 'true') return;
    const voucherId = activeVoucherId(form);
    if (!voucherId) return;
    const modal = form.closest('[data-action-modal]') || document;
    const title = form.dataset.productTitle || activeRowContext().title || modal.querySelector('[data-action-modal-eyebrow]')?.textContent?.trim() || 'Microgift voucher';
    form.dataset.claimVoucherQr = 'true';
    form.innerHTML = loadingMarkup(title);
    prepareVoucher(form, voucherId, title);
  }

  function scanForClaimForms() {
    document.querySelectorAll('[data-action-form="claim"]').forEach(enhanceClaimVoucher);
  }

  function reviewManualClaim(root) {
    const panel = root.querySelector('[data-voucher-claim-panel]');
    const input = panel && panel.querySelector('[name="merchant_claim_code"]');
    const merchantClaimCode = input ? input.value.trim() : '';
    if (input) input.value = '';
    if (!merchantClaimCode) {
      const status = panel && panel.querySelector('[data-voucher-claim-status]');
      if (status) {
        status.textContent = 'Enter the merchant claim code to continue.';
        status.dataset.state = 'error';
      }
      return;
    }
    setRootState(root, { pendingClaimCode: merchantClaimCode });
    renderStep(root, 'confirm');
  }

  async function submitConfirmedClaim(root, button) {
    const state = rootState(root);
    if (!state.actionItemId || !state.pendingClaimCode) {
      renderStep(root, 'claim', { message: 'Enter the merchant claim code before continuing.', state: 'error' });
      return;
    }
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function') {
      renderStep(root, 'confirm');
      const status = root.querySelector('[data-voucher-claim-status]');
      if (status) {
        status.textContent = 'Microgifter API client is unavailable.';
        status.dataset.state = 'error';
      }
      return;
    }
    if (button) {
      button.disabled = true;
      button.textContent = 'Claiming…';
    }
    try {
      const response = await window.Microgifter.post('/api/account/action-center-voucher-claim.php', {
        action_item_id: state.actionItemId,
        merchant_claim_code: state.pendingClaimCode,
        voucher_token: state.token
      });
      state.pendingClaimCode = '';
      state.claimResponse = response && response.data ? response.data : response;
      setRootState(root, state);
      const message = response && response.message ? response.message : 'Gift claimed successfully.';
      renderStep(root, 'claimed', { payload: state.claimResponse, message });
      document.dispatchEvent(new CustomEvent('mg:action-center:voucher-claimed', { detail: state.claimResponse }));
      const refresh = document.querySelector('[data-gift-refresh]');
      if (refresh) window.setTimeout(() => refresh.click(), 650);
    } catch (error) {
      state.pendingClaimCode = '';
      setRootState(root, state);
      renderStep(root, 'claim', { message: friendlyClaimError(error), state: 'error' });
    }
  }

  document.addEventListener('submit', (event) => {
    const flow = event.target.closest('[data-voucher-flow]');
    if (!flow) return;
    event.preventDefault();
    event.stopPropagation();
    reviewManualClaim(flow);
  }, true);

  document.addEventListener('keydown', (event) => {
    const input = event.target.closest('[data-voucher-flow] [name="merchant_claim_code"]');
    if (!input || event.key !== 'Enter') return;
    const root = input.closest('[data-voucher-flow]');
    if (!root) return;
    event.preventDefault();
    event.stopPropagation();
    reviewManualClaim(root);
  }, true);

  document.addEventListener('click', (event) => {
    const root = event.target.closest('[data-voucher-flow]');
    if (!root) return;

    const review = event.target.closest('[data-voucher-review-claim]');
    if (review) {
      event.preventDefault();
      event.stopPropagation();
      reviewManualClaim(root);
      return;
    }

    const back = event.target.closest('[data-voucher-back]');
    if (back) {
      event.preventDefault();
      event.stopPropagation();
      setRootState(root, { pendingClaimCode: '' });
      renderStep(root, 'claim');
      return;
    }

    const confirm = event.target.closest('[data-voucher-confirm-claim]');
    if (confirm) {
      event.preventDefault();
      event.stopPropagation();
      submitConfirmedClaim(root, confirm);
      return;
    }

    const actions = event.target.closest('[data-voucher-actions]');
    if (actions) {
      event.preventDefault();
      event.stopPropagation();
      renderStep(root, 'actions');
      return;
    }

    const nextAction = event.target.closest('[data-voucher-next-action]');
    if (nextAction) {
      event.preventDefault();
      event.stopPropagation();
      openNextAction(root, nextAction.getAttribute('data-voucher-next-action'));
    }
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scanForClaimForms, { once: true });
  } else {
    scanForClaimForms();
  }
  new MutationObserver(scanForClaimForms).observe(document.documentElement, { childList: true, subtree: true });
})();