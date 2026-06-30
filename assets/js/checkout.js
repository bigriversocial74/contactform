document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-checkout]');
  var C = window.MGCustomerCommerce;
  if (!root || !C) return;

  var content = root.querySelector('[data-checkout-content]');
  var sessionId = root.dataset.sessionId || '';

  function renderError(message) {
    content.innerHTML = C.emptyState('Checkout unavailable', message) + '<div class="mg-checkout-actions"><a class="mg-btn mg-btn-primary" href="/cart.php">Back to cart</a><a class="mg-btn mg-btn-soft" href="/account/orders.php">View orders</a></div>';
  }

  function statusPill(value) {
    return C.statusPill(value || 'pending', 'mg-financial-state');
  }

  function line(item) {
    return '<div class="mg-checkout-line"><div><strong>' + C.esc(item.title_snapshot || 'Microgift item') + '</strong><p>Quantity ' + C.quantity(item.quantity) + ' × ' + C.money(item.unit_amount_cents, item.currency) + '</p></div><strong>' + C.money(item.line_total_cents, item.currency) + '</strong></div>';
  }

  function canConfirmCash(session) {
    return session.provider_key === 'cash' &&
      session.payment_status === 'unpaid' &&
      ['created','open'].indexOf(String(session.session_status || '')) !== -1 &&
      ['failed','cancelled','succeeded'].indexOf(String(session.payment_intent_status || '')) === -1;
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
      return '<a class="mg-btn mg-btn-primary" href="' + C.esc(session.checkout_url) + '" rel="nofollow">Continue to Stripe Checkout</a>';
    }
    if (session.session_status === 'expired') {
      return '<button class="mg-btn mg-btn-primary" type="button" data-checkout-restart>Create new payment session</button>';
    }
    return '<button class="mg-btn mg-btn-primary" type="button" disabled>Payment unavailable</button>';
  }

  function sessionNotice(session) {
    if (session.payment_status === 'paid') return 'Payment is already recorded for this order. You can open the order confirmation now.';
    if (session.session_status === 'expired') return 'This payment session expired. Your unpaid order is preserved and can be reopened below.';
    if (session.provider_key === 'cash') return 'Cash payment is enabled. Confirming here records the order and issues the Microgifter items without Stripe.';
    if (session.provider_key === 'sandbox') return 'Sandbox mode is active. No real card will be charged.';
    return 'Stripe hosts the payment form. Microgifter never stores raw card numbers.';
  }

  function render(session, items) {
    var moneyCurrency = session.currency || (items[0] && items[0].currency) || 'USD';
    content.innerHTML =
      '<div class="mg-checkout-head"><div><span class="mg-eyebrow">Payment session</span><h2>' + C.esc(session.merchant_name || 'Microgifter purchase') + '</h2><p>Order ' + C.esc(session.order_id || 'Pending') + ' · Session ' + C.esc(session.session_status || 'open') + '</p></div>' + statusPill(session.payment_status || session.session_status) + '</div>' +
      '<div class="mg-checkout-lines">' + (items.length ? items.map(line).join('') : '<div class="mg-empty-state"><p>No checkout items were returned.</p></div>') + '</div>' +
      '<div class="mg-checkout-totals"><div class="mg-checkout-total"><span>Subtotal</span><strong>' + C.money(session.subtotal_cents, moneyCurrency) + '</strong></div><div class="mg-checkout-total"><span>Tax</span><strong>' + C.money(session.tax_cents, moneyCurrency) + '</strong></div><div class="mg-checkout-total"><span>Microgifter platform share <small>(included)</small></span><strong>' + C.money(session.platform_fee_cents, moneyCurrency) + '</strong></div><div class="mg-checkout-total is-grand"><span>Total charged</span><strong>' + C.money(session.total_cents, moneyCurrency) + '</strong></div></div>' +
      '<div class="mg-checkout-meta"><div><span>Provider</span><strong>' + C.esc(session.provider_key || 'payment') + '</strong></div><div><span>Payment status</span><strong>' + C.esc(session.payment_status || 'unpaid') + '</strong></div><div><span>Expires</span><strong>' + C.esc(session.expires_at || 'Session controlled') + '</strong></div></div>' +
      '<div class="mg-checkout-notice">' + C.esc(sessionNotice(session)) + '</div>' +
      '<div class="mg-checkout-actions">' + paymentAction(session) + '<a class="mg-btn mg-btn-soft" href="/cart.php">Back to cart</a><a class="mg-btn mg-btn-soft" href="/account/orders.php">View orders</a></div><div class="mg-commerce-status" data-checkout-status role="status" aria-live="polite"></div>';
  }

  async function load() {
    if (!sessionId) {
      renderError('A checkout session is required. Start from your cart so Microgifter can create a checkout draft, pending order, and payment session.');
      return;
    }
    var data = C.data(await C.api('GET','/api/payments/session.php?id=' + encodeURIComponent(sessionId)));
    var session = data.session || {};
    render(session, data.items || []);

    var status = root.querySelector('[data-checkout-status]');
    var cash = root.querySelector('[data-cash-confirm]');
    if (cash) {
      cash.onclick = async function () {
        cash.disabled = true;
        try {
          C.status(status, 'Recording cash payment…', 'info');
          var response = await C.api('POST','/api/payments/cash-confirm.php', { session_id: sessionId });
          var result = C.data(response);
          C.status(status, response.message || 'Cash payment recorded.', 'success');
          location.href = '/checkout-success.php?order=' + encodeURIComponent(result.order_id || session.order_id || '');
        } catch (error) {
          C.status(status, error.message || 'Unable to record cash payment.', 'error');
          cash.disabled = false;
        }
      };
    }

    var confirm = root.querySelector('[data-sandbox-confirm]');
    if (confirm) {
      confirm.onclick = async function () {
        confirm.disabled = true;
        try {
          C.status(status, 'Processing secure payment…', 'info');
          var response = await C.api('POST','/api/payments/sandbox-confirm.php', { session_id: sessionId });
          var result = C.data(response);
          C.status(status, response.message || 'Payment completed.', 'success');
          location.href = '/checkout-success.php?order=' + encodeURIComponent(result.order_id || session.order_id || '');
        } catch (error) {
          C.status(status, error.message || 'Unable to complete sandbox payment.', 'error');
          confirm.disabled = false;
        }
      };
    }

    var restart = root.querySelector('[data-checkout-restart]');
    if (restart) {
      restart.onclick = async function () {
        restart.disabled = true;
        try {
          C.status(status, 'Creating a new secure payment session…', 'info');
          var response = await C.api('POST','/api/payments/order-checkout-session.php', {
            order_id:session.order_id,
            idempotency_key: 'payment:' + C.uuid(),
            success_url: '/checkout-success.php',
            cancel_url: '/cart.php'
          });
          var result = C.data(response);
          location.href = '/checkout.php?session=' + encodeURIComponent(result.checkout_session_id || sessionId);
        } catch (error) {
          C.status(status, error.message || 'Unable to restart checkout.', 'error');
          restart.disabled = false;
        }
      };
    }
  }

  load().catch(function (error) {
    renderError(error.message || 'Checkout could not be loaded.');
  });
});
