document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-app][data-merchant-view="orders"]');
  if (!root || !window.Microgifter) return;

  var ui = {
    form: root.querySelector('[data-orders-filters]'),
    list: root.querySelector('[data-orders-list]'),
    kpis: root.querySelector('[data-orders-kpis]'),
    summary: root.querySelector('[data-orders-summary]'),
    updated: root.querySelector('[data-orders-updated]'),
    live: root.querySelector('[data-orders-live]'),
    loading: root.querySelector('[data-orders-loading]'),
    error: root.querySelector('[data-orders-error]'),
    errorMessage: root.querySelector('[data-orders-error-message]'),
    empty: root.querySelector('[data-orders-empty]'),
    content: root.querySelector('[data-orders-content]'),
    pagination: root.querySelector('[data-orders-pagination]'),
    pageLabel: root.querySelector('[data-orders-page-label]'),
    prev: root.querySelector('[data-orders-prev]'),
    next: root.querySelector('[data-orders-next]'),
    refresh: root.querySelector('[data-orders-refresh]'),
    retry: root.querySelector('[data-orders-retry]'),
    drawerLayer: root.querySelector('[data-orders-drawer-layer]'),
    drawer: root.querySelector('[data-orders-drawer]'),
    drawerTitle: root.querySelector('[data-orders-drawer-title]'),
    drawerSubtitle: root.querySelector('[data-orders-drawer-subtitle]'),
    detailLoading: root.querySelector('[data-orders-detail-loading]'),
    detailError: root.querySelector('[data-orders-detail-error]'),
    detailErrorMessage: root.querySelector('[data-orders-detail-error-message]'),
    detail: root.querySelector('[data-orders-detail]'),
    facts: root.querySelector('[data-orders-facts]'),
    issuance: root.querySelector('[data-orders-issuance]'),
    deliveryState: root.querySelector('[data-orders-delivery-state]'),
    items: root.querySelector('[data-orders-items]'),
    payments: root.querySelector('[data-orders-payments]'),
    timeline: root.querySelector('[data-orders-timeline]'),
    reconcile: root.querySelector('[data-orders-reconcile]'),
    reconcileStatus: root.querySelector('[data-orders-reconcile-status]'),
    detailRetry: root.querySelector('[data-orders-detail-retry]')
  };

  if (!ui.form || !ui.list || !ui.loading || !ui.error) return;

  var state = {
    page: 1,
    limit: 25,
    hasMore: false,
    currentId: '',
    current: null,
    lastFocus: null,
    listRequest: 0,
    detailRequest: 0,
    controllers: {},
    hasRenderedOrders: false,
    summaryLoaded: false
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }

  function statusClass(value) {
    return String(value || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
  }

  function label(value) {
    return String(value || '—').replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); });
  }

  function money(cents, currency) {
    if (currency === 'MIXED') {
      return (Number(cents || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' mixed';
    }
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(cents || 0) / 100);
    } catch (error) {
      return (Number(cents || 0) / 100).toFixed(2) + ' ' + (currency || 'USD');
    }
  }

  function dateTime(value) {
    if (!value) return '—';
    var raw = String(value);
    var normalized = raw;
    if (!(/[zZ]$|[+-]\d{2}:?\d{2}$/.test(raw))) {
      normalized = raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T');
      normalized += 'Z';
    }
    var date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  }

  function setLive(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
  }

  function show(node, visible) {
    if (node) node.hidden = !visible;
  }

  function status(value) {
    return '<span class="mg-orders-status is-' + esc(statusClass(value)) + '">' + esc(label(value)) + '</span>';
  }

  function abortRequest(key) {
    var controller = state.controllers[key];
    if (controller) {
      try { controller.abort(); } catch (error) {}
      delete state.controllers[key];
    }
  }

  function timeoutError(message) {
    var error = new Error(message);
    error.code = 'MG_REQUEST_TIMEOUT';
    return error;
  }

  async function getWithTimeout(path, timeoutMs, key, timeoutMessage) {
    abortRequest(key);
    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    if (controller) state.controllers[key] = controller;
    var timedOut = false;
    var timer = window.setTimeout(function () {
      timedOut = true;
      if (controller) controller.abort();
    }, timeoutMs);

    try {
      return await Microgifter.get(path, controller ? { signal: controller.signal } : {});
    } catch (error) {
      if (timedOut) throw timeoutError(timeoutMessage || 'The request took too long. Please try again.');
      if (controller && controller.signal.aborted) {
        var aborted = new Error('Request replaced by a newer request.');
        aborted.code = 'MG_REQUEST_ABORTED';
        throw aborted;
      }
      throw error;
    } finally {
      window.clearTimeout(timer);
      if (state.controllers[key] === controller) delete state.controllers[key];
    }
  }

  async function postWithTimeout(path, payload, timeoutMs, key, timeoutMessage) {
    abortRequest(key);
    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    if (controller) state.controllers[key] = controller;
    var timedOut = false;
    var timer = window.setTimeout(function () {
      timedOut = true;
      if (controller) controller.abort();
    }, timeoutMs);

    try {
      return await Microgifter.post(path, payload, controller ? { signal: controller.signal } : {});
    } catch (error) {
      if (timedOut) throw timeoutError(timeoutMessage || 'The request took too long. Please try again.');
      if (controller && controller.signal.aborted) {
        var aborted = new Error('Request replaced by a newer request.');
        aborted.code = 'MG_REQUEST_ABORTED';
        throw aborted;
      }
      throw error;
    } finally {
      window.clearTimeout(timer);
      if (state.controllers[key] === controller) delete state.controllers[key];
    }
  }

  function filters() {
    var data = new FormData(ui.form);
    var params = new URLSearchParams();
    ['q', 'payment_status', 'fulfillment_status', 'date_from', 'date_to'].forEach(function (key) {
      var value = String(data.get(key) || '').trim();
      if (value) params.set(key, value);
    });
    if (data.get('attention')) params.set('attention', '1');
    params.set('page', String(state.page));
    params.set('limit', String(state.limit));
    params.set('include_summary', '0');
    return params;
  }

  function renderKpis(summary) {
    summary = summary || {};
    var cards = [
      ['Orders', summary.total_orders || 0, 'All merchant orders'],
      ['Paid', summary.paid_orders || 0, 'Captured orders'],
      ['Needs attention', summary.attention_orders || 0, 'Payment or delivery'],
      ['Delivered', summary.fulfilled_orders || 0, 'Complete issuance'],
      ['Paid volume', money(summary.paid_volume_cents, summary.currency), money(summary.refunded_cents, summary.currency) + ' refunded']
    ];
    ui.kpis.innerHTML = cards.map(function (card) {
      return '<article class="mg-orders-kpi"><span>' + esc(card[0]) + '</span><strong>' + esc(card[1]) + '</strong><small>' + esc(card[2]) + '</small></article>';
    }).join('');
  }

  function renderKpiError(message) {
    ui.kpis.innerHTML = '<article class="mg-orders-kpi mg-orders-kpi-wide"><span>Order summary</span><strong>Unavailable</strong><small>' + esc(message) + '</small></article>';
  }

  function renderOrders(data) {
    var orders = data.orders || [];
    state.hasMore = Boolean(data.has_more);
    if (data.summary) {
      renderKpis(data.summary);
      state.summaryLoaded = true;
    }
    ui.summary.textContent = orders.length + ' order' + (orders.length === 1 ? '' : 's') + ' on page ' + state.page + (state.hasMore ? ' · more available' : '');
    ui.updated.textContent = dateTime(data.generated_at || (data.summary || {}).generated_at);
    ui.list.innerHTML = orders.map(function (order) {
      var delivery = order.issued_units + '/' + order.unit_count + ' units';
      return '<tr class="' + (order.attention ? 'mg-orders-row-attention' : '') + '">'
        + '<td><div class="mg-orders-order-id"><strong>' + esc(order.order_id) + '</strong><small>' + esc(order.line_count) + ' lines · ' + esc(order.unit_count) + ' units</small></div></td>'
        + '<td><div class="mg-orders-customer"><strong>' + esc(order.customer && order.customer.display_name || 'Customer') + '</strong><small>' + esc(order.customer && order.customer.email_masked || 'Identity protected') + '</small></div></td>'
        + '<td>' + status(order.payment_status) + '</td>'
        + '<td><div class="mg-orders-delivery">' + status(order.fulfillment_status) + '<small>' + esc(delivery) + '</small></div></td>'
        + '<td><strong>' + esc(money(order.total_cents, order.currency)) + '</strong><small>' + (order.refunded_cents ? esc(money(order.refunded_cents, order.currency)) + ' refunded' : '') + '</small></td>'
        + '<td>' + esc(dateTime(order.updated_at)) + '</td>'
        + '<td><button class="mg-btn mg-btn-soft" type="button" data-order-open="' + esc(order.order_id) + '">Inspect</button></td>'
        + '</tr>';
    }).join('');

    state.hasRenderedOrders = true;
    show(ui.loading, false);
    show(ui.error, false);
    show(ui.empty, orders.length === 0);
    show(ui.content, orders.length > 0);
    show(ui.pagination, orders.length > 0 || state.page > 1);
    ui.pageLabel.textContent = 'Page ' + state.page;
    ui.prev.disabled = state.page <= 1;
    ui.next.disabled = !state.hasMore;
  }

  async function loadSummary(force) {
    if (state.summaryLoaded && !force) return;
    try {
      var response = await getWithTimeout(
        '/api/merchant/commerce-orders-summary.php',
        7000,
        'summary',
        'The order summary took too long to load.'
      );
      var data = response.data || response;
      renderKpis(data.summary || {});
      state.summaryLoaded = true;
    } catch (error) {
      if (error.code === 'MG_REQUEST_ABORTED') return;
      renderKpiError(error.message || 'Unable to load the order summary.');
    }
  }

  async function loadOrders(announce) {
    var requestId = ++state.listRequest;
    abortRequest('list');
    ui.refresh.disabled = true;
    root.setAttribute('aria-busy', 'true');
    if (!state.hasRenderedOrders) {
      show(ui.loading, true);
      show(ui.content, false);
      show(ui.empty, false);
      show(ui.pagination, false);
    }
    show(ui.error, false);
    setLive(ui.live, announce ? 'Refreshing orders…' : (state.hasRenderedOrders ? 'Loading orders…' : ''));

    try {
      var response = await getWithTimeout(
        '/api/merchant/commerce-orders.php?' + filters().toString(),
        12000,
        'list',
        'The order list took too long to load. The request was stopped so this page can remain responsive.'
      );
      if (requestId !== state.listRequest) return;
      var data = response.data || response;
      renderOrders(data);
      var elapsed = Number(data.performance && data.performance.elapsed_ms || 0);
      var message = announce ? 'Orders refreshed.' : '';
      if (elapsed > 0) message = (message ? message + ' ' : '') + 'Loaded in ' + elapsed.toLocaleString() + ' ms.';
      setLive(ui.live, message, 'success');
    } catch (error) {
      if (requestId !== state.listRequest || error.code === 'MG_REQUEST_ABORTED') return;
      show(ui.loading, false);
      if (!state.hasRenderedOrders) {
        show(ui.content, false);
        show(ui.empty, false);
        show(ui.pagination, false);
      }
      show(ui.error, true);
      ui.errorMessage.textContent = error.message || 'Unable to load merchant orders.';
      setLive(ui.live, '', 'error');
    } finally {
      if (requestId === state.listRequest) {
        root.setAttribute('aria-busy', 'false');
        ui.refresh.disabled = false;
      }
    }
  }

  function fact(name, value) {
    return '<div class="mg-orders-fact"><span>' + esc(name) + '</span><strong>' + esc(value == null ? '—' : value) + '</strong></div>';
  }

  function renderIssuance(issuance) {
    var missing = issuance.missing || {};
    ui.deliveryState.innerHTML = status(issuance.state || 'pending');
    ui.issuance.innerHTML = '<div class="mg-orders-issuance-grid">'
      + [['Expected', issuance.expected_units || 0], ['PPPM', issuance.pppm_items || 0], ['Microgifts', issuance.microgifts || 0], ['Action Center', issuance.action_center_items || 0]].map(function (card) {
        return '<div class="mg-orders-issuance-card"><span>' + esc(card[0]) + '</span><strong>' + esc(card[1]) + '</strong></div>';
      }).join('')
      + '</div>'
      + (!issuance.complete ? '<p class="mg-orders-payment-meta">Missing: ' + esc(missing.pppm || 0) + ' PPPM · ' + esc(missing.microgifts || 0) + ' Microgifts · ' + esc(missing.action_center || 0) + ' Action Center</p>' : '');
  }

  function renderItems(items) {
    ui.items.innerHTML = items && items.length ? items.map(function (item) {
      var issue = item.issuance || {};
      return '<article class="mg-orders-item"><div class="mg-orders-item-head"><div><a href="' + esc(item.product_url) + '" target="_blank" rel="noopener"><strong>' + esc(item.title) + '</strong></a><div class="mg-orders-item-meta"><span>' + esc(item.quantity) + ' units</span><span>' + esc(money(item.unit_amount_cents, item.currency)) + ' each</span><span>' + esc(money(item.line_total_cents, item.currency)) + ' line total</span></div></div><span>' + esc(item.item_id) + '</span></div><div class="mg-orders-line-progress"><span>PPPM ' + esc(issue.pppm_items || 0) + ' / ' + esc(issue.expected_units || 0) + '</span><span>Microgifts ' + esc(issue.microgifts || 0) + ' / ' + esc(issue.expected_units || 0) + '</span><span>Action Center ' + esc(issue.action_center_items || 0) + ' / ' + esc(issue.expected_units || 0) + '</span></div></article>';
    }).join('') : '<div class="mg-orders-state"><strong>No order items</strong></div>';
  }

  function paymentRows(items, kind) {
    return items && items.length ? items.map(function (item) {
      var title = kind === 'intent'
        ? (item.provider_key + ' · ' + label(item.status))
        : kind === 'transaction'
          ? (label(item.transaction_type) + ' · ' + label(item.status))
          : kind === 'refund'
            ? ('Refund · ' + label(item.status))
            : ('Dispute · ' + label(item.status));
      var amount = item.amount_cents != null ? money(item.amount_cents, item.currency) : '';
      var meta = [item.reason, item.failure_code, item.failure_message, dateTime(item.processed_at || item.occurred_at || item.response_due_at || item.created_at)].filter(Boolean).join(' · ');
      return '<div class="mg-orders-payment-row"><div><strong>' + esc(title) + '</strong><div class="mg-orders-payment-meta">' + esc(meta) + '</div></div><strong>' + esc(amount) + '</strong></div>';
    }).join('') : '<div class="mg-orders-payment-meta">No records.</div>';
  }

  function renderPayments(payments) {
    payments = payments || {};
    ui.payments.innerHTML = '<div class="mg-orders-payment-group"><h4>Payment attempts</h4>' + paymentRows(payments.intents, 'intent') + '</div>'
      + '<div class="mg-orders-payment-group"><h4>Transactions</h4>' + paymentRows(payments.transactions, 'transaction') + '</div>'
      + '<div class="mg-orders-payment-group"><h4>Refunds</h4>' + paymentRows(payments.refunds, 'refund') + '</div>'
      + '<div class="mg-orders-payment-group"><h4>Disputes</h4>' + paymentRows(payments.disputes, 'dispute') + '</div>';
  }

  function renderTimeline(data) {
    var events = [];
    (data.history || []).forEach(function (item) {
      events.push({ date: item.created_at, title: label(item.status_domain) + ' status: ' + label(item.from_status) + ' → ' + label(item.to_status), meta: item.reason_code || item.actor_type });
    });
    (data.audit_events || []).forEach(function (item) {
      events.push({ date: item.created_at, title: label(String(item.event_type).replace(/\./g, '_')), meta: 'Audited event' });
    });
    ((data.payments || {}).transactions || []).forEach(function (item) {
      events.push({ date: item.occurred_at, title: label(item.transaction_type) + ' payment transaction', meta: label(item.status) });
    });
    ((data.payments || {}).refunds || []).forEach(function (item) {
      events.push({ date: item.processed_at || item.created_at, title: 'Refund ' + label(item.status), meta: item.reason || '' });
    });
    events.sort(function (a, b) {
      return new Date(String(b.date).replace(' ', 'T')).getTime() - new Date(String(a.date).replace(' ', 'T')).getTime();
    });
    ui.timeline.innerHTML = events.length ? events.map(function (event) {
      return '<div class="mg-orders-timeline-row"><time>' + esc(dateTime(event.date)) + '</time><div><strong>' + esc(event.title) + '</strong><div class="mg-orders-payment-meta">' + esc(event.meta || '') + '</div></div></div>';
    }).join('') : '<div class="mg-orders-payment-meta">No lifecycle events recorded.</div>';
  }

  function renderDetail(data) {
    state.current = data;
    var order = data.order || {};
    var customer = order.customer || {};
    ui.drawerTitle.textContent = 'Order ' + String(order.order_id || '').slice(0, 12);
    ui.drawerSubtitle.textContent = (customer.display_name || 'Customer') + ' · ' + (customer.email_masked || 'Identity protected');
    ui.facts.innerHTML = [
      ['Payment', label(order.payment_status)],
      ['Delivery', label(order.fulfillment_status)],
      ['Total', money(order.total_cents, order.currency)],
      ['Subtotal', money(order.subtotal_cents, order.currency)],
      ['Platform fee', money(order.platform_fee_cents, order.currency)],
      ['Source', label(order.source_type)],
      ['Paid', dateTime(order.paid_at)],
      ['Created', dateTime(order.created_at)],
      ['Updated', dateTime(order.updated_at)]
    ].map(function (row) { return fact(row[0], row[1]); }).join('');
    renderIssuance(data.issuance || {});
    renderItems(data.items || []);
    renderPayments(data.payments || {});
    renderTimeline(data);
    ui.reconcile.hidden = !order.can_reconcile;
    ui.reconcile.disabled = false;
    setLive(ui.reconcileStatus, order.can_reconcile
      ? (data.issuance && data.issuance.complete ? 'Delivery is complete. Verification is safe to repeat.' : 'Delivery needs verification or repair.')
      : 'Only paid orders can reconcile delivery.');
    show(ui.detailLoading, false);
    show(ui.detailError, false);
    show(ui.detail, true);
  }

  async function loadDetail(orderId) {
    var requestId = ++state.detailRequest;
    state.currentId = orderId;
    show(ui.detailLoading, true);
    show(ui.detailError, false);
    show(ui.detail, false);
    try {
      var response = await getWithTimeout(
        '/api/merchant/commerce-order.php?order_id=' + encodeURIComponent(orderId),
        12000,
        'detail',
        'The order details took too long to load.'
      );
      if (requestId !== state.detailRequest || orderId !== state.currentId) return;
      renderDetail(response.data || response);
    } catch (error) {
      if (requestId !== state.detailRequest || error.code === 'MG_REQUEST_ABORTED') return;
      show(ui.detailLoading, false);
      show(ui.detail, false);
      show(ui.detailError, true);
      ui.detailErrorMessage.textContent = error.message || 'Unable to load order detail.';
    }
  }

  function openDrawer(orderId, trigger) {
    if (!orderId) return;
    state.lastFocus = trigger || document.activeElement;
    show(ui.drawerLayer, true);
    document.body.style.overflow = 'hidden';
    loadDetail(orderId);
    requestAnimationFrame(function () { ui.drawer.focus(); });
  }

  function closeDrawer() {
    abortRequest('detail');
    state.detailRequest++;
    show(ui.drawerLayer, false);
    document.body.style.overflow = '';
    state.currentId = '';
    state.current = null;
    if (state.lastFocus && typeof state.lastFocus.focus === 'function') state.lastFocus.focus();
  }

  function requestKey(orderId) {
    var storageKey = 'mg-commerce-order-reconcile:' + orderId;
    var key = '';
    try { key = sessionStorage.getItem(storageKey) || ''; } catch (error) {}
    if (!key) {
      key = 'merchant-order:' + orderId + ':' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2));
      try { sessionStorage.setItem(storageKey, key); } catch (error) {}
    }
    return key;
  }

  async function reconcile() {
    if (!state.current || !state.current.order || !state.current.order.can_reconcile) return;
    var orderId = state.current.order.order_id;
    ui.reconcile.disabled = true;
    setLive(ui.reconcileStatus, 'Verifying and repairing delivery…');
    try {
      var response = await postWithTimeout(
        '/api/merchant/commerce-order-reconcile.php',
        { order_id: orderId, request_key: requestKey(orderId) },
        30000,
        'reconcile',
        'Delivery verification took too long. The operation may still be completing; refresh the order before retrying.'
      );
      setLive(ui.reconcileStatus, response.message || 'Order delivery verified.', 'success');
      await loadDetail(orderId);
      loadOrders(false);
      loadSummary(true);
    } catch (error) {
      if (error.code === 'MG_REQUEST_ABORTED') return;
      setLive(ui.reconcileStatus, error.message || 'Unable to reconcile order delivery.', 'error');
      ui.reconcile.disabled = false;
    }
  }

  ui.form.addEventListener('submit', function (event) {
    event.preventDefault();
    state.page = 1;
    loadOrders(false);
  });

  ui.form.addEventListener('reset', function () {
    setTimeout(function () {
      state.page = 1;
      loadOrders(false);
    }, 0);
  });

  ui.refresh.addEventListener('click', function () {
    loadOrders(true);
    loadSummary(true);
  });
  ui.retry.addEventListener('click', function () { loadOrders(false); });
  ui.prev.addEventListener('click', function () {
    if (state.page > 1) {
      state.page--;
      loadOrders(false);
    }
  });
  ui.next.addEventListener('click', function () {
    if (state.hasMore) {
      state.page++;
      loadOrders(false);
    }
  });
  ui.list.addEventListener('click', function (event) {
    var button = event.target.closest('[data-order-open]');
    if (button) openDrawer(button.getAttribute('data-order-open') || '', button);
  });
  root.querySelectorAll('[data-orders-close]').forEach(function (button) {
    button.addEventListener('click', closeDrawer);
  });
  ui.detailRetry.addEventListener('click', function () {
    if (state.currentId) loadDetail(state.currentId);
  });
  ui.reconcile.addEventListener('click', reconcile);
  ui.drawer.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeDrawer();
      return;
    }
    if (event.key !== 'Tab') return;
    var focusable = Array.from(ui.drawer.querySelectorAll('button:not([disabled]):not([hidden]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function (node) { return !node.hidden; });
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  renderKpis({});
  loadOrders(false);
  loadSummary(false);
});
