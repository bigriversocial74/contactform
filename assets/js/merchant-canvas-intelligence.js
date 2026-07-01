window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get || !MG.post) return;

  var state = { data: null, loading: false };

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]; }); }
  function text(node) { return String(node && node.textContent || '').replace(/\s+/g, ' ').trim(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }

  function removeMiddleRow() {
    qsa('[data-canvas-mode-bar], .mg-canvas-mode-bar, .mg-canvas-command-strip', root).forEach(function (node) { node.remove(); });
  }

  function selectedCustomerOptions() {
    var cards = qsa('.mg-canvas-avatar-card[data-session-id]', root);
    if (!cards.length) return '<option value="">No active customer</option>';
    return cards.map(function (card) { return '<option value="' + esc(card.dataset.sessionId || '') + '">' + esc(text(card.querySelector('strong')) || 'Customer') + '</option>'; }).join('');
  }

  function eventsForSession(sessionId) {
    var journeys = state.data && Array.isArray(state.data.journeys) ? state.data.journeys : [];
    var journey = journeys.find(function (item) { return String(item.session_id || '') === String(sessionId || ''); });
    if (journey && Array.isArray(journey.events)) return journey.events;
    var activity = state.data && Array.isArray(state.data.activity) ? state.data.activity : [];
    return activity.filter(function (event) { return String(event.session_id || '') === String(sessionId || ''); });
  }

  function pathForSession(sessionId) {
    var events = eventsForSession(sessionId);
    if (!events.length) return [{ stage: 'Entered', label: 'Customer entered the Store Canvas.', time: 'Current session' }];
    return events.map(function (event) {
      var type = String(event.type || 'event');
      var stage = 'Event';
      if (type.indexOf('entered') !== -1) stage = 'Entered';
      else if (type.indexOf('trigger') !== -1) stage = 'Trigger Zone';
      else if (type.indexOf('message') !== -1) stage = 'Chat';
      else if (type.indexOf('reward') !== -1) stage = 'Reward';
      else if (type.indexOf('claim') !== -1) stage = 'Claim';
      else if (type.indexOf('exit') !== -1) stage = 'Exit';
      else if (type.indexOf('idle') !== -1) stage = 'Idle';
      return { stage: stage, label: event.label || event.event_label || type, time: event.created_at || event.time || '', metadata: event.metadata || {} };
    });
  }

  function scoreForSession(sessionId) {
    var path = pathForSession(sessionId);
    var score = 20;
    path.forEach(function (step) {
      var stage = String(step.stage || '').toLowerCase();
      if (stage.indexOf('trigger') !== -1) score += 18;
      if (stage.indexOf('chat') !== -1) score += 20;
      if (stage.indexOf('reward') !== -1) score += 18;
      if (stage.indexOf('claim') !== -1) score += 22;
      if (stage.indexOf('idle') !== -1) score -= 8;
      if (stage.indexOf('exit') !== -1) score -= 6;
    });
    score = Math.max(0, Math.min(100, score));
    return {
      score: score,
      label: score >= 75 ? 'High intent' : (score >= 45 ? 'Engaged' : 'Watching'),
      why: score >= 75 ? 'Customer crossed high-value actions and is likely ready for a direct offer.' : (score >= 45 ? 'Customer has meaningful activity but needs a stronger next action.' : 'Customer has light movement data so far.')
    };
  }

  async function loadIntel() {
    if (state.loading) return;
    state.loading = true;
    try {
      state.data = payload(await MG.get('/api/merchant-canvas/intelligence.php')) || {};
      document.dispatchEvent(new CustomEvent('mg:storeCanvasIntelligenceLoaded', { detail: state.data }));
    } catch (error) {
      toast(error.message || 'Unable to load Store Canvas intelligence.', 'error');
    } finally {
      state.loading = false;
      removeMiddleRow();
      addMerchantAnalyticsTab();
    }
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

  function merchantAnalytics() {
    var data = state.data || {};
    var activity = Array.isArray(data.activity) ? data.activity : [];
    var journeys = Array.isArray(data.journeys) ? data.journeys : [];
    var zones = Array.isArray(data.zone_metrics) ? data.zone_metrics : [];
    var fires = zones.reduce(function (total, zone) { return total + Number(zone.today && zone.today.fires || 0); }, 0);
    var rewards = zones.reduce(function (total, zone) { return total + Number(zone.today && zone.today.rewards_sent || 0); }, 0);
    var messages = zones.reduce(function (total, zone) { return total + Number(zone.today && zone.today.messages_sent || 0); }, 0);
    return { activity: activity, journeys: journeys, zones: zones, fires: fires, rewards: rewards, messages: messages };
  }

  function addMerchantAnalyticsTab() {
    var drawer = qs('.mg-merchant-control-drawer.is-open');
    if (!drawer) return;
    var tabs = qs('[data-merchant-control-tabs]', drawer);
    if (!tabs) return;
    var existing = tabs.querySelector('[data-merchant-analytics-tab]');
    if (!existing) {
      existing = document.createElement('button');
      existing.type = 'button';
      existing.setAttribute('data-merchant-analytics-tab', '');
      existing.textContent = 'Analytics';
      tabs.appendChild(existing);
    }
  }

  function renderMerchantAnalytics(drawer) {
    var body = qs('[data-merchant-settings-body]', drawer);
    var tabs = qs('[data-merchant-control-tabs]', drawer);
    if (!body || !tabs) return;
    qsa('button', tabs).forEach(function (button) { button.classList.remove('is-active'); });
    var tab = tabs.querySelector('[data-merchant-analytics-tab]');
    if (tab) tab.classList.add('is-active');
    var stats = merchantAnalytics();
    body.innerHTML = '<section class="mg-merchant-analytics-panel">' +
      '<header><span>Merchant Analytics</span><h3>Avatar Performance</h3><p>Store Canvas activity for the merchant avatar, trigger engagement, and customer movement.</p></header>' +
      '<div class="mg-merchant-analytics-grid">' +
        '<article><span>Active Journeys</span><strong>' + esc(stats.journeys.length) + '</strong><small>customer paths</small></article>' +
        '<article><span>Trigger Fires</span><strong>' + esc(stats.fires) + '</strong><small>today</small></article>' +
        '<article><span>Messages</span><strong>' + esc(stats.messages) + '</strong><small>sent by automation</small></article>' +
        '<article><span>Rewards</span><strong>' + esc(stats.rewards) + '</strong><small>delivered</small></article>' +
      '</div>' +
      '<section class="mg-merchant-analytics-card"><h4>Recent Store Canvas Activity</h4>' + (stats.activity.length ? stats.activity.slice(0, 8).map(function (event) { return '<article><strong>' + esc(event.label || event.type || 'Canvas event') + '</strong><span>' + esc(event.created_at || '') + '</span></article>'; }).join('') : '<article><strong>No activity yet</strong><span>Merchant analytics will populate as customers enter, trigger zones fire, and messages/rewards are sent.</span></article>') + '</section>' +
      '<section class="mg-merchant-analytics-card"><h4>What to watch</h4><article><strong>Conversion movement</strong><span>Compare trigger fires against messages, rewards, and claims.</span></article><article><strong>Customer score drivers</strong><span>Use each Customer CRM Movement tab for per-customer scoring and action results.</span></article></section>' +
    '</section>';
  }

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-merchant-analytics-tab]');
    if (!tab) return;
    event.preventDefault();
    event.stopPropagation();
    var drawer = tab.closest('.mg-merchant-control-drawer');
    if (drawer) renderMerchantAnalytics(drawer);
  }, true);

  window.Microgifter.storeCanvasIntelligence = {
    refresh: loadIntel,
    getData: function () { return state.data || {}; },
    getPath: pathForSession,
    getScore: scoreForSession
  };

  new MutationObserver(function () { window.requestAnimationFrame(function () { removeMiddleRow(); addSimulatorTab(); addMerchantAnalyticsTab(); }); }).observe(root, { childList: true, subtree: true });
  removeMiddleRow();
  loadIntel();
  addSimulatorTab();
  addMerchantAnalyticsTab();
  window.setInterval(loadIntel, 15000);
})(window, document);
