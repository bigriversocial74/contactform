(function (window, document) {
  'use strict';

  var MG = window.Microgifter || {};
  var panel = document.querySelector('.mg-subscription-redesign');
  if (!panel || !MG.get || !MG.post) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function readable(value) {
    return String(value || '').replace(/[-_]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function dateLabel(value, includeTime) {
    if (!value) return '—';
    var raw = String(value);
    var date = new Date(/[zZ]$|[+-]\d{2}:?\d{2}$/.test(raw) ? raw : raw.replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return raw;
    var options = { month: 'short', day: 'numeric', year: 'numeric' };
    if (includeTime) {
      options.hour = 'numeric';
      options.minute = '2-digit';
    }
    return date.toLocaleString(undefined, options);
  }

  function money(cents, currency) {
    if (cents == null || !Number.isFinite(Number(cents))) return '';
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency', currency: currency || 'USD', maximumFractionDigits: 2
      }).format(Number(cents) / 100);
    } catch (_) {
      return '$' + (Number(cents) / 100).toFixed(2);
    }
  }

  function body() {
    return panel.querySelector('.mg-app-panel-body');
  }

  function statusBanner(key, tone, title, copy, actionLabel, action) {
    var mount = body();
    if (!mount) return null;
    var old = mount.querySelector('[data-checkout-completion-banner="' + key + '"]');
    if (old) old.remove();
    var box = document.createElement('section');
    box.className = 'mg-sub-checkout-banner is-' + (tone || 'info');
    box.setAttribute('data-checkout-completion-banner', key);
    box.innerHTML = '<div><strong>' + esc(title) + '</strong><span>' + esc(copy) + '</span></div>';
    if (actionLabel && typeof action === 'function') {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'mg-sub-checkout-banner-action';
      button.textContent = actionLabel;
      button.addEventListener('click', action);
      box.appendChild(button);
    }
    mount.insertBefore(box, mount.firstChild);
    return box;
  }

  async function confirmCheckout(requestId, sessionId, attempt) {
    var tries = Number(attempt || 0);
    statusBanner('return', 'info', 'Confirming secure checkout', 'Microgifter is verifying the completed Stripe session and activating your package access.');
    try {
      var response = await MG.post('/api/subscriptions/confirm-checkout.php', {
        request_id: requestId,
        stripe_session_id: sessionId
      });
      var data = response.data || response;
      if (data.confirmed) {
        var target = '/account-subscriptions.php?checkout=activated';
        window.location.replace(target);
        return;
      }
      if (data.pending && tries < 3) {
        statusBanner('return', 'warning', 'Payment confirmation pending', response.message || 'Stripe is still completing the payment. Microgifter will check again automatically.');
        window.setTimeout(function () { confirmCheckout(requestId, sessionId, tries + 1); }, 3500);
        return;
      }
      statusBanner('return', 'warning', 'Payment confirmation pending', response.message || 'The package will activate when Stripe confirms payment.', 'Check again', function () {
        confirmCheckout(requestId, sessionId, 0);
      });
    } catch (error) {
      statusBanner('return', 'error', 'Checkout verification needs attention', error.message || 'Microgifter could not verify the Stripe session yet.', 'Retry verification', function () {
        confirmCheckout(requestId, sessionId, 0);
      });
    }
  }

  function eventMeta(item) {
    var parts = [];
    if (item.package_id) parts.push(readable(item.package_id));
    if (item.billing_cycle) parts.push(readable(item.billing_cycle) + ' billing');
    if (item.amount_cents != null) parts.push(money(item.amount_cents, item.currency || 'USD'));
    if (item.invoice_status) parts.push('Invoice ' + readable(item.invoice_status));
    if (!parts.length && item.to_status) parts.push(readable(item.to_status));
    return parts.join(' · ');
  }

  function historyLinks(item) {
    var links = [];
    if (item.invoice_url) links.push('<a href="' + esc(item.invoice_url) + '" target="_blank" rel="noopener">View invoice</a>');
    if (item.invoice_pdf) links.push('<a href="' + esc(item.invoice_pdf) + '" target="_blank" rel="noopener">Download PDF</a>');
    return links.length ? '<div class="mg-sub-history-links">' + links.join('') + '</div>' : '';
  }

  function renderHistory(data) {
    var mount = body();
    if (!mount) return;
    var old = mount.querySelector('[data-subscription-history]');
    if (old) old.remove();

    var history = Array.isArray(data.history) ? data.history : [];
    var subscription = data.subscription || null;
    var section = document.createElement('section');
    section.className = 'mg-sub-history';
    section.setAttribute('data-subscription-history', '');

    var summary = subscription ? [
      '<article><span>Current status</span><strong>' + esc(readable(subscription.status)) + '</strong></article>',
      '<article><span>Billing cycle</span><strong>' + esc(readable(subscription.billing_cycle)) + '</strong></article>',
      '<article><span>Paid through</span><strong>' + esc(dateLabel(subscription.current_period_end, false)) + '</strong></article>',
      '<article><span>Last payment</span><strong>' + esc(dateLabel(subscription.last_payment_at, false)) + '</strong></article>'
    ].join('') : '<article><span>Billing status</span><strong>No provider-backed subscription</strong></article>';

    var rows = history.length ? history.map(function (item) {
      var meta = eventMeta(item);
      return [
        '<article class="mg-sub-history-row is-' + esc(item.tone || 'info') + '">',
        '<span class="mg-sub-history-dot" aria-hidden="true"></span>',
        '<div class="mg-sub-history-copy"><strong>' + esc(item.label || 'Subscription activity') + '</strong>',
        '<span>' + esc(dateLabel(item.created_at, true)) + (meta ? ' · ' + esc(meta) : '') + '</span>',
        historyLinks(item),
        '</div></article>'
      ].join('');
    }).join('') : '<div class="mg-sub-history-empty"><strong>No billing activity yet.</strong><span>Completed checkouts, invoices, package changes, and lifecycle updates will appear here.</span></div>';

    section.innerHTML = [
      '<header class="mg-sub-history-head"><div><span class="mg-sub-history-kicker">Billing activity</span><h3>Subscription and payment history</h3><p>Verified Checkout confirmations, provider lifecycle changes, invoices, and package updates.</p></div></header>',
      '<div class="mg-sub-history-summary">' + summary + '</div>',
      '<div class="mg-sub-history-list">' + rows + '</div>'
    ].join('');
    mount.appendChild(section);
  }

  async function loadHistory() {
    try {
      var response = await MG.get('/api/subscriptions/history.php');
      renderHistory(response.data || response);
    } catch (error) {
      var mount = body();
      if (!mount) return;
      var section = document.createElement('section');
      section.className = 'mg-sub-history mg-sub-history-error';
      section.setAttribute('data-subscription-history', '');
      section.innerHTML = '<strong>Billing history is temporarily unavailable.</strong><span>' + esc(error.message || 'Refresh the page to try again.') + '</span>';
      mount.appendChild(section);
    }
  }

  function start() {
    var params = new URLSearchParams(window.location.search || '');
    var checkoutState = params.get('checkout');
    var requestId = params.get('request') || '';
    var sessionId = params.get('stripe_session_id') || '';

    if (checkoutState === 'success' && requestId && sessionId) {
      confirmCheckout(requestId, sessionId, 0);
    } else if (checkoutState === 'activated') {
      statusBanner('activated', 'success', 'Subscription activated', 'Your Stripe Checkout session is verified. Package access, permissions, and merchant workspace availability are now refreshed.');
    }
    loadHistory();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})(window, document);
