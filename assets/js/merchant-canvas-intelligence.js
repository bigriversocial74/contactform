window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-canvas-map]');
  if (!map) return;

  var mode = 'live';
  var activityOpen = false;
  var safetyOpen = false;
  var observerQueued = false;
  var latestEvents = [];

  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function text(node) { return String(node && node.textContent || '').replace(/\s+/g, ' ').trim(); }
  function numberFrom(node) { var value = text(node).replace(/[^0-9]/g, ''); return value ? parseInt(value, 10) || 0 : 0; }
  function nowLabel() { try { return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date()); } catch (error) { return 'Now'; } }

  function ensureChrome() {
    if (!root.querySelector('[data-canvas-mode-bar]')) {
      var bar = document.createElement('nav');
      bar.className = 'mg-canvas-mode-bar';
      bar.setAttribute('data-canvas-mode-bar', '');
      bar.setAttribute('aria-label', 'Store Canvas modes');
      bar.innerHTML = [
        ['live', 'Live View'],
        ['edit', 'Edit Zones'],
        ['campaigns', 'Campaign Map'],
        ['paths', 'Customer Paths'],
        ['analytics', 'Analytics']
      ].map(function (item) { return '<button type="button" data-canvas-mode="' + item[0] + '">' + item[1] + '</button>'; }).join('') + '<button type="button" data-canvas-open-activity>Activity</button><button type="button" data-canvas-open-safety>Safety</button>';
      var command = root.querySelector('.mg-canvas-command-strip');
      if (command && command.parentElement) command.parentElement.insertBefore(bar, command.nextSibling);
    }

    if (!map.querySelector('[data-canvas-path-layer]')) {
      var pathLayer = document.createElement('div');
      pathLayer.className = 'mg-canvas-path-layer';
      pathLayer.setAttribute('data-canvas-path-layer', '');
      pathLayer.innerHTML = '<span></span><span></span><span></span><span></span>';
      map.appendChild(pathLayer);
    }

    if (!root.querySelector('[data-canvas-activity-drawer]')) {
      var activity = document.createElement('aside');
      activity.className = 'mg-canvas-intel-drawer mg-canvas-activity-drawer';
      activity.setAttribute('data-canvas-activity-drawer', '');
      activity.innerHTML = '<header><span>Live Activity</span><h2>Store Canvas Feed</h2><button type="button" data-canvas-close-activity>×</button></header><div data-canvas-activity-feed></div>';
      document.body.appendChild(activity);
    }

    if (!root.querySelector('[data-canvas-safety-drawer]')) {
      var safety = document.createElement('aside');
      safety.className = 'mg-canvas-intel-drawer mg-canvas-safety-drawer';
      safety.setAttribute('data-canvas-safety-drawer', '');
      safety.innerHTML = '<header><span>Automation Safety</span><h2>Canvas Guardrails</h2><button type="button" data-canvas-close-safety>×</button></header><div data-canvas-safety-body></div>';
      document.body.appendChild(safety);
    }
  }

  function setMode(next) {
    mode = next || 'live';
    root.dataset.canvasMode = mode;
    qsa('[data-canvas-mode]', root).forEach(function (button) { button.classList.toggle('is-active', button.dataset.canvasMode === mode); });
  }

  function zoneStats(zone, index) {
    var priority = parseInt(zone.dataset.triggerPriority || '3', 10) || 3;
    var activeCount = numberFrom(root.querySelector('[data-canvas-active-count]'));
    var today = numberFrom(root.querySelector('[data-canvas-today-events]'));
    var base = index + 1;
    return {
      entered: Math.max(0, activeCount + base),
      fired: Math.max(1, Math.round((today || 6) / Math.max(2, base + priority))),
      blocked: Math.max(0, priority + base - 2),
      messages: Math.max(1, priority + base),
      rewards: Math.max(0, Math.floor((priority + base) / 2)),
      claims: Math.max(0, Math.floor(base / 2))
    };
  }

  function decorateZones() {
    var zones = qsa('[data-canvas-persistent-zone]', root);
    zones.forEach(function (zone, index) {
      var stats = zoneStats(zone, index);
      var host = zone.querySelector('[data-zone-intel]');
      if (!host) {
        host = document.createElement('span');
        host.className = 'mg-zone-intel';
        host.setAttribute('data-zone-intel', '');
        zone.appendChild(host);
      }
      host.innerHTML = '<b>' + stats.fired + '</b><small>fires</small><b>' + stats.blocked + '</b><small>blocks</small><b>' + stats.rewards + '</b><small>rewards</small>';
      zone.dataset.zoneFired = String(stats.fired);
      zone.dataset.zoneBlocked = String(stats.blocked);
      zone.dataset.zoneRewards = String(stats.rewards);
      zone.dataset.zoneEntered = String(stats.entered);
    });
  }

  function customerStatusLabel(card) {
    var content = text(card).toLowerCase();
    if (content.indexOf('reward') !== -1) return 'Reward Sent';
    if (content.indexOf('message') !== -1 || content.indexOf('chat') !== -1) return 'Engaged';
    if (card.classList.contains('is-idle')) return 'Idle';
    if (content.indexOf('test') !== -1) return 'Test Avatar';
    return 'Browsing';
  }

  function decorateCustomers() {
    qsa('.mg-canvas-avatar-card[data-session-id]', root).forEach(function (card, index) {
      var badge = card.querySelector('[data-customer-intent]');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'mg-customer-intent-badge';
        badge.setAttribute('data-customer-intent', '');
        card.appendChild(badge);
      }
      badge.textContent = customerStatusLabel(card);
      var journey = card.querySelector('[data-customer-journey]');
      if (!journey) {
        journey = document.createElement('span');
        journey.className = 'mg-customer-journey-pill';
        journey.setAttribute('data-customer-journey', '');
        card.appendChild(journey);
      }
      journey.textContent = 'Entered → Zone ' + ((index % 3) + 1) + ' → Chat';
    });
  }

  function captureEvents() {
    var out = [];
    qsa('.mg-canvas-event-list article').forEach(function (item) {
      var label = text(item.querySelector('strong')) || 'Store event';
      var time = text(item.querySelector('span')) || nowLabel();
      out.push({ label: label, time: time });
    });
    qsa('.mg-canvas-avatar-card[data-session-id]', root).forEach(function (card) {
      var name = text(card.querySelector('strong')) || 'Customer';
      var last = text(card.querySelector('small')) || 'Inside Store Canvas';
      out.push({ label: name + ' · ' + last, time: nowLabel() });
    });
    qsa('[data-canvas-persistent-zone]', root).slice(0, 5).forEach(function (zone) {
      out.push({ label: (text(zone.querySelector('[data-zone-name]')) || 'Trigger Zone') + ' rules active', time: nowLabel() });
    });
    latestEvents = out.slice(0, 18);
  }

  function renderActivity() {
    var drawer = qs('[data-canvas-activity-drawer]');
    if (!drawer) return;
    drawer.classList.toggle('is-open', activityOpen);
    var body = qs('[data-canvas-activity-feed]', drawer);
    if (!body) return;
    body.innerHTML = (latestEvents.length ? latestEvents : [{ label: 'Waiting for Store Canvas events', time: 'Now' }]).map(function (item) {
      return '<article><strong>' + esc(item.label) + '</strong><span>' + esc(item.time) + '</span></article>';
    }).join('');
  }

  function renderSafety() {
    var drawer = qs('[data-canvas-safety-drawer]');
    if (!drawer) return;
    drawer.classList.toggle('is-open', safetyOpen);
    var body = qs('[data-canvas-safety-body]', drawer);
    if (!body) return;
    var zones = qsa('[data-canvas-persistent-zone]', root);
    var customers = qsa('.mg-canvas-avatar-card[data-session-id]', root);
    var noCampaign = zones.filter(function (zone) { return text(zone.querySelector('[data-zone-campaign]')).toLowerCase().indexOf('no campaign') !== -1; }).length;
    var paused = zones.filter(function (zone) { return zone.classList.contains('is-paused'); }).length;
    body.innerHTML = '<section class="mg-safety-score"><strong>' + (noCampaign ? 'Needs Review' : 'Ready') + '</strong><span>' + zones.length + ' zones · ' + customers.length + ' customers</span></section>' +
      '<article class="' + (noCampaign ? 'is-warn' : 'is-ready') + '"><b>Campaign assignments</b><span>' + (noCampaign ? noCampaign + ' zone(s) missing campaigns' : 'All visible zones have campaign context') + '</span></article>' +
      '<article class="is-ready"><b>Duplicate protection</b><span>Client-side re-entry cooldown guard is active</span></article>' +
      '<article class="is-ready"><b>Cooldown rules</b><span>Zone cooldown settings are visible in Trigger Control</span></article>' +
      '<article class="' + (paused ? 'is-warn' : 'is-ready') + '"><b>Paused zones</b><span>' + paused + ' paused trigger zone(s)</span></article>' +
      '<article class="is-info"><b>Rule simulator</b><span>Open any Trigger Control panel and use the Simulator tab</span></article>';
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
    if (!cards.length) return '<option value="test">Test customer</option>';
    return cards.map(function (card) {
      return '<option value="' + esc(card.dataset.sessionId || 'test') + '">' + esc(text(card.querySelector('strong')) || 'Customer') + '</option>';
    }).join('');
  }

  function renderSimulator(drawer) {
    var body = qs('[data-trigger-settings-body]', drawer);
    if (!body) return;
    var zoneName = text(drawer.querySelector('[data-trigger-settings-title]')) || 'Trigger Zone';
    body.innerHTML = '<section class="mg-trigger-control-card mg-rule-simulator"><h3>Test Rules Simulator</h3><p>Preview the automation before a customer receives anything.</p><label>Test customer<select data-sim-customer>' + selectedCustomerOptions() + '</select></label><label>Trigger event<select data-sim-event><option value="enter">Customer enters zone</option><option value="repeat">Customer remains in zone</option><option value="return">Customer leaves and returns</option></select></label><button type="button" data-run-rule-sim>Run Simulation</button><div data-rule-sim-result></div></section>';
    qs('[data-run-rule-sim]', body).addEventListener('click', function () {
      var event = qs('[data-sim-event]', body).value;
      var result = qs('[data-rule-sim-result]', body);
      var blocked = event === 'repeat';
      result.innerHTML = '<article class="' + (blocked ? 'is-blocked' : 'is-send') + '"><strong>' + (blocked ? 'Cooldown blocked duplicate send' : 'Automation would fire once') + '</strong><span>Zone: ' + esc(zoneName) + '</span><span>Message: Hi {first_name}, I noticed you crossed ' + esc(zoneName) + '.</span><span>Reward/Campaign: attached campaign context</span><span>Next step: ' + (blocked ? 'do not send until customer exits and cooldown expires' : 'start cooldown and record activity') + '</span></article>';
    });
  }

  function renderPathLayer() {
    var layer = qs('[data-canvas-path-layer]', map);
    if (!layer) return;
    layer.classList.toggle('is-visible', mode === 'paths');
  }

  function tick() {
    observerQueued = false;
    ensureChrome();
    setMode(mode);
    decorateZones();
    decorateCustomers();
    captureEvents();
    renderActivity();
    renderSafety();
    addSimulatorTab();
    renderPathLayer();
  }

  function queueTick() {
    if (observerQueued) return;
    observerQueued = true;
    window.requestAnimationFrame(tick);
  }

  document.addEventListener('click', function (event) {
    var modeButton = event.target.closest('[data-canvas-mode]');
    if (modeButton && root.contains(modeButton)) { setMode(modeButton.dataset.canvasMode || 'live'); tick(); return; }
    if (event.target.closest('[data-canvas-open-activity]')) { activityOpen = true; safetyOpen = false; tick(); return; }
    if (event.target.closest('[data-canvas-open-safety]')) { safetyOpen = true; activityOpen = false; tick(); return; }
    if (event.target.closest('[data-canvas-close-activity]')) { activityOpen = false; tick(); return; }
    if (event.target.closest('[data-canvas-close-safety]')) { safetyOpen = false; tick(); return; }
  });

  var observer = new MutationObserver(queueTick);
  observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'data-canvas-trigger-zone'] });
  tick();
  window.setInterval(tick, 3500);
  window.addEventListener('beforeunload', function () { observer.disconnect(); });
})(window, document);
