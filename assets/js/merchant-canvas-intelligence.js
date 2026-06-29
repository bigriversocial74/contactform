window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get || !MG.post) return;
  var map = root.querySelector('[data-canvas-map]');
  if (!map) return;

  var state = { mode: 'live', activityOpen: false, safetyOpen: false, data: null, saving: false };
  var queued = false;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]; }); }
  function text(node) { return String(node && node.textContent || '').replace(/\s+/g, ' ').trim(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }

  function ensureChrome() {
    if (!root.querySelector('[data-canvas-mode-bar]')) {
      var bar = document.createElement('nav');
      bar.className = 'mg-canvas-mode-bar';
      bar.setAttribute('data-canvas-mode-bar', '');
      bar.innerHTML = ['live:Live View','edit:Edit Zones','campaigns:Campaign Map','paths:Customer Paths','analytics:Analytics'].map(function (item) {
        var parts = item.split(':');
        return '<button type="button" data-canvas-mode="' + parts[0] + '">' + parts[1] + '</button>';
      }).join('') + '<button type="button" data-canvas-open-activity>Activity</button><button type="button" data-canvas-open-safety>Safety</button>';
      var command = root.querySelector('.mg-canvas-command-strip');
      if (command && command.parentElement) command.parentElement.insertBefore(bar, command.nextSibling);
    }
    if (!map.querySelector('[data-canvas-path-layer]')) {
      var path = document.createElement('div');
      path.className = 'mg-canvas-path-layer';
      path.setAttribute('data-canvas-path-layer', '');
      path.innerHTML = '<span></span><span></span><span></span><span></span>';
      map.appendChild(path);
    }
    if (!document.querySelector('[data-canvas-activity-drawer]')) {
      var activity = document.createElement('aside');
      activity.className = 'mg-canvas-intel-drawer';
      activity.setAttribute('data-canvas-activity-drawer', '');
      activity.innerHTML = '<header><div><span>Live Activity</span><h2>Store Canvas Feed</h2></div><button type="button" data-canvas-close-activity>×</button></header><div data-canvas-activity-feed></div>';
      document.body.appendChild(activity);
    }
    if (!document.querySelector('[data-canvas-safety-drawer]')) {
      var safety = document.createElement('aside');
      safety.className = 'mg-canvas-intel-drawer';
      safety.setAttribute('data-canvas-safety-drawer', '');
      safety.innerHTML = '<header><div><span>Automation Safety</span><h2>Canvas Guardrails</h2></div><button type="button" data-canvas-close-safety>×</button></header><div data-canvas-safety-body></div>';
      document.body.appendChild(safety);
    }
  }

  async function loadIntel() {
    try {
      var data = payload(await MG.get('/api/merchant-canvas/intelligence.php')) || {};
      state.data = data;
      var settings = data.settings || {};
      state.mode = settings.canvas_mode || state.mode || 'live';
      state.activityOpen = !!settings.activity_drawer_open;
      state.safetyOpen = !!settings.safety_drawer_open;
      render();
    } catch (error) {
      toast(error.message || 'Unable to load Store Canvas intelligence.', 'error');
      render();
    }
  }

  function saveSettings() {
    if (state.saving) return;
    state.saving = true;
    MG.post('/api/merchant-canvas/intelligence-settings-save.php', {
      canvas_mode: state.mode,
      activity_drawer_open: state.activityOpen ? 1 : 0,
      safety_drawer_open: state.safetyOpen ? 1 : 0,
      overlay_zone_metrics: 1,
      overlay_customer_paths: 1,
      overlay_customer_badges: 1,
      metadata: { source: 'merchant_canvas_intelligence_ui' }
    }).then(function (response) {
      var data = payload(response) || {};
      if (data.settings && state.data) state.data.settings = data.settings;
    }).catch(function () {}).finally(function () { state.saving = false; });
  }

  function setMode(mode) {
    state.mode = mode || 'live';
    root.dataset.canvasMode = state.mode;
    qsa('[data-canvas-mode]', root).forEach(function (button) { button.classList.toggle('is-active', button.dataset.canvasMode === state.mode); });
  }

  function zoneMetricFor(zoneId) {
    var zones = state.data && Array.isArray(state.data.zone_metrics) ? state.data.zone_metrics : [];
    return zones.find(function (zone) { return String(zone.id) === String(zoneId); }) || null;
  }

  function decorateZones() {
    qsa('[data-canvas-persistent-zone]', root).forEach(function (zone) {
      var zoneId = zone.dataset.canvasTriggerZone || '';
      var metric = zoneMetricFor(zoneId);
      var today = metric && metric.today ? metric.today : { fires: 0, cooldown_blocks: 0, rewards_sent: 0 };
      var host = zone.querySelector('[data-zone-intel]');
      if (!host) {
        host = document.createElement('span');
        host.className = 'mg-zone-intel';
        host.setAttribute('data-zone-intel', '');
        zone.appendChild(host);
      }
      host.innerHTML = '<b>' + esc(today.fires || 0) + '</b><small>fires</small><b>' + esc(today.cooldown_blocks || 0) + '</b><small>blocks</small><b>' + esc(today.rewards_sent || 0) + '</b><small>rewards</small>';
    });
  }

  function customerStatus(card) {
    var content = text(card).toLowerCase();
    if (content.indexOf('reward') !== -1) return 'Reward Sent';
    if (content.indexOf('message') !== -1 || content.indexOf('chat') !== -1) return 'Engaged';
    if (card.classList.contains('is-idle')) return 'Idle';
    if (content.indexOf('test') !== -1) return 'Test Avatar';
    return 'Browsing';
  }

  function decorateCustomers() {
    qsa('.mg-canvas-avatar-card[data-session-id]', root).forEach(function (card) {
      var badge = card.querySelector('[data-customer-intent]');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'mg-customer-intent-badge';
        badge.setAttribute('data-customer-intent', '');
        card.appendChild(badge);
      }
      badge.textContent = customerStatus(card);
      var journey = latestJourney(card.dataset.sessionId || '');
      var pill = card.querySelector('[data-customer-journey]');
      if (!pill) {
        pill = document.createElement('span');
        pill.className = 'mg-customer-journey-pill';
        pill.setAttribute('data-customer-journey', '');
        card.appendChild(pill);
      }
      pill.textContent = journey;
    });
  }

  function latestJourney(sessionId) {
    var journeys = state.data && Array.isArray(state.data.journeys) ? state.data.journeys : [];
    var found = journeys.find(function (journey) { return String(journey.session_id) === String(sessionId); });
    if (!found || !Array.isArray(found.events) || !found.events.length) return 'Entered → Canvas';
    var labels = found.events.slice(-4).map(function (event) {
      var type = String(event.type || 'event');
      if (type.indexOf('entered') !== -1) return 'Entered';
      if (type.indexOf('trigger') !== -1) return 'Zone';
      if (type.indexOf('message') !== -1) return 'Chat';
      if (type.indexOf('reward') !== -1) return 'Reward';
      if (type.indexOf('claim') !== -1) return 'Claim';
      if (type.indexOf('exit') !== -1) return 'Exit';
      return event.label || 'Event';
    });
    return labels.join(' → ');
  }

  function renderActivity() {
    var drawer = qs('[data-canvas-activity-drawer]');
    if (!drawer) return;
    drawer.classList.toggle('is-open', state.activityOpen);
    var body = qs('[data-canvas-activity-feed]', drawer);
    var items = state.data && Array.isArray(state.data.activity) ? state.data.activity : [];
    if (body) body.innerHTML = (items.length ? items : [{ label: 'Waiting for Store Canvas events', created_at: 'Now' }]).map(function (item) {
      return '<article><strong>' + esc(item.label || item.type || 'Store event') + '</strong><span>' + esc(item.created_at || '') + '</span></article>';
    }).join('');
  }

  function renderSafety() {
    var drawer = qs('[data-canvas-safety-drawer]');
    if (!drawer) return;
    drawer.classList.toggle('is-open', state.safetyOpen);
    var body = qs('[data-canvas-safety-body]', drawer);
    var safety = state.data && state.data.safety ? state.data.safety : { score: 'checking', checks: [] };
    var checks = Array.isArray(safety.checks) ? safety.checks : [];
    if (body) body.innerHTML = '<section class="mg-safety-score"><strong>' + esc(String(safety.score || 'checking').replace(/_/g, ' ')) + '</strong><span>Store Canvas persistence and automation checks</span></section>' + checks.map(function (check) {
      return '<article class="is-' + esc(check.state || 'info') + '"><b>' + esc(check.label || check.key) + '</b><span>' + esc(check.detail || '') + '</span></article>';
    }).join('');
  }

  function addSimulatorTab() {
    var drawer = qs('.mg-trigger-control-drawer.is-open');
    if (!drawer) return;
    var tabs = qs('[data-trigger-control-tabs]', drawer);
    if (!tabs || tabs.querySelector('[data-intel-simulator-tab]')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('data-intel-simulator-tab', '');
    button.innerHTML = '<span>▻</span>Simulator';
    tabs.appendChild(button);
    button.addEventListener('click', function () {
      qsa('[data-trigger-tab]', tabs).forEach(function (tab) { tab.classList.remove('is-active'); });
      button.classList.add('is-active');
      renderSimulator(drawer);
    });
  }

  function selectedCustomerOptions() {
    var cards = qsa('.mg-canvas-avatar-card[data-session-id]', root);
    if (!cards.length) return '<option value="">No active customer</option>';
    return cards.map(function (card) { return '<option value="' + esc(card.dataset.sessionId || '') + '">' + esc(text(card.querySelector('strong')) || 'Customer') + '</option>'; }).join('');
  }

  function renderSimulator(drawer) {
    var body = qs('[data-trigger-settings-body]', drawer);
    if (!body) return;
    var title = text(qs('[data-trigger-settings-title]', drawer)) || 'Trigger Zone';
    var zoneId = '';
    qsa('[data-canvas-persistent-zone]', root).forEach(function (zone) {
      if (!zoneId && text(zone.querySelector('[data-zone-name]')) === title) zoneId = zone.dataset.canvasTriggerZone || '';
    });
    body.innerHTML = '<section class="mg-trigger-control-card mg-rule-simulator"><h3>Test Rules Simulator</h3><p>Preview the real automation decision and save the simulation run.</p><label>Test customer<select data-sim-customer>' + selectedCustomerOptions() + '</select></label><label>Trigger event<select data-sim-event><option value="enter">Customer enters zone</option><option value="repeat">Customer remains in zone</option><option value="return">Customer leaves and returns</option><option value="manual">Manual test</option></select></label><button type="button" data-run-rule-sim>Run Simulation</button><div data-rule-sim-result></div></section>';
    qs('[data-run-rule-sim]', body).addEventListener('click', function () {
      var result = qs('[data-rule-sim-result]', body);
      result.innerHTML = '<article><strong>Running simulation...</strong></article>';
      MG.post('/api/merchant-canvas/rule-simulator.php', { trigger_zone_id: zoneId, session_id: qs('[data-sim-customer]', body).value, simulation_event: qs('[data-sim-event]', body).value }).then(function (response) {
        var data = payload(response) || {};
        var sim = data.simulation || data;
        result.innerHTML = '<article class="' + (sim.would_block_cooldown ? 'is-blocked' : 'is-send') + '"><strong>' + esc(sim.label || 'Simulation complete') + '</strong><span>Action: ' + esc(sim.automation_action || '') + '</span><span>Cooldown: ' + esc(sim.cooldown_policy || '') + '</span><span>Message: ' + esc(sim.message_preview || 'No message') + '</span><span>' + esc(sim.next_step || '') + '</span></article>';
        loadIntel();
      }).catch(function (error) {
        result.innerHTML = '<article class="is-blocked"><strong>' + esc(error.message || 'Simulation failed') + '</strong></article>';
      });
    });
  }

  function render() {
    ensureChrome();
    setMode(state.mode);
    decorateZones();
    decorateCustomers();
    renderActivity();
    renderSafety();
    addSimulatorTab();
    var path = qs('[data-canvas-path-layer]', map);
    if (path) path.classList.toggle('is-visible', state.mode === 'paths');
  }

  function queueRender() {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(function () { queued = false; render(); });
  }

  document.addEventListener('click', function (event) {
    var modeButton = event.target.closest('[data-canvas-mode]');
    if (modeButton && root.contains(modeButton)) { state.mode = modeButton.dataset.canvasMode || 'live'; render(); saveSettings(); return; }
    if (event.target.closest('[data-canvas-open-activity]')) { state.activityOpen = true; state.safetyOpen = false; render(); saveSettings(); return; }
    if (event.target.closest('[data-canvas-open-safety]')) { state.safetyOpen = true; state.activityOpen = false; render(); saveSettings(); return; }
    if (event.target.closest('[data-canvas-close-activity]')) { state.activityOpen = false; render(); saveSettings(); return; }
    if (event.target.closest('[data-canvas-close-safety]')) { state.safetyOpen = false; render(); saveSettings(); return; }
  });

  new MutationObserver(queueRender).observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'data-canvas-trigger-zone'] });
  loadIntel();
  window.setInterval(loadIntel, 15000);
})(window, document);
