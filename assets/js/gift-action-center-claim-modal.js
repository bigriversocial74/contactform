(() => {
  'use strict';

  const FLOW = new WeakMap();

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
  }

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(cents || 0) / 100);
    } catch (error) {
      return String(currency || 'USD') + ' ' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function titleOf(item) {
    return (item && (item.template_name || item.title || item.reward_title || item.product_title)) || 'Microgift voucher';
  }

  function merchantOf(item) {
    return (item && (item.business_name || item.merchant_name || item.location_name || item.sender_name)) || 'Participating merchant';
  }

  function actionIdOf(item) {
    return String(item && (item.action_item_id || item.public_id || item.id) || '').trim();
  }

  function isDemo(item) {
    const id = actionIdOf(item);
    return !!(item && item.is_demo) || /^demo-/i.test(id);
  }

  function friendlyClaimError(error) {
    const raw = String(error && error.message ? error.message : '').trim();
    if (/Action Center voucher not found|wallet voucher not found|voucher ID is required/i.test(raw)) {
      return 'This gift row is not attached to an active Action Center voucher. Refresh the list, then try again.';
    }
    if (/invalid merchant claim code|invalid claim code|wrong|claim code/i.test(raw)) {
      return 'Wrong merchant claim code. Check the code and try again.';
    }
    if (/too many|temporarily locked|locked/i.test(raw)) return raw || 'Too many invalid claim-code attempts. Try again later.';
    return raw || 'Unable to claim this gift right now.';
  }

  function ensureShell() {
    let backdrop = document.querySelector('[data-claim-modal-backdrop]');
    let modal = document.querySelector('[data-claim-modal]');
    if (backdrop && modal) return { backdrop, modal, body: modal.querySelector('[data-claim-modal-body]') };

    backdrop = document.createElement('div');
    backdrop.className = 'mg-claim-modal-backdrop';
    backdrop.setAttribute('data-claim-modal-backdrop', '');
    backdrop.hidden = true;

    modal = document.createElement('section');
    modal.className = 'mg-claim-modal';
    modal.setAttribute('data-claim-modal', '');
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'mg-claim-modal-title');
    modal.innerHTML = `
      <header class="mg-claim-modal-header">
        <div>
          <span class="mg-claim-modal-eyebrow" data-claim-modal-eyebrow>Claim voucher</span>
          <h2 id="mg-claim-modal-title" data-claim-modal-title>Claim gift</h2>
        </div>
        <button class="mg-claim-modal-close" type="button" data-claim-modal-close aria-label="Close claim modal">×</button>
      </header>
      <div class="mg-claim-modal-body" data-claim-modal-body></div>`;

    document.body.appendChild(backdrop);
    document.body.appendChild(modal);
    return { backdrop, modal, body: modal.querySelector('[data-claim-modal-body]') };
  }

  function setOpen(open) {
    const shell = ensureShell();
    shell.modal.classList.toggle('is-open', open);
    shell.modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    shell.backdrop.hidden = !open;
    document.body.classList.toggle('mg-modal-lock', open);
    document.body.classList.toggle('mg-claim-modal-open', open);
    if (open) {
      const close = shell.modal.querySelector('[data-claim-modal-close]');
      if (close) window.setTimeout(() => close.focus({ preventScroll: true }), 0);
    } else {
      shell.body.innerHTML = '';
      shell.modal.removeAttribute('data-voucher-claimed');
      FLOW.delete(shell.modal);
    }
  }

  function stateFor(modal) {
    return FLOW.get(modal) || {};
  }

  function setState(modal, next) {
    FLOW.set(modal, Object.assign({}, stateFor(modal), next));
  }

  function stepper(step) {
    const steps = [['claim', 'Claim'], ['review', 'Review'], ['confirm', 'Confirm'], ['success', 'Success']];
    const active = Math.max(0, steps.findIndex((item) => item[0] === step));
    return `<ol class="mg-claim-stepper" aria-label="Claim progress">${steps.map((item, index) => {
      const status = index < active ? 'complete' : (index === active ? 'active' : 'pending');
      return `<li class="is-${status}"><span>${index + 1}</span><strong>${esc(item[1])}</strong></li>`;
    }).join('')}</ol>`;
  }

  function status(message, kind) {
    return `<div class="mg-claim-status" data-claim-status data-state="${esc(kind || '')}" role="status" aria-live="polite">${esc(message || '')}</div>`;
  }

  function productHeader(state, subtitle) {
    const value = state.value ? `<span>${esc(state.value)}</span>` : '';
    return `<section class="mg-claim-product-card">
      <strong>${esc(state.title)}</strong>
      <small>${esc(subtitle || state.merchant)}</small>
      ${value}
    </section>`;
  }

  function qrFrame(state) {
    if (state.qrImage) {
      return `<div class="mg-claim-qr-frame"><img src="${esc(state.qrImage)}" alt="QR code for ${esc(state.title)}" loading="eager" referrerpolicy="no-referrer"></div>`;
    }
    const copy = state.isDemo ? 'Demo QR preview' : 'Preparing QR…';
    return `<div class="mg-claim-qr-frame"><div class="mg-claim-qr-placeholder">${esc(copy)}</div></div>`;
  }

  function claimStep(modal, message, kind) {
    const state = stateFor(modal);
    const disabled = !state.isDemo && !state.qrImage;
    const demoNote = state.isDemo ? '<div class="mg-claim-note"><strong>Demo preview only.</strong> This stays local and does not create a redemption, ledger entry, notification, payout, ownership change, or webhook.</div>' : '';
    const codeValue = state.isDemo && state.demoClaimCode ? ` value="${esc(state.demoClaimCode)}"` : '';
    return `<form class="mg-claim-flow" data-claim-step="claim">
      ${stepper('claim')}
      ${productHeader(state)}
      ${qrFrame(state)}
      ${demoNote}
      <label class="mg-claim-code-label">
        <span>${state.isDemo ? 'Demo claim code' : 'Manual merchant claim code'}</span>
        <input type="${state.isDemo ? 'text' : 'password'}" name="merchant_claim_code" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Enter claim code"${codeValue} ${disabled ? 'disabled' : ''} required>
      </label>
      <div class="mg-claim-actions">
        <button class="mg-btn mg-btn-primary" type="submit" ${disabled ? 'disabled' : ''}>Review claim</button>
      </div>
      ${status(message || '', kind || '')}
    </form>`;
  }

  function reviewStep(modal) {
    const state = stateFor(modal);
    return `<section class="mg-claim-flow" data-claim-step="review">
      ${stepper('review')}
      ${productHeader(state, 'Review the claim before confirming')}
      <div class="mg-claim-review-card">
        <div><span>Product</span><strong>${esc(state.title)}</strong></div>
        <div><span>Merchant</span><strong>${esc(state.merchant)}</strong></div>
        <div><span>Claim code</span><strong>••••••</strong></div>
        <div><span>Mode</span><strong>${state.isDemo ? 'Safe demo preview' : 'Live voucher claim'}</strong></div>
      </div>
      <div class="mg-claim-note">${state.isDemo ? 'No API call will run for this demo voucher.' : 'This will submit the manual merchant claim code to the existing Action Center voucher claim API.'}</div>
      <div class="mg-claim-actions is-split">
        <button class="mg-btn mg-btn-soft" type="button" data-claim-back>Back</button>
        <button class="mg-btn mg-btn-primary" type="button" data-claim-to-confirm>Continue</button>
      </div>
      ${status('', '')}
    </section>`;
  }

  function confirmStep(modal) {
    const state = stateFor(modal);
    return `<section class="mg-claim-flow" data-claim-step="confirm">
      ${stepper('confirm')}
      ${productHeader(state, state.isDemo ? 'Confirm demo preview' : 'Confirm merchant redemption')}
      <section class="mg-claim-confirm-card">
        <div aria-hidden="true">✓</div>
        <strong>${state.isDemo ? 'Complete demo preview?' : 'Submit this claim?'}</strong>
        <p>${state.isDemo ? 'The result will be shown locally only.' : 'The claim code will be checked against the authorized merchant claim-code records.'}</p>
      </section>
      <div class="mg-claim-actions is-split">
        <button class="mg-btn mg-btn-soft" type="button" data-claim-back>Back</button>
        <button class="mg-btn mg-btn-primary" type="button" data-claim-confirm>${state.isDemo ? 'Complete preview' : 'Confirm claim'}</button>
      </div>
      ${status('', '')}
    </section>`;
  }

  function successStep(modal, payload, message) {
    const state = stateFor(modal);
    const location = payload && (payload.location_name || (payload.location && payload.location.name)) ? (payload.location_name || payload.location.name) : state.merchant;
    return `<section class="mg-claim-flow" data-claim-step="success">
      ${stepper('success')}
      <section class="mg-claim-success-card">
        <div aria-hidden="true">✓</div>
        <strong>${esc(message || (state.isDemo ? 'Demo claim preview complete.' : 'Gift claimed successfully.'))}</strong>
        <p>${esc(state.title)} was ${state.isDemo ? 'previewed' : 'claimed'}${location ? ' at ' + esc(location) : ''}.</p>
      </section>
      <div class="mg-claim-review-card">
        <div><span>Product</span><strong>${esc(state.title)}</strong></div>
        <div><span>Merchant</span><strong>${esc(location || state.merchant)}</strong></div>
        <div><span>Status</span><strong>${state.isDemo ? 'Demo preview' : 'Claimed'}</strong></div>
        <div><span>Redemption</span><strong>${esc((payload && (payload.redemption_id || payload.attempt_id)) || 'Recorded')}</strong></div>
      </div>
      <div class="mg-claim-actions">
        <button class="mg-btn mg-btn-primary" type="button" data-claim-modal-close>Done</button>
      </div>
    </section>`;
  }

  function errorStep(modal, message) {
    const state = stateFor(modal);
    return `<section class="mg-claim-flow" data-claim-step="error">
      ${stepper('claim')}
      ${productHeader(state, 'Claim unavailable')}
      <section class="mg-claim-error-card">
        <strong>Claim could not be prepared</strong>
        <p>${esc(message || 'Unable to prepare this claim right now.')}</p>
      </section>
      <div class="mg-claim-actions is-split">
        <button class="mg-btn mg-btn-soft" type="button" data-claim-modal-close>Close</button>
        <button class="mg-btn mg-btn-primary" type="button" data-claim-retry>Try again</button>
      </div>
    </section>`;
  }

  function render(modal, markup) {
    const body = modal.querySelector('[data-claim-modal-body]');
    if (body) body.innerHTML = markup;
  }

  async function prepareToken(modal) {
    const state = stateFor(modal);
    if (state.isDemo) {
      render(modal, claimStep(modal));
      return;
    }
    render(modal, claimStep(modal, 'Preparing QR and voucher token…', ''));
    try {
      if (!state.actionItemId) throw new Error('Action Center voucher ID is required.');
      if (!window.Microgifter || typeof window.Microgifter.get !== 'function') throw new Error('Microgifter API client is unavailable.');
      const response = await window.Microgifter.get('/api/account/action-center-voucher-token.php?action_item_id=' + encodeURIComponent(state.actionItemId));
      const data = response && response.data ? response.data : response;
      if (!data || !data.qr_image_url || !data.scan_payload) throw new Error('Voucher QR payload was not returned.');
      setState(modal, {
        token: data.token || '',
        tokenId: data.token_id || '',
        qrImage: data.qr_image_url || '',
        scanPayload: data.scan_payload || '',
        title: (data.voucher && data.voucher.title) || state.title,
        isWalletReward: !!data.is_wallet_reward
      });
      render(modal, claimStep(modal));
    } catch (error) {
      setState(modal, { lastError: friendlyClaimError(error) });
      render(modal, errorStep(modal, friendlyClaimError(error)));
    }
  }

  function openForItem(item) {
    const shell = ensureShell();
    const title = titleOf(item);
    const merchant = merchantOf(item);
    const value = item && item.face_value_cents ? money(item.face_value_cents, item.currency) : '';
    shell.modal.querySelector('[data-claim-modal-eyebrow]').textContent = merchant;
    shell.modal.querySelector('[data-claim-modal-title]').textContent = 'Claim gift';
    FLOW.set(shell.modal, {
      item,
      actionItemId: actionIdOf(item),
      title,
      merchant,
      value,
      token: '',
      qrImage: '',
      scanPayload: '',
      pendingClaimCode: '',
      isDemo: isDemo(item),
      demoClaimCode: item && item.claim_code ? String(item.claim_code) : 'DEMO'
    });
    render(shell.modal, claimStep(shell.modal, 'Preparing claim screen…', ''));
    setOpen(true);
    prepareToken(shell.modal);
  }

  function reviewClaim(modal) {
    const input = modal.querySelector('[name="merchant_claim_code"]');
    const code = input ? input.value.trim() : '';
    if (!code) {
      const statusBox = modal.querySelector('[data-claim-status]');
      if (statusBox) {
        statusBox.textContent = 'Enter the merchant claim code to continue.';
        statusBox.dataset.state = 'error';
      }
      return;
    }
    if (input && !stateFor(modal).isDemo) input.value = '';
    setState(modal, { pendingClaimCode: code });
    render(modal, reviewStep(modal));
  }

  async function submitClaim(modal, button) {
    const state = stateFor(modal);
    if (!state.pendingClaimCode) {
      render(modal, claimStep(modal, 'Enter the merchant claim code before continuing.', 'error'));
      return;
    }
    if (state.isDemo) {
      const payload = { demo_preview: true, action_item_id: state.actionItemId, location_name: state.merchant, title: state.title };
      setState(modal, { pendingClaimCode: '', claimResponse: payload });
      render(modal, successStep(modal, payload, 'Demo claim preview complete. No real claim was created.'));
      return;
    }
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function') {
      render(modal, confirmStep(modal));
      const statusBox = modal.querySelector('[data-claim-status]');
      if (statusBox) {
        statusBox.textContent = 'Microgifter API client is unavailable.';
        statusBox.dataset.state = 'error';
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
      const payload = response && response.data ? response.data : response;
      setState(modal, { pendingClaimCode: '', claimResponse: payload });
      modal.dataset.voucherClaimed = 'true';
      render(modal, successStep(modal, payload, response && response.message ? response.message : 'Gift claimed successfully.'));
      document.dispatchEvent(new CustomEvent('mg:action-center:voucher-claimed', { detail: payload || {} }));
    } catch (error) {
      setState(modal, { pendingClaimCode: '' });
      render(modal, claimStep(modal, friendlyClaimError(error), 'error'));
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Confirm claim';
      }
    }
  }

  function openRestoredClaim(data) {
    const shell = ensureShell();
    const title = data.title || 'Gift claimed';
    const merchant = data.location_name || 'Merchant location';
    shell.modal.querySelector('[data-claim-modal-eyebrow]').textContent = data.is_wallet_reward ? 'Wallet reward claimed' : 'Microgift claimed';
    shell.modal.querySelector('[data-claim-modal-title]').textContent = 'Claim confirmed';
    FLOW.set(shell.modal, {
      item: {},
      actionItemId: data.action_item_id || '',
      title,
      merchant,
      value: money(data.value_cents || 0, data.currency || 'USD'),
      isDemo: false
    });
    render(shell.modal, successStep(shell.modal, data, 'Gift claimed successfully'));
    setOpen(true);
  }

  function boot() {
    const app = document.querySelector('[data-gift-center]');
    if (!app) return;

    app.addEventListener('mg:gift-claim:open', (event) => {
      const item = event.detail && event.detail.item;
      if (!item) return;
      event.preventDefault();
      openForItem(item);
    });

    document.addEventListener('mg:gift-claim:restore', (event) => {
      event.preventDefault();
      openRestoredClaim(event.detail || {});
    });
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-claim-modal-close]') || event.target.closest('[data-claim-modal-backdrop]')) {
      event.preventDefault();
      setOpen(false);
      return;
    }
    const modal = event.target.closest('[data-claim-modal]');
    if (!modal) return;
    if (event.target.closest('[data-claim-back]')) {
      event.preventDefault();
      setState(modal, { pendingClaimCode: '' });
      render(modal, claimStep(modal));
      return;
    }
    if (event.target.closest('[data-claim-to-confirm]')) {
      event.preventDefault();
      render(modal, confirmStep(modal));
      return;
    }
    const confirm = event.target.closest('[data-claim-confirm]');
    if (confirm) {
      event.preventDefault();
      submitClaim(modal, confirm);
      return;
    }
    if (event.target.closest('[data-claim-retry]')) {
      event.preventDefault();
      prepareToken(modal);
    }
  }, true);

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-claim-modal] .mg-claim-flow[data-claim-step="claim"]');
    if (!form) return;
    event.preventDefault();
    event.stopPropagation();
    const modal = form.closest('[data-claim-modal]');
    if (modal) reviewClaim(modal);
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.querySelector('[data-claim-modal].is-open')) {
      event.preventDefault();
      setOpen(false);
    }
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
