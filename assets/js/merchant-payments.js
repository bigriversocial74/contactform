document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-app][data-merchant-view="payments"]');
  if (!root || !window.Microgifter) return;

  var financialData = null;
  var allowedPages = ['methods', 'orders', 'refunds', 'payouts', 'disputes', 'reconciliation'];

  function one(selector) {
    return root.querySelector(selector);
  }

  function all(selector) {
    return Array.prototype.slice.call(root.querySelectorAll(selector));
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
      })[character];
    });
  }

  function money(cents, currency) {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: currency || 'USD'
    }).format(Number(cents || 0) / 100);
  }

  function rows(items, renderer, emptyMessage) {
    return items && items.length
      ? items.map(renderer).join('')
      : '<div class="mg-empty-state">' + esc(emptyMessage) + '</div>';
  }

  function setStatus(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-loading', type === 'loading');
  }

  function uniqueKey() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return 'mg-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  }

  function pageFromHash() {
    var name = String(window.location.hash || '').replace(/^#payments-/, '');
    return allowedPages.indexOf(name) >= 0 ? name : 'methods';
  }

  function activatePage(name, updateHash) {
    if (allowedPages.indexOf(name) < 0) name = 'methods';

    all('[data-payments-tab]').forEach(function (button) {
      var active = button.dataset.paymentsTab === name;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    all('[data-payments-page]').forEach(function (page) {
      var active = page.dataset.paymentsPage === name;
      page.hidden = !active;
      page.classList.toggle('is-active', active);
    });

    if (updateHash) {
      window.history.replaceState(null, '', '#payments-' + name);
    }
  }

  all('[data-payments-tab]').forEach(function (button) {
    button.addEventListener('click', function () {
      activatePage(button.dataset.paymentsTab || 'methods', true);
    });
  });

  window.addEventListener('hashchange', function () {
    activatePage(pageFromHash(), false);
  });

  async function loadPaymentMethods() {
    var form = one('[data-payment-methods-form]');
    var cashToggle = one('[data-cash-payment-toggle]');
    var stripeToggle = one('[data-stripe-payment-toggle]');
    var status = one('[data-payment-methods-status]');
    var save = one('[data-payment-methods-save]');
    var providerBadge = one('[data-financial-provider]');

    if (!form || !cashToggle || !stripeToggle) return;

    function applyMethods(payload) {
      var methods = payload && payload.payment_methods ? payload.payment_methods : {};
      cashToggle.checked = !!(methods.cash && methods.cash.enabled);
      stripeToggle.checked = !!(methods.stripe && methods.stripe.enabled);

      if (providerBadge) {
        if (stripeToggle.checked) {
          providerBadge.textContent = 'Stripe enabled / onboarding pending';
          providerBadge.classList.add('is-ready');
          providerBadge.classList.remove('is-missing');
        } else if (cashToggle.checked) {
          providerBadge.textContent = 'Cash enabled';
          providerBadge.classList.add('is-ready');
          providerBadge.classList.remove('is-missing');
        } else {
          providerBadge.textContent = 'No payment method enabled';
          providerBadge.classList.remove('is-ready');
          providerBadge.classList.add('is-missing');
        }
      }
    }

    setStatus(status, 'Loading payment methods…', 'loading');
    try {
      var response = await Microgifter.get('/api/merchant/payment-methods.php');
      applyMethods(response.data || response);
      setStatus(status, 'Payment method preferences loaded.', 'success');
    } catch (error) {
      setStatus(status, error.message || 'Unable to load payment methods.', 'error');
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      cashToggle.disabled = true;
      stripeToggle.disabled = true;
      if (save) save.disabled = true;
      setStatus(status, 'Saving payment methods…', 'loading');

      try {
        var response = await Microgifter.post('/api/merchant/payment-methods.php', {
          cash_enabled: cashToggle.checked ? 1 : 0,
          stripe_enabled: stripeToggle.checked ? 1 : 0
        });
        applyMethods(response.data || response);
        setStatus(status, response.message || 'Payment methods saved.', 'success');
      } catch (error) {
        setStatus(status, error.message || 'Unable to save payment methods.', 'error');
      } finally {
        cashToggle.disabled = false;
        stripeToggle.disabled = false;
        if (save) save.disabled = false;
      }
    });
  }

  function renderOrders() {
    var mount = one('[data-financial-orders]');
    var search = one('[data-financial-search]');
    var status = one('[data-financial-status]');
    if (!mount || !search || !status) return;

    var query = String(search.value || '').toLowerCase();
    var selectedStatus = status.value;
    var items = (financialData && financialData.orders ? financialData.orders : []).filter(function (item) {
      return (selectedStatus === 'all' || item.payment_status === selectedStatus)
        && String(item.public_id || '').toLowerCase().indexOf(query) >= 0;
    });

    mount.innerHTML = rows(items, function (item) {
      return '<div class="mg-financial-row">'
        + '<div><strong>' + esc(item.public_id) + '</strong><p>' + Number(item.item_count || 0) + ' items · ' + esc(item.created_at) + '</p>'
        + '<div class="mg-financial-meta"><span>' + esc(item.fulfillment_status) + '</span><span>' + money(item.refunded_cents, item.currency) + ' refunded</span></div></div>'
        + '<div class="mg-financial-amount"><strong>' + money(item.total_cents, item.currency) + '</strong></div>'
        + '<span class="mg-financial-state is-' + esc(item.payment_status) + '">' + esc(item.payment_status) + '</span></div>';
    }, 'No orders match the filters.');
  }

  function renderFinancialData(data) {
    var summary = data.summary || {};
    var kpis = one('[data-financial-kpis]');

    if (kpis) {
      kpis.innerHTML = [
        ['Orders', summary.orders],
        ['Gross', money(summary.gross_cents, 'USD')],
        ['Paid', summary.paid_orders],
        ['Refunded', summary.refunded_orders],
        ['Disputed', summary.disputed_orders]
      ].map(function (item) {
        return '<div class="mg-merchant-kpi"><span>' + esc(item[0]) + '</span><strong>' + esc(item[1] || 0) + '</strong></div>';
      }).join('');
    }

    renderOrders();

    var refunds = one('[data-financial-refunds]');
    if (refunds) {
      refunds.innerHTML = rows(data.refunds, function (item) {
        return '<div class="mg-financial-row"><div><strong>' + esc(item.public_id) + '</strong><p>' + esc(item.reason) + ' · ' + esc(item.created_at) + '</p></div><div class="mg-financial-amount"><strong>' + money(item.amount_cents, item.currency) + '</strong></div><span class="mg-financial-state is-' + esc(item.status) + '">' + esc(item.status) + '</span></div>';
      }, 'No refunds.');
    }

    var payouts = one('[data-financial-payouts]');
    if (payouts) {
      payouts.innerHTML = rows(data.payouts, function (item) {
        return '<div class="mg-financial-row"><div><strong>' + esc(item.public_id) + '</strong><p>' + esc(item.provider_key) + ' · arrival ' + esc(item.arrival_date || 'pending') + '</p></div><div class="mg-financial-amount"><strong>' + money(item.net_cents, item.currency) + '</strong><small>Gross ' + money(item.gross_cents, item.currency) + ' · fees ' + money(item.fee_cents, item.currency) + '</small></div><span class="mg-financial-state is-' + esc(item.status) + '">' + esc(item.status) + '</span></div>';
      }, 'No payouts.');
    }

    var disputes = one('[data-financial-disputes]');
    if (disputes) {
      disputes.innerHTML = rows(data.disputes, function (item) {
        return '<div class="mg-financial-row"><div><strong>' + esc(item.public_id) + '</strong><p>' + esc(item.reason || 'Dispute') + ' · due ' + esc(item.response_due_at || 'n/a') + '</p></div><div class="mg-financial-amount"><strong>' + money(item.amount_cents, item.currency) + '</strong></div><span class="mg-financial-state is-' + esc(item.status) + '">' + esc(item.status) + '</span></div>';
      }, 'No disputes.');
    }

    var ledger = one('[data-financial-ledger]');
    if (ledger) {
      ledger.innerHTML = rows(data.ledger, function (item) {
        return '<div class="mg-financial-row"><div><strong>' + esc(item.account_code) + '</strong><p>' + esc(item.entry_type) + '</p></div><div class="mg-financial-amount"><strong>' + money(item.amount_cents, item.currency) + '</strong></div><span></span></div>';
      }, 'No ledger entries.');
    }

    var reconciliation = one('[data-financial-reconciliation]');
    if (reconciliation) {
      reconciliation.innerHTML = rows(data.reconciliation, function (item) {
        return '<div class="mg-financial-row"><div><strong>' + esc(item.public_id) + '</strong><p>' + esc(item.period_start) + ' → ' + esc(item.period_end) + '</p></div><div class="mg-financial-amount"><strong>' + money(item.difference_cents, 'USD') + '</strong><small>' + Number(item.exception_count || 0) + ' exceptions</small></div><span class="mg-financial-state is-' + esc(item.status) + '">' + esc(item.status) + '</span></div>';
      }, 'No reconciliation runs.');
    }
  }

  async function loadFinancialDashboard() {
    var response = await Microgifter.get('/api/merchant/financial-dashboard.php');
    financialData = response.data || response;
    renderFinancialData(financialData);
  }

  var search = one('[data-financial-search]');
  var paymentStatus = one('[data-financial-status]');
  if (search) search.addEventListener('input', renderOrders);
  if (paymentStatus) paymentStatus.addEventListener('change', renderOrders);

  var refundForm = one('[data-refund-form]');
  if (refundForm) {
    refundForm.elements.idempotency_key.value = uniqueKey();
    refundForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      var status = one('[data-refund-status]');
      var payload = Object.fromEntries(new FormData(refundForm).entries());
      payload.amount_cents = Number(payload.amount_cents);
      setStatus(status, 'Creating refund…', 'loading');

      try {
        var response = await Microgifter.post('/api/merchant/refund.php', payload);
        setStatus(status, response.message || 'Refund created.', 'success');
        refundForm.reset();
        refundForm.elements.idempotency_key.value = uniqueKey();
        await loadFinancialDashboard();
      } catch (error) {
        setStatus(status, error.message || 'Unable to create refund.', 'error');
      }
    });
  }

  var reconciliationForm = one('[data-reconciliation-form]');
  if (reconciliationForm) {
    var today = new Date().toISOString().slice(0, 10);
    var past = new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10);
    reconciliationForm.elements.from.value = past;
    reconciliationForm.elements.to.value = today;
    reconciliationForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      var status = one('[data-reconciliation-status]');
      setStatus(status, 'Reconciling…', 'loading');

      try {
        var response = await Microgifter.post('/api/merchant/reconciliation.php', Object.fromEntries(new FormData(reconciliationForm).entries()));
        setStatus(status, response.message || 'Reconciliation completed.', 'success');
        await loadFinancialDashboard();
      } catch (error) {
        setStatus(status, error.message || 'Unable to run reconciliation.', 'error');
      }
    });
  }

  activatePage(pageFromHash(), false);
  loadPaymentMethods();
  loadFinancialDashboard().catch(function (error) {
    console.error(error);
    var kpis = one('[data-financial-kpis]');
    if (kpis) kpis.innerHTML = '<div class="mg-empty-state">Unable to load payment metrics.</div>';
  });
});
