document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-checkout]');
  var C = window.MGCustomerCommerce;
  if (!root || !C) return;

  var content = root.querySelector('[data-checkout-content]');
  var sessionId = root.dataset.sessionId || '';
  var currentSession = null;
  var currentItems = [];
  var loading = false;
  var pollTimer = 0;

  function setBusy(value) { content.setAttribute('aria-busy', value ? 'true' : 'false'); }
  function clearPoll() { window.clearTimeout(pollTimer); pollTimer = 0; }
  function renderError(message) {
    clearPoll();
    setBusy(false);
    content.innerHTML = C.emptyState('Checkout unavailable', message) +
      '<div class="mg-checkout-actions"><a class="mg-btn mg-btn-primary" href="/cart.php">Back to cart</a><a class="mg-btn mg-btn-soft" href="/account/orders.php">View orders</a></div>' +
      '<div class="mg-checkout-refresh-row"><p>Checkout can be resumed from the cart when the service is available.</p><button class="mg-btn mg-btn-soft" type="button" data-checkout-refresh>Try again</button></div>' +
      '<div class="mg-commerce-status" data-checkout-status role="status" aria-live="polite"></div>';
  }
  function statusPill(value) { return C.statusPill(value || 'pending', 'mg-financial-state'); }
  function line(item) {
    return '<div class="mg-checkout-line"><div><strong>' + C.esc(item.title_snapshot || 'Microgift item') + '</strong><p>Quantity ' + C.quantity(item.quantity) + ' × ' + C.money(item.unit_amount_cents, item.currency) + '</p></div><strong>' + C.money(item.line_total_cents, item.currency) + '</strong></div>';
  }
  function providerLabel(provider) {
    if (provider === 'stripe') return 'Stripe card checkout';
    if (provider === 'cash') return 'Cash payment';
    if (provider === 'sandbox') return 'Sandbox payment';
    return 'Payment';
  }
  function canConfirmCash(session) {
    return session.provider_key === 'cash' && session.payment_status === 'unpaid' &&
      ['created', 'open'].indexOf(String(session.session_status || '')) !== -1 &&
      ['failed', 'cancelled', 'succeeded'].indexOf(String(session.payment_intent_status || '')) === -1;
  }
  function paymentAction(session) {
    if (session.payment_status === 'paid') {
      return '<a class="mg-btn mg-btn-primary" href="/checkout-success.php?order=' + encodeURIComponent(session.order_id || '') + '">View order confirmation</a>';
    }
    if (canConfirmCash(session) || session.can_confirm_cash) {
      return '<button class="mg-btn mg-btn-primary" type="button" data-cash-confirm>Confirm cash payment</button>';
    }
    if (session.provider_key === 'sandbox' && session.can_confirm) {
      return '<button class="mg-btn mg-btn-primary" type="button" data-sandbox-confirm>Complete sandbox payment</button>';
    }
    if (session.can_continue_provider && session.checkout_url) {
      var safeUrl = C.safeCheckoutUrl(session.checkout_url);
      return '<a class="mg-btn mg-btn-primary" href="' + C.esc(safeUrl) + '" rel="nofollow">Continue to Stripe Checkout</a>';
    }
    if (session.session_status === 'expired') {
      return '<button class="mg-btn mg-btn-primary" type="button" data-checkout-restart>Create new payment session</button>';
    }
    return '<button class="mg-btn mg-btn-primary" type="button" disabled>Payment unavailable</button>';
  }
  function sessionNotice(session) {
    if (session.payment_status === 'paid') return 'Payment is recorded. Open the order confirmation to review delivery and receipt status.';
    if (session.session_status === 'expired') return 'This payment session expired. The unpaid order is preserved and can receive a new payment session.';
    if (session.provider_key === 'cash') return 'Confirm only after the merchant has received the cash payment. Confirmation triggers delivery processing.';
    if (session.provider_key === 'sandbox') return 'Sandbox mode is active. No real card will be charged.';
    if (session.can_continue_provider) return 'Stripe hosts the card form. Microgifter does not store raw card numbers.';
    return 'Payment readiness is being checked. Refresh the session if the action does not appear.';
  }
  function render(session, items) {
    currentSession = session;
    currentItems = items;
    var currency = session.currency || (items[0] && items[0].currency) || 'USD';
    content.innerHTML =
      '<div class="mg-checkout-head"><div><span class="mg-eyebrow">Payment session</span><h2>' + C.esc(session.merchant_name || 'Microgifter purchase') + '</h2><p>Order ' + C.esc(session.order_id || 'Pending') + '</p><div class="mg-checkout-runtime-meta"><span>' + C.esc(providerLabel(session.provider_key)) + '</span><span>Session ' + C.esc(session.session_status || 'open') + '</span></div></div>' + statusPill(session.payment_status || session.session_status) + '</div>' +
      '<div class="mg-checkout-lines">' + (items.length ? items.map(line).join('') : C.emptyState('No checkout items', 'The frozen order did not return any items.')) + '</div>' +
      '<div class="mg-checkout-totals"><div class="mg-checkout-total"><span>Subtotal</span><strong>' + C.money(session.subtotal_cents, currency) + '</strong></div><div class="mg-checkout-total"><span>Tax</span><strong>' + C.money(session.tax_cents, currency) + '</strong></div><div class="mg-checkout-total"><span>Platform share <small>(included)</small></span><strong>' + C.money(session.platform_fee_cents, currency) + '</strong></div><div class="mg-checkout-total is-grand"><span>Total charged</span><strong>' + C.money(session.total_cents, currency) + '</strong></div></div>' +
      '<div class="mg-checkout-meta"><div><span>Provider</span><strong>' + C.esc(providerLabel(session.provider_key)) + '</strong></div><div><span>Payment status</span><strong>' + C.esc(session.payment_status || 'unpaid') + '</strong></div><div><span>Expires</span><strong>' + C.esc(session.expires_at || 'Session controlled') + '</strong></div></div>' +
      '<div class="mg-checkout-notice">' + C.esc(sessionNotice(session)) + '</div>' +
      '<div class="mg-checkout-actions">' + paymentAction(session) + '<a class="mg-btn mg-btn-soft" href="/cart.php">Back to cart</a><a class="mg-btn mg-btn-soft" href="/account/orders.php">View orders</a></div>' +
      '<div class="mg-checkout-refresh-row"><p data-checkout-refresh-note>' + (navigator.onLine ? 'Session status refreshes automatically while this page is open.' : 'You are offline. Checkout will refresh after connection returns.') + '</p><button class="mg-btn mg-btn-soft" type="button" data-checkout-refresh>Refresh status</button></div>' +
      '<div class="mg-commerce-status" data-checkout-status role="status" aria-live="polite"></div>';
    setBusy(false);
    schedulePoll(session);
  }
  function schedulePoll(session) {
    clearPoll();
    var delay = Number(session.refresh_after_ms || 0);
    if (delay < 1000 || document.hidden || !navigator.onLine || session.payment_status === 'paid' || session.session_status === 'expired') return;
    pollTimer = window.setTimeout(function () { load(true); }, Math.min(15000, Math.max(3000, delay)));
  }
  async function load(quiet) {
    if (loading) return;
    if (!sessionId) {
      renderError('A checkout session is required. Start from your cart to create or resume checkout.');
      return;
    }
    loading = true;
    if (!quiet) setBusy(true);
    try {
      var payload = C.data(await C.api('GET', '/api/payments/session.php?id=' + encodeURIComponent(sessionId)));
      render(payload.session || {}, Array.isArray(payload.items) ? payload.items : []);
    } catch (error) {
      if (quiet && currentSession) {
        var status = content.querySelector('[data-checkout-status]');
        C.status(status, error.message || 'Unable to refresh checkout.', 'error');
        schedulePoll(currentSession);
      } else renderError(error.message || 'Checkout could not be loaded.');
    } finally { loading = false; }
  }
  async function confirmLocal(endpoint, button, progress, fallback) {
    var status = content.querySelector('[data-checkout-status]');
    button.disabled = true;
    try {
      C.status(status, progress, 'info');
      var response = await C.api('POST', endpoint, { session_id: sessionId });
      var result = C.data(response);
      C.status(status, response.message || 'Payment completed.', 'success');
      window.location.href = '/checkout-success.php?order=' + encodeURIComponent(result.order_id || currentSession.order_id || '');
    } catch (error) {
      C.status(status, error.message || fallback, 'error');
      button.disabled = false;
      load(true);
    }
  }
  async function restart(button) {
    var status = content.querySelector('[data-checkout-status]');
    button.disabled = true;
    try {
      C.status(status, 'Creating a new secure payment session…', 'info');
      var response = await C.api('POST', '/api/payments/order-checkout-session.php', {
        order_id: currentSession.order_id,
        provider_key: currentSession.provider_key,
        idempotency_key: 'restart:' + currentSession.order_id + ':' + C.uuid(),
        success_url: '/checkout-success.php',
        cancel_url: '/cart.php'
      });
      var result = C.data(response);
      sessionId = result.checkout_session_id || sessionId;
      root.dataset.sessionId = sessionId;
      window.history.replaceState({}, '', '/checkout.php?session=' + encodeURIComponent(sessionId));
      await load(false);
    } catch (error) {
      C.status(status, error.message || 'Unable to restart checkout.', 'error');
      button.disabled = false;
    }
  }

  content.addEventListener('click', function (event) {
    var refresh = event.target.closest('[data-checkout-refresh]');
    if (refresh) { event.preventDefault(); load(false); return; }
    var cash = event.target.closest('[data-cash-confirm]');
    if (cash) { event.preventDefault(); confirmLocal('/api/payments/cash-confirm.php', cash, 'Recording cash payment…', 'Unable to record cash payment.'); return; }
    var sandbox = event.target.closest('[data-sandbox-confirm]');
    if (sandbox) { event.preventDefault(); confirmLocal('/api/payments/sandbox-confirm.php', sandbox, 'Processing sandbox payment…', 'Unable to complete sandbox payment.'); return; }
    var restartButton = event.target.closest('[data-checkout-restart]');
    if (restartButton) { event.preventDefault(); restart(restartButton); }
  });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) load(true); else clearPoll(); });
  window.addEventListener('online', function () { load(true); });
  window.addEventListener('offline', function () {
    clearPoll();
    var note = content.querySelector('[data-checkout-refresh-note]');
    if (note) note.textContent = 'You are offline. Checkout will refresh after connection returns.';
  });
  window.addEventListener('beforeunload', clearPoll);

  load(false);
});
