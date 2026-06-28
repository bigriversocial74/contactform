window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get) return;

  var layer = root.querySelector('[data-canvas-customers]');
  if (!layer) return;

  var drawer = null;
  var activeZoneId = '';
  var refreshTimer = null;

  function payload(response) { return response && response.data ? response.data : response; }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function fmt(value) {
    var raw = String(value || '').trim();
    if (!raw) return '—';
    return raw.replace('T', ' ').slice(0, 19);
  }

  function ensureDrawer() {
    if (drawer && drawer.isConnected) return drawer;
    drawer = document.createElement('aside');
    drawer.className = 'mg-canvas-trigger-analytics-drawer';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.innerHTML = '<div class="mg-trigger-analytics-head"><div><span>Trigger Analytics</span><h2 data-trigger-analytics-title>Select a trigger</h2></div><button type="button" data-trigger-analytics-close aria-label="Close trigger analytics">×</button></div><div class="mg-trigger-analytics-body" data-trigger-analytics-body><p>Select a trigger zone to review performance.</p></div>';
    root.appendChild(drawer);
    drawer.addEventListener('click', function (event) {
      if (event.target.closest('[data-trigger-analytics-close]')) closeDrawer();
    });
    return drawer;
  }

  function injectButtons() {
    Array.from(layer.querySelectorAll('[data-canvas-persistent-zone]')).forEach(function (zoneEl) {
      var actions = zoneEl.querySelector('.mg-canvas-trigger-actions');
      if (!actions || actions.querySelector('[data-trigger-analytics-open]')) return;
      var button = document.createElement('button');
      button.type = 'button';
      button.title = 'View trigger analytics';
      button.setAttribute('data-trigger-analytics-open', '');
      button.textContent = '↗';
      actions.insertBefore(button, actions.firstChild);
    });
  }

  function openDrawer() {
    ensureDrawer();
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    activeZoneId = '';
    if (refreshTimer) window.clearInterval(refreshTimer);
    refreshTimer = null;
  }

  function renderLoading(zoneEl) {
    var title = ensureDrawer().querySelector('[data-trigger-analytics-title]');
    var body = ensureDrawer().querySelector('[data-trigger-analytics-body]');
    var name = zoneEl.querySelector('[data-trigger-name]') ? zoneEl.querySelector('[data-trigger-name]').value : 'Trigger zone';
    title.textContent = name || 'Trigger zone';
    body.innerHTML = '<div class="mg-trigger-analytics-loading">Loading trigger analytics...</div>';
  }

  function renderError(message) {
    var body = ensureDrawer().querySelector('[data-trigger-analytics-body]');
    body.innerHTML = '<div class="mg-trigger-analytics-error">' + esc(message || 'Unable to load trigger analytics.') + '</div>';
  }

  function renderAnalytics(data) {
    var zone = data.zone || {};
    var stats = data.stats || {};
    var events = Array.isArray(data.events) ? data.events : [];
    var title = ensureDrawer().querySelector('[data-trigger-analytics-title]');
    var body = ensureDrawer().querySelector('[data-trigger-analytics-body]');
    title.textContent = zone.name || 'Trigger zone';
    var statCards = [
      ['Fires', stats.fires || 0],
      ['Customers', stats.unique_customers || 0],
      ['Messages', stats.messages_sent || 0],
      ['Rewards', stats.rewards_sent || 0],
      ['Stamp debits', stats.stamp_debits || 0],
      ['Debit errors', stats.stamp_debit_errors || 0]
    ].map(function (item) {
      return '<article><span>' + esc(item[0]) + '</span><strong>' + esc(item[1]) + '</strong></article>';
    }).join('');
    var eventRows = events.length ? events.map(function (event) {
      var badges = [];
      if (event.message_sent) badges.push('<b>message</b>');
      if (event.reward_sent) badges.push('<b>reward</b>');
      if (event.stamp_debited) badges.push('<b>stamp</b>');
      if (event.stamp_debit_error) badges.push('<b class="is-warn">stamp issue</b>');
      return '<article class="mg-trigger-event"><div><strong>' + esc(event.customer_name || 'Customer') + '</strong><span>' + esc(fmt(event.created_at)) + '</span></div><p>' + esc(event.campaign_title || event.event_label || 'Campaign trigger zone') + '</p><footer>' + (badges.length ? badges.join('') : '<b>event</b>') + '</footer></article>';
    }).join('') : '<div class="mg-trigger-analytics-empty">No trigger fires yet. Move a customer avatar through this zone to create analytics.</div>';
    body.innerHTML = '<section class="mg-trigger-zone-summary"><div><span>Assigned campaign</span><strong>' + esc(zone.campaign_title || 'No active campaign assigned') + '</strong></div><div><span>Priority</span><strong>' + esc(zone.priority || 3) + '</strong></div><div><span>Last triggered</span><strong>' + esc(fmt(stats.last_triggered_at || zone.last_triggered_at)) + '</strong></div></section><section class="mg-trigger-stat-grid">' + statCards + '</section><section class="mg-trigger-ledger-note"><strong>Stamp Ledger</strong><span>Automated trigger messages debit the <code>' + esc(data.stamp_action_key || 'store_canvas_auto_message_send') + '</code> action when the merchant has available Stamps.</span></section><section class="mg-trigger-events"><h3>Recent trigger events</h3>' + eventRows + '</section>';
  }

  async function loadAnalytics(zoneId, zoneEl) {
    if (!zoneId) return;
    activeZoneId = zoneId;
    openDrawer();
    renderLoading(zoneEl || layer.querySelector('[data-canvas-trigger-zone="' + CSS.escape(zoneId) + '"]'));
    try {
      var data = payload(await MG.get('/api/merchant-canvas/trigger-zone-analytics.php?zone_id=' + encodeURIComponent(zoneId))) || {};
      if (activeZoneId === zoneId) renderAnalytics(data);
    } catch (error) {
      if (activeZoneId === zoneId) renderError(error.message || 'Unable to load trigger analytics.');
    }
    if (refreshTimer) window.clearInterval(refreshTimer);
    refreshTimer = window.setInterval(function () {
      if (activeZoneId === zoneId && drawer && drawer.classList.contains('is-open')) {
        MG.get('/api/merchant-canvas/trigger-zone-analytics.php?zone_id=' + encodeURIComponent(zoneId)).then(function (response) {
          if (activeZoneId === zoneId) renderAnalytics(payload(response) || {});
        }).catch(function () {});
      }
    }, 12000);
  }

  layer.addEventListener('click', function (event) {
    var button = event.target.closest('[data-trigger-analytics-open]');
    if (!button) return;
    var zoneEl = button.closest('[data-canvas-persistent-zone]');
    var zoneId = zoneEl ? zoneEl.dataset.canvasTriggerZone : '';
    loadAnalytics(zoneId, zoneEl);
    event.preventDefault();
    event.stopPropagation();
  });

  var observer = new MutationObserver(injectButtons);
  observer.observe(layer, { childList: true, subtree: true });
  ensureDrawer();
  injectButtons();
})(window, document);
